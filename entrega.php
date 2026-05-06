
<?php
include 'php/config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$alumno_id = $_SESSION['user_id'];
$modo_ver_entrega = false;

// DETECTAR QUÉ MODO USAR
// Modo 1: Ver curso completo (viene con ?id=)
// Modo 2: Ver entrega específica (viene con ?id_actividad=)
if (isset($_GET['id_actividad']) && isset($_GET['id_alumno'])) {
    $modo_ver_entrega = true;
    $id_actividad = $_GET['id_actividad'];
    $id_alumno_entrega = $_GET['id_alumno'];
    
    // Verificar que el alumno sea el mismo de la sesión
    if ($id_alumno_entrega != $alumno_id) {
        header("Location: index.php");
        exit();
    }
    
    // Obtener el curso a partir de la actividad
    $sql_curso_id = "SELECT id_curso FROM actividades WHERE id = '$id_actividad'";
    $res_curso_id = mysqli_query($conn, $sql_curso_id);
    
    if (!$res_curso_id || mysqli_num_rows($res_curso_id) == 0) {
        header("Location: mis_cursos.php");
        exit();
    }
    
    $curso_data = mysqli_fetch_assoc($res_curso_id);
    $id_curso = $curso_data['id_curso'];
    
    // Obtener la entrega específica
   $sql_entrega_especifica = "SELECT e.*, ev.calificacion, ev.comentarios, ev.fecha_evaluacion
                           FROM entregas e 
                           LEFT JOIN evaluaciones ev ON e.id = ev.id_entrega 
                           WHERE e.id_actividad = '$id_actividad' 
                           AND e.id_alumno = '$alumno_id'
                           ORDER BY e.fecha_entrega DESC 
                           LIMIT 1";
    $res_entrega_especifica = mysqli_query($conn, $sql_entrega_especifica);
    $entrega_especifica = $res_entrega_especifica && mysqli_num_rows($res_entrega_especifica) > 0 
                          ? mysqli_fetch_assoc($res_entrega_especifica) 
                          : null;
    
    // Obtener detalles de la actividad
    $sql_actividad_detalle = "SELECT * FROM actividades WHERE id = '$id_actividad'";
    $res_actividad_detalle = mysqli_query($conn, $sql_actividad_detalle);
    $actividad_detalle = mysqli_fetch_assoc($res_actividad_detalle);
    
} else if (isset($_GET['id'])) {
    // Modo normal: ver curso completo
    $id_curso = $_GET['id'];
} else {
    header("Location: mis_cursos.php");
    exit();
}

// Obtener info del curso (para ambos modos)
$sql_curso = "SELECT * FROM cursos WHERE id = '$id_curso'";
$res_curso = mysqli_query($conn, $sql_curso);
$curso = mysqli_fetch_assoc($res_curso);

if (!$curso) {
    header("Location: mis_cursos.php");
    exit();
}

// Obtener actividades de este curso (solo en modo curso completo)
if (!$modo_ver_entrega) {
    $sql_actividades = "SELECT * FROM actividades WHERE id_curso = '$id_curso' ORDER BY id";
    $res_actividades = mysqli_query($conn, $sql_actividades);
}

// Obtener progreso actual
$sql_prog = "SELECT progreso FROM inscripciones WHERE id_alumno = '$alumno_id' AND id_curso = '$id_curso'";
$res_prog = mysqli_query($conn, $sql_prog);
$progreso_data = mysqli_fetch_assoc($res_prog);
$porcentaje = $progreso_data['progreso'] ?? 0;

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

// Determinar avatar del alumno
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

