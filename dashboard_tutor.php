<?php
include 'php/config.php';
session_start();

// Seguridad: Solo tutores
if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'tutor') {
    header("Location: index.php");
    exit();
}

$tutor_id = $_SESSION['user_id'];

// --- OPTIMIZACIÓN: Consultas combinadas para mejor rendimiento ---
$queries = [
    'cursos' => "SELECT COUNT(*) as total FROM cursos WHERE id_tutor = '$tutor_id'",
    'alumnos' => "SELECT COUNT(DISTINCT id_alumno) as total FROM inscripciones i 
                   JOIN cursos c ON i.id_curso = c.id WHERE c.id_tutor = '$tutor_id'",
    'pendientes' => "SELECT COUNT(*) as total FROM entregas e
                     JOIN actividades a ON e.id_actividad = a.id
                     JOIN cursos c ON a.id_curso = c.id
                     WHERE c.id_tutor = '$tutor_id' AND e.estado = 'pendiente'"
];

$stats = [];
foreach ($queries as $key => $sql) {
    $res = mysqli_query($conn, $sql);
    $stats[$key] = mysqli_fetch_assoc($res)['total'];
    mysqli_free_result($res);
}

// Obtener lista de cursos con información adicional
$query_lista = "SELECT c.*, COUNT(i.id) as inscritos 
                FROM cursos c 
                LEFT JOIN inscripciones i ON c.id = i.id_curso 
                WHERE c.id_tutor = '$tutor_id' 
                GROUP BY c.id 
                ORDER BY c.fecha_creacion DESC";
$res_lista = mysqli_query($conn, $query_lista);

// Datos para gráfico (cursos vs alumnos)
$query_grafico = "SELECT c.nombre, COUNT(i.id) as alumnos 
                  FROM cursos c 
                  LEFT JOIN inscripciones i ON c.id = i.id_curso 
                  WHERE c.id_tutor = '$tutor_id' 
                  GROUP BY c.id 
                  LIMIT 5";
