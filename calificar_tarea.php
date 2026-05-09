<?php
include 'php/config.php';
session_start();

// Verificar que el usuario sea tutor
if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'tutor') {
    header("Location: index.php");
    exit();
}

$tutor_id = $_SESSION['user_id'];
$entrega_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($entrega_id <= 0) {
    die("ID de entrega no válido.");
}

try {
    // Obtener datos de la entrega verificando que pertenezca a un curso del tutor
    $stmt = $conn->pdo->prepare("
        SELECT e.*, u.nombre as alumno_nombre, a.titulo as actividad_titulo, a.puntos as puntos_max,
               c.id_tutor
        FROM entregas e
        JOIN usuarios u ON e.id_alumno = u.id
        JOIN actividades a ON e.id_actividad = a.id
        JOIN cursos c ON a.id_curso = c.id
        WHERE e.id = :entrega_id AND c.id_tutor = :tutor_id
    ");
    $stmt->execute([':entrega_id' => $entrega_id, ':tutor_id' => $tutor_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$data) {
        die("No tienes permiso para calificar esta entrega o la entrega no existe.");
    }
    
} catch(PDOException $e) {
    die("Error al cargar la entrega: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluando a <?php echo htmlspecialchars($data['alumno_nombre']); ?> - D&F Mindspace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css">
    <style>
        :root {
            --primary: #2cbaec;
            --secondary: #f0ae2a;
            --accent: #83bf46;
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

        /* Partículas flotantes */
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

        .creation-container {
            max-width: 900px;
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

        /* Botón volver */
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

        .back-link i {
            transition: all 0.3s ease;
        }

        .back-link:hover i {
            transform: translateX(-5px);
        }

        /* Header */
        .header-section {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
        }

        .floating-emoji {
            font-size: 3rem;
            position: absolute;
            top: -20px;
            right: 10%;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(10deg); }
        }

        .header-section h1 {
            font-family: 'Nunito', sans-serif;
            font-size: 2.8rem;
            background: linear-gradient(90deg, var(--primary), var(--secondary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
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
            font-size: 1.1rem;
        }

        /* Card principal */
        .form-card {
            background: white;
            border-radius: 30px;
            padding: 50px;
            box-shadow: var(--card-shadow);
            border: 2px solid rgba(44, 186, 236, 0.1);
            position: relative;
            overflow: hidden;
            animation: cardPopIn 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes cardPopIn {
            0% { opacity: 0; transform: translateY(30px) scale(0.95); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }

        .form-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--primary), var(--secondary), var(--accent));
        }

        /* Info del alumno */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 35px;
        }

        .info-item {
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.05), rgba(44, 186, 236, 0.02));
            border-radius: 20px;
            padding: 20px 25px;
            border: 2px solid rgba(44, 186, 236, 0.1);
            transition: all 0.3s ease;
        }

        .info-item:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(44, 186, 236, 0.1);
        }

        .info-label {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-value {
            font-size: 1.15rem;
            font-weight: 700;
            color: #222;
        }

        /* Sección de respuesta */
        .section-title {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            color: var(--primary);
            font-weight: 800;
            font-size: 1.3rem;
        }

        .section-title i {
            background: linear-gradient(135deg, var(--primary), #2ca5d4);
            width: 45px;
            height: 45px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            box-shadow: 0 8px 20px rgba(44, 186, 236, 0.3);
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .section-title:hover i {
            transform: rotate(15deg) scale(1.1);
        }

        .response-box {
            background: linear-gradient(135deg, rgba(44, 186, 236, 0.05), rgba(44, 186, 236, 0.02));
            border-left: 6px solid var(--primary);
            padding: 25px 30px;
            border-radius: 0 20px 20px 0;
            margin-bottom: 30px;
            border-top: 2px solid rgba(44, 186, 236, 0.1);
            border-right: 2px solid rgba(44, 186, 236, 0.1);
            border-bottom: 2px solid rgba(44, 186, 236, 0.1);
            transition: all 0.3s ease;
            min-height: 100px;
        }

        .response-box:hover {
            box-shadow: 0 10px 25px rgba(44, 186, 236, 0.1);
            transform: translateY(-2px);
        }

        .response-box p {
            color: #444;
            line-height: 1.8;
            font-size: 1.05rem;
            margin: 0;
        }

        /* Botón archivo adjunto */
        .btn-archivo {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 28px;
            background: linear-gradient(135deg, rgba(240, 174, 42, 0.1), rgba(240, 174, 42, 0.05));
            color: var(--secondary);
            border: 2px solid rgba(240, 174, 42, 0.3);
            border-radius: 16px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            margin-bottom: 35px;
            box-shadow: 0 6px 15px rgba(240, 174, 42, 0.1);
        }

        .btn-archivo:hover {
            background: linear-gradient(135deg, var(--secondary), #f5c15d);
            color: white;
            border-color: var(--secondary);
            transform: translateY(-4px) scale(1.02);
            box-shadow: 0 15px 30px rgba(240, 174, 42, 0.3);
        }

        /* Separador */
        .form-divider {
            border: none;
            border-top: 2px dashed rgba(44, 186, 236, 0.15);
            margin: 35px 0;
        }

        /* Inputs del formulario */
        .form-label {
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.05rem;
        }

        .form-control {
            border: 2px solid rgba(44, 186, 236, 0.2);
            border-radius: 15px;
            padding: 16px 20px;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            font-size: 1.05rem;
            background: rgba(255, 255, 255, 0.9);
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(44, 186, 236, 0.25);
            transform: translateY(-2px);
            background: white;
        }

        .form-control:hover {
            border-color: rgba(44, 186, 236, 0.4);
            box-shadow: 0 5px 15px rgba(44, 186, 236, 0.1);
        }

        /* Puntos badge */
        .puntos-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, rgba(131, 191, 70, 0.12), rgba(131, 191, 70, 0.05));
            color: var(--accent);
            border: 2px solid rgba(131, 191, 70, 0.3);
            border-radius: 14px;
            padding: 8px 18px;
            font-weight: 700;
            font-size: 0.95rem;
        }

        /* Botón enviar */
        .btn-create {
            background: linear-gradient(90deg, var(--primary), var(--accent));
            border: none;
            border-radius: 20px;
            padding: 20px 50px;
            color: white;
            font-weight: 800;
            font-size: 1.2rem;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 12px 30px rgba(44, 186, 236, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            width: 100%;
            margin-top: 30px;
            position: relative;
            overflow: hidden;
            letter-spacing: 0.5px;
        }

        .btn-create::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: 0.6s;
        }

        .btn-create:hover::before {
            left: 100%;
        }

        .btn-create:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 20px 45px rgba(44, 186, 236, 0.5);
            color: white;
        }

        .btn-create:active {
            transform: translateY(-2px);
        }

        .btn-create i {
            transition: all 0.3s ease;
        }

        .btn-create:hover i {
            transform: rotate(15deg) scale(1.2);
        }

        /* Hint motivacional */
        .motivational-hint {
            background: linear-gradient(135deg, rgba(131, 191, 70, 0.08), rgba(131, 191, 70, 0.03));
            border: 2px dashed rgba(131, 191, 70, 0.3);
            border-radius: 16px;
            padding: 16px 22px;
            margin-top: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.95rem;
            color: #5a8a2e;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .creation-container { padding: 20px 15px; }
            .form-card { padding: 30px 20px; }
            .header-section h1 { font-size: 2rem; }
            .info-grid { grid-template-columns: 1fr; }
            .btn-create { padding: 16px 30px; font-size: 1.05rem; }
        }
    </style>
</head>
<body>

    <!-- Partículas flotantes -->
    <div class="floating-particles" id="particles"></div>

    <div class="creation-container">

        <!-- Botón volver -->
        <a href="dashboard_tutor.php" class="back-link animate__animated animate__fadeInLeft">
            <i class="fas fa-arrow-left"></i> Volver al Panel
        </a>

        <!-- Encabezado -->
        <div class="header-section">
            <span class="floating-emoji">⭐</span>
            <h1>✏️ Evaluar Entrega</h1>
            <p>Revisa la respuesta del explorador y asígnale una calificación motivadora.</p>
        </div>

        <!-- Card principal -->
        <div class="form-card">

            <!-- Info del alumno y actividad -->
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-user-graduate"></i> Explorador</div>
                    <div class="info-value"><?php echo htmlspecialchars($data['alumno_nombre']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-tasks"></i> Misión</div>
                    <div class="info-value"><?php echo htmlspecialchars($data['actividad_titulo']); ?></div>
                </div>
            </div>

            <!-- Puntuación máxima -->
            <div class="mb-4">
                <span class="puntos-badge">
                    <i class="fas fa-trophy"></i>
                    Puntuación máxima: <?php echo $data['puntos_max']; ?> puntos (XP)
                </span>
            </div>

            <!-- Respuesta del alumno -->
            <div class="section-title">
                <i class="fas fa-comment-dots"></i>
                <span>Respuesta del Explorador</span>
            </div>

            <div class="response-box">
                <p><?php echo nl2br(htmlspecialchars($data['respuesta'] ?? 'Sin respuesta escrita.')); ?></p>
            </div>

            <!-- Archivo adjunto -->
            <?php if (!empty($data['archivo'])): ?>
            <div class="mb-3">
                <a href="uploads/<?php echo htmlspecialchars($data['archivo']); ?>" class="btn-archivo" target="_blank">
                    <i class="fas fa-file-download"></i> Ver archivo adjunto
                </a>
            </div>
            <?php endif; ?>

            <hr class="form-divider">

            <!-- Formulario de calificación -->
            <div class="section-title">
                <i class="fas fa-star"></i>
                <span>Asignar Calificación</span>
            </div>

            <form action="guardar_evaluacion.php" method="POST">
                <input type="hidden" name="id_entrega" value="<?php echo $entrega_id; ?>">
                <input type="hidden" name="id_alumno" value="<?php echo $data['id_alumno']; ?>">
                <input type="hidden" name="id_actividad" value="<?php echo $data['id_actividad']; ?>">

                <div class="row">
                    <div class="col-md-4 mb-4">
                        <label class="form-label">
                            <i class="fas fa-medal"></i>
                            Calificación (0 – <?php echo $data['puntos_max']; ?>)
                        </label>
                        <input type="number" name="calificacion" class="form-control"
                               min="0" max="<?php echo $data['puntos_max']; ?>" step="0.5" required
                               placeholder="Ej: <?php echo $data['puntos_max']; ?>">
                    </div>

                    <div class="col-md-12 mb-2">
                        <label class="form-label">
                            <i class="fas fa-heart"></i>
                            Comentarios para el explorador
                        </label>
                        <textarea name="comentarios" class="form-control" rows="4"
                                  placeholder="¡Escribe un mensaje motivador! Ej: ¡Excelente trabajo, sigue adelante! 🌟"></textarea>
                        <div class="motivational-hint">
                            <i class="fas fa-lightbulb"></i>
                            Un comentario positivo puede hacer la diferencia en el aprendizaje del niño. ¡Sé su mayor animador! 🌈
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-create">
                    <i class="fas fa-paper-plane"></i> ENVIAR CALIFICACIÓN
                </button>
            </form>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Crear partículas flotantes
        function crearParticulas() {
            const container = document.getElementById('particles');
            for (let i = 0; i < 50; i++) {
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

        document.addEventListener('DOMContentLoaded', function () {
            crearParticulas();

            // Animación en el botón de envío
            const form = document.querySelector('form');
            form.addEventListener('submit', function () {
                const btn = this.querySelector('.btn-create');
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ENVIANDO CALIFICACIÓN...';
                btn.disabled = true;
            });
        });
    </script>
</body>
</html>