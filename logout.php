<?php
// logout.php
session_start();
session_unset(); // Libera todas las variables de sesión
session_destroy(); // Destruye la sesión
header("Location: index.php"); // Redirige al index publico
exit();
?>