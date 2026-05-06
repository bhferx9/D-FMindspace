<?php
include 'php/config.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_alumno = $_SESSION['user_id'];
    $id_actividad = $_POST['id_actividad'];
    $respuesta = mysqli_real_escape_string($conn, $_POST['respuesta']);
    
    // Manejo básico de archivo
    $nombre_archivo = "";
    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] == 0) {
        $nombre_archivo = time() . "_" . $_FILES['archivo']['name'];
        move_uploaded_file($_FILES['archivo']['tmp_name'], "uploads/" . $nombre_archivo);
    }

    $sql = "INSERT INTO entregas (id_alumno, id_actividad, respuesta, archivo, estado) 
            VALUES ('$id_alumno', '$id_actividad', '$respuesta', '$nombre_archivo', 'pendiente')";

    if (mysqli_query($conn, $sql)) {
        // Redirigir con un mensaje de éxito
        echo "<script>
                alert('¡Misión enviada con éxito! Espera a que tu tutor la revise.');
                window.location='dashboard_alumno.php';
              </script>";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>