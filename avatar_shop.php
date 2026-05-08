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

// Obtener estadísticas del alumno para desbloquear avatares
// 1. Actividades completadas
$query_act_completadas = "SELECT COUNT(DISTINCT e.id_actividad) as total 
                          FROM entregas e 
                          WHERE e.id_alumno = '$alumno_id' 
                          AND e.estado = 'calificado'";
$res_act = mysqli_query($conn, $query_act_completadas);
$act_completadas = mysqli_fetch_assoc($res_act)['total'] ?? 0;

// 2. Calificaciones perfectas (10/10)
$query_perfectas = "SELECT COUNT(*) as total 
                   FROM entregas e 
                   JOIN evaluaciones ev ON e.id = ev.id_entrega 
                   WHERE e.id_alumno = '$alumno_id' 
                   AND ev.calificacion = 10";
$res_perfectas = mysqli_query($conn, $query_perfectas);
$calificaciones_perfectas = mysqli_fetch_assoc($res_perfectas)['total'] ?? 0;

// 3. Cursos completados (100% progreso)
$query_cursos_completados = "SELECT COUNT(*) as total 
                            FROM inscripciones 
                            WHERE id_alumno = '$alumno_id' 
                            AND progreso = 100 
                            AND estado = 'activo'";
$res_cursos_comp = mysqli_query($conn, $query_cursos_completados);
$cursos_completados = mysqli_fetch_assoc($res_cursos_comp)['total'] ?? 0;

// Consultar cursos en los que está inscrito
$query_cursos = "SELECT c.nombre, c.descripcion, i.progreso, i.id_curso, c.nivel 
                 FROM inscripciones i 
                 JOIN cursos c ON i.id_curso = c.id 
                 WHERE i.id_alumno = '$alumno_id' AND i.estado = 'activo'";
$res_cursos = mysqli_query($conn, $query_cursos);

// Consultar notificaciones recientes
$query_notif = "SELECT * FROM notificaciones WHERE id_usuario = '$alumno_id' AND leido = FALSE LIMIT 5";
$res_notif = mysqli_query($conn, $query_notif);

// Consultar actividades recientes con calificación desde tabla evaluaciones
$query_actividades = "SELECT a.titulo, e.fecha_entrega, e.estado, ev.calificacion 
                      FROM entregas e 
                      JOIN actividades a ON e.id_actividad = a.id 
                      LEFT JOIN evaluaciones ev ON e.id = ev.id_entrega
                      WHERE e.id_alumno = '$alumno_id' 
                      ORDER BY e.fecha_entrega DESC LIMIT 4";
$res_actividades = mysqli_query($conn, $query_actividades);

// 4. Actividades con calificación 8+
$query_calificaciones_altas = "SELECT COUNT(*) as total 
                              FROM entregas e 
                              JOIN evaluaciones ev ON e.id = ev.id_entrega 
                              WHERE e.id_alumno = '$alumno_id' 
                              AND ev.calificacion >= 8";
$res_calif_altas = mysqli_query($conn, $query_calificaciones_altas);
$calificaciones_altas = mysqli_fetch_assoc($res_calif_altas)['total'] ?? 0;

// 5. Tiempo total de actividades
$query_tiempo_total = "SELECT COUNT(*) as total 
                      FROM entregas 
                      WHERE id_alumno = '$alumno_id' 
                      AND estado = 'calificado'";
$res_tiempo = mysqli_query($conn, $query_tiempo_total);
$tiempo_actividades = mysqli_fetch_assoc($res_tiempo)['total'] ?? 0;

