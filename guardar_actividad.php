<?php
include 'php/config.php';
session_start();

// Verificar que sea tutor o admin
if (!isset($_SESSION['user_id']) || ($_SESSION['tipo'] != 'tutor' && $_SESSION['tipo'] != 'admin')) {
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recibir y limpiar datos
    $id_curso     = mysqli_real_escape_string($conn, $_POST['id_curso']);
    $titulo       = mysqli_real_escape_string($conn, $_POST['titulo']);
    $descripcion  = mysqli_real_escape_string($conn, $_POST['descripcion']);
    $tipo         = mysqli_real_escape_string($conn, $_POST['tipo']);
    $puntos       = mysqli_real_escape_string($conn, $_POST['puntos']);
    $fecha_limite = mysqli_real_escape_string($conn, $_POST['fecha_limite']);

    // Query para insertar la actividad
    $sql = "INSERT INTO actividades (id_curso, titulo, descripcion, tipo, puntos, fecha_limite, dificultad) 
            VALUES ('$id_curso', '$titulo', '$descripcion', '$tipo', '$puntos', '$fecha_limite', 'Normal')";

    if (mysqli_query($conn, $sql)) {
        // Si todo sale bien, volvemos al panel del tutor con un mensaje de éxito
        echo "<script>
                alert('¡Misión publicada con éxito! Los alumnos ya pueden verla.');
                window.location='dashboard_tutor.php';
              </script>";
    } else {
        echo "Error al crear la misión: " . mysqli_error($conn);
    }
}
?>