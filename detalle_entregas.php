<?php
include 'php/config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'tutor') {
    header("Location: index.php");
    exit();
}

$tutor_id = $_SESSION['user_id'];
$alumno_id = isset($_GET['alumno_id']) ? intval($_GET['alumno_id']) : 0;
$curso_id = isset($_GET['curso_id']) ? intval($_GET['curso_id']) : 0;

// Verificar que el alumno esté inscrito en un curso del tutor
$sql_verificar = "SELECT u.*, c.nombre as curso_nombre
                  FROM usuarios u
                  JOIN inscripciones i ON u.id = i.id_alumno
                  JOIN cursos c ON i.id_curso = c.id
                  WHERE u.id = '$alumno_id' 
                  AND u.tipo = 'alumno'
                  AND c.id_tutor = '$tutor_id'";
                  
if ($curso_id > 0) {
    $sql_verificar .= " AND c.id = '$curso_id'";
    $sql_verificar .= " LIMIT 1";
}

$result_verificar = mysqli_query($conn, $sql_verificar);

if (mysqli_num_rows($result_verificar) == 0) {
    header("Location: reporte_alumnos.php");
    exit();
}

$alumno_info = mysqli_fetch_assoc($result_verificar);

// Obtener información del curso si se especifica
$curso_nombre = $alumno_info['curso_nombre'];
if ($curso_id > 0) {
    $sql_curso = "SELECT * FROM cursos WHERE id = '$curso_id'";
    $res_curso = mysqli_query($conn, $sql_curso);
    if ($res_curso && mysqli_num_rows($res_curso) > 0) {
        $curso_data = mysqli_fetch_assoc($res_curso);
        $curso_nombre = $curso_data['nombre'];
    }
}

// Obtener todas las entregas del alumno
$sql_entregas = "SELECT 
                    e.*,
                    a.titulo as actividad_titulo,
                    a.descripcion as actividad_descripcion,
                    a.tipo as actividad_tipo,
                    a.dificultad,
                    a.fecha_limite,
                    a.puntos as puntos_actividad,
                    c.nombre as curso_nombre,
                    c.id as curso_id,
                    ev.calificacion,
                    ev.comentarios as comentarios_tutor,
                    ev.fecha_evaluacion,
                    DATEDIFF(a.fecha_limite, CURDATE()) as dias_restantes
                FROM entregas e
                JOIN actividades a ON e.id_actividad = a.id
                JOIN cursos c ON a.id_curso = c.id
                LEFT JOIN evaluaciones ev ON e.id = ev.id_entrega
                WHERE e.id_alumno = '$alumno_id'
                AND c.id_tutor = '$tutor_id'";
                
if ($curso_id > 0) {
    $sql_entregas .= " AND c.id = '$curso_id'";
}

$sql_entregas .= " ORDER BY e.fecha_entrega DESC";
$res_entregas = mysqli_query($conn, $sql_entregas);

// Obtener estadísticas
$sql_stats = "SELECT 
                COUNT(*) as total_entregas,
                SUM(CASE WHEN e.estado = 'calificado' THEN 1 ELSE 0 END) as calificadas,
                SUM(CASE WHEN e.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN e.estado = 'entregado' THEN 1 ELSE 0 END) as entregadas,
                AVG(ev.calificacion) as promedio_calificaciones,
                MIN(a.fecha_limite) as proxima_fecha_limite,
                MAX(e.fecha_entrega) as ultima_entrega
              FROM entregas e
              JOIN actividades a ON e.id_actividad = a.id
              JOIN cursos c ON a.id_curso = c.id
              LEFT JOIN evaluaciones ev ON e.id = ev.id_entrega
              WHERE e.id_alumno = '$alumno_id'
              AND c.id_tutor = '$tutor_id'";
              
if ($curso_id > 0) {
    $sql_stats .= " AND c.id = '$curso_id'";
}

$res_stats = mysqli_query($conn, $sql_stats);
$stats = mysqli_fetch_assoc($res_stats);

