<?php
include 'php/config.php';
include 'php/sendgrid_notificaciones.php';
session_start();

// Verificar que el usuario sea tutor
if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'tutor') {
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: revisar_entregas.php");
    exit();
}

$tutor_id = (int)$_SESSION['user_id'];
$id_entrega = isset($_POST['id_entrega']) ? (int)$_POST['id_entrega'] : 0;
$id_alumno = isset($_POST['id_alumno']) ? (int)$_POST['id_alumno'] : 0;
$id_actividad = isset($_POST['id_actividad']) ? (int)$_POST['id_actividad'] : 0;
$calificacion = isset($_POST['calificacion']) ? (float)$_POST['calificacion'] : 0;
$comentarios = trim($_POST['comentarios'] ?? '');

$errores = [];

try {
    // Verificar que la entrega exista y pertenezca a un curso del tutor
    $stmt_check = $conn->pdo->prepare("
        SELECT e.*, a.puntos as puntos_maximos, a.id_curso, a.titulo as actividad_nombre,
               c.nombre as curso_nombre, e.id_alumno
        FROM entregas e
        JOIN actividades a ON e.id_actividad = a.id
        JOIN cursos c ON a.id_curso = c.id
        WHERE e.id = :id_entrega AND c.id_tutor = :tutor_id AND e.estado = 'pendiente'
    ");
    $stmt_check->execute([':id_entrega' => $id_entrega, ':tutor_id' => $tutor_id]);
    
    if ($stmt_check->rowCount() == 0) {
        $errores[] = "No tienes permiso para calificar esta entrega o ya fue calificada.";
    } else {
        $entrega = $stmt_check->fetch(PDO::FETCH_ASSOC);
        $puntos_maximos = $entrega['puntos_maximos'];
        $id_curso = $entrega['id_curso'];
        $id_alumno = $entrega['id_alumno'];
        
        // Validar calificación
        if ($calificacion < 0 || $calificacion > $puntos_maximos) {
            $errores[] = "La calificación debe estar entre 0 y " . $puntos_maximos . " puntos.";
        }
        
        if (empty($errores)) {
            // Iniciar transacción
            $conn->pdo->beginTransaction();
            
            // 1. Guardar la evaluación
            $stmt_eval = $conn->pdo->prepare("
                INSERT INTO evaluaciones (id_entrega, id_tutor, calificacion, comentarios, fecha_evaluacion) 
                VALUES (:id_entrega, :id_tutor, :calificacion, :comentarios, CURRENT_TIMESTAMP)
            ");
            $stmt_eval->execute([
                ':id_entrega' => $id_entrega,
                ':id_tutor' => $tutor_id,
                ':calificacion' => $calificacion,
                ':comentarios' => $comentarios
            ]);
            
            // 2. Marcar la entrega como 'calificado'
            $stmt_update = $conn->pdo->prepare("UPDATE entregas SET estado = 'calificado' WHERE id = :id_entrega");
            $stmt_update->execute([':id_entrega' => $id_entrega]);
            
            // 3. Calcular nuevo progreso
            
            // A. Total de actividades del curso
            $stmt_total = $conn->pdo->prepare("SELECT COUNT(*) as total FROM actividades WHERE id_curso = :id_curso");
            $stmt_total->execute([':id_curso' => $id_curso]);
            $total_actividades = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];
            
            // B. Actividades calificadas por el alumno en este curso
            $stmt_hechas = $conn->pdo->prepare("
                SELECT COUNT(DISTINCT e.id_actividad) as hechas 
                FROM entregas e 
                JOIN actividades a ON e.id_actividad = a.id 
                WHERE e.id_alumno = :id_alumno 
                AND a.id_curso = :id_curso 
                AND e.estado = 'calificado'
            ");
            $stmt_hechas->execute([':id_alumno' => $id_alumno, ':id_curso' => $id_curso]);
            $actividades_hechas = $stmt_hechas->fetch(PDO::FETCH_ASSOC)['hechas'];
            
            // C. Calcular porcentaje
            $nuevo_porcentaje = ($total_actividades > 0) ? round(($actividades_hechas / $total_actividades) * 100) : 0;
            
            // D. Actualizar tabla 'progreso' (PostgreSQL)
            $stmt_progreso = $conn->pdo->prepare("
                INSERT INTO progreso (id_alumno, id_curso, porcentaje, actividades_completadas, fecha_actualizacion)
                VALUES (:id_alumno, :id_curso, :porcentaje, :completadas, CURRENT_TIMESTAMP)
                ON CONFLICT (id_alumno, id_curso) 
                DO UPDATE SET 
                    porcentaje = EXCLUDED.porcentaje,
                    actividades_completadas = EXCLUDED.actividades_completadas,
                    fecha_actualizacion = CURRENT_TIMESTAMP
            ");
            $stmt_progreso->execute([
                ':id_alumno' => $id_alumno,
                ':id_curso' => $id_curso,
                ':porcentaje' => $nuevo_porcentaje,
                ':completadas' => $actividades_hechas
            ]);
            
            // E. Actualizar tabla 'inscripciones'
            $stmt_insc = $conn->pdo->prepare("
                UPDATE inscripciones SET progreso = :porcentaje 
                WHERE id_alumno = :id_alumno AND id_curso = :id_curso
            ");
            $stmt_insc->execute([
                ':porcentaje' => $nuevo_porcentaje,
                ':id_alumno' => $id_alumno,
                ':id_curso' => $id_curso
            ]);
            
            // Confirmar transacción
            $conn->pdo->commit();
            
            // ─────────────────────────────────────────────────────────
            // NOTIFICAR AL ALUMNO QUE SU TAREA FUE CALIFICADA
            // ─────────────────────────────────────────────────────────
            $stmt_alumno = $conn->pdo->prepare("SELECT nombre, email FROM usuarios WHERE id = :id");
            $stmt_alumno->execute([':id' => $id_alumno]);
            $alumno = $stmt_alumno->fetch(PDO::FETCH_ASSOC);
            
            if ($alumno && !empty($alumno['email'])) {
                notificar_alumno_calificacion(
                    $alumno['email'],
                    $alumno['nombre'],
                    $entrega['actividad_nombre'],
                    $entrega['curso_nombre'],
                    $calificacion,
                    $puntos_maximos,
                    $comentarios
                );
            }
            // ─────────────────────────────────────────────────────────
            
            header("Location: revisar_entregas.php?success=1&puntos=" . $calificacion . "&max=" . $puntos_maximos . "&progreso=" . $nuevo_porcentaje);
            exit();
        }
    }
} catch(PDOException $e) {
    if ($conn->pdo->inTransaction()) {
        $conn->pdo->rollBack();
    }
    $errores[] = "Error en la base de datos: " . $e->getMessage();
}

// Mostrar errores si los hay
if (!empty($errores)) {
    $mensaje_error = implode("\\n", $errores);
    echo "<script>
            alert('❌ Error:\\n" . $mensaje_error . "');
            window.history.back();
          </script>";
}
?>