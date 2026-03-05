<?php
require_once 'config/conexion.php';

try {
    // 1. Configuración del servidor
    $stmt_conf = $pdo->query("SELECT discord_link, descripcion_servidor FROM configuracion LIMIT 1");
    $config = $stmt_conf->fetch(PDO::FETCH_ASSOC);
    $discord = $config['discord_link'] ?? '#';

    // 2. Radar de Facciones (Solo identidad y territorios)
    $stmt_equipos = $pdo->query("SELECT nombre_equipo, bandera_url, naciones_activas FROM cuentas WHERE rol = 'lider' ORDER BY id ASC");
    $equipos = $stmt_equipos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error en la red de datos: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Estado Global - Inteligencia Pública</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#0f172a] text-gray-200 min-h-screen">

    <header class="bg-[#111827] p-6 border-b border-gray-800 flex justify-between items-center shadow-2xl">
        <div class="text-xl font-black text-white tracking-tighter uppercase italic">
            Radar de <span class="text-blue-600">Facciones</span> Global
        </div>
        <div class="flex gap-4">
            <a href="<?php echo htmlspecialchars($discord); ?>" target="_blank" class="bg-[#5865F2] hover:bg-[#4752C4] text-white text-[10px] font-black py-2 px-4 rounded transition flex items-center gap-2 tracking-widest uppercase">
                COMUNICACIONES DISCORD
            </a>
            <a href="login.php" class="bg-gray-800 hover:bg-gray-700 text-white text-[10px] font-black py-2 px-4 rounded border border-gray-700 transition tracking-widest uppercase">
                INGRESAR AL MANDO
            </a>
        </div>
    </header>

    <main class="p-8 max-w-6xl mx-auto">
        
        <div class="mb-10 p-6 bg-[#1e293b]/50 border-l-4 border-blue-600 rounded shadow-lg">
            <h1 class="text-xs font-black text-blue-500 uppercase tracking-[0.3em] mb-2">Situación de Operaciones</h1>
            <p class="text-sm text-gray-400 leading-relaxed italic">
                "<?php echo htmlspecialchars($config['descripcion_servidor'] ?? 'Esperando órdenes del alto mando...'); ?>"
            </p>
        </div>

        <div class="bg-[#1e293b] rounded border border-gray-800 shadow-2xl overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-[#111827] text-gray-500 text-[10px] uppercase tracking-[0.2em] border-b border-gray-800">
                    <tr>
                        <th class="p-5">Estandarte Operativo</th>
                        <th class="p-5">Identidad de la Facción</th>
                        <th class="p-5">Jurisdicción Territorial</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    <?php if(empty($equipos)): ?>
                        <tr><td colspan="3" class="p-10 text-center text-gray-600 italic uppercase text-[10px] tracking-widest">No se detectan señales en el radar...</td></tr>
                    <?php else: ?>
                        <?php foreach($equipos as $e): ?>
                        <tr class="border-b border-gray-800/50 hover:bg-blue-900/5 transition duration-300">
                            <td class="p-5">
                                <div class="w-16 h-10 bg-black rounded border border-gray-700 overflow-hidden shadow-inner flex-shrink-0">
                                    <?php if($e['bandera_url']): ?>
                                        <img src="<?php echo htmlspecialchars($e['bandera_url']); ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="h-full flex items-center justify-center text-[8px] text-gray-700 font-bold uppercase">Sin Datos</div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="p-5">
                                <span class="text-xl font-black text-white uppercase italic tracking-tighter">
                                    <?php echo htmlspecialchars($e['nombre_equipo'] ?: 'Comando Desconocido'); ?>
                                </span>
                            </td>
                            <td class="p-5">
                                <div class="flex flex-wrap gap-2">
                                    <?php 
                                    $nacs = array_filter(explode(',', $e['naciones_activas'] ?? ''));
                                    if(empty($nacs)): ?>
                                        <span class="text-gray-600 text-[10px] italic uppercase">Territorio Neutral</span>
                                    <?php else: 
                                        foreach($nacs as $n): ?>
                                            <span class="bg-[#0f172a] text-blue-400 border border-blue-900/30 px-3 py-1 rounded text-[10px] font-black uppercase tracking-tighter">
                                                <?php echo htmlspecialchars(trim($n)); ?>
                                            </span>
                                        <?php endforeach; 
                                    endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <footer class="p-8 text-center text-gray-700 text-[9px] uppercase font-bold tracking-[0.4em] mt-10">
        Radar de Inteligencia Pública &copy; 2026 - Desarrollado por Tarquitet
    </footer>

</body>
</html>