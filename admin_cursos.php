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

// --- PROCESAR ACCIONES POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'guardar_curso') {
        $id_curso = intval($_POST['id_curso'] ?? 0);
        $nombre = mysqli_real_escape_string($conn, trim($_POST['nombre']));
        $descripcion = mysqli_real_escape_string($conn, trim($_POST['descripcion']));
        $nivel = mysqli_real_escape_string($conn, $_POST['nivel']);
        $duracion_horas = intval($_POST['duracion_horas']);
        $id_tutor = !empty($_POST['id_tutor']) ? intval($_POST['id_tutor']) : 'NULL';
        $activo = isset($_POST['activo']) ? 1 : 0;
        
        if (empty($nombre)) {
            $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'El nombre del curso es obligatorio.'];
        } else {
            if ($id_curso > 0) {
                // Actualizar
                $update = "UPDATE cursos SET 
                    nombre = '$nombre', 
                    descripcion = '$descripcion', 
                    nivel = '$nivel', 
                    duracion_horas = $duracion_horas, 
                    id_tutor = $id_tutor, 
                    activo = $activo 
                    WHERE id = $id_curso";
                if (mysqli_query($conn, $update)) {
                    $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Curso actualizado correctamente.'];
                } else {
                    $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'Error al actualizar: ' . mysqli_error($conn)];
                }
            } else {
                // Insertar nuevo
                $insert = "INSERT INTO cursos (nombre, descripcion, nivel, duracion_horas, id_tutor, activo, fecha_creacion) 
                           VALUES ('$nombre', '$descripcion', '$nivel', $duracion_horas, $id_tutor, $activo, NOW())";
                if (mysqli_query($conn, $insert)) {
                    $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Curso creado exitosamente.'];
                } else {
                    $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'Error al crear: ' . mysqli_error($conn)];
                }
            }
        }
        header("Location: admin_cursos.php");
        exit();
    }
    
    if ($accion === 'cambiar_estado') {
        $id_curso = intval($_POST['id_curso']);
        $nuevo_estado = intval($_POST['activo']);
        mysqli_query($conn, "UPDATE cursos SET activo = $nuevo_estado WHERE id = $id_curso");
        $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Estado del curso actualizado.'];
        header("Location: admin_cursos.php");
        exit();
    }
    
    if ($accion === 'eliminar') {
        $id_curso = intval($_POST['id_curso']);
        // Verificar que no tenga actividades o inscripciones activas (opcional)
        mysqli_query($conn, "DELETE FROM cursos WHERE id = $id_curso");
        $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Curso eliminado permanentemente.'];
        header("Location: admin_cursos.php");
        exit();
    }
}

// --- FILTROS Y PAGINACIÓN ---
$busqueda = isset($_GET['busqueda']) ? mysqli_real_escape_string($conn, trim($_GET['busqueda'])) : '';
$estado_filtro = isset($_GET['estado']) ? $_GET['estado'] : '';
$tutor_filtro = isset($_GET['tutor']) ? intval($_GET['tutor']) : 0;

$where = [];
if (!empty($busqueda)) {
    $where[] = "(c.nombre LIKE '%$busqueda%' OR c.descripcion LIKE '%$busqueda%')";
}
if ($estado_filtro !== '') {
    $where[] = "c.activo = " . intval($estado_filtro);
}
if ($tutor_filtro > 0) {
    $where[] = "c.id_tutor = $tutor_filtro";
}
$where_sql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

// Total cursos
$total_query = "SELECT COUNT(*) AS total FROM cursos c $where_sql";
$total_res = mysqli_query($conn, $total_query);
$total_cursos = mysqli_fetch_assoc($total_res)['total'];

$por_pagina = 10;
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina - 1) * $por_pagina;
$total_paginas = ceil($total_cursos / $por_pagina);

// Obtener cursos con datos del tutor
$query_cursos = "
    SELECT c.*, u.nombre AS tutor_nombre 
    FROM cursos c 
    LEFT JOIN usuarios u ON c.id_tutor = u.id 
    $where_sql 
    ORDER BY c.fecha_creacion DESC 
    LIMIT $offset, $por_pagina
";
$res_cursos = mysqli_query($conn, $query_cursos);
$cursos = [];
while ($c = mysqli_fetch_assoc($res_cursos)) {
    // Contar alumnos inscritos
    $id_curso = $c['id'];
    $count_res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM inscripciones WHERE id_curso = $id_curso AND estado = 'activo'");
    $c['total_alumnos'] = mysqli_fetch_assoc($count_res)['total'];
    $cursos[] = $c;
}

