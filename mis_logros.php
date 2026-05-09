<?php
include 'php/config.php';
session_start();

// Verificar si el usuario es alumno
if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'alumno') {
    header("Location: index.php");
    exit();
}

$alumno_id = (int)$_SESSION['user_id'];
$nombre_alumno = $_SESSION['nombre'];

try {
    // Obtener información del alumno
    $stmt = $conn->pdo->prepare("SELECT * FROM usuarios WHERE id = :alumno_id");
    $stmt->execute([':alumno_id' => $alumno_id]);
    $alumno = $stmt->fetch(PDO::FETCH_ASSOC);

    // 1. Actividades completadas (con calificación)
    $stmt = $conn->pdo->prepare("
        SELECT COUNT(DISTINCT e.id_actividad) as total 
        FROM entregas e 
        WHERE e.id_alumno = :alumno_id 
        AND e.estado = 'calificado'
    ");
    $stmt->execute([':alumno_id' => $alumno_id]);
    $act_completadas = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // 2. Cursos inscritos activos
    $stmt = $conn->pdo->prepare("
        SELECT COUNT(*) as total 
        FROM inscripciones 
        WHERE id_alumno = :alumno_id 
        AND estado = 'activo'
    ");
    $stmt->execute([':alumno_id' => $alumno_id]);
    $cursos_inscritos = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // 3. Calificaciones altas (8 o más)
    $stmt = $conn->pdo->prepare("
        SELECT COUNT(*) as total 
        FROM entregas e 
        JOIN evaluaciones ev ON e.id = ev.id_entrega 
        WHERE e.id_alumno = :alumno_id 
        AND ev.calificacion >= 8
    ");
    $stmt->execute([':alumno_id' => $alumno_id]);
    $calificaciones_altas = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // 4. Progreso promedio
    $stmt = $conn->pdo->prepare("
        SELECT COALESCE(AVG(progreso), 0) as promedio 
        FROM inscripciones 
        WHERE id_alumno = :alumno_id 
        AND estado = 'activo'
    ");
    $stmt->execute([':alumno_id' => $alumno_id]);
    $progreso_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $progreso_promedio = round($progreso_data['promedio'] ?? 0);

    // 5. Calificación Perfecta (10/10)
    $stmt = $conn->pdo->prepare("
        SELECT COUNT(*) as total 
        FROM entregas e 
        JOIN evaluaciones ev ON e.id = ev.id_entrega 
        WHERE e.id_alumno = :alumno_id 
        AND ev.calificacion = 10
    ");
    $stmt->execute([':alumno_id' => $alumno_id]);
    $calificacion_perfecta = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // 6. Fecha de la primera actividad completada
    $stmt = $conn->pdo->prepare("
        SELECT MIN(e.fecha_entrega) as primera_fecha 
        FROM entregas e 
        WHERE e.id_alumno = :alumno_id 
        AND e.estado = 'calificado'
    ");
    $stmt->execute([':alumno_id' => $alumno_id]);
    $primer_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $fecha_primer_actividad = $primer_data['primera_fecha'] ?? date('Y-m-d');

    // 7. Curso con mayor progreso
    $stmt = $conn->pdo->prepare("
        SELECT MAX(progreso) as max_progreso 
        FROM inscripciones 
        WHERE id_alumno = :alumno_id 
        AND estado = 'activo'
    ");
    $stmt->execute([':alumno_id' => $alumno_id]);
    $curso_prog_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $max_progreso = $curso_prog_data['max_progreso'] ?? 0;

    // 8. Actividades recientes (últimos 7 días)
    $stmt = $conn->pdo->prepare("
        SELECT COUNT(*) as total 
        FROM entregas e 
        WHERE e.id_alumno = :alumno_id 
        AND e.fecha_entrega >= CURRENT_DATE - INTERVAL '7 days'
    ");
    $stmt->execute([':alumno_id' => $alumno_id]);
    $actividades_recientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

} catch(PDOException $e) {
    // En caso de error, establecer valores por defecto
    $act_completadas = 0;
    $cursos_inscritos = 0;
    $calificaciones_altas = 0;
    $progreso_promedio = 0;
    $calificacion_perfecta = 0;
    $fecha_primer_actividad = date('Y-m-d');
    $max_progreso = 0;
    $actividades_recientes = 0;
}

// GENERAR LOGROS BASADOS EN DATOS REALES (el resto del código de logros es correcto)
$logros_obtenidos = [];

// Logro 1: Primeros Pasos
if ($act_completadas >= 1) {
    $logros_obtenidos[] = [
        'nombre' => 'Primeros Pasos 🚀',
        'descripcion' => 'Completaste tu primera actividad con éxito',
        'icono' => 'fa-seedling',
        'color' => '#2cbaec',
        'puntos' => 50,
        'fecha_obtencion' => $fecha_primer_actividad,
        'tipo' => 'bronce'
    ];
}
// ... Continuar con el resto de logros (igual que en tu código, no cambian)

// Avatares disponibles
$avatares = [
    'panda' => ['emoji' => '🐼', 'color' => '#3A506B', 'nivel' => 1],
    'dragon' => ['emoji' => '🐉', 'color' => '#FF6B6B', 'nivel' => 1],
    'leon' => ['emoji' => '🦁', 'color' => '#FFD93D', 'nivel' => 2],
    'dino' => ['emoji' => '🦖', 'color' => '#6BCF7F', 'nivel' => 1],
    'robot' => ['emoji' => '🤖', 'color' => '#4D96FF', 'nivel' => 3],
    'astronauta' => ['emoji' => '👨‍🚀', 'color' => '#845EC2', 'nivel' => 4],
    'superheroe' => ['emoji' => '🦸‍♂️', 'color' => '#FF6B8B', 'nivel' => 5],
    'mago' => ['emoji' => '🧙‍♂️', 'color' => '#00C2A8', 'nivel' => 6]
];

// Obtener avatar del alumno (sin SHOW COLUMNS)
try {
    $stmt = $conn->pdo->prepare("SELECT COALESCE(avatar, 'panda') as avatar FROM usuarios WHERE id = :alumno_id");
    $stmt->execute([':alumno_id' => $alumno_id]);
    $avatar_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $avatar_key = $avatar_data['avatar'] ?? 'panda';
} catch(PDOException $e) {
    $avatar_key = 'panda';
}

if(!isset($avatares[$avatar_key])) {
    $avatar_key = 'panda';
}

$avatar_emoji = $avatares[$avatar_key]['emoji'];
$avatar_color = $avatares[$avatar_key]['color'];
$avatar_nombre = ucfirst($avatar_key);

// Calcular puntos totales (el resto del código sigue igual)
$puntos_alumno = array_sum(array_column($logros_obtenidos, 'puntos'));

// Contar logros por tipo
$bronze_count = $silver_count = $gold_count = 0;
foreach ($logros_obtenidos as $logro) {
    switch($logro['tipo']) {
        case 'bronce': $bronze_count++; break;
        case 'plata': $silver_count++; break;
        case 'oro': $gold_count++; break;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Logros - D&F Mindspace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Fredoka+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css">
    <style>
        :root {
            --primary: #2cbaec;
            --secondary: #f0ae2a;
            --accent: #83bf46;
            --danger: #ff6b8b;
            --purple: #9c88ff;
            --pink: #fd79a8;
            --bronce: #CD7F32;
            --plata: #C0C0C0;
            --oro: #FFD700;
            --light-bg: #f7fdfe;
            --card-shadow: 0 10px 30px rgba(44, 186, 236, 0.15);
            --sidebar-width: 280px;
        }
        
        body {
            background: linear-gradient(135deg, #f0f9fd 0%, #e6f7fc 100%);
            font-family: 'Nunito', sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
            padding: 0;
            margin: 0;
        }
        
        /* Sidebar estilo infantil */
        .sidebar-kid {
            background: linear-gradient(180deg, #FFFFFF 0%, #f5fcfe 100%);
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 100;
            box-shadow: 5px 0 25px rgba(44, 186, 236, 0.1);
            border-right: 5px solid var(--primary);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .sidebar-content {
            flex: 1;
            overflow-y: auto;
            padding: 25px 0 20px 0;
        }
        
        .sidebar-footer {
            flex-shrink: 0;
            padding: 20px;
            background: linear-gradient(180deg, transparent, rgba(44, 186, 236, 0.05));
            border-top: 1px solid rgba(44, 186,236, 0.1);
        }
        
        .sidebar-brand {
            text-align: center;
            padding: 0 20px 25px;
            border-bottom: 2px solid rgba(44, 186, 236, 0.1);
            margin-bottom: 15px;
        }
        
        .logo-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .logo-main {
            font-family: 'Fredoka One', cursive;
            font-size: 2.2rem;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1;
            letter-spacing: -1px;
            text-shadow: 0 2px 4px rgba(44, 186, 236, 0.2);
        }
        
        .logo-sub {
            font-family: 'Poppins', sans-serif;
            font-size: 1.2rem;
            color: var(--primary);
            font-weight: 600;
            letter-spacing: 8px;
            text-transform: uppercase;
            margin-left: 10px;
            position: relative;
        }
        
        .tagline {
            color: #666;
            font-size: 0.9rem;
            margin-top: 10px;
            font-weight: 500;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        
        /* Avatar del niño */
        .kid-avatar {
            position: relative;
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: <?php echo $avatar_color; ?>;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            border: 4px solid white;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .kid-avatar:hover {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 12px 30px rgba(0,0,0,0.2);
        }
        
        .avatar-emoji {
            font-size: 3.5rem;
            filter: drop-shadow(2px 2px 4px rgba(0,0,0,0.2));
        }
        
        .kid-name {
            font-family: 'Fredoka One', cursive;
            font-size: 1.4rem;
            color: var(--primary);
            margin-bottom: 5px;
        }
        
        .kid-level {
            font-size: 0.9rem;
            color: white;
            background: linear-gradient(90deg, var(--secondary), #f5c15d);
            padding: 3px 12px;
            border-radius: 15px;
            display: inline-block;
        }
        
        /* Navegación del sidebar */
        .nav-item {
            margin: 8px 15px;
        }
        
        .nav-link {
            color: #555;
            border-radius: 15px;
            padding: 14px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 4px solid transparent;
            font-family: 'Nunito', sans-serif;
            font-size: 1.05rem;
        }
        
        .nav-link:hover, .nav-link.active {
            background: linear-gradient(90deg, rgba(44, 186, 236, 0.1) 0%, rgba(44, 186, 236, 0.05) 100%);
            color: var(--primary);
            border-left-color: var(--primary);
            transform: translateX(5px);
        }
        
        .nav-link i {
            width: 24px;
            text-align: center;
            font-size: 1.2rem;
        }
        
        .nav-link span {
            flex: 1;
        }
        
        /* Botón de cerrar sesión */
        .logout-link {
            background: linear-gradient(90deg, rgba(255, 87, 87, 0.1) 0%, rgba(255, 87, 87, 0.05) 100%);
            color: #ff5757 !important;
            border-left: 4px solid #ff5757 !important;
            margin-top: 10px;
        }
        
        .logout-link:hover {
            background: linear-gradient(90deg, rgba(255, 87, 87, 0.2) 0%, rgba(255, 87, 87, 0.1) 100%) !important;
            color: #ff3030 !important;
            border-left-color: #ff3030 !important;
        }
        
        /* Contenido principal */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 30px;
            width: calc(100% - var(--sidebar-width));
            min-height: 100vh;
        }
        
        /* Botón para móvil */
        .menu-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 101;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            font-size: 1.5rem;
            box-shadow: 0 5px 15px rgba(44, 186, 236, 0.3);
            transition: all 0.3s ease;
        }
        
        /* Encabezado de logros */
        .achievements-header {
            background: linear-gradient(135deg, #ffffff, #f8fdff);
            border-radius: 25px;
            padding: 30px;
            margin-bottom: 40px;
            box-shadow: var(--card-shadow);
            border: 3px solid rgba(44, 186, 236, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .achievements-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(44, 186, 236, 0.05));
        }
        
        .header-title {
            font-family: 'Fredoka One', cursive;
            font-size: 2.5rem;
            background: linear-gradient(90deg, var(--secondary), var(--danger));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }
        
        /* Estadísticas de logros */
        .achievements-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .achievement-stat-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            text-align: center;
            border: 3px solid transparent;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .achievement-stat-card:hover {
            transform: translateY(-10px);
            border-color: var(--primary);
        }
        
        .achievement-stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 15px;
            color: white;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }
        
        .achievement-stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            margin: 10px 0;
            font-family: 'Fredoka One', cursive;
        }
        
        .bronze-stat { color: var(--bronce); }
        .silver-stat { color: var(--plata); }
        .gold-stat { color: var(--oro); }
        .total-stat { color: var(--accent); }
        
        .achievement-stat-label {
            color: #666;
            font-size: 1rem;
            font-weight: 600;
        }
        
        /* Grid de logros */
        .achievements-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 50px;
        }
        
        .achievement-card {
            background: white;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: all 0.4s ease;
            border: 3px solid transparent;
            position: relative;
        }
        
        .achievement-card:hover {
            transform: translateY(-15px) scale(1.02);
            box-shadow: 0 25px 50px rgba(44, 186, 236, 0.25);
        }
        
        .achievement-header {
            padding: 25px 25px 15px;
            position: relative;
            overflow: hidden;
            text-align: center;
        }
        
        .achievement-badge {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 15px;
            color: white;
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            position: relative;
            z-index: 1;
        }
        
        .achievement-badge::after {
            content: '';
            position: absolute;
            top: -5px;
            left: -5px;
            right: -5px;
            bottom: -5px;
            border-radius: 50%;
            z-index: -1;
            opacity: 0.3;
        }
        
        .bronze-badge {
            background: linear-gradient(135deg, var(--bronce), #CD7F32);
        }
        .bronze-badge::after { background: linear-gradient(135deg, var(--bronce), #CD7F32); }
        
        .silver-badge {
            background: linear-gradient(135deg, var(--plata), #C0C0C0);
        }
        .silver-badge::after { background: linear-gradient(135deg, var(--plata), #C0C0C0); }
        
        .gold-badge {
            background: linear-gradient(135deg, var(--oro), #FFD700);
        }
        .gold-badge::after { background: linear-gradient(135deg, var(--oro), #FFD700); }
        
        .achievement-title {
            font-family: 'Fredoka One', cursive;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 5px;
            position: relative;
            z-index: 1;
        }
        
        .achievement-points {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255,255,255,0.3);
            backdrop-filter: blur(10px);
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-weight: 700;
            font-size: 0.9rem;
            border: 1px solid rgba(255,255,255,0.3);
            z-index: 1;
        }
        
        .achievement-body {
            padding: 20px 25px;
        }
        
        .achievement-description {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .achievement-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 2px solid rgba(44, 186, 236, 0.1);
        }
        
        .achievement-date {
            font-size: 0.9rem;
            color: #888;
        }
        
        .achievement-status {
            padding: 6px 15px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .status-obtained {
            background: rgba(131, 191, 70, 0.1);
            color: var(--accent);
        }
        
        /* Logros por desbloquear */
        .locked-achievement {
            opacity: 0.7;
        }
        
        .locked-achievement .achievement-header {
            background: linear-gradient(135deg, #888, #aaa);
        }
        
        .locked-status {
            background: rgba(136, 136, 136, 0.1);
            color: #888;
        }
        
        /* Barra de progreso de nivel */
        .level-progress-container {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
            border: 3px solid rgba(44, 186, 236, 0.1);
        }
        
        .level-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .level-title {
            font-family: 'Fredoka One', cursive;
            font-size: 1.5rem;
            color: var(--primary);
        }
        
        .level-info {
            display: flex;
            gap: 20px;
        }
        
        .level-current {
            text-align: center;
        }
        
        .level-number {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(90deg, var(--secondary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-family: 'Fredoka One', cursive;
        }
        
        .level-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .level-progress-bar {
            height: 20px;
            background: rgba(44, 186, 236, 0.1);
            border-radius: 15px;
            overflow: hidden;
            position: relative;
        }
        
        .level-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--secondary), var(--accent));
            border-radius: 15px;
            transition: width 1s ease-in-out;
            position: relative;
        }
        
        .level-progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, 
                transparent, 
                rgba(255,255,255,0.4), 
                transparent);
            animation: shimmer 2s infinite;
        }
        
        .level-milestones {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }
        
        .milestone {
            font-size: 0.85rem;
            color: #888;
            position: relative;
        }
        
        .milestone.active {
            color: var(--primary);
            font-weight: 600;
        }
        
        .milestone::before {
            content: '';
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #ddd;
        }
        
        .milestone.active::before {
            background: var(--primary);
            box-shadow: 0 0 10px var(--primary);
        }
        
        /* Elementos decorativos */
        .floating-element {
            position: fixed;
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.1), rgba(240, 174, 42, 0.1));
            border-radius: 50%;
            animation: floatElement 15s ease-in-out infinite;
            z-index: -1;
            pointer-events: none;
        }
        
        @keyframes floatElement {
            0%, 100% { 
                transform: translate(0, 0) rotate(0deg) scale(1); 
                border-radius: 50%;
            }
            25% { 
                transform: translate(30px, -30px) rotate(90deg) scale(1.1);
                border-radius: 60% 40% 50% 50%;
            }
            50% { 
                transform: translate(0, -60px) rotate(180deg) scale(1);
                border-radius: 40% 60% 40% 60%;
            }
            75% { 
                transform: translate(-30px, -30px) rotate(270deg) scale(0.9);
                border-radius: 50% 50% 60% 40%;
            }
        }
        
        /* Estrella brillante */
        .shining-star {
            position: absolute;
            font-size: 2rem;
            color: var(--secondary);
            animation: twinkle 3s infinite;
            opacity: 0.7;
        }
        
        @keyframes twinkle {
            0%, 100% { opacity: 0.3; transform: scale(1) rotate(0deg); }
            50% { opacity: 1; transform: scale(1.3) rotate(180deg); }
        }
        
        /* Botón principal */
        .btn-main-action {
            background: linear-gradient(90deg, var(--secondary), #f5c15d);
            border: none;
            border-radius: 20px;
            padding: 15px 30px;
            color: white;
            font-weight: 800;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 8px 20px rgba(240, 174, 42, 0.3);
            text-decoration: none;
        }
        
        .btn-main-action:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 30px rgba(240, 174, 42, 0.4);
            color: white;
        }
        
        /* Animaciones */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .achievements-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            }
        }
        
        @media (max-width: 992px) {
            .sidebar-kid {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar-kid.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 20px;
            }
            
            .menu-toggle {
                display: block;
            }
            
            .header-title {
                font-size: 2rem;
            }
            
            .achievements-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .achievements-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .level-info {
                flex-direction: column;
                gap: 10px;
            }
        }
        
        @media (max-width: 576px) {
            .achievements-stats {
                grid-template-columns: 1fr;
            }
            
            .header-title {
                font-size: 1.8rem;
            }
        }
        
        /* Efecto especial para logros desbloqueados */
        .unlocked-effect {
            position: relative;
            overflow: hidden;
        }
        
        .unlocked-effect::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, 
                transparent 30%, 
                rgba(255, 255, 255, 0.3) 50%, 
                transparent 70%);
            transform: translateX(-100%);
        }
        
        .achievement-card:hover.unlocked-effect::before {
            animation: shine 1.5s ease;
        }
        
        @keyframes shine {
            to { transform: translateX(100%); }
        }
        
        /* Indicador de progreso personal */
        .personal-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .personal-stat {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .personal-stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 5px;
        }
        
        .personal-stat-label {
            color: #666;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <!-- Botón para móvil -->
    <button class="menu-toggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Elementos decorativos flotantes -->
    <div class="floating-element" style="top: 10%; right: 5%; width: 100px; height: 100px; background: linear-gradient(135deg, rgba(44, 186, 236, 0.2), rgba(44, 186, 236, 0.1)); animation-delay: 0s;"></div>
    <div class="floating-element" style="bottom: 15%; left: 5%; width: 80px; height: 80px; background: linear-gradient(135deg, rgba(240, 174, 42, 0.2), rgba(240, 174, 42, 0.1)); animation-delay: 1s;"></div>
    <div class="floating-element" style="top: 30%; left: 10%; width: 60px; height: 60px; background: linear-gradient(135deg, rgba(131, 191, 70, 0.2), rgba(131, 191, 70, 0.1)); animation-delay: 2s;"></div>
    
    <!-- Sidebar infantil -->
    <div class="sidebar-kid">
        <!-- Contenido con scroll -->
        <div class="sidebar-content">
            <div class="sidebar-brand">
                <div class="logo-container">
                    <div class="logo-main">D&F</div>
                    <div class="logo-sub">mindspace</div>
                    <div class="tagline">
                        <span>EXPLORA</span> • <span>CREA</span> • <span>APRENDE</span>
                    </div>
                </div>
                
                <!-- Avatar del niño -->
                <div class="kid-avatar" id="kidAvatar">
                    <span class="avatar-emoji"><?php echo $avatar_emoji; ?></span>
                </div>
                
                <h4 class="kid-name"><?php echo $nombre_alumno; ?></h4>
                <span class="kid-level">
                    <i class="fas fa-star me-1"></i>Nivel <?php echo $avatares[$avatar_key]['nivel']; ?>
                </span>
                
                <!-- Puntos REALES -->
                <div class="points-container" style="text-align: center; padding: 15px; background: linear-gradient(135deg, rgba(255, 107, 139, 0.1), rgba(255, 107, 139, 0.05)); border-radius: 15px; margin: 15px;">
                    <div class="points-value" style="font-size: 2rem; font-family: 'Fredoka One', cursive; color: var(--danger); margin: 5px 0;"><?php echo $puntos_alumno; ?></div>
                    <div class="points-label" style="color: #666; font-size: 0.9rem;">Puntos de Logro</div>
                </div>
            </div>
            
            <!-- Navegación -->
            <ul class="nav flex-column mt-3">
                <li class="nav-item">
                    <a href="dashboard_alumno.php" class="nav-link">
                        <i class="fas fa-home"></i>
                        <span>Mi Mundo</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="mis_cursos.php" class="nav-link">
                        <i class="fas fa-compass"></i>
                        <span>Mis Aventuras</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="catalogo.php" class="nav-link">
                        <i class="fas fa-search"></i>
                        <span>Nuevas Aventuras</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="mis_actividades.php" class="nav-link">
                        <i class="fas fa-tasks"></i>
                        <span>Mis Misiones</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="mis_logros.php" class="nav-link active">
                        <i class="fas fa-trophy"></i>
                        <span>Mis Logros</span>
                        <?php if(count($logros_obtenidos) > 0): ?>
                            <span style="background: var(--secondary); color: white; border-radius: 10px; padding: 2px 8px; font-size: 0.8rem;">
                                <?php echo count($logros_obtenidos); ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="mensajes.php" class="nav-link">
                        <i class="fas fa-envelope"></i>
                        <span>Mensajes</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="avatar_shop.php" class="nav-link">
                        <i class="fas fa-user-astronaut"></i>
                        <span>Tienda de Avatares</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="amigos.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span>Amigos Exploradores</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Footer fijo en la parte inferior -->
        <div class="sidebar-footer">
            <a href="logout.php" class="nav-link logout-link">
                <i class="fas fa-sign-out-alt"></i>
                <span>Salir de la Aventura</span>
            </a>
        </div>
    </div>
    
    <!-- Contenido principal -->
    <div class="main-content">
        
        <!-- Encabezado de logros -->
        <div class="achievements-header fade-in-up">
            <!-- Estrellas decorativas -->
            <i class="fas fa-star shining-star" style="top: 30px; right: 100px; animation-delay: 0s;"></i>
            <i class="fas fa-star shining-star" style="bottom: 40px; left: 80px; animation-delay: 1s;"></i>
            <i class="fas fa-star shining-star" style="top: 60px; left: 150px; animation-delay: 2s;"></i>
            
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="header-title">¡Mis Logros y Reconocimientos! 🏆</h1>
                    <p class="fs-5 text-muted">
                        Cada trofeo cuenta una historia de tu crecimiento y esfuerzo en esta aventura de aprendizaje.
                    </p>
                    
                    <!-- Estadísticas personales REALES -->
                    <div class="personal-stats mt-4">
                        <div class="personal-stat">
                            <div class="personal-stat-value"><?php echo $act_completadas; ?></div>
                            <div class="personal-stat-label">Actividades Completadas</div>
                        </div>
                        <div class="personal-stat">
                            <div class="personal-stat-value"><?php echo $cursos_inscritos; ?></div>
                            <div class="personal-stat-label">Cursos Activos</div>
                        </div>
                        <div class="personal-stat">
                            <div class="personal-stat-value"><?php echo $progreso_promedio; ?>%</div>
                            <div class="personal-stat-label">Progreso Promedio</div>
                        </div>
                        <div class="personal-stat">
                            <div class="personal-stat-value"><?php echo $calificaciones_altas; ?></div>
                            <div class="personal-stat-label">Calificaciones Altas</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 text-center">
                    <div class="kid-avatar" style="width: 120px; height: 120px; margin: 0 auto;">
                        <span class="avatar-emoji" style="font-size: 4.5rem;"><?php echo $avatar_emoji; ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Barra de progreso de nivel -->
        <div class="level-progress-container fade-in-up" style="animation-delay: 0.1s">
            <div class="level-header">
                <h3 class="level-title">Tu Progreso de Aprendizaje</h3>
                <div class="level-info">
                    <div class="level-current">
                        <div class="level-number"><?php echo $avatares[$avatar_key]['nivel']; ?></div>
                        <div class="level-label">Nivel Actual</div>
                    </div>
                    <div class="level-next">
                        <div class="level-number"><?php echo $avatares[$avatar_key]['nivel'] + 1; ?></div>
                        <div class="level-label">Próximo Nivel</div>
                    </div>
                </div>
            </div>
            
            <div class="level-progress-bar">
                <div class="level-progress-fill" style="width: <?php echo min(100, ($act_completadas * 20) + ($cursos_inscritos * 15) + ($progreso_promedio * 0.3)); ?>%"></div>
            </div>
            
            <div class="level-milestones">
                <div class="milestone <?php echo $act_completadas >= 1 ? 'active' : ''; ?>">Inicio</div>
                <div class="milestone <?php echo $act_completadas >= 2 ? 'active' : ''; ?>">Avanzado</div>
                <div class="milestone <?php echo $act_completadas >= 3 ? 'active' : ''; ?>">Experto</div>
                <div class="milestone <?php echo $act_completadas >= 4 ? 'active' : ''; ?>">Maestro</div>
                <div class="milestone <?php echo $act_completadas >= 5 ? 'active' : ''; ?>">Leyenda</div>
            </div>
        </div>
        
        <!-- Estadísticas de logros REALES -->
        <div class="achievements-stats fade-in-up" style="animation-delay: 0.2s">
            <div class="achievement-stat-card">
                <div class="achievement-stat-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="achievement-stat-value total-stat">
                    <?php echo count($logros_obtenidos); ?>
                </div>
                <div class="achievement-stat-label">Logros Totales</div>
            </div>
            
            <div class="achievement-stat-card">
                <div class="achievement-stat-icon" style="background: linear-gradient(135deg, var(--bronce), #CD7F32);">
                    <i class="fas fa-medal"></i>
                </div>
                <div class="achievement-stat-value bronze-stat"><?php echo $bronze_count; ?></div>
                <div class="achievement-stat-label">Logros Bronce</div>
            </div>
            
            <div class="achievement-stat-card">
                <div class="achievement-stat-icon" style="background: linear-gradient(135deg, var(--plata), #C0C0C0);">
                    <i class="fas fa-medal"></i>
                </div>
                <div class="achievement-stat-value silver-stat"><?php echo $silver_count; ?></div>
                <div class="achievement-stat-label">Logros Plata</div>
            </div>
            
            <div class="achievement-stat-card">
                <div class="achievement-stat-icon" style="background: linear-gradient(135deg, var(--oro), #FFD700);">
                    <i class="fas fa-medal"></i>
                </div>
                <div class="achievement-stat-value gold-stat"><?php echo $gold_count; ?></div>
                <div class="achievement-stat-label">Logros Oro</div>
            </div>
        </div>
        
        <!-- Encabezado de grid de logros -->
        <div class="courses-header fade-in-up" style="animation-delay: 0.3s">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-2" style="font-family: 'Fredoka One', cursive; color: var(--primary);">
                        <i class="fas fa-medal me-2"></i>Mi Colección de Trofeos
                    </h2>
                    <p class="text-muted mb-0">
                        <?php if(count($logros_obtenidos) > 0): ?>
                            ¡Tienes <?php echo count($logros_obtenidos); ?> logros desbloqueados!
                        <?php else: ?>
                            ¡Comienza a completar actividades para desbloquear logros!
                        <?php endif; ?>
                    </p>
                </div>
                <a href="mis_cursos.php" class="btn-main-action">
                    <i class="fas fa-play"></i> Continuar Aprendiendo
                </a>
            </div>
        </div>
        
        <!-- Grid de logros REALES -->
        <div class="achievements-grid">
            <?php if(count($logros_obtenidos) > 0): ?>
                <!-- Mostrar logros obtenidos -->
                <?php foreach($logros_obtenidos as $logro): 
                    $tipo_class = $logro['tipo'] . '-badge';
                ?>
                <div class="achievement-card fade-in-up unlocked-effect">
                    <div class="achievement-header" style="background: <?php echo $logro['color']; ?>;">
                        <div class="achievement-badge <?php echo $tipo_class; ?>">
                            <i class="fas <?php echo $logro['icono']; ?>"></i>
                        </div>
                        <div class="achievement-points">
                            <i class="fas fa-star me-1"></i> <?php echo $logro['puntos']; ?>
                        </div>
                        <h3 class="achievement-title"><?php echo $logro['nombre']; ?></h3>
                    </div>
                    
                    <div class="achievement-body">
                        <p class="achievement-description">
                            <?php echo $logro['descripcion']; ?>
                        </p>
                        
                        <div class="achievement-meta">
                            <span class="achievement-date">
                                <i class="fas fa-calendar-alt me-1"></i>
                                <?php echo date('d/m/Y', strtotime($logro['fecha_obtencion'])); ?>
                            </span>
                            <span class="achievement-status status-obtained">
                                <i class="fas fa-unlock me-1"></i> Desbloqueado
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <!-- Mostrar logros por desbloquear (opcional) -->
                <?php if(count($logros_obtenidos) < 8): ?>
                <div class="achievement-card fade-in-up locked-achievement">
                    <div class="achievement-header">
                        <div class="achievement-badge bronze-badge" style="opacity: 0.5;">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div class="achievement-points" style="opacity: 0.5;">
                            <i class="fas fa-star me-1"></i> 300
                        </div>
                        <h3 class="achievement-title">Leyenda del Aprendizaje</h3>
                    </div>
                    
                    <div class="achievement-body">
                        <p class="achievement-description">
                            Desbloquea todos los logros disponibles y conviértete en una leyenda
                        </p>
                        
                        <div class="achievement-meta">
                            <span class="achievement-date">
                                Por desbloquear
                            </span>
                            <span class="achievement-status locked-status">
                                <i class="fas fa-lock me-1"></i> <?php echo count($logros_obtenidos); ?>/8
                            </span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <!-- Sin logros -->
                <div class="col-12 text-center py-5 fade-in-up" style="grid-column: 1 / -1; background: white; border-radius: 25px; padding: 60px 30px; box-shadow: var(--card-shadow);">
                    <i class="fas fa-trophy fa-4x text-muted mb-4 opacity-50"></i>
                    <h3 class="mb-3" style="font-family: 'Fredoka One', cursive; color: var(--primary);">
                        ¡Comienza tu Colección de Logros!
                    </h3>
                    <p class="text-muted mb-4" style="max-width: 500px; margin: 0 auto;">
                        Aún no has desbloqueado ningún logro. 
                        ¡Completa actividades, obtén buenas calificaciones y avanza en tus cursos para ganar logros!
                    </p>
                    <a href="mis_cursos.php" class="btn-main-action">
                        <i class="fas fa-play me-2"></i> Comenzar Aventuras
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Sección de próximos desafíos -->
        <div class="side-panel fade-in-up" style="animation-delay: 0.4s">
            <h4 class="panel-title">
                <i class="fas fa-bullseye"></i>
                Próximos Desafíos
            </h4>
            
            <div class="activity-list">
                <?php if($act_completadas < 1): ?>
                <div class="activity-item">
                    <div class="activity-icon" style="background: linear-gradient(135deg, var(--bronce), #CD7F32);">
                        <i class="fas fa-seedling"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title">Primeros Pasos</div>
                        <div class="activity-meta">
                            <span>
                                <i class="fas fa-tasks me-1"></i>
                                Completa tu primera actividad
                            </span>
                        </div>
                    </div>
                    <span class="activity-status status-pending">
                        Pendiente
                    </span>
                </div>
                <?php endif; ?>
                
                <?php if($act_completadas < 3): ?>
                <div class="activity-item">
                    <div class="activity-icon" style="background: linear-gradient(135deg, var(--plata), #C0C0C0);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title">Completista</div>
                        <div class="activity-meta">
                            <span>
                                <i class="fas fa-tasks me-1"></i>
                                Completa 3 actividades (<?php echo $act_completadas; ?>/3)
                            </span>
                        </div>
                    </div>
                    <span class="activity-status status-pending">
                        <?php echo $act_completadas; ?>/3
                    </span>
                </div>
                <?php endif; ?>
                
                <?php if($calificaciones_altas < 2): ?>
                <div class="activity-item">
                    <div class="activity-icon" style="background: linear-gradient(135deg, var(--oro), #FFD700);">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title">Excelencia Académica</div>
                        <div class="activity-meta">
                            <span>
                                <i class="fas fa-star me-1"></i>
                                Obtén 2 calificaciones altas (<?php echo $calificaciones_altas; ?>/2)
                            </span>
                        </div>
                    </div>
                    <span class="activity-status status-pending">
                        <?php echo $calificaciones_altas; ?>/2
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar for mobile
        document.querySelector('.menu-toggle').addEventListener('click', function() {
            const sidebar = document.querySelector('.sidebar-kid');
            sidebar.classList.toggle('active');
            this.innerHTML = sidebar.classList.contains('active') 
                ? '<i class="fas fa-times"></i>' 
                : '<i class="fas fa-bars"></i>';
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar-kid');
            const menuToggle = document.querySelector('.menu-toggle');
            
            if (window.innerWidth < 992 && 
                !sidebar.contains(event.target) && 
                !menuToggle.contains(event.target) && 
                sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
            }
        });

        // Animación del avatar
        const kidAvatar = document.getElementById('kidAvatar');
        kidAvatar.addEventListener('click', function() {
            this.style.transform = 'scale(1.1) rotate(5deg)';
            this.style.boxShadow = '0 12px 30px rgba(0,0,0,0.2)';
            setTimeout(() => {
                this.style.transform = '';
                this.style.boxShadow = '';
            }, 300);
        });
        
        // Efecto especial para tarjetas de logro
        document.addEventListener('DOMContentLoaded', function() {
            // Animar barras de progreso
            const progressBar = document.querySelector('.level-progress-fill');
            if (progressBar) {
                const width = progressBar.style.width;
                progressBar.style.width = '0%';
                
                setTimeout(() => {
                    progressBar.style.transition = 'width 1.5s cubic-bezier(0.34, 1.56, 0.64, 1)';
                    progressBar.style.width = width;
                }, 300);
            }
            
            // Efecto hover para tarjetas de logro
            const achievementCards = document.querySelectorAll('.achievement-card:not(.locked-achievement)');
            achievementCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    const badge = this.querySelector('.achievement-badge');
                    if (badge) {
                        badge.style.transform = 'scale(1.1) rotate(10deg)';
                    }
                });
                
                card.addEventListener('mouseleave', function() {
                    const badge = this.querySelector('.achievement-badge');
                    if (badge) {
                        badge.style.transform = 'scale(1) rotate(0deg)';
                    }
                });
                
                // Efecto de clic para logros desbloqueados
                card.addEventListener('click', function() {
                    this.style.transform = 'scale(0.98)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                    
                    // Crear efecto de partículas
                    const header = this.querySelector('.achievement-header');
                    if (header) {
                        for (let i = 0; i < 5; i++) {
                            const particle = document.createElement('div');
                            particle.style.position = 'absolute';
                            particle.style.width = '6px';
                            particle.style.height = '6px';
                            particle.style.background = 'gold';
                            particle.style.borderRadius = '50%';
                            particle.style.left = '50%';
                            particle.style.top = '50%';
                            particle.style.transform = 'translate(-50%, -50%)';
                            particle.style.zIndex = '100';
                            particle.style.pointerEvents = 'none';
                            
                            header.appendChild(particle);
                            
                            const angle = Math.random() * Math.PI * 2;
                            const distance = Math.random() * 40 + 20;
                            particle.animate([
                                { transform: 'translate(-50%, -50%) scale(1)', opacity: 1 },
                                { 
                                    transform: `translate(-50%, -50%) translate(${Math.cos(angle) * distance}px, ${Math.sin(angle) * distance}px) scale(0)`, 
                                    opacity: 0 
                                }
                            ], {
                                duration: 1000,
                                easing: 'ease-out'
                            });
                            
                            setTimeout(() => particle.remove(), 1000);
                        }
                    }
                });
            });
            
            // Animar elementos al hacer scroll
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('fade-in-up');
                    }
                });
            }, observerOptions);
            
            document.querySelectorAll('.fade-in-up').forEach(el => {
                observer.observe(el);
            });
            
            // Efecto de brillo para logros de oro
            const goldAchievements = document.querySelectorAll('.gold-badge');
            goldAchievements.forEach(badge => {
                setInterval(() => {
                    badge.style.filter = 'drop-shadow(0 0 8px gold)';
                    setTimeout(() => {
                        badge.style.filter = 'drop-shadow(0 0 3px gold)';
                    }, 500);
                }, 2000);
            });
        });
        
        // Efecto especial para botones
        document.querySelectorAll('.btn-main-action').forEach(btn => {
            btn.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px) scale(1.05)';
            });
            
            btn.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });
    </script>
</body>
</html>