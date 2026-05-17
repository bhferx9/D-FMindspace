<?php
include 'php/config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'admin') {
    header("Location: index.php");
    exit();
}

$admin_id     = (int)$_SESSION['user_id'];
$admin_nombre = $_SESSION['usuario_nombre'] ?? 'Administrador';
$partes       = explode(' ', trim($admin_nombre));
$iniciales    = count($partes) >= 2
    ? strtoupper(substr($partes[0],0,1).substr($partes[1],0,1))
    : strtoupper(substr($admin_nombre,0,2));

// ============================================================
//  CREAR TABLAS SI NO EXISTEN (PostgreSQL)
// ============================================================
try {
    $conn->pdo->exec("
        CREATE TABLE IF NOT EXISTS config_admin (
            clave       VARCHAR(80) PRIMARY KEY,
            valor       TEXT,
            actualizado TIMESTAMPTZ DEFAULT now()
        )
    ");

    $conn->pdo->exec("
        CREATE TABLE IF NOT EXISTS config_notificaciones (
            id            SERIAL PRIMARY KEY,
            admin_id      INT NOT NULL,
            canal         VARCHAR(20) NOT NULL DEFAULT 'panel',
            evento        VARCHAR(60) NOT NULL,
            activo        BOOLEAN DEFAULT TRUE,
            umbral        INT DEFAULT NULL,
            actualizado   TIMESTAMPTZ DEFAULT now(),
            UNIQUE(admin_id, evento)
        )
    ");
} catch(PDOException $e) {
    // Tablas ya existen o error de permisos
}

// ============================================================
//  HELPERS (CORREGIDOS)
// ============================================================
function cfg_get($conn, $clave, $default = '') {
    try {
        $stmt = $conn->pdo->prepare("SELECT valor FROM config_admin WHERE clave = :clave");
        $stmt->execute([':clave' => $clave]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row['valor'];
    } catch(PDOException $e) {}
    return $default;
}

function cfg_set($conn, $clave, $valor) {
    try {
        $stmt = $conn->pdo->prepare("
            INSERT INTO config_admin(clave, valor, actualizado)
            VALUES(:clave, :valor, now())
            ON CONFLICT (clave) DO UPDATE 
            SET valor = EXCLUDED.valor, actualizado = now()
        ");
        $stmt->execute([':clave' => $clave, ':valor' => $valor]);
    } catch(PDOException $e) {}
}

// Catálogo de eventos de notificación (sin cambios)
$eventos_catalogo = [
    'seguridad' => [
        ['key'=>'login_fallido',       'label'=>'Intentos de login fallidos',     'umbral'=>true,  'desc'=>'Nº de intentos antes de alertar'],
        ['key'=>'nuevo_admin',         'label'=>'Nuevo administrador creado',      'umbral'=>false, 'desc'=>''],
        ['key'=>'sesion_larga',        'label'=>'Sesión activa inusualmente larga','umbral'=>true,  'desc'=>'Minutos de sesión'],
    ],
    'base_de_datos' => [
        ['key'=>'deadlock',            'label'=>'Deadlock detectado',             'umbral'=>false, 'desc'=>''],
        ['key'=>'conexiones_limite',   'label'=>'Conexiones cerca del límite',    'umbral'=>true,  'desc'=>'% de uso para alertar'],
        ['key'=>'vacuum_vencido',      'label'=>'Autovacuum vencido',             'umbral'=>true,  'desc'=>'Días sin vacuum'],
        ['key'=>'tx_larga',            'label'=>'Transacción larga activa',       'umbral'=>true,  'desc'=>'Segundos para alertar'],
    ],
    'plataforma' => [
        ['key'=>'nuevo_usuario',       'label'=>'Nuevo usuario registrado',       'umbral'=>false, 'desc'=>''],
        ['key'=>'usuario_suspendido',  'label'=>'Usuario suspendido',             'umbral'=>false, 'desc'=>''],
        ['key'=>'curso_publicado',     'label'=>'Curso publicado',                'umbral'=>false, 'desc'=>''],
        ['key'=>'pago_fallido',        'label'=>'Pago fallido',                   'umbral'=>false, 'desc'=>''],
    ],
    'sistema' => [
        ['key'=>'almacenamiento',      'label'=>'Almacenamiento alto',            'umbral'=>true,  'desc'=>'% de disco para alertar'],
        ['key'=>'error_critico',       'label'=>'Error crítico en logs',          'umbral'=>false, 'desc'=>''],
        ['key'=>'backup_fallido',      'label'=>'Backup no completado',           'umbral'=>false, 'desc'=>''],
    ],
];

// ============================================================
//  CARGAR NOTIFICACIONES GUARDADAS (CORREGIDO)
// ============================================================
$notif_guardadas = [];
try {
    $stmt = $conn->pdo->prepare("SELECT * FROM config_notificaciones WHERE admin_id = :admin_id");
    $stmt->execute([':admin_id' => $admin_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $notif_guardadas[$row['evento']] = $row;
    }
} catch(PDOException $e) {}

// ============================================================
//  CARGAR DATOS DEL ADMIN (CORREGIDO)
// ============================================================
$admin_data = [];
try {
    $stmt = $conn->pdo->prepare("SELECT id, nombre, email, avatar FROM usuarios WHERE id = :id");
    $stmt->execute([':id' => $admin_id]);
    $admin_data = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch(PDOException $e) {}

// ============================================================
//  PROCESAR FORMULARIOS (CORREGIDO)
// ============================================================
$errors   = [];
$success  = [];
$tab_open = $_POST['tab_open'] ?? $_GET['tab'] ?? 'perfil';

// ── GUARDAR PERFIL ──────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['guardar_perfil'])) {
    $tab_open  = 'perfil';
    $nombre    = trim($_POST['nombre'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $pwd       = $_POST['password'] ?? '';
    $pwd2      = $_POST['password2'] ?? '';
    $pwd_actual= $_POST['password_actual'] ?? '';

    // Validaciones (igual que antes)
    if (empty($nombre))
        $errors['nombre'] = 'El nombre no puede estar vacío.';
    elseif (strlen($nombre) < 3)
        $errors['nombre'] = 'Mínimo 3 caracteres.';
    elseif (strlen($nombre) > 80)
        $errors['nombre'] = 'Máximo 80 caracteres.';

    if (empty($email))
        $errors['email'] = 'El email es obligatorio.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors['email'] = 'Formato de email inválido.';
    else {
        $stmt = $conn->pdo->prepare("SELECT id FROM usuarios WHERE email = :email AND id != :id");
        $stmt->execute([':email' => $email, ':id' => $admin_id]);
        if ($stmt->rowCount() > 0)
            $errors['email'] = 'Este email ya está en uso por otra cuenta.';
    }

    // Validaciones contraseña
    if (!empty($pwd) || !empty($pwd2) || !empty($pwd_actual)) {
        if (empty($pwd_actual))
            $errors['password_actual'] = 'Debes confirmar tu contraseña actual.';
        else {
            $stmt = $conn->pdo->prepare("SELECT password FROM usuarios WHERE id = :id");
            $stmt->execute([':id' => $admin_id]);
            $row_pwd = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row_pwd || !password_verify($pwd_actual, $row_pwd['password']))
                $errors['password_actual'] = 'La contraseña actual es incorrecta.';
        }
        if (empty($pwd))
            $errors['password'] = 'La nueva contraseña no puede estar vacía.';
        elseif (strlen($pwd) < 8)
            $errors['password'] = 'Mínimo 8 caracteres.';
        elseif (!preg_match('/[A-Z]/', $pwd))
            $errors['password'] = 'Debe contener al menos una mayúscula.';
        elseif (!preg_match('/[0-9]/', $pwd))
            $errors['password'] = 'Debe contener al menos un número.';
        elseif (!preg_match('/[\W_]/', $pwd))
            $errors['password'] = 'Debe contener al menos un símbolo (!@#...).';
        if ($pwd !== $pwd2)
            $errors['password2'] = 'Las contraseñas no coinciden.';
    }

    if (empty($errors)) {
        if (!empty($pwd) && empty($errors)) {
            $hash = password_hash($pwd, PASSWORD_DEFAULT);
            $stmt = $conn->pdo->prepare("UPDATE usuarios SET nombre = :nombre, email = :email, password = :password WHERE id = :id");
            $stmt->execute([
                ':nombre' => $nombre,
                ':email' => $email,
                ':password' => $hash,
                ':id' => $admin_id
            ]);
        } else {
            $stmt = $conn->pdo->prepare("UPDATE usuarios SET nombre = :nombre, email = :email WHERE id = :id");
            $stmt->execute([':nombre' => $nombre, ':email' => $email, ':id' => $admin_id]);
        }
        $_SESSION['usuario_nombre'] = $nombre;
        $admin_nombre = $nombre;
        $success[] = 'perfil';
        // Recargar datos
        $stmt = $conn->pdo->prepare("SELECT id, nombre, email, avatar FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $admin_id]);
        $admin_data = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}

// ── GUARDAR NOTIFICACIONES ──────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['guardar_notif'])) {
    $tab_open = 'notificaciones';
    $notif_errors = false;

    foreach ($eventos_catalogo as $grupo => $eventos) {
        foreach ($eventos as $ev) {
            $key     = $ev['key'];
            $activo  = isset($_POST["notif_activo_$key"]) ? 'TRUE' : 'FALSE';
            $canal_v = $_POST["notif_canal_$key"] ?? 'panel';
            $umbral_v= isset($ev['umbral']) && $ev['umbral'] ? intval($_POST["notif_umbral_$key"] ?? 0) : null;

            if (!in_array($canal_v, ['panel','email','ambos'])) {
                $errors["canal_$key"] = 'Canal inválido.';
                $notif_errors = true;
                continue;
            }
            if ($ev['umbral'] && $umbral_v !== null) {
                if ($umbral_v < 1 || $umbral_v > 9999) {
                    $errors["umbral_$key"] = 'El umbral debe estar entre 1 y 9999.';
                    $notif_errors = true;
                    continue;
                }
            }

            $stmt = $conn->pdo->prepare("
                INSERT INTO config_notificaciones(admin_id, canal, evento, activo, umbral, actualizado)
                VALUES(:admin_id, :canal, :evento, :activo, :umbral, now())
                ON CONFLICT (admin_id, evento)
                DO UPDATE SET canal = EXCLUDED.canal, activo = EXCLUDED.activo,
                              umbral = EXCLUDED.umbral, actualizado = now()
            ");
            $stmt->execute([
                ':admin_id' => $admin_id,
                ':canal' => $canal_v,
                ':evento' => $key,
                ':activo' => $activo === 'TRUE',
                ':umbral' => $umbral_v
            ]);
        }
    }

    if (!$notif_errors) {
        $success[] = 'notificaciones';
        // Recargar
        $notif_guardadas = [];
        $stmt = $conn->pdo->prepare("SELECT * FROM config_notificaciones WHERE admin_id = :admin_id");
        $stmt->execute([':admin_id' => $admin_id]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $notif_guardadas[$row['evento']] = $row;
        }
    }
}

// Alertas pendientes sidebar
$alertas_pendientes = 0;
try {
    $stmt = $conn->pdo->query("SELECT count(*) AS n FROM alertas_admin WHERE resuelta = FALSE");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $alertas_pendientes = $row['n'] ?? 0;
} catch(PDOException $e) {}
?>
// El resto del HTML permanece igual
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Configuración — D&F Mindspace</title>
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
.s-avatar{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--danger),#ff8c42);color:white;font-weight:700;font-size:.95rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.user-row .name{font-weight:600;font-size:.88rem;color:#222;}
.user-row .online{font-size:.72rem;color:var(--accent);display:flex;align-items:center;gap:5px;}
.online-dot{width:7px;height:7px;background:var(--accent);border-radius:50%;animation:blink 2s infinite;}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}
.nav-label{font-size:.65rem;font-weight:700;color:#bbb;letter-spacing:3px;text-transform:uppercase;padding:16px 20px 4px;}
.nav-item{margin:3px 10px;}
.nav-link{display:flex;align-items:center;gap:11px;padding:11px 16px;color:#555;font-weight:600;font-size:.875rem;border-radius:12px;border-left:3px solid transparent;transition:all .25s;text-decoration:none;}
.nav-link i{width:18px;text-align:center;color:var(--primary);font-size:.9rem;}
.nav-link:hover,.nav-link.active{background:rgba(44,186,236,.09);color:var(--primary);border-left-color:var(--primary);transform:translateX(3px);}
.nav-link .badge-pill{margin-left:auto;background:var(--danger);color:white;border-radius:20px;padding:2px 9px;font-size:.68rem;font-weight:700;}
.sidebar-footer{flex-shrink:0;padding:12px 10px;border-top:1px solid rgba(44,186,236,.1);}
.logout-link{display:flex;align-items:center;gap:10px;padding:10px 16px;color:var(--danger);font-weight:600;font-size:.875rem;border-radius:12px;background:rgba(255,87,87,.07);border-left:3px solid var(--danger);text-decoration:none;transition:background .2s;}
.logout-link:hover{background:rgba(255,87,87,.14);}
.logout-link i{color:var(--danger);width:18px;text-align:center;}

/* ─── MAIN ─── */
.main{margin-left:var(--sidebar-w);padding:28px 28px 50px;min-height:100vh;}
.top{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;}
.page-title{font-family:'Nunito',sans-serif;font-weight:900;font-size:1.75rem;color:var(--text);}
.page-title span{color:var(--primary);}
.page-sub{color:var(--muted);font-size:.8rem;margin-top:2px;}

/* ─── TABS ─── */
.tabs-wrap{display:flex;gap:6px;background:var(--panel);padding:8px;border-radius:16px;border:1.5px solid var(--border);box-shadow:var(--shadow);margin-bottom:24px;flex-wrap:wrap;}
.tab-btn{display:flex;align-items:center;gap:8px;padding:10px 22px;border-radius:11px;border:none;background:transparent;font-family:'Poppins',sans-serif;font-weight:600;font-size:.85rem;color:var(--muted);cursor:pointer;transition:all .22s;text-decoration:none;}
.tab-btn i{font-size:.85rem;}
.tab-btn:hover{background:rgba(44,186,236,.07);color:var(--primary);}
.tab-btn.active{background:linear-gradient(135deg,var(--primary),var(--dark-blue));color:white;box-shadow:0 4px 14px rgba(44,186,236,.3);}

/* ─── TAB CONTENT ─── */
.tab-content-inner{display:none;}
.tab-content-inner.active{display:block;animation:fadeUp .3s ease;}
@keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}

/* ─── CARD ─── */
.cfg-card{background:var(--panel);border-radius:16px;border:1.5px solid var(--border);box-shadow:var(--shadow);overflow:hidden;margin-bottom:20px;}
.cfg-card-head{padding:16px 24px;border-bottom:1.5px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:10px;}
.cfg-card-head h6{font-family:'Nunito',sans-serif;font-weight:800;font-size:1rem;color:var(--text);margin:0;display:flex;align-items:center;gap:9px;}
.cfg-card-head h6 i{color:var(--primary);font-size:.95rem;}
.cfg-card-body{padding:24px;}

/* ─── AVATAR EDITOR ─── */
.avatar-editor{display:flex;align-items:center;gap:20px;margin-bottom:26px;padding:18px 20px;background:rgba(44,186,236,.04);border-radius:14px;border:1.5px solid var(--border);}
.big-avatar{width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,var(--danger),#ff8c42);color:white;font-family:'Nunito',sans-serif;font-weight:800;font-size:1.6rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:3px solid rgba(44,186,236,.2);box-shadow:0 4px 14px rgba(44,186,236,.15);}
.avatar-info strong{display:block;font-weight:700;font-size:.95rem;color:var(--text);}
.avatar-info small{font-size:.75rem;color:var(--muted);}
.avatar-info .role-chip{display:inline-block;background:linear-gradient(90deg,var(--danger),#ff8c42);color:white;font-size:.65rem;font-weight:700;letter-spacing:1px;padding:2px 10px;border-radius:20px;margin-top:5px;}

/* ─── FORM ─── */
.form-group{margin-bottom:18px;}
.form-label{display:block;font-weight:600;font-size:.82rem;color:#444;margin-bottom:6px;}
.form-label .req{color:var(--danger);margin-left:2px;}
.form-label .opt{font-size:.72rem;color:var(--muted);font-weight:400;margin-left:4px;}

.form-control-cfg{
    width:100%;padding:10px 14px;
    border:1.5px solid rgba(44,186,236,.25);
    border-radius:11px;
    font-family:'Poppins',sans-serif;font-size:.86rem;color:var(--text);
    background:#fff;outline:none;
    transition:border-color .2s,box-shadow .2s;
}
.form-control-cfg:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(44,186,236,.12);}
.form-control-cfg.is-error{border-color:var(--danger);box-shadow:0 0 0 3px rgba(255,87,87,.1);}
.form-control-cfg.is-ok{border-color:var(--accent);box-shadow:0 0 0 3px rgba(131,191,70,.1);}

.field-error{display:flex;align-items:center;gap:5px;font-size:.75rem;color:var(--danger);margin-top:5px;font-weight:500;}
.field-error i{font-size:.7rem;}
.field-hint{font-size:.73rem;color:var(--muted);margin-top:4px;}

/* Password strength */
.pwd-strength{display:flex;gap:4px;margin-top:8px;}
.pwd-bar{height:4px;flex:1;border-radius:4px;background:rgba(44,186,236,.1);transition:background .3s;}
.pwd-bar.weak  {background:var(--danger);}
.pwd-bar.medium{background:var(--secondary);}
.pwd-bar.strong{background:var(--accent);}
.pwd-label{font-size:.72rem;margin-top:4px;font-weight:600;}
.pwd-label.weak  {color:var(--danger);}
.pwd-label.medium{color:var(--secondary);}
.pwd-label.strong{color:var(--accent);}

/* Toggle password */
.input-wrap{position:relative;}
.toggle-pwd{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:.85rem;padding:0;transition:color .2s;}
.toggle-pwd:hover{color:var(--primary);}

/* ─── DIVIDER ─── */
.form-divider{display:flex;align-items:center;gap:12px;margin:24px 0 20px;}
.form-divider span{font-size:.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1.5px;white-space:nowrap;}
.form-divider::before,.form-divider::after{content:'';flex:1;height:1px;background:var(--border);}

/* ─── SUBMIT BTN ─── */
.btn-save{
    display:inline-flex;align-items:center;gap:9px;
    padding:11px 28px;border-radius:12px;border:none;
    background:linear-gradient(90deg,var(--primary),var(--dark-blue));
    color:white;font-family:'Poppins',sans-serif;font-weight:600;font-size:.88rem;
    cursor:pointer;transition:all .25s;
    box-shadow:0 5px 16px rgba(44,186,236,.3);
}
.btn-save:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(44,186,236,.4);}
.btn-save:active{transform:none;}
.btn-save:disabled{opacity:.6;cursor:not-allowed;transform:none;}

/* ─── NOTIFICACIONES ─── */
.notif-group-title{
    font-family:'Nunito',sans-serif;font-weight:800;font-size:.9rem;
    color:var(--text);padding:12px 20px;
    background:rgba(44,186,236,.04);
    border-bottom:1.5px solid var(--border);
    display:flex;align-items:center;gap:8px;
    text-transform:uppercase;letter-spacing:.5px;font-size:.78rem;color:var(--primary);
}
.notif-group-title i{font-size:.8rem;}

.notif-row{
    display:grid;
    grid-template-columns: 1fr 140px 120px 110px;
    align-items:center;gap:14px;
    padding:14px 20px;
    border-bottom:1px solid rgba(44,186,236,.06);
    transition:background .15s;
}
.notif-row:last-child{border-bottom:none;}
.notif-row:hover{background:rgba(44,186,236,.03);}

.notif-info strong{display:block;font-weight:600;font-size:.84rem;color:#333;}
.notif-info small{font-size:.72rem;color:var(--muted);}

/* Toggle switch */
.toggle-wrap{display:flex;align-items:center;gap:8px;}
.toggle-switch{position:relative;width:42px;height:24px;flex-shrink:0;}
.toggle-switch input{opacity:0;width:0;height:0;position:absolute;}
.toggle-slider{position:absolute;inset:0;background:rgba(44,186,236,.15);border-radius:24px;cursor:pointer;transition:.3s;border:1.5px solid rgba(44,186,236,.2);}
.toggle-slider::before{content:'';position:absolute;width:16px;height:16px;left:3px;bottom:3px;background:white;border-radius:50%;transition:.3s;box-shadow:0 1px 4px rgba(0,0,0,.15);}
.toggle-switch input:checked + .toggle-slider{background:linear-gradient(90deg,var(--primary),var(--dark-blue));border-color:var(--primary);}
.toggle-switch input:checked + .toggle-slider::before{transform:translateX(18px);}
.toggle-label{font-size:.76rem;font-weight:600;color:var(--muted);}

/* Canal select */
.canal-select{
    padding:6px 10px;border:1.5px solid rgba(44,186,236,.22);
    border-radius:9px;font-family:'Poppins',sans-serif;font-size:.76rem;
    color:#444;background:white;outline:none;cursor:pointer;
    transition:border-color .2s;width:100%;
}
.canal-select:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(44,186,236,.1);}
.canal-select:disabled{background:rgba(44,186,236,.04);color:var(--muted);}

/* Umbral input */
.umbral-input{
    padding:6px 10px;border:1.5px solid rgba(44,186,236,.22);
    border-radius:9px;font-family:var(--mono);font-size:.78rem;
    color:#444;background:white;outline:none;width:80px;
    transition:border-color .2s,opacity .2s;
}
.umbral-input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(44,186,236,.1);}
.umbral-input:disabled{background:rgba(44,186,236,.04);color:var(--muted);opacity:.5;}
.umbral-wrap{display:flex;flex-direction:column;gap:3px;}
.umbral-unit{font-size:.68rem;color:var(--muted);}

/* ─── TOAST ─── */
.toast-fixed{position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px;}
.toast-item{
    background:white;border-radius:12px;
    box-shadow:0 8px 28px rgba(0,0,0,.12);
    padding:14px 20px;font-size:.84rem;font-weight:600;color:#222;
    display:flex;align-items:center;gap:10px;
    min-width:260px;animation:toastIn .35s ease;
}
.toast-item.success{border-left:4px solid var(--accent);}
.toast-item.error  {border-left:4px solid var(--danger);}
@keyframes toastIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}

/* ─── BANNER SUCCESS ─── */
.banner-ok{
    display:flex;align-items:center;gap:12px;
    padding:13px 18px;background:rgba(131,191,70,.1);
    border:1.5px solid rgba(131,191,70,.3);border-radius:12px;
    margin-bottom:20px;font-size:.84rem;font-weight:600;color:#4a8a20;
}
.banner-ok i{font-size:1rem;}

/* ─── MOBILE ─── */
.menu-toggle{display:none;position:fixed;top:15px;left:15px;z-index:200;background:linear-gradient(90deg,var(--primary),var(--secondary));border:none;color:white;width:44px;height:44px;border-radius:50%;font-size:1.1rem;box-shadow:0 4px 14px rgba(44,186,236,.35);cursor:pointer;}
@media(max-width:992px){
    .sidebar{transform:translateX(-100%);}
    .sidebar.open{transform:translateX(0);}
    .main{margin-left:0;padding:18px 14px 50px;}
    .menu-toggle{display:flex;align-items:center;justify-content:center;}
    .notif-row{grid-template-columns:1fr 110px;row-gap:8px;}
    .notif-row .umbral-wrap,.notif-row .canal-select{grid-column:span 2;}
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
            <div class="s-avatar"><?= htmlspecialchars($iniciales) ?></div>
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
                <a href="admin_alertas.php" class="nav-link">
                    <i class="fas fa-bell"></i> Alertas
                    <?php if ($alertas_pendientes > 0): ?>
                    <span class="badge-pill"><?= $alertas_pendientes ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item"><a href="admin_servidor.php" class="nav-link"><i class="fas fa-server"></i> Servidor</a></li>
            <li class="nav-item"><a href="admin_config.php" class="nav-link active"><i class="fas fa-cog"></i> Configuración</a></li>
        </ul>
    </div>
    <div class="sidebar-footer">
        <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
    </div>
</div>

<!-- ══ MAIN ══ -->
<div class="main">

    <div class="top">
        <div>
            <h1 class="page-title">Configuración <span>del sistema</span></h1>
            <p class="page-sub">Último guardado: <?= cfg_get($conn,'ultimo_guardado','—') ?></p>
        </div>
    </div>

    <!-- TABS -->
    <div class="tabs-wrap">
        <button class="tab-btn <?= $tab_open==='perfil'?'active':'' ?>" onclick="openTab('perfil')">
            <i class="fas fa-user-shield"></i> Perfil de administrador
        </button>
        <button class="tab-btn <?= $tab_open==='notificaciones'?'active':'' ?>" onclick="openTab('notificaciones')">
            <i class="fas fa-bell"></i> Notificaciones
        </button>
    </div>

    <!-- ══════════════════════════════════════ -->
    <!--  TAB: PERFIL                           -->
    <!-- ══════════════════════════════════════ -->
    <div class="tab-content-inner <?= $tab_open==='perfil'?'active':'' ?>" id="tab-perfil">

        <?php if (in_array('perfil', $success)): ?>
        <div class="banner-ok"><i class="fas fa-circle-check"></i> Perfil actualizado correctamente.</div>
        <?php endif; ?>

        <?php if (!empty($errors) && $tab_open==='perfil'): ?>
        <div class="banner-ok" style="background:rgba(255,87,87,.08);border-color:rgba(255,87,87,.3);color:var(--danger);">
            <i class="fas fa-triangle-exclamation"></i> Revisa los campos marcados antes de guardar.
        </div>
        <?php endif; ?>

        <form method="POST" id="formPerfil" novalidate>
            <input type="hidden" name="tab_open" value="perfil">

            <div class="cfg-card">
                <div class="cfg-card-head">
                    <h6><i class="fas fa-id-card"></i> Información personal</h6>
                </div>
                <div class="cfg-card-body">

                    <!-- Avatar display -->
                    <div class="avatar-editor">
                        <div class="big-avatar" id="bigAvatar"><?= htmlspecialchars($iniciales) ?></div>
                        <div class="avatar-info">
                            <strong><?= htmlspecialchars($admin_data['nombre'] ?? $admin_nombre) ?></strong>
                            <small><?= htmlspecialchars($admin_data['email'] ?? '') ?></small>
                            <div class="role-chip">⚙ ADMINISTRADOR</div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label" for="nombre">Nombre completo <span class="req">*</span></label>
                                <input type="text" id="nombre" name="nombre"
                                    class="form-control-cfg <?= isset($errors['nombre'])?'is-error':'' ?>"
                                    value="<?= htmlspecialchars($_POST['nombre'] ?? $admin_data['nombre'] ?? '') ?>"
                                    placeholder="Ej. María García López"
                                    maxlength="80" autocomplete="name">
                                <?php if (isset($errors['nombre'])): ?>
                                <div class="field-error"><i class="fas fa-circle-exclamation"></i> <?= $errors['nombre'] ?></div>
                                <?php else: ?>
                                <div class="field-hint">Solo letras y espacios. Mínimo 3 caracteres.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label" for="email">Correo electrónico <span class="req">*</span></label>
                                <input type="email" id="email" name="email"
                                    class="form-control-cfg <?= isset($errors['email'])?'is-error':'' ?>"
                                    value="<?= htmlspecialchars($_POST['email'] ?? $admin_data['email'] ?? '') ?>"
                                    placeholder="admin@dfmindspace.com"
                                    autocomplete="email">
                                <?php if (isset($errors['email'])): ?>
                                <div class="field-error"><i class="fas fa-circle-exclamation"></i> <?= $errors['email'] ?></div>
                                <?php else: ?>
                                <div class="field-hint">Se usa para iniciar sesión y recibir alertas.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Cambio de contraseña -->
            <div class="cfg-card">
                <div class="cfg-card-head">
                    <h6><i class="fas fa-lock"></i> Cambio de contraseña</h6>
                    <span style="font-size:.75rem;color:var(--muted);">Deja en blanco para no cambiarla</span>
                </div>
                <div class="cfg-card-body">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label" for="password_actual">
                                    Contraseña actual <span class="req" id="req-actual" style="display:none">*</span>
                                    <span class="opt" id="opt-actual">(requerida al cambiar)</span>
                                </label>
                                <div class="input-wrap">
                                    <input type="password" id="password_actual" name="password_actual"
                                        class="form-control-cfg <?= isset($errors['password_actual'])?'is-error':'' ?>"
                                        placeholder="Tu contraseña actual"
                                        autocomplete="current-password">
                                    <button type="button" class="toggle-pwd" onclick="togglePwd('password_actual',this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <?php if (isset($errors['password_actual'])): ?>
                                <div class="field-error"><i class="fas fa-circle-exclamation"></i> <?= $errors['password_actual'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label" for="password">Nueva contraseña <span class="opt">(opcional)</span></label>
                                <div class="input-wrap">
                                    <input type="password" id="password" name="password"
                                        class="form-control-cfg <?= isset($errors['password'])?'is-error':'' ?>"
                                        placeholder="Nueva contraseña"
                                        autocomplete="new-password"
                                        oninput="checkStrength(this.value)">
                                    <button type="button" class="toggle-pwd" onclick="togglePwd('password',this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <?php if (isset($errors['password'])): ?>
                                <div class="field-error"><i class="fas fa-circle-exclamation"></i> <?= $errors['password'] ?></div>
                                <?php endif; ?>
                                <!-- Strength -->
                                <div class="pwd-strength" id="pwdBars">
                                    <div class="pwd-bar" id="bar1"></div>
                                    <div class="pwd-bar" id="bar2"></div>
                                    <div class="pwd-bar" id="bar3"></div>
                                    <div class="pwd-bar" id="bar4"></div>
                                </div>
                                <div class="pwd-label" id="pwdLabel"></div>
                                <div class="field-hint">Mín. 8 chars · 1 mayúscula · 1 número · 1 símbolo</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label" for="password2">Confirmar nueva contraseña</label>
                                <div class="input-wrap">
                                    <input type="password" id="password2" name="password2"
                                        class="form-control-cfg <?= isset($errors['password2'])?'is-error':'' ?>"
                                        placeholder="Repite la contraseña"
                                        autocomplete="new-password"
                                        oninput="checkMatch()">
                                    <button type="button" class="toggle-pwd" onclick="togglePwd('password2',this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <?php if (isset($errors['password2'])): ?>
                                <div class="field-error"><i class="fas fa-circle-exclamation"></i> <?= $errors['password2'] ?></div>
                                <?php else: ?>
                                <div class="field-hint" id="matchHint"></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;">
                <button type="submit" name="guardar_perfil" class="btn-save" id="btnPerfil">
                    <i class="fas fa-floppy-disk"></i> Guardar perfil
                </button>
            </div>
        </form>
    </div>

    <!-- ══════════════════════════════════════ -->
    <!--  TAB: NOTIFICACIONES                   -->
    <!-- ══════════════════════════════════════ -->
    <div class="tab-content-inner <?= $tab_open==='notificaciones'?'active':'' ?>" id="tab-notificaciones">

        <?php if (in_array('notificaciones', $success)): ?>
        <div class="banner-ok"><i class="fas fa-circle-check"></i> Preferencias de notificaciones guardadas.</div>
        <?php endif; ?>

        <?php if (!empty($errors) && $tab_open==='notificaciones'): ?>
        <div class="banner-ok" style="background:rgba(255,87,87,.08);border-color:rgba(255,87,87,.3);color:var(--danger);">
            <i class="fas fa-triangle-exclamation"></i> Hay errores en algunos campos. Revísalos.
        </div>
        <?php endif; ?>

        <form method="POST" id="formNotif">
            <input type="hidden" name="tab_open" value="notificaciones">

            <?php
            $group_meta = [
                'seguridad'      => ['fa-shield-halved', 'Seguridad y accesos'],
                'base_de_datos'  => ['fa-database',      'Base de datos'],
                'plataforma'     => ['fa-graduation-cap','Plataforma educativa'],
                'sistema'        => ['fa-server',        'Sistema y recursos'],
            ];
            foreach ($eventos_catalogo as $grupo => $eventos):
                [$gico, $glabel] = $group_meta[$grupo];
            ?>
            <div class="cfg-card" style="margin-bottom:16px;">

                <!-- Header de grupo -->
                <div class="notif-group-title">
                    <i class="fas <?= $gico ?>"></i> <?= $glabel ?>
                </div>

                <!-- Header de columnas -->
                <div class="notif-row" style="background:rgba(44,186,236,.02);padding:9px 20px;border-bottom:1.5px solid var(--border);">
                    <span style="font-size:.7rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;">Evento</span>
                    <span style="font-size:.7rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;">Activar</span>
                    <span style="font-size:.7rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;">Canal</span>
                    <span style="font-size:.7rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;">Umbral</span>
                </div>

                <?php foreach ($eventos as $ev):
                    $key      = $ev['key'];
                    $saved    = $notif_guardadas[$key] ?? null;
                    $activo   = $saved ? (bool)$saved['activo'] : false;
                    $canal    = $saved['canal'] ?? 'panel';
                    $umbral   = $saved['umbral'] ?? '';
                    $hasError = isset($errors["canal_$key"]) || isset($errors["umbral_$key"]);
                ?>
                <div class="notif-row <?= $hasError?'notif-row-error':'' ?>" style="<?= $hasError?'background:rgba(255,87,87,.03);':'' ?>">

                    <!-- Info -->
                    <div class="notif-info">
                        <strong><?= htmlspecialchars($ev['label']) ?></strong>
                        <?php if (!empty($ev['desc'])): ?>
                        <small><?= htmlspecialchars($ev['desc']) ?></small>
                        <?php endif; ?>
                        <?php if ($hasError): ?>
                        <div class="field-error" style="margin-top:3px;">
                            <i class="fas fa-circle-exclamation"></i>
                            <?= $errors["canal_$key"] ?? $errors["umbral_$key"] ?? '' ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Toggle -->
                    <div class="toggle-wrap">
                        <label class="toggle-switch">
                            <input type="checkbox" name="notif_activo_<?= $key ?>"
                                id="toggle_<?= $key ?>"
                                <?= $activo?'checked':'' ?>
                                onchange="toggleNotifRow('<?= $key ?>', this.checked)">
                            <span class="toggle-slider"></span>
                        </label>
                        <span class="toggle-label" id="tlabel_<?= $key ?>"><?= $activo?'ON':'OFF' ?></span>
                    </div>

                    <!-- Canal -->
                    <div>
                        <select name="notif_canal_<?= $key ?>"
                            id="canal_<?= $key ?>"
                            class="canal-select"
                            <?= !$activo?'disabled':'' ?>>
                            <option value="panel"  <?= $canal==='panel' ?'selected':'' ?>>
                                Panel
                            </option>
                            <option value="email"  <?= $canal==='email' ?'selected':'' ?>>
                                Email
                            </option>
                            <option value="ambos"  <?= $canal==='ambos' ?'selected':'' ?>>
                                Panel + Email
                            </option>
                        </select>
                    </div>

                    <!-- Umbral -->
                    <div class="umbral-wrap">
                        <?php if ($ev['umbral']): ?>
                        <input type="number" name="notif_umbral_<?= $key ?>"
                            id="umbral_<?= $key ?>"
                            class="umbral-input <?= isset($errors["umbral_$key"])?'is-error':'' ?>"
                            value="<?= htmlspecialchars($_POST["notif_umbral_$key"] ?? $umbral) ?>"
                            min="1" max="9999" placeholder="—"
                            <?= !$activo?'disabled':'' ?>>
                        <span class="umbral-unit"><?= htmlspecialchars($ev['desc']) ?></span>
                        <?php else: ?>
                        <span style="font-size:.75rem;color:var(--muted);">—</span>
                        <?php endif; ?>
                    </div>

                </div>
                <?php endforeach; ?>

            </div>
            <?php endforeach; ?>

            <!-- Acciones rápidas -->
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:8px;">
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <button type="button" onclick="toggleAll(true)"
                        style="padding:8px 16px;border-radius:10px;border:1.5px solid rgba(131,191,70,.3);background:rgba(131,191,70,.08);color:#4a8a20;font-size:.78rem;font-weight:600;cursor:pointer;">
                        <i class="fas fa-toggle-on"></i> Activar todas
                    </button>
                    <button type="button" onclick="toggleAll(false)"
                        style="padding:8px 16px;border-radius:10px;border:1.5px solid rgba(160,120,232,.3);background:rgba(160,120,232,.06);color:#7c55c8;font-size:.78rem;font-weight:600;cursor:pointer;">
                        <i class="fas fa-toggle-off"></i> Desactivar todas
                    </button>
                </div>
                <button type="submit" name="guardar_notif" class="btn-save">
                    <i class="fas fa-floppy-disk"></i> Guardar notificaciones
                </button>
            </div>

        </form>
    </div>

</div><!-- /main -->

<!-- Toasts -->
<div class="toast-fixed" id="toastArea"></div>

<script>
// ─── Tabs ───────────────────────────────────
function openTab(id) {
    document.querySelectorAll('.tab-content-inner').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + id).classList.add('active');
    document.querySelectorAll('.tab-btn').forEach(el => {
        if (el.getAttribute('onclick').includes("'" + id + "'")) el.classList.add('active');
    });
}

// ─── Sidebar mobile ─────────────────────────
const toggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');
toggle.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    toggle.innerHTML = sidebar.classList.contains('open')
        ? '<i class="fas fa-times"></i>'
        : '<i class="fas fa-bars"></i>';
});
document.addEventListener('click', e => {
    if (window.innerWidth < 992 && !sidebar.contains(e.target) && !toggle.contains(e.target) && sidebar.classList.contains('open')) {
        sidebar.classList.remove('open');
        toggle.innerHTML = '<i class="fas fa-bars"></i>';
    }
});

// ─── Toggle password visibility ─────────────
function togglePwd(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

// ─── Password strength ──────────────────────
function checkStrength(pwd) {
    const bars  = [document.getElementById('bar1'), document.getElementById('bar2'),
                   document.getElementById('bar3'), document.getElementById('bar4')];
    const label = document.getElementById('pwdLabel');
    bars.forEach(b => { b.className = 'pwd-bar'; });

    if (!pwd) { label.textContent = ''; return; }

    let score = 0;
    if (pwd.length >= 8)       score++;
    if (/[A-Z]/.test(pwd))    score++;
    if (/[0-9]/.test(pwd))    score++;
    if (/[\W_]/.test(pwd))    score++;

    const levels = [
        { cls: 'weak',   text: 'Muy débil',  color: 'var(--danger)'    },
        { cls: 'weak',   text: 'Débil',       color: 'var(--danger)'    },
        { cls: 'medium', text: 'Regular',     color: 'var(--secondary)' },
        { cls: 'medium', text: 'Buena',       color: 'var(--secondary)' },
        { cls: 'strong', text: 'Fuerte ✓',   color: 'var(--accent)'    },
    ];
    const lvl = levels[score] || levels[0];
    for (let i = 0; i < score; i++) bars[i].classList.add(lvl.cls);
    label.textContent  = lvl.text;
    label.className    = 'pwd-label ' + lvl.cls;

    // Habilitar campo contraseña actual como requerido
    document.getElementById('req-actual').style.display = 'inline';
    document.getElementById('opt-actual').style.display = 'none';
}

// ─── Password match ─────────────────────────
function checkMatch() {
    const p1   = document.getElementById('password').value;
    const p2   = document.getElementById('password2').value;
    const hint = document.getElementById('matchHint');
    if (!hint) return;
    if (!p2)   { hint.textContent = ''; hint.style.color = ''; return; }
    if (p1 === p2) {
        hint.textContent = '✓ Las contraseñas coinciden';
        hint.style.color = 'var(--accent)';
        document.getElementById('password2').classList.remove('is-error');
        document.getElementById('password2').classList.add('is-ok');
    } else {
        hint.textContent = '✗ No coinciden aún';
        hint.style.color = 'var(--danger)';
        document.getElementById('password2').classList.add('is-error');
        document.getElementById('password2').classList.remove('is-ok');
    }
}

// ─── Avatar initial update ──────────────────
document.getElementById('nombre')?.addEventListener('input', function() {
    const partes = this.value.trim().split(' ').filter(Boolean);
    let ini = partes.length >= 2
        ? (partes[0][0] + partes[1][0]).toUpperCase()
        : (partes[0] || '?').substring(0,2).toUpperCase();
    document.getElementById('bigAvatar').textContent = ini;
});

// ─── Notificaciones: toggle row ─────────────
function toggleNotifRow(key, active) {
    const canal   = document.getElementById('canal_'  + key);
    const umbral  = document.getElementById('umbral_' + key);
    const label   = document.getElementById('tlabel_' + key);
    if (canal)  canal.disabled  = !active;
    if (umbral) umbral.disabled = !active;
    if (label)  label.textContent = active ? 'ON' : 'OFF';
}

// ─── Toggle all notifications ───────────────
function toggleAll(state) {
    document.querySelectorAll('[id^="toggle_"]').forEach(cb => {
        const key = cb.id.replace('toggle_', '');
        cb.checked = state;
        toggleNotifRow(key, state);
    });
}

// ─── Client-side validation before submit ───
document.getElementById('formPerfil')?.addEventListener('submit', function(e) {
    let ok = true;

    const nombre = document.getElementById('nombre');
    if (!nombre.value.trim() || nombre.value.trim().length < 3) {
        markError(nombre, 'Mínimo 3 caracteres.');
        ok = false;
    } else { markOk(nombre); }

    const email = document.getElementById('email');
    if (!email.value.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
        markError(email, 'Email inválido.');
        ok = false;
    } else { markOk(email); }

    const pwd  = document.getElementById('password').value;
    const pwd2 = document.getElementById('password2').value;
    if (pwd) {
        if (pwd.length < 8 || !/[A-Z]/.test(pwd) || !/[0-9]/.test(pwd) || !/[\W_]/.test(pwd)) {
            markError(document.getElementById('password'), 'No cumple los requisitos.');
            ok = false;
        }
        if (pwd !== pwd2) {
            markError(document.getElementById('password2'), 'No coinciden.');
            ok = false;
        }
    }

    if (!ok) {
        e.preventDefault();
        showToast('Corrige los errores antes de guardar.', 'error');
    } else {
        document.getElementById('btnPerfil').disabled = true;
        document.getElementById('btnPerfil').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
    }
});

function markError(el, msg) {
    el.classList.add('is-error');
    el.classList.remove('is-ok');
    let err = el.parentElement.querySelector('.field-error-js');
    if (!err) {
        err = document.createElement('div');
        err.className = 'field-error field-error-js';
        err.innerHTML = `<i class="fas fa-circle-exclamation"></i> ${msg}`;
        el.parentElement.appendChild(err);
    } else {
        err.innerHTML = `<i class="fas fa-circle-exclamation"></i> ${msg}`;
    }
}
function markOk(el) {
    el.classList.remove('is-error');
    el.classList.add('is-ok');
    const err = el.parentElement.querySelector('.field-error-js');
    if (err) err.remove();
}

// ─── Toast ──────────────────────────────────
function showToast(msg, type = 'success') {
    const area = document.getElementById('toastArea');
    const t = document.createElement('div');
    t.className = `toast-item ${type}`;
    const icon = type === 'success'
        ? '<i class="fas fa-circle-check" style="color:var(--accent);font-size:1rem;"></i>'
        : '<i class="fas fa-triangle-exclamation" style="color:var(--danger);font-size:1rem;"></i>';
    t.innerHTML = `${icon} ${msg}`;
    area.appendChild(t);
    setTimeout(() => t.style.opacity = '0', 2800);
    setTimeout(() => t.remove(), 3200);
}

// Auto-toast on success
<?php if (in_array('perfil', $success)): ?>
window.addEventListener('load', () => showToast('Perfil actualizado correctamente.'));
<?php endif; ?>
<?php if (in_array('notificaciones', $success)): ?>
window.addEventListener('load', () => showToast('Notificaciones guardadas.'));
<?php endif; ?>
</script>
</body>
</html>