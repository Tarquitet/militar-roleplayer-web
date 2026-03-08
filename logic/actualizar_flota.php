<?php
session_start();
require_once '../config/conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['rol']) && $_SESSION['rol'] === 'lider') {
    $uid = $_SESSION['usuario_id'];
    $slot = (int)$_POST['slot'];
    $ins = $_POST['insignia'];
    $e1 = $_POST['escolta_1']; $e2 = $_POST['escolta_2']; $e3 = $_POST['escolta_3']; $e4 = $_POST['escolta_4'];

    try {
        // Usamos ON DUPLICATE KEY para actualizar si el slot ya existe
        $stmt = $pdo->prepare("
            INSERT INTO flotas (cuenta_id, slot, insignia, escolta_1, escolta_2, escolta_3, escolta_4)
            VALUES (:uid, :sl, :ins, :e1, :e2, :e3, :e4)
            ON DUPLICATE KEY UPDATE 
            insignia = VALUES(insignia), escolta_1 = VALUES(escolta_1), 
            escolta_2 = VALUES(escolta_2), escolta_3 = VALUES(escolta_3), escolta_4 = VALUES(escolta_4)
        ");
        $stmt->execute([':uid'=>$uid, ':sl'=>$slot, ':ins'=>$ins, ':e1'=>$e1, ':e2'=>$e2, ':e3'=>$e3, ':e4'=>$e4]);
        
        header("Location: ../views/lider_inventario.php?msg=fleet_ok");
    } catch (PDOException $e) { die("Fallo en hangar: " . $e->getMessage()); }
}