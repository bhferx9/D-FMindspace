<?php
include 'php/config.php';
include 'php/sendgrid_notificaciones.php';
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
        SELECT a.*, c.nombre as curso_nombre, c.id_tutor
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

try {
    $stmt = $conn->pdo->prepare("SELECT COALESCE(avatar, 'panda') as avatar FROM usuarios WHERE id = :alumno_id");
    $stmt->execute([':alumno_id' => $alumno_id]);
    $avatar_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $avatar_key = $avatar_data['avatar'] ?? 'panda';
} catch(PDOException $e) {
    $avatar_key = 'panda';
}

$avatar_emoji = $avatares[$avatar_key]['emoji'] ?? '🐼';
$avatar_color = $avatares[$avatar_key]['color'] ?? '#3A506B';

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

// ============================================================
// PROCESAR ENVÍO DE TAREA NORMAL
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'entregar_tarea') {
    $respuesta = trim($_POST['respuesta'] ?? '');
    $archivo = '';
    
    // Crear carpeta si no existe
    if (!is_dir('uploads/entregas')) {
        mkdir('uploads/entregas', 0777, true);
    }
    
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
        // Guardar la entrega
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
        
        // ============================================================
        // NOTIFICACIONES
        // ============================================================
        
        // Obtener datos del tutor del curso
        $stmt_tutor = $conn->pdo->prepare("
            SELECT u.id, u.email, u.nombre 
            FROM cursos c
            JOIN usuarios u ON c.id_tutor = u.id
            WHERE c.id = :id_curso
        ");
        $stmt_tutor->execute([':id_curso' => $actividad['id_curso']]);
        $tutor = $stmt_tutor->fetch(PDO::FETCH_ASSOC);
        
        // Obtener email del alumno
        $stmt_alumno_email = $conn->pdo->prepare("SELECT nombre, email FROM usuarios WHERE id = :id");
        $stmt_alumno_email->execute([':id' => $alumno_id]);
        $alumno_info = $stmt_alumno_email->fetch(PDO::FETCH_ASSOC);
        $alumno_nombre_db = $alumno_info['nombre'] ?? $nombre_alumno;
        $alumno_email_db = $alumno_info['email'] ?? '';
        
        // ============================================================
        // 1. NOTIFICACIÓN INMEDIATA AL TUTOR (NUEVA ENTREGA)
        // ============================================================
        if ($tutor && !empty($tutor['email'])) {
            notificar_tutor_nueva_entrega(
                $tutor['email'],
                $tutor['nombre'],
                $alumno_nombre_db,
                $actividad['titulo'],
                $actividad['curso_nombre']
            );
        }
        
        // ============================================================
        // 2. NOTIFICACIÓN DE CONFIRMACIÓN AL ALUMNO
        // ============================================================
        if (!empty($alumno_email_db)) {
            $subject = "✅ ¡Tu misión ha sido entregada!";
            $html = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
                <div style='background:linear-gradient(90deg,#83bf46,#6aab39);padding:24px 32px;border-radius:12px 12px 0 0;'>
                    <h1 style='color:white;margin:0;font-size:22px;'>D&amp;F Mindspace</h1>
                </div>
                <div style='background:#f9fafb;padding:32px;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 12px 12px;'>
                    <h2 style='color:#1a1a2e;font-size:18px;margin-top:0;'>¡Misión entregada con éxito! 🎉</h2>
                    <p style='color:#555;'>Hola <strong>$alumno_nombre_db</strong>,</p>
                    <p style='color:#555;'>Has entregado la actividad <strong>{$actividad['titulo']}</strong> del curso <strong>{$actividad['curso_nombre']}</strong>.</p>
                    <p style='color:#555;'>Tu tutor la revisará y te dará una calificación pronto. ¡Sigue así!</p>
                    <hr style='margin:24px 0;border:none;border-top:1px solid #e5e7eb;'>
                    <p style='color:#aaa;font-size:12px;'>Este es un mensaje automático de D&amp;F Mindspace. No respondas este correo.</p>
                </div>
            </div>";
            
            sendgrid_email($alumno_email_db, $alumno_nombre_db, $subject, $html);
        }
        
        // ============================================================
        // 3. RESUMEN DE TODAS LAS ENTREGAS PENDIENTES (OPCIONAL)
        // ============================================================
        if ($tutor && !empty($tutor['email']) && !empty($tutor['id'])) {
            // Verificar cuántas entregas pendientes tiene el tutor
            $stmt_total_pendientes = $conn->pdo->prepare("
                SELECT COUNT(*) as total 
                FROM entregas e
                JOIN actividades a ON e.id_actividad = a.id
                JOIN cursos c ON a.id_curso = c.id
                WHERE c.id_tutor = :tutor_id 
                AND e.estado = 'pendiente'
            ");
            $stmt_total_pendientes->execute([':tutor_id' => $tutor['id']]);
            $total_pendientes = $stmt_total_pendientes->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Solo enviar resumen si hay entregas pendientes
            if ($total_pendientes >= 1) {
                // Obtener todas las entregas pendientes
                $stmt_resumen = $conn->pdo->prepare("
                    SELECT 
                        e.id as entrega_id,
                        e.fecha_entrega,
                        EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - e.fecha_entrega))/3600 as horas_pasadas,
                        u.nombre as alumno_nombre,
                        a.titulo as actividad_nombre,
                        c.nombre as curso_nombre
                    FROM entregas e
                    JOIN usuarios u ON e.id_alumno = u.id
                    JOIN actividades a ON e.id_actividad = a.id
                    JOIN cursos c ON a.id_curso = c.id
                    WHERE c.id_tutor = :tutor_id
                    AND e.estado = 'pendiente'
                    ORDER BY e.fecha_entrega ASC
                ");
                $stmt_resumen->execute([':tutor_id' => $tutor['id']]);
                $todas_entregas = $stmt_resumen->fetchAll(PDO::FETCH_ASSOC);
                
                // Calcular estadísticas
                $stats = ['recientes' => 0, 'pendientes' => 0, 'urgentes' => 0];
                foreach ($todas_entregas as $e) {
                    $horas = $e['horas_pasadas'] ?? 0;
                    if ($horas > 48) $stats['urgentes']++;
                    elseif ($horas > 24) $stats['pendientes']++;
                    else $stats['recientes']++;
                }
                $todas_entregas['stats'] = $stats;
                
                // Enviar correo con resumen
                notificar_tutor_resumen_pendientes(
                    $tutor['email'],
                    $tutor['nombre'],
                    $todas_entregas
                );
            }
        }
        // ============================================================
        
        header("Location: mis_actividades.php?msg=entregado");
        exit();
        
    } catch(PDOException $e) {
        $error_msg = "Error al guardar la entrega: " . $e->getMessage();
        error_log($error_msg);
    }
}

