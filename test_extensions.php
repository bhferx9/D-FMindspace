<?php
echo "Extensiones cargadas:<br>";
$extensions = get_loaded_extensions();
sort($extensions);
foreach ($extensions as $ext) {
    if (strpos($ext, 'mysql') !== false || strpos($ext, 'pgsql') !== false) {
        echo "- $ext: " . (extension_loaded($ext) ? '✅' : '❌') . "<br>";
    }
}

echo "<br>mysqli function exists: " . (function_exists('mysqli_query') ? 'YES (nativa)' : 'NO (podemos crear la nuestra)');
?>