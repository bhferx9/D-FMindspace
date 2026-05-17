<?php
include 'php/config.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$nombre = trim($_POST['nombre'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$telefono = trim($_POST['telefono'] ?? '');
$fecha_nac = $_POST['fecha_nac'] ?? null;
$tipo = $_POST['tipo'] ?? 'alumno';

$errores = [];

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
    echo json_encode(['success' => false, 'message' => implode(' ', $errores)]);
    exit();
}

try {
    $stmt = $conn->pdo->prepare("SELECT id FROM usuarios WHERE email = :email");
    $stmt->execute([':email' => $email]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Este correo ya está registrado']);
        exit();
    }
    
    $id_hijo_vinculado = null;
    
    if ($tipo == 'padre') {
        $h_email = trim($_POST['hijo_email'] ?? '');
        $h_pass = $_POST['hijo_password'] ?? '';
        
        if (empty($h_email) || empty($h_pass)) {
            echo json_encode(['success' => false, 'message' => 'Debes ingresar el correo y contraseña del hijo para vincular']);
            exit();
        }
        
        $stmt_hijo = $conn->pdo->prepare("SELECT id, password FROM usuarios WHERE email = :email AND tipo = 'alumno'");
        $stmt_hijo->execute([':email' => $h_email]);
        
        if ($stmt_hijo->rowCount() == 0) {
            echo json_encode(['success' => false, 'message' => 'No encontramos ningún alumno con ese correo']);
            exit();
        }
        
        $hijo = $stmt_hijo->fetch(PDO::FETCH_ASSOC);
        
        if (!password_verify($h_pass, $hijo['password'])) {
            echo json_encode(['success' => false, 'message' => 'La contraseña del hijo es incorrecta']);
            exit();
        }
        
        $id_hijo_vinculado = $hijo['id'];
    }
    
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
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
    
    echo json_encode(['success' => true, 'message' => '¡Registro exitoso! Bienvenido a D&F Mindspace']);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error al registrar: ' . $e->getMessage()]);
}
?>