<?php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') { die("No autorizado."); }

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = (int)$_POST['id'];
    $nombre_equipo = $_POST['nombre_equipo'];
    $username = $_POST['username']; // CORRECCIÓN: Usamos el name que viene del modal
    $bandera_url = $_POST['bandera_url'];
    $dinero = (int)$_POST['dinero'];
    $acero = (int)$_POST['acero'];
    $petroleo = (int)$_POST['petroleo'];
    $password = $_POST['password'];

    try {
        // ACTUALIZACIÓN DE LA COLUMNA REAL 'username'
        $sql = "UPDATE cuentas SET 
                nombre_equipo = :ne, 
                username = :un, 
                bandera_url = :bu, 
                dinero = :d, 
                acero = :a, 
                petroleo = :p";
        
        $params = [
            ':ne' => $nombre_equipo,
            ':un' => $username,
            ':bu' => !empty($bandera_url) ? $bandera_url : NULL,
            ':d' => $dinero,
            ':a' => $acero,
            ':p' => $petroleo,
            ':id' => $id
        ];

        if (!empty($password)) {
            $sql .= ", password = :pass";
            $params[':pass'] = $password; 
        }

        $sql .= " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        header("Location: ../views/staff_dashboard.php?msg=update_ok");
        exit();
    } catch (PDOException $e) { die("Fallo crítico: SQLSTATE[" . $e->getCode() . "]: " . $e->getMessage()); }
}