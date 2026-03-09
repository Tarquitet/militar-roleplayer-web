<?php
session_start();
require_once '../config/conexion.php';

// Verificamos que sea un líder y sea una petición POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && $_SESSION['rol'] === 'lider') {
    $flota_id = (int)$_POST['flota_id'];
    $lider_id = $_SESSION['usuario_id']; // El ID del líder actual

    try {
        // Solo puede borrar la flota si le pertenece (cuenta_id = $lider_id)
        $stmt = $pdo->prepare("DELETE FROM flotas WHERE id = ? AND cuenta_id = ?");
        $stmt->execute([$flota_id, $lider_id]);
        
        header("Location: ../views/lider_inventario.php?msg=fleet_deleted");
    } catch (Exception $e) { 
        die("Fallo Crítico: " . $e->getMessage()); 
    }
}
?>