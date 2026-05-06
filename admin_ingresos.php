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

// --- MÉTRICAS FINANCIERAS (simuladas - reemplazar con consultas reales) ---
// Total ingresos del mes actual
$ingresos_mes = 48750.00;
$ingresos_mes_anterior = 42100.00;
$variacion_porcentaje = round((($ingresos_mes - $ingresos_mes_anterior) / $ingresos_mes_anterior) * 100, 1);

// Suscripciones activas
$suscripciones_activas = 124;
$nuevas_suscripciones = 12;

// Ticket promedio
$ticket_promedio = $ingresos_mes / $suscripciones_activas;

// MRR (Monthly Recurring Revenue)
$mrr = 42300.00;

// Próximos cobros (estimado próxima semana)
$proximos_cobros = 8750.00;

// --- DATOS PARA GRÁFICO (últimos 6 meses) ---
$meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun'];
$ingresos_mensuales = [38500, 40100, 42100, 44800, 46200, 48750];

// --- TRANSACCIONES RECIENTES (simuladas) ---
$transacciones = [
    ['fecha' => '2026-04-12', 'usuario' => 'María González', 'plan' => 'Familiar', 'monto' => 299.00, 'metodo' => 'Stripe', 'estado' => 'Completado'],
    ['fecha' => '2026-04-11', 'usuario' => 'Carlos Rivas', 'plan' => 'Institucional', 'monto' => 599.00, 'metodo' => 'PayPal', 'estado' => 'Completado'],
    ['fecha' => '2026-04-10', 'usuario' => 'Ana Torres', 'plan' => 'Familiar', 'monto' => 299.00, 'metodo' => 'Stripe', 'estado' => 'Completado'],
    ['fecha' => '2026-04-09', 'usuario' => 'Javier Moreno', 'plan' => 'Básico', 'monto' => 149.00, 'metodo' => 'Transferencia', 'estado' => 'Pendiente'],
    ['fecha' => '2026-04-08', 'usuario' => 'Lucía Ramírez', 'plan' => 'Familiar', 'monto' => 299.00, 'metodo' => 'Stripe', 'estado' => 'Completado'],
    ['fecha' => '2026-04-07', 'usuario' => 'Roberto Sánchez', 'plan' => 'Institucional', 'monto' => 599.00, 'metodo' => 'PayPal', 'estado' => 'Completado'],
    ['fecha' => '2026-04-06', 'usuario' => 'Elena Vargas', 'plan' => 'Básico', 'monto' => 149.00, 'metodo' => 'Stripe', 'estado' => 'Reembolsado'],
];

