<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'lider') {
    header("Location: ../login.php");
    exit();
}
require_once '../config/conexion.php';

$lider_id = $_SESSION['usuario_id'];

try {
    // 1. Datos de Identidad
    $stmt_u = $pdo->prepare("SELECT nombre_equipo, naciones_activas FROM cuentas WHERE id = :id");
    $stmt_u->execute([':id' => $lider_id]);
    $user = $stmt_u->fetch(PDO::FETCH_ASSOC);

    $naciones_mando = !empty($user['naciones_activas']) ? array_map('trim', explode(',', $user['naciones_activas'])) : [];

    // 2. Radar de Hangar (JOIN corregido con tus columnas)
    $stmt_inv = $pdo->prepare("
        SELECT c.*, IFNULL(i.cantidad, 0) as stock_actual 
        FROM catalogo_tienda c
        LEFT JOIN inventario i ON c.id = i.catalogo_id AND i.cuenta_id = :id
        ORDER BY c.rango ASC, c.es_premium ASC
    ");
    $stmt_inv->execute([':id' => $lider_id]);
    $catalogo_hangar = $stmt_inv->fetchAll(PDO::FETCH_ASSOC);

    // 3. Sistema Global de Naciones
    $naciones_totales = $pdo->query("SELECT nombre FROM naciones ORDER BY nombre ASC")->fetchAll(PDO::FETCH_COLUMN);

    // 4. Inteligencia Naval
    $stmt_f = $pdo->prepare("SELECT * FROM flotas WHERE cuenta_id = :id ORDER BY slot ASC");
    $stmt_f->execute([':id' => $lider_id]);
    $flotas_db = $stmt_f->fetchAll(PDO::FETCH_ASSOC);
    $flotas = [1 => null, 2 => null, 3 => null];
    foreach ($flotas_db as $f) { $flotas[$f['slot']] = $f; }

} catch (PDOException $e) { die("Error de enlace: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Hangar Operativo - <?php echo htmlspecialchars($user['nombre_equipo']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .unowned { filter: grayscale(1) brightness(0.2); opacity: 0.5; } 
        .premium-card { border-color: rgba(234, 179, 8, 0.4); background: linear-gradient(135deg, #1e1b1e 0%, #111827 100%); }
        .rank-label { writing-mode: vertical-lr; transform: rotate(180deg); }
        .modal-active { overflow: hidden; }
    </style>
</head>
<body class="bg-[#0b1120] text-gray-400 min-h-screen pb-20" onload="initInventario()">

    <?php include '../includes/nav_lider.php'; ?>

    <nav class="bg-[#111827] border-b border-gray-800 sticky top-0 z-40">
        <div class="flex px-4 overflow-x-auto">
            <?php foreach ($naciones_totales as $n): ?>
                <button onclick="setNacionHangar('<?php echo $n; ?>')" data-nav-nacion="<?php echo $n; ?>"
                        class="px-6 py-4 text-[9px] font-black uppercase tracking-[0.2em] border-b-2 border-transparent transition-all">
                    <?php echo $n; ?>
                </button>
            <?php endforeach; ?>
        </div>
    </nav>

    <main class="p-8 max-w-[1600px] mx-auto">
        <div class="flex gap-4 mb-10 border-b border-gray-800 pb-6">
            <button id="btn_cat_tanque" onclick="setCategoria('tanque')" class="px-8 py-2 bg-blue-600 text-white text-[10px] font-black rounded uppercase italic tracking-widest shadow-lg">TANQUES</button>
            <button id="btn_cat_avion" onclick="setCategoria('avion')" class="px-8 py-2 bg-gray-900 text-gray-500 text-[10px] font-black border border-gray-700 rounded uppercase italic tracking-widest">AVIONES</button>
            <button id="btn_cat_flota" onclick="setCategoria('flota')" class="px-8 py-2 bg-gray-900 text-gray-500 text-[10px] font-black border border-gray-700 rounded uppercase italic tracking-widest">FLOTAS</button>
        </div>

        <div id="vista_tree" class="flex flex-col lg:flex-row gap-10">
            <div class="flex-1">
                <h2 class="text-[9px] font-black uppercase tracking-[0.4em] text-gray-700 mb-8">Unidades en Reserva</h2>
                <div id="arbol_investigacion" class="space-y-12"></div>
            </div>
            <div class="w-full lg:w-80">
                <h2 class="text-[9px] font-black uppercase tracking-[0.4em] text-yellow-900/40 mb-8 text-center">Activos Premium</h2>
                <div id="arbol_premium" class="grid grid-cols-1 gap-4"></div>
            </div>
        </div>

        <div id="vista_flotas" class="hidden bg-[#111827] rounded border border-gray-800 shadow-2xl overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-900 text-[9px] text-gray-500 uppercase tracking-widest border-b border-gray-800">
                        <th class="p-5 font-black">Slot</th>
                        <th class="p-5 font-black">Buque Insignia</th>
                        <th class="p-5 font-black">Escolta 01</th>
                        <th class="p-5 font-black">Escolta 02</th>
                        <th class="p-5 font-black">Escolta 03</th>
                        <th class="p-5 font-black">Escolta 04</th>
                        <th class="p-5 font-black text-right">Estado</th>
                    </tr>
                </thead>
                <tbody class="text-xs">
                    <?php for ($i = 1; $i <= 3; $i++): $f = $flotas[$i]; ?>
                        <tr class="border-b border-gray-800/50 hover:bg-white/5 transition group cursor-pointer" onclick="abrirModalFlota(<?php echo $i; ?>, <?php echo htmlspecialchars(json_encode($f)); ?>)">
                            <td class="p-5 font-black text-blue-500">0<?php echo $i; ?></td>
                            <td class="p-5">
                                <span class="<?php echo $f ? 'text-white font-bold' : 'text-gray-700 italic'; ?>">
                                    <?php echo $f ? htmlspecialchars($f['insignia']) : 'VACÍO'; ?>
                                </span>
                            </td>
                            <?php for($j=1; $j<=4; $j++): ?>
                                <td class="p-5 text-[10px] text-gray-500 uppercase">
                                    <?php echo $f ? htmlspecialchars($f["escolta_$j"] ?: '-') : '-'; ?>
                                </td>
                            <?php endfor; ?>
                            <td class="p-5 text-right">
                                <?php if($f): ?>
                                    <span class="text-[8px] bg-blue-900/30 text-blue-400 px-2 py-1 rounded font-black tracking-tighter">OPERATIVO</span>
                                <?php else: ?>
                                    <span class="text-[8px] bg-gray-800 text-gray-600 px-2 py-1 rounded font-black tracking-tighter">STANDBY</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div id="modalFlota" class="hidden fixed inset-0 bg-black/95 z-[100] flex items-center justify-center p-4">
        <div class="bg-gray-800 border border-blue-500/20 w-full max-w-sm rounded shadow-2xl overflow-hidden">
            <div class="p-3 bg-gray-900 border-b border-gray-700 flex justify-between items-center">
                <h3 class="font-black text-white text-[9px] uppercase tracking-widest italic" id="tituloModal"></h3>
                <button onclick="cerrarModalFlota()" class="text-gray-500 hover:text-white text-xl">&times;</button>
            </div>
            <form action="../logic/gestionar_flota.php" method="POST" class="p-6 space-y-4">
                <input type="hidden" id="modal_slot" name="slot" value="">
                <div>
                    <label class="text-[8px] text-gray-500 uppercase font-black block mb-1">Unidad Insignia</label>
                    <input type="text" id="modal_insignia" name="insignia" required class="w-full bg-black border border-gray-700 rounded p-2 text-xs text-white outline-none focus:border-blue-500">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <?php for($j=1; $j<=4; $j++): ?>
                        <input type="text" id="modal_escolta_<?php echo $j; ?>" name="escolta_<?php echo $j; ?>" placeholder="Unidad 0<?php echo $j; ?>" class="bg-black/50 border border-gray-800 rounded p-2 text-[10px] text-gray-400 outline-none">
                    <?php endfor; ?>
                </div>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-black py-3 rounded text-[9px] uppercase tracking-[0.3em] transition">REGISTRAR DESPLIEGUE</button>
            </form>
        </div>
    </div>

    

    <script>
        const invData = <?php echo json_encode($catalogo_hangar); ?>;
        const nacionesMando = <?php echo json_encode($naciones_mando); ?>;
        let nacionActual = '<?php echo $naciones_totales[0]; ?>';
        let catActual = 'tanque';

        function initInventario() { renderHangar(); }

        function setCategoria(cat) {
            catActual = cat;
            ['tanque', 'avion', 'flota'].forEach(c => {
                document.getElementById('btn_cat_' + c).className = (c === cat) ? "px-8 py-2 bg-blue-600 text-white text-[10px] font-black rounded uppercase italic tracking-widest shadow-lg" : "px-8 py-2 bg-gray-900 text-gray-500 text-[10px] font-black border border-gray-700 rounded uppercase italic tracking-widest";
            });

            if (cat === 'flota') {
                document.getElementById('vista_tree').classList.add('hidden');
                document.getElementById('vista_flotas').classList.remove('hidden');
            } else {
                document.getElementById('vista_tree').classList.remove('hidden');
                document.getElementById('vista_flotas').classList.add('hidden');
                renderHangar();
            }
        }

        function setNacionHangar(n) { nacionActual = n; renderHangar(); }

        function renderHangar() {
            const invCont = document.getElementById('arbol_investigacion');
            const premCont = document.getElementById('arbol_premium');
            invCont.innerHTML = ''; premCont.innerHTML = '';

            document.querySelectorAll('[data-nav-nacion]').forEach(btn => {
                const n = btn.getAttribute('data-nav-nacion');
                btn.className = (n === nacionActual) ? "px-6 py-4 text-[9px] font-black text-white border-b-2 border-blue-500 uppercase tracking-[0.2em]" : "px-6 py-4 text-[9px] font-black text-gray-600 border-b-2 border-transparent uppercase tracking-[0.2em] opacity-40";
            });

            const items = invData.filter(i => i.nacion === nacionActual && i.tipo === catActual);

            for(let r=1; r<=8; r++) {
                const rankItems = items.filter(i => parseInt(i.rango) === r && !parseInt(i.es_premium));
                if(rankItems.length > 0) {
                    const row = document.createElement('div');
                    row.className = "flex items-center gap-6";
                    row.innerHTML = `<div class="rank-label text-gray-800 font-black text-[9px] uppercase tracking-[0.8em] border-r border-gray-800/30 pr-4">RANK ${r}</div>
                                     <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 flex-1">${rankItems.map(i => cardInv(i)).join('')}</div>`;
                    invCont.appendChild(row);
                }
            }
            const premItems = items.filter(i => parseInt(i.es_premium));
            premCont.innerHTML = premItems.map(i => cardInv(i, true)).join('');
        }

        function cardInv(i, isPremium = false) {
            const owned = parseInt(i.stock_actual) > 0;
            const darkClass = owned ? '' : 'unowned'; 
            const border = isPremium ? 'premium-card' : 'bg-[#111827] border-gray-800';
            
            return `<div class="relative rounded border ${border} overflow-hidden transition-all duration-500 ${darkClass}">
                        <div class="h-20 bg-black relative">
                            ${i.imagen_url ? `<img src="../${i.imagen_url}" class="w-full h-full object-cover">` : ''}
                            <div class="absolute top-1 right-1 bg-black/80 text-[8px] text-white px-1.5 font-black rounded border border-white/5">T-${i.rango}</div>
                        </div>
                        <div class="p-3">
                            <div class="text-[7px] text-blue-500 font-black uppercase mb-1 truncate">${i.subtipo}</div>
                            <div class="text-[9px] text-white font-bold truncate mb-2">${i.nombre_vehiculo}</div>
                            <div class="flex justify-between items-center border-t border-gray-800 pt-2">
                                <span class="text-[8px] font-black text-gray-700 uppercase italic">Stock</span>
                                <span class="${owned ? 'text-blue-400' : 'text-gray-900'} font-black text-xs">${i.stock_actual}</span>
                            </div>
                        </div>
                    </div>`;
        }

        function abrirModalFlota(slot, datos = null) {
            document.getElementById('modal_slot').value = slot;
            document.getElementById('tituloModal').innerText = "GRUPO DE TAREA 0" + slot;
            document.getElementById('modal_insignia').value = datos ? datos.insignia : '';
            for(let j=1; j<=4; j++) { document.getElementById('modal_escolta_' + j).value = datos ? datos['escolta_' + j] : ''; }
            document.getElementById('modalFlota').classList.remove('hidden');
            document.body.classList.add('modal-active');
        }

        function cerrarModalFlota() {
            document.getElementById('modalFlota').classList.add('hidden');
            document.body.classList.remove('modal-active');
        }
    </script>
</body>
</html>