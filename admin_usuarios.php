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

// --- PROCESAR ACCIONES POST (cambiar estado, eliminar) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $id_usuario = intval($_POST['id_usuario'] ?? 0);
    $accion = $_POST['accion'];
    
    if ($accion === 'cambiar_estado' && $id_usuario > 0) {
        $nuevo_estado = intval($_POST['activo'] ?? 1);
        $update = "UPDATE usuarios SET activo = $nuevo_estado WHERE id = $id_usuario AND id != $admin_id";
        mysqli_query($conn, $update);
        $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Estado actualizado correctamente.'];
    }
    elseif ($accion === 'eliminar' && $id_usuario > 0) {
        // No permitir eliminar al propio admin
        if ($id_usuario != $admin_id) {
            $delete = "DELETE FROM usuarios WHERE id = $id_usuario";
            mysqli_query($conn, $delete);
            $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Usuario eliminado permanentemente.'];
        } else {
            $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'No puedes eliminar tu propia cuenta.'];
        }
    }
    header("Location: admin_usuarios.php");
    exit();
}

// --- PROCESAR CREACIÓN/EDICIÓN DE USUARIO (vía modal) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_usuario'])) {
    $id_usuario = intval($_POST['id_usuario'] ?? 0);
    $nombre = mysqli_real_escape_string($conn, trim($_POST['nombre']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = $_POST['password'] ?? '';
    $tipo = mysqli_real_escape_string($conn, $_POST['tipo']);
    $activo = isset($_POST['activo']) ? 1 : 0;
    $fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? "'" . mysqli_real_escape_string($conn, $_POST['fecha_nacimiento']) . "'" : "NULL";
    $telefono = !empty($_POST['telefono']) ? "'" . mysqli_real_escape_string($conn, $_POST['telefono']) . "'" : "NULL";
    
    $errores = [];
    if (empty($nombre)) $errores[] = "El nombre es obligatorio.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = "Email inválido.";
    
    if (empty($errores)) {
        if ($id_usuario > 0) {
            // Actualizar usuario existente
            $update = "UPDATE usuarios SET 
                nombre = '$nombre', 
                email = '$email', 
                tipo = '$tipo', 
                activo = $activo,
                fecha_nacimiento = $fecha_nacimiento,
                telefono = $telefono
                WHERE id = $id_usuario";
            
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $update = "UPDATE usuarios SET 
                    nombre = '$nombre', 
                    email = '$email', 
                    password = '$hash',
                    tipo = '$tipo', 
                    activo = $activo,
                    fecha_nacimiento = $fecha_nacimiento,
                    telefono = $telefono
                    WHERE id = $id_usuario";
            }
            
            if (mysqli_query($conn, $update)) {
                $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Usuario actualizado correctamente.'];
            } else {
                $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'Error al actualizar: ' . mysqli_error($conn)];
            }
        } else {
            // Crear nuevo usuario
            $check_email = mysqli_query($conn, "SELECT id FROM usuarios WHERE email = '$email'");
            if (mysqli_num_rows($check_email) > 0) {
                $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'El email ya está registrado.'];
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $insert = "INSERT INTO usuarios (nombre, email, password, tipo, activo, fecha_nacimiento, telefono, fecha_registro) 
                           VALUES ('$nombre', '$email', '$hash', '$tipo', $activo, $fecha_nacimiento, $telefono, NOW())";
                if (mysqli_query($conn, $insert)) {
                    $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Usuario creado exitosamente.'];
                } else {
                    $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'Error al crear: ' . mysqli_error($conn)];
                }
            }
        }
    } else {
        $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => implode(' ', $errores)];
    }
    header("Location: admin_usuarios.php");
    exit();
}

// --- PARÁMETROS DE PAGINACIÓN Y FILTROS ---
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$por_pagina = 10;
$offset = ($pagina - 1) * $por_pagina;

$busqueda = isset($_GET['busqueda']) ? mysqli_real_escape_string($conn, trim($_GET['busqueda'])) : '';
$filtro_rol = isset($_GET['rol']) ? mysqli_real_escape_string($conn, $_GET['rol']) : '';
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';

