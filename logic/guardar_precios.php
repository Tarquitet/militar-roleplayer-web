<?php
session_start();

// Importamos el diccionario de textos
$txt = require '../config/textos.php';

// Verificación estricta del Alto Mando
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') {
    exit($txt['LOGIC']['ERR_ACCESO_DENEGADO']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nuevos_precios = $_POST['p'];

    // Convertimos el array de nuevo a código PHP para guardarlo en el archivo
    $contenido = "<?php\n\nreturn " . var_export($nuevos_precios, true) . ";\n";

    // Escribimos el archivo de configuración táctica
    if (file_put_contents('../config/precios.php', $contenido)) {
        header("Location: ../views/staff_config_precios.php?mensaje=actualizado");
    } else {
        // Usamos el texto centralizado si fallan los permisos de escritura
        echo $txt['LOGIC']['ERR_ESCRITURA_PRECIOS'];
    }
}
?>