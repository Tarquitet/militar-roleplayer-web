<?php
session_start();
require_once '../config/conexion.php';

// Verificación de Rango
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'lider') {
    die("ACCESO DENEGADO AL ARSENAL.");
}

// ⚠️ Cargamos los precios oficiales para asignar el costo automáticamente
$precios_base = require '../config/precios.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accion = $_POST['accion'] ?? '';

    // ==========================================
    // 💥 PROTOCOLO 1: PURGAR VEHÍCULO (Propio)
    // ==========================================
    if ($accion === 'eliminar') {
        $id_item = (int)$_POST['id'];
        try {
            $stmt_img = $pdo->prepare("SELECT imagen_url FROM catalogo_tienda WHERE id = ?");
            $stmt_img->execute([$id_item]);
            $vieja_foto = $stmt_img->fetchColumn();

            if ($vieja_foto && file_exists("../" . $vieja_foto)) {
                unlink("../" . $vieja_foto);
            }

            $stmt_del = $pdo->prepare("DELETE FROM catalogo_tienda WHERE id = ?");
            $stmt_del->execute([$id_item]);

            header("Location: ../views/lider_tienda.php?msg=purga_ok");
            exit();
        } catch (PDOException $e) { die("ERROR AL DESTRUIR ACTIVO: " . $e->getMessage()); }
    }

    // ==========================================
    // ➕ PROTOCOLO 2: AGREGAR NUEVO VEHÍCULO
    // ==========================================
    if ($accion === 'agregar') {
        $nombre = $_POST['nombre_vehiculo'];
        $nacion = $_POST['nacion'];
        $tipo = $_POST['tipo'];
        $subtipo = $_POST['subtipo'];
        $rango = (int)$_POST['rango'];
        $br = $_POST['br'];
        $is_premium = isset($_POST['is_premium']) ? 1 : 0;
        
        // El sistema calcula automáticamente el costo en base a precios.php
        $costo_d = $precios_base[$tipo][$subtipo][$rango]['dinero'] ?? 0;
        $costo_a = $precios_base[$tipo][$subtipo][$rango]['acero'] ?? 0;
        $costo_p = $precios_base[$tipo][$subtipo][$rango]['petroleo'] ?? 0;

        $ruta_nueva = null;

        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
            $nombre_archivo = "veh_lider_" . time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "", $_FILES["imagen"]["name"]);
            $ruta_destino = "../uploads/tienda/" . $nombre_archivo;
            
            if (move_uploaded_file($_FILES["imagen"]["tmp_name"], $ruta_destino)) {
                $ruta_nueva = "uploads/tienda/" . $nombre_archivo;
            }
        }

        try {
            // Se insertan TODOS los campos, para que no queden en blanco en la DB
            $sql = "INSERT INTO catalogo_tienda 
                    (nombre_vehiculo, nacion, tipo, subtipo, clase, rango, br, is_premium, es_premium, costo_dinero, costo_acero, costo_petroleo, imagen_url) 
                    VALUES (:nom, :nac, :tip, :sub, :cla, :ran, :br, :prem, :prem, :cd, :ca, :cp, :img)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nom'=>$nombre, ':nac'=>$nacion, ':tip'=>$tipo, ':sub'=>$subtipo, ':cla'=>$subtipo, 
                ':ran'=>$rango, ':br'=>$br, ':prem'=>$is_premium, 
                ':cd'=>$costo_d, ':ca'=>$costo_a, ':cp'=>$costo_p, 
                ':img'=>$ruta_nueva
            ]);
            
            header("Location: ../views/lider_tienda.php?msg=add_ok");
            exit();
        } catch (PDOException $e) {
            die("ERROR AL REGISTRAR: " . $e->getMessage());
        }
    }
}
?>