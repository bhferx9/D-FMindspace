<?php
include 'php/config.php';
session_start();

// Seguridad: Solo tutores (padres)
if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'padre') {
    header("Location: index.php");
    exit();
}

$id_padre = $_SESSION['user_id'];

// Obtener datos del padre desde la base de datos (usando PDO directamente)
try {
    $query_padre = "SELECT nombre FROM usuarios WHERE id = ? AND tipo = 'padre'";
    $stmt_padre = $conn->pdo->prepare($query_padre);
    $stmt_padre->execute([$id_padre]);
    $result_padre = $stmt_padre->fetch(PDO::FETCH_ASSOC);

    if ($result_padre) {
        $nombre_padre = $result_padre['nombre'];
    } else {
        $nombre_padre = 'Padre';
    }
} catch(PDOException $e) {
    $nombre_padre = 'Padre';
}

// Iniciales para avatar
$iniciales = '';
$partes = explode(' ', trim($nombre_padre));
if (count($partes) >= 2) {
    $iniciales = strtoupper(substr($partes[0], 0, 1) . substr($partes[1], 0, 1));
} else {
    $iniciales = strtoupper(substr($nombre_padre, 0, 2));
}

// Mapeo de avatares (igual que en dashboard_alumno.php)
$avatares = [
    'panda' => ['emoji' => '🐼', 'color' => '#3A506B', 'nivel' => 1],
    'zorro' => ['emoji' => '🦊', 'color' => '#E67E22', 'nivel' => 1],
    'dragon' => ['emoji' => '🐉', 'color' => '#FF6B6B', 'nivel' => 1],
    'leon' => ['emoji' => '🦁', 'color' => '#FFD93D', 'nivel' => 2],
    'dino' => ['emoji' => '🦖', 'color' => '#6BCF7F', 'nivel' => 1],
    'robot' => ['emoji' => '🤖', 'color' => '#4D96FF', 'nivel' => 3],
    'astronauta' => ['emoji' => '👨‍🚀', 'color' => '#845EC2', 'nivel' => 4],
    'superheroe' => ['emoji' => '🦸‍♂️', 'color' => '#FF6B8B', 'nivel' => 5],
    'mago' => ['emoji' => '🧙‍♂️', 'color' => '#00C2A8', 'nivel' => 6],
    'ninja' => ['emoji' => '🥷', 'color' => '#4A4A4A', 'nivel' => 3],
    'fenix' => ['emoji' => '🔥', 'color' => '#FF4500', 'nivel' => 7],
    'unicornio' => ['emoji' => '🦄', 'color' => '#D65DB1', 'nivel' => 8],
    'ballena' => ['emoji' => '🐋', 'color' => '#4169E1', 'nivel' => 3],
    'aguila' => ['emoji' => '🦅', 'color' => '#DAA520', 'nivel' => 3],
    'lobo' => ['emoji' => '🐺', 'color' => '#708090', 'nivel' => 3],
    'pinguino' => ['emoji' => '🐧', 'color' => '#1C2833', 'nivel' => 2],
    'bufalo' => ['emoji' => '🦬', 'color' => '#8B4513', 'nivel' => 2],
    'conejo' => ['emoji' => '🐰', 'color' => '#F4A460', 'nivel' => 1],
    'gato' => ['emoji' => '🐱', 'color' => '#FFA07A', 'nivel' => 1],
    'perro' => ['emoji' => '🐶', 'color' => '#DEB887', 'nivel' => 1],
    'raton' => ['emoji' => '🐭', 'color' => '#B0C4DE', 'nivel' => 1],
    'abeja' => ['emoji' => '🐝', 'color' => '#FFD700', 'nivel' => 2],
    'pulpo' => ['emoji' => '🐙', 'color' => '#CD5C5C', 'nivel' => 2],
    'robot_avanzado' => ['emoji' => '🤖', 'color' => '#2E86AB', 'nivel' => 5],
    'titan' => ['emoji' => '🏛️', 'color' => '#8B0000', 'nivel' => 4],
    'centauro' => ['emoji' => '🏹', 'color' => '#CD853F', 'nivel' => 4],
    'ciborg' => ['emoji' => '🦾', 'color' => '#4682B4', 'nivel' => 5],
    'kraken' => ['emoji' => '🐙', 'color' => '#2F4F4F', 'nivel' => 5],
    'valquiria' => ['emoji' => '⚔️', 'color' => '#C0C0C0', 'nivel' => 5],
    'dios_ra' => ['emoji' => '☀️', 'color' => '#FFD700', 'nivel' => 6],
    'leviathan' => ['emoji' => '🐉', 'color' => '#1a237e', 'nivel' => 6],
    'thor' => ['emoji' => '🔨', 'color' => '#5DADE2', 'nivel' => 6],
    'cerbero' => ['emoji' => '🐕‍🦺', 'color' => '#8B4513', 'nivel' => 6],
    'zeus' => ['emoji' => '⚡', 'color' => '#FFD700', 'nivel' => 7]
];

