<?php
require_once 'config/conexion.php';
$stmt = $pdo->query("SELECT * FROM bitacora ORDER BY fecha DESC LIMIT 50");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Bitácora de Guerra</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-200 p-8">
    <div class="max-w-4xl mx-auto">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-black uppercase italic">📜 Registro de Operaciones</h1>
            <a href="index.php" class="text-blue-500 hover:underline text-sm uppercase font-bold">Volver al Inicio</a>
        </div>

        <div class="space-y-4">
            <?php foreach ($logs as $log): ?>
                <div class="bg-gray-800 border-l-4 <?php echo $log['categoria'] == 'nuke' ? 'border-red-600' : 'border-blue-600'; ?> p-4 rounded shadow">
                    <div class="flex justify-between text-[10px] text-gray-500 uppercase font-black mb-1">
                        <span><?php echo $log['categoria']; ?></span>
                        <span><?php echo $log['fecha']; ?></span>
                    </div>
                    <p class="text-sm"><?php echo htmlspecialchars($log['mensaje']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>