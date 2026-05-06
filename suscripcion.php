<?php
include 'php/config.php';
session_start();

// Seguridad: Solo tutores (padres)
if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'padre') {
    header("Location: index.php");
    exit();
}

$id_padre = $_SESSION['user_id'];

// Obtener datos del padre desde la base de datos
$query_padre = "SELECT nombre FROM usuarios WHERE id = ? AND tipo = 'padre'";
$stmt_padre = $conn->prepare($query_padre);
$stmt_padre->bind_param("i", $id_padre);
$stmt_padre->execute();
$result_padre = $stmt_padre->get_result();
if ($row_padre = $result_padre->fetch_assoc()) {
    $nombre_padre = $row_padre['nombre'];
} else {
    // Si no se encuentra (raro), usar un fallback
    $nombre_padre = 'Padre';
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

// --- DATOS DE SUSCRIPCIÓN (simulados - reemplazar con consultas reales) ---
$plan_actual = [
    'nombre' => 'Plan Familiar',
    'precio' => 299,
    'moneda' => 'MXN',
    'periodo' => 'mes',
    'proxima_renovacion' => '2026-04-27',
    'dias_restantes' => 31,
    'porcentaje_periodo' => 3,
    'features' => [
        'Hasta 4 perfiles de alumnos',
        'Reportes PDF ilimitados',
        'Mensajería con docentes',
        'Historial completo'
    ]
];

// Métodos de pago (simulados)
$metodos_pago = [
    [
        'tipo' => 'card',
        'marca' => 'Visa',
        'ultimos4' => '4821',
        'expira' => '09/2028',
        'procesador' => 'Stripe',
        'principal' => true
    ],
    [
        'tipo' => 'paypal',
        'email' => 'maria.gonzalez@email.com',
        'principal' => false
    ]
];

// Historial de facturas (simulado)
$facturas = [
    ['id' => 'INV-2026-03', 'plan' => 'Plan Familiar', 'fecha' => '2026-03-27', 'monto' => 299.00, 'estado' => 'paid'],
    ['id' => 'INV-2026-02', 'plan' => 'Plan Familiar', 'fecha' => '2026-02-27', 'monto' => 299.00, 'estado' => 'paid'],
    ['id' => 'INV-2026-01', 'plan' => 'Plan Familiar', 'fecha' => '2026-01-27', 'monto' => 299.00, 'estado' => 'paid'],
    ['id' => 'INV-2025-12', 'plan' => 'Plan Familiar', 'fecha' => '2025-12-27', 'monto' => 299.00, 'estado' => 'paid'],
    ['id' => 'INV-2025-11', 'plan' => 'Plan Familiar', 'fecha' => '2025-11-27', 'monto' => 299.00, 'estado' => 'paid'],
    ['id' => 'INV-2025-10', 'plan' => 'Plan Básico',   'fecha' => '2025-10-27', 'monto' => 149.00, 'estado' => 'refunded'],
    ['id' => 'INV-2025-09', 'plan' => 'Plan Básico',   'fecha' => '2025-09-27', 'monto' => 149.00, 'estado' => 'paid'],
    ['id' => 'INV-2025-08', 'plan' => 'Plan Básico',   'fecha' => '2025-08-27', 'monto' => 149.00, 'estado' => 'paid'],
];

$facturas_json = json_encode($facturas);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suscripción · D&F Mindspace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary:        #2cbaec;
            --primary-dark:   #1a9acc;
            --primary-soft:   rgba(44,186,236,.08);
            --primary-mid:    rgba(44,186,236,.15);
            --secondary:      #f0ae2a;
            --secondary-soft: rgba(240,174,42,.10);
            --accent:         #83bf46;
            --accent-soft:    rgba(131,191,70,.10);
            --danger:         #ff6b8b;
            --danger-soft:    rgba(255,107,139,.08);
            --warn:           #f59e0b;
            --warn-soft:      rgba(245,158,11,.08);
 
            --gray-50:  #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-900: #0f172a;
 
            --sidebar-width: 272px;
            --r-card: 20px;
            --r-pill: 100px;
            --sh-sm:  0 2px 8px rgba(15,23,42,.05);
            --sh-md:  0 8px 24px rgba(15,23,42,.08);
            --sh-lg:  0 20px 48px rgba(44,186,236,.13);
        }
 
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
 
        body {
            font-family: 'DM Sans', sans-serif;
            background: #f2f7fb;
            color: var(--gray-700);
            min-height: 100vh;
            overflow-x: hidden;
        }
 
        /* ══════════════ SIDEBAR ══════════════ */
        .sidebar {
            background: #fff;
            width: var(--sidebar-width);
            height: 100vh; position: fixed; left:0; top:0; z-index:100;
            border-right: 1px solid rgba(44,186,236,.12);
            box-shadow: 2px 0 16px rgba(15,23,42,.05);
            display:flex; flex-direction:column;
            transition: transform .3s ease;
        }
        .sidebar-scroll {
            flex:1; overflow-y:auto; padding-bottom:16px;
            scrollbar-width:thin; scrollbar-color:rgba(44,186,236,.15) transparent;
        }
        .sidebar-scroll::-webkit-scrollbar { width:4px; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background:rgba(44,186,236,.2); border-radius:4px; }
 
        .brand { padding:28px 24px 22px; border-bottom:1px solid rgba(44,186,236,.1); margin-bottom:8px; }
        .brand-mark { display:flex; align-items:center; gap:10px; margin-bottom:4px; }
        .brand-icon { width:36px; height:36px; background:linear-gradient(135deg,var(--primary),var(--secondary)); border-radius:10px; display:flex; align-items:center; justify-content:center; }
        .brand-icon svg { width:20px; height:20px; fill:white; }
        .brand-name { font-family:'Nunito',sans-serif; font-size:1.2rem; font-weight:900; background:linear-gradient(135deg,var(--primary),var(--secondary)); -webkit-background-clip:text; background-clip:text; color:transparent; }
        .brand-tagline { font-size:.68rem; color:var(--gray-400); font-weight:500; letter-spacing:1.5px; text-transform:uppercase; padding-left:46px; }
 
        .user-pill { margin:12px 16px 16px; background:var(--primary-soft); border-radius:14px; padding:12px 16px; display:flex; align-items:center; gap:12px; }
        .user-avatar { width:38px; height:38px; border-radius:50%; background:linear-gradient(135deg,var(--primary),var(--secondary)); display:flex; align-items:center; justify-content:center; font-family:'Nunito',sans-serif; font-weight:800; font-size:.95rem; color:white; flex-shrink:0; }
        .user-name { font-weight:600; font-size:.88rem; color:var(--gray-700); line-height:1.2; }
        .user-role { font-size:.72rem; color:var(--primary); font-weight:500; }
 
        .nav-section { padding:8px 12px 4px; font-size:.65rem; font-weight:700; letter-spacing:1.8px; text-transform:uppercase; color:var(--gray-400); margin-top:8px; }
        .nav-item { margin:2px 12px; }
        .nav-link { display:flex; align-items:center; gap:10px; padding:11px 14px; border-radius:12px; font-size:.88rem; font-weight:500; color:var(--gray-500); transition:all .18s ease; text-decoration:none; }
        .nav-link:hover  { background:var(--gray-100); color:var(--gray-700); }
        .nav-link.active { background:var(--primary-soft); color:var(--primary); font-weight:600; }
        .nav-link .icon  { width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:.85rem; flex-shrink:0; background:transparent; transition:background .18s; }
        .nav-link.active .icon { background:rgba(44,186,236,.15); color:var(--primary); }
        .nav-link:hover .icon  { background:rgba(44,186,236,.08); }
 
        .sidebar-foot { padding:16px 24px; border-top:1px solid rgba(44,186,236,.08); }
        .logout-btn { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:12px; font-size:.85rem; font-weight:600; color:var(--danger); text-decoration:none; transition:background .18s; cursor:pointer; }
        .logout-btn:hover { background:rgba(255,107,139,.07); color:var(--danger); }
 
        /* ══════════════ MAIN ══════════════ */
        .main { margin-left:var(--sidebar-width); padding:36px 48px 64px; min-height:100vh; }
 
        .breadcrumb-row { display:flex; align-items:center; gap:8px; font-size:.8rem; color:var(--gray-400); margin-bottom:28px; }
        .breadcrumb-row a { color:var(--gray-400); text-decoration:none; font-weight:500; transition:color .15s; }
        .breadcrumb-row a:hover { color:var(--primary); }
        .breadcrumb-row .sep { font-size:.65rem; }
        .breadcrumb-row .current { color:var(--gray-700); font-weight:600; }
 
        .page-eyebrow { font-size:.72rem; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:var(--primary); margin-bottom:6px; display:flex; align-items:center; gap:8px; }
        .page-eyebrow::before { content:''; display:inline-block; width:18px; height:2px; background:var(--primary); border-radius:2px; }
        .page-title { font-family:'Nunito',sans-serif; font-size:2rem; font-weight:900; color:var(--gray-900); letter-spacing:-.5px; margin-bottom:4px; line-height:1.1; }
        .page-title span { background:linear-gradient(135deg,var(--primary),var(--secondary)); -webkit-background-clip:text; background-clip:text; color:transparent; }
        .page-sub { font-size:.9rem; color:var(--gray-400); font-weight:400; margin-bottom:32px; }
 
        .section-label { font-size:.72rem; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:var(--gray-400); margin-bottom:14px; display:flex; align-items:center; gap:8px; }
        .section-label::after { content:''; flex:1; height:1px; background:var(--gray-200); }
 
        /* ══════════════ ACTIVE PLAN CARD ══════════════ */
        .plan-hero {
            border-radius: var(--r-card);
            padding: 28px 32px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, var(--gray-900) 0%, #1e3a5f 100%);
            color: white;
            box-shadow: 0 12px 40px rgba(15,23,42,.2);
        }
 
        /* subtle mesh texture */
        .plan-hero::before {
            content:'';
            position:absolute; inset:0;
            background:
                radial-gradient(circle at 80% 20%, rgba(44,186,236,.18) 0%, transparent 55%),
                radial-gradient(circle at 10% 80%, rgba(240,174,42,.12) 0%, transparent 45%);
            pointer-events:none;
        }
 
        .plan-hero-grid {
            position:relative; z-index:1;
            display:grid; grid-template-columns:1fr auto;
            gap:24px; align-items:center;
        }
 
        .plan-badge {
            display:inline-flex; align-items:center; gap:6px;
            background:rgba(44,186,236,.2); border:1px solid rgba(44,186,236,.35);
            border-radius:var(--r-pill); padding:5px 14px;
            font-size:.73rem; font-weight:700; letter-spacing:.5px;
            text-transform:uppercase; color:#7ddcf8;
            margin-bottom:10px;
        }
 
        .plan-name {
            font-family:'Nunito',sans-serif; font-size:1.8rem; font-weight:900;
            letter-spacing:-.4px; margin-bottom:6px; color:white;
        }
 
        .plan-price-row { display:flex; align-items:baseline; gap:6px; margin-bottom:14px; }
        .plan-price { font-family:'Nunito',sans-serif; font-size:2.6rem; font-weight:900; color:white; line-height:1; }
        .plan-period { font-size:.85rem; color:rgba(255,255,255,.5); font-weight:400; }
 
        .plan-features { display:flex; flex-wrap:wrap; gap:8px; }
        .plan-feat {
            display:inline-flex; align-items:center; gap:6px;
            background:rgba(255,255,255,.08); border-radius:var(--r-pill);
            padding:5px 12px; font-size:.77rem; color:rgba(255,255,255,.8); font-weight:500;
        }
        .plan-feat i { font-size:.65rem; color:#7ddcf8; }
 
        /* Renewal info block */
        .renewal-block {
            background:rgba(255,255,255,.07);
            border:1px solid rgba(255,255,255,.12);
            border-radius:16px; padding:20px 22px; text-align:center; min-width:180px;
        }
        .renewal-label { font-size:.68rem; text-transform:uppercase; letter-spacing:1.5px; color:rgba(255,255,255,.45); font-weight:600; margin-bottom:6px; }
        .renewal-date  { font-family:'Nunito',sans-serif; font-size:1.25rem; font-weight:800; color:white; margin-bottom:4px; }
        .renewal-days  { font-size:.78rem; color:#7ddcf8; font-weight:600; }
 
        .renewal-bar-wrap { margin-top:12px; background:rgba(255,255,255,.1); border-radius:4px; height:5px; overflow:hidden; }
        .renewal-bar      { height:100%; background:linear-gradient(90deg,var(--primary),#7ddcf8); border-radius:4px; transition:width 1.2s ease; }
 
        .plan-actions { display:flex; gap:10px; margin-top:20px; position:relative; z-index:1; flex-wrap:wrap; }
 
        .btn-ghost-white {
            display:inline-flex; align-items:center; gap:7px;
            border:1.5px solid rgba(255,255,255,.25); background:rgba(255,255,255,.07);
            color:rgba(255,255,255,.85); border-radius:var(--r-pill);
            padding:10px 20px; font-size:.83rem; font-weight:600;
            cursor:pointer; font-family:'DM Sans',sans-serif; transition:all .2s;
        }
        .btn-ghost-white:hover { background:rgba(255,255,255,.14); border-color:rgba(255,255,255,.4); color:white; }
 
        .btn-danger-ghost {
            display:inline-flex; align-items:center; gap:7px;
            border:1.5px solid rgba(255,107,139,.35); background:rgba(255,107,139,.08);
            color:#fca5a5; border-radius:var(--r-pill);
            padding:10px 20px; font-size:.83rem; font-weight:600;
            cursor:pointer; font-family:'DM Sans',sans-serif; transition:all .2s;
        }
        .btn-danger-ghost:hover { background:rgba(255,107,139,.16); border-color:rgba(255,107,139,.55); color:#fecdd3; }
 
        /* ══════════════ PAYMENT METHOD ══════════════ */
        .payment-method-card {
            background:white; border-radius:var(--r-card);
            border:1px solid rgba(44,186,236,.1);
            box-shadow:var(--sh-sm);
            padding:24px 28px;
            margin-bottom:24px;
        }
 
        .pm-row { display:flex; align-items:center; gap:16px; flex-wrap:wrap; }
 
        .card-visual {
            width:72px; height:46px; border-radius:10px;
            background:linear-gradient(135deg, #1a2744, #2d4a8a);
            display:flex; align-items:center; justify-content:center;
            flex-shrink:0; position:relative; overflow:hidden;
            box-shadow:0 4px 12px rgba(15,23,42,.2);
        }
        .card-visual::before {
            content:''; position:absolute;
            top:4px; left:4px; width:22px; height:16px;
            background:linear-gradient(135deg,rgba(240,174,42,.8),rgba(240,174,42,.4));
            border-radius:4px;
        }
        .card-chip { font-size:.6rem; color:rgba(255,255,255,.6); position:absolute; bottom:6px; right:6px; font-weight:700; letter-spacing:.5px; }
 
        .paypal-visual {
            width:72px; height:46px; border-radius:10px;
            background:#003087; display:flex; align-items:center; justify-content:center;
            flex-shrink:0; box-shadow:0 4px 12px rgba(15,23,42,.2);
        }
        .paypal-visual span { font-family:'Nunito',sans-serif; font-weight:900; font-size:.95rem; color:white; letter-spacing:-1px; }
        .paypal-visual span em { color:#009cde; font-style:normal; }
 
        .pm-info { flex:1; }
        .pm-name { font-weight:700; font-size:.92rem; color:var(--gray-900); margin-bottom:2px; }
        .pm-detail { font-size:.78rem; color:var(--gray-400); }
 
        .pm-status {
            display:inline-flex; align-items:center; gap:5px;
            padding:4px 12px; border-radius:var(--r-pill);
            font-size:.73rem; font-weight:700;
        }
        .pm-status.active { background:var(--accent-soft); color:#3d7318; }
        .pm-status-dot { width:6px; height:6px; border-radius:50%; background:var(--accent); }
 
        .btn-pm-change {
            display:inline-flex; align-items:center; gap:7px;
            border:1.5px solid var(--gray-200); background:white;
            color:var(--gray-600); border-radius:var(--r-pill);
            padding:9px 18px; font-size:.82rem; font-weight:600;
            cursor:pointer; font-family:'DM Sans',sans-serif; transition:all .2s;
        }
        .btn-pm-change:hover { border-color:var(--primary); color:var(--primary); }
 
        /* ══════════════ BILLING HISTORY ══════════════ */
        .billing-panel { background:white; border-radius:var(--r-card); border:1px solid rgba(44,186,236,.1); box-shadow:var(--sh-sm); overflow:hidden; }
 
        .panel-head { padding:18px 24px; border-bottom:1px solid var(--gray-100); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; }
        .panel-head-left { display:flex; align-items:center; gap:10px; }
        .panel-head-icon { width:34px; height:34px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:.85rem; }
        .panel-head-title { font-size:.9rem; font-weight:700; color:var(--gray-900); }
        .panel-head-sub   { font-size:.74rem; color:var(--gray-400); }
 
        /* Filter tabs */
        .filter-tabs { display:flex; gap:4px; background:var(--gray-100); border-radius:10px; padding:4px; }
        .ftab { padding:6px 14px; border-radius:7px; font-size:.77rem; font-weight:600; color:var(--gray-500); cursor:pointer; border:none; background:transparent; font-family:'DM Sans',sans-serif; transition:all .18s; }
        .ftab.active { background:white; color:var(--primary); box-shadow:var(--sh-sm); }
 
        /* Invoice rows */
        .invoice-row {
            display:grid; grid-template-columns:36px 1fr auto auto auto;
            align-items:center; gap:14px;
            padding:14px 24px; border-bottom:1px solid var(--gray-100);
            transition:background .15s; cursor:default;
        }
        .invoice-row:last-child { border-bottom:none; }
        .invoice-row:hover { background:var(--gray-50); }
 
        .inv-icon { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:.85rem; flex-shrink:0; }
 
        .inv-plan  { font-size:.85rem; font-weight:700; color:var(--gray-900); margin-bottom:2px; }
        .inv-date  { font-size:.74rem; color:var(--gray-400); }
 
        .inv-amount { font-family:'Nunito',sans-serif; font-size:1rem; font-weight:800; color:var(--gray-900); text-align:right; white-space:nowrap; }
 
        .inv-badge { display:inline-flex; align-items:center; gap:5px; padding:4px 11px; border-radius:var(--r-pill); font-size:.72rem; font-weight:700; white-space:nowrap; }
        .inv-badge.paid     { background:var(--accent-soft); color:#3d7318; }
        .inv-badge.refunded { background:var(--warn-soft);   color:#92400e; }
 
        .btn-inv-dl {
            display:inline-flex; align-items:center; gap:5px;
            border:1.5px solid var(--gray-200); background:white;
            color:var(--gray-500); border-radius:9px;
            padding:6px 12px; font-size:.75rem; font-weight:600;
            cursor:pointer; font-family:'DM Sans',sans-serif; transition:all .18s;
            white-space:nowrap;
        }
        .btn-inv-dl:hover { border-color:var(--primary); color:var(--primary); }
 
        /* Load more */
        .load-more-row { padding:14px 24px; text-align:center; }
        .btn-load-more { background:none; border:none; color:var(--primary); font-size:.82rem; font-weight:600; cursor:pointer; font-family:'DM Sans',sans-serif; display:inline-flex; align-items:center; gap:6px; transition:opacity .18s; }
        .btn-load-more:hover { opacity:.75; }
 
        /* ══════════════ PLAN CHANGE CARDS ══════════════ */
        .plans-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:24px; }
 
        .plan-option {
            background:white; border-radius:18px;
            border:2px solid var(--gray-200);
            padding:22px 20px; cursor:pointer;
            transition:all .22s; position:relative; overflow:hidden;
        }
        .plan-option:hover { border-color:var(--primary); box-shadow:0 8px 24px rgba(44,186,236,.1); transform:translateY(-2px); }
        .plan-option.current { border-color:var(--primary); background:var(--primary-soft); }
        .plan-option.recommended { border-color:var(--secondary); }
 
        .plan-opt-badge {
            position:absolute; top:12px; right:12px;
            font-size:.65rem; font-weight:800; text-transform:uppercase; letter-spacing:.5px;
            padding:3px 10px; border-radius:var(--r-pill);
        }
        .badge-current     { background:var(--primary-soft); color:var(--primary); }
        .badge-recommended { background:var(--secondary-soft); color:#92600a; }
 
        .plan-opt-icon { font-size:1.6rem; margin-bottom:10px; }
        .plan-opt-name { font-family:'Nunito',sans-serif; font-size:1.05rem; font-weight:800; color:var(--gray-900); margin-bottom:4px; }
        .plan-opt-price { font-family:'Nunito',sans-serif; font-size:1.5rem; font-weight:900; color:var(--primary); line-height:1; margin-bottom:2px; }
        .plan-opt-period { font-size:.73rem; color:var(--gray-400); margin-bottom:12px; }
        .plan-opt-list { list-style:none; display:flex; flex-direction:column; gap:5px; }
        .plan-opt-list li { font-size:.78rem; color:var(--gray-600); display:flex; align-items:flex-start; gap:7px; }
        .plan-opt-list li i { color:var(--accent); font-size:.65rem; margin-top:3px; flex-shrink:0; }
 
        /* ══════════════ MODALS ══════════════ */
        .modal-content { border:none; border-radius:20px; overflow:hidden; box-shadow:0 32px 64px rgba(15,23,42,.15); }
        .modal-header { background:white; padding:22px 26px 18px; border-bottom:1px solid rgba(44,186,236,.1); }
        .modal-body   { padding:22px 26px; }
        .modal-footer { padding:14px 26px 22px; border-top:1px solid rgba(44,186,236,.08); }
 
        /* Card input */
        .card-form { background:var(--gray-50); border-radius:16px; padding:20px; border:1px solid var(--gray-200); margin-bottom:16px; }
        .card-form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .field-label { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--gray-500); display:block; margin-bottom:6px; }
        .field-input {
            width:100%; padding:10px 14px; border-radius:11px;
            border:1.5px solid var(--gray-200); font-family:'DM Sans',sans-serif;
            font-size:.86rem; color:var(--gray-700); background:white;
            transition:border-color .18s;
        }
        .field-input:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(44,186,236,.1); }
        .field-input::placeholder { color:var(--gray-300); }
 
        /* Gateway logos row */
        .gateway-row { display:flex; align-items:center; gap:8px; margin-bottom:20px; }
        .gateway-btn {
            flex:1; border:2px solid var(--gray-200); border-radius:12px; padding:10px;
            display:flex; align-items:center; justify-content:center; gap:8px;
            cursor:pointer; background:white; transition:all .18s; font-family:'DM Sans',sans-serif;
        }
        .gateway-btn.active { border-color:var(--primary); background:var(--primary-soft); }
        .gateway-btn:hover  { border-color:var(--primary); }
        .stripe-logo  { font-family:'Nunito',sans-serif; font-weight:900; font-size:1rem; color:#635bff; letter-spacing:-.5px; }
        .paypal-logo  { font-family:'Nunito',sans-serif; font-weight:900; font-size:1rem; color:#003087; }
        .paypal-logo em { color:#009cde; font-style:normal; }
 
        .secure-note { display:flex; align-items:center; gap:7px; font-size:.75rem; color:var(--gray-400); }
        .secure-note i { color:var(--accent); }
 
        /* Cancel modal */
        .cancel-warning {
            background:var(--danger-soft); border:1px solid rgba(255,107,139,.2);
            border-radius:14px; padding:16px; margin-bottom:16px;
        }
 
        /* Primary button */
        .btn-primary-df {
            display:inline-flex; align-items:center; gap:7px;
            background:linear-gradient(135deg,var(--primary),var(--primary-dark));
            color:white; border:none; border-radius:var(--r-pill);
            padding:11px 24px; font-size:.85rem; font-weight:700;
            cursor:pointer; font-family:'DM Sans',sans-serif;
            box-shadow:0 4px 14px rgba(44,186,236,.3); transition:all .2s;
        }
        .btn-primary-df:hover { transform:translateY(-1px); box-shadow:0 7px 20px rgba(44,186,236,.38); color:white; }
 
        .btn-cancel-red {
            display:inline-flex; align-items:center; gap:7px;
            background:var(--danger); color:white; border:none; border-radius:var(--r-pill);
            padding:11px 24px; font-size:.85rem; font-weight:700;
            cursor:pointer; font-family:'DM Sans',sans-serif; transition:all .2s;
        }
        .btn-cancel-red:hover { background:#e55a7a; transform:translateY(-1px); }
 
        .btn-light-df {
            background:white; border:1.5px solid var(--gray-200); color:var(--gray-600);
            border-radius:var(--r-pill); padding:10px 20px; font-size:.84rem; font-weight:600;
            cursor:pointer; font-family:'DM Sans',sans-serif; transition:all .18s;
        }
        .btn-light-df:hover { border-color:var(--gray-300); color:var(--gray-700); }
 
        /* ══════════════ ANIMATIONS ══════════════ */
        .fade-up { opacity:0; transform:translateY(14px); animation:fuUp .45s forwards; }
        @keyframes fuUp { to { opacity:1; transform:none; } }
 
        /* ══════════════ MENU TOGGLE ══════════════ */
        .menu-toggle { display:none; position:fixed; top:18px; left:18px; z-index:200; background:white; border:1px solid rgba(44,186,236,.2); color:var(--primary); width:44px; height:44px; border-radius:12px; font-size:1.1rem; box-shadow:var(--sh-sm); cursor:pointer; align-items:center; justify-content:center; }
 
        /* ══════════════ TOAST ══════════════ */
        .toast { border:none; border-radius:14px; box-shadow:0 8px 24px rgba(15,23,42,.12); min-width:280px; }
 
        /* ══════════════ RESPONSIVE ══════════════ */
        @media (max-width:1100px) { .plans-grid { grid-template-columns:1fr 1fr; } }
        @media (max-width:992px) {
            .sidebar { transform:translateX(-100%); }
            .sidebar.open { transform:translateX(0); }
            .main { margin-left:0; padding:24px 20px 56px; }
            .menu-toggle { display:flex; }
            .breadcrumb-row { margin-top:56px; }
            .plan-hero-grid { grid-template-columns:1fr; }
        }
        @media (max-width:720px) {
            .plans-grid { grid-template-columns:1fr; }
            .invoice-row { grid-template-columns:36px 1fr auto; }
            .invoice-row .inv-badge,
            .invoice-row .btn-inv-dl { display:none; }
        }
        @media (max-width:540px) { .card-form-row { grid-template-columns:1fr; } }
    </style>
</head>
<body>

<button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>

<!-- ═══════ SIDEBAR ═══════ -->
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
            <a href="suscripcion.php" class="nav-link active">
                <div class="icon"><i class="fas fa-gem"></i></div><span>Suscripción</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="configuracion.php" class="nav-link">
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

    <!-- Breadcrumb -->
    <div class="breadcrumb-row fade-up" style="animation-delay:.02s">
        <a href="dashboard_padre.php"><i class="fas fa-house" style="font-size:.7rem;"></i> Panel</a>
        <span class="sep">›</span>
        <span class="current">Suscripción y pagos</span>
    </div>

    <!-- Header -->
    <div class="fade-up" style="animation-delay:.05s">
        <div class="page-eyebrow">Gestión financiera</div>
        <h1 class="page-title">Tu <span>suscripción</span></h1>
        <p class="page-sub">Administra tu plan, método de pago e historial de facturas</p>
    </div>

    <!-- ─── PLAN HERO ─── -->
    <div class="section-label fade-up" style="animation-delay:.08s">Plan activo</div>
    <div class="plan-hero fade-up" style="animation-delay:.1s">
        <div class="plan-hero-grid">
            <div>
                <div class="plan-badge">
                    <i class="fas fa-circle" style="font-size:.45rem; color:#4ade80;"></i>
                    Activo · Se renueva automáticamente
                </div>
                <div class="plan-name"><?= htmlspecialchars($plan_actual['nombre']) ?></div>
                <div class="plan-price-row">
                    <div class="plan-price">$<?= number_format($plan_actual['precio'], 0) ?></div>
                    <div class="plan-period"><?= $plan_actual['moneda'] ?> / <?= $plan_actual['periodo'] ?></div>
                </div>
                <div class="plan-features">
                    <?php foreach ($plan_actual['features'] as $feat): ?>
                    <span class="plan-feat"><i class="fas fa-check"></i> <?= htmlspecialchars($feat) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="renewal-block">
                <div class="renewal-label">Próxima renovación</div>
                <div class="renewal-date"><?= date('d M Y', strtotime($plan_actual['proxima_renovacion'])) ?></div>
                <div class="renewal-days">en <?= $plan_actual['dias_restantes'] ?> días</div>
                <div class="renewal-bar-wrap">
                    <div class="renewal-bar" id="renewalBar" style="width:<?= $plan_actual['porcentaje_periodo'] ?>%"></div>
                </div>
            </div>
        </div>

        <div class="plan-actions">
            <button class="btn-ghost-white" onclick="openChangePlan()">
                <i class="fas fa-arrows-rotate"></i> Cambiar de plan
            </button>
            <button class="btn-danger-ghost" onclick="openCancel()">
                <i class="fas fa-xmark"></i> Cancelar suscripción
            </button>
        </div>
    </div>

    <!-- ─── PAYMENT METHOD ─── -->
    <div class="section-label fade-up" style="animation-delay:.14s">Método de pago</div>
    <div class="payment-method-card fade-up" style="animation-delay:.16s">
        <?php
        $principal = null;
        $secundarios = [];
        foreach ($metodos_pago as $mp) {
            if ($mp['principal']) $principal = $mp;
            else $secundarios[] = $mp;
        }
        ?>
        <?php if ($principal): ?>
        <div class="pm-row">
            <?php if ($principal['tipo'] == 'card'): ?>
            <div class="card-visual">
                <div class="card-chip"><?= strtoupper($principal['marca']) ?></div>
            </div>
            <div class="pm-info">
                <div class="pm-name"><?= $principal['marca'] ?> terminada en •••• <?= $principal['ultimos4'] ?></div>
                <div class="pm-detail">Vence <?= $principal['expira'] ?> &nbsp;·&nbsp; Procesado por <?= $principal['procesador'] ?></div>
            </div>
            <?php else: ?>
            <div class="paypal-visual">
                <span>Pay<em>Pal</em></span>
            </div>
            <div class="pm-info">
                <div class="pm-name">PayPal</div>
                <div class="pm-detail"><?= $principal['email'] ?> &nbsp;·&nbsp; Cuenta vinculada</div>
            </div>
            <?php endif; ?>
            <div class="pm-status active">
                <div class="pm-status-dot"></div>
                Activo
            </div>
            <button class="btn-pm-change" onclick="openChangePayment('<?= $principal['tipo'] ?>')">
                <i class="fas fa-pen" style="font-size:.75rem;"></i> Actualizar
            </button>
        </div>
        <?php endif; ?>

        <?php foreach ($secundarios as $sec): ?>
        <div style="border-top:1px solid var(--gray-100); margin-top:16px; padding-top:16px;">
            <div class="pm-row">
                <?php if ($sec['tipo'] == 'card'): ?>
                <div class="card-visual">
                    <div class="card-chip"><?= strtoupper($sec['marca']) ?></div>
                </div>
                <div class="pm-info">
                    <div class="pm-name"><?= $sec['marca'] ?> terminada en •••• <?= $sec['ultimos4'] ?></div>
                    <div class="pm-detail">Vence <?= $sec['expira'] ?></div>
                </div>
                <?php else: ?>
                <div class="paypal-visual">
                    <span>Pay<em>Pal</em></span>
                </div>
                <div class="pm-info">
                    <div class="pm-name">PayPal</div>
                    <div class="pm-detail"><?= $sec['email'] ?></div>
                </div>
                <?php endif; ?>
                <div class="pm-status" style="background:var(--gray-100); color:var(--gray-500);">
                    Respaldo
                </div>
                <button class="btn-pm-change" onclick="openChangePayment('<?= $sec['tipo'] ?>')">
                    <i class="fas fa-pen" style="font-size:.75rem;"></i> Gestionar
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ─── BILLING HISTORY ─── -->
    <div class="section-label fade-up" style="animation-delay:.18s">Historial de pagos</div>
    <div class="billing-panel fade-up" style="animation-delay:.2s">
        <div class="panel-head">
            <div class="panel-head-left">
                <div class="panel-head-icon" style="background:var(--secondary-soft); color:var(--secondary);">
                    <i class="fas fa-receipt"></i>
                </div>
                <div>
                    <div class="panel-head-title">Facturas</div>
                    <div class="panel-head-sub">Descarga tus comprobantes de pago</div>
                </div>
            </div>
            <div class="filter-tabs">
                <button class="ftab active" onclick="filterInvoices('all',this)">Todos</button>
                <button class="ftab" onclick="filterInvoices('2026',this)">2026</button>
                <button class="ftab" onclick="filterInvoices('2025',this)">2025</button>
            </div>
        </div>

        <div id="invoiceList"></div>

        <div class="load-more-row" id="loadMoreRow">
            <button class="btn-load-more" onclick="loadMore()">
                <i class="fas fa-chevron-down" style="font-size:.7rem;"></i> Ver más facturas
            </button>
        </div>
    </div>

</main>

<!-- ═══════ MODAL: Change Payment ═══════ -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.05rem;color:var(--gray-900);margin-bottom:2px;">Actualizar método de pago</h5>
                    <p style="font-size:.8rem;color:var(--gray-400);margin:0;">Los cambios aplican en tu próxima renovación</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="field-label">Procesar con</label>
                <div class="gateway-row">
                    <button class="gateway-btn active" id="stripeBtn" onclick="selectGateway('stripe')">
                        <i class="fas fa-lock" style="font-size:.75rem; color:#635bff;"></i>
                        <span class="stripe-logo">stripe</span>
                    </button>
                    <button class="gateway-btn" id="paypalBtn" onclick="selectGateway('paypal')">
                        <i class="fas fa-p" style="font-size:.75rem; color:#003087;"></i>
                        <span class="paypal-logo">Pay<em>Pal</em></span>
                    </button>
                </div>

                <div id="stripeForm">
                    <div class="card-form">
                        <div style="margin-bottom:14px;">
                            <label class="field-label">Número de tarjeta</label>
                            <input class="field-input" type="text" placeholder="1234  5678  9012  3456" maxlength="19" id="cardNumber" oninput="formatCard(this)">
                        </div>
                        <div style="margin-bottom:14px;">
                            <label class="field-label">Nombre en la tarjeta</label>
                            <input class="field-input" type="text" placeholder="MARÍA GONZÁLEZ">
                        </div>
                        <div class="card-form-row">
                            <div>
                                <label class="field-label">Vencimiento</label>
                                <input class="field-input" type="text" placeholder="MM / AA" maxlength="7" oninput="formatExpiry(this)">
                            </div>
                            <div>
                                <label class="field-label">CVC</label>
                                <input class="field-input" type="text" placeholder="•••" maxlength="4">
                            </div>
                        </div>
                    </div>
                    <div class="secure-note">
                        <i class="fas fa-shield-halved"></i>
                        Pagos procesados de forma segura con encriptación SSL por Stripe.
                    </div>
                </div>

                <div id="paypalForm" style="display:none;">
                    <div class="card-form" style="text-align:center;">
                        <div style="margin-bottom:12px;">
                            <div class="paypal-visual" style="width:80px;height:52px;border-radius:12px;margin:0 auto 12px;display:flex;align-items:center;justify-content:center;">
                                <span style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.1rem;color:white;">Pay<em style="color:#009cde;font-style:normal;">Pal</em></span>
                            </div>
                            <p style="font-size:.85rem;color:var(--gray-600);margin:0;">Serás redirigido a PayPal para autorizar el método de pago. El proceso tarda menos de 2 minutos.</p>
                        </div>
                    </div>
                    <div class="secure-note">
                        <i class="fas fa-shield-halved"></i>
                        Autorización segura a través de PayPal. D&F no almacena tus credenciales.
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-end gap-2">
                <button class="btn-light-df" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn-primary-df" onclick="savePaymentMethod()">
                    <i class="fas fa-lock" style="font-size:.75rem;"></i> Guardar método
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════ MODAL: Change Plan ═══════ -->
<div class="modal fade" id="planModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.05rem;color:var(--gray-900);margin-bottom:2px;">Cambiar de plan</h5>
                    <p style="font-size:.8rem;color:var(--gray-400);margin:0;">El cambio aplica en tu próximo ciclo de facturación</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="plans-grid">
                    <div class="plan-option" onclick="selectPlanOption(this,'basico')">
                        <div class="plan-opt-icon">📱</div>
                        <div class="plan-opt-name">Plan Básico</div>
                        <div class="plan-opt-price">$149</div>
                        <div class="plan-opt-period">MXN / mes</div>
                        <ul class="plan-opt-list">
                            <li><i class="fas fa-check"></i> 1 perfil de alumno</li>
                            <li><i class="fas fa-check"></i> Reportes básicos</li>
                            <li><i class="fas fa-check"></i> Seguimiento de progreso</li>
                        </ul>
                    </div>
                    <div class="plan-option current" onclick="selectPlanOption(this,'familiar')">
                        <div class="plan-opt-badge badge-current">Actual</div>
                        <div class="plan-opt-icon">👨‍👩‍👧‍👦</div>
                        <div class="plan-opt-name">Plan Familiar</div>
                        <div class="plan-opt-price">$299</div>
                        <div class="plan-opt-period">MXN / mes</div>
                        <ul class="plan-opt-list">
                            <li><i class="fas fa-check"></i> Hasta 4 perfiles</li>
                            <li><i class="fas fa-check"></i> Reportes PDF ilimitados</li>
                            <li><i class="fas fa-check"></i> Mensajería con docentes</li>
                            <li><i class="fas fa-check"></i> Historial completo</li>
                        </ul>
                    </div>
                    <div class="plan-option recommended" onclick="selectPlanOption(this,'institucional')">
                        <div class="plan-opt-badge badge-recommended">Recomendado</div>
                        <div class="plan-opt-icon">🏫</div>
                        <div class="plan-opt-name">Plan Institucional</div>
                        <div class="plan-opt-price">$599</div>
                        <div class="plan-opt-period">MXN / mes</div>
                        <ul class="plan-opt-list">
                            <li><i class="fas fa-check"></i> Perfiles ilimitados</li>
                            <li><i class="fas fa-check"></i> Panel multigrupo</li>
                            <li><i class="fas fa-check"></i> Análisis avanzado</li>
                            <li><i class="fas fa-check"></i> Soporte prioritario</li>
                        </ul>
                    </div>
                </div>
                <div class="secure-note">
                    <i class="fas fa-circle-info" style="color:var(--primary);"></i>
                    Si cambias a un plan menor, el ajuste se aplica al siguiente ciclo. Si subes de plan, el acceso es inmediato con prorrateo.
                </div>
            </div>
            <div class="modal-footer justify-content-end gap-2">
                <button class="btn-light-df" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn-primary-df" id="confirmPlanBtn" disabled style="opacity:.5;cursor:not-allowed;" onclick="confirmPlanChange()">
                    <i class="fas fa-arrows-rotate" style="font-size:.75rem;"></i> Confirmar cambio
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════ MODAL: Cancel ═══════ -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.05rem;color:var(--gray-900);margin-bottom:2px;">Cancelar suscripción</h5>
                    <p style="font-size:.8rem;color:var(--gray-400);margin:0;">Esta acción no puede deshacerse fácilmente</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="cancel-warning">
                    <div style="display:flex;align-items:flex-start;gap:10px;">
                        <i class="fas fa-triangle-exclamation" style="color:var(--danger);margin-top:2px;"></i>
                        <div>
                            <div style="font-size:.85rem;font-weight:700;color:var(--danger);margin-bottom:4px;">¿Seguro que deseas cancelar?</div>
                            <p style="font-size:.8rem;color:var(--gray-600);margin:0;line-height:1.55;">
                                Al cancelar perderás acceso a los reportes PDF, la mensajería con docentes y el historial completo al final del período actual (27 Abr 2026). Los datos de tus hijos se conservarán durante 90 días.
                            </p>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom:14px;">
                    <label class="field-label">¿Por qué cancelas? (opcional)</label>
                    <select class="field-input" style="width:100%;cursor:pointer;">
                        <option value="">Selecciona un motivo</option>
                        <option>El precio es muy alto</option>
                        <option>Ya no necesito el servicio</option>
                        <option>Problemas técnicos frecuentes</option>
                        <option>Encontré una alternativa mejor</option>
                        <option>Otro motivo</option>
                    </select>
                </div>
                <div style="background:var(--accent-soft);border:1px solid rgba(131,191,70,.2);border-radius:12px;padding:14px;display:flex;align-items:flex-start;gap:10px;">
                    <i class="fas fa-lightbulb" style="color:var(--accent);margin-top:1px;font-size:.9rem;"></i>
                    <p style="font-size:.78rem;color:var(--gray-600);margin:0;line-height:1.5;">¿Sabías que puedes pausar tu suscripción hasta 3 meses sin perder tus datos? <strong style="color:var(--accent);">Contacta a soporte</strong> antes de cancelar.</p>
                </div>
            </div>
            <div class="modal-footer justify-content-between gap-2 flex-wrap">
                <button class="btn-light-df" data-bs-dismiss="modal">Mantener plan</button>
                <button class="btn-cancel-red" onclick="confirmCancel()">
                    <i class="fas fa-xmark" style="font-size:.75rem;"></i> Sí, cancelar suscripción
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ═══════════════════════════════════════════════════════════════════════════════
   JAVASCRIPT COMPLETO (INCLUYE FACTURAS, FILTROS, MODALES, TOAST, ETC.)
   ═══════════════════════════════════════════════════════════════════════════════ */

// ── Sidebar toggle ──
const sidebar     = document.getElementById('sidebar');
const menuToggle  = document.getElementById('menuToggle');
menuToggle.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    menuToggle.innerHTML = sidebar.classList.contains('open')
        ? '<i class="fas fa-times"></i>' : '<i class="fas fa-bars"></i>';
});
document.addEventListener('click', e => {
    if (window.innerWidth < 992 && sidebar.classList.contains('open')
        && !sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
        sidebar.classList.remove('open');
        menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
    }
});

// ── Datos de facturas desde PHP ──
const allInvoices = <?= $facturas_json ?>;

let visibleCount = 4;
let activeFilter = 'all';

function filterInvoices(filter, btn) {
    activeFilter = filter;
    visibleCount = 4;
    document.querySelectorAll('.ftab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    renderInvoices();
}

function getFiltered() {
    if (activeFilter === 'all') return allInvoices;
    return allInvoices.filter(inv => inv.fecha.startsWith(activeFilter));
}

function renderInvoices() {
    const filtered = getFiltered();
    const visible  = filtered.slice(0, visibleCount);
    const list     = document.getElementById('invoiceList');
    const lmRow    = document.getElementById('loadMoreRow');

    list.innerHTML = visible.map(inv => {
        const fechaFormateada = new Date(inv.fecha).toLocaleDateString('es-MX', { day:'2-digit', month:'short', year:'numeric' }).replace(/ /g, ' ');
        const iconBg   = inv.estado === 'paid' ? 'var(--primary-soft)' : 'var(--warn-soft)';
        const iconColor= inv.estado === 'paid' ? 'var(--primary)' : 'var(--warn)';
        const icon     = inv.estado === 'paid' ? 'fa-file-invoice' : 'fa-rotate-left';
        const badgeClass = inv.estado === 'paid' ? 'paid' : 'refunded';
        const badgeText  = inv.estado === 'paid' ? 'Pagado' : 'Reembolsado';
        const badgeIcon  = inv.estado === 'paid' ? 'fa-circle-check' : 'fa-rotate-left';
        return `
        <div class="invoice-row">
            <div class="inv-icon" style="background:${iconBg}; color:${iconColor};">
                <i class="fas ${icon}"></i>
            </div>
            <div>
                <div class="inv-plan">${inv.plan}</div>
                <div class="inv-date">${inv.id} &nbsp;·&nbsp; ${fechaFormateada}</div>
            </div>
            <div class="inv-amount">$${inv.monto.toFixed(2)} MXN</div>
            <div>
                <span class="inv-badge ${badgeClass}">
                    <i class="fas ${badgeIcon}" style="font-size:.6rem;"></i>
                    ${badgeText}
                </span>
            </div>
            <div>
                <button class="btn-inv-dl" onclick="downloadInvoice('${inv.id}')">
                    <i class="fas fa-download" style="font-size:.7rem;"></i> PDF
                </button>
            </div>
        </div>
    `}).join('');

    lmRow.style.display = visibleCount >= filtered.length ? 'none' : 'block';
}

function loadMore() {
    visibleCount += 4;
    renderInvoices();
}

function downloadInvoice(id) {
    showToast(`Descargando factura ${id}…`, 'info');
}

document.addEventListener('DOMContentLoaded', renderInvoices);

// ── Modal de pago ──
let currentGateway = 'stripe';

function openChangePayment(type = 'stripe') {
    selectGateway(type);
    new bootstrap.Modal(document.getElementById('paymentModal')).show();
}

function selectGateway(gw) {
    currentGateway = gw;
    document.getElementById('stripeBtn').classList.toggle('active', gw === 'stripe');
    document.getElementById('paypalBtn').classList.toggle('active', gw === 'paypal');
    document.getElementById('stripeForm').style.display  = gw === 'stripe'  ? 'block' : 'none';
    document.getElementById('paypalForm').style.display  = gw === 'paypal'  ? 'block' : 'none';
}

function formatCard(input) {
    let v = input.value.replace(/\D/g,'').substring(0,16);
    input.value = v.match(/.{1,4}/g)?.join('  ') || v;
}

function formatExpiry(input) {
    let v = input.value.replace(/\D/g,'').substring(0,4);
    if (v.length >= 2) v = v.substring(0,2) + ' / ' + v.substring(2);
    input.value = v;
}

function savePaymentMethod() {
    if (currentGateway === 'stripe') {
        const num = document.getElementById('cardNumber').value;
        if (!num || num.replace(/\s/g,'').length < 16) {
            showToast('Ingresa un número de tarjeta válido', 'warn');
            return;
        }
    }
    bootstrap.Modal.getInstance(document.getElementById('paymentModal')).hide();
    showToast('Método de pago actualizado correctamente', 'success');
}

// ── Modal de cambio de plan ──
let selectedPlan = 'familiar';

function openChangePlan() {
    new bootstrap.Modal(document.getElementById('planModal')).show();
}

function selectPlanOption(el, plan) {
    document.querySelectorAll('.plan-option').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    selectedPlan = plan;
    const btn = document.getElementById('confirmPlanBtn');
    const isDifferent = plan !== 'familiar';
    btn.disabled = !isDifferent;
    btn.style.opacity  = isDifferent ? '1' : '.5';
    btn.style.cursor   = isDifferent ? 'pointer' : 'not-allowed';
}

function confirmPlanChange() {
    const names = { basico:'Plan Básico', familiar:'Plan Familiar', institucional:'Plan Institucional' };
    bootstrap.Modal.getInstance(document.getElementById('planModal')).hide();
    showToast(`Cambio a ${names[selectedPlan]} programado para el próximo ciclo`, 'success');
}

// ── Modal de cancelación ──
function openCancel() {
    new bootstrap.Modal(document.getElementById('cancelModal')).show();
}

function confirmCancel() {
    bootstrap.Modal.getInstance(document.getElementById('cancelModal')).hide();
    showToast('Suscripción cancelada. Tienes acceso hasta el 27 Abr 2026.', 'warn');
}

// ── Toast ──
function showToast(msg, type = 'info') {
    const colors = {
        success: { bg:'#83bf46', icon:'circle-check' },
        warn:    { bg:'#f0ae2a', icon:'triangle-exclamation' },
        info:    { bg:'#2cbaec', icon:'circle-info' },
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

// ── Estilo para plan seleccionado ──
const styleEl = document.createElement('style');
styleEl.textContent = `.plan-option.selected:not(.current) { border-color:var(--primary); background:var(--primary-soft); }`;
document.head.appendChild(styleEl);
</script>

</body>
</html>