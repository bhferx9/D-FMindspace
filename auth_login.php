<?php
include 'php/config.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // No necesitas mysqli_real_escape_string con consultas preparadas
    $email = $_POST['email'];
    $password = $_POST['password'];

    try {
        // Consulta preparada (segura contra inyección SQL)
        $sql = "SELECT * FROM usuarios WHERE email = ? AND activo = TRUE";
        $stmt = $conn->pdo->prepare($sql);
        $stmt->execute([$email]);
        
        // Verificar si encontró el usuario
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verificar contraseña
            if (password_verify($password, $user['password'])) {
                // Guardar datos en sesión
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['nombre'] = $user['nombre'];
                $_SESSION['tipo'] = $user['tipo'];

                // Redirección según el tipo de usuario
                switch ($user['tipo']) {
                    case 'alumno':
                        header("Location: dashboard_alumno.php");
                        break;
                    case 'tutor':
                        header("Location: dashboard_tutor.php");
                        break;
                    case 'padre':
                        header("Location: dashboard_padre.php");
                        break;
                    case 'admin':
                        header("Location: dashboard_admin.php");
                        break;
                    default:
                        header("Location: index.php");
                }
                exit();
            } else {
                echo "<script>alert('Contraseña incorrecta'); window.location='index.php';</script>";
            }
        } else {
            echo "<script>alert('El correo no está registrado o la cuenta no está activa'); window.location='index.php';</script>";
        }
    } catch(PDOException $e) {
        echo "<script>alert('Error en la base de datos: " . addslashes($e->getMessage()) . "'); window.location='index.php';</script>";
    }
}
?>