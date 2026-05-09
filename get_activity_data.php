<?php
include 'php/config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'tutor') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$tutor_id = (int)$_SESSION['user_id'];

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $activity_id = (int)$_GET['id'];
    
    try {
        // Verificar que la actividad pertenezca a un curso del tutor
        $stmt = $conn->pdo->prepare("
            SELECT a.*, c.id_tutor 
            FROM actividades a
            JOIN cursos c ON a.id_curso = c.id
            WHERE a.id = :activity_id AND c.id_tutor = :tutor_id
        ");
        $stmt->execute([':activity_id' => $activity_id, ':tutor_id' => $tutor_id]);
        
        if ($stmt->rowCount() > 0) {
            $activity = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode([
                'success' => true,
                'id' => $activity['id'],
                'titulo' => $activity['titulo'],
                'descripcion' => $activity['descripcion'],
                'tipo' => $activity['tipo'],
                'dificultad' => $activity['dificultad'],
                'fecha_limite' => $activity['fecha_limite'],
                'puntos' => $activity['puntos'],
                'id_curso' => $activity['id_curso']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Actividad no encontrada']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
}
?>