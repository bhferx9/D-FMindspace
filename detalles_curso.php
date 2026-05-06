<?php
include 'php/config.php';
session_start();

// Verificar si el usuario es alumno
if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'alumno') {
    header("Location: index.php");
    exit();
}

$alumno_id = $_SESSION['user_id'];
$nombre_alumno = $_SESSION['nombre'];

// Obtener ID del curso desde la URL
if (!isset($_GET['id'])) {
    header("Location: mis_cursos.php");
    exit();
}

$curso_id = $_GET['id'];

// Verificar que el alumno está inscrito en este curso
$sql_verificar = "SELECT i.*, c.nombre, c.descripcion, c.nivel, c.duracion_horas 
                  FROM inscripciones i 
                  JOIN cursos c ON i.id_curso = c.id 
                  WHERE i.id_alumno = '$alumno_id' 
                  AND i.id_curso = '$curso_id' 
                  AND i.estado = 'activo'";
$res_verificar = mysqli_query($conn, $sql_verificar);

if (mysqli_num_rows($res_verificar) == 0) {
    header("Location: mis_cursos.php");
    exit();
}

$curso_data = mysqli_fetch_assoc($res_verificar);
$curso_nombre = $curso_data['nombre'];
$curso_descripcion = $curso_data['descripcion'];
$curso_nivel = $curso_data['nivel'];
$curso_duracion = $curso_data['duracion_horas'];
$progreso_curso = $curso_data['progreso'];

// Obtener tutor del curso
$sql_tutor = "SELECT u.nombre, u.email 
              FROM usuarios u 
              JOIN cursos c ON u.id = c.id_tutor 
              WHERE c.id = '$curso_id'";
$res_tutor = mysqli_query($conn, $sql_tutor);
$tutor_data = mysqli_fetch_assoc($res_tutor);

// Obtener todas las actividades del curso
$sql_actividades = "SELECT * FROM actividades 
                    WHERE id_curso = '$curso_id' 
                    ORDER BY fecha_limite ASC";
$res_actividades = mysqli_query($conn, $sql_actividades);
$total_actividades = mysqli_num_rows($res_actividades);

// Obtener actividades completadas del alumno
$sql_completadas = "SELECT COUNT(DISTINCT e.id_actividad) as completadas 
                    FROM entregas e 
                    JOIN actividades a ON e.id_actividad = a.id 
                    WHERE e.id_alumno = '$alumno_id' 
                    AND a.id_curso = '$curso_id' 
                    AND e.estado = 'calificado'";
$res_completadas = mysqli_query($conn, $sql_completadas);
$completadas_data = mysqli_fetch_assoc($res_completadas);
$actividades_completadas = $completadas_data['completadas'];

// Obtener estadísticas de calificaciones
$sql_calificaciones = "SELECT ev.calificacion 
                       FROM entregas e 
                       JOIN evaluaciones ev ON e.id = ev.id_entrega 
                       JOIN actividades a ON e.id_actividad = a.id 
                       WHERE e.id_alumno = '$alumno_id' 
                       AND a.id_curso = '$curso_id' 
                       AND ev.calificacion IS NOT NULL";
$res_calificaciones = mysqli_query($conn, $sql_calificaciones);

$total_calificaciones = 0;
$suma_calificaciones = 0;
$calificaciones_array = [];

while ($cal = mysqli_fetch_assoc($res_calificaciones)) {
    $suma_calificaciones += $cal['calificacion'];
    $total_calificaciones++;
    $calificaciones_array[] = $cal['calificacion'];
}

$promedio_calificaciones = $total_calificaciones > 0 ? round($suma_calificaciones / $total_calificaciones, 1) : 0;

// Calcular máxima y mínima calificación
$max_calificacion = !empty($calificaciones_array) ? max($calificaciones_array) : 0;
$min_calificacion = !empty($calificaciones_array) ? min($calificaciones_array) : 0;

