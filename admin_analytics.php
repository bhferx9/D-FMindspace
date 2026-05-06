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

// --- CONSULTAS PARA GRÁFICOS Y ESTADÍSTICAS ---

// 1. Usuarios nuevos por mes (últimos 6 meses)
$usuarios_meses = [];
$meses_labels = [];
for ($i = 5; $i >= 0; $i--) {
    $mes = date('Y-m', strtotime("-$i months"));
    $meses_labels[] = date('M Y', strtotime($mes));
    $query = "SELECT COUNT(*) AS total FROM usuarios WHERE DATE_FORMAT(fecha_registro, '%Y-%m') = '$mes'";
    $res = mysqli_query($conn, $query);
    $usuarios_meses[] = mysqli_fetch_assoc($res)['total'];
}

// 2. Distribución de usuarios por rol
$query_roles = "SELECT tipo, COUNT(*) AS total FROM usuarios GROUP BY tipo";
$res_roles = mysqli_query($conn, $query_roles);
$roles_data = [];
while ($r = mysqli_fetch_assoc($res_roles)) {
    $roles_data[$r['tipo']] = $r['total'];
}

// 3. Actividad semanal: entregas realizadas por día (últimos 7 días)
$entregas_semana = [];
$dias_labels = [];
for ($i = 6; $i >= 0; $i--) {
    $fecha = date('Y-m-d', strtotime("-$i days"));
    $dias_labels[] = date('D', strtotime($fecha));
    $query = "SELECT COUNT(*) AS total FROM entregas WHERE DATE(fecha_entrega) = '$fecha'";
    $res = mysqli_query($conn, $query);
    $entregas_semana[] = mysqli_fetch_assoc($res)['total'];
}

// 4. Top 5 cursos con más inscripciones
$query_top_cursos = "
    SELECT c.nombre, COUNT(i.id) AS inscripciones 
    FROM cursos c 
    JOIN inscripciones i ON c.id = i.id_curso 
    WHERE i.estado = 'activo' 
    GROUP BY c.id 
    ORDER BY inscripciones DESC 
    LIMIT 5
";
$res_top_cursos = mysqli_query($conn, $query_top_cursos);
$top_cursos_nombres = [];
$top_cursos_inscripciones = [];
while ($c = mysqli_fetch_assoc($res_top_cursos)) {
    $top_cursos_nombres[] = $c['nombre'];
    $top_cursos_inscripciones[] = $c['inscripciones'];
}

// 5. Promedio de calificaciones por curso (top 5 con más evaluaciones)
$query_prom_cursos = "
    SELECT c.nombre, AVG(ev.calificacion) AS promedio, COUNT(ev.id) AS evaluaciones
    FROM cursos c
    JOIN actividades a ON a.id_curso = c.id
    JOIN entregas en ON en.id_actividad = a.id
    JOIN evaluaciones ev ON ev.id_entrega = en.id
    GROUP BY c.id
    HAVING evaluaciones > 0
    ORDER BY evaluaciones DESC
    LIMIT 5
";
$res_prom_cursos = mysqli_query($conn, $query_prom_cursos);
$prom_cursos_nombres = [];
$prom_cursos_promedios = [];
while ($c = mysqli_fetch_assoc($res_prom_cursos)) {
    $prom_cursos_nombres[] = $c['nombre'];
    $prom_cursos_promedios[] = round($c['promedio'], 1);
}

// 6. Total de actividades por tipo
$query_tipos_act = "SELECT tipo, COUNT(*) AS total FROM actividades GROUP BY tipo";
$res_tipos = mysqli_query($conn, $query_tipos_act);
$tipos_labels = [];
$tipos_totales = [];
while ($t = mysqli_fetch_assoc($res_tipos)) {
    $tipos_labels[] = $t['tipo'];
    $tipos_totales[] = $t['total'];
}

