<?php
include 'php/config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'tutor') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$tutor_id = $_SESSION['user_id'];

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $activity_id = intval($_GET['id']);
    
    // Verificar que la actividad pertenezca a un curso del tutor
    $sql = "SELECT a.*, c.id_tutor 
            FROM actividades a
            JOIN cursos c ON a.id_curso = c.id
            WHERE a.id = '$activity_id' AND c.id_tutor = '$tutor_id'";
    
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) > 0) {
        $activity = mysqli_fetch_assoc($result);
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
} else {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
}