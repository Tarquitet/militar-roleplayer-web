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
            $v_ofre = !empty($_POST['vehiculo_ofrecido_id']) ? (int)$_POST['vehiculo_ofrecido_id'] : null;
            $stmt_ins = $pdo->prepare("INSERT INTO mercado_tradeos (ofertante_id, receptor_id, vehiculo_ofrecido_id, cantidad_ofrecida, ofrece_dinero, ofrece_acero, ofrece_petroleo, estado) VALUES (?, ?, ?, 1, ?, ?, ?, 'activo')");
            $stmt_ins->execute([$lider_id, $receptor_id, $v_ofre, (int)$_POST['ofrece_dinero'], (int)$_POST['ofrece_acero'], (int)$_POST['ofrece_petroleo']]);
            header("Location: ../views/lider_inventario.php?msg=enviado");

        } elseif ($accion === 'aceptar') {
            $tid = (int)($_POST['tradeo_id'] ?? 0);
            
            // 1. Obtener datos del tradeo
            $stmt = $pdo->prepare("SELECT * FROM mercado_tradeos WHERE id = ? AND receptor_id = ? AND estado = 'activo'");
            $stmt->execute([$tid, $lider_id]);
            $trato = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$trato) throw new Exception("Trato no encontrado.");

            // 2. MOVER RECURSOS (SEPARADOS para evitar Error 2014)
            // Restar al ofertante
            $up1 = $pdo->prepare("UPDATE cuentas SET dinero = dinero - ?, acero = acero - ?, petroleo = petroleo - ? WHERE id = ?");
            $up1->execute([$trato['ofrece_dinero'], $trato['ofrece_acero'], $trato['ofrece_petroleo'], $trato['ofertante_id']]);
            
            // Sumar al receptor (tú)
            $up2 = $pdo->prepare("UPDATE cuentas SET dinero = dinero + ?, acero = acero + ?, petroleo = petroleo + ? WHERE id = ?");
            $up2->execute([$trato['ofrece_dinero'], $trato['ofrece_acero'], $trato['ofrece_petroleo'], $lider_id]);

            // 3. MOVER VEHÍCULO
            if ($trato['vehiculo_ofrecido_id']) {
                $pdo->prepare("UPDATE inventario SET cantidad = cantidad - 1 WHERE cuenta_id = ? AND catalogo_id = ?")->execute([$trato['ofertante_id'], $trato['vehiculo_ofrecido_id']]);
                $pdo->query("DELETE FROM inventario WHERE cantidad <= 0");
                
                $check = $pdo->prepare("SELECT id FROM inventario WHERE cuenta_id = ? AND catalogo_id = ?");
                $check->execute([$lider_id, $trato['vehiculo_ofrecido_id']]);
                if ($inv_id = $check->fetchColumn()) {
                    $pdo->prepare("UPDATE inventario SET cantidad = cantidad + 1 WHERE id = ?")->execute([$inv_id]);
                } else {
                    $pdo->prepare("INSERT INTO inventario (cuenta_id, catalogo_id, cantidad) VALUES (?, ?, 1)")->execute([$lider_id, $trato['vehiculo_ofrecido_id']]);
                }
            }

            $pdo->prepare("UPDATE mercado_tradeos SET estado = 'completado' WHERE id = ?")->execute([$tid]);
            header("Location: ../views/lider_inventario.php?msg=exito");

        } elseif ($accion === 'rechazar') {
            $tid = (int)($_POST['tradeo_id'] ?? 0);
            $pdo->prepare("UPDATE mercado_tradeos SET estado = 'cancelado' WHERE id = ? AND receptor_id = ?")->execute([$tid, $lider_id]);
            header("Location: ../views/lider_inventario.php?msg=rechazado");
            
        } elseif ($accion === 'cancelar') {
            $tid = (int)$_POST['tradeo_id'];
            $pdo->prepare("UPDATE mercado_tradeos SET estado = 'cancelado' WHERE id = ? AND ofertante_id = ?")->execute([$tid, $lider_id]);
            header("Location: ../views/lider_inventario.php?msg=cancelado");
        }

        $pdo->commit();
    } catch (Exception $e) { $pdo->rollBack(); die("ERROR: " . $e->getMessage()); }
}
?>