// Avatares disponibles con requisitos para desbloquear
$avatares = [
    'panda' => [
        'emoji' => '🐼',
        'color' => '#3A506B',
        'nivel' => 1,
        'nombre' => 'Panda Explorador',
        'descripcion' => 'Tu primer compañero de aventuras',
        'requisito' => 'Completar 1 actividad',
        'requisito_valor' => 1,
        'tipo_requisito' => 'actividades',
        'desbloqueado' => $act_completadas >= 1,
        'precio' => 0,
        'categoria' => 'inicial'
    ],
    'dragon' => [
        'emoji' => '🐉',
        'color' => '#FF6B6B',
        'nivel' => 1,
        'nombre' => 'Dragón Sabio',
        'descripcion' => 'Domina el arte del aprendizaje',
        'requisito' => '2 calificaciones perfectas (10/10)',
        'requisito_valor' => 2,
        'tipo_requisito' => 'perfectas',
        'desbloqueado' => $calificaciones_perfectas >= 2,
        'precio' => 0,
        'categoria' => 'especial'
    ],
    'leon' => [
        'emoji' => '🦁',
        'color' => '#FFD93D',
        'nivel' => 2,
        'nombre' => 'León Valiente',
        'descripcion' => 'El rey de las actividades completadas',
        'requisito' => 'Completar 5 actividades',
        'requisito_valor' => 5,
        'tipo_requisito' => 'actividades',
        'desbloqueado' => $act_completadas >= 5,
        'precio' => 0,
        'categoria' => 'especial'
    ],
    'dino' => [
        'emoji' => '🦖',
        'color' => '#6BCF7F',
        'nivel' => 1,
        'nombre' => 'Dino Curioso',
        'descripcion' => 'Siempre buscando nuevos conocimientos',
        'requisito' => 'Completar 1 curso al 100%',
        'requisito_valor' => 1,
        'tipo_requisito' => 'cursos_completados',
        'desbloqueado' => $cursos_completados >= 1,
        'precio' => 0,
        'categoria' => 'especial'
    ],
    'robot' => [
        'emoji' => '🤖',
        'color' => '#4D96FF',
        'nivel' => 3,
        'nombre' => 'Robot Precise',
        'descripcion' => 'Exactitud y precisión en cada respuesta',
        'requisito' => '3 calificaciones perfectas (10/10)',
        'requisito_valor' => 3,
        'tipo_requisito' => 'perfectas',
        'desbloqueado' => $calificaciones_perfectas >= 3,
        'precio' => 0,
        'categoria' => 'raro'
    ],
    'astronauta' => [
        'emoji' => '👨‍🚀',
        'color' => '#845EC2',
        'nivel' => 4,
        'nombre' => 'Astronauta Estelar',
        'descripcion' => 'Explora los límites del conocimiento',
        'requisito' => 'Completar 10 actividades',
        'requisito_valor' => 10,
        'tipo_requisito' => 'actividades',
        'desbloqueado' => $act_completadas >= 10,
        'precio' => 0,
        'categoria' => 'raro'
    ],
    'superheroe' => [
        'emoji' => '🦸‍♂️',
        'color' => '#FF6B8B',
        'nivel' => 5,
        'nombre' => 'Superhéroe del Saber',
        'descripcion' => 'Salva el día con tu conocimiento',
        'requisito' => '5 calificaciones altas (8+)',
        'requisito_valor' => 5,
        'tipo_requisito' => 'calificaciones_altas',
        'desbloqueado' => $calificaciones_altas >= 5,
        'precio' => 0,
        'categoria' => 'épico'
    ],
    'mago' => [
        'emoji' => '🧙‍♂️',
        'color' => '#00C2A8',
        'nivel' => 6,
        'nombre' => 'Mago del Conocimiento',
        'descripcion' => 'Domina la magia del aprendizaje continuo',
        'requisito' => 'Completar 15 actividades',
        'requisito_valor' => 15,
        'tipo_requisito' => 'actividades',
        'desbloqueado' => $act_completadas >= 15,
        'precio' => 0,
        'categoria' => 'legendario'
    ],
    'fenix' => [
        'emoji' => '🦅',
        'color' => '#FF9A76',
        'nivel' => 7,
        'nombre' => 'Fénix Renacido',
        'descripcion' => 'Renace más fuerte con cada desafío superado',
        'requisito' => '3 cursos completados al 100%',
        'requisito_valor' => 3,
        'tipo_requisito' => 'cursos_completados',
        'desbloqueado' => $cursos_completados >= 3,
        'precio' => 0,
        'categoria' => 'legendario'
    ],
    'ninja' => [
        'emoji' => '🥷',
        'color' => '#4A4A4A',
        'nivel' => 3,
        'nombre' => 'Ninja del Aprendizaje',
        'descripcion' => 'Silencioso pero mortalmente eficiente',
        'requisito' => '7 actividades completadas',
        'requisito_valor' => 7,
        'tipo_requisito' => 'actividades',
        'desbloqueado' => $act_completadas >= 7,
        'precio' => 0,
        'categoria' => 'especial'
    ],
    'unicornio' => [
        'emoji' => '🦄',
        'color' => '#D65DB1',
        'nivel' => 8,
        'nombre' => 'Unicornio Mágico',
        'descripcion' => 'Hace realidad los sueños de aprendizaje',
        'requisito' => '10 calificaciones perfectas (10/10)',
        'requisito_valor' => 10,
        'tipo_requisito' => 'perfectas',
        'desbloqueado' => $calificaciones_perfectas >= 10,
        'precio' => 0,
        'categoria' => 'mitico'
    ],
    'robot_avanzado' => [
        'emoji' => '🤖',
        'color' => '#2E86AB',
        'nivel' => 5,
        'nombre' => 'Robot Avanzado',
        'descripcion' => 'Inteligencia artificial en su máximo esplendor',
        'requisito' => '8 calificaciones altas (8+)',
        'requisito_valor' => 8,
        'tipo_requisito' => 'calificaciones_altas',
        'desbloqueado' => $calificaciones_altas >= 8,
        'precio' => 0,
        'categoria' => 'épico'
    ]
];

