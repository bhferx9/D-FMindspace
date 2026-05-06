<?php
include 'php/config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'tutor') {
    header("Location: index.php");
    exit();
}

$tutor_id = $_SESSION['user_id'];
$alumno_id = isset($_GET['alumno_id']) ? intval($_GET['alumno_id']) : 0;
$curso_filtro = isset($_GET['curso_id']) ? intval($_GET['curso_id']) : 0;

// Verificar que el alumno esté inscrito en un curso del tutor
$sql_verificar = "SELECT u.* 
                  FROM usuarios u
                  JOIN inscripciones i ON u.id = i.id_alumno
                  JOIN cursos c ON i.id_curso = c.id
                  WHERE u.id = '$alumno_id' 
                  AND u.tipo = 'alumno'
                  AND c.id_tutor = '$tutor_id'";
                  
if ($curso_filtro > 0) {
    $sql_verificar .= " AND c.id = '$curso_filtro'";
}

$result_verificar = mysqli_query($conn, $sql_verificar);

if (mysqli_num_rows($result_verificar) == 0) {
    header("Location: reporte_alumnos.php");
    exit();
}

$alumno = mysqli_fetch_assoc($result_verificar);

// Obtener cursos del alumno donde el tutor es el profesor
$sql_cursos_alumno = "SELECT c.id, c.nombre, i.fecha_inscripcion, i.progreso, i.estado
                      FROM cursos c
                      JOIN inscripciones i ON c.id = i.id_curso
                      WHERE i.id_alumno = '$alumno_id'
                      AND c.id_tutor = '$tutor_id'
                      ORDER BY c.nombre ASC";
$res_cursos_alumno = mysqli_query($conn, $sql_cursos_alumno);

// Obtener estadísticas del alumno
$sql_estadisticas = "SELECT 
                    COUNT(DISTINCT i.id_curso) as total_cursos,
                    AVG(p.porcentaje) as promedio_general,
                    SUM(CASE WHEN i.estado = 'activo' THEN 1 ELSE 0 END) as cursos_activos,
                    SUM(CASE WHEN i.estado = 'completado' THEN 1 ELSE 0 END) as cursos_completados
                    FROM inscripciones i
                    LEFT JOIN progreso p ON i.id_alumno = p.id_alumno AND i.id_curso = p.id_curso
                    WHERE i.id_alumno = '$alumno_id'
                    AND EXISTS (SELECT 1 FROM cursos c WHERE c.id = i.id_curso AND c.id_tutor = '$tutor_id')";
$res_estadisticas = mysqli_query($conn, $sql_estadisticas);
$estadisticas = mysqli_fetch_assoc($res_estadisticas);

