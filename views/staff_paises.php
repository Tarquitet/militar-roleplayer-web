<?php
session_start();

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/conexion.php';

try {
    // Consultamos todas las naciones registradas
    $stmt = $pdo->query("SELECT id, nombre FROM naciones ORDER BY nombre ASC");
    $naciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al cargar los países: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Staff - Países</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-200 min-h-screen pb-10">

    <?php include '../includes/nav_staff.php'; ?>

    <main class="p-8 max-w-4xl mx-auto">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white mb-2">Catálogo de Naciones</h1>
            <p class="text-gray-400">Agrega o elimina los países disponibles para las facciones.</p>
        </div>

        <?php if (isset($_GET['mensaje'])): ?>
            <?php if ($_GET['mensaje'] == 'agregado'): ?>
                <div class="bg-green-600 text-white p-3 rounded mb-6 text-sm font-medium">País agregado correctamente al sistema.</div>
            <?php elseif ($_GET['mensaje'] == 'eliminado'): ?>
                <div class="bg-red-600 text-white p-3 rounded mb-6 text-sm font-medium">País eliminado del sistema.</div>
            <?php elseif ($_GET['mensaje'] == 'duplicado'): ?>
                <div class="bg-yellow-600 text-white p-3 rounded mb-6 text-sm font-medium">Ese país ya existe en el sistema.</div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="md:col-span-1 bg-gray-800 p-6 rounded-lg border border-gray-700 shadow-lg h-fit">
                <h2 class="text-xl font-bold text-white mb-4">Añadir Nuevo País</h2>
                <form action="../logic/procesar_pais.php" method="POST">
                    <input type="hidden" name="accion" value="agregar">
                    <div class="mb-4">
                        <label class="block text-gray-400 text-sm mb-2" for="nombre_pais">Nombre de la Nación</label>
                        <input type="text" id="nombre_pais" name="nombre_pais" required placeholder="Ej: Italia"
                               class="w-full bg-gray-900 border border-gray-600 rounded px-3 py-2 text-white focus:outline-none focus:border-purple-500">
                    </div>
                    <button type="submit" class="w-full bg-purple-600 hover:bg-purple-500 text-white font-bold py-2 px-4 rounded transition">
                        Agregar al Catálogo
                    </button>
                </form>
            </div>

            <div class="md:col-span-2 bg-gray-800 rounded-lg border border-gray-700 shadow-lg overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-700 text-gray-300 text-xs uppercase tracking-wider">
                            <th class="p-4 border-b border-gray-600">ID</th>
                            <th class="p-4 border-b border-gray-600">Nombre del País</th>
                            <th class="p-4 border-b border-gray-600 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-300">
                        <?php foreach ($naciones as $nacion): ?>
                            <tr class="hover:bg-gray-750 border-b border-gray-700 transition last:border-0">
                                <td class="p-4 text-gray-500">#<?php echo $nacion['id']; ?></td>
                                <td class="p-4 font-medium text-white"><?php echo htmlspecialchars($nacion['nombre']); ?></td>
                                <td class="p-4 text-right">
                                    <form action="../logic/procesar_pais.php" method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar este país?');">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id_pais" value="<?php echo $nacion['id']; ?>">
                                        <button type="submit" class="text-red-400 hover:text-red-300 text-sm font-bold bg-red-900 bg-opacity-30 hover:bg-opacity-50 px-3 py-1 rounded transition">
                                            Eliminar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</body>
</html>