// Mensaje de sesión (si se implementan acciones)
$mensaje = $_SESSION['mensaje'] ?? null;
unset($_SESSION['mensaje']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingresos - D&F Mindspace Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;800&family=Poppins:wght@400;500;600&family=Fredoka+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
    --primary: #2cbaec;
    --secondary: #f0ae2a;
    --accent: #83bf46;
    --danger: #ff5757;
    --dark-blue: #1a8db8;
    --sidebar-width: 260px;
    --shadow: 0 4px 20px rgba(44,186,236,0.10);
    --gray-100: #f1f5f9;
    --gray-200: #e2e8f0;
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

/* Métricas */
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
.metric-card.c-purple{ border-top-color: #9c88ff; }

.mc-icon {
    width: 50px; height: 50px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem; color: white; margin-bottom: 14px;
}
.mc-icon.blue   { background: linear-gradient(135deg, var(--primary), var(--dark-blue)); box-shadow: 0 5px 14px rgba(44,186,236,.3); }
.mc-icon.green  { background: linear-gradient(135deg, var(--accent), #6ca839); box-shadow: 0 5px 14px rgba(131,191,70,.3); }
.mc-icon.orange { background: linear-gradient(135deg, var(--secondary), #d69925); box-shadow: 0 5px 14px rgba(240,174,42,.3); }
.mc-icon.red    { background: linear-gradient(135deg, var(--danger), #ff8c42); box-shadow: 0 5px 14px rgba(255,87,87,.25); }
.mc-icon.purple { background: linear-gradient(135deg, #9c88ff, #8c7ae6); box-shadow: 0 5px 14px rgba(156,136,255,.3); }

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

/* Panel de usuarios / tabla */
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
.search-wrapper {
    display: flex; align-items: center; gap: 8px;
    background: rgba(44,186,236,.06);
    border: 1.5px solid rgba(44,186,236,.15);
    border-radius: 10px; padding: 6px 12px;
}
.search-wrapper input {
    border: none; background: transparent; outline: none;
    font-size: .82rem; font-family: 'Poppins', sans-serif;
    color: #333; width: 160px;
}
.search-wrapper i { color: #aaa; font-size: .8rem; }

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

.status-tag {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 10px;
    font-size: .72rem; font-weight: 700;
}
.status-tag.active    { background: rgba(131,191,70,.12); color: #6ca839; }
.status-tag.warning   { background: rgba(240,174,42,.12); color: #d69925; }
.status-tag.danger    { background: rgba(255,87,87,.12); color: var(--danger); }
.status-dot-sm { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

.act-btn {
    border: 1.5px solid rgba(44,186,236,.22);
    background: white; border-radius: 8px;
    padding: 5px 9px; font-size: .76rem;
    cursor: pointer; transition: all .2s; color: var(--primary);
}
.act-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }

.tbl-footer {
    padding: 14px 22px;
    border-top: 1.5px solid rgba(44,186,236,.08);
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 8px;
}
.tbl-footer span { font-size: .78rem; color: #aaa; }
.tbl-footer a { font-size: .82rem; font-weight: 600; color: var(--primary); text-decoration: none; }
.tbl-footer a:hover { text-decoration: underline; }

/* Filtros (para admin_cursos) */
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

/* Paginación */
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

/* MENU TOGGLE */
.menu-toggle {
    display: none; position: fixed; top: 15px; left: 15px;
    z-index: 200; background: linear-gradient(90deg, var(--primary), var(--secondary));
    border: none; color: white; width: 44px; height: 44px;
    border-radius: 50%; font-size: 1.1rem;
    box-shadow: 0 4px 14px rgba(44,186,236,.35); cursor: pointer;
}

/* RESPONSIVE */
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
            <li class="nav-item"><a href="admin_cursos.php" class="nav-link"><i class="fas fa-map-marked-alt"></i> Cursos</a></li>
            <li class="nav-item"><a href="admin_ingresos.php" class="nav-link active"><i class="fas fa-coins"></i> Ingresos</a></li>
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
            <h1 class="page-title">Panel de <span>ingresos</span></h1>
            <p class="page-sub">Métricas financieras y transacciones recientes</p>
        </div>
        <button class="btn-add" onclick="exportarReporte()"><i class="fas fa-download"></i> Exportar reporte</button>
    </div>

    <!-- Métricas principales -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="metric-card c-green">
                <div class="trend <?= $variacion_porcentaje >= 0 ? 'up' : 'down' ?>">
                    <i class="fas fa-arrow-<?= $variacion_porcentaje >= 0 ? 'up' : 'down' ?>" style="font-size:.6rem;"></i> 
                    <?= abs($variacion_porcentaje) ?>%
                </div>
                <div class="mc-icon green"><i class="fas fa-dollar-sign"></i></div>
                <div class="mc-num">$<?= number_format($ingresos_mes, 0) ?></div>
                <div class="mc-title">Ingresos este mes</div>
                <div class="mc-sub">vs $<?= number_format($ingresos_mes_anterior, 0) ?> mes anterior</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card c-blue">
                <div class="trend up"><i class="fas fa-arrow-up" style="font-size:.6rem;"></i> +<?= $nuevas_suscripciones ?></div>
                <div class="mc-icon blue"><i class="fas fa-users"></i></div>
                <div class="mc-num"><?= $suscripciones_activas ?></div>
                <div class="mc-title">Suscripciones activas</div>
                <div class="mc-sub">+<?= $nuevas_suscripciones ?> nuevas este mes</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card c-orange">
                <div class="trend neu"><i class="fas fa-minus" style="font-size:.6rem;"></i> estable</div>
                <div class="mc-icon orange"><i class="fas fa-chart-bar"></i></div>
                <div class="mc-num">$<?= number_format($ticket_promedio, 0) ?></div>
                <div class="mc-title">Ticket promedio</div>
                <div class="mc-sub">Por suscripción activa</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card c-purple">
                <div class="trend up"><i class="fas fa-arrow-up" style="font-size:.6rem;"></i> 5%</div>
                <div class="mc-icon purple" style="background: linear-gradient(135deg, #9c88ff, #8c7ae6);"><i class="fas fa-calendar-alt"></i></div>
                <div class="mc-num">$<?= number_format($proximos_cobros, 0) ?></div>
                <div class="mc-title">Próximos cobros</div>
                <div class="mc-sub">Estimado próx. 7 días</div>
            </div>
        </div>
    </div>

    <!-- Gráfico de ingresos mensuales -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="users-panel">
                <div class="users-head">
                    <h6><i class="fas fa-chart-line me-2"></i> Ingresos últimos 6 meses</h6>
                    <span class="badge bg-primary">MRR: $<?= number_format($mrr, 0) ?></span>
                </div>
                <div class="p-3">
                    <canvas id="ingresosChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de transacciones recientes -->
    <div class="users-panel">
        <div class="users-head">
            <h6><i class="fas fa-list me-2"></i> Transacciones recientes</h6>
            <div class="search-wrapper">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Buscar transacción..." id="searchTransaccion">
            </div>
        </div>

        <div class="table-responsive">
            <table class="utbl" id="tablaTransacciones">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th>Plan</th>
                        <th>Monto</th>
                        <th>Método</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transacciones as $t): 
                        $estadoClass = $t['estado'] == 'Completado' ? 'active' : ($t['estado'] == 'Pendiente' ? 'warning' : 'danger');
                    ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($t['fecha'])) ?></td>
                        <td><?= htmlspecialchars($t['usuario']) ?></td>
                        <td><?= $t['plan'] ?></td>
                        <td><strong>$<?= number_format($t['monto'], 2) ?> MXN</strong></td>
                        <td><?= $t['metodo'] ?></td>
                        <td>
                            <span class="status-tag <?= $estadoClass ?>">
                                <span class="status-dot-sm"></span> <?= $t['estado'] ?>
                            </span>
                        </td>
                        <td>
                            <button class="act-btn" onclick="verDetalle('<?= $t['usuario'] ?>')" title="Ver detalle"><i class="fas fa-eye"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="tbl-footer">
            <span>Mostrando <?= count($transacciones) ?> transacciones recientes</span>
            <a href="#">Ver todas las transacciones <i class="fas fa-arrow-right ms-1"></i></a>
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

// Gráfico de ingresos
const ctx = document.getElementById('ingresosChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($meses) ?>,
        datasets: [{
            label: 'Ingresos (MXN)',
            data: <?= json_encode($ingresos_mensuales) ?>,
            borderColor: '#2cbaec',
            backgroundColor: 'rgba(44, 186, 236, 0.1)',
            borderWidth: 3,
            pointBackgroundColor: '#2cbaec',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 5,
            tension: 0.3,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: (ctx) => ` $${ctx.raw.toLocaleString()} MXN`
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: (value) => '$' + value.toLocaleString()
                }
            }
        }
    }
});

// Filtro de búsqueda en tabla
document.getElementById('searchTransaccion').addEventListener('keyup', function() {
    const term = this.value.toLowerCase();
    const rows = document.querySelectorAll('#tablaTransacciones tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(term) ? '' : 'none';
    });
});

// Funciones placeholder
function verDetalle(usuario) {
    alert('Detalle de transacción de ' + usuario + ' (funcionalidad en desarrollo)');
}

function exportarReporte() {
    alert('Exportando reporte financiero...');
}
</script>
</body>
</html>