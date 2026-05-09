<?php
// Configurar manejo de errores más detallado
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'php/config.php';
session_start();

// Función para mostrar errores con diseño personalizado
function mostrarErrorConDiseño($titulo, $mensaje, $tipo = 'error') {
    $colores = [
        'error' => ['bg' => '#ff6b8b', 'light' => 'rgba(255, 107, 139, 0.1)', 'border' => 'rgba(255, 107, 139, 0.3)'],
        'warning' => ['bg' => '#f0ae2a', 'light' => 'rgba(240, 174, 42, 0.1)', 'border' => 'rgba(240, 174, 42, 0.3)'],
        'info' => ['bg' => '#2cbaec', 'light' => 'rgba(44, 186, 236, 0.1)', 'border' => 'rgba(44, 186, 236, 0.3)']
    ];
    
    $color = $colores[$tipo] ?? $colores['error'];
    $icono = $tipo == 'error' ? '❌' : ($tipo == 'warning' ? '⚠️' : 'ℹ️');
    
    return '
    <div class="alert-custom" style="
        background: linear-gradient(135deg, ' . $color['light'] . ', rgba(255, 255, 255, 0.05));
        border: 2px solid ' . $color['border'] . ';
        border-left: 5px solid ' . $color['bg'] . ';
        color: #333;
        padding: 25px;
        margin-bottom: 30px;
        border-radius: 15px;
        animation: slideInDown 0.5s ease-out;
    ">
        <div style="display: flex; align-items: flex-start; gap: 15px;">
            <div style="
                background: ' . $color['bg'] . ';
                color: white;
                width: 50px;
                height: 50px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.5rem;
                flex-shrink: 0;
            ">
                ' . $icono . '
            </div>
            <div style="flex: 1;">
                <h4 style="
                    color: ' . $color['bg'] . ';
                    font-weight: 800;
                    margin: 0 0 10px 0;
                    font-size: 1.3rem;
                ">
                    ' . htmlspecialchars($titulo) . '
                </h4>
                <div style="
                    background: rgba(255, 255, 255, 0.5);
                    padding: 15px;
                    border-radius: 10px;
                    margin-top: 10px;
                    font-family: monospace;
                    font-size: 0.9rem;
                    overflow-x: auto;
                    color: #555;
                ">
                    ' . nl2br(htmlspecialchars($mensaje)) . '
                </div>
                <div style="margin-top: 15px; font-size: 0.85rem; color: #666; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-info-circle"></i>
                    Este mensaje es visible solo en el entorno de desarrollo.
                </div>
            </div>
        </div>
    </div>';
}

// Función para registrar errores personalizados
function registrarErrorPersonalizado($mensaje, $tipo = 'error') {
    $error_file = 'error_log_custom.txt';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$tipo] $mensaje\n";
    file_put_contents($error_file, $log_entry, FILE_APPEND);
}

// Configurar manejador de errores personalizado
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $error_types = [
        E_ERROR => 'Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parse Error',
        E_NOTICE => 'Notice',
        E_STRICT => 'Strict Standards',
        E_DEPRECATED => 'Deprecated'
    ];
    
    $error_type = $error_types[$errno] ?? 'Unknown Error';
    
    registrarErrorPersonalizado("$error_type: $errstr in $errfile on line $errline", strtolower($error_type));
    
    if (ini_get('display_errors')) {
        echo mostrarErrorConDiseño($error_type, "$errstr\nArchivo: $errfile\nLínea: $errline");
    }
    
    return true;
});

set_exception_handler(function($exception) {
    registrarErrorPersonalizado("Excepción: " . $exception->getMessage() . " en " . $exception->getFile() . ":" . $exception->getLine(), 'exception');
    
    if (ini_get('display_errors')) {
        echo mostrarErrorConDiseño('Excepción no capturada', 
            $exception->getMessage() . "\n" .
            "Archivo: " . $exception->getFile() . "\n" .
            "Línea: " . $exception->getLine() . "\n" .
            "Traza:\n" . $exception->getTraceAsString()
        );
    }
});

if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'tutor') {
    header("Location: index.php");
    exit();
}

$tutor_id = $_SESSION['user_id'];
$entrega_id = isset($_GET['entrega_id']) ? intval($_GET['entrega_id']) : 0;
$modificar = isset($_GET['modificar']) ? true : false;

if ($entrega_id == 0) {
    header("Location: dashboard_tutor.php");
    exit();
}

// Verificar si la tabla usuarios tiene la columna 'puntos' (PostgreSQL)
try {
    $stmt = $conn->pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name='usuarios' AND column_name='puntos'");
    $stmt->execute();
    $tiene_columna_puntos = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    $tiene_columna_puntos = false;
    registrarErrorPersonalizado("Error al verificar columna 'puntos': " . $e->getMessage(), 'warning');
}