// Función para obtener color según el estado
function getEstadoColor($estado) {
    $colores = [
        'pendiente' => 'warning',
        'calificado' => 'success',
        'entregado' => 'info',
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
        'Examen' => 'fas fa-file-alt',
        'Proyecto' => 'fas fa-project-diagram',
        'Foro' => 'fas fa-comments'
    ];
    return $iconos[$tipo] ?? 'fas fa-tasks';
}

// Función para obtener color de calificación
function getCalificacionColor($calificacion) {
    if ($calificacion === null) return 'secondary';
    if ($calificacion >= 9) return 'success';
    if ($calificacion >= 7) return 'info';
    if ($calificacion >= 6) return 'warning';
    return 'danger';
}

// Función para obtener texto de calificación
function getCalificacionTexto($calificacion) {
    if ($calificacion === null) return 'Sin calificar';
    if ($calificacion >= 9) return 'Excelente';
    if ($calificacion >= 7) return 'Bueno';
    if ($calificacion >= 6) return 'Suficiente';
    return 'Necesita mejorar';
}

// Función para obtener color de dificultad
function getDificultadColor($dificultad) {
    $colores = [
        'Fácil' => 'success',
        'Normal' => 'info',
        'Difícil' => 'warning',
        'Muy Difícil' => 'danger'
    ];
    return $colores[$dificultad] ?? 'secondary';
}

