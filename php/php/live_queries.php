<?php
// php/live_queries.php
// Devuelve queries activas en pg_stat_activity (para el auto-refresh del terminal)

include 'config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'admin') {
    http_response_code(403);
    echo json_encode([]);
    exit();
}

$rows = [];
$r = mysqli_query($conn, "
    SELECT 
        pid,
        state,
        ROUND(EXTRACT(EPOCH FROM (now() - query_start))::numeric, 2) AS duracion_seg,
        LEFT(query, 80) AS query
    FROM pg_stat_activity
    WHERE state != 'idle'
      AND query NOT LIKE '%pg_stat_activity%'
      AND query NOT LIKE '%live_queries%'
    ORDER BY duracion_seg DESC
    LIMIT 8
");

if ($r) {
    while ($row = mysqli_fetch_assoc($r)) {
        $rows[] = $row;
    }
}

echo json_encode($rows);