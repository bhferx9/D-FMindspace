<?php
include 'config.php';

echo "<h2>Probando nueva conexión</h2>";

// Prueba 1: Consulta simple
$sql = "SELECT NOW() as fecha_actual";
$result = mysqli_query($conn, $sql);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "✅ Conexión exitosa a PostgreSQL<br>";
    echo "📅 Fecha del servidor: " . $row['fecha_actual'] . "<br>";
} else {
    echo "❌ Falló la consulta<br>";
}

// Prueba 2: Ver funciones
echo "<br>📋 Funciones disponibles:<br>";
echo "- mysqli_query(): " . (function_exists('mysqli_query') ? '✅' : '❌') . "<br>";
echo "- mysqli_fetch_assoc(): " . (function_exists('mysqli_fetch_assoc') ? '✅' : '❌') . "<br>";
echo "- mysqli_num_rows(): " . (function_exists('mysqli_num_rows') ? '✅' : '❌') . "<br>";

mysqli_close($conn);
?>
