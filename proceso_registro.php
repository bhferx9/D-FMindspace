<?php
include 'php/config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = mysqli_real_escape_string($conn, $_POST['nombre']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $tipo = $_POST['tipo'];
    $id_hijo_vinculado = "NULL"; // Valor por defecto para la DB

    // 1. Verificar si el email ya existe
    $check_email = mysqli_query($conn, "SELECT id FROM usuarios WHERE email = '$email'");
    if (mysqli_num_rows($check_email) > 0) {
        die("<script>alert('Error: Este correo ya está registrado.'); window.history.back();</script>");
    }

    // 2. Lógica especial para Padres (VINCULACIÓN)
    if ($tipo == 'padre') {
        $nombre_hijo = mysqli_real_escape_string($conn, $_POST['nombre_hijo']);
        
        // Buscar al hijo en la base de datos
        $buscar_hijo = mysqli_query($conn, "SELECT id FROM usuarios WHERE nombre = '$nombre_hijo' AND tipo = 'alumno'");
        
        if (mysqli_num_rows($buscar_hijo) > 0) {
            $fila_hijo = mysqli_fetch_assoc($buscar_hijo);
            $id_hijo_vinculado = $fila_hijo['id'];
        } else {
            // Si el hijo no existe, cancelamos el registro del padre
            die("<script>
                alert('No se pudo encontrar a un alumno con el nombre: $nombre_hijo. Por favor, asegúrate de escribirlo igual a como él se registró.'); 
                window.history.back();
            </script>");
        }
    }

    // 3. Insertar el nuevo usuario
    // Nota: Asegúrate de tener la columna 'id_hijo_vinculado' en tu tabla usuarios
    $sql = "INSERT INTO usuarios (nombre, email, password, tipo, id_hijo_vinculado) 
            VALUES ('$nombre', '$email', '$password', '$tipo', $id_hijo_vinculado)";

    if (mysqli_query($conn, $sql)) {
        echo "<script>
                alert('¡Registro exitoso! Bienvenido a D&F Mindspace.');
                window.location='index.php';
              </script>";
    } else {
        echo "Error en la base de datos: " . mysqli_error($conn);
    }
}
?>