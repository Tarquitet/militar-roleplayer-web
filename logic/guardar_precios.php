<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') {
    exit("Acceso denegado");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nuevos_precios = $_POST['p'];

    // Convertimos el array de nuevo a código PHP para guardarlo en el archivo
    $contenido = "<?php\n\nreturn " . var_export($nuevos_precios, true) . ";\n";

    // Escribimos el archivo
    if (file_put_contents('../config/precios.php', $contenido)) {
        header("Location: ../views/staff_config_precios.php?mensaje=actualizado");
    } else {
        echo "Error: Asegúrate de que la carpeta 'config' tenga permisos de escritura.";
    }
}