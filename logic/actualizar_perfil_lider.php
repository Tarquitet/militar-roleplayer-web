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
    $id_lider = $_SESSION['usuario_id'];
    $nombre_equipo = trim($_POST['nombre_equipo']);
    
    // 1. Lógica de Subida de Bandera (Estandarte)
    $ruta_bandera = null;
    if (isset($_FILES['bandera']) && $_FILES['bandera']['error'] == 0) {
        $directorio = "../uploads/banderas/";
        // Generamos un nombre de archivo único para evitar sobreescrituras no deseadas
        $nombre_archivo = "flag_" . $id_lider . "_" . time() . "." . pathinfo($_FILES["bandera"]["name"], PATHINFO_EXTENSION);
        $ruta_destino = $directorio . $nombre_archivo;
        
        if (move_uploaded_file($_FILES["bandera"]["tmp_name"], $ruta_destino)) {
            $ruta_bandera = "uploads/banderas/" . $nombre_archivo;
        }
    }

    try {
        // Si subió bandera nueva, actualizamos todo. Si no, solo el nombre operativo.
        if ($ruta_bandera) {
            $sql = "UPDATE cuentas SET nombre_equipo = :nom, bandera_url = :img WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':nom' => $nombre_equipo, ':img' => $ruta_bandera, ':id' => $id_lider]);
        } else {
            $sql = "UPDATE cuentas SET nombre_equipo = :nom WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':nom' => $nombre_equipo, ':id' => $id_lider]);
        }

        // Redirección exitosa al panel de mando
        header("Location: ../views/lider_dashboard.php?mensaje=perfil_ok");
        exit();
    } catch (PDOException $e) {
        // Reporte de error táctico si la base de datos rechaza la actualización
        die($txt['LOGIC']['ERR_ACTUALIZAR_PERFIL'] . $e->getMessage());
    }
} else {
    header("Location: ../views/lider_dashboard.php");
    exit();
}
?>