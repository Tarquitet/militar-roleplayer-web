<?php
session_start();

// Importamos el diccionario de textos
$txt = require '../config/textos.php';

// Validación de rango de Mando (Líder)
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'lider') {
    exit($txt['LOGIC']['ERR_ACCESO_DENEGADO']);
}

require_once '../config/conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_SESSION['usuario_id'];
    $nombre = trim($_POST['nombre_equipo']);
    
    // Gestión de subida de bandera
    $query_update = "UPDATE cuentas SET nombre_equipo = :nom";
    $params = [':nom' => $nombre, ':id' => $id];

    if (isset($_FILES['bandera']) && $_FILES['bandera']['error'] == 0) {
        $ext = pathinfo($_FILES["bandera"]["name"], PATHINFO_EXTENSION);
        $nombre_archivo = "flag_" . $id . "_" . time() . "." . $ext;
        $ruta_destino = "../uploads/flags/" . $nombre_archivo;
        
        if (move_uploaded_file($_FILES["bandera"]["tmp_name"], $ruta_destino)) {
            $query_update .= ", bandera_url = :img";
            $params[':img'] = "uploads/flags/" . $nombre_archivo;
        }
    }

    $query_update .= " WHERE id = :id";

    try {
        $stmt = $pdo->prepare($query_update);
        $stmt->execute($params);
        header("Location: ../views/lider_dashboard.php?status=success");
        exit();
    } catch (PDOException $e) {
        // Usamos el texto centralizado para el error
        die($txt['LOGIC']['ERR_ACTUALIZAR_PERFIL'] . $e->getMessage());
    }
} else {
    header("Location: ../views/lider_dashboard.php");
    exit();
}
?>