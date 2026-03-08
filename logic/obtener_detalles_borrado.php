<?php
require_once '../config/conexion.php';

$id = (int)$_GET['id'];
$resultados = [];

// 1. Equipos con Unidades Físicas
$stmt = $pdo->prepare("SELECT c.nombre_equipo, i.cantidad FROM inventario i JOIN cuentas c ON i.cuenta_id = c.id WHERE i.catalogo_id = :id");
$stmt->execute([':id' => $id]);
while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $resultados[] = ['equipo' => $r['nombre_equipo'], 'tipo' => 'unidad', 'cantidad' => $r['cantidad']];
}

// 2. Equipos con Patentes (Planos)
$stmt = $pdo->prepare("SELECT c.nombre_equipo FROM planos_desbloqueados p JOIN cuentas c ON p.cuenta_id = c.id WHERE p.catalogo_id = :id");
$stmt->execute([':id' => $id]);
while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $resultados[] = ['equipo' => $r['nombre_equipo'], 'tipo' => 'plano', 'cantidad' => 1];
}

header('Content-Type: application/json');
echo json_encode($resultados);