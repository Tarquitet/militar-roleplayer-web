<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') { die("Acceso denegado."); }
require_once '../config/conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $inv_id = (int)($_POST['inv_id'] ?? 0);
    $accion = $_POST['accion'];
    $equipo_id = (int)$_POST['equipo_id'];
    $catalogo_id = (int)($_POST['catalogo_id'] ?? 0);

    if ($accion === 'sumar') {
        if ($inv_id > 0) {
            $pdo->prepare("UPDATE inventario SET cantidad = cantidad + 1 WHERE id = ?")->execute([$inv_id]);
        } else {
            $pdo->prepare("INSERT INTO inventario (cuenta_id, catalogo_id, cantidad) VALUES (?, ?, 1)")->execute([$equipo_id, $catalogo_id]);
        }
    } elseif ($accion === 'restar' && $inv_id > 0) {
        $pdo->prepare("UPDATE inventario SET cantidad = cantidad - 1 WHERE id = ?")->execute([$inv_id]);
        $pdo->query("DELETE FROM inventario WHERE cantidad <= 0");
    }
    
    header("Location: ../views/staff_ver_inventario.php?id=" . $equipo_id);
    exit();
}
?>