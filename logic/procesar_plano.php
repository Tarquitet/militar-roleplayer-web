<?php
session_start();
require_once '../config/conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['catalogo_id'])) {
    $lider_id = $_SESSION['usuario_id'];
    $cat_id = (int)$_POST['catalogo_id'];

    try {
        $pdo->beginTransaction();

        // 1. Obtener costo del vehículo (Solo dinero para el plano)
        $stmt_cat = $pdo->prepare("SELECT costo_dinero FROM catalogo_tienda WHERE id = :id");
        $stmt_cat->execute([':id' => $cat_id]);
        $vehiculo = $stmt_cat->fetch(PDO::FETCH_ASSOC);

        // 2. Verificar si ya tiene el plano
        $stmt_check = $pdo->prepare("SELECT id FROM planos_desbloqueados WHERE cuenta_id = :uid AND catalogo_id = :cid");
        $stmt_check->execute([':uid' => $lider_id, ':cid' => $cat_id]);
        
        if ($stmt_check->fetch()) {
            throw new Exception("Ya posees la patente de este activo.");
        }

        // 3. Verificar dinero
        $stmt_u = $pdo->prepare("SELECT dinero FROM cuentas WHERE id = :id FOR UPDATE");
        $stmt_u->execute([':id' => $lider_id]);
        $user = $stmt_u->fetch(PDO::FETCH_ASSOC);

        if ($user['dinero'] < $vehiculo['costo_dinero']) {
            throw new Exception("Capital insuficiente para adquirir la patente.");
        }

        // 4. Cobrar y Registrar
        $pdo->prepare("UPDATE cuentas SET dinero = dinero - :costo WHERE id = :id")
            ->execute([':costo' => $vehiculo['costo_dinero'], ':id' => $lider_id]);

        $pdo->prepare("INSERT INTO planos_desbloqueados (cuenta_id, catalogo_id) VALUES (:uid, :cid)")
            ->execute([':uid' => $lider_id, ':cid' => $cat_id]);

        $pdo->commit();
        header("Location: ../views/lider_tienda.php?status=plano_ok");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error Táctico: " . $e->getMessage());
    }
}