// Si hay un curso filtrado, obtener actividades y entregas de ese curso específico
if ($curso_filtro > 0) {
    // Verificar que el alumno esté inscrito en ese curso
    $sql_check_curso = "SELECT 1 FROM inscripciones 
                       WHERE id_alumno = '$alumno_id' 
                       AND id_curso = '$curso_filtro'";
    $check_curso = mysqli_query($conn, $sql_check_curso);
    
    if (mysqli_num_rows($check_curso) == 0) {
        // Si no está inscrito, redirigir sin filtro de curso
        header("Location: detalle_alumno.php?alumno=$alumno_id");
        exit();
    }
    
    // Obtener actividades del curso filtrado
    $sql_actividades = "SELECT a.*, 
                       e.id as entrega_id, 
                       e.estado as estado_entrega,
                       e.fecha_entrega,
                       ev.calificacion,
                       ev.comentarios
                       FROM actividades a
                       LEFT JOIN entregas e ON a.id = e.id_actividad AND e.id_alumno = '$alumno_id'
                       LEFT JOIN evaluaciones ev ON e.id = ev.id_entrega
                       WHERE a.id_curso = '$curso_filtro'
                       ORDER BY a.fecha_limite ASC";
    
    // Obtener información del curso filtrado
    $sql_curso_info = "SELECT c.* FROM cursos c WHERE c.id = '$curso_filtro'";
    $res_curso_info = mysqli_query($conn, $sql_curso_info);
    $curso_info = mysqli_fetch_assoc($res_curso_info);
    
    // Obtener estadísticas del curso específico
    $sql_stats_curso = "SELECT 
                       COUNT(DISTINCT a.id) as total_actividades,
                       SUM(CASE WHEN e.id IS NOT NULL THEN 1 ELSE 0 END) as entregas_realizadas,
                       SUM(CASE WHEN e.estado = 'calificado' THEN 1 ELSE 0 END) as entregas_calificadas,
                       AVG(CASE WHEN e.estado = 'calificado' THEN ev.calificacion ELSE NULL END) as promedio_curso
                       FROM actividades a
                       LEFT JOIN entregas e ON a.id = e.id_actividad AND e.id_alumno = '$alumno_id'
                       LEFT JOIN evaluaciones ev ON e.id = ev.id_entrega
                       WHERE a.id_curso = '$curso_filtro'";
    $res_stats_curso = mysqli_query($conn, $sql_stats_curso);
    $stats_curso = mysqli_fetch_assoc($res_stats_curso);
} else {
    // Obtener todas las actividades de todos los cursos del tutor
    $sql_actividades = "SELECT a.*, 
                       c.nombre as curso_nombre,
                       e.id as entrega_id, 
                       e.estado as estado_entrega,
                       e.fecha_entrega,
                       ev.calificacion,
                       ev.comentarios
                       FROM actividades a
                       JOIN cursos c ON a.id_curso = c.id
                       LEFT JOIN entregas e ON a.id = e.id_actividad AND e.id_alumno = '$alumno_id'
                       LEFT JOIN evaluaciones ev ON e.id = ev.id_entrega
                       WHERE c.id_tutor = '$tutor_id'
                       AND EXISTS (SELECT 1 FROM inscripciones i WHERE i.id_alumno = '$alumno_id' AND i.id_curso = c.id)
                       ORDER BY c.nombre, a.fecha_limite ASC";
}

$res_actividades = mysqli_query($conn, $sql_actividades);

// Función para obtener color según el estado
function getEstadoColor($estado) {
    $colores = [
        'pendiente' => 'warning',
        'calificado' => 'success',
        'entregado' => 'info',
        'en_revision' => 'primary',
        'no_entregado' => 'danger'
    ];
    return $colores[$estado] ?? 'secondary';
}

// Función para obtener icono según el tipo de actividad
function getActividadIcono($tipo) {
    $iconos = [
        'Quiz' => 'fas fa-question-circle',
        'Video' => 'fas fa-video',
        'Tarea' => 'fas fa-file-upload',
        'Juego' => 'fas fa-gamepad',
        'Lectura' => 'fas fa-book',
        'Examen' => 'fas fa-file-alt'
    ];
    return $iconos[$tipo] ?? 'fas fa-tasks';
}

// Función para obtener color de calificación
function getCalificacionColor($calificacion) {
    if ($calificacion >= 9) return 'success';
    if ($calificacion >= 7) return 'info';
    if ($calificacion >= 6) return 'warning';
    return 'danger';
}