$where = [];
if (!empty($busqueda)) {
    $where[] = "(nombre LIKE '%$busqueda%' OR email LIKE '%$busqueda%')";
}
if (!empty($filtro_rol)) {
    $where[] = "tipo = '$filtro_rol'";
}
if ($filtro_estado !== '') {
    $where[] = "activo = " . intval($filtro_estado);
}
$where_sql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

// Contar total de usuarios
$total_query = "SELECT COUNT(*) AS total FROM usuarios $where_sql";
$total_res = mysqli_query($conn, $total_query);
$total_usuarios = mysqli_fetch_assoc($total_res)['total'];
$total_paginas = ceil($total_usuarios / $por_pagina);

// Obtener usuarios de la página actual
$query_usuarios = "
    SELECT id, nombre, email, tipo, activo, fecha_registro, avatar 
    FROM usuarios 
    $where_sql 
    ORDER BY fecha_registro DESC 
    LIMIT $offset, $por_pagina
";
$res_usuarios = mysqli_query($conn, $query_usuarios);
$usuarios = [];
while ($u = mysqli_fetch_assoc($res_usuarios)) {
    $usuarios[] = $u;
}

// Función para estilo de avatar
function getAvatarStyle($tipo, $avatar = null) {
    $colors = [
        'alumno' => 'linear-gradient(135deg, #83bf46, #6ca839)',
        'tutor'  => 'linear-gradient(135deg, #2cbaec, #1a8db8)',
        'padre'  => 'linear-gradient(135deg, #f0ae2a, #d69925)',
        'admin'  => 'linear-gradient(135deg, #ff5757, #ff8c42)'
    ];
    return $colors[$tipo] ?? $colors['alumno'];
}

