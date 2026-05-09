<?php
include 'php/config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'tutor') {
    header("Location: index.php");
    exit();
}

$tutor_id = (int)$_SESSION['user_id'];
$curso_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Inicializar variables
$curso_data = null;
$success_message = '';
$error_message = '';

// Variables para estadísticas
$inscripciones = ['total' => 0];
$actividades = ['total' => 0];
$progreso = ['promedio' => 0];

if ($curso_id > 0) {
    try {
        // Verificar que el curso pertenezca al tutor
        $stmt = $conn->pdo->prepare("SELECT * FROM cursos WHERE id = :curso_id AND id_tutor = :tutor_id");
        $stmt->execute([':curso_id' => $curso_id, ':tutor_id' => $tutor_id]);
        
        if ($stmt->rowCount() == 0) {
            $error_message = "Curso no encontrado o no tienes permiso para editarlo.";
        } else {
            $curso_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Obtener estadísticas del curso
            $stmt_insc = $conn->pdo->prepare("SELECT COUNT(*) as total FROM inscripciones WHERE id_curso = :curso_id AND estado = 'activo'");
            $stmt_insc->execute([':curso_id' => $curso_id]);
            $inscripciones = $stmt_insc->fetch(PDO::FETCH_ASSOC);
            
            $stmt_act = $conn->pdo->prepare("SELECT COUNT(*) as total FROM actividades WHERE id_curso = :curso_id");
            $stmt_act->execute([':curso_id' => $curso_id]);
            $actividades = $stmt_act->fetch(PDO::FETCH_ASSOC);
            
            $stmt_prog = $conn->pdo->prepare("SELECT COALESCE(AVG(porcentaje), 0) as promedio FROM progreso WHERE id_curso = :curso_id");
            $stmt_prog->execute([':curso_id' => $curso_id]);
            $progreso = $stmt_prog->fetch(PDO::FETCH_ASSOC);
        }
    } catch(PDOException $e) {
        $error_message = "Error al cargar el curso: " . $e->getMessage();
    }
}

