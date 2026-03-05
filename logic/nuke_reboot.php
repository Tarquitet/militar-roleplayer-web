<?php
session_start();

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_once '../config/conexion.php';

    try {
        // Iniciamos la transacción de seguridad
        $pdo->beginTransaction();

        // 1. Vaciamos la tabla de inventarios
        $pdo->exec("DELETE FROM inventario");
        
        // 2. Vaciamos la tabla de flotas
        $pdo->exec("DELETE FROM flotas");

        // 3. Reseteamos los recursos, nombres y naciones de los líderes
        // Nota: NO borramos las cuentas, solo las limpiamos
        $stmt = $pdo->prepare("UPDATE cuentas SET nombre_equipo = NULL, bandera_url = NULL, dinero = 0, acero = 0, petroleo = 0, naciones_activas = NULL WHERE rol = 'lider'");
        $stmt->execute();

        // Si todo salió bien, confirmamos los cambios
        $pdo->commit();
        $pdo->exec("INSERT INTO bitacora (mensaje, categoria) VALUES ('☢️ REBOOT TOTAL: Se ha reiniciado la temporada. Todos los activos han sido eliminados.', 'nuke')");

        // Regresamos al dashboard con un mensaje de éxito destructivo
        header("Location: ../views/staff_dashboard.php?mensaje=nuke");
        exit();

    } catch (PDOException $e) {
        // Si algo falla, deshacemos cualquier cambio que se haya intentado hacer
        $pdo->rollBack();
        die("Error crítico durante el Reboot: " . $e->getMessage());
    }
} else {
    // Si intentan entrar por URL, los pateamos al dashboard
    header("Location: ../views/staff_dashboard.php");
    exit();
}
?>