<?php
session_start();
require_once '../config/conexion.php';

// Verificación estricta de Staff
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') {
    die("ACCESO DENEGADO AL ARSENAL.");
}

// ⚠️ IMPORTANTE: Cargamos los rangos y precios oficiales del sistema
$precios_base = require '../config/precios.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accion = $_POST['accion'] ?? '';

    // ==========================================
    // 💥 PROTOCOLO 1: PURGAR VEHÍCULO
    // ==========================================
    if ($accion === 'eliminar') {
        $id_item = (int)$_POST['id'];
        try {
            $stmt_img = $pdo->prepare("SELECT imagen_url FROM catalogo_tienda WHERE id = ?");
            $stmt_img->execute([$id_item]);
            $vieja_foto = $stmt_img->fetchColumn();

            if ($vieja_foto && file_exists("../" . $vieja_foto)) {
                unlink("../" . $vieja_foto); // Destrucción física
            }

            $stmt_del = $pdo->prepare("DELETE FROM catalogo_tienda WHERE id = ?");
            $stmt_del->execute([$id_item]);

            header("Location: ../views/staff_tienda.php?msg=purga_ok");
            exit();
        } catch (PDOException $e) { die("ERROR EN PURGA: " . $e->getMessage()); }
    }

    // ==========================================
    // 🔄 PROTOCOLO 2 Y 3: EDITAR / AGREGAR
    // ==========================================
    if ($accion === 'editar' || $accion === 'agregar') {
        $id_item = (int)($_POST['id'] ?? 0);
        $nombre = $_POST['nombre_vehiculo'];
        $nacion = $_POST['nacion'];
        $tipo = $_POST['tipo'];
        $subtipo = $_POST['subtipo'];
        $rango = (int)$_POST['rango']; // Dejamos que el Staff decida el Tier
        $br = $_POST['br'];
        $is_premium = isset($_POST['is_premium']) ? 1 : 0;

        // ⚠️ EL BLINDAJE CONTRA EL "BOOM" ⚠️
        // Buscamos el costo en el archivo de configuración. Si el Staff pone un Rango que no existe,
        // el '?? 0' evita que el sistema explote y simplemente le asigna costo 0.
        $costo_d = $precios_base[$tipo][$subtipo][$rango]['dinero'] ?? 0;
        $costo_a = $precios_base[$tipo][$subtipo][$rango]['acero'] ?? 0;
        $costo_p = $precios_base[$tipo][$subtipo][$rango]['petroleo'] ?? 0;

        $ruta_nueva = null;

        // Procesamiento de Imagen Segura
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
            if ($accion === 'editar') {
                $stmt_img = $pdo->prepare("SELECT imagen_url FROM catalogo_tienda WHERE id = ?");
                $stmt_img->execute([$id_item]);
                $foto_basura = $stmt_img->fetchColumn();
                if ($foto_basura && file_exists("../" . $foto_basura)) { unlink("../" . $foto_basura); }
            }

            $nombre_archivo = "veh_" . time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "", $_FILES["imagen"]["name"]);
            $ruta_destino = "../uploads/tienda/" . $nombre_archivo;
            if (move_uploaded_file($_FILES["imagen"]["tmp_name"], $ruta_destino)) {
                $ruta_nueva = "uploads/tienda/" . $nombre_archivo;
            }
        }

        try {
            if ($accion === 'editar') {
                $sql = "UPDATE catalogo_tienda SET 
                        nombre_vehiculo = :nom, nacion = :nac, tipo = :tip, subtipo = :sub, clase = :cla, 
                        rango = :ran, br = :br, is_premium = :prem, es_premium = :prem,
                        costo_dinero = :cd, costo_acero = :ca, costo_petroleo = :cp";
                
                $params = [
                    ':nom'=>$nombre, ':nac'=>$nacion, ':tip'=>$tipo, ':sub'=>$subtipo, ':cla'=>$subtipo, 
                    ':ran'=>$rango, ':br'=>$br, ':prem'=>$is_premium,
                    ':cd'=>$costo_d, ':ca'=>$costo_a, ':cp'=>$costo_p,
                    ':id'=>$id_item
                ];

                if ($ruta_nueva) {
                    $sql .= ", imagen_url = :img WHERE id = :id";
                    $params[':img'] = $ruta_nueva;
                } else {
                    $sql .= " WHERE id = :id";
                }

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                header("Location: ../views/staff_tienda.php?msg=edit_ok");

            } else { // AGREGAR
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
                header("Location: ../views/staff_tienda.php?msg=add_ok");
            }
            exit();
        } catch (PDOException $e) { die("ERROR DB: " . $e->getMessage()); }
    }
}
?>