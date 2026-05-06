<?php
include 'php/config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'tutor') {
    header("Location: index.php");
    exit();
}

$tutor_id = $_SESSION['user_id'];
$tutor_nombre = $_SESSION['nombre'];

// Obtener parámetros de filtro
$curso_filtro = isset($_GET['curso_id']) ? intval($_GET['curso_id']) : 0;
$estado_filtro = isset($_GET['estado']) ? $_GET['estado'] : '';

// Consulta para obtener cursos del tutor (para filtro)
$sql_cursos = "SELECT id, nombre FROM cursos WHERE id_tutor = '$tutor_id' ORDER BY nombre ASC";
$res_cursos = mysqli_query($conn, $sql_cursos);

// Consulta principal CON LA ESTRUCTURA CORRECTA DE TU BASE DE DATOS
$sql = "SELECT 
            u.id as alumno_id,
            u.nombre as alumno_nombre,
            u.email as alumno_email,
            u.fecha_nacimiento,
            u.fecha_registro,
            c.id as curso_id,
            c.nombre as curso_nombre,
            c.nivel as curso_nivel,
            p.porcentaje,
            p.actividades_completadas,
            i.estado as estado_inscripcion,
            i.fecha_inscripcion,
            i.progreso as progreso_inscripcion,
            -- Contar tareas pendientes para este alumno en este curso
            (SELECT COUNT(*) FROM entregas e 
             JOIN actividades a ON e.id_actividad = a.id 
             WHERE e.id_alumno = u.id AND a.id_curso = c.id AND e.estado = 'pendiente') as tareas_pendientes,
            -- Contar tareas calificadas para este alumno en este curso
            (SELECT COUNT(*) FROM entregas e 
             JOIN actividades a ON e.id_actividad = a.id 
             WHERE e.id_alumno = u.id AND a.id_curso = c.id AND e.estado = 'calificado') as tareas_calificadas,
            -- Obtener la última calificación si existe
            (SELECT ev.calificacion FROM evaluaciones ev
             JOIN entregas en ON ev.id_entrega = en.id
             WHERE en.id_alumno = u.id 
             AND en.id_actividad IN (SELECT id FROM actividades WHERE id_curso = c.id)
             ORDER BY ev.fecha_evaluacion DESC LIMIT 1) as ultima_calificacion
        FROM usuarios u
        JOIN inscripciones i ON u.id = i.id_alumno
        JOIN cursos c ON i.id_curso = c.id
        LEFT JOIN progreso p ON (u.id = p.id_alumno AND c.id = p.id_curso)
        WHERE c.id_tutor = '$tutor_id' 
        AND u.tipo = 'alumno'";

// Aplicar filtros
if ($curso_filtro > 0) {
    $sql .= " AND c.id = $curso_filtro";
}

if ($estado_filtro == 'completado') {
    $sql .= " AND p.porcentaje >= 100";
} elseif ($estado_filtro == 'en_progreso') {
    $sql .= " AND p.porcentaje < 100 AND p.porcentaje > 0";
} elseif ($estado_filtro == 'nuevo') {
    $sql .= " AND (p.porcentaje IS NULL OR p.porcentaje = 0)";
}

$sql .= " ORDER BY c.nombre ASC, p.porcentaje DESC, u.nombre ASC";

$res = mysqli_query($conn, $sql);

// Obtener estadísticas generales
$sql_stats = "SELECT 
                COUNT(DISTINCT u.id) as total_alumnos,
                COUNT(DISTINCT c.id) as total_cursos,
                AVG(p.porcentaje) as promedio_progreso,
                SUM(CASE WHEN p.porcentaje >= 100 THEN 1 ELSE 0 END) as completados,
                SUM(CASE WHEN p.porcentaje < 100 AND p.porcentaje > 0 THEN 1 ELSE 0 END) as en_progreso,
                SUM(CASE WHEN p.porcentaje = 0 OR p.porcentaje IS NULL THEN 1 ELSE 0 END) as nuevos,
                -- Tareas pendientes de calificar
                (SELECT COUNT(*) FROM entregas e 
                 JOIN actividades a ON e.id_actividad = a.id 
                 JOIN cursos c2 ON a.id_curso = c2.id
                 WHERE e.estado = 'pendiente' AND c2.id_tutor = '$tutor_id') as tareas_pendientes_totales
              FROM usuarios u
              JOIN inscripciones i ON u.id = i.id_alumno
              JOIN cursos c ON i.id_curso = c.id
              LEFT JOIN progreso p ON (u.id = p.id_alumno AND c.id = p.id_curso)
              WHERE c.id_tutor = '$tutor_id' 
              AND u.tipo = 'alumno'";

