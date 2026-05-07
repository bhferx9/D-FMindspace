<?php
// =============================================
// CONEXIÓN A POSTGRESQL
// =============================================

if (defined('CONFIG_POSTGRES_CARGADO')) {
    return;
}
define('CONFIG_POSTGRES_CARGADO', true);

$host = '192.168.0.17';
$port = '5432';
$dbname = 'df_mindspace';
$user = 'admin_user';
$password = 'admin123';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    $conn = new stdClass();
    $conn->pdo = $pdo;
    $conn->last_result = null;
    
    // =============================================
    // FUNCIONES ADAPTADORAS
    // =============================================
    
    if (!function_exists('mysqli_query')) {
        function mysqli_query($conn, $sql) {
            try {
                $conn->last_result = $conn->pdo->query($sql);
                return $conn->last_result;
            } catch(PDOException $e) {
                echo "Error SQL: " . htmlspecialchars($e->getMessage()) . "<br>";
                echo "Consulta: " . htmlspecialchars($sql) . "<br>";
                return false;
            }
        }
    }
    
    if (!function_exists('mysqli_fetch_assoc')) {
        function mysqli_fetch_assoc($result) {
            return $result ? $result->fetch(PDO::FETCH_ASSOC) : false;
        }
    }
    
    if (!function_exists('mysqli_fetch_array')) {
        function mysqli_fetch_array($result) {
            return $result ? $result->fetch(PDO::FETCH_BOTH) : false;
        }
    }
    
    if (!function_exists('mysqli_num_rows')) {
        function mysqli_num_rows($result) {
            return $result ? $result->rowCount() : 0;
        }
    }
    
    if (!function_exists('mysqli_data_seek')) {
        function mysqli_data_seek($result, $offset) {
            // No es necesario en PDO, pero mantenemos compatibilidad
            return true;
        }
    }
    
    if (!function_exists('mysqli_real_escape_string')) {
        function mysqli_real_escape_string($conn, $string) {
            $escaped = $conn->pdo->quote($string);
            return substr($escaped, 1, -1);
        }
    }
    
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>