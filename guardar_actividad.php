<?php
include 'php/config.php';
session_start();

// Verificar que sea tutor o admin
if (!isset($_SESSION['user_id']) || ($_SESSION['tipo'] != 'tutor' && $_SESSION['tipo'] != 'admin')) {
    header("Location: index.php");
    exit();
}

$tutor_id = (int)$_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recibir y limpiar datos
    $id_curso = isset($_POST['id_curso']) ? (int)$_POST['id_curso'] : 0;
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $tipo = $_POST['tipo'] ?? 'Quiz';
    $puntos = isset($_POST['puntos']) ? (int)$_POST['puntos'] : 0;
    $fecha_limite = $_POST['fecha_limite'] ?? null;
    $dificultad = $_POST['dificultad'] ?? 'Normal';
    
    // Validaciones
    $errores = [];
    
    if ($id_curso <= 0) {
        $errores[] = "Debes seleccionar un curso válido.";
    }
    
    if (empty($titulo) || strlen($titulo) < 3) {
        $errores[] = "El título debe tener al menos 3 caracteres.";
    }
    
    if ($puntos < 1 || $puntos > 1000) {
        $errores[] = "Los puntos deben estar entre 1 y 1000.";
    }
    
    if (empty($fecha_limite)) {
        $errores[] = "Debes seleccionar una fecha límite.";
    }
    
    if (empty($errores)) {
        try {
            // Verificar que el curso pertenezca al tutor
            $stmt_check = $conn->pdo->prepare("SELECT id FROM cursos WHERE id = :id_curso AND id_tutor = :tutor_id");
            $stmt_check->execute([':id_curso' => $id_curso, ':tutor_id' => $tutor_id]);
            
            if ($stmt_check->rowCount() == 0) {
                $errores[] = "No tienes permiso para agregar actividades a este curso.";
            } else {
                // Insertar la actividad
                $stmt = $conn->pdo->prepare("
                    INSERT INTO actividades (id_curso, titulo, descripcion, tipo, puntos, fecha_limite, dificultad) 
                    VALUES (:id_curso, :titulo, :descripcion, :tipo, :puntos, :fecha_limite, :dificultad)
                ");
                
                $stmt->execute([
                    ':id_curso' => $id_curso,
                    ':titulo' => $titulo,
                    ':descripcion' => $descripcion,
                    ':tipo' => $tipo,
                    ':puntos' => $puntos,
                    ':fecha_limite' => $fecha_limite,
                    ':dificultad' => $dificultad
                ]);
                
                // Redirigir con mensaje de éxito
                echo "<script>
                        alert('🎉 ¡Misión publicada con éxito! Los alumnos ya pueden verla.');
                        window.location='dashboard_tutor.php';
                      </script>";
                exit();
            }
        } catch(PDOException $e) {
            $errores[] = "Error al crear la misión: " . $e->getMessage();
        }
    }
    
    // Si hay errores, mostrarlos
    if (!empty($errores)) {
        $mensaje_error = implode("\\n", $errores);
        echo "<script>
                alert('❌ Error: \\n" . $mensaje_error . "');
                window.history.back();
              </script>";
    }
}
?>