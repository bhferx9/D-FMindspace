<?php
include 'php/config.php';

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: index.php");
    exit();
}

$nombre = trim($_POST['nombre'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$telefono = trim($_POST['telefono'] ?? '');
$fecha_nac = $_POST['fecha_nac'] ?? null;
$tipo = $_POST['tipo'] ?? 'alumno';

$errores = [];

// Validaciones básicas
if (empty($nombre) || strlen($nombre) < 2) {
    $errores[] = "El nombre debe tener al menos 2 caracteres.";
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errores[] = "Correo electrónico inválido.";
}
if (empty($password) || strlen($password) < 6) {
    $errores[] = "La contraseña debe tener al menos 6 caracteres.";
}
if (!empty($telefono) && !preg_match('/^\d{10}$/', $telefono)) {
    $errores[] = "El teléfono debe tener 10 dígitos.";
}

if (!empty($errores)) {
    die("<script>alert('Error:\\n" . implode("\\n", $errores) . "'); window.history.back();</script>");
}

try {
    // Verificar si el email ya existe
    $stmt = $conn->pdo->prepare("SELECT id FROM usuarios WHERE email = :email");
    $stmt->execute([':email' => $email]);
    
    if ($stmt->rowCount() > 0) {
        die("<script>alert('Error: Este correo ya está registrado.'); window.history.back();</script>");
    }
    
    $id_hijo_vinculado = null;
    
    // Si es padre, validar credenciales del HIJO
    if ($tipo == 'padre') {
        $h_email = trim($_POST['hijo_email'] ?? '');
        $h_pass = $_POST['hijo_password'] ?? '';
        
        if (empty($h_email) || empty($h_pass)) {
            die("<script>alert('Error: Debes ingresar el correo y contraseña del hijo para vincular.'); window.history.back();</script>");
        }
        
        // Buscar al hijo
        $stmt_hijo = $conn->pdo->prepare("SELECT id, password FROM usuarios WHERE email = :email AND tipo = 'alumno'");
        $stmt_hijo->execute([':email' => $h_email]);
        
        if ($stmt_hijo->rowCount() == 0) {
            die("<script>alert('Error: No encontramos ningún alumno con ese correo.'); window.history.back();</script>");
        }
        
        $hijo = $stmt_hijo->fetch(PDO::FETCH_ASSOC);
        
        // Verificar contraseña del hijo
        if (!password_verify($h_pass, $hijo['password'])) {
            die("<script>alert('Error: La contraseña del hijo es incorrecta.'); window.history.back();</script>");
        }
        
        $id_hijo_vinculado = $hijo['id'];
    }
    
    // Encriptar contraseña
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Insertar nuevo usuario
    $sql = "INSERT INTO usuarios (nombre, email, password, tipo, telefono, fecha_nacimiento, id_hijo_vinculado, fecha_registro) 
            VALUES (:nombre, :email, :password, :tipo, :telefono, :fecha_nac, :id_hijo_vinculado, CURRENT_TIMESTAMP)";
    
    $stmt_insert = $conn->pdo->prepare($sql);
    $stmt_insert->execute([
        ':nombre' => $nombre,
        ':email' => $email,
        ':password' => $password_hash,
        ':tipo' => $tipo,
        ':telefono' => $telefono ?: null,
        ':fecha_nac' => $fecha_nac ?: null,
        ':id_hijo_vinculado' => $id_hijo_vinculado
    ]);
    
    echo "<script>
            alert('🎉 ¡Registro exitoso! Bienvenido a D&F Mindspace.');
            window.location='index.php';
          </script>";
    
} catch(PDOException $e) {
    echo "<script>
            alert('❌ Error al registrar: " . addslashes($e->getMessage()) . "');
            window.history.back();
          </script>";
}
?>