// Manejar actualización del curso
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['editar_curso'])) {
    $curso_id = (int)$_POST['id_curso'];
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $nivel = $_POST['nivel'] ?? 'Básico';
    $duracion_horas = (int)$_POST['duracion_horas'] ?? 0;
    
    // Validaciones
    if (empty($nombre) || strlen($nombre) < 3) {
        $error_message = "El nombre del curso debe tener al menos 3 caracteres.";
    } elseif ($duracion_horas < 1 || $duracion_horas > 1000) {
        $error_message = "La duración debe estar entre 1 y 1000 horas.";
    } else {
        try {
            // Verificar nuevamente que el curso pertenezca al tutor
            $stmt_check = $conn->pdo->prepare("SELECT id FROM cursos WHERE id = :curso_id AND id_tutor = :tutor_id");
            $stmt_check->execute([':curso_id' => $curso_id, ':tutor_id' => $tutor_id]);
            
            if ($stmt_check->rowCount() > 0) {
                $stmt_update = $conn->pdo->prepare("
                    UPDATE cursos SET 
                        nombre = :nombre,
                        descripcion = :descripcion,
                        nivel = :nivel,
                        duracion_horas = :duracion_horas
                    WHERE id = :curso_id
                ");
                
                $stmt_update->execute([
                    ':nombre' => $nombre,
                    ':descripcion' => $descripcion,
                    ':nivel' => $nivel,
                    ':duracion_horas' => $duracion_horas,
                    ':curso_id' => $curso_id
                ]);
                
                $success_message = "🎉 ¡Curso actualizado exitosamente!";
                
                // Obtener datos actualizados
                $stmt = $conn->pdo->prepare("SELECT * FROM cursos WHERE id = :curso_id");
                $stmt->execute([':curso_id' => $curso_id]);
                $curso_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
            } else {
                $error_message = "No tienes permiso para editar este curso.";
            }
        } catch(PDOException $e) {
            $error_message = "Error al actualizar: " . $e->getMessage();
        }
    }
}

// Manejar eliminación del curso
if (isset($_GET['eliminar']) && $curso_id > 0) {
    try {
        // Verificar que no haya inscripciones ni actividades
        $stmt_insc = $conn->pdo->prepare("SELECT COUNT(*) as total FROM inscripciones WHERE id_curso = :curso_id");
        $stmt_insc->execute([':curso_id' => $curso_id]);
        $row_inscripciones = $stmt_insc->fetch(PDO::FETCH_ASSOC);
        
        $stmt_act = $conn->pdo->prepare("SELECT COUNT(*) as total FROM actividades WHERE id_curso = :curso_id");
        $stmt_act->execute([':curso_id' => $curso_id]);
        $row_actividades = $stmt_act->fetch(PDO::FETCH_ASSOC);
        
        if ($row_inscripciones['total'] > 0) {
            $error_message = "No se puede eliminar: Hay alumnos inscritos en este curso.";
        } elseif ($row_actividades['total'] > 0) {
            $error_message = "No se puede eliminar: Hay actividades asociadas a este curso.";
        } else {
            $stmt_delete = $conn->pdo->prepare("DELETE FROM cursos WHERE id = :curso_id AND id_tutor = :tutor_id");
            $stmt_delete->execute([':curso_id' => $curso_id, ':tutor_id' => $tutor_id]);
            
            $success_message = "✅ ¡Curso eliminado exitosamente!";
            // Redirigir después de 2 segundos
            echo '<script>setTimeout(function() { window.location.href = "dashboard_tutor.php"; }, 2000);</script>';
        }
    } catch(PDOException $e) {
        $error_message = "Error al eliminar el curso: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $curso_id ? '✏️ Editar Curso' : '➕ Crear Curso'; ?> - D&F Mindspace</title>
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
            overflow-x: hidden;
        }
        
        .edit-container {
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
            margin-bottom: 40px;
            position: relative;
        }
        
        .header-section h1 {
            font-family: 'Nunito', sans-serif;
            font-size: 2.8rem;
            background: linear-gradient(90deg, var(--primary), var(--accent));
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
        
        .form-card {
            background: white;
            border-radius: 30px;
            padding: 50px;
            box-shadow: var(--card-shadow);
            border: 2px solid rgba(44, 186, 236, 0.1);
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
            background: linear-gradient(90deg, var(--primary), var(--accent));
        }
        
        .form-section {
            margin-bottom: 35px;
            padding-bottom: 25px;
            border-bottom: 2px dashed rgba(44, 186, 236, 0.1);
        }
        
        .section-title {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            color: var(--primary);
            font-weight: 800;
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
            box-shadow: 0 6px 15px rgba(44, 186, 236, 0.3);
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
            color: var(--danger);
            font-weight: 700;
            animation: pulse 1s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .btn-submit {
            background: linear-gradient(90deg, var(--primary), var(--accent));
            border: none;
            border-radius: 20px;
            padding: 18px 45px;
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
            margin-top: 30px;
            position: relative;
            overflow: hidden;
            letter-spacing: 0.5px;
        }
        
        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: 0.6s;
        }
        
        .btn-submit:hover::before {
            left: 100%;
        }
        
        .btn-submit:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 20px 45px rgba(44, 186, 236, 0.5);
            color: white;
        }
        
        .btn-submit:active {
            transform: translateY(-2px);
        }
        
        .btn-submit i {
            transition: all 0.3s ease;
        }
        
        .btn-submit:hover i {
            transform: rotate(90deg) scale(1.2);
        }
        
        .btn-delete {
            background: linear-gradient(90deg, var(--danger), #ff4757);
            border: none;
            border-radius: 20px;
            padding: 15px 35px;
            color: white;
            font-weight: 800;
            font-size: 1.1rem;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 10px 25px rgba(255, 107, 139, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            margin-top: 15px;
            position: relative;
            overflow: hidden;
        }
        
        .btn-delete:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 15px 35px rgba(255, 107, 139, 0.5);
            color: white;
        }
        
        .alert-custom {
            border-radius: 20px;
            border: none;
            padding: 25px;
            margin-bottom: 30px;
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
            border-left: 8px solid var(--danger);
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
        
        .course-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.05), rgba(44, 186, 236, 0.02));
            border-radius: 20px;
            padding: 20px;
            border: 2px solid rgba(44, 186, 236, 0.1);
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(44, 186, 236, 0.1);
        }
        
        .stat-number {
            font-size: 2.2rem;
            font-weight: 900;
            color: var(--primary);
            margin-bottom: 5px;
            line-height: 1;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.95rem;
            font-weight: 600;
        }
        
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
        
        /* Modal para confirmar eliminación */
        .modal-custom {
            border-radius: 30px;
            border: none;
            overflow: hidden;
        }
        
        .modal-header-custom {
            background: linear-gradient(90deg, var(--danger), #ff4757);
            color: white;
            border: none;
            padding: 25px;
        }
        
        .modal-body-custom {
            padding: 30px;
        }
        
        .modal-footer-custom {
            border: none;
            padding: 20px 30px 30px;
        }
        
        .delete-confirmation {
            text-align: center;
            padding: 20px;
        }
        
        .delete-icon {
            font-size: 4rem;
            color: var(--danger);
            margin-bottom: 20px;
            animation: shake 0.5s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        
        @media (max-width: 768px) {
            .edit-container {
                padding: 20px 15px;
            }
            
            .header-section h1 {
                font-size: 2.2rem;
            }
            
            .form-card {
                padding: 30px 20px;
            }
            
            .course-stats {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .btn-submit, .btn-delete {
                padding: 16px 30px;
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Partículas flotantes -->
    <div class="floating-particles" id="particles"></div>
    
    <div class="edit-container">
        <!-- Botón para volver -->
        <a href="dashboard_tutor.php" class="back-link animate__animated animate__fadeInLeft">
            <i class="fas fa-arrow-left"></i> Volver a Cursos
        </a>
        
        <!-- Encabezado -->
        <div class="header-section">
            <h1>
                <?php if($curso_id): ?>
                    ✏️ Editar Curso
                <?php else: ?>
                    ➕ Crear Nuevo Curso
                <?php endif; ?>
            </h1>
            <p>
                <?php if($curso_id): ?>
                    Modifica la información de tu curso. Los cambios se reflejarán inmediatamente.
                <?php else: ?>
                    Crea un nuevo curso para que tus exploradores comiencen su aventura de aprendizaje.
                <?php endif; ?>
            </p>
        </div>
        
        <!-- Mensajes de éxito/error -->
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-custom animate__animated animate__fadeInDown">
                <div class="d-flex align-items-center">
                    <i class="fas fa-check-circle fa-3x me-3"></i>
                    <div>
                        <h4 class="alert-heading mb-2 fw-bold">¡Éxito!</h4>
                        <p class="mb-0 fs-5"><?php echo $success_message; ?></p>
                        <?php if(isset($_GET['eliminar'])): ?>
                            <div class="mt-3">
                                <div class="spinner-border spinner-border-sm text-success me-2" role="status">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                                <small>Redirigiendo a la lista de cursos...</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php elseif ($error_message): ?>
            <div class="alert alert-danger alert-custom animate__animated animate__shakeX">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle fa-3x me-3"></i>
                    <div>
                        <h4 class="alert-heading mb-2 fw-bold">¡Error!</h4>
                        <p class="mb-0 fs-5"><?php echo htmlspecialchars($error_message); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Estadísticas del curso (solo si existe) -->
        <?php if($curso_data): ?>
            <?php
            // Obtener estadísticas del curso
            $sql_inscripciones = "SELECT COUNT(*) as total FROM inscripciones WHERE id_curso = '$curso_id' AND estado = 'activo'";
            $sql_actividades = "SELECT COUNT(*) as total FROM actividades WHERE id_curso = '$curso_id'";
            $sql_progreso = "SELECT AVG(porcentaje) as promedio FROM progreso WHERE id_curso = '$curso_id'";
            
            $res_insc = mysqli_query($conn, $sql_inscripciones);
            $res_act = mysqli_query($conn, $sql_actividades);
            $res_prog = mysqli_query($conn, $sql_progreso);
            
            $inscripciones = mysqli_fetch_assoc($res_insc);
            $actividades = mysqli_fetch_assoc($res_act);
            $progreso = mysqli_fetch_assoc($res_prog);
            ?>
            
            <div class="course-stats animate__animated animate__fadeInUp">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $inscripciones['total']; ?></div>
                    <div class="stat-label">Alumnos Inscritos</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $actividades['total']; ?></div>
                    <div class="stat-label">Misiones Activas</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($progreso['promedio'] ?? 0, 1); ?>%</div>
                    <div class="stat-label">Progreso Promedio</div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Formulario principal -->
        <div class="form-card animate__animated animate__fadeInUp">
            <form method="POST" id="editCursoForm">
                <input type="hidden" name="editar_curso" value="1">
                <input type="hidden" name="id_curso" value="<?php echo $curso_id ? $curso_data['id'] : ''; ?>">
                
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-info-circle"></i>
                        <span>Información del Curso</span>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">
                            <i class="fas fa-graduation-cap"></i>
                            Nombre del Curso *
                        </label>
                        <input type="text" name="nombre" class="form-control" 
                               value="<?php echo $curso_data ? htmlspecialchars($curso_data['nombre']) : ''; ?>" 
                               placeholder="Ej: Matemáticas Divertidas, Ciencias para Exploradores" 
                               required maxlength="200" id="nombreCurso">
                        <div class="character-counter" id="nombreCounter"><?php echo $curso_data ? strlen($curso_data['nombre']) : 0; ?>/200</div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">
                            <i class="fas fa-align-left"></i>
                            Descripción
                        </label>
                        <textarea name="descripcion" class="form-control" rows="4" 
                                  placeholder="Describe el curso, qué aprenderán los estudiantes, y por qué es especial..." 
                                  maxlength="1000" id="descripcionCurso"><?php echo $curso_data ? htmlspecialchars($curso_data['descripcion']) : ''; ?></textarea>
                        <div class="character-counter" id="descripcionCounter"><?php echo $curso_data ? strlen($curso_data['descripcion']) : 0; ?>/1000</div>
                    </div>
                </div>
                
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-cogs"></i>
                        <span>Configuración del Curso</span>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label">
                                <i class="fas fa-chart-line"></i>
                                Nivel *
                            </label>
                            <select name="nivel" class="form-select" required>
                                <option value="">Selecciona un nivel</option>
                                <option value="Principiante" <?php echo ($curso_data && $curso_data['nivel'] == 'Principiante') ? 'selected' : ''; ?>>👶 Principiante</option>
                                <option value="Básico" <?php echo ($curso_data && $curso_data['nivel'] == 'Básico') ? 'selected' : ''; ?>>⭐ Básico</option>
                                <option value="Intermedio" <?php echo ($curso_data && $curso_data['nivel'] == 'Intermedio') ? 'selected' : ''; ?>>⭐⭐ Intermedio</option>
                                <option value="Avanzado" <?php echo ($curso_data && $curso_data['nivel'] == 'Avanzado') ? 'selected' : ''; ?>>⭐⭐⭐ Avanzado</option>
                                <option value="Experto" <?php echo ($curso_data && $curso_data['nivel'] == 'Experto') ? 'selected' : ''; ?>>🏆 Experto</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <label class="form-label">
                                <i class="fas fa-clock"></i>
                                Duración (horas) *
                            </label>
                            <input type="number" name="duracion_horas" class="form-control" 
                                   value="<?php echo $curso_data ? $curso_data['duracion_horas'] : '20'; ?>" 
                                   min="1" max="1000" required>
                            <small class="text-muted d-block mt-2">Tiempo estimado para completar el curso</small>
                        </div>
                    </div>
                    
                    <?php if($curso_data): ?>
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label class="form-label">
                                    <i class="fas fa-calendar-plus"></i>
                                    Fecha de Creación
                                </label>
                                <input type="text" class="form-control" 
                                       value="<?php echo date('d/m/Y H:i', strtotime($curso_data['fecha_creacion'])); ?>" 
                                       readonly>
                            </div>
                            
                            <div class="col-md-6 mb-4">
                                <label class="form-label">
                                    <i class="fas fa-toggle-on"></i>
                                    Estado Actual
                                </label>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="form-check form-switch" style="transform: scale(1.3);">
                                        <input class="form-check-input" type="checkbox" 
                                               id="estadoCurso" <?php echo $curso_data['activo'] == 1 ? 'checked' : ''; ?> 
                                               disabled>
                                        <label class="form-check-label ms-3" for="estadoCurso">
                                            <?php echo $curso_data['activo'] == 1 ? 'Activo' : 'Inactivo'; ?>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Botón para guardar -->
                <button type="submit" class="btn btn-submit">
                    <i class="fas fa-save"></i> 
                    <?php echo $curso_id ? 'GUARDAR CAMBIOS' : 'CREAR CURSO'; ?>
                </button>
                
                <!-- Botón para eliminar (solo si el curso existe) -->
                <?php if($curso_id && $curso_data): ?>
                    <button type="button" class="btn btn-delete" data-bs-toggle="modal" data-bs-target="#deleteModal">
                        <i class="fas fa-trash-alt"></i> ELIMINAR CURSO
                    </button>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <!-- Modal para confirmar eliminación -->
    <?php if($curso_id && $curso_data): ?>
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-custom">
                <div class="modal-header modal-header-custom">
                    <h3 class="modal-title fw-bold"><i class="fas fa-trash me-2"></i>Confirmar Eliminación</h3>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body modal-body-custom">
                    <div class="delete-confirmation">
                        <div class="delete-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h4 class="fw-bold mb-3">¿Eliminar "<?php echo htmlspecialchars($curso_data['nombre']); ?>"?</h4>
                        <p class="text-muted">Esta acción eliminará permanentemente el curso y toda su información asociada.</p>
                        
                        <div class="alert alert-danger mt-3">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <strong>¡Advertencia Crítica!</strong>
                            <ul class="mt-2 mb-0 ps-3">
                                <li>Se eliminarán todas las actividades del curso</li>
                                <li>Se perderá el historial de progreso</li>
                                <li>Los alumnos serán desinscritos automáticamente</li>
                                <li>Esta acción <strong>NO</strong> se puede deshacer</li>
                            </ul>
                        </div>
                        
                        <div class="mt-4 p-3 bg-light rounded">
                            <p class="mb-2"><strong>Resumen del curso:</strong></p>
                            <div class="d-flex justify-content-between">
                                <span>Alumnos inscritos:</span>
                                <strong><?php echo $inscripciones['total']; ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Misiones activas:</span>
                                <strong><?php echo $actividades['total']; ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer modal-footer-custom">
                    <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <a href="editar_curso.php?id=<?php echo $curso_id; ?>&eliminar=1" class="btn btn-delete">
                        <i class="fas fa-trash me-2"></i>Sí, Eliminar Curso
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Crear partículas flotantes
        function crearParticulas() {
            const container = document.getElementById('particles');
            for (let i = 0; i < 25; i++) {
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
        
        // Contadores de caracteres
        function setupCharacterCounters() {
            const nombreInput = document.getElementById('nombreCurso');
            const descripcionInput = document.getElementById('descripcionCurso');
            const nombreCounter = document.getElementById('nombreCounter');
            const descripcionCounter = document.getElementById('descripcionCounter');
            
            if (nombreInput && nombreCounter) {
                nombreInput.addEventListener('input', function() {
                    const length = this.value.length;
                    nombreCounter.textContent = `${length}/200`;
                    
                    nombreCounter.className = 'character-counter';
                    if (length > 180) {
                        nombreCounter.classList.add('warning');
                    }
                    if (length > 195) {
                        nombreCounter.classList.add('danger');
                    }
                });
            }
            
            if (descripcionInput && descripcionCounter) {
                descripcionInput.addEventListener('input', function() {
                    const length = this.value.length;
                    descripcionCounter.textContent = `${length}/1000`;
                    
                    descripcionCounter.className = 'character-counter';
                    if (length > 800) {
                        descripcionCounter.classList.add('warning');
                    }
                    if (length > 950) {
                        descripcionCounter.classList.add('danger');
                    }
                });
            }
        }
        
        // Validar formulario
        function setupFormValidation() {
            const form = document.getElementById('editCursoForm');
            if (!form) return;
            
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const nombre = form.querySelector('input[name="nombre"]').value.trim();
                const duracion = parseInt(form.querySelector('input[name="duracion_horas"]').value);
                const nivel = form.querySelector('select[name="nivel"]').value;
                
                let errors = [];
                
                if (nombre.length < 3) {
                    errors.push('El nombre del curso debe tener al menos 3 caracteres');
                }
                
                if (duracion < 1 || duracion > 1000) {
                    errors.push('La duración debe estar entre 1 y 1000 horas');
                }
                
                if (!nivel) {
                    errors.push('Debes seleccionar un nivel');
                }
                
                if (errors.length > 0) {
                    showNotification(errors.join(', '), 'warning');
                    return;
                }
                
                // Cambiar texto del botón
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>PROCESANDO...';
                submitBtn.disabled = true;
                
                // Enviar formulario
                this.submit();
            });
        }
        
        // Mostrar notificación
        function showNotification(message, type = 'warning') {
            // Crear elemento de notificación
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-custom animate__animated animate__fadeInDown`;
            notification.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} fa-3x me-3 text-${type}"></i>
                    <div>
                        <h4 class="alert-heading mb-2 fw-bold">${type === 'success' ? '¡Éxito!' : '¡Atención!'}</h4>
                        <p class="mb-0 fs-5">${message}</p>
                    </div>
                </div>
            `;
            
            // Insertar después del botón de volver
            const backLink = document.querySelector('.back-link');
            if (backLink && backLink.parentNode) {
                backLink.parentNode.insertBefore(notification, backLink.nextSibling);
            }
            
            // Auto-remover después de 5 segundos
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.classList.add('animate__fadeOutUp');
                    setTimeout(() => notification.remove(), 500);
                }
            }, 5000);
        }
        
        // Efecto de escritura en el placeholder
        function typeWriterEffect() {
            const input = document.getElementById('nombreCurso');
            if (!input || input.value) return;
            
            const placeholders = [
                "Ej: Matemáticas Divertidas",
                "Ej: Ciencias para Exploradores",
                "Ej: Aventura en Español",
                "Ej: Historia Viva"
            ];
            
            let currentIndex = 0;
            let charIndex = 0;
            let isDeleting = false;
            
            function type() {
                const currentText = placeholders[currentIndex];
                
                if (isDeleting) {
                    input.placeholder = currentText.substring(0, charIndex - 1);
                    charIndex--;
                } else {
                    input.placeholder = currentText.substring(0, charIndex + 1);
                    charIndex++;
                }
                
                let speed = isDeleting ? 50 : 100;
                
                if (!isDeleting && charIndex === currentText.length) {
                    speed = 2000;
                    isDeleting = true;
                } else if (isDeleting && charIndex === 0) {
                    isDeleting = false;
                    currentIndex = (currentIndex + 1) % placeholders.length;
                    speed = 500;
                }
                
                setTimeout(type, speed);
            }
            
            type();
        }
        
        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            crearParticulas();
            setupCharacterCounters();
            setupFormValidation();
            typeWriterEffect();
            
            // Configurar evento focus en nombre
            const nombreInput = document.getElementById('nombreCurso');
            if (nombreInput && !nombreInput.value) {
                nombreInput.addEventListener('focus', function() {
                    this.placeholder = "Ej: Matemáticas Divertidas, Ciencias para Exploradores";
                });
                
                nombreInput.addEventListener('blur', function() {
                    if (!this.value) {
                        typeWriterEffect();
                    }
                });
            }
        });
    </script>
</body>
</html>