<?php
// configuracion_cuenta.php - CORREGIDO PARA POSTGRESQL
include 'php/config.php';
session_start();

// Seguridad: Solo tutores
if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'tutor') {
    header("Location: index.php");
    exit();
}

$tutor_id = $_SESSION['user_id'];
$tutor_nombre = $_SESSION['nombre'];
$tutor_email = $_SESSION['email'] ?? '';

// Variables para mensajes
$success = '';
$error = '';
$warning = '';

// Obtener datos actuales del tutor usando PDO
try {
    $stmt = $conn->pdo->prepare("SELECT * FROM usuarios WHERE id = :id AND tipo = 'tutor'");
    $stmt->execute([':id' => $tutor_id]);
    $tutor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tutor) {
        die("Tutor no encontrado.");
    }
} catch(PDOException $e) {
    die("Error al cargar datos: " . $e->getMessage());
}

// Procesar actualización de datos generales
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_datos'])) {
    $nuevo_nombre = trim($_POST['nombre'] ?? '');
    $nuevo_email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
    
    // Validaciones
    if (empty($nuevo_nombre)) {
        $error = "El nombre es obligatorio";
    } elseif (!filter_var($nuevo_email, FILTER_VALIDATE_EMAIL)) {
        $error = "El email no es válido";
    } else {
        try {
            // Verificar si el email ya existe (excluyendo al tutor actual)
            $check_email = $conn->pdo->prepare("SELECT id FROM usuarios WHERE email = :email AND id != :id");
            $check_email->execute([':email' => $nuevo_email, ':id' => $tutor_id]);
            
            if ($check_email->rowCount() > 0) {
                $error = "Este email ya está registrado por otro usuario";
            } else {
                // Preparar consulta de actualización
                $update_query = $conn->pdo->prepare("
                    UPDATE usuarios SET 
                        nombre = :nombre,
                        email = :email,
                        telefono = :telefono,
                        fecha_nacimiento = :fecha_nacimiento
                    WHERE id = :id
                ");
                
                $update_query->execute([
                    ':nombre' => $nuevo_nombre,
                    ':email' => $nuevo_email,
                    ':telefono' => $telefono,
                    ':fecha_nacimiento' => $fecha_nacimiento,
                    ':id' => $tutor_id
                ]);
                
                // Actualizar sesión
                $_SESSION['nombre'] = $nuevo_nombre;
                $_SESSION['email'] = $nuevo_email;
                $tutor_nombre = $nuevo_nombre;
                $tutor_email = $nuevo_email;
                
                // Actualizar datos locales
                $tutor['nombre'] = $nuevo_nombre;
                $tutor['email'] = $nuevo_email;
                $tutor['telefono'] = $telefono;
                $tutor['fecha_nacimiento'] = $fecha_nacimiento;
                
                $success = "Datos actualizados correctamente";
            }
        } catch(PDOException $e) {
            $error = "Error al actualizar los datos: " . $e->getMessage();
        }
    }
}

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cambiar_password'])) {
    $password_actual = $_POST['password_actual'] ?? '';
    $password_nueva = $_POST['password_nueva'] ?? '';
    $password_confirmar = $_POST['password_confirmar'] ?? '';
    
    // Validaciones
    if (empty($password_actual) || empty($password_nueva) || empty($password_confirmar)) {
        $error = "Todos los campos de contraseña son obligatorios";
    } elseif ($password_nueva !== $password_confirmar) {
        $error = "Las nuevas contraseñas no coinciden";
    } elseif (strlen($password_nueva) < 6) {
        $error = "La nueva contraseña debe tener al menos 6 caracteres";
    } else {
        try {
            // Verificar contraseña actual
            $check_password = $conn->pdo->prepare("SELECT password FROM usuarios WHERE id = :id");
            $check_password->execute([':id' => $tutor_id]);
            $user_data = $check_password->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($password_actual, $user_data['password'])) {
                // Encriptar nueva contraseña
                $password_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
                
                // Actualizar contraseña
                $update_password = $conn->pdo->prepare("UPDATE usuarios SET password = :password WHERE id = :id");
                $update_password->execute([':password' => $password_hash, ':id' => $tutor_id]);
                
                $success = "Contraseña cambiada exitosamente";
            } else {
                $error = "La contraseña actual es incorrecta";
            }
        } catch(PDOException $e) {
            $error = "Error al cambiar la contraseña: " . $e->getMessage();
        }
    }
}

