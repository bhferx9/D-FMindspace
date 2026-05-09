<?php
include 'php/config.php';
session_start();

// Verificar que el usuario sea padre
if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'padre') {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['codigo'])) {
    $codigo = trim($_POST['codigo']);
    $id_padre = (int)$_SESSION['user_id'];
    
    if (empty($codigo)) {
        $_SESSION['toast'] = ['error', 'Por favor ingresa un código de vinculación'];
        header('Location: dashboard_padre.php');
        exit();
    }
    
    try {
        // Buscar alumno por código de vinculación
        $stmt = $conn->pdo->prepare("SELECT id FROM usuarios WHERE codigo_vinculacion = :codigo AND tipo = 'alumno' AND activo = TRUE");
        $stmt->execute([':codigo' => $codigo]);
        
        if ($stmt->rowCount() > 0) {
            $alumno = $stmt->fetch(PDO::FETCH_ASSOC);
            $id_alumno = (int)$alumno['id'];
            
            // Verificar que no exista ya vinculación activa
            $check = $conn->pdo->prepare("SELECT id FROM vinculaciones WHERE id_padre = :id_padre AND id_alumno = :id_alumno AND estado = 'activo'");
            $check->execute([':id_padre' => $id_padre, ':id_alumno' => $id_alumno]);
            
            if ($check->rowCount() == 0) {
                // Crear vinculación
                $insert = $conn->pdo->prepare("INSERT INTO vinculaciones (id_padre, id_alumno, estado, fecha_vinculacion) VALUES (:id_padre, :id_alumno, 'activo', CURRENT_TIMESTAMP)");
                $insert->execute([
                    ':id_padre' => $id_padre,
                    ':id_alumno' => $id_alumno
                ]);
                
                $_SESSION['toast'] = ['success', '🎉 ¡Hijo vinculado correctamente! Ahora puedes ver su progreso.'];
            } else {
                $_SESSION['toast'] = ['warning', '⚠️ Este hijo ya está vinculado a tu cuenta.'];
            }
        } else {
            $_SESSION['toast'] = ['error', '❌ Código no válido. Verifica el código e intenta nuevamente.'];
        }
        
    } catch(PDOException $e) {
        $_SESSION['toast'] = ['error', 'Error al vincular: ' . $e->getMessage()];
    }
}

header('Location: dashboard_padre.php');
exit;
?>