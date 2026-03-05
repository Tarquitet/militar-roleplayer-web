<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'lider') exit();

require_once '../config/conexion.php';

// Importamos el diccionario de textos
$txt = require '../config/textos.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $lider_id = $_SESSION['usuario_id'];
    $item_id = (int)$_POST['catalogo_id'];
    // Captura de cantidad desde el formulario del Árbol Tecnológico
    $cantidad = isset($_POST['cantidad']) ? (int)$_POST['cantidad'] : 1;

    // Validación de seguridad para cantidades negativas
    if ($cantidad <= 0) {
        header("Location: ../views/lider_tienda.php?error=cantidad_invalida");
        exit();
    }

    try {
        $pdo->beginTransaction();

        // 1. Obtener precio base del vehículo
        $stmt_item = $pdo->prepare("SELECT * FROM catalogo_tienda WHERE id = :id");
        $stmt_item->execute([':id' => $item_id]);
        $item = $stmt_item->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            // Usamos el texto centralizado para la excepción
            throw new Exception($txt['LOGIC']['ERR_ACTIVO_NO_ENCONTRADO']);
        }

        // 2. Cálculo de Costo Total (Precio x Cantidad)
        $total_dinero = $item['costo_dinero'] * $cantidad;
        $total_acero = $item['costo_acero'] * $cantidad;
        $total_petroleo = $item['costo_petroleo'] * $cantidad;

        // 3. Obtener recursos del líder con bloqueo de fila
        $stmt_user = $pdo->prepare("SELECT dinero, acero, petroleo FROM cuentas WHERE id = :id FOR UPDATE");
        $stmt_user->execute([':id' => $lider_id]);
        $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

        // 4. Validación de Solvencia Económica
        if ($user['dinero'] >= $total_dinero && 
            $user['acero'] >= $total_acero && 
            $user['petroleo'] >= $total_petroleo) {
            
            // A. Deducción de Recursos
            $stmt_pay = $pdo->prepare("UPDATE cuentas SET dinero = dinero - :d, acero = acero - :a, petroleo = petroleo - :p WHERE id = :id");
            $stmt_pay->execute([
                ':d' => $total_dinero,
                ':a' => $total_acero,
                ':p' => $total_petroleo,
                ':id' => $lider_id
            ]);

            // B. Incremento en Hangar (Suma la cantidad solicitada)
            // Nota: Se requiere índice UNIQUE en (cuenta_id, catalogo_id) en la tabla inventario
            $stmt_inv = $pdo->prepare("INSERT INTO inventario (cuenta_id, catalogo_id, cantidad) 
                                       VALUES (:uid, :cid, :cant) 
                                       ON DUPLICATE KEY UPDATE cantidad = cantidad + :cant");
            $stmt_inv->execute([
                ':uid' => $lider_id, 
                ':cid' => $item_id,
                ':cant' => $cantidad
            ]);

            $pdo->commit();
            header("Location: ../views/lider_tienda.php?status=compra_exitosa&unidades=$cantidad");
            exit();

        } else {
            $pdo->rollBack();
            header("Location: ../views/lider_tienda.php?error=fondos_insuficientes");
            exit();
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // Usamos el texto centralizado para el error crítico
        die($txt['LOGIC']['ERR_CADENA_SUMINISTRO'] . $e->getMessage());
    }
} else {
    header("Location: ../views/lider_tienda.php");
    exit();
}
?>