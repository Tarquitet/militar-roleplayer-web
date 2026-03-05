<?php
// config/conexion.php

$host = 'localhost';
$dbname = 'militar_rp'; // <-- ¡Cámbialo por el nombre real!
$username = 'root'; // <-- Usuario por defecto en XAMPP/Laragon
$password = ''; // <-- Contraseña por defecto (suele estar vacía en local)

try {
    // Creamos la conexión PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Configuramos PDO para que nos muestre los errores de SQL si nos equivocamos en algo
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Puedes descomentar la siguiente línea temporalmente para probar si conecta
    // echo "Conexión exitosa a la base de datos";
    
} catch(PDOException $e) {
    // Si algo falla, detenemos todo y mostramos el error
    die("Error de conexión: " . $e->getMessage());
}
?>