function getAvatarEmoji($avatar_key) {
    global $avatares;
    return isset($avatares[$avatar_key]) ? $avatares[$avatar_key]['emoji'] : '🧒';
}

function getAvatarColor($avatar_key) {
    global $avatares;
    return isset($avatares[$avatar_key]) ? $avatares[$avatar_key]['color'] : '#3A506B';
}

// Obtener hijos vinculados
try {
    $query_hijos = "
        SELECT 
            u.id,
            u.nombre,
            u.avatar,
            EXTRACT(YEAR FROM age(CURRENT_DATE, u.fecha_nacimiento)) as edad,
            (SELECT COUNT(*) FROM inscripciones WHERE id_alumno = u.id AND estado = 'activo') AS cursos_activos,
            (SELECT COALESCE(SUM(actividades_completadas), 0) FROM progreso WHERE id_alumno = u.id) AS completadas,
            (SELECT COUNT(*) FROM entregas WHERE id_alumno = u.id) AS total_entregas,
            (SELECT COALESCE(AVG(porcentaje), 0) FROM progreso WHERE id_alumno = u.id) AS progreso_promedio
        FROM vinculaciones v
        JOIN usuarios u ON v.id_alumno = u.id
        WHERE v.id_padre = ? AND v.estado = 'activo' AND u.activo = TRUE
        ORDER BY u.nombre
    ";

    $stmt = $conn->pdo->prepare($query_hijos);
    $stmt->execute([$id_padre]);
    $hijos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $hijos = [];
}

$total_hijos = count($hijos);

