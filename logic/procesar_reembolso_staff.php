<?php
session_start();
require_once '../config/conexion.php';

// Verificación de rango de Staff
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') { die("Acceso Denegado: Rango Insuficiente."); }

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tipo = $_POST['tipo'];
    $target_id = (int)$_POST['target_id'];
    $equipo_id = (int)$_POST['equipo_id'];

    try {
        $pdo->beginTransaction();

        if ($tipo === 'plano') {
            // --- CASO PATENTE: Solo devuelve dinero ---
            $stmt = $pdo->prepare("SELECT c.costo_dinero FROM planos_desbloqueados p JOIN catalogo_tienda c ON p.catalogo_id = c.id WHERE p.id = :id");
            $stmt->execute([':id' => $target_id]);
            $costo = $stmt->fetchColumn();

            // Sumar dinero a la cuenta
            $pdo->prepare("UPDATE cuentas SET dinero = dinero + :c WHERE id = :eid")->execute([':c' => $costo, ':eid' => $equipo_id]);
            
            // Purgar registro de patente
            $pdo->prepare("DELETE FROM planos_desbloqueados WHERE id = :id")->execute([':id' => $target_id]);

        } else {
            // --- CASO VEHÍCULO: Devolución Triple (100% recursos) ---
            $stmt = $pdo->prepare("SELECT i.cantidad, c.costo_dinero, c.costo_acero, c.costo_petroleo FROM inventario i JOIN catalogo_tienda c ON i.catalogo_id = c.id WHERE i.id = :id");
            $stmt->execute([':id' => $target_id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($item) {
                // Cálculo de recuperación total
                $r_dinero = $item['cantidad'] * $item['costo_dinero'];
                $r_acero = $item['cantidad'] * $item['costo_acero'];
                $r_petroleo = $item['cantidad'] * $item['costo_petroleo'];

                // Devolver TODOS los recursos a la cuenta del equipo
                $stmt_upd = $pdo->prepare("UPDATE cuentas SET dinero = dinero + :d, acero = acero + :a, petroleo = petroleo + :p WHERE id = :eid");
                $stmt_upd->execute([
                    ':d' => $r_dinero, 
                    ':a' => $r_acero, 
                    ':p' => $r_petroleo, 
                    ':eid' => $equipo_id
                ]);

                // Borrar unidades del hangar
                $pdo->prepare("DELETE FROM inventario WHERE id = :id")->execute([':id' => $target_id]);
            }
        }

        $pdo->commit();
        // Redirigir con confirmación
        header("Location: ../views/staff_ver_inventario.php?id=$equipo_id&msg=reembolso_ok");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        die("FALLO CRÍTICO EN LA CADENA DE REEMBOLSO: " . $e->getMessage());
    }
}