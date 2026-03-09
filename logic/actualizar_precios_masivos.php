<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') {
    die("Acceso denegado. Protocolo de seguridad violado.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo'] ?? '';
    $subtipo = $_POST['subtipo'] ?? '';
    $precios_post = $_POST['precios'] ?? [];

    if (empty($tipo) || empty($subtipo) || empty($precios_post)) {
        header("Location: ../views/staff_tienda.php?msg=err_datos_vacios");
        exit();
    }

    $archivo_precios = '../config/precios.php';
    
    // Leemos el archivo actual
    if (file_exists($archivo_precios)) {
        $precios = require $archivo_precios;
    } else {
        $precios = [];
    }

    // Aseguramos que la estructura matriz exista
    if (!isset($precios[$tipo])) {
        $precios[$tipo] = [];
    }
    if (!isset($precios[$tipo][$subtipo])) {
        $precios[$tipo][$subtipo] = [];
    }

    // Iteramos sobre los 8 rangos recibidos del formulario tipo Excel
    foreach ($precios_post as $rango => $valores) {
        $precios[$tipo][$subtipo][(int)$rango] = [
            'dinero' => (int)$valores['dinero'],
            'acero' => (int)$valores['acero'],
            'petroleo' => (int)$valores['petroleo']
        ];
    }

    // Convertimos el array a código PHP válido
    $contenido = "<?php\n\nreturn " . var_export($precios, true) . ";\n";

    // Sobreescribimos el archivo físico
    if (file_put_contents($archivo_precios, $contenido) !== false) {
        header("Location: ../views/staff_tienda.php?msg=precios_ok");
    } else {
        die("Error crítico: El servidor ha denegado los permisos de escritura en 'config/precios.php'.");
    }
    exit();
}