// Mensaje de sesión
$mensaje = $_SESSION['mensaje'] ?? null;
unset($_SESSION['mensaje']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - D&F Mindspace Admin</title>
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

        /* TABLA DE USUARIOS */
        .users-panel {
            background: white;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: var(--shadow);
            border: 1.5px solid rgba(44,186,236,.10);
        }
        .users-head {
            padding: 18px 22px;
            border-bottom: 1.5px solid rgba(44,186,236,.08);
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 10px;
        }
        .users-head h6 { font-weight: 700; font-size: .95rem; color: #222; margin: 0; }
        .utbl { width: 100%; border-collapse: collapse; }
        .utbl th {
            padding: 13px 20px;
            font-size: .78rem; font-weight: 700;
            color: var(--primary); text-transform: uppercase;
            letter-spacing: 1px;
            background: rgba(44,186,236,.04);
            border-bottom: 1.5px solid rgba(44,186,236,.08);
            white-space: nowrap;
        }
        .utbl td {
            padding: 14px 20px;
            font-size: .85rem;
            border-bottom: 1px solid rgba(44,186,236,.06);
            vertical-align: middle;
        }
        .utbl tbody tr:last-child td { border-bottom: none; }
        .utbl tbody tr:hover { background: rgba(44,186,236,.03); }
        .uname { font-weight: 600; color: #222; }
        .uemail { font-size: .75rem; color: #aaa; }
        .u-avatar {
            width: 34px; height: 34px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 700; font-size: .82rem; flex-shrink: 0;
        }
        .role-tag {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 10px; border-radius: 10px;
            font-size: .72rem; font-weight: 700; color: white; white-space: nowrap;
        }
        .role-tag.alumno  { background: linear-gradient(90deg, var(--accent), #6ca839); }
        .role-tag.tutor   { background: linear-gradient(90deg, var(--primary), var(--dark-blue)); }
        .role-tag.padre   { background: linear-gradient(90deg, var(--secondary), #d69925); }
        .role-tag.admin   { background: linear-gradient(90deg, var(--danger), #ff8c42); }
        .status-tag {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 10px; border-radius: 10px;
            font-size: .72rem; font-weight: 700;
        }
        .status-tag.active    { background: rgba(131,191,70,.12); color: #6ca839; }
        .status-tag.inactive  { background: rgba(180,180,180,.12); color: #999; }
        .status-dot-sm { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .act-btn {
            border: 1.5px solid rgba(44,186,236,.22);
            background: white; border-radius: 8px;
            padding: 5px 9px; font-size: .76rem;
            cursor: pointer; transition: all .2s; color: var(--primary);
        }
        .act-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }
        .act-btn.danger { color: var(--danger); border-color: rgba(255,87,87,.25); }
        .act-btn.danger:hover { background: var(--danger); color: white; border-color: var(--danger); }

        /* PAGINACIÓN */
        .pagination-wrap {
            padding: 14px 22px;
            border-top: 1.5px solid rgba(44,186,236,.08);
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 8px;
        }
        .pagination {
            display: flex; gap: 5px;
        }
        .page-link {
            padding: 6px 12px;
            border-radius: 8px;
            background: white;
            border: 1.5px solid rgba(44,186,236,.2);
            color: var(--primary);
            font-weight: 600;
            font-size: .8rem;
            text-decoration: none;
            transition: all .2s;
        }
        .page-link:hover, .page-link.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        .page-link.disabled {
            opacity: .5;
            pointer-events: none;
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
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(44,186,236,.12);
        }

        /* TOAST */
        .toast-container { z-index: 9999; }
        .toast {
            border: none;
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(15,23,42,.12);
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

<!-- SIDEBAR -->
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
            <li class="nav-item"><a href="admin_usuarios.php" class="nav-link active"><i class="fas fa-users"></i> Usuarios</a></li>
            <li class="nav-item"><a href="admin_tutores.php" class="nav-link"><i class="fas fa-graduation-cap"></i> Tutores</a></li>
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
            <h1 class="page-title">Gestión de <span>usuarios</span></h1>
            <p class="page-sub">Administra cuentas de alumnos, tutores, padres y administradores</p>
        </div>
        <button class="btn-add" onclick="abrirModalUsuario()"><i class="fas fa-user-plus"></i> Nuevo usuario</button>
    </div>

    <!-- Filtros -->
    <form method="GET" class="filtros-bar">
        <div class="search-wrapper">
            <i class="fas fa-search"></i>
            <input type="text" name="busqueda" placeholder="Buscar por nombre o email..." value="<?= htmlspecialchars($busqueda) ?>">
        </div>
        <select name="rol" class="filter-select">
            <option value="">Todos los roles</option>
            <option value="alumno" <?= $filtro_rol == 'alumno' ? 'selected' : '' ?>>Alumnos</option>
            <option value="tutor" <?= $filtro_rol == 'tutor' ? 'selected' : '' ?>>Tutores</option>
            <option value="padre" <?= $filtro_rol == 'padre' ? 'selected' : '' ?>>Padres</option>
            <option value="admin" <?= $filtro_rol == 'admin' ? 'selected' : '' ?>>Administradores</option>
        </select>
        <select name="estado" class="filter-select">
            <option value="">Todos los estados</option>
            <option value="1" <?= $filtro_estado === '1' ? 'selected' : '' ?>>Activos</option>
            <option value="0" <?= $filtro_estado === '0' ? 'selected' : '' ?>>Inactivos</option>
        </select>
        <button type="submit" class="btn-filtrar"><i class="fas fa-filter me-1"></i> Filtrar</button>
        <?php if (!empty($busqueda) || !empty($filtro_rol) || $filtro_estado !== ''): ?>
            <a href="admin_usuarios.php" class="btn-filtrar" style="background:var(--gray-100);">Limpiar</a>
        <?php endif; ?>
    </form>

    <!-- Tabla de usuarios -->
    <div class="users-panel">
        <div class="users-head">
            <h6><i class="fas fa-list me-2"></i> Usuarios registrados</h6>
            <span style="font-size:.8rem;color:var(--primary);">Total: <?= $total_usuarios ?></span>
        </div>

        <div class="table-responsive">
            <table class="utbl">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): 
                        $avatarBg = getAvatarStyle($u['tipo'], $u['avatar']);
                        $inicialesU = strtoupper(substr($u['nombre'], 0, 1));
                        $fecha = date('d M Y', strtotime($u['fecha_registro']));
                        $estadoClass = $u['activo'] ? 'active' : 'inactive';
                        $estadoText = $u['activo'] ? 'Activo' : 'Inactivo';
                    ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div class="u-avatar" style="background:<?= $avatarBg ?>;"><?= $inicialesU ?></div>
                                <div>
                                    <div class="uname"><?= htmlspecialchars($u['nombre']) ?></div>
                                    <div class="uemail"><?= htmlspecialchars($u['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="role-tag <?= $u['tipo'] ?>">
                                <i class="fas fa-<?= $u['tipo'] == 'alumno' ? 'child' : ($u['tipo'] == 'tutor' ? 'chalkboard-teacher' : ($u['tipo'] == 'padre' ? 'user-friends' : 'shield-alt')) ?>" style="font-size:.65rem;"></i> 
                                <?= ucfirst($u['tipo']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-tag <?= $estadoClass ?>">
                                <span class="status-dot-sm"></span> <?= $estadoText ?>
                            </span>
                        </td>
                        <td style="color:#aaa;font-size:.8rem;"><?= $fecha ?></td>
                        <td>
                            <div style="display:flex;gap:5px;">
                                <button class="act-btn" onclick="abrirModalUsuario(<?= $u['id'] ?>)" title="Editar"><i class="fas fa-pen"></i></button>
                                <?php if ($u['id'] != $admin_id): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Estás seguro de cambiar el estado de este usuario?');">
                                        <input type="hidden" name="accion" value="cambiar_estado">
                                        <input type="hidden" name="id_usuario" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="activo" value="<?= $u['activo'] ? 0 : 1 ?>">
                                        <button type="submit" class="act-btn <?= $u['activo'] ? 'danger' : '' ?>" title="<?= $u['activo'] ? 'Suspender' : 'Activar' ?>">
                                            <i class="fas fa-<?= $u['activo'] ? 'ban' : 'check' ?>"></i>
                                        </button>
                                    </form>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar permanentemente a <?= htmlspecialchars($u['nombre']) ?>? Esta acción no se puede deshacer.');">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id_usuario" value="<?= $u['id'] ?>">
                                        <button type="submit" class="act-btn danger" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                <?php else: ?>
                                    <span style="font-size:.7rem;color:#aaa;">(Tú)</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($usuarios)): ?>
                    <tr><td colspan="5" class="text-center py-4 text-muted">No se encontraron usuarios con los filtros seleccionados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <?php if ($total_paginas > 1): ?>
        <div class="pagination-wrap">
            <span>Página <?= $pagina ?> de <?= $total_paginas ?></span>
            <div class="pagination">
                <?php
                $query_params = $_GET;
                unset($query_params['pagina']);
                $base_url = 'admin_usuarios.php?' . http_build_query($query_params);
                $base_url = $base_url ? $base_url . '&' : 'admin_usuarios.php?';
                
                // Anterior
                if ($pagina > 1) {
                    echo '<a class="page-link" href="' . $base_url . 'pagina=' . ($pagina-1) . '"><i class="fas fa-chevron-left"></i></a>';
                } else {
                    echo '<span class="page-link disabled"><i class="fas fa-chevron-left"></i></span>';
                }
                
                // Páginas
                for ($i = max(1, $pagina-2); $i <= min($total_paginas, $pagina+2); $i++) {
                    $active = $i == $pagina ? 'active' : '';
                    echo '<a class="page-link ' . $active . '" href="' . $base_url . 'pagina=' . $i . '">' . $i . '</a>';
                }
                
                // Siguiente
                if ($pagina < $total_paginas) {
                    echo '<a class="page-link" href="' . $base_url . 'pagina=' . ($pagina+1) . '"><i class="fas fa-chevron-right"></i></a>';
                } else {
                    echo '<span class="page-link disabled"><i class="fas fa-chevron-right"></i></span>';
                }
                ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL CREAR/EDITAR USUARIO -->
<div class="modal fade" id="usuarioModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="formUsuario">
                <input type="hidden" name="guardar_usuario" value="1">
                <input type="hidden" name="id_usuario" id="modalIdUsuario" value="">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle"><i class="fas fa-user-plus me-2"></i>Nuevo usuario</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre completo *</label>
                        <input type="text" name="nombre" id="modalNombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correo electrónico *</label>
                        <input type="email" name="email" id="modalEmail" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contraseña <span id="passwordHint">*</span></label>
                            <input type="password" name="password" id="modalPassword" class="form-control">
                            <small id="passwordHelp" class="text-muted"></small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rol *</label>
                            <select name="tipo" id="modalTipo" class="form-select" required>
                                <option value="alumno">Alumno</option>
                                <option value="tutor">Tutor</option>
                                <option value="padre">Padre</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fecha de nacimiento</label>
                            <input type="date" name="fecha_nacimiento" id="modalFechaNac" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="telefono" id="modalTelefono" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="activo" id="modalActivo" value="1" checked>
                            <label class="form-check-label" for="modalActivo">Usuario activo</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" style="background:var(--primary);border:none;border-radius:12px;padding:10px 24px;font-weight:600;">
                        <i class="fas fa-save me-1"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- TOAST -->
<div class="toast-container position-fixed bottom-0 end-0 p-3"></div>

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

// Modal usuario
let usuarioModal;
document.addEventListener('DOMContentLoaded', () => {
    usuarioModal = new bootstrap.Modal(document.getElementById('usuarioModal'));
});

// Datos de usuarios para edición (inyectados desde PHP)
const usuariosData = <?= json_encode($usuarios) ?>;

function abrirModalUsuario(id = null) {
    const form = document.getElementById('formUsuario');
    const title = document.getElementById('modalTitle');
    const idInput = document.getElementById('modalIdUsuario');
    const nombre = document.getElementById('modalNombre');
    const email = document.getElementById('modalEmail');
    const password = document.getElementById('modalPassword');
    const tipo = document.getElementById('modalTipo');
    const fechaNac = document.getElementById('modalFechaNac');
    const telefono = document.getElementById('modalTelefono');
    const activo = document.getElementById('modalActivo');
    const passHint = document.getElementById('passwordHint');
    const passHelp = document.getElementById('passwordHelp');
    
    if (id) {
        // Edición: buscar usuario en el array (o podrías hacer fetch, pero usamos los datos cargados)
        let usuario = usuariosData.find(u => u.id == id);
        if (!usuario) {
            // Si no está en la página actual, podrías hacer una petición AJAX. Por simplicidad, asumimos que está.
            // Podríamos recargar la página con un parámetro? Mejor: hacer fetch rápido.
            fetch(`get_usuario.php?id=${id}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) rellenarFormulario(data.usuario);
                });
            return;
        }
        rellenarFormulario(usuario);
        
        function rellenarFormulario(u) {
            title.innerHTML = '<i class="fas fa-user-edit me-2"></i>Editar usuario';
            idInput.value = u.id;
            nombre.value = u.nombre;
            email.value = u.email;
            password.value = '';
            password.required = false;
            passHint.textContent = '(dejar en blanco para no cambiar)';
            passHelp.textContent = 'Deja vacío para mantener la contraseña actual.';
            tipo.value = u.tipo;
            fechaNac.value = u.fecha_nacimiento || '';
            telefono.value = u.telefono || '';
            activo.checked = u.activo == 1;
        }
    } else {
        // Nuevo usuario
        title.innerHTML = '<i class="fas fa-user-plus me-2"></i>Nuevo usuario';
        idInput.value = '';
        nombre.value = '';
        email.value = '';
        password.value = '';
        password.required = true;
        passHint.textContent = '*';
        passHelp.textContent = '';
        tipo.value = 'alumno';
        fechaNac.value = '';
        telefono.value = '';
        activo.checked = true;
    }
    usuarioModal.show();
}

// Toast para mensajes (si se requiere)
function showToast(msg, type = 'info') {
    const colors = {
        success: { bg: '#83bf46', icon: 'circle-check' },
        danger:  { bg: '#ff5757', icon: 'circle-exclamation' },
        info:    { bg: '#2cbaec', icon: 'circle-info' },
    };
    const c = colors[type] || colors.info;
    const id = 't' + Date.now();
    const container = document.querySelector('.toast-container');
    container.insertAdjacentHTML('beforeend', `
        <div id="${id}" class="toast" role="alert" data-bs-autohide="true" data-bs-delay="3500">
            <div class="toast-header" style="background:${c.bg};color:white;border-radius:13px 13px 0 0;">
                <i class="fas fa-${c.icon} me-2"></i>
                <strong class="me-auto">Admin</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">${msg}</div>
        </div>
    `);
    const el = document.getElementById(id);
    new bootstrap.Toast(el).show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
}
</script>

<!-- Archivo auxiliar get_usuario.php (opcional, para edición si el usuario no está en la página actual) -->
<?php
// Si se accede directamente a get_usuario.php, devolver JSON
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    // Este bloque se ejecutará solo si se llama a este mismo archivo con ?ajax=1&id=...
    // Pero para simplificar, podemos crear un archivo separado. Lo dejamos como sugerencia.
}
?>
</body>
</html>