// Procesar eliminación de cuenta
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar_cuenta'])) {
    $confirmacion = $_POST['confirmacion'] ?? '';
    
    if ($confirmacion === 'ELIMINAR') {
        try {
            // Verificar si el tutor tiene cursos activos
            $check_cursos = $conn->pdo->prepare("SELECT COUNT(*) as total FROM cursos WHERE id_tutor = :id_tutor AND activo = TRUE");
            $check_cursos->execute([':id_tutor' => $tutor_id]);
            $cursos_activos = $check_cursos->fetch(PDO::FETCH_ASSOC)['total'];
            
            if ($cursos_activos > 0) {
                $error = "No puedes eliminar tu cuenta mientras tengas cursos activos. Primero inactívalos o transfiérelos.";
            } else {
                // Marcar cuenta como inactiva (no eliminar físicamente)
                $deactivate_query = $conn->pdo->prepare("UPDATE usuarios SET activo = FALSE WHERE id = :id");
                $deactivate_query->execute([':id' => $tutor_id]);
                
                session_destroy();
                header("Location: index.php?account_deleted=1");
                exit();
            }
        } catch(PDOException $e) {
            $error = "Error al eliminar la cuenta: " . $e->getMessage();
        }
    } else {
        $error = "Debes escribir 'ELIMINAR' para confirmar la eliminación";
    }
}

