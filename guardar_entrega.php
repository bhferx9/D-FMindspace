<?php
include 'php/config.php';
include 'php/sendgrid_notificaciones.php';
session_start();

// Verificar que el usuario sea alumno
if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'alumno') {
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: dashboard_alumno.php");
    exit();
}

$id_alumno    = (int)$_SESSION['user_id'];
$id_actividad = isset($_POST['id_actividad']) ? (int)$_POST['id_actividad'] : 0;
$respuesta    = trim($_POST['respuesta'] ?? '');
$errores      = [];

try {
    // Consulta para obtener datos de la actividad y del tutor
    $stmt = $conn->pdo->prepare("
        SELECT 
            a.id,
            a.titulo as actividad_nombre,
            a.descripcion,
            a.puntos,
            a.dificultad,
            a.fecha_limite,
            a.intentos_permitidos,
            a.id_curso,
            c.id_tutor, 
            c.id as id_curso, 
            c.nombre as curso_nombre,
            u.email as tutor_email, 
            u.nombre as tutor_nombre
        FROM actividades a
        JOIN cursos c ON a.id_curso = c.id
        JOIN usuarios u ON c.id_tutor = u.id
        WHERE a.id = :id_actividad
    ");
    $stmt->execute([':id_actividad' => $id_actividad]);

    if ($stmt->rowCount() == 0) {
        $errores[] = "La actividad no existe.";
    } else {
        $actividad = $stmt->fetch(PDO::FETCH_ASSOC);

        // Validar que el alumno esté inscrito en el curso
        $stmt_insc = $conn->pdo->prepare("
            SELECT id FROM inscripciones
            WHERE id_alumno = :id_alumno AND id_curso = :id_curso AND estado = 'activo'
        ");
        $stmt_insc->execute([':id_alumno' => $id_alumno, ':id_curso' => $actividad['id_curso']]);
        if ($stmt_insc->rowCount() == 0) {
            $errores[] = "No estás inscrito en el curso de esta actividad.";
        }

        // Verificar límite de intentos
        $stmt_intentos = $conn->pdo->prepare("
            SELECT COUNT(*) as total FROM entregas
            WHERE id_actividad = :id_actividad AND id_alumno = :id_alumno
        ");
        $stmt_intentos->execute([':id_actividad' => $id_actividad, ':id_alumno' => $id_alumno]);
        $intentos_usados    = $stmt_intentos->fetch(PDO::FETCH_ASSOC)['total'];
        $intentos_permitidos = $actividad['intentos_permitidos'] ?? 1;
        if ($intentos_usados >= $intentos_permitidos) {
            $errores[] = "Has alcanzado el límite de intentos para esta actividad.";
        }

        // Verificar fecha límite
        $fecha_limite = new DateTime($actividad['fecha_limite']);
        $hoy = new DateTime();
        if ($fecha_limite < $hoy) {
            $errores[] = "Esta actividad ya venció. No puedes enviar entregas.";
        }
    }

} catch(PDOException $e) {
    $errores[] = "Error al validar la actividad: " . $e->getMessage();
}

// Manejo de archivo
$nombre_archivo = "";
if (empty($errores) && isset($_FILES['archivo']) && $_FILES['archivo']['error'] == 0) {
    $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip'];
    $archivo_extension = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));

    if (!in_array($archivo_extension, $extensiones_permitidas)) {
        $errores[] = "Tipo de archivo no permitido. Extensiones permitidas: " . implode(', ', $extensiones_permitidas);
    }

    $tamano_maximo = 10 * 1024 * 1024; // 10 MB
    if ($_FILES['archivo']['size'] > $tamano_maximo) {
        $errores[] = "El archivo es demasiado grande. Máximo 10 MB.";
    }

    if (empty($errores)) {
        // Crear carpeta si no existe
        if (!is_dir('uploads/entregas')) {
            mkdir('uploads/entregas', 0777, true);
        }
        $nombre_archivo = time() . "_" . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['archivo']['name']);
        $ruta_destino   = "uploads/entregas/" . $nombre_archivo;
        if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $ruta_destino)) {
            $errores[] = "Error al subir el archivo.";
            $nombre_archivo = "";
        }
    }
}

