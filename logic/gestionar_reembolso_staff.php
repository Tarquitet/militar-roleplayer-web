<?php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') { die("No autorizado."); }

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = (int)$_POST['solicitud_id'];
    $accion = $_POST['accion'];

    try {
        $pdo->beginTransaction();

        // 1. Obtener datos de la solicitud
        $stmt = $pdo->prepare("SELECT * FROM solicitudes_reembolso WHERE id = :id FOR UPDATE");
        $stmt->execute([':id' => $id]);
        $sol = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sol || $sol['estado'] !== 'pendiente') throw new Exception("Solicitud no válida.");

        if ($accion === 'aprobar') {
            // A. Devolver TODOS los recursos al equipo (Dinero, Acero, Petróleo)
            $stmt_upd = $pdo->prepare("UPDATE cuentas SET dinero = dinero + :d, acero = acero + :a, petroleo = petroleo + :p WHERE id = :uid");
            $stmt_upd->execute([
                ':d' => $sol['dinero_total'],
                ':a' => $sol['acero_total'],
                ':p' => $sol['petroleo_total'],
                ':uid' => $sol['cuenta_id']
            ]);

            // B. Restar unidades del inventario
            $stmt_inv = $pdo->prepare("UPDATE inventario SET cantidad = cantidad - :qty WHERE id = :inv_id");
            $stmt_inv->execute([':qty' => $sol['cantidad'], ':inv_id' => $sol['inventario_id']]);

            // C. Limpiar inventario si llega a cero
            $pdo->prepare("DELETE FROM inventario WHERE cantidad <= 0")->execute();

            // D. Actualizar estado a 'aprobado'
            $pdo->prepare("UPDATE solicitudes_reembolso SET estado = 'aprobado' WHERE id = :id")->execute([':id' => $id]);

        } else {
            // Caso rechazar: Solo cambiamos el estado para desbloquear el activo en el hangar
            $pdo->prepare("UPDATE solicitudes_reembolso SET estado = 'rechazado' WHERE id = :id")->execute([':id' => $id]);
        }

        $pdo->commit();
        header("Location: ../views/staff_dashboard.php?msg=operacion_ok");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error logístico: " . $e->getMessage());
    }
}