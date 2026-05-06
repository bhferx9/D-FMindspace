<?php
include 'php/config.php';
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['tipo'], ['tutor', 'alumno', 'padre'])) {
    header("Location: index.php");
    exit();
}

$usuario_id = $_SESSION['user_id'];
$usuario_tipo = $_SESSION['tipo'];
$entrega_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($entrega_id == 0) {
    header("Location: dashboard_" . $usuario_tipo . ".php");
    exit();
}

// Obtener información de la entrega con validación de permisos
$sql_entrega = "SELECT 
                    e.*,
                    u.nombre as alumno_nombre,
                    u.email as alumno_email,
                    u.id as alumno_id,
                    a.titulo as actividad_titulo,
                    a.descripcion as actividad_descripcion,
                    a.tipo as actividad_tipo,
                    a.dificultad,
                    a.fecha_limite,
                    a.puntos as puntos_actividad,
                    a.id_curso,
                    c.nombre as curso_nombre,
                    c.id_tutor,
                    c.descripcion as curso_descripcion,
                    ev.calificacion,
                    ev.comentarios as comentarios_tutor,
                    ev.fecha_evaluacion,
                    tu.nombre as tutor_nombre,
                    DATEDIFF(a.fecha_limite, CURDATE()) as dias_restantes
                FROM entregas e
                JOIN usuarios u ON e.id_alumno = u.id
                JOIN actividades a ON e.id_actividad = a.id
                JOIN cursos c ON a.id_curso = c.id
                LEFT JOIN evaluaciones ev ON e.id = ev.id_entrega
                LEFT JOIN usuarios tu ON c.id_tutor = tu.id
                WHERE e.id = '$entrega_id'";

$res_entrega = mysqli_query($conn, $sql_entrega);

if (mysqli_num_rows($res_entrega) == 0) {
    header("Location: dashboard_" . $usuario_tipo . ".php");
    exit();
}

$entrega = mysqli_fetch_assoc($res_entrega);

// Verificar permisos según el tipo de usuario
$tiene_permiso = false;

switch ($usuario_tipo) {
    case 'tutor':
        // Tutor solo puede ver entregas de sus propios cursos
        if ($entrega['id_tutor'] == $usuario_id) {
            $tiene_permiso = true;
        }
        break;
        
    case 'alumno':
        // Alumno solo puede ver sus propias entregas
        if ($entrega['alumno_id'] == $usuario_id) {
            $tiene_permiso = true;
        }
        break;
        
    case 'padre':
        // Padre puede ver entregas de su hijo vinculado
        $sql_hijo = "SELECT id_hijo_vinculado FROM usuarios WHERE id = '$usuario_id'";
        $res_hijo = mysqli_query($conn, $sql_hijo);
        if ($res_hijo && mysqli_fetch_assoc($res_hijo)['id_hijo_vinculado'] == $entrega['alumno_id']) {
            $tiene_permiso = true;
        }
        break;
}

if (!$tiene_permiso) {
    header("Location: dashboard_" . $usuario_tipo . ".php");
    exit();
}

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
    return $fecha_obj->format('d/m/Y H:i');
}

// Función para formatear diferencia de tiempo
function formatTiempoTranscurrido($fecha) {
    if (!$fecha) return '';
    
    $fecha_entrega = new DateTime($fecha);
    $ahora = new DateTime();
    $diferencia = $fecha_entrega->diff($ahora);
    
    if ($diferencia->y > 0) return 'hace ' . $diferencia->y . ' año' . ($diferencia->y > 1 ? 's' : '');
    if ($diferencia->m > 0) return 'hace ' . $diferencia->m . ' mes' . ($diferencia->m > 1 ? 'es' : '');
    if ($diferencia->d > 0) return 'hace ' . $diferencia->d . ' día' . ($diferencia->d > 1 ? 's' : '');
    if ($diferencia->h > 0) return 'hace ' . $diferencia->h . ' hora' . ($diferencia->h > 1 ? 's' : '');
    if ($diferencia->i > 0) return 'hace ' . $diferencia->i . ' minuto' . ($diferencia->i > 1 ? 's' : '');
    return 'hace unos segundos';
}

