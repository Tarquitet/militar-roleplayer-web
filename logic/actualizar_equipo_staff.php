<?php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') { die("No autorizado."); }

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = (int)$_POST['id'];

    // --- ACCIÓN A: CENSURA Y BORRADO FÍSICO ---
    if (isset($_POST['solo_borrar_bandera_staff']) && $_POST['solo_borrar_bandera_staff'] == '1') {
        try {
            // 1. Buscamos la ruta actual antes de borrarla de la BD
            $stmt_path = $pdo->prepare("SELECT bandera_url FROM cuentas WHERE id = ?");
            $stmt_path->execute([$id]);
            $current_path = $stmt_path->fetchColumn();

            // 2. Si existe el archivo en la carpeta, lo eliminamos físicamente
            if ($current_path && file_exists("../" . $current_path)) {
                unlink("../" . $current_path);
            }

            // 3. Ahora sí, limpiamos la base de datos
            $stmt = $pdo->prepare("UPDATE cuentas SET bandera_url = NULL WHERE id = ?");
            $stmt->execute([$id]);
            
            header("Location: ../views/staff_dashboard.php?msg=censura_ok");
            exit();
        } catch (Exception $e) {
            die("Error en borrado físico: " . $e->getMessage());
        }
    }

    // --- ACCIÓN B: ACTUALIZACIÓN GENERAL ---
    $nombre_equipo = $_POST['nombre_equipo'];
    $username = $_POST['username']; 
    $bandera_url = $_POST['bandera_url'];
    $dinero = (int)$_POST['dinero'];
    $acero = (int)$_POST['acero'];
    $petroleo = (int)$_POST['petroleo'];
    $password = $_POST['password'];

    try {
        $sql = "UPDATE cuentas SET 
                nombre_equipo = :ne, 
                username = :un, 
                bandera_url = :bu, 
                dinero = :d, 
                acero = :a, 
                petroleo = :p";
        
        $params = [
            ':ne' => $nombre_equipo,
            ':un' => $username,
            ':bu' => !empty($bandera_url) ? $bandera_url : NULL,
            ':d' => $dinero,
            ':a' => $acero,
            ':p' => $petroleo,
            ':id' => $id
        ];

        if (!empty($password)) {
            $sql .= ", password = :pass";
            $params[':pass'] = $password; 
        }

        $sql .= " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        header("Location: ../views/staff_dashboard.php?msg=update_ok");
        exit();
    } catch (PDOException $e) { die("Fallo crítico: " . $e->getMessage()); }
}