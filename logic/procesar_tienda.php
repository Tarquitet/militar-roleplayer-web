<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') {
    header("Location: ../login.php");
    exit();
}
require_once '../config/conexion.php';

// Importamos el diccionario de textos
$txt = require '../config/textos.php';

// Cargamos los precios predefinidos
$precios_base = require_once '../config/precios.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accion = $_POST['accion'];

    try {
        if ($accion == 'agregar') {
            // 1. CAPTURA DE DATOS
            $tipo = $_POST['tipo'] ?? '';
            $subtipo = $_POST['subtipo'] ?? '';
            $nacion = $_POST['nacion'] ?? '';
            $rango = (int)($_POST['rango'] ?? 0);
            $br = trim($_POST['br'] ?? '');
            $nombre = trim($_POST['nombre_vehiculo'] ?? '');
            $is_premium = isset($_POST['is_premium']) ? 1 : 0;

            // 2. ☢️ FILTRO DE SEGURIDAD ABSOLUTO (Punto pedido)
            // Si falta cualquier dato vital, el sistema bloquea el registro.
            if (empty($tipo) || empty($subtipo) || empty($nacion) || $rango === 0 || empty($br) || empty($nombre)) {
                die("☢️ BLOQUEO DE SEGURIDAD: Faltan datos obligatorios. El activo no ha sido registrado.");
            }

            // 3. ASIGNACIÓN DE COSTOS AUTOMÁTICOS
            $c_dinero = $precios_base[$tipo][$subtipo][$rango]['dinero'] ?? 0;
            $c_acero = $precios_base[$tipo][$subtipo][$rango]['acero'] ?? 0;
            $c_petroleo = $precios_base[$tipo][$subtipo][$rango]['petroleo'] ?? 0;

            // 4. LÓGICA DE IMAGEN (Con validación de existencia)
            $ruta_imagen = null;
            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
                $directorio = "../uploads/vehiculos/";
                if (!is_dir($directorio)) mkdir($directorio, 0777, true); // Crear carpeta si no existe

                $nombre_archivo = time() . "_" . basename($_FILES["imagen"]["name"]);
                $ruta_destino = $directorio . $nombre_archivo;
                
                if (move_uploaded_file($_FILES["imagen"]["tmp_name"], $ruta_destino)) {
                    $ruta_imagen = "uploads/vehiculos/" . $nombre_archivo;
                }
            }

            // Si no se subió imagen, también es un fallo de seguridad
            if (!$ruta_imagen) {
                die("☢️ ERROR LOGÍSTICO: El activo requiere una imagen válida para ser registrado.");
            }

            // 5. INSERCIÓN EN BASE DE DATOS (Columnas actualizadas)
            // Guardamos el subtipo también en 'clase' para que el acordeón lo encuentre
            $sql = "INSERT INTO catalogo_tienda 
                    (tipo, subtipo, clase, nacion, rango, br, nombre_vehiculo, costo_dinero, costo_acero, costo_petroleo, imagen_url, is_premium) 
                    VALUES (:tipo, :subtipo, :clase, :nacion, :rango, :br, :nombre, :cd, :ca, :cp, :img, :premium)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':tipo'    => $tipo,
                ':subtipo' => $subtipo,
                ':clase'   => $subtipo, // Mapeo para el acordeón
                ':nacion'  => $nacion,
                ':rango'   => $rango,
                ':br'      => $br,
                ':nombre'  => $nombre,
                ':cd'      => $c_dinero,
                ':ca'      => $c_acero,
                ':cp'      => $c_petroleo,
                ':img'     => $ruta_imagen,
                ':premium' => $is_premium
            ]);

            header("Location: ../views/staff_tienda.php?mensaje=agregado");
            exit();

        } elseif ($accion == 'eliminar') {
            $id = (int)$_POST['id']; // Cambiado de item_id a id para coincidir con el form
            
            // Primero obtenemos la ruta de la imagen para borrarla físicamente (limpieza de disco)
            $stmt_img = $pdo->prepare("SELECT imagen_url FROM catalogo_tienda WHERE id = ?");
            $stmt_img->execute([$id]);
            $old_img = $stmt_img->fetchColumn();
            if ($old_img && file_exists("../" . $old_img)) unlink("../" . $old_img);

            $stmt = $pdo->prepare("DELETE FROM catalogo_tienda WHERE id = :id");
            $stmt->execute([':id' => $id]);
            
            header("Location: ../views/staff_tienda.php?mensaje=eliminado");
            exit();
        }

    } catch (Exception $e) {
        die("☢️ FALLO EN LA MATRIZ: " . $e->getMessage());
    }
} else {
    header("Location: ../views/staff_tienda.php");
    exit();
}