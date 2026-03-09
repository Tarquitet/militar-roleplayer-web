<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'lider') {
    header("Location: ../login.php");
    exit();
}
require_once '../config/conexion.php';
$root_path = "../";
$txt = require '../config/textos.php';

// --- NUEVO: Cargar los precios para el frontend ---
$precios_base = require '../config/precios.php';
$precios_json = json_encode($precios_base);

$lider_id = $_SESSION['usuario_id'];

try {
    // 1. DATOS DEL USUARIO
    $stmt_u = $pdo->prepare("SELECT dinero, acero, petroleo, naciones_activas FROM cuentas WHERE id = :id");
    $stmt_u->execute([':id' => $lider_id]);
    $user = $stmt_u->fetch(PDO::FETCH_ASSOC);
    
    $naciones_raw = explode(',', $user['naciones_activas'] ?? '');
    $naciones_mando = array_filter(array_map('trim', $naciones_raw));

    // 2. OBTENER STOCK ACTUAL
    $stmt_inv = $pdo->prepare("SELECT catalogo_id, cantidad FROM inventario WHERE cuenta_id = :id");
    $stmt_inv->execute([':id' => $lider_id]);
    $mi_stock = $stmt_inv->fetchAll(PDO::FETCH_KEY_PAIR);

    // 3. CATALOGO AGRUPADO
    $catalogo_agrupado = [];
    if (!empty($naciones_mando)) {
        $placeholders = str_repeat('?,', count($naciones_mando) - 1) . '?';
        $stmt_cat = $pdo->prepare("SELECT * FROM catalogo_tienda WHERE nacion IN ($placeholders) ORDER BY rango ASC, CAST(br AS DECIMAL(10,1)) ASC");
        $stmt_cat->execute(array_values($naciones_mando));
        $catalogo_raw = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

        foreach ($catalogo_raw as $item) {
            $n = $item['nacion'];
            $r = $item['rango'];
            $t = $item['tipo'];
            $c = !empty($item['subtipo']) ? $item['subtipo'] : (!empty($item['clase']) ? $item['clase'] : 'No Clasificado');
            $catalogo_agrupado[$n][$r][$t][$c][] = $item;
        }
    }

    $stmt_p = $pdo->prepare("SELECT catalogo_id FROM planos_desbloqueados WHERE cuenta_id = :id");
    $stmt_p->execute([':id' => $lider_id]);
    $mis_planos = $stmt_p->fetchAll(PDO::FETCH_COLUMN);

    // JERARQUÍA DE ORDENAMIENTO (ORDEN SOLICITADO)
    $orden_tanques = ['Ligero', 'Mediano', 'Pesado', 'Caza Tanques', 'AAA'];
    $orden_aviones = ['Caza', 'Interceptor', 'Avion de Ataque', 'Bombardero'];

} catch (PDOException $e) { die("Error: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title><?php echo $txt['LIDER_TIENDA']['TITULO']; ?></title>
    <?php include '../includes/head.php'; ?>
    <style>
        .modal-active { overflow: hidden; }
        .btn-nacion.active { background-color: var(--dark-olive) !important; color: var(--aoe-gold) !important; border-color: var(--aoe-gold) !important; }
        .img-locked { filter: grayscale(1) brightness(0.3) contrast(1.2); }
        
        /* ESTILO ACORDEÓN JACKY (NEGRO Y ROJO) */
        .tier-container { border: 1px solid #991b1b; background: #050505; margin-bottom: 1.5rem; overflow: hidden; }
        .tier-header { 
            background: url('https://www.transparenttextures.com/patterns/diagmonds-light.png'), #000; 
            border-bottom: 2px solid #ef4444; 
            cursor: pointer; 
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .tier-header h2 { 
            color: #ef4444; 
            font-weight: 900; 
            text-transform: uppercase; 
            letter-spacing: 4px; 
            margin: 0; 
            font-size: 1.3rem; 
            text-shadow: 2px 2px 0 #000;
        }
        
        /* TARJETAS E INFO TÉCNICA */
        .tag-br { position: absolute; top: 0; left: 0; background: #000; color: #fff; font-size: 10px; font-weight: 900; padding: 3px 8px; z-index: 10; border-right: 1px solid #333; border-bottom: 1px solid #333; font-family: monospace; }
        .tag-premium { position: absolute; top: 0; right: 0; background: #c5a059; color: #000; font-size: 9px; font-weight: 900; padding: 3px 8px; z-index: 10; text-transform: uppercase; }
        .card-premium { border-color: #c5a059 !important; box-shadow: inset 0 0 15px rgba(197, 160, 89, 0.15); }
        
        .stat-grid-label { font-size: 7px; color: #555; font-weight: 900; text-transform: uppercase; }
        .stat-grid-value { font-size: 10px; font-weight: 900; font-family: 'Space Mono', monospace; }

        .custom-scrollbar::-webkit-scrollbar { height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #991b1b; border-radius: 10px; }
    </style>
</head>
<body class="bg-[#0d0e0a] text-[var(--text-main)] min-h-screen pb-20" onload="initTienda()">
    <?php include '../includes/nav_lider.php'; ?>

    <nav class="bg-[#1a1c11] border-b border-[var(--wood-border)] sticky top-0 z-40 shadow-xl">
        <div class="flex px-4 py-2 items-center gap-4">
            <span class="text-[var(--aoe-gold)] text-[10px] font-black uppercase tracking-widest pl-4"><?php echo $txt['LIDER_TIENDA']['LBL_PRODUCCION']; ?></span>
            <div class="flex gap-2">
                <?php foreach ($naciones_mando as $n): ?>
                    <button onclick="setNacion('<?php echo htmlspecialchars($n); ?>')" data-nav-nacion="<?php echo htmlspecialchars($n); ?>" class="btn-nacion px-4 py-1 text-[10px] font-black uppercase border border-[var(--wood-border)] bg-black/40">
                        <?php echo htmlspecialchars($n); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </nav>

    <div class="m-panel !p-4 !border-t-0 !border-x-0 sticky top-12 z-30 bg-black/80 backdrop-blur-md border-b border-white/5">
        <div class="max-w-[1600px] w-full mx-auto flex flex-col md:flex-row justify-between items-center px-4 gap-4">
            <div class="flex gap-4">
                <button id="btn_tanque" onclick="setTipo('tanque')" class="btn-m !text-[10px]"><?php echo $txt['LIDER_TIENDA']['BTN_TANQUES']; ?></button>
                <button id="btn_avion" onclick="setTipo('avion')" class="btn-m !text-[10px] grayscale opacity-70"><?php echo $txt['LIDER_TIENDA']['BTN_AVIONES']; ?></button>
            </div>
            <div class="flex gap-10 font-black font-['Cinzel'] text-sm">
                <span class="text-green-500">$<?php echo number_format($user['dinero']); ?></span>
                <span class="text-white"><?php echo number_format($user['acero']); ?>T</span>
                <span class="text-yellow-500"><?php echo number_format($user['petroleo']); ?>L</span>
            </div>
        </div>
    </div>

    <main class="p-8 max-w-[1600px] mx-auto">

        <div class="flex flex-col md:flex-row justify-between items-center mb-6 border-b border-[var(--wood-border)]/50 pb-4 gap-4">
            <h2 class="text-[var(--aoe-gold)] font-black uppercase text-[12px] tracking-[0.3em] font-['Cinzel'] flex items-center gap-2">
                ⚙️ <?php echo $txt['LIDER_TIENDA']['TITULO']; ?>
            </h2>
            <button onclick="document.getElementById('modalRegistroLider').classList.remove('hidden'); document.body.classList.add('modal-active');" class="btn-m !bg-blue-900/30 !border-blue-700 !text-blue-400 px-6 py-3 text-[10px] font-black uppercase hover:bg-blue-700 hover:text-white transition">
                <?php echo $txt['LIDER_TIENDA']['BTN_REGISTRAR']; ?>
            </button>
        </div>

        <div id="cont_tienda" class="space-y-4">
            <?php foreach($catalogo_agrupado as $nacion => $tiers): ?>
                <div class="bloque-nacion" data-nacion="<?php echo htmlspecialchars($nacion); ?>">
                    <?php ksort($tiers); foreach($tiers as $tier => $tipos): ?>
                        <div class="tier-container shadow-2xl">
                            <div class="tier-header" onclick="toggleTier(this)">
                                <h2><?php echo $txt['LIDER_TIENDA']['LBL_TIER_RAN']; ?> <?php echo $tier; ?></h2>
                                <span class="text-red-500 font-bold">▼</span>
                            </div>

                            <div class="tier-content p-6 space-y-8 block">
                                <?php foreach(['tanque', 'avion'] as $tipo_v): if(!isset($tipos[$tipo_v])) continue; ?>
                                    <div class="seccion-tipo" data-tipo="<?php echo $tipo_v; ?>">
                                        <?php 
                                        $orden = ($tipo_v == 'tanque') ? $orden_tanques : $orden_aviones;
                                        foreach($orden as $clase_n): if(!isset($tipos[$tipo_v][$clase_n])) continue; 
                                        ?>
                                            <div class="clase-container mb-6">
                                                <h3 class="text-gray-600 font-black uppercase text-[9px] tracking-widest border-b border-white/5 pb-1 mb-4"><?php echo $clase_n; ?></h3>
                                                
                                                <div class="flex gap-4 overflow-x-auto pb-4 custom-scrollbar">
                                                    <?php foreach($tipos[$tipo_v][$clase_n] as $item): 
                                                        $tiene_plano = in_array($item['id'], $mis_planos);
                                                        $es_prem = ($item['is_premium'] == 1);
                                                        $stock_actual = $mi_stock[$item['id']] ?? 0;
                                                    ?>
                                                        <div class="fila-v flex-shrink-0 w-64 flex flex-col bg-[#111] border <?php echo $es_prem ? 'card-premium' : 'border-gray-800'; ?> relative hover:brightness-110 transition shadow-lg" id="v-<?php echo $item['id']; ?>">
                                                            
                                                            <div class="tag-br">BR: <?php echo htmlspecialchars($item['br']); ?></div>
                                                            <?php if($es_prem): ?><div class="tag-premium">PREMIUM</div><?php endif; ?>

                                                            <div class="h-32 bg-black relative border-b border-gray-800 overflow-hidden">
                                                                <img src="../<?php echo $item['imagen_url']; ?>" class="w-full h-full object-cover <?php echo !$tiene_plano ? 'img-locked' : ''; ?>">
                                                                <div class="absolute bottom-0 right-0 bg-black/80 px-2 py-0.5 text-[8px] text-gray-500 font-bold border-t border-l border-gray-800 uppercase">TIER <?php echo $item['rango']; ?></div>
                                                            </div>

                                                            <div class="p-3 flex-grow flex flex-col">
                                                                <h3 class="text-[11px] font-black text-white uppercase text-center truncate mb-2"><?php echo htmlspecialchars($item['nombre_vehiculo']); ?></h3>
                                                                
                                                                <div class="flex justify-center gap-1 mb-3">
                                                                    <span class="text-[7px] bg-blue-900/30 text-blue-400 border border-blue-900/50 px-1.5 py-0.5 rounded font-black uppercase"><?php echo htmlspecialchars($item['tipo']); ?></span>
                                                                    <span class="text-[7px] bg-gray-800 text-gray-400 px-1.5 py-0.5 rounded font-black uppercase"><?php echo $clase_n; ?></span>
                                                                </div>

                                                                <div class="grid grid-cols-3 gap-0 bg-black border border-gray-800 p-1.5 text-center rounded mb-3">
                                                                    <div class="border-r border-gray-800">
                                                                        <span class="stat-grid-label block">CASH</span>
                                                                        <span class="stat-grid-value text-green-500">$<?php echo number_format($item['costo_dinero']); ?></span>
                                                                    </div>
                                                                    <div class="border-r border-gray-800">
                                                                        <span class="stat-grid-label block">STEEL</span>
                                                                        <span class="stat-grid-value text-white"><?php echo number_format($item['costo_acero']); ?>T</span>
                                                                    </div>
                                                                    <div>
                                                                        <span class="stat-grid-label block">FUEL</span>
                                                                        <span class="stat-grid-value text-yellow-500"><?php echo number_format($item['costo_petroleo']); ?>L</span>
                                                                    </div>
                                                                </div>

                                                                <div class="mt-auto flex justify-between items-center pt-1 px-1">
                                                                    <span class="text-[8px] text-gray-500 font-bold uppercase"><?php echo $txt['LIDER_TIENDA']['LBL_EN_HANGAR']; ?></span>
                                                                    <span class="text-xs font-black <?php echo $stock_actual > 0 ? 'text-[var(--aoe-gold)]' : 'text-gray-700'; ?>"><?php echo $stock_actual; ?>x</span>
                                                                </div>
                                                            </div>

                                                            <div class="p-2 bg-black/60 border-t border-gray-800">
                                                                <?php if($tiene_plano): ?>
                                                                    <div class="flex gap-1">
                                                                        <input type="number" id="qty-<?php echo $item['id']; ?>" value="1" min="1" class="m-input w-14 !p-1 text-center text-xs font-black">
                                                                        <button onclick="prepararCompraVehiculo(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['nombre_vehiculo']); ?>', <?php echo $item['costo_dinero']; ?>, <?php echo $item['costo_acero']; ?>, <?php echo $item['costo_petroleo']; ?>)" 
                                                                                class="btn-m flex-1 !py-2 !text-[9px] uppercase font-black"><?php echo $txt['LIDER_TIENDA']['BTN_ADQUIRIR']; ?></button>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <button onclick="prepararCompraPlano(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['nombre_vehiculo']); ?>', <?php echo $item['costo_dinero']; ?>)" 
                                                                            class="btn-m w-full !py-2 !text-[9px] !bg-blue-900/40 !text-blue-300 border-blue-600 font-black uppercase"><?php echo $txt['LIDER_TIENDA']['BTN_COMPRAR_PLANO']; ?></button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="mensaje_vacio" class="m-panel p-20 text-center text-gray-500 font-bold uppercase hidden">
            <?php echo $txt['STAFF_TIENDA']['SIN_VEHICULOS']; ?>
        </div>
    </main>

    <div id="modalConfirm" class="hidden fixed inset-0 bg-black/95 z-[200] flex items-center justify-center p-4">
        <div class="m-panel w-full max-w-sm relative border-[var(--aoe-gold)] border-2 shadow-[0_0_50px_rgba(255,204,0,0.1)]">
            <h3 class="text-[var(--aoe-gold)] font-black text-xs mb-4 tracking-[0.3em] uppercase text-center border-b border-[var(--wood-border)] pb-2"><?php echo $txt['LIDER_TIENDA']['MODAL_CONFIRM_TIT']; ?></h3>
            <div id="confirmText" class="text-[11px] text-[var(--parchment)] text-center uppercase tracking-widest mb-8 leading-relaxed"></div>
            <form id="formConfirm" method="POST" class="flex flex-col gap-3">
                <input type="hidden" name="catalogo_id" id="modal_cat_id">
                <input type="hidden" name="cantidad" id="modal_qty">
                <button type="submit" class="btn-m w-full py-4 text-[10px] font-black uppercase bg-[var(--dark-olive)]"><?php echo $txt['LIDER_TIENDA']['BTN_CONFIRM_DESP']; ?></button>
                <button type="button" onclick="cerrarModal()" class="text-gray-500 text-[9px] font-black uppercase hover:text-white transition"><?php echo $txt['LIDER_TIENDA']['BTN_CANCELAR']; ?></button>
            </form>
        </div>
    </div>

    <div id="modalRegistroLider" class="hidden fixed inset-0 bg-black/95 z-[200] flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="m-panel border-[var(--aoe-gold)] w-full max-w-xl relative shadow-2xl">
            <button type="button" onclick="document.getElementById('modalRegistroLider').classList.add('hidden'); document.body.classList.remove('modal-active');" class="absolute top-4 right-4 text-gray-500 hover:text-white text-2xl">&times;</button>
            <h3 class="m-title text-xl mb-6 border-b border-[var(--wood-border)] pb-2 text-[var(--aoe-gold)]"><?php echo $txt['LIDER_TIENDA']['MODAL_ADD_TIT']; ?></h3>
            
            <form action="../logic/procesar_tienda_lider.php" method="POST" enctype="multipart/form-data" class="space-y-4" onsubmit="return confirm('<?php echo $txt['JS_ALERTAS']['CONFIRMAR_ALTA_LIDER']; ?>');">
                <input type="hidden" name="accion" value="agregar">
                
                <div>
                    <label class="block text-[10px] text-gray-500 uppercase font-black mb-1"><?php echo $txt['LIDER_TIENDA']['LBL_NOM_VEH']; ?></label>
                    <input type="text" name="nombre_vehiculo" required class="m-input w-full text-lg text-white">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] text-gray-500 uppercase font-black mb-1"><?php echo $txt['LIDER_TIENDA']['LBL_NAC_ASIG']; ?></label>
                        <select name="nacion" required class="m-input w-full font-black uppercase text-[var(--aoe-gold)]">
                            <?php foreach($naciones_mando as $nm): ?><option value="<?php echo htmlspecialchars($nm); ?>"><?php echo htmlspecialchars($nm); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] text-gray-500 uppercase font-black mb-1"><?php echo $txt['LIDER_TIENDA']['LBL_CLAS_PRIM']; ?></label>
                        <select name="tipo" id="lider_tipo_v" onchange="actualizarSubtiposLider()" class="m-input w-full uppercase">
                            <option value="tanque"><?php echo $txt['LIDER_TIENDA']['OPT_TANQUE']; ?></option>
                            <option value="avion"><?php echo $txt['LIDER_TIENDA']['OPT_AVION']; ?></option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] text-gray-500 uppercase font-black mb-1"><?php echo $txt['LIDER_TIENDA']['LBL_SUB_CLA']; ?></label>
                        <select name="subtipo" id="lider_subtipo_v" onchange="actualizarPrecioLiderPreview()" class="m-input w-full uppercase"></select>
                    </div>
                    <div>
                        <label class="block text-[10px] text-gray-500 uppercase font-black mb-1"><?php echo $txt['LIDER_TIENDA']['LBL_TIER_RAN']; ?></label>
                        <select name="rango" id="lider_rango_v" onchange="actualizarPrecioLiderPreview()" class="m-input w-full text-center font-black">
                            <?php for($i=1; $i<=8; $i++) echo "<option value='$i'>$i</option>"; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] text-gray-500 uppercase font-black mb-1"><?php echo $txt['LIDER_TIENDA']['LBL_BR']; ?></label>
                        <input type="text" name="br" required placeholder="Ej: 3.3" class="m-input w-full text-center font-mono text-white">
                    </div>
                    <div class="flex items-end">
                        <div class="bg-black p-3 border border-[var(--wood-border)] flex items-center justify-center gap-2 w-full h-[42px]">
                            <input type="checkbox" name="is_premium" value="1" class="w-4 h-4 accent-[#c5a059]">
                            <span class="text-[10px] font-black text-[#c5a059] uppercase"><?php echo $txt['LIDER_TIENDA']['LBL_PREMIUM']; ?></span>
                        </div>
                    </div>
                </div>

                <div class="border border-[var(--wood-border)] bg-[#0a0a0a] p-4 mt-6 text-center shadow-inner">
                    <span class="block text-[9px] text-[var(--parchment)] font-bold uppercase mb-3 tracking-[0.2em] border-b border-white/5 pb-2"><?php echo $txt['LIDER_TIENDA']['TIT_COSTO_PROD']; ?></span>
                    <div class="grid grid-cols-3 gap-2">
                        <div class="bg-black p-2 border border-green-900/30">
                            <span class="block text-[8px] text-green-700 font-black mb-1">CASH</span>
                            <span id="lider_prev_cash" class="text-xs text-green-500 font-mono font-black">...</span>
                        </div>
                        <div class="bg-black p-2 border border-gray-700/30">
                            <span class="block text-[8px] text-gray-500 font-black mb-1">STEEL</span>
                            <span id="lider_prev_steel" class="text-xs text-white font-mono font-black">...</span>
                        </div>
                        <div class="bg-black p-2 border border-yellow-900/30">
                            <span class="block text-[8px] text-yellow-700 font-black mb-1">FUEL</span>
                            <span id="lider_prev_fuel" class="text-xs text-yellow-500 font-mono font-black">...</span>
                        </div>
                    </div>
                    <p class="text-[7px] text-gray-500 mt-2 uppercase italic"><?php echo $txt['LIDER_TIENDA']['TXT_INFO_COSTO']; ?></p>
                </div>

                <div class="border border-[var(--wood-border)] bg-[#0a0a0a] p-4 text-center mt-4">
                    <label class="block text-[10px] text-[var(--aoe-gold)] uppercase font-black mb-2"><?php echo $txt['LIDER_TIENDA']['LBL_IMG_CLAS']; ?></label>
                    <input type="file" name="imagen" required accept="image/*" class="w-full text-[10px] text-gray-400 p-2 bg-black border border-white/10">
                </div>
                
                <button type="submit" class="btn-m w-full py-4 mt-4 text-[11px] tracking-[0.2em]"><?php echo $txt['LIDER_TIENDA']['BTN_ENVIAR_PROD']; ?></button>
            </form>
        </div>
    </div>

    <script>
        const urlParams = new URLSearchParams(window.location.search);
        let nacActual = urlParams.get('nacion') || '<?php echo $naciones_mando[0] ?? ""; ?>';
        let tipoActual = urlParams.get('tipo') || 'tanque';
        const preciosBase = <?php echo $precios_json; ?>;

        function initTienda() { 
            if(nacActual) setNacion(nacActual);
            if(tipoActual) setTipo(tipoActual);
        }

        function toggleTier(el) {
            const content = el.nextElementSibling;
            const arrow = el.querySelector('span');
            content.style.display = (content.style.display === 'none') ? 'block' : 'none';
            arrow.innerText = (content.style.display === 'none') ? '▼' : '▲';
        }

        function setNacion(n) {
            nacActual = n;
            document.querySelectorAll('.btn-nacion').forEach(b => b.classList.toggle('active', b.dataset.navNacion === n));
            aplicarFiltros();
        }

        function setTipo(t) {
            tipoActual = t;
            document.getElementById('btn_tanque').classList.toggle('grayscale', t !== 'tanque');
            document.getElementById('btn_avion').classList.toggle('grayscale', t !== 'avion');
            aplicarFiltros();
        }

        function aplicarFiltros() {
            let totalVisibles = 0;
            document.querySelectorAll('.bloque-nacion').forEach(bloque => {
                if (bloque.dataset.nacion === nacActual) {
                    bloque.style.display = 'block';
                    bloque.querySelectorAll('.seccion-tipo').forEach(sec => {
                        sec.style.display = (sec.dataset.tipo === tipoActual) ? 'block' : 'none';
                    });
                    
                    bloque.querySelectorAll('.tier-container').forEach(tier => {
                        let tierTieneVisible = false;
                        tier.querySelectorAll('.clase-container').forEach(claseBlock => {
                            if (claseBlock.closest('.seccion-tipo').dataset.tipo === tipoActual) {
                                const items = claseBlock.querySelectorAll('.fila-v');
                                claseBlock.style.display = items.length > 0 ? 'block' : 'none';
                                if (items.length > 0) tierTieneVisible = true;
                            }
                        });
                        tier.style.display = tierTieneVisible ? 'block' : 'none';
                        if(tierTieneVisible) totalVisibles++;
                    });
                } else {
                    bloque.style.display = 'none';
                }
            });
            document.getElementById('mensaje_vacio').style.display = (totalVisibles === 0) ? 'block' : 'none';
        }

        function prepararCompraPlano(id, nombre, costo) {
            document.getElementById('modal_cat_id').value = id;
            document.getElementById('modal_qty').value = 1;
            document.getElementById('formConfirm').action = '../logic/procesar_plano.php';
            document.getElementById('confirmText').innerHTML = `<?php echo $txt['LIDER_TIENDA']['JS_CONFIRM_PLANO']; ?> <br><span class='text-white font-black'>${nombre}</span>?<br><br><span class='text-green-500 text-lg'>$${costo.toLocaleString()}</span>`;
            document.getElementById('modalConfirm').classList.remove('hidden');
            document.body.classList.add('modal-active');
        }

        function prepararCompraVehiculo(id, nombre, d, a, p) {
            const qtyInput = document.getElementById('qty-' + id);
            const qty = qtyInput ? parseInt(qtyInput.value) : 1;
            document.getElementById('modal_cat_id').value = id;
            document.getElementById('modal_qty').value = qty;
            document.getElementById('formConfirm').action = '../logic/procesar_compra.php';
            document.getElementById('confirmText').innerHTML = `<?php echo $txt['LIDER_TIENDA']['JS_CONFIRM_PROD']; ?> <span class='text-[var(--aoe-gold)]'>${qty} <?php echo $txt['LIDER_TIENDA']['JS_UNIDADES']; ?></span> DE <br><span class='text-white'>${nombre}</span>?<br><br><?php echo $txt['LIDER_TIENDA']['JS_COSTO_TOTAL']; ?> <span class='text-green-500'>$${(d*qty).toLocaleString()}</span> | <span class='text-white'>${(a*qty).toLocaleString()}T</span> | <span class='text-yellow-500'>${(p*qty).toLocaleString()}L</span>`;
            document.getElementById('modalConfirm').classList.remove('hidden');
            document.body.classList.add('modal-active');
        }

        function cerrarModal() { document.getElementById('modalConfirm').classList.add('hidden'); document.body.classList.remove('modal-active'); }

        function actualizarSubtiposLider() {
            const tipo = document.getElementById('lider_tipo_v').value;
            const selectSubtipo = document.getElementById('lider_subtipo_v');
            selectSubtipo.innerHTML = '';
            
            let opciones = tipo === 'tanque' ? ['Ligero', 'AAA', 'Mediano', 'Pesado', 'Caza Tanques'] : ['Caza', 'Interceptor', 'Bombardero', 'Avion de Ataque'];
            opciones.forEach(opcion => selectSubtipo.add(new Option(opcion, opcion)));

            // Tras cargar las subclases, forzamos la actualización del precio visual
            actualizarPrecioLiderPreview();
        }

        function actualizarPrecioLiderPreview() {
            const t = document.getElementById('lider_tipo_v').value;
            const s = document.getElementById('lider_subtipo_v').value;
            const r = document.getElementById('lider_rango_v').value;
            
            // Extraemos el costo, o ponemos 0 si hay algún error
            const data = preciosBase[t] && preciosBase[t][s] && preciosBase[t][s][r] ? preciosBase[t][s][r] : {dinero:0, acero:0, petroleo:0};
            
            document.getElementById('lider_prev_cash').innerText = '$' + Number(data.dinero).toLocaleString();
            document.getElementById('lider_prev_steel').innerText = Number(data.acero).toLocaleString() + 'T';
            document.getElementById('lider_prev_fuel').innerText = Number(data.petroleo).toLocaleString() + 'L';
        }
        // Forzamos el llenado del menú apenas cargue la página
        window.addEventListener('DOMContentLoaded', actualizarSubtiposLider);

        // Inicializamos todo apenas cargue la página
        window.addEventListener('DOMContentLoaded', () => {
            actualizarSubtiposLider();
        });
    </script>
</body>
</html>