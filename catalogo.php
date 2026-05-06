<?php
include 'php/config.php';
session_start();

// Seguridad: Solo alumnos pueden ver el catálogo
if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'alumno') {
    header("Location: index.php");
    exit();
}

$alumno_id = $_SESSION['user_id'];
$nombre_alumno = $_SESSION['nombre'];

// Consultar cursos que estén activos y en los que el alumno NO esté inscrito aún
$query = "SELECT c.*, u.nombre as tutor_nombre 
          FROM cursos c 
          JOIN usuarios u ON c.id_tutor = u.id 
          WHERE c.activo = TRUE 
          AND c.id NOT IN (SELECT id_curso FROM inscripciones WHERE id_alumno = '$alumno_id')
          ORDER BY c.fecha_creacion DESC";
$resultado = mysqli_query($conn, $query);

// Consultar cursos en los que está inscrito
$query_cursos = "SELECT c.nombre, c.descripcion, i.progreso, i.id_curso, c.nivel 
                 FROM inscripciones i 
                 JOIN cursos c ON i.id_curso = c.id 
                 WHERE i.id_alumno = '$alumno_id' AND i.estado = 'activo'";
$res_cursos = mysqli_query($conn, $query_cursos);

// Consultar notificaciones recientes
$query_notif = "SELECT * FROM notificaciones WHERE id_usuario = '$alumno_id' AND leido = FALSE LIMIT 5";
$res_notif = mysqli_query($conn, $query_notif);

// CORREGIDO: Consultar actividades recientes con calificación desde tabla evaluaciones
$query_actividades = "SELECT a.titulo, e.fecha_entrega, e.estado, ev.calificacion 
                      FROM entregas e 
                      JOIN actividades a ON e.id_actividad = a.id 
                      LEFT JOIN evaluaciones ev ON e.id = ev.id_entrega
                      WHERE e.id_alumno = '$alumno_id' 
                      ORDER BY e.fecha_entrega DESC LIMIT 4";
$res_actividades = mysqli_query($conn, $query_actividades);

// CORREGIDO: Verificar si la tabla alumnos existe, sino usar tabla usuarios
// Primero verificamos si existe la tabla alumnos
$check_table = mysqli_query($conn, "SHOW TABLES LIKE 'alumnos'");
$puntos_alumno = 0;


