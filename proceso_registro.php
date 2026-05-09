<?php
include 'php/config.php';

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: index.php");
    exit();
}

$nombre = trim($_POST['nombre'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$tipo = $_POST['tipo'] ?? 'alumno';
$id_hijo_vinculado = null;

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
if (!in_array($tipo, ['alumno', 'tutor', 'padre', 'admin'])) {
    $errores[] = "Tipo de usuario inválido.";
}

if (!empty($errores)) {
    die("<script>alert('Error:\\n" . implode("\\n", $errores) . "'); window.history.back();</script>");
}

try {
    // 1. Verificar si el email ya existe
    $stmt = $conn->pdo->prepare("SELECT id FROM usuarios WHERE email = :email");
    $stmt->execute([':email' => $email]);
    
    if ($stmt->rowCount() > 0) {
        die("<script>alert('Error: Este correo ya está registrado.'); window.history.back();</script>");
    }

    // 2. Lógica especial para Padres (VINCULACIÓN)
    if ($tipo == 'padre') {
        $nombre_hijo = trim($_POST['nombre_hijo'] ?? '');
        $password_hijo = $_POST['password_hijo'] ?? '';
        
        if (empty($nombre_hijo)) {
            die("<script>alert('Error: Debes ingresar el nombre del hijo para vincular.'); window.history.back();</script>");
        }
        
        // Buscar al hijo en la base de datos (por nombre y tipo)
        $stmt_hijo = $conn->pdo->prepare("SELECT id, password FROM usuarios WHERE nombre = :nombre AND tipo = 'alumno'");
        $stmt_hijo->execute([':nombre' => $nombre_hijo]);
        
        if ($stmt_hijo->rowCount() == 0) {
            die("<script>
                alert('No se pudo encontrar a un alumno con el nombre: $nombre_hijo. Por favor, asegúrate de escribirlo igual a como él se registró.'); 
                window.history.back();
            </script>");
        }
        
        $hijo = $stmt_hijo->fetch(PDO::FETCH_ASSOC);
        
        // Verificar la contraseña del hijo (si se proporcionó)
        if (!empty($password_hijo)) {
            if (!password_verify($password_hijo, $hijo['password'])) {
                die("<script>
                    alert('Error: La contraseña del hijo es incorrecta. No se puede vincular la cuenta.');
                    window.history.back();
                </script>");
            }
        }
        
        $id_hijo_vinculado = $hijo['id'];
    }

    // 3. Encriptar contraseña
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // 4. Insertar el nuevo usuario
    $sql = "INSERT INTO usuarios (nombre, email, password, tipo, id_hijo_vinculado, fecha_registro) 
            VALUES (:nombre, :email, :password, :tipo, :id_hijo_vinculado, CURRENT_TIMESTAMP)";
    
    $stmt_insert = $conn->pdo->prepare($sql);
    $stmt_insert->execute([
        ':nombre' => $nombre,
        ':email' => $email,
        ':password' => $password_hash,
        ':tipo' => $tipo,
        ':id_hijo_vinculado' => $id_hijo_vinculado
    ]);
    
    echo "<script>
            alert('🎉 ¡Registro exitoso! Bienvenido a D&F Mindspace.');
            window.location='index.php';
          </script>";
    
} catch(PDOException $e) {
    echo "<script>
            alert('❌ Error en la base de datos: " . addslashes($e->getMessage()) . "');
            window.history.back();
          </script>";
}
?>