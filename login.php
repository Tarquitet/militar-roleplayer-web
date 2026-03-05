<?php
// Iniciar la sesión para poder guardar los datos del usuario
session_start();

// Si el usuario ya está logueado, lo redirigimos para que no vea el login
if (isset($_SESSION['rol'])) {
    header("Location: views/" . ($_SESSION['rol'] == 'staff' ? 'staff_dashboard.php' : 'lider_dashboard.php'));
    exit();
}

// Requerir la conexión a la base de datos y el diccionario de textos
$root_path = ""; // Estamos en la raíz
require_once 'config/conexion.php';
$txt = require 'config/textos.php';

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
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['rol'] = $user['rol'];
                $_SESSION['nombre_equipo'] = $user['nombre_equipo'];

                header("Location: views/" . ($user['rol'] == 'staff' ? 'staff_dashboard.php' : 'lider_dashboard.php'));
                exit();
            } else {
                $error = $txt['LOGIN']['ERR_CREDENCIALES'];
            }
        } catch (PDOException $e) {
            $error = "Error en la base de datos: " . $e->getMessage();
        }
    } else {
        $error = $txt['LOGIN']['ERR_CAMPOS'];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title><?php echo $txt['LOGIN']['TITULO']; ?></title>
    <?php include 'includes/head.php'; ?>
</head>
<body class="flex items-center justify-center h-screen bg-[#0d0e0a]">

    <div class="m-panel p-10 w-96 text-center shadow-2xl relative">
        <a href="index.php" class="absolute -top-10 left-0 text-[10px] text-[var(--aoe-gold)] uppercase font-bold tracking-widest hover:underline transition">
            ← <?php echo $txt['GLOBAL']['VOLVER_RADAR']; ?>
        </a>

        <h2 class="m-title text-2xl mb-8 border-b border-[var(--wood-border)] pb-4">
            <?php echo $txt['LOGIN']['TITULO']; ?>
        </h2>

        <?php if (!empty($error)): ?>
            <div class="bg-red-900/40 border border-red-600 text-red-200 p-2 mb-6 text-xs italic">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" class="space-y-6">
            <div class="text-left">
                <label class="block text-[10px] uppercase font-bold mb-2 text-[var(--khaki-beige)] tracking-widest">
                    <?php echo $txt['LOGIN']['USUARIO']; ?>
                </label>
                <input class="m-input w-full text-center" 
                       type="text" name="username" placeholder="---" required>
            </div>
            
            <div class="text-left">
                <label class="block text-[10px] uppercase font-bold mb-2 text-[var(--khaki-beige)] tracking-widest">
                    <?php echo $txt['LOGIN']['CLAVE']; ?>
                </label>
                <input class="m-input w-full text-center" 
                       type="password" name="password" placeholder="---" required>
            </div>
            
            <button class="btn-m w-full py-4 mt-4 text-sm" type="submit">
                <?php echo $txt['BOTONES']['ENTRAR']; ?>
            </button>
        </form>
    </div>

</body>
</html>