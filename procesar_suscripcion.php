<?php
include 'php/config.php';
session_start();

// Verificar que el usuario sea padre
if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] != 'padre') {
    header("Location: index.php");
    exit();
}

$id_usuario = (int)$_SESSION['user_id'];
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// Función para enviar respuesta JSON
function responderJSON($success, $message, $data = []) {
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}

// Función para redirigir con mensaje
function redirigirConMensaje($url, $tipo, $mensaje) {
    $_SESSION['toast'] = [$tipo, $mensaje];
    header("Location: $url");
    exit();
}

// =============================================
// PROCESAR ACCIONES
// =============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ACCIÓN: Guardar método de pago
    if ($accion === 'guardar_metodo_pago') {
        $tipo = $_POST['tipo'] ?? 'stripe';
        
        if ($tipo === 'stripe') {
            $marca = $_POST['marca'] ?? 'Visa';
            $ultimos4 = $_POST['ultimos4'] ?? '4242';
            $expira_mes = $_POST['expira_mes'] ?? 12;
            $expira_anio = $_POST['expira_anio'] ?? 2028;
            
            try {
                // Desactivar método principal actual
                $stmt = $conn->pdo->prepare("UPDATE metodos_pago SET es_principal = FALSE WHERE id_usuario = ?");
                $stmt->execute([$id_usuario]);
                
                // Insertar nuevo método
                $stmt = $conn->pdo->prepare("
                    INSERT INTO metodos_pago (id_usuario, tipo, marca, ultimos4, expira_mes, expira_anio, procesador, es_principal, activo)
                    VALUES (?, 'card', ?, ?, ?, ?, 'stripe', TRUE, TRUE)
                ");
                $stmt->execute([$id_usuario, $marca, $ultimos4, $expira_mes, $expira_anio]);
                
                redirigirConMensaje('suscripcion.php', 'success', 'Método de pago actualizado correctamente');
            } catch(PDOException $e) {
                redirigirConMensaje('suscripcion.php', 'error', 'Error al guardar método de pago');
            }
        }
        
        elseif ($tipo === 'paypal') {
            $email = $_POST['email'] ?? '';
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                redirigirConMensaje('suscripcion.php', 'error', 'Correo electrónico inválido');
            }
            
            try {
                // Desactivar método principal actual
                $stmt = $conn->pdo->prepare("UPDATE metodos_pago SET es_principal = FALSE WHERE id_usuario = ?");
                $stmt->execute([$id_usuario]);
                
                // Insertar nuevo método
                $stmt = $conn->pdo->prepare("
                    INSERT INTO metodos_pago (id_usuario, tipo, paypal_email, procesador, es_principal, activo)
                    VALUES (?, 'paypal', ?, 'paypal', TRUE, TRUE)
                ");
                $stmt->execute([$id_usuario, $email]);
                
                redirigirConMensaje('suscripcion.php', 'success', 'Cuenta de PayPal vinculada correctamente');
            } catch(PDOException $e) {
                redirigirConMensaje('suscripcion.php', 'error', 'Error al vincular PayPal');
            }
        }
    }
    
    // ACCIÓN: Cambiar plan
    elseif ($accion === 'cambiar_plan') {
        $nuevo_plan = $_POST['plan'] ?? '';
        $planes_map = [
            'basico' => 1,
            'familiar' => 2,
            'institucional' => 3
        ];
        
        $id_plan = $planes_map[$nuevo_plan] ?? 0;
        
        if ($id_plan == 0) {
            redirigirConMensaje('suscripcion.php', 'error', 'Plan no válido');
        }
        
        try {
            // Obtener suscripción actual
            $stmt = $conn->pdo->prepare("SELECT id FROM suscripciones WHERE id_usuario = ? AND estado = 'activo'");
            $stmt->execute([$id_usuario]);
            $suscripcion = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($suscripcion) {
                // Cancelar suscripción actual
                $stmt = $conn->pdo->prepare("
                    UPDATE suscripciones 
                    SET estado = 'cancelado', fecha_cancelacion = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $stmt->execute([$suscripcion['id']]);
            }
            
            // Crear nueva suscripción
            $fecha_inicio = date('Y-m-d H:i:s');
            $fecha_renovacion = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            $stmt = $conn->pdo->prepare("
                INSERT INTO suscripciones (id_usuario, id_plan, estado, fecha_inicio, fecha_proxima_renovacion)
                VALUES (?, ?, 'activo', ?, ?)
            ");
            $stmt->execute([$id_usuario, $id_plan, $fecha_inicio, $fecha_renovacion]);
            
            redirigirConMensaje('suscripcion.php', 'success', 'Plan cambiado exitosamente');
        } catch(PDOException $e) {
            redirigirConMensaje('suscripcion.php', 'error', 'Error al cambiar de plan');
        }
    }
    
    // ACCIÓN: Cancelar suscripción
    elseif ($accion === 'cancelar_suscripcion') {
        $motivo = $_POST['motivo'] ?? '';
        
        try {
            $stmt = $conn->pdo->prepare("
                UPDATE suscripciones 
                SET estado = 'cancelado', 
                    fecha_cancelacion = CURRENT_TIMESTAMP,
                    motivo_cancelacion = ?
                WHERE id_usuario = ? AND estado = 'activo'
            ");
            $stmt->execute([$motivo, $id_usuario]);
            
            redirigirConMensaje('suscripcion.php', 'warn', 'Suscripción cancelada. Tendrás acceso hasta el final del período actual.');
        } catch(PDOException $e) {
            redirigirConMensaje('suscripcion.php', 'error', 'Error al cancelar suscripción');
        }
    }
    
    // ACCIÓN: Descargar factura
    elseif ($accion === 'descargar_factura') {
        $factura_id = $_POST['factura_id'] ?? '';
        
        // Simular descarga (en producción, generar PDF real)
        $stmt = $conn->pdo->prepare("SELECT * FROM facturas WHERE numero_factura = ? AND id_usuario = ?");
        $stmt->execute([$factura_id, $id_usuario]);
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($factura) {
            responderJSON(true, 'Descargando factura...', ['factura' => $factura]);
        } else {
            responderJSON(false, 'Factura no encontrada');
        }
    }
    
    // ACCIÓN: Reactivar suscripción
    elseif ($accion === 'reactivar_suscripcion') {
        try {
            // Buscar plan familiar por defecto
            $stmt = $conn->pdo->prepare("SELECT id FROM planes WHERE nombre = 'Plan Familiar' LIMIT 1");
            $stmt->execute();
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);
            $id_plan = $plan ? $plan['id'] : 2;
            
            // Crear nueva suscripción
            $fecha_inicio = date('Y-m-d H:i:s');
            $fecha_renovacion = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            $stmt = $conn->pdo->prepare("
                INSERT INTO suscripciones (id_usuario, id_plan, estado, fecha_inicio, fecha_proxima_renovacion)
                VALUES (?, ?, 'activo', ?, ?)
            ");
            $stmt->execute([$id_usuario, $id_plan, $fecha_inicio, $fecha_renovacion]);
            
            redirigirConMensaje('suscripcion.php', 'success', 'Suscripción reactivada correctamente');
        } catch(PDOException $e) {
            redirigirConMensaje('suscripcion.php', 'error', 'Error al reactivar suscripción');
        }
    }
    
    // ACCIÓN: Simular pago (demo)
    elseif ($accion === 'simular_pago') {
        try {
            // Obtener suscripción activa
            $stmt = $conn->pdo->prepare("
                SELECT s.id, p.nombre, p.precio 
                FROM suscripciones s
                JOIN planes p ON s.id_plan = p.id
                WHERE s.id_usuario = ? AND s.estado = 'activo'
            ");
            $stmt->execute([$id_usuario]);
            $suscripcion = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($suscripcion) {
                // Generar número de factura
                $num_factura = 'FACT-' . date('Ymd') . '-' . rand(1000, 9999);
                
                // Crear factura
                $stmt = $conn->pdo->prepare("
                    INSERT INTO facturas (id_suscripcion, id_usuario, numero_factura, monto, estado, fecha_emision, fecha_pago)
                    VALUES (?, ?, ?, ?, 'pagado', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ");
                $stmt->execute([
                    $suscripcion['id'],
                    $id_usuario,
                    $num_factura,
                    $suscripcion['precio']
                ]);
                
                // Actualizar fecha de renovación
                $nueva_renovacion = date('Y-m-d H:i:s', strtotime('+30 days'));
                $stmt = $conn->pdo->prepare("
                    UPDATE suscripciones 
                    SET fecha_proxima_renovacion = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id_usuario = ? AND estado = 'activo'
                ");
                $stmt->execute([$nueva_renovacion, $id_usuario]);
                
                responderJSON(true, 'Pago simulado exitosamente', ['renovacion' => $nueva_renovacion]);
            } else {
                responderJSON(false, 'No hay suscripción activa');
            }
        } catch(PDOException $e) {
            responderJSON(false, 'Error al procesar pago: ' . $e->getMessage());
        }
    }
    
    else {
        redirigirConMensaje('suscripcion.php', 'error', 'Acción no válida');
    }
}

// GET - Descargar factura
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['descargar_factura'])) {
    $factura_id = $_GET['descargar_factura'];
    
    // Redirigir a un generador de PDF (simulado)
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="factura_' . $factura_id . '.pdf"');
    // En producción, generar PDF real aquí
    echo "PDF simulado para factura: " . $factura_id;
    exit();
}
?>