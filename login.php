<?php
// Iniciar la sesión para poder guardar los datos del usuario
session_start();

// Si el usuario ya está logueado, lo redirigimos para que no vea el login
if (isset($_SESSION['rol'])) {
    if ($_SESSION['rol'] == 'staff') {
        header("Location: views/staff_dashboard.php");
    } else {
        header("Location: views/lider_dashboard.php");
    }
    exit();
}

// Requerir la conexión a la base de datos
require_once 'config/conexion.php';

$error = '';

// Procesar el formulario cuando se envía
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($usuario) && !empty($password)) {
        try {
            // Consultamos si existe el usuario y la contraseña coincide
            $stmt = $pdo->prepare("SELECT id, username, rol, nombre_equipo FROM cuentas WHERE username = :username AND password = :password");
            $stmt->bindParam(':username', $usuario);
            $stmt->bindParam(':password', $password);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                // El usuario existe, guardamos sus datos en la sesión
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['rol'] = $user['rol'];
                $_SESSION['nombre_equipo'] = $user['nombre_equipo'];

                // Redirigir según el rol
                if ($user['rol'] == 'staff') {
                    header("Location: views/staff_dashboard.php");
                } else {
                    header("Location: views/lider_dashboard.php");
                }
                exit();
            } else {
                $error = "Usuario o contraseña incorrectos.";
            }
        } catch (PDOException $e) {
            $error = "Error en la base de datos: " . $e->getMessage();
        }
    } else {
        $error = "Por favor, completa todos los campos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Panel de Servidor</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 flex items-center justify-center h-screen">

    <div class="bg-gray-800 p-8 rounded-lg shadow-lg w-96 border border-gray-700">
        <h2 class="text-2xl font-bold text-white mb-6 text-center">Acceso al Sistema</h2>

        <?php if (!empty($error)): ?>
            <div class="bg-red-500 text-white p-3 rounded mb-4 text-sm text-center">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="mb-4">
                <label class="block text-gray-300 text-sm font-bold mb-2" for="username">Usuario</label>
                <input class="w-full px-3 py-2 text-gray-900 bg-gray-200 rounded focus:outline-none focus:bg-white" 
                       type="text" id="username" name="username" placeholder="Ej: lider1 o staff" required>
            </div>
            
            <div class="mb-6">
                <label class="block text-gray-300 text-sm font-bold mb-2" for="password">Contraseña</label>
                <input class="w-full px-3 py-2 text-gray-900 bg-gray-200 rounded focus:outline-none focus:bg-white" 
                       type="password" id="password" name="password" placeholder="Tu contraseña" required>
            </div>
            
            <button class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-2 px-4 rounded transition duration-200" 
                    type="submit">
                Entrar al Panel
            </button>
        </form>
    </div>

</body>
</html>