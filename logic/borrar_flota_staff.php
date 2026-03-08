<?php
session_start();
require_once '../config/conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['rol']) && $_SESSION['rol'] === 'staff') {
    $flota_id = (int)$_POST['flota_id'];
    $lider_id = (int)$_POST['lider_id'];

    try {
        // 1. Obtener datos antes de borrar para la bitácora
        $stmt = $pdo->prepare("SELECT c.nombre_equipo, f.slot FROM flotas f JOIN cuentas c ON f.cuenta_id = c.id WHERE f.id = ?");
        $stmt->execute([$flota_id]);
        $info = $stmt->fetch();

        if ($info) {
            // 2. Eliminar de la base de datos
            $pdo->prepare("DELETE FROM flotas WHERE id = ?")->execute([$flota_id]);

            // 3. Notificación oficial
            $msj = "LOGÍSTICA: El Estado Mayor ha DESTRUIDO la flota de " . $info['nombre_equipo'] . " (Slot " . $info['slot'] . ").";
            $pdo->prepare("INSERT INTO bitacora (mensaje, categoria) VALUES (?, 'combate')")->execute([$msj]);
        }

        header("Location: ../views/staff_ver_inventario.php?id=" . $lider_id . "&msg=success");
    } catch (PDOException $e) { die("Error: " . $e->getMessage()); }
}