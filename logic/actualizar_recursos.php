<?php
session_start();

// Importamos el diccionario de textos tácticos
$txt = require '../config/textos.php';

// Verificación estricta de Alto Mando
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') {
    exit($txt['LOGIC']['ERR_ACCESO_DENEGADO']);
}

require_once '../config/conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = (int)$_POST['equipo_id'];
    $nombre = trim($_POST['nombre_equipo']);
    $dinero = (int)$_POST['dinero'];
    $acero = (int)$_POST['acero'];
    $petroleo = (int)$_POST['petroleo'];
    $nueva_pass = trim($_POST['nueva_password']); 
    
    $naciones = !empty($_POST['naciones_activas_string']) ? $_POST['naciones_activas_string'] : null;

    try {
        // 1. Iniciamos la base de la consulta
        $sql = "UPDATE cuentas SET 
                nombre_equipo = :nom, 
                dinero = :din, 
                acero = :ace, 
                petroleo = :pet, 
                naciones_activas = :nac";
        
        // 2. Definimos los parámetros base
        $params = [
            ':nom' => $nombre,
            ':din' => $dinero,
            ':ace' => $acero,
            ':pet' => $petroleo,
            ':nac' => $naciones
        ];

        // 3. Si Jacky escribió una contraseña, la sumamos a la consulta
        if (!empty($nueva_pass)) {
            $sql .= ", password = :pass";
            $params[':pass'] = $nueva_pass; 
        }

        // 4. Cerramos la consulta con el ID (ESTA ERA LA PARTE QUE FALLABA)
        $sql .= " WHERE id = :id";
        $params[':id'] = $id; // Agregamos el ID al array de parámetros
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        header("Location: ../views/staff_dashboard.php?mensaje=ok");
        exit();

    } catch (PDOException $e) {
        // Reporte de error táctico si el SQL falla
        die($txt['LOGIC']['ERR_ACTUALIZAR_RECURSOS'] . $e->getMessage());
    }
} else {
    header("Location: ../views/staff_dashboard.php");
    exit();
}
?>