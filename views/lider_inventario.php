<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'lider') {
    header("Location: ../login.php");
    exit();
}
require_once '../config/conexion.php';
$root_path = "../";
$txt = require '../config/textos.php';

$lider_id = $_SESSION['usuario_id'];

try {
    $stmt_u = $pdo->prepare("SELECT nombre_equipo, naciones_activas FROM cuentas WHERE id = :id");
    $stmt_u->execute([':id' => $lider_id]);
    $user = $stmt_u->fetch(PDO::FETCH_ASSOC);

    $naciones_mando = !empty($user['naciones_activas']) ? array_map('trim', explode(',', $user['naciones_activas'])) : [];

    $stmt_inv = $pdo->prepare("
        SELECT c.*, IFNULL(i.cantidad, 0) as stock_actual 
        FROM catalogo_tienda c
        LEFT JOIN inventario i ON c.id = i.catalogo_id AND i.cuenta_id = :id
        ORDER BY c.rango ASC, c.es_premium ASC
    ");
    $stmt_inv->execute([':id' => $lider_id]);
    $catalogo_hangar = $stmt_inv->fetchAll(PDO::FETCH_ASSOC);

    $naciones_totales = $pdo->query("SELECT nombre FROM naciones ORDER BY nombre ASC")->fetchAll(PDO::FETCH_COLUMN);

    $stmt_f = $pdo->prepare("SELECT * FROM flotas WHERE cuenta_id = :id ORDER BY slot ASC");
    $stmt_f->execute([':id' => $lider_id]);
    $flotas_db = $stmt_f->fetchAll(PDO::FETCH_ASSOC);
    $flotas = [1 => null, 2 => null, 3 => null];
    foreach ($flotas_db as $f) { $flotas[$f['slot']] = $f; }

} catch (PDOException $e) { die("Error de enlace táctico: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title><?php echo $txt['LIDER_INVENTARIO']['TITULO']; ?> - <?php echo htmlspecialchars($user['nombre_equipo']); ?></title>
    <?php include '../includes/head.php'; ?>
    <style>
        .modal-active { overflow: hidden; }
        .btn-nacion-hangar { transition: all 0.2s; cursor: pointer; }
        .btn-nacion-hangar.active { background-color: var(--dark-olive) !important; border-color: var(--ao-gold) !important; color: var(--aoe-gold) !important; }

        /* --- NIEBLA DE GUERRA TÁCTICA --- */
        .sector-locked {
            filter: grayscale(1) brightness(0.2) !important;
            pointer-events: none !important;
            user-select: none;
        }

        .m-panel.locked-overlay::after {
            content: "ZONA SIN JURISDICCIÓN";
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%) rotate(-10deg);
            font-family: 'Cinzel', serif;
            color: rgba(255, 204, 0, 0.15);
            font-size: 3rem;
            font-weight: 900;
            white-space: nowrap;
            z-index: 50;
            border: 2px solid rgba(255, 204, 0, 0.1);
            padding: 1rem 3rem;
            pointer-events: none;
        }
    </style>
</head>
<body class="bg-[#0d0e0a] text-[var(--text-main)] min-h-screen pb-20" onload="initInventario()">

    <?php include '../includes/nav_lider.php'; ?>

    <nav class="bg-[#1a1c11] border-b border-[var(--wood-border)] sticky top-0 z-40 overflow-x-auto shadow-xl">
        <div class="flex px-4 py-2 items-center gap-4">
            <span class="text-[var(--aoe-gold)] text-[10px] font-black uppercase tracking-widest pl-4">INTELIGENCIA TERRITORIAL:</span>
            <div class="flex gap-2">
                <?php foreach ($naciones_totales as $n): 
                    $bajo_mando = in_array($n, $naciones_mando); ?>
                    <button onclick="setNacionHangar('<?php echo $n; ?>')" 
                            data-nav-nacion="<?php echo $n; ?>"
                            class="btn-nacion-hangar px-4 py-1 text-[10px] font-black uppercase tracking-widest border border-[var(--wood-border)] 
                            <?php echo $bajo_mando ? 'bg-black/40 text-[var(--parchment)]' : 'bg-black/10 text-gray-700 opacity-60'; ?>">
                        <?php echo $n; ?> <?php echo $bajo_mando ? '' : '🔒'; ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </nav>

    <main class="p-8 max-w-[1600px] mx-auto mt-6">
        <div class="flex gap-4 mb-6 border-b border-[var(--wood-border)] pb-6">
            <button id="btn_cat_tanque" onclick="setCategoria('tanque')" class="btn-m !text-[10px]"><?php echo $txt['LIDER_INVENTARIO']['CAT_TANQUES']; ?></button>
            <button id="btn_cat_avion" onclick="setCategoria('avion')" class="btn-m !text-[10px] grayscale opacity-70"><?php echo $txt['LIDER_INVENTARIO']['CAT_AVIONES']; ?></button>
            <button id="btn_cat_flota" onclick="setCategoria('flota')" class="btn-m !text-[10px] grayscale opacity-70"><?php echo $txt['LIDER_INVENTARIO']['CAT_FLOTAS']; ?></button>
        </div>

        <div id="vista_tree" class="m-panel !p-0 overflow-hidden shadow-2xl relative">
            <table class="w-full text-left border-collapse table-m">
                <thead>
                    <tr class="text-[9px] uppercase tracking-widest bg-black/40 text-[var(--aoe-gold)]">
                        <th class="p-4 text-center">RANK</th>
                        <th class="p-4">IDENTIFICACIÓN DEL ACTIVO</th>
                        <th class="p-4 text-center">EXISTENCIAS (STOCK)</th>
                        <th class="p-4 text-right">ESTADO OPERATIVO</th>
                    </tr>
                </thead>
                <tbody id="cuerpo_hangar">
                    <?php foreach ($catalogo_hangar as $item): ?>
                        <tr class="fila-hangar transition hover:bg-white/5 border-b border-[var(--wood-border)]/10" 
                            data-tipo="<?php echo $item['tipo']; ?>" 
                            data-nacion="<?php echo htmlspecialchars($item['nacion']); ?>">
                            
                            <td class="p-4 text-center font-black font-['Cinzel'] text-sm">T-<?php echo $item['rango']; ?></td>
                            
                            <td class="p-4 flex items-center gap-3">
                                <div class="w-12 h-12 bg-black border border-[var(--wood-border)] <?php echo $item['stock_actual'] > 0 ? '' : 'grayscale opacity-30'; ?>">
                                    <?php if($item['imagen_url']): ?><img src="../<?php echo $item['imagen_url']; ?>" class="w-full h-full object-cover"><?php endif; ?>
                                </div>
                                <div class="flex flex-col">
                                    <span class="font-bold <?php echo $item['stock_actual'] > 0 ? 'text-white' : 'text-gray-600'; ?> uppercase tracking-wide text-xs">
                                        <?php echo htmlspecialchars($item['nombre_vehiculo']); ?>
                                    </span>
                                    <span class="text-[7px] text-[var(--parchment)] opacity-50 uppercase"><?php echo htmlspecialchars($item['subtipo']); ?></span>
                                </div>
                            </td>

                            <td class="p-4 text-center">
                                <span class="font-black text-xl <?php echo $item['stock_actual'] > 0 ? 'text-[var(--aoe-gold)]' : 'text-gray-800'; ?>">
                                    <?php echo $item['stock_actual']; ?>
                                </span>
                            </td>

                            <td class="p-4 text-right">
                                <?php if($item['stock_actual'] > 0): ?>
                                    <span class="text-[8px] bg-[var(--olive-drab)] text-[var(--aoe-gold)] px-2 py-1 border border-[var(--aoe-gold)] font-black tracking-widest">EN RESERVA</span>
                                <?php else: ?>
                                    <span class="text-[8px] text-gray-700 font-bold">SIN ACTIVOS</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="vista_flotas" class="hidden m-panel !p-0 overflow-hidden">
            <table class="w-full text-left border-collapse table-m">
                <thead>
                    <tr class="text-[9px] uppercase tracking-widest text-[var(--aoe-gold)] bg-black/40">
                        <th class="p-5 w-20 text-center border-r border-[var(--wood-border)]/50"><?php echo $txt['LIDER_INVENTARIO']['TH_SLOT']; ?></th>
                        <th class="p-5 border-r border-[var(--wood-border)]/50"><?php echo $txt['LIDER_INVENTARIO']['TH_INSIGNIA']; ?></th>
                        <th class="p-5"><?php echo $txt['LIDER_INVENTARIO']['TH_ESCOLTA']; ?> 01</th>
                        <th class="p-5"><?php echo $txt['LIDER_INVENTARIO']['TH_ESCOLTA']; ?> 02</th>
                        <th class="p-5"><?php echo $txt['LIDER_INVENTARIO']['TH_ESCOLTA']; ?> 03</th>
                        <th class="p-5"><?php echo $txt['LIDER_INVENTARIO']['TH_ESCOLTA']; ?> 04</th>
                        <th class="p-5 text-right"><?php echo $txt['LIDER_INVENTARIO']['TH_ESTADO']; ?></th>
                    </tr>
                </thead>
                <tbody class="text-xs text-[var(--text-main)] font-bold">
                    <?php for ($i = 1; $i <= 3; $i++): $f = $flotas[$i]; ?>
                        <tr class="transition hover:bg-white/5 cursor-pointer" onclick="abrirModalFlota(<?php echo $i; ?>, <?php echo htmlspecialchars(json_encode($f)); ?>)">
                            <td class="p-5 text-center font-black text-[var(--parchment)] border-r border-[var(--wood-border)]/30 font-['Cinzel']">0<?php echo $i; ?></td>
                            <td class="p-5 border-r border-[var(--wood-border)]/30">
                                <span class="<?php echo $f ? 'text-[var(--aoe-gold)] tracking-wide' : 'text-gray-600 italic'; ?>">
                                    <?php echo $f ? htmlspecialchars($f['insignia']) : $txt['LIDER_INVENTARIO']['VACIO']; ?>
                                </span>
                            </td>
                            <?php for($j=1; $j<=4; $j++): ?>
                                <td class="p-5 text-[10px] text-gray-500 uppercase"><?php echo $f ? htmlspecialchars($f["escolta_$j"] ?: '-') : '-'; ?></td>
                            <?php endfor; ?>
                            <td class="p-5 text-right">
                                <?php if($f): ?>
                                    <span class="text-[8px] bg-[var(--olive-drab)] text-[var(--aoe-gold)] px-2 py-1 border border-[var(--aoe-gold)] font-black tracking-widest shadow-inner"><?php echo $txt['LIDER_INVENTARIO']['ESTADO_OPERATIVO']; ?></span>
                                <?php else: ?>
                                    <span class="text-[8px] bg-black/50 text-gray-500 px-2 py-1 border border-[var(--wood-border)] font-black tracking-widest"><?php echo $txt['LIDER_INVENTARIO']['ESTADO_STANDBY']; ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div id="modalFlota" class="hidden fixed inset-0 bg-black/90 z-[100] flex items-center justify-center p-4">
        <div class="m-panel border-[var(--aoe-gold)] w-full max-w-md relative">
            <button onclick="cerrarModalFlota()" class="absolute top-4 right-4 text-[var(--parchment)] hover:text-white font-bold text-xl">&times;</button>
            <h3 class="m-title text-xl mb-6 border-b border-[var(--wood-border)] pb-2" id="tituloModal"></h3>
            <form action="../logic/gestionar_flota.php" method="POST" class="space-y-5">
                <input type="hidden" id="modal_slot" name="slot" value="">
                <div class="bg-black/40 p-4 border border-[var(--wood-border)] shadow-inner">
                    <label class="block text-[10px] text-[var(--aoe-gold)] uppercase font-black mb-2 tracking-widest text-center"><?php echo $txt['LIDER_INVENTARIO']['LBL_INSIGNIA']; ?></label>
                    <input type="text" id="modal_insignia" name="insignia" required placeholder="<?php echo $txt['LIDER_INVENTARIO']['PH_UNIDAD']; ?>" class="m-input w-full text-center text-lg outline-none">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <?php for($j=1; $j<=4; $j++): ?>
                        <div>
                            <label class="block text-[9px] text-[var(--parchment)] uppercase font-bold mb-1 tracking-widest text-center"><?php echo $txt['LIDER_INVENTARIO']['TH_ESCOLTA']; ?> 0<?php echo $j; ?></label>
                            <input type="text" id="modal_escolta_<?php echo $j; ?>" name="escolta_<?php echo $j; ?>" placeholder="<?php echo $txt['LIDER_INVENTARIO']['PH_UNIDAD']; ?>" class="m-input w-full text-center text-xs outline-none">
                        </div>
                    <?php endfor; ?>
                </div>
                <button type="submit" class="btn-m w-full py-4 mt-2 text-xs tracking-widest"><?php echo $txt['LIDER_INVENTARIO']['BTN_DESPLIEGUE']; ?></button>
            </form>
        </div>
    </div>

    <script>
        const nacionesMando = <?php echo json_encode($naciones_mando); ?>;
        let nacionActual = '<?php echo $naciones_totales[0]; ?>';
        let catActual = 'tanque';

        function initInventario() { setNacionHangar(nacionActual); }

        function setCategoria(cat) {
            catActual = cat;
            ['tanque', 'avion', 'flota'].forEach(c => {
                document.getElementById('btn_cat_' + c).className = (c === cat) ? "btn-m !text-[10px]" : "btn-m !text-[10px] grayscale opacity-70";
            });

            if (cat === 'flota') {
                document.getElementById('vista_tree').classList.add('hidden');
                document.getElementById('vista_flotas').classList.remove('hidden');
            } else {
                document.getElementById('vista_tree').classList.remove('hidden');
                document.getElementById('vista_flotas').classList.add('hidden');
                aplicarFiltros();
            }
        }

        function setNacionHangar(n) {
            nacionActual = n;
            document.querySelectorAll('.btn-nacion-hangar').forEach(b => {
                b.classList.toggle('active', b.getAttribute('data-nav-nacion') === n);
            });
            aplicarFiltros();
        }

        function aplicarFiltros() {
            if (catActual === 'flota') return;

            const cuerpo = document.getElementById('cuerpo_hangar');
            const panel = document.getElementById('vista_tree');
            const esMio = nacionesMando.includes(nacionActual);

            // Efecto Niebla de Guerra
            if (!esMio) {
                cuerpo.classList.add('sector-locked');
                panel.classList.add('locked-overlay');
            } else {
                cuerpo.classList.remove('sector-locked');
                panel.classList.remove('locked-overlay');
            }

            document.querySelectorAll('.fila-hangar').forEach(f => {
                f.style.display = (f.dataset.tipo === catActual && f.dataset.nacion === nacionActual) ? '' : 'none';
            });
        }

        function abrirModalFlota(slot, datos = null) {
            document.getElementById('modal_slot').value = slot;
            document.getElementById('tituloModal').innerText = "<?php echo $txt['LIDER_INVENTARIO']['MODAL_TITULO']; ?>".replace('0', '0' + slot);
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