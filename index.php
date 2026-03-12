<?php
$root_path = "";
require_once 'config/conexion.php';
// Cargamos los textos una sola vez
$txt = require 'config/textos.php';

try {
    $stmt_conf = $pdo->query("SELECT discord_link, descripcion_servidor FROM configuracion LIMIT 1");
    $config = $stmt_conf->fetch(PDO::FETCH_ASSOC);
    $discord = $config['discord_link'] ?? '#';

    $stmt_equipos = $pdo->query("SELECT nombre_equipo, bandera_url, naciones_activas FROM cuentas WHERE rol = 'lider' ORDER BY id ASC");
    $equipos = $stmt_equipos->fetchAll(PDO::FETCH_ASSOC);

    // OPTIMIZACIÓN: Cierre táctico de la conexión de base de datos
    // Permite que InfinityFree libere el proceso mientras el usuario lee la página
    $stmt_conf = null;
    $stmt_equipos = null;
    $pdo = null;

} catch (PDOException $e) { die("Fallo en radar: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title><?php echo $txt['GLOBAL']['NOMBRE_PROYECTO']; ?></title>
    <?php include 'includes/head.php'; ?>
</head>
<body>

    <header class="m-panel p-6 flex justify-between items-center shadow-2xl mb-8">
        <div class="text-2xl font-black text-[var(--aoe-gold)] tracking-tighter uppercase italic">
            <?php echo $txt['GLOBAL']['NOMBRE_PROYECTO']; ?>
        </div>
        <div class="flex gap-4">
            <a href="<?php echo htmlspecialchars($discord); ?>" target="_blank" class="btn-m text-[10px]">
                <?php echo $txt['BOTONES']['DISCORD']; ?>
            </a>
            <a href="login.php" class="btn-m text-[10px] grayscale opacity-70">
                <?php echo $txt['BOTONES']['INGRESAR']; ?>
            </a>
        </div>
    </header>

    <main class="p-8 max-w-6xl mx-auto">
        <div class="mb-10 p-6 m-panel border-l-8 border-[var(--aoe-gold)]">
            <h1 class="text-xs font-black text-[var(--aoe-gold)] uppercase tracking-[0.3em] mb-2">
                <?php echo $txt['GLOBAL']['SITUACION_TITULO']; ?>
            </h1>
            <p class="text-lg italic">
                "<?php echo htmlspecialchars($config['descripcion_servidor'] ?? '...'); ?>"
            </p>
        </div>

        <div class="m-panel overflow-hidden">
            <table class="w-full text-left table-m">
                <thead class="text-[9px] uppercase tracking-[0.2em] text-[var(--aoe-gold)]">
                    <tr>
                        <th class="p-5"><?php echo $txt['RADAR']['COL_ESTANDARTE']; ?></th>
                        <th class="p-5"><?php echo $txt['RADAR']['COL_IDENTIDAD']; ?></th>
                        <th class="p-5"><?php echo $txt['RADAR']['COL_TERRITORIO']; ?></th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    <?php if(empty($equipos)): ?>
                        <tr><td colspan="3" class="p-10 text-center italic text-gray-600">
                            <?php echo $txt['RADAR']['SIN_DATOS']; ?>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach($equipos as $e): ?>
                        <tr class="hover:bg-white/5 transition duration-300">
                            <td class="p-5">
                                <div class="w-16 h-10 bg-black border border-[var(--wood-border)]">
                                    <?php if($e['bandera_url']): ?>
                                        <img src="<?php echo htmlspecialchars($e['bandera_url']); ?>" class="w-full h-full object-cover">
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="p-5">
                                <span class="text-2xl font-black text-white italic tracking-tighter uppercase">
                                    <?php echo htmlspecialchars($e['nombre_equipo'] ?: '...'); ?>
                                </span>
                            </td>
                            <td class="p-5">
                                <div class="flex flex-wrap gap-2">
                                    <?php 
                                    $nacs = array_filter(explode(',', $e['naciones_activas'] ?? ''));
                                    if(empty($nacs)): ?>
                                        <span class="text-gray-600 italic uppercase text-[10px]">
                                            <?php echo $txt['RADAR']['NEUTRAL']; ?>
                                        </span>
                                    <?php else: 
                                        foreach($nacs as $n): ?>
                                            <span class="bg-black/40 text-[var(--aoe-gold)] border border-[var(--wood-border)] px-3 py-1 rounded text-[10px] font-black uppercase">
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

    <?php include 'includes/footer.php'; ?>
</body>
</html>