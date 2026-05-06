<?php
session_start();
require_once 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['codigo'])) {
    $codigo = trim($_POST['codigo']);
    $id_padre = $_SESSION['usuario_id'];
    
    // Buscar alumno por código (debes tener un campo 'codigo_vinculacion' en usuarios o tabla aparte)
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE codigo_vinculacion = ? AND tipo = 'alumno'");
    $stmt->bind_param("s", $codigo);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($alumno = $result->fetch_assoc()) {
        $id_alumno = $alumno['id'];
        // Verificar que no exista ya vinculación activa
        $check = $conn->prepare("SELECT id FROM vinculaciones WHERE id_padre = ? AND id_alumno = ?");
        $check->bind_param("ii", $id_padre, $id_alumno);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            $insert = $conn->prepare("INSERT INTO vinculaciones (id_padre, id_alumno, estado) VALUES (?, ?, 'activo')");
            $insert->bind_param("ii", $id_padre, $id_alumno);
            $insert->execute();
            $_SESSION['toast'] = ['success', 'Hijo vinculado correctamente'];
        } else {
            $_SESSION['toast'] = ['warn', 'Este hijo ya está vinculado a tu cuenta'];
        }
    } else {
        $_SESSION['toast'] = ['error', 'Código no válido'];
    }
    header('Location: dashboard_padre.php');
    exit;
}