$res_grafico = mysqli_query($conn, $query_grafico);
$datos_grafico = [];
while($row = mysqli_fetch_assoc($res_grafico)) {
    $datos_grafico[] = $row;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Tutor - D&F Mindspace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Poppins:wght@300;400;600;700&family=Fredoka+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css">
<style>
    :root {
        --primary: #2cbaec;      /* Azul principal */
        --secondary: #f0ae2a;    /* Naranja/dorado */
        --accent: #83bf46;       /* Verde éxito */
        --success: #83bf46;      /* Verde (mismo que accent) */
        --warning: #f0ae2a;      /* Naranja (mismo que secondary) */
        --explore: #2cbaec;      /* Azul explorar */
        --create: #f0ae2a;       /* Naranja crear */
        --learn: #83bf46;        /* Verde aprender */
        --light-bg: #f7fdfe;     /* Fondo claro azulado */
        --card-shadow: 0 10px 30px rgba(44, 186, 236, 0.15);
        --sidebar-width: 280px;
        --dark-blue: #1a8db8;    /* Azul más oscuro para hover */
        --dark-orange: #d69925;  /* Naranja más oscuro para hover */
        --dark-green: #6ca839;   /* Verde más oscuro para hover */
    }
    
    body {
        background: linear-gradient(135deg, #f0f9fd 0%, #e6f7fc 100%);
        font-family: 'Poppins', sans-serif;
        min-height: 100vh;
        overflow-x: hidden;
        padding: 0;
        margin: 0;
    }
    
    .sidebar {
        background: linear-gradient(180deg, #FFFFFF 0%, #f5fcfe 100%);
        width: var(--sidebar-width);
        height: 100vh;
        position: fixed;
        left: 0;
        top: 0;
        z-index: 100;
        box-shadow: 5px 0 25px rgba(44, 186, 236, 0.1);
        border-right: 3px solid var(--primary);
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    
    .sidebar-content {
        flex: 1;
        overflow-y: auto;
        padding: 25px 0 20px 0;
    }
    
    .sidebar-footer {
        flex-shrink: 0;
        padding: 20px;
        background: linear-gradient(180deg, transparent, rgba(44, 186, 236, 0.05));
        border-top: 1px solid rgba(44, 186, 236, 0.1);
    }
    
    .sidebar-brand {
        text-align: center;
        padding: 0 20px 30px;
        border-bottom: 2px solid rgba(44, 186, 236, 0.1);
        margin-bottom: 20px;
    }
    
    .logo-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
    }
    
    .logo-main {
        font-family: 'Fredoka One', cursive;
        font-size: 2.5rem;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        line-height: 1;
        letter-spacing: -1px;
        text-shadow: 0 2px 4px rgba(44, 186, 236, 0.2);
    }
    
    .logo-sub {
        font-family: 'Poppins', sans-serif;
        font-size: 1.2rem;
        color: var(--primary);
        font-weight: 600;
        letter-spacing: 8px;
        text-transform: uppercase;
        margin-left: 10px;
        position: relative;
    }
    
    .logo-sub::before {
        content: '';
        position: absolute;
        left: -10px;
        top: 50%;
        width: 6px;
        height: 6px;
        background: var(--secondary);
        border-radius: 50%;
        transform: translateY(-50%);
    }
    
    .logo-sub::after {
        content: '';
        position: absolute;
        right: -10px;
        top: 50%;
        width: 6px;
        height: 6px;
        background: var(--accent);
        border-radius: 50%;
        transform: translateY(-50%);
    }
    
    .tagline {
        color: #666;
        font-size: 0.9rem;
        margin-top: 10px;
        font-weight: 500;
        letter-spacing: 2px;
        text-transform: uppercase;
    }
    
    .tagline span {
        color: var(--explore);
        font-weight: 700;
    }
    
    .tagline span:nth-child(2) {
        color: var(--create);
    }
    
    .tagline span:nth-child(3) {
        color: var(--learn);
    }
    
    .nav-item {
        margin: 8px 15px;
    }
    
    .nav-link {
        color: #555;
        border-radius: 12px;
        padding: 14px 20px;
        font-weight: 600;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 12px;
        border-left: 4px solid transparent;
    }
    
    .nav-link:hover, .nav-link.active {
        background: linear-gradient(90deg, rgba(44, 186, 236, 0.1) 0%, rgba(44, 186, 236, 0.05) 100%);
        color: var(--primary);
        border-left-color: var(--primary);
        transform: translateX(5px);
    }
    
    .nav-link i {
        width: 24px;
        text-align: center;
        color: var(--primary);
    }
    
    /* Estilo específico para el botón de cerrar sesión */
    .logout-link {
        background: linear-gradient(90deg, rgba(255, 87, 87, 0.1) 0%, rgba(255, 87, 87, 0.05) 100%);
        color: #ff5757 !important;
        border-left: 4px solid #ff5757 !important;
        margin-top: 10px;
    }
    
    .logout-link:hover {
        background: linear-gradient(90deg, rgba(255, 87, 87, 0.2) 0%, rgba(255, 87, 87, 0.1) 100%) !important;
        color: #ff3030 !important;
        border-left-color: #ff3030 !important;
    }
    
    .logout-link i {
        color: #ff5757 !important;
    }
    
    .badge-notification {
        background: linear-gradient(90deg, var(--secondary), #f5c15d);
        color: white;
        border-radius: 10px;
        padding: 4px 10px;
        font-size: 0.75rem;
        font-weight: 600;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(240, 174, 42, 0.4); }
        70% { transform: scale(1.05); box-shadow: 0 0 0 6px rgba(240, 174, 42, 0); }
        100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(240, 174, 42, 0); }
    }
    
    .main-content {
        margin-left: var(--sidebar-width);
        padding: 30px;
        width: calc(100% - var(--sidebar-width));
        min-height: 100vh;
    }
    
    .welcome-banner {
        background: linear-gradient(90deg, #ffffff, #f7fdfe);
        color: #333;
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 30px;
        box-shadow: var(--card-shadow);
        position: relative;
        overflow: hidden;
        border: 2px solid rgba(44, 186, 236, 0.1);
    }
    
    .welcome-banner::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 300px;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(44, 186, 236, 0.05));
    }
    
    .banner-title {
        font-family: 'Nunito', sans-serif;
        font-weight: 800;
        color: var(--primary);
        font-size: 2.2rem;
        margin-bottom: 10px;
    }
    
    .banner-subtitle {
        color: #666;
        font-size: 1.1rem;
    }
    
    .stat-card {
        background: white;
        border-radius: 18px;
        padding: 25px;
        box-shadow: var(--card-shadow);
        transition: all 0.3s ease;
        height: 100%;
        border: none;
        position: relative;
        overflow: hidden;
        border-top: 5px solid transparent;
    }
    
    .stat-card:hover {
        transform: translateY(-10px) scale(1.02);
        box-shadow: 0 20px 40px rgba(44, 186, 236, 0.2);
    }
    
    .stat-card:nth-child(1) {
        border-top-color: var(--explore);
    }
    
    .stat-card:nth-child(2) {
        border-top-color: var(--create);
    }
    
    .stat-card:nth-child(3) {
        border-top-color: var(--learn);
    }
    
    .stat-icon {
        width: 70px;
        height: 70px;
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        margin-bottom: 20px;
        background: linear-gradient(135deg, var(--primary), #2ca5d4);
        color: white;
        box-shadow: 0 8px 20px rgba(44, 186, 236, 0.3);
    }
    
    .stat-number {
        font-size: 2.8rem;
        font-weight: 800;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin: 10px 0;
        font-family: 'Nunito', sans-serif;
    }
    
    .action-buttons {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        margin: 30px 0;
    }
    
    .btn-primary-custom {
        background: linear-gradient(90deg, var(--primary), var(--dark-blue));
        border: none;
        border-radius: 15px;
        padding: 14px 28px;
        color: white;
        font-weight: 700;
        transition: all 0.3s ease;
        box-shadow: 0 8px 20px rgba(44, 186, 236, 0.3);
        display: flex;
        align-items: center;
        gap: 8px;
        position: relative;
        overflow: hidden;
    }
    
    .btn-primary-custom::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: 0.5s;
    }
    
    .btn-primary-custom:hover::before {
        left: 100%;
    }
    
    .btn-primary-custom:hover {
        transform: translateY(-3px) scale(1.05);
        box-shadow: 0 12px 25px rgba(44, 186, 236, 0.4);
        color: white;
    }
    
    .btn-outline-custom {
        border: 2px solid var(--primary);
        color: var(--primary);
        border-radius: 15px;
        padding: 12px 24px;
        font-weight: 600;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-outline-custom:hover {
        background: var(--primary);
        color: white;
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(44, 186, 236, 0.2);
    }
    
    .courses-table {
        background: white;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: var(--card-shadow);
        border: 2px solid rgba(44, 186, 236, 0.1);
    }
    
    .table-header {
        background: linear-gradient(90deg, rgba(44, 186, 236, 0.9), rgba(131, 191, 70, 0.9));
        color: white;
        padding: 25px;
        position: relative;
        overflow: hidden;
    }
    
    .table-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path d="M0,0 L100,0 L100,100 Z" fill="rgba(255,255,255,0.1)"/></svg>');
        background-size: cover;
    }
    
    .table th {
        border: none;
        font-weight: 700;
        color: var(--primary);
        padding: 20px;
        background: rgba(44, 186, 236, 0.05);
    }
    
    .table td {
        padding: 20px;
        vertical-align: middle;
        border-bottom: 1px solid rgba(44, 186, 236, 0.1);
    }
    
    .table tbody tr:hover {
        background: rgba(44, 186, 236, 0.05);
        transition: all 0.2s ease;
    }
    
    .badge-level {
        padding: 8px 16px;
        border-radius: 12px;
        font-weight: 600;
        font-family: 'Nunito', sans-serif;
    }
    
    .chart-container {
        background: white;
        border-radius: 20px;
        padding: 30px;
        box-shadow: var(--card-shadow);
        margin-top: 30px;
        border: 2px solid rgba(44, 186, 236, 0.1);
    }
    
    .mindspace-emoji {
        font-size: 2.5rem;
        position: relative;
        display: inline-block;
    }
    
    .mindspace-emoji::after {
        content: '✨';
        position: absolute;
        top: -10px;
        right: -15px;
        font-size: 1.2rem;
        animation: sparkle 2s infinite;
    }
    
    @keyframes sparkle {
        0%, 100% { opacity: 0.5; transform: scale(1); }
        50% { opacity: 1; transform: scale(1.2); }
    }
    
    .floating-shapes {
        position: absolute;
        background: linear-gradient(135deg, rgba(44, 186, 236, 0.1), rgba(240, 174, 42, 0.1));
        border-radius: 30% 70% 70% 30% / 30% 30% 70% 70%;
        animation: float 6s ease-in-out infinite;
        z-index: 0;
    }
    
    @keyframes float {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-20px) rotate(180deg); }
    }
    
    .shape-1 {
        top: 10%;
        right: 5%;
        width: 80px;
        height: 80px;
        animation-delay: 0s;
        background: linear-gradient(135deg, rgba(44, 186, 236, 0.15), rgba(44, 186, 236, 0.05));
    }
    
    .shape-2 {
        bottom: 15%;
        right: 10%;
        width: 60px;
        height: 60px;
        animation-delay: 1s;
        background: linear-gradient(135deg, rgba(240, 174, 42, 0.15), rgba(240, 174, 42, 0.05));
    }
    
    .shape-3 {
        top: 20%;
        left: 5%;
        width: 70px;
        height: 70px;
        animation-delay: 2s;
        background: linear-gradient(135deg, rgba(131, 191, 70, 0.15), rgba(131, 191, 70, 0.05));
    }
    
    @media (max-width: 992px) {
        .sidebar {
            transform: translateX(-100%);
            width: 280px;
        }
        
        .sidebar.active {
            transform: translateX(0);
        }
        
        .main-content {
            margin-left: 0;
            width: 100%;
            padding: 20px;
        }
        
        .menu-toggle {
            display: block;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 101;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            font-size: 1.5rem;
            box-shadow: 0 5px 15px rgba(44, 186, 236, 0.3);
            transition: all 0.3s ease;
        }
        
        .menu-toggle:hover {
            transform: scale(1.1);
        }
        
        .banner-title {
            font-size: 1.8rem;
        }
        
        .stat-number {
            font-size: 2.2rem;
        }
        
        .action-buttons {
            justify-content: center;
        }
    }
    
    .explore-badge {
        background: linear-gradient(90deg, var(--explore), var(--dark-blue));
        color: white;
        box-shadow: 0 4px 10px rgba(44, 186, 236, 0.3);
    }
    
    .create-badge {
        background: linear-gradient(90deg, var(--create), var(--dark-orange));
        color: white;
        box-shadow: 0 4px 10px rgba(240, 174, 42, 0.3);
    }
    
    .learn-badge {
        background: linear-gradient(90deg, var(--learn), var(--dark-green));
        color: white;
        box-shadow: 0 4px 10px rgba(131, 191, 70, 0.3);
    }
    
    .mission-tag {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: rgba(44, 186, 236, 0.1);
        color: var(--explore);
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        border: 1px solid rgba(44, 186, 236, 0.2);
    }
    
    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        margin-right: 10px;
        box-shadow: 0 4px 10px rgba(44, 186, 236, 0.3);
    }
    
    .progress-bar-custom {
        border-radius: 10px;
        overflow: hidden;
        height: 10px;
        background-color: rgba(44, 186, 236, 0.1);
    }
    
    .progress-bar-custom .progress-bar {
        border-radius: 10px;
        background: linear-gradient(90deg, var(--primary), var(--accent));
    }
    
    /* Scrollbar personalizado para sidebar */
    .sidebar-content::-webkit-scrollbar {
        width: 6px;
    }
    
    .sidebar-content::-webkit-scrollbar-track {
        background: rgba(44, 186, 236, 0.05);
        border-radius: 10px;
    }
    
    .sidebar-content::-webkit-scrollbar-thumb {
        background: rgba(44, 186, 236, 0.2);
        border-radius: 10px;
    }
    
    .sidebar-content::-webkit-scrollbar-thumb:hover {
        background: rgba(44, 186, 236, 0.3);
    }
    
    /* Firefox scrollbar */
    .sidebar-content {
        scrollbar-width: thin;
        scrollbar-color: rgba(44, 186, 236, 0.2) rgba(44, 186, 236, 0.05);
    }
