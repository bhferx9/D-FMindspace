<?php
include 'php/config.php';
session_start();

// Seguridad: Solo tutores o admins
if (!isset($_SESSION['user_id']) || ($_SESSION['tipo'] != 'tutor' && $_SESSION['tipo'] != 'admin')) {
    header("Location: index.php");
    exit();
}

$tutor_id = $_SESSION['user_id'];
$tutor_nombre = $_SESSION['nombre'];

// Obtener cursos creados por este tutor usando PDO
try {
    $stmt = $conn->pdo->prepare("SELECT id, nombre FROM cursos WHERE id_tutor = :tutor_id ORDER BY nombre ASC");
    $stmt->execute([':tutor_id' => $tutor_id]);
    $cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $cursos = [];
}

// Manejar el envío del formulario
$success = false;
$error = '';
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tipo_actividad = $_POST['tipo_actividad'] ?? '';
    $id_curso = intval($_POST['id_curso'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $puntos = intval($_POST['puntos'] ?? 0);
    $dificultad = $_POST['dificultad'] ?? 'Normal';
    $fecha_limite = $_POST['fecha_limite'] ?? null;
    
    // Validaciones básicas
    if (empty($titulo) || strlen($titulo) < 3) {
        $error = "El título debe tener al menos 3 caracteres.";
    } elseif ($puntos < 1 || $puntos > 1000) {
        $error = "Los puntos deben estar entre 1 y 1000.";
    } elseif ($id_curso <= 0) {
        $error = "Debes seleccionar un curso válido.";
    } else {
        // Verificar que el curso pertenezca al tutor
        try {
            $check_curso = $conn->pdo->prepare("SELECT id FROM cursos WHERE id = :id AND id_tutor = :tutor");
            $check_curso->execute([':id' => $id_curso, ':tutor' => $tutor_id]);
            if ($check_curso->rowCount() == 0) {
                $error = "No tienes permiso para crear actividades en este curso.";
            }
        } catch(PDOException $e) {
            $error = "Error al verificar el curso.";
        }
    }
    
    if (empty($error)) {
        try {
            if ($tipo_actividad == 'examen') {
                // Para examen: obtener configuraciones adicionales
                $tiempo_limite = intval($_POST['tiempo_limite'] ?? 60);
                $intentos_permitidos = intval($_POST['intentos_permitidos'] ?? 1);
                $mostrar_resultados = isset($_POST['mostrar_resultados']) ? 1 : 0;
                $tipo = 'Examen';
                
                // Insertar la actividad como examen
                $stmt = $conn->pdo->prepare("
                    INSERT INTO actividades (id_curso, titulo, descripcion, tipo, dificultad, fecha_limite, puntos, 
                            tiempo_limite, intentos_permitidos, mostrar_resultados, es_examen) 
                    VALUES (:id_curso, :titulo, :descripcion, :tipo, :dificultad, :fecha_limite, :puntos,
                            :tiempo_limite, :intentos_permitidos, :mostrar_resultados, :es_examen)
                ");
                
                $stmt->execute([
                    ':id_curso' => $id_curso,
                    ':titulo' => $titulo,
                    ':descripcion' => $descripcion,
                    ':tipo' => $tipo,
                    ':dificultad' => $dificultad,
                    ':fecha_limite' => $fecha_limite,
                    ':puntos' => $puntos,
                    ':tiempo_limite' => $tiempo_limite,
                    ':intentos_permitidos' => $intentos_permitidos,
                    ':mostrar_resultados' => $mostrar_resultados,
                    ':es_examen' => 1
                ]);
                
                $actividad_id = $conn->pdo->lastInsertId();
                
                // Procesar preguntas del examen
                if (isset($_POST['preguntas']) && is_array($_POST['preguntas'])) {
                    $preguntas = $_POST['preguntas'];
                    $tipos_pregunta = $_POST['tipo_pregunta'] ?? [];
                    $puntos_pregunta = $_POST['puntos_pregunta'] ?? [];
                    
                    foreach ($preguntas as $index => $pregunta_text) {
                        if (!empty(trim($pregunta_text))) {
                            $tipo_pregunta = $tipos_pregunta[$index] ?? 'opcion_multiple';
                            $puntos_preg = intval($puntos_pregunta[$index] ?? 5);
                            
                            // Insertar pregunta
                            $stmt_preg = $conn->pdo->prepare("
                                INSERT INTO preguntas_examen (id_actividad, pregunta, tipo_pregunta, puntos) 
                                VALUES (:id_actividad, :pregunta, :tipo_pregunta, :puntos)
                            ");
                            $stmt_preg->execute([
                                ':id_actividad' => $actividad_id,
                                ':pregunta' => $pregunta_text,
                                ':tipo_pregunta' => $tipo_pregunta,
                                ':puntos' => $puntos_preg
                            ]);
                            $pregunta_id = $conn->pdo->lastInsertId();
                            
                            // Procesar opciones si es pregunta de opción múltiple
                            if ($tipo_pregunta == 'opcion_multiple' && isset($_POST['opciones'][$index])) {
                                foreach ($_POST['opciones'][$index] as $opcion_index => $opcion_text) {
                                    if (!empty(trim($opcion_text))) {
                                        $es_correcta = isset($_POST['correcta'][$index][$opcion_index]) ? 1 : 0;
                                        $stmt_opc = $conn->pdo->prepare("
                                            INSERT INTO opciones_pregunta (id_pregunta, opcion_text, es_correcta) 
                                            VALUES (:id_pregunta, :opcion_text, :es_correcta)
                                        ");
                                        $stmt_opc->execute([
                                            ':id_pregunta' => $pregunta_id,
                                            ':opcion_text' => $opcion_text,
                                            ':es_correcta' => $es_correcta
                                        ]);
                                    }
                                }
                            }
                            
                            // Procesar respuesta correcta si es pregunta corta
                            if ($tipo_pregunta == 'respuesta_corta' && isset($_POST['respuesta_correcta'][$index])) {
                                $respuesta = trim($_POST['respuesta_correcta'][$index]);
                                if (!empty($respuesta)) {
                                    $stmt_resp = $conn->pdo->prepare("
                                        INSERT INTO respuestas_correctas (id_pregunta, respuesta_correcta) 
                                        VALUES (:id_pregunta, :respuesta)
                                    ");
                                    $stmt_resp->execute([
                                        ':id_pregunta' => $pregunta_id,
                                        ':respuesta' => $respuesta
                                    ]);
                                }
                            }
                        }
                    }
                }
                
                $success = true;
                $success_message = "🎉 ¡Evaluación programada creada exitosamente!";
                
            } else {
                // Para actividad normal
                $tipo = $_POST['tipo'] ?? 'Quiz';
                
                $stmt = $conn->pdo->prepare("
                    INSERT INTO actividades (id_curso, titulo, descripcion, tipo, dificultad, fecha_limite, puntos, es_examen) 
                    VALUES (:id_curso, :titulo, :descripcion, :tipo, :dificultad, :fecha_limite, :puntos, :es_examen)
                ");
                
                $stmt->execute([
                    ':id_curso' => $id_curso,
                    ':titulo' => $titulo,
                    ':descripcion' => $descripcion,
                    ':tipo' => $tipo,
                    ':dificultad' => $dificultad,
                    ':fecha_limite' => $fecha_limite,
                    ':puntos' => $puntos,
                    ':es_examen' => 0
                ]);
                
                $success = true;
                $success_message = "🚀 ¡Misión creada exitosamente!";
            }
        } catch(PDOException $e) {
            $error = "Error al crear la actividad: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Misión - D&F Mindspace</title>
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
            overflow-x: hidden;
        }
        
        .creation-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.6s ease-out forwards;
        }
        
        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .header-section {
            text-align: center;
            margin-bottom: 50px;
            position: relative;
        }
        
        .floating-emoji {
            font-size: 3rem;
            position: absolute;
            top: -20px;
            right: 10%;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(10deg); }
        }
        
        .header-section h1 {
            font-family: 'Nunito', sans-serif;
            font-size: 3rem;
            background: linear-gradient(90deg, var(--primary), var(--secondary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 15px;
            animation: gradientText 3s ease infinite;
            background-size: 200% 200%;
        }
        
        @keyframes gradientText {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .header-section p {
            color: #666;
            font-size: 1.2rem;
            max-width: 700px;
            margin: 0 auto;
            line-height: 1.6;
        }
        
        .activity-type-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .type-card {
            background: white;
            border-radius: 25px;
            padding: 40px 30px;
            box-shadow: var(--card-shadow);
            border: 3px solid transparent;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
            text-align: center;
            position: relative;
            overflow: hidden;
            transform-style: preserve-3d;
            perspective: 1000px;
        }
        
        .type-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            transform: scaleX(0);
            transition: transform 0.4s ease;
        }
        
        .type-card:hover::before {
            transform: scaleX(1);
        }
        
        .type-card:hover {
            transform: translateY(-10px) rotateX(5deg);
            box-shadow: 0 20px 40px rgba(44, 186, 236, 0.3);
        }
        
        .type-card.active {
            border-color: var(--primary);
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.05), rgba(44, 186, 236, 0.02));
            transform: translateY(-5px);
        }
        
        .type-card.active::before {
            transform: scaleX(1);
        }
        
        .type-icon {
            width: 90px;
            height: 90px;
            border-radius: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.8rem;
            margin: 0 auto 25px;
            background: linear-gradient(135deg, var(--primary), #2ca5d4);
            color: white;
            box-shadow: 0 12px 25px rgba(44, 186, 236, 0.4);
            transition: all 0.4s ease;
            transform: translateZ(20px);
        }
        
        .type-card:hover .type-icon {
            transform: translateZ(30px) scale(1.1);
            box-shadow: 0 15px 35px rgba(44, 186, 236, 0.5);
        }
        
        .type-card:nth-child(2) .type-icon {
            background: linear-gradient(135deg, var(--secondary), #f5c15d);
            box-shadow: 0 12px 25px rgba(240, 174, 42, 0.4);
        }
        
        .type-card:nth-child(2):hover .type-icon {
            box-shadow: 0 15px 35px rgba(240, 174, 42, 0.5);
        }
        
        .type-title {
            font-size: 1.7rem;
            font-weight: 800;
            color: #222;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .type-card:hover .type-title {
            color: var(--primary);
        }
        
        .type-description {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.6;
            font-size: 1.05rem;
        }
        
        .type-badge {
            display: inline-block;
            padding: 8px 18px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 700;
            background: linear-gradient(90deg, rgba(44, 186, 236, 0.1), rgba(44, 186, 236, 0.05));
            color: var(--primary);
            border: 2px solid rgba(44, 186, 236, 0.2);
            transition: all 0.3s ease;
        }
        
        .type-card:nth-child(2) .type-badge {
            background: linear-gradient(90deg, rgba(240, 174, 42, 0.1), rgba(240, 174, 42, 0.05));
            color: var(--secondary);
            border-color: rgba(240, 174, 42, 0.2);
        }
        
        .form-card {
            background: white;
            border-radius: 30px;
            padding: 50px;
            box-shadow: var(--card-shadow);
            border: 2px solid rgba(44, 186, 236, 0.1);
            margin-top: 40px;
            display: none;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }
        
        .form-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--primary), var(--secondary), var(--accent));
        }
        
        .form-card.active {
            display: block;
            opacity: 1;
            transform: translateY(0);
            animation: cardPopIn 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        
        @keyframes cardPopIn {
            0% { opacity: 0; transform: translateY(30px) scale(0.95); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        .form-section {
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 2px dashed rgba(44, 186, 236, 0.1);
        }
        
        .section-title {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
            color: var(--primary);
            font-weight: 800;
            font-size: 1.5rem;
        }
        
        .section-title i {
            background: linear-gradient(135deg, var(--primary), #2ca5d4);
            width: 50px;
            height: 50px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.4rem;
            box-shadow: 0 8px 20px rgba(44, 186, 236, 0.3);
            transition: all 0.3s ease;
        }
        
        .section-title:hover i {
            transform: rotate(15deg) scale(1.1);
        }
        
        .form-label {
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.1rem;
        }
        
        .form-label::after {
            content: '';
            flex: 1;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(44, 186, 236, 0.2));
            margin-left: 15px;
        }
        
        .form-control, .form-select {
            border: 2px solid rgba(44, 186, 236, 0.2);
            border-radius: 15px;
            padding: 16px 20px;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            font-size: 1.05rem;
            background: rgba(255, 255, 255, 0.9);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(44, 186, 236, 0.25);
            transform: translateY(-2px);
            background: white;
        }
        
        .form-control:hover, .form-select:hover {
            border-color: rgba(44, 186, 236, 0.4);
            box-shadow: 0 5px 15px rgba(44, 186, 236, 0.1);
        }
        
        .character-counter {
            font-size: 0.9rem;
            color: #888;
            text-align: right;
            margin-top: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .character-counter.warning {
            color: var(--secondary);
            font-weight: 600;
        }
        
        .character-counter.danger {
            color: #ff5757;
            font-weight: 700;
            animation: pulse 1s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .btn-create {
            background: linear-gradient(90deg, var(--primary), var(--accent));
            border: none;
            border-radius: 20px;
            padding: 20px 50px;
            color: white;
            font-weight: 800;
            font-size: 1.2rem;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 12px 30px rgba(44, 186, 236, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            width: 100%;
            margin-top: 40px;
            position: relative;
            overflow: hidden;
            letter-spacing: 0.5px;
        }
        
        .btn-create::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: 0.6s;
        }
        
        .btn-create:hover::before {
            left: 100%;
        }
        
        .btn-create:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 20px 45px rgba(44, 186, 236, 0.5);
            color: white;
        }
        
        .btn-create:active {
            transform: translateY(-2px);
        }
        
        .btn-create i {
            transition: all 0.3s ease;
        }
        
        .btn-create:hover i {
            transform: rotate(90deg) scale(1.2);
        }
        
        .alert-custom {
            border-radius: 20px;
            border: none;
            padding: 30px;
            margin-bottom: 40px;
            box-shadow: var(--card-shadow);
            animation: fadeIn 0.5s ease-out;
            position: relative;
            overflow: hidden;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-success {
            background: linear-gradient(135deg, rgba(131, 191, 70, 0.1), rgba(131, 191, 70, 0.05));
            border-left: 8px solid var(--accent);
            color: #2d5014;
        }
        
        .alert-success::before {
            content: '✨';
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 2rem;
            opacity: 0.3;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, rgba(255, 87, 87, 0.1), rgba(255, 87, 87, 0.05));
            border-left: 8px solid #ff5757;
            color: #8b0000;
        }
        
        .alert-danger::before {
            content: '⚠️';
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 2rem;
            opacity: 0.3;
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
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 8px 20px rgba(44, 186, 236, 0.1);
        }
        
        .back-link:hover {
            color: white;
            background: linear-gradient(135deg, var(--primary), #2ca5d4);
            border-color: var(--primary);
            transform: translateX(-10px) scale(1.05);
            box-shadow: 0 15px 30px rgba(44, 186, 236, 0.3);
        }
        
        .back-link i {
            transition: all 0.3s ease;
        }
        
        .back-link:hover i {
            transform: translateX(-5px);
        }
        
        /* Estilos específicos para preguntas de examen */
        .question-container {
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.05), rgba(44, 186, 236, 0.02));
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            border: 2px solid rgba(44, 186, 236, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .question-container:hover {
            border-color: var(--primary);
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.08), rgba(44, 186, 236, 0.04));
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(44, 186, 236, 0.1);
        }
        
        .question-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 8px;
            height: 100%;
            background: linear-gradient(to bottom, var(--primary), var(--accent));
        }
        
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .question-number {
            font-weight: 800;
            color: var(--primary);
            font-size: 1.3rem;
            background: rgba(44, 186, 236, 0.1);
            padding: 8px 20px;
            border-radius: 15px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-remove-question {
            background: linear-gradient(135deg, rgba(255, 87, 87, 0.1), rgba(255, 87, 87, 0.05));
            color: #ff5757;
            border: 2px solid rgba(255, 87, 87, 0.3);
            border-radius: 12px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-remove-question:hover {
            background: linear-gradient(135deg, #ff5757, #ff3030);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 87, 87, 0.3);
        }
        
        .option-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            padding: 15px;
            background: white;
            border-radius: 12px;
            border: 2px solid rgba(44, 186, 236, 0.1);
            transition: all 0.3s ease;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }
        
        .option-item:hover {
            border-color: var(--primary);
            box-shadow: 0 5px 15px rgba(44, 186, 236, 0.1);
            transform: translateY(-2px);
        }
        
        .correct-option {
            background: linear-gradient(135deg, rgba(131, 191, 70, 0.1), rgba(131, 191, 70, 0.05));
            border-color: var(--accent);
        }
        
        .btn-add-option {
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.1), rgba(44, 186, 236, 0.05));
            color: var(--primary);
            border: 2px solid rgba(44, 186, 236, 0.3);
            border-radius: 12px;
            padding: 12px 25px;
            margin-top: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-add-option:hover {
            background: linear-gradient(135deg, var(--primary), #2ca5d4);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(44, 186, 236, 0.3);
        }
        
        .btn-add-question {
            background: linear-gradient(90deg, var(--secondary), #f5c15d);
            color: white;
            border: none;
            border-radius: 20px;
            padding: 18px 35px;
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 10px 25px rgba(240, 174, 42, 0.3);
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 30px 0;
            width: 100%;
            justify-content: center;
        }
        
        .btn-add-question:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 15px 35px rgba(240, 174, 42, 0.4);
        }
        
        .btn-add-question:active {
            transform: translateY(-2px);
        }
        
        .exam-settings {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .setting-card {
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.05), rgba(44, 186, 236, 0.02));
            border-radius: 20px;
            padding: 25px;
            border: 2px solid rgba(44, 186, 236, 0.1);
            transition: all 0.3s ease;
        }
        
        .setting-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(44, 186, 236, 0.1);
        }
        
        .setting-label {
            font-weight: 700;
            color: var(--primary);
            font-size: 1rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .setting-value {
            font-size: 1.4rem;
            font-weight: 800;
            color: #222;
            margin: 5px 0;
        }
        
        .timer-display {
            font-size: 2.5rem;
            font-weight: 900;
            text-align: center;
            color: var(--primary);
            margin: 15px 0;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .preview-container {
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.05), rgba(44, 186, 236, 0.02));
            border-radius: 25px;
            padding: 30px;
            margin-top: 30px;
            border: 2px dashed rgba(44, 186, 236, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .preview-container::before {
            content: '👁️';
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 2rem;
            opacity: 0.1;
        }
        
        .preview-title {
            color: var(--primary);
            font-weight: 800;
            margin-bottom: 20px;
            font-size: 1.4rem;
        }
        
        .question-preview {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 6px solid var(--primary);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        
        .question-preview:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(44, 186, 236, 0.15);
        }
        
        .difficulty-badge {
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 5px;
        }
        
        .difficulty-easy {
            background: linear-gradient(135deg, rgba(131, 191, 70, 0.15), rgba(131, 191, 70, 0.1));
            color: var(--accent);
            border: 2px solid rgba(131, 191, 70, 0.3);
        }
        
        .difficulty-medium {
            background: linear-gradient(135deg, rgba(240, 174, 42, 0.15), rgba(240, 174, 42, 0.1));
            color: var(--secondary);
            border: 2px solid rgba(240, 174, 42, 0.3);
        }
        
        .difficulty-hard {
            background: linear-gradient(135deg, rgba(255, 107, 139, 0.15), rgba(255, 107, 139, 0.1));
            color: #ff6b8b;
            border: 2px solid rgba(255, 107, 139, 0.3);
        }
        
        /* Partículas flotantes */
        .floating-particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }
        
        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: var(--primary);
            border-radius: 50%;
            opacity: 0.3;
            animation: floatParticle 20s infinite linear;
        }
        
        @keyframes floatParticle {
            0% { transform: translateY(100vh) translateX(0) rotate(0deg); }
            100% { transform: translateY(-100px) translateX(100px) rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .creation-container {
                padding: 20px 15px;
            }
            
            .header-section h1 {
                font-size: 2.2rem;
            }
            
            .form-card {
                padding: 30px 20px;
            }
            
            .activity-type-selector {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .exam-settings {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .btn-create, .btn-add-question {
                padding: 16px 30px;
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Partículas flotantes -->
    <div class="floating-particles" id="particles"></div>
    
    <div class="creation-container">
        <!-- Botón para volver -->
        <a href="dashboard_tutor.php" class="back-link animate__animated animate__fadeInLeft">
            <i class="fas fa-arrow-left"></i> Volver al Panel
        </a>
        
        <!-- Encabezado -->
        <div class="header-section">
            <span class="floating-emoji">🚀</span>
            <h1>✨ Crear Nueva Misión</h1>
            <p>Diseña aventuras interactivas o evaluaciones programadas para medir el aprendizaje de tus exploradores.</p>
        </div>
        
        <!-- Mensajes de éxito/error -->
        <?php if ($success): ?>
            <div class="alert alert-success alert-custom animate__animated animate__fadeInDown" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-check-circle fa-3x me-3"></i>
                    <div>
                        <h4 class="alert-heading mb-2 fw-bold">¡Misión Creada con Éxito!</h4>
                        <p class="mb-0 fs-5"><?php echo $success_message; ?></p>
                        <hr class="my-3">
                        <a href="crear_actividad.php" class="btn btn-outline-success me-2">
                            <i class="fas fa-plus me-2"></i>Crear Otra
                        </a>
                        <a href="dashboard_tutor.php" class="btn btn-success">
                            <i class="fas fa-home me-2"></i>Ir al Panel
                        </a>
                    </div>
                </div>
            </div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger alert-custom animate__animated animate__shakeX" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle fa-3x me-3"></i>
                    <div>
                        <h4 class="alert-heading mb-2 fw-bold">Error al Crear la Misión</h4>
                        <p class="mb-0 fs-5"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Selector de tipo de actividad -->
        <div class="activity-type-selector animate__animated animate__fadeInUp">
            <div class="type-card active" id="typeNormal" onclick="selectType('normal')">
                <div class="type-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <h3 class="type-title">🎮 Actividad Interactiva</h3>
                <p class="type-description">Crea misiones divertidas con videos, cuestionarios o tareas para subir archivos. Ideal para aprendizaje continuo y creativo.</p>
                <div class="type-badge">Recomendado para aprendizaje diario</div>
                <div class="mt-3">
                    <i class="fas fa-star text-warning me-2"></i>
                    <small class="text-muted">Flexible y creativo</small>
                </div>
            </div>
            
            <div class="type-card" id="typeExam" onclick="selectType('examen')">
                <div class="type-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <h3 class="type-title">📝 Evaluación Programada</h3>
                <p class="type-description">Genera exámenes con límite de tiempo, preguntas aleatorias y calificación automática. Perfecto para evaluaciones formales.</p>
                <div class="type-badge">Ideal para exámenes parciales/finales</div>
                <div class="mt-3">
                    <i class="fas fa-clock text-info me-2"></i>
                    <small class="text-muted">Control de tiempo y intentos</small>
                </div>
            </div>
        </div>
        
        <!-- Formulario para Actividad Normal -->
        <div class="form-card active" id="formNormal">
            <form method="POST" id="formActividadNormal">
                <input type="hidden" name="tipo_actividad" value="normal">
                
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-info-circle"></i>
                        <span>Información de la Misión</span>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-8 mb-4">
                            <label class="form-label">
                                <i class="fas fa-heading"></i>
                                Título de la Misión *
                            </label>
                            <input type="text" name="titulo" class="form-control" 
                                   placeholder="Ej: El laberinto de las letras o Sumas y restas mágicas" 
                                   required maxlength="200" id="tituloNormal">
                            <div class="character-counter" id="tituloCounterNormal">0/200</div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="form-label">
                                <i class="fas fa-trophy"></i>
                                Puntos (XP) *
                            </label>
                            <input type="number" name="puntos" class="form-control" 
                                   value="10" min="1" max="1000" required>
                            <small class="text-muted d-block mt-2">Experiencia que ganarán los exploradores</small>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">
                            <i class="fas fa-book"></i>
                            Curso *
                        </label>
                        <select name="id_curso" class="form-select" required>
                            <option value="">Selecciona un curso</option>
                            <?php 
                            mysqli_data_seek($res_cursos, 0);
                            while($c = mysqli_fetch_assoc($res_cursos)): 
                            ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nombre']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">
                            <i class="fas fa-align-left"></i>
                            Descripción e Instrucciones
                        </label>
                        <textarea name="descripcion" class="form-control" rows="4" 
                                  placeholder="Describe la misión, qué deben hacer los exploradores, y cómo completarla con éxito..." 
                                  maxlength="1000" id="descripcionNormal"></textarea>
                        <div class="character-counter" id="descripcionCounterNormal">0/1000</div>
                    </div>
                </div>
                
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-cogs"></i>
                        <span>Configuración de la Misión</span>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <label class="form-label">
                                <i class="fas fa-puzzle-piece"></i>
                                Tipo de Actividad *
                            </label>
                            <select name="tipo" class="form-select" required>
                                <option value="Quiz">🔍 Cuestionario Interactivo</option>
                                <option value="Video">🎥 Video con Preguntas</option>
                                <option value="Tarea">📤 Subir Archivo/Tarea</option>
                                <option value="Juego">🎮 Juego Educativo</option>
                                <option value="Lectura">📚 Lectura y Reflexión</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-4">
                            <label class="form-label">
                                <i class="fas fa-chart-line"></i>
                                Dificultad *
                            </label>
                            <select name="dificultad" class="form-select" required>
                                <option value="Fácil">⭐ Fácil</option>
                                <option value="Normal">⭐⭐ Normal</option>
                                <option value="Difícil">⭐⭐⭐ Difícil</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-4">
                            <label class="form-label">
                                <i class="fas fa-calendar-alt"></i>
                                Fecha Límite *
                            </label>
                            <input type="datetime-local" name="fecha_limite" class="form-control" required>
                            <small class="text-muted d-block mt-2">Fecha y hora límite para entregar</small>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-create">
                    <i class="fas fa-plus-circle"></i> CREAR MISIÓN INTERACTIVA
                </button>
            </form>
        </div>
        
        <!-- Formulario para Examen -->
        <div class="form-card" id="formExamen">
            <form method="POST" id="formActividadExamen">
                <input type="hidden" name="tipo_actividad" value="examen">
                
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-file-alt"></i>
                        <span>Configuración del Examen</span>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-8 mb-4">
                            <label class="form-label">
                                <i class="fas fa-heading"></i>
                                Título del Examen *
                            </label>
                            <input type="text" name="titulo" class="form-control" 
                                   placeholder="Ej: Evaluación de Matemáticas - Unidad 1" 
                                   required maxlength="200" id="tituloExamen">
                            <div class="character-counter" id="tituloCounterExamen">0/200</div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="form-label">
                                <i class="fas fa-trophy"></i>
                                Puntos Máximos *
                            </label>
                            <input type="number" name="puntos" class="form-control" 
                                   value="100" min="10" max="1000" required>
                            <small class="text-muted d-block mt-2">Puntuación máxima del examen</small>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">
                            <i class="fas fa-book"></i>
                            Curso *
                        </label>
                        <select name="id_curso" class="form-select" required>
                            <option value="">Selecciona un curso</option>
                            <?php 
                            mysqli_data_seek($res_cursos, 0);
                            while($c = mysqli_fetch_assoc($res_cursos)): 
                            ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nombre']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">
                            <i class="fas fa-align-left"></i>
                            Instrucciones del Examen
                        </label>
                        <textarea name="descripcion" class="form-control" rows="3" 
                                  placeholder="Instrucciones importantes para los estudiantes antes de comenzar el examen..." 
                                  maxlength="500" id="descripcionExamen"></textarea>
                        <div class="character-counter" id="descripcionCounterExamen">0/500</div>
                    </div>
                    
                    <div class="exam-settings">
                        <div class="setting-card">
                            <div class="setting-label">⏱️ Tiempo Límite (minutos)</div>
                            <div class="timer-display" id="timerDisplay">60</div>
                            <input type="range" name="tiempo_limite" class="form-range" 
                                   min="5" max="180" value="60" step="5"
                                   oninput="document.getElementById('timerDisplay').textContent = this.value">
                            <small class="text-muted">Tiempo máximo para completar</small>
                        </div>
                        
                        <div class="setting-card">
                            <div class="setting-label">🔄 Intentos Permitidos</div>
                            <div class="setting-value" id="intentosDisplay">1</div>
                            <input type="range" name="intentos_permitidos" class="form-range" 
                                   min="1" max="3" value="1" step="1"
                                   oninput="document.getElementById('intentosDisplay').textContent = this.value">
                            <small class="text-muted">Número de intentos permitidos</small>
                        </div>
                        
                        <div class="setting-card">
                            <div class="setting-label">📊 Mostrar Resultados</div>
                            <div class="form-check form-switch mt-3" style="transform: scale(1.3);">
                                <input class="form-check-input" type="checkbox" name="mostrar_resultados" id="mostrarResultados" checked>
                                <label class="form-check-label ms-3" for="mostrarResultados">
                                    Mostrar calificación después del examen
                                </label>
                            </div>
                        </div>
                        
                        <div class="setting-card">
                            <div class="setting-label">🎯 Dificultad</div>
                            <select name="dificultad" class="form-select mt-2" required>
                                <option value="Fácil">⭐ Fácil</option>
                                <option value="Normal" selected>⭐⭐ Normal</option>
                                <option value="Difícil">⭐⭐⭐ Difícil</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">
                            <i class="fas fa-calendar-alt"></i>
                            Fecha y Hora del Examen *
                        </label>
                        <input type="datetime-local" name="fecha_limite" class="form-control" required>
                        <small class="text-muted d-block mt-2">Fecha límite para presentar el examen</small>
                    </div>
                </div>
                
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-question-circle"></i>
                        <span>Preguntas del Examen</span>
                    </div>
                    
                    <div id="preguntasContainer">
                        <!-- Las preguntas se agregarán dinámicamente aquí -->
                    </div>
                    
                    <button type="button" class="btn btn-add-question" onclick="agregarPregunta()">
                        <i class="fas fa-plus"></i> AGREGAR NUEVA PREGUNTA
                    </button>
                </div>
                
                <div class="preview-container">
                    <div class="section-title">
                        <i class="fas fa-eye"></i>
                        <span>Vista Previa del Examen</span>
                    </div>
                    <div id="examPreview">
                        <p class="text-muted">Agrega preguntas para ver la vista previa aquí...</p>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-create">
                    <i class="fas fa-file-alt"></i> CREAR EVALUACIÓN PROGRAMADA
                </button>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Variables globales
        let preguntaCount = 0;
        let currentType = 'normal';
        
        // Crear partículas flotantes
        function crearParticulas() {
            const container = document.getElementById('particles');
            for (let i = 0; i < 50; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.top = Math.random() * 100 + '%';
                particle.style.background = Math.random() > 0.5 ? 'var(--primary)' : 'var(--secondary)';
                particle.style.width = Math.random() * 4 + 2 + 'px';
                particle.style.height = particle.style.width;
                particle.style.opacity = Math.random() * 0.3 + 0.1;
                particle.style.animationDelay = Math.random() * 20 + 's';
                particle.style.animationDuration = Math.random() * 10 + 15 + 's';
                container.appendChild(particle);
            }
        }
        
        // Seleccionar tipo de actividad
        function selectType(type) {
            currentType = type;
            
            // Actualizar tarjetas
            document.getElementById('typeNormal').classList.remove('active');
            document.getElementById('typeExam').classList.remove('active');
            document.getElementById('type' + (type === 'normal' ? 'Normal' : 'Exam')).classList.add('active');
            
            // Mostrar formulario correspondiente
            document.getElementById('formNormal').classList.remove('active');
            document.getElementById('formExamen').classList.remove('active');
            document.getElementById('form' + (type === 'normal' ? 'Normal' : 'Examen')).classList.add('active');
            
            // Resetear preguntas si cambiamos a actividad normal
            if (type === 'normal') {
                preguntaCount = 0;
                document.getElementById('preguntasContainer').innerHTML = '';
                document.getElementById('examPreview').innerHTML = '<p class="text-muted">Agrega preguntas para ver la vista previa aquí...</p>';
            } else {
                if (preguntaCount === 0) {
                    agregarPregunta();
                }
            }
        }
        
        // Agregar nueva pregunta al examen
        function agregarPregunta() {
            preguntaCount++;
            const preguntaId = 'pregunta_' + preguntaCount;
            
            const preguntaHTML = `
                <div class="question-container" id="${preguntaId}">
                    <div class="question-header">
                        <span class="question-number">
                            <i class="fas fa-question-circle"></i>
                            Pregunta #${preguntaCount}
                        </span>
                        <button type="button" class="btn-remove-question" onclick="eliminarPregunta('${preguntaId}')">
                            <i class="fas fa-times me-1"></i> Eliminar
                        </button>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Texto de la Pregunta *</label>
                        <input type="text" name="preguntas[]" class="form-control pregunta-texto" 
                               placeholder="Escribe la pregunta aquí..." required
                               oninput="actualizarVistaPrevia()">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo de Pregunta</label>
                            <select name="tipo_pregunta[]" class="form-select tipo-pregunta" onchange="cambiarTipoPregunta(this, ${preguntaCount})">
                                <option value="opcion_multiple">Opción Múltiple</option>
                                <option value="respuesta_corta">Respuesta Corta</option>
                                <option value="verdadero_falso">Verdadero/Falso</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Puntos de la Pregunta</label>
                            <input type="number" name="puntos_pregunta[]" class="form-control" value="5" min="1" max="20">
                        </div>
                    </div>
                    
                    <div class="opciones-container" id="opciones_${preguntaCount}">
                        <div class="opcion-group">
                            <label class="form-label">Opciones de Respuesta</label>
                            <div class="option-item correct-option">
                                <input type="radio" name="correcta[${preguntaCount}][0]" value="1" checked>
                                <input type="text" name="opciones[${preguntaCount}][]" class="form-control" 
                                       placeholder="Opción 1" required>
                                <span class="badge bg-success">Correcta</span>
                            </div>
                            <div class="option-item">
                                <input type="radio" name="correcta[${preguntaCount}][1]" value="1">
                                <input type="text" name="opciones[${preguntaCount}][]" class="form-control" 
                                       placeholder="Opción 2" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="respuesta-corta-container" id="respuesta_${preguntaCount}" style="display: none;">
                        <label class="form-label">Respuesta Correcta *</label>
                        <input type="text" name="respuesta_correcta[]" class="form-control" 
                               placeholder="Escribe la respuesta correcta">
                    </div>
                    
                    <div class="text-end">
                        <button type="button" class="btn btn-add-option" onclick="agregarOpcion(${preguntaCount})">
                            <i class="fas fa-plus me-1"></i> Agregar Opción
                        </button>
                    </div>
                </div>
            `;
            
            document.getElementById('preguntasContainer').insertAdjacentHTML('beforeend', preguntaHTML);
            actualizarVistaPrevia();
        }
        
        // Eliminar pregunta
        function eliminarPregunta(preguntaId) {
            document.getElementById(preguntaId).remove();
            actualizarNumeracionPreguntas();
            actualizarVistaPrevia();
        }
        
        // Actualizar numeración de preguntas
        function actualizarNumeracionPreguntas() {
            const preguntas = document.querySelectorAll('.question-container');
            preguntaCount = preguntas.length;
            
            preguntas.forEach((pregunta, index) => {
                const numero = index + 1;
                pregunta.querySelector('.question-number').innerHTML = `<i class="fas fa-question-circle"></i> Pregunta #${numero}`;
                
                // Actualizar names de opciones
                const opciones = pregunta.querySelectorAll('input[type="radio"]');
                opciones.forEach((opcion, opcionIndex) => {
                    opcion.name = `correcta[${numero}][${opcionIndex}]`;
                });
            });
        }
        
        // Cambiar tipo de pregunta
        function cambiarTipoPregunta(select, preguntaNum) {
            const tipo = select.value;
            const opcionesContainer = document.getElementById(`opciones_${preguntaNum}`);
            const respuestaContainer = document.getElementById(`respuesta_${preguntaNum}`);
            
            if (tipo === 'opcion_multiple' || tipo === 'verdadero_falso') {
                opcionesContainer.style.display = 'block';
                respuestaContainer.style.display = 'none';
                
                if (tipo === 'verdadero_falso') {
                    opcionesContainer.innerHTML = `
                        <div class="opcion-group">
                            <label class="form-label">Opciones Verdadero/Falso</label>
                            <div class="option-item correct-option">
                                <input type="radio" name="correcta[${preguntaNum}][0]" value="1" checked>
                                <input type="text" name="opciones[${preguntaNum}][]" class="form-control" 
                                       value="Verdadero" readonly>
                                <span class="badge bg-success">Correcta</span>
                            </div>
                            <div class="option-item">
                                <input type="radio" name="correcta[${preguntaNum}][1]" value="1">
                                <input type="text" name="opciones[${preguntaNum}][]" class="form-control" 
                                       value="Falso" readonly>
                            </div>
                        </div>
                    `;
                }
            } else if (tipo === 'respuesta_corta') {
                opcionesContainer.style.display = 'none';
                respuestaContainer.style.display = 'block';
            }
            
            actualizarVistaPrevia();
        }
        
        // Agregar opción a pregunta de opción múltiple
        function agregarOpcion(preguntaNum) {
            const opcionesContainer = document.getElementById(`opciones_${preguntaNum}`);
            const opcionCount = opcionesContainer.querySelectorAll('.option-item').length;
            
            const opcionHTML = `
                <div class="option-item">
                    <input type="radio" name="correcta[${preguntaNum}][${opcionCount}]" value="1">
                    <input type="text" name="opciones[${preguntaNum}][]" class="form-control" 
                           placeholder="Opción ${opcionCount + 1}" required>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.option-item').remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            opcionesContainer.querySelector('.opcion-group').insertAdjacentHTML('beforeend', opcionHTML);
        }
        
        // Actualizar vista previa del examen
        function actualizarVistaPrevia() {
            const preview = document.getElementById('examPreview');
            const preguntas = document.querySelectorAll('.question-container');
            
            if (preguntas.length === 0) {
                preview.innerHTML = '<p class="text-muted">Agrega preguntas para ver la vista previa aquí...</p>';
                return;
            }
            
            let previewHTML = '<div class="row">';
            
            preguntas.forEach((pregunta, index) => {
                const texto = pregunta.querySelector('.pregunta-texto').value || 'Pregunta sin texto';
                const tipo = pregunta.querySelector('.tipo-pregunta').value;
                
                previewHTML += `
                    <div class="col-md-6 mb-3">
                        <div class="question-preview">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <strong>Pregunta ${index + 1}</strong>
                                <span class="badge bg-primary">${getTipoNombre(tipo)}</span>
                            </div>
                            <p class="mb-2">${texto.substring(0, 100)}${texto.length > 100 ? '...' : ''}</p>
                            <small class="text-muted">${getOpcionesPreview(pregunta, tipo)}</small>
                        </div>
                    </div>
                `;
            });
            
            previewHTML += '</div>';
            preview.innerHTML = previewHTML;
        }
        
        // Obtener nombre del tipo de pregunta
        function getTipoNombre(tipo) {
            const tipos = {
                'opcion_multiple': 'Opción Múltiple',
                'respuesta_corta': 'Respuesta Corta',
                'verdadero_falso': 'V/F'
            };
            return tipos[tipo] || tipo;
        }
        
        // Obtener vista previa de opciones
        function getOpcionesPreview(pregunta, tipo) {
            if (tipo === 'opcion_multiple') {
                const opciones = pregunta.querySelectorAll('.option-item input[type="text"]');
                return `${opciones.length} opciones`;
            } else if (tipo === 'verdadero_falso') {
                return 'Verdadero / Falso';
            } else if (tipo === 'respuesta_corta') {
                return 'Respuesta corta';
            }
            return '';
        }
        
        // Validar formularios
        document.addEventListener('DOMContentLoaded', function() {
            crearParticulas();
            
            // Establecer fecha mínima como mañana
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            
            const fechaInputs = document.querySelectorAll('input[type="datetime-local"]');
            fechaInputs.forEach(input => {
                input.min = tomorrow.toISOString().slice(0, 16);
                
                // Establecer fecha por defecto (mañana a las 10:00)
                const defaultDate = new Date(tomorrow);
                defaultDate.setHours(10, 0, 0, 0);
                input.value = defaultDate.toISOString().slice(0, 16);
            });
            
            // Agregar primera pregunta si es examen
            if (currentType === 'examen') {
                agregarPregunta();
            }
            
            // Validar formulario de examen
            document.getElementById('formActividadExamen').addEventListener('submit', function(e) {
                const preguntas = document.querySelectorAll('.question-container');
                
                if (preguntas.length === 0) {
                    e.preventDefault();
                    showError('Debes agregar al menos una pregunta al examen.');
                    return false;
                }
                
                // Cambiar texto del botón
                const submitBtn = this.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> CREANDO EVALUACIÓN...';
                submitBtn.disabled = true;
                
                return true;
            });
            
            // Validar formulario de actividad normal
            document.getElementById('formActividadNormal').addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> CREANDO MISIÓN...';
                submitBtn.disabled = true;
            });
        });
        
        // Mostrar error con estilo
        function showError(mensaje) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-danger alert-custom animate__animated animate__fadeInDown';
            errorDiv.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                    <div>
                        <h4 class="alert-heading mb-2 fw-bold">Error</h4>
                        <p class="mb-0">${mensaje}</p>
                    </div>
                </div>
            `;
            
            document.querySelector('.creation-container').insertBefore(errorDiv, document.querySelector('.activity-type-selector'));
            
            setTimeout(() => {
                errorDiv.classList.add('animate__fadeOutUp');
                setTimeout(() => errorDiv.remove(), 500);
            }, 5000);
        }
        
        // Efecto de escritura en los placeholders
        const placeholders = [
            "Ej: El laberinto de las letras",
            "Ej: Sumas y restas mágicas",
            "Ej: Viaje al espacio",
            "Ej: Animales del mundo"
        ];
        
        let currentPlaceholderIndex = 0;
        let charIndex = 0;
        let isDeleting = false;
        
        function typeWriterEffect() {
            const input = document.getElementById('tituloNormal');
            if (!input) return;
            
            const currentPlaceholder = placeholders[currentPlaceholderIndex];
            
            if (isDeleting) {
                input.placeholder = currentPlaceholder.substring(0, charIndex - 1);
                charIndex--;
            } else {
                input.placeholder = currentPlaceholder.substring(0, charIndex + 1);
                charIndex++;
            }
            
            let speed = isDeleting ? 50 : 100;
            
            if (!isDeleting && charIndex === currentPlaceholder.length) {
                speed = 2000;
                isDeleting = true;
            } else if (isDeleting && charIndex === 0) {
                isDeleting = false;
                currentPlaceholderIndex = (currentPlaceholderIndex + 1) % placeholders.length;
                speed = 500;
            }
            
            setTimeout(typeWriterEffect, speed);
        }
        
        // Iniciar efecto si el campo está vacío
        const tituloInput = document.getElementById('tituloNormal');
        if (tituloInput && !tituloInput.value) {
            typeWriterEffect();
        }
        
        tituloInput.addEventListener('focus', () => {
            tituloInput.placeholder = "Ej: El laberinto de las letras o Sumas y restas mágicas";
        });
        
        tituloInput.addEventListener('blur', function() {
            if (!this.value) {
                typeWriterEffect();
            }
        });
    </script>
</body>
</html>