<?php
include 'php/config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'tutor') {
    header("Location: index.php");
    exit();
}

$tutor_id = $_SESSION['user_id'];

// Obtener cursos para el formulario y filtro
$sql_cursos = "SELECT id, nombre FROM cursos WHERE id_tutor = '$tutor_id' ORDER BY nombre ASC";
$res_cursos = mysqli_query($conn, $sql_cursos);

// Obtener cursos para array
$cursos_array = [];
while($curso = mysqli_fetch_assoc($res_cursos)) {
    $cursos_array[$curso['id']] = $curso['nombre'];
}
mysqli_data_seek($res_cursos, 0);

// Inicializar variables
$activity_data = null;
$success_message = '';
$error_message = '';
$curso_filtro = isset($_GET['curso']) ? intval($_GET['curso']) : 0;

// Manejar solicitud de datos para edición (GET)
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    
    // Obtener datos de la actividad
    $sql_activity = "SELECT a.*, c.id_tutor 
                    FROM actividades a
                    JOIN cursos c ON a.id_curso = c.id
                    WHERE a.id = '$edit_id' AND c.id_tutor = '$tutor_id'";
    
    $result_activity = mysqli_query($conn, $sql_activity);
    
    if (mysqli_num_rows($result_activity) > 0) {
        $activity_data = mysqli_fetch_assoc($result_activity);
        
        // Convertir fecha al formato correcto para el input
        $activity_data['fecha_formatted'] = date('Y-m-d', strtotime($activity_data['fecha_limite']));
    } else {
        $error_message = "Actividad no encontrada o no tienes permiso para editarla.";
    }
}

// Manejar actualización de actividad (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['editar_actividad'])) {
    $id_actividad = intval($_POST['id_actividad']);
    $id_curso = mysqli_real_escape_string($conn, $_POST['id_curso']);
    $titulo = mysqli_real_escape_string($conn, trim($_POST['titulo']));
    $descripcion = mysqli_real_escape_string($conn, trim($_POST['descripcion']));
    $tipo = mysqli_real_escape_string($conn, $_POST['tipo']);
    $dificultad = mysqli_real_escape_string($conn, $_POST['dificultad']);
    $fecha_limite = mysqli_real_escape_string($conn, $_POST['fecha_limite']);
    $puntos = intval($_POST['puntos']);
    
    // Validaciones
    if (empty($titulo) || strlen($titulo) < 3) {
        $error_message = "El título debe tener al menos 3 caracteres.";
    } elseif ($puntos < 1 || $puntos > 1000) {
        $error_message = "Los puntos deben estar entre 1 y 1000.";
    } else {
        // Verificar que la actividad pertenezca al tutor
        $sql_verificar = "SELECT a.id FROM actividades a 
                         JOIN cursos c ON a.id_curso = c.id 
                         WHERE a.id = '$id_actividad' AND c.id_tutor = '$tutor_id'";
        $verificar = mysqli_query($conn, $sql_verificar);
        
        if (mysqli_num_rows($verificar) > 0) {
            $sql_update = "UPDATE actividades SET 
                          id_curso = '$id_curso',
                          titulo = '$titulo',
                          descripcion = '$descripcion',
                          tipo = '$tipo',
                          dificultad = '$dificultad',
                          fecha_limite = '$fecha_limite',
                          puntos = '$puntos'
                          WHERE id = '$id_actividad'";
            
            if (mysqli_query($conn, $sql_update)) {
                $success_message = "🎉 ¡Misión actualizada exitosamente!";
                
                // Obtener datos actualizados para el modal
                $sql_updated = "SELECT a.* FROM actividades a WHERE a.id = '$id_actividad'";
                $result_updated = mysqli_query($conn, $sql_updated);
                $activity_data = mysqli_fetch_assoc($result_updated);
                $activity_data['fecha_formatted'] = date('Y-m-d', strtotime($activity_data['fecha_limite']));
            } else {
                $error_message = "Error al actualizar: " . mysqli_error($conn);
            }
        } else {
            $error_message = "No tienes permiso para editar esta actividad.";
        }
    }
}

// Manejar eliminación de actividad
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $id_eliminar = intval($_GET['eliminar']);
    
    // Verificar que la actividad pertenezca al tutor
    $sql_verificar = "SELECT c.id FROM actividades a 
                     JOIN cursos c ON a.id_curso = c.id 
                     WHERE a.id = '$id_eliminar' AND c.id_tutor = '$tutor_id'";
    $verificar = mysqli_query($conn, $sql_verificar);
    
    if (mysqli_num_rows($verificar) > 0) {
        // Verificar si hay entregas relacionadas
        $sql_check_entregas = "SELECT COUNT(*) as total FROM entregas WHERE id_actividad = '$id_eliminar'";
        $res_check = mysqli_query($conn, $sql_check_entregas);
        $row_check = mysqli_fetch_assoc($res_check);
        
        if ($row_check['total'] > 0) {
            $error_message = "No se puede eliminar: Hay entregas asociadas a esta actividad.";
        } else {
            $sql_delete = "DELETE FROM actividades WHERE id = '$id_eliminar'";
            
            if (mysqli_query($conn, $sql_delete)) {
                $success_message = "¡Misión eliminada exitosamente!";
            } else {
                $error_message = "Error al eliminar la misión: " . mysqli_error($conn);
            }
        }
    } else {
        $error_message = "No tienes permiso para eliminar esta actividad.";
    }
}

