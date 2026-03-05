<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') exit();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $precios = require '../config/precios.php';

    $t = $_POST['tipo'];
    $s = $_POST['subtipo'];
    $r = (int)$_POST['rango'];

    // Actualizamos solo los valores recibidos
    $precios[$t][$s][$r] = [
        'dinero' => (int)$_POST['dinero'],
        'acero' => (int)$_POST['acero'],
        'petroleo' => (int)$_POST['petroleo']
    ];

    $contenido = "<?php\n\nreturn " . var_export($precios, true) . ";\n";
    file_put_contents('../config/precios.php', $contenido);

    header("Location: ../views/staff_tienda.php?mensaje=precio_actualizado");
}