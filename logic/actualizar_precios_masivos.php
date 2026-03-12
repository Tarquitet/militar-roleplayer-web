<?php
session_start();

// 🔥 INYECCIÓN 1: Traemos la base de datos para actualizar los vehículos existentes
require_once '../config/conexion.php';

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

    try {
        // Iniciamos un protocolo de guardado seguro en la Base de Datos
        $pdo->beginTransaction();

        // Iteramos sobre los 8 rangos recibidos del formulario tipo Excel
        foreach ($precios_post as $rango => $valores) {
            $d = (int)$valores['dinero'];
            $a = (int)$valores['acero'];
            $p = (int)$valores['petroleo'];
            $r = (int)$rango;

            // Actualizamos el array para el archivo de configuración
            $precios[$tipo][$subtipo][$r] = [
                'dinero' => $d,
                'acero' => $a,
                'petroleo' => $p
            ];

            // 🔥 INYECCIÓN 2 (MAGIA UX): Buscamos los vehículos existentes en la base de datos 
            // que coincidan con este Tipo y Rango, y los actualizamos todos de golpe.
            $stmt = $pdo->prepare("
                UPDATE catalogo_tienda 
                SET costo_dinero = ?, costo_acero = ?, costo_petroleo = ? 
                WHERE tipo = ? AND (subtipo = ? OR clase = ?) AND rango = ?
            ");
            $stmt->execute([$d, $a, $p, $tipo, $subtipo, $subtipo, $r]);
        }

        // Confirmamos los cambios en la base de datos
        $pdo->commit();
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        die("ERROR CRÍTICO AL ACTUALIZAR LA BASE DE DATOS: " . $e->getMessage());
    }

    // Convertimos el array a código PHP válido
    $contenido = "<?php\n\nreturn " . var_export($precios, true) . ";\n";

    // Sobreescribimos el archivo físico
    if (file_put_contents($archivo_precios, $contenido) !== false) {
        
        // 🔥 INYECCIÓN 3 (EL EXORCISMO DEL FANTASMA OPCACHE): 
        // Limpiamos la caché interna de PHP para forzar la lectura del nuevo archivo inmediatamente.
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate(realpath($archivo_precios), true);
        }
        
        header("Location: ../views/staff_tienda.php?msg=precios_ok");
    } else {
        die("Error crítico: El servidor ha denegado los permisos de escritura en 'config/precios.php'.");
    }
    exit();
}