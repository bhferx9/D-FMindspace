<?php
include 'php/config.php';
session_start();

// Seguridad: Solo tutores (padres)
if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'padre') {
    header("Location: index.php");
    exit();
}

$id_padre = $_SESSION['user_id'];

// Obtener datos del padre desde la base de datos
$query_padre = "SELECT nombre FROM usuarios WHERE id = ? AND tipo = 'padre'";
$stmt_padre = $conn->prepare($query_padre);
$stmt_padre->bind_param("i", $id_padre);
$stmt_padre->execute();
$result_padre = $stmt_padre->get_result();
if ($row_padre = $result_padre->fetch_assoc()) {
    $nombre_padre = $row_padre['nombre'];
} else {
    // Si no se encuentra (raro), usar un fallback
    $nombre_padre = 'Padre';
}
$stmt_padre->close();

// Iniciales para avatar
$iniciales = '';
$partes = explode(' ', trim($nombre_padre));
if (count($partes) >= 2) {
    $iniciales = strtoupper(substr($partes[0], 0, 1) . substr($partes[1], 0, 1));
} else {
    $iniciales = strtoupper(substr($nombre_padre, 0, 2));
}

// Obtener hijos vinculados para el selector
$query_hijos = "
    SELECT u.id, u.nombre 
    FROM vinculaciones v
    JOIN usuarios u ON v.id_alumno = u.id
    WHERE v.id_padre = ? AND v.estado = 'activo' AND u.activo = 1
    ORDER BY u.nombre
";
$stmt_hijos = $conn->prepare($query_hijos);
$stmt_hijos->bind_param("i", $id_padre);
$stmt_hijos->execute();
$hijos = $stmt_hijos->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_hijos->close();

// Determinar hijo seleccionado (por GET o primer hijo)
$id_hijo_seleccionado = isset($_GET['hijo']) ? intval($_GET['hijo']) : (count($hijos) > 0 ? $hijos[0]['id'] : 0);
$nombre_hijo_seleccionado = '';
foreach ($hijos as $h) {
    if ($h['id'] == $id_hijo_seleccionado) {
        $nombre_hijo_seleccionado = $h['nombre'];
        break;
    }
}

