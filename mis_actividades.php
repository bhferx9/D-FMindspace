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
$curso_filtro = isset($_GET['curso']) ? $_GET['curso'] : null;

// Consultar todas las actividades del alumno
$query_actividades = "SELECT 
    a.id as actividad_id,
    a.titulo,
    a.descripcion,
    a.tipo,
    a.dificultad,
    a.fecha_limite,
    a.puntos,
    c.id as curso_id,
    c.nombre as curso_nombre,
    e.id as entrega_id,
    e.respuesta,
    e.archivo,
    e.fecha_entrega,
    e.estado as estado_entrega,
    ev.calificacion,
    ev.comentarios,
    ev.fecha_evaluacion,
    u.nombre as tutor_nombre
FROM actividades a
JOIN cursos c ON a.id_curso = c.id
LEFT JOIN entregas e ON a.id = e.id_actividad AND e.id_alumno = '$alumno_id'
LEFT JOIN evaluaciones ev ON e.id = ev.id_entrega
LEFT JOIN usuarios u ON ev.id_tutor = u.id
WHERE c.id IN (
    SELECT id_curso FROM inscripciones 
    WHERE id_alumno = '$alumno_id' AND estado = 'activo'
)";

// Aplicar filtro por curso si existe
if ($curso_filtro) {
    $query_actividades .= " AND c.id = '$curso_filtro'";
}

$query_actividades .= " ORDER BY a.fecha_limite ASC, c.nombre ASC";
$res_actividades = mysqli_query($conn, $query_actividades);

// Contar actividades por estado
$total_actividades = 0;
$pendientes = 0;
$entregadas = 0;
$calificadas = 0;
$calificacion_promedio = 0;
$total_calificacion = 0;
$cont_calificadas = 0;

// Almacenar todas las actividades para usarlas en el modal
$actividades_data = [];
if (mysqli_num_rows($res_actividades) > 0) {
    mysqli_data_seek($res_actividades, 0);
    while ($act = mysqli_fetch_assoc($res_actividades)) {
        $total_actividades++;
        $actividades_data[] = $act; // Almacenar para usar en el modal
        
        if (!$act['entrega_id']) {
            $pendientes++;
        } elseif ($act['estado_entrega'] == 'pendiente') {
            $pendientes++;
        } elseif ($act['estado_entrega'] == 'entregado') {
            $entregadas++;
        } elseif ($act['estado_entrega'] == 'calificado') {
            $calificadas++;
            if ($act['calificacion']) {
                $total_calificacion += $act['calificacion'];
                $cont_calificadas++;
            }
        }
    }
    
    if ($cont_calificadas > 0) {
        $calificacion_promedio = round($total_calificacion / $cont_calificadas, 1);
    }
}

// Obtener lista de cursos para filtro
$query_cursos = "SELECT DISTINCT c.id, c.nombre 
                 FROM actividades a
                 JOIN cursos c ON a.id_curso = c.id
                 WHERE c.id IN (
                     SELECT id_curso FROM inscripciones 
                     WHERE id_alumno = '$alumno_id' AND estado = 'activo'
                 )
                 ORDER BY c.nombre";
$res_cursos = mysqli_query($conn, $query_cursos);

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
$avatar_nombre = ucfirst($avatar_key);