// =============================================
// CORREGIDO PARA POSTGRESQL - Obtener avatar actual
// =============================================
$avatar_actual = 'panda';
$query_avatar = "SELECT COALESCE(avatar, 'panda') as avatar FROM usuarios WHERE id = '$alumno_id'";
$res_avatar = mysqli_query($conn, $query_avatar);
if($res_avatar && mysqli_num_rows($res_avatar) > 0) {
    $avatar_data = mysqli_fetch_assoc($res_avatar);
    $avatar_actual = $avatar_data['avatar'];
}

// =============================================
// CORREGIDO PARA POSTGRESQL - Función actualizar avatar
// =============================================
function actualizarAvatar($conn, $alumno_id, $nuevo_avatar) {
    // Actualizar directamente (asumiendo que la columna existe)
    $sql = "UPDATE usuarios SET avatar = '$nuevo_avatar' WHERE id = '$alumno_id'";
    return mysqli_query($conn, $sql);
}

// Procesar cambio de avatar si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seleccionar_avatar'])) {
    $nuevo_avatar = $_POST['avatar'];
    
    // Verificar que el avatar existe y está desbloqueado
    if (isset($avatares[$nuevo_avatar]) && $avatares[$nuevo_avatar]['desbloqueado']) {
        if (actualizarAvatar($conn, $alumno_id, $nuevo_avatar)) {
            $avatar_actual = $nuevo_avatar;
            $mensaje_exito = "¡Avatar cambiado exitosamente a " . $avatares[$nuevo_avatar]['nombre'] . "!";
        } else {
            $mensaje_error = "Error al cambiar el avatar. Intenta de nuevo.";
        }
    } else {
        $mensaje_error = "Este avatar aún no está desbloqueado o no existe.";
    }
}

// Contar avatares desbloqueados
$avatares_desbloqueados = 0;
foreach ($avatares as $avatar) {
    if ($avatar['desbloqueado']) {
        $avatares_desbloqueados++;
    }
}

// Calcular progreso de desbloqueo
$progreso_desbloqueo = ($avatares_desbloqueados / count($avatares)) * 100;

