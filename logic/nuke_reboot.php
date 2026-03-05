<?php
session_start();

// Verificamos que sea el Alto Mando (Staff) quien autoriza el reinicio
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_once '../config/conexion.php';
    
    // Importamos el diccionario de textos tácticos
    $txt = require '../config/textos.php';

    try {
        // Iniciamos la transacción de seguridad
        $pdo->beginTransaction();

        // 1. Vaciamos la tabla de inventarios (Purgar suministros)
        $pdo->exec("DELETE FROM inventario");
        
        // 2. Vaciamos la tabla de flotas (Hundir navíos)
        $pdo->exec("DELETE FROM flotas");

        // 3. Reseteamos los recursos, nombres y naciones de los líderes
        // Nota: NO borramos las cuentas de usuario, solo limpiamos su progreso
        $stmt = $pdo->prepare("UPDATE cuentas SET nombre_equipo = NULL, bandera_url = NULL, dinero = 0, acero = 0, petroleo = 0, naciones_activas = NULL WHERE rol = 'lider'");
        $stmt->execute();

        // 4. Registro en la Bitácora usando el texto centralizado
        $mensaje_bitacora = $txt['LOGIC']['LOG_NUKE'];
        $stmt_log = $pdo->prepare("INSERT INTO bitacora (mensaje, categoria) VALUES (:msg, 'nuke')");
        $stmt_log->execute([':msg' => $mensaje_bitacora]);

        // Si todo salió bien, confirmamos la aniquilación
        $pdo->commit();

        // Regresamos al dashboard con un mensaje de éxito destructivo
        header("Location: ../views/staff_dashboard.php?mensaje=nuke");
        exit();

    } catch (PDOException $e) {
        // Si algo falla, deshacemos cualquier cambio para evitar bases de datos corruptas
        $pdo->rollBack();
        
        // Usamos el texto centralizado para el error de sistema
        die($txt['LOGIC']['ERR_NUKE_CRITICO'] . $e->getMessage());
    }
} else {
    // Si intentan entrar por URL directamente, los pateamos al dashboard
    header("Location: ../views/staff_dashboard.php");
    exit();
}
?>