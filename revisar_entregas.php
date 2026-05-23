<?php
include 'php/config.php';
include 'php/sendgrid_notificaciones.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'tutor') {
    header("Location: index.php");
    exit();
}

$tutor_id = $_SESSION['user_id'];
$tutor_nombre = $_SESSION['nombre'];

try {
    // Consultar entregas pendientes usando PDO
    $sql = "SELECT e.id as entrega_id, 
                   u.nombre as alumno_nombre, 
                   u.email as alumno_email,
                   a.titulo as actividad_nombre,
                   a.tipo as actividad_tipo,
                   a.dificultad as actividad_dificultad,
                   c.nombre as curso_nombre,
                   c.id as curso_id,
                   e.id_alumno,
                   e.fecha_entrega,
                   e.archivo as archivo_adjunto,
                   e.respuesta as comentario_alumno,
                   ev.calificacion,
                   EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - e.fecha_entrega))/3600 as horas_pasadas,
                   (SELECT COUNT(*) FROM entregas e2 WHERE e2.id_alumno = e.id_alumno AND e2.id_actividad = e.id_actividad) as intentos_totales
            FROM entregas e
            JOIN usuarios u ON e.id_alumno = u.id
            JOIN actividades a ON e.id_actividad = a.id
            JOIN cursos c ON a.id_curso = c.id
            LEFT JOIN evaluaciones ev ON e.id = ev.id_entrega
            WHERE c.id_tutor = :tutor_id 
            AND e.estado = 'pendiente'
            ORDER BY 
                CASE 
                    WHEN EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - e.fecha_entrega))/3600 > 48 THEN 1
                    WHEN EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - e.fecha_entrega))/3600 > 24 THEN 2
                    ELSE 3
                END,
                e.fecha_entrega ASC";
    
    $stmt = $conn->pdo->prepare($sql);
    $stmt->execute([':tutor_id' => $tutor_id]);
    $entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Contadores para estadísticas
    $total_pendientes = count($entregas);
    $urgentes = 0;
    $recientes = 0;
    
    foreach ($entregas as $entrega) {
        $horas = $entrega['horas_pasadas'];
        if ($horas > 48) $urgentes++;
        if ($horas < 24) $recientes++;
    }
    
} catch(PDOException $e) {
    $error_msg = "Error al cargar las entregas: " . $e->getMessage();
    $entregas = [];
    $total_pendientes = 0;
    $urgentes = 0;
    $recientes = 0;
}

// Función para obtener color según urgencia
function getUrgenciaColor($horas) {
    if ($horas > 72) return 'danger';
    if ($horas > 48) return 'warning';
    if ($horas > 24) return 'info';
    return 'success';
}

// Función para obtener color de dificultad
function getDificultadColor($dificultad) {
    $colores = [
        'Fácil' => 'success',
        'Normal' => 'info',
        'Difícil' => 'danger'
    ];
    return $colores[$dificultad] ?? 'secondary';
}

