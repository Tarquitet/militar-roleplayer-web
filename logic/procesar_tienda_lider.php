<?php
session_start();
// Verificamos que sea un líder el que intenta subir el activo
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'lider') {
    header("Location: ../login.php");
    exit();
}
require_once '../config/conexion.php';

// Importamos el diccionario de textos
$txt = require '../config/textos.php';

// Cargamos los precios automáticos definidos en el sistema
$precios_base = require_once '../config/precios.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // 1. Captura de datos del formulario
        $tipo = $_POST['tipo'];
        $subtipo = $_POST['subtipo'];
        $nacion = $_POST['nacion'];
        $rango = (int)$_POST['rango'];
        $nombre = trim($_POST['nombre_vehiculo']);
        
        // Captura del estatus Premium
        $es_premium = isset($_POST['es_premium']) ? 1 : 0;

        // 2. Cálculo automático de precios basado en precios.php
        $c_dinero = $precios_base[$tipo][$subtipo][$rango]['dinero'] ?? 0;
        $c_acero = $precios_base[$tipo][$subtipo][$rango]['acero'] ?? 0;
        $c_petroleo = $precios_base[$tipo][$subtipo][$rango]['petroleo'] ?? 0;

        // 3. Gestión de la Imagen (Misma lógica que el Staff)
        $ruta_imagen = null;
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
            $directorio = "../uploads/vehiculos/";
            $nombre_archivo = time() . "_" . basename($_FILES["imagen"]["name"]);
            $ruta_destino = $directorio . $nombre_archivo;
            
            if (move_uploaded_file($_FILES["imagen"]["tmp_name"], $ruta_destino)) {
                $ruta_imagen = "uploads/vehiculos/" . $nombre_archivo;
            }
        }

        // 4. Inserción en la tienda global
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
            ':premium' => $es_premium
        ]);

        header("Location: ../views/lider_tienda.php?mensaje=agregado");
        exit();

    } catch (PDOException $e) {
        // Usamos el texto centralizado para el error
        die($txt['LOGIC']['ERR_CRITICO_SUMINISTRO'] . $e->getMessage());
    }
} else {
    header("Location: ../views/lider_tienda.php");
    exit();
}