<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') { die("Acceso denegado."); }
require_once '../config/conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $catalogo_id = (int)$_POST['catalogo_id'];
    $equipo_id = (int)$_POST['equipo_id'];
    $tipo_entrega = $_POST['tipo_entrega'];
    $cantidad = (int)($_POST['cantidad'] ?? 1);
    $cobrar = isset($_POST['cobrar']) ? 1 : 0;

    try {
        $pdo->beginTransaction();

        if ($cobrar) {
            $stmt = $pdo->prepare("SELECT costo_dinero, costo_acero, costo_petroleo FROM catalogo_tienda WHERE id = ?");
            $stmt->execute([$catalogo_id]);
            $costo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $multiplicador = ($tipo_entrega === 'vehiculo') ? $cantidad : 1;
            
            $pdo->prepare("UPDATE cuentas SET dinero = dinero - ?, acero = acero - ?, petroleo = petroleo - ? WHERE id = ?")
                ->execute([
                    $costo['costo_dinero'] * $multiplicador, 
                    $costo['costo_acero'] * $multiplicador, 
                    $costo['costo_petroleo'] * $multiplicador, 
                    $equipo_id
                ]);
        }

        if ($tipo_entrega === 'patente') {
            $pdo->prepare("INSERT IGNORE INTO planos_desbloqueados (cuenta_id, catalogo_id) VALUES (?, ?)")->execute([$equipo_id, $catalogo_id]);
        } else {
            $check = $pdo->prepare("SELECT id FROM inventario WHERE cuenta_id = ? AND catalogo_id = ?");
            $check->execute([$equipo_id, $catalogo_id]);
            if ($inv_id = $check->fetchColumn()) {
                $pdo->prepare("UPDATE inventario SET cantidad = cantidad + ? WHERE id = ?")->execute([$cantidad, $inv_id]);
            } else {
                $pdo->prepare("INSERT INTO inventario (cuenta_id, catalogo_id, cantidad) VALUES (?, ?, ?)")->execute([$equipo_id, $catalogo_id, $cantidad]);
            }
        }

        $pdo->commit();
        header("Location: ../views/staff_ver_inventario.php?id=" . $equipo_id);
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error: " . $e->getMessage());
    }
}
?>