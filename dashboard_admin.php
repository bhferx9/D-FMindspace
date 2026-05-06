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

// --- CONSULTAS PARA MÉTRICAS ---
// Total de usuarios
$total_usuarios = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM usuarios"))['total'];

// Nuevos este mes (registros en los últimos 30 días)
$nuevos_mes = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) AS total FROM usuarios WHERE fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
))['total'];

// Cursos activos
$cursos_activos = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) AS total FROM cursos WHERE activo = 1"
))['total'];

// Cursos con alumnos inscritos (activos)
$cursos_con_alumnos = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(DISTINCT c.id) AS total 
     FROM cursos c 
     JOIN inscripciones i ON c.id = i.id_curso 
     WHERE c.activo = 1 AND i.estado = 'activo'"
))['total'];

// Ingresos del mes (simulado – podrías tener tabla de pagos)
$ingresos_mes = 48000; // Placeholder
$meta_ingresos = 55000;

// Alertas pendientes (simuladas, puedes crear tabla alertas_admin)
$alertas_pendientes = 7;
$alertas_criticas = 2;

// --- OBTENER ÚLTIMOS 5 USUARIOS REGISTRADOS ---
$query_ultimos = "
    SELECT id, nombre, email, tipo, activo, fecha_registro, avatar 
    FROM usuarios 
    ORDER BY fecha_registro DESC 
    LIMIT 5
";
$res_ultimos = mysqli_query($conn, $query_ultimos);
$ultimos_usuarios = [];
while ($u = mysqli_fetch_assoc($res_ultimos)) {
    $ultimos_usuarios[] = $u;
}

// Función para avatar emoji/color según tipo
function getAvatarStyle($tipo, $avatar = null) {
    $colors = [
        'alumno' => 'linear-gradient(135deg, #83bf46, #6ca839)',
        'tutor'  => 'linear-gradient(135deg, #2cbaec, #1a8db8)',
        'padre'  => 'linear-gradient(135deg, #f0ae2a, #d69925)',
        'admin'  => 'linear-gradient(135deg, #ff5757, #ff8c42)'
    ];
    $emojis = [
        'alumno' => '🧑‍🎓',
        'tutor'  => '👩‍🏫',
        'padre'  => '👪',
        'admin'  => '🛡️'
    ];
    return [
        'bg' => $colors[$tipo] ?? $colors['alumno'],
        'icon' => $emojis[$tipo] ?? '👤'
    ];
}