// Si no hay hijos, mostrar mensaje
if (empty($hijos)) {
    $mensaje_sin_hijos = true;
} else {
    // Obtener estadísticas del hijo seleccionado
    $stats = [];
    
    // Progreso general (promedio de progreso por curso)
    $query_progreso = "
        SELECT AVG(porcentaje) AS promedio_global 
        FROM progreso 
        WHERE id_alumno = ?
    ";
    $stmt = $conn->prepare($query_progreso);
    $stmt->bind_param("i", $id_hijo_seleccionado);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $progreso_global = $res['promedio_global'] ? round($res['promedio_global']) : 0;
    $stmt->close();
    
    // Total cursos activos
    $query_cursos = "SELECT COUNT(*) AS total FROM inscripciones WHERE id_alumno = ? AND estado = 'activo'";
    $stmt = $conn->prepare($query_cursos);
    $stmt->bind_param("i", $id_hijo_seleccionado);
    $stmt->execute();
    $cursos_activos = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    // Promedio de calificaciones (de evaluaciones)
    $query_calif = "
        SELECT AVG(e.calificacion) AS promedio_calif
        FROM evaluaciones e
        JOIN entregas en ON e.id_entrega = en.id
        WHERE en.id_alumno = ?
    ";
    $stmt = $conn->prepare($query_calif);
    $stmt->bind_param("i", $id_hijo_seleccionado);
    $stmt->execute();
    $res_calif = $stmt->get_result()->fetch_assoc();
    $promedio_calif = $res_calif['promedio_calif'] ? round($res_calif['promedio_calif'], 1) : '--';
    $stmt->close();
    
    // Últimas actividades con estado
    $query_actividades = "
        SELECT a.titulo, c.nombre AS curso, en.estado, ev.calificacion, en.fecha_entrega
        FROM actividades a
        JOIN cursos c ON a.id_curso = c.id
        LEFT JOIN entregas en ON en.id_actividad = a.id AND en.id_alumno = ?
        LEFT JOIN evaluaciones ev ON ev.id_entrega = en.id
        ORDER BY en.fecha_entrega DESC
        LIMIT 5
    ";
    $stmt = $conn->prepare($query_actividades);
    $stmt->bind_param("i", $id_hijo_seleccionado);
    $stmt->execute();
    $actividades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes · D&F Mindspace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js para gráficos -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* === MISMO CSS DEL DASHBOARD (SIN CAMBIOS) === */
        :root {
            --primary: #2cbaec;
            --primary-dark: #1a9acc;
            --primary-soft: rgba(44, 186, 236, 0.08);
            --secondary: #f0ae2a;
            --secondary-soft: rgba(240, 174, 42, 0.1);
            --accent: #83bf46;
            --accent-soft: rgba(131, 191, 70, 0.1);
            --danger: #ff6b8b;
            --gray-50: #f9fbfd;
            --gray-100: #f1f5f9;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-700: #334155;
            --gray-900: #0f172a;
            --sidebar-width: 272px;
            --radius-card: 20px;
            --radius-pill: 100px;
            --shadow-sm: 0 2px 8px rgba(15, 23, 42, 0.06);
            --shadow-md: 0 8px 24px rgba(15, 23, 42, 0.08);
            --shadow-lg: 0 20px 48px rgba(44, 186, 236, 0.14);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DM Sans', sans-serif;
            background: #f4f8fc;
            color: var(--gray-700);
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        /* ── SIDEBAR ── */
        .sidebar {
            background: #ffffff;
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0; top: 0;
            z-index: 100;
            border-right: 1px solid rgba(44, 186, 236, 0.12);
            box-shadow: 2px 0 16px rgba(15, 23, 42, 0.05);
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease;
        }
 
        .sidebar-scroll {
            flex: 1;
            overflow-y: auto;
            padding: 0 0 16px;
            scrollbar-width: thin;
            scrollbar-color: rgba(44,186,236,0.15) transparent;
        }
 
        .sidebar-scroll::-webkit-scrollbar { width: 4px; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(44,186,236,0.2); border-radius: 4px; }
 
        /* Brand */
        .brand {
            padding: 28px 24px 22px;
            border-bottom: 1px solid rgba(44,186,236,0.1);
            margin-bottom: 8px;
        }
 
        .brand-mark {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 4px;
        }
 
        .brand-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
        }
 
        .brand-icon svg { width: 20px; height: 20px; fill: white; }
 
        .brand-name {
            font-family: 'Nunito', sans-serif;
            font-size: 1.2rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            letter-spacing: -0.3px;
        }
 
        .brand-tagline {
            font-size: 0.68rem;
            color: var(--gray-400);
            font-weight: 500;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            padding-left: 46px;
        }
 
        /* User pill */
        .user-pill {
            margin: 12px 16px 16px;
            background: var(--primary-soft);
            border-radius: 14px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
 
        .user-avatar {
            width: 38px; height: 38px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Nunito', sans-serif;
            font-weight: 800;
            font-size: 0.95rem;
            color: white;
            flex-shrink: 0;
        }
 
        .user-name {
            font-weight: 600;
            font-size: 0.88rem;
            color: var(--gray-700);
            line-height: 1.2;
        }
 
        .user-role {
            font-size: 0.72rem;
            color: var(--primary);
            font-weight: 500;
        }
 
        /* Nav */
        .nav-section {
            padding: 8px 12px 4px;
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 1.8px;
            text-transform: uppercase;
            color: var(--gray-400);
            margin-top: 8px;
        }
 
        .nav-item { margin: 2px 12px; }
 
        .nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 14px;
            border-radius: 12px;
            font-size: 0.88rem;
            font-weight: 500;
            color: var(--gray-500);
            transition: all 0.18s ease;
            text-decoration: none;
        }
 
        .nav-link:hover {
            background: var(--gray-100);
            color: var(--gray-700);
        }
 
        .nav-link.active {
            background: var(--primary-soft);
            color: var(--primary);
            font-weight: 600;
        }
 
        .nav-link .icon {
            width: 32px; height: 32px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem;
            flex-shrink: 0;
            background: transparent;
            transition: background 0.18s;
        }
 
        .nav-link.active .icon {
            background: rgba(44,186,236,0.15);
            color: var(--primary);
        }
 
        .nav-link:hover .icon {
            background: rgba(44,186,236,0.08);
        }
 
        /* Sidebar footer */
        .sidebar-foot {
            padding: 16px 24px;
            border-top: 1px solid rgba(44,186,236,0.08);
        }
 
        .logout-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--danger);
            text-decoration: none;
            transition: background 0.18s;
            cursor: pointer;
        }
 
        .logout-btn:hover { background: rgba(255,107,139,0.07); color: var(--danger); }
 
        /* ── MAIN ── */
        .main {
            margin-left: var(--sidebar-width);
            padding: 40px 48px;
            min-height: 100vh;
        }
 
        /* Page header */
        .page-header {
            margin-bottom: 36px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 16px;
        }
 
        .header-eyebrow {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--primary);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
 
        .header-eyebrow::before {
            content: '';
            display: inline-block;
            width: 18px; height: 2px;
            background: var(--primary);
            border-radius: 2px;
        }
 
        .page-title {
            font-family: 'Nunito', sans-serif;
            font-size: 2rem;
            font-weight: 900;
            color: var(--gray-900);
            letter-spacing: -0.5px;
            margin-bottom: 4px;
            line-height: 1.1;
        }
 
        .page-title span {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
 
        .page-sub {
            font-size: 0.9rem;
            color: var(--gray-400);
            font-weight: 400;
        }
 
        .header-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            background: white;
            border-radius: var(--radius-pill);
            padding: 10px 18px;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(44,186,236,0.12);
            font-size: 0.82rem;
            color: var(--gray-500);
            font-weight: 500;
        }
 
        .header-meta .dot {
            width: 7px; height: 7px;
            background: var(--accent);
            border-radius: 50%;
            flex-shrink: 0;
            box-shadow: 0 0 0 3px var(--accent-soft);
        }
 
        /* ── CHILD CARDS ── */
        .children-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
 
        .child-card {
            background: white;
            border-radius: var(--radius-card);
            border: 1.5px solid rgba(44,186,236,0.1);
            padding: 28px 24px 24px;
            cursor: pointer;
            transition: all 0.24s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
 
        .child-card::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: var(--radius-card);
            border: 1.5px solid var(--primary);
            opacity: 0;
            transition: opacity 0.24s;
            pointer-events: none;
        }
 
        .child-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
 
        .child-card:hover::after { opacity: 1; }
 
        /* Accent strip at top */
        .card-strip {
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            border-radius: var(--radius-card) var(--radius-card) 0 0;
        }
 
        .strip-blue  { background: linear-gradient(90deg, var(--primary), #6fd3f7); }
        .strip-amber { background: linear-gradient(90deg, var(--secondary), #f7cc6f); }
        .strip-green { background: linear-gradient(90deg, var(--accent), #b2d96f); }
 
        .card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 20px;
        }
 
        .child-emoji {
            width: 68px; height: 68px;
            border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.4rem;
            background: var(--gray-100);
            transition: transform 0.24s;
        }
 
        .child-card:hover .child-emoji { transform: scale(1.06); }
 
        .progress-ring-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
        }
 
        .progress-ring { transform: rotate(-90deg); }
 
        .ring-bg { fill: none; stroke: var(--gray-100); stroke-width: 4; }
        .ring-fill { fill: none; stroke-width: 4; stroke-linecap: round; transition: stroke-dashoffset 1s ease; }
        .ring-blue  { stroke: var(--primary); }
        .ring-amber { stroke: var(--secondary); }
 
        .ring-label {
            font-family: 'Nunito', sans-serif;
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
 
        .child-name {
            font-family: 'Nunito', sans-serif;
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 4px;
        }
 
        .child-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
        }
 
        .meta-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.76rem;
            font-weight: 500;
            color: var(--gray-500);
        }
 
        .meta-chip i { font-size: 0.7rem; color: var(--gray-400); }
 
        /* Stats row */
        .card-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 20px;
        }
 
        .stat-chip {
            background: var(--gray-50);
            border-radius: 10px;
            padding: 8px 6px;
            text-align: center;
            border: 1px solid rgba(44,186,236,0.07);
        }
 
        .stat-chip-val {
            font-family: 'Nunito', sans-serif;
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--gray-900);
            line-height: 1;
            margin-bottom: 2px;
        }
 
        .stat-chip-lbl {
            font-size: 0.65rem;
            color: var(--gray-400);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
 
        .card-cta {
            width: 100%;
            border-radius: var(--radius-pill);
            padding: 11px;
            font-size: 0.85rem;
            font-weight: 700;
            border: 1.5px solid var(--primary);
            color: var(--primary);
            background: transparent;
            transition: all 0.2s;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
        }
 
        .card-cta:hover {
            background: var(--primary);
            color: white;
        }
 
        /* Add child card */
        .add-card {
            background: white;
            border-radius: var(--radius-card);
            border: 1.5px dashed rgba(44,186,236,0.25);
            padding: 28px 24px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 280px;
            cursor: pointer;
            transition: all 0.24s;
            text-align: center;
            gap: 12px;
        }
 
        .add-card:hover {
            border-color: var(--primary);
            background: var(--primary-soft);
            transform: translateY(-3px);
        }
 
        .add-icon {
            width: 56px; height: 56px;
            border-radius: 16px;
            background: var(--primary-soft);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
            color: var(--primary);
            transition: all 0.2s;
        }
 
        .add-card:hover .add-icon {
            background: rgba(44,186,236,0.15);
            transform: scale(1.06);
        }
 
        .add-title {
            font-family: 'Nunito', sans-serif;
            font-size: 1rem;
            font-weight: 800;
            color: var(--gray-700);
        }
 
        .add-sub {
            font-size: 0.8rem;
            color: var(--gray-400);
            max-width: 180px;
        }
 
        /* ── TIPS PANEL ── */
        .tips-panel {
            background: white;
            border-radius: var(--radius-card);
            border: 1px solid rgba(44,186,236,0.1);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
 
        .tips-header {
            padding: 18px 24px;
            border-bottom: 1px solid rgba(44,186,236,0.08);
            display: flex;
            align-items: center;
            gap: 10px;
        }
 
        .tips-header-icon {
            width: 32px; height: 32px;
            border-radius: 9px;
            background: var(--secondary-soft);
            display: flex; align-items: center; justify-content: center;
            color: var(--secondary);
            font-size: 0.9rem;
        }
 
        .tips-header-title {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--gray-700);
        }
 
        .tips-header-sub {
            font-size: 0.75rem;
            color: var(--gray-400);
            font-weight: 400;
        }
 
        .tips-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            padding: 4px 8px 12px;
        }
 
        .tip-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 12px;
            transition: background 0.18s;
        }
 
        .tip-item:hover { background: var(--gray-50); }
 
        .tip-icon {
            width: 34px; height: 34px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem;
            flex-shrink: 0;
            margin-top: 1px;
        }
 
        .tip-text strong {
            display: block;
            font-size: 0.83rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 2px;
        }
 
        .tip-text span {
            font-size: 0.74rem;
            color: var(--gray-400);
            line-height: 1.4;
        }
 
        /* ── MENU TOGGLE ── */
        .menu-toggle {
            display: none;
            position: fixed;
            top: 18px; left: 18px;
            z-index: 200;
            background: white;
            border: 1px solid rgba(44,186,236,0.2);
            color: var(--primary);
            width: 44px; height: 44px;
            border-radius: 12px;
            font-size: 1.1rem;
            box-shadow: var(--shadow-sm);
            cursor: pointer;
        }
 
        /* ── MODAL ── */
        .modal-content {
            border: none;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 32px 64px rgba(15,23,42,0.15);
        }
 
        .modal-header {
            background: white;
            padding: 24px 28px 20px;
            border-bottom: 1px solid rgba(44,186,236,0.12);
        }
 
        .modal-body { padding: 24px 28px; }
        .modal-footer { padding: 16px 28px 24px; border-top: 1px solid rgba(44,186,236,0.08); }
 
        .form-label {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--gray-500);
            margin-bottom: 8px;
        }
 
        .form-control {
            border-radius: 12px;
            border: 1.5px solid rgba(44,186,236,0.2);
            padding: 11px 16px;
            font-size: 0.9rem;
            font-family: 'DM Sans', sans-serif;
        }
 
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(44,186,236,0.12);
        }
 
        /* ── Toast ── */
        .toast-container { z-index: 9999; }
 
        .toast {
            border: none;
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(15,23,42,0.12);
            min-width: 280px;
        }
 
        /* ── ANIMATIONS ── */
        .fade-up {
            opacity: 0;
            transform: translateY(16px);
            animation: fadeUp 0.5s forwards;
        }
 
        @keyframes fadeUp {
            to { opacity: 1; transform: none; }
        }
 
        /* ── RESPONSIVE ── */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main { margin-left: 0; padding: 24px 20px; }
            .menu-toggle { display: flex; align-items: center; justify-content: center; }
            .page-header { margin-top: 56px; }
        }
 
        @media (max-width: 600px) {
            .page-title { font-size: 1.6rem; }
            .children-grid { grid-template-columns: 1fr; }
            .tips-grid { grid-template-columns: 1fr; }
        }

        .selector-hijo {
            background: white;
            border-radius: 16px;
            padding: 12px 20px;
            margin-bottom: 28px;
            border: 1px solid rgba(44,186,236,0.15);
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .selector-hijo label {
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.9rem;
        }
        .selector-hijo select {
            border: 1.5px solid rgba(44,186,236,0.25);
            border-radius: 30px;
            padding: 8px 20px;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--gray-700);
            background: white;
            cursor: pointer;
            min-width: 220px;
        }
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 18px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 18px;
            padding: 20px 18px;
            border: 1px solid rgba(44,186,236,0.1);
            box-shadow: var(--shadow-sm);
        }
        .stat-card .label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--gray-400);
            margin-bottom: 8px;
        }
        .stat-card .value {
            font-family: 'Nunito', sans-serif;
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--gray-900);
            line-height: 1;
        }
        .stat-card .unit {
            font-size: 0.9rem;
            color: var(--gray-500);
            margin-left: 4px;
        }
        .chart-container {
            background: white;
            border-radius: 20px;
            padding: 22px;
            margin-bottom: 30px;
            border: 1px solid rgba(44,186,236,0.1);
            box-shadow: var(--shadow-sm);
        }
        .chart-title {
            font-family: 'Nunito', sans-serif;
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 16px;
            color: var(--gray-700);
        }
        .table-actividades {
            background: white;
            border-radius: 20px;
            padding: 8px 0;
            border: 1px solid rgba(44,186,236,0.1);
            box-shadow: var(--shadow-sm);
            overflow-x: auto;
        }
        .table-actividades table {
            width: 100%;
            border-collapse: collapse;
        }
        .table-actividades th {
            text-align: left;
            padding: 16px 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--gray-400);
            border-bottom: 1px solid rgba(44,186,236,0.1);
        }
        .table-actividades td {
            padding: 14px 20px;
            border-bottom: 1px solid rgba(44,186,236,0.05);
            font-size: 0.9rem;
        }
        .badge-estado {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-pendiente { background: rgba(240,174,42,0.12); color: var(--secondary); }
        .badge-calificado { background: rgba(131,191,70,0.12); color: var(--accent); }
        .btn-pdf {
            background: var(--danger);
            color: white;
            border: none;
            border-radius: 30px;
            padding: 10px 22px;
            font-weight: 600;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .btn-pdf:hover {
            background: #e05577;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>

    <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>

    <!-- SIDEBAR (exactamente igual que en dashboard_padre.php) -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-scroll">
            <div class="brand">
                <div class="brand-mark">
                    <div class="brand-icon">
                        <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"/></svg>
                    </div>
                    <div class="brand-name">D&F Mindspace</div>
                </div>
                <div class="brand-tagline">familia conectada</div>
            </div>

            <div class="user-pill">
                <div class="user-avatar"><?= htmlspecialchars($iniciales) ?></div>
                <div>
                    <div class="user-name"><?= htmlspecialchars($nombre_padre) ?></div>
                    <div class="user-role">Cuenta familiar</div>
                </div>
            </div>

            <div class="nav-section">Principal</div>
            <div class="nav-item">
                <a href="dashboard_padre.php" class="nav-link">
                    <div class="icon"><i class="fas fa-users"></i></div>
                    <span>Mis hijos</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="reportes.php" class="nav-link active">
                    <div class="icon"><i class="fas fa-file-lines"></i></div>
                    <span>Reportes</span>
                </a>
            </div>
            <div class="nav-section" style="margin-top: 16px;">Cuenta</div>
            <div class="nav-item">
                <a href="suscripcion.php" class="nav-link">
                    <div class="icon"><i class="fas fa-gem"></i></div>
                    <span>Suscripción</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="configuracion.php" class="nav-link">
                    <div class="icon"><i class="fas fa-sliders"></i></div>
                    <span>Configuración</span>
                </a>
            </div>
        </div>
        <div class="sidebar-foot">
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-arrow-right-from-bracket"></i>
                Cerrar sesión
            </a>
        </div>
    </nav>

    <!-- MAIN -->
    <main class="main">

        <div class="page-header">
            <div>
                <div class="header-eyebrow">Análisis detallado</div>
                <h1 class="page-title">Reportes de<br><span>progreso</span></h1>
                <p class="page-sub">Visualiza el avance académico de tus hijos</p>
            </div>
            <div>
                <button class="btn-pdf" onclick="generarPDF()">
                    <i class="fas fa-file-pdf"></i> Descargar informe PDF
                </button>
            </div>
        </div>

        <?php if (!empty($mensaje_sin_hijos)): ?>
            <div class="alert alert-info" style="border-radius: 16px; background: white; border:1px solid rgba(44,186,236,0.2);">
                <i class="fas fa-info-circle me-2"></i> No tienes hijos vinculados. Ve a <a href="dashboard_padre.php">Mis hijos</a> para agregar uno.
            </div>
        <?php else: ?>
            <!-- Selector de hijo -->
            <div class="selector-hijo">
                <label><i class="fas fa-child me-2"></i>Ver reporte de:</label>
                <select id="selectHijo" onchange="cambiarHijo(this.value)">
                    <?php foreach ($hijos as $h): ?>
                        <option value="<?= $h['id'] ?>" <?= $h['id'] == $id_hijo_seleccionado ? 'selected' : '' ?>>
                            <?= htmlspecialchars($h['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Tarjetas de estadísticas -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="label">Progreso general</div>
                    <div class="value"><?= $progreso_global ?>%</div>
                </div>
                <div class="stat-card">
                    <div class="label">Cursos activos</div>
                    <div class="value"><?= $cursos_activos ?></div>
                </div>
                <div class="stat-card">
                    <div class="label">Promedio calificaciones</div>
                    <div class="value"><?= $promedio_calif ?><span class="unit">/10</span></div>
                </div>
            </div>

            <!-- Gráfico de progreso semanal (simulado) -->
            <div class="chart-container">
                <div class="chart-title"><i class="fas fa-chart-line me-2" style="color: var(--primary);"></i>Progreso semanal</div>
                <canvas id="progresoChart" style="max-height: 220px; width: 100%;"></canvas>
            </div>

            <!-- Tabla de últimas actividades -->
            <div class="table-actividades">
                <table>
                    <thead>
                        <tr>
                            <th>Actividad</th>
                            <th>Curso</th>
                            <th>Estado</th>
                            <th>Calificación</th>
                            <th>Fecha entrega</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($actividades)): ?>
                            <?php foreach ($actividades as $act): ?>
                                <tr>
                                    <td><?= htmlspecialchars($act['titulo']) ?></td>
                                    <td><?= htmlspecialchars($act['curso']) ?></td>
                                    <td>
                                        <span class="badge-estado <?= $act['estado'] == 'calificado' ? 'badge-calificado' : 'badge-pendiente' ?>">
                                            <?= ucfirst($act['estado'] ?? 'pendiente') ?>
                                        </span>
                                    </td>
                                    <td><?= $act['calificacion'] ?? '--' ?></td>
                                    <td><?= $act['fecha_entrega'] ? date('d/m/Y', strtotime($act['fecha_entrega'])) : '--' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align:center; padding:30px;">No hay actividades registradas aún.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>

    <!-- Toast container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle
        const sidebar = document.getElementById('sidebar');
        const toggle  = document.getElementById('menuToggle');
        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            toggle.innerHTML = sidebar.classList.contains('open') ? '<i class="fas fa-times"></i>' : '<i class="fas fa-bars"></i>';
        });
        document.addEventListener('click', e => {
            if (window.innerWidth < 992 && sidebar.classList.contains('open') && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
                sidebar.classList.remove('open');
                toggle.innerHTML = '<i class="fas fa-bars"></i>';
            }
        });

        // Cambiar hijo
        function cambiarHijo(id) {
            window.location.href = `reportes.php?hijo=${id}`;
        }

        // Gráfico simulado (datos de ejemplo, puedes reemplazar con PHP)
        const ctx = document.getElementById('progresoChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
                datasets: [{
                    label: 'Actividades completadas',
                    data: [2, 3, 1, 4, 2, 5, 3],
                    borderColor: '#2cbaec',
                    backgroundColor: 'rgba(44, 186, 236, 0.05)',
                    borderWidth: 3,
                    pointBackgroundColor: '#2cbaec',
                    pointBorderColor: 'white',
                    pointRadius: 5,
                    tension: 0.2,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#e2e8f0' } },
                    x: { grid: { display: false } }
                }
            }
        });

        // Generar PDF (simulado con impresión)
        function generarPDF() {
            toast('Preparando informe PDF...', 'info');
            setTimeout(() => {
                window.print();
            }, 800);
        }

        // Función toast (igual que en dashboard)
        function toast(msg, type = 'info') {
            const colors = {
                success: { bg: '#83bf46', icon: 'circle-check' },
                warn:    { bg: '#f0ae2a', icon: 'triangle-exclamation' },
                info:    { bg: '#2cbaec', icon: 'circle-info' },
            };
            const c = colors[type] || colors.info;
            const id = `t${Date.now()}`;
            const container = document.querySelector('.toast-container');
            container.insertAdjacentHTML('beforeend', `
                <div id="${id}" class="toast" role="alert" data-bs-autohide="true" data-bs-delay="3200">
                    <div class="toast-header" style="background:${c.bg};color:white;border-radius:13px 13px 0 0;border:none;">
                        <i class="fas fa-${c.icon} me-2"></i>
                        <strong class="me-auto" style="font-size:0.82rem;">D&F Mindspace</strong>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body" style="font-size:0.85rem;color:#334155;">${msg}</div>
                </div>
            `);
            const el = document.getElementById(id);
            new bootstrap.Toast(el).show();
            el.addEventListener('hidden.bs.toast', () => el.remove());
        }
    </script>
</body>
</html>