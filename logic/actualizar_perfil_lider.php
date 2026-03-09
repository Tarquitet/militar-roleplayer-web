<?php
session_start();
$txt = require '../config/textos.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'lider') {
    exit($txt['LOGIC']['ERR_ACCESO_DENEGADO']);
}

require_once '../config/conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_lider = $_SESSION['usuario_id'];

    // --- ACCIÓN A: ELIMINACIÓN QUIRÚRGICA (SOLO BORRAR) ---
    // Unificamos 'solo_borrar_bandera' o 'delete_flag'
    if (isset($_POST['solo_borrar_bandera']) || isset($_POST['delete_flag'])) {
        try {
            // 1. Localizar el archivo actual en el disco
            $stmt = $pdo->prepare("SELECT bandera_url FROM cuentas WHERE id = ?");
            $stmt->execute([$id_lider]);
            $archivo_viejo = $stmt->fetchColumn();

            // 2. Si existe, lo borramos físicamente del servidor
            if ($archivo_viejo && file_exists("../" . $archivo_viejo)) {
                unlink("../" . $archivo_viejo);
            }

            // 3. Limpiamos la base de datos
            $stmt = $pdo->prepare("UPDATE cuentas SET bandera_url = NULL WHERE id = ?");
            $stmt->execute([$id_lider]);
            
            header("Location: ../views/lider_dashboard.php?status=bandera_eliminada");
            exit();
        } catch (PDOException $e) {
            die("ERROR CRÍTICO EN BORRADO: " . $e->getMessage());
        }
    }

    // --- ACCIÓN B: ACTUALIZACIÓN / REEMPLAZO ---
    $nombre_equipo = trim($_POST['nombre_equipo']);
    $ruta_nueva = null;

    // ¿El líder subió un nuevo estandarte?
    if (isset($_FILES['bandera']) && $_FILES['bandera']['error'] == 0) {
        try {
            // 1. BUSCAR Y BORRAR EL ANTERIOR (Para no dejar basura al reemplazar)
            $stmt = $pdo->prepare("SELECT bandera_url FROM cuentas WHERE id = ?");
            $stmt->execute([$id_lider]);
            $archivo_a_reemplazar = $stmt->fetchColumn();

            if ($archivo_a_reemplazar && file_exists("../" . $archivo_a_reemplazar)) {
                unlink("../" . $archivo_a_reemplazar);
            }

            // 2. SUBIR EL NUEVO
            $directorio = "../uploads/banderas/";
            $nombre_archivo = "flag_" . $id_lider . "_" . time() . "." . pathinfo($_FILES["bandera"]["name"], PATHINFO_EXTENSION);
            $ruta_destino = $directorio . $nombre_archivo;
            
            if (move_uploaded_file($_FILES["bandera"]["tmp_name"], $ruta_destino)) {
                $ruta_nueva = "uploads/banderas/" . $nombre_archivo;
            }
        } catch (Exception $e) {
            die("Error procesando archivos: " . $e->getMessage());
        }
    }

    // --- GUARDAR EN BASE DE DATOS ---
    try {
        if ($ruta_nueva) {
            // Caso: Cambio nombre + Nueva bandera
            $sql = "UPDATE cuentas SET nombre_equipo = :nom, bandera_url = :img WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':nom' => $nombre_equipo, ':img' => $ruta_nueva, ':id' => $id_lider]);
        } else {
            // Caso: Solo cambio nombre (la bandera se queda como está)
            $sql = "UPDATE cuentas SET nombre_equipo = :nom WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':nom' => $nombre_equipo, ':id' => $id_lider]);
        }

        header("Location: ../views/lider_dashboard.php?mensaje=perfil_ok");
        exit();
    } catch (PDOException $e) {
        die($txt['LOGIC']['ERR_ACTUALIZAR_PERFIL'] . $e->getMessage());
    }
} else {
    header("Location: ../views/lider_dashboard.php");
    exit();
}