// Calcular fecha de registro (si existe)
$fecha_registro = $tutor['fecha_registro'] ?? null;
$fecha_formateada = $fecha_registro ? date('d/m/Y', strtotime($fecha_registro)) : 'No disponible';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Cuenta - D&F Mindspace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Poppins:wght@300;400;600;700&family=Fredoka+One&display=swap" rel="stylesheet">
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
        
        body {
            background: linear-gradient(135deg, #f0f9fd 0%, #e6f7fc 100%);
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            padding: 30px;
        }
        
        .config-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .config-header {
            background: linear-gradient(90deg, rgba(44, 186, 236, 0.9), rgba(131, 191, 70, 0.9));
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 30px;
            margin-bottom: 0;
        }
        
        .config-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            margin-bottom: 30px;
            border: 2px solid rgba(44, 186, 236, 0.1);
        }
        
        .section-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            border: 2px solid rgba(44, 186, 236, 0.1);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 15px;
            border-bottom: 3px solid rgba(44, 186, 236, 0.1);
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 8px;
            font-size: 1rem;
        }
        
        .form-control-custom {
            border: 2px solid rgba(44, 186, 236, 0.2);
            border-radius: 12px;
            padding: 12px 15px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control-custom:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(44, 186, 236, 0.25);
        }
        
        .btn-save {
            background: linear-gradient(90deg, var(--accent), #6aab39);
            border: none;
            border-radius: 12px;
            padding: 12px 30px;
            color: white;
            font-weight: 700;
            transition: all 0.3s ease;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(131, 191, 70, 0.3);
        }
        
        .btn-change-password {
            background: linear-gradient(90deg, var(--secondary), #f5c15d);
            border: none;
            border-radius: 12px;
            padding: 12px 30px;
            color: white;
            font-weight: 700;
            transition: all 0.3s ease;
        }
        
        .btn-change-password:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(240, 174, 42, 0.3);
        }
        
        .btn-delete {
            background: linear-gradient(90deg, var(--danger), #ff4d6d);
            border: none;
            border-radius: 12px;
            padding: 12px 30px;
            color: white;
            font-weight: 700;
            transition: all 0.3s ease;
        }
        
        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(255, 107, 139, 0.3);
        }
        
        .alert-custom {
            border-radius: 12px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 25px;
            animation: slideInDown 0.5s ease-out;
        }
        
        .alert-custom.alert-success {
            background: linear-gradient(135deg, rgba(131, 191, 70, 0.1), rgba(131, 191, 70, 0.05));
            border-left: 5px solid var(--accent);
            color: #155724;
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
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
            padding: 10px 20px;
            background: rgba(44, 186, 236, 0.1);
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            background: var(--primary);
            color: white;
            transform: translateX(-5px);
        }
        
        .password-strength {
            margin-top: 10px;
        }
        
        .strength-bar {
            height: 5px;
            border-radius: 3px;
            background: #e0e0e0;
            overflow: hidden;
            margin-bottom: 5px;
        }
        
        .strength-fill {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease, background-color 0.3s ease;
        }
        
        .strength-text {
            font-size: 0.85rem;
            color: #666;
        }
        
        .info-box {
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.05), rgba(44, 186, 236, 0.02));
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            border-left: 4px solid var(--primary);
        }
        
        .danger-zone {
            background: linear-gradient(135deg, rgba(255, 107, 139, 0.05), rgba(255, 107, 139, 0.02));
            border: 2px solid rgba(255, 107, 139, 0.1);
        }
        
        .danger-zone .section-title {
            color: var(--danger);
        }
        
        /* Modal de confirmación personalizado */
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
        
        .user-avatar-large {
            width: 100px;
            height: 100px;
            border-radius: 20px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: bold;
            box-shadow: 0 10px 25px rgba(44, 186, 236, 0.3);
            margin: 0 auto 20px;
        }
        
        .account-info {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .account-info h4 {
            color: var(--primary);
            font-weight: 800;
            margin-bottom: 5px;
        }
        
        .account-info p {
            color: #666;
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    
    <!-- Main Content -->
    <div class="animate__animated animate__fadeIn">
        <!-- Botón para volver -->
        <a href="dashboard_tutor.php" class="back-link animate__animated animate__fadeInLeft">
            <i class="fas fa-arrow-left"></i> Volver al Dashboard
        </a>
        
        <div class="config-container">
            <!-- Header -->
            <div class="config-card">
                <div class="config-header">
                    <h1 class="fw-bold mb-3"><i class="fas fa-user-cog me-3"></i>Configuración de Cuenta</h1>
                    <p class="mb-0 opacity-75">Gestiona tu información personal, contraseña y preferencias</p>
                </div>
                
                <div class="p-5">
                    <!-- Información de la cuenta -->
                    <div class="account-info">
                        <div class="user-avatar-large">
                            <?php echo strtoupper(substr($tutor_nombre, 0, 1)); ?>
                        </div>
                        <h4>Prof. <?php echo htmlspecialchars($tutor_nombre); ?></h4>
                        <p><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($tutor_email); ?></p>
                        <p class="text-muted">
                            <i class="fas fa-calendar me-2"></i>
                            Miembro desde: <?php echo date('d/m/Y', strtotime($tutor['fecha_registro'])); ?>
                        </p>
                    </div>
                    
                    <!-- Mensajes -->
                    <?php if($success): ?>
                        <div class="alert alert-success alert-custom animate__animated animate__bounceIn">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($error): ?>
                        <div class="alert alert-danger alert-custom animate__animated animate__shakeX">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Datos Generales -->
                    <div class="section-card">
                        <h3 class="section-title">
                            <i class="fas fa-user-edit"></i>
                            Datos Personales
                        </h3>
                        
                        <form method="POST" action="" id="datosForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Nombre Completo *</label>
                                        <input type="text" class="form-control-custom" name="nombre" 
                                               value="<?php echo htmlspecialchars($tutor['nombre']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Email *</label>
                                        <input type="email" class="form-control-custom" name="email" 
                                               value="<?php echo htmlspecialchars($tutor['email']); ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Teléfono</label>
                                        <input type="tel" class="form-control-custom" name="telefono" 
                                               value="<?php echo htmlspecialchars($tutor['telefono'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Fecha de Nacimiento</label>
                                        <input type="date" class="form-control-custom" name="fecha_nacimiento" 
                                               value="<?php echo htmlspecialchars($tutor['fecha_nacimiento'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="info-box">
                                <i class="fas fa-info-circle text-primary me-2"></i>
                                <small>Los campos marcados con * son obligatorios. Tu email se utilizará para notificaciones y recuperación de cuenta.</small>
                            </div>
                            
                            <div class="text-end mt-4">
                                <button type="button" class="btn-save" data-bs-toggle="modal" data-bs-target="#saveModal">
                                    <i class="fas fa-save me-2"></i>Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Cambio de Contraseña -->
                    <div class="section-card">
                        <h3 class="section-title">
                            <i class="fas fa-key"></i>
                            Seguridad y Contraseña
                        </h3>
                        
                        <form method="POST" action="" id="passwordForm">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label class="form-label">Contraseña Actual *</label>
                                        <input type="password" class="form-control-custom" 
                                               name="password_actual" id="password_actual" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Nueva Contraseña *</label>
                                        <input type="password" class="form-control-custom" 
                                               name="password_nueva" id="password_nueva" required>
                                        <div class="password-strength">
                                            <div class="strength-bar">
                                                <div class="strength-fill" id="strengthFill"></div>
                                            </div>
                                            <div class="strength-text" id="strengthText">Fuerza de la contraseña</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Confirmar Nueva Contraseña *</label>
                                        <input type="password" class="form-control-custom" 
                                               name="password_confirmar" id="password_confirmar" required>
                                        <div class="text-muted mt-2" id="passwordMatch"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="info-box">
                                <i class="fas fa-shield-alt text-primary me-2"></i>
                                <small>Tu contraseña debe tener al menos 6 caracteres. Recomendamos usar una combinación de letras, números y símbolos para mayor seguridad.</small>
                            </div>
                            
                            <div class="text-end mt-4">
                                <button type="button" class="btn-change-password" data-bs-toggle="modal" data-bs-target="#passwordModal">
                                    <i class="fas fa-key me-2"></i>Cambiar Contraseña
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Zona de Peligro -->
                    <div class="section-card danger-zone">
                        <h3 class="section-title">
                            <i class="fas fa-exclamation-triangle"></i>
                            Zona de Peligro
                        </h3>
                        
                        <div class="info-box">
                            <i class="fas fa-exclamation-circle text-danger me-2"></i>
                            <small class="text-danger"><strong>¡Advertencia!</strong> Las acciones en esta sección son permanentes y no se pueden deshacer.</small>
                        </div>
                        
                        <div class="mt-4">
                            <h5 class="text-danger mb-3"><i class="fas fa-user-slash me-2"></i>Eliminar Cuenta</h5>
                            <p class="text-muted mb-4">
                                Al eliminar tu cuenta:
                            </p>
                            <ul class="text-muted mb-4">
                                <li>Tu perfil será desactivado permanentemente</li>
                                <li>No podrás acceder a tus cursos ni actividades</li>
                                <li>Los datos de tus alumnos se mantendrán en el sistema</li>
                                <li>Esta acción no se puede deshacer</li>
                            </ul>
                            
                            <form method="POST" action="" id="deleteForm">
                                <div class="form-group">
                                    <label class="form-label text-danger">
                                        Para confirmar, escribe <strong>ELIMINAR</strong> en el siguiente campo:
                                    </label>
                                    <input type="text" class="form-control-custom" name="confirmacion" 
                                           placeholder="Escribe ELIMINAR aquí" required>
                                </div>
                                
                                <div class="text-end mt-4">
                                    <button type="button" class="btn-delete" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                        <i class="fas fa-trash-alt me-2"></i>Eliminar Mi Cuenta
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de confirmación para guardar datos -->
    <div class="modal fade modal-custom" id="saveModal" tabindex="-1" aria-labelledby="saveModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="saveModalLabel">
                        <i class="fas fa-save"></i>
                        Confirmar Cambios
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-4">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>¿Estás seguro de guardar los cambios?</strong>
                    </div>
                    
                    <p>Se actualizarán tus datos personales en el sistema.</p>
                    
                    <div class="mt-4">
                        <p class="mb-2"><strong>Cambios a realizar:</strong></p>
                        <ul class="list-group list-group-flush" id="changesList">
                            <!-- Los cambios se insertarán aquí con JavaScript -->
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <button type="submit" form="datosForm" name="actualizar_datos" class="btn btn-confirm">
                        <i class="fas fa-save me-2"></i>Sí, Guardar Cambios
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de confirmación para cambiar contraseña -->
    <div class="modal fade modal-custom" id="passwordModal" tabindex="-1" aria-labelledby="passwordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="passwordModalLabel">
                        <i class="fas fa-key"></i>
                        Confirmar Cambio de Contraseña
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning mb-4">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>¡Importante!</strong> Al cambiar tu contraseña:
                    </div>
                    
                    <ul class="mb-4">
                        <li>Tu sesión actual permanecerá activa</li>
                        <li>Deberás usar la nueva contraseña para futuros inicios de sesión</li>
                        <li>Se recomienda cerrar sesión en todos los dispositivos</li>
                    </ul>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-shield-alt me-2"></i>
                        <strong>Seguridad:</strong> Asegúrate de que tu nueva contraseña sea segura y única.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <button type="submit" form="passwordForm" name="cambiar_password" class="btn btn-confirm">
                        <i class="fas fa-key me-2"></i>Sí, Cambiar Contraseña
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de confirmación para eliminar cuenta -->
    <div class="modal fade modal-custom" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(90deg, rgba(255, 107, 139, 0.9), rgba(255, 77, 109, 0.9));">
                    <h5 class="modal-title" id="deleteModalLabel">
                        <i class="fas fa-exclamation-triangle"></i>
                        Confirmar Eliminación de Cuenta
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger mb-4">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>¡ATENCIÓN! Esta acción es irreversible</strong>
                    </div>
                    
                    <p>¿Estás absolutamente seguro de que deseas eliminar tu cuenta?</p>
                    
                    <div class="mt-4">
                        <p class="mb-2"><strong>Consecuencias:</strong></p>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex">
                                <i class="fas fa-times text-danger me-2 mt-1"></i>
                                <span>Perderás acceso a todos tus cursos</span>
                            </li>
                            <li class="list-group-item d-flex">
                                <i class="fas fa-times text-danger me-2 mt-1"></i>
                                <span>No podrás recuperar tus datos</span>
                            </li>
                            <li class="list-group-item d-flex">
                                <i class="fas fa-times text-danger me-2 mt-1"></i>
                                <span>Tus alumnos perderán su tutor</span>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="alert alert-warning mt-4">
                        <i class="fas fa-lightbulb me-2"></i>
                        <strong>Sugerencia:</strong> Considera inactivar tu cuenta temporalmente en lugar de eliminarla permanentemente.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <button type="submit" form="deleteForm" name="eliminar_cuenta" class="btn btn-confirm" style="background: linear-gradient(90deg, var(--danger), #ff4d6d);">
                        <i class="fas fa-trash-alt me-2"></i>Sí, Eliminar Mi Cuenta
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animación de entrada
            const configCard = document.querySelector('.config-card');
            configCard.style.opacity = '0';
            configCard.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                configCard.style.transition = 'all 0.6s ease-out';
                configCard.style.opacity = '1';
                configCard.style.transform = 'translateY(0)';
            }, 300);
            
            // Validación de fuerza de contraseña
            const passwordInput = document.getElementById('password_nueva');
            const confirmInput = document.getElementById('password_confirmar');
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            const passwordMatch = document.getElementById('passwordMatch');
            
            function checkPasswordStrength(password) {
                let strength = 0;
                
                // Longitud mínima
                if (password.length >= 6) strength += 1;
                if (password.length >= 8) strength += 1;
                
                // Contiene números
                if (/\d/.test(password)) strength += 1;
                
                // Contiene letras minúsculas y mayúsculas
                if (/[a-z]/.test(password)) strength += 1;
                if (/[A-Z]/.test(password)) strength += 1;
                
                // Contiene caracteres especiales
                if (/[^a-zA-Z0-9]/.test(password)) strength += 1;
                
                // Actualizar visualización
                let percentage = (strength / 6) * 100;
                let color, text;
                
                if (strength <= 2) {
                    color = '#ff6b8b'; // Rojo
                    text = 'Débil';
                } else if (strength <= 4) {
                    color = '#f0ae2a'; // Naranja
                    text = 'Moderada';
                } else {
                    color = '#83bf46'; // Verde
                    text = 'Fuerte';
                }
                
                strengthFill.style.width = percentage + '%';
                strengthFill.style.backgroundColor = color;
                strengthText.textContent = 'Fuerza: ' + text;
                strengthText.style.color = color;
            }
            
            function checkPasswordMatch() {
                const password = passwordInput.value;
                const confirm = confirmInput.value;
                
                if (confirm === '') {
                    passwordMatch.textContent = '';
                    confirmInput.style.borderColor = '';
                } else if (password === confirm) {
                    passwordMatch.innerHTML = '<i class="fas fa-check-circle text-success me-2"></i>Las contraseñas coinciden';
                    confirmInput.style.borderColor = '#83bf46';
                } else {
                    passwordMatch.innerHTML = '<i class="fas fa-times-circle text-danger me-2"></i>Las contraseñas no coinciden';
                    confirmInput.style.borderColor = '#ff6b8b';
                }
            }
            
            passwordInput.addEventListener('input', function() {
                checkPasswordStrength(this.value);
                checkPasswordMatch();
            });
            
            confirmInput.addEventListener('input', checkPasswordMatch);
            
            // Configurar modal de guardar datos
            const saveModal = document.getElementById('saveModal');
            const changesList = document.getElementById('changesList');
            const datosForm = document.getElementById('datosForm');
            
            // Datos originales
            const originalData = {
                nombre: '<?php echo addslashes($tutor['nombre']); ?>',
                email: '<?php echo addslashes($tutor['email']); ?>',
                telefono: '<?php echo addslashes($tutor['telefono'] ?? ''); ?>',
                fecha_nacimiento: '<?php echo addslashes($tutor['fecha_nacimiento'] ?? ''); ?>'
            };
            
            // Cuando se va a mostrar el modal de guardar
            saveModal.addEventListener('show.bs.modal', function() {
                // Obtener valores actuales del formulario
                const currentData = {
                    nombre: datosForm.querySelector('input[name="nombre"]').value,
                    email: datosForm.querySelector('input[name="email"]').value,
                    telefono: datosForm.querySelector('input[name="telefono"]').value,
                    fecha_nacimiento: datosForm.querySelector('input[name="fecha_nacimiento"]').value
                };
                
                // Limpiar lista de cambios
                changesList.innerHTML = '';
                
                // Comparar y mostrar cambios
                let hasChanges = false;
                
                for (const field in currentData) {
                    if (currentData[field] !== originalData[field]) {
                        hasChanges = true;
                        
                        let original = originalData[field] || '(vacío)';
                        let current = currentData[field] || '(vacío)';
                        
                        // Formatear fechas
                        if (field === 'fecha_nacimiento' && current) {
                            current = new Date(current).toLocaleDateString('es-ES');
                            original = original ? new Date(original).toLocaleDateString('es-ES') : '(vacío)';
                        }
                        
                        const listItem = document.createElement('li');
                        listItem.className = 'list-group-item d-flex justify-content-between';
                        
                        let fieldName = '';
                        switch(field) {
                            case 'nombre': fieldName = 'Nombre'; break;
                            case 'email': fieldName = 'Email'; break;
                            case 'telefono': fieldName = 'Teléfono'; break;
                            case 'fecha_nacimiento': fieldName = 'Fecha de Nacimiento'; break;
                        }
                        
                        listItem.innerHTML = `
                            <span>${fieldName}:</span>
                            <div class="text-end">
                                <div class="text-danger"><small><s>${original}</s></small></div>
                                <div class="text-success fw-bold">${current}</div>
                            </div>
                        `;
                        
                        changesList.appendChild(listItem);
                    }
                }
                
                // Si no hay cambios, mostrar mensaje
                if (!hasChanges) {
                    changesList.innerHTML = `
                        <li class="list-group-item text-center text-muted">
                            <i class="fas fa-info-circle me-2"></i>
                            No se detectaron cambios en los datos
                        </li>
                    `;
                }
            });
            
            // Validación de formulario de contraseña
            const passwordForm = document.getElementById('passwordForm');
            
            passwordForm.addEventListener('submit', function(e) {
                const passwordActual = this.querySelector('input[name="password_actual"]').value;
                const passwordNueva = this.querySelector('input[name="password_nueva"]').value;
                const passwordConfirmar = this.querySelector('input[name="password_confirmar"]').value;
                
                if (!passwordActual || !passwordNueva || !passwordConfirmar) {
                    e.preventDefault();
                    showAlert('Por favor completa todos los campos de contraseña', 'error');
                    return false;
                }
                
                if (passwordNueva.length < 6) {
                    e.preventDefault();
                    showAlert('La nueva contraseña debe tener al menos 6 caracteres', 'error');
                    return false;
                }
                
                if (passwordNueva !== passwordConfirmar) {
                    e.preventDefault();
                    showAlert('Las nuevas contraseñas no coinciden', 'error');
                    return false;
                }
                
                return true;
            });
            
            // Validación de formulario de eliminación
            const deleteForm = document.getElementById('deleteForm');
            
            deleteForm.addEventListener('submit', function(e) {
                const confirmacion = this.querySelector('input[name="confirmacion"]').value;
                
                if (confirmacion !== 'ELIMINAR') {
                    e.preventDefault();
                    showAlert('Debes escribir EXACTAMENTE "ELIMINAR" para confirmar', 'error');
                    return false;
                }
                
                return true;
            });
            
            function validateEmail(email) {
                const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
                return re.test(String(email).toLowerCase());
            }
            
            function showAlert(message, type = 'error') {
                const alertHtml = `
                    <div class="alert-custom alert-${type === 'error' ? 'danger' : 'success'} animate__animated animate__shakeX">
                        <i class="fas fa-${type === 'error' ? 'exclamation-triangle' : 'check-circle'} me-2"></i>
                        ${message}
                    </div>
                `;
                
                const configCard = document.querySelector('.config-card .p-5');
                configCard.insertAdjacentHTML('afterbegin', alertHtml);
                
                setTimeout(() => {
                    const alert = configCard.querySelector('.alert-custom');
                    if (alert) {
                        alert.remove();
                    }
                }, 5000);
            }
            
            // Validar email en tiempo real
            const emailInput = datosForm.querySelector('input[name="email"]');
            
            emailInput.addEventListener('blur', function() {
                const email = this.value.trim();
                
                if (email === originalData.email) {
                    return; // No cambió el email
                }
                
                if (!validateEmail(email)) {
                    showAlert('Por favor ingresa un email válido', 'error');
                }
            });
            
            // Efectos visuales
            const inputs = document.querySelectorAll('.form-control-custom');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'translateY(-2px)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'translateY(0)';
                });
            });
            
            // Validar formulario de datos antes de abrir modal
            datosForm.addEventListener('submit', function(e) {
                // Esta función ya no previene el envío porque el modal lo maneja
                // Solo validamos básicamente
                const nombre = this.querySelector('input[name="nombre"]').value.trim();
                const email = this.querySelector('input[name="email"]').value.trim();
                
                if (!nombre) {
                    e.preventDefault();
                    showAlert('Por favor ingresa tu nombre', 'error');
                    return false;
                }
                
                if (!validateEmail(email)) {
                    e.preventDefault();
                    showAlert('Por favor ingresa un email válido', 'error');
                    return false;
                }
                
                return true;
            });
        });
    </script>
</body>
</html>