// Obtener avatar del alumno (mismo sistema que en el dashboard)
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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo de Aventuras - D&F Mindspace</title>
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
        
        /* Encabezado del catálogo */
        .catalog-header {
            background: linear-gradient(135deg, #ffffff, #f8fdff);
            border-radius: 25px;
            padding: 40px;
            margin-bottom: 40px;
            box-shadow: var(--card-shadow);
            border: 3px solid rgba(44, 186, 236, 0.1);
            position: relative;
            overflow: hidden;
            text-align: center;
        }
        
        .catalog-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(44, 186, 236, 0.05));
        }
        
        /* ICONO MEJORADO - En lugar de emoji */
        .header-icon {
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
        
        .catalog-title {
            font-family: 'Fredoka One', cursive;
            font-size: 3rem;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 15px;
            line-height: 1.2;
        }
        
        .catalog-subtitle {
            font-size: 1.3rem;
            color: #666;
            max-width: 600px;
            margin: 0 auto 30px;
        }
        
        /* Grid de cursos del catálogo */
        .catalog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }
        
        .catalog-card {
            background: white;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: all 0.4s ease;
            border: 3px solid transparent;
            display: flex;
            flex-direction: column;
            position: relative;
            animation: fadeInUp 0.6s ease-out;
        }
        
        .catalog-card:hover {
            transform: translateY(-15px) scale(1.02);
            box-shadow: 0 25px 50px rgba(44, 186, 236, 0.25);
            border-color: var(--primary);
        }
        
        .catalog-card-header {
            padding: 30px 30px 20px;
            position: relative;
            overflow: hidden;
            min-height: 180px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }
        
        .catalog-card-header::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(0deg, rgba(0,0,0,0.3), rgba(0,0,0,0.1));
            z-index: 1;
        }
        
        .catalog-card-bg {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-size: cover;
            background-position: center;
            transition: transform 0.5s ease;
        }
        
        .catalog-card:hover .catalog-card-bg {
            transform: scale(1.1);
        }
        
        .catalog-card-content {
            position: relative;
            z-index: 2;
        }
        
        .catalog-card-badge {
            display: inline-block;
            background: rgba(255,255,255,0.3);
            backdrop-filter: blur(10px);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .catalog-card-title {
            font-family: 'Fredoka One', cursive;
            font-size: 1.8rem;
            color: white;
            margin-bottom: 10px;
            line-height: 1.3;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .catalog-card-body {
            padding: 25px 30px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .catalog-card-description {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.6;
            flex: 1;
        }
        
        .catalog-card-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding: 15px;
            background: rgba(44, 186, 236, 0.05);
            border-radius: 15px;
        }
        
        .catalog-card-tutor {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .tutor-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--secondary), #f5c15d);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .catalog-card-duration {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            font-weight: 600;
        }
        
        .catalog-card-actions {
            display: flex;
            gap: 10px;
        }
        
        /* BOTONES REDONDEADOS - Mejorados */
        .btn-catalog {
            flex: 1;
            padding: 14px 20px;
            border-radius: 15px;
            font-weight: 700;
            text-align: center;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 1.1rem;
            border: none;
            cursor: pointer;
        }
        
        .btn-join {
            background: linear-gradient(90deg, var(--accent), #6aab39);
            color: white;
            box-shadow: 0 5px 15px rgba(131, 191, 70, 0.3);
        }
        
        .btn-join:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 20px rgba(131, 191, 70, 0.4);
            color: white;
        }
        
        .btn-details {
            background: white;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-details:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-3px) scale(1.02);
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
        
        /* Estilos de nivel */
        .level-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 3;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.9rem;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        
        .level-basic {
            background: linear-gradient(90deg, var(--primary), #2ca5d4);
            color: white;
        }
        
        .level-intermediate {
            background: linear-gradient(90deg, var(--secondary), #f5c15d);
            color: white;
        }
        
        .level-advanced {
            background: linear-gradient(90deg, var(--accent), #6aab39);
            color: white;
        }
        
        /* FILTROS REDONDEADOS - Mejorados */
        .filter-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .filter-btn {
            padding: 12px 25px;
            border: 2px solid rgba(44, 186, 236, 0.2);
            background: white;
            border-radius: 15px;
            font-weight: 700;
            color: #666;
            transition: all 0.3s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: 'Nunito', sans-serif;
        }
        
        .filter-btn:hover, .filter-btn.active {
            background: linear-gradient(90deg, var(--primary), var(--accent));
            color: white;
            border-color: transparent;
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 5px 15px rgba(44, 186, 236, 0.3);
        }
        
        /* Buscador redondeado */
        .search-box {
            position: relative;
            margin-bottom: 30px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .search-box input {
            width: 100%;
            padding: 16px 20px 16px 55px;
            border: 2px solid rgba(44, 186, 236, 0.2);
            border-radius: 15px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            background: white;
            font-family: 'Nunito', sans-serif;
        }
        
        .search-box input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(44, 186, 236, 0.1);
            outline: none;
        }
        
        .search-box i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            font-size: 1.3rem;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .catalog-grid {
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
                padding: 20px;
            }
            
            .menu-toggle {
                display: block;
            }
            
            .catalog-title {
                font-size: 2.5rem;
            }
            
            .header-icon {
                font-size: 3.5rem;
            }
            
            .catalog-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .catalog-title {
                font-size: 2.2rem;
            }
            
            .header-icon {
                font-size: 3rem;
            }
            
            .catalog-card-actions {
                flex-direction: column;
            }
            
            .filter-buttons {
                justify-content: center;
            }
            
            .filter-btn {
                padding: 10px 20px;
            }
        }
        
        @media (max-width: 576px) {
            .catalog-title {
                font-size: 1.8rem;
            }
            
            .header-icon {
                font-size: 2.5rem;
            }
            
            .catalog-subtitle {
                font-size: 1.1rem;
            }
            
            .catalog-header {
                padding: 30px 20px;
            }
            
            .filter-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .filter-btn {
                width: 100%;
                justify-content: center;
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
        
        /* Mensaje cuando no hay cursos */
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
        
        /* Modal de felicitaciones - NUEVO */
        .celebration-modal .modal-content {
            border-radius: 25px;
            overflow: hidden;
            border: none;
        }
        
        .celebration-header {
            background: linear-gradient(135deg, var(--accent), #6aab39);
            color: white;
            padding: 30px;
            text-align: center;
            border: none;
            position: relative;
            overflow: hidden;
        }
        
        .celebration-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            right: -50%;
            bottom: -50%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1%, transparent 20%);
            animation: pulse 2s infinite;
        }
        
        .celebration-icon {
            font-size: 5rem;
            margin-bottom: 20px;
            animation: bounce 1s infinite;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-20px) scale(1.1); }
        }
        
        .confetti {
            position: absolute;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            opacity: 0;
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
                    <a href="catalogo.php" class="nav-link active">
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
        
        <!-- Encabezado del catálogo -->
        <div class="catalog-header fade-in-up">
            <!-- Estrellas decorativas -->
            <i class="fas fa-star floating-star" style="top: 30px; right: 100px; animation-delay: 0s;"></i>
            <i class="fas fa-star floating-star" style="bottom: 40px; left: 80px; animation-delay: 1s;"></i>
            <i class="fas fa-star floating-star" style="top: 60px; left: 150px; animation-delay: 2s;"></i>
            
            <!-- ICONO MEJORADO - Sin emoji -->
            <div class="header-icon">
                <i class="fas fa-globe-americas"></i>
            </div>
            
            <h1 class="catalog-title">Descubre Nuevos Mundos</h1>
            <p class="catalog-subtitle">
                ¡Embárcate en aventuras emocionantes! Cada curso es una nueva oportunidad 
                para aprender, explorar y convertirte en un verdadero explorador del conocimiento.
            </p>
            
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="¿Qué aventura buscas hoy? Ej: Matemáticas, Ciencias...">
            </div>
            
            <!-- FILTROS REDONDEADOS -->
            <div class="filter-buttons">
                <button class="filter-btn active" data-filter="all">
                    <i class="fas fa-layer-group"></i> Todas
                </button>
                <button class="filter-btn" data-filter="básico">
                    <i class="fas fa-seedling"></i> Básico
                </button>
                <button class="filter-btn" data-filter="intermedio">
                    <i class="fas fa-star"></i> Intermedio
                </button>
                <button class="filter-btn" data-filter="avanzado">
                    <i class="fas fa-rocket"></i> Avanzado
                </button>
            </div>
        </div>
        
        <!-- Grid de cursos del catálogo -->
        <?php if(mysqli_num_rows($resultado) > 0): ?>
            <div class="catalog-grid">
                <?php while($curso = mysqli_fetch_assoc($resultado)): 
                    // Determinar color según nivel
                    $nivel_class = '';
                    switch(strtolower($curso['nivel'])) {
                        case 'básico': $nivel_class = 'level-basic'; break;
                        case 'intermedio': $nivel_class = 'level-intermediate'; break;
                        case 'avanzado': $nivel_class = 'level-advanced'; break;
                        default: $nivel_class = 'level-basic';
                    }
                    
                    // Generar gradiente de fondo según nivel
                    $bg_gradient = '';
                    switch(strtolower($curso['nivel'])) {
                        case 'básico': 
                            $bg_gradient = 'linear-gradient(135deg, var(--primary), #2ca5d4)';
                            break;
                        case 'intermedio': 
                            $bg_gradient = 'linear-gradient(135deg, var(--secondary), #f5c15d)';
                            break;
                        case 'avanzado': 
                            $bg_gradient = 'linear-gradient(135deg, var(--accent), #6aab39)';
                            break;
                        default: 
                            $bg_gradient = 'linear-gradient(135deg, var(--primary), #2ca5d4)';
                    }
                    
                    // Obtener inicial del tutor
                    $tutor_inicial = strtoupper(substr($curso['tutor_nombre'], 0, 1));
                ?>
                <div class="catalog-card" data-nivel="<?php echo strtolower($curso['nivel']); ?>" 
                     data-nombre="<?php echo htmlspecialchars(strtolower($curso['nombre'])); ?>"
                     data-descripcion="<?php echo htmlspecialchars(strtolower($curso['descripcion'])); ?>">
                    
                    <!-- Encabezado con fondo dinámico -->
                    <div class="catalog-card-header">
                        <div class="catalog-card-bg" style="background: <?php echo $bg_gradient; ?>;"></div>
                        <div class="catalog-card-content">
                            <span class="catalog-card-badge">
                                <i class="fas fa-graduation-cap me-1"></i>
                                <?php echo $curso['nivel']; ?>
                            </span>
                            <h3 class="catalog-card-title"><?php echo htmlspecialchars($curso['nombre']); ?></h3>
                        </div>
                        
                        <!-- Badge de nivel -->
                        <div class="level-badge <?php echo $nivel_class; ?>">
                            <?php 
                            switch(strtolower($curso['nivel'])) {
                                case 'básico': echo '🌟 Para Iniciar'; break;
                                case 'intermedio': echo '🚀 Para Avanzar'; break;
                                case 'avanzado': echo '🔥 Para Expertos'; break;
                                default: echo '🎯 Nueva Aventura';
                            }
                            ?>
                        </div>
                    </div>
                    
                    <!-- Cuerpo de la tarjeta -->
                    <div class="catalog-card-body">
                        <p class="catalog-card-description">
                            <?php echo htmlspecialchars($curso['descripcion']); ?>
                        </p>
                        
                        <div class="catalog-card-meta">
                            <div class="catalog-card-tutor">
                                <div class="tutor-avatar">
                                    <?php echo $tutor_inicial; ?>
                                </div>
                                <div>
                                    <div class="small fw-bold">Tutor Guía</div>
                                    <div class="small"><?php echo htmlspecialchars($curso['tutor_nombre']); ?></div>
                                </div>
                            </div>
                            
                            <div class="catalog-card-duration">
                                <i class="far fa-clock"></i>
                                <span><?php echo $curso['duracion_horas']; ?> horas</span>
                            </div>
                        </div>
                        
                        <div class="catalog-card-actions">
                            <form action="inscribir_logica.php" method="POST" class="w-100" id="form-<?php echo $curso['id']; ?>">
                                <input type="hidden" name="id_curso" value="<?php echo $curso['id']; ?>">
                                <input type="hidden" name="curso_nombre" value="<?php echo htmlspecialchars($curso['nombre']); ?>">
                                <button type="button" class="btn-catalog btn-join btn-inscribir" 
                                        data-id="<?php echo $curso['id']; ?>"
                                        data-nombre="<?php echo htmlspecialchars($curso['nombre']); ?>">
                                    <i class="fas fa-rocket me-2"></i>
                                    ¡QUIERO UNIRME!
                                </button>
                            </form>
                            
                            <button class="btn-catalog btn-details btn-more-info" 
                                    data-id="<?php echo $curso['id']; ?>"
                                    data-nombre="<?php echo htmlspecialchars($curso['nombre']); ?>"
                                    data-descripcion="<?php echo htmlspecialchars($curso['descripcion']); ?>"
                                    data-nivel="<?php echo $curso['nivel']; ?>"
                                    data-tutor="<?php echo htmlspecialchars($curso['tutor_nombre']); ?>"
                                    data-duracion="<?php echo $curso['duracion_horas']; ?>">
                                <i class="fas fa-info-circle me-2"></i>
                                Ver Más
                            </button>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="no-courses fade-in-up">
                <div class="no-courses-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <h2 class="mb-3" style="font-family: 'Fredoka One', cursive; color: var(--primary);">
                    ¡Eres un Explorador Completo! 🎉
                </h2>
                <p class="text-muted mb-4" style="max-width: 500px; margin: 0 auto;">
                    Ya estás inscrito en todas las aventuras disponibles. 
                    ¡Sigue aprendiendo en tus cursos actuales o vuelve pronto para descubrir nuevas aventuras!
                </p>
                <a href="dashboard_alumno.php" class="btn-join" style="display: inline-flex; align-items: center; gap: 10px; padding: 15px 30px; border-radius: 15px; text-decoration: none;">
                    <i class="fas fa-arrow-left me-2"></i> Volver a Mis Aventuras
                </a>
            </div>
        <?php endif; ?>
        
        <!-- Modal para más información -->
        <div class="modal fade" id="courseModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content" style="border-radius: 25px; overflow: hidden; border: none;">
                    <div class="modal-header" style="background: linear-gradient(135deg, var(--primary), var(--accent)); color: white; border: none;">
                        <h5 class="modal-title" id="modalTitle" style="font-family: 'Fredoka One', cursive;"></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div class="row g-0">
                            <div class="col-md-8 p-4">
                                <h6 class="fw-bold mb-3 text-primary">📖 Sobre esta Aventura</h6>
                                <p id="modalDescription" class="mb-4"></p>
                                
                                <div class="row mb-4">
                                    <div class="col-6">
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <div class="bg-primary bg-opacity-10 p-2 rounded">
                                                <i class="fas fa-user-graduate text-primary"></i>
                                            </div>
                                            <div>
                                                <small class="text-muted d-block">Tutor Guía</small>
                                                <strong id="modalTutor"></strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <div class="bg-primary bg-opacity-10 p-2 rounded">
                                                <i class="fas fa-clock text-primary"></i>
                                            </div>
                                            <div>
                                                <small class="text-muted d-block">Duración</small>
                                                <strong id="modalDuration"></strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 bg-light p-4">
                                <h6 class="fw-bold mb-3 text-primary">🚀 Comienza tu Viaje</h6>
                                <p class="small text-muted mb-4">
                                    Al unirte a esta aventura, desbloquearás:
                                </p>
                                <ul class="list-unstyled mb-4">
                                    <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Misiones emocionantes</li>
                                    <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Puntos de experiencia</li>
                                    <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Logros especiales</li>
                                    <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Apoyo del tutor</li>
                                </ul>
                                
                                <form id="modalInscriptionForm" action="inscribir_logica.php" method="POST">
                                    <input type="hidden" name="id_curso" id="modalCourseId">
                                    <input type="hidden" name="curso_nombre" id="modalCourseName">
                                    <button type="button" class="btn-join w-100" id="modalJoinBtn">
                                        <i class="fas fa-rocket me-2"></i>
                                        ¡UNIRME A LA AVENTURA!
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modal de felicitaciones - NUEVO -->
        <div class="modal fade celebration-modal" id="celebrationModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="celebration-header">
                        <div class="celebration-icon">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <h3 class="fw-bold mb-3" id="celebrationTitle">¡Felicidades!</h3>
                        <p class="mb-0" id="celebrationMessage">Te has unido exitosamente a la aventura.</p>
                    </div>
                    <div class="modal-body p-4 text-center">
                        <p class="mb-4">¡Tu viaje de aprendizaje está por comenzar! Prepárate para descubrir cosas increíbles.</p>
                        
                        <div class="row mb-4">
                            <div class="col-4">
                                <div class="text-center">
                                    <div class="bg-primary bg-opacity-10 p-3 rounded-circle d-inline-block mb-2">
                                        <i class="fas fa-gem fa-2x text-primary"></i>
                                    </div>
                                    <div class="small fw-bold">50 Puntos</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="text-center">
                                    <div class="bg-primary bg-opacity-10 p-3 rounded-circle d-inline-block mb-2">
                                        <i class="fas fa-star fa-2x text-warning"></i>
                                    </div>
                                    <div class="small fw-bold">Nuevo Logro</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="text-center">
                                    <div class="bg-primary bg-opacity-10 p-3 rounded-circle d-inline-block mb-2">
                                        <i class="fas fa-compass fa-2x text-success"></i>
                                    </div>
                                    <div class="small fw-bold">¡A Explorar!</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-3 justify-content-center">
                            <button type="button" class="btn btn-primary" data-bs-dismiss="modal" style="border-radius: 15px; padding: 10px 25px;">
                                <i class="fas fa-check me-2"></i> ¡Entendido!
                            </button>
                            <a href="mis_cursos.php" class="btn btn-outline-primary" style="border-radius: 15px; padding: 10px 25px;">
                                <i class="fas fa-compass me-2"></i> Ir a mis Aventuras
                            </a>
                        </div>
                    </div>
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
        
        // Filtrado y búsqueda de cursos
        const searchInput = document.getElementById('searchInput');
        const filterButtons = document.querySelectorAll('.filter-btn');
        const catalogCards = document.querySelectorAll('.catalog-card');
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            filterCards(searchTerm, getActiveFilter());
        });
        
        filterButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                filterButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                filterCards(searchInput.value.toLowerCase(), this.dataset.filter);
            });
        });
        
        function getActiveFilter() {
            const activeBtn = document.querySelector('.filter-btn.active');
            return activeBtn ? activeBtn.dataset.filter : 'all';
        }
        
        function filterCards(searchTerm, filter) {
            catalogCards.forEach(card => {
                const nombre = card.dataset.nombre;
                const descripcion = card.dataset.descripcion;
                const nivel = card.dataset.nivel;
                
                const matchesSearch = nombre.includes(searchTerm) || descripcion.includes(searchTerm);
                const matchesFilter = filter === 'all' || nivel === filter;
                
                if (matchesSearch && matchesFilter) {
                    card.style.display = 'flex';
                    card.classList.add('fade-in-up');
                } else {
                    card.style.display = 'none';
                }
            });
        }
        
        // Modal para más información
        const courseModal = new bootstrap.Modal(document.getElementById('courseModal'));
        const moreInfoButtons = document.querySelectorAll('.btn-more-info');
        
        moreInfoButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const courseId = this.dataset.id;
                const courseName = this.dataset.nombre;
                const courseDescription = this.dataset.descripcion;
                const courseLevel = this.dataset.nivel;
                const courseTutor = this.dataset.tutor;
                const courseDuration = this.dataset.duracion;
                
                document.getElementById('modalTitle').textContent = courseName;
                document.getElementById('modalDescription').textContent = courseDescription;
                document.getElementById('modalTutor').textContent = courseTutor;
                document.getElementById('modalDuration').textContent = `${courseDuration} horas`;
                document.getElementById('modalCourseId').value = courseId;
                document.getElementById('modalCourseName').value = courseName;
                
                courseModal.show();
            });
        });
        
        // Inscripción con modal de felicitaciones
        const joinButtons = document.querySelectorAll('.btn-inscribir');
        const modalJoinBtn = document.getElementById('modalJoinBtn');
        const celebrationModal = new bootstrap.Modal(document.getElementById('celebrationModal'));
        
        joinButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const courseId = this.dataset.id;
                const courseName = this.dataset.nombre;
                const form = document.getElementById(`form-${courseId}`);
                
                // Mostrar modal de confirmación
                showCelebrationModal(courseName, form);
            });
        });
        
        modalJoinBtn.addEventListener('click', function() {
            const courseId = document.getElementById('modalCourseId').value;
            const courseName = document.getElementById('modalCourseName').value;
            const form = document.getElementById('modalInscriptionForm');
            
            courseModal.hide();
            showCelebrationModal(courseName, form);
        });
        
        function showCelebrationModal(courseName, form) {
            document.getElementById('celebrationTitle').textContent = '¡Te has unido a la aventura!';
            document.getElementById('celebrationMessage').textContent = `¡Felicidades! Ahora formas parte de "${courseName}".`;
            
            // Crear confeti
            createConfetti();
            
            // Mostrar modal
            celebrationModal.show();
            
            // Enviar formulario después de 2 segundos
            setTimeout(() => {
                form.submit();
            }, 2000);
        }
        
        function createConfetti() {
            const colors = ['#2cbaec', '#f0ae2a', '#83bf46', '#ff6b8b', '#9c88ff'];
            const modalElement = document.querySelector('.celebration-modal .modal-content');
            
            for (let i = 0; i < 50; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.background = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.left = Math.random() * 100 + '%';
                confetti.style.top = '-10px';
                
                modalElement.appendChild(confetti);
                
                // Animación del confeti
                confetti.animate([
                    { 
                        transform: 'translate(0, 0) rotate(0deg)', 
                        opacity: 1 
                    },
                    { 
                        transform: `translate(${Math.random() * 200 - 100}px, ${Math.random() * 200 + 100}px) rotate(${Math.random() * 360}deg)`, 
                        opacity: 0 
                    }
                ], {
                    duration: 1000 + Math.random() * 1000,
                    easing: 'cubic-bezier(0.1, 0.8, 0.9, 1)'
                });
                
                setTimeout(() => confetti.remove(), 2000);
            }
        }
        
        // Animar elementos al hacer scroll
        document.addEventListener('DOMContentLoaded', function() {
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
            
            document.querySelectorAll('.catalog-card, .no-courses').forEach(el => {
                observer.observe(el);
            });
            
            // Efecto hover para tarjetas
            const cards = document.querySelectorAll('.catalog-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    const headerBg = this.querySelector('.catalog-card-bg');
                    headerBg.style.transform = 'scale(1.1)';
                });
                
                card.addEventListener('mouseleave', function() {
                    const headerBg = this.querySelector('.catalog-card-bg');
                    headerBg.style.transform = 'scale(1)';
                });
            });
        });
        
        // Efecto especial para botones
        document.querySelectorAll('.btn-join, .btn-details, .filter-btn').forEach(btn => {
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