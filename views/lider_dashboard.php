<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'lider') {
    header("Location: ../login.php");
    exit();
}
require_once '../config/conexion.php';

$lider_id = $_SESSION['usuario_id'];

try {
    // 1. Datos de mi equipo (Recursos Propios)
    $stmt_mio = $pdo->prepare("SELECT id, nombre_equipo, bandera_url, dinero, acero, petroleo FROM cuentas WHERE id = :id");
    $stmt_mio->execute([':id' => $lider_id]);
    $mi_equipo = $stmt_mio->fetch(PDO::FETCH_ASSOC);

    // 2. RADAR: Obtenemos TODOS los recursos de los enemigos
    $stmt_otros = $pdo->prepare("SELECT nombre_equipo, bandera_url, dinero, acero, petroleo, naciones_activas FROM cuentas WHERE rol = 'lider' AND id != :id ORDER BY dinero DESC");
    $stmt_otros->execute([':id' => $lider_id]);
    $otros_equipos = $stmt_otros->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error en el radar: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Mando - Operaciones</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .modal-active { overflow: hidden; }
        .recurso-input-mini { width: 75px; }
    </style>
</head>
<body class="bg-[#0f172a] text-gray-200 min-h-screen pb-20">

    <?php include '../includes/nav_lider.php'; ?>

    <main class="p-8 max-w-[95%] mx-auto">
        
        <div class="mb-12">
            <h2 class="text-blue-500 font-black uppercase text-[10px] tracking-[0.3em] mb-4">Estado de Mi Facción</h2>
            <div class="bg-[#1e293b] rounded-lg border border-blue-900/50 shadow-2xl overflow-hidden">
                <table class="w-full text-left">
                    <thead class="bg-[#111827] text-gray-500 text-[10px] uppercase tracking-widest border-b border-gray-700">
                        <tr>
                            <th class="p-4">Identidad</th>
                            <th class="p-4 text-center">Recursos Actuales</th>
                            <th class="p-4 text-center">Estandarte</th>
                            <th class="p-4 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="bg-blue-900/5">
                            <td class="p-4">
                                <span class="text-2xl font-black text-white italic uppercase tracking-tighter">
                                    <?php echo htmlspecialchars($mi_equipo['nombre_equipo'] ?: 'Sin Asignar'); ?>
                                </span>
                                <p class="text-[9px] text-blue-400 font-bold mt-1">ID DE MANDO: #<?php echo $mi_equipo['id']; ?></p>
                            </td>
                            <td class="p-4">
                                <div class="flex justify-center gap-6">
                                    <div class="text-center"><span class="block text-[8px] text-gray-500 uppercase">Dinero</span><span class="text-green-400 font-black text-lg">$<?php echo number_format($mi_equipo['dinero']); ?></span></div>
                                    <div class="text-center border-l border-gray-700 pl-6"><span class="block text-[8px] text-gray-500 uppercase">Acero</span><span class="text-white font-black text-lg"><?php echo number_format($mi_equipo['acero']); ?>t</span></div>
                                    <div class="text-center border-l border-gray-700 pl-6"><span class="block text-[8px] text-gray-500 uppercase">Petróleo</span><span class="text-yellow-500 font-black text-lg"><?php echo number_format($mi_equipo['petroleo']); ?>L</span></div>
                                </div>
                            </td>
                            <td class="p-4">
                                <div class="flex justify-center">
                                    <div class="w-20 h-12 bg-black rounded border border-gray-600 overflow-hidden shadow-inner">
                                        <?php if($mi_equipo['bandera_url']): ?>
                                            <img src="../<?php echo $mi_equipo['bandera_url']; ?>" class="w-full h-full object-cover">
                                        <?php else: ?><div class="h-full flex items-center justify-center text-[8px] text-gray-700 font-bold">NO FLAG</div><?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4 text-right">
                                <button onclick="abrirModal()" class="bg-blue-600 hover:bg-blue-500 text-white font-black py-2 px-5 rounded text-[10px] uppercase shadow-lg transition tracking-widest">Configurar Identidad</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <h2 class="text-gray-500 font-black uppercase text-[10px] tracking-[0.3em] mb-4">Radar de Facciones Enemigas</h2>
            <div class="bg-[#1e293b]/50 rounded-lg border border-gray-800 shadow-xl overflow-hidden">
                <table class="w-full text-left">
                    <thead class="bg-[#111827] text-gray-600 text-[9px] uppercase tracking-wider border-b border-gray-800">
                        <tr>
                            <th class="p-4">Enemigo</th>
                            <th class="p-4 text-center">Dinero ($)</th>
                            <th class="p-4 text-center">Acero</th>
                            <th class="p-4 text-center">Petróleo (L)</th>
                            <th class="p-4">Jurisdicción</th>
                        </tr>
                    </thead>
                    <tbody class="text-xs">
                        <?php foreach($otros_equipos as $rival): ?>
                            <tr class="border-b border-gray-800/50 hover:bg-gray-800/20 transition">
                                <td class="p-4 flex items-center gap-3">
                                    <div class="w-10 h-6 bg-black rounded border border-gray-700 overflow-hidden grayscale opacity-40">
                                        <?php if($rival['bandera_url']): ?><img src="../<?php echo $rival['bandera_url']; ?>" class="w-full h-full object-cover"><?php endif; ?>
                                    </div>
                                    <span class="font-black text-gray-400 uppercase italic"><?php echo htmlspecialchars($rival['nombre_equipo']); ?></span>
                                </td>
                                <td class="p-4 text-center font-bold text-green-600/70">$<?php echo number_format($rival['dinero']); ?></td>
                                <td class="p-4 text-center font-bold text-gray-400"><?php echo number_format($rival['acero']); ?>t</td>
                                <td class="p-4 text-center font-bold text-yellow-600/70"><?php echo number_format($rival['petroleo']); ?>L</td>
                                <td class="p-4">
                                    <div class="flex flex-wrap gap-1">
                                        <?php 
                                        $nacs = array_filter(explode(',', $rival['naciones_activas'] ?? ''));
                                        foreach($nacs as $n): ?>
                                            <span class="bg-gray-900 text-gray-600 border border-gray-800 px-2 py-0.5 rounded text-[8px] uppercase font-bold"><?php echo trim($n); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div id="modalConfig" class="hidden fixed inset-0 bg-black/95 z-[100] flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="bg-[#1e293b] border-2 border-blue-600/30 w-full max-w-sm rounded shadow-2xl overflow-hidden">
            <div class="p-3 bg-[#111827] border-b border-gray-700 flex justify-between items-center">
                <h3 class="font-black text-white text-[10px] uppercase tracking-widest">Protocolo de Identidad</h3>
                <button onclick="cerrarModal()" class="text-gray-500 hover:text-white text-2xl font-bold">&times;</button>
            </div>
            <form action="../logic/actualizar_perfil_lider.php" method="POST" enctype="multipart/form-data" class="p-6 space-y-5">
                <div>
                    <label class="text-[9px] text-gray-500 uppercase font-black block mb-2">Nombre de Facción</label>
                    <input type="text" name="nombre_equipo" value="<?php echo htmlspecialchars($mi_equipo['nombre_equipo']); ?>" required class="w-full bg-[#0f172a] border border-gray-700 rounded px-4 py-3 text-sm text-white focus:border-blue-600 outline-none">
                </div>
                <div>
                    <label class="text-[9px] text-gray-500 uppercase font-black block mb-2">Estandarte</label>
                    <div class="bg-[#0f172a] border border-gray-700 rounded p-4 text-center">
                        <input type="file" name="bandera" accept="image/*" class="text-[10px] text-gray-500 file:bg-blue-600 file:border-0 file:text-white file:text-[9px] file:font-black file:rounded file:px-3 file:py-1 cursor-pointer">
                    </div>
                </div>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-black py-4 rounded text-xs uppercase shadow-lg tracking-[0.2em]">Confirmar Identidad</button>
            </form>
        </div>
    </div>

    <script>
        function abrirModal() { document.getElementById('modalConfig').classList.remove('hidden'); document.body.classList.add('modal-active'); }
        function cerrarModal() { document.getElementById('modalConfig').classList.add('hidden'); document.body.classList.remove('modal-active'); }
    </script>
</body>
</html>