<?php
session_start();
require_once '../config/conexion.php';

// Verificamos que sea un líder el que envía la petición
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['rol']) && $_SESSION['rol'] === 'lider') {
    $lider_id = $_SESSION['usuario_id'];
    $inv_id = (int)$_POST['inventario_id'];
    $qty = (int)$_POST['cantidad'];

    try {
        // 1. Obtener costos base del activo y validar que el líder realmente tenga ese stock
        $stmt = $pdo->prepare("
            SELECT i.cantidad, c.costo_dinero, c.costo_acero, c.costo_petroleo, c.nombre_vehiculo
            FROM inventario i
            JOIN catalogo_tienda c ON i.catalogo_id = c.id
            WHERE i.id = :id AND i.cuenta_id = :uid
        ");
        $stmt->execute([':id' => $inv_id, ':uid' => $lider_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        // Validación de seguridad de stock
        if (!$item || $item['cantidad'] < $qty || $qty <= 0) {
            header("Location: ../views/lider_inventario.php?error=stock_invalido");
            exit();
        }

        // 2. Calcular montos de reembolso (100% de la inversión inicial)
        $r_dinero = $qty * $item['costo_dinero'];
        $r_acero = $qty * $item['costo_acero'];
        $r_petroleo = $qty * $item['costo_petroleo'];

        // 3. Insertar la solicitud en estado 'pendiente'
        // Esto activará la pantalla de bloqueo en el Hangar del Líder
        $stmt_ins = $pdo->prepare("
            INSERT INTO solicitudes_reembolso 
            (cuenta_id, inventario_id, cantidad, dinero_total, acero_total, petroleo_total, estado) 
            VALUES (:uid, :inv, :qty, :d, :a, :p, 'pendiente')
        ");
        
        $stmt_ins->execute([
            ':uid' => $lider_id,
            ':inv' => $inv_id,
            ':qty' => $qty,
            ':d'   => $r_dinero,
            ':a'   => $r_acero,
            ':p'   => $r_petroleo
        ]);

        // Redirigir al hangar con el aviso de solicitud enviada
        header("Location: ../views/lider_inventario.php?msg=req_enviada");
        exit();

    } catch (Exception $e) {
        die("FALLO CRÍTICO EN TRANSMISIÓN DE DATOS: " . $e->getMessage());
    }
} else {
    // Si no es un líder o no es un POST, fuera de aquí
    header("Location: ../login.php");
    exit();
}