// Función para obtener icono según tipo de actividad
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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📋 Revisar Tareas - D&F Mindspace</title>
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
        
        .review-container {
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
            background: linear-gradient(90deg, var(--primary), var(--secondary), var(--accent));
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
        
        .stats-container {
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
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 20px;
            color: white;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover .stat-icon {
            transform: scale(1.1) rotate(10deg);
        }
        
        .stat-card:nth-child(1) .stat-icon {
            background: linear-gradient(135deg, var(--primary), #2ca5d4);
        }
        
        .stat-card:nth-child(2) .stat-icon {
            background: linear-gradient(135deg, var(--danger), #ff4757);
        }
        
        .stat-card:nth-child(3) .stat-icon {
            background: linear-gradient(135deg, var(--accent), #6aab39);
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 900;
            margin: 10px 0;
            color: #222;
            line-height: 1;
        }
        
        .stat-label {
            color: #666;
            font-size: 1.1rem;
            font-weight: 600;
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
        
        .delivery-card {
            background: white;
            border-radius: 25px;
            padding: 30px;
            box-shadow: var(--card-shadow);
            margin-bottom: 25px;
            border: 3px solid transparent;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }
        
        .delivery-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(44, 186, 236, 0.2);
        }
        
        .delivery-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 8px;
            background: linear-gradient(to bottom, var(--primary), var(--accent));
        }
        
        .delivery-card.urgent {
            border-color: var(--danger);
            animation: pulseBorder 2s infinite;
        }
        
        .delivery-card.warning {
            border-color: var(--secondary);
        }
        
        .delivery-card.info {
            border-color: var(--primary);
        }
        
        @keyframes pulseBorder {
            0%, 100% { border-color: var(--danger); }
            50% { border-color: rgba(255, 107, 139, 0.5); }
        }
        
        .student-info {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .student-avatar {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
            box-shadow: 0 8px 25px rgba(44, 186, 236, 0.3);
        }
        
        .student-details h4 {
            font-weight: 800;
            color: #222;
            margin-bottom: 5px;
            font-size: 1.4rem;
        }
        
        .student-details p {
            color: #666;
            margin-bottom: 0;
        }
        
        .badge-custom {
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .badge-danger {
            background: linear-gradient(135deg, rgba(255, 107, 139, 0.15), rgba(255, 107, 139, 0.1));
            color: var(--danger);
            border: 2px solid rgba(255, 107, 139, 0.3);
        }
        
        .badge-warning {
            background: linear-gradient(135deg, rgba(240, 174, 42, 0.15), rgba(240, 174, 42, 0.1));
            color: var(--secondary);
            border: 2px solid rgba(240, 174, 42, 0.3);
        }
        
        .badge-info {
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.15), rgba(44, 186, 236, 0.1));
            color: var(--primary);
            border: 2px solid rgba(44, 186, 236, 0.3);
        }
        
        .badge-success {
            background: linear-gradient(135deg, rgba(131, 191, 70, 0.15), rgba(131, 191, 70, 0.1));
            color: var(--accent);
            border: 2px solid rgba(131, 191, 70, 0.3);
        }
        
        .btn-grade {
            background: linear-gradient(90deg, var(--primary), var(--accent));
            border: none;
            border-radius: 20px;
            padding: 15px 35px;
            color: white;
            font-weight: 800;
            font-size: 1.1rem;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 10px 25px rgba(44, 186, 236, 0.3);
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }
        
        .btn-grade::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: 0.6s;
        }
        
        .btn-grade:hover::before {
            left: 100%;
        }
        
        .btn-grade:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 35px rgba(44, 186, 236, 0.4);
            color: white;
            text-decoration: none;
        }
        
        .btn-preview {
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.1), rgba(44, 186, 236, 0.05));
            color: var(--primary);
            border: 2px solid rgba(44, 186, 236, 0.3);
            border-radius: 15px;
            padding: 12px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-preview:hover {
            background: linear-gradient(135deg, var(--primary), #2ca5d4);
            color: white;
            border-color: var(--primary);
            text-decoration: none;
        }
        
        .no-deliveries {
            text-align: center;
            padding: 80px 40px;
            background: white;
            border-radius: 30px;
            box-shadow: var(--card-shadow);
        }
        
        .no-deliveries-icon {
            font-size: 5rem;
            color: var(--primary);
            margin-bottom: 30px;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        
        .no-deliveries h3 {
            color: #222;
            font-weight: 800;
            margin-bottom: 15px;
            font-size: 2rem;
        }
        
        .no-deliveries p {
            color: #666;
            font-size: 1.2rem;
            max-width: 500px;
            margin: 0 auto 30px;
            line-height: 1.6;
        }
        
        .activity-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
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
        }
        
        .activity-difficulty {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            border-radius: 15px;
            font-weight: 600;
        }
        
        .difficulty-facil {
            background: linear-gradient(135deg, rgba(131, 191, 70, 0.15), rgba(131, 191, 70, 0.1));
            color: var(--accent);
            border: 2px solid rgba(131, 191, 70, 0.3);
        }
        
        .difficulty-normal {
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.15), rgba(44, 186, 236, 0.1));
            color: var(--primary);
            border: 2px solid rgba(44, 186, 236, 0.3);
        }
        
        .difficulty-dificil {
            background: linear-gradient(135deg, rgba(255, 107, 139, 0.15), rgba(255, 107, 139, 0.1));
            color: var(--danger);
            border: 2px solid rgba(255, 107, 139, 0.3);
        }
        
        .comment-preview {
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.05), rgba(44, 186, 236, 0.02));
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
            border-left: 5px solid var(--primary);
            font-style: italic;
            color: #555;
            max-height: 150px;
            overflow-y: auto;
        }
        
        .file-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 20px;
            background: white;
            border-radius: 15px;
            border: 2px solid rgba(44, 186, 236, 0.2);
            margin: 15px 0;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .file-indicator:hover {
            border-color: var(--primary);
            background: rgba(44, 186, 236, 0.05);
        }
        
        .time-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #666;
            font-size: 0.9rem;
        }
        
        .intento-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(240, 174, 42, 0.15), rgba(240, 174, 42, 0.1));
            color: var(--secondary);
            border: 1px solid rgba(240, 174, 42, 0.3);
            font-size: 0.8rem;
            font-weight: 600;
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
        
        .course-filter {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 12px 25px;
            border-radius: 20px;
            border: 2px solid rgba(44, 186, 236, 0.2);
            background: white;
            color: var(--primary);
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .filter-btn:hover, .filter-btn.active {
            background: linear-gradient(135deg, var(--primary), #2ca5d4);
            color: white;
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(44, 186, 236, 0.3);
        }
        
        @media (max-width: 768px) {
            .review-container {
                padding: 20px 15px;
            }
            
            .header-section h1 {
                font-size: 2.2rem;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .student-info {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .delivery-card {
                padding: 20px;
            }
            
            .course-filter {
                justify-content: center;
            }
        }
        .modal-content {
            border-radius: 25px;
        }

        .modal-header {
            border-bottom: none;
        }

        .modal-footer {
            border-top: none;
        }

    </style>
</head>
<body>
    <!-- Partículas flotantes -->
    <div class="floating-particles" id="particles"></div>
    
    <div class="review-container">
        <!-- Botón para volver -->
        <a href="dashboard_tutor.php" class="back-link animate__animated animate__fadeInLeft">
            <i class="fas fa-arrow-left"></i> Volver al Panel
        </a>
        
        <!-- Encabezado -->
        <div class="header-section">
            <h1>📋 Tareas por Calificar</h1>
            <p>Revisa y califica las entregas de tus exploradores. ¡Motívalos con retroalimentación positiva!</p>
        </div>
        
        <!-- Estadísticas -->
        <div class="stats-container">
            <div class="stat-card animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
                <div class="stat-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-number"><?php echo $total_pendientes; ?></div>
                <div class="stat-label">Tareas Pendientes</div>
                <small class="text-muted">Esperando tu revisión</small>
            </div>
            
            <div class="stat-card animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-number"><?php echo $urgentes; ?></div>
                <div class="stat-label">Urgentes (+48h)</div>
                <small class="text-muted">Necesitan atención inmediata</small>
            </div>
            
            <div class="stat-card animate__animated animate__fadeInUp" style="animation-delay: 0.3s">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?php echo $recientes; ?></div>
                <div class="stat-label">Recientes</div>
                <small class="text-muted">Entregadas en las últimas 24h</small>
            </div>
        </div>
        
        <!-- Lista de entregas (AHORA CON PDO) -->
        <?php if (isset($error_msg)): ?>
            <div class="alert alert-danger"><?php echo $error_msg; ?></div>
        <?php endif; ?>
        
        <?php if ($total_pendientes > 0): ?>
            <?php foreach($entregas as $row): 
                $horas_pasadas = $row['horas_pasadas'];
                $urgencia_class = getUrgenciaColor($horas_pasadas);
                $urgencia_text = '';
                
                if ($horas_pasadas > 72) $urgencia_text = 'Muy Urgente';
                elseif ($horas_pasadas > 48) $urgencia_text = 'Urgente';
                elseif ($horas_pasadas > 24) $urgencia_text = 'Pendiente';
                else $urgencia_text = 'Reciente';
                
                $iniciales = strtoupper(substr($row['alumno_nombre'], 0, 2));
                $actividad_icono = getActividadIcono($row['actividad_tipo']);
                $dificultad_class = getDificultadColor($row['actividad_dificultad']);
            ?>
                <div class="delivery-card <?php echo $urgencia_class; ?> animate__animated animate__fadeInUp" data-course-id="<?php echo $row['curso_id'] ?? ''; ?>">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="student-info">
                                <div class="student-avatar">
                                    <?php echo $iniciales; ?>
                                </div>
                                <div class="student-details">
                                    <h4><?php echo htmlspecialchars($row['alumno_nombre']); ?></h4>
                                    <p>
                                        <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($row['alumno_email']); ?> • 
                                        <i class="fas fa-book me-1 ms-3"></i><?php echo htmlspecialchars($row['curso_nombre']); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="activity-info">
                                <span class="activity-type">
                                    <i class="<?php echo $actividad_icono; ?> me-2"></i>
                                    <?php echo htmlspecialchars($row['actividad_tipo']); ?>
                                </span>
                                <span class="activity-difficulty difficulty-<?php echo strtolower($row['actividad_dificultad'] ?? 'normal'); ?>">
                                    <?php if($row['actividad_dificultad'] == 'Fácil'): ?>
                                        <i class="fas fa-star"></i>
                                    <?php elseif($row['actividad_dificultad'] == 'Normal'): ?>
                                        <i class="fas fa-star"></i><i class="fas fa-star"></i>
                                    <?php elseif($row['actividad_dificultad'] == 'Difícil'): ?>
                                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($row['actividad_dificultad'] ?? 'Normal'); ?>
                                </span>
                                <?php if($row['intentos_totales'] > 1): ?>
                                    <span class="intento-badge">
                                        <i class="fas fa-redo"></i>
                                        <?php echo $row['intentos_totales']; ?> intentos
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <h5 class="mb-3">
                                <i class="fas fa-tasks me-2 text-primary"></i>
                                <?php echo htmlspecialchars($row['actividad_nombre']); ?>
                            </h5>
                            
                            <?php if(!empty($row['comentario_alumno'])): ?>
                                <div class="comment-preview">
                                    <strong><i class="fas fa-comment me-2"></i>Respuesta del alumno:</strong>
                                    <p class="mb-0 mt-2"><?php echo nl2br(htmlspecialchars(substr($row['comentario_alumno'], 0, 300))); ?><?php echo strlen($row['comentario_alumno']) > 300 ? '...' : ''; ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if(!empty($row['archivo_adjunto'])): ?>
                                <a href="uploads/<?php echo htmlspecialchars($row['archivo_adjunto']); ?>" 
                                   class="file-indicator" 
                                   target="_blank">
                                    <i class="fas fa-paperclip text-primary fa-lg"></i>
                                    <div>
                                        <strong>Archivo adjunto</strong>
                                        <div class="small text-muted">
                                            <?php 
                                            $extension = pathinfo($row['archivo_adjunto'], PATHINFO_EXTENSION);
                                            echo strtoupper($extension) . ' • Haz clic para ver';
                                            ?>
                                        </div>
                                    </div>
                                    <i class="fas fa-external-link-alt ms-auto"></i>
                                </a>
                            <?php endif; ?>
                            
                            <div class="d-flex align-items-center gap-3 mt-3 flex-wrap">
                                <div class="time-indicator">
                                    <i class="fas fa-clock"></i>
                                    Enviado <?php echo date('d/m/Y H:i', strtotime($row['fecha_entrega'])); ?>
                                </div>
                                <span class="badge-custom badge-<?php echo $urgencia_class; ?>">
                                    <i class="fas fa-<?php echo $urgencia_class == 'danger' ? 'fire' : ($urgencia_class == 'warning' ? 'exclamation-triangle' : 'clock'); ?>"></i>
                                    <?php echo $urgencia_text; ?> (<?php echo round($horas_pasadas); ?>h)
                                </span>
                            </div>
                        </div>
                        
                        <div class="col-md-4 d-flex flex-column justify-content-between align-items-end">
                            <div class="d-flex flex-column gap-3 w-100">
                                <?php if(!empty($row['archivo_adjunto'])): ?>
                                    <a href="preview_file.php?file=<?php echo urlencode($row['archivo_adjunto']); ?>" 
                                       class="btn-preview" target="_blank">
                                        <i class="fas fa-eye"></i> Vista Previa
                                    </a>
                                <?php endif; ?>
                                
                                <a href="calificar_tarea.php?id=<?php echo $row['entrega_id']; ?>" 
                                   class="btn-grade">
                                    <i class="fas fa-star"></i> Calificar Tarea
                                </a>
                                
                                <a href="ver_historial.php?alumno=<?php echo $row['id_alumno'] ?? ''; ?>&curso=<?php echo $row['curso_id'] ?? ''; ?>" 
                                   class="btn-preview">
                                    <i class="fas fa-history"></i> Ver Historial
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-deliveries animate__animated animate__fadeIn">
                <div class="no-deliveries-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3>¡Todo al día! 🎉</h3>
                <p>No hay tareas pendientes por calificar. Tus exploradores están esperando nuevas misiones.</p>
                <div class="d-flex gap-3 justify-content-center flex-wrap">
                    <a href="crear_actividad.php" class="btn-grade">
                        <i class="fas fa-plus-circle"></i> Crear Nueva Misión
                    </a>
                    <a href="dashboard_tutor.php" class="btn-preview">
                        <i class="fas fa-chart-line"></i> Ver Estadísticas
                    </a>
                    <a href="ver_calificaciones.php" class="btn-preview">
                        <i class="fas fa-list-check"></i> Ver Calificaciones
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal de éxito -->
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 25px; overflow: hidden;">
            <div class="modal-header" style="background: linear-gradient(135deg, #83bf46, #6aab39); border: none;">
                <h5 class="modal-title text-white fw-bold" id="successModalLabel">
                    <i class="fas fa-check-circle me-2"></i>¡Calificación Enviada!
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div class="mb-3">
                    <i class="fas fa-trophy" style="font-size: 4rem; color: #f0ae2a;"></i>
                </div>
                <h4 class="fw-bold">¡Excelente trabajo!</h4>
                <p>Has calificado la tarea del alumno correctamente.</p>
                <div class="alert alert-success rounded-3 d-inline-block">
                    <strong>Puntuación:</strong> <span id="modalPuntos"></span> / <span id="modalMax"></span> puntos
                </div>
                <div class="mt-2">
                    <span class="badge bg-info fs-6">Progreso actualizado: <span id="modalProgreso"></span>%</span>
                </div>
            </div>
            <div class="modal-footer justify-content-center" style="background: #f8f9fa;">
                <button type="button" class="btn btn-success px-4" data-bs-dismiss="modal">
                    <i class="fas fa-check me-2"></i>Continuar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Contenedor de notificaciones Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
    <div id="successToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="5000">
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-check-circle me-2"></i>
                <span id="toastMessage">¡Tarea calificada exitosamente!</span>
                <br>
                <small id="toastDetails"></small>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>
    
<!-- Modal de éxito -->
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 25px; overflow: hidden;">
            <div class="modal-header" style="background: linear-gradient(135deg, #83bf46, #6aab39); border: none;">
                <h5 class="modal-title text-white fw-bold" id="successModalLabel">
                    <i class="fas fa-check-circle me-2"></i>¡Calificación Enviada!
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div class="mb-3">
                    <i class="fas fa-trophy" style="font-size: 4rem; color: #f0ae2a;"></i>
                </div>
                <h4 class="fw-bold">¡Excelente trabajo!</h4>
                <p>Has calificado la tarea del alumno correctamente.</p>
                <div class="alert alert-success rounded-3 d-inline-block">
                    <strong>Puntuación:</strong> <span id="modalPuntos"></span> / <span id="modalMax"></span> puntos
                </div>
                <div class="mt-2">
                    <span class="badge bg-info fs-6">Progreso actualizado: <span id="modalProgreso"></span>%</span>
                </div>
            </div>
            <div class="modal-footer justify-content-center" style="background: #f8f9fa;">
                <button type="button" class="btn btn-success px-4" data-bs-dismiss="modal">
                    <i class="fas fa-check me-2"></i>Continuar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast de notificación -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
    <div id="successToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="5000">
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-check-circle me-2"></i>
                <span id="toastMessage">¡Tarea calificada exitosamente!</span>
                <br>
                <small id="toastDetails"></small>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Crear partículas flotantes
        function crearParticulas() {
            const container = document.getElementById('particles');
            if (container) {
                for (let i = 0; i < 30; i++) {
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
        }
        
        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            crearParticulas();
            
            // Mostrar notificación si hay tareas urgentes
            const urgentCount = <?php echo $urgentes; ?>;
            if (urgentCount > 0) {
                showNotification(`Tienes ${urgentCount} tarea(s) urgente(s) por calificar.`, 'warning');
            }
            
            // Configurar filtros por curso
            setupCourseFilters();
        });
        
        // Función para mostrar notificaciones
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `position-fixed top-0 end-0 m-4 p-3 rounded-3 shadow-lg animate__animated animate__fadeInRight animate__faster` + 
                                   ` ${type === 'warning' ? 'bg-warning text-dark' : 'bg-primary text-white'}`;
            notification.style.zIndex = '9999';
            notification.innerHTML = `
                <div class="d-flex align-items-center gap-3">
                    <i class="fas fa-${type === 'warning' ? 'exclamation-triangle' : 'info-circle'} fa-2x"></i>
                    <div>
                        <strong>¡Atención!</strong>
                        <div class="small">${message}</div>
                    </div>
                    <button class="btn btn-sm btn-close" onclick="this.parentElement.parentElement.remove()"></button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.classList.add('animate__fadeOutRight');
                    setTimeout(() => notification.remove(), 500);
                }
            }, 5000);
        }
        
        // Función para configurar filtros por curso
        function setupCourseFilters() {
            const deliveries = document.querySelectorAll('.delivery-card');
            if (deliveries.length === 0) return;
            
            // Crear contenedor de filtros
            const filterHTML = `
                <div class="course-filter animate__animated animate__fadeInUp">
                    <button class="filter-btn active" data-course="all">
                        <i class="fas fa-layer-group me-2"></i>Todos los Cursos
                    </button>
                    <button class="filter-btn" data-course="urgent">
                        <i class="fas fa-exclamation-triangle me-2"></i>Solo Urgentes
                    </button>
                    <button class="filter-btn" data-course="recent">
                        <i class="fas fa-clock me-2"></i>Recientes (24h)
                    </button>
                </div>
            `;
            
            // Insertar filtros después de las estadísticas
            const statsContainer = document.querySelector('.stats-container');
            if (statsContainer) {
                statsContainer.insertAdjacentHTML('afterend', filterHTML);
            }
            
            // Configurar eventos de los filtros
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    const filter = this.dataset.course;
                    filterDeliveries(filter);
                });
            });
        }
        
        // Función para filtrar entregas
        function filterDeliveries(filter) {
            const deliveries = document.querySelectorAll('.delivery-card');
            
            deliveries.forEach(delivery => {
                let show = true;
                
                switch(filter) {
                    case 'urgent':
                        show = delivery.classList.contains('danger') || delivery.classList.contains('warning');
                        break;
                    case 'recent':
                        const horasText = delivery.querySelector('.badge-custom')?.textContent;
                        if (horasText) {
                            const horasMatch = horasText.match(/\((\d+)h\)/);
                            if (horasMatch) {
                                const horas = parseInt(horasMatch[1]);
                                show = horas <= 24;
                            }
                        }
                        break;
                    case 'all':
                    default:
                        show = true;
                }
                
                delivery.style.display = show ? 'block' : 'none';
                if (show) delivery.classList.add('animate__fadeIn');
            });
        }

        // Verificar si hay parámetro de éxito en la URL
function checkSuccessMessage() {
    const urlParams = new URLSearchParams(window.location.search);
    const success = urlParams.get('success');
    const puntos = urlParams.get('puntos');
    const maxPuntos = urlParams.get('max');
    const progreso = urlParams.get('progreso');
    
    if (success == '1') {
        // Actualizar modal con los valores
        document.getElementById('modalPuntos').textContent = puntos;
        document.getElementById('modalMax').textContent = maxPuntos;
        document.getElementById('modalProgreso').textContent = progreso;
        
        // Mostrar modal
        const modal = new bootstrap.Modal(document.getElementById('successModal'));
        modal.show();
        
        // También mostrar toast como respaldo
        const toastElement = document.getElementById('successToast');
        const toastMessage = document.getElementById('toastMessage');
        const toastDetails = document.getElementById('toastDetails');
        toastMessage.innerHTML = '<i class="fas fa-check-circle me-2"></i>¡Tarea calificada exitosamente!';
        toastDetails.innerHTML = `Puntuación: ${puntos}/${maxPuntos} puntos | Progreso: ${progreso}%`;
        
        const toast = new bootstrap.Toast(toastElement, { delay: 5000 });
        toast.show();
        
        // Limpiar URL sin recargar la página
        window.history.replaceState({}, document.title, window.location.pathname);
    }
}

// Ejecutar al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    // ... tu código existente ...
    checkSuccessMessage();
});

    </script>
</body>
</html>