$res_stats = mysqli_query($conn, $sql_stats);
$stats = mysqli_fetch_assoc($res_stats);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Exploradores - D&F Mindspace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css">
    <style>
        :root {
            --primary: #2cbaec;
            --secondary: #f0ae2a;
            --accent: #83bf46;
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
        
        .report-container {
            padding: 30px 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header-section {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .header-section h1 {
            font-family: 'Nunito', sans-serif;
            font-weight: 800;
            font-size: 2.8rem;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }
        
        .header-section p {
            color: #666;
            font-size: 1.1rem;
            max-width: 700px;
            margin: 0 auto;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            border: 2px solid rgba(44, 186, 236, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(44, 186, 236, 0.2);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 20px;
            background: linear-gradient(135deg, var(--primary), #2ca5d4);
            color: white;
            box-shadow: 0 8px 20px rgba(44, 186, 236, 0.3);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary);
            margin: 10px 0;
            font-family: 'Nunito', sans-serif;
        }
        
        .stat-card.warning .stat-icon {
            background: linear-gradient(135deg, var(--secondary), #f5c15d);
        }
        
        .stat-card.warning .stat-number {
            color: var(--secondary);
        }
        
        .stat-card.success .stat-icon {
            background: linear-gradient(135deg, var(--accent), #6ca839);
        }
        
        .stat-card.success .stat-number {
            color: var(--accent);
        }
        
        .filters-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
            border: 2px solid rgba(44, 186, 236, 0.1);
        }
        
        .filter-group {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .form-control, .form-select {
            border: 2px solid rgba(44, 186, 236, 0.2);
            border-radius: 12px;
            padding: 12px 15px;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(44, 186, 236, 0.25);
        }
        
        .btn-filter {
            background: linear-gradient(90deg, var(--primary), var(--accent));
            border: none;
            border-radius: 12px;
            padding: 12px 25px;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(44, 186, 236, 0.3);
        }
        
        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(44, 186, 236, 0.4);
            color: white;
        }
        
        .table-container {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            border: 2px solid rgba(44, 186, 236, 0.1);
            margin-bottom: 30px;
        }
        
        .table-header {
            background: linear-gradient(90deg, rgba(44, 186, 236, 0.9), rgba(131, 191, 70, 0.9));
            color: white;
            padding: 25px;
            position: relative;
            overflow: hidden;
        }
        
        .table-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path d="M0,0 L100,0 L100,100 Z" fill="rgba(255,255,255,0.1)"/></svg>');
            background-size: cover;
        }
        
        .table th {
            border: none;
            font-weight: 700;
            color: var(--primary);
            padding: 20px;
            background: rgba(44, 186, 236, 0.05);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .table td {
            padding: 20px;
            vertical-align: middle;
            border-bottom: 1px solid rgba(44, 186, 236, 0.1);
            transition: all 0.2s ease;
        }
        
        .table tbody tr:hover {
            background: rgba(44, 186, 236, 0.05);
            transform: translateX(5px);
        }
        
        .progress-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .progress {
            flex: 1;
            height: 10px;
            border-radius: 5px;
            background-color: rgba(44, 186, 236, 0.1);
            overflow: hidden;
        }
        
        .progress-bar {
            border-radius: 5px;
            transition: width 1s ease-in-out;
        }
        
        .alumno-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .alumno-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
            box-shadow: 0 5px 15px rgba(44, 186, 236, 0.3);
        }
        
        .badge-status {
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .badge-completed {
            background: linear-gradient(90deg, var(--accent), #6ca839);
            color: white;
        }
        
        .badge-progress {
            background: linear-gradient(90deg, var(--secondary), #f5c15d);
            color: #333;
        }
        
        .badge-new {
            background: linear-gradient(90deg, var(--primary), #2ca5d4);
            color: white;
        }
        
        .badge-pending {
            background: linear-gradient(90deg, #ff6b8b, #ff8ba0);
            color: white;
        }
        
        .btn-action {
            padding: 8px 15px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: none;
            cursor: pointer;
        }
        
        .btn-view {
            background: rgba(44, 186, 236, 0.1);
            color: var(--primary);
            border: 1px solid rgba(44, 186, 236, 0.3);
        }
        
        .btn-view:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(44, 186, 236, 0.3);
        }
        
        .btn-tareas {
            background: rgba(240, 174, 42, 0.1);
            color: var(--secondary);
            border: 1px solid rgba(240, 174, 42, 0.3);
        }
        
        .btn-tareas:hover {
            background: var(--secondary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(240, 174, 42, 0.3);
        }
        
        .btn-calificar {
            background: rgba(131, 191, 70, 0.1);
            color: var(--accent);
            border: 1px solid rgba(131, 191, 70, 0.3);
        }
        
        .btn-calificar:hover {
            background: var(--accent);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(131, 191, 70, 0.3);
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
            margin-bottom: 30px;
            padding: 12px 25px;
            background: rgba(44, 186, 236, 0.1);
            border-radius: 15px;
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            color: white;
            background: var(--primary);
            transform: translateX(-5px);
            box-shadow: 0 8px 20px rgba(44, 186, 236, 0.3);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 30px;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: rgba(44, 186, 236, 0.3);
            margin-bottom: 20px;
        }
        
        .missions-info {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .mission-count {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 10px;
            font-size: 0.85rem;
        }
        
        .mission-completed {
            background: rgba(131, 191, 70, 0.1);
            color: var(--accent);
        }
        
        .mission-pending {
            background: rgba(255, 107, 139, 0.1);
            color: #ff6b8b;
        }
        
        .mission-total {
            background: rgba(44, 186, 236, 0.1);
            color: var(--primary);
        }
        
        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: var(--card-shadow);
        }
        
        .modal-header {
            background: linear-gradient(90deg, var(--primary), var(--accent));
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 25px;
        }
        
        .student-detail {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(44, 186, 236, 0.1);
        }
        
        .student-avatar-lg {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 2rem;
            box-shadow: 0 8px 25px rgba(44, 186, 236, 0.3);
        }
        
        @media (max-width: 768px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .filter-group {
                flex-direction: column;
                align-items: stretch;
            }
            
            .table {
                display: block;
                overflow-x: auto;
            }
            
            .header-section h1 {
                font-size: 2.2rem;
            }
            
            .btn-action {
                padding: 6px 10px;
                font-size: 0.8rem;
            }
        }
        
        .progress-animated {
            animation: progressAnimation 1s ease-out;
        }
        
        @keyframes progressAnimation {
            from { width: 0; }
        }
        
        .edad-badge {
            background: rgba(108, 99, 255, 0.1);
            color: #6c63ff;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .calificacion-badge {
            background: rgba(240, 174, 42, 0.1);
            color: var(--secondary);
            padding: 5px 10px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .alert-tareas-pendientes {
            background: linear-gradient(90deg, rgba(255, 107, 139, 0.1), rgba(255, 107, 139, 0.05));
            border: 1px solid rgba(255, 107, 139, 0.2);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .export-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-bottom: 20px;
        }
        
        .btn-export {
            background: white;
            border: 2px solid var(--primary);
            color: var(--primary);
            border-radius: 12px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-export:hover {
            background: var(--primary);
            color: white;
        }
    </style>
</head>
<body>
    <div class="report-container">
        <!-- Botón para volver -->
        <a href="dashboard_tutor.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Volver al Panel
        </a>
        
        <!-- Encabezado -->
        <div class="header-section">
            <h1>📊 Reporte de Exploradores</h1>
            <p>Sigue el progreso de cada explorador en sus aventuras de aprendizaje. Visualiza, analiza y guía su crecimiento.</p>
        </div>
        
        <!-- Alert de tareas pendientes -->
        <?php if ($stats['tareas_pendientes_totales'] > 0): ?>
        <div class="alert-tareas-pendientes animate__animated animate__fadeIn">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-circle fa-2x me-3" style="color: #ff6b8b;"></i>
                <div>
                    <h5 class="fw-bold mb-1" style="color: #ff6b8b;">¡Tienes tareas pendientes por calificar!</h5>
                    <p class="mb-0">Hay <strong><?php echo $stats['tareas_pendientes_totales']; ?></strong> tareas esperando tu evaluación.</p>
                </div>
                <a href="revisar_entregas.php" class="btn btn-danger ms-auto">
                    <i class="fas fa-star me-2"></i>Ir a Calificar
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Tarjetas de estadísticas -->
        <div class="stats-cards animate__animated animate__fadeInUp">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <h3 class="stat-number"><?php echo $stats['total_alumnos'] ?? 0; ?></h3>
                <p class="text-muted mb-0">Exploradores Activos</p>
                <small class="text-primary">En tus cursos</small>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-book"></i>
                </div>
                <h3 class="stat-number"><?php echo $stats['total_cursos'] ?? 0; ?></h3>
                <p class="text-muted mb-0">Aventuras Activas</p>
                <small class="text-primary">Cursos impartidos</small>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3 class="stat-number"><?php echo round($stats['promedio_progreso'] ?? 0); ?>%</h3>
                <p class="text-muted mb-0">Progreso Promedio</p>
                <small class="text-primary">Entre todos los alumnos</small>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <h3 class="stat-number"><?php echo $stats['completados'] ?? 0; ?></h3>
                <p class="text-muted mb-0">Completados</p>
                <small class="text-accent">¡Felicidades!</small>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="filters-card animate__animated animate__fadeIn">
            <form method="GET" class="filter-group">
                <div style="flex: 1;">
                    <label class="form-label fw-bold">Filtrar por Aventura</label>
                    <select name="curso_id" class="form-select">
                        <option value="0">Todas las Aventuras</option>
                        <?php 
                        mysqli_data_seek($res_cursos, 0); // Reiniciar el puntero del resultado
                        while($curso = mysqli_fetch_assoc($res_cursos)): 
                        ?>
                        <option value="<?php echo $curso['id']; ?>" <?php echo $curso_filtro == $curso['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($curso['nombre']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div style="flex: 1;">
                    <label class="form-label fw-bold">Filtrar por Estado</label>
                    <select name="estado" class="form-select">
                        <option value="">Todos los Estados</option>
                        <option value="completado" <?php echo $estado_filtro == 'completado' ? 'selected' : ''; ?>>✅ Completados (100%)</option>
                        <option value="en_progreso" <?php echo $estado_filtro == 'en_progreso' ? 'selected' : ''; ?>>⏳ En Progreso (1-99%)</option>
                        <option value="nuevo" <?php echo $estado_filtro == 'nuevo' ? 'selected' : ''; ?>>🆕 Nuevos (0%)</option>
                    </select>
                </div>
                
                <div>
                    <button type="submit" class="btn btn-filter">
                        <i class="fas fa-filter me-2"></i>Aplicar Filtros
                    </button>
                    <a href="reporte_alumnos.php" class="btn btn-outline-secondary ms-2">
                        <i class="fas fa-redo me-2"></i>Limpiar
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Tabla de resultados -->
        <div class="table-container animate__animated animate__fadeIn">
            <div class="table-header">
                <h4 class="mb-0 fw-bold"><i class="fas fa-list-ul me-2"></i> Detalle de Exploradores</h4>
                <p class="mb-0 opacity-75">
                    <?php 
                    $total_registros = mysqli_num_rows($res);
                    echo $total_registros . ($total_registros == 1 ? ' registro encontrado' : ' registros encontrados');
                    ?>
                </p>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Explorador</th>
                            <th>Aventura</th>
                            <th>Misiones</th>
                            <th>Progreso</th>
                            <th>Última Calificación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($res) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($res)): 
                                // Calcular edad a partir de fecha_nacimiento
                                $edad = '';
                                if ($row['fecha_nacimiento']) {
                                    $fecha_nac = new DateTime($row['fecha_nacimiento']);
                                    $hoy = new DateTime();
                                    $edad = $hoy->diff($fecha_nac)->y;
                                }
                                
                                // Obtener iniciales para el avatar
                                $iniciales = '';
                                $nombre_parts = explode(' ', $row['alumno_nombre']);
                                if (count($nombre_parts) >= 2) {
                                    $iniciales = strtoupper(substr($nombre_parts[0], 0, 1) . substr($nombre_parts[1], 0, 1));
                                } else {
                                    $iniciales = strtoupper(substr($row['alumno_nombre'], 0, 2));
                                }
                                
                                // Obtener actividades totales del curso
                                $sql_total_actividades = "SELECT COUNT(*) as total FROM actividades WHERE id_curso = " . $row['curso_id'];
                                $res_total = mysqli_query($conn, $sql_total_actividades);
                                $total_actividades = mysqli_fetch_assoc($res_total)['total'];
                                
                                $misiones_completadas = $row['actividades_completadas'] ?? 0;
                                $misiones_pendientes = $total_actividades - $misiones_completadas;
                                
                                // Determinar color de progreso
                                $porcentaje = $row['porcentaje'] ?? 0;
                                $progress_color = $porcentaje >= 100 ? 'bg-success' : 
                                                 ($porcentaje >= 70 ? 'bg-info' : 
                                                 ($porcentaje >= 40 ? 'bg-warning' : 'bg-primary'));
                                
                                // Determinar badge de estado
                                $estado_badge = '';
                                if ($porcentaje >= 100) {
                                    $estado_badge = 'badge-completed';
                                    $estado_text = '🏆 Completado';
                                } elseif ($porcentaje > 0) {
                                    $estado_badge = 'badge-progress';
                                    $estado_text = '⏳ En progreso';
                                } else {
                                    $estado_badge = 'badge-new';
                                    $estado_text = '🆕 Nuevo';
                                }
                            ?>
                            <tr>
                                <td>
                                    <div class="alumno-info">
                                        <div class="alumno-avatar">
                                            <?php echo $iniciales; ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($row['alumno_nombre']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($row['alumno_email']); ?></small>
                                            <div class="mt-1">
                                                <?php if($edad): ?>
                                                <span class="edad-badge me-2"><?php echo $edad; ?> años</span>
                                                <?php endif; ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar-alt me-1"></i>
                                                    Inscrito: <?php echo date('d/m/Y', strtotime($row['fecha_inscripcion'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="fw-bold">
                                    <div><?php echo htmlspecialchars($row['curso_nombre']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($row['curso_nivel']); ?></small>
                                </td>
                                <td>
                                    <div class="missions-info">
                                        <div class="mission-count mission-completed">
                                            <i class="fas fa-check-circle"></i>
                                            <span><?php echo $misiones_completadas; ?></span>
                                        </div>
                                        <div class="mission-count mission-pending">
                                            <i class="fas fa-clock"></i>
                                            <span><?php echo $misiones_pendientes; ?></span>
                                        </div>
                                        <div class="mission-count mission-total">
                                            <i class="fas fa-bullseye"></i>
                                            <span><?php echo $total_actividades; ?></span>
                                        </div>
                                    </div>
                                    <?php if($row['tareas_pendientes'] > 0): ?>
                                    <small class="text-danger d-block mt-1">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <?php echo $row['tareas_pendientes']; ?> tarea(s) pendiente(s)
                                    </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="progress-container">
                                        <div class="progress">
                                            <div class="progress-bar progress-animated <?php echo $progress_color; ?>" 
                                                 style="width: <?php echo $porcentaje; ?>%"
                                                 role="progressbar"
                                                 aria-valuenow="<?php echo $porcentaje; ?>"
                                                 aria-valuemin="0"
                                                 aria-valuemax="100">
                                            </div>
                                        </div>
                                        <span class="fw-bold"><?php echo round($porcentaje); ?>%</span>
                                    </div>
                                    <span class="badge <?php echo $estado_badge; ?> badge-status mt-1">
                                        <?php echo $estado_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($row['ultima_calificacion']): ?>
                                    <div class="calificacion-badge">
                                        <i class="fas fa-star text-warning me-1"></i>
                                        <?php echo number_format($row['ultima_calificacion'], 1); ?>/10
                                    </div>
                                    <?php else: ?>
                                    <span class="text-muted">Sin calificaciones</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <a href="detalle_alumno.php?alumno_id=<?php echo $row['alumno_id']; ?>&curso_id=<?php echo $row['curso_id']; ?>" 
                                           class="btn-action btn-view">
                                            <i class="fas fa-eye"></i> Ver
                                        </a>
                                        <?php if($row['tareas_pendientes'] > 0): ?>
                                        <a href="revisar_entregas.php?alumno_id=<?php echo $row['alumno_id']; ?>&curso_id=<?php echo $row['curso_id']; ?>" 
                                           class="btn-action btn-calificar">
                                            <i class="fas fa-star"></i> Calificar (<?php echo $row['tareas_pendientes']; ?>)
                                        </a>
                                        <?php endif; ?>
                                        <a href="detalle_entregas.php?alumno_id=<?php echo $row['alumno_id']; ?>&curso_id=<?php echo $row['curso_id']; ?>" 
                                           class="btn-action btn-tareas">
                                            <i class="fas fa-tasks"></i> Tareas
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <i class="fas fa-user-graduate"></i>
                                        <h4 class="text-muted mb-3">No hay exploradores registrados</h4>
                                        <p class="text-muted mb-4">
                                            <?php if($curso_filtro > 0 || $estado_filtro != ''): ?>
                                            No se encontraron alumnos con los filtros aplicados.
                                            <?php else: ?>
                                            Los alumnos aparecerán aquí cuando se inscriban en tus cursos.
                                            <?php endif; ?>
                                        </p>
                                        <?php if($curso_filtro > 0 || $estado_filtro != ''): ?>
                                        <a href="reporte_alumnos.php" class="btn btn-primary">
                                            <i class="fas fa-redo me-2"></i>Limpiar filtros
                                        </a>
                                        <?php else: ?>
                                        <a href="dashboard_tutor.php" class="btn btn-primary">
                                            <i class="fas fa-rocket me-2"></i>Volver al Panel
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Animaciones de progreso
        document.addEventListener('DOMContentLoaded', function() {
            const progressBars = document.querySelectorAll('.progress-bar');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0';
                setTimeout(() => {
                    bar.style.width = width;
                }, 300);
            });
            
            // Inicializar tooltips de Bootstrap si los hubiera
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Efecto de resaltado para filas con tareas pendientes
            document.querySelectorAll('tr').forEach(row => {
                const pendingCount = row.querySelector('.text-danger');
                if (pendingCount) {
                    row.style.borderLeft = '3px solid #ff6b8b';
                    row.addEventListener('mouseenter', () => {
                        row.style.backgroundColor = 'rgba(255, 107, 139, 0.05)';
                    });
                    row.addEventListener('mouseleave', () => {
                        row.style.backgroundColor = '';
                    });
                }
            });
        });
        
        // Función para exportar a PDF
        function exportToPDF() {
            alert('La función de exportación a PDF está en desarrollo. Por ahora, puedes usar la función de impresión del navegador.');
            window.print();
        }
        
        // Función para exportar a Excel
        function exportToExcel() {
            alert('La función de exportación a Excel está en desarrollo. Próximamente disponible.');
        }
        
        // Filtrar tabla con búsqueda en tiempo real (si se implementara)
        function filterTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const table = document.querySelector('.table');
            const tr = table.getElementsByTagName('tr');
            
            for (let i = 0; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName('td')[0];
                if (td) {
                    const txtValue = td.textContent || td.innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = '';
                    } else {
                        tr[i].style.display = 'none';
                    }
                }
            }
        }
    </script>
</body>
</html>