<?php
include 'php/config.php';
session_start();

// Seguridad: Solo tutores (padres)
if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'padre') {
    header("Location: index.php");
    exit();
}

$id_padre = $_SESSION['user_id'];

// Obtener datos del padre desde la base de datos (usando PDO)
try {
    $query_padre = "SELECT nombre FROM usuarios WHERE id = ? AND tipo = 'padre'";
    $stmt_padre = $conn->pdo->prepare($query_padre);
    $stmt_padre->execute([$id_padre]);
    $row_padre = $stmt_padre->fetch(PDO::FETCH_ASSOC);
    
    if ($row_padre) {
        $nombre_padre = $row_padre['nombre'];
    } else {
        $nombre_padre = 'Padre';
    }
} catch(PDOException $e) {
    $nombre_padre = 'Padre';
}

// Iniciales para avatar del padre
$iniciales = '';
$partes = explode(' ', trim($nombre_padre));
if (count($partes) >= 2) {
    $iniciales = strtoupper(substr($partes[0], 0, 1) . substr($partes[1], 0, 1));
} else {
    $iniciales = strtoupper(substr($nombre_padre, 0, 2));
}

// Mapeo de avatares (igual que en dashboard_alumno.php)
$avatares = [
    'panda' => ['emoji' => '🐼', 'color' => '#3A506B', 'nivel' => 1],
    'zorro' => ['emoji' => '🦊', 'color' => '#E67E22', 'nivel' => 1],
    'dragon' => ['emoji' => '🐉', 'color' => '#FF6B6B', 'nivel' => 1],
    'leon' => ['emoji' => '🦁', 'color' => '#FFD93D', 'nivel' => 2],
    'dino' => ['emoji' => '🦖', 'color' => '#6BCF7F', 'nivel' => 1],
    'robot' => ['emoji' => '🤖', 'color' => '#4D96FF', 'nivel' => 3],
    'astronauta' => ['emoji' => '👨‍🚀', 'color' => '#845EC2', 'nivel' => 4],
    'superheroe' => ['emoji' => '🦸‍♂️', 'color' => '#FF6B8B', 'nivel' => 5],
    'mago' => ['emoji' => '🧙‍♂️', 'color' => '#00C2A8', 'nivel' => 6],
    'ninja' => ['emoji' => '🥷', 'color' => '#4A4A4A', 'nivel' => 3],
    'fenix' => ['emoji' => '🔥', 'color' => '#FF4500', 'nivel' => 7],
    'unicornio' => ['emoji' => '🦄', 'color' => '#D65DB1', 'nivel' => 8],
    'ballena' => ['emoji' => '🐋', 'color' => '#4169E1', 'nivel' => 3],
    'aguila' => ['emoji' => '🦅', 'color' => '#DAA520', 'nivel' => 3],
    'lobo' => ['emoji' => '🐺', 'color' => '#708090', 'nivel' => 3],
    'pinguino' => ['emoji' => '🐧', 'color' => '#1C2833', 'nivel' => 2],
    'bufalo' => ['emoji' => '🦬', 'color' => '#8B4513', 'nivel' => 2],
    'conejo' => ['emoji' => '🐰', 'color' => '#F4A460', 'nivel' => 1],
    'gato' => ['emoji' => '🐱', 'color' => '#FFA07A', 'nivel' => 1],
    'perro' => ['emoji' => '🐶', 'color' => '#DEB887', 'nivel' => 1],
    'raton' => ['emoji' => '🐭', 'color' => '#B0C4DE', 'nivel' => 1],
    'abeja' => ['emoji' => '🐝', 'color' => '#FFD700', 'nivel' => 2],
    'pulpo' => ['emoji' => '🐙', 'color' => '#CD5C5C', 'nivel' => 2],
    'robot_avanzado' => ['emoji' => '🤖', 'color' => '#2E86AB', 'nivel' => 5],
    'titan' => ['emoji' => '🏛️', 'color' => '#8B0000', 'nivel' => 4],
    'centauro' => ['emoji' => '🏹', 'color' => '#CD853F', 'nivel' => 4],
    'ciborg' => ['emoji' => '🦾', 'color' => '#4682B4', 'nivel' => 5],
    'kraken' => ['emoji' => '🐙', 'color' => '#2F4F4F', 'nivel' => 5],
    'valquiria' => ['emoji' => '⚔️', 'color' => '#C0C0C0', 'nivel' => 5],
    'dios_ra' => ['emoji' => '☀️', 'color' => '#FFD700', 'nivel' => 6],
    'leviathan' => ['emoji' => '🐉', 'color' => '#1a237e', 'nivel' => 6],
    'thor' => ['emoji' => '🔨', 'color' => '#5DADE2', 'nivel' => 6],
    'cerbero' => ['emoji' => '🐕‍🦺', 'color' => '#8B4513', 'nivel' => 6],
    'zeus' => ['emoji' => '⚡', 'color' => '#FFD700', 'nivel' => 7]
];

function getAvatarEmoji($avatar_key) {
    global $avatares;
    return isset($avatares[$avatar_key]) ? $avatares[$avatar_key]['emoji'] : '🧒';
}

function getAvatarColor($avatar_key) {
    global $avatares;
    return isset($avatares[$avatar_key]) ? $avatares[$avatar_key]['color'] : '#3A506B';
}

// Obtener ID del hijo desde GET y verificar vinculación
$id_hijo = isset($_GET['hijo']) ? intval($_GET['hijo']) : 0;
if ($id_hijo <= 0) {
    header("Location: dashboard_padre.php");
    exit();
}

// Verificar que el hijo esté vinculado al padre
try {
    $query_vinculo = "SELECT id FROM vinculaciones WHERE id_padre = ? AND id_alumno = ? AND estado = 'activo'";
    $stmt_vinculo = $conn->pdo->prepare($query_vinculo);
    $stmt_vinculo->execute([$id_padre, $id_hijo]);
    
    if ($stmt_vinculo->rowCount() == 0) {
        header("Location: dashboard_padre.php");
        exit();
    }
} catch(PDOException $e) {
    header("Location: dashboard_padre.php");
    exit();
}

// Obtener datos del hijo
try {
    $query_hijo = "SELECT nombre, avatar, fecha_nacimiento FROM usuarios WHERE id = ? AND tipo = 'alumno'";
    $stmt_hijo = $conn->pdo->prepare($query_hijo);
    $stmt_hijo->execute([$id_hijo]);
    $hijo = $stmt_hijo->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $hijo = null;
}

if (!$hijo) {
    header("Location: dashboard_padre.php");
    exit();
}

// Calcular edad (PostgreSQL)
$fecha_nac = $hijo['fecha_nacimiento'];
$edad = date_diff(date_create($fecha_nac), date_create('today'))->y;

// Emoji y color según avatar del hijo
$emoji_hijo = getAvatarEmoji($hijo['avatar']);
$avatar_color_hijo = getAvatarColor($hijo['avatar']);

// --- ESTADÍSTICAS GENERALES ---
// Promedio general (de todas las evaluaciones)
try {
    $query_promedio = "
        SELECT COALESCE(AVG(ev.calificacion), 0) AS promedio
        FROM evaluaciones ev
        JOIN entregas en ON ev.id_entrega = en.id
        WHERE en.id_alumno = ?
    ";
    $stmt = $conn->pdo->prepare($query_promedio);
    $stmt->execute([$id_hijo]);
    $res_prom = $stmt->fetch(PDO::FETCH_ASSOC);
    $promedio_general = round($res_prom['promedio'], 1);
} catch(PDOException $e) {
    $promedio_general = 0;
}

