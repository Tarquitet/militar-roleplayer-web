<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') {
    header("Location: ../login.php");
    exit();
}

$root_path = "../";
require_once '../config/conexion.php';
$txt = require '../config/textos.php';

// Cargamos el archivo de precios para pasarlo a JavaScript
$precios_base = require '../config/precios.php';
$precios_json = json_encode($precios_base);

try {
    $stmt_catalogo = $pdo->query("SELECT * FROM catalogo_tienda ORDER BY rango ASC, br ASC");
    $catalogo_db = $stmt_catalogo->fetchAll(PDO::FETCH_ASSOC);

    $stmt_naciones = $pdo->query("SELECT nombre FROM naciones ORDER BY nombre ASC");
    $lista_naciones = $stmt_naciones->fetchAll(PDO::FETCH_COLUMN);
    
    // AGRUPACIÓN PARA EL ACORDEÓN DE LA TIENDA
    $catalogo_agrupado = [];
    foreach($catalogo_db as $cn) { 
        $nacion = $cn['nacion'];
        $tier = $cn['rango'] ?? 1;
        $tipo = $cn['tipo'] ?? 'tanque';
        $clase = !empty($cn['subtipo']) ? $cn['subtipo'] : (!empty($cn['clase']) ? $cn['clase'] : 'No Clasificado'); 
        $catalogo_agrupado[$nacion][$tier][$tipo][$clase][] = $cn; 
    }

    $orden_tanques = ['Ligero', 'Mediano', 'Pesado', 'Caza Tanques', 'AAA'];
    $orden_aviones = ['Caza', 'Interceptor', 'Avion de Ataque', 'Bombardero en Picado', 'Bombardero de Pimera Línea'];

} catch (PDOException $e) {
    die("Fallo de enlace con el catálogo: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title><?php echo $txt['GLOBAL']['MANDO_STAFF'] ?? 'ADMINISTRACIÓN GLOBAL'; ?> - <?php echo $txt['STAFF_TIENDA']['TITULO_PAGINA'] ?? 'TIENDA'; ?></title>
    <?php include '../includes/head.php'; ?>
    <style>
        .modal-active { overflow: hidden; }
        .img-purga { filter: sepia(1) hue-rotate(-50deg) saturate(2) brightness(0.7); }
        .custom-scrollbar::-webkit-scrollbar { height: 8px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #0a0a0a; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #333; border-radius: 4px; border: 1px solid #000; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #555; }
        
        /* ESTILOS DE FICHA TÉCNICA */
        .tag-premium { position: absolute; top: 0; right: 0; background: #c5a059; color: #000; font-size: 9px; font-weight: 900; padding: 3px 8px; z-index: 10; text-transform: uppercase; letter-spacing: 1px; }
        .tag-br { position: absolute; top: 0; left: 0; background: #000; border-right: 1px solid #333; border-bottom: 1px solid #333; color: #fff; font-size: 10px; font-weight: 900; padding: 3px 8px; z-index: 10; font-family: monospace; }
        .card-premium { border-color: #c5a059 !important; box-shadow: inset 0 0 15px rgba(197, 160, 89, 0.15); }
        .stat-grid-label { font-size: 7px; color: #555; font-weight: 900; text-transform: uppercase; }
        .stat-grid-value { font-size: 9px; font-weight: 900; font-family: 'Space Mono', monospace; }
        
        /* ACORDEÓN ROJO TIPO JACKY */
        .tier-header { background: url('https://www.transparenttextures.com/patterns/diagmonds-light.png'), #000; border-bottom: 2px solid #991b1b; }
    </style>
</head>
<body class="bg-[#0d0e0a] text-[var(--text-main)] min-h-screen pb-20" onload="initTienda()">

    <?php include '../includes/nav_staff.php'; ?>

    <main class="p-8 max-w-[1600px] mx-auto relative">
        <div class="flex gap-4 mb-6">
            <button onclick="abrirModalPrecios()" 
                    class="btn-m !bg-none !border-[var(--khaki-beige)] !text-[var(--parchment)] hover:!text-[var(--aoe-gold)] hover:!border-[var(--aoe-gold)]">
                ⚙️ <?php echo $txt['STAFF_TIENDA']['BTN_PRECIOS'] ?? 'AJUSTAR PRECIOS'; ?>
            </button>
            <button onclick="abrirModalNuevoVehiculo()" class="btn-m">
                ➕ <?php echo $txt['STAFF_TIENDA']['BTN_ANADIR'] ?? 'AÑADIR ACTIVO'; ?>
            </button>
        </div>

        <div class="m-panel mb-8 p-4 bg-black/40 shadow-2xl backdrop-blur-md">
            <div class="flex gap-4 mb-4 border-b border-[var(--wood-border)] pb-4">
                <button id="btn_tipo_tanque" onclick="setFiltroTipo('tanque')" class="btn-m !text-[10px]">
                    <?php echo $txt['STAFF_TIENDA']['CAT_TANQUES'] ?? 'BLINDADOS'; ?>
                </button>
                <button id="btn_tipo_avion" onclick="setFiltroTipo('avion')" class="btn-m !text-[10px] grayscale opacity-70">
                    <?php echo $txt['STAFF_TIENDA']['CAT_AVIONES'] ?? 'FUERZA AÉREA'; ?>
                </button>
            </div>
            
            <div class="flex items-center gap-4">
                <span class="text-[var(--aoe-gold)] text-[10px] font-black uppercase tracking-widest">
                    <?php echo $txt['STAFF_TIENDA']['FILTRO_PAIS'] ?? 'SECTOR NACIONAL:'; ?>
                </span>
                <div id="contenedor_naciones" class="flex flex-wrap gap-2"></div>
            </div>
        </div>

        <div id="mensaje_vacio" class="m-panel p-10 text-center text-gray-500 italic font-bold uppercase tracking-widest hidden">
            <?php echo $txt['STAFF_TIENDA']['SIN_VEHICULOS'] ?? 'SIN ACTIVOS REGISTRADOS EN ESTA CLASIFICACIÓN.'; ?>
        </div>

        <div id="cont_hangar" class="space-y-6">
            <?php foreach($catalogo_agrupado as $nacion => $tiers): ?>
                <div class="bloque-nacion" data-nacion="<?php echo htmlspecialchars($nacion); ?>">
                    <?php foreach($tiers as $tier => $tipos): ?>
                        <div class="tier-container border border-gray-800 bg-[#0a0a0a] shadow-lg mb-6">
                            <div class="tier-header p-3 px-6 cursor-pointer flex justify-between items-center transition hover:bg-black" onclick="toggleTier(this)">
                                <h2 class="text-white font-black uppercase text-lg tracking-[0.3em] m-0"><?php echo $txt['STAFF_TIENDA']['LBL_TIER'] ?? 'RANGO / TIER'; ?> <?php echo $tier; ?></h2>
                                <span class="text-gray-500 text-xs font-bold transition-transform duration-300">▼</span>
                            </div>
                            <div class="tier-content p-6 space-y-8 block">
                                <?php foreach(['tanque' => $orden_tanques, 'avion' => $orden_aviones] as $tipo_vehiculo => $orden_clases): 
                                    if(!isset($tipos[$tipo_vehiculo])) continue;
                                ?>
                                    <div class="seccion-tipo" data-tipo="<?php echo $tipo_vehiculo; ?>">
                                        <?php foreach($orden_clases as $clase_nombre): 
                                            if(!isset($tipos[$tipo_vehiculo][$clase_nombre])) continue;
                                        ?>
                                            <div class="clase-container mb-8">
                                                <h3 class="text-[var(--aoe-gold)] font-black uppercase text-[10px] tracking-[0.2em] border-b border-gray-800 pb-2 mb-4"><?php echo $clase_nombre; ?></h3>
                                                <div class="flex gap-4 overflow-x-auto pb-4 custom-scrollbar">
                                                    <?php foreach($tipos[$tipo_vehiculo][$clase_nombre] as $item): 
                                                        $es_prem = isset($item['is_premium']) && $item['is_premium'] == 1;
                                                    ?>
                                                        <div class="fila-vehiculo flex-shrink-0 w-64 flex flex-col bg-[#111] border <?php echo $es_prem ? 'card-premium' : 'border-gray-800'; ?> relative hover:brightness-110 transition shadow-xl">
                                                            <?php if($es_prem): ?><div class="tag-premium"><?php echo $txt['STAFF_TIENDA']['TAG_PREMIUM'] ?? 'PREMIUM'; ?></div><?php endif; ?>
                                                            <div class="tag-br"><?php echo $txt['STAFF_TIENDA']['TAG_BR'] ?? 'BR:'; ?> <?php echo htmlspecialchars($item['br'] ?? '1.0'); ?></div>
                                                            
                                                            <div class="h-28 bg-[#0a0a0a] relative overflow-hidden border-b border-gray-800">
                                                                <img src="../<?php echo $item['imagen_url']; ?>" class="w-full h-full object-cover opacity-80">
                                                                <div class="absolute bottom-0 right-0 bg-black/80 px-2 py-0.5 text-[8px] text-gray-400 font-bold border-t border-l border-gray-800 uppercase"><?php echo $txt['STAFF_TIENDA']['LBL_TIER'] ?? 'TIER'; ?> <?php echo $item['rango']; ?></div>
                                                            </div>

                                                            <div class="p-3 flex-grow flex flex-col">
                                                                <h3 class="text-[11px] font-black text-white uppercase text-center truncate mb-2"><?php echo htmlspecialchars($item['nombre_vehiculo']); ?></h3>
                                                                
                                                                <div class="flex justify-center gap-1 mb-3">
                                                                    <span class="text-[8px] bg-blue-900/30 text-blue-400 px-1.5 py-0.5 rounded font-black uppercase"><?php echo htmlspecialchars($item['tipo']); ?></span>
                                                                    <span class="text-[8px] bg-gray-800 text-gray-400 px-1.5 py-0.5 rounded font-black uppercase"><?php echo $clase_nombre; ?></span>
                                                                </div>

                                                                <div class="grid grid-cols-3 gap-0 bg-black border border-gray-800 p-1.5 text-center rounded">
                                                                    <div class="border-r border-gray-800">
                                                                        <span class="stat-grid-label block"><?php echo $txt['STAFF_TIENDA']['TH_DINERO'] ?? 'CASH'; ?></span>
                                                                        <span class="stat-grid-value text-green-500">$<?php echo number_format($item['costo_dinero']); ?></span>
                                                                    </div>
                                                                    <div class="border-r border-gray-800">
                                                                        <span class="stat-grid-label block"><?php echo $txt['STAFF_TIENDA']['TH_ACERO'] ?? 'STEEL'; ?></span>
                                                                        <span class="stat-grid-value text-white"><?php echo number_format($item['costo_acero']); ?>T</span>
                                                                    </div>
                                                                    <div>
                                                                        <span class="stat-grid-label block"><?php echo $txt['STAFF_TIENDA']['TH_PETROLEO'] ?? 'FUEL'; ?></span>
                                                                        <span class="stat-grid-value text-yellow-500"><?php echo number_format($item['costo_petroleo']); ?>L</span>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="p-2 bg-black/80 flex gap-2 border-t border-gray-800">
                                                                <?php $item_json = htmlspecialchars(json_encode($item), ENT_QUOTES, "UTF-8"); ?>
                                                                <button type="button" onclick='abrirModalEditarTienda(<?php echo $item_json; ?>)' class="w-1/2 btn-m !bg-yellow-900/30 !text-yellow-500 border-yellow-700 font-black uppercase text-[9px] hover:bg-yellow-600 hover:text-black transition tracking-widest"><?php echo $txt['STAFF_TIENDA']['BTN_EDITAR'] ?? 'EDITAR'; ?></button>
                                                                
                                                                <button type="button" onclick="abrirPurgaSeguridad(<?php echo $item['id']; ?>, '<?php echo addslashes($item['nombre_vehiculo']); ?>', '../<?php echo $item['imagen_url']; ?>')" class="w-1/2 btn-m !bg-red-950/30 !text-red-500 border-red-900 font-black uppercase text-[9px] hover:bg-red-700 hover:text-white transition tracking-widest"><?php echo $txt['STAFF_TIENDA']['BTN_BORRAR'] ?? 'PURGAR'; ?></button>
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

        <div id="modalConfirmarBorrado" class="hidden fixed inset-0 bg-black/95 z-[200] flex items-center justify-center p-4">
            <div class="m-panel w-full max-w-2xl relative border-red-800 border-2 shadow-[0_0_50px_rgba(220,38,38,0.2)]">
                <button onclick="document.getElementById('modalConfirmarBorrado').classList.add('hidden')" class="absolute top-4 right-4 text-gray-500 hover:text-white font-bold text-xl">&times;</button>
                <h3 class="text-red-500 font-black text-lg mb-2 tracking-[0.2em] uppercase text-center border-b border-red-900/40 pb-2"><?php echo $txt['STAFF_TIENDA']['PURGA_TITULO'] ?? '⚠️ PROTOCOLO DE PURGA DE ACTIVOS'; ?></h3>
                <div class="flex gap-6 mb-6 mt-6 bg-red-950/10 p-4 border border-red-900/30">
                    <div class="w-1/3 h-24 bg-black border border-red-900/50 overflow-hidden"><img id="purga_img" src="" class="w-full h-full object-cover img-purga"></div>
                    <div class="w-2/3">
                        <span class="text-[9px] text-gray-500 font-bold uppercase tracking-widest block mb-1"><?php echo $txt['STAFF_TIENDA']['PURGA_LBL_ID'] ?? 'Identificación del Activo:'; ?></span>
                        <h4 id="purga_nombre" class="text-white font-black text-2xl font-['Cinzel'] uppercase"></h4>
                        <p class="text-[10px] text-red-400 font-bold mt-2 uppercase tracking-tighter italic"><?php echo $txt['STAFF_TIENDA']['PURGA_CONFIRM'] ?? '¿Está seguro de eliminar este registro del sistema global?'; ?></p>
                    </div>
                </div>
                <div class="mb-8">
                    <span class="text-[10px] text-gray-500 font-black uppercase tracking-widest block mb-3"><?php echo $txt['STAFF_TIENDA']['PURGA_ESCANEO'] ?? '📡 ESCANEO DE IMPACTO EN FACCIONES:'; ?></span>
                    <div id="impacto_lista" class="max-h-40 overflow-y-auto space-y-2 bg-black/50 border border-gray-800 p-4 custom-scrollbar"></div>
                </div>
                <form action="../logic/procesar_tienda_staff.php" method="POST" class="grid grid-cols-2 gap-4">
                    <input type="hidden" name="accion" value="eliminar"><input type="hidden" id="purga_id" name="id">
                    <button type="submit" name="reembolsar" value="0" class="btn-m !bg-none !border-gray-700 !text-gray-500 hover:!text-white hover:!border-white !py-4 text-[10px] font-black uppercase"><?php echo $txt['STAFF_TIENDA']['BTN_DEL_SIMPLE'] ?? 'BORRAR SIN REEMBOLSO'; ?></button>
                    <button type="submit" name="reembolsar" value="1" class="btn-m !bg-red-800 !border-red-600 !text-white !py-4 text-[10px] font-black uppercase animate-pulse"><?php echo $txt['STAFF_TIENDA']['BTN_DEL_REEMBOLSO'] ?? 'PURGAR Y REEMBOLSAR A TODOS'; ?></button>
                </form>
            </div>
        </div>

        <div id="modalNuevoVehiculo" class="hidden fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-4">
            <div class="m-panel w-full max-w-lg relative border-[var(--aoe-gold)]">
                <button type="button" onclick="document.getElementById('modalNuevoVehiculo').classList.add('hidden')" class="absolute top-4 right-4 text-white font-bold text-xl">&times;</button>
                <h2 class="m-title text-2xl mb-6 border-b border-[var(--wood-border)] pb-2"><?php echo $txt['STAFF_TIENDA']['MODAL_ADD_TITULO'] ?? 'REGISTRAR NUEVO ACTIVO'; ?></h2>
                <form action="../logic/procesar_tienda_staff.php" method="POST" enctype="multipart/form-data" onsubmit="return validarRegistroVehiculo(event)">
                    <input type="hidden" name="accion" value="agregar">
                    <div class="mb-4 flex gap-4">
                        <div class="w-1/2">
                            <label class="block text-[10px] text-gray-500 uppercase font-black mb-1"><?php echo $txt['STAFF_TIENDA']['LBL_TIPO'] ?? 'CATEGORÍA'; ?> *</label>
                            <select id="tipo_vehiculo" name="tipo" onchange="actualizarSubtipos()" required class="m-input w-full outline-none">
                                <option value="tanque">Tanque</option><option value="avion">Avión</option>
                            </select>
                        </div>
                        <div class="w-1/2">
                            <label class="block text-[10px] text-gray-500 uppercase font-black mb-1"><?php echo $txt['STAFF_TIENDA']['LBL_SUBTIPO'] ?? 'CLASIFICACIÓN'; ?> *</label>
                            <select id="subtipo_vehiculo" name="subtipo" onchange="actualizarPrecioPreview()" required class="m-input w-full outline-none"></select>
                        </div>
                    </div>
                    <div class="mb-4 flex gap-4">
                        <div class="w-1/2">
                            <label class="block text-[10px] text-gray-500 uppercase font-black mb-1"><?php echo $txt['STAFF_TIENDA']['LBL_NACION'] ?? 'NACIÓN ASIGNADA'; ?> *</label>
                            <select name="nacion" required class="m-input w-full">
                                <?php foreach ($lista_naciones as $nacion): ?><option value="<?php echo htmlspecialchars($nacion); ?>"><?php echo htmlspecialchars($nacion); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="w-1/4">
                            <label class="block text-[10px] text-gray-500 uppercase font-black mb-1"><?php echo $txt['STAFF_TIENDA']['LBL_RANGO'] ?? 'RANGO'; ?> *</label>
                            <select id="rango_vehiculo" name="rango" onchange="actualizarPrecioPreview()" required class="m-input w-full text-center font-black">
                                <?php for($i=1; $i<=8; $i++) echo "<option value='$i'>$i</option>"; ?>
                            </select>
                        </div>
                        <div class="w-1/4">
                            <label class="block text-[10px] text-blue-400 uppercase font-black mb-1">B.R. *</label>
                            <input type="text" name="br" required placeholder="1.3" class="m-input w-full text-center font-mono">
                        </div>
                    </div>
                    <div class="mb-4 bg-black/40 p-4 border border-white/5 text-center">
                        <div class="flex justify-between items-center text-[10px] font-black">
                            <div><span class="block text-gray-600">CASH</span><span id="prev_dinero" class="text-green-500 text-lg">...</span></div>
                            <div class="border-l border-white/5 pl-4"><span class="block text-gray-600">ACERO</span><span id="prev_acero" class="text-white text-lg">...</span></div>
                            <div class="border-l border-white/5 pl-4"><span class="block text-gray-600">FUEL</span><span id="prev_petroleo" class="text-yellow-500 text-lg">...</span></div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-[10px] text-gray-500 uppercase font-black mb-1"><?php echo $txt['STAFF_TIENDA']['LBL_NOMBRE'] ?? 'NOMBRE OPERATIVO'; ?> *</label>
                        <input type="text" name="nombre_vehiculo" required placeholder="..." class="m-input w-full">
                    </div>
                    <div class="mb-6 flex items-center justify-between gap-4">
                        <div class="flex-grow">
                            <label class="block text-[9px] text-gray-500 uppercase font-black mb-1"><?php echo $txt['STAFF_TIENDA']['LBL_IMG'] ?? 'FOTOGRAFÍA TÁCTICA'; ?> *</label>
                            <input type="file" name="imagen" accept="image/*" required class="w-full text-[10px] border border-gray-800 p-2 bg-black/40 text-gray-300">
                        </div>
                        <div class="flex-shrink-0 bg-black/50 p-3 border border-[#c5a059] flex items-center gap-2 mt-4">
                            <input type="checkbox" name="is_premium" value="1" class="w-4 h-4 accent-[#c5a059]">
                            <span class="text-[10px] font-black text-[#c5a059] uppercase"><?php echo $txt['STAFF_TIENDA']['LBL_PREMIUM_TIT'] ?? 'ESTATUS DE ÉLITE'; ?></span>
                        </div>
                    </div>
                    <button type="submit" class="btn-m w-full py-4 font-black uppercase tracking-widest"><?php echo $txt['STAFF_TIENDA']['BTN_CONFIRMAR'] ?? 'CONFIRMAR Y REGISTRAR'; ?></button>
                </form>
            </div>
        </div>

        <div id="modalEditorPrecios" class="hidden fixed inset-0 bg-black/95 z-[60] flex items-center justify-center p-4 backdrop-blur-md">
            <div class="m-panel w-full max-w-4xl relative border-[var(--aoe-gold)] bg-[#1e2017] p-8 shadow-[0_0_40px_rgba(197,160,89,0.2)]">
                <button type="button" onclick="document.getElementById('modalEditorPrecios').classList.add('hidden')" class="absolute top-4 right-4 text-gray-500 hover:text-white font-bold text-2xl transition">&times;</button>
                
                <h2 class="text-3xl mb-2 uppercase font-black italic text-[var(--aoe-gold)] tracking-widest font-['Cinzel']"><?php echo $txt['STAFF_TIENDA']['MODAL_PR_TITULO'] ?? 'AJUSTE DE ECONOMÍA GLOBAL'; ?></h2>
                <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest mb-6 border-b border-gray-800 pb-4">Edición masiva de costos por subclase. Los 8 rangos se guardarán simultáneamente.</p>
                
                <form action="../logic/actualizar_precios_masivos.php" method="POST" class="space-y-6">
                    <div class="grid grid-cols-2 gap-6 bg-black/40 p-4 border border-[#3e1414]">
                        <div>
                            <label class="block text-[11px] text-[var(--aoe-gold)] uppercase font-black mb-2 tracking-widest"><?php echo $txt['STAFF_TIENDA']['LBL_TIPO'] ?? 'CATEGORÍA'; ?></label>
                            <select id="edit_tipo" name="tipo" onchange="updateEditSubtipos()" class="w-full bg-black text-white border border-[#3e1414] p-3 outline-none font-bold">
                                <option value="tanque">Tanque</option>
                                <option value="avion">Avión</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] text-[var(--aoe-gold)] uppercase font-black mb-2 tracking-widest"><?php echo $txt['STAFF_TIENDA']['LBL_SUBTIPO'] ?? 'CLASIFICACIÓN'; ?></label>
                            <select id="edit_subtipo" name="subtipo" onchange="refreshEditValues()" class="w-full bg-black text-white border border-[#3e1414] p-3 outline-none font-bold"></select>
                        </div>
                    </div>

                    <div class="overflow-x-auto border border-gray-800/50 shadow-inner">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-black text-gray-500 text-[10px] uppercase font-black tracking-widest">
                                    <th class="p-3 border-b border-gray-800 text-center w-24">RANGO</th>
                                    <th class="p-3 border-b border-gray-800 text-center text-green-600">DINERO ($)</th>
                                    <th class="p-3 border-b border-gray-800 text-center text-gray-400">ACERO (T)</th>
                                    <th class="p-3 border-b border-gray-800 text-center text-yellow-600">PETRÓLEO (L)</th>
                                </tr>
                            </thead>
                            <tbody id="tabla_precios_body">
                                </tbody>
                        </table>
                    </div>
                    
                    <button type="submit" class="btn-m w-full py-5 uppercase font-black tracking-[0.2em] text-[13px] border border-yellow-700 !bg-yellow-900/20 !text-yellow-500 hover:!bg-yellow-600 hover:!text-black transition-all">
                        💾 GUARDAR TABLA COMPLETA
                    </button>
                </form>
            </div>
        </div>

        <div id="modalErrorArchivo" class="hidden fixed inset-0 bg-black/90 z-[200] flex items-center justify-center p-4">
            <div class="m-panel w-full max-w-sm relative border-red-800 border-2">
                <h3 class="text-red-500 font-black text-lg mb-4 text-center"><?php echo $txt['STAFF_TIENDA']['ERR_CARGA_TIT'] ?? '❌ ERROR'; ?></h3>
                <p id="errorArchivoMsg" class="text-gray-400 text-center uppercase mb-6"></p>
                <button onclick="document.getElementById('modalErrorArchivo').classList.add('hidden')" class="btn-m w-full"><?php echo $txt['STAFF_TIENDA']['BTN_ENTENDIDO'] ?? 'ENTENDIDO'; ?></button>
            </div>
        </div>

        <div id="modalEdit" class="hidden fixed inset-0 bg-black/90 z-[200] flex items-center justify-center p-4">
            <div class="m-panel border-[var(--aoe-gold)] w-full max-w-2xl relative shadow-2xl">
                <button type="button" onclick="document.getElementById('modalEdit').classList.add('hidden'); document.body.classList.remove('modal-active');" class="absolute top-4 right-4 text-gray-500 hover:text-white text-2xl">&times;</button>
                <h3 class="m-title text-xl mb-6 border-b border-[var(--wood-border)] pb-2 text-yellow-500"><?php echo $txt['STAFF_TIENDA']['MODAL_EDIT_TITULO'] ?? 'MODIFICAR ACTIVO'; ?></h3>
                
                <form action="../logic/procesar_tienda_staff.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] text-gray-500 uppercase font-black mb-1"><?php echo $txt['STAFF_TIENDA']['LBL_NOMBRE'] ?? 'Nombre del Vehículo'; ?></label>
                            <input type="text" name="nombre_vehiculo" id="edit_nombre" required class="m-input w-full">
                        </div>
                        <div>
                            <label class="block text-[10px] text-gray-500 uppercase font-black mb-1"><?php echo $txt['STAFF_TIENDA']['LBL_NACION'] ?? 'Nación Fabricante'; ?></label>
                            <select name="nacion" id="edit_nacion" class="m-input w-full uppercase font-black">
                                <?php foreach($lista_naciones as $n): ?><option value="<?php echo htmlspecialchars($n); ?>"><?php echo htmlspecialchars($n); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[10px] text-gray-500 uppercase font-black mb-1"><?php echo $txt['STAFF_TIENDA']['LBL_TIPO'] ?? 'Tipo'; ?></label>
                            <select name="tipo" id="edit_tipo_v" onchange="actualizarSubtiposEdit()" class="m-input w-full uppercase">
                                <option value="tanque">Tanque</option>
                                <option value="avion">Avión</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] text-gray-500 uppercase font-black mb-1"><?php echo $txt['STAFF_TIENDA']['LBL_SUBTIPO'] ?? 'Sub-clase'; ?></label>
                            <select name="subtipo" id="edit_subtipo_v" onchange="actualizarPrecioEditPreview()" class="m-input w-full uppercase"></select>
                        </div>
                        <div>
                            <label class="block text-[10px] text-gray-500 uppercase font-black mb-1"><?php echo $txt['STAFF_TIENDA']['LBL_RANGO'] ?? 'Tier / Rango'; ?></label>
                            <select name="rango" id="edit_rango_v" onchange="actualizarPrecioEditPreview()" class="m-input w-full text-center font-black">
                                <?php for($i=1; $i<=8; $i++) echo "<option value='$i'>$i</option>"; ?>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] text-gray-500 uppercase font-black mb-1">Battle Rating (BR)</label>
                            <input type="text" name="br" id="edit_br" class="m-input w-full text-center" placeholder="Ej: 5.7">
                        </div>
                        <div class="flex items-end mb-1">
                            <div class="bg-black/50 p-3 border border-[#c5a059] flex items-center gap-2 w-full">
                                <input type="checkbox" name="is_premium" id="edit_premium" value="1" class="w-4 h-4 accent-[#c5a059]">
                                <span class="text-[10px] font-black text-[#c5a059] uppercase"><?php echo $txt['STAFF_TIENDA']['LBL_PREMIUM_TIT'] ?? 'ES PREMIUM'; ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="border border-white/5 bg-black/80 p-4 mt-4 mb-4 shadow-inner">
                        <h4 class="text-[9px] text-gray-500 font-black uppercase mb-3 text-center tracking-[0.2em] border-b border-white/5 pb-2">IMPACTO ECONÓMICO EN MERCADO</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="border-r border-white/5 pr-4 text-center">
                                <span class="block text-[8px] text-gray-600 font-bold uppercase mb-2">COSTO ACTUAL</span>
                                <div class="flex justify-between items-center bg-white/5 p-1 px-2 mb-1"><span class="text-[8px] text-gray-500 font-black">CASH</span><span id="edit_curr_cash" class="text-[9px] text-green-700 font-mono">...</span></div>
                                <div class="flex justify-between items-center bg-white/5 p-1 px-2 mb-1"><span class="text-[8px] text-gray-500 font-black">STEEL</span><span id="edit_curr_steel" class="text-[9px] text-gray-500 font-mono">...</span></div>
                                <div class="flex justify-between items-center bg-white/5 p-1 px-2"><span class="text-[8px] text-gray-500 font-black">FUEL</span><span id="edit_curr_fuel" class="text-[9px] text-yellow-700 font-mono">...</span></div>
                            </div>
                            <div class="pl-4 text-center">
                                <span class="block text-[8px] text-[var(--aoe-gold)] font-bold uppercase mb-2">NUEVO COSTO</span>
                                <div class="flex justify-between items-center bg-green-900/10 p-1 px-2 mb-1 border border-green-900/30"><span class="text-[8px] text-green-500 font-black">CASH</span><span id="edit_new_cash" class="text-[10px] text-green-400 font-black font-mono">...</span></div>
                                <div class="flex justify-between items-center bg-gray-800/30 p-1 px-2 mb-1 border border-gray-700/50"><span class="text-[8px] text-white font-black">STEEL</span><span id="edit_new_steel" class="text-[10px] text-white font-black font-mono">...</span></div>
                                <div class="flex justify-between items-center bg-yellow-900/10 p-1 px-2 border border-yellow-900/30"><span class="text-[8px] text-yellow-500 font-black">FUEL</span><span id="edit_new_fuel" class="text-[10px] text-yellow-400 font-black font-mono">...</span></div>
                            </div>
                        </div>
                        <div id="edit_price_warning" class="mt-4 p-2 bg-yellow-900/20 border border-yellow-700 text-yellow-500 text-[9px] font-black uppercase text-center hidden animate-pulse">
                            ⚠️ ALERTA: EL CAMBIO DE TIPO/TIER ALTERARÁ EL COSTO DEL VEHÍCULO.
                        </div>
                    </div>

                    <div class="border border-white/10 p-4 bg-black/50 mt-4 text-center">
                        <label class="block text-[10px] text-blue-400 uppercase font-black mb-2">ACTUALIZAR FOTOGRAFÍA (Opcional)</label>
                        <input type="file" name="imagen" accept="image/*" class="w-full text-[10px] border border-gray-800 p-2 bg-black/40 text-gray-400">
                    </div>
                    
                    <button type="submit" class="btn-m w-full !bg-yellow-700 !text-black py-4 mt-6 text-xs tracking-[0.2em] hover:bg-yellow-500">APLICAR MODIFICACIONES</button>
                </form>
            </div>
        </div>

    </main>

    <script>
        const preciosBase = <?php echo $precios_json; ?>;
        const todasLasNaciones = <?php echo json_encode($lista_naciones); ?>;
        
        // Variables globales necesarias para que los modales no fallen
        const opsTanques = ['Ligero', 'Mediano', 'Pesado', 'Caza Tanques', 'AAA'];
        const opsAviones = ['Caza', 'Interceptor', 'Avion de Ataque', 'Bombardero en Picado', 'Bombardero de Pimera Línea'];
        
        let filtroTipoActual = 'tanque';
        let filtroNacionActual = todasLasNaciones.length > 0 ? todasLasNaciones[0] : '';
        let vehiculoEnEdicion = null;

        function initTienda() { 
            renderBotonesNaciones(); 
            aplicarFiltrosTabla(); 
        }

        // --- FUNCIONES DEL TABLERO PRINCIPAL ---
        function setFiltroTipo(t) { 
            filtroTipoActual = t; 
            document.getElementById('btn_tipo_tanque').classList.toggle('grayscale', t !== 'tanque'); 
            document.getElementById('btn_tipo_tanque').classList.toggle('opacity-70', t !== 'tanque'); 
            document.getElementById('btn_tipo_avion').classList.toggle('grayscale', t !== 'avion'); 
            document.getElementById('btn_tipo_avion').classList.toggle('opacity-70', t !== 'avion'); 
            aplicarFiltrosTabla(); 
        }

        function renderBotonesNaciones() {
            const cont = document.getElementById('contenedor_naciones');
            cont.innerHTML = '';
            if (todasLasNaciones.length === 0) {
                cont.innerHTML = '<span class="text-gray-500 text-[10px]">Sin Naciones Registradas</span>';
                return;
            }
            todasLasNaciones.forEach(n => {
                const btn = document.createElement('button'); 
                btn.innerText = n;
                btn.className = `px-4 py-1 text-[10px] font-black uppercase border transition ${n === filtroNacionActual ? 'bg-red-900 text-white border-red-500 shadow-[0_0_10px_rgba(220,38,38,0.5)]' : 'bg-black/50 text-gray-500 border-gray-800 hover:text-white'}`;
                btn.onclick = () => { 
                    filtroNacionActual = n; 
                    renderBotonesNaciones(); 
                    aplicarFiltrosTabla(); 
                };
                cont.appendChild(btn);
            });
        }

        function aplicarFiltrosTabla() {
            let totalVisibles = 0;
            document.querySelectorAll('.bloque-nacion').forEach(bloqueNacion => {
                if (bloqueNacion.dataset.nacion !== filtroNacionActual) {
                    bloqueNacion.style.display = 'none';
                    return;
                }
                bloqueNacion.style.display = 'block';
                bloqueNacion.querySelectorAll('.tier-container').forEach(tier => {
                    let hayAlgoEnEsteTier = false;
                    tier.querySelectorAll('.seccion-tipo').forEach(seccionTipo => {
                        if (seccionTipo.dataset.tipo !== filtroTipoActual) {
                            seccionTipo.style.display = 'none';
                        } else {
                            let tarjetas = seccionTipo.querySelectorAll('.fila-vehiculo');
                            if (tarjetas.length > 0) {
                                seccionTipo.style.display = 'block';
                                hayAlgoEnEsteTier = true;
                                totalVisibles += tarjetas.length;
                            } else {
                                seccionTipo.style.display = 'none';
                            }
                        }
                    });
                    tier.style.display = hayAlgoEnEsteTier ? 'block' : 'none';
                });
            });
            document.getElementById('mensaje_vacio').classList.toggle('hidden', totalVisibles > 0);
        }

        function toggleTier(el) {
            const content = el.nextElementSibling;
            const arrow = el.querySelector('span');
            if (content.style.display === 'none' || content.style.display === '') {
                content.style.display = 'block';
                arrow.style.transform = 'rotate(0deg)';
            } else {
                content.style.display = 'none';
                arrow.style.transform = 'rotate(180deg)';
            }
        }

        // --- MODAL PRECIOS MASIVOS (EXCEL) ---
        function abrirModalPrecios() {
            updateEditSubtipos();
            document.getElementById('modalEditorPrecios').classList.remove('hidden');
        }

        function updateEditSubtipos() {
            const t = document.getElementById('edit_tipo').value;
            const sel = document.getElementById('edit_subtipo');
            sel.innerHTML = '';
            let ops = t === 'tanque' ? opsTanques : opsAviones;
            ops.forEach(o => sel.add(new Option(o, o)));
            refreshEditValues();
        }

        function refreshEditValues() {
            const t = document.getElementById('edit_tipo').value;
            const s = document.getElementById('edit_subtipo').value;
            const tbody = document.getElementById('tabla_precios_body');
            
            tbody.innerHTML = ''; 
            for (let r = 1; r <= 8; r++) {
                let d = 0, a = 0, p = 0;
                if(preciosBase[t] && preciosBase[t][s] && preciosBase[t][s][r]) {
                    d = preciosBase[t][s][r].dinero || 0;
                    a = preciosBase[t][s][r].acero || 0;
                    p = preciosBase[t][s][r].petroleo || 0;
                }
                
                let tr = document.createElement('tr');
                tr.className = "bg-[#0a0b08] hover:bg-[#11110b] transition-colors";
                tr.innerHTML = `
                    <td class="p-2 text-center text-gray-400 font-black text-xs border-r border-b border-[#3e1414]">TIER ${r}</td>
                    <td class="p-2 border-b border-[#3e1414]"><input type="number" name="precios[${r}][dinero]" value="${d}" class="w-full bg-black/50 border border-green-900/30 p-2 text-right font-mono font-bold text-green-500 text-sm focus:border-green-500 outline-none transition"></td>
                    <td class="p-2 border-b border-[#3e1414]"><input type="number" name="precios[${r}][acero]" value="${a}" class="w-full bg-black/50 border border-gray-700/50 p-2 text-right font-mono font-bold text-white text-sm focus:border-white outline-none transition"></td>
                    <td class="p-2 border-b border-[#3e1414]"><input type="number" name="precios[${r}][petroleo]" value="${p}" class="w-full bg-black/50 border border-yellow-900/30 p-2 text-right font-mono font-bold text-yellow-500 text-sm focus:border-yellow-500 outline-none transition"></td>
                `;
                tbody.appendChild(tr);
            }
        }

        // --- MODAL AÑADIR VEHICULO ---
        function abrirModalNuevoVehiculo() {
            actualizarSubtipos(); 
            document.getElementById('modalNuevoVehiculo').classList.remove('hidden');
        }

        function actualizarSubtipos() {
            const t = document.getElementById('tipo_vehiculo').value;
            const sel = document.getElementById('subtipo_vehiculo');
            sel.innerHTML = ''; 
            let ops = t === 'tanque' ? opsTanques : opsAviones;
            ops.forEach(o => sel.add(new Option(o, o)));
            actualizarPrecioPreview();
        }

        function actualizarPrecioPreview() {
            const t = document.getElementById('tipo_vehiculo').value;
            const s = document.getElementById('subtipo_vehiculo').value;
            const r = document.getElementById('rango_vehiculo').value;
            
            let d = 0, a = 0, p = 0;
            if(preciosBase[t] && preciosBase[t][s] && preciosBase[t][s][r]) {
                d = preciosBase[t][s][r].dinero;
                a = preciosBase[t][s][r].acero;
                p = preciosBase[t][s][r].petroleo;
            }
            document.getElementById('prev_dinero').innerText = '$' + d; 
            document.getElementById('prev_acero').innerText = a + 'T'; 
            document.getElementById('prev_petroleo').innerText = p + 'L';
        }

        function validarRegistroVehiculo(e) { 
            if(!e.target.checkValidity()) { 
                e.preventDefault(); 
                alert("<?php echo $txt['JS_ALERTAS']['ERR_CAMPOS_OBL'] ?? 'Faltan campos'; ?>"); 
                return false; 
            } 
            return true; 
        }

        // --- MODAL EDITAR VEHÍCULO ---
        function abrirModalEditarTienda(item) {
            vehiculoEnEdicion = item; 
            document.getElementById('edit_id').value = item.id;
            document.getElementById('edit_nombre').value = item.nombre_vehiculo;
            document.getElementById('edit_nacion').value = item.nacion;
            document.getElementById('edit_tipo_v').value = item.tipo;
            
            document.getElementById('edit_curr_cash').innerText = '$' + Number(item.costo_dinero).toLocaleString();
            document.getElementById('edit_curr_steel').innerText = Number(item.costo_acero).toLocaleString() + 'T';
            document.getElementById('edit_curr_fuel').innerText = Number(item.costo_petroleo).toLocaleString() + 'L';

            let claseActual = item.subtipo || item.clase; 
            actualizarSubtiposEdit(claseActual);

            document.getElementById('edit_rango_v').value = item.rango;
            document.getElementById('edit_br').value = item.br;
            document.getElementById('edit_premium').checked = (item.is_premium == 1);
            
            document.getElementById('modalEdit').classList.remove('hidden');
            document.body.classList.add('modal-active');
            actualizarPrecioEditPreview();
        }

        function actualizarSubtiposEdit(valorPreseleccionado = null) {
            const t = document.getElementById('edit_tipo_v').value;
            const selectSubtipo = document.getElementById('edit_subtipo_v');
            selectSubtipo.innerHTML = '';
            let ops = t === 'tanque' ? opsTanques : opsAviones;
            ops.forEach(opcion => {
                let opt = new Option(opcion, opcion);
                if (opcion === valorPreseleccionado) { opt.selected = true; }
                selectSubtipo.add(opt);
            });
            actualizarPrecioEditPreview();
        }

        function actualizarPrecioEditPreview() {
            if (!vehiculoEnEdicion) return;
            const t = document.getElementById('edit_tipo_v').value;
            const s = document.getElementById('edit_subtipo_v').value;
            const r = document.getElementById('edit_rango_v').value;
            
            let d = 0, a = 0, p = 0;
            if(preciosBase[t] && preciosBase[t][s] && preciosBase[t][s][r]) {
                d = preciosBase[t][s][r].dinero;
                a = preciosBase[t][s][r].acero;
                p = preciosBase[t][s][r].petroleo;
            }
            document.getElementById('edit_new_cash').innerText = '$' + Number(d).toLocaleString();
            document.getElementById('edit_new_steel').innerText = Number(a).toLocaleString() + 'T';
            document.getElementById('edit_new_fuel').innerText = Number(p).toLocaleString() + 'L';

            const precioCambio = (
                Number(d) !== Number(vehiculoEnEdicion.costo_dinero) ||
                Number(a) !== Number(vehiculoEnEdicion.costo_acero) ||
                Number(p) !== Number(vehiculoEnEdicion.costo_petroleo)
            );
            document.getElementById('edit_price_warning').classList.toggle('hidden', !precioCambio);
        }

        // --- PURGA DE SEGURIDAD ---
        function abrirPurgaSeguridad(id, nombre, img) {
            document.getElementById('purga_id').value = id;
            document.getElementById('purga_nombre').innerText = nombre;
            document.getElementById('purga_img').src = img;
            document.getElementById('modalConfirmarBorrado').classList.remove('hidden');
            const lista = document.getElementById('impacto_lista');
            lista.innerHTML = '<p class="text-gray-600 text-[10px] text-center italic py-4 animate-pulse">Iniciando escaneo de registros...</p>';
            fetch(`../logic/obtener_detalles_borrado.php?id=${id}`).then(r => r.json()).then(data => {
                lista.innerHTML = '';
                if (data.length === 0) { lista.innerHTML = '<p class="text-green-500 text-[10px] text-center font-black uppercase py-6 tracking-widest">✅ ACTIVO LIMPIO: Sin impacto.</p>'; } 
                else { data.forEach(item => {
                    const div = document.createElement('div');
                    div.className = "flex justify-between items-center bg-white/5 p-3 border-l-2 border-red-500/50 mb-1 text-[10px] font-black uppercase";
                    let status = item.tipo === 'unidad' ? `<span class="text-[var(--aoe-gold)]">${item.cantidad}x UNIDADES</span>` : `<span class="text-blue-400">PATENTE</span>`;
                    div.innerHTML = `<span class="text-white">${item.equipo}</span> ${status}`;
                    lista.appendChild(div);
                }); }
            });
        }
    </script>
</body>
</html>