// Obtener puntos totales ganados en este curso
$sql_puntos = "SELECT SUM(a.puntos) as puntos_totales 
               FROM entregas e 
               JOIN actividades a ON e.id_actividad = a.id 
               WHERE e.id_alumno = '$alumno_id' 
               AND a.id_curso = '$curso_id' 
               AND e.estado = 'calificado'";
$res_puntos = mysqli_query($conn, $sql_puntos);
$puntos_data = mysqli_fetch_assoc($res_puntos);
$puntos_totales = $puntos_data['puntos_totales'] ?? 0;

// Obtener fechas importantes del curso
$sql_fechas = "SELECT MIN(fecha_limite) as primera_fecha, MAX(fecha_limite) as ultima_fecha 
               FROM actividades 
               WHERE id_curso = '$curso_id'";
$res_fechas = mysqli_query($conn, $sql_fechas);
$fechas_data = mysqli_fetch_assoc($res_fechas);

// Obtener actividades próximas a vencer (en los próximos 7 días)
$hoy = date('Y-m-d');
$proxima_semana = date('Y-m-d', strtotime('+7 days'));

$sql_proximas = "SELECT * FROM actividades 
                 WHERE id_curso = '$curso_id' 
                 AND fecha_limite BETWEEN '$hoy' AND '$proxima_semana'
                 ORDER BY fecha_limite ASC 
                 LIMIT 5";
$res_proximas = mysqli_query($conn, $sql_proximas);

// Obtener avatar del alumno
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

$check_avatar_col = mysqli_query($conn, "SHOW COLUMNS FROM usuarios LIKE 'avatar'");
$avatar_key = 'panda';

if(mysqli_num_rows($check_avatar_col) > 0) {
    $query_avatar = "SELECT avatar FROM usuarios WHERE id = '$alumno_id'";
    $res_avatar = mysqli_query($conn, $query_avatar);
    if($res_avatar && mysqli_num_rows($res_avatar) > 0) {
        $avatar_data = mysqli_fetch_assoc($res_avatar);
        $avatar_key = $avatar_data['avatar'] ?: 'panda';
    }
}

if(!isset($avatares[$avatar_key])) {
    $avatar_key = 'panda';
}