// ============================================================
// PROCESAR ENVÍO DE EXAMEN (AJAX)
// ============================================================
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
        
        // También enviar notificaciones para examen
        // Obtener datos del tutor
        $stmt_tutor = $conn->pdo->prepare("
            SELECT u.email, u.nombre 
            FROM cursos c
            JOIN usuarios u ON c.id_tutor = u.id
            WHERE c.id = :id_curso
        ");
        $stmt_tutor->execute([':id_curso' => $actividad['id_curso']]);
        $tutor = $stmt_tutor->fetch(PDO::FETCH_ASSOC);
        
        $stmt_alumno_email = $conn->pdo->prepare("SELECT nombre, email FROM usuarios WHERE id = :id");
        $stmt_alumno_email->execute([':id' => $alumno_id]);
        $alumno_info = $stmt_alumno_email->fetch(PDO::FETCH_ASSOC);
        
        if ($tutor && !empty($tutor['email'])) {
            notificar_tutor_nueva_entrega(
                $tutor['email'],
                $tutor['nombre'],
                $alumno_info['nombre'] ?? $nombre_alumno,
                $actividad['titulo'],
                $actividad['curso_nombre']
            );
        }
        
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
 
        /* ===== SIDEBAR ===== */
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
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            margin-bottom: 20px;
        }
 
        .btn-back:hover {
            transform: translateY(-3px) scale(1.05);
            color: white;
        }
 
        @media (max-width: 992px) {
            .sidebar-kid { transform: translateX(-100%); }
            .sidebar-kid.active { transform: translateX(0); }
            .main-content { margin-left: 0; width: 100%; padding: 20px; }
            .menu-toggle { display: block; }
        }
    </style>
</head>
<body>
    <button class="menu-toggle"><i class="fas fa-bars"></i></button>

    <!-- SIDEBAR -->
    <div class="sidebar-kid">
        <div class="sidebar-content">
            <div class="sidebar-brand">
                <div class="logo-container">
                    <div class="logo-main">D&F</div>
                    <div class="logo-sub">mindspace</div>
                    <div class="tagline">EXPLORA • CREA • APRENDE</div>
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
            </p>
        </div>

        <?php if ($es_examen): ?>
            <!-- INTERFAZ DE EXAMEN -->
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Este es un examen. Responde todas las preguntas antes de entregar.
            </div>
            <!-- Aquí iría el código del examen (lo tienes en tu archivo original) -->
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
                        <button type="submit" class="btn-send" style="background:var(--accent); color:white; border:none; border-radius:50px; padding:15px 40px; font-size:1.3rem; font-weight:bold; box-shadow:0 6px 0 #6aab39;">
                            <?= $entrega_existente ? 'ACTUALIZAR MISIÓN' : '¡ENVIAR MISIÓN!' ?> <i class="fas fa-paper-plane ms-2"></i>
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelector('.menu-toggle').addEventListener('click', function(){
            document.querySelector('.sidebar-kid').classList.toggle('active');
        });
    </script>
</body>
</html>