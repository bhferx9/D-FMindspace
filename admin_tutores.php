<?php
include 'php/config.php';
session_start();


// Verificar que el usuario sea administrador
if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'admin') {
    header("Location: index.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$admin_nombre = $_SESSION['usuario_nombre'] ?? 'Administrador';


// Iniciales para avatar
$iniciales = '';
$partes = explode(' ', trim($admin_nombre));
if (count($partes) >= 2) {
    $iniciales = strtoupper(substr($partes[0], 0, 1) . substr($partes[1], 0, 1));
} else {
    $iniciales = strtoupper(substr($admin_nombre, 0, 2));
}

// --- PROCESAR CREACIÓN/EDICIÓN DE TUTOR ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_tutor'])) {
    $id_tutor = intval($_POST['id_tutor'] ?? 0);
    $nombre = mysqli_real_escape_string($conn, trim($_POST['nombre']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = $_POST['password'] ?? '';
    $activo = isset($_POST['activo']) ? 1 : 0;

    $errores = [];
    if (empty($nombre)) $errores[] = "El nombre es obligatorio.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = "Email inválido.";

    if (empty($errores)) {
        if ($id_tutor > 0) {
            // Actualizar tutor existente
            $update = "UPDATE usuarios SET nombre = '$nombre', email = '$email', activo = $activo";
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $update .= ", password = '$hash'";
            }
            $update .= " WHERE id = $id_tutor AND tipo = 'tutor'";
            
            if (mysqli_query($conn, $update)) {
                $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Tutor actualizado correctamente.'];
            } else {
                $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'Error al actualizar: ' . mysqli_error($conn)];
            }
        } else {
            // Crear nuevo tutor
            $check_email = mysqli_query($conn, "SELECT id FROM usuarios WHERE email = '$email'");
            if (mysqli_num_rows($check_email) > 0) {
                $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'El email ya está registrado.'];
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $insert = "INSERT INTO usuarios (nombre, email, password, tipo, activo, fecha_registro) 
                           VALUES ('$nombre', '$email', '$hash', 'tutor', $activo, NOW())";
                if (mysqli_query($conn, $insert)) {
                    $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Tutor creado exitosamente.'];
                } else {
                    $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'Error al crear: ' . mysqli_error($conn)];
                }
            }
        }
    } else {
        $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => implode(' ', $errores)];
    }
    header("Location: admin_tutores.php");
    exit();
}

// --- PROCESAR ASIGNACIÓN DE CURSO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'asignar_curso') {
    $id_tutor = intval($_POST['id_tutor']);
    $id_curso = intval($_POST['id_curso']);
    $check = mysqli_query($conn, "SELECT id FROM cursos WHERE id = $id_curso AND id_tutor = $id_tutor");
    if (mysqli_num_rows($check) == 0) {
        $update = "UPDATE cursos SET id_tutor = $id_tutor WHERE id = $id_curso";
        if (mysqli_query($conn, $update)) {
            $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Curso asignado correctamente.'];
        }
    } else {
        $_SESSION['mensaje'] = ['tipo' => 'warning', 'texto' => 'El tutor ya tiene asignado este curso.'];
    }
    header("Location: admin_tutores.php");
    exit();
}

// --- PROCESAR DESASIGNACIÓN DE CURSO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'desasignar_curso') {
    $id_curso = intval($_POST['id_curso']);
    $update = "UPDATE cursos SET id_tutor = NULL WHERE id = $id_curso";
    mysqli_query($conn, $update);
    $_SESSION['mensaje'] = ['tipo' => 'info', 'texto' => 'Curso desasignado.'];
    header("Location: admin_tutores.php");
    exit();
}

// --- PROCESAR CAMBIO DE ESTADO (SUSPENDER/ACTIVAR) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'cambiar_estado') {
    $id_tutor = intval($_POST['id_tutor']);
    $nuevo_estado = intval($_POST['activo']);
    $update = "UPDATE usuarios SET activo = $nuevo_estado WHERE id = $id_tutor AND tipo = 'tutor'";
    mysqli_query($conn, $update);
    $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Estado actualizado.'];
    header("Location: admin_tutores.php");
    exit();
}

// --- FILTROS Y PAGINACIÓN ---
$busqueda = isset($_GET['busqueda']) ? mysqli_real_escape_string($conn, trim($_GET['busqueda'])) : '';
$estado_filtro = isset($_GET['estado']) ? $_GET['estado'] : '';

