<?php
include 'php/config.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    // Buscamos al usuario por email
    $sql = "SELECT * FROM usuarios WHERE email = '$email' AND activo = TRUE";
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        
        // Verificamos la contraseña encriptada
        if (password_verify($password, $user['password'])) {
            // Guardamos datos en la sesión
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nombre'] = $user['nombre'];
            $_SESSION['tipo'] = $user['tipo'];

            // Redirección según el tipo de usuario (usando tus ENUMS)
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
            }
            exit();
        } else {
            echo "<script>alert('Contraseña incorrecta'); window.location='index.php';</script>";
        }
    } else {
        echo "<script>alert('El correo no está registrado'); window.location='index.php';</script>";
    }
}
?>