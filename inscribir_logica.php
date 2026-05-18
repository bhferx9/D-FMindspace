<?php
include 'php/config.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['user_id'])) {
    $id_alumno = $_SESSION['user_id'];
    $id_curso = $_POST['id_curso'];

    try {
        // Verificar si ya existe una inscripción
        $check = $conn->pdo->prepare("
            SELECT id, estado FROM inscripciones 
            WHERE id_alumno = :alumno AND id_curso = :curso
        ");
        $check->execute([':alumno' => $id_alumno, ':curso' => $id_curso]);
        $existente = $check->fetch(PDO::FETCH_ASSOC);

        if ($existente && $existente['estado'] == 'activo') {
            echo "<script>
                    alert('¡Ya estás en esta aventura!');
                    window.location='dashboard_alumno.php';
                  </script>";
            exit();
        }
        
        // Si existe pero está inactivo, o no existe, hacemos limpieza completa
        $conn->pdo->beginTransaction();
        
        // 1. Eliminar evaluaciones antiguas
        $delEval = $conn->pdo->prepare("
            DELETE FROM evaluaciones 
            WHERE id_entrega IN (
                SELECT e.id FROM entregas e
                JOIN actividades a ON e.id_actividad = a.id
                WHERE e.id_alumno = :alumno AND a.id_curso = :curso
            )
        ");
        $delEval->execute([':alumno' => $id_alumno, ':curso' => $id_curso]);
        
        // 2. Eliminar entregas antiguas
        $delEnt = $conn->pdo->prepare("
            DELETE FROM entregas 
            WHERE id_alumno = :alumno 
            AND id_actividad IN (SELECT id FROM actividades WHERE id_curso = :curso)
        ");
        $delEnt->execute([':alumno' => $id_alumno, ':curso' => $id_curso]);
        
        // 3. Actualizar o insertar inscripción
        if ($existente) {
            // Reactivar inscripción existente
            $update = $conn->pdo->prepare("
                UPDATE inscripciones 
                SET estado = 'activo', progreso = 0, fecha_inscripcion = NOW() 
                WHERE id_alumno = :alumno AND id_curso = :curso
            ");
            $update->execute([':alumno' => $id_alumno, ':curso' => $id_curso]);
        } else {
            // Crear nueva inscripción
            $insert = $conn->pdo->prepare("
                INSERT INTO inscripciones (id_alumno, id_curso, progreso, estado, fecha_inscripcion) 
                VALUES (:alumno, :curso, 0, 'activo', NOW())
            ");
            $insert->execute([':alumno' => $id_alumno, ':curso' => $id_curso]);
        }
        
        // 4. Actualizar o insertar en tabla progreso
        $checkProg = $conn->pdo->prepare("
            SELECT id FROM progreso WHERE id_alumno = :alumno AND id_curso = :curso
        ");
        $checkProg->execute([':alumno' => $id_alumno, ':curso' => $id_curso]);
        
        if ($checkProg->fetch()) {
            $updateProg = $conn->pdo->prepare("
                UPDATE progreso SET actividades_completadas = 0, porcentaje = 0 
                WHERE id_alumno = :alumno AND id_curso = :curso
            ");
            $updateProg->execute([':alumno' => $id_alumno, ':curso' => $id_curso]);
        } else {
            $insertProg = $conn->pdo->prepare("
                INSERT INTO progreso (id_alumno, id_curso, actividades_completadas, porcentaje) 
                VALUES (:alumno, :curso, 0, 0)
            ");
            $insertProg->execute([':alumno' => $id_alumno, ':curso' => $id_curso]);
        }
        
        $conn->pdo->commit();
        
        echo "<script>
                alert('¡Bienvenido! Tu aventura comenzará desde cero.');
                window.location='dashboard_alumno.php';
              </script>";
        
    } catch (PDOException $e) {
        if ($conn->pdo->inTransaction()) {
            $conn->pdo->rollBack();
        }
        echo "<script>
                alert('Error al inscribirse: " . addslashes($e->getMessage()) . "');
                window.location='catalogo.php';
              </script>";
    }
} else {
    header("Location: index.php");
    exit();
}
?>