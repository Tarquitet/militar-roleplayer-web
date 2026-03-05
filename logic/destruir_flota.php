<?php
session_start();

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $flota_id = (int)$_POST['flota_id'];
    $equipo_id = (int)$_POST['equipo_id'];

    try {
        // Borramos específicamente esa flota
        $stmt = $pdo->prepare("DELETE FROM flotas WHERE id = :id");
        $stmt->bindParam(':id', $flota_id);
        $stmt->execute();

        // Regresamos al inventario de ese mismo equipo con un mensaje de éxito
        header("Location: ../views/staff_ver_inventario.php?id=" . $equipo_id . "&mensaje=destruida");
        $mensaje_log = "La flota de " . $nombre_equipo . " ha sido destruida por orden del Staff.";
        $stmt_log = $pdo->prepare("INSERT INTO bitacora (mensaje, categoria) VALUES (:msg, 'combate')");
        $stmt_log->execute([':msg' => $mensaje_log]);
        exit();

    } catch (PDOException $e) {
        die("Error al destruir la flota: " . $e->getMessage());
    }
} else {
    header("Location: ../views/staff_dashboard.php");
    exit();
}
?>