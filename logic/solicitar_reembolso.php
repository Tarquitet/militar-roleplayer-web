<?php
session_start();
require_once '../config/conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['rol']) && $_SESSION['rol'] === 'lider') {
    $lider_id = $_SESSION['usuario_id'];
    $inv_id = (int)$_POST['inventario_id'];
    $qty = (int)$_POST['cantidad'];
    
    // NUEVO: Capturamos si la petición viene del botón amarillo (Tradeo)
    $es_tradeo = isset($_POST['es_tradeo']) && $_POST['es_tradeo'] == '1' ? 1 : 0;

    try {
        $stmt = $pdo->prepare("
            SELECT i.cantidad, c.costo_dinero, c.costo_acero, c.costo_petroleo, c.nombre_vehiculo
            FROM inventario i
            JOIN catalogo_tienda c ON i.catalogo_id = c.id
            WHERE i.id = :id AND i.cuenta_id = :uid
        ");
        $stmt->execute([':id' => $inv_id, ':uid' => $lider_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item || $item['cantidad'] < $qty || $qty <= 0) {
            header("Location: ../views/lider_inventario.php?error=stock_invalido");
            exit();
        }

        // EL BLINDAJE: Si es tradeo, el reembolso monetario es CERO.
        if ($es_tradeo) {
            $r_dinero = 0; 
            $r_acero = 0; 
            $r_petroleo = 0;
        } else {
            // Si es de tienda, calcula el dinero normal
            $r_dinero = $qty * $item['costo_dinero'];
            $r_acero = $qty * $item['costo_acero'];
            $r_petroleo = $qty * $item['costo_petroleo'];
        }

        // Insertamos la petición añadiendo la bandera 'es_tradeo'
        $stmt_ins = $pdo->prepare("
            INSERT INTO solicitudes_reembolso 
            (cuenta_id, inventario_id, cantidad, dinero_total, acero_total, petroleo_total, estado, es_tradeo) 
            VALUES (:uid, :inv, :qty, :d, :a, :p, 'pendiente', :es_tr)
        ");
        
        $stmt_ins->execute([
            ':uid' => $lider_id, 
            ':inv' => $inv_id, 
            ':qty' => $qty,
            ':d' => $r_dinero, 
            ':a' => $r_acero, 
            ':p' => $r_petroleo, 
            ':es_tr' => $es_tradeo
        ]);

        header("Location: ../views/lider_inventario.php?msg=req_enviada");
        exit();

    } catch (Exception $e) { 
        die("FALLO CRÍTICO: " . $e->getMessage()); 
    }
} else {
    header("Location: ../login.php"); 
    exit();
}
?>