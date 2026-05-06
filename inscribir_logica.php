<?php
include 'php/config.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['user_id'])) {
    $id_alumno = $_SESSION['user_id'];
    $id_curso = $_POST['id_curso'];

    // 1. Insertar en la tabla inscripciones
    $sql_ins = "INSERT INTO inscripciones (id_alumno, id_curso, progreso, estado) 
                VALUES ('$id_alumno', '$id_curso', 0, 'activo')";
    
    // 2. También inicializamos la tabla de progreso para este alumno y curso
    $sql_prog = "INSERT INTO progreso (id_alumno, id_curso, actividades_completadas, porcentaje) 
                 VALUES ('$id_alumno', '$id_curso', 0, 0)";

    if (mysqli_query($conn, $sql_ins) && mysqli_query($conn, $sql_prog)) {
        echo "<script>
                alert('¡Genial! Ya eres parte de esta aventura.');
                window.location='dashboard_alumno.php';
              </script>";
    } else {
        echo "Error al inscribirse: " . mysqli_error($conn);
    }

    // Validar si ya existe la inscripción antes de insertar
$check = mysqli_query($conn, "SELECT id FROM inscripciones WHERE id_alumno = '$id_alumno' AND id_curso = '$id_curso'");
if (mysqli_num_rows($check) > 0) {
    echo "<script>alert('¡Ya estás en esta aventura!'); window.location='dashboard_alumno.php';</script>";
    exit();
}
}
?>