// Función para verificar el estado de la tarea
function verificarEstadoTarea($conn, $actividad_id, $alumno_id) {
    $sql_envio = "SELECT e.*, ev.calificacion 
                  FROM entregas e 
                  LEFT JOIN evaluaciones ev ON e.id = ev.id_entrega 
                  WHERE e.id_actividad = '$actividad_id' 
                  AND e.id_alumno = '$alumno_id' 
                  ORDER BY e.fecha_entrega DESC 
                  LIMIT 1";
    
    $res_envio = mysqli_query($conn, $sql_envio);
    
    $sql_actividad = "SELECT intentos_permitidos FROM actividades WHERE id = '$actividad_id'";
    $res_actividad = mysqli_query($conn, $sql_actividad);
    $actividad_data = mysqli_fetch_assoc($res_actividad);
    $intentos_permitidos = $actividad_data['intentos_permitidos'];
    
    if ($res_envio && mysqli_num_rows($res_envio) > 0) {
        $envio_data = mysqli_fetch_assoc($res_envio);
        
        $sql_count_entregas = "SELECT COUNT(*) as total_entregas 
                              FROM entregas 
                              WHERE id_actividad = '$actividad_id' 
                              AND id_alumno = '$alumno_id'";
        $res_count = mysqli_query($conn, $sql_count_entregas);
        $count_data = mysqli_fetch_assoc($res_count);
        $intentos_usados = $count_data['total_entregas'];
        
        if ($intentos_usados >= $intentos_permitidos) {
            return [
                'estado' => 'bloqueada',
                'texto_boton' => 'Ver Entrega',
                'clase_boton' => 'btn-view',
                'icono_boton' => 'fa-eye',
                'mensaje' => 'Ya completaste esta misión',
                'calificacion' => $envio_data['calificacion'] ?? null,
                'fecha_envio' => $envio_data['fecha_entrega'] ?? null,
                'intentos_usados' => $intentos_usados,
                'intentos_restantes' => 0
            ];
        } else {
            $intentos_restantes = $intentos_permitidos - $intentos_usados;
            return [
                'estado' => 'intentos_disponibles',
                'texto_boton' => 'Volver a Intentar',
                'clase_boton' => 'btn-retry',
                'icono_boton' => 'fa-redo',
                'mensaje' => 'Intento ' . ($intentos_usados) . ' de ' . $intentos_permitidos . ' completado',
                'calificacion' => $envio_data['calificacion'] ?? null,
                'intentos_restantes' => $intentos_restantes,
                'intentos_usados' => $intentos_usados
            ];
        }
    } else {
        return [
            'estado' => 'disponible',
            'texto_boton' => 'Comenzar Misión',
            'clase_boton' => 'btn-start',
            'icono_boton' => 'fa-play',
            'mensaje' => 'Primer intento de ' . $intentos_permitidos,
            'intentos_restantes' => $intentos_permitidos,
            'intentos_usados' => 0
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php 
        if ($modo_ver_entrega) {
            echo "Mi Entrega - " . htmlspecialchars($actividad_detalle['titulo']);
        } else {
            echo htmlspecialchars($curso['nombre']) . " - D&F Mindspace";
        }
    ?></title>
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
        
        /* Encabezado del curso */
        .course-header {
            background: linear-gradient(135deg, #ffffff, #f8fdff);
            border-radius: 25px;
            padding: 40px;
            margin-bottom: 40px;
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
        
        .course-header-icon {
            font-size: 4rem;
            color: var(--primary);
            margin-bottom: 20px;
            display: inline-block;
            animation: float 3s ease-in-out infinite;
            text-shadow: 0 4px 8px rgba(44, 186, 236, 0.3);
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .course-title {
            font-family: 'Fredoka One', cursive;
            font-size: 2.8rem;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 15px;
            line-height: 1.2;
        }
        
        .course-description {
            font-size: 1.3rem;
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        /* Barra de progreso mejorada */
        .progress-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 40px;
            box-shadow: var(--card-shadow);
            border: 3px solid rgba(44, 186, 236, 0.1);
        }
        
        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .progress-label {
            font-family: 'Fredoka One', cursive;
            font-size: 1.5rem;
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
            margin-bottom: 15px;
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
        
        /* Grid de misiones */
        .missions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 35px;
            margin-bottom: 60px;
        }
        
        .mission-card {
            background: white;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: all 0.4s ease;
            border: 3px solid transparent;
            position: relative;
            animation: fadeInUp 0.6s ease-out;
        }
        
        .mission-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 25px 50px rgba(44, 186, 236, 0.25);
            border-color: var(--primary);
        }
        
        .mission-header {
            padding: 30px 30px 25px;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, var(--primary), #2ca5d4);
        }
        
        .mission-header::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: translateX(-100%);
        }
        
        .mission-card:hover .mission-header::after {
            animation: shine 1.5s ease;
        }
        
        @keyframes shine {
            to { transform: translateX(100%); }
        }
        
        .mission-points {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255,255,255,0.3);
            backdrop-filter: blur(10px);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.9rem;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .mission-icon {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            margin-bottom: 15px;
            border: 2px solid rgba(255,255,255,0.3);
        }
        
        .mission-title {
            font-family: 'Fredoka One', cursive;
            font-size: 1.6rem;
            color: white;
            margin-bottom: 10px;
            line-height: 1.3;
        }
        
        .mission-body {
            padding: 30px;
        }
        
        .mission-description {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.6;
        }
        
        .mission-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
            padding: 20px;
            background: rgba(44, 186, 236, 0.05);
            border-radius: 15px;
        }
        
        .mission-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: white;
            border-radius: 15px;
            font-weight: 600;
            color: #555;
            border: 2px solid rgba(44, 186, 236, 0.2);
        }
        
        .mission-badge i {
            color: var(--primary);
        }
        
        /* Estado de la tarea */
        .mission-status {
            padding: 15px;
            border-radius: 15px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }
        
        .status-available {
            background: rgba(131, 191, 70, 0.1);
            color: var(--accent);
            border: 2px solid rgba(131, 191, 70, 0.2);
        }
        
        .status-blocked {
            background: rgba(255, 107, 139, 0.1);
            color: var(--danger);
            border: 2px solid rgba(255, 107, 139, 0.2);
        }
        
        .status-attempts {
            background: rgba(240, 174, 42, 0.1);
            color: var(--secondary);
            border: 2px solid rgba(240, 174, 42, 0.2);
        }
        
        .mission-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        /* BOTONES REDONDEADOS */
        .btn-mission {
            flex: 1;
            padding: 16px 20px;
            border-radius: 15px;
            font-weight: 700;
            text-align: center;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 1.1rem;
            border: none;
            cursor: pointer;
            min-height: 60px;
        }
        
        .btn-start {
            background: linear-gradient(90deg, var(--accent), #6aab39);
            color: white;
            box-shadow: 0 5px 15px rgba(131, 191, 70, 0.3);
        }
        
        .btn-start:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 20px rgba(131, 191, 70, 0.4);
            color: white;
        }
        
        .btn-retry {
            background: linear-gradient(90deg, var(--secondary), #f5c15d);
            color: white;
            box-shadow: 0 5px 15px rgba(240, 174, 42, 0.3);
        }
        
        .btn-retry:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 20px rgba(240, 174, 42, 0.4);
            color: white;
        }
        
        .btn-view {
            background: linear-gradient(90deg, var(--primary), #2ca5d4);
            color: white;
            box-shadow: 0 5px 15px rgba(44, 186, 236, 0.3);
        }
        
        .btn-view:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 20px rgba(44, 186, 236, 0.4);
            color: white;
        }
        
        .btn-preview {
            background: white;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-preview:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-3px) scale(1.02);
        }
        
        /* Calificación */
        .mission-grade {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            background: linear-gradient(90deg, rgba(156, 136, 255, 0.1), rgba(156, 136, 255, 0.05));
            border-radius: 15px;
            margin-top: 15px;
            border: 2px solid rgba(156, 136, 255, 0.2);
        }
        
        .grade-value {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(90deg, var(--purple), var(--pink));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
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
        
        /* Nivel del curso */
        .course-level {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 20px;
            background: linear-gradient(90deg, var(--secondary), #f5c15d);
            color: white;
            border-radius: 15px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        /* Botón de volver */
        .btn-back {
            background: linear-gradient(90deg, var(--primary), #2ca5d4);
            color: white;
            border: none;
            border-radius: 15px;
            padding: 12px 25px;
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
        
        /* Responsive */
        @media (max-width: 1200px) {
            .missions-grid {
                grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
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
            
            .course-title {
                font-size: 2.3rem;
            }
            
            .missions-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .course-title {
                font-size: 2rem;
            }
            
            .mission-actions {
                flex-direction: column;
            }
            
            .mission-meta {
                flex-direction: column;
            }
            
            .main-content {
                padding: 20px;
            }
            
            .mission-body {
                padding: 20px;
            }
            
            .course-header {
                padding: 30px 20px;
            }
        }
        
        @media (max-width: 576px) {
            .course-title {
                font-size: 1.8rem;
            }
            
            .course-description {
                font-size: 1.1rem;
            }
            
            .course-header {
                padding: 25px 15px;
            }
            
            .mission-title {
                font-size: 1.4rem;
            }
            
            .btn-mission {
                padding: 14px 15px;
                font-size: 1rem;
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
        
        /* Sin misiones */
        .no-missions {
            text-align: center;
            padding: 80px 30px;
            background: white;
            border-radius: 25px;
            box-shadow: var(--card-shadow);
            border: 3px solid rgba(44, 186, 236, 0.1);
        }
        
        .no-missions-icon {
            font-size: 5rem;
            margin-bottom: 20px;
            opacity: 0.5;
            color: var(--primary);
        }
        
        /* Estrellas decorativas */
        .floating-star {
            position: absolute;
            color: var(--secondary);
            font-size: 1.5rem;
            animation: twinkle 3s infinite;
        }
        
        @keyframes twinkle {
            0%, 100% { opacity: 0.3; transform: scale(1) rotate(0deg); }
            50% { opacity: 1; transform: scale(1.3) rotate(180deg); }
        }
        
        /* Estilos para dificultades */
        .difficulty-badge {
            padding: 8px 16px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .difficulty-easy {
            background: rgba(131, 191, 70, 0.1);
            color: var(--accent);
            border: 2px solid rgba(131, 191, 70, 0.2);
        }
        
        .difficulty-normal {
            background: rgba(44, 186, 236, 0.1);
            color: var(--primary);
            border: 2px solid rgba(44, 186, 236, 0.2);
        }
        
        .difficulty-hard {
            background: rgba(240, 174, 42, 0.1);
            color: var(--secondary);
            border: 2px solid rgba(240, 174, 42, 0.2);
        }
        
        /* Tarea completada */
        .mission-completed {
            position: absolute;
            top: 10px;
            left: 10px;
            background: var(--accent);
            color: white;
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 1;
        }
        
        /* ESTILOS ADICIONALES PARA MODO VER ENTREGA */
        .entrega-detalle-container {
            background: white;
            border-radius: 25px;
            padding: 40px;
            margin-bottom: 40px;
            box-shadow: var(--card-shadow);
            border: 3px solid rgba(44, 186, 236, 0.1);
        }
        
        .entrega-header {
            background: linear-gradient(135deg, var(--primary), #2ca5d4);
            color: white;
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
        }
        
        .entrega-content {
            padding: 20px;
        }
        
        .respuesta-texto {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
            border-left: 5px solid var(--primary);
        }
        
        .evaluacion-box {
            background: linear-gradient(135deg, rgba(131, 191, 70, 0.1), rgba(131, 191, 70, 0.05));
            border-radius: 20px;
            padding: 25px;
            margin-top: 30px;
            border: 2px solid rgba(131, 191, 70, 0.2);
        }
        
        .calificacion-grande {
            font-size: 3rem;
            font-weight: 800;
            color: var(--accent);
            text-align: center;
            margin: 20px 0;
        }
        
        .sin-evaluar {
            text-align: center;
            padding: 30px;
            color: #666;
            font-style: italic;
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
                
                <h4 class="kid-name"><?php echo htmlspecialchars($_SESSION['nombre']); ?></h4>
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
        
        <?php if ($modo_ver_entrega): ?>
        <!-- =========================================== -->
        <!-- MODO VER ENTREGA ESPECÍFICA -->
        <!-- =========================================== -->
        <div class="entrega-detalle-container fade-in-up">
            <!-- Botón de volver -->
            <a href="entrega.php?id=<?php echo $id_curso; ?>" class="btn-back">
                <i class="fas fa-arrow-left"></i> Volver al Curso
            </a>
            
            <div class="entrega-header">
                <h1 class="course-title">
                    <i class="fas fa-eye me-3"></i>
                    Mi Entrega
                </h1>
                <h2 class="text-white mt-3"><?php echo htmlspecialchars($actividad_detalle['titulo']); ?></h2>
                <p class="text-white-50 mb-0">
                    Curso: <?php echo htmlspecialchars($curso['nombre']); ?>
                </p>
            </div>
            
            <div class="entrega-content">
                <!-- Información de la actividad -->
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-body">
                        <h4 class="card-title">
                            <i class="fas fa-info-circle text-primary me-2"></i>
                            Detalles de la misión
                        </h4>
                        <p class="card-text"><?php echo htmlspecialchars($actividad_detalle['descripcion']); ?></p>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <p><strong><i class="fas fa-tag me-2"></i>Tipo:</strong> 
                                   <?php echo htmlspecialchars($actividad_detalle['tipo']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong><i class="fas fa-chart-line me-2"></i>Dificultad:</strong> 
                                   <?php echo htmlspecialchars($actividad_detalle['dificultad']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong><i class="fas fa-star me-2"></i>Puntos:</strong> 
                                   <?php echo $actividad_detalle['puntos']; ?> pts</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong><i class="fas fa-calendar-alt me-2"></i>Fecha límite:</strong> 
                                   <?php echo date('d/m/Y', strtotime($actividad_detalle['fecha_limite'])); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Detalles de la entrega -->
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-body">
                        <h4 class="card-title">
                            <i class="fas fa-paper-plane text-success me-2"></i>
                            Mi entrega enviada
                        </h4>
                        
                        <?php if ($entrega_especifica): ?>
                            <p><strong>Fecha de entrega:</strong> 
                               <?php echo date('d/m/Y H:i', strtotime($entrega_especifica['fecha_entrega'])); ?></p>
                            
                            <?php if ($entrega_especifica['archivo']): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-paperclip me-2"></i>
                                    <strong>Archivo adjunto:</strong>
                                    <a href="uploads/<?php echo htmlspecialchars($entrega_especifica['archivo']); ?>" 
                                       class="btn btn-sm btn-primary ms-3" download>
                                        <i class="fas fa-download me-1"></i> Descargar archivo
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                           <?php if (!empty($entrega_especifica['respuesta'])): ?>
                                <div class="respuesta-texto">
                                    <h5><i class="fas fa-comment-dots me-2"></i>Mi respuesta:</h5>
                                    <p><?php echo nl2br(htmlspecialchars($entrega_especifica['respuesta'])); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Evaluación -->
                            <?php if ($entrega_especifica['calificacion']): ?>
                                <div class="evaluacion-box">
                                    <h4 class="text-success">
                                        <i class="fas fa-trophy me-2"></i>
                                        Evaluación del tutor
                                    </h4>
                                    
                                    <div class="calificacion-grande">
                                        <?php echo $entrega_especifica['calificacion']; ?>/10
                                    </div>
                                    
                                    <?php if ($entrega_especifica['comentarios']): ?>
                                        <div class="alert alert-light">
                                            <h5><i class="fas fa-comments me-2"></i>Comentarios del tutor:</h5>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($entrega_especifica['comentarios'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <p class="text-muted text-end mb-0">
                                        <i class="fas fa-calendar-check me-1"></i>
                                        Evaluado el: <?php echo date('d/m/Y', strtotime($entrega_especifica['fecha_evaluacion'])); ?>
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="sin-evaluar">
                                    <i class="fas fa-clock fa-3x text-warning mb-3"></i>
                                    <h5 class="text-warning">¡Esperando evaluación!</h5>
                                    <p>Tu tutor aún no ha evaluado esta entrega.</p>
                                    <p class="small text-muted">Recibirás una notificación cuando sea evaluada.</p>
                                </div>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                No se encontró información de entrega para esta actividad.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Botones de acción -->
                <div class="d-flex gap-3 mt-4">
                    <a href="entrega.php?id=<?php echo $id_curso; ?>" class="btn btn-primary btn-lg">
                        <i class="fas fa-arrow-left me-2"></i> Volver al curso
                    </a>
                    
                    <?php if ($entrega_especifica && !$entrega_especifica['calificacion']): ?>
                        <!-- Si aún no está evaluada, mostrar botón para editar si hay intentos disponibles -->
                        <?php 
                        $estado = verificarEstadoTarea($conn, $id_actividad, $alumno_id);
                        if ($estado['estado'] == 'intentos_disponibles'): ?>
                            <a href="realizar_actividad.php?id=<?php echo $id_actividad; ?>" class="btn btn-warning btn-lg">
                                <i class="fas fa-redo me-2"></i> Volver a intentar
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- =========================================== -->
        <!-- MODO VER CURSO COMPLETO (ORIGINAL) -->
        <!-- =========================================== -->
        <div class="course-header fade-in-up">
            <!-- Estrellas decorativas -->
            <i class="fas fa-star floating-star" style="top: 30px; right: 100px; animation-delay: 0s;"></i>
            <i class="fas fa-star floating-star" style="bottom: 40px; left: 80px; animation-delay: 1s;"></i>
            <i class="fas fa-star floating-star" style="top: 60px; left: 150px; animation-delay: 2s;"></i>
            
            <!-- Icono del curso -->
            <div class="course-header-icon">
                <i class="fas fa-compass"></i>
            </div>
            
            <!-- Botón de volver -->
            <a href="mis_cursos.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Volver a Mis Aventuras
            </a>
            
            <!-- Nivel del curso -->
            <div class="course-level">
                <i class="fas fa-<?php 
                    switch(strtolower($curso['nivel'])) {
                        case 'básico': echo 'seedling'; break;
                        case 'intermedio': echo 'star'; break;
                        case 'avanzado': echo 'rocket'; break;
                        default: echo 'compass';
                    }
                ?>"></i>
                Nivel: <?php echo htmlspecialchars($curso['nivel']); ?>
            </div>
            
            <h1 class="course-title"><?php echo htmlspecialchars($curso['nombre']); ?></h1>
            <p class="course-description">
                <?php echo htmlspecialchars($curso['descripcion']); ?>
            </p>
        </div>
        
        <!-- Sección de progreso -->
        <div class="progress-section fade-in-up" style="animation-delay: 0.1s">
            <div class="progress-header">
                <div class="progress-label">Tu Progreso en esta Aventura</div>
                <div class="progress-percentage"><?php echo $porcentaje; ?>%</div>
            </div>
            
            <div class="progress-container">
                <div class="progress-fill" style="width: <?php echo $porcentaje; ?>%"></div>
            </div>
            
            <div class="mt-3 text-center">
                <small class="text-muted">
                    <i class="fas fa-lightbulb text-warning me-1"></i>
                    ¡Sigue así! Cada misión completada te acerca más a dominar esta aventura.
                </small>
            </div>
        </div>
        
        <!-- Encabezado de misiones -->
        <div class="mb-4 fade-in-up" style="animation-delay: 0.2s">
            <h3 class="fw-bold mb-3" style="font-family: 'Fredoka One', cursive; color: var(--primary); font-size: 2rem;">
                <i class="fas fa-map-marked-alt me-2"></i> Tus Próximas Misiones
            </h3>
            <p class="text-muted mb-4">Completa estas misiones para avanzar en tu aventura</p>
        </div>
        
        <!-- Grid de misiones -->
        <?php if(mysqli_num_rows($res_actividades) > 0): ?>
            <div class="missions-grid">
                <?php mysqli_data_seek($res_actividades, 0); // Resetear puntero ?>
                <?php while($act = mysqli_fetch_assoc($res_actividades)): 
                    // Determinar icono según tipo
                    $mission_icon = '';
                    switch(strtolower($act['tipo'])) {
                        case 'quiz': $mission_icon = 'fa-puzzle-piece'; break;
                        case 'tarea': $mission_icon = 'fa-tasks'; break;
                        case 'examen': $mission_icon = 'fa-file-alt'; break;
                        default: $mission_icon = 'fa-star';
                    }
                    
                    // Determinar color de dificultad
                    $difficulty_class = '';
                    switch(strtolower($act['dificultad'])) {
                        case 'fácil': $difficulty_class = 'difficulty-easy'; break;
                        case 'normal': $difficulty_class = 'difficulty-normal'; break;
                        case 'difícil': $difficulty_class = 'difficulty-hard'; break;
                        default: $difficulty_class = 'difficulty-normal';
                    }
                    
                    // Verificar estado de la tarea
                    $estado_tarea = verificarEstadoTarea($conn, $act['id'], $alumno_id);
                    $status_class = '';
                    switch($estado_tarea['estado']) {
                        case 'bloqueada': $status_class = 'status-blocked'; break;
                        case 'intentos_disponibles': $status_class = 'status-attempts'; break;
                        default: $status_class = 'status-available';
                    }
                ?>
                <div class="mission-card fade-in-up">
                    <!-- Encabezado de la misión -->
                    <div class="mission-header">
                        <?php if($estado_tarea['estado'] == 'bloqueada'): ?>
                            <div class="mission-completed">
                                <i class="fas fa-check-circle me-1"></i>Completada
                            </div>
                        <?php endif; ?>
                        
                        <div class="mission-points">
                            <i class="fas fa-star me-1"></i> <?php echo $act['puntos']; ?> pts
                        </div>
                        
                        <div class="mission-icon">
                            <i class="fas <?php echo $mission_icon; ?>"></i>
                        </div>
                        
                        <h3 class="mission-title"><?php echo htmlspecialchars($act['titulo']); ?></h3>
                    </div>
                    
                    <!-- Cuerpo de la misión -->
                    <div class="mission-body">
                        <p class="mission-description">
                            <?php echo htmlspecialchars($act['descripcion']); ?>
                        </p>
                        
                        <div class="mission-meta">
                            <span class="mission-badge <?php echo $difficulty_class; ?>">
                                <i class="fas fa-chart-line"></i>
                                Dificultad: <?php echo htmlspecialchars($act['dificultad']); ?>
                            </span>
                            
                            <span class="mission-badge">
                                <i class="far fa-clock"></i>
                                <?php echo $act['tiempo_limite']; ?> min
                            </span>
                            
                            <span class="mission-badge">
                                <i class="fas fa-redo"></i>
                                <?php echo $act['intentos_permitidos']; ?> intentos
                            </span>
                        </div>
                        
                        <!-- Estado de la tarea -->
                        <div class="mission-status <?php echo $status_class; ?>">
                            <i class="fas fa-<?php 
                                switch($estado_tarea['estado']) {
                                    case 'bloqueada': echo 'lock'; break;
                                    case 'intentos_disponibles': echo 'exclamation-triangle'; break;
                                    default: echo 'check-circle';
                                }
                            ?>"></i>
                            <span><?php echo $estado_tarea['mensaje']; ?></span>
                            
                            <?php if(isset($estado_tarea['intentos_restantes']) && $estado_tarea['intentos_restantes'] > 0): ?>
                                <span class="ms-auto">Intentos restantes: <?php echo $estado_tarea['intentos_restantes']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Mostrar calificación si está bloqueada -->
                        <?php if($estado_tarea['estado'] == 'bloqueada' && isset($estado_tarea['calificacion'])): ?>
                            <div class="mission-grade">
                                <i class="fas fa-trophy text-warning"></i>
                                <strong>Tu calificación:</strong>
                                <span class="grade-value"><?php echo $estado_tarea['calificacion']; ?>/10</span>
                                <?php if(isset($estado_tarea['fecha_envio'])): ?>
                                    <span class="ms-auto">Enviado: <?php echo date('d/m/Y', strtotime($estado_tarea['fecha_envio'])); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php elseif($estado_tarea['estado'] == 'intentos_disponibles' && isset($estado_tarea['calificacion'])): ?>
                            <div class="mission-grade">
                                <i class="fas fa-history text-info"></i>
                                <strong>Calificación anterior:</strong>
                                <span class="grade-value"><?php echo $estado_tarea['calificacion']; ?>/10</span>
                                <span class="ms-auto">Puedes mejorar tu nota</span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mission-actions">
                            <?php if($estado_tarea['estado'] == 'bloqueada'): ?>
                                <!-- Botón para ver entrega (tarea bloqueada) -->
                                <a href="entrega.php?id_actividad=<?php echo $act['id']; ?>&id_alumno=<?php echo $alumno_id; ?>"  
                                   class="btn-mission <?php echo $estado_tarea['clase_boton']; ?>">
                                    <i class="fas <?php echo $estado_tarea['icono_boton']; ?> me-2"></i>
                                    <?php echo $estado_tarea['texto_boton']; ?>
                                </a>
                            <?php else: ?>
                                <!-- Botón para realizar actividad -->
                                <a href="realizar_actividad.php?id=<?php echo $act['id']; ?>" 
                                   class="btn-mission <?php echo $estado_tarea['clase_boton']; ?>">
                                    <i class="fas <?php echo $estado_tarea['icono_boton']; ?> me-2"></i>
                                    <?php echo $estado_tarea['texto_boton']; ?>
                                </a>
                            <?php endif; ?>
                            
                            <button class="btn-mission btn-preview btn-preview-mission"
                                    data-id="<?php echo $act['id']; ?>"
                                    data-titulo="<?php echo htmlspecialchars($act['titulo']); ?>"
                                    data-descripcion="<?php echo htmlspecialchars($act['descripcion']); ?>"
                                    data-tipo="<?php echo htmlspecialchars($act['tipo']); ?>"
                                    data-dificultad="<?php echo htmlspecialchars($act['dificultad']); ?>"
                                    data-tiempo="<?php echo $act['tiempo_limite']; ?>"
                                    data-fecha="<?php echo $act['fecha_limite']; ?>"
                                    data-puntos="<?php echo $act['puntos']; ?>"
                                    data-estado="<?php echo $estado_tarea['estado']; ?>"
                                    data-intentos="<?php echo $estado_tarea['intentos_restantes'] ?? 0; ?>"
                                    data-calificacion="<?php echo $estado_tarea['calificacion'] ?? 0; ?>">
                                <i class="fas fa-info-circle me-2"></i>
                                Detalles
                            </button>
                        </div>
                        
                        <div class="mt-4 text-end">
                            <small class="text-danger fw-bold">
                                <i class="fas fa-calendar-alt me-1"></i>
                                Límite: <?php echo date('d/m/Y', strtotime($act['fecha_limite'])); ?>
                            </small>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="no-missions fade-in-up">
                <div class="no-missions-icon">
                    <i class="fas fa-flag-checkered"></i>
                </div>
                <h2 class="mb-3" style="font-family: 'Fredoka One', cursive; color: var(--primary);">
                    ¡Aventura en Construcción! 🏗️
                </h2>
                <p class="text-muted mb-4" style="max-width: 500px; margin: 0 auto;">
                    Tu tutor aún está preparando las misiones para esta aventura. 
                    ¡Vuelve pronto para comenzar tu viaje de aprendizaje!
                </p>
                <a href="catalogo.php" class="btn-start" style="display: inline-flex; align-items: center; gap: 10px; padding: 15px 30px; border-radius: 15px; text-decoration: none;">
                    <i class="fas fa-compass me-2"></i> Explorar Otras Aventuras
                </a>
            </div>
        <?php endif; ?>
        
        <!-- Modal de vista previa -->
        <div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="border-radius: 25px; overflow: hidden; border: none;">
                    <div class="modal-header" style="background: linear-gradient(135deg, var(--primary), var(--accent)); color: white; border: none;">
                        <h5 class="modal-title" style="font-family: 'Fredoka One', cursive;">
                            <i class="fas fa-info-circle me-2"></i> Vista Previa de Misión
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <h4 id="previewTitle" class="fw-bold mb-3"></h4>
                        <p id="previewDescription" class="mb-4"></p>
                        
                        <div class="row mb-4">
                            <!-- ... contenido del modal original ... -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
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
        if (kidAvatar) {
            kidAvatar.addEventListener('click', function() {
                this.style.transform = 'scale(1.1) rotate(5deg)';
                this.style.boxShadow = '0 12px 30px rgba(0,0,0,0.2)';
                setTimeout(() => {
                    this.style.transform = '';
                    this.style.boxShadow = '';
                }, 300);
            });
        }
        
        <?php if (!$modo_ver_entrega): ?>
        // Modal de vista previa (solo en modo ver curso)
        const previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
        const previewButtons = document.querySelectorAll('.btn-preview-mission');
        
        previewButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                // ... código del modal original ...
            });
        });
        
        // Animación de la barra de progreso
        document.addEventListener('DOMContentLoaded', function() {
            const progressBar = document.querySelector('.progress-fill');
            if (progressBar) {
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
                
                document.querySelectorAll('.mission-card, .no-missions').forEach(el => {
                    observer.observe(el);
                });
            }
        });
        
        // Efecto especial para botones
        document.querySelectorAll('.btn-start, .btn-retry, .btn-view, .btn-preview, .btn-back').forEach(btn => {
            btn.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-3px) scale(1.02)';
            });
            
            btn.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>