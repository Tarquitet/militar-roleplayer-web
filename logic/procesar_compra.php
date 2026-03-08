<?php
session_start();
require_once '../config/conexion.php';
$txt = require '../config/textos.php'; // Cargamos el diccionario para los errores

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['catalogo_id'])) {
    $lider_id = $_SESSION['usuario_id'];
    $cat_id = (int)$_POST['catalogo_id'];
    $cantidad = (int)($_POST['cantidad'] ?? 1); // Captura la cantidad del modal

    if ($cantidad < 1) $cantidad = 1;

    try {
        $pdo->beginTransaction();

        // 1. Obtener costos base del catálogo
        $stmt_cat = $pdo->prepare("SELECT costo_dinero, costo_acero, costo_petroleo, nombre_vehiculo FROM catalogo_tienda WHERE id = :id");
        $stmt_cat->execute([':id' => $cat_id]);
        $vehiculo = $stmt_cat->fetch(PDO::FETCH_ASSOC);

        if (!$vehiculo) throw new Exception($txt['LOGIC']['ERR_ACTIVO_NO_ENCONTRADO']);

        // 2. CÁLCULO TOTAL: Costo Base x Cantidad
        $total_dinero = $vehiculo['costo_dinero'] * $cantidad;
        $total_acero = $vehiculo['costo_acero'] * $cantidad;
        $total_petroleo = $vehiculo['costo_petroleo'] * $cantidad;

        // 3. Verificar si el líder tiene recursos suficientes para el TOTAL
        $stmt_u = $pdo->prepare("SELECT dinero, acero, petroleo FROM cuentas WHERE id = :id FOR UPDATE");
        $stmt_u->execute([':id' => $lider_id]);
        $user = $stmt_u->fetch(PDO::FETCH_ASSOC);

        if ($user['dinero'] < $total_dinero || $user['acero'] < $total_acero || $user['petroleo'] < $total_petroleo) {
            throw new Exception("Recursos insuficientes para fabricar " . $cantidad . " unidades.");
        }

        // 4. DESCUENTO DE RECURSOS
        $stmt_upd = $pdo->prepare("UPDATE cuentas SET dinero = dinero - :d, acero = acero - :a, petroleo = petroleo - :p WHERE id = :id");
        $stmt_upd->execute([
            ':d' => $total_dinero,
            ':a' => $total_acero,
            ':p' => $total_petroleo,
            ':id' => $lider_id
        ]);

        // 5. ACTUALIZAR INVENTARIO (Sumar unidades compradas)
        $stmt_inv = $pdo->prepare("INSERT INTO inventario (cuenta_id, catalogo_id, cantidad) 
                                   VALUES (:uid, :cid, :qty) 
                                   ON DUPLICATE KEY UPDATE cantidad = cantidad + :qty");
        $stmt_inv->execute([
            ':uid' => $lider_id,
            ':cid' => $cat_id,
            ':qty' => $cantidad
        ]);

        $pdo->commit();
        header("Location: ../views/lider_tienda.php?status=compra_ok&qty=" . $cantidad);
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        // Redirigir con error para que el líder sepa qué pasó
        die($txt['LOGIC']['ERR_CADENA_SUMINISTRO'] . $e->getMessage());
    }
}