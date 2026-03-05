<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'lider') exit();

require_once '../config/conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_lider = $_SESSION['usuario_id'];
    $nombre_equipo = trim($_POST['nombre_equipo']);
    
    // 1. Lógica de Subida de Bandera
    $ruta_bandera = null;
    if (isset($_FILES['bandera']) && $_FILES['bandera']['error'] == 0) {
        $directorio = "../uploads/banderas/";
        $nombre_archivo = "flag_" . $id_lider . "_" . time() . "." . pathinfo($_FILES["bandera"]["name"], PATHINFO_EXTENSION);
        $ruta_destino = $directorio . $nombre_archivo;
        
        if (move_uploaded_file($_FILES["bandera"]["tmp_name"], $ruta_destino)) {
            $ruta_bandera = "uploads/banderas/" . $nombre_archivo;
        }
    }

    try {
        // Si subió bandera nueva, actualizamos todo. Si no, solo el nombre.
        if ($ruta_bandera) {
            $sql = "UPDATE cuentas SET nombre_equipo = :nom, bandera_url = :img WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':nom' => $nombre_equipo, ':img' => $ruta_bandera, ':id' => $id_lider]);
        } else {
            $sql = "UPDATE cuentas SET nombre_equipo = :nom WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':nom' => $nombre_equipo, ':id' => $id_lider]);
        }

        header("Location: ../views/lider_dashboard.php?mensaje=perfil_ok");
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}