</style>
</head>
<body>
    <!-- Botón para móvil -->
    <button class="menu-toggle d-lg-none">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Shapes flotantes -->
    <div class="floating-shapes shape-1"></div>
    <div class="floating-shapes shape-2"></div>
    
<!-- Sidebar -->
<div class="sidebar">
    <!-- Contenido con scroll -->
    <div class="sidebar-content">
        <div class="sidebar-brand">
            <div class="logo-container">
                <div class="logo-main">D&F</div>
                <div class="logo-sub">mindspace</div>
                <div class="tagline">
                    <span>EXPLORA</span> • <span>CREA</span> • <span>APRENDE</span>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <div class="user-avatar d-inline-flex">
                    <?php echo strtoupper(substr($_SESSION['nombre'], 0, 1)); ?>
                </div>
                <h6 class="mt-2 fw-bold">Prof. <?php echo htmlspecialchars($_SESSION['nombre']); ?></h6>
                <small class="text-muted">Tutor Certificado</small>
            </div>
        </div>
        
        <ul class="nav flex-column mt-3">
            <li class="nav-item">
                <a href="dashboard_tutor.php" class="nav-link active">
                    <i class="fas fa-rocket"></i>
                    <span>Mi Panel</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="crear_curso.php" class="nav-link">
                    <i class="fas fa-compass"></i>
                    <span>Explorar Nuevo Curso</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="crear_actividad.php" class="nav-link">
                    <i class="fas fa-lightbulb"></i>
                    <span>Crear Misión</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="gestionar_actividades.php" class="nav-link">
                    <i class="fas fa-tasks"></i>
                    <span>Gestionar Misiones</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="revisar_entregas.php" class="nav-link d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-star"></i>
                        <span>Calificar Misiones</span>
                    </div>
                    <?php if($stats['pendientes'] > 0): ?>
                        <span class="badge-notification"><?php echo $stats['pendientes']; ?> nuevo<?php echo $stats['pendientes'] > 1 ? 's' : ''; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="reporte_alumnos.php" class="nav-link">
                    <i class="fas fa-chart-network"></i>
                    <span>Reportes de Aprendizaje</span>
                </a>
            </li>
            <!-- Agregar algunos items de ejemplo para que haya scroll -->
            <li class="nav-item">
                <a href="#" class="nav-link">
                    <i class="fas fa-comments"></i>
                    <span>Mensajes</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="configuracion_cuenta.php" class="nav-link">
                    <i class="fas fa-cog"></i>
                    <span>Configuración</span>
                </a>
            </li>
        </ul>
    </div>
    
    <!-- Footer fijo en la parte inferior -->
    <div class="sidebar-footer">
        <a href="logout.php" class="nav-link logout-link">
            <i class="fas fa-sign-out-alt"></i>
            <span>Cerrar Sesión</span>
        </a>
    </div>
