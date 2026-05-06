<?php
include 'php/config.php';
session_start();

// Verificar que el usuario es tutor
if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'tutor') {
    header("Location: index.php");
    exit();
}

$tutor_id = $_SESSION['user_id'];
$nombre_tutor = $_SESSION['nombre'];

// Manejar el envío del formulario
$success = false;
$error = '';
$curso_creado = false;
$nombre_curso = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validar y sanitizar datos
    $nombre = mysqli_real_escape_string($conn, trim($_POST['nombre']));
    $desc = mysqli_real_escape_string($conn, trim($_POST['descripcion']));
    $nivel = mysqli_real_escape_string($conn, $_POST['nivel']);
    $duracion = intval($_POST['duracion']);
    
    // Validaciones adicionales
    if (empty($nombre) || strlen($nombre) < 3) {
        $error = "El nombre del curso debe tener al menos 3 caracteres.";
    } elseif ($duracion < 1 || $duracion > 1000) {
        $error = "La duración debe estar entre 1 y 1000 horas.";
    } else {
        // Insertar en la base de datos - SOLO COLUMNAS EXISTENTES
        $sql = "INSERT INTO cursos (nombre, descripcion, nivel, duracion_horas, id_tutor, fecha_creacion, activo) 
                VALUES ('$nombre', '$desc', '$nivel', '$duracion', '$tutor_id', NOW(), 1)";
        
        if (mysqli_query($conn, $sql)) {
            $curso_id = mysqli_insert_id($conn);
            $success = true;
            $curso_creado = true;
            $nombre_curso = $nombre;
        } else {
            $error = "Error al crear el curso: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Aventura - D&F Mindspace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Poppins:wght@300;400;600;700&family=Fredoka+One&display=swap" rel="stylesheet">
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
        
        .create-course-container {
            max-width: 900px;
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
            font-family: 'Fredoka One', cursive;
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
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }
        
        .creation-card {
            background: white;
            border-radius: 30px;
            padding: 50px;
            box-shadow: var(--card-shadow);
            border: 2px solid rgba(44, 186, 236, 0.1);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .creation-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--primary), var(--secondary), var(--accent));
        }
        
        .form-section {
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 2px dashed rgba(44, 186, 236, 0.1);
            opacity: 0;
            transform: translateX(-20px);
            animation: slideInLeft 0.5s ease-out forwards;
        }
        
        .form-section:nth-child(2) { animation-delay: 0.1s; }
        .form-section:nth-child(3) { animation-delay: 0.2s; }
        
        @keyframes slideInLeft {
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .section-title {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
            color: var(--primary);
            font-weight: 700;
            font-size: 1.4rem;
        }
        
        .section-title i {
            background: linear-gradient(135deg, var(--primary), #2ca5d4);
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
            transition: all 0.3s ease;
        }
        
        .section-title:hover i {
            transform: rotate(15deg) scale(1.1);
        }
        
        .form-label {
            font-weight: 600;
            color: #444;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-label::after {
            content: '';
            flex: 1;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(44, 186, 236, 0.2));
            margin-left: 10px;
        }
        
        .form-control, .form-select {
            border: 2px solid rgba(44, 186, 236, 0.2);
            border-radius: 15px;
            padding: 15px 20px;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(44, 186, 236, 0.25);
            transform: translateY(-2px);
        }
        
        .form-control:hover, .form-select:hover {
            border-color: rgba(44, 186, 236, 0.4);
        }
        
        .character-counter {
            font-size: 0.85rem;
            color: #888;
            text-align: right;
            margin-top: 8px;
            transition: all 0.3s ease;
        }
        
        .character-counter.warning {
            color: var(--secondary);
            font-weight: 600;
        }
        
        .character-counter.danger {
            color: #dc3545;
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
            padding: 18px 50px;
            color: white;
            font-weight: 800;
            font-size: 1.2rem;
            transition: all 0.4s ease;
            box-shadow: 0 10px 25px rgba(44, 186, 236, 0.3);
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
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: 0.6s;
        }
        
        .btn-create:hover::before {
            left: 100%;
        }
        
        .btn-create:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 15px 35px rgba(44, 186, 236, 0.4);
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
        
        /* Estilos para el modal de éxito */
        .success-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .success-content {
            background: white;
            border-radius: 30px;
            padding: 60px 40px;
            text-align: center;
            max-width: 500px;
            width: 90%;
            position: relative;
            animation: popIn 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        @keyframes popIn {
            0% { transform: scale(0.5) rotate(-10deg); opacity: 0; }
            100% { transform: scale(1) rotate(0); opacity: 1; }
        }
        
        .confetti {
            position: absolute;
            width: 10px;
            height: 10px;
            background: var(--primary);
            border-radius: 50%;
            animation: confettiFall 2s ease-out forwards;
        }
        
        @keyframes confettiFall {
            0% { transform: translateY(-100px) rotate(0deg); opacity: 1; }
            100% { transform: translateY(100vh) rotate(360deg); opacity: 0; }
        }
        
        .success-icon {
            font-size: 5rem;
            background: linear-gradient(135deg, var(--accent), #6ca839);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 20px;
            animation: bounce 1s infinite alternate;
        }
        
        @keyframes bounce {
            from { transform: translateY(0) scale(1); }
            to { transform: translateY(-10px) scale(1.1); }
        }
        
        .alert-custom {
            border-radius: 20px;
            border: none;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
            animation: shake 0.5s ease-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
            margin-bottom: 30px;
            padding: 12px 25px;
            background: rgba(44, 186, 236, 0.1);
            border-radius: 15px;
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            color: white;
            background: var(--primary);
            transform: translateX(-5px);
            box-shadow: 0 8px 20px rgba(44, 186, 236, 0.3);
        }
        
        .back-link i {
            transition: all 0.3s ease;
        }
        
        .back-link:hover i {
            transform: translateX(-3px);
        }
        
        /* Preview mejorado */
        .preview-card {
            background: linear-gradient(135deg, #ffffff, #f8fbff);
            border-radius: 25px;
            padding: 30px;
            border: 2px solid rgba(44, 186, 236, 0.2);
            box-shadow: var(--card-shadow);
            transition: all 0.4s ease;
        }
        
        .preview-card:hover {
            transform: translateY(-5px) scale(1.01);
            box-shadow: 0 20px 40px rgba(44, 186, 236, 0.2);
        }
        
        .preview-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(44, 186, 236, 0.1);
        }
        
        .preview-icon {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            background: linear-gradient(135deg, var(--primary), #2ca5d4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            box-shadow: 0 8px 20px rgba(44, 186, 236, 0.3);
            transition: all 0.3s ease;
        }
        
        .preview-card:hover .preview-icon {
            transform: rotate(15deg) scale(1.1);
        }
        
        .preview-title {
            font-size: 1.6rem;
            font-weight: 800;
            color: #222;
            margin: 0;
            transition: all 0.3s ease;
        }
        
        .preview-meta {
            display: flex;
            gap: 25px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #666;
            font-size: 0.95rem;
            background: rgba(44, 186, 236, 0.1);
            padding: 10px 15px;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .meta-item:hover {
            background: rgba(44, 186, 236, 0.2);
            transform: translateY(-2px);
        }
        
        .meta-item i {
            color: var(--primary);
            font-size: 1.1rem;
        }
        
        .progress-indicator {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s ease;
            z-index: 999;
        }
        
        .field-error {
            color: #dc3545;
            font-size: 0.85rem;
            margin-top: 5px;
            display: none;
            animation: slideDown 0.3s ease-out;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .input-valid {
            border-color: var(--accent) !important;
        }
        
        .input-invalid {
            border-color: #dc3545 !important;
            animation: shakeField 0.5s ease-out;
        }
        
        @keyframes shakeField {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        @media (max-width: 768px) {
            .creation-card {
                padding: 30px 20px;
            }
            
            .header-section h1 {
                font-size: 2.2rem;
            }
            
            .floating-emoji {
                font-size: 2rem;
                right: 5%;
            }
            
            .preview-meta {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn-create {
                padding: 15px 30px;
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Barra de progreso -->
    <div class="progress-indicator" id="progressBar"></div>
    
    <!-- Modal de éxito -->
    <div class="success-modal" id="successModal">
        <div class="success-content">
            <div class="success-icon">
                <i class="fas fa-trophy"></i>
            </div>
            <h2 class="fw-bold mb-3" style="color: var(--accent);">¡Aventura Creada!</h2>
            <p class="mb-4" id="successMessage">El curso ha sido creado exitosamente.</p>
            <div class="d-flex gap-3 justify-content-center">
                <a href="dashboard_tutor.php" class="btn btn-primary" style="background: var(--primary); border: none; padding: 12px 30px; border-radius: 15px;">
                    <i class="fas fa-home me-2"></i>Volver al Panel
                </a>
                <button onclick="closeSuccessModal()" class="btn btn-outline-primary" style="border-color: var(--primary); color: var(--primary); padding: 12px 30px; border-radius: 15px;">
                    <i class="fas fa-plus me-2"></i>Crear Otra
                </button>
            </div>
        </div>
    </div>
    
    <div class="create-course-container">
        <!-- Botón para volver -->
        <a href="dashboard_tutor.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Volver al Panel
        </a>
        
        <!-- Encabezado -->
        <div class="header-section">
            <span class="floating-emoji">🚀</span>
            <h1>Crear Nueva Aventura</h1>
            <p>Diseña una experiencia de aprendizaje única para tus pequeños exploradores. Cada detalle hace la diferencia.</p>
        </div>
        
        <!-- Mensaje de error -->
        <?php if ($error): ?>
        <div class="alert alert-danger alert-custom" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                <div>
                    <h4 class="alert-heading mb-2">Error al Crear la Aventura</h4>
                    <p class="mb-0"><?php echo htmlspecialchars($error); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Formulario de creación -->
        <div class="creation-card">
            <form method="POST" id="courseForm" novalidate>
                
                <!-- Sección 1: Información Básica -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-info-circle"></i>
                        <span>Información de la Aventura</span>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-8 mb-4">
                            <label class="form-label">
                                <i class="fas fa-heading"></i>
                                Nombre de la Aventura *
                            </label>
                            <input type="text" name="nombre" class="form-control" 
                                   placeholder="Ej: Matemáticas Mágicas o Viaje al Espacio" 
                                   required maxlength="100" id="courseName">
                            <div class="character-counter" id="nameCounter">0/100</div>
                            <div class="field-error" id="nameError">El nombre debe tener al menos 3 caracteres</div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="form-label">
                                <i class="fas fa-clock"></i>
                                Horas de Exploración *
                            </label>
                            <input type="number" name="duracion" class="form-control" 
                                   value="10" min="1" max="1000" required id="courseDuration">
                            <small class="text-muted d-block mt-2">Horas estimadas de aprendizaje</small>
                            <div class="field-error" id="durationError">La duración debe ser entre 1 y 1000 horas</div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">
                            <i class="fas fa-align-left"></i>
                            Descripción de la Aventura
                        </label>
                        <textarea name="descripcion" class="form-control" rows="4" 
                                  placeholder="Describe qué aprenderán los niños en esta aventura... ¿Qué descubrirán? ¿Qué habilidades desarrollarán?" 
                                  maxlength="500" id="courseDesc"></textarea>
                        <div class="character-counter" id="descCounter">0/500</div>
                    </div>
                </div>
                
                <!-- Sección 2: Configuración -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-cogs"></i>
                        <span>Configuración de la Aventura</span>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">
                            <i class="fas fa-chart-line"></i>
                            Nivel de Dificultad *
                        </label>
                        <select name="nivel" class="form-select" required id="courseLevel">
                            <option value="Básico">⭐ Básico - Para pequeños exploradores</option>
                            <option value="Intermedio">⭐⭐ Intermedio - Desafíos emocionantes</option>
                            <option value="Avanzado">⭐⭐⭐ Avanzado - Grandes aventureros</option>
                        </select>
                    </div>
                </div>
                
                <!-- Vista Previa -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-eye"></i>
                        <span>Vista Previa</span>
                    </div>
                    
                    <div class="preview-card">
                        <div class="preview-header">
                            <div class="preview-icon">
                                <i class="fas fa-book"></i>
                            </div>
                            <div>
                                <h3 class="preview-title" id="previewTitle">Nombre de la Aventura</h3>
                                <p class="text-muted mb-0" id="previewDesc">Descripción aparecerá aquí...</p>
                            </div>
                        </div>
                        
                        <div class="preview-meta">
                            <div class="meta-item">
                                <i class="fas fa-user-graduate"></i>
                                <span>Nivel: <strong id="previewLevel">Básico</strong></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-clock"></i>
                                <span>Duración: <strong id="previewDuration">10</strong> horas</span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-calendar"></i>
                                <span>Creado: <strong><?php echo date('d/m/Y'); ?></strong></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Botón de creación -->
                <button type="submit" class="btn btn-create" id="createButton">
                    <i class="fas fa-plus-circle"></i> CREAR AVENTURA DE APRENDIZAJE
                </button>
                
                <p class="text-center text-muted mt-4">
                    <i class="fas fa-lightbulb text-warning me-2"></i>
                    <small>Recuerda: Una buena aventura es aquella que despierta la curiosidad y el amor por aprender.</small>
                </p>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if ($curso_creado): ?>
        // Mostrar modal de éxito si el curso fue creado
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                document.getElementById('successMessage').textContent = 'Tu aventura "' + <?php echo json_encode($nombre_curso); ?> + '" ha sido creada exitosamente. ¡Los niños están listos para explorar!';
                showSuccessModal();
                createConfetti();
            }, 500);
        });
        <?php endif; ?>
        
        // Variables globales
        let isSubmitting = false;
        let confettiInterval;
        
        // Actualizar contadores de caracteres
        function updateCharacterCounters() {
            const nameField = document.getElementById('courseName');
            const descField = document.getElementById('courseDesc');
            
            document.getElementById('nameCounter').textContent = nameField.value.length + '/100';
            document.getElementById('descCounter').textContent = descField.value.length + '/500';
            
            // Cambiar color cuando se acerca al límite
            [nameField, descField].forEach(field => {
                const counter = field.id === 'courseName' ? 'nameCounter' : 'descCounter';
                const max = field.id === 'courseName' ? 100 : 500;
                
                const countElement = document.getElementById(counter);
                if (field.value.length > max * 0.9) {
                    countElement.classList.add('danger');
                    countElement.classList.remove('warning');
                } else if (field.value.length > max * 0.7) {
                    countElement.classList.add('warning');
                    countElement.classList.remove('danger');
                } else {
                    countElement.classList.remove('warning', 'danger');
                }
            });
        }
        
        // Validación en tiempo real
        function validateField(field, min, max, errorId) {
            const value = field.value.trim();
            const errorElement = document.getElementById(errorId);
            
            if (field.type === 'number') {
                const numValue = parseInt(value);
                if (isNaN(numValue) || numValue < min || numValue > max) {
                    field.classList.add('input-invalid');
                    field.classList.remove('input-valid');
                    errorElement.style.display = 'block';
                    return false;
                }
            } else {
                if (value.length < min) {
                    field.classList.add('input-invalid');
                    field.classList.remove('input-valid');
                    errorElement.style.display = 'block';
                    return false;
                }
            }
            
            field.classList.add('input-valid');
            field.classList.remove('input-invalid');
            errorElement.style.display = 'none';
            return true;
        }
        
        // Vista previa en tiempo real
        document.getElementById('courseName').addEventListener('input', function() {
            document.getElementById('previewTitle').textContent = this.value || 'Nombre de la Aventura';
            validateField(this, 3, 100, 'nameError');
        });
        
        document.getElementById('courseDesc').addEventListener('input', function() {
            document.getElementById('previewDesc').textContent = this.value || 'Descripción aparecerá aquí...';
        });
        
        document.getElementById('courseDuration').addEventListener('input', function() {
            document.getElementById('previewDuration').textContent = this.value;
            validateField(this, 1, 1000, 'durationError');
        });
        
        document.querySelector('select[name="nivel"]').addEventListener('change', function() {
            document.getElementById('previewLevel').textContent = this.value.split(' - ')[0];
        });
        
        // Barra de progreso
        function updateProgressBar() {
            const form = document.getElementById('courseForm');
            const fields = form.querySelectorAll('input[required], select[required], textarea[required]');
            let filledCount = 0;
            
            fields.forEach(field => {
                if (field.type === 'checkbox' || field.type === 'radio') {
                    if (field.checked) filledCount++;
                } else if (field.value.trim() !== '') {
                    filledCount++;
                }
            });
            
            const progress = (filledCount / fields.length) * 100;
            document.getElementById('progressBar').style.transform = `scaleX(${progress / 100})`;
        }
        
        // Observar cambios en los campos
        document.querySelectorAll('#courseForm input, #courseForm select, #courseForm textarea').forEach(field => {
            field.addEventListener('input', updateProgressBar);
            field.addEventListener('change', updateProgressBar);
        });
        
        // Modal de éxito
        function showSuccessModal() {
            const modal = document.getElementById('successModal');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeSuccessModal() {
            const modal = document.getElementById('successModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
            window.location.href = 'crear_curso.php';
        }
        
        // Crear confetti
        function createConfetti() {
            const modal = document.getElementById('successModal');
            const colors = ['#2cbaec', '#f0ae2a', '#83bf46', '#ff6b8b', '#6c63ff', '#36d1dc'];
            
            confettiInterval = setInterval(() => {
                for (let i = 0; i < 5; i++) {
                    const confetti = document.createElement('div');
                    confetti.className = 'confetti';
                    confetti.style.left = Math.random() * 100 + '%';
                    confetti.style.background = colors[Math.floor(Math.random() * colors.length)];
                    confetti.style.animationDelay = Math.random() + 's';
                    confetti.style.width = Math.random() * 10 + 5 + 'px';
                    confetti.style.height = confetti.style.width;
                    modal.appendChild(confetti);
                    
                    setTimeout(() => confetti.remove(), 2000);
                }
            }, 200);
            
            setTimeout(() => clearInterval(confettiInterval), 2000);
        }
        
        // Validación del formulario
        document.getElementById('courseForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (isSubmitting) return;
            
            const nameField = document.getElementById('courseName');
            const durationField = document.getElementById('courseDuration');
            const createButton = document.getElementById('createButton');
            
            // Validar todos los campos
            const isNameValid = validateField(nameField, 3, 100, 'nameError');
            const isDurationValid = validateField(durationField, 1, 1000, 'durationError');
            
            if (!isNameValid || !isDurationValid) {
                // Animación de shake para el formulario
                this.style.animation = 'shake 0.5s ease-out';
                setTimeout(() => this.style.animation = '', 500);
                
                // Enfocar el primer campo con error
                if (!isNameValid) nameField.focus();
                else if (!isDurationValid) durationField.focus();
                
                return;
            }
            
            // Cambiar estado del botón
            isSubmitting = true;
            createButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> CREANDO AVENTURA...';
            createButton.disabled = true;
            
            // Simular progreso
            let progress = 0;
            const progressInterval = setInterval(() => {
                progress += 10;
                createButton.style.background = `linear-gradient(90deg, var(--primary) ${progress}%, var(--accent))`;
                if (progress >= 100) {
                    clearInterval(progressInterval);
                    // Enviar formulario
                    this.submit();
                }
            }, 100);
        });
        
        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            updateCharacterCounters();
            updateProgressBar();
            
            // Actualizar contadores cuando el usuario escribe
            document.getElementById('courseName').addEventListener('input', updateCharacterCounters);
            document.getElementById('courseDesc').addEventListener('input', updateCharacterCounters);
            
            // Efecto de escritura automática en el placeholder
            const placeholders = [
                "Ej: Matemáticas Mágicas",
                "Ej: Viaje al Espacio",
                "Ej: Descubre la Naturaleza",
                "Ej: Arte y Creatividad"
            ];
            
            let currentIndex = 0;
            let charIndex = 0;
            const nameField = document.getElementById('courseName');
            const originalPlaceholder = nameField.placeholder;
            
            function typeWriter() {
                if (charIndex < placeholders[currentIndex].length) {
                    nameField.placeholder = originalPlaceholder + " - " + placeholders[currentIndex].substring(0, charIndex + 1);
                    charIndex++;
                    setTimeout(typeWriter, 50);
                } else {
                    setTimeout(() => {
                        charIndex = 0;
                        currentIndex = (currentIndex + 1) % placeholders.length;
                        setTimeout(typeWriter, 1000);
                    }, 2000);
                }
            }
            
            // Iniciar efecto si el campo está vacío
            if (!nameField.value) {
                typeWriter();
            }
            
            nameField.addEventListener('focus', () => {
                nameField.placeholder = originalPlaceholder;
            });
            
            nameField.addEventListener('blur', function() {
                if (!this.value) {
                    typeWriter();
                }
            });
        });
        
        // Efecto de partículas en el fondo
        function createParticles() {
            const particlesContainer = document.createElement('div');
            particlesContainer.style.position = 'fixed';
            particlesContainer.style.top = '0';
            particlesContainer.style.left = '0';
            particlesContainer.style.width = '100%';
            particlesContainer.style.height = '100%';
            particlesContainer.style.pointerEvents = 'none';
            particlesContainer.style.zIndex = '-1';
            document.body.appendChild(particlesContainer);
            
            for (let i = 0; i < 20; i++) {
                const particle = document.createElement('div');
                particle.style.position = 'absolute';
                particle.style.width = Math.random() * 100 + 50 + 'px';
                particle.style.height = particle.style.width;
                particle.style.background = `radial-gradient(circle, rgba(44, 186, 236, ${Math.random() * 0.1}) 0%, transparent 70%)`;
                particle.style.borderRadius = '50%';
                particle.style.top = Math.random() * 100 + '%';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.opacity = Math.random() * 0.3 + 0.1;
                particlesContainer.appendChild(particle);
            }
        }
        
        createParticles();
    </script>
</body>
</html>