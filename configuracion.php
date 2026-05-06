<?php
include 'php/config.php';
session_start();

// Seguridad: Solo tutores (padres)
if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'padre') {
    header("Location: index.php");
    exit();
}

$id_padre = $_SESSION['user_id'];

// Obtener datos del padre desde la base de datos (nombre y email)
$query_padre = "SELECT nombre, email FROM usuarios WHERE id = ? AND tipo = 'padre'";
$stmt_padre = $conn->prepare($query_padre);
$stmt_padre->bind_param("i", $id_padre);
$stmt_padre->execute();
$result_padre = $stmt_padre->get_result();

if ($row_padre = $result_padre->fetch_assoc()) {
    $nombre_padre = $row_padre['nombre'];
    $email_padre = $row_padre['email'];
} else {
    // Si no se encuentra (raro), usar un fallback
    $nombre_padre = 'Padre';
    $email_padre = '';
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

// Obtener hijos vinculados para mostrar códigos de vinculación
$query_hijos = "
    SELECT u.id, u.nombre, u.codigo_vinculacion
    FROM vinculaciones v
    JOIN usuarios u ON v.id_alumno = u.id
    WHERE v.id_padre = ? AND v.estado = 'activo'
    ORDER BY u.nombre
";
$stmt = $conn->prepare($query_hijos);
$stmt->bind_param("i", $id_padre);
$stmt->execute();
$hijos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Procesar mensajes de éxito/error
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'actualizar_perfil') {
        $nuevo_nombre = trim($_POST['nombre'] ?? '');
        $nuevo_email = trim($_POST['email'] ?? '');
        $password_actual = $_POST['password_actual'] ?? '';

        // Verificar contraseña actual
        $query = "SELECT password FROM usuarios WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id_padre);
        $stmt->execute();
        $hash = $stmt->get_result()->fetch_assoc()['password'];
        $stmt->close();

        if (password_verify($password_actual, $hash)) {
            // Actualizar
            $update = "UPDATE usuarios SET nombre = ?, email = ? WHERE id = ?";
            $stmt = $conn->prepare($update);
            $stmt->bind_param("ssi", $nuevo_nombre, $nuevo_email, $id_padre);
            if ($stmt->execute()) {
                $_SESSION['usuario_nombre'] = $nuevo_nombre;
                $nombre_padre = $nuevo_nombre;
                $email_padre = $nuevo_email;
                $mensaje = "Perfil actualizado correctamente.";
                $tipo_mensaje = 'success';
            } else {
                $mensaje = "Error al actualizar. El email podría estar en uso.";
                $tipo_mensaje = 'danger';
            }
            $stmt->close();
        } else {
            $mensaje = "Contraseña actual incorrecta.";
            $tipo_mensaje = 'danger';
        }
    } elseif ($accion === 'cambiar_password') {
        $pass_actual = $_POST['pass_actual'] ?? '';
        $nueva_pass = $_POST['nueva_pass'] ?? '';
        $confirmar_pass = $_POST['confirmar_pass'] ?? '';

        if ($nueva_pass !== $confirmar_pass) {
            $mensaje = "Las contraseñas nuevas no coinciden.";
            $tipo_mensaje = 'danger';
        } elseif (strlen($nueva_pass) < 6) {
            $mensaje = "La contraseña debe tener al menos 6 caracteres.";
            $tipo_mensaje = 'danger';
        } else {
            $query = "SELECT password FROM usuarios WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id_padre);
            $stmt->execute();
            $hash = $stmt->get_result()->fetch_assoc()['password'];
            $stmt->close();

            if (password_verify($pass_actual, $hash)) {
                $nuevo_hash = password_hash($nueva_pass, PASSWORD_DEFAULT);
                $update = "UPDATE usuarios SET password = ? WHERE id = ?";
                $stmt = $conn->prepare($update);
                $stmt->bind_param("si", $nuevo_hash, $id_padre);
                $stmt->execute();
                $stmt->close();
                $mensaje = "Contraseña cambiada exitosamente.";
                $tipo_mensaje = 'success';
            } else {
                $mensaje = "Contraseña actual incorrecta.";
                $tipo_mensaje = 'danger';
            }
        }
    } elseif ($accion === 'desvincular_hijo') {
        $id_hijo = intval($_POST['id_hijo'] ?? 0);
        if ($id_hijo > 0) {
            $delete = "DELETE FROM vinculaciones WHERE id_padre = ? AND id_alumno = ?";
            $stmt = $conn->prepare($delete);
            $stmt->bind_param("ii", $id_padre, $id_hijo);
            $stmt->execute();
            $stmt->close();
            $mensaje = "Hijo desvinculado correctamente.";
            $tipo_mensaje = 'success';
            // Refrescar lista de hijos
            header("Location: configuracion.php?msg=desvinculado");
            exit;
        }
    }
}

