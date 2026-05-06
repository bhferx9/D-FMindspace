<?php
include 'php/config.php';
session_start();

$entrega_id = $_GET['id'];

// Obtener datos de la entrega
$sql = "SELECT e.*, u.nombre as alumno_nombre, a.titulo as actividad_titulo, a.puntos as puntos_max
        FROM entregas e
        JOIN usuarios u ON e.id_alumno = u.id
        JOIN actividades a ON e.id_actividad = a.id
        WHERE e.id = '$entrega_id'";
$res = mysqli_query($conn, $sql);
$data = mysqli_fetch_assoc($res);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Evaluando a <?php echo $data['alumno_nombre']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #f0f2f5; font-family: 'Quicksand', sans-serif; }
        .grade-card { border-radius: 20px; border: none; }
        .response-box { background: #fff; border-left: 5px solid #4DB6AC; padding: 20px; border-radius: 10px; }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card grade-card shadow p-4">
                <h3 class="fw-bold mb-4">Evaluando: <?php echo $data['actividad_titulo']; ?></h3>
                <p><strong>Alumno:</strong> <?php echo $data['alumno_nombre']; ?></p>
                
                <div class="response-box mb-4 shadow-sm">
                    <h6 class="fw-bold text-muted">RESPUESTA DEL ALUMNO:</h6>
                    <p class="mb-0"><?php echo nl2br($data['respuesta']); ?></p>
                </div>

                <form action="guardar_evaluacion.php" method="POST">
                    <input type="hidden" name="id_entrega" value="<?php echo $entrega_id; ?>">
                    <input type="hidden" name="id_alumno" value="<?php echo $data['id_alumno']; ?>">
                    <input type="hidden" name="id_actividad" value="<?php echo $data['id_actividad']; ?>">

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Calificación (0 - <?php echo $data['puntos_max']; ?>)</label>
                            <input type="number" name="calificacion" class="form-control" max="<?php echo $data['puntos_max']; ?>" required>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-bold">Comentarios para el niño (¡Sé motivador! 🌈)</label>
                            <textarea name="comentarios" class="form-control" rows="3" placeholder="¡Excelente trabajo! Sigue así..."></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success w-100 rounded-pill fw-bold">ENVIAR CALIFICACIÓN</button>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>