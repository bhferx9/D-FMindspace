<?php
// funciones_suscripcion.php - Lógica de suscripciones

function obtenerSuscripcionActiva($conn, $id_usuario) {
    try {
        $stmt = $conn->pdo->prepare("
            SELECT 
                s.*,
                p.nombre as plan_nombre,
                p.precio,
                p.moneda,
                p.periodo,
                p.caracteristicas
            FROM suscripciones s
            JOIN planes p ON s.id_plan = p.id
            WHERE s.id_usuario = ? AND s.estado = 'activo'
            ORDER BY s.fecha_inicio DESC
            LIMIT 1
        ");
        $stmt->execute([$id_usuario]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return null;
    }
}

function obtenerMetodosPago($conn, $id_usuario) {
    try {
        $stmt = $conn->pdo->prepare("
            SELECT * FROM metodos_pago 
            WHERE id_usuario = ? AND activo = TRUE 
            ORDER BY es_principal DESC, created_at DESC
        ");
        $stmt->execute([$id_usuario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return [];
    }
}

function obtenerFacturas($conn, $id_usuario, $limit = 10, $offset = 0) {
    try {
        $stmt = $conn->pdo->prepare("
            SELECT 
                f.*,
                p.nombre as plan_nombre
            FROM facturas f
            JOIN suscripciones s ON f.id_suscripcion = s.id
            JOIN planes p ON s.id_plan = p.id
            WHERE f.id_usuario = ?
            ORDER BY f.fecha_emision DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$id_usuario, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return [];
    }
}

function calcularDiasRestantes($fecha_proxima) {
    if (!$fecha_proxima) return 0;
    $hoy = new DateTime();
    $proxima = new DateTime($fecha_proxima);
    $diferencia = $hoy->diff($proxima);
    return $diferencia->days;
}

function calcularPorcentajePeriodo($fecha_inicio, $fecha_proxima) {
    if (!$fecha_inicio || !$fecha_proxima) return 0;
    $inicio = new DateTime($fecha_inicio);
    $proxima = new DateTime($fecha_proxima);
    $hoy = new DateTime();
    
    $total = $inicio->diff($proxima)->days;
    $transcurrido = $inicio->diff($hoy)->days;
    
    if ($total <= 0) return 0;
    $porcentaje = ($transcurrido / $total) * 100;
    return min(100, max(0, round($porcentaje)));
}

function obtenerEstadisticasPagos($conn, $id_usuario) {
    try {
        $stmt = $conn->pdo->prepare("
            SELECT COALESCE(SUM(monto), 0) as total_gastado 
            FROM facturas 
            WHERE id_usuario = ? AND estado = 'pagado'
        ");
        $stmt->execute([$id_usuario]);
        $total_gastado = $stmt->fetch(PDO::FETCH_ASSOC)['total_gastado'];
        
        $stmt = $conn->pdo->prepare("
            SELECT fecha_pago, monto 
            FROM facturas 
            WHERE id_usuario = ? AND estado = 'pagado'
            ORDER BY fecha_pago DESC 
            LIMIT 1
        ");
        $stmt->execute([$id_usuario]);
        $ultimo_pago = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'total_gastado' => $total_gastado,
            'ultimo_pago' => $ultimo_pago
        ];
    } catch(PDOException $e) {
        return ['total_gastado' => 0, 'ultimo_pago' => null];
    }
}
?>