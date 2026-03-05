<?php
session_start();
// Validación de rango de Mando
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'lider') {
    exit("Acceso denegado: Protocolo de Seguridad Activo.");
}

require_once '../config/conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $lider_id = $_SESSION['usuario_id'];
    $slot = (int)$_POST['slot'];
    
    // Captura técnica de activos
    $insignia = trim($_POST['insignia']);
    $e1 = trim($_POST['escolta_1']);
    $e2 = trim($_POST['escolta_2']);
    $e3 = trim($_POST['escolta_3']);
    $e4 = trim($_POST['escolta_4']);

    try {
        // Motor de actualización de despliegue
        // Se requiere un índice UNIQUE en (cuenta_id, slot) para que funcione correctamente
        $sql = "INSERT INTO flotas (cuenta_id, slot, insignia, escolta_1, escolta_2, escolta_3, escolta_4) 
                VALUES (:uid, :slot, :ins, :e1, :e2, :e3, :e4)
                ON DUPLICATE KEY UPDATE 
                insignia = VALUES(insignia), 
                escolta_1 = VALUES(escolta_1), 
                escolta_2 = VALUES(escolta_2), 
                escolta_3 = VALUES(escolta_3), 
                escolta_4 = VALUES(escolta_4)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':uid'  => $lider_id,
            ':slot' => $slot,
            ':ins'  => $insignia,
            ':e1'   => $e1,
            ':e2'   => $e2,
            ':e3'   => $e3,
            ':e4'   => $e4
        ]);

        // Retorno al Hangar Operativo
        header("Location: ../views/lider_inventario.php?status=despliegue_exitoso");
        exit();

    } catch (PDOException $e) {
        die("Error en la red de comunicaciones navales: " . $e->getMessage());
    }
} else {
    header("Location: ../views/lider_inventario.php");
    exit();
}