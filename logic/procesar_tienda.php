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
            $tipo = $_POST['tipo'];
            $subtipo = $_POST['subtipo'];
            $nacion = $_POST['nacion'];
            $rango = (int)$_POST['rango'];
            $nombre = trim($_POST['nombre_vehiculo']);
            
            // CAPTURA DE ESTADO PREMIUM
            $es_premium = isset($_POST['es_premium']) ? 1 : 0;

            // Asignamos costos automáticamente leyendo el archivo basado en el rango
            $c_dinero = $precios_base[$tipo][$subtipo][$rango]['dinero'] ?? 0;
            $c_acero = $precios_base[$tipo][$subtipo][$rango]['acero'] ?? 0;
            $c_petroleo = $precios_base[$tipo][$subtipo][$rango]['petroleo'] ?? 0;

            // Lógica para subir la imagen
            $ruta_imagen = null;
            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
                $directorio = "../uploads/vehiculos/";
                $nombre_archivo = time() . "_" . basename($_FILES["imagen"]["name"]);
                $ruta_destino = $directorio . $nombre_archivo;
                
                if (move_uploaded_file($_FILES["imagen"]["tmp_name"], $ruta_destino)) {
                    $ruta_imagen = "uploads/vehiculos/" . $nombre_archivo;
                }
            }

            // ACTUALIZACIÓN DE INSERT: Se añade la columna 'es_premium'
            $sql = "INSERT INTO catalogo_tienda 
                    (tipo, subtipo, nacion, rango, nombre_vehiculo, costo_dinero, costo_acero, costo_petroleo, imagen_url, es_premium) 
                    VALUES (:tipo, :subtipo, :nacion, :rango, :nombre, :cd, :ca, :cp, :img, :premium)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':tipo' => $tipo,
                ':subtipo' => $subtipo,
                ':nacion' => $nacion,
                ':rango' => $rango,
                ':nombre' => $nombre,
                ':cd' => $c_dinero,
                ':ca' => $c_acero,
                ':cp' => $c_petroleo,
                ':img' => $ruta_imagen,
                ':premium' => $es_premium // Valor capturado de la casilla
            ]);

            header("Location: ../views/staff_tienda.php?mensaje=agregado");
            exit();

        } elseif ($accion == 'eliminar') {
            $id = (int)$_POST['item_id'];
            $stmt = $pdo->prepare("DELETE FROM catalogo_tienda WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            header("Location: ../views/staff_tienda.php?mensaje=eliminado");
            exit();
        }

    } catch (PDOException $e) {
        // Usamos el texto centralizado para el error
        die($txt['LOGIC']['ERR_DB_CATALOGO_STAFF'] . $e->getMessage());
    }
} else {
    header("Location: ../views/staff_tienda.php");
    exit();
}
?>