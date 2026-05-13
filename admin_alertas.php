<?php
include 'php/config.php';
session_start();

// ========== GENERAR ALERTAS AUTOMÁTICAMENTE ==========

if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'admin') {
    header("Location: index.php");
    exit();
}


$admin_nombre = $_SESSION['usuario_nombre'] ?? 'Administrador';
$partes = explode(' ', trim($admin_nombre));
$iniciales = count($partes) >= 2
    ? strtoupper(substr($partes[0],0,1).substr($partes[1],0,1))
    : strtoupper(substr($admin_nombre,0,2));

// ========== CORREGIDO: Definir $filtro ==========
$filtro = $_GET['filtro'] ?? 'todas';
// ===============================================

// ============================================================
// CONSULTAS CORREGIDAS PARA POSTGRESQL (usando PDO directamente)
// ============================================================

try {
    // 1. BLOQUEOS ACTIVOS
    $locks = [];
    $stmt = $conn->pdo->query("
        SELECT 
            bl.pid AS pid_bloqueado,
            ka.usename AS usuario_bloqueado,
            LEFT(ka.query, 70) AS query_bloqueada,
            kl.pid AS pid_bloqueante,
            ka2.usename AS usuario_bloqueante,
            LEFT(ka2.query, 70) AS query_bloqueante,
            bl.relation::regclass AS tabla,
            ROUND(EXTRACT(EPOCH FROM (now()-ka.query_start))::numeric,1) AS espera_seg
        FROM pg_catalog.pg_locks bl
        JOIN pg_catalog.pg_stat_activity ka ON bl.pid = ka.pid
        JOIN pg_catalog.pg_locks kl ON kl.transactionid = bl.transactionid AND kl.pid != bl.pid
        JOIN pg_catalog.pg_stat_activity ka2 ON kl.pid = ka2.pid
        WHERE NOT bl.granted
        ORDER BY espera_seg DESC
        LIMIT 20
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $locks[] = $row;
    
    // 2. CONEXIONES
    $conn_data = [];
    $stmt = $conn->pdo->query("
        SELECT
            (SELECT setting::int FROM pg_settings WHERE name='max_connections') AS max_conn,
            (SELECT count(*) FROM pg_stat_activity) AS used,
            (SELECT setting::int FROM pg_settings WHERE name='superuser_reserved_connections') AS res_super,
            (SELECT count(*) FROM pg_stat_activity)::numeric / (SELECT setting::int FROM pg_settings WHERE name='max_connections')::numeric * 100 AS pct_uso
    ");
    $conn_data = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $pct_conn = round(floatval($conn_data['pct_uso'] ?? 0), 1);
    $conn_data['disponibles'] = ($conn_data['max_conn'] ?? 0) - ($conn_data['used'] ?? 0) - ($conn_data['res_super'] ?? 0);
    $conn_nivel = $pct_conn > 85 ? 'crit' : ($pct_conn > 60 ? 'warn' : 'ok');
    
    // 3. CONEXIONES POR USUARIO
    $conn_history = [];
    $stmt = $conn->pdo->query("
        SELECT usename AS usuario, count(*) AS total, state
        FROM pg_stat_activity
        GROUP BY usename, state
        ORDER BY total DESC
        LIMIT 12
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $conn_history[] = $row;
    
    // 4. BLOAT / VACUUM
    $bloat = [];
    $stmt = $conn->pdo->query("
        SELECT
            schemaname || '.' || relname AS tabla,
            n_dead_tup AS filas_muertas,
            n_live_tup AS filas_vivas,
            CASE WHEN n_live_tup > 0
                 THEN ROUND(n_dead_tup::numeric / n_live_tup * 100, 1)
                 ELSE 0 END AS pct_bloat,
            last_autovacuum,
            last_autoanalyze,
            pg_size_pretty(pg_total_relation_size(relid)) AS tamano
        FROM pg_stat_user_tables
        WHERE n_dead_tup > 100
        ORDER BY pct_bloat DESC
        LIMIT 10
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $bloat[] = $row;
    
    // 5. TRANSACCIONES LARGAS
    $long_tx = [];
    $stmt = $conn->pdo->query("
        SELECT
            pid,
            usename AS usuario,
            datname AS base,
            state,
            ROUND(EXTRACT(EPOCH FROM (now()-xact_start))::numeric,1) AS duracion_seg,
            ROUND(EXTRACT(EPOCH FROM (now()-query_start))::numeric,1) AS query_seg,
            LEFT(query,80) AS query,
            wait_event_type,
            wait_event
        FROM pg_stat_activity
        WHERE xact_start IS NOT NULL
          AND EXTRACT(EPOCH FROM (now()-xact_start)) > 30
        ORDER BY duracion_seg DESC
        LIMIT 15
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $long_tx[] = $row;
    
    // 6. REPLICACIÓN
    $replication = [];
    $stmt = $conn->pdo->query("
        SELECT
            client_addr,
            usename,
            application_name,
            state,
            sent_lsn::text,
            write_lsn::text,
            flush_lsn::text,
            replay_lsn::text,
            pg_size_pretty(pg_wal_lsn_diff(sent_lsn, replay_lsn)) AS lag_size,
            sync_state
        FROM pg_stat_replication
        ORDER BY client_addr
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $replication[] = $row;
    
    // 7. ÍNDICES NO USADOS
    $unused_idx = [];
    $stmt = $conn->pdo->query("
        SELECT
            schemaname || '.' || relname AS tabla,
            indexrelname AS indice,
            pg_size_pretty(pg_relation_size(indexrelid)) AS tamano,
            idx_scan AS escaneos,
            idx_tup_read AS tuplas_leidas
        FROM pg_stat_user_indexes
        WHERE idx_scan < 50
          AND NOT EXISTS (
              SELECT 1 FROM pg_constraint c
              WHERE c.conindid = indexrelid
          )
        ORDER BY pg_relation_size(indexrelid) DESC
        LIMIT 10
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $unused_idx[] = $row;
    
    // 8. ESTADÍSTICAS GLOBALES DB
    $db_global = [];
    $stmt = $conn->pdo->query("
        SELECT
            datname,
            numbackends,
            xact_commit,
            xact_rollback,
            ROUND(xact_rollback::numeric/(NULLIF(xact_commit+xact_rollback,0))*100,2) AS pct_rollback,
            deadlocks,
            conflicts,
            temp_files,
            pg_size_pretty(temp_bytes) AS temp_size
        FROM pg_stat_database
        WHERE datname = current_database()
    ");
    $db_global = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
} catch(PDOException $e) {
    // Si hay error, mostrar mensaje pero continuar
    $error_msg = $e->getMessage();
}

// Contadores para el sidebar
$total_pendientes = count($locks) + count($long_tx);
$counts = [
    'crit' => count($locks),
    'warn' => count($long_tx),
    'info' => 0
];

// Alertas personalizadas (vacío por ahora)
$alertas_custom = [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Alertas DBA — D&F Mindspace</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;800;900&family=Poppins:wght@400;500;600&family=Fredoka+One&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary:   #2cbaec;
    --secondary: #f0ae2a;
    --accent:    #83bf46;
    --danger:    #ff5757;
    --dark-blue: #1a8db8;
    --purple:    #a078e8;
    --sidebar-w: 260px;
    --bg:        #f0f9fd;
    --panel:     #ffffff;
    --border:    rgba(44,186,236,.13);
    --muted:     #9fb5c2;
    --text:      #1a1a2e;
    --mono:      'JetBrains Mono', monospace;
    --shadow:    0 4px 20px rgba(44,186,236,.09);
}
*{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--bg);font-family:'Poppins',sans-serif;min-height:100vh;}

/* ─── SIDEBAR ─── */
.sidebar{width:var(--sidebar-w);height:100vh;position:fixed;left:0;top:0;background:#fff;border-right:3px solid var(--primary);box-shadow:4px 0 18px rgba(44,186,236,.08);display:flex;flex-direction:column;z-index:100;transition:transform .3s;}
.sidebar-inner{flex:1;overflow-y:auto;padding:24px 0 16px;scrollbar-width:thin;scrollbar-color:rgba(44,186,236,.15) transparent;}
.brand{text-align:center;padding:0 18px 22px;border-bottom:1.5px solid rgba(44,186,236,.1);margin-bottom:10px;}
.brand-logo{font-family:'Fredoka One',cursive;font-size:2.2rem;background:linear-gradient(90deg,var(--primary),var(--secondary));-webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1;}
.brand-sub{font-size:.85rem;color:var(--primary);font-weight:600;letter-spacing:6px;text-transform:uppercase;margin:3px 0 6px;}
.brand-tagline{font-size:.7rem;color:#aaa;letter-spacing:2px;text-transform:uppercase;}
.brand-tagline b:nth-child(1){color:var(--primary);}
.brand-tagline b:nth-child(2){color:var(--secondary);}
.brand-tagline b:nth-child(3){color:var(--accent);}
.admin-chip{display:inline-block;background:linear-gradient(90deg,var(--danger),#ff8c42);color:white;font-size:.65rem;font-weight:700;letter-spacing:2px;padding:3px 12px;border-radius:20px;margin-top:8px;}
.user-row{display:flex;align-items:center;gap:10px;margin:12px 14px 4px;padding:12px 14px;background:rgba(44,186,236,.05);border-radius:14px;}
.avatar{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--danger),#ff8c42);color:white;font-weight:700;font-size:.95rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.user-row .name{font-weight:600;font-size:.88rem;color:#222;}
.user-row .online{font-size:.72rem;color:var(--accent);display:flex;align-items:center;gap:5px;}
.online-dot{width:7px;height:7px;background:var(--accent);border-radius:50%;animation:blink 2s infinite;}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}
.nav-label{font-size:.65rem;font-weight:700;color:#bbb;letter-spacing:3px;text-transform:uppercase;padding:16px 20px 4px;}
.nav-item{margin:3px 10px;}
.nav-link{display:flex;align-items:center;gap:11px;padding:11px 16px;color:#555;font-weight:600;font-size:.875rem;border-radius:12px;border-left:3px solid transparent;transition:all .25s;text-decoration:none;}
.nav-link i{width:18px;text-align:center;color:var(--primary);font-size:.9rem;}
.nav-link:hover,.nav-link.active{background:rgba(44,186,236,.09);color:var(--primary);border-left-color:var(--primary);transform:translateX(3px);}
.nav-link .badge-pill{margin-left:auto;background:var(--danger);color:white;border-radius:20px;padding:2px 9px;font-size:.68rem;font-weight:700;animation:pulsePill 2s infinite;}
@keyframes pulsePill{0%,100%{transform:scale(1)}50%{transform:scale(1.1)}}
.sidebar-footer{flex-shrink:0;padding:12px 10px;border-top:1px solid rgba(44,186,236,.1);}
.logout-link{display:flex;align-items:center;gap:10px;padding:10px 16px;color:var(--danger);font-weight:600;font-size:.875rem;border-radius:12px;background:rgba(255,87,87,.07);border-left:3px solid var(--danger);text-decoration:none;transition:background .2s;}
.logout-link:hover{background:rgba(255,87,87,.14);}
.logout-link i{color:var(--danger);width:18px;text-align:center;}

/* ─── MAIN ─── */
.main{margin-left:var(--sidebar-w);padding:28px 28px 40px;min-height:100vh;}
.top{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;}
.page-title{font-family:'Nunito',sans-serif;font-weight:900;font-size:1.75rem;color:var(--text);}
.page-title span{color:var(--danger);}
.page-sub{color:var(--muted);font-size:.8rem;margin-top:2px;}

/* ─── KPI STRIP ─── */
.kpi-strip{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:22px;}
.kpi{flex:1;min-width:130px;background:var(--panel);border:1.5px solid var(--border);border-radius:14px;padding:16px 18px;box-shadow:var(--shadow);display:flex;align-items:center;gap:13px;transition:transform .2s;}
.kpi:hover{transform:translateY(-3px);}
.kpi-icon{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:.95rem;color:white;flex-shrink:0;}
.ki-red   {background:linear-gradient(135deg,var(--danger),#ff8c42);}
.ki-orange{background:linear-gradient(135deg,var(--secondary),#c28a10);}
.ki-blue  {background:linear-gradient(135deg,var(--primary),var(--dark-blue));}
.ki-green {background:linear-gradient(135deg,var(--accent),#5a9e28);}
.ki-purple{background:linear-gradient(135deg,var(--purple),#6e44c4);}
.kpi-label{font-size:.68rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:1px;}
.kpi-val{font-family:'Nunito',sans-serif;font-weight:800;font-size:1.35rem;color:var(--text);line-height:1.2;}
.kpi-val.danger{color:var(--danger);}
.kpi-val.warn{color:var(--secondary);}
.kpi-val.ok{color:var(--accent);}

/* ─── TABS ─── */
.tabs-bar{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px;background:var(--panel);padding:8px;border-radius:14px;border:1.5px solid var(--border);box-shadow:var(--shadow);}
.tab-btn{padding:8px 18px;border-radius:10px;border:none;background:transparent;font-family:'Poppins',sans-serif;font-weight:600;font-size:.8rem;color:var(--muted);cursor:pointer;display:flex;align-items:center;gap:7px;transition:all .2s;position:relative;}
.tab-btn:hover{background:rgba(44,186,236,.07);color:var(--primary);}
.tab-btn.active{background:linear-gradient(135deg,var(--primary),var(--dark-blue));color:white;box-shadow:0 4px 14px rgba(44,186,236,.28);}
.tab-btn .tbadge{font-size:.65rem;font-weight:700;padding:1px 7px;border-radius:20px;}
.tab-btn.active .tbadge{background:rgba(255,255,255,.25);}
.tab-btn:not(.active) .tbadge{background:rgba(44,186,236,.12);color:var(--primary);}
.tbadge.red-b{background:rgba(255,87,87,.15)!important;color:var(--danger)!important;}

/* ─── PANEL ─── */
.panel{background:var(--panel);border-radius:16px;border:1.5px solid var(--border);box-shadow:var(--shadow);overflow:hidden;margin-bottom:18px;}
.panel-head{padding:13px 20px;border-bottom:1.5px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;}
.panel-head h6{font-weight:700;font-size:.88rem;color:#222;margin:0;display:flex;align-items:center;gap:8px;}
.panel-head h6 i{color:var(--primary);}
.ph-right{display:flex;align-items:center;gap:8px;}

/* Severity dot */
.sev{width:9px;height:9px;border-radius:50%;flex-shrink:0;}
.sev.crit{background:var(--danger);}
.sev.warn{background:var(--secondary);}
.sev.info{background:var(--primary);}
.sev.ok  {background:var(--accent);}

/* ─── ALERTA ROW ─── */
.alert-row{display:flex;align-items:flex-start;gap:13px;padding:13px 20px;border-bottom:1px solid rgba(44,186,236,.06);transition:background .15s;animation:rowIn .3s ease both;}
@keyframes rowIn{from{opacity:0;transform:translateX(-8px)}to{opacity:1;transform:none}}
.alert-row:last-child{border-bottom:none;}
.alert-row:hover{background:rgba(44,186,236,.03);}
.alert-ico{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:.85rem;color:white;flex-shrink:0;}
.ai-crit{background:linear-gradient(135deg,var(--danger),#ff8c42);}
.ai-warn{background:linear-gradient(135deg,var(--secondary),#c28a10);}
.ai-info{background:linear-gradient(135deg,var(--primary),var(--dark-blue));}
.ai-ok  {background:linear-gradient(135deg,var(--accent),#5a9e28);}
.alert-body{flex:1;}
.alert-body strong{display:block;font-size:.84rem;font-weight:600;color:#222;}
.alert-body small{font-size:.73rem;color:var(--muted);}
.alert-meta{display:flex;align-items:center;gap:8px;margin-top:4px;flex-wrap:wrap;}
.cat-tag{font-size:.67rem;font-weight:700;padding:2px 8px;border-radius:20px;background:rgba(44,186,236,.1);color:var(--primary);font-family:var(--mono);}
.ts-tag{font-size:.67rem;color:var(--muted);font-family:var(--mono);}
.resolve-form{flex-shrink:0;align-self:center;}
.btn-resolve{border:1.5px solid rgba(131,191,70,.35);background:rgba(131,191,70,.08);color:#5a9e28;border-radius:8px;padding:5px 13px;font-size:.73rem;font-weight:600;cursor:pointer;transition:all .2s;white-space:nowrap;}
.btn-resolve:hover{background:var(--accent);color:white;border-color:var(--accent);}
.resolved-tag{font-size:.72rem;color:var(--accent);font-weight:600;display:flex;align-items:center;gap:4px;}

/* ─── TABLA DBA ─── */
.dba-tbl{width:100%;border-collapse:collapse;}
.dba-tbl th{padding:10px 16px;font-size:.72rem;font-weight:700;color:var(--primary);text-transform:uppercase;letter-spacing:1px;background:rgba(44,186,236,.04);border-bottom:1.5px solid var(--border);white-space:nowrap;}
.dba-tbl td{padding:11px 16px;font-size:.81rem;border-bottom:1px solid rgba(44,186,236,.06);vertical-align:middle;}
.dba-tbl tbody tr:last-child td{border-bottom:none;}
.dba-tbl tbody tr:hover{background:rgba(44,186,236,.03);}
.mono{font-family:var(--mono);font-size:.77rem;}
.pill{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:.7rem;font-weight:700;white-space:nowrap;}
.pill-red   {background:rgba(255,87,87,.12);color:var(--danger);}
.pill-orange{background:rgba(240,174,42,.12);color:#c28a10;}
.pill-green {background:rgba(131,191,70,.12);color:#5a9e28;}
.pill-blue  {background:rgba(44,186,236,.12);color:var(--dark-blue);}
.pill-purple{background:rgba(160,120,232,.12);color:#7c55c8;}

/* Bar inline */
.mini-bar-wrap{width:90px;background:rgba(44,186,236,.08);border-radius:4px;height:5px;display:inline-block;vertical-align:middle;}
.mini-bar{height:100%;border-radius:4px;transition:width .8s ease;}

/* Connection ring */
.conn-ring-wrap{display:flex;align-items:center;gap:16px;flex-wrap:wrap;padding:16px 20px;}
.conn-donut{position:relative;width:80px;height:80px;flex-shrink:0;}
.conn-donut svg{transform:rotate(-90deg);}
.cd-bg{fill:none;stroke:rgba(44,186,236,.1);stroke-width:7;}
.cd-fg{fill:none;stroke-width:7;stroke-linecap:round;transition:stroke-dasharray 1.2s ease;}
.cd-label{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-family:'Nunito',sans-serif;font-weight:800;font-size:.85rem;text-align:center;}
.conn-stats{flex:1;display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.cs-item{background:rgba(44,186,236,.04);border-radius:10px;padding:10px 14px;}
.cs-label{font-size:.67rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:1px;}
.cs-val{font-family:'Nunito',sans-serif;font-weight:800;font-size:1.1rem;color:var(--text);}

/* Section divider */
.sec-title{font-family:'Nunito',sans-serif;font-weight:800;font-size:1.05rem;color:var(--text);display:flex;align-items:center;gap:9px;margin:22px 0 10px;}
.sec-title i{color:var(--primary);}
.sec-title .stag{font-size:.7rem;font-weight:700;padding:3px 10px;border-radius:20px;background:rgba(44,186,236,.1);color:var(--primary);}

/* empty state */
.empty-state{padding:28px;text-align:center;color:var(--muted);font-size:.84rem;}
.empty-state i{font-size:1.6rem;color:rgba(44,186,236,.25);display:block;margin-bottom:8px;}

/* toast */
.toast-fixed{position:fixed;bottom:24px;right:24px;z-index:9999;}
.toast{background:white;border-left:4px solid var(--accent);border-radius:12px;box-shadow:0 8px 28px rgba(0,0,0,.12);padding:14px 20px;font-size:.84rem;font-weight:600;color:#222;display:flex;align-items:center;gap:10px;animation:toastIn .4s ease;min-width:240px;}
@keyframes toastIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}

/* mobile */
.menu-toggle{display:none;position:fixed;top:15px;left:15px;z-index:200;background:linear-gradient(90deg,var(--primary),var(--secondary));border:none;color:white;width:44px;height:44px;border-radius:50%;font-size:1.1rem;box-shadow:0 4px 14px rgba(44,186,236,.35);cursor:pointer;}
@media(max-width:992px){
    .sidebar{transform:translateX(-100%);}
    .sidebar.open{transform:translateX(0);}
    .main{margin-left:0;padding:18px 14px 36px;}
    .menu-toggle{display:flex;align-items:center;justify-content:center;}
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
            <li class="nav-item">
                <a href="admin_alertas.php" class="nav-link active">
                    <i class="fas fa-bell"></i> Alertas
                    <?php if ($total_pendientes > 0): ?>
                    <span class="badge-pill"><?= $total_pendientes ?></span>
                    <?php endif; ?>
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

<!-- ══ MAIN ══ -->
<div class="main">

    <!-- Top -->
    <div class="top">
        <div>
            <h1 class="page-title">Alertas & <span>logs DBA</span></h1>
            <p class="page-sub"><?= date('d/m/Y H:i:s') ?> · PostgreSQL <?= htmlspecialchars($db_global['datname'] ?? 'N/A') ?></p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="admin_alertas.php?filtro=<?= $filtro ?>" class="btn btn-sm" style="background:rgba(44,186,236,.1);color:var(--primary);border:1.5px solid rgba(44,186,236,.2);border-radius:10px;font-weight:600;font-size:.8rem;display:flex;align-items:center;gap:6px;">
                <i class="fas fa-rotate"></i> Refrescar
            </a>
            <a href="admin_servidor.php" style="background:linear-gradient(90deg,var(--primary),var(--dark-blue));color:white;padding:8px 18px;border-radius:10px;font-weight:600;font-size:.8rem;text-decoration:none;display:flex;align-items:center;gap:7px;">
                <i class="fas fa-server"></i> Monitor DB
            </a>
        </div>
    </div>

    <!-- KPIs -->
    <div class="kpi-strip">
        <div class="kpi">
            <div class="kpi-icon ki-red"><i class="fas fa-circle-exclamation"></i></div>
            <div>
                <div class="kpi-label">Críticas</div>
                <div class="kpi-val <?= $counts['crit']>0?'danger':'' ?>"><?= $counts['crit'] ?></div>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-icon ki-orange"><i class="fas fa-triangle-exclamation"></i></div>
            <div>
                <div class="kpi-label">Advertencias</div>
                <div class="kpi-val <?= $counts['warn']>0?'warn':'' ?>"><?= $counts['warn'] ?></div>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-icon ki-blue"><i class="fas fa-lock"></i></div>
            <div>
                <div class="kpi-label">Bloqueos activos</div>
                <div class="kpi-val <?= count($locks)>0?'danger':'ok' ?>"><?= count($locks) ?></div>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-icon ki-purple"><i class="fas fa-hourglass-half"></i></div>
            <div>
                <div class="kpi-label">Tx largas</div>
                <div class="kpi-val <?= count($long_tx)>0?'warn':'' ?>"><?= count($long_tx) ?></div>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-icon ki-green"><i class="fas fa-plug"></i></div>
            <div>
                <div class="kpi-label">Conexiones</div>
                <div class="kpi-val <?= $conn_nivel=='crit'?'danger':($conn_nivel=='warn'?'warn':'ok') ?>">
                    <?= $conn_data['used']??0 ?>/<?= $conn_data['max_conn']??'?' ?>
                </div>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-icon ki-red"><i class="fas fa-bomb"></i></div>
            <div>
                <div class="kpi-label">Deadlocks</div>
                <div class="kpi-val <?= ($db_global['deadlocks']??0)>0?'danger':'' ?>"><?= $db_global['deadlocks']??0 ?></div>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════ -->
    <!--  SECCIÓN 1 — BLOQUEOS ACTIVOS       -->
    <!-- ════════════════════════════════════ -->
    <div class="sec-title">
        <i class="fas fa-lock"></i> Bloqueos activos
        <?php if (count($locks)>0): ?>
        <span class="stag" style="background:rgba(255,87,87,.12);color:var(--danger);"><?= count($locks) ?> lock<?= count($locks)!=1?'s':'' ?></span>
        <?php endif; ?>
    </div>
    <div class="panel">
        <div class="panel-head">
            <h6><i class="fas fa-link-slash"></i> pg_locks — sesiones bloqueadas</h6>
            <span style="font-size:.73rem;color:var(--muted);">Se actualiza al recargar</span>
        </div>
        <?php if (!empty($locks)): ?>
        <div class="table-responsive">
            <table class="dba-tbl">
                <thead>
                    <tr>
                        <th>PID bloqueado</th>
                        <th>Usuario</th>
                        <th>Tabla</th>
                        <th>Espera</th>
                        <th>PID bloqueante</th>
                        <th>Query bloqueante</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($locks as $l):
                    $espera = floatval($l['espera_seg']);
                    $eClass = $espera > 30 ? 'pill-red' : ($espera > 10 ? 'pill-orange' : 'pill-blue');
                ?>
                <tr>
                    <td><span class="mono" style="color:var(--danger);"><?= $l['pid_bloqueado'] ?></span></td>
                    <td><span class="mono"><?= htmlspecialchars($l['usuario_bloqueado']) ?></span></td>
                    <td><span class="mono" style="color:var(--primary);"><?= htmlspecialchars($l['tabla']??'—') ?></span></td>
                    <td><span class="pill <?= $eClass ?>"><?= $espera ?>s</span></td>
                    <td><span class="mono" style="color:var(--secondary);"><?= $l['pid_bloqueante'] ?></span></td>
                    <td><span class="mono" style="color:#666;font-size:.72rem;" title="<?= htmlspecialchars($l['query_bloqueante']) ?>"><?= htmlspecialchars(substr($l['query_bloqueante'],0,55)).'...' ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-circle-check" style="color:var(--accent);font-size:1.6rem;"></i>
            <div style="color:var(--accent);font-weight:600;margin-top:6px;">Sin bloqueos activos</div>
            <div>Todas las sesiones funcionan sin contención.</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ════════════════════════════════════ -->
    <!--  SECCIÓN 2 — CONEXIONES             -->
    <!-- ════════════════════════════════════ -->
    <div class="sec-title"><i class="fas fa-plug"></i> Conexiones a la base de datos</div>
    <div class="panel">
        <div class="panel-head">
            <h6><i class="fas fa-network-wired"></i> pg_stat_activity — uso de conexiones</h6>
            <?php
            $connColor = ['ok'=>'var(--accent)','warn'=>'var(--secondary)','crit'=>'var(--danger)'][$conn_nivel];
            ?>
            <span class="pill" style="background:rgba(<?= $conn_nivel=='ok'?'131,191,70':($conn_nivel=='warn'?'240,174,42':'255,87,87') ?>,.12);color:<?= $connColor ?>;">
                <?= $pct_conn ?>% uso
            </span>
        </div>
        <div class="conn-ring-wrap">
            <?php
            $r_val = 34; $circ = 2*M_PI*$r_val;
            $dash = round(min($pct_conn,100)/100*$circ,2);
            $ringColor = $conn_nivel=='ok'?'#83bf46':($conn_nivel=='warn'?'#f0ae2a':'#ff5757');
            ?>
            <div class="conn-donut">
                <svg width="80" height="80" viewBox="0 0 80 80">
                    <circle class="cd-bg" cx="40" cy="40" r="<?= $r_val ?>"/>
                    <circle class="cd-fg" cx="40" cy="40" r="<?= $r_val ?>"
                        stroke="<?= $ringColor ?>"
                        stroke-dasharray="<?= $dash ?> <?= $circ ?>"/>
                </svg>
                <div class="cd-label" style="color:<?= $ringColor ?>"><?= $pct_conn ?>%</div>
            </div>
            <div class="conn-stats">
                <div class="cs-item">
                    <div class="cs-label">Usadas</div>
                    <div class="cs-val"><?= $conn_data['used']??0 ?></div>
                </div>
                <div class="cs-item">
                    <div class="cs-label">Máximo</div>
                    <div class="cs-val"><?= $conn_data['max_conn']??'?' ?></div>
                </div>
                <div class="cs-item">
                    <div class="cs-label">Disponibles</div>
                    <div class="cs-val" style="color:var(--accent);"><?= $conn_data['disponibles']??'?' ?></div>
                </div>
                <div class="cs-item">
                    <div class="cs-label">Reservadas</div>
                    <div class="cs-val" style="color:var(--muted);"><?= $conn_data['res_super']??0 ?></div>
                </div>
            </div>
        </div>
        <?php if (!empty($conn_history)): ?>
        <div style="border-top:1.5px solid var(--border);">
            <table class="dba-tbl">
                <thead>
                    <tr><th>Usuario</th><th>Estado</th><th>Sesiones</th><th>Proporción</th></tr>
                </thead>
                <tbody>
                <?php
                $maxSes = max(array_column($conn_history,'total')?:[1]);
                foreach ($conn_history as $ch):
                    $pBar = $maxSes>0?round($ch['total']/$maxSes*100):0;
                    $stClass = $ch['state']=='active'?'pill-green':($ch['state']=='idle in transaction'?'pill-orange':'pill-blue');
                ?>
                <tr>
                    <td><span class="mono"><?= htmlspecialchars($ch['usuario']??'—') ?></span></td>
                    <td><span class="pill <?= $stClass ?>"><?= htmlspecialchars($ch['state']??'—') ?></span></td>
                    <td><span class="mono" style="font-weight:600;"><?= $ch['total'] ?></span></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div class="mini-bar-wrap" style="width:100px;">
                                <div class="mini-bar" style="width:<?= $pBar ?>%;background:linear-gradient(90deg,var(--primary),var(--accent));"></div>
                            </div>
                            <span style="font-size:.72rem;color:var(--muted);font-family:var(--mono);"><?= $pBar ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- ════════════════════════════════════ -->
    <!--  SECCIÓN 3 — TRANSACCIONES LARGAS   -->
    <!-- ════════════════════════════════════ -->
    <div class="sec-title">
        <i class="fas fa-hourglass-half"></i> Transacciones largas
        <?php if (!empty($long_tx)): ?>
        <span class="stag" style="background:rgba(240,174,42,.12);color:#c28a10;">&gt;30s activas</span>
        <?php endif; ?>
    </div>
    <div class="panel">
        <div class="panel-head">
            <h6><i class="fas fa-clock-rotate-left"></i> pg_stat_activity — xact_start</h6>
        </div>
        <?php if (!empty($long_tx)): ?>
        <div class="table-responsive">
            <table class="dba-tbl">
                <thead>
                    <tr><th>PID</th><th>Usuario</th><th>Estado</th><th>Duración tx</th><th>Evento espera</th><th>Query</th></tr>
                </thead>
                <tbody>
                <?php foreach ($long_tx as $tx):
                    $dur = floatval($tx['duracion_seg']);
                    $dc = $dur > 300?'pill-red':($dur>60?'pill-orange':'pill-blue');
                ?>
                <tr>
                    <td><span class="mono"><?= $tx['pid'] ?></span></td>
                    <td><span class="mono"><?= htmlspecialchars($tx['usuario']) ?></span></td>
                    <td>
                        <?php $sc2 = $tx['state']=='active'?'pill-green':($tx['state']=='idle in transaction'?'pill-orange':'pill-blue'); ?>
                        <span class="pill <?= $sc2 ?>"><?= htmlspecialchars($tx['state']) ?></span>
                    </td>
                    <td><span class="pill <?= $dc ?>"><?= $dur ?>s</span></td>
                    <td><span class="mono" style="color:var(--muted);font-size:.72rem;"><?= htmlspecialchars($tx['wait_event_type']??'—').' / '.htmlspecialchars($tx['wait_event']??'—') ?></span></td>
                    <td><span class="mono" style="font-size:.72rem;color:#555;" title="<?= htmlspecialchars($tx['query']) ?>"><?= htmlspecialchars(substr($tx['query'],0,55)) ?>...</span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-circle-check" style="color:var(--accent);font-size:1.6rem;"></i>
            <div style="color:var(--accent);font-weight:600;margin-top:6px;">Sin transacciones largas</div>
            <div>Ninguna transacción lleva más de 30 segundos activa.</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ════════════════════════════════════ -->
    <!--  SECCIÓN 4 — BLOAT / VACUUM         -->
    <!-- ════════════════════════════════════ -->
    <div class="sec-title"><i class="fas fa-trash-can-arrow-up"></i> Bloat de tablas (vacuum pendiente)</div>
    <div class="panel">
        <div class="panel-head">
            <h6><i class="fas fa-broom"></i> pg_stat_user_tables — filas muertas</h6>
            <span style="font-size:.73rem;color:var(--muted);">Tablas con &gt;100 filas muertas</span>
        </div>
        <?php if (!empty($bloat)): ?>
        <div class="table-responsive">
            <table class="dba-tbl">
                <thead>
                    <tr><th>Tabla</th><th>Vivas</th><th>Muertas</th><th>% Bloat</th><th>Tamaño</th><th>Último autovacuum</th></tr>
                </thead>
                <tbody>
                <?php foreach ($bloat as $b):
                    $pct = floatval($b['pct_bloat']);
                    $bc  = $pct>20?'pill-red':($pct>5?'pill-orange':'pill-green');
                    $vac = $b['last_autovacuum'] ?? $b['last_vacuum'] ?? null;
                    $vacTxt = $vac ? date('d/m H:i',strtotime($vac)) : '— nunca';
                    $vacOld = !$vac || strtotime($vac) < strtotime('-3 days');
                ?>
                <tr>
                    <td><span class="mono" style="color:var(--primary);"><?= htmlspecialchars($b['tabla']) ?></span></td>
                    <td><span class="mono"><?= number_format($b['filas_vivas']) ?></span></td>
                    <td><span class="mono" style="color:var(--danger);"><?= number_format($b['filas_muertas']) ?></span></td>
                    <td>
                        <span class="pill <?= $bc ?>"><?= $pct ?>%</span>
                        <div class="mini-bar-wrap" style="width:70px;display:inline-block;margin-left:6px;vertical-align:middle;">
                            <div class="mini-bar" style="width:<?= min($pct,100) ?>%;background:<?= $pct>20?'var(--danger)':($pct>5?'var(--secondary)':'var(--accent)') ?>;"></div>
                        </div>
                    </td>
                    <td><span class="mono"><?= $b['tamano'] ?></span></td>
                    <td><span class="mono" style="color:<?= $vacOld?'var(--danger)':'var(--accent)' ?>;font-size:.74rem;">
                        <?= $vacOld?'⚠ ':'' ?><?= $vacTxt ?>
                    </span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-circle-check" style="color:var(--accent);font-size:1.6rem;"></i>
            <div style="color:var(--accent);font-weight:600;margin-top:6px;">Sin bloat significativo</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ════════════════════════════════════ -->
    <!--  SECCIÓN 5 — ÍNDICES NO USADOS      -->
    <!-- ════════════════════════════════════ -->
    <div class="sec-title"><i class="fas fa-magnifying-glass-minus"></i> Índices sin uso</div>
    <div class="panel">
        <div class="panel-head">
            <h6><i class="fas fa-layer-group"></i> pg_stat_user_indexes — candidatos a DROP</h6>
            <span style="font-size:.73rem;color:var(--muted);">&lt;50 escaneos desde último reset</span>
        </div>
        <?php if (!empty($unused_idx)): ?>
        <div class="table-responsive">
            <table class="dba-tbl">
                <thead>
                    <tr><th>Tabla</th><th>Índice</th><th>Tamaño</th><th>Escaneos</th><th>Tuplas leídas</th></tr>
                </thead>
                <tbody>
                <?php foreach ($unused_idx as $ix): ?>
                <tr>
                    <td><span class="mono" style="color:var(--primary);"><?= htmlspecialchars($ix['tabla']) ?></span></td>
                    <td><span class="mono"><?= htmlspecialchars($ix['indice']) ?></span></td>
                    <td><span class="pill pill-orange"><?= $ix['tamano'] ?></span></td>
                    <td><span class="mono" style="color:var(--danger);"><?= number_format($ix['escaneos']) ?></span></td>
                    <td><span class="mono" style="color:var(--muted);"><?= number_format($ix['tuplas_leidas']) ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-circle-check" style="color:var(--accent);font-size:1.6rem;"></i>
            <div style="color:var(--accent);font-weight:600;margin-top:6px;">Todos los índices se están usando</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ════════════════════════════════════ -->
    <!--  SECCIÓN 6 — REPLICACIÓN            -->
    <!-- ════════════════════════════════════ -->
    <div class="sec-title"><i class="fas fa-copy"></i> Replicación</div>
    <div class="panel">
        <div class="panel-head">
            <h6><i class="fas fa-arrows-rotate"></i> pg_stat_replication</h6>
        </div>
        <?php if (!empty($replication)): ?>
        <div class="table-responsive">
            <table class="dba-tbl">
                <thead>
                    <tr><th>Réplica</th><th>Usuario</th><th>App</th><th>Estado</th><th>Sync</th><th>Lag (tamaño)</th></tr>
                </thead>
                <tbody>
                <?php foreach ($replication as $rep):
                    $sc3 = $rep['state']=='streaming'?'pill-green':($rep['state']=='catchup'?'pill-orange':'pill-red');
                    $syncC = $rep['sync_state']=='sync'?'pill-green':($rep['sync_state']=='async'?'pill-blue':'pill-orange');
                ?>
                <tr>
                    <td><span class="mono"><?= htmlspecialchars($rep['client_addr']) ?></span></td>
                    <td><span class="mono"><?= htmlspecialchars($rep['usename']) ?></span></td>
                    <td><span class="mono"><?= htmlspecialchars($rep['application_name']) ?></span></td>
                    <td><span class="pill <?= $sc3 ?>"><?= htmlspecialchars($rep['state']) ?></span></td>
                    <td><span class="pill <?= $syncC ?>"><?= htmlspecialchars($rep['sync_state']) ?></span></td>
                    <td><span class="mono" style="<?= !empty($rep['lag_size'])?'color:var(--secondary)':'' ?>"><?= htmlspecialchars($rep['lag_size']??'0 bytes') ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-info-circle"></i>
            Sin réplicas configuradas o en modo standalone.
        </div>
        <?php endif; ?>
    </div>

    <!-- ════════════════════════════════════ -->
    <!--  SECCIÓN 7 — ALERTAS MANUALES       -->
    <!-- ════════════════════════════════════ -->
    <div class="sec-title"><i class="fas fa-bell"></i> Alertas del sistema
        <span class="stag"><?= $total_pendientes ?> pendientes</span>
    </div>

    <!-- Filtros -->
    <div class="tabs-bar">
        <?php
        $filtros = [
            'todas'     => ['Todas',      $total_pendientes, 'fa-list'],
            'crit'      => ['Críticas',   $counts['crit'],   'fa-circle-exclamation'],
            'warn'      => ['Advertencias',$counts['warn'],  'fa-triangle-exclamation'],
            'info'      => ['Info',       $counts['info'],   'fa-circle-info'],
            'resueltas' => ['Resueltas',  '',                'fa-circle-check'],
        ];
        foreach ($filtros as $key => [$label, $badge, $icon]):
            $isActive = $filtro === $key;
        ?>
        <a href="admin_alertas.php?filtro=<?= $key ?>" class="tab-btn <?= $isActive?'active':'' ?>" style="text-decoration:none;">
            <i class="fas <?= $icon ?>"></i> <?= $label ?>
            <?php if ($badge !== ''): ?>
            <span class="tbadge <?= !$isActive&&$key=='crit'&&$badge>0?'red-b':'' ?>"><?= $badge ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="panel">
        <?php if (!empty($alertas_custom)): ?>
        <?php foreach ($alertas_custom as $a):
            $tipo  = $a['tipo'];
            $icons = ['crit'=>'fa-circle-exclamation','warn'=>'fa-triangle-exclamation','info'=>'fa-circle-info'];
            $ts    = date('d/m H:i', strtotime($a['creada_en']));
        ?>
        <div class="alert-row">
            <div class="alert-ico ai-<?= $tipo ?>"><i class="fas <?= $icons[$tipo]??'fa-bell' ?>"></i></div>
            <div class="alert-body">
                <strong><?= htmlspecialchars($a['mensaje']) ?></strong>
                <?php if (!empty($a['detalle'])): ?>
                <small><?= htmlspecialchars($a['detalle']) ?></small>
                <?php endif; ?>
                <div class="alert-meta">
                    <span class="cat-tag"><?= htmlspecialchars($a['categoria']) ?></span>
                    <span class="ts-tag"><i class="fas fa-clock" style="font-size:.6rem;"></i> <?= $ts ?></span>
                    <?php if ($a['resuelta']): ?>
                    <span class="ts-tag" style="color:var(--accent);">Resuelta: <?= date('d/m H:i',strtotime($a['resuelta_en'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!$a['resuelta']): ?>
            <form class="resolve-form" method="POST">
                <input type="hidden" name="resolver_id" value="<?= $a['id'] ?>">
                <button type="submit" class="btn-resolve"><i class="fas fa-check" style="font-size:.7rem;"></i> Resolver</button>
            </form>
            <?php else: ?>
            <span class="resolved-tag"><i class="fas fa-circle-check"></i> Resuelta</span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-bell-slash"></i>
            <?php if ($filtro==='resueltas'): ?>
            No hay alertas resueltas registradas.
            <?php else: ?>
            <div style="font-weight:600;margin-top:6px;">Sin alertas en esta categoría</div>
            <div>El sistema opera sin incidencias pendientes.</div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ════════════════════════════════════ -->
    <!--  SECCIÓN 8 — STATS GLOBALES DB      -->
    <!-- ════════════════════════════════════ -->
    <div class="sec-title"><i class="fas fa-chart-bar"></i> Estadísticas globales · <?= htmlspecialchars($db_global['datname']??'') ?></div>
    <div class="panel">
        <div class="panel-head">
            <h6><i class="fas fa-database"></i> pg_stat_database</h6>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1px;background:var(--border);">
            <?php
            $stats = [
                ['label'=>'Commits',      'val'=>number_format($db_global['xact_commit']??0),    'color'=>'var(--accent)',    'icon'=>'fa-check'],
                ['label'=>'Rollbacks',    'val'=>number_format($db_global['xact_rollback']??0),  'color'=>'var(--danger)',    'icon'=>'fa-rotate-left'],
                ['label'=>'% Rollback',   'val'=>($db_global['pct_rollback']??0).'%',            'color'=>'var(--secondary)', 'icon'=>'fa-percent'],
                ['label'=>'Deadlocks',    'val'=>$db_global['deadlocks']??0,                     'color'=>'var(--danger)',    'icon'=>'fa-bomb'],
                ['label'=>'Conflictos',   'val'=>$db_global['conflicts']??0,                     'color'=>'var(--secondary)', 'icon'=>'fa-bolt'],
                ['label'=>'Archivos temp','val'=>$db_global['temp_files']??0,                    'color'=>'var(--purple)',    'icon'=>'fa-file'],
                ['label'=>'Tamaño temp',  'val'=>$db_global['temp_size']??'0 bytes',             'color'=>'var(--purple)',    'icon'=>'fa-hard-drive'],
                ['label'=>'Backends',     'val'=>$db_global['numbackends']??0,                   'color'=>'var(--primary)',   'icon'=>'fa-plug'],
            ];
            foreach ($stats as $s):
            ?>
            <div style="background:var(--panel);padding:16px 20px;">
                <div style="font-size:.68rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">
                    <i class="fas <?= $s['icon'] ?>" style="color:<?= $s['color'] ?>;margin-right:4px;font-size:.7rem;"></i>
                    <?= $s['label'] ?>
                </div>
                <div style="font-family:'Nunito',sans-serif;font-weight:800;font-size:1.3rem;color:<?= $s['color'] ?>;"><?= $s['val'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div><!-- /main -->

<!-- Toast container -->
<div class="toast-fixed" id="toastArea"></div>

<script>
// Sidebar
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

// Toast al resolver alerta
document.querySelectorAll('.resolve-form').forEach(f => {
    f.addEventListener('submit', () => {
        showToast('✓ Alerta marcada como resuelta');
    });
});
function showToast(msg) {
    const area = document.getElementById('toastArea');
    const t = document.createElement('div');
    t.className = 'toast';
    t.innerHTML = `<i class="fas fa-circle-check" style="color:var(--accent);"></i> ${msg}`;
    area.appendChild(t);
    setTimeout(()=>t.remove(), 3200);
}

// Auto-refresco silencioso de KPIs cada 60s
setTimeout(() => location.reload(), 60000);
</script>
</body>
</html>