<?php
session_start();

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/conexion.php';

// Importamos el diccionario de textos
$txt = require '../config/textos.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accion = $_POST['accion'];

    try {
        if ($accion == 'agregar') {
            $nombre_pais = trim($_POST['nombre_pais']);
            
            // Verificamos si ya existe para no tener duplicados
            $stmt_check = $pdo->prepare("SELECT id FROM naciones WHERE nombre = :nombre");
            $stmt_check->bindParam(':nombre', $nombre_pais);
            $stmt_check->execute();

            if ($stmt_check->rowCount() > 0) {
                header("Location: ../views/staff_paises.php?mensaje=duplicado");
                exit();
            }

            // Si no existe, lo insertamos
            $stmt_insert = $pdo->prepare("INSERT INTO naciones (nombre) VALUES (:nombre)");
            $stmt_insert->bindParam(':nombre', $nombre_pais);
            $stmt_insert->execute();

            header("Location: ../views/staff_paises.php?mensaje=agregado");
            exit();

        } elseif ($accion == 'eliminar') {
            $id_pais = (int)$_POST['id_pais'];
            
            // Eliminamos la nación por su ID
            $stmt_delete = $pdo->prepare("DELETE FROM naciones WHERE id = :id");
            $stmt_delete->bindParam(':id', $id_pais);
            $stmt_delete->execute();

            header("Location: ../views/staff_paises.php?mensaje=eliminado");
            exit();
        }

    } catch (PDOException $e) {
        // Usamos el texto centralizado para el error
        die($txt['LOGIC']['ERR_DB_PAISES_STAFF'] . $e->getMessage());
    }
} else {
    header("Location: ../views/staff_paises.php");
    exit();
}
?>