// Función para formatear fecha
function formatDate($date) {
    if (!$date) return 'Sin fecha límite';
    return date('d/m/Y', strtotime($date));
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Misiones - D&F Mindspace</title>
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
        
        /* Encabezado de página */
        .page-header {
            background: linear-gradient(135deg, #ffffff, #f8fdff);
            border-radius: 25px;
            padding: 30px;
            margin-bottom: 30px;
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
            font-size: 2.5rem;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }
        
        /* Filtros */
        .filter-container {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
            border: 3px solid rgba(44, 186, 236, 0.1);
        }
        
        .filter-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }
        
        .filter-badge {
            padding: 8px 15px;
            border-radius: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .filter-badge:hover {
            transform: translateY(-3px);
        }
        
        .filter-badge.active {
            border-color: var(--primary);
            box-shadow: 0 5px 15px rgba(44, 186, 236, 0.2);
        }
        
        /* Estadísticas */
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
        }
        
        .stat-card:hover {
            transform: translateY(-10px);
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
            font-size: 2.5rem;
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
        
        /* Lista de actividades */
        .activities-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .activity-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: all 0.4s ease;
            border: 3px solid transparent;
        }
        
        .activity-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 50px rgba(44, 186, 236, 0.25);
        }
        
        .activity-header {
            padding: 20px 25px;
            border-bottom: 2px solid rgba(44, 186, 236, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .activity-course {
            font-size: 0.9rem;
            color: var(--primary);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .activity-status-badge {
            padding: 6px 15px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .activity-body {
            padding: 25px;
        }
        
        .activity-title {
            font-family: 'Fredoka One', cursive;
            font-size: 1.6rem;
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .activity-meta {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            font-size: 0.95rem;
        }
        
        .meta-item i {
            color: var(--primary);
        }
        
        .activity-description {
            color: #666;
            line-height: 1.6;
            margin-bottom: 25px;
            max-height: 60px;
            overflow: hidden;
            position: relative;
        }
        
        .activity-description:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 20px;
            background: linear-gradient(transparent, white);
        }
        
        .activity-footer {
            padding: 20px 25px;
            background: rgba(44, 186, 236, 0.05);
            border-top: 2px solid rgba(44, 186, 236, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .calificacion-box {
            background: white;
            padding: 15px;
            border-radius: 15px;
            border-left: 4px solid var(--accent);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            flex: 1;
            max-width: 300px;
        }
        
        .calificacion-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .calificacion-valor {
            font-family: 'Fredoka One', cursive;
            font-size: 2rem;
            color: var(--accent);
        }
        
        .calificacion-tutor {
            font-size: 0.9rem;
            color: #666;
        }
        
        .btn-activity {
            padding: 12px 25px;
            border-radius: 15px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-entregar {
            background: linear-gradient(90deg, var(--primary), #2ca5d4);
            color: white;
            box-shadow: 0 5px 15px rgba(44, 186, 236, 0.3);
        }
        
        .btn-entregar:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(44, 186, 236, 0.4);
            color: white;
        }
        
        .btn-ver {
            background: white;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-ver:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-3px);
        }
        
        .btn-editar {
            background: white;
            border: 2px solid var(--secondary);
            color: var(--secondary);
        }
        
        .btn-editar:hover {
            background: var(--secondary);
            color: white;
            transform: translateY(-3px);
        }
        
        .btn-detalles {
            background: white;
            border: 2px solid var(--accent);
            color: var(--accent);
        }
        
        .btn-detalles:hover {
            background: var(--accent);
            color: white;
            transform: translateY(-3px);
        }
        
        /* Estados de actividades */
        .status-pendiente {
            border-left: 4px solid var(--secondary);
        }
        
        .status-pendiente .activity-status-badge {
            background: rgba(240, 174, 42, 0.2);
            color: var(--secondary);
        }
        
        .status-entregado {
            border-left: 4px solid var(--primary);
        }
        
        .status-entregado .activity-status-badge {
            background: rgba(44, 186, 236, 0.2);
            color: var(--primary);
        }
        
        .status-calificado {
            border-left: 4px solid var(--accent);
        }
        
        .status-calificado .activity-status-badge {
            background: rgba(131, 191, 70, 0.2);
            color: var(--accent);
        }
        
        .status-vencido {
            border-left: 4px solid var(--danger);
        }
        
        .status-vencido .activity-status-badge {
            background: rgba(255, 107, 139, 0.2);
            color: var(--danger);
        }
        
        /* Sin actividades */
        .no-activities {
            text-align: center;
            padding: 80px 30px;
            background: white;
            border-radius: 25px;
            box-shadow: var(--card-shadow);
            border: 3px solid rgba(44, 186, 236, 0.1);
        }
        
        .no-activities-icon {
            font-size: 5rem;
            margin-bottom: 20px;
            opacity: 0.5;
            color: var(--primary);
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
        
        /* Modal personalizado */
        .modal-activity {
            border-radius: 25px;
            border: none;
            overflow: hidden;
        }
        
        .modal-activity .modal-header {
            background: linear-gradient(135deg, var(--primary), #2ca5d4);
            color: white;
            border-bottom: none;
            padding: 25px 30px;
        }
        
        .modal-activity .modal-title {
            font-family: 'Fredoka One', cursive;
            font-size: 1.8rem;
        }
        
        .modal-activity .modal-body {
            padding: 30px;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .modal-activity .modal-footer {
            border-top: 2px solid rgba(44, 186, 236, 0.1);
            padding: 20px 30px;
        }
        
        .detail-section {
            margin-bottom: 25px;
            padding: 20px;
            background: rgba(44, 186, 236, 0.05);
            border-radius: 15px;
            border-left: 4px solid var(--primary);
        }
        
        .detail-section h5 {
            color: var(--primary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .detail-content {
            color: #666;
            line-height: 1.6;
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .detail-item {
            padding: 12px 15px;
            background: white;
            border-radius: 10px;
            border: 1px solid rgba(44, 186, 236, 0.2);
        }
        
        .detail-label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-weight: 600;
            color: #333;
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
            
            .page-title {
                font-size: 2rem;
            }
        }
        
        @media (max-width: 768px) {
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .activity-footer {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .calificacion-box {
                max-width: 100%;
            }
            
            .modal-activity .modal-body {
                max-height: 60vh;
            }
        }
        
        @media (max-width: 576px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .activity-meta {
                flex-direction: column;
                gap: 10px;
            }
            
            .page-title {
                font-size: 1.8rem;
            }
            
            .activity-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .btn-activity {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Botón para móvil -->
    <button class="menu-toggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Modal para detalles de actividad -->
    <div class="modal fade" id="activityModal" tabindex="-1" aria-labelledby="activityModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-activity">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="activityModalLabel">
                        <i class="fas fa-info-circle"></i> Detalles de la Misión
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalBodyContent">
                    <!-- El contenido se cargará aquí dinámicamente -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
    
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
                    <a href="mis_cursos.php" class="nav-link">
                        <i class="fas fa-compass"></i>
                        <span>Mis Aventuras</span>
                        <?php if(mysqli_num_rows($res_cursos) > 0): ?>
                            <span class="badge-notification ms-auto"><?php echo mysqli_num_rows($res_cursos); ?></span>
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
                    <a href="mis_actividades.php" class="nav-link active">
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
        <!-- Encabezado -->
        <div class="page-header fade-in-up">
            <a href="dashboard_alumno.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Volver a Mi Mundo
            </a>
            
            <h1 class="page-title">Mis Misiones de Aprendizaje</h1>
            <p class="text-muted fs-5">
                Aquí puedes ver todas las actividades de tus cursos. ¡Mantente al día con tus misiones!
            </p>
        </div>
        
        <!-- Estadísticas -->
        <div class="stats-container fade-in-up" style="animation-delay: 0.1s">
            <div class="stat-card">
                <div class="stat-icon stat-icon-primary">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="stat-value stat-value-primary"><?php echo $total_actividades; ?></div>
                <div class="stat-label">Total de Misiones</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon stat-icon-warning">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value stat-value-warning"><?php echo $pendientes; ?></div>
                <div class="stat-label">Misiones Pendientes</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon stat-icon-info">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <div class="stat-value stat-value-info"><?php echo $entregadas; ?></div>
                <div class="stat-label">Misiones Entregadas</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon stat-icon-success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value stat-value-success"><?php echo $calificacion_promedio; ?></div>
                <div class="stat-label">Promedio de Calificaciones</div>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="filter-container fade-in-up" style="animation-delay: 0.2s">
            <h5 class="mb-3"><i class="fas fa-filter me-2"></i>Filtrar Misiones</h5>
            
            <div class="mb-3">
                <label class="form-label">Filtrar por curso:</label>
                <div class="filter-badges">
                    <a href="mis_actividades.php" class="filter-badge <?php echo !$curso_filtro ? 'active' : ''; ?>" 
                       style="background: rgba(44, 186, 236, 0.1); color: var(--primary);">
                        <i class="fas fa-layer-group me-1"></i> Todos los cursos
                    </a>
                    
                    <?php 
                    mysqli_data_seek($res_cursos, 0);
                    while($curso = mysqli_fetch_assoc($res_cursos)): ?>
                    <a href="mis_actividades.php?curso=<?php echo $curso['id']; ?>" 
                       class="filter-badge <?php echo $curso_filtro == $curso['id'] ? 'active' : ''; ?>"
                       style="background: rgba(131, 191, 70, 0.1); color: var(--accent);">
                        <i class="fas fa-book me-1"></i> <?php echo htmlspecialchars($curso['nombre']); ?>
                    </a>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
        
        <!-- Lista de actividades -->
        <?php if(count($actividades_data) > 0): ?>
            <div class="activities-list">
                <?php 
                $delay = 0.3;
                foreach($actividades_data as $index => $act): 
                    // Determinar estado y color
                    $status_class = '';
                    $status_text = '';
                    $status_color = '';
                    $icon = '';
                    
                    if (!$act['entrega_id']) {
                        $status_class = 'status-pendiente';
                        $status_text = 'PENDIENTE';
                        $status_color = 'var(--secondary)';
                        $icon = 'clock';
                    } elseif ($act['estado_entrega'] == 'pendiente') {
                        $status_class = 'status-entregado';
                        $status_text = 'ENTREGADO';
                        $status_color = 'var(--primary)';
                        $icon = 'paper-plane';
                    } elseif ($act['estado_entrega'] == 'calificado') {
                        $status_class = 'status-calificado';
                        $status_text = 'CALIFICADO';
                        $status_color = 'var(--accent)';
                        $icon = 'check-circle';
                    }
                    
                    // Verificar si está vencida
                    $fecha_limite = strtotime($act['fecha_limite']);
                    $hoy = time();
                    $vencida = false;
                    
                    if ($fecha_limite && $hoy > $fecha_limite && !$act['entrega_id']) {
                        $status_class = 'status-vencido';
                        $status_text = 'VENCIDA';
                        $status_color = 'var(--danger)';
                        $icon = 'exclamation-triangle';
                        $vencida = true;
                    }
                    
                    // Determinar color del tipo de actividad
                    $tipo_color = 'var(--primary)';
                    $tipo_icon = 'question';
                    
                    switch($act['tipo']) {
                        case 'Quiz':
                            $tipo_color = 'var(--purple)';
                            $tipo_icon = 'clipboard-check';
                            break;
                        case 'Tarea':
                            $tipo_color = 'var(--accent)';
                            $tipo_icon = 'file-alt';
                            break;
                        case 'Examen':
                            $tipo_color = 'var(--danger)';
                            $tipo_icon = 'file-signature';
                            break;
                        case 'Proyecto':
                            $tipo_color = 'var(--secondary)';
                            $tipo_icon = 'project-diagram';
                            break;
                    }
                    
                    // Formatear fecha límite
                    $fecha_limite_formatted = formatDate($act['fecha_limite']);
                    
                    // Determinar qué botones mostrar
                    $mostrar_entregar = !$act['entrega_id'] && !$vencida;
                    $mostrar_ver = $act['entrega_id'];
                    $mostrar_editar = $act['entrega_id'] && $act['estado_entrega'] == 'pendiente';
                ?>
                <div class="activity-card <?php echo $status_class; ?> fade-in-up" style="animation-delay: <?php echo $delay; ?>s">
                    <div class="activity-header">
                        <div class="activity-course">
                            <i class="fas fa-book" style="color: <?php echo $status_color; ?>;"></i>
                            <?php echo htmlspecialchars($act['curso_nombre']); ?>
                        </div>
                        <span class="activity-status-badge">
                            <i class="fas fa-<?php echo $icon; ?> me-1"></i>
                            <?php echo $status_text; ?>
                        </span>
                    </div>
                    
                    <div class="activity-body">
                        <h3 class="activity-title">
                            <i class="fas fa-<?php echo $tipo_icon; ?>" style="color: <?php echo $tipo_color; ?>;"></i>
                            <?php echo htmlspecialchars($act['titulo']); ?>
                        </h3>
                        
                        <div class="activity-meta">
                            <div class="meta-item">
                                <i class="fas fa-flag"></i>
                                <span>Tipo: <strong><?php echo htmlspecialchars($act['tipo']); ?></strong></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-chart-line"></i>
                                <span>Dificultad: <strong><?php echo htmlspecialchars($act['dificultad']); ?></strong></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Fecha límite: <strong><?php echo $fecha_limite_formatted; ?></strong></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-star"></i>
                                <span>Puntos: <strong><?php echo $act['puntos']; ?></strong></span>
                            </div>
                        </div>
                        
                        <?php if($act['descripcion']): ?>
                        <div class="activity-description">
                            <?php echo htmlspecialchars($act['descripcion']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if($act['entrega_id']): ?>
                        <div class="alert alert-info p-3">
                            <strong><i class="fas fa-paper-plane me-1"></i> Tu entrega:</strong>
                            <?php if($act['archivo']): ?>
                                <a href="<?php echo htmlspecialchars($act['archivo']); ?>" target="_blank" class="text-decoration-none">
                                    <i class="fas fa-file-download me-1"></i> Descargar archivo
                                </a>
                            <?php endif; ?>
                            <?php if($act['respuesta']): ?>
                                <div class="mt-2">
                                    <strong>Respuesta:</strong> <?php echo htmlspecialchars(substr($act['respuesta'], 0, 100)) . (strlen($act['respuesta']) > 100 ? '...' : ''); ?>
                                </div>
                            <?php endif; ?>
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i> Entregado el: 
                                    <?php echo date('d/m/Y H:i', strtotime($act['fecha_entrega'])); ?>
                                </small>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="activity-footer">
                        <?php if($act['calificacion']): ?>
                        <div class="calificacion-box">
                            <div class="calificacion-header">
                                <span class="calificacion-valor"><?php echo $act['calificacion']; ?>/10</span>
                                <span class="badge bg-success">Calificado</span>
                            </div>
                            <?php if($act['comentarios']): ?>
                                <div class="calificacion-comentario">
                                    <strong>Comentario:</strong> <?php echo htmlspecialchars(substr($act['comentarios'], 0, 80)) . (strlen($act['comentarios']) > 80 ? '...' : ''); ?>
                                </div>
                            <?php endif; ?>
                            <?php if($act['tutor_nombre']): ?>
                                <div class="calificacion-tutor">
                                    <i class="fas fa-user-graduate me-1"></i> <?php echo htmlspecialchars($act['tutor_nombre']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-flex gap-2 flex-wrap activity-actions">
                            <?php if($mostrar_entregar): ?>
                            <a href="realizar_actividad.php?id=<?php echo $act['actividad_id']; ?>" class="btn-activity btn-entregar">
                                <i class="fas fa-paper-plane"></i> Entregar
                            </a>
                            <?php endif; ?>
                            
                            <!-- CORRECCIÓN: Usar el mismo nombre de archivo que existe
                            <?php if($mostrar_ver): ?>
                            <a href="entrega.php?id_actividad=<?php echo $act['actividad_id']; ?>&id_alumno=<?php echo $alumno_id; ?>" 
                               class="btn-activity btn-ver">
                                <i class="fas fa-eye"></i> Ver Entrega
                            </a>
                            <?php endif; ?> -->
                            
                            <?php if($mostrar_editar): ?>
                            <a href="editar_entrega.php?id=<?php echo $act['entrega_id']; ?>" class="btn-activity btn-editar">
                                <i class="fas fa-edit"></i> Editar
                            </a>
                            <?php endif; ?>
                            
                            <button type="button" class="btn-activity btn-detalles" 
                                    onclick="showActivityDetails(<?php echo $index; ?>)">
                                <i class="fas fa-info-circle"></i> Detalles
                            </button>
                        </div>
                    </div>
                </div>
                <?php 
                $delay += 0.05;
                endforeach; 
                ?>
            </div>
        <?php else: ?>
            <div class="no-activities fade-in-up" style="animation-delay: 0.3s">
                <div class="no-activities-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <h3 style="color: var(--primary); margin-bottom: 15px; font-family: 'Fredoka One', cursive;">
                    ¡No tienes misiones asignadas!
                </h3>
                <p style="color: #666; margin-bottom: 30px; max-width: 500px; margin-left: auto; margin-right: auto;">
                    Parece que aún no tienes actividades asignadas en tus cursos. 
                    ¡Continúa aprendiendo y pronto tendrás emocionantes misiones que completar!
                </p>
                <a href="mis_cursos.php" class="btn btn-primary btn-lg" style="background: linear-gradient(90deg, var(--primary), var(--secondary)); border: none;">
                    <i class="fas fa-arrow-left me-2"></i> Volver a Mis Aventuras
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Datos de actividades para el modal
        const actividades = <?php echo json_encode($actividades_data); ?>;
        
        // Función para mostrar detalles de la actividad en modal
        function showActivityDetails(index) {
            const activity = actividades[index];
            const modal = new bootstrap.Modal(document.getElementById('activityModal'));
            const modalBody = document.getElementById('modalBodyContent');
            
            // Determinar icono según tipo
            let tipoIcon = 'question';
            switch(activity.tipo) {
                case 'Quiz': tipoIcon = 'clipboard-check'; break;
                case 'Tarea': tipoIcon = 'file-alt'; break;
                case 'Examen': tipoIcon = 'file-signature'; break;
                case 'Proyecto': tipoIcon = 'project-diagram'; break;
            }
            
            // Determinar estado
            let estadoIcon = 'clock';
            let estadoText = 'PENDIENTE';
            let estadoClass = 'badge bg-warning';
            
            if (!activity.entrega_id) {
                // Verificar si está vencida
                const fechaLimite = new Date(activity.fecha_limite);
                const hoy = new Date();
                if (fechaLimite < hoy) {
                    estadoIcon = 'exclamation-triangle';
                    estadoText = 'VENCIDA';
                    estadoClass = 'badge bg-danger';
                }
            } else if (activity.estado_entrega == 'pendiente') {
                estadoIcon = 'paper-plane';
                estadoText = 'ENTREGADA';
                estadoClass = 'badge bg-info';
            } else if (activity.estado_entrega == 'calificado') {
                estadoIcon = 'check-circle';
                estadoText = 'CALIFICADA';
                estadoClass = 'badge bg-success';
            }
            
            // Crear contenido del modal
            modalBody.innerHTML = `
                <div class="detail-section">
                    <h5><i class="fas fa-${tipoIcon}"></i> Información General</h5>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Título</div>
                            <div class="detail-value">${escapeHtml(activity.titulo)}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Curso</div>
                            <div class="detail-value">${escapeHtml(activity.curso_nombre)}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Tipo</div>
                            <div class="detail-value">${escapeHtml(activity.tipo)}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Estado</div>
                            <div class="detail-value">
                                <span class="${estadoClass}">
                                    <i class="fas fa-${estadoIcon} me-1"></i> ${estadoText}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h5><i class="fas fa-cogs"></i> Detalles Técnicos</h5>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Dificultad</div>
                            <div class="detail-value">${escapeHtml(activity.dificultad)}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Fecha Límite</div>
                            <div class="detail-value">${formatDate(activity.fecha_limite)}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Puntos</div>
                            <div class="detail-value">${activity.puntos} puntos</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Intentos Permitidos</div>
                            <div class="detail-value">${activity.intentos_permitidos || 1}</div>
                        </div>
                    </div>
                </div>
                
                ${activity.descripcion ? `
                <div class="detail-section">
                    <h5><i class="fas fa-align-left"></i> Descripción</h5>
                    <div class="detail-content">
                        ${escapeHtml(activity.descripcion).replace(/\n/g, '<br>')}
                    </div>
                </div>
                ` : ''}
                
                ${activity.entrega_id ? `
                <div class="detail-section">
                    <h5><i class="fas fa-history"></i> Historial de Entrega</h5>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Fecha de Entrega</div>
                            <div class="detail-value">${formatDateTime(activity.fecha_entrega)}</div>
                        </div>
                        ${activity.archivo ? `
                        <div class="detail-item">
                            <div class="detail-label">Archivo Adjunto</div>
                            <div class="detail-value">
                                <i class="fas fa-file me-1"></i> ${escapeHtml(activity.archivo)}
                            </div>
                        </div>
                        ` : ''}
                        ${activity.respuesta ? `
                        <div class="detail-item" style="grid-column: 1 / -1;">
                            <div class="detail-label">Respuesta</div>
                            <div class="detail-value">
                                ${escapeHtml(activity.respuesta).replace(/\n/g, '<br>')}
                            </div>
                        </div>
                        ` : ''}
                    </div>
                </div>
                ` : ''}
                
                ${activity.calificacion ? `
                <div class="detail-section">
                    <h5><i class="fas fa-star"></i> Evaluación</h5>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Calificación</div>
                            <div class="detail-value" style="font-size: 1.5rem; color: var(--accent);">
                                ${activity.calificacion}/10
                            </div>
                        </div>
                        ${activity.fecha_evaluacion ? `
                        <div class="detail-item">
                            <div class="detail-label">Fecha de Evaluación</div>
                            <div class="detail-value">${formatDate(activity.fecha_evaluacion)}</div>
                        </div>
                        ` : ''}
                        ${activity.tutor_nombre ? `
                        <div class="detail-item">
                            <div class="detail-label">Evaluado por</div>
                            <div class="detail-value">
                                <i class="fas fa-user-graduate me-1"></i> ${escapeHtml(activity.tutor_nombre)}
                            </div>
                        </div>
                        ` : ''}
                    </div>
                    ${activity.comentarios ? `
                    <div class="mt-3">
                        <div class="detail-label">Comentarios del Tutor:</div>
                        <div class="detail-content mt-2 p-3 bg-light rounded">
                            ${escapeHtml(activity.comentarios).replace(/\n/g, '<br>')}
                        </div>
                    </div>
                    ` : ''}
                </div>
                ` : ''}
            `;
            
            // Actualizar título del modal
            document.getElementById('activityModalLabel').innerHTML = `
                <i class="fas fa-${tipoIcon}"></i> ${escapeHtml(activity.titulo)}
            `;
            
            modal.show();
        }
        
        // Función para escapar HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Función para formatear fecha
        function formatDate(dateString) {
            if (!dateString) return 'No especificada';
            const date = new Date(dateString);
            return date.toLocaleDateString('es-ES', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
        }
        
        // Función para formatear fecha y hora
        function formatDateTime(dateTimeString) {
            if (!dateTimeString) return 'No especificada';
            const date = new Date(dateTimeString);
            return date.toLocaleString('es-ES', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        // Toggle del menú en móvil
        document.querySelector('.menu-toggle').addEventListener('click', function() {
            const sidebar = document.querySelector('.sidebar-kid');
            sidebar.classList.toggle('active');
            this.innerHTML = sidebar.classList.contains('active') 
                ? '<i class="fas fa-times"></i>' 
                : '<i class="fas fa-bars"></i>';
        });
        
        // Animación al cargar
        document.addEventListener('DOMContentLoaded', function() {
            // Animación para las tarjetas
            const cards = document.querySelectorAll('.fade-in-up');
            cards.forEach((card, index) => {
                card.style.animationDelay = (index * 0.1) + 's';
            });
            
            // Efecto hover en tarjetas de actividad
            const activityCards = document.querySelectorAll('.activity-card');
            activityCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-10px) scale(1.01)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });
            
            // Efecto en el avatar
            const kidAvatar = document.getElementById('kidAvatar');
            if(kidAvatar) {
                kidAvatar.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.1) rotate(5deg)';
                });
                
                kidAvatar.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1) rotate(0deg)';
                });
            }
        });
        
        // Cerrar menú al hacer clic en enlace en móvil
        window.addEventListener('resize', function() {
            if (window.innerWidth < 992) {
                const links = document.querySelectorAll('.nav-link');
                links.forEach(link => {
                    link.addEventListener('click', function() {
                        const sidebar = document.querySelector('.sidebar-kid');
                        const menuToggle = document.querySelector('.menu-toggle');
                        if(sidebar.classList.contains('active')) {
                            sidebar.classList.remove('active');
                            menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>