// Obtener lista de tutores para filtro y asignación
$tutores_query = "SELECT id, nombre FROM usuarios WHERE tipo = 'tutor' AND activo = 1 ORDER BY nombre";
$tutores_res = mysqli_query($conn, $tutores_query);
$tutores = [];
while ($t = mysqli_fetch_assoc($tutores_res)) {
    $tutores[] = $t;
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
    <title>Gestión de Cursos - D&F Mindspace Admin</title>
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
            <li class="nav-item"><a href="admin_usuarios.php" class="nav-link"><i class="fas fa-users"></i> Usuarios</a></li>
            <li class="nav-item"><a href="admin_tutores.php" class="nav-link"><i class="fas fa-graduation-cap"></i> Tutores</a></li>
            <li class="nav-item"><a href="admin_cursos.php" class="nav-link active"><i class="fas fa-map-marked-alt"></i> Cursos</a></li>
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
            <h1 class="page-title">Gestión de <span>cursos</span></h1>
            <p class="page-sub">Administra los cursos, asigna tutores y controla su visibilidad</p>
        </div>
        <button class="btn-add" onclick="abrirModalCurso()"><i class="fas fa-plus-circle"></i> Nuevo curso</button>
    </div>

    <!-- Filtros -->
    <form method="GET" class="filtros-bar">
        <div class="search-wrapper">
            <i class="fas fa-search"></i>
            <input type="text" name="busqueda" placeholder="Buscar por nombre o descripción..." value="<?= htmlspecialchars($busqueda) ?>">
        </div>
        <select name="estado" class="filter-select">
            <option value="">Todos los estados</option>
            <option value="1" <?= $estado_filtro === '1' ? 'selected' : '' ?>>Activos</option>
            <option value="0" <?= $estado_filtro === '0' ? 'selected' : '' ?>>Inactivos</option>
        </select>
        <select name="tutor" class="filter-select">
            <option value="0">Todos los tutores</option>
            <?php foreach ($tutores as $t): ?>
                <option value="<?= $t['id'] ?>" <?= $tutor_filtro == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-filtrar"><i class="fas fa-filter me-1"></i> Filtrar</button>
        <?php if (!empty($busqueda) || $estado_filtro !== '' || $tutor_filtro > 0): ?>
            <a href="admin_cursos.php" class="btn-filtrar" style="background:var(--gray-100);">Limpiar</a>
        <?php endif; ?>
    </form>

    <!-- Tabla de cursos -->
    <div class="users-panel">
        <div class="users-head">
            <h6><i class="fas fa-book-open me-2"></i> Cursos registrados</h6>
            <span style="font-size:.8rem;color:var(--primary);">Total: <?= $total_cursos ?></span>
        </div>

        <div class="table-responsive">
            <table class="utbl">
                <thead>
                    <tr>
                        <th>Curso</th>
                        <th>Nivel</th>
                        <th>Duración</th>
                        <th>Tutor</th>
                        <th>Alumnos</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cursos as $curso): 
                        $estadoClass = $curso['activo'] ? 'active' : 'inactive';
                        $estadoText = $curso['activo'] ? 'Activo' : 'Inactivo';
                    ?>
                    <tr>
                        <td>
                            <div>
                                <div class="uname"><?= htmlspecialchars($curso['nombre']) ?></div>
                                <div style="font-size:.75rem;color:#aaa;max-width:250px;"><?= htmlspecialchars(substr($curso['descripcion'], 0, 60)) ?>...</div>
                            </div>
                        </td>
                        <td><span class="badge bg-light text-dark"><?= htmlspecialchars($curso['nivel']) ?></span></td>
                        <td><?= $curso['duracion_horas'] ?> horas</td>
                        <td><?= htmlspecialchars($curso['tutor_nombre'] ?? 'Sin asignar') ?></td>
                        <td><span class="badge bg-info"><?= $curso['total_alumnos'] ?></span></td>
                        <td>
                            <span class="status-tag <?= $estadoClass ?>">
                                <span class="status-dot-sm"></span> <?= $estadoText ?>
                            </span>
                        </td>
                        <td>
                            <div style="display:flex;gap:5px;">
                                <button class="act-btn" onclick="abrirModalCurso(<?= $curso['id'] ?>)" title="Editar"><i class="fas fa-pen"></i></button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Cambiar estado del curso?');">
                                    <input type="hidden" name="accion" value="cambiar_estado">
                                    <input type="hidden" name="id_curso" value="<?= $curso['id'] ?>">
                                    <input type="hidden" name="activo" value="<?= $curso['activo'] ? 0 : 1 ?>">
                                    <button type="submit" class="act-btn <?= $curso['activo'] ? 'danger' : '' ?>" title="<?= $curso['activo'] ? 'Desactivar' : 'Activar' ?>">
                                        <i class="fas fa-<?= $curso['activo'] ? 'ban' : 'check' ?>"></i>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este curso permanentemente? Se perderán todas las actividades asociadas.');">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id_curso" value="<?= $curso['id'] ?>">
                                    <button type="submit" class="act-btn danger" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($cursos)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">No se encontraron cursos con los filtros seleccionados.</td></tr>
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
                $base_url = 'admin_cursos.php?' . http_build_query($query_params);
                $base_url = $base_url ? $base_url . '&' : 'admin_cursos.php?';
                
                if ($pagina > 1) {
                    echo '<a class="page-link" href="' . $base_url . 'pagina=' . ($pagina-1) . '"><i class="fas fa-chevron-left"></i></a>';
                } else {
                    echo '<span class="page-link disabled"><i class="fas fa-chevron-left"></i></span>';
                }
                
                for ($i = max(1, $pagina-2); $i <= min($total_paginas, $pagina+2); $i++) {
                    $active = $i == $pagina ? 'active' : '';
                    echo '<a class="page-link ' . $active . '" href="' . $base_url . 'pagina=' . $i . '">' . $i . '</a>';
                }
                
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