// Mostrar mensaje de toast si existe
if (isset($_SESSION['toast'])) {
    $toast = $_SESSION['toast'];
    $tipo = $toast[0];
    $mensaje = $toast[1];
    unset($_SESSION['toast']);
    $toast_script = "<script>showToast('$mensaje', '$tipo');</script>";
} else {
    $toast_script = "";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Hijos · D&F Mindspace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
            width: 68px;
            height: 68px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
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
    </style>
</head>
<body>
    <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>

    <!-- SIDEBAR -->
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
                <a href="dashboard_padre.php" class="nav-link active">
                    <div class="icon"><i class="fas fa-users"></i></div>
                    <span>Mis hijos</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="reportes.php" class="nav-link">
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
                <div class="header-eyebrow">Seguimiento familiar</div>
                <h1 class="page-title">¿De quién vemos el<br><span>progreso hoy?</span></h1>
                <p class="page-sub">Selecciona un perfil para revisar actividades, logros y avances</p>
            </div>
            <div class="header-meta">
                <div class="dot"></div>
                <?= $total_hijos ?> perfiles activos
            </div>
        </div>

        <div class="children-grid">
            <?php foreach ($hijos as $index => $hijo): 
                $progreso = $hijo['progreso_promedio'] ? round($hijo['progreso_promedio']) : 0;
                $circunferencia = 131.9;
                $offset = $circunferencia * (1 - $progreso / 100);
                $color_ring = ($index % 3 == 0) ? 'blue' : (($index % 3 == 1) ? 'amber' : 'green');
                $strip_class = 'strip-' . $color_ring;
                $ring_class = 'ring-' . $color_ring;
                $emoji = getAvatarEmoji($hijo['avatar']);
                $avatar_color = getAvatarColor($hijo['avatar']);
            ?>
            <div class="child-card fade-up" style="animation-delay: <?= 0.05 + $index*0.07 ?>s" onclick="selectChild('<?= htmlspecialchars($hijo['nombre'], ENT_QUOTES) ?>', <?= $hijo['id'] ?>)">
                <div class="card-strip <?= $strip_class ?>"></div>
                <div class="card-top">
                    <div class="child-emoji" style="background: <?= $avatar_color ?>;"><?= $emoji ?></div>
                    <div class="progress-ring-wrap">
                        <svg class="progress-ring" width="52" height="52" viewBox="0 0 52 52">
                            <circle class="ring-bg" cx="26" cy="26" r="21"/>
                            <circle class="ring-fill <?= $ring_class ?>" cx="26" cy="26" r="21"
                                stroke-dasharray="<?= $circunferencia ?>"
                                stroke-dashoffset="<?= $offset ?>" />
                        </svg>
                        <span class="ring-label"><?= $progreso ?>%</span>
                    </div>
                </div>
                <div class="child-name"><?= htmlspecialchars($hijo['nombre']) ?></div>
                <div class="child-meta">
                    <span class="meta-chip"><i class="fas fa-graduation-cap"></i> <?= $hijo['cursos_activos'] ?> cursos</span>
                    <span class="meta-chip"><i class="fas fa-cake-candles"></i> <?= $hijo['edad'] ?? '--' ?> años</span>
                </div>
                <div class="card-stats">
                    <div class="stat-chip">
                        <div class="stat-chip-val" style="color: var(--primary);"><?= $hijo['total_entregas'] ?? 0 ?></div>
                        <div class="stat-chip-lbl">Entregas</div>
                    </div>
                    <div class="stat-chip">
                        <div class="stat-chip-val" style="color: var(--accent);"><?= $hijo['completadas'] ?? 0 ?></div>
                        <div class="stat-chip-lbl">Completadas</div>
                    </div>
                    <div class="stat-chip">
                        <div class="stat-chip-val" style="color: var(--secondary);">--</div>
                        <div class="stat-chip-lbl">Logros</div>
                    </div>
                </div>
                <button class="card-cta">
                    <i class="fas fa-arrow-right" style="font-size: 0.8rem;"></i>
                    Ver seguimiento
                </button>
            </div>
            <?php endforeach; ?>

            <div class="add-card fade-up" style="animation-delay: 0.19s" onclick="openModal()">
                <div class="add-icon"><i class="fas fa-plus"></i></div>
                <div class="add-title">Vincular otro hijo</div>
                <div class="add-sub">Agrega otro estudiante a tu cuenta familiar</div>
            </div>
        </div>

        <!-- Tips panel (estático) -->
        <div class="tips-panel fade-up" style="animation-delay: 0.28s">
            <div class="tips-header">
                <div class="tips-header-icon"><i class="fas fa-lightbulb"></i></div>
                <div>
                    <div class="tips-header-title">Lo que puedes hacer desde aquí</div>
                    <div class="tips-header-sub">Herramientas para acompañar el aprendizaje de tus hijos</div>
                </div>
            </div>
            <div class="tips-grid">
                <div class="tip-item">
                    <div class="tip-icon" style="background: var(--primary-soft); color: var(--primary);"><i class="fas fa-chart-line"></i></div>
                    <div class="tip-text"><strong>Progreso en tiempo real</strong><span>Avances por materia y tendencias</span></div>
                </div>
                <div class="tip-item">
                    <div class="tip-icon" style="background: var(--secondary-soft); color: var(--secondary);"><i class="fas fa-bell"></i></div>
                    <div class="tip-text"><strong>Notificaciones clave</strong><span>Alertas de entregas y logros</span></div>
                </div>
                <div class="tip-item">
                    <div class="tip-icon" style="background: var(--accent-soft); color: var(--accent);"><i class="fas fa-file-pdf"></i></div>
                    <div class="tip-text"><strong>Reportes descargables</strong><span>Informes en PDF</span></div>
                </div>
                <div class="tip-item">
                    <div class="tip-icon" style="background: rgba(255,107,139,0.08); color: var(--danger);"><i class="fas fa-arrow-right-arrow-left"></i></div>
                    <div class="tip-text"><strong>Cambia de perfil sin salir</strong><span>Alterna entre tus hijos</span></div>
                </div>
            </div>
        </div>
    </main>

    <!-- MODAL -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="fw-bold mb-1" style="color: var(--gray-900); font-family: 'Nunito', sans-serif;">Vincular nuevo hijo</h5>
                        <p class="mb-0" style="font-size: 0.82rem; color: var(--gray-400);">Ingresa el código que aparece en el perfil del estudiante</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="vincular_hijo.php" method="POST">
                    <div class="modal-body">
                        <label class="form-label">Código de vinculación</label>
                        <div class="input-group">
                            <span class="input-group-text" style="background: var(--gray-50); border: 1.5px solid rgba(44,186,236,0.2); border-right: none; border-radius: 12px 0 0 12px;">
                                <i class="fas fa-key" style="color: var(--primary);"></i>
                            </span>
                            <input type="text" class="form-control" name="codigo" placeholder="Ej. DF-2026-ABC123" required>
                        </div>
                        <p style="font-size: 0.76rem; color: var(--gray-400); margin-top: 8px;">
                            El código lo encuentra el estudiante en su perfil dentro de D&F Mindspace.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary px-4" style="background: var(--primary); border-radius: var(--radius-pill);">Vincular cuenta</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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

        function selectChild(name, id) {
            sessionStorage.setItem('selectedChild', name);
            sessionStorage.setItem('selectedChildId', id);
            toast(`Viendo el progreso de ${name}`, 'success');
            setTimeout(() => window.location.href = `dashboard_padre_detalle.php?hijo=${id}`, 900);
        }

        let bsModal;
        function openModal() {
            bsModal = new bootstrap.Modal(document.getElementById('addModal'));
            bsModal.show();
        }

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

        function showToast(msg, type) {
            toast(msg, type);
        }
        <?php echo $toast_script; ?>
    </script>
</body>
</html>