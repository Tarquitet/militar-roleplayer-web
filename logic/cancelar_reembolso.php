<?php
session_start();
require_once '../config/conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['rol']) && $_SESSION['rol'] === 'lider') {
    $id = (int)$_POST['id'];
    $uid = $_SESSION['usuario_id'];

    try {
        // Solo puede cancelar sus propias peticiones pendientes
        $stmt = $pdo->prepare("DELETE FROM solicitudes_reembolso WHERE id = ? AND cuenta_id = ? AND estado = 'pendiente'");
        $stmt->execute([$id, $uid]);
        
        header("Location: ../views/lider_inventario.php?msg=cancel_ok");
    } catch (PDOException $e) { die("Fallo: " . $e->getMessage()); }
}