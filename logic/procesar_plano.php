<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'lider') exit();

require_once '../config/conexion.php';
$txt = require '../config/textos.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $lider_id = $_SESSION['usuario_id'];
    $item_id = (int)$_POST['catalogo_id'];

    try {
        $pdo->beginTransaction();

        // 1. SEGURIDAD: Verificar si ya tiene el plano para no cobrarle doble
        $stmt_check = $pdo->prepare("SELECT id FROM planos_desbloqueados WHERE cuenta_id = :uid AND catalogo_id = :cid");
        $stmt_check->execute([':uid' => $lider_id, ':cid' => $item_id]);
        if ($stmt_check->fetch()) {
            $pdo->rollBack();
            $pdo = null;
            header("Location: ../views/lider_tienda.php?error=plano_ya_desbloqueado");
            exit();
        }

        // 2. Obtener SOLO el costo de dinero del vehículo base
        $stmt_item = $pdo->prepare("SELECT costo_dinero FROM catalogo_tienda WHERE id = :id");
        $stmt_item->execute([':id' => $item_id]);
        $item = $stmt_item->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            throw new Exception($txt['LOGIC']['ERR_ACTIVO_NO_ENCONTRADO']);
        }

        $costo_plano = $item['costo_dinero'];

        // 3. Verificar fondos (SOLO DINERO, ignoramos acero y petróleo)
        $stmt_user = $pdo->prepare("SELECT dinero FROM cuentas WHERE id = :id FOR UPDATE");
        $stmt_user->execute([':id' => $lider_id]);
        $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

        if ($user['dinero'] >= $costo_plano) {
            
            // 4. Deducción de capital
            $stmt_pay = $pdo->prepare("UPDATE cuentas SET dinero = dinero - :d WHERE id = :id");
            $stmt_pay->execute([':d' => $costo_plano, ':id' => $lider_id]);

            // 5. Otorgar el Plano (Permiso)
            $stmt_plano = $pdo->prepare("INSERT INTO planos_desbloqueados (cuenta_id, catalogo_id) VALUES (:uid, :cid)");
            $stmt_plano->execute([':uid' => $lider_id, ':cid' => $item_id]);

            $pdo->commit();
            
            // Cierre táctico
            $stmt_check = null; $stmt_item = null; $stmt_user = null; $stmt_pay = null; $stmt_plano = null; $pdo = null;
            
            header("Location: ../views/lider_tienda.php?status=plano_adquirido");
            exit();
            
        } else {
            $pdo->rollBack();
            $pdo = null;
            header("Location: ../views/lider_tienda.php?error=fondos_insuficientes");
            exit();
        }

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        $pdo = null;
        die($txt['LOGIC']['ERR_CADENA_SUMINISTRO'] . $e->getMessage());
    }
} else {
    header("Location: ../views/lider_tienda.php");
    exit();
}
?>