<?php
session_start();
require_once '../config/conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['rol']) && $_SESSION['rol'] === 'lider') {
    $lider_id = $_SESSION['usuario_id'];
    $accion = $_POST['accion'];

    try {
        $pdo->beginTransaction();

        if ($accion === 'crear') {
            $receptor_id = (int)$_POST['receptor_id'];
            $cat_req_id = (int)$_POST['vehiculo_requerido_id'];
            $q_req = (int)$_POST['cantidad_requerida'];
            
            $cat_ofre_id = !empty($_POST['vehiculo_ofrecido_id']) ? (int)$_POST['vehiculo_ofrecido_id'] : NULL;
            $q_ofre = (int)($_POST['cantidad_ofrecida'] ?? 0);
            
            $ofrece_d = (int)$_POST['ofrece_dinero'];
            $ofrece_a = (int)$_POST['ofrece_acero'];
            $ofrece_p = (int)$_POST['ofrece_petroleo'];

            // 1. VALIDACIÓN DE STOCK REAL DE OFERTA
            if ($cat_ofre_id) {
                $stmt_stock = $pdo->prepare("SELECT cantidad FROM inventario WHERE cuenta_id = :uid AND catalogo_id = :cid");
                $stmt_stock->execute([':uid' => $lider_id, ':cid' => $cat_ofre_id]);
                $stock_actual = (int)$stmt_stock->fetchColumn();
                
                if ($stock_actual < $q_ofre) throw new Exception("Error: Cantidad ofrecida superior al stock disponible.");
            }

            // 2. REGISTRO DEL CONTRATO EN ESTADO ACTIVO
            $stmt_ins = $pdo->prepare("
                INSERT INTO mercado_tradeos 
                (ofertante_id, receptor_id, vehiculo_ofrecido_id, cantidad_ofrecida, ofrece_dinero, ofrece_acero, ofrece_petroleo, 
                 vehiculo_requerido_id, cantidad_requerida, estado) 
                VALUES (:oid, :rid, :vof, :qof, :od, :oa, :op, :vreq, :qreq, 'activo')
            ");
            $stmt_ins->execute([
                ':oid' => $lider_id, ':rid' => $receptor_id, ':vof' => $cat_ofre_id,
                ':qof' => $q_ofre, ':od' => $ofrece_d, ':oa' => $ofrece_a, ':op' => $ofrece_p,
                ':vreq' => $cat_req_id, ':qreq' => $q_req
            ]);

            header("Location: ../views/lider_inventario.php?msg=contrato_enviado");

        } elseif ($accion === 'cancelar') {
            $trade_id = (int)$_POST['tradeo_id'];
            // Solo el ofertante puede cancelar contratos en estado 'activo'
            $stmt_del = $pdo->prepare("UPDATE mercado_tradeos SET estado = 'cancelado' WHERE id = :tid AND ofertante_id = :uid AND estado = 'activo'");
            $stmt_del->execute([':tid' => $trade_id, ':uid' => $lider_id]);
            
            header("Location: ../views/lider_inventario.php?msg=contrato_cancelado");
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("BLOQUEO DE RED DIPLOMÁTICA: " . $e->getMessage());
    }
}