// Mensaje por GET (después de desvincular)
if (isset($_GET['msg']) && $_GET['msg'] === 'desvinculado') {
    $mensaje = "Hijo desvinculado correctamente.";
    $tipo_mensaje = 'success';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración · D&F Mindspace</title>
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
        .settings-section {
            background: white;
            border-radius: var(--r-card);
            border: 1px solid rgba(44,186,236,0.1);
            box-shadow: var(--sh-sm);
            padding: 28px 32px;
            margin-bottom: 24px;
        }
        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid rgba(44,186,236,0.1);
        }
        .section-header-icon {
            width: 40px; height: 40px;
            border-radius: 12px;
            background: var(--primary-soft);
            display: flex; align-items: center; justify-content: center;
            color: var(--primary);
            font-size: 1.1rem;
        }
        .section-header h3 {
            font-family: 'Nunito', sans-serif;
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--gray-900);
            margin: 0;
        }
        .section-header p {
            font-size: 0.8rem;
            color: var(--gray-400);
            margin: 2px 0 0;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px 30px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group.full-width {
            grid-column: span 2;
        }
        .form-label {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--gray-500);
            margin-bottom: 8px;
            display: block;
        }
        .form-control {
            border-radius: 12px;
            border: 1.5px solid rgba(44,186,236,0.2);
            padding: 11px 16px;
            font-size: 0.9rem;
            font-family: 'DM Sans', sans-serif;
            background: white;
            transition: border-color 0.18s, box-shadow 0.18s;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(44,186,236,0.12);
            outline: none;
        }
        .btn-save {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: var(--r-pill);
            padding: 11px 28px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            box-shadow: 0 4px 12px rgba(44,186,236,0.25);
            transition: all 0.2s;
        }
        .btn-save:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(44,186,236,0.35);
        }
        .btn-outline-danger {
            border: 1.5px solid var(--danger);
            background: transparent;
            color: var(--danger);
            border-radius: var(--r-pill);
            padding: 9px 22px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.18s;
        }
        .btn-outline-danger:hover {
            background: var(--danger);
            color: white;
        }
        .toggle-switch {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        .toggle-switch input {
            display: none;
        }
        .toggle-slider {
            width: 44px; height: 24px;
            background: var(--gray-200);
            border-radius: 30px;
            position: relative;
            cursor: pointer;
            transition: background 0.2s;
        }
        .toggle-slider::before {
            content: '';
            position: absolute;
            width: 20px; height: 20px;
            background: white;
            border-radius: 50%;
            top: 2px; left: 2px;
            transition: transform 0.2s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        input:checked + .toggle-slider {
            background: var(--primary);
        }
        input:checked + .toggle-slider::before {
            transform: translateX(20px);
        }
        .child-code-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 0;
            border-bottom: 1px solid rgba(44,186,236,0.08);
        }
        .child-code-row:last-child {
            border-bottom: none;
        }
        .child-info-code {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .child-avatar-small {
            width: 40px; height: 40px;
            border-radius: 12px;
            background: var(--primary-soft);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
        }
        .code-badge {
            background: var(--gray-100);
            padding: 6px 14px;
            border-radius: 30px;
            font-family: monospace;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gray-700);
            border: 1px solid var(--gray-200);
            letter-spacing: 0.5px;
        }
        .btn-copy {
            background: none;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            padding: 6px 10px;
            color: var(--gray-500);
            cursor: pointer;
            transition: all 0.15s;
        }
        .btn-copy:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        .danger-zone {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,107,139,0.2);
        }
        .danger-zone h4 {
            color: var(--danger);
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 12px;
        }
        .alert {
            border-radius: 14px;
            padding: 14px 20px;
            margin-bottom: 24px;
            border: none;
            font-size: 0.9rem;
        }
        .alert-success {
            background: var(--accent-soft);
            color: #3d7318;
            border-left: 4px solid var(--accent);
        }
        .alert-danger {
            background: var(--danger-soft);
            color: #b91c1c;
            border-left: 4px solid var(--danger);
        }
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .form-group.full-width { grid-column: span 1; }
            .settings-section { padding: 20px; }
        }
    </style>
</head>
<body>

<button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>

<!-- ═══════ SIDEBAR (IGUAL QUE EN DASHBOARD) ═══════ -->
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
                <div class="icon"><i class="fas fa-users"></i></div><span>Mis hijos</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="dashboard_padre_detalle.php" class="nav-link">
                <div class="icon"><i class="fas fa-chart-line"></i></div><span>Visión general</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="reportes.php" class="nav-link">
                <div class="icon"><i class="fas fa-file-lines"></i></div><span>Reportes</span>
            </a>
        </div>
        <div class="nav-section" style="margin-top:16px;">Cuenta</div>
        <div class="nav-item">
            <a href="suscripcion.php" class="nav-link">
                <div class="icon"><i class="fas fa-gem"></i></div><span>Suscripción</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="configuracion.php" class="nav-link active">
                <div class="icon"><i class="fas fa-sliders"></i></div><span>Configuración</span>
            </a>
        </div>
    </div>
    <div class="sidebar-foot">
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-arrow-right-from-bracket"></i>Cerrar sesión
        </a>
    </div>
