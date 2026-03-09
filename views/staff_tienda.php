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
    $orden_aviones = ['Caza', 'Interceptor', 'Avion de Ataque', 'Bombardero'];

} catch (PDOException $e) {
    die("Fallo de enlace con el catálogo: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title><?php echo $txt['GLOBAL']['MANDO_STAFF']; ?> - Tienda</title>
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
            <button onclick="document.getElementById('modalEditorPrecios').classList.remove('hidden')" 
                    class="btn-m !bg-none !border-[var(--khaki-beige)] !text-[var(--parchment)] hover:!text-[var(--aoe-gold)] hover:!border-[var(--aoe-gold)]">
                ⚙️ <?php echo $txt['STAFF_TIENDA']['BTN_PRECIOS']; ?>
            </button>
            <button onclick="document.getElementById('modalNuevoVehiculo').classList.remove('hidden')" 
                    class="btn-m">
                ➕ <?php echo $txt['STAFF_TIENDA']['BTN_ANADIR']; ?>
            </button>
        </div>

        <div class="m-panel mb-8 p-4 bg-black/40 shadow-2xl backdrop-blur-md">
            <div class="flex gap-4 mb-4 border-b border-[var(--wood-border)] pb-4">
                <button id="btn_tipo_tanque" onclick="setFiltroTipo('tanque')" class="btn-m !text-[10px]">
                    <?php echo $txt['STAFF_TIENDA']['CAT_TANQUES']; ?>
                </button>
                <button id="btn_tipo_avion" onclick="setFiltroTipo('avion')" class="btn-m !text-[10px] grayscale opacity-70">
                    <?php echo $txt['STAFF_TIENDA']['CAT_AVIONES']; ?>
                </button>
            </div>
            
            <div class="flex items-center gap-4">
                <span class="text-[var(--aoe-gold)] text-[10px] font-black uppercase tracking-widest">
                    <?php echo $txt['STAFF_TIENDA']['FILTRO_PAIS']; ?>
                </span>
                <div id="contenedor_naciones" class="flex flex-wrap gap-2"></div>
            </div>
        </div>

        <div id="mensaje_vacio" class="m-panel p-10 text-center text-gray-500 italic font-bold uppercase tracking-widest hidden">
            <?php echo $txt['STAFF_TIENDA']['SIN_VEHICULOS']; ?>
        </div>

        <div id="cont_hangar" class="space-y-6">
            <?php foreach($catalogo_agrupado as $nacion => $tiers): ?>
                <div class="bloque-nacion" data-nacion="<?php echo htmlspecialchars($nacion); ?>">
                    <?php foreach($tiers as $tier => $tipos): ?>
                        <div class="tier-container border border-gray-800 bg-[#0a0a0a] shadow-lg mb-6">
                            <div class="tier-header p-3 px-6 cursor-pointer flex justify-between items-center transition hover:bg-black" onclick="toggleTier(this)">
                                <h2 class="text-white font-black uppercase text-lg tracking-[0.3em] m-0">RANGO / TIER <?php echo $tier; ?></h2>
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
                                                            <?php if($es_prem): ?><div class="tag-premium">PREMIUM</div><?php endif; ?>
                                                            <div class="tag-br">BR: <?php echo htmlspecialchars($item['br'] ?? '1.0'); ?></div>
                                                            
                                                            <div class="h-28 bg-[#0a0a0a] relative overflow-hidden border-b border-gray-800">
                                                                <img src="../<?php echo $item['imagen_url']; ?>" class="w-full h-full object-cover opacity-80">
                                                                <div class="absolute bottom-0 right-0 bg-black/80 px-2 py-0.5 text-[8px] text-gray-400 font-bold border-t border-l border-gray-800 uppercase">TIER <?php echo $item['rango']; ?></div>
                                                            </div>

                                                            <div class="p-3 flex-grow flex flex-col">
                                                                <h3 class="text-[11px] font-black text-white uppercase text-center truncate mb-2"><?php echo htmlspecialchars($item['nombre_vehiculo']); ?></h3>
                                                                
                                                                <div class="flex justify-center gap-1 mb-3">
                                                                    <span class="text-[8px] bg-blue-900/30 text-blue-400 px-1.5 py-0.5 rounded font-black uppercase"><?php echo htmlspecialchars($item['tipo']); ?></span>
                                                                    <span class="text-[8px] bg-gray-800 text-gray-400 px-1.5 py-0.5 rounded font-black uppercase"><?php echo $clase_nombre; ?></span>
                                                                </div>

                                                                <div class="grid grid-cols-3 gap-0 bg-black border border-gray-800 p-1.5 text-center rounded">
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
                                                            </div>

                                                            <div class="p-2 bg-black/80 flex gap-2 border-t border-gray-800">
                                                                <?php $item_json = htmlspecialchars(json_encode($item), ENT_QUOTES, "UTF-8"); ?>
                                                                <button type="button" onclick='abrirModalEditarTienda(<?php echo $item_json; ?>)' class="w-1/2 btn-m !bg-yellow-900/30 !text-yellow-500 border-yellow-700 font-black uppercase text-[9px] hover:bg-yellow-600 hover:text-black transition tracking-widest">EDITAR</button>
                                                                
                                                                <button type="button" onclick="abrirPurgaSeguridad(<?php echo $item['id']; ?>, '<?php echo addslashes($item['nombre_vehiculo']); ?>', '../<?php echo $item['imagen_url']; ?>')" class="w-1/2 btn-m !bg-red-950/30 !text-red-500 border-red-900 font-black uppercase text-[9px] hover:bg-red-700 hover:text-white transition tracking-widest">PURGAR</button>
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
                <h3 class="text-red-500 font-black text-lg mb-2 tracking-[0.2em] uppercase text-center border-b border-red-900/40 pb-2">⚠️ PROTOCOLO DE PURGA DE ACTIVOS</h3>
                <div class="flex gap-6 mb-6 mt-6 bg-red-950/10 p-4 border border-red-900/30">
                    <div class="w-1/3 h-24 bg-black border border-red-900/50 overflow-hidden"><img id="purga_img" src="" class="w-full h-full object-cover img-purga"></div>
                    <div class="w-2/3">
                        <span class="text-[9px] text-gray-500 font-bold uppercase tracking-widest block mb-1">Identificación del Activo:</span>
                        <h4 id="purga_nombre" class="text-white font-black text-2xl font-['Cinzel'] uppercase"></h4>
                        <p class="text-[10px] text-red-400 font-bold mt-2 uppercase tracking-tighter italic">¿Está seguro de eliminar este registro del sistema global?</p>
                    </div>
                </div>
                <div class="mb-8">
                    <span class="text-[10px] text-gray-500 font-black uppercase tracking-widest block mb-3">📡 ESCANEO DE IMPACTO EN FACCIONES:</span>
                    <div id="impacto_lista" class="max-h-40 overflow-y-auto space-y-2 bg-black/50 border border-gray-800 p-4 custom-scrollbar"></div>
                </div>
                <form action="../logic/procesar_tienda_staff.php" method="POST" class="grid grid-cols-2 gap-4">
                    <input type="hidden" name="accion" value="eliminar"><input type="hidden" id="purga_id" name="id">
                    <button type="submit" name="reembolsar" value="0" class="btn-m !bg-none !border-gray-700 !text-gray-500 hover:!text-white hover:!border-white !py-4 text-[10px] font-black uppercase">BORRAR SIN REEMBOLSO</button>
                    <button type="submit" name="reembolsar" value="1" class="btn-m !bg-red-800 !border-red-600 !text-white !py-4 text-[10px] font-black uppercase animate-pulse">PURGAR Y REEMBOLSAR A TODOS</button>
                </form>
            </div>
        </div>

        <div id="modalNuevoVehiculo" class="hidden fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-4">
            <div class="m-panel w-full max-w-lg relative border-[var(--aoe-gold)]">
                <button onclick="document.getElementById('modalNuevoVehiculo').classList.add('hidden')" class="absolute top-4 right-4 text-white font-bold text-xl">&times;</button>
                <h2 class="m-title text-2xl mb-6 border-b border-[var(--wood-border)] pb-2"><?php echo $txt['STAFF_TIENDA']['MODAL_ADD_TITULO']; ?></h2>
                <form action="../logic/procesar_tienda_staff.php" method="POST" enctype="multipart/form-data" onsubmit="return validarRegistroVehiculo(event)">
                    <input type="hidden" name="accion" value="agregar">
                    <div class="mb-4 flex gap-4">
                        <div class="w-1/2">
                            <label class="block text-[10px] text-gray-500 uppercase font-black mb-1">TIPO *</label>
                            <select id="tipo_vehiculo" name="tipo" onchange="actualizarSubtipos()" required class="m-input w-full outline-none">
                                <option value="tanque">Tanque</option><option value="avion">Avión</option>
                            </select>
                        </div>
                        <div class="w-1/2">
                            <label class="block text-[10px] text-gray-500 uppercase font-black mb-1">CLASE *</label>
                            <select id="subtipo_vehiculo" name="subtipo" onchange="actualizarPrecioPreview()" required class="m-input w-full outline-none"></select>
                        </div>
                    </div>
                    <div class="mb-4 flex gap-4">
                        <div class="w-1/2">
                            <label class="block text-[10px] text-gray-500 uppercase font-black mb-1">NACIÓN *</label>
                            <select name="nacion" required class="m-input w-full"><?php foreach ($lista_naciones as $nacion): ?><option value="<?php echo htmlspecialchars($nacion); ?>"><?php echo htmlspecialchars($nacion); ?></option><?php endforeach; ?></select>
                        </div>
                        <div class="w-1/4">
                            <label class="block text-[10px] text-gray-500 uppercase font-black mb-1">RANGO *</label>
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
                        <label class="block text-[10px] text-gray-500 uppercase font-black mb-1">NOMBRE *</label>
                        <input type="text" name="nombre_vehiculo" required placeholder="..." class="m-input w-full">
                    </div>
                    <div class="mb-6 flex items-center justify-between gap-4">
                        <div class="flex-grow">
                            <label class="block text-[9px] text-gray-500 uppercase font-black mb-1">IMAGEN *</label>
                            <input type="file" name="imagen" accept="image/*" required class="w-full text-[10px] border border-gray-800 p-2 bg-black/40">
                        </div>
                        <div class="flex-shrink-0 bg-black/50 p-3 border border-[#c5a059] flex items-center gap-2 mt-4">
                            <input type="checkbox" name="is_premium" value="1" class="w-4 h-4 accent-[#c5a059]"><span class="text-[10px] font-black text-[#c5a059] uppercase">PREMIUM</span>
                        </div>
                    </div>
                    <button type="submit" class="btn-m w-full py-4 font-black uppercase tracking-widest">AUTORIZAR REGISTRO</button>
                </form>
            </div>
        </div>

        <div id="modalEditorPrecios" class="hidden fixed inset-0 bg-black/90 z-[60] flex items-center justify-center p-4">
            <div class="m-panel w-full max-w-lg relative border-[var(--aoe-gold)]">
                <button onclick="document.getElementById('modalEditorPrecios').classList.add('hidden')" class="absolute top-4 right-4 text-white font-bold text-xl">&times;</button>
                <h2 class="m-title text-2xl mb-1 uppercase font-black italic"><?php echo $txt['STAFF_TIENDA']['MODAL_PR_TITULO']; ?></h2>
                <form action="../logic/actualizar_precio_unico.php" method="POST" class="space-y-6 mt-6">
                    <div class="grid grid-cols-3 gap-4">
                        <div><label class="stat-label block mb-1">TIPO</label><select id="edit_tipo" name="tipo" onchange="updateEditSubtipos()" class="m-input w-full text-[10px]"></select></div>
                        <div><label class="stat-label block mb-1">SUBTIPO</label><select id="edit_subtipo" name="subtipo" onchange="refreshEditValues()" class="m-input w-full text-[10px]"></select></div>
                        <div>
                            <label class="stat-label block mb-1">RANGO</label>
                            <select id="edit_rango" name="rango" onchange="refreshEditValues()" class="m-input w-full text-center font-black">
                                <?php for($i=1; $i<=8; $i++) echo "<option value='$i'>$i</option>"; ?>
                            </select>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between bg-black/50 p-3 border border-gray-800"><span class="text-[10px] font-black text-green-500 uppercase">DINERO ($)</span><input type="number" id="new_dinero" name="dinero" class="m-input w-32 text-right"></div>
                        <div class="flex items-center justify-between bg-black/50 p-3 border border-gray-800"><span class="text-[10px] font-black text-white uppercase">ACERO (T)</span><input type="number" id="new_acero" name="acero" class="m-input w-32 text-right"></div>
                        <div class="flex items-center justify-between bg-black/50 p-3 border border-gray-800"><span class="text-[10px] font-black text-yellow-500 uppercase">PETRÓLEO (L)</span><input type="number" id="new_petroleo" name="petroleo" class="m-input w-32 text-right"></div>
                    </div>
                    <button type="submit" class="btn-m w-full py-4 uppercase font-black tracking-widest"><?php echo $txt['STAFF_TIENDA']['BTN_ACTUALIZAR_PR']; ?></button>
                </form>
            </div>
        </div>

        <div id="modalErrorArchivo" class="hidden fixed inset-0 bg-black/90 z-[200] flex items-center justify-center p-4">
            <div class="m-panel w-full max-w-sm relative border-red-800 border-2">
                <h3 class="text-red-500 font-black text-lg mb-4 text-center">❌ ERROR DE CARGA</h3>
                <p id="errorArchivoMsg" class="text-gray-400 text-center uppercase mb-6"></p>
                <button onclick="document.getElementById('modalErrorArchivo').classList.add('hidden')" class="btn-m w-full">ENTENDIDO</button>
            </div>
        </div>

        <div id="modalEdit" class="hidden fixed inset-0 bg-black/90 z-[200] flex items-center justify-center p-4">
            <div class="m-panel border-[var(--aoe-gold)] w-full max-w-2xl relative shadow-2xl">
                <button onclick="document.getElementById('modalEdit').classList.add('hidden'); document.body.classList.remove('modal-active');" class="absolute top-4 right-4 text-gray-500 hover:text-white text-2xl">&times;</button>
                <h3 class="m-title text-xl mb-6 border-b border-[var(--wood-border)] pb-2 text-yellow-500">MODIFICAR ACTIVO DEL CATÁLOGO</h3>
                
                <form action="../logic/procesar_tienda_staff.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] text-gray-500 uppercase font-black mb-1">Nombre del Vehículo</label>
                            <input type="text" name="nombre_vehiculo" id="edit_nombre" required class="m-input w-full">
                        </div>
                        <div>
                            <label class="block text-[10px] text-gray-500 uppercase font-black mb-1">Nación Fabricante</label>
                            <select name="nacion" id="edit_nacion" class="m-input w-full uppercase font-black">
                                <?php foreach($lista_naciones as $n): ?><option value="<?php echo htmlspecialchars($n); ?>"><?php echo htmlspecialchars($n); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[10px] text-gray-500 uppercase font-black mb-1">Clase (Tipo)</label>
                            <select name="tipo" id="edit_tipo_v" onchange="actualizarSubtiposEdit()" class="m-input w-full uppercase">
                                <option value="tanque">Tanque</option>
                                <option value="avion">Avión</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] text-gray-500 uppercase font-black mb-1">Sub-clase</label>
                            <select name="subtipo" id="edit_subtipo_v" onchange="actualizarPrecioEditPreview()" class="m-input w-full uppercase"></select>
                        </div>
                        <div>
                            <label class="block text-[10px] text-gray-500 uppercase font-black mb-1">Tier / Rango</label>
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
                                <span class="text-[10px] font-black text-[#c5a059] uppercase">ES UNIDAD PREMIUM</span>
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
                        <p class="text-[8px] text-gray-600 mt-2">* Si sube un nuevo archivo, la fotografía anterior será destruida del servidor.</p>
                    </div>
                    
                    <button type="submit" class="btn-m w-full !bg-yellow-700 !text-black py-4 mt-6 text-xs tracking-[0.2em] hover:bg-yellow-500">APLICAR MODIFICACIONES</button>
                </form>
            </div>
        </div>

    </main>

    <script>
        const preciosBase = <?php echo $precios_json; ?>;
        const todasLasNaciones = <?php echo json_encode($lista_naciones); ?>;
        let filtroTipoActual = 'tanque';
        let filtroNacionActual = todasLasNaciones[0] || '';
        let vehiculoEnEdicion = null; // Memoria caché del vehículo

        function initTienda() { actualizarSubtipos(); updateEditSubtipos(); renderBotonesNaciones(); aplicarFiltrosTabla(); }

        function toggleTier(el) {
            const content = el.nextElementSibling;
            const arrow = el.querySelector('span');
            content.style.display = (content.style.display === 'none') ? 'block' : 'none';
            arrow.style.transform = (content.style.display === 'none') ? 'rotate(0deg)' : 'rotate(180deg)';
        }

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

        function setFiltroTipo(t) { filtroTipoActual = t; document.getElementById('btn_tipo_tanque').classList.toggle('grayscale', t !== 'tanque'); document.getElementById('btn_tipo_avion').classList.toggle('grayscale', t !== 'avion'); aplicarFiltrosTabla(); }

        function renderBotonesNaciones() {
            const cont = document.getElementById('contenedor_naciones');
            cont.innerHTML = '';
            todasLasNaciones.forEach(n => {
                const btn = document.createElement('button'); btn.innerText = n;
                btn.className = `px-4 py-1 text-[10px] font-black uppercase border transition ${n === filtroNacionActual ? 'bg-red-900 text-white border-red-500' : 'bg-black/50 text-gray-500 border-gray-800 hover:text-white'}`;
                btn.onclick = () => { filtroNacionActual = n; renderBotonesNaciones(); aplicarFiltrosTabla(); };
                cont.appendChild(btn);
            });
        }

        function aplicarFiltrosTabla() {
            let totalMostrados = 0;
            document.querySelectorAll('.bloque-nacion').forEach(bloque => {
                if(bloque.dataset.nacion === filtroNacionActual) {
                    bloque.style.display = 'block';
                    bloque.querySelectorAll('.seccion-tipo').forEach(sec => { sec.style.display = (sec.dataset.tipo === filtroTipoActual) ? 'block' : 'none'; });
                    bloque.querySelectorAll('.tier-container').forEach(tier => {
                        let tierTieneVisible = false;
                        tier.querySelectorAll('.clase-container').forEach(claseBlock => {
                            if (claseBlock.closest('.seccion-tipo').dataset.tipo === filtroTipoActual) {
                                const items = Array.from(claseBlock.querySelectorAll('.fila-vehiculo')).length > 0;
                                claseBlock.style.display = items ? 'block' : 'none';
                                if (items) tierTieneVisible = true;
                            }
                        });
                        tier.style.display = tierTieneVisible ? 'block' : 'none';
                        if(tierTieneVisible) totalMostrados++;
                    });
                } else { bloque.style.display = 'none'; }
            });
            document.getElementById('mensaje_vacio').classList.toggle('hidden', totalMostrados > 0);
        }

        function validarRegistroVehiculo(e) { if(!e.target.checkValidity()) { e.preventDefault(); alert("🚨 ERROR: Todos los campos con (*) son obligatorios."); return false; } return true; }

        function actualizarSubtipos() {
            const t = document.getElementById('tipo_vehiculo').value, sel = document.getElementById('subtipo_vehiculo');
            sel.innerHTML = '';
            let ops = t === 'tanque' ? ['Ligero', 'AAA', 'Mediano', 'Pesado', 'Caza Tanques'] : ['Caza', 'Interceptor', 'Bombardero', 'Avion de Ataque'];
            ops.forEach(o => sel.add(new Option(o, o)));
            actualizarPrecioPreview();
        }

        function actualizarPrecioPreview() {
            const t = document.getElementById('tipo_vehiculo').value, s = document.getElementById('subtipo_vehiculo').value, r = document.getElementById('rango_vehiculo').value;
            const data = preciosBase[t] && preciosBase[t][s] && preciosBase[t][s][r] ? preciosBase[t][s][r] : {dinero:0, acero:0, petroleo:0};
            document.getElementById('prev_dinero').innerText = '$' + data.dinero; document.getElementById('prev_acero').innerText = data.acero; document.getElementById('prev_petroleo').innerText = data.petroleo + 'L';
        }

        function updateEditSubtipos() {
            const t = document.getElementById('edit_tipo');
            if(t.options.length === 0) { t.add(new Option('Tanque', 'tanque')); t.add(new Option('Avión', 'avion')); }
            const sel = document.getElementById('edit_subtipo'); sel.innerHTML = '';
            let ops = t.value === 'tanque' ? ['Ligero', 'AAA', 'Mediano', 'Pesado', 'Caza Tanques'] : ['Caza', 'Interceptor', 'Bombardero', 'Avion de Ataque'];
            ops.forEach(o => sel.add(new Option(o, o))); refreshEditValues();
        }

        function refreshEditValues() {
            const t = document.getElementById('edit_tipo').value, s = document.getElementById('edit_subtipo').value, r = document.getElementById('edit_rango').value;
            const data = preciosBase[t] && preciosBase[t][s] && preciosBase[t][s][r] ? preciosBase[t][s][r] : {dinero:0, acero:0, petroleo:0};
            document.getElementById('new_dinero').value = data.dinero; document.getElementById('new_acero').value = data.acero; document.getElementById('new_petroleo').value = data.petroleo;
        }

        function abrirModalEditarTienda(item) {
            vehiculoEnEdicion = item; // Guardamos el vehículo actual en memoria
            
            document.getElementById('edit_id').value = item.id;
            document.getElementById('edit_nombre').value = item.nombre_vehiculo;
            document.getElementById('edit_nacion').value = item.nacion;
            document.getElementById('edit_tipo_v').value = item.tipo;
            
            // Rellenamos el costo ACTUAL en la tabla izquierda
            document.getElementById('edit_curr_cash').innerText = '$' + Number(item.costo_dinero).toLocaleString();
            document.getElementById('edit_curr_steel').innerText = Number(item.costo_acero).toLocaleString() + 'T';
            document.getElementById('edit_curr_fuel').innerText = Number(item.costo_petroleo).toLocaleString() + 'L';

            // Disparamos los dropdowns
            let claseActual = item.subtipo || item.clase; 
            actualizarSubtiposEdit(claseActual);

            document.getElementById('edit_rango_v').value = item.rango;
            document.getElementById('edit_br').value = item.br;
            document.getElementById('edit_premium').checked = (item.is_premium == 1);
            
            document.getElementById('modalEdit').classList.remove('hidden');
            document.body.classList.add('modal-active');
            
            // Forzamos el cálculo inicial de la columna derecha
            actualizarPrecioEditPreview();
        }

        function actualizarSubtiposEdit(valorPreseleccionado = null) {
            const tipo = document.getElementById('edit_tipo_v').value;
            const selectSubtipo = document.getElementById('edit_subtipo_v');
            selectSubtipo.innerHTML = '';
            
            let opciones = tipo === 'tanque' ? ['Ligero', 'AAA', 'Mediano', 'Pesado', 'Caza Tanques'] : ['Caza', 'Interceptor', 'Bombardero', 'Avion de Ataque'];
            
            opciones.forEach(opcion => {
                let opt = new Option(opcion, opcion);
                if (opcion === valorPreseleccionado) { opt.selected = true; }
                selectSubtipo.add(opt);
            });
            
            // Al cambiar de Tanque a Avión, recalculamos el precio visualmente
            actualizarPrecioEditPreview();
        }

        function actualizarPrecioEditPreview() {
            if (!vehiculoEnEdicion) return;

            // Leemos qué seleccionó el Staff ahora mismo
            const t = document.getElementById('edit_tipo_v').value;
            const s = document.getElementById('edit_subtipo_v').value;
            const r = document.getElementById('edit_rango_v').value;
            
            // Buscamos en precios.php (exportado a JS) el nuevo valor
            const data = preciosBase[t] && preciosBase[t][s] && preciosBase[t][s][r] ? preciosBase[t][s][r] : {dinero:0, acero:0, petroleo:0};
            
            // Actualizamos la columna derecha
            document.getElementById('edit_new_cash').innerText = '$' + Number(data.dinero).toLocaleString();
            document.getElementById('edit_new_steel').innerText = Number(data.acero).toLocaleString() + 'T';
            document.getElementById('edit_new_fuel').innerText = Number(data.petroleo).toLocaleString() + 'L';

            // Comparamos: ¿El nuevo precio es diferente al viejo?
            const precioCambio = (
                Number(data.dinero) !== Number(vehiculoEnEdicion.costo_dinero) ||
                Number(data.acero) !== Number(vehiculoEnEdicion.costo_acero) ||
                Number(data.petroleo) !== Number(vehiculoEnEdicion.costo_petroleo)
            );

            // Si cambió, encendemos la alarma visual
            document.getElementById('edit_price_warning').classList.toggle('hidden', !precioCambio);
        }
    </script>
</body>
</html>