$where = ["tipo = 'tutor'"];
if (!empty($busqueda)) {
    $where[] = "(nombre LIKE '%$busqueda%' OR email LIKE '%$busqueda%')";
}
if ($estado_filtro !== '') {
    $where[] = "activo = " . ($estado_filtro ? 'TRUE' : 'FALSE');
}
$where_sql = 'WHERE ' . implode(' AND ', $where);

// Total tutores
$total_query = "SELECT COUNT(*) AS total FROM usuarios $where_sql";
$total_res = mysqli_query($conn, $total_query);
$total_tutores = mysqli_fetch_assoc($total_res)['total'];

$por_pagina = 8;
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina - 1) * $por_pagina;
$total_paginas = ceil($total_tutores / $por_pagina);

// Obtener tutores
$query_tutores = "
    SELECT id, nombre, email, activo, fecha_registro 
    FROM usuarios 
    $where_sql 
    ORDER BY nombre ASC 
    LIMIT $por_pagina OFFSET $offset
";
$res_tutores = mysqli_query($conn, $query_tutores);
$tutores = [];
while ($t = mysqli_fetch_assoc($res_tutores)) {
    $tutores[] = $t;
}

// Obtener lista de cursos disponibles para asignación
$cursos_disponibles = mysqli_query($conn, "
    SELECT id, nombre FROM cursos 
    WHERE activo = TRUE 
    ORDER BY nombre
");

// Mensaje de sesión
$mensaje = $_SESSION['mensaje'] ?? null;
unset($_SESSION['mensaje']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Tutores - D&F Mindspace Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;800&family=Poppins:wght@400;500;600&family=Fredoka+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* === MISMO CSS DEL DASHBOARD ADMIN (sin cambios) === */
        :root {
            --primary: #2cbaec;
            --secondary: #f0ae2a;
            --accent: #83bf46;
            --danger: #ff5757;
            --dark-blue: #1a8db8;
            --sidebar-width: 260px;
            --shadow: 0 4px 20px rgba(44,186,236,0.10);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #f0f9fd;
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
        }
        /* Sidebar (idéntico) */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0; top: 0;
            background: #fff;
            border-right: 3px solid var(--primary);
            box-shadow: 4px 0 18px rgba(44,186,236,0.08);
            display: flex;
            flex-direction: column;
            z-index: 100;
            transition: transform .3s;
        }
        .sidebar-inner {
            flex: 1;
            overflow-y: auto;
            padding: 24px 0 16px;
            scrollbar-width: thin;
            scrollbar-color: rgba(44,186,236,.15) transparent;
        }
        .brand {
            text-align: center;
            padding: 0 18px 22px;
            border-bottom: 1.5px solid rgba(44,186,236,.1);
            margin-bottom: 10px;
        }
        .brand-logo { font-family: 'Fredoka One', cursive; font-size: 2.2rem; background: linear-gradient(90deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; line-height: 1; }
        .brand-sub { font-size: .85rem; color: var(--primary); font-weight: 600; letter-spacing: 6px; text-transform: uppercase; margin: 3px 0 6px; }
        .brand-tagline { font-size: .7rem; color: #aaa; letter-spacing: 2px; text-transform: uppercase; }
        .brand-tagline b:nth-child(1) { color: var(--primary); }
        .brand-tagline b:nth-child(2) { color: var(--secondary); }
        .brand-tagline b:nth-child(3) { color: var(--accent); }
        .admin-chip {
            display: inline-block;
            background: linear-gradient(90deg, var(--danger), #ff8c42);
            color: white;
            font-size: .65rem;
            font-weight: 700;
            letter-spacing: 2px;
            padding: 3px 12px;
            border-radius: 20px;
            margin-top: 8px;
        }
        .user-row {
            display: flex; align-items: center; gap: 10px;
            margin: 12px 14px 4px;
            padding: 12px 14px;
            background: rgba(44,186,236,.05);
            border-radius: 14px;
        }
        .avatar {
            width: 40px; height: 40px; border-radius: 50%;
            background: linear-gradient(135deg, var(--danger), #ff8c42);
            color: white; font-weight: 700; font-size: .95rem;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .user-row .name { font-weight: 600; font-size: .88rem; color: #222; }
        .user-row .online { font-size: .72rem; color: var(--accent); display: flex; align-items: center; gap: 5px; }
        .online-dot { width: 7px; height: 7px; background: var(--accent); border-radius: 50%; animation: blink 2s infinite; }
        @keyframes blink { 0%,100%{opacity:1}50%{opacity:.3} }
        .nav-label { font-size: .65rem; font-weight: 700; color: #bbb; letter-spacing: 3px; text-transform: uppercase; padding: 16px 20px 4px; }
        .nav-item { margin: 3px 10px; }
        .nav-link {
            display: flex; align-items: center; gap: 11px;
            padding: 11px 16px;
            color: #555; font-weight: 600; font-size: .875rem;
            border-radius: 12px;
            border-left: 3px solid transparent;
            transition: all .25s;
            text-decoration: none;
        }
        .nav-link i { width: 18px; text-align: center; color: var(--primary); font-size: .9rem; }
        .nav-link:hover, .nav-link.active {
            background: rgba(44,186,236,.09);
            color: var(--primary);
            border-left-color: var(--primary);
            transform: translateX(3px);
        }
        .sidebar-footer {
            flex-shrink: 0;
            padding: 12px 10px;
            border-top: 1px solid rgba(44,186,236,.1);
        }
        .logout-link {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 16px;
            color: var(--danger); font-weight: 600; font-size: .875rem;
            border-radius: 12px;
            background: rgba(255,87,87,.07);
            border-left: 3px solid var(--danger);
            text-decoration: none;
            transition: background .2s;
        }
        .logout-link:hover { background: rgba(255,87,87,.14); }
        .logout-link i { color: var(--danger); width: 18px; text-align: center; }

        /* MAIN */
        .main {
            margin-left: var(--sidebar-width);
            padding: 32px 30px;
            min-height: 100vh;
        }
        .top {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 28px; flex-wrap: wrap; gap: 14px;
        }
        .page-title { font-family: 'Nunito', sans-serif; font-weight: 800; font-size: 1.75rem; color: #1a1a2e; }
        .page-title span { color: var(--primary); }
        .page-sub { color: #aaa; font-size: .83rem; margin-top: 2px; }

        .btn-add {
            background: linear-gradient(90deg, var(--primary), var(--dark-blue));
            border: none; color: white;
            padding: 10px 22px; border-radius: 12px;
            font-weight: 600; font-size: .875rem;
            display: flex; align-items: center; gap: 8px;
            cursor: pointer; transition: all .25s;
            box-shadow: 0 5px 16px rgba(44,186,236,.3);
            text-decoration: none;
        }
        .btn-add:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(44,186,236,.4); color: white; }

        /* FILTROS */
        .filtros-bar {
            background: white;
            border-radius: 16px;
            padding: 16px 24px;
            box-shadow: var(--shadow);
            border: 1.5px solid rgba(44,186,236,.12);
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 15px;
        }
        .search-wrapper {
            display: flex; align-items: center; gap: 8px;
            background: rgba(44,186,236,.06);
            border: 1.5px solid rgba(44,186,236,.15);
            border-radius: 10px; padding: 6px 12px;
            flex: 2; min-width: 200px;
        }
        .search-wrapper input {
            border: none; background: transparent; outline: none;
            font-size: .82rem; font-family: 'Poppins', sans-serif;
            color: #333; width: 100%;
        }
        .search-wrapper i { color: #aaa; font-size: .8rem; }
        .filter-select {
            padding: 6px 12px;
            border: 1.5px solid rgba(44,186,236,.15);
            border-radius: 10px;
            background: white;
            font-size: .82rem;
            color: #333;
            flex: 1;
            min-width: 130px;
        }
        .btn-filtrar {
            background: white;
            border: 1.5px solid var(--primary);
            color: var(--primary);
            border-radius: 10px;
            padding: 6px 18px;
            font-weight: 600;
            font-size: .82rem;
            cursor: pointer;
            transition: all .2s;
        }
        .btn-filtrar:hover { background: var(--primary); color: white; }

        /* TARJETAS DE TUTORES */
        .tutores-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .tutor-card {
            background: white;
            border-radius: 18px;
            box-shadow: var(--shadow);
            border: 1.5px solid rgba(44,186,236,.1);
            padding: 22px 20px;
            transition: all .25s;
            position: relative;
        }
        .tutor-card:hover { transform: translateY(-5px); box-shadow: 0 12px 28px rgba(44,186,236,.15); }
        .tutor-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 18px;
        }
        .tutor-avatar {
            width: 60px; height: 60px;
            border-radius: 18px;
            background: linear-gradient(135deg, var(--primary), var(--dark-blue));
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 700; font-size: 1.5rem;
            flex-shrink: 0;
        }
        .tutor-info h5 {
            font-family: 'Nunito', sans-serif;
            font-weight: 800;
            font-size: 1.1rem;
            margin-bottom: 2px;
            color: #1a1a2e;
        }
        .tutor-email {
            font-size: .75rem;
            color: #888;
            word-break: break-all;
        }
        .tutor-status {
            position: absolute;
            top: 18px; right: 18px;
        }
        .status-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 10px; border-radius: 20px;
            font-size: .68rem; font-weight: 700;
        }
        .status-badge.active { background: rgba(131,191,70,.12); color: #6ca839; }
        .status-badge.inactive { background: rgba(180,180,180,.12); color: #999; }
        
        .cursos-asignados {
            margin: 15px 0 10px;
        }
        .cursos-title {
            font-size: .75rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 1px; color: var(--primary); margin-bottom: 8px;
        }
        .curso-tag {
            display: inline-block;
            background: rgba(44,186,236,.08);
            border: 1px solid rgba(44,186,236,.2);
            border-radius: 20px;
            padding: 4px 12px;
            font-size: .75rem;
            margin: 0 5px 8px 0;
            color: #444;
        }
        .curso-tag i { color: var(--primary); margin-right: 4px; font-size: .65rem; }
        .btn-asignar {
            background: white;
            border: 1.5px solid var(--primary);
            color: var(--primary);
            border-radius: 20px;
            padding: 6px 14px;
            font-size: .75rem; font-weight: 600;
            cursor: pointer; transition: all .2s;
            margin-top: 5px;
        }
        .btn-asignar:hover { background: var(--primary); color: white; }
        
        .tutor-actions {
            display: flex; gap: 8px; margin-top: 15px;
            border-top: 1px solid rgba(44,186,236,.1);
            padding-top: 15px;
        }
        .btn-card-action {
            flex: 1;
            background: white;
            border: 1.5px solid rgba(44,186,236,.2);
            color: #666;
            border-radius: 10px;
            padding: 6px 0;
            font-size: .75rem; font-weight: 600;
            cursor: pointer; transition: all .2s;
        }
        .btn-card-action:hover { border-color: var(--primary); color: var(--primary); }
        .btn-card-action.danger:hover { border-color: var(--danger); color: var(--danger); }

        /* PAGINACIÓN */
        .pagination-wrap {
            display: flex; align-items: center; justify-content: center;
            gap: 10px; margin-top: 20px;
        }
        .page-link {
            padding: 8px 14px;
            border-radius: 10px;
            background: white;
            border: 1.5px solid rgba(44,186,236,.2);
            color: var(--primary);
            font-weight: 600;
            font-size: .85rem;
            text-decoration: none;
            transition: all .2s;
        }
        .page-link:hover, .page-link.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* MODAL */
        .modal-content {
            border: none;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        .modal-header {
            background: linear-gradient(90deg, var(--primary), var(--dark-blue));
            color: white;
            border-bottom: none;
            padding: 20px 28px;
        }
        .modal-title {
            font-family: 'Nunito', sans-serif;
            font-weight: 800;
            font-size: 1.2rem;
        }
        .modal-body { padding: 28px; }
        .modal-footer {
            padding: 16px 28px 24px;
            border-top: 1px solid rgba(44,186,236,.1);
        }
        .form-label {
            font-size: .75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--primary);
            margin-bottom: 6px;
        }
        .form-control, .form-select {
            border-radius: 12px;
            border: 1.5px solid rgba(44,186,236,.2);
            padding: 10px 14px;
            font-size: .9rem;
            background: white;
        }

        /* MENU TOGGLE */
        .menu-toggle {
            display: none; position: fixed; top: 15px; left: 15px;
            z-index: 200; background: linear-gradient(90deg, var(--primary), var(--secondary));
            border: none; color: white; width: 44px; height: 44px;
            border-radius: 50%; font-size: 1.1rem;
            box-shadow: 0 4px 14px rgba(44,186,236,.35); cursor: pointer;
        }
        @media(max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main { margin-left: 0; padding: 18px 16px; }
            .menu-toggle { display: flex; align-items: center; justify-content: center; }
        }
    </style>
</head>
<body>

<button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>

<!-- SIDEBAR (IDÉNTICO A DASHBOARD_ADMIN.PHP) -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-inner">
        <div class="brand">
            <div class="brand-logo">D&F</div>
            <div class="brand-sub">mindspace</div>
            <div class="brand-tagline"><b>EXPLORA</b> · <b>CREA</b> · <b>APRENDE</b></div>
            <div class="admin-chip">⚙ ADMINISTRADOR</div>
        </div>

        <div class="user-row">
            <div class="avatar"><?= htmlspecialchars($iniciales) ?></div>
            <div>
                <div class="name"><?= htmlspecialchars($admin_nombre) ?></div>
                <div class="online"><span class="online-dot"></span>En línea</div>
            </div>
        </div>

        <div class="nav-label" style="margin-top:14px;">Principal</div>
        <ul class="nav flex-column">
            <li class="nav-item"><a href="dashboard_admin.php" class="nav-link"><i class="fas fa-rocket"></i> Panel</a></li>
            <li class="nav-item"><a href="admin_analytics.php" class="nav-link"><i class="fas fa-chart-line"></i> Analíticas</a></li>
        </ul>

        <div class="nav-label">Gestión</div>
        <ul class="nav flex-column">
            <li class="nav-item"><a href="admin_usuarios.php" class="nav-link"><i class="fas fa-users"></i> Usuarios</a></li>
            <li class="nav-item"><a href="admin_tutores.php" class="nav-link active"><i class="fas fa-graduation-cap"></i> Tutores</a></li>
            <li class="nav-item"><a href="admin_cursos.php" class="nav-link"><i class="fas fa-map-marked-alt"></i> Cursos</a></li>
            <li class="nav-item"><a href="admin_ingresos.php" class="nav-link"><i class="fas fa-coins"></i> Ingresos</a></li>
        </ul>

        <div class="nav-label">Sistema</div>
        <ul class="nav flex-column">
            <li class="nav-item"><a href="admin_alertas.php" class="nav-link"><i class="fas fa-bell"></i> Alertas</a></li>
            <li class="nav-item"><a href="admin_servidor.php" class="nav-link"><i class="fas fa-server"></i> Servidor</a></li>
            <li class="nav-item"><a href="admin_config.php" class="nav-link"><i class="fas fa-cog"></i> Configuración</a></li>
        </ul>
    </div>

    <div class="sidebar-footer">
        <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
    </div>
</div>

<!-- MAIN -->
<div class="main">

    <?php if ($mensaje): ?>
    <div class="alert alert-<?= $mensaje['tipo'] ?> alert-dismissible fade show" role="alert" style="border-radius:14px;">
        <?= htmlspecialchars($mensaje['texto']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="top">
        <div>
            <h1 class="page-title">Gestión de <span>tutores</span></h1>
            <p class="page-sub">Administra tutores y asigna cursos</p>
        </div>
        <button class="btn-add" onclick="abrirModalTutor()"><i class="fas fa-user-plus"></i> Nuevo tutor</button>
    </div>

    <!-- Filtros -->
    <form method="GET" class="filtros-bar">
        <div class="search-wrapper">
            <i class="fas fa-search"></i>
            <input type="text" name="busqueda" placeholder="Buscar por nombre o email..." value="<?= htmlspecialchars($busqueda) ?>">
        </div>
        <select name="estado" class="filter-select">
            <option value="">Todos los estados</option>
            <option value="1" <?= $estado_filtro === '1' ? 'selected' : '' ?>>Activos</option>
            <option value="0" <?= $estado_filtro === '0' ? 'selected' : '' ?>>Inactivos</option>
        </select>
        <button type="submit" class="btn-filtrar"><i class="fas fa-filter me-1"></i> Filtrar</button>
        <?php if (!empty($busqueda) || $estado_filtro !== ''): ?>
            <a href="admin_tutores.php" class="btn-filtrar" style="background:var(--gray-100);">Limpiar</a>
        <?php endif; ?>
    </form>

    <!-- Tarjetas de tutores -->
    <div class="tutores-grid">
        <?php foreach ($tutores as $tutor): 
            $inicialesT = strtoupper(substr($tutor['nombre'], 0, 1));
            $estadoClass = $tutor['activo'] ? 'active' : 'inactive';
            $estadoText = $tutor['activo'] ? 'Activo' : 'Inactivo';
            
            // Obtener cursos asignados a este tutor
            $cursos_tutor = mysqli_query($conn, "SELECT id, nombre FROM cursos WHERE id_tutor = {$tutor['id']}");
            $cursos_asignados = [];
            while ($c = mysqli_fetch_assoc($cursos_tutor)) {
                $cursos_asignados[] = $c;
            }
        ?>
        <div class="tutor-card">
            <div class="tutor-status">
                <span class="status-badge <?= $estadoClass ?>"><?= $estadoText ?></span>
            </div>
            <div class="tutor-header">
                <div class="tutor-avatar" style="background: linear-gradient(135deg, <?= $tutor['activo'] ? 'var(--primary), var(--dark-blue)' : '#bbb, #999' ?>);">
                    <?= $inicialesT ?>
                </div>
                <div class="tutor-info">
                    <h5><?= htmlspecialchars($tutor['nombre']) ?></h5>
                    <div class="tutor-email"><?= htmlspecialchars($tutor['email']) ?></div>
                </div>
            </div>
            
            <div class="cursos-asignados">
                <div class="cursos-title"><i class="fas fa-book-open me-1"></i> Cursos asignados (<?= count($cursos_asignados) ?>)</div>
                <?php if (!empty($cursos_asignados)): ?>
                    <?php foreach ($cursos_asignados as $curso): ?>
                        <span class="curso-tag">
                            <i class="fas fa-graduation-cap"></i> <?= htmlspecialchars($curso['nombre']) ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Desasignar este curso?');">
                                <input type="hidden" name="accion" value="desasignar_curso">
                                <input type="hidden" name="id_curso" value="<?= $curso['id'] ?>">
                                <button type="submit" style="background:none;border:none;color:var(--danger);cursor:pointer;margin-left:4px;" title="Desasignar"><i class="fas fa-times-circle"></i></button>
                            </form>
                        </span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="font-size:.75rem; color:#aaa;">Sin cursos asignados</div>
                <?php endif; ?>
            </div>
            
            <button class="btn-asignar" onclick="abrirModalAsignar(<?= $tutor['id'] ?>, '<?= htmlspecialchars($tutor['nombre'], ENT_QUOTES) ?>')">
                <i class="fas fa-plus-circle"></i> Asignar curso
            </button>
            
            <div class="tutor-actions">
                <button class="btn-card-action" onclick="editarTutor(<?= $tutor['id'] ?>, '<?= htmlspecialchars($tutor['nombre'], ENT_QUOTES) ?>', '<?= htmlspecialchars($tutor['email'], ENT_QUOTES) ?>', <?= $tutor['activo'] ?>)">
                    <i class="fas fa-pen"></i> Editar
                </button>
                <?php if ($tutor['id'] != $admin_id): ?>
                <form method="POST" style="flex:1;" onsubmit="return confirm('¿Cambiar estado del tutor?');">
                    <input type="hidden" name="accion" value="cambiar_estado">
                    <input type="hidden" name="id_tutor" value="<?= $tutor['id'] ?>">
                    <input type="hidden" name="activo" value="<?= $tutor['activo'] ? 0 : 1 ?>">
                    <button type="submit" class="btn-card-action <?= $tutor['activo'] ? 'danger' : '' ?>" style="width:100%;">
                        <i class="fas fa-<?= $tutor['activo'] ? 'ban' : 'check' ?>"></i> <?= $tutor['activo'] ? 'Suspender' : 'Activar' ?>
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($tutores)): ?>
            <div style="grid-column:1/-1; text-align:center; padding:40px; background:white; border-radius:18px; color:#aaa;">
                <i class="fas fa-user-graduate fa-2x mb-3"></i>
                <p>No se encontraron tutores con los filtros seleccionados.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Paginación -->
    <?php if ($total_paginas > 1): ?>
    <div class="pagination-wrap">
        <?php
        $query_params = $_GET;
        unset($query_params['pagina']);
        $base_url = 'admin_tutores.php?' . http_build_query($query_params);
        $base_url = $base_url ? $base_url . '&' : 'admin_tutores.php?';
        
        if ($pagina > 1) {
            echo '<a class="page-link" href="' . $base_url . 'pagina=' . ($pagina-1) . '"><i class="fas fa-chevron-left"></i></a>';
        }
        for ($i = max(1, $pagina-2); $i <= min($total_paginas, $pagina+2); $i++) {
            $active = $i == $pagina ? 'active' : '';
            echo '<a class="page-link ' . $active . '" href="' . $base_url . 'pagina=' . $i . '">' . $i . '</a>';
        }
        if ($pagina < $total_paginas) {
            echo '<a class="page-link" href="' . $base_url . 'pagina=' . ($pagina+1) . '"><i class="fas fa-chevron-right"></i></a>';
        }
        ?>
    </div>
    <?php endif; ?>
</div>

<!-- MODAL ASIGNAR CURSO -->
<div class="modal fade" id="asignarModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="accion" value="asignar_curso">
                <input type="hidden" name="id_tutor" id="asignarIdTutor">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-link me-2"></i>Asignar curso a <span id="nombreTutorAsignar"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Selecciona un curso</label>
                    <select name="id_curso" class="form-select" required>
                        <option value="">-- Selecciona --</option>
                        <?php 
                        mysqli_data_seek($cursos_disponibles, 0);
                        while ($c = mysqli_fetch_assoc($cursos_disponibles)): 
                        ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" style="background:var(--primary);border:none;">Asignar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL CREAR/EDITAR TUTOR -->
<div class="modal fade" id="tutorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="guardar_tutor" value="1">
                <input type="hidden" name="id_tutor" id="editTutorId">
                <div class="modal-header">
                    <h5 class="modal-title" id="tutorModalTitle"><i class="fas fa-user-plus me-2"></i>Nuevo tutor</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre completo *</label>
                        <input type="text" name="nombre" id="editNombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correo electrónico *</label>
                        <input type="email" name="email" id="editEmail" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña <span id="passHint">*</span></label>
                        <input type="password" name="password" id="editPassword" class="form-control">
                        <small id="passHelp" class="text-muted"></small>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="activo" id="editActivo" value="1" checked>
                            <label class="form-check-label" for="editActivo">Tutor activo</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" style="background:var(--primary);border:none;">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Sidebar toggle
const toggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');
toggle.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    toggle.innerHTML = sidebar.classList.contains('open') ? '<i class="fas fa-times"></i>' : '<i class="fas fa-bars"></i>';
});
document.addEventListener('click', e => {
    if (window.innerWidth < 992 && !sidebar.contains(e.target) && !toggle.contains(e.target) && sidebar.classList.contains('open')) {
        sidebar.classList.remove('open');
        toggle.innerHTML = '<i class="fas fa-bars"></i>';
    }
});

// Modal Asignar
let asignarModal;
document.addEventListener('DOMContentLoaded', () => {
    asignarModal = new bootstrap.Modal(document.getElementById('asignarModal'));
    tutorModal = new bootstrap.Modal(document.getElementById('tutorModal'));
});

function abrirModalAsignar(idTutor, nombreTutor) {
    document.getElementById('asignarIdTutor').value = idTutor;
    document.getElementById('nombreTutorAsignar').textContent = nombreTutor;
    asignarModal.show();
}

// Modal Tutor (crear/editar)
let tutorModal;

function abrirModalTutor() {
    document.getElementById('tutorModalTitle').innerHTML = '<i class="fas fa-user-plus me-2"></i>Nuevo tutor';
    document.getElementById('editTutorId').value = '';
    document.getElementById('editNombre').value = '';
    document.getElementById('editEmail').value = '';
    document.getElementById('editPassword').value = '';
    document.getElementById('editPassword').required = true;
    document.getElementById('passHint').textContent = '*';
    document.getElementById('passHelp').textContent = '';
    document.getElementById('editActivo').checked = true;
    tutorModal.show();
}

function editarTutor(id, nombre, email, activo) {
    document.getElementById('tutorModalTitle').innerHTML = '<i class="fas fa-user-edit me-2"></i>Editar tutor';
    document.getElementById('editTutorId').value = id;
    document.getElementById('editNombre').value = nombre;
    document.getElementById('editEmail').value = email;
    document.getElementById('editPassword').value = '';
    document.getElementById('editPassword').required = false;
    document.getElementById('passHint').textContent = '(dejar en blanco para no cambiar)';
    document.getElementById('passHelp').textContent = 'Deja vacío para mantener la contraseña actual.';
    document.getElementById('editActivo').checked = (activo == 1);
    tutorModal.show();
}
</script>
</body>
</html>