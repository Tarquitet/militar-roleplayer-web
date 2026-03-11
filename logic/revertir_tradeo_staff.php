<?php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') { die("No autorizado."); }

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tradeo_id = (int)$_POST['tradeo_id'];
    
    // Capturar las intenciones de reversión de los checkboxes
    $rev_vehiculo = isset($_POST['rev_vehiculo']) ? true : false;
    $rev_enviados = isset($_POST['rev_enviados']) ? true : false;
    $rev_cobrados = isset($_POST['rev_cobrados']) ? true : false;

    if (!$rev_vehiculo && !$rev_enviados && !$rev_cobrados) {
        header("Location: ../views/staff_dashboard.php?msg=err_nada_seleccionado");
        exit();
    }

    try {
        $pdo->beginTransaction();

        // 1. Obtener la foto exacta del tradeo original
        $stmt_t = $pdo->prepare("SELECT * FROM mercado_tradeos WHERE id = ?");
        $stmt_t->execute([$tradeo_id]);
        $tradeo = $stmt_t->fetch(PDO::FETCH_ASSOC);

        if (!$tradeo || $tradeo['estado'] !== 'completado') {
            throw new Exception("El tradeo no es válido o ya fue anulado.");
        }

        $ofertante_id = $tradeo['ofertante_id'];
        $receptor_id = $tradeo['receptor_id'];

        // 2. REVERTIR VEHÍCULO (Quitar a Receptor, Dar a Ofertante)
        if ($rev_vehiculo && $tradeo['vehiculo_ofrecido_id'] > 0) {
            $cat_id = $tradeo['vehiculo_ofrecido_id'];
            $qty = $tradeo['cantidad_ofrecida'];

            // A) Restar al Receptor (Puede quedar en negativo si ya lo destruyó/vendió, es penalización militar)
            $stmt_quitar = $pdo->prepare("UPDATE inventario SET cantidad = cantidad - ? WHERE cuenta_id = ? AND catalogo_id = ?");
            $stmt_quitar->execute([$qty, $receptor_id, $cat_id]);

            // B) Sumar al Ofertante original
            // Verificamos si el ofertante aún tiene fila en el inventario para ese vehículo
            $stmt_check = $pdo->prepare("SELECT id FROM inventario WHERE cuenta_id = ? AND catalogo_id = ?");
            $stmt_check->execute([$ofertante_id, $cat_id]);
            if ($stmt_check->rowCount() > 0) {
                $stmt_add = $pdo->prepare("UPDATE inventario SET cantidad = cantidad + ? WHERE cuenta_id = ? AND catalogo_id = ?");
                $stmt_add->execute([$qty, $ofertante_id, $cat_id]);
            } else {
                $stmt_insert = $pdo->prepare("INSERT INTO inventario (cuenta_id, catalogo_id, cantidad) VALUES (?, ?, ?)");
                $stmt_insert->execute([$ofertante_id, $cat_id, $qty]);
            }
        }

        // 3. REVERTIR RECURSOS ENVIADOS POR EL OFERTANTE
        // (Ofertante recibe de vuelta, Receptor los pierde)
        if ($rev_enviados) {
            $d = $tradeo['ofrece_dinero']; $a = $tradeo['ofrece_acero']; $p = $tradeo['ofrece_petroleo'];
            if ($d > 0 || $a > 0 || $p > 0) {
                $pdo->exec("UPDATE cuentas SET dinero = dinero + $d, acero = acero + $a, petroleo = petroleo + $p WHERE id = $ofertante_id");
                $pdo->exec("UPDATE cuentas SET dinero = dinero - $d, acero = acero - $a, petroleo = petroleo - $p WHERE id = $receptor_id");
            }
        }

        // 4. REVERTIR COBRO / PAGO (Receptor recibe de vuelta, Ofertante los pierde)
        if ($rev_cobrados) {
            $p_d = $tradeo['pide_dinero']; $p_a = $tradeo['pide_acero']; $p_p = $tradeo['pide_petroleo'];
            if ($p_d > 0 || $p_a > 0 || $p_p > 0) {
                // El que cobró (Ofertante) pierde el dinero, el que pagó (Receptor) lo recupera
                $pdo->exec("UPDATE cuentas SET dinero = dinero - $p_d, acero = acero - $p_a, petroleo = petroleo - $p_p WHERE id = $ofertante_id");
                $pdo->exec("UPDATE cuentas SET dinero = dinero + $p_d, acero = acero + $p_a, petroleo = petroleo + $p_p WHERE id = $receptor_id");
            }
        }

        // 5. MARCAR TRADEO COMO ANULADO PARA QUE NO SE PUEDA VOLVER A REVERTIR
        $stmt_update = $pdo->prepare("UPDATE mercado_tradeos SET estado = 'anulado_staff' WHERE id = ?");
        $stmt_update->execute([$tradeo_id]);

        // 6. REGISTRAR EN BITÁCORA DEL SISTEMA
        $msg = "El Staff ha ejecutado un Reembolso Selectivo de la Transacción #$tradeo_id.";
        $stmt_log = $pdo->prepare("INSERT INTO bitacora (mensaje, categoria) VALUES (?, 'sistema')");
        $stmt_log->execute([$msg]);

        $pdo->commit();
        header("Location: ../views/staff_dashboard.php?msg=reembolso_ok");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        die("ERROR CRÍTICO EN REVERSIÓN TÁCTICA: " . $e->getMessage());
    }
}