// Consultar actividades con filtro por curso
$sql_base = "SELECT a.*, c.nombre as curso_nombre, c.id as curso_id
        FROM actividades a
        JOIN cursos c ON a.id_curso = c.id
        WHERE c.id_tutor = '$tutor_id'";
        
// Aplicar filtro si está seleccionado
if ($curso_filtro > 0) {
    $sql_base .= " AND c.id = '$curso_filtro'";
}

$sql = $sql_base . " ORDER BY c.nombre, a.fecha_limite ASC";

$res = mysqli_query($conn, $sql);

// Obtener estadísticas por curso
$sql_stats = "SELECT c.id, c.nombre, COUNT(a.id) as total_actividades,
              SUM(CASE WHEN a.fecha_limite < CURDATE() THEN 1 ELSE 0 END) as vencidas
              FROM cursos c
              LEFT JOIN actividades a ON c.id = a.id_curso
              WHERE c.id_tutor = '$tutor_id'
              GROUP BY c.id
              ORDER BY c.nombre ASC";
$res_stats = mysqli_query($conn, $sql_stats);

// Funciones auxiliares
function getActividadIcono($tipo) {
    $iconos = [
        'Quiz' => 'fas fa-question-circle',
        'Video' => 'fas fa-video',
        'Tarea' => 'fas fa-file-upload',
        'Juego' => 'fas fa-gamepad',
        'Lectura' => 'fas fa-book',
        'Examen' => 'fas fa-file-alt'
    ];
    return $iconos[$tipo] ?? 'fas fa-tasks';
}

function getDificultadColor($dificultad) {
    $colores = [
        'Fácil' => 'success',
        'Normal' => 'primary',
        'Difícil' => 'danger'
    ];
    return $colores[$dificultad] ?? 'secondary';
}

// Obtener actividades agrupadas por curso para el filtro
$sql_grouped = "SELECT c.id, c.nombre, GROUP_CONCAT(a.id) as actividades_ids
                FROM cursos c
                LEFT JOIN actividades a ON c.id = a.id_curso
                WHERE c.id_tutor = '$tutor_id'
                GROUP BY c.id
                ORDER BY c.nombre ASC";
