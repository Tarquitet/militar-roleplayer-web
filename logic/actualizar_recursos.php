<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') exit();

require_once '../config/conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = (int)$_POST['equipo_id'];
    $nombre = trim($_POST['nombre_equipo']);
    $dinero = (int)$_POST['dinero'];
    $acero = (int)$_POST['acero'];
    $petroleo = (int)$_POST['petroleo'];
    
    // AQUÍ ESTÁ EL TRUCO: Recogemos el string del hidden input
    // Si por alguna razón llega vacío, le asignamos NULL
    $naciones = !empty($_POST['naciones_activas_string']) ? $_POST['naciones_activas_string'] : null;

    try {
        $sql = "UPDATE cuentas SET 
                nombre_equipo = :nom, 
                dinero = :din, 
                acero = :ace, 
                petroleo = :pet, 
                naciones_activas = :nac 
                WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nom' => $nombre,
            ':din' => $dinero,
            ':ace' => $acero,
            ':pet' => $petroleo,
            ':nac' => $naciones,
            ':id'  => $id
        ]);

        header("Location: ../views/staff_dashboard.php?mensaje=ok");
        exit();
    } catch (PDOException $e) {
        die("Error al actualizar: " . $e->getMessage());
    }
}