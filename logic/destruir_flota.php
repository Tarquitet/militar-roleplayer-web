<?php
session_start();

// Verificación de Alto Mando
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/conexion.php';

// Importamos el diccionario de textos
$txt = require '../config/textos.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $flota_id = (int)$_POST['flota_id'];
    $equipo_id = (int)$_POST['equipo_id'];

    try {
        // [CORRECCIÓN] Obtenemos el nombre del equipo primero para que la bitácora funcione
        $stmt_eq = $pdo->prepare("SELECT nombre_equipo FROM cuentas WHERE id = :id");
        $stmt_eq->execute([':id' => $equipo_id]);
        $equipo = $stmt_eq->fetch(PDO::FETCH_ASSOC);
        $nombre_equipo = $equipo ? $equipo['nombre_equipo'] : 'Facción Desconocida';

        // Borramos específicamente la flota indicada
        $stmt = $pdo->prepare("DELETE FROM flotas WHERE id = :id");
        $stmt->bindParam(':id', $flota_id);
        $stmt->execute();

        // Registramos el ataque en la bitácora usando nuestro texto centralizado
        $mensaje_log = $txt['LOGIC']['LOG_FLOTA_DESTRUIDA'] . $nombre_equipo;
        $stmt_log = $pdo->prepare("INSERT INTO bitacora (mensaje, categoria) VALUES (:msg, 'combate')");
        $stmt_log->execute([':msg' => $mensaje_log]);

        // Regresamos al inventario de ese mismo equipo con la confirmación de la orden
        header("Location: ../views/staff_ver_inventario.php?id=" . $equipo_id . "&mensaje=destruida");
        exit();

    } catch (PDOException $e) {
        // Reporte de error táctico si falla la conexión
        die($txt['LOGIC']['ERR_DESTRUIR_FLOTA'] . $e->getMessage());
    }
} else {
    header("Location: ../views/staff_dashboard.php");
    exit();
}
?>