$res_grouped = mysqli_query($conn, $sql_grouped);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🛠️ Gestión de Misiones - D&F Mindspace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css">
    <style>
        :root {
            --primary: #2cbaec;
            --secondary: #f0ae2a;
            --accent: #83bf46;
            --danger: #ff6b8b;
            --light-bg: #f7fdfe;
            --card-shadow: 0 10px 30px rgba(44, 186, 236, 0.15);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #f0f9fd 0%, #e6f7fc 100%);
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        .management-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.6s ease-out forwards;
        }
        
        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .header-section {
            text-align: center;
            margin-bottom: 50px;
            position: relative;
        }
        
        .header-section h1 {
            font-family: 'Nunito', sans-serif;
            font-size: 3rem;
            background: linear-gradient(90deg, var(--primary), var(--secondary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 15px;
            animation: gradientText 3s ease infinite;
            background-size: 200% 200%;
        }
        
        @keyframes gradientText {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .header-section p {
            color: #666;
            font-size: 1.2rem;
            max-width: 700px;
            margin: 0 auto;
            line-height: 1.6;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 800;
            margin-bottom: 40px;
            padding: 15px 30px;
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.1), rgba(44, 186, 236, 0.05));
            border-radius: 20px;
            border: 2px solid rgba(44, 186, 236, 0.2);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 8px 20px rgba(44, 186, 236, 0.1);
        }
        
        .back-link:hover {
            color: white;
            background: linear-gradient(135deg, var(--primary), #2ca5d4);
            border-color: var(--primary);
            transform: translateX(-10px) scale(1.05);
            box-shadow: 0 15px 30px rgba(44, 186, 236, 0.3);
        }
        
        .activity-card {
            background: white;
            border-radius: 25px;
            padding: 30px;
            box-shadow: var(--card-shadow);
            border: 3px solid transparent;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            height: 100%;
            position: relative;
            overflow: hidden;
        }
        
        .activity-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            transform: scaleX(0);
            transition: transform 0.4s ease;
        }
        
        .activity-card:hover::before {
            transform: scaleX(1);
        }
        
        .activity-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(44, 186, 236, 0.3);
        }
        
        .course-badge {
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 700;
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.1), rgba(44, 186, 236, 0.05));
            color: var(--primary);
            border: 2px solid rgba(44, 186, 236, 0.2);
            margin-bottom: 20px;
            display: inline-block;
        }
        
        .points-badge {
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 800;
            background: linear-gradient(135deg, rgba(240, 174, 42, 0.15), rgba(240, 174, 42, 0.1));
            color: var(--secondary);
            border: 2px solid rgba(240, 174, 42, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .activity-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: #222;
            margin-bottom: 15px;
            line-height: 1.4;
        }
        
        .activity-description {
            color: #666;
            line-height: 1.6;
            margin-bottom: 25px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .activity-meta {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .activity-type {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            border-radius: 15px;
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.1), rgba(44, 186, 236, 0.05));
            color: var(--primary);
            font-weight: 600;
        }
        
        .activity-difficulty {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            border-radius: 15px;
            font-weight: 600;
        }
        
        .difficulty-facil {
            background: linear-gradient(135deg, rgba(131, 191, 70, 0.15), rgba(131, 191, 70, 0.1));
            color: var(--accent);
            border: 2px solid rgba(131, 191, 70, 0.3);
        }
        
        .difficulty-normal {
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.15), rgba(44, 186, 236, 0.1));
            color: var(--primary);
            border: 2px solid rgba(44, 186, 236, 0.3);
        }
        
        .difficulty-dificil {
            background: linear-gradient(135deg, rgba(255, 107, 139, 0.15), rgba(255, 107, 139, 0.1));
            color: var(--danger);
            border: 2px solid rgba(255, 107, 139, 0.3);
        }
        
        .deadline-warning {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 15px;
            background: linear-gradient(135deg, rgba(255, 107, 139, 0.1), rgba(255, 107, 139, 0.05));
            border-radius: 15px;
            color: var(--danger);
            font-weight: 600;
            border: 2px solid rgba(255, 107, 139, 0.2);
        }
        
        .deadline-normal {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 15px;
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.1), rgba(44, 186, 236, 0.05));
            border-radius: 15px;
            color: var(--primary);
            font-weight: 600;
            border: 2px solid rgba(44, 186, 236, 0.2);
        }
        
        .btn-action-group {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }
        
        .btn-edit {
            background: linear-gradient(90deg, var(--primary), #2ca5d4);
            border: none;
            border-radius: 15px;
            padding: 12px 25px;
            color: white;
            font-weight: 700;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            cursor: pointer;
        }
        
        .btn-edit:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(44, 186, 236, 0.3);
            color: white;
        }
        
        .btn-delete {
            background: linear-gradient(90deg, var(--danger), #ff4757);
            border: none;
            border-radius: 15px;
            padding: 12px 25px;
            color: white;
            font-weight: 700;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            cursor: pointer;
        }
        
        .btn-delete:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(255, 107, 139, 0.3);
            color: white;
        }
        
        .no-activities {
            text-align: center;
            padding: 80px 40px;
            background: white;
            border-radius: 30px;
            box-shadow: var(--card-shadow);
            grid-column: 1 / -1;
        }
        
        .no-activities-icon {
            font-size: 5rem;
            color: var(--primary);
            margin-bottom: 30px;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        
        .no-activities h3 {
            color: #222;
            font-weight: 800;
            margin-bottom: 15px;
            font-size: 2rem;
        }
        
        .no-activities p {
            color: #666;
            font-size: 1.2rem;
            max-width: 500px;
            margin: 0 auto 30px;
            line-height: 1.6;
        }
        
        .btn-create-new {
            background: linear-gradient(90deg, var(--primary), var(--accent));
            border: none;
            border-radius: 20px;
            padding: 18px 35px;
            color: white;
            font-weight: 800;
            font-size: 1.1rem;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 10px 25px rgba(44, 186, 236, 0.3);
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }
        
        .btn-create-new::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: 0.6s;
        }
        
        .btn-create-new:hover::before {
            left: 100%;
        }
        
        .btn-create-new:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 35px rgba(44, 186, 236, 0.4);
            color: white;
            text-decoration: none;
        }
        
        .activities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        /* Filtros de curso */
        .course-filter-section {
            background: white;
            border-radius: 25px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
        }
        
        .filter-title {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 12px 25px;
            border-radius: 15px;
            border: 2px solid rgba(44, 186, 236, 0.2);
            background: white;
            color: var(--primary);
            font-weight: 700;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        
        .filter-btn:hover, .filter-btn.active {
            background: linear-gradient(135deg, var(--primary), #2ca5d4);
            color: white;
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(44, 186, 236, 0.3);
        }
        
        .filter-btn .badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            font-size: 0.8rem;
            padding: 3px 8px;
            border-radius: 10px;
        }
        
        /* Estadísticas de cursos */
        .course-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            border: 2px solid transparent;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--primary);
            margin-bottom: 5px;
            line-height: 1;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .course-section {
            margin-bottom: 50px;
        }
        
        .course-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid rgba(44, 186, 236, 0.1);
        }
        
        .course-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            background: linear-gradient(135deg, var(--primary), #2ca5d4);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
            box-shadow: 0 8px 20px rgba(44, 186, 236, 0.3);
        }
        
        .course-title {
            font-size: 1.8rem;
            font-weight: 800;
            color: #222;
            margin-bottom: 5px;
        }
        
        .course-meta {
            color: #666;
            font-size: 1rem;
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .course-count {
            padding: 5px 12px;
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.1), rgba(44, 186, 236, 0.05));
            border-radius: 12px;
            font-weight: 700;
            color: var(--primary);
            border: 2px solid rgba(44, 186, 236, 0.2);
        }
        
        /* Modal Styles */
        .modal-custom {
            border-radius: 30px;
            border: none;
            overflow: hidden;
        }
        
        .modal-header-custom {
            background: linear-gradient(90deg, var(--primary), var(--accent));
            color: white;
            border: none;
            padding: 30px;
        }
        
        .modal-body-custom {
            padding: 30px;
        }
        
        .modal-footer-custom {
            border: none;
            padding: 20px 30px 30px;
        }
        
        .form-label-custom {
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.1rem;
        }
        
        .form-control-custom, .form-select-custom {
            border: 2px solid rgba(44, 186, 236, 0.2);
            border-radius: 15px;
            padding: 16px 20px;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            font-size: 1.05rem;
            background: rgba(255, 255, 255, 0.9);
        }
        
        .form-control-custom:focus, .form-select-custom:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(44, 186, 236, 0.25);
            transform: translateY(-2px);
            background: white;
        }
        
        .btn-submit {
            background: linear-gradient(90deg, var(--primary), var(--accent));
            border: none;
            border-radius: 20px;
            padding: 15px 35px;
            color: white;
            font-weight: 800;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        
        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(44, 186, 236, 0.3);
        }
        
        .btn-cancel {
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.1), rgba(44, 186, 236, 0.05));
            color: var(--primary);
            border: 2px solid rgba(44, 186, 236, 0.3);
            border-radius: 20px;
            padding: 15px 35px;
            font-weight: 700;
            transition: all 0.3s ease;
        }
        
        .btn-cancel:hover {
            background: linear-gradient(135deg, var(--primary), #2ca5d4);
            color: white;
            border-color: var(--primary);
        }
        
        .delete-confirmation {
            text-align: center;
            padding: 20px;
        }
        
        .delete-icon {
            font-size: 4rem;
            color: var(--danger);
            margin-bottom: 20px;
            animation: shake 0.5s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        
        .floating-particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }
        
        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: var(--primary);
            border-radius: 50%;
            opacity: 0.3;
            animation: floatParticle 20s infinite linear;
        }
        
        @keyframes floatParticle {
            0% { transform: translateY(100vh) translateX(0) rotate(0deg); }
            100% { transform: translateY(-100px) translateX(100px) rotate(360deg); }
        }
        
        .notification-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        
        .notification {
            padding: 20px 30px;
            border-radius: 20px;
            color: white;
            margin-bottom: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            animation: slideInRight 0.3s ease-out;
            display: flex;
            align-items: center;
            gap: 15px;
            max-width: 400px;
        }
        
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .notification.success {
            background: linear-gradient(135deg, var(--accent), #6aab39);
        }
        
        .notification.danger {
            background: linear-gradient(135deg, var(--danger), #ff4757);
        }
        
        .notification.warning {
            background: linear-gradient(135deg, var(--secondary), #f5c15d);
        }
        
        .alert-custom {
            border-radius: 20px;
            border: none;
            padding: 25px;
            box-shadow: var(--card-shadow);
            animation: fadeInDown 0.5s ease-out;
            margin-bottom: 30px;
        }
        
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 768px) {
            .management-container {
                padding: 20px 15px;
            }
            
            .header-section h1 {
                font-size: 2.2rem;
            }
            
            .activities-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .activity-card {
                padding: 20px;
            }
            
            .btn-action-group {
                flex-direction: column;
            }
            
            .filter-buttons {
                justify-content: center;
            }
            
            .course-stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Partículas flotantes -->
    <div class="floating-particles" id="particles"></div>
    
    <!-- Notificaciones -->
    <div class="notification-container" id="notificationContainer"></div>
    
    <div class="management-container">
        <!-- Botón para volver -->
        <a href="dashboard_tutor.php" class="back-link animate__animated animate__fadeInLeft">
            <i class="fas fa-arrow-left"></i> Volver al Panel
        </a>
        
        <!-- Encabezado -->
        <div class="header-section">
            <h1>🛠️ Gestión de Misiones</h1>
            <p>Crea, edita y elimina actividades para tus cursos. ¡Gestiona las aventuras de tus exploradores!</p>
        </div>
        
        <!-- Mensajes de éxito/error -->
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-custom animate__animated animate__fadeInDown">
                <div class="d-flex align-items-center">
                    <i class="fas fa-check-circle fa-3x me-3 text-success"></i>
                    <div>
                        <h4 class="alert-heading mb-2 fw-bold">¡Éxito!</h4>
                        <p class="mb-0 fs-5"><?php echo $success_message; ?></p>
                    </div>
                </div>
            </div>
        <?php elseif ($error_message): ?>
            <div class="alert alert-danger alert-custom animate__animated animate__shakeX">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle fa-3x me-3 text-danger"></i>
                    <div>
                        <h4 class="alert-heading mb-2 fw-bold">¡Error!</h4>
                        <p class="mb-0 fs-5"><?php echo htmlspecialchars($error_message); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Botón Crear Nueva -->
        <div class="d-flex justify-content-end mb-4">
            <a href="crear_actividad.php" class="btn-create-new animate__animated animate__fadeInRight">
                <i class="fas fa-plus-circle"></i> CREAR NUEVA MISIÓN
            </a>
        </div>
        
        <!-- Sección de Filtros -->
        <div class="course-filter-section animate__animated animate__fadeInUp">
            <div class="filter-title">
                <i class="fas fa-filter"></i>
                Filtrar por Curso
            </div>
            <div class="filter-buttons">
                <a href="?curso=0" class="filter-btn <?php echo $curso_filtro == 0 ? 'active' : ''; ?>">
                    <i class="fas fa-layer-group"></i>
                    Todos los Cursos
                    <span class="badge"><?php echo mysqli_num_rows($res); ?></span>
                </a>
                
                <?php while($stats = mysqli_fetch_assoc($res_stats)): 
                    $es_activo = $curso_filtro == $stats['id'];
                ?>
                    <a href="?curso=<?php echo $stats['id']; ?>" 
                       class="filter-btn <?php echo $es_activo ? 'active' : ''; ?>">
                        <i class="fas fa-book"></i>
                        <?php echo htmlspecialchars($stats['nombre']); ?>
                        <span class="badge"><?php echo $stats['total_actividades']; ?></span>
                    </a>
                <?php endwhile; ?>
            </div>
        </div>
        
        <!-- Mostrar actividades -->
        <?php if(mysqli_num_rows($res) > 0): ?>
            <?php
            // Agrupar actividades por curso
            $actividades_por_curso = [];
            mysqli_data_seek($res, 0);
            while($act = mysqli_fetch_assoc($res)) {
                $curso_id = $act['curso_id'];
                if (!isset($actividades_por_curso[$curso_id])) {
                    $actividades_por_curso[$curso_id] = [
                        'nombre' => $act['curso_nombre'],
                        'actividades' => []
                    ];
                }
                $actividades_por_curso[$curso_id]['actividades'][] = $act;
            }
            
            // Mostrar secciones por curso
            foreach($actividades_por_curso as $curso_id => $curso_data):
                $total_actividades = count($curso_data['actividades']);
            ?>
                <div class="course-section animate__animated animate__fadeInUp">
                    <div class="course-header">
                        <div class="course-icon">
                            <i class="fas fa-book"></i>
                        </div>
                        <div>
                            <div class="course-title"><?php echo htmlspecialchars($curso_data['nombre']); ?></div>
                            <div class="course-meta">
                                <span class="course-count">
                                    <i class="fas fa-tasks me-1"></i> <?php echo $total_actividades; ?> misiones
                                </span>
                                <?php if($curso_filtro == 0): ?>
                                    <a href="?curso=<?php echo $curso_id; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye me-1"></i> Ver solo este curso
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="activities-grid">
                        <?php foreach($curso_data['actividades'] as $act): 
                            $icono = getActividadIcono($act['tipo']);
                            $dificultad_color = getDificultadColor($act['dificultad']);
                            
                            // Verificar si la fecha límite está próxima
                            $fecha_limite = new DateTime($act['fecha_limite']);
                            $hoy = new DateTime();
                            $diferencia = $hoy->diff($fecha_limite);
                            $dias_restantes = $diferencia->days;
                            $es_proxima = $dias_restantes <= 3;
                        ?>
                            <div class="activity-card" id="activity-<?php echo $act['id']; ?>">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <span class="course-badge">
                                        <i class="fas fa-book me-2"></i><?php echo htmlspecialchars($act['curso_nombre']); ?>
                                    </span>
                                    <span class="points-badge">
                                        <i class="fas fa-trophy"></i> <?php echo $act['puntos']; ?> XP
                                    </span>
                                </div>
                                
                                <h3 class="activity-title"><?php echo htmlspecialchars($act['titulo']); ?></h3>
                                
                                <?php if(!empty($act['descripcion'])): ?>
                                    <p class="activity-description"><?php echo htmlspecialchars($act['descripcion']); ?></p>
                                <?php endif; ?>
                                
                                <div class="activity-meta">
                                    <span class="activity-type">
                                        <i class="<?php echo str_replace('fas fa-', '', $icono); ?> me-2"></i>
                                        <?php echo htmlspecialchars($act['tipo']); ?>
                                    </span>
                                    <span class="activity-difficulty difficulty-<?php echo strtolower($act['dificultad']); ?>">
                                        <?php if($act['dificultad'] == 'Fácil'): ?>
                                            <i class="fas fa-star"></i>
                                        <?php elseif($act['dificultad'] == 'Normal'): ?>
                                            <i class="fas fa-star"></i><i class="fas fa-star"></i>
                                        <?php elseif($act['dificultad'] == 'Difícil'): ?>
                                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($act['dificultad']); ?>
                                    </span>
                                </div>
                                
                                <div class="<?php echo $es_proxima ? 'deadline-warning' : 'deadline-normal'; ?>">
                                    <i class="fas fa-<?php echo $es_proxima ? 'exclamation-triangle' : 'calendar-alt'; ?>"></i>
                                    <div>
                                        <strong>Fecha Límite:</strong>
                                        <div><?php echo date('d/m/Y', strtotime($act['fecha_limite'])); ?></div>
                                        <?php if($es_proxima): ?>
                                            <small class="text-danger fw-bold">(<?php echo $dias_restantes; ?> días restantes)</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="btn-action-group">
                                    <button type="button" class="btn-edit" onclick="openEditModal(<?php echo $act['id']; ?>)">
                                        <i class="fas fa-edit"></i> Editar
                                    </button>
                                    <button type="button" class="btn-delete" onclick="openDeleteModal(<?php echo $act['id']; ?>, '<?php echo htmlspecialchars(addslashes($act['titulo'])); ?>')">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
        <?php else: ?>
            <div class="no-activities animate__animated animate__fadeIn">
                <div class="no-activities-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <h3><?php echo $curso_filtro > 0 ? '¡No hay misiones en este curso!' : '¡Comienza la Aventura! 🚀'; ?></h3>
                <p>
                    <?php if($curso_filtro > 0): ?>
                        Este curso aún no tiene misiones. ¡Crea la primera para tus exploradores!
                    <?php else: ?>
                        No has creado misiones todavía. Diseña actividades emocionantes para que tus exploradores aprendan divirtiéndose.
                    <?php endif; ?>
                </p>
                <a href="crear_actividad.php" class="btn-create-new">
                    <i class="fas fa-plus-circle"></i> CREAR NUEVA MISIÓN
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal para Editar Actividad -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content modal-custom">
                <div class="modal-header modal-header-custom">
                    <h3 class="modal-title fw-bold"><i class="fas fa-edit me-2"></i>Editar Misión</h3>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editForm" method="POST" action="">
                    <div class="modal-body modal-body-custom">
                        <input type="hidden" name="editar_actividad" value="1">
                        <input type="hidden" name="id_actividad" id="editId" value="<?php echo isset($activity_data['id']) ? $activity_data['id'] : ''; ?>">
                        
                        <div class="row">
                            <div class="col-md-8 mb-4">
                                <label class="form-label-custom">
                                    <i class="fas fa-heading"></i>
                                    Título de la Misión *
                                </label>
                                <input type="text" name="titulo" id="editTitulo" class="form-control-custom" 
                                       value="<?php echo isset($activity_data['titulo']) ? htmlspecialchars($activity_data['titulo']) : ''; ?>" 
                                       required maxlength="200">
                            </div>
                            <div class="col-md-4 mb-4">
                                <label class="form-label-custom">
                                    <i class="fas fa-trophy"></i>
                                    Puntos (XP) *
                                </label>
                                <input type="number" name="puntos" id="editPuntos" class="form-control-custom" 
                                       value="<?php echo isset($activity_data['puntos']) ? $activity_data['puntos'] : '10'; ?>" 
                                       required min="1" max="1000">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label-custom">
                                <i class="fas fa-book"></i>
                                Curso *
                            </label>
                            <select name="id_curso" id="editCurso" class="form-select-custom" required>
                                <option value="">Selecciona un curso</option>
                                <?php 
                                mysqli_data_seek($res_cursos, 0);
                                while($c = mysqli_fetch_assoc($res_cursos)): 
                                    $selected = (isset($activity_data['id_curso']) && $activity_data['id_curso'] == $c['id']) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($c['nombre']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label-custom">
                                <i class="fas fa-align-left"></i>
                                Descripción
                            </label>
                            <textarea name="descripcion" id="editDescripcion" class="form-control-custom" rows="4" maxlength="1000"><?php echo isset($activity_data['descripcion']) ? htmlspecialchars($activity_data['descripcion']) : ''; ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-4">
                                <label class="form-label-custom">
                                    <i class="fas fa-puzzle-piece"></i>
                                    Tipo de Actividad *
                                </label>
                                <select name="tipo" id="editTipo" class="form-select-custom" required>
                                    <option value="Quiz" <?php echo (isset($activity_data['tipo']) && $activity_data['tipo'] == 'Quiz') ? 'selected' : ''; ?>>🔍 Cuestionario Interactivo</option>
                                    <option value="Video" <?php echo (isset($activity_data['tipo']) && $activity_data['tipo'] == 'Video') ? 'selected' : ''; ?>>🎥 Video con Preguntas</option>
                                    <option value="Tarea" <?php echo (isset($activity_data['tipo']) && $activity_data['tipo'] == 'Tarea') ? 'selected' : ''; ?>>📤 Subir Archivo/Tarea</option>
                                    <option value="Juego" <?php echo (isset($activity_data['tipo']) && $activity_data['tipo'] == 'Juego') ? 'selected' : ''; ?>>🎮 Juego Educativo</option>
                                    <option value="Lectura" <?php echo (isset($activity_data['tipo']) && $activity_data['tipo'] == 'Lectura') ? 'selected' : ''; ?>>📚 Lectura y Reflexión</option>
                                    <option value="Examen" <?php echo (isset($activity_data['tipo']) && $activity_data['tipo'] == 'Examen') ? 'selected' : ''; ?>>📝 Evaluación</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-4">
                                <label class="form-label-custom">
                                    <i class="fas fa-chart-line"></i>
                                    Dificultad *
                                </label>
                                <select name="dificultad" id="editDificultad" class="form-select-custom" required>
                                    <option value="Fácil" <?php echo (isset($activity_data['dificultad']) && $activity_data['dificultad'] == 'Fácil') ? 'selected' : ''; ?>>⭐ Fácil</option>
                                    <option value="Normal" <?php echo (isset($activity_data['dificultad']) && $activity_data['dificultad'] == 'Normal') ? 'selected' : ''; ?>>⭐⭐ Normal</option>
                                    <option value="Difícil" <?php echo (isset($activity_data['dificultad']) && $activity_data['dificultad'] == 'Difícil') ? 'selected' : ''; ?>>⭐⭐⭐ Difícil</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-4">
                                <label class="form-label-custom">
                                    <i class="fas fa-calendar-alt"></i>
                                    Fecha Límite *
                                </label>
                                <input type="date" name="fecha_limite" id="editFechaLimite" class="form-control-custom" 
                                       value="<?php echo isset($activity_data['fecha_formatted']) ? $activity_data['fecha_formatted'] : ''; ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer modal-footer-custom">
                        <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-save me-2"></i>Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal para Confirmar Eliminación -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-custom">
                <div class="modal-header modal-header-custom">
                    <h3 class="modal-title fw-bold"><i class="fas fa-trash me-2"></i>Confirmar Eliminación</h3>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body modal-body-custom">
                    <div class="delete-confirmation">
                        <div class="delete-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h4 class="fw-bold mb-3" id="deleteTitle"></h4>
                        <p class="text-muted">¿Estás seguro de que quieres eliminar esta misión? Esta acción no se puede deshacer.</p>
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Advertencia:</strong> Si hay entregas asociadas, no podrás eliminarla.
                        </div>
                    </div>
                </div>
                <div class="modal-footer modal-footer-custom">
                    <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <a href="#" class="btn-submit" id="confirmDeleteBtn">
                        <i class="fas fa-trash me-2"></i>Sí, Eliminar
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Variables
        let editModal = null;
        let deleteModal = null;
        let currentDeleteId = null;
        let deleteModalInstance = null;
        let editModalInstance = null;
        
        // Crear partículas flotantes
        function crearParticulas() {
            const container = document.getElementById('particles');
            for (let i = 0; i < 30; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.top = Math.random() * 100 + '%';
                particle.style.background = Math.random() > 0.5 ? 'var(--primary)' : 'var(--secondary)';
                particle.style.width = Math.random() * 4 + 2 + 'px';
                particle.style.height = particle.style.width;
                particle.style.opacity = Math.random() * 0.3 + 0.1;
                particle.style.animationDelay = Math.random() * 20 + 's';
                particle.style.animationDuration = Math.random() * 10 + 15 + 's';
                container.appendChild(particle);
            }
        }
        
        // Mostrar notificación
        function showNotification(message, type = 'success') {
            const container = document.getElementById('notificationContainer');
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'exclamation-triangle'} fa-2x"></i>
                <div>
                    <strong>${type === 'success' ? '¡Éxito!' : type === 'danger' ? '¡Error!' : '¡Advertencia!'}</strong>
                    <div>${message}</div>
                </div>
                <button class="btn btn-sm btn-light ms-auto" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            container.appendChild(notification);
            
            // Auto-remover después de 5 segundos
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.style.animation = 'slideInRight 0.3s ease-out reverse';
                    setTimeout(() => notification.remove(), 300);
                }
            }, 5000);
        }
        
        // Abrir modal de edición
        function openEditModal(activityId) {
            // Establecer el ID en el formulario
            document.getElementById('editId').value = activityId;
            
            // Cargar datos vía URL
            const cursoFiltro = new URLSearchParams(window.location.search).get('curso') || 0;
            window.location.href = `?curso=${cursoFiltro}&edit_id=${activityId}#editModal`;
        }
        
        // Abrir modal de eliminación
        function openDeleteModal(activityId, activityTitle) {
            currentDeleteId = activityId;
            document.getElementById('deleteTitle').textContent = `¿Eliminar "${activityTitle}"?`;
            
            // Obtener parámetro de curso actual para mantener el filtro
            const cursoFiltro = new URLSearchParams(window.location.search).get('curso') || 0;
            document.getElementById('confirmDeleteBtn').href = `?curso=${cursoFiltro}&eliminar=${activityId}`;
            
            deleteModalInstance = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModalInstance.show();
        }
        
        // Manejar eliminación con animación
        function handleDelete() {
            if (!currentDeleteId) return;
            
            const activityCard = document.getElementById(`activity-${currentDeleteId}`);
            if (activityCard) {
                // Aplicar animación de salida
                activityCard.style.animation = 'fadeOut 0.5s ease-out forwards';
                
                // Después de la animación, redirigir
                setTimeout(() => {
                    const cursoFiltro = new URLSearchParams(window.location.search).get('curso') || 0;
                    window.location.href = `?curso=${cursoFiltro}&eliminar=${currentDeleteId}`;
                }, 500);
            } else {
                const cursoFiltro = new URLSearchParams(window.location.search).get('curso') || 0;
                window.location.href = `?curso=${cursoFiltro}&eliminar=${currentDeleteId}`;
            }
            
            if (deleteModalInstance) {
                deleteModalInstance.hide();
            }
        }
        
        // Validar formulario de edición
        document.getElementById('editForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validaciones
            const titulo = document.getElementById('editTitulo').value.trim();
            const puntos = parseInt(document.getElementById('editPuntos').value);
            const curso = document.getElementById('editCurso').value;
            const fecha = document.getElementById('editFechaLimite').value;
            
            let errors = [];
            
            if (titulo.length < 3) {
                errors.push('El título debe tener al menos 3 caracteres');
            }
            
            if (puntos < 1 || puntos > 1000) {
                errors.push('Los puntos deben estar entre 1 y 1000');
            }
            
            if (!curso) {
                errors.push('Debes seleccionar un curso');
            }
            
            if (!fecha) {
                errors.push('Debes seleccionar una fecha límite');
            }
            
            if (errors.length > 0) {
                showNotification(errors.join(', '), 'warning');
                return;
            }
            
            // Cambiar texto del botón
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Guardando...';
            submitBtn.disabled = true;
            
            // Enviar formulario
            this.submit();
        });
        
        // Filtrar actividades por búsqueda (opcional)
        function setupSearchFilter() {
            const searchInput = document.createElement('input');
            searchInput.type = 'text';
            searchInput.className = 'form-control mb-3';
            searchInput.placeholder = '🔍 Buscar misiones por título...';
            searchInput.style.borderRadius = '15px';
            searchInput.style.padding = '12px 20px';
            searchInput.style.border = '2px solid rgba(44, 186, 236, 0.2)';
            
            // Insertar después del botón de crear
            const createBtn = document.querySelector('.btn-create-new');
            if (createBtn && createBtn.parentNode) {
                createBtn.parentNode.insertBefore(searchInput, createBtn);
            }
            
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const activities = document.querySelectorAll('.activity-card');
                
                activities.forEach(activity => {
                    const title = activity.querySelector('.activity-title').textContent.toLowerCase();
                    const description = activity.querySelector('.activity-description')?.textContent.toLowerCase() || '';
                    
                    if (title.includes(searchTerm) || description.includes(searchTerm)) {
                        activity.style.display = 'block';
                        activity.classList.add('animate__fadeIn');
                    } else {
                        activity.style.display = 'none';
                        activity.classList.remove('animate__fadeIn');
                    }
                });
            });
        }
        
        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            crearParticulas();
            
            // Configurar botón de confirmación de eliminación
            document.getElementById('confirmDeleteBtn')?.addEventListener('click', function(e) {
                e.preventDefault();
                handleDelete();
            });
            
            // Inicializar modales de Bootstrap
            const editModalElement = document.getElementById('editModal');
            const deleteModalElement = document.getElementById('deleteModal');
            
            if (editModalElement) {
                editModalInstance = new bootstrap.Modal(editModalElement);
            }
            
            if (deleteModalElement) {
                deleteModalInstance = new bootstrap.Modal(deleteModalElement);
            }
            
            // Si hay parámetro edit_id en la URL, abrir modal automáticamente
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('edit_id')) {
                if (editModalInstance) {
                    editModalInstance.show();
                }
            }
            
            // Agregar animaciones a las tarjetas
            const cards = document.querySelectorAll('.activity-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
            
            // Mostrar notificación si hay mensajes
            <?php if ($success_message): ?>
                showNotification('<?php echo addslashes($success_message); ?>', 'success');
            <?php elseif ($error_message): ?>
                showNotification('<?php echo addslashes($error_message); ?>', 'danger');
            <?php endif; ?>
            
            // Opcional: Configurar búsqueda
            // setupSearchFilter();
        });
        
        // Agregar estilos para animación de fadeOut
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeOut {
                from { opacity: 1; transform: translateY(0); }
                to { opacity: 0; transform: translateY(-20px); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>