// 7. Ingresos mensuales (simulados para gráfico)
$ingresos_meses = [28500, 31200, 29800, 34500, 38900, 42500, 48000];
$meses_ingresos = ['Sep', 'Oct', 'Nov', 'Dic', 'Ene', 'Feb', 'Mar'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analíticas · D&F Mindspace Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;800&family=Poppins:wght@400;500;600&family=Fredoka+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
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
 
    /* SIDEBAR */
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
    .nav-link .badge-pill {
        margin-left: auto;
        background: var(--danger);
        color: white;
        border-radius: 20px;
        padding: 2px 9px;
        font-size: .68rem;
        font-weight: 700;
        animation: pulsePill 2s infinite;
    }
    @keyframes pulsePill { 0%,100%{transform:scale(1)}50%{transform:scale(1.1)} }
 
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
 
    /* TOP */
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
 
    /* SERVER BAR */
    .server-bar {
        background: white;
        border-radius: 16px;
        padding: 16px 24px;
        display: flex; align-items: center;
        box-shadow: var(--shadow);
        border: 1.5px solid rgba(44,186,236,.12);
        margin-bottom: 24px;
        flex-wrap: wrap;
        gap: 8px;
    }
    .server-label {
        font-size: .72rem; font-weight: 700;
        color: var(--primary); text-transform: uppercase;
        letter-spacing: 2px; margin-right: 8px;
        display: flex; align-items: center; gap: 7px;
        flex-shrink: 0;
    }
    .server-dot { width: 8px; height: 8px; background: var(--accent); border-radius: 50%; animation: blink 2s infinite; }
    .sep { width: 1px; height: 32px; background: rgba(44,186,236,.13); margin: 0 16px; flex-shrink: 0; }
    .srv-metric { display: flex; flex-direction: column; align-items: center; flex-shrink: 0; }
    .srv-metric .slabel { font-size: .67rem; color: #bbb; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
    .srv-metric .sval { font-family: 'Nunito', sans-serif; font-weight: 800; font-size: 1.1rem; }
    .sval.g { color: var(--accent); }
    .sval.b { color: var(--primary); }
    .sval.o { color: var(--secondary); }
    .sval.r { color: var(--danger); }
 
    /* METRIC CARDS */
    .metric-card {
        background: white;
        border-radius: 18px;
        padding: 22px 20px 18px;
        box-shadow: var(--shadow);
        border-top: 4px solid transparent;
        transition: transform .25s, box-shadow .25s;
        height: 100%;
        position: relative;
    }
    .metric-card:hover { transform: translateY(-6px); box-shadow: 0 14px 32px rgba(44,186,236,.15); }
    .metric-card.c-blue  { border-top-color: var(--primary); }
    .metric-card.c-green { border-top-color: var(--accent); }
    .metric-card.c-orange{ border-top-color: var(--secondary); }
    .metric-card.c-red   { border-top-color: var(--danger); }
 
    .mc-icon {
        width: 50px; height: 50px; border-radius: 14px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.3rem; color: white; margin-bottom: 14px;
    }
    .mc-icon.blue   { background: linear-gradient(135deg, var(--primary), var(--dark-blue)); box-shadow: 0 5px 14px rgba(44,186,236,.3); }
    .mc-icon.green  { background: linear-gradient(135deg, var(--accent), #6ca839); box-shadow: 0 5px 14px rgba(131,191,70,.3); }
    .mc-icon.orange { background: linear-gradient(135deg, var(--secondary), #d69925); box-shadow: 0 5px 14px rgba(240,174,42,.3); }
    .mc-icon.red    { background: linear-gradient(135deg, var(--danger), #ff8c42); box-shadow: 0 5px 14px rgba(255,87,87,.25); }
 
    .mc-num { font-family: 'Nunito', sans-serif; font-weight: 800; font-size: 2.2rem; color: #1a1a2e; line-height: 1; margin-bottom: 4px; }
    .mc-title { font-weight: 600; font-size: .9rem; color: #444; }
    .mc-sub { font-size: .76rem; color: #aaa; margin-top: 3px; }
 
    .trend {
        position: absolute; top: 16px; right: 16px;
        font-size: .7rem; font-weight: 700;
        padding: 3px 9px; border-radius: 20px;
        display: flex; align-items: center; gap: 3px;
    }
    .trend.up { background: rgba(131,191,70,.12); color: #6ca839; }
    .trend.down { background: rgba(255,87,87,.12); color: var(--danger); }
    .trend.neu { background: rgba(240,174,42,.12); color: #d69925; }
 
    /* SECTION TITLE */
    .sec-title {
        font-family: 'Nunito', sans-serif;
        font-weight: 800; font-size: 1.15rem; color: #1a1a2e;
        display: flex; align-items: center; gap: 9px;
        margin: 28px 0 14px;
    }
    .sec-title i { color: var(--primary); font-size: 1rem; }
 
    /* ALERTS PANEL */
    .alerts-panel {
        background: white;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: var(--shadow);
        border: 1.5px solid rgba(44,186,236,.10);
        height: 100%;
    }
    .alerts-head {
        padding: 16px 20px;
        border-bottom: 1.5px solid rgba(44,186,236,.08);
        display: flex; align-items: center; justify-content: space-between;
    }
    .alerts-head h6 { font-weight: 700; font-size: .95rem; color: #222; margin: 0; display: flex; align-items: center; gap: 8px; }
    .alerts-head h6 i { color: var(--danger); }
 
    .alert-item {
        display: flex; align-items: flex-start; gap: 12px;
        padding: 14px 20px;
        border-bottom: 1px solid rgba(44,186,236,.06);
        transition: background .2s;
    }
    .alert-item:last-child { border-bottom: none; }
    .alert-item:hover { background: rgba(44,186,236,.03); }
 
    .alert-icon {
        width: 34px; height: 34px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: .8rem; color: white; flex-shrink: 0;
    }
    .alert-icon.crit { background: linear-gradient(135deg, var(--danger), #ff8c42); }
    .alert-icon.warn { background: linear-gradient(135deg, var(--secondary), #d69925); }
    .alert-icon.info { background: linear-gradient(135deg, var(--primary), var(--dark-blue)); }
 
    .alert-body { flex: 1; }
    .alert-body strong { display: block; font-size: .84rem; font-weight: 600; color: #222; }
    .alert-body small { font-size: .74rem; color: #aaa; }
 
    .resolve-btn {
        border: 1.5px solid rgba(44,186,236,.25);
        background: white; color: var(--primary);
        border-radius: 8px; padding: 4px 10px;
        font-size: .72rem; font-weight: 600; cursor: pointer;
        transition: all .2s; flex-shrink: 0;
        align-self: center;
    }
    .resolve-btn:hover { background: var(--primary); color: white; }
 
    /* USERS TABLE */
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
    .users-head h6 { font-weight: 700; font-size: .95rem; color: #222; margin: 0; display: flex; align-items: center; gap: 8px; }
    .users-head h6 i { color: var(--primary); }
 
    .search-bar {
        display: flex; align-items: center; gap: 8px;
        background: rgba(44,186,236,.06);
        border: 1.5px solid rgba(44,186,236,.15);
        border-radius: 10px; padding: 6px 12px;
    }
    .search-bar input {
        border: none; background: transparent; outline: none;
        font-size: .82rem; font-family: 'Poppins', sans-serif;
        color: #333; width: 160px;
    }
    .search-bar i { color: #aaa; font-size: .8rem; }
 
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
 
    .status-tag {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 3px 10px; border-radius: 10px;
        font-size: .72rem; font-weight: 700;
    }
    .status-tag.active    { background: rgba(131,191,70,.12); color: #6ca839; }
    .status-tag.inactive  { background: rgba(180,180,180,.12); color: #999; }
    .status-tag.suspended { background: rgba(255,87,87,.12); color: var(--danger); }
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
 
    .tbl-footer {
        padding: 14px 22px;
        border-top: 1.5px solid rgba(44,186,236,.08);
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 8px;
    }
    .tbl-footer span { font-size: .78rem; color: #aaa; }
    .tbl-footer a { font-size: .82rem; font-weight: 600; color: var(--primary); text-decoration: none; }
    .tbl-footer a:hover { text-decoration: underline; }
 
    /* Mobile */
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
        /* Estilos adicionales para analytics */
        .chart-container {
            background: white;
            border-radius: 18px;
            padding: 20px;
            box-shadow: var(--shadow);
            border: 1.5px solid rgba(44,186,236,0.10);
            margin-bottom: 24px;
            height: 100%;
        }
        .chart-title {
            font-family: 'Nunito', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            color: #1a1a2e;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .chart-title i {
            color: var(--primary);
        }
        .small-chart {
            min-height: 200px;
        }
    </style>
</head>
<body>

<button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>

<!-- SIDEBAR (idéntico a dashboard_admin.php, con active en Analytics) -->
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
            <li class="nav-item"><a href="admin_analytics.php" class="nav-link active"><i class="fas fa-chart-line"></i> Analíticas</a></li>
        </ul>

        <div class="nav-label">Gestión</div>
        <ul class="nav flex-column">
            <li class="nav-item"><a href="admin_usuarios.php" class="nav-link"><i class="fas fa-users"></i> Usuarios</a></li>
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

    <div class="top">
        <div>
            <h1 class="page-title">Analíticas <span>avanzadas</span></h1>
            <p class="page-sub"><?= date('l j \d\e F, Y') ?> · Datos en tiempo real</p>
        </div>
        <a href="#" class="btn-add" onclick="exportarReporte()"><i class="fas fa-download"></i> Exportar reporte</a>
    </div>

    <!-- Fila 1: Usuarios nuevos + Distribución por rol -->
    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <div class="chart-container">
                <div class="chart-title"><i class="fas fa-user-plus"></i> Nuevos usuarios (últimos 6 meses)</div>
                <canvas id="usuariosChart" style="max-height: 250px;"></canvas>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="chart-container">
                <div class="chart-title"><i class="fas fa-pie-chart"></i> Distribución por rol</div>
                <canvas id="rolesChart" style="max-height: 220px;"></canvas>
                <div class="mt-3">
                    <?php foreach ($roles_data as $rol => $total): ?>
                    <div style="display:flex; justify-content:space-between; font-size:.85rem;">
                        <span><?= ucfirst($rol) ?></span>
                        <strong><?= $total ?></strong>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Fila 2: Actividad semanal + Tipos de actividad -->
    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <div class="chart-container">
                <div class="chart-title"><i class="fas fa-calendar-week"></i> Entregas realizadas (última semana)</div>
                <canvas id="entregasSemanaChart" style="max-height: 200px;"></canvas>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="chart-container">
                <div class="chart-title"><i class="fas fa-tasks"></i> Actividades por tipo</div>
                <canvas id="tiposActChart" style="max-height: 200px;"></canvas>
            </div>
        </div>
    </div>

    <!-- Fila 3: Top cursos + Promedio calificaciones -->
    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="chart-container">
                <div class="chart-title"><i class="fas fa-trophy"></i> Cursos con más inscripciones</div>
                <canvas id="topCursosChart" style="max-height: 220px;"></canvas>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="chart-container">
                <div class="chart-title"><i class="fas fa-star"></i> Promedio de calificaciones por curso (top 5)</div>
                <canvas id="promCursosChart" style="max-height: 220px;"></canvas>
            </div>
        </div>
    </div>

    <!-- Fila 4: Ingresos (simulado) -->
    <div class="row g-3">
        <div class="col-12">
            <div class="chart-container">
                <div class="chart-title"><i class="fas fa-coins"></i> Ingresos mensuales (MXN)</div>
                <canvas id="ingresosChart" style="max-height: 200px;"></canvas>
            </div>
        </div>
    </div>

    <div style="height:36px;"></div>
</div>

<script>
// Sidebar toggle (mismo que en dashboard_admin)
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

// Datos desde PHP
const usuariosMeses = <?= json_encode($usuarios_meses) ?>;
const mesesLabels = <?= json_encode($meses_labels) ?>;
const rolesLabels = <?= json_encode(array_keys($roles_data)) ?>;
const rolesTotales = <?= json_encode(array_values($roles_data)) ?>;
const entregasSemana = <?= json_encode($entregas_semana) ?>;
const diasLabels = <?= json_encode($dias_labels) ?>;
const tiposLabels = <?= json_encode($tipos_labels) ?>;
const tiposTotales = <?= json_encode($tipos_totales) ?>;
const topCursosNombres = <?= json_encode($top_cursos_nombres) ?>;
const topCursosInscripciones = <?= json_encode($top_cursos_inscripciones) ?>;
const promCursosNombres = <?= json_encode($prom_cursos_nombres) ?>;
const promCursosPromedios = <?= json_encode($prom_cursos_promedios) ?>;
const ingresosMeses = <?= json_encode($meses_ingresos) ?>;
const ingresosValores = <?= json_encode($ingresos_meses) ?>;

// Gráfico 1: Usuarios nuevos
new Chart(document.getElementById('usuariosChart'), {
    type: 'line',
    data: {
        labels: mesesLabels,
        datasets: [{
            label: 'Nuevos usuarios',
            data: usuariosMeses,
            borderColor: '#2cbaec',
            backgroundColor: 'rgba(44,186,236,0.1)',
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
        }
    }
});

// Gráfico 2: Distribución por rol (doughnut)
new Chart(document.getElementById('rolesChart'), {
    type: 'doughnut',
    data: {
        labels: rolesLabels.map(r => r.charAt(0).toUpperCase() + r.slice(1)),
        datasets: [{
            data: rolesTotales,
            backgroundColor: ['#83bf46', '#2cbaec', '#f0ae2a', '#ff5757'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

// Gráfico 3: Entregas semanales (bar)
new Chart(document.getElementById('entregasSemanaChart'), {
    type: 'bar',
    data: {
        labels: diasLabels,
        datasets: [{
            label: 'Entregas',
            data: entregasSemana,
            backgroundColor: '#83bf46',
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        }
    }
});

// Gráfico 4: Tipos de actividad (polarArea)
new Chart(document.getElementById('tiposActChart'), {
    type: 'polarArea',
    data: {
        labels: tiposLabels,
        datasets: [{
            data: tiposTotales,
            backgroundColor: ['#2cbaec', '#f0ae2a', '#83bf46', '#ff5757', '#9c88ff']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

// Gráfico 5: Top cursos (horizontal bar)
new Chart(document.getElementById('topCursosChart'), {
    type: 'bar',
    data: {
        labels: topCursosNombres,
        datasets: [{
            label: 'Inscripciones activas',
            data: topCursosInscripciones,
            backgroundColor: '#f0ae2a',
            borderRadius: 8
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: {
            legend: { display: false }
        }
    }
});

// Gráfico 6: Promedio calificaciones (bar)
new Chart(document.getElementById('promCursosChart'), {
    type: 'bar',
    data: {
        labels: promCursosNombres,
        datasets: [{
            label: 'Promedio (0-10)',
            data: promCursosPromedios,
            backgroundColor: '#2cbaec',
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { min: 0, max: 10 }
        },
        plugins: {
            legend: { display: false }
        }
    }
});

// Gráfico 7: Ingresos (line)
new Chart(document.getElementById('ingresosChart'), {
    type: 'line',
    data: {
        labels: ingresosMeses,
        datasets: [{
            label: 'Ingresos (MXN)',
            data: ingresosValores,
            borderColor: '#ff5757',
            backgroundColor: 'rgba(255,87,87,0.1)',
            borderWidth: 3,
            pointBackgroundColor: '#ff5757',
            tension: 0.3,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: {
            tooltip: {
                callbacks: {
                    label: (ctx) => `$${ctx.raw.toLocaleString()} MXN`
                }
            }
        }
    }
});

function exportarReporte() {
    alert('Funcionalidad de exportación en desarrollo.');
}
</script>
</body>
</html>