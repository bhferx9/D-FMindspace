<?php
include_once 'php/config.php';
session_start();

// Verificar si el usuario es alumno
if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'alumno') {
    header("Location: index.php");
    exit();
}

$alumno_id = $_SESSION['user_id'];
$nombre_alumno = $_SESSION['nombre'];

// ========== CÓDIGO DE VINCULACIÓN PARA PADRES ==========
$codigo_actual = '';
try {
    $stmt_codigo = $conn->pdo->prepare("SELECT codigo_vinculacion FROM usuarios WHERE id = ?");
    $stmt_codigo->execute([$alumno_id]);
    $codigo_actual = $stmt_codigo->fetchColumn();

    if (!$codigo_actual) {
        // Generar código único formato DF-XXXXXX-YYYY
        do {
            $part1 = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $part2 = rand(1000, 9999);
            $nuevo = "DF-" . $part1 . "-" . $part2;
            $check = $conn->pdo->prepare("SELECT id FROM usuarios WHERE codigo_vinculacion = ?");
            $check->execute([$nuevo]);
        } while ($check->rowCount() > 0);
        
        $update = $conn->pdo->prepare("UPDATE usuarios SET codigo_vinculacion = ? WHERE id = ?");
        $update->execute([$nuevo, $alumno_id]);
        $codigo_actual = $nuevo;
    }

    // Regenerar código si se solicita
    if (isset($_GET['regenerar']) && $_GET['regenerar'] == 1) {
        do {
            $part1 = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $part2 = rand(1000, 9999);
            $nuevo = "DF-" . $part1 . "-" . $part2;
            $check = $conn->pdo->prepare("SELECT id FROM usuarios WHERE codigo_vinculacion = ?");
            $check->execute([$nuevo]);
        } while ($check->rowCount() > 0);
        
        $update = $conn->pdo->prepare("UPDATE usuarios SET codigo_vinculacion = ? WHERE id = ?");
        $update->execute([$nuevo, $alumno_id]);
        header("Location: dashboard_alumno.php?msg=regenerado");
        exit();
    }

    $mensaje_codigo = isset($_GET['msg']) && $_GET['msg'] == 'regenerado' ? 'Código regenerado con éxito' : '';
} catch (PDOException $e) {
    $codigo_actual = 'Error al generar código';
    $mensaje_codigo = '';
}
// Consultar cursos en los que está inscrito - USANDO PDO DIRECTAMENTE
try {
    $stmt = $conn->pdo->prepare("
        SELECT DISTINCT c.nombre, c.descripcion, i.progreso, i.id_curso, c.nivel 
        FROM inscripciones i 
        JOIN cursos c ON i.id_curso = c.id 
        WHERE i.id_alumno = ? AND i.estado = 'activo'
    ");
    $stmt->execute([$alumno_id]);
    $cursos_array = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $num_cursos = count($cursos_array);
} catch(PDOException $e) {
    $cursos_array = [];
    $num_cursos = 0;
}

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


$actividades_data = [];
try {
    $stmt_actividades = $conn->pdo->prepare("
        SELECT a.id, a.titulo, a.fecha_limite, 
               e.id as entrega_id, e.estado as estado_entrega
        FROM actividades a
        JOIN cursos c ON a.id_curso = c.id
        JOIN inscripciones i ON c.id = i.id_curso
        LEFT JOIN entregas e ON a.id = e.id_actividad AND e.id_alumno = :alumno_id
        WHERE i.id_alumno = :alumno_id AND i.estado = 'activo'
        ORDER BY a.fecha_limite ASC
    ");
    $stmt_actividades->execute([':alumno_id' => $alumno_id]);
    $actividades_data = $stmt_actividades->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $actividades_data = [];
}

// CORREGIDO: Verificar si la tabla alumnos existe, sino usar tabla usuarios
// Primero verificamos si existe la tabla alumnos

$puntos_alumno = 0;

// if(mysqli_num_rows($check_table) > 0) {
//     // La tabla alumnos existe
//     $query_puntos = "SELECT puntos FROM alumnos WHERE id = '$alumno_id'";
//     $res_puntos = mysqli_query($conn, $query_puntos);
//     if($res_puntos && mysqli_num_rows($res_puntos) > 0) {
//         $puntos_data = mysqli_fetch_assoc($res_puntos);
//         $puntos_alumno = $puntos_data['puntos'];
//     }
// } else {
//     // No existe tabla alumnos, usar valor por defecto
//     $puntos_alumno = 250; // Valor por defecto
// }

// Avatares disponibles (estilo Kahoot para niños)
// Obtener avatar del alumno - ARREGLO COMPLETO
$avatares = [
    'panda' => ['emoji' => '🐼', 'color' => '#3A506B', 'nivel' => 1],
    'zorro' => ['emoji' => '🦊', 'color' => '#E67E22', 'nivel' => 1],
    'dragon' => ['emoji' => '🐉', 'color' => '#FF6B6B', 'nivel' => 1],
    'leon' => ['emoji' => '🦁', 'color' => '#FFD93D', 'nivel' => 2],
    'dino' => ['emoji' => '🦖', 'color' => '#6BCF7F', 'nivel' => 1],
    'robot' => ['emoji' => '🤖', 'color' => '#4D96FF', 'nivel' => 3],
    'astronauta' => ['emoji' => '👨‍🚀', 'color' => '#845EC2', 'nivel' => 4],
    'superheroe' => ['emoji' => '🦸‍♂️', 'color' => '#FF6B8B', 'nivel' => 5],
    'mago' => ['emoji' => '🧙‍♂️', 'color' => '#00C2A8', 'nivel' => 6],
    'ninja' => ['emoji' => '🥷', 'color' => '#4A4A4A', 'nivel' => 3],
    'fenix' => ['emoji' => '🔥', 'color' => '#FF4500', 'nivel' => 7],
    'unicornio' => ['emoji' => '🦄', 'color' => '#D65DB1', 'nivel' => 8],
    'ballena' => ['emoji' => '🐋', 'color' => '#4169E1', 'nivel' => 3],
    'aguila' => ['emoji' => '🦅', 'color' => '#DAA520', 'nivel' => 3],
    'lobo' => ['emoji' => '🐺', 'color' => '#708090', 'nivel' => 3],
    'pinguino' => ['emoji' => '🐧', 'color' => '#1C2833', 'nivel' => 2],
    'bufalo' => ['emoji' => '🦬', 'color' => '#8B4513', 'nivel' => 2],
    'conejo' => ['emoji' => '🐰', 'color' => '#F4A460', 'nivel' => 1],
    'gato' => ['emoji' => '🐱', 'color' => '#FFA07A', 'nivel' => 1],
    'perro' => ['emoji' => '🐶', 'color' => '#DEB887', 'nivel' => 1],
    'raton' => ['emoji' => '🐭', 'color' => '#B0C4DE', 'nivel' => 1],
    'abeja' => ['emoji' => '🐝', 'color' => '#FFD700', 'nivel' => 2],
    'pulpo' => ['emoji' => '🐙', 'color' => '#CD5C5C', 'nivel' => 2],
    'robot_avanzado' => ['emoji' => '🤖', 'color' => '#2E86AB', 'nivel' => 5],
    'titan' => ['emoji' => '🏛️', 'color' => '#8B0000', 'nivel' => 4],
    'centauro' => ['emoji' => '🏹', 'color' => '#CD853F', 'nivel' => 4],
    'ciborg' => ['emoji' => '🦾', 'color' => '#4682B4', 'nivel' => 5],
    'kraken' => ['emoji' => '🐙', 'color' => '#2F4F4F', 'nivel' => 5],
    'valquiria' => ['emoji' => '⚔️', 'color' => '#C0C0C0', 'nivel' => 5],
    'dios_ra' => ['emoji' => '☀️', 'color' => '#FFD700', 'nivel' => 6],
    'leviathan' => ['emoji' => '🐉', 'color' => '#1a237e', 'nivel' => 6],
    'thor' => ['emoji' => '🔨', 'color' => '#5DADE2', 'nivel' => 6],
    'cerbero' => ['emoji' => '🐕‍🦺', 'color' => '#8B4513', 'nivel' => 6],
    'zeus' => ['emoji' => '⚡', 'color' => '#FFD700', 'nivel' => 7]
];

// CORREGIDO: Verificar si la tabla alumnos tiene columna avatar

$avatar_key = 'panda'; // default


$query_avatar = $query_avatar = "SELECT COALESCE(avatar, 'panda') as avatar FROM usuarios WHERE id = '$alumno_id'";
$res_avatar = mysqli_query($conn, $query_avatar);
if($res_avatar && mysqli_num_rows($res_avatar) > 0) {
    $avatar_data = mysqli_fetch_assoc($res_avatar);
    $avatar_key = $avatar_data['avatar'];  // Ya tiene 'panda' como default por COALESCE
    }


// Asegurarse de que el avatar exista en el array
if(!isset($avatares[$avatar_key])) {
    $avatar_key = 'panda';
}

$avatar_emoji = $avatares[$avatar_key]['emoji'];
$avatar_color = $avatares[$avatar_key]['color'];
$avatar_nombre = ucfirst($avatar_key);
// Consultar cursos en los que está inscrito
$query_cursos = "SELECT c.nombre, c.descripcion, i.progreso, i.id_curso, c.nivel 
                 FROM inscripciones i 
                 JOIN cursos c ON i.id_curso = c.id 
                 WHERE i.id_alumno = '$alumno_id' AND i.estado = 'activo'";
$res_cursos = mysqli_query($conn, $query_cursos);

// =============================================
// CÓDIGO DE DEPURACIÓN - ELIMINAR DESPUÉS
// =============================================
echo "<!-- DEBUG: ID del alumno: " . $alumno_id . " -->";

if (!$res_cursos) {
    echo "<!-- ERROR en consulta: " . mysqli_error($conn) . " -->";
} else {
    $num_filas = mysqli_num_rows($res_cursos);
    echo "<!-- DEBUG: Número de cursos encontrados: " . $num_filas . " -->";
    
    if ($num_filas == 0) {
        // Verificar si hay inscripciones activas
        $check_inscripciones = mysqli_query($conn, "SELECT * FROM inscripciones WHERE id_alumno = '$alumno_id'");
        if ($check_inscripciones) {
            $total_inscripciones = mysqli_num_rows($check_inscripciones);
            echo "<!-- DEBUG: Total de inscripciones para este alumno: " . $total_inscripciones . " -->";
            
            // Mostrar estado de las inscripciones
            while($ins = mysqli_fetch_assoc($check_inscripciones)) {
                echo "<!-- DEBUG: Inscripción - Curso ID: " . $ins['id_curso'] . ", Estado: " . $ins['estado'] . " -->";
            }
        }
        
        // Verificar si hay cursos en la tabla cursos
        $check_cursos = mysqli_query($conn, "SELECT COUNT(*) as total FROM cursos");
        if ($check_cursos) {
            $total_cursos_db = mysqli_fetch_assoc($check_cursos)['total'];
            echo "<!-- DEBUG: Total de cursos en la base de datos: " . $total_cursos_db . " -->";
        }
    }
}
// =============================================
// FIN CÓDIGO DE DEPURACIÓN
// =============================================


?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Mundo de Aprendizaje - D&F Mindspace</title>
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
        
        /* MEJORAS EN LAS TARJETAS DE CURSOS - NUEVO DISEÑO */
        .courses-header {
            background: linear-gradient(135deg, #ffffff, #f8fdff);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
            border: 3px solid rgba(44, 186, 236, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .courses-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(44, 186, 236, 0.05));
        }
        
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
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
            position: relative;
        }
        
        .course-card:hover {
            transform: translateY(-15px) scale(1.02);
            box-shadow: 0 25px 50px rgba(44, 186, 236, 0.25);
            border-color: var(--primary);
        }
        
        .course-header {
            padding: 25px 25px 15px;
            background: linear-gradient(135deg, var(--primary), #2ca5d4);
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
        
        .course-category {
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
            padding: 20px 25px;
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
        
        /* Sección de Estadísticas */
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
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 10px 0;
            font-family: 'Fredoka One', cursive;
        }
        
        .stat-label {
            color: #666;
            font-size: 1rem;
            font-weight: 600;
        }
        
        /* Panel lateral mejorado */
        .side-panel {
            background: white;
            border-radius: 25px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--card-shadow);
            border: 3px solid rgba(44, 186, 236, 0.1);
            transition: all 0.3s ease;
        }
        
        .side-panel:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
        }
        
        .panel-title {
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
        
        /* Actividades recientes mejoradas */
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
        
        .status-graded {
            background: rgba(44, 186, 236, 0.1);
            color: var(--primary);
        }
        
        /* Banner de bienvenida mejorado */
        .welcome-banner {
            background: linear-gradient(135deg, #ffffff, #f8fdff);
            border-radius: 25px;
            padding: 30px;
            margin-bottom: 40px;
            box-shadow: var(--card-shadow);
            border: 3px solid rgba(44, 186, 236, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .welcome-banner::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(44, 186, 236, 0.05));
        }
        
        .banner-title {
            font-family: 'Fredoka One', cursive;
            font-size: 2.5rem;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 15px;
            line-height: 1.2;
        }
        
        .banner-stats {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        
        .banner-stat {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 20px;
            background: rgba(44, 186, 236, 0.1);
            border-radius: 15px;
            border: 2px solid rgba(44, 186, 236, 0.2);
        }
        
        .banner-stat i {
            font-size: 1.5rem;
            color: var(--primary);
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
        
        /* Responsive */
        @media (max-width: 1200px) {
            .courses-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
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
            
            .banner-title {
                font-size: 2rem;
            }
            
            .courses-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .course-actions {
                flex-direction: column;
            }
            
            .banner-stats {
                flex-direction: column;
                align-items: flex-start;
            }
        }
        
        @media (max-width: 576px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .banner-title {
                font-size: 1.8rem;
            }
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
        }
        
        .btn-main-action:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 30px rgba(131, 191, 70, 0.4);
            color: white;
        }
        
        /* Animación de carga para tarjetas */
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
        
        /* Indicador de progreso especial */
        .progress-star {
            position: absolute;
            top: -10px;
            right: -10px;
            width: 40px;
            height: 40px;
            background: var(--secondary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            box-shadow: 0 4px 10px rgba(240, 174, 42, 0.3);
            z-index: 2;
            animation: starPulse 2s infinite;
        }
        
        @keyframes starPulse {
            0%, 100% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.1) rotate(15deg); }
        }

        .codigo-box {
    transition: all 0.2s ease;
}
        .codigo-box:hover {
            transform: scale(1.01);
            box-shadow: 0 6px 14px rgba(0,0,0,0.15);
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
                <!-- CÓDIGO DE VINCULACIÓN (LLAMATIVO) -->
<div class="codigo-box mt-3 p-2 text-center" style="background: linear-gradient(135deg, #f0ae2a, #fdcc5c); border-radius: 18px; margin: 0 10px 10px 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
    <small class="text-white fw-bold"><i class="fas fa-link"></i> Código para tus padres</small>
    <div class="fw-bold font-monospace bg-white rounded p-2 mt-1" style="font-size: 0.85rem; letter-spacing: 1px; word-break: break-all;" id="codigoVinculacionSidebar">
        <?php echo htmlspecialchars($codigo_actual); ?>
    </div>
    <div class="mt-2 d-flex gap-2 justify-content-center">
        <button class="btn btn-sm btn-light" onclick="copiarCodigoSidebar()" style="font-size: 0.7rem; font-weight: bold;">
            <i class="fas fa-copy"></i> Copiar
        </button>
        <a href="?regenerar=1" class="btn btn-sm btn-light" onclick="return confirm('¿Regenerar código? El anterior dejará de funcionar.')" style="font-size: 0.7rem; font-weight: bold;">
            <i class="fas fa-sync-alt"></i> Regenerar
        </a>
    </div>
    <?php if ($mensaje_codigo): ?>
        <div class="alert alert-success mt-2 py-1 px-2 mb-0" style="font-size: 0.7rem;"><?php echo $mensaje_codigo; ?></div>
    <?php endif; ?>
</div>

<script>
function copiarCodigoSidebar() {
    var codigo = document.getElementById('codigoVinculacionSidebar').innerText;
    navigator.clipboard.writeText(codigo).then(function() {
        alert('✅ Código copiado al portapapeles. Compártelo con tus padres.');
    }).catch(function() {
        alert('❌ No se pudo copiar automáticamente. Cópialo manualmente.');
    });
}
</script>
                <!-- Puntos -->
                <!-- <div class="points-container" style="text-align: center; padding: 15px; background: linear-gradient(135deg, rgba(255, 107, 139, 0.1), rgba(255, 107, 139, 0.05)); border-radius: 15px; margin: 15px;">
                    <div class="points-value" style="font-size: 2rem; font-family: 'Fredoka One', cursive; color: var(--danger); margin: 5px 0;"><?php echo $puntos_alumno; ?></div>
                    <div class="points-label" style="color: #666; font-size: 0.9rem;">Puntos de Aventura</div>
                </div> -->
            </div>
            
            <!-- Navegación -->
            <ul class="nav flex-column mt-3">
                <li class="nav-item">
                    <a href="dashboard_alumno.php" class="nav-link active">
                        <i class="fas fa-home"></i>
                        <span>Mi Mundo</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="mis_cursos.php" class="nav-link">
                        <i class="fas fa-compass"></i>
                        <span>Mis Aventuras</span>
                        <!-- NOTIFICACIÓN ELIMINADA - No debe mostrar número -->
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
                        <?php 
                        // Calcular misiones PENDIENTES para el ALUMNO
                        // Pendiente = NO ha entregado Y NO está vencida
                        $misiones_pendientes = 0;
                        foreach($actividades_data as $act) {
                            // Verificar si está vencida
                            $fecha_limite = strtotime($act['fecha_limite']);
                            $hoy = time();
                            $tiene_fecha_limite = $act['fecha_limite'] && !empty($act['fecha_limite']);
                            
                            // Está vencida si: tiene fecha, la fecha pasó, y NO ha entregado
                            $vencida = $tiene_fecha_limite && $hoy > $fecha_limite && !$act['entrega_id'];
                            
                            // Para el alumno, una misión está PENDIENTE solo si:
                            // NO ha entregado Y NO está vencida
                            $es_pendiente = (!$act['entrega_id'] && !$vencida);
                            
                            if ($es_pendiente) {
                                $misiones_pendientes++;
                            }
                        }
                        ?>
                        <?php if($misiones_pendientes > 0): ?>
                            <span class="badge-notification ms-auto"><?php echo $misiones_pendientes; ?></span>
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
        
        <!-- Banner de Bienvenida -->
        <div class="welcome-banner fade-in-up">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="banner-title">¡Bienvenido, Explorador <?php echo $avatar_nombre; ?>! 🚀</h1>
                    <p class="fs-5 text-muted">
                        Hoy es <strong><?php echo date('l, d \d\e F'); ?></strong> - 
                        ¡Un día perfecto para aprender cosas increíbles! 
                    </p>
                    
                    <div class="banner-stats">
                        <div class="banner-stat">
                            <i class="fas fa-gem"></i>
                            <span>Nivel <?php echo $avatares[$avatar_key]['nivel']; ?></span>
                        </div>
                        <div class="banner-stat">
                            <i class="fas fa-coins"></i>
                            <span><?php echo $puntos_alumno; ?> Puntos</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 text-center">
                    <div class="kid-avatar" style="width: 140px; height: 140px; margin: 0 auto;">
                        <span class="avatar-emoji" style="font-size: 5rem;"><?php echo $avatar_emoji; ?></span>
                        <div class="avatar-status"></div>
                        <div class="progress-star">
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Estadísticas rápidas -->
        <div class="stats-container fade-in-up" style="animation-delay: 0.1s">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-compass"></i>
                </div>
                <div class="stat-value">
                    <?php echo $num_cursos; ?>
                </div>
                <div class="stat-label">Aventuras Activas</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="stat-value">
                    <?php echo mysqli_num_rows($res_actividades); ?>
                </div>
                <div class="stat-label">Misiones</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="stat-value">
                    <?php echo $puntos_alumno; ?>
                </div>
                <div class="stat-label">Puntos Totales</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value">
                    <?php 
                    $progreso_total = 0;
                    if($num_cursos > 0) {
                        foreach($cursos_array as $curso) {
                            $progreso_total += $curso['progreso'];
                        }
                        echo round($progreso_total / $num_cursos) . '%';
                    } else {
                        echo '0%';
                    }
                    ?>
                </div>
                <div class="stat-label">Progreso Total</div>
            </div>
        </div>
        
        <!-- Encabezado de cursos -->
        <div class="courses-header fade-in-up" style="animation-delay: 0.2s">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-2" style="font-family: 'Fredoka One', cursive; color: var(--primary);">
                        <i class="fas fa-map-marked-alt me-2"></i>Mis Aventuras de Aprendizaje
                    </h2>
                    <p class="text-muted mb-0">Continúa tu viaje de descubrimiento y aprendizaje</p>
                </div>
                <a href="catalogo.php" class="btn-main-action">
                    <i class="fas fa-plus-circle"></i> Explorar Nuevas
                </a>
            </div>
        </div>
        
                <!-- Grid de cursos mejorado -->
        <?php if($num_cursos > 0): ?>
            <div class="courses-grid">
                <?php foreach($cursos_array as $curso): 
                    // Determinar color del curso basado en nivel
                    $nivel_color = 'primary';
                    switch(strtolower($curso['nivel'])) {
                        case 'básico': $nivel_color = 'primary'; break;
                        case 'intermedio': $nivel_color = 'secondary'; break;
                        case 'avanzado': $nivel_color = 'accent'; break;
                        default: $nivel_color = 'primary';
                    }
                ?>
                <div class="course-card fade-in-up">
                    <div class="course-header" style="background: linear-gradient(135deg, var(--<?php echo $nivel_color; ?>), 
                        <?php 
                            switch($nivel_color) {
                                case 'secondary': echo '#f5c15d'; break;
                                case 'accent': echo '#6aab39'; break;
                                case 'purple': echo '#8c7ae6'; break;
                                case 'pink': echo '#fd79a8'; break;
                                case 'danger': echo '#ff4d6d'; break;
                                default: echo '#2ca5d4';
                            }
                        ?>);">
                        <span class="course-category">
                            <i class="fas fa-<?php 
                                switch(strtolower($curso['nivel'])) {
                                    case 'básico': echo 'seedling'; break;
                                    case 'intermedio': echo 'star'; break;
                                    case 'avanzado': echo 'rocket'; break;
                                    default: echo 'compass';
                                }
                            ?> me-1"></i>
                            Nivel: <?php echo htmlspecialchars($curso['nivel']); ?>
                        </span>
                        <h3 class="course-title"><?php echo htmlspecialchars($curso['nombre']); ?></h3>
                    </div>
                    
                    <div class="course-body">
                        <p class="course-description">
                            <?php echo htmlspecialchars(substr($curso['descripcion'], 0, 120)) . (strlen($curso['descripcion']) > 120 ? '...' : ''); ?>
                        </p>
                        
                        <div class="course-progress-container">
                            <div class="progress-label">
                                <span>Tu Progreso</span>
                                <span class="fw-bold"><?php echo $curso['progreso']; ?>%</span>
                            </div>
                            <div class="progress-bar-container">
                                <div class="progress-bar-fill" style="width: <?php echo $curso['progreso']; ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="course-actions">
                            <a href="ver_curso.php?id=<?php echo $curso['id_curso']; ?>" 
                               class="btn-course btn-primary-course">
                                <i class="fas fa-play"></i> Continuar
                            </a>
                            <a href="detalles_curso.php?id=<?php echo $curso['id_curso']; ?>" 
                               class="btn-course btn-outline-course">
                                <i class="fas fa-info-circle"></i> Detalles
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5 fade-in-up" style="background: white; border-radius: 25px; padding: 60px 30px; box-shadow: var(--card-shadow);">
                <i class="fas fa-compass fa-4x text-muted mb-4 opacity-50"></i>
                <h3 class="mb-3" style="font-family: 'Fredoka One', cursive; color: var(--primary);">
                    ¡Tu Aventura Comienza Aquí!
                </h3>
                <p class="text-muted mb-4" style="max-width: 500px; margin: 0 auto;">
                    Aún no estás inscrito en ninguna aventura de aprendizaje. 
                    ¡Descubre cursos increíbles y comienza tu viaje!
                </p>
                <a href="catalogo.php" class="btn-main-action">
                    <i class="fas fa-search me-2"></i> Explorar Aventuras Disponibles
                </a>
            </div>
        <?php endif; ?>
        
        <div class="row g-4 mt-4">
            <!-- Actividades Recientes -->
            <div class="col-lg-6">
                <div class="side-panel fade-in-up" style="animation-delay: 0.3s">
                    <h4 class="panel-title">
                        <i class="fas fa-history"></i>
                        Mis Misiones Recientes
                    </h4>
                    
                    <?php if(mysqli_num_rows($res_actividades) > 0): ?>
                        <div class="activity-list">
                            <?php mysqli_data_seek($res_actividades, 0); ?>
                            <?php while($act = mysqli_fetch_assoc($res_actividades)): 
                                $estado_class = '';
                                $calificacion = $act['calificacion'] ? $act['calificacion'] : '';
                                
                                switch($act['estado']) {
                                    case 'calificado':
                                        $estado_class = 'status-graded';
                                        $icon = 'check-circle';
                                        break;
                                    case 'pendiente':
                                        $estado_class = 'status-pending';
                                        $icon = 'clock';
                                        break;
                                    default:
                                        $estado_class = 'status-completed';
                                        $icon = 'check';
                                }
                            ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-tasks"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title"><?php echo htmlspecialchars($act['titulo']); ?></div>
                                    <div class="activity-meta">
                                        <span>
                                            <i class="fas fa-calendar me-1"></i>
                                            <?php echo date('d/m H:i', strtotime($act['fecha_entrega'])); ?>
                                        </span>
                                        <?php if($act['estado'] == 'calificado' && $calificacion): ?>
                                        <span>
                                            <i class="fas fa-star me-1"></i>
                                            <?php echo $calificacion; ?>/10
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="activity-status <?php echo $estado_class; ?>">
                                    <i class="fas fa-<?php echo $icon; ?> me-1"></i>
                                    <?php echo ucfirst($act['estado']); ?>
                                </span>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-tasks fa-3x text-muted mb-3 opacity-50"></i>
                            <p class="text-muted mb-0">Aún no tienes misiones asignadas.</p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="text-center mt-3">
                        <a href="mis_actividades.php" class="btn btn-sm btn-outline-primary">
                            Ver todas las misiones
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Notificaciones y Logros -->
            <div class="col-lg-6">
                <!-- Notificaciones -->
                <div class="side-panel fade-in-up" style="animation-delay: 0.4s">
                    <h4 class="panel-title">
                        <i class="fas fa-bell"></i>
                        Mensajes Importantes
                    </h4>
                    
                    <?php if(mysqli_num_rows($res_notif) > 0): ?>
                        <?php while($n = mysqli_fetch_assoc($res_notif)): ?>
                        <div class="activity-item mb-3">
                            <div class="activity-icon" style="background: linear-gradient(135deg, var(--secondary), #f5c15d);">
                                <i class="fas fa-bullhorn"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title"><?php echo htmlspecialchars($n['titulo']); ?></div>
                                <p class="mb-0 text-muted small"><?php echo htmlspecialchars(substr($n['mensaje'], 0, 80)) . (strlen($n['mensaje']) > 80 ? '...' : ''); ?></p>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-envelope-open-text fa-3x text-muted mb-3 opacity-50"></i>
                            <p class="text-muted mb-0">¡Todo al día! No tienes mensajes nuevos.</p>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Mini logros -->
                    <div class="mt-4 pt-3 border-top">
                        <h6 class="fw-bold mb-3"><i class="fas fa-trophy me-2 text-warning"></i>Logros Recientes</h6>
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="text-center p-3 rounded" style="background: rgba(44, 186, 236, 0.1);">
                                    <i class="fas fa-medal fa-2x mb-2" style="color: var(--secondary);"></i>
                                    <div class="small fw-bold">Primeros Pasos</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center p-3 rounded" style="background: rgba(131, 191, 70, 0.1);">
                                    <i class="fas fa-star fa-2x mb-2" style="color: var(--accent);"></i>
                                    <div class="small fw-bold">Aventurero</div>
                                </div>
                            </div>
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
            this.style.transform = 'scale(1.2) rotate(15deg)';
            this.style.boxShadow = '0 15px 35px rgba(0,0,0,0.25)';
            
            // Crear partículas
            for (let i = 0; i < 10; i++) {
                const particle = document.createElement('div');
                particle.style.position = 'absolute';
                particle.style.width = '8px';
                particle.style.height = '8px';
                particle.style.background = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim();
                particle.style.borderRadius = '50%';
                particle.style.left = '50%';
                particle.style.top = '50%';
                particle.style.transform = 'translate(-50%, -50%)';
                particle.style.zIndex = '10';
                
                this.appendChild(particle);
                
                // Animación de partícula
                const angle = Math.random() * Math.PI * 2;
                const distance = Math.random() * 50 + 30;
                particle.animate([
                    { transform: 'translate(-50%, -50%) scale(1)', opacity: 1 },
                    { 
                        transform: `translate(-50%, -50%) translate(${Math.cos(angle) * distance}px, ${Math.sin(angle) * distance}px) scale(0)`, 
                        opacity: 0 
                    }
                ], {
                    duration: 800,
                    easing: 'ease-out'
                });
                
                setTimeout(() => particle.remove(), 800);
            }
            
            setTimeout(() => {
                this.style.transform = '';
                this.style.boxShadow = '';
            }, 300);
        });
        
        // Animación de barras de progreso al cargar
        document.addEventListener('DOMContentLoaded', function() {
            const progressBars = document.querySelectorAll('.progress-bar-fill');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                
                setTimeout(() => {
                    bar.style.transition = 'width 1.5s cubic-bezier(0.34, 1.56, 0.64, 1)';
                    bar.style.width = width;
                }, 300);
            });
            
            // Efecto hover para tarjetas de curso
            const courseCards = document.querySelectorAll('.course-card');
            courseCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    const header = this.querySelector('.course-header');
                    header.style.transform = 'scale(1.05)';
                });
                
                card.addEventListener('mouseleave', function() {
                    const header = this.querySelector('.course-header');
                    header.style.transform = 'scale(1)';
                });
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
            
            // Observar todos los elementos con clase fade-in-up
            document.querySelectorAll('.fade-in-up').forEach(el => {
                observer.observe(el);
            });
        });
        
        // Efecto especial para botones de acción
        document.querySelectorAll('.btn-main-action, .btn-primary-course').forEach(btn => {
            btn.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px) scale(1.05)';
            });
            
            btn.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
            
            // Efecto de clic
            btn.addEventListener('click', function(e) {
                // Crear efecto de onda
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.cssText = `
                    position: absolute;
                    border-radius: 50%;
                    background: rgba(255, 255, 255, 0.6);
                    transform: scale(0);
                    animation: ripple 0.6s linear;
                    width: ${size}px;
                    height: ${size}px;
                    top: ${y}px;
                    left: ${x}px;
                    pointer-events: none;
                `;
                
                this.appendChild(ripple);
                setTimeout(() => ripple.remove(), 600);
            });
        });
        
        // Agregar estilo para ripple
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>