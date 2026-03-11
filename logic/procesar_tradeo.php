<?php
session_start();
require_once '../config/conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['rol']) && $_SESSION['rol'] === 'lider') {
    $lider_id = $_SESSION['usuario_id'];
    $accion = $_POST['accion'];

    try {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->beginTransaction();

        if ($accion === 'crear') {
            $receptor_id = (int)($_POST['receptor_id'] ?? 0);
            if ($receptor_id <= 0) throw new Exception("Error: No se seleccionó un equipo rival.");

            // Lo que se ofrece y se pide
            $v_ofre = !empty($_POST['vehiculo_ofrecido_id']) ? (int)$_POST['vehiculo_ofrecido_id'] : null;
            $cant_ofre = !empty($_POST['cantidad_ofrecida']) ? (int)$_POST['cantidad_ofrecida'] : 0;
            $ofrece_d = (int)($_POST['ofrece_dinero'] ?? 0);
            $ofrece_a = (int)($_POST['ofrece_acero'] ?? 0);
            $ofrece_p = (int)($_POST['ofrece_petroleo'] ?? 0);
            $pide_d = (int)($_POST['requiere_dinero'] ?? 0);
            $pide_a = (int)($_POST['requiere_acero'] ?? 0);
            $pide_p = (int)($_POST['requiere_petroleo'] ?? 0);

            // 1. Validar que el Ofertante TENGA lo que está ofreciendo
            $stmt_check = $pdo->prepare("SELECT dinero, acero, petroleo FROM cuentas WHERE id = ?");
            $stmt_check->execute([$lider_id]);
            $mis_fondos = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if ($mis_fondos['dinero'] < $ofrece_d || $mis_fondos['acero'] < $ofrece_a || $mis_fondos['petroleo'] < $ofrece_p) {
                throw new Exception("No tienes suficientes recursos para hacer esta oferta.");
            }

            // 2. RETENCIÓN (Escrow): Quitarle los recursos y el vehículo al ofertante INMEDIATAMENTE
            $pdo->prepare("UPDATE cuentas SET dinero = dinero - ?, acero = acero - ?, petroleo = petroleo - ? WHERE id = ?")->execute([$ofrece_d, $ofrece_a, $ofrece_p, $lider_id]);

            if ($v_ofre && $cant_ofre > 0) {
                // Descontar vehículo
                $pdo->prepare("UPDATE inventario SET cantidad = cantidad - ? WHERE cuenta_id = ? AND catalogo_id = ?")->execute([$cant_ofre, $lider_id, $v_ofre]);
                $pdo->query("DELETE FROM inventario WHERE cantidad <= 0"); // Limpiar vacíos
            }

            // 3. Crear el contrato
            $stmt_ins = $pdo->prepare("INSERT INTO mercado_tradeos (ofertante_id, receptor_id, vehiculo_ofrecido_id, cantidad_ofrecida, ofrece_dinero, ofrece_acero, ofrece_petroleo, cantidad_requerida, pide_dinero, pide_acero, pide_petroleo, estado) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, 'activo')");
            $stmt_ins->execute([$lider_id, $receptor_id, $v_ofre, $cant_ofre, $ofrece_d, $ofrece_a, $ofrece_p, $pide_d, $pide_a, $pide_p]);
            
            $pdo->commit();
            header("Location: ../views/lider_inventario.php?msg=oferta_enviada"); exit();

        } elseif ($accion === 'aceptar') {
            $tid = (int)($_POST['tradeo_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM mercado_tradeos WHERE id = ? AND receptor_id = ? AND estado = 'activo'");
            $stmt->execute([$tid, $lider_id]);
            $trato = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$trato) throw new Exception("Trato no encontrado.");

            // 1. Validar fondos del RECEPTOR para pagar el precio
            $stmt_check = $pdo->prepare("SELECT dinero, acero, petroleo FROM cuentas WHERE id = ?");
            $stmt_check->execute([$lider_id]);
            $mis_fondos = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if ($mis_fondos['dinero'] < $trato['pide_dinero'] || $mis_fondos['acero'] < $trato['pide_acero'] || $mis_fondos['petroleo'] < $trato['pide_petroleo']) {
                throw new Exception("No tienes recursos suficientes para pagar este trato.");
            }
            
            // 2. Cobrar al RECEPTOR y darle lo ofrecido
            $pdo->prepare("UPDATE cuentas SET dinero = dinero - ? + ?, acero = acero - ? + ?, petroleo = petroleo - ? + ? WHERE id = ?")
                ->execute([$trato['pide_dinero'], $trato['ofrece_dinero'], $trato['pide_acero'], $trato['ofrece_acero'], $trato['pide_petroleo'], $trato['ofrece_petroleo'], $lider_id]);
            
            // 3. Pagar al OFERTANTE (Solo recibe lo que pidió, porque lo que ofreció ya se le descontó antes)
            $pdo->prepare("UPDATE cuentas SET dinero = dinero + ?, acero = acero + ?, petroleo = petroleo + ? WHERE id = ?")
                ->execute([$trato['pide_dinero'], $trato['pide_acero'], $trato['pide_petroleo'], $trato['ofertante_id']]);

            // 4. Entregar el vehículo retenido al RECEPTOR
            if (!empty($trato['vehiculo_ofrecido_id']) && $trato['cantidad_ofrecida'] > 0) {
                $check = $pdo->prepare("SELECT id FROM inventario WHERE cuenta_id = ? AND catalogo_id = ?");
                $check->execute([$lider_id, $trato['vehiculo_ofrecido_id']]);
                if ($inv_id = $check->fetchColumn()) {
                    $pdo->prepare("UPDATE inventario SET cantidad = cantidad + ? WHERE id = ?")->execute([$trato['cantidad_ofrecida'], $inv_id]);
                } else {
                    $pdo->prepare("INSERT INTO inventario (cuenta_id, catalogo_id, cantidad) VALUES (?, ?, ?)")->execute([$lider_id, $trato['vehiculo_ofrecido_id'], $trato['cantidad_ofrecida']]);
                }
            }

            $pdo->prepare("UPDATE mercado_tradeos SET estado = 'completado' WHERE id = ?")->execute([$tid]);
            $pdo->commit();
            header("Location: ../views/lider_inventario.php?msg=oferta_aceptada"); exit();

        } elseif ($accion === 'rechazar' || $accion === 'cancelar') {
            $tid = (int)$_POST['tradeo_id'];
            $columna_auth = ($accion === 'rechazar') ? 'receptor_id' : 'ofertante_id';
            
            $stmt = $pdo->prepare("SELECT * FROM mercado_tradeos WHERE id = ? AND $columna_auth = ? AND estado = 'activo'");
            $stmt->execute([$tid, $lider_id]);
            $trato = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$trato) throw new Exception("Operación no autorizada.");

            // DEVOLVER la retención al OFERTANTE
            $pdo->prepare("UPDATE cuentas SET dinero = dinero + ?, acero = acero + ?, petroleo = petroleo + ? WHERE id = ?")
                ->execute([$trato['ofrece_dinero'], $trato['ofrece_acero'], $trato['ofrece_petroleo'], $trato['ofertante_id']]);
            
            if (!empty($trato['vehiculo_ofrecido_id']) && $trato['cantidad_ofrecida'] > 0) {
                $check = $pdo->prepare("SELECT id FROM inventario WHERE cuenta_id = ? AND catalogo_id = ?");
                $check->execute([$trato['ofertante_id'], $trato['vehiculo_ofrecido_id']]);
                if ($inv_id = $check->fetchColumn()) {
                    $pdo->prepare("UPDATE inventario SET cantidad = cantidad + ? WHERE id = ?")->execute([$trato['cantidad_ofrecida'], $inv_id]);
                } else {
                    $pdo->prepare("INSERT INTO inventario (cuenta_id, catalogo_id, cantidad) VALUES (?, ?, ?)")->execute([$trato['ofertante_id'], $trato['vehiculo_ofrecido_id'], $trato['cantidad_ofrecida']]);
                }
            }

            $pdo->prepare("UPDATE mercado_tradeos SET estado = 'cancelado' WHERE id = ?")->execute([$tid]);
            $pdo->commit();
            $msg = ($accion === 'rechazar') ? 'oferta_rechazada' : 'oferta_cancelada';
            header("Location: ../views/lider_inventario.php?msg=$msg"); exit();
        }

    } catch (Exception $e) { 
        $pdo->rollBack(); 
        die("<div style='background:#111; color:#ef4444; padding:30px; text-align:center; font-family:monospace; border: 2px solid #ef4444;'><h2>🚨 ERROR AL PROCESAR 🚨</h2><p>" . htmlspecialchars($e->getMessage()) . "</p><br><a href='../views/lider_inventario.php' style='color:white; text-decoration:underline;'>Regresar al inventario</a></div>"); 
    }
}
?>