</nav>

<!-- ═══════ MAIN ═══════ -->
<main class="main">

    <div class="page-header">
        <div>
            <div class="header-eyebrow">Personalización</div>
            <h1 class="page-title">Configuración de<br><span>tu cuenta</span></h1>
            <p class="page-sub">Administra tu perfil, seguridad y preferencias</p>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipo_mensaje ?>">
            <i class="fas fa-<?= $tipo_mensaje === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
            <?= htmlspecialchars($mensaje) ?>
        </div>
    <?php endif; ?>

    <!-- Perfil -->
    <div class="settings-section">
        <div class="section-header">
            <div class="section-header-icon"><i class="fas fa-user"></i></div>
            <div>
                <h3>Información personal</h3>
                <p>Actualiza tu nombre y correo electrónico</p>
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="accion" value="actualizar_perfil">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Nombre completo</label>
                    <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($nombre_padre) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Correo electrónico</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($email_padre) ?>" required>
                </div>
                <div class="form-group full-width">
                    <label class="form-label">Contraseña actual (para confirmar cambios)</label>
                    <input type="password" name="password_actual" class="form-control" placeholder="••••••••" required>
                </div>
            </div>
            <div style="display: flex; justify-content: flex-end; margin-top: 10px;">
                <button type="submit" class="btn-save"><i class="fas fa-check me-2"></i>Guardar cambios</button>
            </div>
        </form>
    </div>

    <!-- Seguridad -->
    <div class="settings-section">
        <div class="section-header">
            <div class="section-header-icon"><i class="fas fa-lock"></i></div>
            <div>
                <h3>Seguridad</h3>
                <p>Cambia tu contraseña periódicamente</p>
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="accion" value="cambiar_password">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Contraseña actual</label>
                    <input type="password" name="pass_actual" class="form-control" placeholder="••••••••" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nueva contraseña</label>
                    <input type="password" name="nueva_pass" class="form-control" placeholder="Mínimo 6 caracteres" required>
                </div>
                <div class="form-group full-width">
                    <label class="form-label">Confirmar nueva contraseña</label>
                    <input type="password" name="confirmar_pass" class="form-control" placeholder="Repite la contraseña" required>
                </div>
            </div>
            <div style="display: flex; justify-content: flex-end; margin-top: 10px;">
                <button type="submit" class="btn-save"><i class="fas fa-key me-2"></i>Actualizar contraseña</button>
            </div>
        </form>
    </div>

    <!-- Preferencias de notificaciones -->
    <div class="settings-section">
        <div class="section-header">
            <div class="section-header-icon"><i class="fas fa-bell"></i></div>
            <div>
                <h3>Notificaciones</h3>
                <p>Elige cómo quieres recibir avisos</p>
            </div>
        </div>
        <div class="toggle-switch">
            <input type="checkbox" id="notifEmail" checked>
            <label for="notifEmail" class="toggle-slider"></label>
            <span style="font-weight: 500; color: var(--gray-700);">Recibir notificaciones por correo electrónico</span>
        </div>
        <div class="toggle-switch">
            <input type="checkbox" id="notifPush" checked>
            <label for="notifPush" class="toggle-slider"></label>
            <span style="font-weight: 500; color: var(--gray-700);">Notificaciones push en el navegador</span>
        </div>
        <div class="toggle-switch">
            <input type="checkbox" id="notifResumen">
            <label for="notifResumen" class="toggle-slider"></label>
            <span style="font-weight: 500; color: var(--gray-700);">Resumen semanal de progreso (viernes)</span>
        </div>
        <p class="text-muted small mt-3"><i class="fas fa-info-circle me-1"></i> Los cambios se guardan automáticamente.</p>
    </div>

    <!-- Vinculación de hijos -->
    <div class="settings-section">
        <div class="section-header">
            <div class="section-header-icon"><i class="fas fa-link"></i></div>
            <div>
                <h3>Hijos vinculados</h3>
                <p>Códigos para vincular nuevos dispositivos o desvincular</p>
            </div>
        </div>
        <?php if (empty($hijos)): ?>
            <p class="text-muted">Aún no tienes hijos vinculados. Ve a <a href="dashboard_padre.php">Mis hijos</a> para agregar uno.</p>
        <?php else: ?>
            <?php foreach ($hijos as $hijo): ?>
            <div class="child-code-row">
                <div class="child-info-code">
                    <div class="child-avatar-small">
                        <?php
                        $emojis = ['panda'=>'🐼','dragon'=>'🐉','leon'=>'🦁','buho'=>'🦉','zorro'=>'🦊','gato'=>'🐱'];
                        echo $emojis[$hijo['avatar'] ?? ''] ?? '🧒';
                        ?>
                    </div>
                    <div>
                        <strong style="color: var(--gray-900);"><?= htmlspecialchars($hijo['nombre']) ?></strong>
                        <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                            <span class="code-badge"><?= htmlspecialchars($hijo['codigo_vinculacion'] ?? 'DF-'.strtoupper(substr(md5($hijo['id']),0,8))) ?></span>
                            <button class="btn-copy" onclick="copiarCodigo('<?= htmlspecialchars($hijo['codigo_vinculacion'] ?? 'DF-'.strtoupper(substr(md5($hijo['id']),0,8))) ?>')" title="Copiar código">
                                <i class="far fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <form method="POST" onsubmit="return confirm('¿Estás seguro de desvincular a <?= htmlspecialchars($hijo['nombre']) ?>?');">
                    <input type="hidden" name="accion" value="desvincular_hijo">
                    <input type="hidden" name="id_hijo" value="<?= $hijo['id'] ?>">
                    <button type="submit" class="btn-outline-danger"><i class="fas fa-user-minus me-1"></i>Desvincular</button>
                </form>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <p class="text-muted small mt-3"><i class="fas fa-info-circle me-1"></i> Comparte el código con tu hijo para que pueda vincularse desde su perfil.</p>
    </div>

    <!-- Zona peligrosa -->
    <div class="settings-section">
        <div class="section-header">
            <div class="section-header-icon" style="background: var(--danger-soft); color: var(--danger);"><i class="fas fa-triangle-exclamation"></i></div>
            <div>
                <h3 style="color: var(--danger);">Zona de peligro</h3>
                <p>Acciones irreversibles</p>
            </div>
        </div>
        <div style="display: flex; flex-wrap: wrap; gap: 16px; align-items: center;">
            <button class="btn-outline-danger" onclick="cerrarSesionTodos()">
                <i class="fas fa-sign-out-alt me-1"></i>Cerrar sesión en todos los dispositivos
            </button>
            <button class="btn-outline-danger" onclick="eliminarCuenta()">
                <i class="fas fa-trash-alt me-1"></i>Eliminar cuenta permanentemente
            </button>
        </div>
        <p class="text-muted small mt-3">Estas acciones no se pueden deshacer.</p>
    </div>