$avatar_emoji = $avatares[$avatar_key]['emoji'];
$avatar_color = $avatares[$avatar_key]['color'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles: <?php echo htmlspecialchars($curso_nombre); ?> - D&F Mindspace</title>
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
            border-top: 1px solid rgba(44, 186, 236, 0.1);
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
            font-family: 'Fredoka One', cursive;
            font-size: 1.1rem;
            color: var(--accent);
            font-weight: 600;
        }
        
        /* Avatar del niño */
        .kid-avatar {
            position: relative;
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: <?php echo $avatar_color; ?>;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            border: 3px solid white;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .kid-avatar:hover {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 12px 30px rgba(0,0,0,0.2);
        }
        
        .avatar-emoji {
            font-size: 2.8rem;
            filter: drop-shadow(2px 2px 4px rgba(0,0,0,0.2));
        }
        
        .kid-name {
            font-family: 'Fredoka One', cursive;
            font-size: 1.2rem;
            color: var(--primary);
            margin-bottom: 5px;
        }
        
        .kid-level {
            font-size: 0.8rem;
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
        
        .menu-toggle:hover {
            transform: scale(1.1);
        }
        
        /* Encabezado del curso */
        .course-header {
            background: linear-gradient(135deg, #ffffff, #f8fdff);
            border-radius: 25px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
            border: 3px solid rgba(44, 186, 236, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .course-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(44, 186, 236, 0.05));
        }
        
        .course-title {
            font-family: 'Fredoka One', cursive;
            font-size: 2.5rem;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 15px;
            line-height: 1.2;
        }
        
        .course-description {
            font-size: 1.2rem;
            color: #666;
            line-height: 1.6;
        }
        
        /* Estadísticas del curso */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
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
        
        .stat-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin: 0 auto 15px;
            color: white;
        }
        
        .stat-icon-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }
        
        .stat-icon-success {
            background: linear-gradient(135deg, var(--accent), #6aab39);
        }
        
        .stat-icon-warning {
            background: linear-gradient(135deg, var(--secondary), #f5c15d);
        }
        
        .stat-icon-info {
            background: linear-gradient(135deg, var(--purple), var(--pink));
        }
        
        .stat-value {
            font-size: 2.2rem;
            font-weight: 800;
            margin: 10px 0;
            font-family: 'Fredoka One', cursive;
        }
        
        .stat-value-primary {
            color: var(--primary);
        }
        
        .stat-value-success {
            color: var(--accent);
        }
        
        .stat-value-warning {
            color: var(--secondary);
        }
        
        .stat-value-info {
            color: var(--purple);
        }
        
        .stat-label {
            color: #666;
            font-size: 0.95rem;
            font-weight: 600;
        }
        
        /* Barra de progreso principal */
        .progress-section {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
            border: 3px solid rgba(44, 186, 236, 0.1);
        }
        
        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .progress-label {
            font-family: 'Fredoka One', cursive;
            font-size: 1.4rem;
            color: var(--primary);
        }
        
        .progress-percentage {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .progress-container {
            height: 20px;
            background: rgba(44, 186, 236, 0.1);
            border-radius: 15px;
            overflow: hidden;
            position: relative;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            border-radius: 15px;
            transition: width 1s ease-in-out;
            position: relative;
            overflow: hidden;
        }
        
        .progress-fill::after {
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
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        /* Información del curso */
        .course-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            border: 3px solid rgba(44, 186, 236, 0.1);
        }
        
        .info-card-title {
            font-family: 'Fredoka One', cursive;
            font-size: 1.3rem;
            color: var(--primary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Tabla de actividades */
        .activities-section {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
            border: 3px solid rgba(44, 186, 236, 0.1);
        }
        
        .section-title {
            font-family: 'Fredoka One', cursive;
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .activities-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .activities-table th {
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.1), rgba(44, 186, 236, 0.05));
            color: var(--primary);
            font-weight: 700;
            padding: 15px;
            text-align: left;
            border-bottom: 3px solid rgba(44, 186, 236, 0.2);
        }
        
        .activities-table td {
            padding: 15px;
            border-bottom: 1px solid rgba(44, 186, 236, 0.1);
            vertical-align: middle;
        }
        
        .activities-table tr:hover {
            background: rgba(44, 186, 236, 0.05);
        }
        
        .activity-status {
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-completed {
            background: rgba(131, 191, 70, 0.1);
            color: var(--accent);
        }
        
        .status-pending {
            background: rgba(240, 174, 42, 0.1);
            color: var(--secondary);
        }
        
        .status-missing {
            background: rgba(255, 107, 139, 0.1);
            color: var(--danger);
        }
        
        /* Botones de acción */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn-action {
            padding: 12px 25px;
            border-radius: 15px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }
        
        .btn-primary-action {
            background: linear-gradient(90deg, var(--primary), #2ca5d4);
            color: white;
            box-shadow: 0 5px 15px rgba(44, 186, 236, 0.3);
        }
        
        .btn-primary-action:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 20px rgba(44, 186, 236, 0.4);
            color: white;
        }
        
        .btn-secondary-action {
            background: white;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-secondary-action:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-3px) scale(1.02);
        }
        
        /* Botón de volver */
        .btn-back {
            background: linear-gradient(90deg, var(--primary), #2ca5d4);
            color: white;
            border: none;
            border-radius: 15px;
            padding: 10px 20px;
            font-weight: 700;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 5px 15px rgba(44, 186, 236, 0.3);
            text-decoration: none;
            margin-bottom: 20px;
        }
        
        .btn-back:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 8px 20px rgba(44, 186, 236, 0.4);
            color: white;
        }
        
        /* Nivel del curso */
        .course-level {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            background: linear-gradient(90deg, var(--secondary), #f5c15d);
            color: white;
            border-radius: 15px;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
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
            
            .course-title {
                font-size: 2rem;
            }
            
            .activities-table {
                display: block;
                overflow-x: auto;
            }
        }
        
        @media (max-width: 768px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .course-info-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-action {
                justify-content: center;
            }
        }
        
        @media (max-width: 576px) {
            .course-title {
                font-size: 1.8rem;
            }
            
            .course-header {
                padding: 25px 20px;
            }
            
            .section-title {
                font-size: 1.3rem;
            }
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
        
        /* Calificación */
        .grade-badge {
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 12px;
        }
        
        .grade-excellent {
            background: rgba(131, 191, 70, 0.2);
            color: var(--accent);
        }
        
        .grade-good {
            background: rgba(44, 186, 236, 0.2);
            color: var(--primary);
        }
        
        .grade-average {
            background: rgba(240, 174, 42, 0.2);
            color: var(--secondary);
        }
        
        .grade-poor {
            background: rgba(255, 107, 139, 0.2);
            color: var(--danger);
        }
        
        /* Indicador de tiempo */
        .time-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .time-urgent {
            background: rgba(255, 107, 139, 0.1);
            color: var(--danger);
            border: 2px solid rgba(255, 107, 139, 0.2);
        }
        
        .time-warning {
            background: rgba(240, 174, 42, 0.1);
            color: var(--secondary);
            border: 2px solid rgba(240, 174, 42, 0.2);
        }
        
        .time-normal {
            background: rgba(44, 186, 236, 0.1);
            color: var(--primary);
            border: 2px solid rgba(44, 186, 236, 0.2);
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
                    <div class="logo-sub">Aventuras de Aprendizaje</div>
                </div>
                
                <!-- Avatar del niño -->
                <div class="kid-avatar" id="kidAvatar">
                    <span class="avatar-emoji"><?php echo $avatar_emoji; ?></span>
                </div>
                
                <h4 class="kid-name"><?php echo htmlspecialchars($nombre_alumno); ?></h4>
                <span class="kid-level">
                    <i class="fas fa-star me-1"></i>Explorador
                </span>
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
                    <a href="mis_cursos.php" class="nav-link active">
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
                    <a href="mis_logros.php" class="nav-link">
                        <i class="fas fa-trophy"></i>
                        <span>Mis Logros</span>
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
        
        <!-- Botón de volver -->
        <a href="mis_cursos.php" class="btn-back fade-in-up">
            <i class="fas fa-arrow-left"></i> Volver a Mis Aventuras
        </a>
        
        <!-- Encabezado del curso -->
        <div class="course-header fade-in-up">
            <!-- Nivel del curso -->
            <div class="course-level">
                <i class="fas fa-<?php 
                    switch(strtolower($curso_nivel)) {
                        case 'básico': echo 'seedling'; break;
                        case 'intermedio': echo 'star'; break;
                        case 'avanzado': echo 'rocket'; break;
                        default: echo 'compass';
                    }
                ?>"></i>
                Nivel: <?php echo htmlspecialchars($curso_nivel); ?>
            </div>
            
            <h1 class="course-title"><?php echo htmlspecialchars($curso_nombre); ?></h1>
            <p class="course-description">
                <?php echo htmlspecialchars($curso_descripcion); ?>
            </p>
            
            <!-- Información adicional -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <p><i class="fas fa-user-graduate me-2 text-primary"></i> <strong>Tutor:</strong> <?php echo htmlspecialchars($tutor_data['nombre']); ?></p>
                    <p><i class="fas fa-clock me-2 text-primary"></i> <strong>Duración:</strong> <?php echo $curso_duracion; ?> horas</p>
                </div>
                <div class="col-md-6">
                    <?php if($fechas_data['primera_fecha']): ?>
                        <p><i class="fas fa-calendar-alt me-2 text-primary"></i> <strong>Inicio:</strong> <?php echo date('d/m/Y', strtotime($fechas_data['primera_fecha'])); ?></p>
                    <?php endif; ?>
                    <?php if($fechas_data['ultima_fecha']): ?>
                        <p><i class="fas fa-calendar-times me-2 text-primary"></i> <strong>Fin estimado:</strong> <?php echo date('d/m/Y', strtotime($fechas_data['ultima_fecha'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Estadísticas del curso -->
        <div class="stats-container fade-in-up" style="animation-delay: 0.1s">
            <div class="stat-card">
                <div class="stat-icon stat-icon-primary">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value stat-value-primary"><?php echo $progreso_curso; ?>%</div>
                <div class="stat-label">Progreso General</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon stat-icon-success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value stat-value-success"><?php echo $actividades_completadas; ?>/<?php echo $total_actividades; ?></div>
                <div class="stat-label">Misiones Completadas</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon stat-icon-warning">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-value stat-value-warning"><?php echo $promedio_calificaciones; ?></div>
                <div class="stat-label">Calificación Promedio</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon stat-icon-info">
                    <i class="fas fa-gem"></i>
                </div>
                <div class="stat-value stat-value-info"><?php echo $puntos_totales; ?></div>
                <div class="stat-label">Puntos Ganados</div>
            </div>
        </div>
        
        <!-- Barra de progreso principal -->
        <div class="progress-section fade-in-up" style="animation-delay: 0.2s">
            <div class="progress-header">
                <div class="progress-label">Tu Progreso en esta Aventura</div>
                <div class="progress-percentage"><?php echo $progreso_curso; ?>%</div>
            </div>
            
            <div class="progress-container">
                <div class="progress-fill" style="width: <?php echo $progreso_curso; ?>%"></div>
            </div>
            
            <div class="mt-3 text-center">
                <small class="text-muted">
                    <i class="fas fa-lightbulb text-warning me-1"></i>
                    <?php if($progreso_curso < 30): ?>
                        ¡Recién comienzas! Sigue completando misiones para avanzar.
                    <?php elseif($progreso_curso < 70): ?>
                        ¡Vas por buen camino! Sigue así para dominar esta aventura.
                    <?php elseif($progreso_curso < 100): ?>
                        ¡Estás cerca de completar la aventura! Un último esfuerzo.
                    <?php else: ?>
                        ¡Felicidades! Has completado esta aventura exitosamente.
                    <?php endif; ?>
                </small>
            </div>
        </div>
        
        <!-- Información del curso -->
        <div class="course-info-grid fade-in-up" style="animation-delay: 0.3s">
            <!-- Calificaciones -->
            <div class="info-card">
                <h3 class="info-card-title">
                    <i class="fas fa-trophy"></i> Rendimiento
                </h3>
                <div class="mb-3">
                    <p><strong>Calificación Promedio:</strong> <span class="fw-bold fs-5"><?php echo $promedio_calificaciones; ?>/10</span></p>
                    <?php if($max_calificacion > 0): ?>
                        <p><strong>Mejor Calificación:</strong> <span class="fw-bold text-success"><?php echo $max_calificacion; ?>/10</span></p>
                    <?php endif; ?>
                    <?php if($min_calificacion > 0): ?>
                        <p><strong>Peor Calificación:</strong> <span class="fw-bold text-warning"><?php echo $min_calificacion; ?>/10</span></p>
                    <?php endif; ?>
                    <p><strong>Calificaciones Obtenidas:</strong> <span class="fw-bold"><?php echo $total_calificaciones; ?>/<?php echo $total_actividades; ?></span></p>
                </div>
                
                <?php if($promedio_calificaciones >= 9): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-medal me-2"></i>
                        <strong>¡Excelente trabajo!</strong> Tu desempeño es sobresaliente.
                    </div>
                <?php elseif($promedio_calificaciones >= 7): ?>
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-thumbs-up me-2"></i>
                        <strong>¡Buen trabajo!</strong> Vas por buen camino.
                    </div>
                <?php elseif($promedio_calificaciones > 0): ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-lightbulb me-2"></i>
                        <strong>¡Puedes mejorar!</strong> Revisa las misiones para mejorar tu calificación.
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Tutor -->
            <div class="info-card">
                <h3 class="info-card-title">
                    <i class="fas fa-user-graduate"></i> Tu Guía
                </h3>
                <div class="d-flex align-items-center mb-3">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                        <i class="fas fa-chalkboard-teacher fa-2x text-primary"></i>
                    </div>
                    <div>
                        <h5 class="mb-1"><?php echo htmlspecialchars($tutor_data['nombre']); ?></h5>
                        <p class="text-muted mb-0">Tutor de la aventura</p>
                    </div>
                </div>
                <p><i class="fas fa-envelope me-2"></i> <strong>Email:</strong> <?php echo htmlspecialchars($tutor_data['email']); ?></p>
                <p><i class="fas fa-comments me-2"></i> <strong>Disponible para:</strong></p>
                <ul class="list-unstyled">
                    <li><i class="fas fa-check-circle text-success me-2"></i> Responder dudas</li>
                    <li><i class="fas fa-check-circle text-success me-2"></i> Revisar actividades</li>
                    <li><i class="fas fa-check-circle text-success me-2"></i> Orientación personalizada</li>
                </ul>
            </div>
        </div>
        
        <!-- Actividades del curso -->
        <div class="activities-section fade-in-up" style="animation-delay: 0.4s">
            <h3 class="section-title">
                <i class="fas fa-tasks"></i> Mis Misiones en esta Aventura
            </h3>
            
            <?php if($total_actividades > 0): ?>
                <div class="table-responsive">
                    <table class="activities-table">
                        <thead>
                            <tr>
                                <th>Misión</th>
                                <th>Tipo</th>
                                <th>Fecha Límite</th>
                                <th>Estado</th>
                                <th>Calificación</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php mysqli_data_seek($res_actividades, 0); ?>
                            <?php while($actividad = mysqli_fetch_assoc($res_actividades)): 
                                // Obtener estado de la actividad para este alumno
                                $sql_estado = "SELECT e.estado, ev.calificacion 
                                              FROM entregas e 
                                              LEFT JOIN evaluaciones ev ON e.id = ev.id_entrega 
                                              WHERE e.id_actividad = '" . $actividad['id'] . "' 
                                              AND e.id_alumno = '$alumno_id' 
                                              ORDER BY e.fecha_entrega DESC 
                                              LIMIT 1";
                                $res_estado = mysqli_query($conn, $sql_estado);
                                $estado_data = mysqli_fetch_assoc($res_estado);
                                
                                $estado = $estado_data['estado'] ?? 'pendiente';
                                $calificacion = $estado_data['calificacion'] ?? null;
                                
                                // Determinar clase de estado
                                $estado_class = '';
                                switch($estado) {
                                    case 'calificado': $estado_class = 'status-completed'; break;
                                    case 'pendiente': $estado_class = 'status-pending'; break;
                                    default: $estado_class = 'status-missing';
                                }
                                
                                // Determinar clase de calificación
                                $grade_class = '';
                                if($calificacion !== null) {
                                    if($calificacion >= 9) $grade_class = 'grade-excellent';
                                    elseif($calificacion >= 7) $grade_class = 'grade-good';
                                    elseif($calificacion >= 5) $grade_class = 'grade-average';
                                    else $grade_class = 'grade-poor';
                                }
                                
                                // Determinar indicador de tiempo
                                $fecha_limite = strtotime($actividad['fecha_limite']);
                                $dias_restantes = floor(($fecha_limite - time()) / (60 * 60 * 24));
                                
                                $time_class = '';
                                if($dias_restantes < 0) {
                                    $time_text = 'Vencida';
                                    $time_class = 'time-urgent';
                                } elseif($dias_restantes < 3) {
                                    $time_text = '¡Pronto!';
                                    $time_class = 'time-urgent';
                                } elseif($dias_restantes < 7) {
                                    $time_text = 'Esta semana';
                                    $time_class = 'time-warning';
                                } else {
                                    $time_text = 'Tiempo suficiente';
                                    $time_class = 'time-normal';
                                }
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($actividad['titulo']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars(substr($actividad['descripcion'], 0, 60)) . (strlen($actividad['descripcion']) > 60 ? '...' : ''); ?></small>
                                </td>
                                <td>
                                    <?php 
                                    $tipo_icon = '';
                                    switch(strtolower($actividad['tipo'])) {
                                        case 'quiz': $tipo_icon = 'fa-puzzle-piece'; break;
                                        case 'tarea': $tipo_icon = 'fa-tasks'; break;
                                        case 'examen': $tipo_icon = 'fa-file-alt'; break;
                                        default: $tipo_icon = 'fa-star';
                                    }
                                    ?>
                                    <i class="fas <?php echo $tipo_icon; ?> me-1"></i>
                                    <?php echo htmlspecialchars($actividad['tipo']); ?>
                                </td>
                                <td>
                                    <div class="<?php echo $time_class; ?> time-indicator">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo date('d/m/Y', strtotime($actividad['fecha_limite'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="activity-status <?php echo $estado_class; ?>">
                                        <i class="fas fa-<?php 
                                            switch($estado) {
                                                case 'calificado': echo 'check-circle'; break;
                                                case 'pendiente': echo 'clock'; break;
                                                default: echo 'exclamation-triangle';
                                            }
                                        ?> me-1"></i>
                                        <?php echo ucfirst($estado); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($calificacion !== null): ?>
                                        <span class="grade-badge <?php echo $grade_class; ?>">
                                            <?php echo $calificacion; ?>/10
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($estado == 'calificado'): ?>
                                        <a href="entrega.php?id_actividad=<?php echo $actividad['id']; ?>&id_alumno=<?php echo $alumno_id; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i> Ver
                                        </a>
                                    <?php else: ?>
                                        <a href="realizar_actividad.php?id=<?php echo $actividad['id']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="fas fa-play me-1"></i> Realizar
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-tasks fa-4x text-muted mb-3 opacity-50"></i>
                    <h5 class="text-muted mb-3">No hay misiones asignadas en este curso</h5>
                    <p class="text-muted">Tu tutor aún no ha publicado misiones para esta aventura.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Acciones principales -->
        <div class="action-buttons fade-in-up" style="animation-delay: 0.5s">
            <a href="ver_curso.php?id=<?php echo $curso_id; ?>" class="btn-action btn-primary-action">
                <i class="fas fa-play-circle"></i> Continuar Aventura
            </a>
            <a href="mis_cursos.php" class="btn-action btn-secondary-action">
                <i class="fas fa-arrow-left"></i> Volver a Mis Aventuras
            </a>
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
        
        // Animación de la barra de progreso
        document.addEventListener('DOMContentLoaded', function() {
            const progressBar = document.querySelector('.progress-fill');
            const width = progressBar.style.width;
            progressBar.style.width = '0%';
            
            setTimeout(() => {
                progressBar.style.transition = 'width 1.5s cubic-bezier(0.34, 1.56, 0.64, 1)';
                progressBar.style.width = width;
            }, 300);
            
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
        });
        
        // Efecto especial para botones
        document.querySelectorAll('.btn-primary-action, .btn-secondary-action, .btn-back').forEach(btn => {
            btn.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-3px) scale(1.02)';
            });
            
            btn.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });
    </script>
</body>
</html>