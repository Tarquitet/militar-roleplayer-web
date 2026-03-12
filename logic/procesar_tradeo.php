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

            // Recibimos el ID exacto del inventario que seleccionó
            $inv_ofre = !empty($_POST['inv_ofrecido_id']) ? (int)$_POST['inv_ofrecido_id'] : null;
            $cant_ofre = !empty($_POST['cantidad_ofrecida']) ? (int)$_POST['cantidad_ofrecida'] : 0;
            
            $ofrece_d = (int)($_POST['ofrece_dinero'] ?? 0);
            $ofrece_a = (int)($_POST['ofrece_acero'] ?? 0);
            $ofrece_p = (int)($_POST['ofrece_petroleo'] ?? 0);
            $pide_d = (int)($_POST['requiere_dinero'] ?? 0);
            $pide_a = (int)($_POST['requiere_acero'] ?? 0);
            $pide_p = (int)($_POST['requiere_petroleo'] ?? 0);

            // 1. Validar fondos
            $stmt_check = $pdo->prepare("SELECT dinero, acero, petroleo FROM cuentas WHERE id = ?");
            $stmt_check->execute([$lider_id]);
            $mis_fondos = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if ($mis_fondos['dinero'] < $ofrece_d || $mis_fondos['acero'] < $ofrece_a || $mis_fondos['petroleo'] < $ofrece_p) {
                throw new Exception("No tienes suficientes recursos para hacer esta oferta.");
            }

            // 2. RETENCIÓN MONETARIA
            $pdo->prepare("UPDATE cuentas SET dinero = dinero - ?, acero = acero - ?, petroleo = petroleo - ? WHERE id = ?")->execute([$ofrece_d, $ofrece_a, $ofrece_p, $lider_id]);

            // 3. RETENCIÓN FÍSICA (Identificando si era de Tienda o Tradeo)
            $v_ofre = null;
            $origen_ofre = null;

            if ($inv_ofre && $cant_ofre > 0) {
                $stmt_inv = $pdo->prepare("SELECT catalogo_id, origen, cantidad FROM inventario WHERE id = ? AND cuenta_id = ?");
                $stmt_inv->execute([$inv_ofre, $lider_id]);
                $inv_data = $stmt_inv->fetch(PDO::FETCH_ASSOC);

                if (!$inv_data || $inv_data['cantidad'] < $cant_ofre) throw new Exception("Stock de vehículo insuficiente.");
                
                $v_ofre = $inv_data['catalogo_id'];
                $origen_ofre = $inv_data['origen'];

                $pdo->prepare("UPDATE inventario SET cantidad = cantidad - ? WHERE id = ?")->execute([$cant_ofre, $inv_ofre]);
                $pdo->query("DELETE FROM inventario WHERE cantidad <= 0");
            }

            // 4. Crear contrato guardando el origen original
            $stmt_ins = $pdo->prepare("INSERT INTO mercado_tradeos (ofertante_id, receptor_id, vehiculo_ofrecido_id, origen_vehiculo, cantidad_ofrecida, ofrece_dinero, ofrece_acero, ofrece_petroleo, cantidad_requerida, pide_dinero, pide_acero, pide_petroleo, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, 'activo')");
            $stmt_ins->execute([$lider_id, $receptor_id, $v_ofre, $origen_ofre, $cant_ofre, $ofrece_d, $ofrece_a, $ofrece_p, $pide_d, $pide_a, $pide_p]);
            
            $pdo->commit();
            header("Location: ../views/lider_inventario.php?msg=oferta_enviada"); exit();

        } elseif ($accion === 'aceptar') {
            $tid = (int)($_POST['tradeo_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM mercado_tradeos WHERE id = ? AND receptor_id = ? AND estado = 'activo'");
            $stmt->execute([$tid, $lider_id]);
            $trato = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$trato) throw new Exception("Trato no encontrado.");

            $stmt_check = $pdo->prepare("SELECT dinero, acero, petroleo FROM cuentas WHERE id = ?");
            $stmt_check->execute([$lider_id]);
            $mis_fondos = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if ($mis_fondos['dinero'] < $trato['pide_dinero'] || $mis_fondos['acero'] < $trato['pide_acero'] || $mis_fondos['petroleo'] < $trato['pide_petroleo']) {
                throw new Exception("No tienes recursos suficientes para pagar este trato.");
            }
            
            $pdo->prepare("UPDATE cuentas SET dinero = dinero - ? + ?, acero = acero - ? + ?, petroleo = petroleo - ? + ? WHERE id = ?")
                ->execute([$trato['pide_dinero'], $trato['ofrece_dinero'], $trato['pide_acero'], $trato['ofrece_acero'], $trato['pide_petroleo'], $trato['ofrece_petroleo'], $lider_id]);
            
            $pdo->prepare("UPDATE cuentas SET dinero = dinero + ?, acero = acero + ?, petroleo = petroleo + ? WHERE id = ?")
                ->execute([$trato['pide_dinero'], $trato['pide_acero'], $trato['pide_petroleo'], $trato['ofertante_id']]);

            // ENTREGA (Obligatoriamente entra como 'tradeo' para bloquear su venta por dinero)
            if (!empty($trato['vehiculo_ofrecido_id']) && $trato['cantidad_ofrecida'] > 0) {
                $check = $pdo->prepare("SELECT id FROM inventario WHERE cuenta_id = ? AND catalogo_id = ? AND origen = 'tradeo'");
                $check->execute([$lider_id, $trato['vehiculo_ofrecido_id']]);
                if ($inv_id = $check->fetchColumn()) {
                    $pdo->prepare("UPDATE inventario SET cantidad = cantidad + ? WHERE id = ?")->execute([$trato['cantidad_ofrecida'], $inv_id]);
                } else {
                    $pdo->prepare("INSERT INTO inventario (cuenta_id, catalogo_id, cantidad, origen) VALUES (?, ?, ?, 'tradeo')")->execute([$lider_id, $trato['vehiculo_ofrecido_id'], $trato['cantidad_ofrecida']]);
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

            $pdo->prepare("UPDATE cuentas SET dinero = dinero + ?, acero = acero + ?, petroleo = petroleo + ? WHERE id = ?")
                ->execute([$trato['ofrece_dinero'], $trato['ofrece_acero'], $trato['ofrece_petroleo'], $trato['ofertante_id']]);
            
            // DEVOLUCIÓN: Se devuelve al ofertante con su Origen Original
            if (!empty($trato['vehiculo_ofrecido_id']) && $trato['cantidad_ofrecida'] > 0) {
                // Si es un tradeo viejo y dice NULL, asumimos 'tienda' para salvarlo
                $origen_orig = $trato['origen_vehiculo'] ?? 'tienda';
                $check = $pdo->prepare("SELECT id FROM inventario WHERE cuenta_id = ? AND catalogo_id = ? AND origen = ?");
                $check->execute([$trato['ofertante_id'], $trato['vehiculo_ofrecido_id'], $origen_orig]);
                if ($inv_id = $check->fetchColumn()) {
                    $pdo->prepare("UPDATE inventario SET cantidad = cantidad + ? WHERE id = ?")->execute([$trato['cantidad_ofrecida'], $inv_id]);
                } else {
                    $pdo->prepare("INSERT INTO inventario (cuenta_id, catalogo_id, cantidad, origen) VALUES (?, ?, ?, ?)")->execute([$trato['ofertante_id'], $trato['vehiculo_ofrecido_id'], $trato['cantidad_ofrecida'], $origen_orig]);
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
} else {
    header("Location: ../login.php"); exit();
}
?>