</div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Welcome Banner -->
        <div class="welcome-banner animate__animated animate__fadeIn">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="banner-title">
                        ¡Bienvenido al <span class="mindspace-emoji">Mindspace</span>!
                    </h1>
                    <p class="banner-subtitle">
                        Hoy es <strong><?php echo date('l, d \d\e F'); ?></strong> - 
                        Un día perfecto para inspirar mentes curiosas. 
                        <span class="mission-tag"><i class="fas fa-bullseye"></i> <?php echo $stats['cursos']; ?> misiones activas</span>
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <div class="d-flex justify-content-lg-end">
                        <div class="bg-light p-4 rounded-circle">
                            <i class="fas fa-graduation-cap fa-4x" style="color: var(--primary);"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row mb-4 g-4 animate__animated animate__fadeInUp">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-compass"></i>
                    </div>
                    <h3 class="stat-number"><?php echo $stats['cursos']; ?></h3>
                    <h6 class="fw-bold">Aventuras de Aprendizaje</h6>
                    <p class="text-muted mb-0">Cursos activos donde los niños exploran</p>
                    <div class="mt-3">
                        <span class="badge explore-badge">EXPLORA</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="stat-number"><?php echo $stats['alumnos']; ?></h3>
                    <h6 class="fw-bold">Pequeños Exploradores</h6>
                    <p class="text-muted mb-0">Mentes curiosas en tu aventura</p>
                    <div class="mt-3">
                        <span class="badge create-badge">CREA</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <h3 class="stat-number"><?php echo $stats['pendientes']; ?></h3>
                    <h6 class="fw-bold">Misiones por Calificar</h6>
                    <p class="text-muted mb-0">Logros esperando tu reconocimiento</p>
                    <div class="mt-3">
                        <span class="badge learn-badge">APRENDE</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Action Buttons -->
        <div class="action-buttons animate__animated animate__fadeIn">
            <a href="revisar_entregas.php" class="btn btn-primary-custom">
                <i class="fas fa-star"></i> Revisar Misiones
                <?php if($stats['pendientes'] > 0): ?>
                    <span class="badge bg-white text-primary ms-2"><?php echo $stats['pendientes']; ?></span>
                <?php endif; ?>
            </a>
            <a href="crear_actividad.php" class="btn btn-outline-custom">
                <i class="fas fa-plus-circle"></i> Nueva Misión
            </a>
            <a href="crear_curso.php" class="btn btn-outline-custom">
                <i class="fas fa-compass"></i> Nuevo Curso
            </a>
            <a href="reporte_alumnos.php" class="btn btn-outline-custom">
                <i class="fas fa-chart-network"></i> Ver Progresos
            </a>
        </div>
        
        <!-- Courses Table -->
        <div class="courses-table animate__animated animate__fadeIn">
            <div class="table-header">
                <h4 class="mb-2 fw-bold"><i class="fas fa-map me-2"></i> Tus Aventuras de Aprendizaje</h4>
                <p class="mb-0 opacity-75">Gestiona los cursos donde los niños exploran y aprenden</p>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Aventura</th>
                            <th>Nivel</th>
                            <th>Estado</th>
                            <th>Exploradores</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($res_lista) > 0): ?>
                            <?php while($c = mysqli_fetch_assoc($res_lista)): ?>
                            <tr>
                                <td class="fw-bold">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 p-2 rounded me-3">
                                            <i class="fas fa-map-marked-alt text-primary"></i>
                                        </div>
                                        <div>
                                            <div><?php echo htmlspecialchars($c['nombre']); ?></div>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>Creado: <?php echo date('d/m/Y', strtotime($c['fecha_creacion'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $badge_class = '';
                                    switch(strtolower($c['nivel'])) {
                                        case 'básico': $badge_class = 'explore-badge'; break;
                                        case 'intermedio': $badge_class = 'create-badge'; break;
                                        case 'avanzado': $badge_class = 'learn-badge'; break;
                                        default: $badge_class = 'bg-secondary';
                                    }
                                    ?>
                                    <span class="badge badge-level <?php echo $badge_class; ?>">
                                        <i class="fas fa-level-up-alt me-1"></i><?php echo htmlspecialchars($c['nivel']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($c['activo']): ?>
                                        <span class="text-success fw-bold">
                                            <i class="fas fa-check-circle text-success me-2"></i>Explorando
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">
                                            <i class="fas fa-pause-circle text-secondary me-2"></i>En pausa
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="progress flex-grow-1 me-3 progress-bar-custom">
                                            <?php 
                                            $porcentaje = $c['inscritos'] > 0 ? min(($c['inscritos'] / 20) * 100, 100) : 0;
                                            $color = $porcentaje < 30 ? 'bg-warning' : ($porcentaje < 70 ? 'bg-info' : 'bg-success');
                                            ?>
                                            <div class="progress-bar <?php echo $color; ?>" style="width: <?php echo $porcentaje; ?>%"></div>
                                        </div>
                                        <div class="text-center">
                                            <span class="fw-bold d-block"><?php echo $c['inscritos']; ?></span>
                                            <small class="text-muted">niños</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="editar_curso.php?id=<?php echo $c['id']; ?>" class="btn btn-outline-primary" title="Editar Aventura">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div class="py-4">
                                        <i class="fas fa-compass fa-3x text-muted mb-3 opacity-50"></i>
                                        <h5 class="fw-bold text-primary">¡Comienza la Aventura!</h5>
                                        <p class="text-muted">Aún no has creado cursos. ¡Inspira a los niños con nuevas exploraciones!</p>
                                        <a href="crear_curso.php" class="btn btn-primary-custom mt-3">
                                            <i class="fas fa-plus me-2"></i>Crear Primera Aventura
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Chart Section -->
        <?php if(!empty($datos_grafico)): ?>
        <div class="chart-container animate__animated animate__fadeIn">
            <h5 class="fw-bold mb-4"><i class="fas fa-chart-network me-2"></i> Mapa de Exploradores</h5>
            <p class="text-muted mb-4">Distribución de niños en tus aventuras de aprendizaje</p>
            <div class="row align-items-center">
                <div class="col-md-8">
                    <canvas id="courseChart" height="180"></canvas>
                </div>
                <div class="col-md-4">
                    <div class="p-3 bg-light rounded">
                        <h6 class="fw-bold text-primary">Resumen Mindspace</h6>
                        <?php 
                        $total_alumnos_chart = 0;
                        foreach($datos_grafico as $dato) {
                            $total_alumnos_chart += $dato['alumnos'];
                        }
                        ?>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total Exploradores:</span>
                                <strong><?php echo $total_alumnos_chart; ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Aventuras Activas:</span>
                                <strong><?php echo count($datos_grafico); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Promedio por Aventura:</span>
                                <strong><?php echo round($total_alumnos_chart / count($datos_grafico), 1); ?></strong>
                            </div>
                        </div>
                        <hr class="my-3">
                        <small class="text-muted">
                            <i class="fas fa-lightbulb text-warning me-1"></i>
                            Cada punto en el gráfico representa una mente curiosa explorando
                        </small>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Toggle sidebar for mobile
        document.querySelector('.menu-toggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
            this.innerHTML = document.querySelector('.sidebar').classList.contains('active') 
                ? '<i class="fas fa-times"></i>' 
                : '<i class="fas fa-bars"></i>';
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const menuToggle = document.querySelector('.menu-toggle');
            
            if (window.innerWidth < 992 && 
                !sidebar.contains(event.target) && 
                !menuToggle.contains(event.target) && 
                sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
            }
        });
        
        // Chart initialization
        <?php if(!empty($datos_grafico)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('courseChart').getContext('2d');
            const chartData = {
                labels: [<?php echo '"' . implode('","', array_column($datos_grafico, 'nombre')) . '"'; ?>],
                datasets: [{
                    data: [<?php echo implode(',', array_column($datos_grafico, 'alumnos')); ?>],
                    backgroundColor: [
                        'rgba(108, 99, 255, 0.8)',
                        'rgba(255, 101, 132, 0.8)',
                        'rgba(76, 175, 80, 0.8)',
                        'rgba(255, 152, 0, 0.8)',
                        'rgba(74, 111, 165, 0.8)'
                    ],
                    borderColor: [
                        'rgba(108, 99, 255, 1)',
                        'rgba(255, 101, 132, 1)',
                        'rgba(76, 175, 80, 1)',
                        'rgba(255, 152, 0, 1)',
                        'rgba(74, 111, 165, 1)'
                    ],
                    borderWidth: 2,
                    hoverOffset: 20,
                    borderRadius: 10
                }]
            };
            
            new Chart(ctx, {
                type: 'polarArea',
                data: chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                font: {
                                    family: "'Poppins', sans-serif",
                                    size: 12
                                },
                                padding: 20,
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0,0,0,0.8)',
                            titleFont: {
                                family: "'Poppins', sans-serif",
                                size: 13
                            },
                            bodyFont: {
                                family: "'Poppins', sans-serif",
                                size: 12
                            },
                            callbacks: {
                                label: function(context) {
                                    return `${context.label}: ${context.raw} exploradores`;
                                }
                            }
                        }
                    },
                    animation: {
                        animateRotate: true,
                        animateScale: true,
                        duration: 2000,
                        easing: 'easeOutQuart'
                    },
                    scales: {
                        r: {
                            grid: {
                                color: 'rgba(74, 111, 165, 0.1)'
                            },
                            ticks: {
                                backdropColor: 'transparent',
                                font: {
                                    family: "'Poppins', sans-serif"
                                }
                            }
                        }
                    }
                }
            });
        });
        <?php endif; ?>
        
        // Add animations on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate__animated', 'animate__fadeInUp');
                }
            });
        }, observerOptions);
        
        // Observe elements for animation
        document.querySelectorAll('.stat-card, .courses-table, .chart-container').forEach(el => {
            observer.observe(el);
        });
        
        // Add subtle hover effect to table rows
        document.querySelectorAll('.table tbody tr').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.01)';
            });
            row.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });
    </script>
</body>
</html>