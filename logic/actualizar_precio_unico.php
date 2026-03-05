<?php
session_start();

// Importamos el diccionario de textos
$txt = require '../config/textos.php';

// Verificación estricta de Alto Mando
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') {
    exit($txt['LOGIC']['ERR_ACCESO_DENEGADO']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $precios = require '../config/precios.php';

    $t = $_POST['tipo'];
    $s = $_POST['subtipo'];
    $r = (int)$_POST['rango'];

    // Actualizamos solo los valores recibidos para ese vehículo específico
    $precios[$t][$s][$r] = [
        'dinero' => (int)$_POST['dinero'],
        'acero' => (int)$_POST['acero'],
        'petroleo' => (int)$_POST['petroleo']
    ];

    $contenido = "<?php\n\nreturn " . var_export($precios, true) . ";\n";
    
    // Verificamos si la escritura fue exitosa
    if (file_put_contents('../config/precios.php', $contenido)) {
        header("Location: ../views/staff_tienda.php?mensaje=precio_actualizado");
        exit();
    } else {
        // Usamos el texto centralizado si fallan los permisos de escritura
        die($txt['LOGIC']['ERR_ESCRITURA_PRECIOS']);
    }
} else {
    header("Location: ../views/staff_tienda.php");
    exit();
}
?>