<?php
include 'php/config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'alumno') {
    header("Location: index.php");
    exit();
}

if (!isset($_GET['id']) || !isset($_GET['confirm']) || $_GET['confirm'] != 'si') {
    header("Location: mis_cursos.php");
    exit();
}

$alumno_id = (int)$_SESSION['user_id'];
$id_curso = (int)$_GET['id'];

try {
    $stmt = $conn->pdo->prepare("
        UPDATE inscripciones 
        SET estado = 'inactivo' 
        WHERE id_alumno = :alumno_id AND id_curso = :curso_id AND estado = 'activo'
    ");
    $stmt->execute([':alumno_id' => $alumno_id, ':curso_id' => $id_curso]);
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['mensaje_exito'] = "Has salido del curso correctamente.";
    } else {
        $_SESSION['mensaje_error'] = "No estabas inscrito activamente en este curso.";
    }
} catch(PDOException $e) {
    $_SESSION['mensaje_error'] = "Error al salir del curso: " . $e->getMessage();
}

header("Location: mis_cursos.php");
exit();
?>