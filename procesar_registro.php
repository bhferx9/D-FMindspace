<?php
include 'php/config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = mysqli_real_escape_string($conn, $_POST['nombre']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $telefono = mysqli_real_escape_string($conn, $_POST['telefono']);
    $fecha_nac = mysqli_real_escape_string($conn, $_POST['fecha_nac']);
    $tipo = mysqli_real_escape_string($conn, $_POST['tipo']);
    
    $id_hijo_vinculado = "NULL";

    // 1. Validar si el email del PADRE ya existe
    $check_email = mysqli_query($conn, "SELECT id FROM usuarios WHERE email = '$email'");
    if (mysqli_num_rows($check_email) > 0) {
        die("<script>alert('Error: Este correo ya está registrado.'); window.history.back();</script>");
    }

    // 2. Si es padre, validar credenciales del HIJO
    if ($tipo == 'padre') {
        $h_email = mysqli_real_escape_string($conn, $_POST['hijo_email']);
        $h_pass = $_POST['hijo_password'];

        $res_hijo = mysqli_query($conn, "SELECT id, password FROM usuarios WHERE email = '$h_email' AND tipo = 'alumno'");
        
        if ($hijo = mysqli_fetch_assoc($res_hijo)) {
            // Verificar si la contraseña del hijo es correcta
            if (password_verify($h_pass, $hijo['password'])) {
                $id_hijo_vinculado = $hijo['id'];
            } else {
                die("<script>alert('Error: La contraseña del hijo es incorrecta.'); window.history.back();</script>");
            }
        } else {
            die("<script>alert('Error: No encontramos ningún alumno con ese correo.'); window.history.back();</script>");
        }
    }

    // 3. Insertar al nuevo usuario
    $sql = "INSERT INTO usuarios (nombre, email, password, tipo, telefono, fecha_nacimiento, id_hijo_vinculado) 
            VALUES ('$nombre', '$email', '$password', '$tipo', '$telefono', '$fecha_nac', $id_hijo_vinculado)";

    if (mysqli_query($conn, $sql)) {
        echo "<script>alert('¡Registro exitoso! Bienvenido.'); window.location='index.php';</script>";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>