// --- SIMULACIÓN DE ALERTAS (puedes leer de tabla alertas_admin) ---
$alertas = [
    ['tipo' => 'crit', 'icono' => 'shield-alt', 'mensaje' => '3 intentos de acceso fallidos', 'detalle' => 'IP: 192.168.1.45 · hace 1 hr'],
    ['tipo' => 'crit', 'icono' => 'database', 'mensaje' => 'Almacenamiento al 87%', 'detalle' => 'Base de datos · hace 3 hrs'],
    ['tipo' => 'warn', 'icono' => 'user-slash', 'mensaje' => 'Cuenta suspendida activa', 'detalle' => 'j.moreno@email.com · hace 5 hrs'],
    ['tipo' => 'warn', 'icono' => 'clock', 'mensaje' => 'Backup vencido (48 hrs)', 'detalle' => 'Último: 25 mar · Programado: diario'],
    ['tipo' => 'info', 'icono' => 'envelope', 'mensaje' => '5 correos de bienvenida pendientes', 'detalle' => 'Cola de envío · hace 6 hrs'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - D&F Mindspace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;800&family=Poppins:wght@400;500;600&family=Fredoka+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            <li class="nav-item"><a href="admin_dashboard.php" class="nav-link active"><i class="fas fa-rocket"></i> Panel</a></li>
            <li class="nav-item"><a href="admin_analytics.php" class="nav-link"><i class="fas fa-chart-line"></i> Analíticas</a></li>
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
            <li class="nav-item">
                <a href="admin_alertas.php" class="nav-link">
                    <i class="fas fa-bell"></i> Alertas
                    <span class="badge-pill"><?= $alertas_pendientes ?></span>
                </a>
            </li>
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
            <h1 class="page-title">Panel de <span>administración</span></h1>
            <p class="page-sub"><?= date('l j \d\e F, Y') ?> · Actualizado hace 2 min</p>
        </div>
        <a href="admin_usuarios.php?accion=nuevo" class="btn-add"><i class="fas fa-user-plus"></i> Nuevo usuario</a>
    </div>

    <!-- Server bar (simulado) -->
    <div class="server-bar">
        <div class="server-label"><span class="server-dot"></span> Servidor</div>
        <div class="sep"></div>
        <div class="srv-metric"><span class="slabel">Uptime</span><span class="sval g">99.8%</span></div>
        <div class="sep"></div>
        <div class="srv-metric"><span class="slabel">CPU</span><span class="sval b" id="cpu">36%</span></div>
        <div class="sep"></div>
        <div class="srv-metric"><span class="slabel">RAM</span><span class="sval o">2.1 GB</span></div>
        <div class="sep"></div>
        <div class="srv-metric"><span class="slabel">Errores hoy</span><span class="sval r">3</span></div>
    </div>

    <!-- Metric cards -->
    <div class="row g-3 mb-2">
        <div class="col-6 col-xl-3">
            <div class="metric-card c-blue">
                <div class="trend up"><i class="fas fa-arrow-up" style="font-size:.6rem;"></i> +12%</div>
                <div class="mc-icon blue"><i class="fas fa-users"></i></div>
                <div class="mc-num"><?= number_format($total_usuarios) ?></div>
                <div class="mc-title">Usuarios totales</div>
                <div class="mc-sub">+<?= $nuevos_mes ?> nuevos este mes</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="metric-card c-green">
                <div class="trend up"><i class="fas fa-arrow-up" style="font-size:.6rem;"></i> +8%</div>
                <div class="mc-icon green"><i class="fas fa-compass"></i></div>
                <div class="mc-num"><?= $cursos_activos ?></div>
                <div class="mc-title">Cursos activos</div>
                <div class="mc-sub"><?= $cursos_con_alumnos ?> con alumnos inscritos</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="metric-card c-orange">
                <div class="trend neu"><i class="fas fa-minus" style="font-size:.6rem;"></i> estable</div>
                <div class="mc-icon orange"><i class="fas fa-coins"></i></div>
                <div class="mc-num">$<?= number_format($ingresos_mes/1000, 0) ?>k</div>
                <div class="mc-title">Ingresos del mes</div>
                <div class="mc-sub">Meta: $<?= number_format($meta_ingresos/1000, 0) ?>k MXN</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="metric-card c-red">
                <div class="trend down"><i class="fas fa-exclamation" style="font-size:.6rem;"></i> atención</div>
                <div class="mc-icon red"><i class="fas fa-bell"></i></div>
                <div class="mc-num"><?= $alertas_pendientes ?></div>
                <div class="mc-title">Alertas pendientes</div>
                <div class="mc-sub"><?= $alertas_criticas ?> críticas sin resolver</div>
            </div>
        </div>
    </div>

    <!-- Alerts + Users -->
    <div class="row g-3 mt-1">

        <!-- Alertas -->
        <div class="col-lg-4">
            <div class="sec-title"><i class="fas fa-bell"></i> Alertas del sistema</div>
            <div class="alerts-panel">
                <div class="alerts-head">
                    <h6><i class="fas fa-circle-exclamation"></i> Pendientes</h6>
                    <span style="font-size:.75rem;color:#aaa;"><?= $alertas_pendientes ?> sin resolver</span>
                </div>

                <?php foreach ($alertas as $alerta): ?>
                <div class="alert-item">
                    <div class="alert-icon <?= $alerta['tipo'] ?>"><i class="fas fa-<?= $alerta['icono'] ?>"></i></div>
                    <div class="alert-body">
                        <strong><?= htmlspecialchars($alerta['mensaje']) ?></strong>
                        <small><?= htmlspecialchars($alerta['detalle']) ?></small>
                    </div>
                    <button class="resolve-btn">Revisar</button>
                </div>
                <?php endforeach; ?>

                <div style="padding:12px 20px;text-align:right;">
                    <a href="admin_alertas.php" style="font-size:.8rem;font-weight:600;color:var(--primary);text-decoration:none;">Ver todas <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>

        <!-- Usuarios recientes -->
        <div class="col-lg-8">
            <div class="sec-title"><i class="fas fa-users"></i> Usuarios recientes</div>
            <div class="users-panel">
                <div class="users-head">
                    <h6><i class="fas fa-list"></i> Últimas altas y cambios</h6>
                    <div class="search-bar">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Buscar usuario...">
                    </div>
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
                            <?php foreach ($ultimos_usuarios as $u): 
                                $avatarStyle = getAvatarStyle($u['tipo'], $u['avatar']);
                                $inicialesU = strtoupper(substr($u['nombre'], 0, 1));
                                $fecha = date('d M, H:i', strtotime($u['fecha_registro']));
                                $estadoClass = $u['activo'] ? 'active' : 'inactive';
                                $estadoText = $u['activo'] ? 'Activo' : 'Inactivo';
                            ?>
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <div class="u-avatar" style="background:<?= $avatarStyle['bg'] ?>;"><?= $inicialesU ?></div>
                                        <div>
                                            <div class="uname"><?= htmlspecialchars($u['nombre']) ?></div>
                                            <div class="uemail"><?= htmlspecialchars($u['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-tag <?= $u['tipo'] ?>">
                                        <i class="fas fa-<?= $u['tipo'] == 'alumno' ? 'child' : ($u['tipo'] == 'tutor' ? 'chalkboard-teacher' : 'user-friends') ?>" style="font-size:.65rem;"></i> 
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
                                        <button class="act-btn" onclick="verUsuario(<?= $u['id'] ?>)"><i class="fas fa-eye"></i></button>
                                        <button class="act-btn" onclick="editarUsuario(<?= $u['id'] ?>)"><i class="fas fa-pen"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($ultimos_usuarios)): ?>
                            <tr><td colspan="5" class="text-center py-3 text-muted">No hay usuarios registrados aún.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="tbl-footer">
                    <span>Mostrando <?= count($ultimos_usuarios) ?> de <?= $total_usuarios ?> usuarios</span>
                    <a href="admin_usuarios.php">Ver todos los usuarios <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>
    </div>

    <div style="height:36px;"></div>
</div>

<script>
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
// Simulación de CPU
setInterval(() => {
    const el = document.getElementById('cpu');
    if (el) el.textContent = Math.floor(28 + Math.random() * 18) + '%';
}, 3500);

// Funciones placeholder para acciones
function verUsuario(id) {
    window.location.href = 'admin_usuarios.php?ver=' + id;
}
function editarUsuario(id) {
    window.location.href = 'admin_usuarios.php?editar=' + id;
}
</script>
</body>
</html>