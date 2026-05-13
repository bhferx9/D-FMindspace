<?php
// php/query_runner.php
// Ejecuta queries SQL desde el panel admin con restricciones de seguridad

include 'config.php';
session_start();

header('Content-Type: application/json');

// Solo admins autenticados
if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado.']);
    exit();
}

$sql = trim($_POST['sql'] ?? '');
if (!$sql) {
    echo json_encode(['error' => 'Query vacía.']);
    exit();
}

// ── Bloquear comandos destructivos en producción ──
// Comenta estas líneas si necesitas permisos totales en local
$bloqueadas = ['DROP', 'TRUNCATE', 'DELETE FROM pg_', 'ALTER SYSTEM', 'COPY TO', 'COPY FROM', 'CREATE EXTENSION', 'pg_terminate_backend'];
$sqlUpper = strtoupper($sql);
foreach ($bloqueadas as $bl) {
    if (str_contains($sqlUpper, strtoupper($bl))) {
        echo json_encode(['error' => "Operación bloqueada por seguridad: {$bl}"]);
        exit();
    }
}

try {
    $result = mysqli_query($conn, $sql);

    if ($result === false) {
        echo json_encode(['error' => mysqli_error($conn)]);
        exit();
    }

    if ($result === true) {
        // INSERT, UPDATE, DELETE, CREATE, etc.
        echo json_encode([
            'rows'     => [],
            'affected' => mysqli_affected_rows($conn)
        ]);
        exit();
    }

    // SELECT – devolver filas
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    echo json_encode(['rows' => $rows]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}