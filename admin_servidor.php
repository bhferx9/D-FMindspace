<?php
include 'php/config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'admin') {
    header("Location: index.php");
    exit();
}

$admin_nombre = $_SESSION['usuario_nombre'] ?? 'Administrador';
$iniciales = '';
$partes = explode(' ', trim($admin_nombre));
$iniciales = count($partes) >= 2
    ? strtoupper(substr($partes[0], 0, 1) . substr($partes[1], 0, 1))
    : strtoupper(substr($admin_nombre, 0, 2));

// ─── MÉTRICAS DE LA BASE DE DATOS (PostgreSQL via mysqli/pg) ───────────────
$db_stats = [];
$query_log = [];
$conn_activas = 0;
$db_size = 'N/A';
$db_version = 'N/A';
$slow_queries = [];
$table_stats = [];

try {
    // Versión del servidor
    $r = mysqli_query($conn, "SELECT version() AS v");
    if ($r) $db_version = mysqli_fetch_assoc($r)['v'] ?? 'N/A';

    // Tamaño de la base de datos
    $r = mysqli_query($conn, "SELECT pg_size_pretty(pg_database_size(current_database())) AS size");
    if ($r) $db_size = mysqli_fetch_assoc($r)['size'] ?? 'N/A';

    // Conexiones activas
    $r = mysqli_query($conn, "SELECT count(*) AS total FROM pg_stat_activity WHERE state = 'active'");
    if ($r) $conn_activas = mysqli_fetch_assoc($r)['total'] ?? 0;

    // Estadísticas generales de la DB
    $r = mysqli_query($conn, "
        SELECT 
            numbackends AS conexiones,
            xact_commit AS commits,
            xact_rollback AS rollbacks,
            blks_hit AS cache_hits,
            blks_read AS disk_reads,
            tup_inserted AS inserts,
            tup_updated AS updates,
            tup_deleted AS deletes,
            deadlocks
        FROM pg_stat_database 
        WHERE datname = current_database()
    ");
    if ($r) $db_stats = mysqli_fetch_assoc($r);

    // Tamaño de las tablas principales
    $r = mysqli_query($conn, "
        SELECT 
            relname AS tabla,
            n_live_tup AS filas,
            pg_size_pretty(pg_total_relation_size(relid)) AS tamano,
            n_dead_tup AS muertos,
            last_vacuum,
            last_autovacuum
        FROM pg_stat_user_tables
        ORDER BY pg_total_relation_size(relid) DESC
        LIMIT 8
    ");
    if ($r) while ($row = mysqli_fetch_assoc($r)) $table_stats[] = $row;

    // Queries activas (corriendo en este momento)
    $r = mysqli_query($conn, "
        SELECT 
            pid,
            usename AS usuario,
            application_name AS app,
            state,
            ROUND(EXTRACT(EPOCH FROM (now() - query_start))::numeric, 2) AS duracion_seg,
            LEFT(query, 80) AS query
        FROM pg_stat_activity
        WHERE state != 'idle' AND query NOT LIKE '%pg_stat_activity%'
        ORDER BY duracion_seg DESC
        LIMIT 10
    ");
    if ($r) while ($row = mysqli_fetch_assoc($r)) $query_log[] = $row;

    // Queries lentas (pg_stat_statements si está disponible)
    $r = mysqli_query($conn, "
        SELECT 
            LEFT(query, 90) AS query,
            calls,
            ROUND((mean_exec_time)::numeric, 2) AS media_ms,
            ROUND((total_exec_time)::numeric, 2) AS total_ms,
            rows
        FROM pg_stat_statements
        ORDER BY mean_exec_time DESC
        LIMIT 6
    ");
    if ($r) while ($row = mysqli_fetch_assoc($r)) $slow_queries[] = $row;

} catch (Exception $e) {
    // silencioso – manejo visual abajo
}

// Cache hit ratio
$cache_ratio = 0;
if (!empty($db_stats['cache_hits']) && !empty($db_stats['disk_reads'])) {
    $total_reads = $db_stats['cache_hits'] + $db_stats['disk_reads'];
    $cache_ratio = $total_reads > 0 ? round($db_stats['cache_hits'] / $total_reads * 100, 1) : 0;
}

// Estado salud general (heurístico)
$deadlocks  = $db_stats['deadlocks'] ?? 0;
$rollbacks  = $db_stats['rollbacks'] ?? 0;
$health     = ($deadlocks == 0 && $rollbacks < 50) ? 'ok' : (($deadlocks < 3) ? 'warn' : 'crit');
$health_txt = ['ok' => 'Saludable', 'warn' => 'Advertencia', 'crit' => 'Crítico'][$health];

// Alertas pendientes (para badge sidebar)
$alertas_pendientes = 7;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Servidor DB — D&F Mindspace</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;800&family=Poppins:wght@400;500;600&family=Fredoka+One&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary:      #2cbaec;
    --secondary:    #f0ae2a;
    --accent:       #83bf46;
    --danger:       #ff5757;
    --dark-blue:    #1a8db8;
    --sidebar-width:260px;
    --shadow:       0 4px 20px rgba(44,186,236,.10);
    --mono:         'JetBrains Mono', monospace;
    --bg:           #f0f9fd;
    --panel:        #fff;
    --border:       rgba(44,186,236,.13);
    --muted:        #a0b3c0;
    --text:         #1a1a2e;
}

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    background: var(--bg);
    font-family: 'Poppins', sans-serif;
    min-height: 100vh;
}

/* ─── SIDEBAR (idéntico al dashboard) ─── */
.sidebar {
    width: var(--sidebar-width);
    height: 100vh;
    position: fixed;
    left: 0; top: 0;
    background: #fff;
    border-right: 3px solid var(--primary);
    box-shadow: 4px 0 18px rgba(44,186,236,.08);
    display: flex; flex-direction: column;
    z-index: 100; transition: transform .3s;
}
.sidebar-inner { flex:1; overflow-y:auto; padding:24px 0 16px; scrollbar-width:thin; scrollbar-color:rgba(44,186,236,.15) transparent; }
.brand { text-align:center; padding:0 18px 22px; border-bottom:1.5px solid rgba(44,186,236,.1); margin-bottom:10px; }
.brand-logo { font-family:'Fredoka One',cursive; font-size:2.2rem; background:linear-gradient(90deg,var(--primary),var(--secondary)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; line-height:1; }
.brand-sub { font-size:.85rem; color:var(--primary); font-weight:600; letter-spacing:6px; text-transform:uppercase; margin:3px 0 6px; }
.brand-tagline { font-size:.7rem; color:#aaa; letter-spacing:2px; text-transform:uppercase; }
.brand-tagline b:nth-child(1){color:var(--primary);}
.brand-tagline b:nth-child(2){color:var(--secondary);}
.brand-tagline b:nth-child(3){color:var(--accent);}
.admin-chip { display:inline-block; background:linear-gradient(90deg,var(--danger),#ff8c42); color:white; font-size:.65rem; font-weight:700; letter-spacing:2px; padding:3px 12px; border-radius:20px; margin-top:8px; }
.user-row { display:flex; align-items:center; gap:10px; margin:12px 14px 4px; padding:12px 14px; background:rgba(44,186,236,.05); border-radius:14px; }
.avatar { width:40px; height:40px; border-radius:50%; background:linear-gradient(135deg,var(--danger),#ff8c42); color:white; font-weight:700; font-size:.95rem; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.user-row .name { font-weight:600; font-size:.88rem; color:#222; }
.user-row .online { font-size:.72rem; color:var(--accent); display:flex; align-items:center; gap:5px; }
.online-dot { width:7px; height:7px; background:var(--accent); border-radius:50%; animation:blink 2s infinite; }
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}
.nav-label { font-size:.65rem; font-weight:700; color:#bbb; letter-spacing:3px; text-transform:uppercase; padding:16px 20px 4px; }
.nav-item { margin:3px 10px; }
.nav-link { display:flex; align-items:center; gap:11px; padding:11px 16px; color:#555; font-weight:600; font-size:.875rem; border-radius:12px; border-left:3px solid transparent; transition:all .25s; text-decoration:none; }
.nav-link i { width:18px; text-align:center; color:var(--primary); font-size:.9rem; }
.nav-link:hover, .nav-link.active { background:rgba(44,186,236,.09); color:var(--primary); border-left-color:var(--primary); transform:translateX(3px); }
.nav-link .badge-pill { margin-left:auto; background:var(--danger); color:white; border-radius:20px; padding:2px 9px; font-size:.68rem; font-weight:700; animation:pulsePill 2s infinite; }
@keyframes pulsePill{0%,100%{transform:scale(1)}50%{transform:scale(1.1)}}
.sidebar-footer { flex-shrink:0; padding:12px 10px; border-top:1px solid rgba(44,186,236,.1); }
.logout-link { display:flex; align-items:center; gap:10px; padding:10px 16px; color:var(--danger); font-weight:600; font-size:.875rem; border-radius:12px; background:rgba(255,87,87,.07); border-left:3px solid var(--danger); text-decoration:none; transition:background .2s; }
.logout-link:hover { background:rgba(255,87,87,.14); }
.logout-link i { color:var(--danger); width:18px; text-align:center; }

/* ─── MAIN ─── */
.main { margin-left:var(--sidebar-width); padding:30px 28px; min-height:100vh; }

.top { display:flex; align-items:center; justify-content:space-between; margin-bottom:22px; flex-wrap:wrap; gap:12px; }
.page-title { font-family:'Nunito',sans-serif; font-weight:800; font-size:1.7rem; color:var(--text); }
.page-title span { color:var(--primary); }
.page-sub { color:#aaa; font-size:.82rem; margin-top:2px; }

/* ─── HEALTH BADGE TOP ─── */
.health-badge {
    display:inline-flex; align-items:center; gap:8px;
    padding:8px 18px; border-radius:30px; font-size:.78rem; font-weight:700;
    letter-spacing:.5px;
}
.health-badge.ok   { background:rgba(131,191,70,.12); color:#5a9e28; border:1.5px solid rgba(131,191,70,.3); }
.health-badge.warn { background:rgba(240,174,42,.12); color:#c28a10; border:1.5px solid rgba(240,174,42,.3); }
.health-badge.crit { background:rgba(255,87,87,.12); color:var(--danger); border:1.5px solid rgba(255,87,87,.3); }
.health-pulse { width:8px; height:8px; border-radius:50%; background:currentColor; animation:blink 1.5s infinite; }

/* ─── STAT PILLS ROW ─── */
.stat-row {
    display:flex; gap:10px; flex-wrap:wrap; margin-bottom:22px;
}
.stat-pill {
    background:var(--panel);
    border:1.5px solid var(--border);
    border-radius:14px;
    padding:14px 20px;
    display:flex; align-items:center; gap:12px;
    box-shadow:var(--shadow);
    flex:1; min-width:150px;
    transition:transform .2s;
}
.stat-pill:hover { transform:translateY(-3px); }
.sp-icon {
    width:38px; height:38px; border-radius:11px;
    display:flex; align-items:center; justify-content:center;
    font-size:.85rem; color:white; flex-shrink:0;
}
.sp-icon.blue   { background:linear-gradient(135deg,var(--primary),var(--dark-blue)); }
.sp-icon.green  { background:linear-gradient(135deg,var(--accent),#5a9e28); }
.sp-icon.orange { background:linear-gradient(135deg,var(--secondary),#d69925); }
.sp-icon.red    { background:linear-gradient(135deg,var(--danger),#ff8c42); }
.sp-icon.purple { background:linear-gradient(135deg,#a078e8,#7c5abf); }
.sp-label { font-size:.7rem; color:var(--muted); font-weight:600; text-transform:uppercase; letter-spacing:1px; }
.sp-val { font-family:'Nunito',sans-serif; font-weight:800; font-size:1.15rem; color:var(--text); line-height:1.2; }

/* ─── PANEL BASE ─── */
.panel {
    background:var(--panel);
    border-radius:16px;
    border:1.5px solid var(--border);
    box-shadow:var(--shadow);
    overflow:hidden;
}
.panel-head {
    padding:14px 20px;
    border-bottom:1.5px solid var(--border);
    display:flex; align-items:center; justify-content:space-between;
    flex-wrap:wrap; gap:8px;
}
.panel-head h6 {
    font-weight:700; font-size:.9rem; color:#222; margin:0;
    display:flex; align-items:center; gap:8px;
}
.panel-head h6 i { color:var(--primary); font-size:.85rem; }

/* ─── TERMINAL / LOG ─── */
.terminal-wrap {
    background:#0d1117;
    border-radius:16px;
    overflow:hidden;
    border:1.5px solid rgba(44,186,236,.25);
    box-shadow:0 6px 30px rgba(0,0,0,.18);
}
.terminal-bar {
    background:#161b22;
    padding:10px 16px;
    display:flex; align-items:center; gap:10px;
    border-bottom:1px solid rgba(44,186,236,.12);
}
.term-dots { display:flex; gap:6px; }
.term-dot { width:12px; height:12px; border-radius:50%; }
.term-dot.r { background:#ff5757; }
.term-dot.y { background:#f0ae2a; }
.term-dot.g { background:#83bf46; }
.term-title { font-family:var(--mono); font-size:.76rem; color:rgba(44,186,236,.7); margin-left:8px; letter-spacing:1px; }
.term-live { margin-left:auto; display:flex; align-items:center; gap:6px; font-family:var(--mono); font-size:.68rem; color:var(--accent); }
.term-live-dot { width:6px; height:6px; background:var(--accent); border-radius:50%; animation:blink 1s infinite; }

.terminal-body {
    padding:16px;
    height:280px;
    overflow-y:auto;
    scrollbar-width:thin;
    scrollbar-color:rgba(44,186,236,.2) transparent;
}
.log-line {
    display:flex; align-items:flex-start; gap:10px;
    font-family:var(--mono);
    font-size:.76rem; line-height:1.7;
    padding:2px 0;
    border-bottom:1px solid rgba(255,255,255,.03);
    animation:fadeInLine .3s ease;
}
@keyframes fadeInLine{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:none}}
.log-ts { color:rgba(44,186,236,.5); flex-shrink:0; width:90px; }
.log-pid { color:rgba(240,174,42,.6); flex-shrink:0; width:52px; }
.log-state { flex-shrink:0; width:64px; font-weight:600; }
.log-state.active  { color:var(--accent); }
.log-state.idle    { color:rgba(160,180,200,.4); }
.log-state.wait    { color:var(--secondary); }
.log-state.lock    { color:var(--danger); }
.log-query { color:#c9d1d9; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.log-dur { flex-shrink:0; text-align:right; }
.log-dur.fast  { color:var(--accent); }
.log-dur.med   { color:var(--secondary); }
.log-dur.slow  { color:var(--danger); }

.term-input-bar {
    background:#161b22;
    border-top:1px solid rgba(44,186,236,.12);
    padding:10px 16px;
    display:flex; align-items:center; gap:8px;
}
.term-prompt { font-family:var(--mono); font-size:.8rem; color:var(--accent); flex-shrink:0; }
.term-input {
    flex:1; background:transparent; border:none; outline:none;
    font-family:var(--mono); font-size:.8rem; color:#e0e0e0;
    caret-color:var(--primary);
}
.term-input::placeholder { color:rgba(160,180,200,.3); }
.term-run {
    background:rgba(44,186,236,.15); border:1px solid rgba(44,186,236,.3);
    color:var(--primary); border-radius:8px; padding:5px 14px;
    font-family:var(--mono); font-size:.75rem; font-weight:600;
    cursor:pointer; transition:all .2s;
}
.term-run:hover { background:var(--primary); color:white; }

/* Output query result */
.query-result {
    background:#0d1117;
    border-top:1px solid rgba(44,186,236,.1);
    padding:12px 16px;
    display:none;
}
.query-result.visible { display:block; }
.qr-table { width:100%; border-collapse:collapse; font-family:var(--mono); font-size:.73rem; color:#c9d1d9; }
.qr-table th { color:var(--primary); font-weight:600; padding:6px 10px; border-bottom:1px solid rgba(44,186,236,.15); text-align:left; background:rgba(44,186,236,.05); }
.qr-table td { padding:5px 10px; border-bottom:1px solid rgba(255,255,255,.04); }
.qr-table tbody tr:hover { background:rgba(44,186,236,.04); }
.qr-msg { font-family:var(--mono); font-size:.76rem; padding:4px 0; }
.qr-msg.ok   { color:var(--accent); }
.qr-msg.err  { color:var(--danger); }
.qr-time { font-family:var(--mono); font-size:.7rem; color:var(--muted); margin-top:6px; }

/* ─── TABLE STATS ─── */
.db-table { width:100%; border-collapse:collapse; }
.db-table th { padding:11px 16px; font-size:.74rem; font-weight:700; color:var(--primary); text-transform:uppercase; letter-spacing:1px; background:rgba(44,186,236,.04); border-bottom:1.5px solid var(--border); white-space:nowrap; }
.db-table td { padding:11px 16px; font-size:.82rem; border-bottom:1px solid rgba(44,186,236,.06); vertical-align:middle; }
.db-table tbody tr:last-child td { border-bottom:none; }
.db-table tbody tr:hover { background:rgba(44,186,236,.03); }
.tname { font-family:var(--mono); font-weight:600; color:var(--text); font-size:.8rem; }
.tsize { font-family:var(--mono); font-size:.78rem; color:var(--muted); }
.rows-bar-wrap { width:100%; background:rgba(44,186,236,.08); border-radius:4px; height:5px; overflow:hidden; }
.rows-bar { height:100%; background:linear-gradient(90deg,var(--primary),var(--accent)); border-radius:4px; transition:width .8s ease; }
.vacuum-ok  { color:var(--accent); font-size:.72rem; }
.vacuum-old { color:var(--secondary); font-size:.72rem; }
.vacuum-na  { color:var(--muted); font-size:.72rem; }

/* ─── SLOW QUERIES ─── */
.sq-item {
    padding:13px 18px;
    border-bottom:1px solid var(--border);
    display:flex; align-items:center; gap:12px;
}
.sq-item:last-child { border-bottom:none; }
.sq-rank { font-family:var(--mono); font-size:.7rem; color:var(--muted); width:20px; flex-shrink:0; }
.sq-query { font-family:var(--mono); font-size:.75rem; color:#555; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.sq-badge {
    font-family:var(--mono); font-size:.7rem; font-weight:600;
    padding:3px 9px; border-radius:20px; flex-shrink:0; white-space:nowrap;
}
.sq-badge.fast  { background:rgba(131,191,70,.12); color:#5a9e28; }
.sq-badge.med   { background:rgba(240,174,42,.12); color:#c28a10; }
.sq-badge.slow  { background:rgba(255,87,87,.12); color:var(--danger); }
.sq-calls { font-family:var(--mono); font-size:.72rem; color:var(--muted); flex-shrink:0; }

/* ─── MINI GAUGES ─── */
.gauges-row { display:flex; gap:14px; flex-wrap:wrap; }
.gauge-card {
    flex:1; min-width:130px;
    background:var(--panel);
    border:1.5px solid var(--border);
    border-radius:14px; padding:16px 14px;
    box-shadow:var(--shadow);
    display:flex; flex-direction:column; align-items:center; gap:8px;
}
.gauge-label { font-size:.72rem; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:1px; text-align:center; }
.gauge-val { font-family:'Nunito',sans-serif; font-weight:800; font-size:1.5rem; }
.gauge-sub { font-size:.68rem; color:var(--muted); text-align:center; }

/* Donut SVG */
.donut-wrap { position:relative; width:70px; height:70px; }
.donut-wrap svg { transform:rotate(-90deg); }
.donut-bg { fill:none; stroke:rgba(44,186,236,.1); stroke-width:6; }
.donut-fg { fill:none; stroke-width:6; stroke-linecap:round; transition:stroke-dasharray 1s ease; }
.donut-center { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); font-family:'Nunito',sans-serif; font-weight:800; font-size:.78rem; }

/* ─── Section title ─── */
.sec-title { font-family:'Nunito',sans-serif; font-weight:800; font-size:1.05rem; color:var(--text); display:flex; align-items:center; gap:8px; margin:22px 0 12px; }
.sec-title i { color:var(--primary); font-size:.95rem; }

/* ─── Mobile ─── */
.menu-toggle { display:none; position:fixed; top:15px; left:15px; z-index:200; background:linear-gradient(90deg,var(--primary),var(--secondary)); border:none; color:white; width:44px; height:44px; border-radius:50%; font-size:1.1rem; box-shadow:0 4px 14px rgba(44,186,236,.35); cursor:pointer; }
@media(max-width:992px){
    .sidebar{transform:translateX(-100%);}
    .sidebar.open{transform:translateX(0);}
    .main{margin-left:0; padding:18px 14px;}
    .menu-toggle{display:flex; align-items:center; justify-content:center;}
    .terminal-body{height:200px;}
}
</style>
</head>
<body>

<button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>

<!-- ══ SIDEBAR ══ -->
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
            <li class="nav-item"><a href="admin_dashboard.php" class="nav-link"><i class="fas fa-rocket"></i> Panel</a></li>
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
            <li class="nav-item"><a href="admin_alertas.php" class="nav-link"><i class="fas fa-bell"></i> Alertas <span class="badge-pill"><?= $alertas_pendientes ?></span></a></li>
            <li class="nav-item"><a href="admin_servidor.php" class="nav-link active"><i class="fas fa-server"></i> Servidor</a></li>
            <li class="nav-item"><a href="admin_config.php" class="nav-link"><i class="fas fa-cog"></i> Configuración</a></li>
        </ul>
    </div>
    <div class="sidebar-footer">
        <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
    </div>
</div>

<!-- ══ MAIN ══ -->
<div class="main">

    <!-- Top -->
    <div class="top">
        <div>
            <h1 class="page-title">Monitor de <span>base de datos</span></h1>
            <p class="page-sub"><?= $db_version ?></p>
        </div>
        <div class="health-badge <?= $health ?>">
            <span class="health-pulse"></span>
            <?= $health_txt ?>
        </div>
    </div>

    <!-- Stat pills -->
    <div class="stat-row">
        <div class="stat-pill">
            <div class="sp-icon blue"><i class="fas fa-database"></i></div>
            <div>
                <div class="sp-label">Tamaño DB</div>
                <div class="sp-val"><?= $db_size ?></div>
            </div>
        </div>
        <div class="stat-pill">
            <div class="sp-icon green"><i class="fas fa-plug"></i></div>
            <div>
                <div class="sp-label">Conexiones activas</div>
                <div class="sp-val"><?= $conn_activas ?></div>
            </div>
        </div>
        <div class="stat-pill">
            <div class="sp-icon orange"><i class="fas fa-arrows-rotate"></i></div>
            <div>
                <div class="sp-label">Commits</div>
                <div class="sp-val"><?= number_format($db_stats['commits'] ?? 0) ?></div>
            </div>
        </div>
        <div class="stat-pill">
            <div class="sp-icon red"><i class="fas fa-bomb"></i></div>
            <div>
                <div class="sp-label">Deadlocks</div>
                <div class="sp-val"><?= $db_stats['deadlocks'] ?? 0 ?></div>
            </div>
        </div>
        <div class="stat-pill">
            <div class="sp-icon purple"><i class="fas fa-bolt"></i></div>
            <div>
                <div class="sp-label">Cache ratio</div>
                <div class="sp-val"><?= $cache_ratio ?>%</div>
            </div>
        </div>
    </div>

    <!-- Gauges -->
    <div class="gauges-row mb-3">
        <?php
        $gauges = [
            ['label'=>'Inserts',  'val'=>$db_stats['inserts']??0, 'color'=>'#83bf46', 'max'=>5000],
            ['label'=>'Updates',  'val'=>$db_stats['updates']??0, 'color'=>'#2cbaec', 'max'=>5000],
            ['label'=>'Deletes',  'val'=>$db_stats['deletes']??0, 'color'=>'#ff5757', 'max'=>1000],
            ['label'=>'Rollbacks','val'=>$db_stats['rollbacks']??0,'color'=>'#f0ae2a','max'=>200],
        ];
        foreach ($gauges as $g):
            $pct = min(100, $g['max'] > 0 ? round($g['val']/$g['max']*100) : 0);
            $r = 29; $circ = 2 * M_PI * $r;
            $dash = round($pct / 100 * $circ, 2);
        ?>
        <div class="gauge-card">
            <div class="gauge-label"><?= $g['label'] ?></div>
            <div class="donut-wrap">
                <svg width="70" height="70" viewBox="0 0 70 70">
                    <circle class="donut-bg" cx="35" cy="35" r="<?= $r ?>"/>
                    <circle class="donut-fg" cx="35" cy="35" r="<?= $r ?>"
                        stroke="<?= $g['color'] ?>"
                        stroke-dasharray="<?= $dash ?> <?= $circ ?>"
                        stroke-dashoffset="0"/>
                </svg>
                <div class="donut-center" style="color:<?= $g['color'] ?>"><?= $pct ?>%</div>
            </div>
            <div class="gauge-val" style="color:<?= $g['color'] ?>"><?= number_format($g['val']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Terminal de queries activas + input SQL -->
    <div class="sec-title"><i class="fas fa-terminal"></i> Actividad en tiempo real</div>
    <div class="terminal-wrap mb-3">
        <div class="terminal-bar">
            <div class="term-dots">
                <div class="term-dot r"></div>
                <div class="term-dot y"></div>
                <div class="term-dot g"></div>
            </div>
            <div class="term-title">pg_stat_activity — <?= $conn_activas ?> activas</div>
            <div class="term-live"><span class="term-live-dot"></span> LIVE</div>
        </div>

        <div class="terminal-body" id="termBody">
            <!-- Encabezado fijo -->
            <div class="log-line" style="opacity:.4; border-bottom:1px solid rgba(44,186,236,.08); margin-bottom:4px; padding-bottom:4px;">
                <span class="log-ts"># timestamp</span>
                <span class="log-pid">pid</span>
                <span class="log-state">estado</span>
                <span class="log-query">query</span>
                <span class="log-dur">dur</span>
            </div>

            <?php if (!empty($query_log)): ?>
                <?php foreach ($query_log as $q):
                    $dur   = floatval($q['duracion_seg'] ?? 0);
                    $dclass= $dur < 1 ? 'fast' : ($dur < 5 ? 'med' : 'slow');
                    $sclass= in_array($q['state'],['active','fastpath function call']) ? 'active' : (str_contains($q['state']??'','lock') ? 'lock' : 'idle');
                ?>
                <div class="log-line">
                    <span class="log-ts"><?= date('H:i:s') ?></span>
                    <span class="log-pid">[<?= $q['pid'] ?>]</span>
                    <span class="log-state <?= $sclass ?>"><?= strtoupper(substr($q['state'] ?? 'idle', 0, 6)) ?></span>
                    <span class="log-query" title="<?= htmlspecialchars($q['query']) ?>"><?= htmlspecialchars($q['query']) ?></span>
                    <span class="log-dur <?= $dclass ?>"><?= $dur ?>s</span>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="log-line">
                    <span class="log-ts"><?= date('H:i:s') ?></span>
                    <span class="log-pid">[--]</span>
                    <span class="log-state idle">IDLE</span>
                    <span class="log-query" style="color:rgba(160,180,200,.4);">Sin queries activas en este momento</span>
                    <span class="log-dur fast">0s</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Input SQL directo -->
        <div class="term-input-bar">
            <span class="term-prompt">db=# &gt;</span>
            <input type="text" class="term-input" id="sqlInput"
                   placeholder="SELECT * FROM usuarios LIMIT 5;" 
                   autocomplete="off" spellcheck="false">
            <button class="term-run" id="runBtn"><i class="fas fa-play" style="font-size:.7rem;"></i> Ejecutar</button>
        </div>

        <!-- Resultado query -->
        <div class="query-result" id="queryResult">
            <div id="queryOutput"></div>
            <div class="qr-time" id="queryTime"></div>
        </div>
    </div>

    <!-- Tabla de tablas + Slow queries -->
    <div class="row g-3">

        <!-- Tablas -->
        <div class="col-lg-7">
            <div class="sec-title"><i class="fas fa-table"></i> Tablas del esquema</div>
            <div class="panel">
                <div class="panel-head">
                    <h6><i class="fas fa-layer-group"></i> pg_stat_user_tables</h6>
                    <span style="font-size:.74rem;color:var(--muted);"><?= count($table_stats) ?> tablas</span>
                </div>
                <div class="table-responsive">
                    <?php if (!empty($table_stats)):
                        $maxRows = max(array_column($table_stats,'filas') ?: [1]);
                    ?>
                    <table class="db-table">
                        <thead>
                            <tr>
                                <th>Tabla</th>
                                <th>Filas</th>
                                <th>Tamaño</th>
                                <th>Muertos</th>
                                <th>Vacuum</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($table_stats as $t):
                            $pct = $maxRows > 0 ? min(100, round($t['filas']/$maxRows*100)) : 0;
                            $vacDate = $t['last_autovacuum'] ?? $t['last_vacuum'] ?? null;
                            $vacClass = !$vacDate ? 'vacuum-na' : (strtotime($vacDate) > strtotime('-7 days') ? 'vacuum-ok' : 'vacuum-old');
                            $vacTxt   = $vacDate ? date('d/m H:i', strtotime($vacDate)) : '—';
                        ?>
                        <tr>
                            <td><span class="tname"><?= htmlspecialchars($t['tabla']) ?></span></td>
                            <td>
                                <div style="font-family:var(--mono);font-size:.78rem;margin-bottom:4px;"><?= number_format($t['filas']) ?></div>
                                <div class="rows-bar-wrap"><div class="rows-bar" style="width:<?= $pct ?>%"></div></div>
                            </td>
                            <td><span class="tsize"><?= $t['tamano'] ?></span></td>
                            <td><span style="font-family:var(--mono);font-size:.78rem;color:<?= $t['muertos'] > 0 ? 'var(--secondary)':'var(--muted)' ?>;"><?= number_format($t['muertos']) ?></span></td>
                            <td><span class="<?= $vacClass ?>"><?= $vacTxt ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div style="padding:24px;text-align:center;color:var(--muted);font-size:.85rem;">
                        <i class="fas fa-circle-info" style="color:var(--primary);"></i> No se encontraron tablas de usuario.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Slow queries -->
        <div class="col-lg-5">
            <div class="sec-title"><i class="fas fa-stopwatch"></i> Queries más lentas</div>
            <div class="panel">
                <div class="panel-head">
                    <h6><i class="fas fa-hourglass-half"></i> pg_stat_statements</h6>
                    <span style="font-size:.74rem;color:var(--muted);">Top 6</span>
                </div>
                <?php if (!empty($slow_queries)): ?>
                    <?php foreach ($slow_queries as $i => $sq):
                        $ms = floatval($sq['media_ms']);
                        $badge = $ms < 5 ? ['fast','<5ms'] : ($ms < 50 ? ['med', round($ms).'ms'] : ['slow', round($ms).'ms']);
                    ?>
                    <div class="sq-item">
                        <span class="sq-rank"><?= $i+1 ?>.</span>
                        <span class="sq-query" title="<?= htmlspecialchars($sq['query']) ?>"><?= htmlspecialchars($sq['query']) ?></span>
                        <span class="sq-calls"><?= number_format($sq['calls']) ?>x</span>
                        <span class="sq-badge <?= $badge[0] ?>"><?= $badge[1] ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="padding:24px;text-align:center;color:var(--muted);font-size:.82rem;">
                        <i class="fas fa-circle-check" style="color:var(--accent);"></i>
                        pg_stat_statements no está disponible o sin datos.
                    </div>
                <?php endif; ?>
                <div style="padding:12px 18px;border-top:1px solid var(--border);text-align:right;">
                    <a href="#" style="font-size:.78rem;font-weight:600;color:var(--primary);text-decoration:none;">
                        EXPLAIN ANALYZE <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

    </div>

    <div style="height:36px;"></div>
</div>

<!-- ══ SCRIPTS ══ -->
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

// ── Terminal SQL ──
const sqlInput  = document.getElementById('sqlInput');
const runBtn    = document.getElementById('runBtn');
const qResult   = document.getElementById('queryResult');
const qOutput   = document.getElementById('queryOutput');
const qTime     = document.getElementById('queryTime');

function runQuery() {
    const sql = sqlInput.value.trim();
    if (!sql) return;

    runBtn.disabled = true;
    runBtn.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:.7rem;"></i>';
    qResult.classList.add('visible');
    qOutput.innerHTML = '<span class="qr-msg ok">Ejecutando...</span>';
    qTime.textContent = '';

    const t0 = performance.now();
    fetch('php/query_runner.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'sql=' + encodeURIComponent(sql)
    })
    .then(r => r.json())
    .then(data => {
        const ms = ((performance.now() - t0) / 1000).toFixed(3);
        if (data.error) {
            qOutput.innerHTML = `<span class="qr-msg err"><i class="fas fa-triangle-exclamation"></i> ${escHtml(data.error)}</span>`;
        } else if (data.rows && data.rows.length > 0) {
            const cols = Object.keys(data.rows[0]);
            let html = '<table class="qr-table"><thead><tr>' + cols.map(c=>`<th>${escHtml(c)}</th>`).join('') + '</tr></thead><tbody>';
            data.rows.slice(0,50).forEach(row => {
                html += '<tr>' + cols.map(c=>`<td>${escHtml(String(row[c]??'NULL'))}</td>`).join('') + '</tr>';
            });
            html += '</tbody></table>';
            if (data.rows.length > 50) html += `<div class="qr-msg ok" style="margin-top:6px;">... ${data.rows.length} filas (mostrando 50)</div>`;
            qOutput.innerHTML = html;
        } else {
            qOutput.innerHTML = `<span class="qr-msg ok"><i class="fas fa-circle-check"></i> ${data.affected ?? 0} fila(s) afectadas.</span>`;
        }
        qTime.textContent = `Tiempo: ${ms}s · ${(data.rows?.length??0)} filas`;
    })
    .catch(err => {
        qOutput.innerHTML = `<span class="qr-msg err"><i class="fas fa-triangle-exclamation"></i> Error de conexión: ${escHtml(err.message)}</span>`;
    })
    .finally(() => {
        runBtn.disabled = false;
        runBtn.innerHTML = '<i class="fas fa-play" style="font-size:.7rem;"></i> Ejecutar';
    });
}

function escHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

runBtn.addEventListener('click', runQuery);
sqlInput.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) runQuery(); });

// Historial simple con flechas
const history = [];
let hIdx = -1;
sqlInput.addEventListener('keydown', e => {
    if (e.key === 'ArrowUp') {
        if (hIdx < history.length - 1) { hIdx++; sqlInput.value = history[history.length - 1 - hIdx]; }
        e.preventDefault();
    }
    if (e.key === 'ArrowDown') {
        if (hIdx > 0) { hIdx--; sqlInput.value = history[history.length - 1 - hIdx]; }
        else { hIdx = -1; sqlInput.value = ''; }
        e.preventDefault();
    }
    if (e.key === 'Enter') { if (sqlInput.value.trim()) { history.push(sqlInput.value.trim()); hIdx = -1; } }
});

// Auto-refresh terminal cada 30s
setInterval(() => {
    fetch('php/live_queries.php')
        .then(r => r.json())
        .then(lines => {
            if (!lines || !lines.length) return;
            const body = document.getElementById('termBody');
            lines.forEach(q => {
                const dur = parseFloat(q.duracion_seg ?? 0);
                const dc = dur < 1 ? 'fast' : dur < 5 ? 'med' : 'slow';
                const sc = (q.state||'').includes('active') ? 'active' : 'idle';
                const div = document.createElement('div');
                div.className = 'log-line';
                div.innerHTML = `
                    <span class="log-ts">${new Date().toTimeString().slice(0,8)}</span>
                    <span class="log-pid">[${q.pid}]</span>
                    <span class="log-state ${sc}">${(q.state||'idle').substring(0,6).toUpperCase()}</span>
                    <span class="log-query" title="${escHtml(q.query)}">${escHtml(q.query)}</span>
                    <span class="log-dur ${dc}">${dur}s</span>
                `;
                body.appendChild(div);
                body.scrollTop = body.scrollHeight;
            });
        })
        .catch(()=>{});
}, 30000);
</script>
</body>
</html>