// Verificar si la entrega está vencida
$hoy = new DateTime();
$fecha_limite = new DateTime($entrega['fecha_limite']);
$esta_vencida = $fecha_limite < $hoy && $entrega['estado'] == 'pendiente';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📄 Ver Entrega - D&F Mindspace</title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
            opacity: 0;
            animation: fadeIn 0.6s ease-out forwards;
        }
        
        @keyframes fadeIn {
            to { opacity: 1; }
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
        
        .delivery-container {
            background: white;
            border-radius: 25px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .delivery-header {
            background: linear-gradient(90deg, rgba(44, 186, 236, 0.9), rgba(131, 191, 70, 0.9));
            color: white;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .delivery-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path d="M0,0 L100,0 L100,100 Z" fill="rgba(255,255,255,0.1)"/></svg>');
            background-size: cover;
        }
        
        .header-content {
            position: relative;
            z-index: 1;
        }
        
        .delivery-title {
            font-size: 2.5rem;
            font-weight: 900;
            margin-bottom: 15px;
            line-height: 1.3;
        }
        
        .delivery-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 25px;
            line-height: 1.6;
        }
        
        .header-meta {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            margin-top: 25px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .meta-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .meta-text {
            display: flex;
            flex-direction: column;
        }
        
        .meta-label {
            font-size: 0.85rem;
            opacity: 0.8;
        }
        
        .meta-value {
            font-size: 1.1rem;
            font-weight: 700;
        }
        
        .delivery-body {
            padding: 40px;
        }
        
        .section-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
            border: 2px solid rgba(44, 186, 236, 0.1);
            transition: all 0.3s ease;
        }
        
        .section-card:hover {
            border-color: var(--primary);
            box-shadow: 0 15px 35px rgba(44, 186, 236, 0.2);
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 15px;
            border-bottom: 3px solid rgba(44, 186, 236, 0.1);
        }
        
        .badge-custom {
            padding: 8px 16px;
            border-radius: 15px;
            font-weight: 700;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .badge-estado {
            background: linear-gradient(135deg, rgba(240, 174, 42, 0.15), rgba(240, 174, 42, 0.1));
            color: var(--secondary);
            border: 2px solid rgba(240, 174, 42, 0.3);
        }
        
        .badge-calificado {
            background: linear-gradient(135deg, rgba(131, 191, 70, 0.15), rgba(131, 191, 70, 0.1));
            color: var(--accent);
            border: 2px solid rgba(131, 191, 70, 0.3);
        }
        
        .badge-tipo {
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.15), rgba(44, 186, 236, 0.1));
            color: var(--primary);
            border: 2px solid rgba(44, 186, 236, 0.2);
        }
        
        .badge-dificultad {
            background: linear-gradient(135deg, rgba(156, 136, 255, 0.15), rgba(156, 136, 255, 0.1));
            color: #9c88ff;
            border: 2px solid rgba(156, 136, 255, 0.3);
        }
        
        .badge-vencida {
            background: linear-gradient(135deg, rgba(255, 107, 139, 0.15), rgba(255, 107, 139, 0.1));
            color: var(--danger);
            border: 2px solid rgba(255, 107, 139, 0.3);
        }
        
        .alumno-info {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .alumno-avatar {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
            box-shadow: 0 10px 25px rgba(44, 186, 236, 0.3);
            flex-shrink: 0;
        }
        
        .alumno-details {
            flex: 1;
        }
        
        .alumno-name {
            font-size: 1.8rem;
            font-weight: 900;
            color: #222;
            margin-bottom: 5px;
        }
        
        .alumno-email {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .curso-info {
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.05), rgba(44, 186, 236, 0.02));
            border-radius: 15px;
            padding: 20px;
            margin-top: 15px;
            border-left: 5px solid var(--primary);
        }
        
        .curso-title {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 5px;
        }
        
        .descripcion-box {
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.05), rgba(44, 186, 236, 0.02));
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
            border-left: 5px solid var(--accent);
        }
        
        .descripcion-text {
            font-size: 1.1rem;
            line-height: 1.7;
            color: #444;
        }
        
        .respuesta-box {
            background: linear-gradient(135deg, rgba(131, 191, 70, 0.05), rgba(131, 191, 70, 0.02));
            border-radius: 15px;
            padding: 30px;
            margin: 30px 0;
            border: 2px solid rgba(131, 191, 70, 0.1);
            position: relative;
        }
        
        .respuesta-box::before {
            content: '📝 Respuesta del Alumno';
            position: absolute;
            top: -15px;
            left: 20px;
            background: white;
            padding: 5px 15px;
            border-radius: 10px;
            font-weight: 700;
            color: var(--accent);
            font-size: 0.9rem;
            border: 2px solid rgba(131, 191, 70, 0.2);
        }
        
        .respuesta-text {
            font-size: 1.1rem;
            line-height: 1.8;
            color: #333;
            white-space: pre-wrap;
        }
        
        .file-section {
            background: linear-gradient(135deg, rgba(240, 174, 42, 0.05), rgba(240, 174, 42, 0.02));
            border-radius: 15px;
            padding: 25px;
            margin: 30px 0;
            border: 2px solid rgba(240, 174, 42, 0.1);
        }
        
        .file-preview {
            border: 2px dashed rgba(240, 174, 42, 0.3);
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            margin-top: 20px;
        }
        
        .file-icon {
            font-size: 4rem;
            color: var(--secondary);
            margin-bottom: 20px;
        }
        
        .file-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: #222;
            margin-bottom: 10px;
            word-break: break-all;
        }
        
        .file-size {
            color: #666;
            margin-bottom: 20px;
        }
        
        .btn-download {
            background: linear-gradient(90deg, var(--secondary), #f5c15d);
            border: none;
            border-radius: 15px;
            padding: 12px 30px;
            color: white;
            font-weight: 700;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-download:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(240, 174, 42, 0.3);
            color: white;
        }
        
        .evaluacion-section {
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.05), rgba(44, 186, 236, 0.02));
            border-radius: 20px;
            padding: 30px;
            margin: 30px 0;
            border: 2px solid rgba(44, 186, 236, 0.1);
        }
        
        .calificacion-display {
            text-align: center;
            padding: 30px;
        }
        
        .calificacion-numero {
            font-size: 4rem;
            font-weight: 900;
            line-height: 1;
            margin-bottom: 10px;
        }
        
        .calificacion-texto {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 25px;
        }
        
        .comentarios-box {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-top: 25px;
            border: 2px solid rgba(44, 186, 236, 0.1);
        }
        
        .tutor-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .tutor-avatar {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            background: linear-gradient(135deg, var(--primary), #2ca5d4);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.5rem;
            box-shadow: 0 8px 20px rgba(44, 186, 236, 0.3);
        }
        
        .tutor-details {
            flex: 1;
        }
        
        .tutor-name {
            font-size: 1.3rem;
            font-weight: 800;
            color: #222;
        }
        
        .tutor-role {
            color: var(--primary);
            font-size: 0.95rem;
            font-weight: 600;
        }
        
        .fecha-evaluacion {
            text-align: right;
            color: #666;
            font-size: 0.9rem;
            margin-top: 15px;
        }
        
        .action-buttons {
            display: flex;
            gap: 20px;
            margin-top: 40px;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 15px 30px;
            border-radius: 15px;
            font-weight: 700;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            border: none;
            cursor: pointer;
            flex: 1;
            min-width: 200px;
            justify-content: center;
        }
        
        .btn-primary-custom {
            background: linear-gradient(90deg, var(--primary), #2ca5d4);
            color: white;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(44, 186, 236, 0.3);
        }
        
        .btn-warning-custom {
            background: linear-gradient(90deg, var(--secondary), #f5c15d);
            color: white;
        }
        
        .btn-warning-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(240, 174, 42, 0.3);
        }
        
        .btn-success-custom {
            background: linear-gradient(90deg, var(--accent), #6aab39);
            color: white;
        }
        
        .btn-success-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(131, 191, 70, 0.3);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 30px;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            color: rgba(44, 186, 236, 0.3);
            margin-bottom: 25px;
        }
        
        @media (max-width: 768px) {
            .container-custom {
                padding: 20px 15px;
            }
            
            .delivery-header {
                padding: 30px 20px;
            }
            
            .delivery-title {
                font-size: 2rem;
            }
            
            .delivery-body {
                padding: 25px;
            }
            
            .header-meta {
                flex-direction: column;
                gap: 15px;
            }
            
            .alumno-info {
                flex-direction: column;
                text-align: center;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-action {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container-custom">
        <!-- Botón para volver -->
        <?php 
        $url_volver = '';
        switch ($usuario_tipo) {
            case 'tutor':
                $url_volver = "detalle_alumno.php?alumno_id=" . $entrega['alumno_id'] . "&curso_id=" . $entrega['id_curso'];
                break;
            case 'alumno':
                $url_volver = "dashboard_alumno.php";
                break;
            case 'padre':
                $url_volver = "dashboard_padre.php";
                break;
        }
        ?>
        <a href="<?php echo $url_volver; ?>" class="back-link animate__animated animate__fadeInLeft">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
        
        <!-- Contenedor principal -->
        <div class="delivery-container animate__animated animate__fadeInUp">
            <!-- Encabezado -->
            <div class="delivery-header">
                <div class="header-content">
                    <h1 class="delivery-title"><?php echo htmlspecialchars($entrega['actividad_titulo']); ?></h1>
                    <p class="delivery-subtitle">Entrega realizada por <?php echo htmlspecialchars($entrega['alumno_nombre']); ?> en el curso <?php echo htmlspecialchars($entrega['curso_nombre']); ?></p>
                    
                    <div class="header-meta">
                        <div class="meta-item">
                            <div class="meta-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="meta-text">
                                <span class="meta-label">Fecha de Entrega</span>
                                <span class="meta-value"><?php echo formatFecha($entrega['fecha_entrega']); ?></span>
                            </div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="meta-text">
                                <span class="meta-label">Estado</span>
                                <span class="meta-value"><?php echo ucfirst($entrega['estado']); ?></span>
                            </div>
                        </div>
                        
                        <div class="meta-item">
                            <div class="meta-icon">
                                <i class="fas fa-trophy"></i>
                            </div>
                            <div class="meta-text">
                                <span class="meta-label">Puntos</span>
                                <span class="meta-value"><?php echo $entrega['puntos_actividad']; ?> XP</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Cuerpo de la entrega -->
            <div class="delivery-body">
                <!-- Información del alumno -->
                <div class="section-card">
                    <h2 class="section-title">
                        <i class="fas fa-user-graduate"></i>
                        Información del Explorador
                    </h2>
                    
                    <div class="alumno-info">
                        <div class="alumno-avatar">
                            <?php 
                            $nombres = explode(' ', $entrega['alumno_nombre']);
                            $iniciales = '';
                            foreach ($nombres as $nombre) {
                                $iniciales .= strtoupper(substr($nombre, 0, 1));
                                if (strlen($iniciales) >= 2) break;
                            }
                            echo $iniciales ?: '??';
                            ?>
                        </div>
                        <div class="alumno-details">
                            <h3 class="alumno-name"><?php echo htmlspecialchars($entrega['alumno_nombre']); ?></h3>
                            <div class="alumno-email">
                                <i class="fas fa-envelope"></i>
                                <?php echo htmlspecialchars($entrega['alumno_email']); ?>
                            </div>
                            
                            <div class="curso-info">
                                <h4 class="curso-title">
                                    <i class="fas fa-book me-2"></i>
                                    <?php echo htmlspecialchars($entrega['curso_nombre']); ?>
                                </h4>
                                <?php if(!empty($entrega['curso_descripcion'])): ?>
                                    <p class="mb-0"><?php echo htmlspecialchars($entrega['curso_descripcion']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Información de la actividad -->
                <div class="section-card">
                    <h2 class="section-title">
                        <i class="fas fa-tasks"></i>
                        Detalles de la Actividad
                    </h2>
                    
                    <div class="mb-4">
                        <div class="d-flex gap-3 mb-3 flex-wrap">
                            <span class="badge-custom badge-tipo">
                                <i class="<?php echo str_replace('fas fa-', '', getActividadIcono($entrega['actividad_tipo'])); ?>"></i>
                                <?php echo htmlspecialchars($entrega['actividad_tipo']); ?>
                            </span>
                            
                            <span class="badge-custom badge-dificultad">
                                <i class="fas fa-signal"></i>
                                <?php echo htmlspecialchars($entrega['dificultad']); ?>
                            </span>
                            
                            <span class="badge-custom" style="background: linear-gradient(135deg, rgba(240, 174, 42, 0.15), rgba(240, 174, 42, 0.1)); color: var(--secondary); border: 2px solid rgba(240, 174, 42, 0.3);">
                                <i class="fas fa-trophy"></i>
                                <?php echo $entrega['puntos_actividad']; ?> XP
                            </span>
                            
                            <?php if($entrega['estado'] == 'pendiente'): ?>
                                <span class="badge-custom badge-estado">
                                    <i class="fas fa-clock"></i>
                                    <?php echo ucfirst($entrega['estado']); ?>
                                </span>
                            <?php else: ?>
                                <span class="badge-custom badge-calificado">
                                    <i class="fas fa-star"></i>
                                    <?php echo ucfirst($entrega['estado']); ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if($esta_vencida): ?>
                                <span class="badge-custom badge-vencida">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Vencida
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="descripcion-box">
                            <h4 class="mb-3" style="color: var(--accent);">
                                <i class="fas fa-align-left me-2"></i>Descripción de la Actividad
                            </h4>
                            <p class="descripcion-text">
                                <?php echo nl2br(htmlspecialchars($entrega['actividad_descripcion'])); ?>
                            </p>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <i class="fas fa-calendar-day text-primary"></i>
                                    <div>
                                        <div class="fw-bold">Fecha Límite</div>
                                        <div class="text-muted"><?php echo formatFecha($entrega['fecha_limite']); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <i class="fas fa-hourglass-half text-warning"></i>
                                    <div>
                                        <div class="fw-bold">Tiempo Transcurrido</div>
                                        <div class="text-muted"><?php echo formatTiempoTranscurrido($entrega['fecha_entrega']); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Contenido de la entrega -->
                <div class="section-card">
                    <h2 class="section-title">
                        <i class="fas fa-paper-plane"></i>
                        Contenido de la Entrega
                    </h2>
                    
                    <?php if(!empty($entrega['respuesta'])): ?>
                        <div class="respuesta-box">
                            <p class="respuesta-text"><?php echo nl2br(htmlspecialchars($entrega['respuesta'])); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(!empty($entrega['archivo'])): ?>
                        <div class="file-section">
                            <h4 class="mb-4" style="color: var(--secondary);">
                                <i class="fas fa-paperclip me-2"></i>Archivo Adjunto
                            </h4>
                            
                            <div class="file-preview">
                                <div class="file-icon">
                                    <?php 
                                    $extension = strtolower(pathinfo($entrega['archivo'], PATHINFO_EXTENSION));
                                    $iconos_archivos = [
                                        'pdf' => 'fas fa-file-pdf',
                                        'doc' => 'fas fa-file-word',
                                        'docx' => 'fas fa-file-word',
                                        'txt' => 'fas fa-file-alt',
                                        'jpg' => 'fas fa-file-image',
                                        'jpeg' => 'fas fa-file-image',
                                        'png' => 'fas fa-file-image',
                                        'zip' => 'fas fa-file-archive',
                                        'rar' => 'fas fa-file-archive',
                                        'mp4' => 'fas fa-file-video',
                                        'mov' => 'fas fa-file-video'
                                    ];
                                    $icono_archivo = $iconos_archivos[$extension] ?? 'fas fa-file';
                                    ?>
                                    <i class="<?php echo $icono_archivo; ?>"></i>
                                </div>
                                <div class="file-name"><?php echo htmlspecialchars($entrega['archivo']); ?></div>
                                <div class="file-size">
                                    <?php 
                                    $ruta_archivo = 'uploads/' . $entrega['archivo'];
                                    if (file_exists($ruta_archivo)) {
                                        $tamanio = filesize($ruta_archivo);
                                        if ($tamanio < 1024) {
                                            echo $tamanio . ' bytes';
                                        } elseif ($tamanio < 1048576) {
                                            echo round($tamanio / 1024, 2) . ' KB';
                                        } else {
                                            echo round($tamanio / 1048576, 2) . ' MB';
                                        }
                                    }
                                    ?>
                                </div>
                                <a href="<?php echo $ruta_archivo; ?>" class="btn-download" download>
                                    <i class="fas fa-download"></i> Descargar Archivo
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(empty($entrega['respuesta']) && empty($entrega['archivo'])): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-inbox"></i>
                            </div>
                            <h4 class="mb-3">Sin contenido</h4>
                            <p class="text-muted">Esta entrega no contiene texto ni archivos adjuntos.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Evaluación (si existe) -->
                <?php if($entrega['estado'] == 'calificado' && $entrega['calificacion'] !== null): ?>
                    <div class="section-card">
                        <h2 class="section-title">
                            <i class="fas fa-star"></i>
                            Evaluación
                        </h2>
                        
                        <div class="evaluacion-section">
                            <?php
                            $calificacion_color = getCalificacionColor($entrega['calificacion']);
                            $calificacion_texto = getCalificacionTexto($entrega['calificacion']);
                            ?>
                            
                            <div class="calificacion-display">
                                <div class="calificacion-numero text-<?php echo $calificacion_color; ?>">
                                    <?php echo number_format($entrega['calificacion'], 1); ?>
                                </div>
                                <div class="calificacion-texto text-<?php echo $calificacion_color; ?>">
                                    <?php echo $calificacion_texto; ?>
                                </div>
                                
                                <?php if(!empty($entrega['comentarios_tutor'])): ?>
                                    <div class="comentarios-box">
                                        <div class="tutor-info">
                                            <div class="tutor-avatar">
                                                <?php 
                                                $nombres_tutor = explode(' ', $entrega['tutor_nombre']);
                                                $iniciales_tutor = '';
                                                foreach ($nombres_tutor as $nombre) {
                                                    $iniciales_tutor .= strtoupper(substr($nombre, 0, 1));
                                                    if (strlen($iniciales_tutor) >= 2) break;
                                                }
                                                echo $iniciales_tutor ?: '??';
                                                ?>
                                            </div>
                                            <div class="tutor-details">
                                                <div class="tutor-name"><?php echo htmlspecialchars($entrega['tutor_nombre']); ?></div>
                                                <div class="tutor-role">Tutor del Curso</div>
                                            </div>
                                        </div>
                                        
                                        <h5 class="mb-3">Comentarios:</h5>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($entrega['comentarios_tutor'])); ?></p>
                                        
                                        <?php if($entrega['fecha_evaluacion']): ?>
                                            <div class="fecha-evaluacion">
                                                <i class="fas fa-calendar-check me-1"></i>
                                                <?php echo formatFecha($entrega['fecha_evaluacion']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Botones de acción -->
        <div class="action-buttons animate__animated animate__fadeInUp">
            <?php if($usuario_tipo == 'tutor' && $entrega['estado'] == 'pendiente'): ?>
                <a href="revisar_entrega.php?entrega_id=<?php echo $entrega_id; ?>" 
                   class="btn-action btn-warning-custom">
                    <i class="fas fa-star"></i> Calificar Esta Entrega
                </a>
            <?php elseif($usuario_tipo == 'tutor' && $entrega['estado'] == 'calificado'): ?>
                <a href="revisar_entrega.php?entrega_id=<?php echo $entrega_id; ?>&modificar=true" 
                   class="btn-action btn-success-custom">
                    <i class="fas fa-edit"></i> Modificar Calificación
                </a>
            <?php endif; ?>
            
            <?php if($usuario_tipo == 'tutor'): ?>
                <a href="detalle_alumno.php?alumno_id=<?php echo $entrega['alumno_id']; ?>&curso_id=<?php echo $entrega['id_curso']; ?>" 
                   class="btn-action btn-primary-custom">
                    <i class="fas fa-list"></i> Ver Todas las Tareas
                </a>
            <?php endif; ?>
            
            <button onclick="window.print()" class="btn-action" style="background: linear-gradient(90deg, #6c757d, #5a6268); color: white;">
                <i class="fas fa-print"></i> Imprimir Entrega
            </button>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Configurar tooltips si los hubiera
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Resaltar elementos importantes
            const estadoBadge = document.querySelector('.badge-estado, .badge-calificado');
            if (estadoBadge) {
                estadoBadge.style.animation = 'pulse 2s infinite';
            }
            
            // Mostrar alerta si está vencida
            const vencidaBadge = document.querySelector('.badge-vencida');
            if (vencidaBadge) {
                setTimeout(() => {
                    vencidaBadge.style.boxShadow = '0 0 15px rgba(255, 107, 139, 0.5)';
                    setTimeout(() => {
                        vencidaBadge.style.boxShadow = 'none';
                    }, 1000);
                }, 1000);
            }
        });
        
        // Animación de pulso
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); }
                100% { transform: scale(1); }
            }
        `;
        document.head.appendChild(style);
        
        // Función para copiar información al portapapeles
        function copiarInformacion() {
            const info = {
                titulo: document.querySelector('.delivery-title').textContent,
                alumno: document.querySelector('.alumno-name').textContent,
                curso: document.querySelector('.curso-title').textContent,
                fecha: document.querySelector('.meta-value:nth-child(2)').textContent,
                estado: document.querySelector('.meta-value:nth-child(4)').textContent
            };
            
            const texto = `Título: ${info.titulo}\nAlumno: ${info.alumno}\nCurso: ${info.curso}\nFecha: ${info.fecha}\nEstado: ${info.estado}`;
            
            navigator.clipboard.writeText(texto).then(() => {
                alert('Información copiada al portapapeles');
            });
        }
        
        // Función para compartir (si se implementara)
        function compartirEntrega() {
            if (navigator.share) {
                navigator.share({
                    title: document.querySelector('.delivery-title').textContent,
                    text: 'Mira esta entrega en D&F Mindspace',
                    url: window.location.href
                });
            } else {
                alert('La función de compartir no está disponible en este navegador');
            }
        }
    </script>
</body>
</html>