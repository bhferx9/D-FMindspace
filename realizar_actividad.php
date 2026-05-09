<?php
include 'php/config.php';
session_start();

// Verificar sesión de alumno
if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'alumno') {
    header("Location: index.php");
    exit();
}

$alumno_id = (int)$_SESSION['user_id'];
$nombre_alumno = $_SESSION['nombre'];

// Validar ID de actividad
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: mis_actividades.php");
    exit();
}
$id_actividad = (int)$_GET['id'];

try {
    // Obtener datos de la actividad
    $stmt = $conn->pdo->prepare("
        SELECT a.*, c.nombre as curso_nombre 
        FROM actividades a 
        JOIN cursos c ON a.id_curso = c.id 
        WHERE a.id = :id_actividad
    ");
    $stmt->execute([':id_actividad' => $id_actividad]);
    
    if ($stmt->rowCount() == 0) {
        header("Location: mis_actividades.php");
        exit();
    }
    $actividad = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar fecha límite
    $fecha_limite = new DateTime($actividad['fecha_limite']);
    $hoy = new DateTime();
    if ($fecha_limite < $hoy) {
        header("Location: mis_actividades.php?error=vencida");
        exit();
    }
    
    // Verificar inscripción en el curso
    $stmt = $conn->pdo->prepare("
        SELECT id FROM inscripciones 
        WHERE id_alumno = :alumno_id AND id_curso = :id_curso AND estado = 'activo'
    ");
    $stmt->execute([
        ':alumno_id' => $alumno_id,
        ':id_curso' => $actividad['id_curso']
    ]);
    
    if ($stmt->rowCount() == 0) {
        header("Location: mis_actividades.php");
        exit();
    }
    
    // Verificar entregas previas
    $stmt = $conn->pdo->prepare("
        SELECT id, estado, respuesta, archivo 
        FROM entregas 
        WHERE id_alumno = :alumno_id AND id_actividad = :id_actividad
        ORDER BY fecha_entrega DESC 
        LIMIT 1
    ");
    $stmt->execute([
        ':alumno_id' => $alumno_id,
        ':id_actividad' => $id_actividad
    ]);
    $entrega_existente = $stmt->rowCount() > 0 ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    
    if ($entrega_existente && $entrega_existente['estado'] == 'calificado') {
        header("Location: ver_entrega.php?id=" . $entrega_existente['id']);
        exit();
    }
    
    // Verificar intentos permitidos
    $intentos_permitidos = $actividad['intentos_permitidos'] ?? 1;
    $stmt = $conn->pdo->prepare("
        SELECT COUNT(*) as total 
        FROM entregas 
        WHERE id_alumno = :alumno_id AND id_actividad = :id_actividad
    ");
    $stmt->execute([
        ':alumno_id' => $alumno_id,
        ':id_actividad' => $id_actividad
    ]);
    $intentos_usados = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($intentos_usados >= $intentos_permitidos) {
        header("Location: mis_actividades.php?error=intentos_agotados");
        exit();
    }
    
} catch(PDOException $e) {
    error_log("Error en realizar_actividad.php: " . $e->getMessage());
    header("Location: mis_actividades.php");
    exit();
}

// Avatares
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

try {
    $stmt = $conn->pdo->prepare("SELECT COALESCE(avatar, 'panda') as avatar FROM usuarios WHERE id = :alumno_id");
    $stmt->execute([':alumno_id' => $alumno_id]);
    $avatar_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $avatar_key = $avatar_data['avatar'] ?? 'panda';
} catch(PDOException $e) {
    $avatar_key = 'panda';
}

$avatar_emoji = $avatares[$avatar_key]['emoji'];
$avatar_color = $avatares[$avatar_key]['color'];

$es_examen = ($actividad['es_examen'] == 1 || $actividad['tipo'] == 'Examen');
$preguntas = [];

if ($es_examen) {
    try {
        $stmt = $conn->pdo->prepare("SELECT * FROM preguntas_examen WHERE id_actividad = :id_actividad ORDER BY id");
        $stmt->execute([':id_actividad' => $id_actividad]);
        $preguntas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($preguntas as &$preg) {
            if (in_array($preg['tipo_pregunta'], ['opcion_multiple', 'verdadero_falso'])) {
                $stmt_opc = $conn->pdo->prepare("SELECT * FROM opciones_pregunta WHERE id_pregunta = :id_pregunta");
                $stmt_opc->execute([':id_pregunta' => $preg['id']]);
                $preg['opciones'] = $stmt_opc->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $preg['opciones'] = [];
            }
        }
        
        if (empty($preguntas)) {
            header("Location: mis_actividades.php?error=sin_preguntas");
            exit();
        }
    } catch(PDOException $e) {
        header("Location: mis_actividades.php?error=no_preguntas");
        exit();
    }
}

// Procesar envío de tarea normal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'entregar_tarea') {
    $respuesta = trim($_POST['respuesta'] ?? '');
    $archivo = '';
    
    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] == 0) {
        $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip'];
        $archivo_extension = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
        
        if (in_array($archivo_extension, $extensiones_permitidas) && $_FILES['archivo']['size'] <= 10 * 1024 * 1024) {
            $nombre_archivo = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['archivo']['name']);
            $ruta_destino = 'uploads/entregas/' . $nombre_archivo;
            if (move_uploaded_file($_FILES['archivo']['tmp_name'], $ruta_destino)) {
                $archivo = $ruta_destino;
            }
        }
    }
    
    try {
        if ($entrega_existente) {
            $stmt = $conn->pdo->prepare("
                UPDATE entregas SET respuesta = :respuesta, archivo = :archivo, fecha_entrega = CURRENT_TIMESTAMP 
                WHERE id = :id_entrega
            ");
            $stmt->execute([
                ':respuesta' => $respuesta,
                ':archivo' => $archivo,
                ':id_entrega' => $entrega_existente['id']
            ]);
        } else {
            $stmt = $conn->pdo->prepare("
                INSERT INTO entregas (id_alumno, id_actividad, respuesta, archivo, fecha_entrega, estado) 
                VALUES (:alumno_id, :id_actividad, :respuesta, :archivo, CURRENT_TIMESTAMP, 'pendiente')
            ");
            $stmt->execute([
                ':alumno_id' => $alumno_id,
                ':id_actividad' => $id_actividad,
                ':respuesta' => $respuesta,
                ':archivo' => $archivo
            ]);
        }
        header("Location: mis_actividades.php?msg=entregado");
        exit();
    } catch(PDOException $e) {
        $error_msg = "Error al guardar la entrega: " . $e->getMessage();
    }
}

// Procesar envío de examen (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'entregar_examen') {
    header('Content-Type: application/json');
    $respuestas = json_decode($_POST['respuestas'] ?? '[]', true);
    
    try {
        $conn->pdo->beginTransaction();
        
        $stmt = $conn->pdo->prepare("
            INSERT INTO entregas (id_alumno, id_actividad, fecha_entrega, estado) 
            VALUES (:alumno_id, :id_actividad, CURRENT_TIMESTAMP, 'pendiente')
        ");
        $stmt->execute([
            ':alumno_id' => $alumno_id,
            ':id_actividad' => $id_actividad
        ]);
        $id_entrega = $conn->pdo->lastInsertId();
        
        $stmt_resp = $conn->pdo->prepare("
            INSERT INTO respuestas_examen (id_alumno, id_pregunta, id_opcion, respuesta_texto, id_entrega) 
            VALUES (:alumno_id, :id_pregunta, :id_opcion, :respuesta_texto, :id_entrega)
        ");
        
        foreach ($respuestas as $id_pregunta => $respuesta) {
            $id_opcion = null;
            $respuesta_texto = null;
            
            if (is_array($respuesta) && isset($respuesta['id_opcion'])) {
                $id_opcion = (int)$respuesta['id_opcion'];
            } else {
                $respuesta_texto = trim($respuesta);
            }
            
            $stmt_resp->execute([
                ':alumno_id' => $alumno_id,
                ':id_pregunta' => (int)$id_pregunta,
                ':id_opcion' => $id_opcion,
                ':respuesta_texto' => $respuesta_texto,
                ':id_entrega' => $id_entrega
            ]);
        }
        
        $conn->pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Examen entregado correctamente']);
        
    } catch(Exception $e) {
        if ($conn->pdo->inTransaction()) {
            $conn->pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $es_examen ? 'Examen' : 'Misión' ?> · D&F Mindspace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Fredoka+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
 
        /* ===== SIDEBAR (igual que mis_actividades.php) ===== */
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
        }
 
        .logo-sub {
            font-family: 'Nunito', sans-serif;
            font-size: 1.2rem;
            color: var(--primary);
            font-weight: 600;
            letter-spacing: 8px;
            text-transform: uppercase;
        }
 
        .tagline {
            color: #666;
            font-size: 0.9rem;
            font-weight: 500;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
 
        .kid-avatar {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: #FF6B6B;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            border: 4px solid white;
            position: relative;
        }
 
        .avatar-emoji { font-size: 3.5rem; }
 
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
 
        .nav-item { margin: 8px 15px; }
 
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
 
        .nav-link i { width: 24px; text-align: center; font-size: 1.2rem; }
 
        .badge-notification {
            background: linear-gradient(90deg, var(--secondary), #f5c15d);
            color: white;
            border-radius: 10px;
            padding: 4px 10px;
            font-size: 0.75rem;
            font-weight: 600;
        }
 
        .logout-link {
            background: linear-gradient(90deg, rgba(255, 87, 87, 0.1) 0%, rgba(255, 87, 87, 0.05) 100%);
            color: #ff5757 !important;
            border-left: 4px solid #ff5757 !important;
        }
 
        /* ===== CONTENIDO PRINCIPAL ===== */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 30px;
            width: calc(100% - var(--sidebar-width));
            min-height: 100vh;
        }
 
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
        }
 
        /* ===== PAGE HEADER (igual que mis_actividades) ===== */
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
            top: 0; right: 0;
            width: 300px; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 107, 139, 0.05));
        }
 
        .page-title {
            font-family: 'Fredoka One', cursive;
            font-size: 2.5rem;
            background: linear-gradient(90deg, var(--danger), var(--purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }
 
        .btn-back {
            background: linear-gradient(90deg, var(--primary), #2ca5d4);
            color: white;
            border: none;
            border-radius: 15px;
            padding: 10px 20px;
            font-weight: 700;
            font-family: 'Nunito', sans-serif;
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
            color: white;
        }
 
        /* ===== BARRA DE TIEMPO ===== */
        .timer-card {
            background: white;
            border-radius: 20px;
            padding: 20px 28px;
            margin-bottom: 25px;
            box-shadow: var(--card-shadow);
            border: 3px solid rgba(240, 174, 42, 0.25);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }
 
        .timer-info { display: flex; align-items: center; gap: 14px; }
 
        .timer-icon {
            width: 55px;
            height: 55px;
            border-radius: 15px;
            background: linear-gradient(135deg, var(--secondary), #f5c15d);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            color: white;
        }
 
        .timer-value {
            font-family: 'Fredoka One', cursive;
            font-size: 2.2rem;
            color: var(--secondary);
            line-height: 1;
        }
 
        .timer-label { color: #666; font-size: 0.9rem; font-weight: 600; }
 
        .timer-progress-wrap { flex: 1; min-width: 200px; }
 
        .timer-track {
            height: 12px;
            background: rgba(240, 174, 42, 0.15);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 6px;
        }
 
        .timer-fill {
            height: 100%;
            width: 72%;
            background: linear-gradient(90deg, var(--secondary), #f5c15d);
            border-radius: 10px;
            transition: width 1s linear;
        }
 
        .timer-fill.warning { background: linear-gradient(90deg, var(--danger), #ff4466); }
 
        .timer-meta { font-size: 0.85rem; color: #999; font-weight: 600; text-align: right; }
 
        /* ===== PROGRESO DE PREGUNTAS ===== */
        .progress-card {
            background: white;
            border-radius: 20px;
            padding: 20px 28px;
            margin-bottom: 25px;
            box-shadow: var(--card-shadow);
            border: 3px solid rgba(44, 186, 236, 0.1);
            display: flex;
            align-items: center;
            gap: 18px;
        }
 
        .progress-text { font-weight: 700; color: #555; font-size: 0.95rem; white-space: nowrap; }
 
        .progress-track {
            flex: 1;
            height: 12px;
            background: rgba(44, 186, 236, 0.12);
            border-radius: 10px;
            overflow: hidden;
        }
 
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), #2ca5d4);
            border-radius: 10px;
            transition: width 0.4s ease;
        }
 
        .progress-pct { font-family: 'Fredoka One', cursive; font-size: 1.2rem; color: var(--primary); }
 
        /* ===== TARJETA DE PREGUNTA ===== */
        .question-card {
            background: white;
            border-radius: 20px;
            margin-bottom: 25px;
            box-shadow: var(--card-shadow);
            border: 3px solid transparent;
            overflow: hidden;
            transition: all 0.3s ease;
        }
 
        .question-card.answered { border-color: rgba(131, 191, 70, 0.4); }
 
        .question-header {
            padding: 22px 28px 0;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
 
        .question-number {
            min-width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--danger), var(--purple));
            color: white;
            font-family: 'Fredoka One', cursive;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
 
        .question-text {
            font-size: 1.1rem;
            font-weight: 700;
            color: #333;
            line-height: 1.5;
            flex: 1;
            padding-top: 6px;
        }
 
        .question-pts {
            background: rgba(156, 136, 255, 0.15);
            color: var(--purple);
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 700;
            white-space: nowrap;
            flex-shrink: 0;
        }
 
        /* ===== OPCIONES ===== */
        .options-wrap { padding: 20px 28px 25px; }
 
        .option {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 18px;
            border-radius: 15px;
            border: 2px solid rgba(44, 186, 236, 0.2);
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.25s ease;
            font-size: 1rem;
            font-weight: 600;
            color: #444;
        }
 
        .option:hover {
            border-color: var(--primary);
            background: rgba(44, 186, 236, 0.06);
            transform: translateX(5px);
        }
 
        .option.selected {
            border-color: var(--primary);
            background: rgba(44, 186, 236, 0.1);
            color: var(--primary);
        }
 
        .opt-radio {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: 2px solid #ccc;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.25s;
        }
 
        .option.selected .opt-radio {
            border-color: var(--primary);
            background: var(--primary);
        }
 
        .opt-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: white;
            display: none;
        }
 
        .option.selected .opt-dot { display: block; }
 
        .opt-letter {
            font-family: 'Fredoka One', cursive;
            font-size: 1rem;
            min-width: 20px;
        }
 
        /* ===== RESPUESTA ABIERTA ===== */
        .textarea-wrap { padding: 0 28px 25px; }
 
        .exam-textarea {
            width: 100%;
            min-height: 120px;
            border: 2px solid rgba(44, 186, 236, 0.2);
            border-radius: 15px;
            padding: 15px 18px;
            font-family: 'Nunito', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            color: #444;
            resize: vertical;
            outline: none;
            transition: border 0.25s;
        }
 
        .exam-textarea:focus { border-color: var(--primary); }
 
        /* ===== NAVEGACIÓN DE BURBUJAS ===== */
        .bubble-nav {
            padding: 18px 28px;
            background: rgba(44, 186, 236, 0.04);
            border-top: 2px solid rgba(44, 186, 236, 0.1);
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
 
        .bubble-label { font-size: 0.85rem; color: #999; font-weight: 600; margin-right: 5px; }
 
        .q-bubble {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: 2px solid rgba(44, 186, 236, 0.3);
            background: white;
            font-family: 'Fredoka One', cursive;
            font-size: 1rem;
            color: #999;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }
 
        .q-bubble:hover { border-color: var(--primary); color: var(--primary); transform: scale(1.1); }
        .q-bubble.current { border-color: var(--primary); background: var(--primary); color: white; }
        .q-bubble.done { border-color: var(--accent); background: rgba(131, 191, 70, 0.12); color: var(--accent); }
 
        /* ===== BOTONES ACCIÓN (estilo del archivo original) ===== */
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
 
        .btn-activity {
            padding: 12px 25px;
            border-radius: 15px;
            font-weight: 700;
            font-family: 'Nunito', sans-serif;
            font-size: 1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
 
        .btn-prev-q {
            background: white;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
 
        .btn-prev-q:hover { background: var(--primary); color: white; transform: translateY(-3px); }
 
        .btn-next-q {
            background: linear-gradient(90deg, var(--primary), #2ca5d4);
            color: white;
            box-shadow: 0 5px 15px rgba(44, 186, 236, 0.3);
        }
 
        .btn-next-q:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(44, 186, 236, 0.4); color: white; }
 
        .btn-entregar {
            background: linear-gradient(90deg, var(--accent), #6aab39);
            color: white;
            box-shadow: 0 5px 15px rgba(131, 191, 70, 0.3);
        }
 
        .btn-entregar:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(131, 191, 70, 0.4); color: white; }
 
        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .sidebar-kid { transform: translateX(-100%); }
            .sidebar-kid.active { transform: translateX(0); }
            .main-content { margin-left: 0; width: 100%; padding: 20px; }
            .menu-toggle { display: block; }
            .page-title { font-size: 2rem; }
        }
 
        @media (max-width: 768px) {
            .timer-card { flex-direction: column; align-items: flex-start; }
            .question-header { flex-wrap: wrap; }
            .activity-footer { flex-direction: column; align-items: flex-start; }
        }
 
        @media (max-width: 576px) {
            .page-title { font-size: 1.8rem; }
            .btn-activity { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <button class="menu-toggle"><i class="fas fa-bars"></i></button>

    <!-- SIDEBAR INFANTIL (idéntico a mis_actividades.php) -->
    <div class="sidebar-kid">
        <div class="sidebar-content">
            <div class="sidebar-brand">
                <div class="logo-container">
                    <div class="logo-main">D&F</div>
                    <div class="logo-sub">mindspace</div>
                    <div class="tagline"><span>EXPLORA</span> • <span>CREA</span> • <span>APRENDE</span></div>
                </div>
                <div class="kid-avatar" style="background: <?= $avatar_color ?>;">
                    <span class="avatar-emoji"><?= $avatar_emoji ?></span>
                    <div class="avatar-status"></div>
                </div>
                <h4 class="kid-name"><?= htmlspecialchars($nombre_alumno) ?></h4>
                <span class="kid-level"><i class="fas fa-star me-1"></i>Nivel 1</span>
            </div>
            <ul class="nav flex-column mt-3">
                <li class="nav-item"><a href="dashboard_alumno.php" class="nav-link"><i class="fas fa-home"></i><span>Mi Mundo</span></a></li>
                <li class="nav-item"><a href="mis_cursos.php" class="nav-link"><i class="fas fa-compass"></i><span>Mis Aventuras</span></a></li>
                <li class="nav-item"><a href="catalogo.php" class="nav-link"><i class="fas fa-search"></i><span>Nuevas Aventuras</span></a></li>
                <li class="nav-item"><a href="mis_actividades.php" class="nav-link active"><i class="fas fa-tasks"></i><span>Mis Misiones</span></a></li>
                <li class="nav-item"><a href="avatar_shop.php" class="nav-link"><i class="fas fa-user-astronaut"></i><span>Tienda de Avatares</span></a></li>
            </ul>
        </div>
        <div class="sidebar-footer">
            <a href="logout.php" class="nav-link logout-link"><i class="fas fa-sign-out-alt"></i><span>Salir de la Aventura</span></a>
        </div>
    </div>

    <!-- CONTENIDO PRINCIPAL -->
    <div class="main-content">
        <div class="page-header">
            <a href="mis_actividades.php" class="btn-back"><i class="fas fa-arrow-left"></i> Volver a Mis Misiones</a>
            <h1 class="page-title">
                <?php if ($es_examen): ?>
                    <i class="fas fa-file-signature me-2"></i> <?= htmlspecialchars($actividad['titulo']) ?>
                <?php else: ?>
                    🚀 Misión: <?= htmlspecialchars($actividad['titulo']) ?>
                <?php endif; ?>
            </h1>
            <p class="text-muted fs-5">
                Curso: <strong><?= htmlspecialchars($actividad['curso_nombre']) ?></strong> &nbsp;·&nbsp;
                Dificultad: <strong><?= htmlspecialchars($actividad['dificultad']) ?></strong> &nbsp;·&nbsp;
                <i class="fas fa-star text-warning"></i> <strong><?= $actividad['puntos'] ?> puntos</strong>
                <?php if ($es_examen && $actividad['tiempo_limite']): ?>
                    &nbsp;·&nbsp; <i class="fas fa-hourglass-half"></i> <?= $actividad['tiempo_limite'] ?> minutos
                <?php endif; ?>
            </p>
        </div>

        <?php if ($es_examen): ?>
            <!-- INTERFAZ DE EXAMEN -->
            <div class="timer-card">
                <div class="timer-info">
                    <div class="timer-icon"><i class="fas fa-hourglass-half"></i></div>
                    <div>
                        <div class="timer-label">Tiempo restante</div>
                        <div class="timer-value" id="timerDisplay">--:--</div>
                    </div>
                </div>
                <div class="timer-progress-wrap">
                    <div class="timer-track">
                        <div class="timer-fill" id="timerFill"></div>
                    </div>
                    <div class="timer-meta">de <?= $actividad['tiempo_limite'] ?>:00 minutos</div>
                </div>
            </div>

            <div class="progress-card">
                <div class="progress-text" id="progressText">Pregunta 1 de <?= count($preguntas) ?></div>
                <div class="progress-track">
                    <div class="progress-fill" id="progressFill" style="width: 0%;"></div>
                </div>
                <div class="progress-pct" id="progressPct">0%</div>
            </div>

            <div class="question-card" id="questionCard">
                <div class="question-header">
                    <div class="question-number" id="qNum">1</div>
                    <div class="question-text" id="qText"></div>
                    <span class="question-pts" id="qPts"></span>
                </div>

                <div class="options-wrap" id="optionsWrap"></div>
                <div class="textarea-wrap" id="textareaWrap" style="display:none;">
                    <textarea class="exam-textarea" id="openAnswer" placeholder="Escribe tu respuesta aquí..."></textarea>
                </div>

                <div class="bubble-nav">
                    <span class="bubble-label"><i class="fas fa-map-signs me-1"></i>Ir a pregunta:</span>
                    <div id="bubbleContainer"></div>
                </div>
            </div>

            <div class="activity-footer" style="background:white; border-radius:20px; box-shadow:var(--card-shadow); border:3px solid rgba(44,186,236,0.1);">
                <button class="btn-activity btn-prev-q" id="btnPrev" onclick="navigate(-1)"><i class="fas fa-arrow-left"></i> Anterior</button>
                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn-activity btn-next-q" id="btnNext" onclick="navigate(1)">Siguiente <i class="fas fa-arrow-right"></i></button>
                    <button class="btn-activity btn-entregar" id="btnSubmit" style="display:none;" onclick="openConfirm()"><i class="fas fa-paper-plane"></i> Entregar Examen</button>
                </div>
            </div>

            <!-- Modal confirmación -->
            <div class="modal fade" id="confirmModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content" style="border-radius:25px;">
                        <div class="modal-header" style="background:linear-gradient(135deg,var(--danger),var(--purple));color:white;">
                            <h5 class="modal-title" style="font-family:'Fredoka One',cursive;"><i class="fas fa-paper-plane me-2"></i>¿Entregar examen?</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Respondiste <strong id="answeredCount">0</strong> de <strong><?= count($preguntas) ?></strong> preguntas.</p>
                            <p class="fw-bold text-danger">Una vez entregado, no podrás hacer cambios.</p>
                        </div>
                        <div class="modal-footer">
                            <button class="btn-activity btn-prev-q" data-bs-dismiss="modal">Seguir revisando</button>
                            <button class="btn-activity btn-entregar" onclick="finalSubmit()">Sí, entregar</button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                // Datos de preguntas desde PHP
                const preguntas = <?= json_encode($preguntas) ?>;
                const tiempoLimiteMinutos = <?= $actividad['tiempo_limite'] ?: 0 ?>;
                const idActividad = <?= $id_actividad ?>;
            </script>
            <script>
                // Lógica del examen corregida
                let current = 0;
                const answers = Array(preguntas.length).fill(null);      // id_opcion seleccionada
                const textAnswers = Array(preguntas.length).fill('');    // texto libre
                let timerInterval;
                let remainingSeconds = tiempoLimiteMinutos * 60;
                const totalSeconds = remainingSeconds;
                const letters = ['A', 'B', 'C', 'D', 'E', 'F'];

                function renderQuestion() {
                    const q = preguntas[current];
                    document.getElementById('qNum').textContent = current + 1;
                    document.getElementById('qText').textContent = q.pregunta;
                    document.getElementById('qPts').textContent = (q.puntos || 5) + ' pts';
                    document.getElementById('progressText').textContent = `Pregunta ${current+1} de ${preguntas.length}`;
                    const pct = ((current+1)/preguntas.length)*100;
                    document.getElementById('progressFill').style.width = pct + '%';
                    document.getElementById('progressPct').textContent = Math.round(pct) + '%';

                    const optWrap = document.getElementById('optionsWrap');
                    const taWrap = document.getElementById('textareaWrap');
                    
                    if (q.tipo_pregunta === 'opcion_multiple' || q.tipo_pregunta === 'verdadero_falso') {
                        taWrap.style.display = 'none';
                        optWrap.style.display = 'block';
                        let html = '';
                        if (q.opciones && q.opciones.length > 0) {
                            q.opciones.forEach((opt, i) => {
                                const isSelected = (answers[current] === opt.id);
                                const selectedClass = isSelected ? 'selected' : '';
                                html += `<div class="option ${selectedClass}" onclick="selectOption(${opt.id})">
                                    <div class="opt-radio"><div class="opt-dot"></div></div>
                                    <span class="opt-letter">${letters[i]}.</span> ${opt.opcion_text}
                                </div>`;
                            });
                        } else {
                            html = '<p class="text-muted">No hay opciones disponibles.</p>';
                        }
                        optWrap.innerHTML = html;
                    } else {
                        optWrap.style.display = 'none';
                        taWrap.style.display = 'block';
                        document.getElementById('openAnswer').value = textAnswers[current] || '';
                    }

                    // Burbujas
                    let bubbles = '';
                    for (let i = 0; i < preguntas.length; i++) {
                        const qType = preguntas[i].tipo_pregunta;
                        const hasAnswer = (qType === 'opcion_multiple' || qType === 'verdadero_falso') 
                                          ? answers[i] !== null 
                                          : textAnswers[i].trim() !== '';
                        let cls = i === current ? 'current' : (hasAnswer ? 'done' : '');
                        bubbles += `<div class="q-bubble ${cls}" onclick="jumpTo(${i})">${i+1}</div>`;
                    }
                    document.getElementById('bubbleContainer').innerHTML = bubbles;

                    document.getElementById('btnPrev').style.visibility = current === 0 ? 'hidden' : 'visible';
                    const isLast = current === preguntas.length - 1;
                    document.getElementById('btnNext').style.display = isLast ? 'none' : '';
                    document.getElementById('btnSubmit').style.display = isLast ? '' : 'none';
                }

                function selectOption(idOpcion) {
                    answers[current] = idOpcion;
                    renderQuestion();
                }

                function navigate(dir) {
                    if (preguntas[current].tipo_pregunta === 'respuesta_corta') {
                        textAnswers[current] = document.getElementById('openAnswer').value;
                    }
                    current = Math.max(0, Math.min(preguntas.length - 1, current + dir));
                    renderQuestion();
                }

                function jumpTo(i) {
                    if (preguntas[current].tipo_pregunta === 'respuesta_corta') {
                        textAnswers[current] = document.getElementById('openAnswer').value;
                    }
                    current = i;
                    renderQuestion();
                }

                function openConfirm() {
                    if (preguntas[current].tipo_pregunta === 'respuesta_corta') {
                        textAnswers[current] = document.getElementById('openAnswer').value;
                    }
                    const answered = answers.filter(a => a !== null).length + textAnswers.filter(t => t.trim() !== '').length;
                    document.getElementById('answeredCount').textContent = answered;
                    new bootstrap.Modal(document.getElementById('confirmModal')).show();
                }

                function finalSubmit() {
                    clearInterval(timerInterval);
                    const respuestas = {};
                    preguntas.forEach((q, idx) => {
                        if (q.tipo_pregunta === 'opcion_multiple' || q.tipo_pregunta === 'verdadero_falso') {
                            if (answers[idx] !== null) {
                                respuestas[q.id] = { id_opcion: answers[idx] };
                            }
                        } else {
                            const txt = textAnswers[idx].trim();
                            if (txt !== '') {
                                respuestas[q.id] = txt;
                            }
                        }
                    });

                    fetch('realizar_actividad.php?id=<?= $id_actividad ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'accion=entregar_examen&respuestas=' + encodeURIComponent(JSON.stringify(respuestas))
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            alert('¡Examen entregado!');
                            window.location.href = 'mis_actividades.php?msg=examen_entregado';
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(err => alert('Error de conexión'));
                }

                function updateTimer() {
                    if (remainingSeconds <= 0) {
                        clearInterval(timerInterval);
                        alert('¡Tiempo agotado! Se entregará automáticamente.');
                        finalSubmit();
                        return;
                    }
                    remainingSeconds--;
                    const mins = Math.floor(remainingSeconds / 60);
                    const secs = remainingSeconds % 60;
                    document.getElementById('timerDisplay').textContent = 
                        String(mins).padStart(2,'0') + ':' + String(secs).padStart(2,'0');
                    const pct = (remainingSeconds / totalSeconds) * 100;
                    const fill = document.getElementById('timerFill');
                    fill.style.width = pct + '%';
                    if (pct < 25) fill.classList.add('warning');
                }

                // Iniciar
                renderQuestion();
                if (tiempoLimiteMinutos > 0) {
                    timerInterval = setInterval(updateTimer, 1000);
                }

                // Menú móvil
                document.querySelector('.menu-toggle').addEventListener('click', function(){
                    document.querySelector('.sidebar-kid').classList.toggle('active');
                });
            </script>
        <?php else: ?>
            <!-- FORMULARIO DE TAREA NORMAL -->
            <div style="background:white; border-radius:30px; border:4px dashed var(--primary); padding:40px; box-shadow:0 10px 25px rgba(0,0,0,0.05); position:relative;">
                <i class="fas fa-file-signature fa-5x" style="position:absolute; top:20px; right:20px; opacity:0.2; transform:rotate(15deg); color:var(--primary);"></i>
                <div class="alert alert-info rounded-4 border-0">
                    <h5 class="fw-bold"><i class="fas fa-info-circle me-2"></i>¿Qué tienes que hacer?</h5>
                    <p class="mb-0"><?= nl2br(htmlspecialchars($actividad['descripcion'])) ?></p>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="accion" value="entregar_tarea">
                    <div class="mb-4">
                        <label class="form-label fw-bold fs-5">Tu respuesta aquí abajo:</label>
                        <textarea name="respuesta" class="form-control rounded-4" rows="6" placeholder="¡Escribe aquí todo lo que aprendiste!" required><?= htmlspecialchars($entrega_existente['respuesta'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-5">
                        <label class="form-label fw-bold"><i class="fas fa-cloud-upload-alt me-2"></i>¿Tienes un archivo o dibujo? Súbelo aquí:</label>
                        <input type="file" name="archivo" class="form-control rounded-pill">
                        <?php if (!empty($entrega_existente['archivo'])): ?>
                            <small class="text-muted">Archivo actual: <?= basename($entrega_existente['archivo']) ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="text-center">
                        <button type="submit" class="btn-send" style="background:var(--accent); color:white; border:none; border-radius:50px; padding:15px 40px; font-size:1.3rem; font-weight:bold; box-shadow:0 6px 0 #E57373;">
                            <?= $entrega_existente ? 'ACTUALIZAR MISIÓN' : '¡ENVIAR MISIÓN!' ?> <i class="fas fa-paper-plane ms-2"></i>
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>