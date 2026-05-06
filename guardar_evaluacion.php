<?php
include 'php/config.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_entrega = $_POST['id_entrega'];
    $id_alumno = $_POST['id_alumno'];
    $id_actividad = $_POST['id_actividad'];
    $tutor_id = $_SESSION['user_id'];
    $calif = $_POST['calificacion'];
    $comentarios = mysqli_real_escape_string($conn, $_POST['comentarios']);
    
    // 1. Guardar la evaluación
    $sql_eval = "INSERT INTO evaluaciones (id_entrega, id_tutor, calificacion, comentarios) 
                 VALUES ('$id_entrega', '$tutor_id', '$calif', '$comentarios')";
    
    // 2. Marcar la entrega como 'calificado'
    $sql_upd_entrega = "UPDATE entregas SET estado = 'calificado' WHERE id = '$id_entrega'";

    if (mysqli_query($conn, $sql_eval) && mysqli_query($conn, $sql_upd_entrega)) {
        
        // --- LÓGICA DE ACTUALIZACIÓN DE PROGRESO ---
        
        // A. Obtener el ID del curso al que pertenece la actividad
        $res_c = mysqli_query($conn, "SELECT id_curso FROM actividades WHERE id = '$id_actividad'");
        $act = mysqli_fetch_assoc($res_c);
        $id_curso = $act['id_curso'];

        // B. Contar cuántas actividades totales tiene el curso
        $res_total = mysqli_query($conn, "SELECT COUNT(*) as total FROM actividades WHERE id_curso = '$id_curso'");
        $total_actividades = mysqli_fetch_assoc($res_total)['total'];

        // C. Contar cuántas tareas CALIFICADAS tiene el alumno en este curso
        $res_comp = mysqli_query($conn, "SELECT COUNT(DISTINCT e.id_actividad) as hechas 
                                         FROM entregas e 
                                         JOIN actividades a ON e.id_actividad = a.id 
                                         WHERE e.id_alumno = '$id_alumno' AND a.id_curso = '$id_curso' AND e.estado = 'calificado'");
        $actividades_hechas = mysqli_fetch_assoc($res_comp)['hechas'];

        // D. Calcular porcentaje
        $nuevo_porcentaje = ($total_actividades > 0) ? ($actividades_hechas / $total_actividades) * 100 : 0;

        // E. Actualizar tablas 'progreso' e 'inscripciones'
        mysqli_query($conn, "UPDATE progreso SET porcentaje = '$nuevo_porcentaje', actividades_completadas = '$actividades_hechas', fecha_actualizacion = NOW() 
                             WHERE id_alumno = '$id_alumno' AND id_curso = '$id_curso'");
        
        mysqli_query($conn, "UPDATE inscripciones SET progreso = '$nuevo_porcentaje' 
                             WHERE id_alumno = '$id_alumno' AND id_curso = '$id_curso'");

        echo "<script>alert('Tarea calificada y progreso actualizado al " . round($nuevo_porcentaje) . "%'); window.location='revisar_entregas.php';</script>";
    }
}
?>