// Actividades completadas (entregas con estado 'calificado')
try {
    $query_completadas = "SELECT COUNT(*) AS total FROM entregas WHERE id_alumno = ? AND estado = 'calificado'";
    $stmt = $conn->pdo->prepare($query_completadas);
    $stmt->execute([$id_hijo]);
    $completadas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch(PDOException $e) {
    $completadas = 0;
}

// Total de actividades asignadas (en cursos inscritos)
try {
    $query_total_act = "
        SELECT COUNT(*) AS total
        FROM actividades a
        JOIN cursos c ON a.id_curso = c.id
        JOIN inscripciones i ON i.id_curso = c.id
        WHERE i.id_alumno = ? AND i.estado = 'activo'
    ";
    $stmt = $conn->pdo->prepare($query_total_act);
    $stmt->execute([$id_hijo]);
    $total_actividades = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch(PDOException $e) {
    $total_actividades = 0;
}


// Tiempo total de estudio esta semana (PostgreSQL)
try {
    $query_tiempo = "
        SELECT COALESCE(EXTRACT(EPOCH FROM SUM(tiempo_total)), 0) AS total_segundos
        FROM progreso
        WHERE id_alumno = ? AND fecha_actualizacion >= CURRENT_DATE - INTERVAL '7 days'
    ";
    $stmt = $conn->pdo->prepare($query_tiempo);
    $stmt->execute([$id_hijo]);
    $res_tiempo = $stmt->fetch(PDO::FETCH_ASSOC);
    $tiempo_seg = $res_tiempo['total_segundos'] ?? 0;
} catch(PDOException $e) {
    $tiempo_seg = 0;
}

$horas = floor($tiempo_seg / 3600);
$minutos = floor(($tiempo_seg % 3600) / 60);
$tiempo_total = ($horas ? $horas.'h ' : '') . $minutos.'m';

// Promedio diario (minutos)
$promedio_min_diarios = $tiempo_seg ? round(($tiempo_seg / 60) / 7) : 0;

// =============================================
// LOGROS REALES (calculados dinámicamente)
// =============================================

// 1. Obtener estadísticas reales del alumno
try {
    // Total de entregas completadas (calificadas)
    $stmt_total_entregas = $conn->pdo->prepare("
        SELECT COUNT(*) as total 
        FROM entregas 
        WHERE id_alumno = ? AND estado = 'calificado'
    ");
    $stmt_total_entregas->execute([$id_hijo]);
    $total_entregas = $stmt_total_entregas->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Primera entrega (fecha)
    $stmt_primera = $conn->pdo->prepare("
        SELECT MIN(fecha_entrega) as primera_fecha 
        FROM entregas 
        WHERE id_alumno = ?
    ");
    $stmt_primera->execute([$id_hijo]);
    $primera_entrega = $stmt_primera->fetch(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $total_entregas = 0;
    $primera_entrega = null;
}

// 2. Calcular racha de días REAL (basada en entregas por día)
try {
    $stmt_racha = $conn->pdo->prepare("
        SELECT DISTINCT DATE(fecha_entrega) as fecha
        FROM entregas
        WHERE id_alumno = ?
        ORDER BY fecha DESC
    ");
    $stmt_racha->execute([$id_hijo]);
    $fechas_entregas = $stmt_racha->fetchAll(PDO::FETCH_COLUMN);
    
    $racha_dias = 0;
    if (!empty($fechas_entregas)) {
        $hoy = date('Y-m-d');
        $ayer = date('Y-m-d', strtotime('-1 day'));
        
        if (in_array($hoy, $fechas_entregas) || in_array($ayer, $fechas_entregas)) {
            $fecha_actual = new DateTime();
            if (!in_array($hoy, $fechas_entregas)) {
                $fecha_actual->modify('-1 day');
            }
            
            for ($i = 0; $i <= 60; $i++) {
                $fecha_buscar = $fecha_actual->format('Y-m-d');
                if (in_array($fecha_buscar, $fechas_entregas)) {
                    $racha_dias++;
                    $fecha_actual->modify('-1 day');
                } else {
                    break;
                }
            }
        }
    }
} catch(PDOException $e) {
    $racha_dias = 0;
}

// 3. Construir array de logros REALES basados en datos
$logros = [];

// Logro 1: Primera entrega
if ($total_entregas >= 1 && $primera_entrega && $primera_entrega['primera_fecha']) {
    $logros[] = [
        'icono' => '🎯',
        'nombre' => 'Primer paso completado',
        'fecha' => date('d M Y', strtotime($primera_entrega['primera_fecha'])),
        'descripcion' => 'Primera actividad entregada'
    ];
}

// Logro 2: 5 entregas completadas
if ($total_entregas >= 5) {
    $logros[] = [
        'icono' => '🔍',
        'nombre' => 'Explorador principiante',
        'fecha' => date('d M Y'),
        'descripcion' => '5 actividades completadas'
    ];
}

// Logro 3: 10 entregas completadas
if ($total_entregas >= 10) {
    $logros[] = [
        'icono' => '🧭',
        'nombre' => 'Aventurero',
        'fecha' => date('d M Y'),
        'descripcion' => '10 actividades completadas'
    ];
}

// Logro 4: 20 entregas completadas
if ($total_entregas >= 20) {
    $logros[] = [
        'icono' => '🏆',
        'nombre' => 'Maestro del conocimiento',
        'fecha' => date('d M Y'),
        'descripcion' => '20 actividades completadas'
    ];
}

// Logro 5: Excelencia académica (promedio >= 8.5)
if ($promedio_general >= 8.5) {
    $logros[] = [
        'icono' => '⭐',
        'nombre' => 'Excelencia académica',
        'fecha' => date('d M Y'),
        'descripcion' => 'Promedio general superior a 8.5'
    ];
}

// Logro 6: Promedio perfecto
if ($promedio_general == 10 && $total_entregas >= 3) {
    $logros[] = [
        'icono' => '🌟',
        'nombre' => '¡Perfecto!',
        'fecha' => date('d M Y'),
        'descripcion' => 'Calificación perfecta en todas las actividades'
    ];
}

// Logro 7: Racha de 5 días
if ($racha_dias >= 5) {
    $logros[] = [
        'icono' => '⚡',
        'nombre' => 'Racha de ' . min(5, floor($racha_dias/5)*5) . ' días',
        'fecha' => date('d M Y'),
        'descripcion' => $racha_dias . ' días consecutivos de actividad'
    ];
}

// Logro 8: Racha de 10 días
if ($racha_dias >= 10) {
    $logros[] = [
        'icono' => '🔥',
        'nombre' => 'Racha de ' . min(10, floor($racha_dias/10)*10) . ' días',
        'fecha' => date('d M Y'),
        'descripcion' => $racha_dias . ' días consecutivos de actividad'
    ];
}

// Logro 9: Primer curso completado
try {
    $stmt_curso_completo = $conn->pdo->prepare("
        SELECT c.nombre, i.progreso
        FROM inscripciones i
        JOIN cursos c ON i.id_curso = c.id
        WHERE i.id_alumno = ? AND i.progreso = 100 AND i.estado = 'activo'
        LIMIT 1
    ");
    $stmt_curso_completo->execute([$id_hijo]);
    $curso_completo = $stmt_curso_completo->fetch(PDO::FETCH_ASSOC);
    
    if ($curso_completo) {
        $logros[] = [
            'icono' => '🎓',
            'nombre' => '¡Curso completado!',
            'fecha' => date('d M Y'),
            'descripcion' => 'Completaste el curso: ' . htmlspecialchars($curso_completo['nombre'])
        ];
    }
} catch(PDOException $e) {
    // No hacer nada
}

// Limitar a máximo 6 logros y ordenar por fecha (los más recientes primero)
usort($logros, function($a, $b) {
    return strtotime($b['fecha']) - strtotime($a['fecha']);
});
$logros = array_slice($logros, 0, 6);

// --- RETROALIMENTACIÓN DE DOCENTES ---
try {
    $query_feedback = "
        SELECT 
            u.nombre AS tutor,
            ev.comentarios,
            ev.fecha_evaluacion,
            a.titulo AS actividad,
            c.nombre AS curso
        FROM evaluaciones ev
        JOIN entregas en ON ev.id_entrega = en.id
        JOIN usuarios u ON ev.id_tutor = u.id
        JOIN actividades a ON en.id_actividad = a.id
        JOIN cursos c ON a.id_curso = c.id
        WHERE en.id_alumno = ? AND ev.comentarios IS NOT NULL AND ev.comentarios != ''
        ORDER BY ev.fecha_evaluacion DESC
        LIMIT 5
    ";
    $stmt = $conn->pdo->prepare($query_feedback);
    $stmt->execute([$id_hijo]);
    $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $feedbacks = [];
}

// --- DESEMPEÑO POR MATERIA ---
try {
    $query_materias = "
        SELECT 
            c.nombre AS materia,
            COALESCE(AVG(ev.calificacion), 0) AS promedio,
            COUNT(ev.id) AS evaluaciones
        FROM cursos c
        JOIN actividades a ON a.id_curso = c.id
        JOIN entregas en ON en.id_actividad = a.id
        JOIN evaluaciones ev ON ev.id_entrega = en.id
        WHERE en.id_alumno = ?
        GROUP BY c.id, c.nombre
        ORDER BY promedio DESC
    ";
    $stmt = $conn->pdo->prepare($query_materias);
    $stmt->execute([$id_hijo]);
    $materias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $materias = [];
}

// Colores para materias
$colores = ['#2cbaec', '#83bf46', '#f0ae2a', '#a78bfa', '#fb7185'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progreso de <?= htmlspecialchars($hijo['nombre']) ?> · D&F Mindspace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary:        #2cbaec;
            --primary-dark:   #1a9acc;
            --primary-soft:   rgba(44, 186, 236, 0.08);
            --primary-mid:    rgba(44, 186, 236, 0.15);
            --secondary:      #f0ae2a;
            --secondary-soft: rgba(240, 174, 42, 0.10);
            --accent:         #83bf46;
            --accent-soft:    rgba(131, 191, 70, 0.10);
            --danger:         #ff6b8b;
            --danger-soft:    rgba(255, 107, 139, 0.08);
 
            --gray-50:  #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-700: #334155;
            --gray-900: #0f172a;
 
            --sidebar-width: 272px;
            --r-card:  20px;
            --r-pill:  100px;
            --sh-sm:   0 2px 8px rgba(15,23,42,.05);
            --sh-md:   0 8px 24px rgba(15,23,42,.08);
            --sh-lg:   0 20px 48px rgba(44,186,236,.13);
        }
 
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
 
        body {
            font-family: 'DM Sans', sans-serif;
            background: #f2f7fb;
            color: var(--gray-700);
            min-height: 100vh;
            overflow-x: hidden;
        }
 
        /* ═══════════════════════════════
           SIDEBAR  (same system as seleccion_perfil)
        ═══════════════════════════════ */
        .sidebar {
            background: #fff;
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0; top: 0;
            z-index: 100;
            border-right: 1px solid rgba(44,186,236,.12);
            box-shadow: 2px 0 16px rgba(15,23,42,.05);
            display: flex; flex-direction: column;
            transition: transform .3s ease;
        }
 
        .sidebar-scroll {
            flex: 1; overflow-y: auto; padding-bottom: 16px;
            scrollbar-width: thin;
            scrollbar-color: rgba(44,186,236,.15) transparent;
        }
        .sidebar-scroll::-webkit-scrollbar { width: 4px; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(44,186,236,.2); border-radius: 4px; }
 
        .brand {
            padding: 28px 24px 22px;
            border-bottom: 1px solid rgba(44,186,236,.1);
            margin-bottom: 8px;
        }
        .brand-mark { display:flex; align-items:center; gap:10px; margin-bottom:4px; }
        .brand-icon {
            width:36px; height:36px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius:10px;
            display:flex; align-items:center; justify-content:center;
        }
        .brand-icon svg { width:20px; height:20px; fill:white; }
        .brand-name {
            font-family:'Nunito',sans-serif; font-size:1.2rem; font-weight:900;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip:text; background-clip:text; color:transparent;
        }
        .brand-tagline { font-size:.68rem; color:var(--gray-400); font-weight:500; letter-spacing:1.5px; text-transform:uppercase; padding-left:46px; }
 
        .user-pill {
            margin: 12px 16px 16px;
            background: var(--primary-soft);
            border-radius: 14px; padding: 12px 16px;
            display:flex; align-items:center; gap:12px;
        }
        .user-avatar {
            width:38px; height:38px; border-radius:50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display:flex; align-items:center; justify-content:center;
            font-family:'Nunito',sans-serif; font-weight:800; font-size:.95rem; color:white; flex-shrink:0;
        }
        .user-name  { font-weight:600; font-size:.88rem; color:var(--gray-700); line-height:1.2; }
        .user-role  { font-size:.72rem; color:var(--primary); font-weight:500; }
 
        .nav-section {
            padding: 8px 12px 4px;
            font-size:.65rem; font-weight:700; letter-spacing:1.8px;
            text-transform:uppercase; color:var(--gray-400); margin-top:8px;
        }
        .nav-item { margin: 2px 12px; }
        .nav-link {
            display:flex; align-items:center; gap:10px;
            padding:11px 14px; border-radius:12px;
            font-size:.88rem; font-weight:500; color:var(--gray-500);
            transition:all .18s ease; text-decoration:none;
        }
        .nav-link:hover  { background:var(--gray-100); color:var(--gray-700); }
        .nav-link.active { background:var(--primary-soft); color:var(--primary); font-weight:600; }
        .nav-link .icon {
            width:32px; height:32px; border-radius:8px;
            display:flex; align-items:center; justify-content:center;
            font-size:.85rem; flex-shrink:0; background:transparent; transition:background .18s;
        }
        .nav-link.active .icon { background: rgba(44,186,236,.15); color:var(--primary); }
        .nav-link:hover .icon  { background: rgba(44,186,236,.08); }
 
        .sidebar-foot {
            padding:16px 24px;
            border-top:1px solid rgba(44,186,236,.08);
        }
        .logout-btn {
            display:flex; align-items:center; gap:10px;
            padding:10px 14px; border-radius:12px;
            font-size:.85rem; font-weight:600; color:var(--danger);
            text-decoration:none; transition:background .18s; cursor:pointer;
        }
        .logout-btn:hover { background: rgba(255,107,139,.07); color:var(--danger); }
 
        /* ═══════════════════════════════
           MAIN LAYOUT
        ═══════════════════════════════ */
        .main {
            margin-left: var(--sidebar-width);
            padding: 36px 48px 60px;
            min-height: 100vh;
        }
 
        /* Breadcrumb nav */
        .breadcrumb-row {
            display:flex; align-items:center; gap:8px;
            font-size:.8rem; color:var(--gray-400);
            margin-bottom:28px;
        }
        .breadcrumb-row a {
            color:var(--gray-400); text-decoration:none; font-weight:500;
            transition:color .15s;
        }
        .breadcrumb-row a:hover { color:var(--primary); }
        .breadcrumb-row .sep { font-size:.65rem; }
        .breadcrumb-row .current { color:var(--gray-700); font-weight:600; }
 
        /* ═══════════════════════════════
           SECTION 1 — STUDENT HERO HEADER
        ═══════════════════════════════ */
        .student-hero {
            background: white;
            border-radius: var(--r-card);
            border: 1px solid rgba(44,186,236,.1);
            box-shadow: var(--sh-sm);
            padding: 28px 32px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            position: relative;
            overflow: hidden;
        }
 
        /* subtle background pattern */
        .student-hero::before {
            content:'';
            position:absolute; top:0; right:0;
            width:280px; height:100%;
            background: linear-gradient(120deg, transparent 40%, rgba(44,186,236,.04) 100%);
            pointer-events:none;
        }
 
        .hero-left { display:flex; align-items:center; gap:20px; }
 
        .hero-avatar {
            width:72px; height:72px; border-radius:22px;
            background: <?= $avatar_color_hijo ?>;
            display:flex; align-items:center; justify-content:center;
            font-size:2.4rem; flex-shrink:0;
            border: 2px solid rgba(44,186,236,.12);
        }
 
        .hero-eyebrow {
            font-size:.7rem; font-weight:700;
            letter-spacing:1.8px; text-transform:uppercase;
            color:var(--primary); margin-bottom:4px;
        }
        .hero-name {
            font-family:'Nunito',sans-serif; font-size:1.65rem; font-weight:900;
            color:var(--gray-900); letter-spacing:-.4px; margin-bottom:6px;
        }
        .hero-chips { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .chip {
            display:inline-flex; align-items:center; gap:5px;
            padding:5px 12px; border-radius:var(--r-pill);
            font-size:.75rem; font-weight:600; border:1px solid;
        }
        .chip-blue   { background:var(--primary-soft);   color:var(--primary);   border-color:rgba(44,186,236,.2); }
        .chip-amber  { background:var(--secondary-soft); color:#c88a00;          border-color:rgba(240,174,42,.25); }
        .chip-green  { background:var(--accent-soft);    color:#5a8a1e;          border-color:rgba(131,191,70,.25); }
        .chip-gray   { background:var(--gray-100);       color:var(--gray-500);  border-color:var(--gray-200); }
 
        /* KPI strip */
        .hero-kpis {
            display:flex; align-items:center; gap:6px;
            background:var(--gray-50); border-radius:16px;
            padding:12px 20px; border:1px solid var(--gray-200);
            flex-wrap:wrap;
        }
        .kpi-item { text-align:center; padding:0 16px; }
        .kpi-item + .kpi-item { border-left:1px solid var(--gray-200); }
        .kpi-val {
            font-family:'Nunito',sans-serif; font-size:1.5rem; font-weight:900;
            line-height:1; margin-bottom:3px;
        }
        .kpi-lbl { font-size:.68rem; color:var(--gray-400); font-weight:600; text-transform:uppercase; letter-spacing:.5px; }
 
        /* PDF button */
        .btn-pdf {
            display:inline-flex; align-items:center; gap:8px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color:white; border:none; border-radius:var(--r-pill);
            padding:12px 22px; font-size:.85rem; font-weight:700;
            cursor:pointer; font-family:'DM Sans',sans-serif;
            box-shadow:0 4px 14px rgba(44,186,236,.3);
            transition:all .2s ease;
            text-decoration:none;
        }
        .btn-pdf:hover {
            transform:translateY(-2px);
            box-shadow:0 8px 22px rgba(44,186,236,.38);
            color:white;
        }
        .btn-pdf:active { transform:translateY(0); }
 
        /* ═══════════════════════════════
           SECTION 2 — GENERAL SUMMARY
        ═══════════════════════════════ */
        .section-label {
            font-size:.72rem; font-weight:700;
            letter-spacing:2px; text-transform:uppercase;
            color:var(--gray-400); margin-bottom:14px;
            display:flex; align-items:center; gap:8px;
        }
        .section-label::after {
            content:''; flex:1; height:1px;
            background:var(--gray-200);
        }
 
        .summary-grid {
            display:grid;
            grid-template-columns: repeat(4, 1fr);
            gap:14px;
            margin-bottom:24px;
        }
 
        .sum-card {
            background:white; border-radius:16px;
            padding:20px 18px;
            border:1px solid var(--gray-200);
            box-shadow:var(--sh-sm);
            transition:transform .2s, box-shadow .2s;
        }
        .sum-card:hover { transform:translateY(-3px); box-shadow:var(--sh-md); }
 
        .sum-icon {
            width:38px; height:38px; border-radius:11px;
            display:flex; align-items:center; justify-content:center;
            font-size:.95rem; margin-bottom:14px;
        }
        .sum-val {
            font-family:'Nunito',sans-serif; font-size:1.9rem; font-weight:900;
            line-height:1; margin-bottom:4px;
        }
        .sum-lbl { font-size:.78rem; color:var(--gray-500); font-weight:500; }
        .sum-sub { font-size:.72rem; color:var(--gray-400); margin-top:4px; }
 
        /* Achievement badges row */
        .achievements-row {
            display:flex; gap:10px; flex-wrap:wrap;
            margin-bottom:24px;
        }
        .achievement {
            background:white; border-radius:14px;
            border:1px solid var(--gray-200);
            padding:10px 16px;
            display:flex; align-items:center; gap:10px;
            box-shadow:var(--sh-sm);
            transition:all .2s;
        }
        .achievement:hover { border-color:var(--primary); box-shadow:0 4px 14px rgba(44,186,236,.1); }
        .ach-icon { font-size:1.4rem; }
        .ach-name { font-size:.8rem; font-weight:700; color:var(--gray-700); }
        .ach-date { font-size:.7rem; color:var(--gray-400); }
 
        /* ═══════════════════════════════
           CONTENT COLUMNS LAYOUT
        ═══════════════════════════════ */
        .content-cols {
            display:grid;
            grid-template-columns: 1fr 400px;
            gap:20px;
            align-items:start;
        }
 
        /* ═══════════════════════════════
           SECTION 3 — FEEDBACK THREAD
        ═══════════════════════════════ */
        .panel {
            background:white; border-radius:var(--r-card);
            border:1px solid rgba(44,186,236,.1);
            box-shadow:var(--sh-sm);
            overflow:hidden;
        }
 
        .panel-head {
            padding:18px 24px;
            border-bottom:1px solid var(--gray-100);
            display:flex; align-items:center; justify-content:space-between;
        }
        .panel-head-left { display:flex; align-items:center; gap:10px; }
        .panel-head-icon {
            width:34px; height:34px; border-radius:10px;
            display:flex; align-items:center; justify-content:center;
            font-size:.85rem;
        }
        .panel-head-title { font-size:.9rem; font-weight:700; color:var(--gray-900); }
        .panel-head-sub   { font-size:.74rem; color:var(--gray-400); }
 
        /* Feedback list */
        .feedback-list { padding:8px 0; }
 
        .feedback-thread {
            border-bottom:1px solid var(--gray-100);
        }
        .feedback-thread:last-child { border-bottom:none; }
 
        .feedback-item {
            padding:16px 24px;
            display:flex; gap:12px;
            cursor:pointer;
            transition:background .15s;
        }
        .feedback-item:hover { background:var(--gray-50); }
        .feedback-item.unread { background: rgba(44,186,236,.03); }
 
        .fb-avatar {
            width:36px; height:36px; border-radius:50%; flex-shrink:0;
            display:flex; align-items:center; justify-content:center;
            font-size:.8rem; font-weight:700; color:white;
            margin-top:2px;
        }
 
        .fb-body { flex:1; min-width:0; }
        .fb-top { display:flex; align-items:baseline; justify-content:space-between; gap:8px; margin-bottom:3px; }
        .fb-sender { font-size:.83rem; font-weight:700; color:var(--gray-900); }
        .fb-time   { font-size:.7rem; color:var(--gray-400); white-space:nowrap; flex-shrink:0; }
        .fb-subject { font-size:.79rem; font-weight:600; color:var(--gray-600); margin-bottom:3px; }
        .fb-preview { font-size:.77rem; color:var(--gray-400); line-height:1.45; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .unread-dot { width:7px; height:7px; background:var(--primary); border-radius:50%; flex-shrink:0; margin-top:6px; }
 
        /* Reply area */
        .reply-box {
            border-top:1px solid var(--gray-100);
            padding:16px 20px;
        }
        .reply-label { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--gray-400); margin-bottom:10px; }
        .reply-select {
            width:100%; padding:9px 12px; border-radius:11px;
            border:1.5px solid var(--gray-200);
            font-family:'DM Sans',sans-serif; font-size:.83rem; color:var(--gray-700);
            background:var(--gray-50); margin-bottom:10px;
            appearance:none; cursor:pointer;
            transition:border-color .18s;
        }
        .reply-select:focus { outline:none; border-color:var(--primary); }
        .reply-textarea {
            width:100%; padding:10px 14px; border-radius:12px;
            border:1.5px solid var(--gray-200);
            font-family:'DM Sans',sans-serif; font-size:.83rem; color:var(--gray-700);
            background:var(--gray-50); resize:none; min-height:80px;
            transition:border-color .18s;
            margin-bottom:10px;
        }
        .reply-textarea:focus { outline:none; border-color:var(--primary); background:white; }
        .reply-textarea::placeholder { color:var(--gray-400); }
        .reply-footer { display:flex; align-items:center; justify-content:flex-end; }
        .btn-send {
            display:inline-flex; align-items:center; gap:7px;
            background:var(--primary); color:white; border:none;
            border-radius:var(--r-pill); padding:9px 20px;
            font-size:.82rem; font-weight:700; cursor:pointer;
            font-family:'DM Sans',sans-serif; transition:all .18s;
        }
        .btn-send:hover { background:var(--primary-dark); transform:translateY(-1px); }
 
        /* ═══════════════════════════════
           RIGHT COLUMN — CHART PANEL
        ═══════════════════════════════ */
        .chart-panel { display:flex; flex-direction:column; gap:20px; }
 
        .period-tabs {
            display:flex; gap:4px;
            background:var(--gray-100); border-radius:10px; padding:4px;
        }
        .ptab {
            flex:1; text-align:center; padding:7px;
            border-radius:7px; font-size:.78rem; font-weight:600;
            color:var(--gray-500); cursor:pointer; border:none;
            background:transparent; font-family:'DM Sans',sans-serif;
            transition:all .18s;
        }
        .ptab.active { background:white; color:var(--primary); box-shadow:var(--sh-sm); }
 
        canvas { max-width:100%; }
 
        /* Subject breakdown */
        .subject-list { padding:4px 0; }
        .subject-row {
            display:flex; align-items:center; gap:12px;
            padding:11px 24px; transition:background .15s;
        }
        .subject-row:hover { background:var(--gray-50); }
        .subject-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
        .subject-name { font-size:.83rem; font-weight:600; color:var(--gray-700); flex:1; }
        .subject-bar-wrap { width:90px; height:6px; background:var(--gray-100); border-radius:3px; overflow:hidden; }
        .subject-bar { height:100%; border-radius:3px; }
        .subject-score { font-family:'Nunito',sans-serif; font-size:.88rem; font-weight:800; min-width:30px; text-align:right; }
 
        /* ═══════════════════════════════
           ANIMATIONS
        ═══════════════════════════════ */
        .fade-up { opacity:0; transform:translateY(14px); animation:fuUp .45s forwards; }
        @keyframes fuUp { to { opacity:1; transform:none; } }
 
        /* menu toggle */
        .menu-toggle {
            display:none; position:fixed; top:18px; left:18px; z-index:200;
            background:white; border:1px solid rgba(44,186,236,.2);
            color:var(--primary); width:44px; height:44px;
            border-radius:12px; font-size:1.1rem;
            box-shadow:var(--sh-sm); cursor:pointer;
            align-items:center; justify-content:center;
        }
 
        /* modal */
        .modal-content { border:none; border-radius:20px; overflow:hidden; box-shadow:0 32px 64px rgba(15,23,42,.15); }
        .modal-header { background:white; padding:22px 26px 18px; border-bottom:1px solid rgba(44,186,236,.1); }
        .modal-body   { padding:20px 26px; }
        .modal-footer { padding:14px 26px 22px; border-top:1px solid rgba(44,186,236,.08); }
 
        /* toast */
        .toast { border:none; border-radius:14px; box-shadow:0 8px 24px rgba(15,23,42,.12); min-width:280px; }
 
        /* ═══════════════════════════════
           RESPONSIVE
        ═══════════════════════════════ */
        @media (max-width:1280px) {
            .summary-grid { grid-template-columns:repeat(2, 1fr); }
            .content-cols  { grid-template-columns:1fr; }
        }
        @media (max-width:992px) {
            .sidebar { transform:translateX(-100%); }
            .sidebar.open { transform:translateX(0); }
            .main { margin-left:0; padding:24px 20px 48px; }
            .menu-toggle { display:flex; }
            .breadcrumb-row { margin-top:56px; }
        }
        @media (max-width:640px) {
            .summary-grid { grid-template-columns:1fr 1fr; }
            .hero-kpis    { display:none; }
            .student-hero { flex-direction:column; align-items:flex-start; }
        }
    </style>
    </style>
</head>
<body>

<button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>

<!-- SIDEBAR -->
<nav class="sidebar" id="sidebar">
    <div class="sidebar-scroll">
        <div class="brand">
            <div class="brand-mark">
                <div class="brand-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"/></svg>
                </div>
                <div class="brand-name">D&F Mindspace</div>
            </div>
            <div class="brand-tagline">familia conectada</div>
        </div>

        <div class="user-pill">
            <div class="user-avatar"><?= htmlspecialchars($iniciales) ?></div>
            <div>
                <div class="user-name"><?= htmlspecialchars($nombre_padre) ?></div>
                <div class="user-role">Cuenta familiar</div>
            </div>
        </div>

        <div class="nav-section">Principal</div>
        <div class="nav-item">
            <a href="dashboard_padre.php" class="nav-link">
                <div class="icon"><i class="fas fa-users"></i></div>
                <span>Mis hijos</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="dashboard_padre_detalle.php?hijo=<?= $id_hijo ?>" class="nav-link active">
                <div class="icon"><i class="fas fa-chart-line"></i></div>
                <span>Visión general</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="reportes.php?hijo=<?= $id_hijo ?>" class="nav-link">
                <div class="icon"><i class="fas fa-file-lines"></i></div>
                <span>Reportes</span>
            </a>
        </div>
        <div class="nav-section" style="margin-top:16px;">Cuenta</div>
        <div class="nav-item">
            <a href="suscripcion.php" class="nav-link">
                <div class="icon"><i class="fas fa-gem"></i></div>
                <span>Suscripción</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="configuracion.php" class="nav-link">
                <div class="icon"><i class="fas fa-sliders"></i></div>
                <span>Configuración</span>
            </a>
        </div>
    </div>
    <div class="sidebar-foot">
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-arrow-right-from-bracket"></i>
            Cerrar sesión
        </a>
    </div>
</nav>

<!-- MAIN -->
<main class="main">

    <!-- Breadcrumb -->
    <div class="breadcrumb-row fade-up" style="animation-delay:.02s">
        <a href="dashboard_padre.php"><i class="fas fa-house" style="font-size:.7rem;"></i> Mis hijos</a>
        <span class="sep">›</span>
        <span class="current"><?= htmlspecialchars($hijo['nombre']) ?></span>
    </div>

    <!-- HERO HEADER (con avatar del hijo) -->
    <div class="student-hero fade-up" style="animation-delay:.06s">
        <div class="hero-left">
            <div class="hero-avatar"><?= $emoji_hijo ?></div>
            <div>
                <div class="hero-eyebrow">Progreso del alumno</div>
                <div class="hero-name"><?= htmlspecialchars($hijo['nombre']) ?></div>
                <div class="hero-chips">
                    <span class="chip chip-blue"><i class="fas fa-graduation-cap"></i> <?= $edad ?> años</span>
                    <span class="chip chip-amber"><i class="fas fa-cake-candles"></i> Activo</span>
                    <span class="chip chip-green"><i class="fas fa-circle" style="font-size:.5rem;"></i> Activa esta semana</span>
                </div>
            </div>
        </div>

        <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
            <div class="hero-kpis">
                <div class="kpi-item">
                    <div class="kpi-val" style="color:var(--primary);"><?= $promedio_general ?></div>
                    <div class="kpi-lbl">Promedio</div>
                </div>
                <div class="kpi-item">
                    <div class="kpi-val" style="color:var(--accent);"><?= $completadas ?>/<?= $total_actividades ?></div>
                    <div class="kpi-lbl">Completadas</div>
                </div>
                <div class="kpi-item">
                    <div class="kpi-val" style="color:var(--secondary);"><?= $racha_dias ?></div>
                    <div class="kpi-lbl">Racha días</div>
                </div>
                <div class="kpi-item">
                    <div class="kpi-val" style="color:var(--gray-700);"><?= count($logros) ?></div>
                    <div class="kpi-lbl">Logros</div>
                </div>
            </div>
            <button class="btn-pdf" onclick="generatePDF()">
                <i class="fas fa-file-arrow-down"></i>
                Exportar reporte PDF
            </button>
        </div>
    </div>

    <!-- SUMMARY CARDS -->
    <div class="section-label fade-up" style="animation-delay:.1s">Resumen general</div>

    <div class="summary-grid fade-up" style="animation-delay:.13s">
        <div class="sum-card">
            <div class="sum-icon" style="background:var(--primary-soft); color:var(--primary);">
                <i class="fas fa-star"></i>
            </div>
            <div class="sum-val" style="color:var(--primary);"><?= $promedio_general ?></div>
            <div class="sum-lbl">Promedio general</div>
            <div class="sum-sub">↑ +0.6 vs mes anterior</div>
        </div>
        <div class="sum-card">
            <div class="sum-icon" style="background:var(--accent-soft); color:var(--accent);">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="sum-val" style="color:var(--accent);"><?= $completadas ?> / <?= $total_actividades ?></div>
            <div class="sum-lbl">Actividades completadas</div>
            <div class="sum-sub"><?= $total_actividades - $completadas ?> pendientes</div>
        </div>
        <div class="sum-card">
            <div class="sum-icon" style="background:var(--secondary-soft); color:var(--secondary);">
                <i class="fas fa-fire"></i>
            </div>
            <div class="sum-val" style="color:var(--secondary);"><?= $racha_dias ?> días</div>
            <div class="sum-lbl">Racha de actividad</div>
            <div class="sum-sub">Mejor marca: 12 días</div>
        </div>
        <div class="sum-card">
            <div class="sum-icon" style="background:rgba(255,107,139,.08); color:var(--danger);">
                <i class="fas fa-clock"></i>
            </div>
            <div class="sum-val" style="color:var(--gray-700);"><?= $tiempo_total ?></div>
            <div class="sum-lbl">Tiempo esta semana</div>
            <div class="sum-sub">Promedio: <?= $promedio_min_diarios ?> min/día</div>
        </div>
    </div>

    <!-- Logros -->
    <div class="section-label fade-up" style="animation-delay:.16s">Logros obtenidos</div>
    <div class="achievements-row fade-up" style="animation-delay:.18s">
        <?php foreach ($logros as $logro): ?>
        <div class="achievement">
            <div class="ach-icon"><?= $logro['icono'] ?></div>
            <div>
                <div class="ach-name"><?= htmlspecialchars($logro['nombre']) ?></div>
                <div class="ach-date">Obtenido el <?= $logro['fecha'] ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- TWO-COLUMN SECTION -->
    <div class="content-cols">

        <!-- LEFT: Feedback thread -->
        <div class="fade-up" style="animation-delay:.21s">
            <div class="section-label">Retroalimentación de docentes</div>
            <div class="panel">
                <div class="panel-head">
                    <div class="panel-head-left">
                        <div class="panel-head-icon" style="background:var(--primary-soft); color:var(--primary);">
                            <i class="fas fa-comments"></i>
                        </div>
                        <div>
                            <div class="panel-head-title">Conversaciones con docentes</div>
                            <div class="panel-head-sub"><?= count($feedbacks) ?> mensajes</div>
                        </div>
                    </div>
                    <?php if (count($feedbacks) > 0): ?>
                    <span class="chip chip-blue" style="font-size:.7rem;"><?= count($feedbacks) ?> nuevos</span>
                    <?php endif; ?>
                </div>

                <div class="feedback-list">
                    <?php if (empty($feedbacks)): ?>
                        <div style="padding: 20px; text-align: center; color: var(--gray-400);">
                            <i class="fas fa-comment-slash"></i> No hay mensajes de docentes aún.
                        </div>
                    <?php else: ?>
                        <?php foreach ($feedbacks as $index => $fb): ?>
                        <div class="feedback-thread">
                            <div class="feedback-item unread" onclick="openThread(<?= $index+1 ?>)">
                                <div class="fb-avatar" style="background:linear-gradient(135deg, #2cbaec, #6fd3f7);"><?= strtoupper(substr($fb['tutor'], 0, 1)) ?></div>
                                <div class="fb-body">
                                    <div class="fb-top">
                                        <span class="fb-sender"><?= htmlspecialchars($fb['tutor']) ?></span>
                                        <span class="fb-time"><?= date('d M H:i', strtotime($fb['fecha_evaluacion'])) ?></span>
                                    </div>
                                    <div class="fb-subject"><?= htmlspecialchars($fb['curso']) ?> — <?= htmlspecialchars($fb['actividad']) ?></div>
                                    <div class="fb-preview"><?= htmlspecialchars(substr($fb['comentarios'], 0, 60)) ?>...</div>
                                </div>
                                <div class="unread-dot"></div>
                            </div>

                            <div id="thread-<?= $index+1 ?>" style="display:none; padding:0 24px 16px; border-top:1px solid var(--gray-100);">
                                <div style="margin:14px 0 10px;">
                                    <div style="background:var(--primary-soft); border-radius:12px; padding:12px 16px; margin-bottom:10px;">
                                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                                            <div class="fb-avatar" style="width:28px;height:28px;font-size:.7rem;background:linear-gradient(135deg,#2cbaec,#6fd3f7);"><?= strtoupper(substr($fb['tutor'],0,1)) ?></div>
                                            <span style="font-size:.8rem;font-weight:700;color:var(--gray-700);"><?= htmlspecialchars($fb['tutor']) ?></span>
                                            <span style="font-size:.7rem;color:var(--gray-400);"><?= date('d M H:i', strtotime($fb['fecha_evaluacion'])) ?></span>
                                        </div>
                                        <p style="font-size:.82rem;color:var(--gray-700);line-height:1.55;margin:0;">
                                            <?= nl2br(htmlspecialchars($fb['comentarios'])) ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="reply-label">Responder al docente</div>
                                <textarea class="reply-textarea" placeholder="Escribe tu mensaje para el docente..."></textarea>
                                <div class="reply-footer">
                                    <button class="btn-send" onclick="sendReply(<?= $index+1 ?>)">
                                        <i class="fas fa-paper-plane" style="font-size:.75rem;"></i>
                                        Enviar
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- RIGHT: Chart + Subject breakdown -->
        <div class="chart-panel fade-up" style="animation-delay:.24s">

            <!-- Progress chart -->
            <div>
                <div class="section-label">Progreso en el tiempo</div>
                <div class="panel">
                    <div class="panel-head">
                        <div class="panel-head-left">
                            <div class="panel-head-icon" style="background:var(--primary-soft); color:var(--primary);">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div>
                                <div class="panel-head-title">Calificaciones</div>
                                <div class="panel-head-sub">Tendencia por periodo</div>
                            </div>
                        </div>
                        <div class="period-tabs">
                            <button class="ptab" onclick="setPeriod('week',this)">Sem</button>
                            <button class="ptab active" onclick="setPeriod('month',this)">Mes</button>
                            <button class="ptab" onclick="setPeriod('quarter',this)">Trim</button>
                        </div>
                    </div>
                    <div style="padding:16px 20px 20px;">
                        <canvas id="progressChart" height="190"></canvas>
                    </div>
                </div>
            </div>

            <!-- Subject breakdown -->
            <div>
                <div class="section-label">Desempeño por materia</div>
                <div class="panel">
                    <div class="panel-head">
                        <div class="panel-head-left">
                            <div class="panel-head-icon" style="background:var(--secondary-soft); color:var(--secondary);">
                                <i class="fas fa-book-open"></i>
                            </div>
                            <div>
                                <div class="panel-head-title">Materias</div>
                                <div class="panel-head-sub">Promedio del período actual</div>
                            </div>
                        </div>
                    </div>
                    <div class="subject-list">
                        <?php foreach ($materias as $i => $mat): 
                            $color = $colores[$i % count($colores)];
                            $prom = round($mat['promedio'], 1);
                            $porcentaje = ($prom / 10) * 100;
                        ?>
                        <div class="subject-row">
                            <div class="subject-dot" style="background:<?= $color ?>;"></div>
                            <div class="subject-name"><?= htmlspecialchars($mat['materia']) ?></div>
                            <div class="subject-bar-wrap"><div class="subject-bar" style="width:<?= $porcentaje ?>%;background:<?= $color ?>;"></div></div>
                            <div class="subject-score" style="color:<?= $color ?>;"><?= $prom ?></div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($materias)): ?>
                            <div style="padding: 20px; text-align: center; color: var(--gray-400);">Sin calificaciones aún.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>

</main>

<!-- PDF Modal -->
<div class="modal fade" id="pdfModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.05rem;color:var(--gray-900);margin-bottom:2px;">
                        Generar reporte PDF
                    </h5>
                    <p style="font-size:.8rem;color:var(--gray-400);margin:0;"><?= htmlspecialchars($hijo['nombre']) ?> · <?= $edad ?> años</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div style="margin-bottom:16px;">
                    <label style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--gray-500);display:block;margin-bottom:8px;">Período del reporte</label>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                        <button class="pdf-period-btn active" onclick="selectPeriod(this)">Esta semana</button>
                        <button class="pdf-period-btn" onclick="selectPeriod(this)">Este mes</button>
                        <button class="pdf-period-btn" onclick="selectPeriod(this)">Este trimestre</button>
                        <button class="pdf-period-btn" onclick="selectPeriod(this)">Personalizado</button>
                    </div>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--gray-500);display:block;margin-bottom:8px;">Incluir en el reporte</label>
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        <label style="display:flex;align-items:center;gap:10px;font-size:.84rem;cursor:pointer;">
                            <input type="checkbox" checked style="accent-color:var(--primary);"> Resumen de calificaciones
                        </label>
                        <label style="display:flex;align-items:center;gap:10px;font-size:.84rem;cursor:pointer;">
                            <input type="checkbox" checked style="accent-color:var(--primary);"> Retroalimentación de docentes
                        </label>
                        <label style="display:flex;align-items:center;gap:10px;font-size:.84rem;cursor:pointer;">
                            <input type="checkbox" checked style="accent-color:var(--primary);"> Logros obtenidos
                        </label>
                        <label style="display:flex;align-items:center;gap:10px;font-size:.84rem;cursor:pointer;">
                            <input type="checkbox" style="accent-color:var(--primary);"> Detalle por actividad
                        </label>
                    </div>
                </div>
                <div style="background:var(--gray-50);border-radius:12px;padding:12px 14px;border:1px solid var(--gray-200);display:flex;align-items:flex-start;gap:8px;">
                    <i class="fas fa-circle-info" style="color:var(--primary);margin-top:1px;font-size:.85rem;"></i>
                    <p style="font-size:.76rem;color:var(--gray-500);margin:0;line-height:1.5;">El PDF se generará en español e incluirá el sello de D&F Mindspace. Podrás descargarlo o compartirlo directamente.</p>
                </div>
            </div>
            <div class="modal-footer justify-content-end gap-2">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" style="border-radius:var(--r-pill);font-size:.84rem;font-weight:600;">
                    Cancelar
                </button>
                <button type="button" onclick="downloadPDF()" style="background:var(--primary);color:white;border:none;border-radius:var(--r-pill);padding:10px 22px;font-size:.84rem;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;display:inline-flex;align-items:center;gap:7px;">
                    <i class="fas fa-file-arrow-down"></i> Descargar PDF
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.pdf-period-btn {
    padding:9px 12px; border-radius:10px; border:1.5px solid var(--gray-200);
    background:white; font-size:.82rem; font-weight:600; color:var(--gray-500);
    cursor:pointer; font-family:'DM Sans',sans-serif; transition:all .18s;
}
.pdf-period-btn:hover { border-color:var(--primary); color:var(--primary); }
.pdf-period-btn.active { background:var(--primary-soft); border-color:var(--primary); color:var(--primary); }
</style>

<!-- Toast container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    /* ── Sidebar ── */
    const sidebar = document.getElementById('sidebar');
    const menuToggle = document.getElementById('menuToggle');
    menuToggle.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        menuToggle.innerHTML = sidebar.classList.contains('open')
            ? '<i class="fas fa-times"></i>' : '<i class="fas fa-bars"></i>';
    });
    document.addEventListener('click', e => {
        if (window.innerWidth < 992 && sidebar.classList.contains('open')
            && !sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
            sidebar.classList.remove('open');
            menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
        }
    });

    /* ── Chart data ── */
    const chartData = {
        week: {
            labels: ['Lun','Mar','Mié','Jue','Vie'],
            data:   [7.5, 8.0, 8.5, 8.2, 9.0]
        },
        month: {
            labels: ['Sem 1','Sem 2','Sem 3','Sem 4'],
            data:   [7.8, 8.1, 8.4, 8.7]
        },
        quarter: {
            labels: ['Ene','Feb','Mar'],
            data:   [7.4, 8.0, 8.4]
        }
    };

    let currentChart;

    function buildChart(period) {
        const d = chartData[period];
        const ctx = document.getElementById('progressChart').getContext('2d');
        if (currentChart) currentChart.destroy();

        const gradient = ctx.createLinearGradient(0, 0, 0, 180);
        gradient.addColorStop(0,   'rgba(44,186,236,0.18)');
        gradient.addColorStop(1,   'rgba(44,186,236,0)');

        currentChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: d.labels,
                datasets: [{
                    data: d.data,
                    borderColor: '#2cbaec',
                    backgroundColor: gradient,
                    borderWidth: 2.5,
                    pointBackgroundColor: '#2cbaec',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleFont: { family: "'Nunito',sans-serif", size: 12, weight: '700' },
                        bodyFont: { family: "'DM Sans',sans-serif", size: 12 },
                        padding: 10,
                        cornerRadius: 10,
                        callbacks: {
                            label: ctx => ` Promedio: ${ctx.raw}`
                        }
                    }
                },
                scales: {
                    y: {
                        min: 5, max: 10,
                        grid: { color: 'rgba(148,163,184,.12)', drawBorder: false },
                        ticks: {
                            font: { family: "'DM Sans',sans-serif", size: 11 },
                            color: '#94a3b8',
                            padding: 8
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { family: "'DM Sans',sans-serif", size: 11 },
                            color: '#94a3b8'
                        }
                    }
                }
            }
        });
    }

    function setPeriod(period, btn) {
        document.querySelectorAll('.ptab').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        buildChart(period);
    }

    document.addEventListener('DOMContentLoaded', () => buildChart('month'));

    /* ── Feedback threads ── */
    function openThread(id) {
        const el = document.getElementById(`thread-${id}`);
        const isOpen = el.style.display !== 'none';
        // Cerrar todos
        document.querySelectorAll('[id^="thread-"]').forEach(t => t.style.display = 'none');
        if (!isOpen) {
            el.style.display = 'block';
            el.closest('.feedback-thread').querySelector('.unread-dot')?.remove();
            el.closest('.feedback-thread').querySelector('.feedback-item')?.classList.remove('unread');
        }
    }

    function sendReply(threadId) {
        const thread = document.getElementById(`thread-${threadId}`);
        const ta = thread.querySelector('.reply-textarea');
        if (!ta.value.trim()) { showToast('Escribe un mensaje antes de enviar', 'warn'); return; }
        showToast('Mensaje enviado al docente', 'success');
        ta.value = '';
    }

    function generatePDF() {
    // Cerrar el modal si está abierto
    const modal = bootstrap.Modal.getInstance(document.getElementById('pdfModal'));
    if (modal) modal.hide();
    
    // Pequeño retraso para que se cierre el modal
    setTimeout(() => {
        // Abrir la ventana de impresión del navegador
        window.print();
    }, 200);
}

// Función para añadir estilos específicos para la impresión
function addPrintStyles() {
    const style = document.createElement('style');
    style.id = 'print-styles';
    style.textContent = `
        @media print {
            /* Ocultar elementos que no deben imprimirse */
            .sidebar,
            .menu-toggle,
            .btn-pdf,
            .btn-close,
            .modal,
            .modal-backdrop,
            .toast-container,
            .period-tabs,
            .reply-box,
            .btn-send,
            .reply-textarea,
            .reply-label,
            .reply-select,
            .fb-avatar,
            .unread-dot,
            .feedback-item .fb-avatar,
            .feedback-thread .reply-label,
            .feedback-thread .reply-textarea,
            .feedback-thread .reply-footer,
            button,
            .btn,
            .modal-footer button,
            .btn-light,
            .btn-pdf {
                display: none !important;
            }
            
            /* Mostrar todo el contenido principal */
            .main {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }
            
            /* Asegurar que los paneles se vean bien */
            .panel,
            .student-hero,
            .sum-card,
            .achievement,
            .subject-row {
                break-inside: avoid;
                page-break-inside: avoid;
            }
            
            /* Ajustes de color para impresión */
            .student-hero,
            .panel,
            .sum-card {
                background: white !important;
                border: 1px solid #ddd !important;
                box-shadow: none !important;
            }
            
            .hero-avatar {
                background: #f0f0f0 !important;
                border: 1px solid #ccc !important;
            }
            
            /* Eliminar gradientes */
            .btn-pdf,
            .btn-send,
            .brand-icon,
            .user-avatar {
                background: #2cbaec !important;
            }
            
            /* Asegurar que los textos sean legibles */
            body {
                color: black !important;
                background: white !important;
            }
            
            /* Mostrar URLs después de los enlaces (opcional) */
            a[href]:after {
                content: " (" attr(href) ")";
                font-size: 0.8em;
                color: #666;
            }
            
            /* Ajustar márgenes de página */
            @page {
                margin: 1.5cm;
                size: A4;
            }
            
            /* Evitar que los elementos flotantes se corten */
            .content-cols {
                display: block !important;
            }
            
            .chart-panel,
            .fade-up {
                width: 100% !important;
            }
            
            /* Asegurar que las tablas se vean bien */
            table {
                width: 100%;
                border-collapse: collapse;
            }
            
            th, td {
                border: 1px solid #ddd;
                padding: 8px;
            }
            
            th {
                background: #f0f0f0 !important;
            }
        }
    `;
    document.head.appendChild(style);
}

// Llamar a la función cuando el documento esté listo
document.addEventListener('DOMContentLoaded', addPrintStyles);

    /* ── Toast ── */
    function showToast(msg, type = 'info') {
        const colors = {
            success: { bg: '#83bf46',  icon: 'circle-check' },
            warn:    { bg: '#f0ae2a',  icon: 'triangle-exclamation' },
            info:    { bg: '#2cbaec',  icon: 'circle-info' },
        };
        const c = colors[type] || colors.info;
        const id = `t${Date.now()}`;
        document.querySelector('.toast-container').insertAdjacentHTML('beforeend', `
            <div id="${id}" class="toast" role="alert" data-bs-autohide="true" data-bs-delay="3200">
                <div class="toast-header" style="background:${c.bg};color:white;border-radius:13px 13px 0 0;border:none;">
                    <i class="fas fa-${c.icon} me-2"></i>
                    <strong class="me-auto" style="font-size:.82rem;">D&F Mindspace</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body" style="font-size:.84rem;color:#334155;">${msg}</div>
            </div>
        `);
        const el = document.getElementById(id);
        new bootstrap.Toast(el).show();
        el.addEventListener('hidden.bs.toast', () => el.remove());
    }
</script>

</body>
</html>