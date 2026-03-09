<?php
session_start();

// Verificamos que sea el Staff quien autoriza el reinicio
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_once '../config/conexion.php';
    $txt = require '../config/textos.php';

    // NUEVA VALIDACIÓN DE SEGURIDAD
    $palabra = $_POST['confirm_word'] ?? '';
    
    if (strtoupper($palabra) !== 'REINICIAR') {
        // Si la palabra no coincide, lo regresamos con el error
        header("Location: ../views/staff_dashboard.php?msg=err_nuke");
        exit();
    }

    try {
        $pdo->beginTransaction();

        // 1. LIMPIEZA DE TABLAS (Borrar todo el progreso)
        $pdo->exec("DELETE FROM inventario");
        $pdo->exec("DELETE FROM flotas");
        $pdo->exec("DELETE FROM mercado_tradeos"); // Limpiar ofertas de intercambio
        $pdo->exec("DELETE FROM solicitudes_reembolso"); // Limpiar peticiones pendientes
        $pdo->exec("DELETE FROM planos_desbloqueados"); // Quitar patentes aprendidas
        $pdo->exec("DELETE FROM catalogo_tienda"); // Esto vacía la tienda por completo

        // 2. REINICIO DE EQUIPOS
        // Cambiamos el nombre a "Equipo [ID]" y ponemos una bandera por defecto
        $stmt = $pdo->prepare("
            UPDATE cuentas 
            SET nombre_equipo = CONCAT('Equipo ', id), 
                bandera_url = 'assets/img/banderas/default.png', 
                dinero = 0, 
                acero = 0, 
                petroleo = 0, 
                naciones_activas = NULL 
            WHERE rol = 'lider'
        ");
        $stmt->execute();

        // 3. REGISTRO EN EL HISTORIAL
        $mensaje_bitacora = $txt['LOGIC']['LOG_NUKE'];
        $stmt_log = $pdo->prepare("INSERT INTO bitacora (mensaje, categoria) VALUES (:msg, 'nuke')");
        $stmt_log->execute([':msg' => $mensaje_bitacora]);

        // 4. LIMPIEZA DE ARCHIVOS (Borrar imágenes subidas)
        $carpetas_a_limpiar = [
            '../uploads/banderas/',
            '../uploads/vehiculos/'
        ];

        foreach ($carpetas_a_limpiar as $carpeta) {
            $archivos = glob($carpeta . '*'); 
            if ($archivos) {
                foreach ($archivos as $archivo) {
                    if (is_file($archivo)) {
                        unlink($archivo);
                    }
                }
            }
        }

        $pdo->commit();
        header("Location: ../views/staff_dashboard.php?mensaje=nuke");
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        die("ERROR CRÍTICO AL REINICIAR: " . $e->getMessage());
    }
} else {
    header("Location: ../views/staff_dashboard.php");
    exit();
}