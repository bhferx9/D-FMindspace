<?php
include 'php/config.php';
session_start();


header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
} 


$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Correo y contraseña son obligatorios']);
    exit();
}

try {
    $sql = "SELECT * FROM usuarios WHERE email = ? AND activo = TRUE";
    $stmt = $conn->pdo->prepare($sql);
    $stmt->execute([$email]);
    
    if ($stmt->rowCount() == 0) {
        echo json_encode(['success' => false, 'message' => 'El correo no está registrado o la cuenta no está activa']);
        exit();
    }
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta']);
        exit();
    }
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['nombre'] = $user['nombre'];
    $_SESSION['tipo'] = $user['tipo'];
    
    // Redirección según tipo
    $redirect = match($user['tipo']) {
        'alumno' => 'dashboard_alumno.php',
        'tutor' => 'dashboard_tutor.php',
        'padre' => 'dashboard_padre.php',
        'admin' => 'dashboard_admin.php',
        default => 'index.php'
    };
    
    echo json_encode(['success' => true, 'redirect' => $redirect]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}
?>