</main>

<!-- Toast container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Sidebar toggle (mismo que en dashboard)
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

    // Copiar código al portapapeles
    function copiarCodigo(codigo) {
        navigator.clipboard.writeText(codigo).then(() => {
            showToast('Código copiado al portapapeles', 'success');
        }).catch(() => {
            showToast('No se pudo copiar', 'warn');
        });
    }

    // Acciones peligrosas (simuladas)
    function cerrarSesionTodos() {
        if (confirm('¿Cerrar sesión en todos los demás dispositivos?')) {
            // Aquí podrías invalidar tokens o similar
            showToast('Sesiones cerradas en otros dispositivos', 'info');
        }
    }

    function eliminarCuenta() {
        if (confirm('¿Estás ABSOLUTAMENTE seguro? Esta acción eliminará tu cuenta y todos los datos asociados de forma permanente.')) {
            if (confirm('Última advertencia: perderás acceso a todos los perfiles vinculados. ¿Continuar?')) {
                // Redirigir a script de eliminación
                window.location.href = 'eliminar_cuenta.php';
            }
        }
    }

    // Toast
    function showToast(msg, type = 'info') {
        const colors = {
            success: { bg: '#83bf46', icon: 'circle-check' },
            warn:    { bg: '#f0ae2a', icon: 'triangle-exclamation' },
            info:    { bg: '#2cbaec', icon: 'circle-info' },
        };
        const c = colors[type] || colors.info;
        const id = `t${Date.now()}`;
        document.querySelector('.toast-container').insertAdjacentHTML('beforeend', `
            <div id="${id}" class="toast" role="alert" data-bs-autohide="true" data-bs-delay="3500">
                <div class="toast-header" style="background:${c.bg};color:white;border-radius:13px 13px 0 0;border:none;">
                    <i class="fas fa-${c.icon} me-2"></i>
                    <strong class="me-auto" style="font-size:.82rem;">D&F Mindspace</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body" style="font-size:.84rem;color:#334155;">${msg}</div>
            </div>
        `);
        const el = document.getElementById(id);
        new bootstrap.Toast(el).show();
        el.addEventListener('hidden.bs.toast', () => el.remove());
    }
</script>
</body>
</html>