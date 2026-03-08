<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['inventario_id'])) {
    $inventario_id = (int)$_POST['inventario_id'];

    try {
        $pdo->beginTransaction();

        // 1. Buscar el item en el inventario y saber cuánto costó en el catálogo
        $stmt = $pdo->prepare("
            SELECT i.cuenta_id, i.cantidad, c.dinero, c.acero, c.petroleo 
            FROM inventario i
            JOIN catalogo_tienda c ON i.catalogo_id = c.id
            WHERE i.id = :inv_id FOR UPDATE
        ");
        $stmt->execute([':inv_id' => $inventario_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            throw new Exception("El activo no existe en el inventario.");
        }

        // 2. Devolver los recursos al jugador (El valor de 1 unidad)
        $stmt_reembolso = $pdo->prepare("
            UPDATE cuentas 
            SET dinero = dinero + :din, 
                acero = acero + :ace, 
                petroleo = petroleo + :pet 
            WHERE id = :uid
        ");
        $stmt_reembolso->execute([
            ':din' => $item['dinero'],
            ':ace' => $item['acero'],
            ':pet' => $item['petroleo'],
            ':uid' => $item['cuenta_id']
        ]);

        // 3. Reducir la cantidad en el inventario o eliminar la fila si solo tenía 1
        if ($item['cantidad'] > 1) {
            $stmt_restar = $pdo->prepare("UPDATE inventario SET cantidad = cantidad - 1 WHERE id = :inv_id");
            $stmt_restar->execute([':inv_id' => $inventario_id]);
        } else {
            $stmt_borrar = $pdo->prepare("DELETE FROM inventario WHERE id = :inv_id");
            $stmt_borrar->execute([':inv_id' => $inventario_id]);
        }

        $pdo->commit();
        // Redirigir de vuelta al inventario del usuario que estábamos viendo
        header("Location: ../views/staff_ver_inventario.php?id=" . $item['cuenta_id'] . "&status=reembolso_ok");
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("Error en reembolso: " . $e->getMessage());
    }
} else {
    header("Location: ../views/staff_dashboard.php");
    exit();
}
?>