// Obtener información de la entrega
try {
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
                        ev.calificacion,
                        ev.comentarios as comentarios_tutor,
                        ev.fecha_evaluacion,
                        (a.fecha_limite::date - CURRENT_DATE) as dias_restantes
                    FROM entregas e
                    JOIN usuarios u ON e.id_alumno = u.id
                    JOIN actividades a ON e.id_actividad = a.id
                    JOIN cursos c ON a.id_curso = c.id
                    LEFT JOIN evaluaciones ev ON e.id = ev.id_entrega
                    WHERE e.id = :entrega_id";
    
    $stmt = $conn->pdo->prepare($sql_entrega);
    $stmt->execute([':entrega_id' => $entrega_id]);
    
    if ($stmt->rowCount() == 0) {
        header("Location: dashboard_tutor.php");
        exit();
    }

    $entrega = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verificar que el tutor sea el dueño del curso
    if ($entrega['id_tutor'] != $tutor_id) {
        header("Location: dashboard_tutor.php");
        exit();
    }
} catch (PDOException $e) {
    echo mostrarErrorConDiseño('Error al obtener información de la entrega', $e->getMessage());
    exit();
}

// Variables para mensajes
$error = '';
$success = '';
$warning = '';

// Procesar calificación si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $calificacion = isset($_POST['calificacion']) ? floatval($_POST['calificacion']) : 0;
    $comentarios = isset($_POST['comentarios']) ? trim($_POST['comentarios']) : '';
    $estado = $calificacion > 0 ? 'calificado' : 'pendiente';
    
    if ($calificacion < 0 || $calificacion > 10) {
        $error = "La calificación debe estar entre 0 y 10";
    } else {
        try {
            $conn->pdo->beginTransaction();
            
            // Verificar si ya existe una evaluación
            $stmt_check = $conn->pdo->prepare("SELECT id FROM evaluaciones WHERE id_entrega = :id_entrega");
            $stmt_check->execute([':id_entrega' => $entrega_id]);
            
            if ($stmt_check->rowCount() > 0) {
                // Obtener calificación anterior
                $stmt_old = $conn->pdo->prepare("SELECT calificacion FROM evaluaciones WHERE id_entrega = :id_entrega");
                $stmt_old->execute([':id_entrega' => $entrega_id]);
                $old_grade = $stmt_old->fetch(PDO::FETCH_ASSOC)['calificacion'];
                
                // Actualizar evaluación existente
                $stmt_update = $conn->pdo->prepare("
                    UPDATE evaluaciones 
                    SET calificacion = :calificacion, 
                        comentarios = :comentarios,
                        fecha_evaluacion = CURRENT_TIMESTAMP
                    WHERE id_entrega = :id_entrega
                ");
                $stmt_update->execute([
                    ':calificacion' => $calificacion,
                    ':comentarios' => $comentarios,
                    ':id_entrega' => $entrega_id
                ]);
                
                // Si había puntos anteriormente y la tabla tiene la columna
                if ($tiene_columna_puntos && $old_grade >= 6 && $calificacion < 6) {
                    $sql_remove_points = "UPDATE usuarios 
                                         SET puntos = GREATEST(puntos - :puntos, 0)
                                         WHERE id = :alumno_id";
                    $stmt_remove = $conn->pdo->prepare($sql_remove_points);
                    $stmt_remove->execute([
                        ':puntos' => $entrega['puntos_actividad'],
                        ':alumno_id' => $entrega['alumno_id']
                    ]);
                    $warning = "Se han retirado los puntos porque la nueva calificación es menor a 6";
                }
            } else {
                // Insertar nueva evaluación
                $stmt_insert = $conn->pdo->prepare("
                    INSERT INTO evaluaciones (id_entrega, calificacion, comentarios, fecha_evaluacion)
                    VALUES (:id_entrega, :calificacion, :comentarios, CURRENT_TIMESTAMP)
                ");
                $stmt_insert->execute([
                    ':id_entrega' => $entrega_id,
                    ':calificacion' => $calificacion,
                    ':comentarios' => $comentarios
                ]);
            }
            
            // Actualizar estado de la entrega
            $stmt_update_entrega = $conn->pdo->prepare("UPDATE entregas SET estado = :estado WHERE id = :id_entrega");
            $stmt_update_entrega->execute([
                ':estado' => $estado,
                ':id_entrega' => $entrega_id
            ]);
            
            // Calcular puntos ganados (si la calificación es 6 o más)
            $puntos_ganados = 0;
            if ($calificacion >= 6) {
                $puntos_ganados = $entrega['puntos_actividad'];
                
                if ($tiene_columna_puntos) {
                    $stmt_update_puntos = $conn->pdo->prepare("
                        UPDATE usuarios 
                        SET puntos = COALESCE(puntos, 0) + :puntos 
                        WHERE id = :alumno_id
                    ");
                    $stmt_update_puntos->execute([
                        ':puntos' => $puntos_ganados,
                        ':alumno_id' => $entrega['alumno_id']
                    ]);
                    
                    // Registrar en el log de puntos
                    $stmt_log = $conn->pdo->prepare("
                        INSERT INTO puntos_log (id_usuario, puntos, tipo, descripcion)
                        VALUES (:id_usuario, :puntos, 'actividad', :descripcion)
                    ");
                    $stmt_log->execute([
                        ':id_usuario' => $entrega['alumno_id'],
                        ':puntos' => $puntos_ganados,
                        ':descripcion' => 'Calificación de actividad: ' . $entrega['actividad_titulo']
                    ]);
                }
            }
            
            $conn->pdo->commit();
            
            $success = "Entrega calificada exitosamente" . 
                      ($puntos_ganados > 0 && $tiene_columna_puntos ? " - El alumno ha ganado $puntos_ganados puntos" : "") .
                      ($warning ? "<br>" . $warning : "");
            
            // Actualizar datos de la entrega
            $stmt->execute([':entrega_id' => $entrega_id]);
            $entrega = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            if ($conn->pdo->inTransaction()) {
                $conn->pdo->rollBack();
            }
            $error = "Error en el sistema: " . $e->getMessage();
            registrarErrorPersonalizado($error, 'error');
            echo mostrarErrorConDiseño('Error al procesar la calificación', $e->getMessage());
        } catch (Exception $e) {
            if ($conn->pdo->inTransaction()) {
                $conn->pdo->rollBack();
            }
            $error = "Error en el sistema: " . $e->getMessage();
            echo mostrarErrorConDiseño('Error General', $e->getMessage());
            registrarErrorPersonalizado($e->getMessage(), 'error');
        }
    }
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

function getCalificacionColor($calificacion) {
    if ($calificacion === null) return 'secondary';
    if ($calificacion >= 9) return 'success';
    if ($calificacion >= 7) return 'info';
    if ($calificacion >= 6) return 'warning';
    return 'danger';
}

function getCalificacionTexto($calificacion) {
    if ($calificacion === null) return 'Sin calificar';
    if ($calificacion >= 9) return 'Excelente';
    if ($calificacion >= 7) return 'Bueno';
    if ($calificacion >= 6) return 'Suficiente';
    return 'Necesita mejorar';
}

function formatFecha($fecha) {
    if (!$fecha) return 'No especificada';
    $fecha_obj = new DateTime($fecha);
    return $fecha_obj->format('d/m/Y H:i');
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
    <title><?php echo $modificar ? '✏️ Modificar Calificación' : '⭐ Calificar Entrega'; ?> - D&F Mindspace</title>
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
        
        .evaluation-container {
            background: white;
            border-radius: 25px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .evaluation-header {
            background: linear-gradient(90deg, rgba(44, 186, 236, 0.9), rgba(131, 191, 70, 0.9));
            color: white;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .evaluation-header::before {
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
        
        .evaluation-title {
            font-size: 2.5rem;
            font-weight: 900;
            margin-bottom: 15px;
            line-height: 1.3;
        }
        
        .evaluation-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 25px;
            line-height: 1.6;
        }
        
        .badge-custom {
            padding: 8px 16px;
            border-radius: 15px;
            font-weight: 700;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-right: 10px;
            margin-bottom: 10px;
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
        
        .evaluation-body {
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
        
        .evaluation-form {
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.05), rgba(44, 186, 236, 0.02));
            border-radius: 20px;
            padding: 40px;
            margin: 40px 0;
            border: 2px solid rgba(44, 186, 236, 0.1);
        }
        
        .form-group {
            margin-bottom: 30px;
        }
        
        .form-label {
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 10px;
            font-size: 1.1rem;
        }
        
        .form-control-custom {
            border: 2px solid rgba(44, 186, 236, 0.2);
            border-radius: 15px;
            padding: 15px 20px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-control-custom:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(44, 186, 236, 0.25);
        }
        
        .form-text {
            color: #666;
            font-size: 0.9rem;
            margin-top: 5px;
        }
        
        .calificacion-display {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .calificacion-actual {
            font-size: 3rem;
            font-weight: 900;
            min-width: 120px;
            text-align: center;
            padding: 20px;
            border-radius: 20px;
            background: white;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .calificacion-texto {
            font-size: 1.2rem;
            font-weight: 700;
            flex: 1;
        }
        
        .rating-container {
            position: relative;
            margin-bottom: 10px;
        }
        
        .rating-input {
            width: 100%;
            height: 60px;
            -webkit-appearance: none;
            appearance: none;
            background: linear-gradient(90deg, 
                #ff6b8b 0%, 
                #ff8b6b 16.66%, 
                #f0ae2a 33.33%, 
                #83bf46 50%, 
                #2cbaec 66.66%, 
                #9c88ff 83.33%, 
                #6c5ce7 100%);
            border-radius: 30px;
            outline: none;
            opacity: 0.7;
            transition: opacity .2s;
            position: relative;
            z-index: 1;
        }
        
        .rating-input:hover {
            opacity: 1;
        }
        
        .rating-input::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: white;
            cursor: pointer;
            border: 5px solid var(--primary);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            position: relative;
            z-index: 2;
        }
        
        .rating-input::-moz-range-thumb {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: white;
            cursor: pointer;
            border: 5px solid var(--primary);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            position: relative;
            z-index: 2;
        }
        
        .rating-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            padding: 0 5px;
        }
        
        .rating-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #666;
        }
        
        .rating-value-display {
            position: absolute;
            top: -40px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--primary);
            color: white;
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 1.1rem;
            box-shadow: 0 5px 15px rgba(44, 186, 236, 0.3);
            display: none;
            z-index: 3;
            min-width: 60px;
            text-align: center;
            transition: all 0.2s ease;
        }
        
        .rating-value-display::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border-width: 8px;
            border-style: solid;
            border-color: var(--primary) transparent transparent transparent;
        }
        
        .puntos-info {
            background: linear-gradient(135deg, rgba(131, 191, 70, 0.1), rgba(131, 191, 70, 0.05));
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
            border-left: 5px solid var(--accent);
        }
        
        .puntos-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-submit {
            background: linear-gradient(90deg, var(--accent), #6aab39);
            border: none;
            border-radius: 15px;
            padding: 18px 40px;
            color: white;
            font-weight: 800;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            width: 100%;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn-submit:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(131, 191, 70, 0.4);
        }
        
        .btn-modificar {
            background: linear-gradient(90deg, var(--secondary), #f5c15d);
            border: none;
            border-radius: 15px;
            padding: 18px 40px;
            color: white;
            font-weight: 800;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            width: 100%;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn-modificar:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(240, 174, 42, 0.4);
        }
        
        .alert-custom {
            border-radius: 15px;
            border: none;
            padding: 20px 25px;
            margin-bottom: 30px;
            animation: slideInDown 0.5s ease-out;
            display: flex;
            align-items: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .alert-custom.alert-danger {
            background: linear-gradient(135deg, rgba(255, 107, 139, 0.1), rgba(255, 107, 139, 0.05));
            border-left: 5px solid var(--danger);
            color: #721c24;
        }

        .alert-custom.alert-warning {
            background: linear-gradient(135deg, rgba(240, 174, 42, 0.1), rgba(240, 174, 42, 0.05));
            border-left: 5px solid var(--secondary);
            color: #856404;
        }

        .alert-custom.alert-success {
            background: linear-gradient(135deg, rgba(131, 191, 70, 0.1), rgba(131, 191, 70, 0.05));
            border-left: 5px solid var(--accent);
            color: #155724;
        }
        
        /* Modal personalizado */
        .modal-custom .modal-content {
            border-radius: 20px;
            border: 2px solid rgba(44, 186, 236, 0.2);
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(44, 186, 236, 0.2);
        }

        .modal-custom .modal-header {
            background: linear-gradient(90deg, rgba(44, 186, 236, 0.9), rgba(131, 191, 70, 0.9));
            color: white;
            border-bottom: none;
            padding: 25px;
        }

        .modal-custom .modal-title {
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-custom .modal-body {
            padding: 30px;
            font-size: 1.1rem;
            line-height: 1.6;
        }

        .modal-custom .modal-footer {
            border-top: 2px solid rgba(44, 186, 236, 0.1);
            padding: 20px 30px;
            gap: 15px;
        }

        /* Botones del modal */
        .btn-confirm {
            background: linear-gradient(90deg, var(--accent), #6aab39);
            border: none;
            border-radius: 12px;
            padding: 12px 30px;
            color: white;
            font-weight: 700;
            transition: all 0.3s ease;
        }

        .btn-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(131, 191, 70, 0.3);
        }

        .btn-cancel {
            background: linear-gradient(90deg, #6c757d, #5a6268);
            border: none;
            border-radius: 12px;
            padding: 12px 30px;
            color: white;
            font-weight: 700;
            transition: all 0.3s ease;
        }

        .btn-cancel:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(108, 117, 125, 0.3);
        }
        
        @keyframes slideInDown {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        @media (max-width: 768px) {
            .container-custom {
                padding: 20px 15px;
            }
            
            .evaluation-header {
                padding: 30px 20px;
            }
            
            .evaluation-title {
                font-size: 2rem;
            }
            
            .evaluation-body {
                padding: 25px;
            }
            
            .alumno-info {
                flex-direction: column;
                text-align: center;
            }
            
            .calificacion-display {
                flex-direction: column;
                text-align: center;
            }
            
            .rating-input::-webkit-slider-thumb {
                width: 50px;
                height: 50px;
            }
            
            .rating-input::-moz-range-thumb {
                width: 50px;
                height: 50px;
            }
            
            .rating-value-display {
                top: -35px;
                font-size: 1rem;
                padding: 6px 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container-custom">
        <!-- Botón para volver -->
        <a href="revisar_entrega.php?id=<?php echo $entrega_id; ?>" class="back-link animate__animated animate__fadeInLeft">
            <i class="fas fa-arrow-left"></i> Volver a Ver Entrega
        </a>
        
        <!-- Contenedor principal -->
        <div class="evaluation-container animate__animated animate__fadeInUp">
            <!-- Encabezado -->
            <div class="evaluation-header">
                <div class="header-content">
                    <h1 class="evaluation-title">
                        <?php if($modificar): ?>
                            <i class="fas fa-edit"></i> Modificar Calificación
                        <?php else: ?>
                            <i class="fas fa-star"></i> Calificar Entrega
                        <?php endif; ?>
                    </h1>
                    <p class="evaluation-subtitle">
                        <?php echo htmlspecialchars($entrega['actividad_titulo']); ?> - 
                        <?php echo htmlspecialchars($entrega['alumno_nombre']); ?>
                    </p>
                    
                    <div class="d-flex flex-wrap">
                        <span class="badge-custom badge-tipo">
                            <i class="<?php echo str_replace('fas fa-', '', getActividadIcono($entrega['actividad_tipo'])); ?>"></i>
                            <?php echo htmlspecialchars($entrega['actividad_tipo']); ?>
                        </span>
                        
                        <span class="badge-custom badge-dificultad">
                            <i class="fas fa-signal"></i>
                            <?php echo htmlspecialchars($entrega['dificultad']); ?>
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
                </div>
            </div>
            
            <!-- Cuerpo de la evaluación -->
            <div class="evaluation-body">
                <!-- Aquí se mostrarán los errores de MySQL con diseño personalizado -->
                
                <!-- Mensajes de éxito/error del formulario -->
                <?php if($error): ?>
                    <div class="alert alert-danger alert-custom animate__animated animate__shakeX">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if($success): ?>
                    <div class="alert alert-success alert-custom animate__animated animate__bounceIn">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if($warning): ?>
                    <div class="alert alert-warning alert-custom animate__animated animate__bounceIn">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $warning; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Información del alumno -->
                <div class="section-card">
                    <h2 class="section-title">
                        <i class="fas fa-user-graduate"></i>
                        Explorador
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
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Contenido de la entrega -->
                <div class="section-card">
                    <h2 class="section-title">
                        <i class="fas fa-paper-plane"></i>
                        Entrega del Explorador
                    </h2>
                    
                    <?php if(!empty($entrega['actividad_descripcion'])): ?>
                        <div class="descripcion-box">
                            <h4 class="mb-3" style="color: var(--accent);">
                                <i class="fas fa-align-left me-2"></i>Descripción de la Actividad
                            </h4>
                            <p class="descripcion-text">
                                <?php echo nl2br(htmlspecialchars($entrega['actividad_descripcion'])); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                    
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
                                <a href="<?php echo $ruta_archivo; ?>" class="btn-download" download target="_blank">
                                    <i class="fas fa-download"></i> Descargar Archivo
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Formulario de calificación -->
                <div class="evaluation-form">
                    <form method="POST" action="" id="evaluationForm">
                        <?php if($entrega['calificacion'] !== null): ?>
                            <div class="calificacion-display">
                                <div class="calificacion-actual text-<?php echo getCalificacionColor($entrega['calificacion']); ?>">
                                    <?php echo number_format($entrega['calificacion'], 1); ?>
                                </div>
                                <div class="calificacion-texto text-<?php echo getCalificacionColor($entrega['calificacion']); ?>">
                                    Calificación actual: <?php echo getCalificacionTexto($entrega['calificacion']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-star me-2"></i>Calificación (0-10)
                            </label>
                            
                            <div class="rating-container">
                                <!-- Tooltip que muestra la calificación en tiempo real -->
                                <div class="rating-value-display" id="rating-tooltip">
                                    5.0
                                </div>
                                
                                <input type="range" class="form-control-custom rating-input" 
                                       id="calificacion" name="calificacion" 
                                       min="0" max="10" step="0.1" 
                                       value="<?php echo $entrega['calificacion'] !== null ? $entrega['calificacion'] : '5'; ?>"
                                       oninput="updateRating(this.value)"
                                       onmousemove="showRatingTooltip(event)"
                                       onmouseenter="showRatingTooltip(event)"
                                       onmouseleave="hideRatingTooltip()"
                                       ontouchstart="showRatingTooltip(event)"
                                       ontouchmove="showRatingTooltip(event)">
                            </div>
                            
                            <div class="rating-labels">
                                <span class="rating-label">0</span>
                                <span class="rating-label">2</span>
                                <span class="rating-label">4</span>
                                <span class="rating-label">6</span>
                                <span class="rating-label">8</span>
                                <span class="rating-label">10</span>
                            </div>
                            <div id="rating-value" class="form-text">
                                Valor seleccionado: <span id="current-rating" class="fw-bold"><?php echo $entrega['calificacion'] !== null ? number_format($entrega['calificacion'], 1) : '5.0'; ?></span>
                                <span id="rating-text"></span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="comentarios">
                                <i class="fas fa-comment me-2"></i>Comentarios para el alumno
                            </label>
                            <textarea class="form-control-custom" id="comentarios" name="comentarios" 
                                      rows="6" placeholder="Escribe aquí tus comentarios sobre la entrega..."><?php 
                                echo $entrega['comentarios_tutor'] ?? ''; 
                            ?></textarea>
                            <div class="form-text">Estos comentarios serán visibles para el alumno.</div>
                        </div>
                        
                        <div class="puntos-info">
                            <div class="puntos-title">
                                <i class="fas fa-trophy"></i>
                                Sistema de Puntos
                            </div>
                            <p class="mb-0">
                                Esta actividad otorga <strong><?php echo $entrega['puntos_actividad']; ?> puntos (XP)</strong>.
                                <br>
                                <small>El alumno recibirá los puntos solo si obtiene una calificación de 6.0 o superior.</small>
                                <?php if(!$tiene_columna_puntos): ?>
                                    <br>
                                    <small class="text-warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Sistema de puntos no disponible en este momento.
                                    </small>
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <?php if($modificar): ?>
                            <button type="submit" class="btn-modificar" id="submitBtn">
                                <i class="fas fa-save me-2"></i>
                                Actualizar Calificación
                            </button>
                        <?php else: ?>
                            <button type="submit" class="btn-submit" id="submitBtn">
                                <i class="fas fa-star me-2"></i>
                                Calificar Entrega
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmación personalizado -->
    <div class="modal fade modal-custom" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmModalLabel">
                        <i class="fas fa-exclamation-triangle"></i>
                        Confirmar Modificación
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="confirmMessage">
                        <!-- El mensaje se insertará aquí con JavaScript -->
                    </div>
                    <div class="mt-4">
                        <p class="mb-2"><strong>Detalles:</strong></p>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Calificación anterior:</span>
                                <span class="fw-bold text-<?php echo getCalificacionColor($entrega['calificacion']); ?>">
                                    <?php echo $entrega['calificacion'] !== null ? number_format($entrega['calificacion'], 1) : 'Sin calificar'; ?>
                                </span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Calificación nueva:</span>
                                <span class="fw-bold" id="newGradeValue">--</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Puntos de la actividad:</span>
                                <span class="fw-bold"><?php echo $entrega['puntos_actividad']; ?> XP</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Estado de puntos:</span>
                                <span class="fw-bold" id="pointsStatus">--</span>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" id="confirmCancel" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <button type="button" class="btn btn-confirm" id="confirmSubmit">
                        <i class="fas fa-check me-2"></i>Confirmar Cambio
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar con el valor actual
            const initialValue = <?php echo $entrega['calificacion'] !== null ? $entrega['calificacion'] : '5'; ?>;
            updateRating(initialValue);
            
            // Configurar animación de entrada
            const form = document.querySelector('.evaluation-form');
            form.style.opacity = '0';
            form.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                form.style.transition = 'all 0.6s ease-out';
                form.style.opacity = '1';
                form.style.transform = 'translateY(0)';
            }, 300);
            
            // Mostrar tooltip inmediatamente
            const slider = document.getElementById('calificacion');
            if (slider) {
                setTimeout(() => {
                    showRatingTooltip({target: slider});
                }, 100);
            }
            
            // Inicializar valor del modal
            updateModalValues();
            
            // Configurar evento de input para actualizar valores del modal
            document.getElementById('calificacion').addEventListener('input', updateModalValues);
        });
        
        function updateRating(value) {
            const ratingDisplay = document.getElementById('current-rating');
            const ratingText = document.getElementById('rating-text');
            const tooltip = document.getElementById('rating-tooltip');
            
            // Actualizar valor numérico
            const formattedValue = parseFloat(value).toFixed(1);
            ratingDisplay.textContent = formattedValue;
            tooltip.textContent = formattedValue;
            
            // Actualizar color y texto
            let colorClass = 'secondary';
            let text = '';
            let color = '#2cbaec';
            
            if (value >= 9) {
                colorClass = 'success';
                color = '#83bf46';
                text = ' - ¡Excelente! ⭐⭐⭐';
            } else if (value >= 7) {
                colorClass = 'info';
                color = '#2cbaec';
                text = ' - Buen trabajo! 👍';
            } else if (value >= 6) {
                colorClass = 'warning';
                color = '#f0ae2a';
                text = ' - Suficiente';
            } else if (value >= 1) {
                colorClass = 'danger';
                color = '#ff6b8b';
                text = ' - Necesita mejorar';
            } else {
                colorClass = 'dark';
                color = '#6c757d';
                text = ' - Sin calificar';
            }
            
            ratingDisplay.className = 'fw-bold text-' + colorClass;
            ratingText.innerHTML = '<span class="text-' + colorClass + '">' + text + '</span>';
            
            // Actualizar color del slider thumb y tooltip
            const slider = document.getElementById('calificacion');
            slider.style.setProperty('--thumb-color', color);
            tooltip.style.background = color;
            
            // Actualizar color del triángulo del tooltip
            updateTooltipArrow(color);
        }
        
        function showRatingTooltip(event) {
            const tooltip = document.getElementById('rating-tooltip');
            const slider = document.getElementById('calificacion');
            
            // Calcular posición del tooltip
            const rect = slider.getBoundingClientRect();
            const value = parseFloat(slider.value);
            const min = parseFloat(slider.min);
            const max = parseFloat(slider.max);
            const percent = (value - min) / (max - min);
            
            // Posicionar tooltip sobre el thumb
            let thumbPosition;
            if (event.type && event.type.includes('touch')) {
                // Para dispositivos táctiles
                const touch = event.touches[0];
                const touchX = touch.clientX;
                const relativeX = touchX - rect.left;
                const percentX = Math.min(Math.max(relativeX / rect.width, 0), 1);
                thumbPosition = percentX * rect.width;
            } else {
                // Para mouse o carga inicial
                thumbPosition = percent * rect.width;
            }
            
            // Ajustar posición para que no se salga de los límites
            const tooltipWidth = tooltip.offsetWidth || 60;
            const leftPosition = Math.max(
                10, 
                Math.min(
                    thumbPosition - (tooltipWidth / 2),
                    rect.width - tooltipWidth - 10
                )
            );
            
            // Mostrar tooltip
            tooltip.style.left = leftPosition + 'px';
            tooltip.style.display = 'block';
            tooltip.style.opacity = '1';
            
            // Actualizar valor en tooltip
            const formattedValue = parseFloat(slider.value).toFixed(1);
            tooltip.textContent = formattedValue;
            
            // Actualizar color basado en el valor
            let color = '#2cbaec';
            if (value >= 9) color = '#83bf46';
            else if (value >= 7) color = '#2cbaec';
            else if (value >= 6) color = '#f0ae2a';
            else if (value >= 1) color = '#ff6b8b';
            else color = '#6c757d';
            
            tooltip.style.background = color;
            updateTooltipArrow(color);
        }
        
        function hideRatingTooltip() {
            const tooltip = document.getElementById('rating-tooltip');
            // Ocultar con transición suave
            tooltip.style.transition = 'opacity 0.3s ease';
            tooltip.style.opacity = '0';
            setTimeout(() => {
                tooltip.style.display = 'none';
                tooltip.style.opacity = '1';
            }, 300);
        }
        
        function updateTooltipArrow(color) {
            const styleId = 'tooltip-arrow-style';
            let style = document.getElementById(styleId);
            
            if (!style) {
                style = document.createElement('style');
                style.id = styleId;
                document.head.appendChild(style);
            }
            
            style.textContent = `
                #rating-tooltip::after {
                    border-color: ${color} transparent transparent transparent !important;
                }
            `;
        }
        
        function updateModalValues() {
            const calificacionInput = document.getElementById('calificacion');
            const newGradeValue = document.getElementById('newGradeValue');
            const pointsStatus = document.getElementById('pointsStatus');
            const oldGrade = <?php echo $entrega['calificacion'] !== null ? $entrega['calificacion'] : 'null'; ?>;
            const newGrade = parseFloat(calificacionInput.value);
            
            // Actualizar valor numérico
            newGradeValue.textContent = newGrade.toFixed(1);
            
            // Actualizar color según la calificación
            if (newGrade >= 6) {
                newGradeValue.className = 'fw-bold text-success';
            } else {
                newGradeValue.className = 'fw-bold text-danger';
            }
            
            // Actualizar estado de puntos
            if (oldGrade !== null && oldGrade >= 6 && newGrade < 6) {
                pointsStatus.textContent = 'Puntos serán RETIRADOS';
                pointsStatus.className = 'fw-bold text-danger';
            } else if (oldGrade !== null && oldGrade < 6 && newGrade >= 6) {
                pointsStatus.textContent = 'Puntos serán OTORGADOS';
                pointsStatus.className = 'fw-bold text-success';
            } else if (newGrade >= 6) {
                pointsStatus.textContent = 'Puntos serán MANTENIDOS';
                pointsStatus.className = 'fw-bold text-success';
            } else {
                pointsStatus.textContent = 'Sin puntos';
                pointsStatus.className = 'fw-bold text-secondary';
            }
        }
        
        // Validar formulario
        document.getElementById('evaluationForm').addEventListener('submit', function(e) {
            e.preventDefault(); // Prevenir envío por defecto
            
            const calificacionInput = document.getElementById('calificacion');
            const calificacion = parseFloat(calificacionInput.value);
            const oldGrade = <?php echo $entrega['calificacion'] !== null ? $entrega['calificacion'] : 'null'; ?>;
            const form = this;
            
            // Validaciones básicas
            if (calificacion < 0 || calificacion > 10) {
                showAlert('La calificación debe estar entre 0 y 10', 'error');
                return false;
            }
            
            if (calificacion > 0 && calificacion < 0.1) {
                showAlert('La calificación mínima es 0.1', 'error');
                return false;
            }
            
            // Confirmar si se está modificando
            const isModifying = <?php echo $modificar ? 'true' : 'false'; ?>;
            
            // Condiciones para mostrar el modal de confirmación:
            // 1. Se está modificando (modificar=true)
            // 2. Había una calificación anterior
            // 3. La nueva calificación es diferente a la anterior
            // 4. Y se cumple una de estas condiciones:
            //    a) Nueva calificación < 6 y anterior >= 6 (se retiran puntos)
            //    b) Cualquier cambio cuando hay calificación anterior
            
            if (isModifying && oldGrade !== null && calificacion != oldGrade) {
                // Mostrar modal de confirmación personalizado
                const confirmModal = document.getElementById('confirmModal');
                const confirmMessage = document.getElementById('confirmMessage');
                const modal = new bootstrap.Modal(confirmModal);
                
                // Determinar el tipo de mensaje
                let messageType = 'info';
                let messageText = '';
                
                if (oldGrade >= 6 && calificacion < 6) {
                    messageType = 'warning';
                    messageText = `
                        <div class="alert alert-warning mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>¡Advertencia!</strong> La nueva calificación es menor a 6.0.<br><br>
                            <strong>Se retirarán los puntos</strong> que el alumno había recibido por esta actividad.
                        </div>
                    `;
                } else if (oldGrade < 6 && calificacion >= 6) {
                    messageType = 'success';
                    messageText = `
                        <div class="alert alert-success mb-0">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>¡Excelente noticia!</strong> La nueva calificación es 6.0 o superior.<br><br>
                            <strong>Se otorgarán puntos</strong> al alumno por esta actividad.
                        </div>
                    `;
                } else {
                    messageText = `
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Confirmar cambio de calificación</strong><br><br>
                            Estás a punto de modificar la calificación de esta entrega.
                        </div>
                    `;
                }
                
                // Configurar mensaje
                confirmMessage.innerHTML = messageText;
                
                // Configurar botón de confirmación
                document.getElementById('confirmSubmit').onclick = function() {
                    modal.hide();
                    // Enviar formulario después de confirmar
                    submitForm(form);
                };
                
                // Configurar botón de cancelación
                document.getElementById('confirmCancel').onclick = function() {
                    modal.hide();
                    // Restaurar estado normal del botón
                    const submitBtn = document.getElementById('submitBtn');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = submitBtn.innerHTML.replace('Procesando...', 
                        isModifying ? '<i class="fas fa-save me-2"></i> Actualizar Calificación' : 
                                     '<i class="fas fa-star me-2"></i> Calificar Entrega');
                };
                
                // Mostrar modal
                modal.show();
                return false;
            } else {
                // Si no necesita confirmación, enviar directamente
                submitForm(form);
            }
        });
        
        function submitForm(form) {
            // Mostrar animación de carga
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            
            // Cambiar texto del botón
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Procesando...';
            submitBtn.disabled = true;
            
            // Pequeña pausa para que el usuario vea la animación
            setTimeout(() => {
                // Enviar formulario
                form.submit();
            }, 500);
        }
        
        function showAlert(message, type = 'error') {
            const alertHtml = `
                <div class="alert-custom alert-${type === 'error' ? 'danger' : 'warning'} animate__animated animate__shakeX">
                    <i class="fas fa-${type === 'error' ? 'exclamation-triangle' : 'exclamation-circle'} me-2"></i>
                    ${message}
                </div>
            `;
            
            // Insertar al principio del cuerpo de la evaluación
            const evaluationBody = document.querySelector('.evaluation-body');
            evaluationBody.insertAdjacentHTML('afterbegin', alertHtml);
            
            // Auto-remover después de 5 segundos
            setTimeout(() => {
                const alert = evaluationBody.querySelector('.alert-custom');
                if (alert) {
                    alert.remove();
                }
            }, 5000);
        }
        
        // CSS para el thumb del slider
        const sliderStyle = document.createElement('style');
        sliderStyle.textContent = `
            #calificacion::-webkit-slider-thumb {
                border-color: var(--thumb-color, #2cbaec) !important;
            }
            #calificacion::-moz-range-thumb {
                border-color: var(--thumb-color, #2cbaec) !important;
            }
        `;
        document.head.appendChild(sliderStyle);
    </script>
</body>
</html>