// Obtener información del avatar actual
$avatar_actual_info = $avatares[$avatar_actual] ?? $avatares['panda'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tienda de Avatares - D&F Mindspace</title>
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
            --bronze: #CD7F32;
            --silver: #C0C0C0;
            --gold: #FFD700;
            --epic: #9c88ff;
            --legendary: #FF6B8B;
            --mythic: #00C2A8;
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
            background: <?php echo $avatar_actual_info['color']; ?>;
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
        
        /* Encabezado de la tienda */
        .shop-header {
            background: linear-gradient(135deg, #ffffff, #f8fdff);
            border-radius: 25px;
            padding: 30px;
            margin-bottom: 40px;
            box-shadow: var(--card-shadow);
            border: 3px solid rgba(44, 186, 236, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .shop-header::before {
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
            background: linear-gradient(90deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }
        
        /* Avatar actual destacado */
        .current-avatar-card {
            background: white;
            border-radius: 25px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
            border: 3px solid var(--primary);
            text-align: center;
        }
        
        .current-avatar-display {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: <?php echo $avatar_actual_info['color']; ?>;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 15px 30px rgba(0,0,0,0.2);
            border: 5px solid white;
        }
        
        .current-avatar-emoji {
            font-size: 6rem;
            filter: drop-shadow(3px 3px 6px rgba(0,0,0,0.3));
        }
        
        .current-avatar-name {
            font-family: 'Fredoka One', cursive;
            font-size: 1.8rem;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .current-avatar-level {
            display: inline-block;
            padding: 8px 20px;
            background: linear-gradient(90deg, var(--secondary), #f5c15d);
            color: white;
            border-radius: 20px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        /* Estadísticas de progreso */
        .progress-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .progress-stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            text-align: center;
            border: 3px solid transparent;
            transition: all 0.3s ease;
        }
        
        .progress-stat-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
        }
        
        .progress-stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin: 0 auto 15px;
            color: white;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }
        
        .progress-stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
            margin: 10px 0;
            font-family: 'Fredoka One', cursive;
        }
        
        .progress-stat-label {
            color: #666;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        /* Progreso de desbloqueo */
        .unlock-progress-container {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
            border: 3px solid rgba(44, 186, 236, 0.1);
        }
        
        .unlock-progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .unlock-progress-title {
            font-family: 'Fredoka One', cursive;
            font-size: 1.5rem;
            color: var(--primary);
        }
        
        .unlock-progress-value {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(90deg, var(--accent), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .unlock-progress-bar {
            height: 15px;
            background: rgba(44, 186, 236, 0.1);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        
        .unlock-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--accent), var(--primary));
            border-radius: 10px;
            transition: width 1s ease-in-out;
        }
        
        .unlock-progress-text {
            display: flex;
            justify-content: space-between;
            color: #666;
            font-size: 0.9rem;
        }
        
        /* Grid de avatares */
        .avatars-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 50px;
        }
        
        .avatar-card {
            background: white;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: all 0.4s ease;
            border: 3px solid transparent;
            position: relative;
        }
        
        .avatar-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(44, 186, 236, 0.25);
        }
        
        .avatar-card.desbloqueado:hover {
            border-color: var(--accent);
        }
        
        .avatar-card.bloqueado {
            opacity: 0.7;
            filter: grayscale(30%);
        }
        
        .avatar-header {
            padding: 25px 25px 15px;
            position: relative;
            overflow: hidden;
            text-align: center;
        }
        
        .avatar-emoji-container {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            margin: 0 auto 15px;
            color: white;
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            border: 4px solid white;
            position: relative;
            z-index: 1;
        }
        
        .avatar-category-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 6px 12px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 0.75rem;
            color: white;
            z-index: 2;
        }
        
        .categoria-inicial { background: var(--bronze); }
        .categoria-especial { background: var(--primary); }
        .categoria-raro { background: var(--purple); }
        .categoria-epico { background: var(--epic); }
        .categoria-legendario { background: var(--legendary); }
        .categoria-mitico { background: var(--mythic); }
        
        .avatar-level {
            position: absolute;
            top: 15px;
            left: 15px;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: var(--secondary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            z-index: 2;
        }
        
        .avatar-title {
            font-family: 'Fredoka One', cursive;
            font-size: 1.4rem;
            color: white;
            margin-bottom: 5px;
            position: relative;
            z-index: 1;
        }
        
        .avatar-body {
            padding: 20px;
        }
        
        .avatar-description {
            color: #666;
            margin-bottom: 15px;
            line-height: 1.5;
            font-size: 0.95rem;
        }
        
        .avatar-requirement {
            background: rgba(44, 186, 236, 0.1);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .requirement-label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 5px;
        }
        
        .requirement-value {
            font-weight: 700;
            color: var(--primary);
        }
        
        .requirement-progress {
            margin-top: 8px;
        }
        
        .requirement-progress-bar {
            height: 6px;
            background: rgba(44, 186, 236, 0.2);
            border-radius: 3px;
            overflow: hidden;
        }
        
        .requirement-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--accent), var(--primary));
            border-radius: 3px;
            transition: width 0.5s ease;
        }
        
        .avatar-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-avatar {
            flex: 1;
            padding: 12px;
            border-radius: 15px;
            font-weight: 700;
            text-align: center;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: none;
            cursor: pointer;
        }
        
        .btn-select {
            background: linear-gradient(90deg, var(--accent), #6aab39);
            color: white;
        }
        
        .btn-select:hover {
            background: linear-gradient(90deg, #6aab39, var(--accent));
            transform: translateY(-3px);
        }
        
        .btn-current {
            background: linear-gradient(90deg, var(--primary), #2ca5d4);
            color: white;
        }
        
        .btn-locked {
            background: #ddd;
            color: #888;
            cursor: not-allowed;
        }
        
        .btn-locked:hover {
            transform: none;
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
        
        /* Efecto de desbloqueo */
        .unlocked-badge {
            position: absolute;
            top: -10px;
            right: -10px;
            width: 40px;
            height: 40px;
            background: var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            box-shadow: 0 4px 10px rgba(131, 191, 70, 0.3);
            z-index: 3;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        /* Efecto brillante para avatar actual */
        .current-glow {
            position: relative;
        }
        
        .current-glow::after {
            content: '';
            position: absolute;
            top: -5px;
            left: -5px;
            right: -5px;
            bottom: -5px;
            border-radius: 50%;
            background: var(--primary);
            z-index: -1;
            opacity: 0.3;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.3; }
            50% { transform: scale(1.1); opacity: 0.5; }
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .avatars-grid {
                grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
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
            
            .avatars-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .progress-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .avatar-actions {
                flex-direction: column;
            }
        }
        
        @media (max-width: 576px) {
            .progress-stats {
                grid-template-columns: 1fr;
            }
            
            .header-title {
                font-size: 1.8rem;
            }
        }
        
        /* Mensajes de éxito/error */
        .alert-message {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            animation: slideInRight 0.5s ease;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        /* Filtros de categoría */
        .category-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 25px;
            padding: 20px;
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
        }
        
        .filter-btn {
            padding: 10px 20px;
            border-radius: 15px;
            border: 2px solid var(--primary);
            background: white;
            color: var(--primary);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .filter-btn:hover, .filter-btn.active {
            background: var(--primary);
            color: white;
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
                    <span class="avatar-emoji"><?php echo $avatar_actual_info['emoji']; ?></span>
                </div>
                
                <h4 class="kid-name"><?php echo $nombre_alumno; ?></h4>
                <span class="kid-level">
                    <i class="fas fa-star me-1"></i>Nivel <?php echo $avatar_actual_info['nivel']; ?>
                </span>
                
                <!-- Estadísticas rápidas -->
                <div style="text-align: center; padding: 15px; background: linear-gradient(135deg, rgba(44, 186, 236, 0.1), rgba(44, 186, 236, 0.05)); border-radius: 15px; margin: 15px;">
                    <div style="font-size: 1.8rem; font-family: 'Fredoka One', cursive; color: var(--accent); margin: 5px 0;">
                        <?php echo $avatares_desbloqueados; ?>/<?php echo count($avatares); ?>
                    </div>
                    <div style="color: #666; font-size: 0.9rem;">Avatares Desbloqueados</div>
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
                    <a href="mis_actividades.php" class="nav-link">
                        <i class="fas fa-tasks"></i>
                        <span>Mis Misiones</span>
                        <?php if(mysqli_num_rows($res_actividades) > 0): ?>
                            <span class="badge-notification ms-auto"><?php echo mysqli_num_rows($res_actividades); ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="avatar_shop.php" class="nav-link active">
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
        
        <!-- Mensajes de éxito/error -->
        <?php if(isset($mensaje_exito)): ?>
            <div class="alert alert-success alert-dismissible fade show alert-message" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $mensaje_exito; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if(isset($mensaje_error)): ?>
            <div class="alert alert-danger alert-dismissible fade show alert-message" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $mensaje_error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <!-- Encabezado de la tienda -->
        <div class="shop-header fade-in-up">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="header-title">¡Tienda de Avatares! 🎭</h1>
                    <p class="fs-5 text-muted">
                        Desbloquea avatares especiales demostrando tu progreso y habilidades.
                        ¡Cada avatar cuenta una historia de tu aprendizaje!
                    </p>
                </div>
                <div class="col-lg-4 text-center">
                    <div class="kid-avatar current-glow" style="width: 120px; height: 120px;">
                        <span class="avatar-emoji" style="font-size: 5rem;"><?php echo $avatar_actual_info['emoji']; ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Avatar actual destacado -->
        <div class="current-avatar-card fade-in-up" style="animation-delay: 0.1s">
            <div class="current-avatar-display">
                <span class="current-avatar-emoji"><?php echo $avatar_actual_info['emoji']; ?></span>
            </div>
            <h3 class="current-avatar-name"><?php echo $avatar_actual_info['nombre']; ?></h3>
            <span class="current-avatar-level">
                <i class="fas fa-star me-1"></i> Nivel <?php echo $avatar_actual_info['nivel']; ?>
            </span>
            <p class="text-muted mb-0">
                <?php echo $avatar_actual_info['descripcion']; ?>
            </p>
        </div>
        
        <!-- Estadísticas de progreso -->
        <div class="progress-stats fade-in-up" style="animation-delay: 0.2s">
            <div class="progress-stat-card">
                <div class="progress-stat-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="progress-stat-value"><?php echo $act_completadas; ?></div>
                <div class="progress-stat-label">Actividades Completadas</div>
            </div>
            
            <div class="progress-stat-card">
                <div class="progress-stat-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="progress-stat-value"><?php echo $calificaciones_perfectas; ?></div>
                <div class="progress-stat-label">Calificaciones Perfectas (10/10)</div>
            </div>
            
            <div class="progress-stat-card">
                <div class="progress-stat-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="progress-stat-value"><?php echo $cursos_completados; ?></div>
                <div class="progress-stat-label">Cursos Completados</div>
            </div>
            
            <div class="progress-stat-card">
                <div class="progress-stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="progress-stat-value"><?php echo $calificaciones_altas; ?></div>
                <div class="progress-stat-label">Calificaciones Altas (8+)</div>
            </div>
        </div>
        
        <!-- Progreso de desbloqueo -->
        <div class="unlock-progress-container fade-in-up" style="animation-delay: 0.3s">
            <div class="unlock-progress-header">
                <h3 class="unlock-progress-title">Tu Colección de Avatares</h3>
                <div class="unlock-progress-value">
                    <?php echo round($progreso_desbloqueo, 0); ?>%
                </div>
            </div>
            
            <div class="unlock-progress-bar">
                <div class="unlock-progress-fill" style="width: <?php echo $progreso_desbloqueo; ?>%"></div>
            </div>
            
            <div class="unlock-progress-text">
                <span><?php echo $avatares_desbloqueados; ?> desbloqueados</span>
                <span><?php echo count($avatares); ?> total</span>
            </div>
        </div>
        
        <!-- Filtros de categoría -->
        <div class="category-filters fade-in-up" style="animation-delay: 0.4s">
            <button class="filter-btn active" data-category="todos">
                <i class="fas fa-globe me-2"></i>Todos
            </button>
            <button class="filter-btn" data-category="inicial">
                <i class="fas fa-seedling me-2"></i>Inicial
            </button>
            <button class="filter-btn" data-category="especial">
                <i class="fas fa-star me-2"></i>Especial
            </button>
            <button class="filter-btn" data-category="raro">
                <i class="fas fa-gem me-2"></i>Raro
            </button>
            <button class="filter-btn" data-category="epico">
                <i class="fas fa-crown me-2"></i>Épico
            </button>
            <button class="filter-btn" data-category="legendario">
                <i class="fas fa-fire me-2"></i>Legendario
            </button>
            <button class="filter-btn" data-category="mitico">
                <i class="fas fa-bolt me-2"></i>Mítico
            </button>
            <button class="filter-btn" data-category="desbloqueados">
                <i class="fas fa-unlock me-2"></i>Desbloqueados
            </button>
            <button class="filter-btn" data-category="bloqueados">
                <i class="fas fa-lock me-2"></i>Por Desbloquear
            </button>
        </div>
        
        <!-- Grid de avatares -->
        <div class="avatars-grid">
            <?php foreach($avatares as $key => $avatar): 
                $card_class = $avatar['desbloqueado'] ? 'desbloqueado' : 'bloqueado';
                $categoria_class = 'categoria-' . $avatar['categoria'];
                
                // Calcular progreso del requisito
                $progreso_requisito = 0;
                switch($avatar['tipo_requisito']) {
                    case 'actividades':
                        $progreso_requisito = min(100, ($act_completadas / $avatar['requisito_valor']) * 100);
                        break;
                    case 'perfectas':
                        $progreso_requisito = min(100, ($calificaciones_perfectas / $avatar['requisito_valor']) * 100);
                        break;
                    case 'cursos_completados':
                        $progreso_requisito = min(100, ($cursos_completados / $avatar['requisito_valor']) * 100);
                        break;
                    case 'calificaciones_altas':
                        $progreso_requisito = min(100, ($calificaciones_altas / $avatar['requisito_valor']) * 100);
                        break;
                }
            ?>
            <div class="avatar-card fade-in-up <?php echo $card_class; ?>" data-categoria="<?php echo $avatar['categoria']; ?>" data-estado="<?php echo $card_class; ?>">
                <?php if($avatar['desbloqueado']): ?>
                    <div class="unlocked-badge">
                        <i class="fas fa-unlock"></i>
                    </div>
                <?php endif; ?>
                
                <div class="avatar-header" style="background: <?php echo $avatar['color']; ?>;">
                    <span class="avatar-category-badge <?php echo $categoria_class; ?>">
                        <?php echo ucfirst($avatar['categoria']); ?>
                    </span>
                    
                    <span class="avatar-level">
                        <?php echo $avatar['nivel']; ?>
                    </span>
                    
                    <div class="avatar-emoji-container" style="background: <?php echo $avatar['color']; ?>;">
                        <?php echo $avatar['emoji']; ?>
                    </div>
                    
                    <h3 class="avatar-title"><?php echo $avatar['nombre']; ?></h3>
                </div>
                
                <div class="avatar-body">
                    <p class="avatar-description">
                        <?php echo $avatar['descripcion']; ?>
                    </p>
                    
                    <div class="avatar-requirement">
                        <div class="requirement-label">
                            <i class="fas fa-lock me-1"></i> Requisito para desbloquear:
                        </div>
                        <div class="requirement-value">
                            <?php echo $avatar['requisito']; ?>
                        </div>
                        
                        <div class="requirement-progress">
                            <div class="requirement-progress-bar">
                                <div class="requirement-progress-fill" style="width: <?php echo $progreso_requisito; ?>%"></div>
                            </div>
                            <small class="text-muted">
                                <?php 
                                $actual = 0;
                                switch($avatar['tipo_requisito']) {
                                    case 'actividades': $actual = $act_completadas; break;
                                    case 'perfectas': $actual = $calificaciones_perfectas; break;
                                    case 'cursos_completados': $actual = $cursos_completados; break;
                                    case 'calificaciones_altas': $actual = $calificaciones_altas; break;
                                }
                                echo $actual . '/' . $avatar['requisito_valor'];
                                ?>
                            </small>
                        </div>
                    </div>
                    
                    <form method="POST" class="avatar-actions">
                        <?php if($avatar['desbloqueado']): ?>
                            <?php if($key == $avatar_actual): ?>
                                <button type="button" class="btn-avatar btn-current" disabled>
                                    <i class="fas fa-check me-2"></i>
                                    Actual
                                </button>
                            <?php else: ?>
                                <button type="submit" name="seleccionar_avatar" class="btn-avatar btn-select">
                                    <i class="fas fa-user-check me-2"></i>
                                    Seleccionar
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <button type="button" class="btn-avatar btn-locked" disabled>
                                <i class="fas fa-lock me-2"></i>
                                Bloqueado
                            </button>
                        <?php endif; ?>
                        <input type="hidden" name="avatar" value="<?php echo $key; ?>">
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Información de requisitos -->
        <div class="side-panel fade-in-up" style="animation-delay: 0.5s">
            <h4 class="panel-title">
                <i class="fas fa-info-circle"></i>
                ¿Cómo desbloquear más avatares?
            </h4>
            
            <div class="activity-list">
                <div class="activity-item">
                    <div class="activity-icon" style="background: linear-gradient(135deg, var(--bronze), #CD7F32);">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title">Completa Actividades</div>
                        <div class="activity-meta">
                            <span>
                                Cada actividad completada te acerca a nuevos avatares
                            </span>
                        </div>
                    </div>
                    <span class="activity-status status-obtained">
                        <?php echo $act_completadas; ?> completadas
                    </span>
                </div>
                
                <div class="activity-item">
                    <div class="activity-icon" style="background: linear-gradient(135deg, var(--gold), #FFD700);">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title">Busca Calificaciones Perfectas</div>
                        <div class="activity-meta">
                            <span>
                                Obtén 10/10 en tus actividades para avatares especiales
                            </span>
                        </div>
                    </div>
                    <span class="activity-status status-obtained">
                        <?php echo $calificaciones_perfectas; ?> perfectas
                    </span>
                </div>
                
                <div class="activity-item">
                    <div class="activity-icon" style="background: linear-gradient(135deg, var(--primary), #2ca5d4);">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title">Termina Tus Cursos</div>
                        <div class="activity-meta">
                            <span>
                                Completa cursos al 100% para avatares exclusivos
                            </span>
                        </div>
                    </div>
                    <span class="activity-status status-obtained">
                        <?php echo $cursos_completados; ?> completados
                    </span>
                </div>
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
        
        // Filtros de categoría
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                // Remover clase active de todos los botones
                document.querySelectorAll('.filter-btn').forEach(b => {
                    b.classList.remove('active');
                });
                
                // Agregar clase active al botón clickeado
                this.classList.add('active');
                
                const categoria = this.dataset.category;
                const avatarCards = document.querySelectorAll('.avatar-card');
                
                avatarCards.forEach(card => {
                    const cardCategoria = card.dataset.categoria;
                    const cardEstado = card.dataset.estado;
                    
                    if (categoria === 'todos') {
                        card.style.display = 'block';
                    } else if (categoria === 'desbloqueados') {
                        card.style.display = cardEstado === 'desbloqueado' ? 'block' : 'none';
                    } else if (categoria === 'bloqueados') {
                        card.style.display = cardEstado === 'bloqueado' ? 'block' : 'none';
                    } else {
                        card.style.display = cardCategoria === categoria ? 'block' : 'none';
                    }
                });
            });
        });
        
        // Efecto hover para tarjetas de avatar
        document.addEventListener('DOMContentLoaded', function() {
            const avatarCards = document.querySelectorAll('.avatar-card.desbloqueado');
            
            avatarCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    const emojiContainer = this.querySelector('.avatar-emoji-container');
                    if (emojiContainer) {
                        emojiContainer.style.transform = 'scale(1.1) rotate(5deg)';
                    }
                });
                
                card.addEventListener('mouseleave', function() {
                    const emojiContainer = this.querySelector('.avatar-emoji-container');
                    if (emojiContainer) {
                        emojiContainer.style.transform = 'scale(1) rotate(0deg)';
                    }
                });
            });
            
            // Animar barras de progreso
            const progressBars = document.querySelectorAll('.unlock-progress-fill, .requirement-progress-fill');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                
                setTimeout(() => {
                    bar.style.transition = 'width 1.5s ease-in-out';
                    bar.style.width = width;
                }, 300);
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
        });
        
        // Confirmación al seleccionar avatar
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const avatarName = this.querySelector('button')?.textContent.trim();
                if (avatarName.includes('Seleccionar')) {
                    if (!confirm('¿Estás seguro de que quieres cambiar tu avatar?')) {
                        e.preventDefault();
                    }
                }
            });
        });
        
        // Auto-ocultar mensajes de alerta después de 5 segundos
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-message');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>