// Guardar entrega
if (empty($errores)) {
    try {
        $conn->pdo->beginTransaction();
        
        $stmt_insert = $conn->pdo->prepare("
            INSERT INTO entregas (id_alumno, id_actividad, respuesta, archivo, estado, fecha_entrega)
            VALUES (:id_alumno, :id_actividad, :respuesta, :archivo, 'pendiente', CURRENT_TIMESTAMP)
        ");
        $stmt_insert->execute([
            ':id_alumno'    => $id_alumno,
            ':id_actividad' => $id_actividad,
            ':respuesta'    => $respuesta,
            ':archivo'      => $nombre_archivo
        ]);
        
        $conn->pdo->commit();

        // Obtener nombre del alumno
        $stmt_alumno = $conn->pdo->prepare("SELECT nombre, email FROM usuarios WHERE id = :id");
        $stmt_alumno->execute([':id' => $id_alumno]);
        $alumno_data = $stmt_alumno->fetch(PDO::FETCH_ASSOC);
        $alumno_nombre = $alumno_data['nombre'] ?? 'Alumno';
        $alumno_email = $alumno_data['email'] ?? '';

        // ─────────────────────────────────────────────────────────
        // 1. NOTIFICAR AL TUTOR
        // ─────────────────────────────────────────────────────────
        $tutor_email = $actividad['tutor_email'] ?? '';
        $tutor_nombre = $actividad['tutor_nombre'] ?? 'Tutor';
        
        if (!empty($tutor_email)) {
            notificar_tutor_nueva_entrega(
                $tutor_email,
                $tutor_nombre,
                $alumno_nombre,
                $actividad['actividad_nombre'] ?? 'Actividad',
                $actividad['curso_nombre'] ?? 'Curso'
            );
        }
        
        // ─────────────────────────────────────────────────────────
        // 2. NOTIFICAR AL ALUMNO (confirmación)
        // ─────────────────────────────────────────────────────────
        if (!empty($alumno_email)) {
            $subject = "✅ ¡Tu misión ha sido entregada!";
            $html = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
                <div style='background:linear-gradient(90deg,#83bf46,#6aab39);padding:24px 32px;border-radius:12px 12px 0 0;'>
                    <h1 style='color:white;margin:0;font-size:22px;'>D&amp;F Mindspace</h1>
                </div>
                <div style='background:#f9fafb;padding:32px;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 12px 12px;'>
                    <h2 style='color:#1a1a2e;font-size:18px;margin-top:0;'>¡Misión entregada con éxito! 🎉</h2>
                    <p style='color:#555;'>Hola <strong>$alumno_nombre</strong>,</p>
                    <p style='color:#555;'>Has entregado la actividad <strong>{$actividad['actividad_nombre']}</strong> del curso <strong>{$actividad['curso_nombre']}</strong>.</p>
                    <p style='color:#555;'>Tu tutor la revisará y te dará una calificación pronto. ¡Sigue así!</p>
                    <hr style='margin:24px 0;border:none;border-top:1px solid #e5e7eb;'>
                    <p style='color:#aaa;font-size:12px;'>Este es un mensaje automático. No respondas este correo.</p>
                </div>
            </div>";
            
            sendgrid_email($alumno_email, $alumno_nombre, $subject, $html);
        }

        echo "<script>
            alert('🎉 ¡Misión enviada con éxito! Espera a que tu tutor la revise.');
            window.location='dashboard_alumno.php';
        </script>";
        exit();

    } catch(PDOException $e) {
        if ($conn->pdo->inTransaction()) {
            $conn->pdo->rollBack();
        }
        $errores[] = "Error al guardar la entrega: " . $e->getMessage();
    }
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