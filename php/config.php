<?php
// =============================================
// CONEXIÓN A POSTGRESQL
// =============================================

if (defined('CONFIG_POSTGRES_CARGADO')) {
    return;
}
define('CONFIG_POSTGRES_CARGADO', true);

$host = '192.168.0.13';
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
    // =============================================
    // FUNCIONES MYSQLI ADICIONALES PARA COMPATIBILIDAD
    // =============================================

    if (!function_exists('mysqli_free_result')) {
        function mysqli_free_result($result) {
            // PDO no necesita liberar resultados manualmente
            return true;
        }
    }

    if (!function_exists('mysqli_fetch_row')) {
        function mysqli_fetch_row($result) {
            if ($result) {
                $row = $result->fetch(PDO::FETCH_NUM);
                return $row !== false ? $row : null;
            }
            return null;
        }
    }

    if (!function_exists('mysqli_fetch_object')) {
        function mysqli_fetch_object($result) {
            if ($result) {
                return $result->fetch(PDO::FETCH_OBJ);
            }
            return null;
        }
    }

    if (!function_exists('mysqli_stmt_bind_param')) {
        function mysqli_stmt_bind_param($stmt, $types, ...$params) {
            // Esto es más complejo, mejor usar consultas preparadas directamente
            return false;
        }
    }

    if (!function_exists('mysqli_prepare')) {
        function mysqli_prepare($conn, $sql) {
            return $conn->pdo->prepare($sql);
        }
    }

    if (!function_exists('mysqli_stmt_execute')) {
        function mysqli_stmt_execute($stmt) {
            return $stmt->execute();
        }
    }

    if (!function_exists('mysqli_stmt_get_result')) {
        function mysqli_stmt_get_result($stmt) {
            return $stmt;
        }
    }

    if (!function_exists('mysqli_fetch_assoc')) {
        function mysqli_fetch_assoc($result) {
            if ($result) {
                return $result->fetch(PDO::FETCH_ASSOC);
            }
            return false;
        }
    }

    if (!function_exists('mysqli_num_rows')) {
        function mysqli_num_rows($result) {
            if ($result) {
                if ($result instanceof PDOStatement) {
                    return $result->rowCount();
                }
                // Si es un array, contar elementos
                if (is_array($result)) {
                    return count($result);
                }
            }
            return 0;
        }
    }

    if (!function_exists('mysqli_fetch_all')) {
    function mysqli_fetch_all($result, $mode = MYSQLI_ASSOC) {
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            if ($mode === MYSQLI_ASSOC) {
                $rows[] = $row;
            } elseif ($mode === MYSQLI_NUM) {
                $rows[] = array_values($row);
            }
        }
        return $rows;
    }
}
    
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>