<!-- MODAL CREAR/EDITAR CURSO -->
<div class="modal fade" id="cursoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="accion" value="guardar_curso">
                <input type="hidden" name="id_curso" id="modalIdCurso" value="">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle"><i class="fas fa-plus-circle me-2"></i>Nuevo curso</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre del curso *</label>
                        <input type="text" name="nombre" id="modalNombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea name="descripcion" id="modalDescripcion" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nivel *</label>
                            <select name="nivel" id="modalNivel" class="form-select" required>
                                <option value="Básico">Básico</option>
                                <option value="Intermedio">Intermedio</option>
                                <option value="Avanzado">Avanzado</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Duración (horas) *</label>
                            <input type="number" name="duracion_horas" id="modalDuracion" class="form-control" min="1" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tutor asignado</label>
                        <select name="id_tutor" id="modalTutor" class="form-select">
                            <option value="">-- Sin tutor --</option>
                            <?php foreach ($tutores as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="activo" id="modalActivo" value="1" checked>
                            <label class="form-check-label" for="modalActivo">Curso activo (visible para alumnos)</label>
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

// Modal curso
let cursoModal;
document.addEventListener('DOMContentLoaded', () => {
    cursoModal = new bootstrap.Modal(document.getElementById('cursoModal'));
});

// Cargar datos de cursos (para edición rápida sin AJAX)
const cursosData = <?= json_encode($cursos) ?>;

function abrirModalCurso(id = null) {
    const form = document.querySelector('#cursoModal form');
    const title = document.getElementById('modalTitle');
    const idInput = document.getElementById('modalIdCurso');
    const nombre = document.getElementById('modalNombre');
    const desc = document.getElementById('modalDescripcion');
    const nivel = document.getElementById('modalNivel');
    const duracion = document.getElementById('modalDuracion');
    const tutor = document.getElementById('modalTutor');
    const activo = document.getElementById('modalActivo');
    
    if (id) {
        // Editar: buscar curso en el array local
        let curso = cursosData.find(c => c.id == id);
        if (!curso) {
            // Si no está en la página actual, hacemos fetch
            fetch(`get_curso.php?id=${id}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) rellenarFormulario(data.curso);
                });
            return;
        }
        rellenarFormulario(curso);
        
        function rellenarFormulario(c) {
            title.innerHTML = '<i class="fas fa-edit me-2"></i>Editar curso';
            idInput.value = c.id;
            nombre.value = c.nombre;
            desc.value = c.descripcion || '';
            nivel.value = c.nivel;
            duracion.value = c.duracion_horas;
            tutor.value = c.id_tutor || '';
            activo.checked = c.activo == 1;
        }
    } else {
        // Nuevo
        title.innerHTML = '<i class="fas fa-plus-circle me-2"></i>Nuevo curso';
        idInput.value = '';
        nombre.value = '';
        desc.value = '';
        nivel.value = 'Básico';
        duracion.value = 10;
        tutor.value = '';
        activo.checked = true;
    }
    cursoModal.show();
}
</script>
</body>
</html>