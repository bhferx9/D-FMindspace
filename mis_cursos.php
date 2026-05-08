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

// Consultar cursos en los que está inscrito (CORREGIDO PARA POSTGRESQL)
// Consultar cursos en los que está inscrito - USANDO PDO DIRECTAMENTE
try {
    $stmt = $conn->pdo->prepare("
        SELECT c.id, c.nombre, c.descripcion, i.progreso, c.nivel, c.duracion_horas, 
               c.id_tutor, u.nombre as tutor_nombre, COUNT(a.id) as total_actividades
        FROM inscripciones i 
        JOIN cursos c ON i.id_curso = c.id 
        LEFT JOIN usuarios u ON c.id_tutor = u.id
        LEFT JOIN actividades a ON c.id = a.id_curso
        WHERE i.id_alumno = :alumno_id AND i.estado = 'activo'
        GROUP BY c.id, c.nombre, c.descripcion, i.progreso, c.nivel, c.duracion_horas, 
                 c.id_tutor, u.nombre, i.fecha_inscripcion
        ORDER BY i.fecha_inscripcion DESC
    ");
    $stmt->execute([':alumno_id' => $alumno_id]);
    $cursos_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_cursos = count($cursos_data);
} catch(PDOException $e) {
    $cursos_data = [];
    $total_cursos = 0;
}

// Consultar actividades recientes
$query_actividades = "SELECT a.titulo, a.id_curso, e.fecha_entrega, e.estado, ev.calificacion 
                      FROM entregas e 
                      JOIN actividades a ON e.id_actividad = a.id 
                      LEFT JOIN evaluaciones ev ON e.id = ev.id_entrega
                      WHERE e.id_alumno = '$alumno_id' 
                      ORDER BY e.fecha_entrega DESC LIMIT 5";
$res_actividades = mysqli_query($conn, $query_actividades);
// Calcular estadísticas
$progreso_promedio = 0;
$total_actividades_completadas = 0;

if ($total_cursos > 0) {
    // Recorrer los cursos para calcular progreso y actividades completadas
    foreach($cursos_data as $curso) {
        $progreso_promedio += $curso['progreso'];
        
        // Contar actividades completadas para este curso
        try {
            $stmt_completadas = $conn->pdo->prepare("
                SELECT COUNT(DISTINCT e.id_actividad) as completadas 
                FROM entregas e 
                JOIN actividades a ON e.id_actividad = a.id 
                WHERE e.id_alumno = :alumno_id 
                AND a.id_curso = :curso_id 
                AND e.estado = 'calificado'
            ");
            $stmt_completadas->execute([
                ':alumno_id' => $alumno_id,
                ':curso_id' => $curso['id']
            ]);
            $completadas_data = $stmt_completadas->fetch(PDO::FETCH_ASSOC);
            $total_actividades_completadas += $completadas_data['completadas'];
        } catch(PDOException $e) {
            // Error al contar, continuar
        }
    }
    $progreso_promedio = round($progreso_promedio / $total_cursos);
}

if ($total_cursos > 0) {
    mysqli_data_seek($res_cursos, 0);
    while($curso = mysqli_fetch_assoc($res_cursos)) {
        $progreso_promedio += $curso['progreso'];
        
        // Contar actividades completadas para este curso
        $curso_id = $curso['id'];
        $sql_completadas = "SELECT COUNT(DISTINCT e.id_actividad) as completadas 
                           FROM entregas e 
                           JOIN actividades a ON e.id_actividad = a.id 
                           WHERE e.id_alumno = '$alumno_id' 
                           AND a.id_curso = '$curso_id' 
                           AND e.estado = 'calificado'";
        $res_completadas = mysqli_query($conn, $sql_completadas);
        $completadas_data = mysqli_fetch_assoc($res_completadas);
        $total_actividades_completadas += $completadas_data['completadas'];
    }
    $progreso_promedio = round($progreso_promedio / $total_cursos);
}

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

// CORREGIDO PARA POSTGRESQL - Verificar columna avatar
$check_avatar_col = mysqli_query($conn, "SELECT column_name FROM information_schema.columns WHERE table_name='usuarios' AND column_name='avatar'");
$avatar_key = 'panda';

if(mysqli_num_rows($check_avatar_col) > 0) {
    $query_avatar = "SELECT COALESCE(avatar, 'panda') as avatar FROM usuarios WHERE id = '$alumno_id'";
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
    <title>Mis Aventuras - D&F Mindspace</title>
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
        
        .avatar-status {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 20px;
            height: 20px;
            background: var(--accent);
            border: 3px solid white;
            border-radius: 50%;
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
        
        .badge-notification {
            background: linear-gradient(90deg, var(--secondary), #f5c15d);
            color: white;
            border-radius: 10px;
            padding: 4px 10px;
            font-size: 0.75rem;
            font-weight: 600;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
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
        
        /* Avatar del niño */
        /* .kid-avatar {
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
        } */
        
        /* .kid-name {
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
        } */
        

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
            padding: 40px;
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
        
        /* Encabezado de la página */
        .page-header {
            background: linear-gradient(135deg, #ffffff, #f8fdff);
            border-radius: 25px;
            padding: 40px;
            margin-bottom: 40px;
            box-shadow: var(--card-shadow);
            border: 3px solid rgba(44, 186, 236, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(44, 186, 236, 0.05));
        }
        
        .page-title {
            font-family: 'Fredoka One', cursive;
            font-size: 2.8rem;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 15px;
            line-height: 1.2;
        }
        
        .page-description {
            font-size: 1.2rem;
            color: #666;
            line-height: 1.6;
        }
        
        /* Estadísticas */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
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
        
        /* Botón de acción principal */
        .btn-main-action {
            background: linear-gradient(90deg, var(--accent), #6aab39);
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
            box-shadow: 0 8px 20px rgba(131, 191, 70, 0.3);
            text-decoration: none;
        }
        
        .btn-main-action:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 30px rgba(131, 191, 70, 0.4);
            color: white;
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
        
        /* Grid de cursos */
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }
        
        .course-card {
            background: white;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: all 0.4s ease;
            border: 3px solid transparent;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .course-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 25px 50px rgba(44, 186, 236, 0.25);
            border-color: var(--primary);
        }
        
        .course-header {
            padding: 25px 25px 20px;
            position: relative;
            overflow: hidden;
        }
        
        .course-header::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: translateX(-100%);
        }
        
        .course-card:hover .course-header::after {
            animation: shine 1.5s ease;
        }
        
        @keyframes shine {
            to { transform: translateX(100%); }
        }
        
        .course-level {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 15px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .course-title {
            font-family: 'Fredoka One', cursive;
            font-size: 1.6rem;
            color: white;
            margin-bottom: 10px;
            line-height: 1.3;
        }
        
        .course-body {
            padding: 25px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .course-description {
            color: #666;
            margin-bottom: 20px;
            flex: 1;
            line-height: 1.6;
        }
        
        .course-progress-container {
            margin-bottom: 25px;
        }
        
        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        
        .progress-bar-container {
            height: 12px;
            background: rgba(44, 186, 236, 0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            border-radius: 10px;
            transition: width 1s ease-in-out;
            position: relative;
            overflow: hidden;
        }
        
        .progress-bar-fill::after {
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
        
        .course-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(44, 186, 236, 0.05);
            border-radius: 15px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            color: #666;
        }
        
        .meta-item i {
            color: var(--primary);
        }
        
        .course-actions {
            display: flex;
            gap: 10px;
            margin-top: auto;
        }
        
        .btn-course {
            flex: 1;
            padding: 12px 20px;
            border-radius: 15px;
            font-weight: 700;
            text-align: center;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-primary-course {
            background: linear-gradient(90deg, var(--primary), #2ca5d4);
            color: white;
            box-shadow: 0 5px 15px rgba(44, 186, 236, 0.3);
        }
        
        .btn-primary-course:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(44, 186, 236, 0.4);
            color: white;
        }
        
        .btn-outline-course {
            border: 2px solid var(--primary);
            color: var(--primary);
            background: white;
        }
        
        .btn-outline-course:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-3px);
        }
        
        /* Mensaje sin cursos */
        .no-courses {
            text-align: center;
            padding: 80px 30px;
            background: white;
            border-radius: 25px;
            box-shadow: var(--card-shadow);
            border: 3px solid rgba(44, 186, 236, 0.1);
        }
        
        .no-courses-icon {
            font-size: 5rem;
            margin-bottom: 20px;
            opacity: 0.5;
            color: var(--primary);
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .courses-grid {
                grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
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
                padding: 30px;
            }
            
            .menu-toggle {
                display: block;
            }
            
            .page-title {
                font-size: 2.3rem;
            }
            
            .courses-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }
            
            .course-actions {
                flex-direction: column;
            }
            
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 576px) {
            .page-title {
                font-size: 1.8rem;
            }
            
            .page-description {
                font-size: 1.1rem;
            }
            
            .page-header {
                padding: 25px 20px;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
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
        
        /* Actividades recientes */
        .recent-activities {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-top: 30px;
            box-shadow: var(--card-shadow);
            border: 3px solid rgba(44, 186, 236, 0.1);
        }
        
        .activities-title {
            font-family: 'Fredoka One', cursive;
            font-size: 1.4rem;
            color: var(--primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 3px solid rgba(44, 186, 236, 0.1);
        }
        
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.05), rgba(44, 186, 236, 0.02));
            border-radius: 15px;
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
            gap: 15px;
        }
        
        .activity-item:hover {
            transform: translateX(5px);
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.1), rgba(44, 186, 236, 0.05));
        }
        
        .activity-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            flex-shrink: 0;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 700;
            margin-bottom: 5px;
            color: #333;
        }
        
        .activity-meta {
            display: flex;
            gap: 15px;
            font-size: 0.85rem;
            color: #666;
        }
        
        .activity-status {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-completed {
            background: rgba(131, 191, 70, 0.1);
            color: var(--accent);
        }
        
        .status-pending {
            background: rgba(240, 174, 42, 0.1);
            color: var(--secondary);
        }
    </style>
</head>
<body>
    <!-- Botón para móvil -->
    <button class="menu-toggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Elementos decorativos flotantes -->
    <div class="floating-element" style="top: 10%; right: 5%; width: 100px; height: 100px; background: linear-gradient(135deg, rgb(44, 185, 236), rgba(44, 186, 236, 0.1)); animation-delay: 0s;"></div>
    <div class="floating-element" style="bottom: 15%; left: 5%; width: 80px; height: 80px; background: linear-gradient(135deg, rgb(240, 174, 42), rgba(240, 174, 42, 0.1)); animation-delay: 1s;"></div>
    <div class="floating-element" style="top: 30%; left: 10%; width: 60px; height: 60px; background: linear-gradient(135deg, rgb(130, 191, 70), rgba(131, 191, 70, 0.1)); animation-delay: 2s;"></div>
    
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
                    <div class="avatar-status"></div>
                </div>
                
                <h4 class="kid-name"><?php echo $nombre_alumno; ?></h4>
                <span class="kid-level">
                    <i class="fas fa-star me-1"></i>Nivel <?php echo $avatares[$avatar_key]['nivel']; ?>
                </span>
                
                <!-- Puntos -->
                <!-- <div class="points-container" style="text-align: center; padding: 15px; background: linear-gradient(135deg, rgba(255, 107, 139, 0.1), rgba(255, 107, 139, 0.05)); border-radius: 15px; margin: 15px;">
                    <div class="points-value" style="font-size: 2rem; font-family: 'Fredoka One', cursive; color: var(--danger); margin: 5px 0;"><?php echo $puntos_alumno; ?></div>
                    <div class="points-label" style="color: #666; font-size: 0.9rem;">Puntos de Aventura</div>
                </div> -->
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
                        <?php if($total_cursos > 0): ?>
                            <span class="badge-notification ms-auto"><?php echo $total_cursos; ?></span>
                        <?php endif; ?>
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
                        <?php if(mysqli_num_rows($res_actividades) > 0): ?>
                            <span class="badge-notification ms-auto"><?php echo mysqli_num_rows($res_actividades); ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            
                <li class="nav-item">
                    <a href="avatar_shop.php" class="nav-link">
                        <i class="fas fa-user-astronaut"></i>
                        <span>Tienda de Avatares</span>
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
        
        <!-- Encabezado de la página -->
        <div class="page-header fade-in-up">
            <!-- Botón de volver -->
            <a href="dashboard_alumno.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Volver a Mi Mundo
            </a>
            
            <h1 class="page-title">Mis Aventuras de Aprendizaje</h1>
            <p class="page-description">
                Aquí puedes ver todos los cursos en los que estás inscrito. ¡Continúa tu viaje de aprendizaje!
            </p>
        </div>
        
        <!-- Estadísticas -->
        <div class="stats-container fade-in-up" style="animation-delay: 0.1s">
            <div class="stat-card">
                <div class="stat-icon stat-icon-primary">
                    <i class="fas fa-compass"></i>
                </div>
                <div class="stat-value stat-value-primary"><?php echo $total_cursos; ?></div>
                <div class="stat-label">Aventuras Activas</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon stat-icon-success">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value stat-value-success"><?php echo $progreso_promedio; ?>%</div>
                <div class="stat-label">Progreso Promedio</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon stat-icon-warning">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="stat-value stat-value-warning"><?php echo $total_actividades_completadas; ?></div>
                <div class="stat-label">Misiones Completadas</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon stat-icon-info">
                    <i class="fas fa-clock"></i>
                </div>
                <?php
                // Calcular tiempo total estimado
                $tiempo_total = 0;
                if ($total_cursos > 0) {
                    foreach($cursos_data as $curso) {
                        $tiempo_total += $curso['duracion_horas'];
                    }
                }
                ?>
                <div class="stat-value stat-value-info"><?php echo $tiempo_total; ?>h</div>
                <div class="stat-label">Tiempo Total de Aventuras</div>
            </div>
        </div>
    </div>
    
    <!-- Botón para explorar más cursos -->
    <div class="mb-5 fade-in-up" style="animation-delay: 0.2s">
        <a href="catalogo.php" class="btn-main-action">
            <i class="fas fa-plus-circle"></i> Descubrir Nuevas Aventuras
        </a>
    </div>
    
    <!-- Grid de cursos -->
    <!-- Grid de cursos -->
    <?php if($total_cursos > 0): ?>
        <div class="courses-grid fade-in-up" style="animation-delay: 0.3s">
            <?php 
            $course_colors = [
                'var(--primary)', 
                'var(--accent)', 
                'var(--secondary)', 
                'var(--purple)', 
                'var(--pink)',
                '#FF9F1C',
                '#2EC4B6',
                '#E71D36'
            ];
            $color_index = 0;
            
            foreach($cursos_data as $curso):
                // Asignar color de forma cíclica
                $course_color = $course_colors[$color_index % count($course_colors)];
                $color_index++;
                
                // Determinar el color del nivel
                $level_colors = [
                    'Básico' => 'rgba(44, 186, 236, 0.8)',
                    'Intermedio' => 'rgba(131, 191, 70, 0.8)',
                    'Avanzado' => 'rgba(240, 174, 42, 0.8)'
                ];
                $level_color = isset($level_colors[$curso['nivel']]) ? $level_colors[$curso['nivel']] : $level_colors['Básico'];
            ?>
            <div class="course-card">
                <!-- Encabezado del curso con color dinámico -->
                <div class="course-header" style="background: <?php echo $course_color; ?>;">
                    <span class="course-level" style="background: <?php echo $level_color; ?>;">
                        <i class="fas fa-chart-line me-1"></i> <?php echo htmlspecialchars($curso['nivel']); ?>
                    </span>
                    <h3 class="course-title"><?php echo htmlspecialchars($curso['nombre']); ?></h3>
                    <div style="display: flex; align-items: center; gap: 10px; margin-top: 10px;">
                        <div style="display: flex; align-items: center; gap: 5px; color: rgba(255,255,255,0.9);">
                            <i class="fas fa-user-graduate"></i>
                            <span>Tutor: <?php echo htmlspecialchars($curso['tutor_nombre']); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Cuerpo del curso -->
                <div class="course-body">
                    <p class="course-description">
                        <?php echo htmlspecialchars($curso['descripcion'] ?? '¡Embárcate en esta aventura de aprendizaje!'); ?>
                    </p>
                    
                    <!-- Progreso -->
                    <div class="course-progress-container">
                        <div class="progress-label">
                            <span>Tu progreso en la aventura:</span>
                            <span><?php echo $curso['progreso']; ?>%</span>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill" style="width: <?php echo $curso['progreso']; ?>%; background: linear-gradient(90deg, <?php echo $course_color; ?>, <?php echo adjustBrightness($course_color, 30); ?>);"></div>
                        </div>
                    </div>
                    
                    <!-- Metadatos -->
                    <div class="course-meta">
                        <div class="meta-item">
                            <i class="fas fa-clock"></i>
                            <span><?php echo $curso['duracion_horas']; ?> horas</span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-tasks"></i>
                            <span><?php echo $curso['total_actividades']; ?> misiones</span>
                        </div>
                    </div>
                    
                    <!-- Botones de acción -->
                    <div class="course-actions">
                        <a href="ver_curso.php?id=<?php echo $curso['id']; ?>" class="btn-course btn-primary-course">
                            <i class="fas fa-play-circle"></i> Continuar Aventura
                        </a>
                        <a href="mis_actividades.php?curso=<?php echo $curso['id']; ?>" class="btn-course btn-outline-course">
                            <i class="fas fa-list-check"></i> Ver Misiones
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <!-- Mensaje cuando no hay cursos -->
        <div class="no-courses fade-in-up" style="animation-delay: 0.3s">
            <div class="no-courses-icon">
                <i class="fas fa-compass"></i>
            </div>
            <h3 style="color: var(--primary); margin-bottom: 15px; font-family: 'Fredoka One', cursive;">
                ¡No tienes aventuras activas!
            </h3>
            <p style="color: #666; margin-bottom: 30px; max-width: 500px; margin-left: auto; margin-right: auto;">
                Parece que aún no te has inscrito en ninguna aventura de aprendizaje. ¡Explora nuestro catálogo y encuentra emocionantes cursos para comenzar tu viaje!
            </p>
            <a href="catalogo.php" class="btn-main-action">
                <i class="fas fa-search"></i> Explorar Aventuras Disponibles
            </a>
        </div>
    <?php endif; ?>
    
    <!-- Actividades recientes -->
    <?php if(mysqli_num_rows($res_actividades) > 0): ?>
    <div class="recent-activities fade-in-up" style="animation-delay: 0.4s">
        <h3 class="activities-title">
            <i class="fas fa-history"></i> Tus Misiones Recientes
        </h3>
        
        <div class="activity-list">
            <?php 
            mysqli_data_seek($res_actividades, 0);
            while($actividad = mysqli_fetch_assoc($res_actividades)): 
                $status_text = ($actividad['estado'] == 'calificado') ? 'Calificado' : 'Pendiente';
                $status_class = ($actividad['estado'] == 'calificado') ? 'status-completed' : 'status-pending';
            ?>
            <div class="activity-item">
                <div class="activity-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="activity-content">
                    <div class="activity-title"><?php echo htmlspecialchars($actividad['titulo']); ?></div>
                    <div class="activity-meta">
                        <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($actividad['fecha_entrega'])); ?></span>
                        <?php if($actividad['calificacion']): ?>
                        <span><i class="fas fa-star"></i> Calificación: <?php echo $actividad['calificacion']; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <span class="activity-status <?php echo $status_class; ?>">
                    <?php echo $status_text; ?>
                </span>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Función para ajustar brillo del color (se debe añadir al principio del PHP) -->
<?php
function adjustBrightness($hex, $steps) {
    $steps = max(-255, min(255, $steps));
    $hex = str_replace('#', '', $hex);
    
    if (strlen($hex) == 3) {
        $hex = str_repeat(substr($hex,0,1), 2).str_repeat(substr($hex,1,1), 2).str_repeat(substr($hex,2,1), 2);
    }
    
    $r = hexdec(substr($hex,0,2));
    $g = hexdec(substr($hex,2,2));
    $b = hexdec(substr($hex,4,2));
    
    $r = max(0, min(255, $r + $steps));
    $g = max(0, min(255, $g + $steps));
    $b = max(0, min(255, $b + $steps));
    
    $r_hex = str_pad(dechex($r), 2, '0', STR_PAD_LEFT);
    $g_hex = str_pad(dechex($g), 2, '0', STR_PAD_LEFT);
    $b_hex = str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
    
    return '#'.$r_hex.$g_hex.$b_hex;
}
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // Toggle del menú en móvil
    $(document).ready(function() {
        $('.menu-toggle').click(function() {
            $('.sidebar-kid').toggleClass('active');
        });
        
        // Animaciones al cargar
        $('.fade-in-up').each(function(index) {
            $(this).css('animation-delay', (index * 0.1) + 's');
        });
        
        // Efecto en el avatar
        $('#kidAvatar').hover(
            function() {
                $(this).css('transform', 'scale(1.1) rotate(5deg)');
            },
            function() {
                $(this).css('transform', 'scale(1) rotate(0deg)');
            }
        );
        
        // Animación para las barras de progreso
        $('.progress-bar-fill').each(function() {
            var width = $(this).css('width');
            $(this).css('width', '0');
            setTimeout(() => {
                $(this).css('width', width);
            }, 300);
        });
        
        // Cerrar menú al hacer clic en enlace en móvil
        if ($(window).width() < 992) {
            $('.nav-link').click(function() {
                $('.sidebar-kid').removeClass('active');
            });
        }
    });
</script>
</body>
</html>