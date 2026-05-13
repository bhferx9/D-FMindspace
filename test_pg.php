<?php
// Configuración de tu VM
$host = '192.168.0.9';  // CAMBIA por la IP real de tu VM
$port = '5432';
$dbname = 'df_mindspace';
$user = 'admin_user';
$password = 'admin123';  // La que configuraste en PostgreSQL

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => true
    ]);
    
    echo "🎉 CONEXIÓN EXITOSA a PostgreSQL\n";
    echo "Base de datos: " . $dbname . "\n";
    echo "Usuario: " . $user . "\n";
    
    // Prueba una consulta simple
    $result = $pdo->query("SELECT version() as version");
    $row = $result->fetch();
    echo "Versión PostgreSQL: " . $row['version'] . "\n";
    
} catch(PDOException $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    
    // Consejos según el error
    if(strpos($e->getMessage(), "could not find driver")) {
        echo "→ Te falta instalar pdo_pgsql en PHP\n";
    } elseif(strpos($e->getMessage(), "Connection refused")) {
        echo "→ ¿Postgres está corriendo? En VM: sudo systemctl status postgresql\n";
        echo "→ ¿Firewall? En VM: sudo ufw status\n";
    } elseif(strpos($e->getMessage(), "authentication failed")) {
        echo "→ Contraseña incorrecta para admin_user\n";
    } elseif(strpos($e->getMessage(), "does not exist")) {
        echo "→ La base de datos '$dbname' no existe\n";
    }
}
?>