// Función para formatear fecha
function formatFecha($fecha) {
    if (!$fecha) return 'No especificada';
    $fecha_obj = new DateTime($fecha);
    return $fecha_obj->format('d/m/Y');
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📚 Detalle de Tareas - D&F Mindspace</title>
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
        }
        
        .container-custom {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
            opacity: 0;
            animation: fadeIn 0.6s ease-out forwards;
        }
        
        @keyframes fadeIn {
            to { opacity: 1; }
        }
        
        .header-section {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
        }
        
        .header-section h1 {
            font-family: 'Nunito', sans-serif;
            font-size: 2.8rem;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 15px;
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
            transition: all 0.3s ease;
            box-shadow: 0 8px 20px rgba(44, 186, 236, 0.1);
        }
        
        .back-link:hover {
            color: white;
            background: linear-gradient(135deg, var(--primary), #2ca5d4);
            border-color: var(--primary);
            transform: translateX(-5px);
            box-shadow: 0 15px 30px rgba(44, 186, 236, 0.3);
        }
        
        .student-card {
            background: white;
            border-radius: 25px;
            padding: 30px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .student-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
        }
        
        .student-avatar {
            width: 100px;
            height: 100px;
            border-radius: 20px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: bold;
            box-shadow: 0 12px 30px rgba(44, 186, 236, 0.3);
            flex-shrink: 0;
        }
        
        .student-details {
            flex: 1;
        }
        
        .student-name {
            font-size: 2rem;
            font-weight: 900;
            color: #222;
            margin-bottom: 5px;
        }
        
        .student-email {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .course-info {
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.05), rgba(44, 186, 236, 0.02));
            border-radius: 15px;
            padding: 20px;
            margin-top: 15px;
            border-left: 5px solid var(--primary);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-item {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid rgba(44, 186, 236, 0.1);
        }
        
        .stat-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(44, 186, 236, 0.2);
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
        
        .stat-icon.pendientes {
            background: linear-gradient(135deg, var(--secondary), #f5c15d);
        }
        
        .stat-icon.calificadas {
            background: linear-gradient(135deg, var(--accent), #6aab39);
        }
        
        .stat-icon.total {
            background: linear-gradient(135deg, var(--primary), #2ca5d4);
        }
        
        .stat-icon.promedio {
            background: linear-gradient(135deg, #9c88ff, #8c7ae6);
        }
        
        .stat-number {
            font-size: 2.2rem;
            font-weight: 900;
            color: #222;
            margin: 5px 0;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.95rem;
            font-weight: 600;
        }
        
        .tasks-container {
            background: white;
            border-radius: 25px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            margin-top: 30px;
        }
        
        .tasks-header {
            background: linear-gradient(90deg, rgba(44, 186, 236, 0.9), rgba(131, 191, 70, 0.9));
            color: white;
            padding: 25px;
            position: relative;
            overflow: hidden;
        }
        
        .tasks-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path d="M0,0 L100,0 L100,100 Z" fill="rgba(255,255,255,0.1)"/></svg>');
            background-size: cover;
        }
        
        .task-list {
            padding: 0;
        }
        
        .task-item {
            padding: 30px;
            border-bottom: 2px solid rgba(44, 186, 236, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .task-item:last-child {
            border-bottom: none;
        }
        
        .task-item:hover {
            background: rgba(44, 186, 236, 0.03);
            transform: translateX(5px);
        }
        
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .task-title {
            font-size: 1.4rem;
            font-weight: 800;
            color: #222;
            margin-bottom: 5px;
        }
        
        .task-course {
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
        
        .task-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .meta-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 15px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 700;
        }
        
        .badge-type {
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.15), rgba(44, 186, 236, 0.1));
            color: var(--primary);
            border: 2px solid rgba(44, 186, 236, 0.2);
        }
        
        .badge-dificultad {
            background: linear-gradient(135deg, rgba(240, 174, 42, 0.15), rgba(240, 174, 42, 0.1));
            color: var(--secondary);
            border: 2px solid rgba(240, 174, 42, 0.2);
        }
        
        .badge-puntos {
            background: linear-gradient(135deg, rgba(131, 191, 70, 0.15), rgba(131, 191, 70, 0.1));
            color: var(--accent);
            border: 2px solid rgba(131, 191, 70, 0.2);
        }
        
        .task-status {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .status-badge {
            padding: 10px 20px;
            border-radius: 15px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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
        
        .fecha-limite {
            color: #666;
            font-size: 0.95rem;
        }
        
        .fecha-limite.vencida {
            color: var(--danger);
            font-weight: 600;
        }
        
        .grade-section {
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.05), rgba(44, 186, 236, 0.02));
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
            border-left: 5px solid var(--primary);
        }
        
        .grade-display {
            font-size: 3rem;
            font-weight: 900;
            text-align: center;
            margin: 10px 0;
        }
        
        .grade-comments {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid rgba(44, 186, 236, 0.1);
        }
        
        .delivery-content {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
            border: 2px solid rgba(44, 186, 236, 0.1);
        }
        
        .delivery-content h5 {
            color: var(--primary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .file-download {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 20px;
            border-radius: 10px;
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.1), rgba(44, 186, 236, 0.05));
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            margin-top: 15px;
            transition: all 0.3s ease;
        }
        
        .file-download:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(44, 186, 236, 0.3);
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 12px 25px;
            border-radius: 15px;
            font-weight: 700;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border: none;
            cursor: pointer;
        }
        
        .btn-calificar {
            background: linear-gradient(90deg, var(--accent), #6aab39);
            color: white;
        }
        
        .btn-calificar:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(131, 191, 70, 0.3);
        }
        
        .btn-ver {
            background: linear-gradient(90deg, var(--primary), #2ca5d4);
            color: white;
        }
        
        .btn-ver:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(44, 186, 236, 0.3);
        }
        
        .empty-state {
            text-align: center;
            padding: 80px 40px;
        }
        
        .empty-state-icon {
            font-size: 5rem;
            color: rgba(44, 186, 236, 0.3);
            margin-bottom: 30px;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        
        .empty-state h3 {
            color: #222;
            font-weight: 800;
            margin-bottom: 15px;
            font-size: 2rem;
        }
        
        .empty-state p {
            color: #666;
            font-size: 1.2rem;
            max-width: 500px;
            margin: 0 auto 30px;
            line-height: 1.6;
        }
        
        .filter-section {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
        }
        
        .filter-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .btn-filter {
            padding: 10px 20px;
            border-radius: 12px;
            border: 2px solid rgba(44, 186, 236, 0.2);
            background: white;
            color: var(--primary);
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .btn-filter:hover, .btn-filter.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        @media (max-width: 768px) {
            .container-custom {
                padding: 20px 15px;
            }
            
            .header-section h1 {
                font-size: 2.2rem;
            }
            
            .student-card {
                flex-direction: column;
                text-align: center;
                padding: 25px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .task-item {
                padding: 20px;
            }
            
            .task-header {
                flex-direction: column;
                gap: 15px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-action {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container-custom">
        <!-- Botón para volver -->
        <a href="reporte_alumnos.php" class="back-link animate__animated animate__fadeInLeft">
            <i class="fas fa-arrow-left"></i> Volver al Reporte
        </a>
        
        <!-- Encabezado -->
        <div class="header-section animate__animated animate__fadeInUp">
            <h1>📚 Detalle de Tareas</h1>
            <p>Revisa todas las entregas y calificaciones de este explorador en tus cursos.</p>
        </div>
        
        <!-- Información del alumno -->
        <div class="student-card animate__animated animate__fadeInUp">
            <div class="student-avatar">
                <?php 
                $nombres = explode(' ', $alumno_info['nombre']);
                $iniciales = '';
                foreach ($nombres as $nombre) {
                    $iniciales .= strtoupper(substr($nombre, 0, 1));
                    if (strlen($iniciales) >= 2) break;
                }
                echo $iniciales ?: '??';
                ?>
            </div>
            <div class="student-details">
                <h1 class="student-name"><?php echo htmlspecialchars($alumno_info['nombre']); ?></h1>
                <div class="student-email">
                    <i class="fas fa-envelope"></i>
                    <?php echo htmlspecialchars($alumno_info['email']); ?>
                </div>
                <div class="course-info">
                    <h5><i class="fas fa-book me-2"></i>Curso Actual</h5>
                    <p class="mb-0"><?php echo htmlspecialchars($curso_nombre); ?></p>
                    <?php if ($curso_id > 0): ?>
                        <a href="detalle_alumno.php?alumno_id=<?php echo $alumno_id; ?>&curso_id=<?php echo $curso_id; ?>" 
                           class="btn btn-sm btn-primary mt-2">
                            <i class="fas fa-eye me-2"></i>Ver perfil completo
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Filtros de estado -->
        <div class="filter-section animate__animated animate__fadeInUp">
            <h5 class="mb-3"><i class="fas fa-filter me-2"></i>Filtrar por Estado</h5>
            <div class="filter-buttons">
                <a href="detalle_entregas.php?alumno_id=<?php echo $alumno_id; ?>&curso_id=<?php echo $curso_id; ?>" 
                   class="btn-filter <?php echo !isset($_GET['estado']) ? 'active' : ''; ?>">
                    <i class="fas fa-layer-group me-2"></i>Todas las Tareas
                </a>
                <a href="detalle_entregas.php?alumno_id=<?php echo $alumno_id; ?>&curso_id=<?php echo $curso_id; ?>&estado=pendiente" 
                   class="btn-filter <?php echo (isset($_GET['estado']) && $_GET['estado'] == 'pendiente') ? 'active' : ''; ?>">
                    <i class="fas fa-clock me-2"></i>Pendientes
                </a>
                <a href="detalle_entregas.php?alumno_id=<?php echo $alumno_id; ?>&curso_id=<?php echo $curso_id; ?>&estado=calificado" 
                   class="btn-filter <?php echo (isset($_GET['estado']) && $_GET['estado'] == 'calificado') ? 'active' : ''; ?>">
                    <i class="fas fa-star me-2"></i>Calificadas
                </a>
                <a href="detalle_entregas.php?alumno_id=<?php echo $alumno_id; ?>&curso_id=<?php echo $curso_id; ?>&estado=entregado" 
                   class="btn-filter <?php echo (isset($_GET['estado']) && $_GET['estado'] == 'entregado') ? 'active' : ''; ?>">
                    <i class="fas fa-paper-plane me-2"></i>Entregadas
                </a>
            </div>
        </div>
        
        <!-- Estadísticas -->
        <div class="stats-grid animate__animated animate__fadeInUp">
            <div class="stat-item">
                <div class="stat-icon pendientes">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?php echo $stats['pendientes'] ?? 0; ?></div>
                <div class="stat-label">Tareas Pendientes</div>
            </div>
            
            <div class="stat-item">
                <div class="stat-icon calificadas">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-number"><?php echo $stats['calificadas'] ?? 0; ?></div>
                <div class="stat-label">Tareas Calificadas</div>
            </div>
            
            <div class="stat-item">
                <div class="stat-icon total">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total_entregas'] ?? 0; ?></div>
                <div class="stat-label">Total de Entregas</div>
            </div>
            
            <div class="stat-item">
                <div class="stat-icon promedio">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-number">
                    <?php echo $stats['promedio_calificaciones'] ? number_format($stats['promedio_calificaciones'], 1) : '0.0'; ?>
                </div>
                <div class="stat-label">Promedio General</div>
            </div>
        </div>
        
        <!-- Lista de tareas -->
        <div class="tasks-container animate__animated animate__fadeInUp">
            <div class="tasks-header">
                <h4 class="mb-0"><i class="fas fa-list-ul me-2"></i> Historial de Entregas</h4>
                <p class="mb-0 opacity-75">
                    <?php 
                    $total_tareas = mysqli_num_rows($res_entregas);
                    $filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
                    
                    $texto_estado = '';
                    if ($filtro_estado == 'pendiente') $texto_estado = 'pendientes';
                    elseif ($filtro_estado == 'calificado') $texto_estado = 'calificadas';
                    elseif ($filtro_estado == 'entregado') $texto_estado = 'entregadas';
                    
                    echo $total_tareas . ' tarea' . ($total_tareas != 1 ? 's' : '') . ' ' . $texto_estado . ' encontrada' . ($total_tareas != 1 ? 's' : '');
                    ?>
                </p>
            </div>
            
            <div class="task-list">
                <?php if(mysqli_num_rows($res_entregas) > 0): ?>
                    <?php while($entrega = mysqli_fetch_assoc($res_entregas)): 
                        // Aplicar filtro de estado si existe
                        $estado_filtro = isset($_GET['estado']) ? $_GET['estado'] : '';
                        if ($estado_filtro && $entrega['estado'] != $estado_filtro) continue;
                        
                        $icono = getActividadIcono($entrega['actividad_tipo']);
                        $estado_color = getEstadoColor($entrega['estado']);
                        $calificacion_color = getCalificacionColor($entrega['calificacion']);
                        $dificultad_color = getDificultadColor($entrega['dificultad']);
                        $calificacion_texto = getCalificacionTexto($entrega['calificacion']);
                        
                        // Verificar si está vencida
                        $hoy = new DateTime();
                        $fecha_limite = new DateTime($entrega['fecha_limite']);
                        $esta_vencida = $fecha_limite < $hoy && $entrega['estado'] == 'pendiente';
                        $fecha_limite_class = $esta_vencida ? 'vencida' : '';
                    ?>
                        <div class="task-item">
                            <div class="task-header">
                                <div>
                                    <h3 class="task-title"><?php echo htmlspecialchars($entrega['actividad_titulo']); ?></h3>
                                    <span class="task-course">
                                        <i class="fas fa-book"></i>
                                        <?php echo htmlspecialchars($entrega['curso_nombre']); ?>
                                    </span>
                                </div>
                                <div class="text-end">
                                    <div class="fecha-limite <?php echo $fecha_limite_class; ?>">
                                        <i class="fas fa-calendar-alt me-1"></i>
                                        Límite: <?php echo formatFecha($entrega['fecha_limite']); ?>
                                    </div>
                                    <?php if($esta_vencida): ?>
                                        <small class="text-danger">
                                            <i class="fas fa-exclamation-circle me-1"></i>Vencida
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="task-meta">
                                <span class="meta-badge badge-type">
                                    <i class="<?php echo str_replace('fas fa-', '', $icono); ?>"></i>
                                    <?php echo htmlspecialchars($entrega['actividad_tipo']); ?>
                                </span>
                                <span class="meta-badge badge-dificultad">
                                    <i class="fas fa-signal"></i>
                                    <?php echo htmlspecialchars($entrega['dificultad']); ?>
                                </span>
                                <span class="meta-badge badge-puntos">
                                    <i class="fas fa-trophy"></i>
                                    <?php echo $entrega['puntos_actividad']; ?> XP
                                </span>
                            </div>
                            
                            <?php if(!empty($entrega['actividad_descripcion'])): ?>
                                <p class="mb-3"><?php echo htmlspecialchars(substr($entrega['actividad_descripcion'], 0, 200)); ?><?php echo strlen($entrega['actividad_descripcion']) > 200 ? '...' : ''; ?></p>
                            <?php endif; ?>
                            
                            <div class="task-status">
                                <span class="status-badge status-<?php echo $estado_color; ?>">
                                    <i class="fas fa-<?php echo $estado_color == 'success' ? 'check-circle' : ($estado_color == 'warning' ? 'clock' : 'paper-plane'); ?>"></i>
                                    <?php echo ucfirst($entrega['estado']); ?>
                                </span>
                                <div class="ms-auto">
                                    <small class="text-muted">
                                        <i class="fas fa-paper-plane me-1"></i>
                                        Entregado: <?php echo date('d/m/Y H:i', strtotime($entrega['fecha_entrega'])); ?>
                                    </small>
                                </div>
                            </div>
                            
                            <!-- Contenido de la entrega -->
                            <?php if(!empty($entrega['respuesta']) || !empty($entrega['archivo'])): ?>
                                <div class="delivery-content">
                                    <h5><i class="fas fa-paperclip"></i> Contenido de la Entrega</h5>
                                    
                                    <?php if(!empty($entrega['respuesta'])): ?>
                                        <div class="mb-3">
                                            <strong>Respuesta:</strong>
                                            <p class="mb-0 mt-2"><?php echo nl2br(htmlspecialchars($entrega['respuesta'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if(!empty($entrega['archivo'])): ?>
                                        <div>
                                            <strong>Archivo Adjunto:</strong>
                                            <div class="mt-2">
                                                <a href="uploads/<?php echo htmlspecialchars($entrega['archivo']); ?>" 
                                                   class="file-download" download>
                                                    <i class="fas fa-download"></i>
                                                    <?php echo htmlspecialchars($entrega['archivo']); ?>
                                                </a>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Calificación -->
                            <?php if($entrega['estado'] == 'calificado' && $entrega['calificacion'] !== null): ?>
                                <div class="grade-section">
                                    <div class="text-center">
                                        <h5 class="text-muted mb-1">Calificación</h5>
                                        <div class="grade-display text-<?php echo $calificacion_color; ?>">
                                            <?php echo number_format($entrega['calificacion'], 1); ?>
                                        </div>
                                        <p class="mb-0 text-<?php echo $calificacion_color; ?> fw-bold">
                                            <i class="fas fa-<?php echo $calificacion_color == 'success' ? 'trophy' : ($calificacion_color == 'info' ? 'thumbs-up' : ($calificacion_color == 'warning' ? 'meh' : 'frown')); ?>"></i>
                                            <?php echo $calificacion_texto; ?>
                                        </p>
                                    </div>
                                    
                                    <?php if(!empty($entrega['comentarios_tutor'])): ?>
                                        <div class="grade-comments">
                                            <h6><i class="fas fa-comment me-2"></i>Comentarios del Tutor</h6>
                                            <p class="mb-0 mt-2"><?php echo nl2br(htmlspecialchars($entrega['comentarios_tutor'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if($entrega['fecha_evaluacion']): ?>
                                        <div class="text-end mt-3">
                                            <small class="text-muted">
                                                <i class="fas fa-calendar-check me-1"></i>
                                                Calificado: <?php echo date('d/m/Y H:i', strtotime($entrega['fecha_evaluacion'])); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Botones de acción -->
                            <div class="action-buttons">
                                <?php if($entrega['estado'] == 'pendiente'): ?>
                                    <a href="revisar_entrega.php?entrega_id=<?php echo $entrega['id']; ?>" 
                                       class="btn-action btn-calificar">
                                        <i class="fas fa-star"></i> Calificar Tarea
                                    </a>
                                <?php endif; ?>
                                
                                <a href="ver_entrega.php?id=<?php echo $entrega['id']; ?>" 
                                   class="btn-action btn-ver">
                                    <i class="fas fa-eye"></i> Ver Entrega Completa
                                </a>
                                
                                <?php if($entrega['estado'] == 'calificado'): ?>
                                    <button class="btn-action" style="background: linear-gradient(90deg, #6c757d, #5a6268); color: white;" 
                                            onclick="verCalificacion(<?php echo $entrega['id']; ?>)">
                                        <i class="fas fa-edit"></i> Modificar Calificación
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <h3>
                            <?php if(isset($_GET['estado'])): ?>
                                ¡No hay tareas <?php echo $_GET['estado'] == 'pendiente' ? 'pendientes' : ($_GET['estado'] == 'calificado' ? 'calificadas' : 'entregadas'); ?>!
                            <?php else: ?>
                                ¡No hay tareas registradas!
                            <?php endif; ?>
                        </h3>
                        <p>
                            <?php if(isset($_GET['estado'])): ?>
                                Este explorador no tiene tareas <?php echo $_GET['estado'] == 'pendiente' ? 'pendientes de calificar' : ($_GET['estado'] == 'calificado' ? 'calificadas' : 'entregadas'); ?>.
                            <?php else: ?>
                                Este explorador aún no ha realizado entregas en este curso.
                            <?php endif; ?>
                        </p>
                        <a href="reporte_alumnos.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left me-2"></i>Volver al Reporte
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para ver calificación (se podría expandir)
        function verCalificacion(entregaId) {
            if (confirm('¿Deseas modificar la calificación de esta entrega?')) {
                window.location.href = 'revisar_entrega.php?entrega_id=' + entregaId + '&modificar=true';
            }
        }
        
        // Animación para las tarjetas
        document.addEventListener('DOMContentLoaded', function() {
            const taskItems = document.querySelectorAll('.task-item');
            taskItems.forEach((item, index) => {
                item.style.animationDelay = `${index * 0.1}s`;
                item.classList.add('animate__animated', 'animate__fadeInUp');
            });
            
            // Configurar tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Resaltar tareas pendientes
            document.querySelectorAll('.status-pendiente').forEach(badge => {
                const item = badge.closest('.task-item');
                if (item) {
                    item.style.borderLeft = '5px solid var(--secondary)';
                }
            });
            
            // Resaltar tareas vencidas
            document.querySelectorAll('.fecha-limite.vencida').forEach(fecha => {
                const item = fecha.closest('.task-item');
                if (item) {
                    item.style.borderLeft = '5px solid var(--danger)';
                }
            });
        });
        
        // Función para exportar reporte
        function exportarReporte() {
            const nombreAlumno = document.querySelector('.student-name').textContent;
            const fecha = new Date().toLocaleDateString('es-MX');
            
            let contenido = `REPORTE DE TAREAS - ${nombreAlumno}\n`;
            contenido += `Fecha: ${fecha}\n`;
            contenido += `Curso: ${document.querySelector('.course-info p').textContent}\n`;
            contenido += '='.repeat(50) + '\n\n';
            
            // Obtener datos de cada tarea
            document.querySelectorAll('.task-item').forEach((task, index) => {
                const titulo = task.querySelector('.task-title').textContent;
                const curso = task.querySelector('.task-course').textContent;
                const estado = task.querySelector('.status-badge').textContent;
                const fechaLimite = task.querySelector('.fecha-limite').textContent.replace('Límite: ', '');
                
                contenido += `${index + 1}. ${titulo}\n`;
                contenido += `   Curso: ${curso}\n`;
                contenido += `   Estado: ${estado}\n`;
                contenido += `   Fecha límite: ${fechaLimite}\n`;
                
                // Agregar calificación si existe
                const calificacionElement = task.querySelector('.grade-display');
                if (calificacionElement) {
                    contenido += `   Calificación: ${calificacionElement.textContent}\n`;
                }
                
                contenido += '\n';
            });
            
            // Descargar archivo
            const blob = new Blob([contenido], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `tareas_${nombreAlumno.replace(/\s+/g, '_')}_${fecha.replace(/\//g, '-')}.txt`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