// Función para obtener progreso en texto
function getProgresoTexto($porcentaje) {
    if ($porcentaje >= 90) return 'Excelente';
    if ($porcentaje >= 70) return 'Bueno';
    if ($porcentaje >= 50) return 'Regular';
    if ($porcentaje >= 30) return 'Necesita mejorar';
    return 'Muy bajo';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📊 Detalle del Alumno - D&F Mindspace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css">
    <style>
        :root {
            --primary: #2cbaec;
            --secondary: #f0ae2a;
            --accent: #83bf46;
            --danger: #ff6b8b;
            --light-bg: #f7fdfe;
            --card-shadow: 0 10px 30px rgba(44, 186, 236, 0.15);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #f0f9fd 0%, #e6f7fc 100%);
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        .detail-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.6s ease-out forwards;
        }
        
        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .header-section {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
        }
        
        .header-section h1 {
            font-family: 'Nunito', sans-serif;
            font-size: 3rem;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 15px;
            animation: gradientText 3s ease infinite;
            background-size: 200% 200%;
        }
        
        @keyframes gradientText {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .header-section p {
            color: #666;
            font-size: 1.2rem;
            max-width: 700px;
            margin: 0 auto;
            line-height: 1.6;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 800;
            margin-bottom: 40px;
            padding: 15px 30px;
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.1), rgba(44, 186, 236, 0.05));
            border-radius: 20px;
            border: 2px solid rgba(44, 186, 236, 0.2);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 8px 20px rgba(44, 186, 236, 0.1);
        }
        
        .back-link:hover {
            color: white;
            background: linear-gradient(135deg, var(--primary), #2ca5d4);
            border-color: var(--primary);
            transform: translateX(-10px) scale(1.05);
            box-shadow: 0 15px 30px rgba(44, 186, 236, 0.3);
        }
        
        .student-header {
            background: white;
            border-radius: 30px;
            padding: 40px;
            box-shadow: var(--card-shadow);
            margin-bottom: 40px;
            display: flex;
            align-items: center;
            gap: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .student-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
        }
        
        .student-avatar {
            width: 120px;
            height: 120px;
            border-radius: 25px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3.5rem;
            font-weight: bold;
            box-shadow: 0 12px 30px rgba(44, 186, 236, 0.3);
            flex-shrink: 0;
        }
        
        .student-info {
            flex: 1;
        }
        
        .student-name {
            font-size: 2.2rem;
            font-weight: 900;
            color: #222;
            margin-bottom: 10px;
        }
        
        .student-email {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .student-meta {
            display: flex;
            gap: 25px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .meta-label {
            color: #666;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .meta-value {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--primary);
        }
        
        .course-filter-section {
            background: white;
            border-radius: 25px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
        }
        
        .filter-title {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 12px 25px;
            border-radius: 15px;
            border: 2px solid rgba(44, 186, 236, 0.2);
            background: white;
            color: var(--primary);
            font-weight: 700;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        
        .filter-btn:hover, .filter-btn.active {
            background: linear-gradient(135deg, var(--primary), #2ca5d4);
            color: white;
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(44, 186, 236, 0.3);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: white;
            border-radius: 25px;
            padding: 30px;
            box-shadow: var(--card-shadow);
            border: 3px solid transparent;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            transform: scaleX(0);
            transition: transform 0.4s ease;
        }
        
        .stat-card:hover::before {
            transform: scaleX(1);
        }
        
        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(44, 186, 236, 0.3);
        }
        
        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            margin: 0 auto 20px;
            color: white;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        
        .stat-card:nth-child(1) .stat-icon {
            background: linear-gradient(135deg, var(--primary), #2ca5d4);
        }
        
        .stat-card:nth-child(2) .stat-icon {
            background: linear-gradient(135deg, var(--accent), #6aab39);
        }
        
        .stat-card:nth-child(3) .stat-icon {
            background: linear-gradient(135deg, var(--secondary), #f5c15d);
        }
        
        .stat-card:nth-child(4) .stat-icon {
            background: linear-gradient(135deg, var(--danger), #ff4757);
        }
        
        .stat-number {
            font-size: 2.8rem;
            font-weight: 900;
            margin: 10px 0;
            color: #222;
            line-height: 1;
        }
        
        .stat-label {
            color: #666;
            font-size: 1rem;
            font-weight: 600;
        }
        
        .activities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .activity-card {
            background: white;
            border-radius: 25px;
            padding: 30px;
            box-shadow: var(--card-shadow);
            border: 3px solid transparent;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            height: 100%;
            position: relative;
            overflow: hidden;
        }
        
        .activity-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            transform: scaleX(0);
            transition: transform 0.4s ease;
        }
        
        .activity-card:hover::before {
            transform: scaleX(1);
        }
        
        .activity-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(44, 186, 236, 0.3);
        }
        
        .course-badge {
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 700;
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.1), rgba(44, 186, 236, 0.05));
            color: var(--primary);
            border: 2px solid rgba(44, 186, 236, 0.2);
            margin-bottom: 20px;
            display: inline-block;
        }
        
        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .activity-title {
            font-size: 1.4rem;
            font-weight: 800;
            color: #222;
            margin-bottom: 10px;
            line-height: 1.4;
        }
        
        .activity-type {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            border-radius: 15px;
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.1), rgba(44, 186, 236, 0.05));
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .activity-description {
            color: #666;
            line-height: 1.6;
            margin-bottom: 25px;
            font-size: 0.95rem;
        }
        
        .delivery-info {
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.05), rgba(44, 186, 236, 0.02));
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
            border-left: 5px solid var(--primary);
        }
        
        .delivery-status {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .status-badge {
            padding: 8px 15px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .status-pendiente {
            background: linear-gradient(135deg, rgba(240, 174, 42, 0.15), rgba(240, 174, 42, 0.1));
            color: var(--secondary);
            border: 2px solid rgba(240, 174, 42, 0.3);
        }
        
        .status-calificado {
            background: linear-gradient(135deg, rgba(131, 191, 70, 0.15), rgba(131, 191, 70, 0.1));
            color: var(--accent);
            border: 2px solid rgba(131, 191, 70, 0.3);
        }
        
        .status-entregado {
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.15), rgba(44, 186, 236, 0.1));
            color: var(--primary);
            border: 2px solid rgba(44, 186, 236, 0.3);
        }
        
        .status-no_entregado {
            background: linear-gradient(135deg, rgba(255, 107, 139, 0.15), rgba(255, 107, 139, 0.1));
            color: var(--danger);
            border: 2px solid rgba(255, 107, 139, 0.3);
        }
        
        .grade-display {
            font-size: 2.5rem;
            font-weight: 900;
            text-align: center;
            margin: 15px 0;
        }
        
        .grade-excelente {
            color: var(--accent);
        }
        
        .grade-bueno {
            color: var(--primary);
        }
        
        .grade-regular {
            color: var(--secondary);
        }
        
        .grade-bajo {
            color: var(--danger);
        }
        
        .btn-ver-entrega {
            background: linear-gradient(90deg, var(--primary), #2ca5d4);
            border: none;
            border-radius: 15px;
            padding: 12px 25px;
            color: white;
            font-weight: 700;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            margin-top: 15px;
        }
        
        .btn-ver-entrega:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(44, 186, 236, 0.3);
            color: white;
        }
        
        .no-activities {
            text-align: center;
            padding: 80px 40px;
            background: white;
            border-radius: 30px;
            box-shadow: var(--card-shadow);
            grid-column: 1 / -1;
        }
        
        .no-activities-icon {
            font-size: 5rem;
            color: var(--primary);
            margin-bottom: 30px;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        
        .no-activities h3 {
            color: #222;
            font-weight: 800;
            margin-bottom: 15px;
            font-size: 2rem;
        }
        
        .no-activities p {
            color: #666;
            font-size: 1.2rem;
            max-width: 500px;
            margin: 0 auto 30px;
            line-height: 1.6;
        }
        
        .floating-particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }
        
        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: var(--primary);
            border-radius: 50%;
            opacity: 0.3;
            animation: floatParticle 20s infinite linear;
        }
        
        @keyframes floatParticle {
            0% { transform: translateY(100vh) translateX(0) rotate(0deg); }
            100% { transform: translateY(-100px) translateX(100px) rotate(360deg); }
        }
        
        .course-info-card {
            background: white;
            border-radius: 25px;
            padding: 30px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
            border-left: 8px solid var(--primary);
        }
        
        .course-title {
            font-size: 1.8rem;
            font-weight: 800;
            color: #222;
            margin-bottom: 10px;
        }
        
        .course-description {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .course-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .course-stat-item {
            text-align: center;
            padding: 15px;
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.05), rgba(44, 186, 236, 0.02));
            border-radius: 15px;
            border: 2px solid rgba(44, 186, 236, 0.1);
        }
        
        .course-stat-number {
            font-size: 2rem;
            font-weight: 900;
            color: var(--primary);
            margin-bottom: 5px;
        }
        
        .course-stat-label {
            color: #666;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .detail-container {
                padding: 20px 15px;
            }
            
            .header-section h1 {
                font-size: 2.2rem;
            }
            
            .student-header {
                flex-direction: column;
                text-align: center;
                padding: 30px 20px;
            }
            
            .student-meta {
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .activities-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .activity-card {
                padding: 20px;
            }
            
            .filter-buttons {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Partículas flotantes -->
    <div class="floating-particles" id="particles"></div>
    
    <div class="detail-container">
        <!-- Botón para volver -->
        <a href="reporte_alumnos.php" class="back-link animate__animated animate__fadeInLeft">
            <i class="fas fa-arrow-left"></i> Volver al Reporte
        </a>
        
        <!-- Encabezado -->
        <div class="header-section">
            <h1>📊 Detalle del Alumno</h1>
            <p>Revisa el desempeño, entregas y progreso de este explorador en tus cursos.</p>
        </div>
        
        <!-- Información del alumno -->
        <div class="student-header animate__animated animate__fadeInUp">
            <div class="student-avatar">
                <?php echo strtoupper(substr($alumno['nombre'], 0, 1)); ?>
            </div>
            <div class="student-info">
                <h1 class="student-name"><?php echo htmlspecialchars($alumno['nombre']); ?></h1>
                <div class="student-email">
                    <i class="fas fa-envelope"></i>
                    <?php echo htmlspecialchars($alumno['email']); ?>
                </div>
                <div class="student-meta">
                    <div class="meta-item">
                        <span class="meta-label">Fecha de Registro</span>
                        <span class="meta-value"><?php echo date('d/m/Y', strtotime($alumno['fecha_registro'])); ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Cursos Inscritos</span>
                        <span class="meta-value"><?php echo $estadisticas['total_cursos'] ?? 0; ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Progreso General</span>
                        <span class="meta-value"><?php echo number_format($estadisticas['promedio_general'] ?? 0, 1); ?>%</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Estado</span>
                        <span class="meta-value" style="color: var(--accent);">
                            <i class="fas fa-circle" style="font-size: 0.8rem;"></i> Activo
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sección de Filtros por Curso -->
        <div class="course-filter-section animate__animated animate__fadeInUp">
            <div class="filter-title">
                <i class="fas fa-filter"></i>
                Filtrar por Curso
            </div>
            <div class="filter-buttons">
                <a href="detalle_alumno.php?alumno_id=<?php echo $alumno_id; ?>" 
                   class="filter-btn <?php echo $curso_filtro == 0 ? 'active' : ''; ?>">
                    <i class="fas fa-layer-group"></i>
                    Todos los Cursos
                </a>
                
                <?php 
                mysqli_data_seek($res_cursos_alumno, 0);
                while($curso = mysqli_fetch_assoc($res_cursos_alumno)): 
                    $es_activo = $curso_filtro == $curso['id'];
                    $color_progreso = $curso['progreso'] >= 70 ? 'var(--accent)' : ($curso['progreso'] >= 40 ? 'var(--primary)' : 'var(--danger)');
                ?>
                    <a href="detalle_alumno.php?alumno_id=<?php echo $alumno_id; ?>&curso_id=<?php echo $curso['id']; ?>" 
                       class="filter-btn <?php echo $es_activo ? 'active' : ''; ?>">
                        <i class="fas fa-book"></i>
                        <?php echo htmlspecialchars($curso['nombre']); ?>
                        <span class="badge" style="background: <?php echo $color_progreso; ?>">
                            <?php echo $curso['progreso']; ?>%
                        </span>
                    </a>
                <?php endwhile; ?>
            </div>
        </div>
        
        <!-- Información del curso filtrado (si hay filtro) -->
        <?php if($curso_filtro > 0 && isset($curso_info)): ?>
        <div class="course-info-card animate__animated animate__fadeInUp">
            <h2 class="course-title"><?php echo htmlspecialchars($curso_info['nombre']); ?></h2>
            <?php if(!empty($curso_info['descripcion'])): ?>
                <p class="course-description"><?php echo htmlspecialchars($curso_info['descripcion']); ?></p>
            <?php endif; ?>
            
            <div class="course-stats">
                <div class="course-stat-item">
                    <div class="course-stat-number"><?php echo $stats_curso['total_actividades'] ?? 0; ?></div>
                    <div class="course-stat-label">Total Actividades</div>
                </div>
                <div class="course-stat-item">
                    <div class="course-stat-number"><?php echo $stats_curso['entregas_realizadas'] ?? 0; ?></div>
                    <div class="course-stat-label">Entregas Realizadas</div>
                </div>
                <div class="course-stat-item">
                    <div class="course-stat-number"><?php echo $stats_curso['entregas_calificadas'] ?? 0; ?></div>
                    <div class="course-stat-label">Calificadas</div>
                </div>
                <div class="course-stat-item">
                    <div class="course-stat-number">
                        <?php echo isset($stats_curso['promedio_curso']) ? number_format($stats_curso['promedio_curso'], 1) : '0.0'; ?>
                    </div>
                    <div class="course-stat-label">Promedio del Curso</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Estadísticas generales -->
        <div class="stats-grid animate__animated animate__fadeInUp">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="stat-number"><?php echo $estadisticas['total_cursos'] ?? 0; ?></div>
                <div class="stat-label">Cursos Inscritos</div>
                <small class="text-muted">Con este tutor</small>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-number"><?php echo number_format($estadisticas['promedio_general'] ?? 0, 1); ?>%</div>
                <div class="stat-label">Progreso General</div>
                <small class="text-muted"><?php echo getProgresoTexto($estadisticas['promedio_general'] ?? 0); ?></small>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number"><?php echo $estadisticas['cursos_activos'] ?? 0; ?></div>
                <div class="stat-label">Cursos Activos</div>
                <small class="text-muted">En progreso</small>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="stat-number"><?php echo $estadisticas['cursos_completados'] ?? 0; ?></div>
                <div class="stat-label">Cursos Completados</div>
                <small class="text-muted">Finalizados con éxito</small>
            </div>
        </div>
        
        <!-- Actividades y entregas -->
        <h2 class="mb-4 animate__animated animate__fadeInUp">
            <i class="fas fa-tasks me-2"></i>
            <?php if($curso_filtro > 0): ?>
                Actividades del Curso
            <?php else: ?>
                Todas las Actividades
            <?php endif; ?>
        </h2>
        
        <?php if(mysqli_num_rows($res_actividades) > 0): ?>
            <div class="activities-grid">
                <?php while($act = mysqli_fetch_assoc($res_actividades)): 
                    $icono = getActividadIcono($act['tipo']);
                    $estado_color = getEstadoColor($act['estado_entrega'] ?? 'no_entregado');
                    $estado_texto = $act['estado_entrega'] ?? 'No entregado';
                    $calificacion_color = isset($act['calificacion']) ? getCalificacionColor($act['calificacion']) : '';
                    
                    // Verificar si la actividad está vencida
                    $fecha_limite = new DateTime($act['fecha_limite']);
                    $hoy = new DateTime();
                    $esta_vencida = $fecha_limite < $hoy && ($act['estado_entrega'] == null || $act['estado_entrega'] == 'pendiente');
                ?>
                    <div class="activity-card animate__animated animate__fadeInUp">
                        <?php if($curso_filtro == 0): ?>
                            <span class="course-badge">
                                <i class="fas fa-book me-2"></i><?php echo htmlspecialchars($act['curso_nombre'] ?? 'Curso'); ?>
                            </span>
                        <?php endif; ?>
                        
                        <div class="activity-header">
                            <div>
                                <h3 class="activity-title"><?php echo htmlspecialchars($act['titulo']); ?></h3>
                                <span class="activity-type">
                                    <i class="<?php echo str_replace('fas fa-', '', $icono); ?> me-2"></i>
                                    <?php echo htmlspecialchars($act['tipo']); ?>
                                </span>
                            </div>
                            <?php if($esta_vencida): ?>
                                <span class="status-badge status-no_entregado">
                                    <i class="fas fa-clock"></i> Vencida
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if(!empty($act['descripcion'])): ?>
                            <p class="activity-description"><?php echo htmlspecialchars(substr($act['descripcion'], 0, 150)); ?><?php echo strlen($act['descripcion']) > 150 ? '...' : ''; ?></p>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between text-muted mb-3">
                            <div>
                                <i class="fas fa-calendar-alt me-1"></i>
                                <?php echo date('d/m/Y', strtotime($act['fecha_limite'])); ?>
                            </div>
                            <div>
                                <i class="fas fa-trophy me-1"></i>
                                <?php echo $act['puntos']; ?> XP
                            </div>
                        </div>
                        
                        <!-- Información de entrega -->
                        <div class="delivery-info">
                            <div class="delivery-status">
                                <span class="status-badge status-<?php echo $estado_color; ?>">
                                    <i class="fas fa-<?php echo $estado_color == 'success' ? 'check-circle' : ($estado_color == 'warning' ? 'clock' : ($estado_color == 'danger' ? 'times-circle' : 'paper-plane')); ?>"></i>
                                    <?php echo ucfirst($estado_texto); ?>
                                </span>
                                
                                <?php if($act['fecha_entrega']): ?>
                                    <span class="ms-auto">
                                        <i class="fas fa-paper-plane me-1"></i>
                                        <?php echo date('d/m/Y H:i', strtotime($act['fecha_entrega'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if(isset($act['calificacion'])): ?>
                                <div class="grade-display grade-<?php echo $calificacion_color; ?>">
                                    <?php echo number_format($act['calificacion'], 1); ?>
                                </div>
                                
                                <?php if(!empty($act['comentarios'])): ?>
                                    <div class="mt-3">
                                        <strong><i class="fas fa-comment me-2"></i>Comentarios:</strong>
                                        <p class="mb-0 mt-1"><?php echo htmlspecialchars($act['comentarios']); ?></p>
                                    </div>
                                <?php endif; ?>
                            <?php elseif($act['estado_entrega'] == 'pendiente'): ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                                    <p class="mb-0">Esperando calificación</p>
                                </div>
                            <?php elseif($act['estado_entrega'] == null): ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-times-circle fa-2x text-danger mb-2"></i>
                                    <p class="mb-0">No ha entregado esta actividad</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if($act['entrega_id']): ?>
                            <a href="ver_entrega.php?id=<?php echo $act['entrega_id']; ?>" 
                               class="btn-ver-entrega">
                                <i class="fas fa-eye"></i> Ver Entrega Completa
                            </a>
                        <?php elseif($esta_vencida): ?>
                            <button class="btn-ver-entrega" style="background: linear-gradient(90deg, var(--danger), #ff4757);" disabled>
                                <i class="fas fa-clock"></i> Actividad Vencida
                            </button>
                        <?php else: ?>
                            <button class="btn-ver-entrega" style="background: linear-gradient(90deg, #999, #777);" disabled>
                                <i class="fas fa-ban"></i> Sin Entrega
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="no-activities animate__animated animate__fadeIn">
                <div class="no-activities-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <h3>
                    <?php if($curso_filtro > 0): ?>
                        ¡No hay actividades en este curso!
                    <?php else: ?>
                        ¡No hay actividades disponibles!
                    <?php endif; ?>
                </h3>
                <p>
                    <?php if($curso_filtro > 0): ?>
                        Este curso aún no tiene actividades asignadas. Crea algunas misiones para este explorador.
                    <?php else: ?>
                        Este alumno no tiene actividades asignadas en tus cursos.
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Crear partículas flotantes
        function crearParticulas() {
            const container = document.getElementById('particles');
            for (let i = 0; i < 25; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.top = Math.random() * 100 + '%';
                particle.style.background = Math.random() > 0.5 ? 'var(--primary)' : 'var(--secondary)';
                particle.style.width = Math.random() * 4 + 2 + 'px';
                particle.style.height = particle.style.width;
                particle.style.opacity = Math.random() * 0.3 + 0.1;
                particle.style.animationDelay = Math.random() * 20 + 's';
                particle.style.animationDuration = Math.random() * 10 + 15 + 's';
                container.appendChild(particle);
            }
        }
        
        // Inicializar animaciones
        document.addEventListener('DOMContentLoaded', function() {
            crearParticulas();
            
            // Agregar animaciones a las tarjetas
            const cards = document.querySelectorAll('.stat-card, .activity-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
            
            // Configurar tooltips para botones deshabilitados
            const disabledButtons = document.querySelectorAll('button:disabled');
            disabledButtons.forEach(button => {
                if (button.textContent.includes('Vencida')) {
                    button.title = 'Esta actividad ya pasó su fecha límite';
                } else if (button.textContent.includes('Sin Entrega')) {
                    button.title = 'El alumno no ha entregado esta actividad';
                }
            });
        });
        
        // Función para exportar reporte (opcional)
        function exportarReporte() {
            const nombreAlumno = document.querySelector('.student-name').textContent;
            const fecha = new Date().toLocaleDateString('es-MX');
            
            // Crear contenido del reporte
            let contenido = `Reporte del Alumno: ${nombreAlumno}\n`;
            contenido += `Fecha: ${fecha}\n\n`;
            contenido += `Cursos Inscritos: ${document.querySelector('.stat-card:nth-child(1) .stat-number').textContent}\n`;
            contenido += `Progreso General: ${document.querySelector('.stat-card:nth-child(2) .stat-number').textContent}\n`;
            contenido += `Cursos Activos: ${document.querySelector('.stat-card:nth-child(3) .stat-number').textContent}\n`;
            contenido += `Cursos Completados: ${document.querySelector('.stat-card:nth-child(4) .stat-number').textContent}\n\n`;
            
            // Agregar actividades
            contenido += "ACTIVIDADES:\n";
            document.querySelectorAll('.activity-card').forEach((card, index) => {
                const titulo = card.querySelector('.activity-title').textContent;
                const estado = card.querySelector('.status-badge').textContent.trim();
                const fechaLimite = card.querySelector('.fa-calendar-alt').parentElement.textContent.trim();
                const puntos = card.querySelector('.fa-trophy').parentElement.textContent.trim();
                
                contenido += `${index + 1}. ${titulo}\n`;
                contenido += `   Estado: ${estado}\n`;
                contenido += `   Fecha límite: ${fechaLimite}\n`;
                contenido += `   Puntos: ${puntos}\n\n`;
            });
            
            // Descargar archivo
            const blob = new Blob([contenido], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `reporte_${nombreAlumno.replace(/\s+/g, '_')}_${fecha.replace(/\//g, '-')}.txt`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>