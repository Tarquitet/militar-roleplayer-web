<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') { header("Location: ../login.php"); exit(); }
require_once '../config/conexion.php';
$root_path = "../";
$txt = require '../config/textos.php';
$equipo_id = (int)$_GET['id'];

try {
    $stmt_eq = $pdo->prepare("SELECT nombre_equipo, dinero, acero, petroleo FROM cuentas WHERE id = :id");
    $stmt_eq->execute([':id' => $equipo_id]);
    $equipo = $stmt_eq->fetch(PDO::FETCH_ASSOC);

    // Obtener naciones para los filtros
    $stmt_naciones = $pdo->query("SELECT nombre FROM naciones ORDER BY nombre ASC");
    $lista_naciones = $stmt_naciones->fetchAll(PDO::FETCH_COLUMN);

    // Obtener el inventario real del equipo
    $stmt_inv = $pdo->prepare("SELECT id as inv_id, catalogo_id, cantidad as stock_actual FROM inventario WHERE cuenta_id = :id AND cantidad > 0");
    $stmt_inv->execute([':id' => $equipo_id]);
    $inventario = $stmt_inv->fetchAll(PDO::FETCH_ASSOC);

    // --- FLOTAS X EQUIPO ---
    $stmt_f = $pdo->prepare("SELECT * FROM flotas WHERE cuenta_id = :id ORDER BY slot ASC");
    $stmt_f->execute([':id' => $equipo_id]);
    $flotas_listado = $stmt_f->fetchAll(PDO::FETCH_ASSOC);
    
    $mis_flotas = [1 => null, 2 => null, 3 => null];
    foreach($flotas_listado as $f) { $mis_flotas[$f['slot']] = $f; }
    // ------------------

    // Obtener patentes del equipo
    $stmt_p = $pdo->prepare("SELECT id as plano_id, catalogo_id FROM planos_desbloqueados WHERE cuenta_id = :id");
    $stmt_p->execute([':id' => $equipo_id]);
    $planos_pagados = $stmt_p->fetchAll(PDO::FETCH_ASSOC);

    // Obtener catálogo completo para cruzar datos
    $stmt_cat = $pdo->query("SELECT * FROM catalogo_tienda ORDER BY nacion ASC, rango ASC, CAST(br AS DECIMAL(10,1)) ASC");
    $catalogo_db = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

    // Cruzar Catálogo Global con el Inventario del Equipo
    $catalogo_equipo = [];
    foreach($catalogo_db as $cn) {
        $id_cat = $cn['id'];
        
        // Revisar si tiene el vehículo
        $cn['stock_actual'] = 0;
        $cn['inv_id'] = null;
        foreach($inventario as $inv) {
            if($inv['catalogo_id'] == $id_cat) {
                $cn['stock_actual'] = $inv['stock_actual'];
                $cn['inv_id'] = $inv['inv_id']; break;
            }
        }
        
        // Revisar si tiene la patente
        $cn['tiene_patente'] = false;
        $cn['plano_id'] = null;
        foreach($planos_pagados as $p) {
            if($p['catalogo_id'] == $id_cat) {
                $cn['tiene_patente'] = true;
                $cn['plano_id'] = $p['plano_id']; break;
            }
        }

        $nacion = $cn['nacion'];
        $tier = $cn['rango'] ?? 1;
        $tipo = $cn['tipo'] ?? 'tanque';
        $clase = !empty($cn['subtipo']) ? $cn['subtipo'] : (!empty($cn['clase']) ? $cn['clase'] : 'No Clasificado'); 
        $catalogo_equipo[$nacion][$tier][$tipo][$clase][] = $cn;
    }

    $orden_tanques = ['Ligero', 'Mediano', 'Pesado', 'Caza Tanques', 'AAA'];
    $orden_aviones = ['Caza', 'Interceptor', 'Avion de Ataque', 'Bombardero'];

} catch (PDOException $e) { die("Fallo: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title><?php echo $txt['STAFF_VER_INVENTARIO']['TITULO_PAGINA_GESTION']; ?></title>
    <?php include '../includes/head.php'; ?>
    <style>
        .modal-active { overflow: hidden; }
        .glass-panel { background: rgba(13, 14, 10, 0.98); backdrop-filter: blur(15px); border: 1px solid rgba(197, 160, 89, 0.3); }
        .terminal-input { background: rgba(0,0,0,0.6); border: 1px solid #444; color: #fff; padding: 10px; width: 100%; text-align: center; font-family: 'Space Mono', monospace; }
        .stat-label { font-size: 9px; color: rgba(255,255,255,0.4); font-weight: 900; text-transform: uppercase; letter-spacing: 2px; }
        .fleet-row { background: rgba(255,255,255,0.02); border: 1px solid #222; transition: 0.3s; }
        .unit-pill { font-size: 10px; color: #fff; font-weight: 700; text-transform: uppercase; background: #000; padding: 4px 10px; border: 1px solid #1a1a1a; }
        .btn-close-modal { position: absolute; top: 15px; right: 15px; width: 32px; height: 32px; background: #222; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 1px solid #333; font-size: 18px; z-index: 200; }
        .input-error { color: #ff0000 !important; border-color: #ff0000 !important; }
        
        .tier-container { border: 1px solid #991b1b; background: #050505; margin-bottom: 1.5rem; }
        .tier-header { background: url('https://www.transparenttextures.com/patterns/diagmonds-light.png'), #000; border-bottom: 2px solid #ef4444; cursor: pointer; padding: 12px 24px; display: flex; justify-content: space-between; align-items: center; }
        .tier-header h2 { color: #ef4444; font-weight: 900; text-transform: uppercase; letter-spacing: 3px; font-size: 1.2rem; margin: 0; text-shadow: 2px 2px 0 #000; }
        
        .custom-scrollbar::-webkit-scrollbar { height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #991b1b; border-radius: 10px; }
        .tag-premium { position: absolute; top: 0; right: 0; background: #c5a059; color: #000; font-size: 9px; font-weight: 900; padding: 3px 8px; z-index: 10; text-transform: uppercase; }
        .tag-br { position: absolute; top: 0; left: 0; background: #000; border-right: 1px solid #333; border-bottom: 1px solid #333; color: #fff; font-size: 10px; font-weight: 900; padding: 3px 8px; z-index: 10; font-family: monospace; }
        .card-premium { border-color: #c5a059 !important; box-shadow: inset 0 0 15px rgba(197, 160, 89, 0.15); }
        .stat-grid-label { font-size: 7px; color: #555; font-weight: 900; text-transform: uppercase; }
        .stat-grid-value { font-size: 9px; font-weight: 900; font-family: 'Space Mono', monospace; }
        
        .not-owned .veh-img { filter: grayscale(1) opacity(0.3); }

        .slot-box { border: 2px dashed #222; height: auto; min-height: 400px; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #090909; transition: 0.3s; }
        .slot-box.filled { border-style: solid; border-color: #1a1a1a; justify-content: flex-start; padding: 25px; }
    </style>
</head>
<body class="bg-[#0a0b08] text-[var(--text-main)] min-h-screen pb-20" onload="initFiltros()">
    <?php include '../includes/nav_staff.php'; ?>

    <main class="p-8 max-w-[1600px] mx-auto">
        <div class="mb-12 flex justify-between items-center border-b border-white/5 pb-8">
            <a href="staff_dashboard.php" class="btn-m !bg-none !border-white/10 !text-gray-500 hover:!text-[var(--aoe-gold)] !py-2 !px-6 text-[10px] uppercase font-black transition"><?php echo $txt['STAFF_VER_INVENTARIO']['BTN_VOLVER']; ?></a>
            <div class="text-right">
                <span class="stat-label"><?php echo $txt['STAFF_VER_INVENTARIO']['LBL_FONDOS_EQUIPO']; ?></span>
                <div class="flex gap-6 mt-1 font-black text-lg">
                    <span class="text-green-500">$<?php echo number_format($equipo['dinero']); ?></span>
                    <span class="text-white"><?php echo number_format($equipo['acero']); ?>T</span>
                    <span class="text-yellow-500"><?php echo number_format($equipo['petroleo']); ?>L</span>
                </div>
            </div>
        </div>

        <h1 class="text-3xl font-black mb-8 uppercase italic text-white"><?php echo $txt['STAFF_VER_INVENTARIO']['TITULO_GESTION']; ?> <span class="text-[var(--aoe-gold)]"><?php echo htmlspecialchars($equipo['nombre_equipo']); ?></span></h1>

        <div class="m-panel mb-8 p-4 bg-black/40 shadow-2xl border-white/5">
            <div class="flex gap-4 mb-4 border-b border-white/5 pb-4">
                <button id="btn_tipo_tanque" onclick="setFiltroTipo('tanque')" class="btn-m !text-[10px]"><?php echo $txt['STAFF_VER_INVENTARIO']['CAT_TANQUES']; ?></button>
                <button id="btn_tipo_avion" onclick="setFiltroTipo('avion')" class="btn-m !text-[10px] grayscale opacity-70"><?php echo $txt['STAFF_VER_INVENTARIO']['CAT_AVIONES']; ?></button>
                <button id="btn_tipo_flotas" onclick="setFiltroTipo('flotas')" class="btn-m !text-[10px] grayscale opacity-70"><?php echo $txt['STAFF_VER_INVENTARIO']['CAT_FLOTAS']; ?></button>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-[var(--aoe-gold)] text-[10px] font-black uppercase tracking-widest"><?php echo $txt['STAFF_VER_INVENTARIO']['LBL_NACION']; ?></span>
                <div id="contenedor_naciones" class="flex flex-wrap gap-2"></div>
            </div>
        </div>

        <div id="mensaje_vacio" class="m-panel mb-8 p-10 bg-[#0a0a0a] border border-gray-800 text-center hidden">
            <span class="text-gray-600 font-black uppercase tracking-widest text-[11px]"><?php echo $txt['STAFF_VER_INVENTARIO']['SIN_ACTIVOS_CAT']; ?></span>
        </div>

        <div class="mb-20">
            <div id="cont_hangar" class="space-y-6">
                <?php foreach($catalogo_equipo as $nacion => $tiers): ?>
                    <div class="bloque-nacion" data-nacion="<?php echo htmlspecialchars($nacion); ?>">
                        <?php foreach($tiers as $tier => $tipos): ?>
                            <div class="tier-container shadow-2xl">
                                <div class="tier-header" onclick="toggleTier(this)">
                                    <h2><?php echo $txt['STAFF_VER_INVENTARIO']['LBL_TIER']; ?> <?php echo $tier; ?></h2>
                                    <span class="text-red-500 font-bold">▼</span>
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
                                                    <h3 class="text-gray-600 font-black uppercase text-[10px] tracking-widest border-b border-white/5 pb-1 mb-4"><?php echo $clase_nombre; ?></h3>
                                                    <div class="flex gap-4 overflow-x-auto pb-4 custom-scrollbar">
                                                        <?php foreach($tipos[$tipo_vehiculo][$clase_nombre] as $i): 
                                                            $i_json = htmlspecialchars(json_encode($i), ENT_QUOTES, 'UTF-8'); 
                                                            $es_prem = isset($i['is_premium']) && $i['is_premium'] == 1;
                                                            $tiene_algo = ($i['stock_actual'] > 0 || $i['tiene_patente']);
                                                        ?>
                                                            <div class="flex-shrink-0 w-64 flex flex-col bg-[#111] border <?php echo $es_prem ? 'card-premium' : 'border-gray-800'; ?> relative hover:brightness-110 transition <?php echo !$tiene_algo ? 'not-owned' : ''; ?>">
                                                                <?php if($es_prem): ?><div class="tag-premium"><?php echo $txt['STAFF_VER_INVENTARIO']['TAG_PREMIUM']; ?></div><?php endif; ?>
                                                                <div class="tag-br"><?php echo $txt['STAFF_VER_INVENTARIO']['TAG_BR']; ?> <?php echo htmlspecialchars($i['br'] ?? '1.0'); ?></div>
                                                                <div class="h-28 bg-black overflow-hidden"><img src="../<?php echo $i['imagen_url']; ?>" class="w-full h-full object-cover veh-img"></div>
                                                                <div class="p-3 flex-grow flex flex-col">
                                                                    <span class="text-[11px] text-white font-black uppercase block mb-2 truncate text-center"><?php echo htmlspecialchars($i['nombre_vehiculo']); ?></span>
                                                                    
                                                                    <div class="grid grid-cols-3 gap-0 bg-black border border-gray-800 p-1 text-center rounded mt-auto mb-3">
                                                                        <div class="border-r border-gray-800"><span class="stat-grid-label block"><?php echo $txt['STAFF_VER_INVENTARIO']['LBL_CASH']; ?></span><span class="stat-grid-value text-green-500">$<?php echo number_format($i['costo_dinero']); ?></span></div>
                                                                        <div class="border-r border-gray-800"><span class="stat-grid-label block"><?php echo $txt['STAFF_VER_INVENTARIO']['LBL_STEEL']; ?></span><span class="stat-grid-value text-white"><?php echo number_format($i['costo_acero']); ?>T</span></div>
                                                                        <div><span class="stat-grid-label block"><?php echo $txt['STAFF_VER_INVENTARIO']['LBL_FUEL']; ?></span><span class="stat-grid-value text-yellow-500"><?php echo number_format($i['costo_petroleo']); ?>L</span></div>
                                                                    </div>
                                                                </div>

                                                                <div class="flex flex-col border-t border-gray-800">
                                                                    <?php if($i['tiene_patente']): ?>
                                                                        <button onclick='abrirModalPatente(<?php echo $i_json; ?>)' class="bg-red-950/20 text-red-500 py-2 text-[9px] font-black uppercase hover:bg-red-900 hover:text-white transition"><?php echo $txt['STAFF_VER_INVENTARIO']['BTN_ELIMINAR_PATENTE']; ?></button>
                                                                    <?php else: ?>
                                                                        <button onclick='abrirModalOtorgar(<?php echo $i_json; ?>, "patente")' class="bg-blue-900/20 text-blue-400 py-2 text-[9px] font-black uppercase hover:bg-blue-600 hover:text-white transition"><?php echo $txt['STAFF_VER_INVENTARIO']['BTN_OTORGAR_PATENTE']; ?></button>
                                                                    <?php endif; ?>

                                                                    <?php if($i['stock_actual'] > 0): ?>
                                                                        <button onclick='abrirModalVehiculo(<?php echo $i_json; ?>)' class="bg-yellow-900/20 text-yellow-500 border-t border-gray-800 py-2 text-[9px] font-black uppercase hover:bg-yellow-600 hover:text-black transition"><?php echo $txt['STAFF_VER_INVENTARIO']['BTN_UNIDADES_FISICAS']; ?> <?php echo $i['stock_actual']; ?>x</button>
                                                                    <?php else: ?>
                                                                        <button onclick='abrirModalOtorgar(<?php echo $i_json; ?>, "vehiculo")' class="bg-green-900/20 text-green-500 border-t border-gray-800 py-2 text-[9px] font-black uppercase hover:bg-green-700 hover:text-white transition"><?php echo $txt['STAFF_VER_INVENTARIO']['BTN_DAR_VEHICULO']; ?></button>
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
        </div>

        <div id="cont_flotas" class="hidden grid grid-cols-1 md:grid-cols-3 gap-10 mt-8">
                <?php for($s=1; $s<=3; $s++): $fl = $mis_flotas[$s]; ?>
                    <div class="slot-box relative <?php echo $fl ? 'filled' : ''; ?>">
                        <?php if(!$fl): ?>
                            <span class="text-[11px] font-black text-gray-700 uppercase tracking-[0.3em]">SLOT <?php echo $s; ?><?php echo $txt['STAFF_VER_INVENTARIO']['FLT_VACIO']; ?></span>
                        <?php else: ?>
                            
                            <form action="../logic/borrar_flota_staff.php" method="POST" class="absolute top-4 right-4 z-10">
                                <input type="hidden" name="flota_id" value="<?php echo $fl['id']; ?>">
                                <input type="hidden" name="lider_id" value="<?php echo $equipo_id; ?>">
                                <button type="button" onclick="prepararDestruccionFlota(<?php echo $fl['id']; ?>)" class="absolute top-4 right-4 z-10 bg-black/80 text-red-500 border border-red-900/50 px-3 py-1 text-[8px] font-black uppercase hover:bg-red-600 hover:text-white transition">
                                DESTRUIR
                                </button>
                            </form>
                            
                            <div class="w-full text-center border-b border-yellow-900/20 pb-4 mb-8 text-[#c5a059] font-black text-[11px] uppercase tracking-widest"><?php echo $txt['STAFF_VER_INVENTARIO']['FLT_ACTIVO']; ?><?php echo $s; ?></div>
                            <div class="w-full space-y-6">
                                <div><span class="stat-label block mb-1">U<?php echo $txt['STAFF_VER_INVENTARIO']['FLT_INSIGNIA']; ?></span><div class="bg-black p-3 border border-white/5 text-white font-bold uppercase text-sm"><?php echo htmlspecialchars($fl['insignia']); ?></div></div>
                                <div class="grid grid-cols-2 gap-3 text-gray-400 uppercase text-[10px]">
                                    <?php for($esc=1;$esc<=4;$esc++): ?>
                                        <div><span class="stat-label block mb-1"><?php echo $txt['STAFF_VER_INVENTARIO']['FLT_ESCOLTAS']; ?><?php echo $esc; ?></span><div class="bg-black p-2 border border-white/5"><?php echo htmlspecialchars($fl['escolta_'.$esc] ?: '-'); ?></div></div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
    </main>

    <div id="modalDestruirFlota" class="hidden fixed inset-0 bg-black/98 z-[300] flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="m-panel w-full max-w-sm border-red-600 bg-[#0a0a0a] p-8 text-center relative shadow-2xl">
            <button type="button" onclick="cerrarModal('modalDestruirFlota')" class="btn-close-modal">&times;</button>
            <div class="text-red-600 text-5xl mb-4"><?php echo $txt['STAFF_VER_INVENTARIO']['FLT_DESTRUIR_ICON']; ?></div>
            <h2 class="text-white font-black uppercase tracking-[0.2em] mb-4 text-sm"><?php echo $txt['STAFF_VER_INVENTARIO']['MODAL_ANIQ_FLOTA']; ?></h2>
            <p class="text-gray-400 text-xs font-bold leading-relaxed mb-8 uppercase"><?php echo $txt['STAFF_VER_INVENTARIO']['CONFIRM_FLOTA_DESC']; ?></p>
            
            <form action="../logic/borrar_flota_staff.php" method="POST">
                <input type="hidden" name="flota_id" id="form_flota_id_destruir">
                <input type="hidden" name="lider_id" value="<?php echo $equipo_id; ?>">
                <div class="flex gap-4">
                    <button type="submit" class="flex-1 bg-red-600 text-black py-3 font-black uppercase text-[10px] hover:bg-red-500 transition tracking-widest"><?php echo $txt['STAFF_VER_INVENTARIO']['BTN_FLT_CONFIRMAR']; ?></button>
                    <button type="button" onclick="cerrarModal('modalDestruirFlota')" class="flex-1 border border-gray-600 text-gray-500 py-3 font-black uppercase text-[10px] hover:bg-gray-800 hover:text-white transition tracking-widest"><?php echo $txt['STAFF_VER_INVENTARIO']['BTN_FLT_CANCELAR']; ?></button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalPatente" class="hidden fixed inset-0 bg-black/98 z-[200] flex items-center justify-center p-4 backdrop-blur-md">
        <div class="m-panel w-full max-w-md glass-panel p-10 border-red-500/30 relative">
            <button onclick="cerrarModal('modalPatente')" class="btn-close-modal">&times;</button>
            <h2 class="text-red-500 font-black text-center text-[10px] uppercase mb-8 tracking-[0.3em]"><?php echo $txt['STAFF_VER_INVENTARIO']['MODAL_PURGA_PATENTE_TIT']; ?></h2>
            <div class="text-center mb-6"><span id="p_nombre" class="text-white font-black text-3xl uppercase font-['Cinzel']"></span></div>
            <form action="../logic/procesar_reembolso_staff.php" method="POST">
                <input type="hidden" name="tipo" value="plano">
                <input type="hidden" name="target_id" id="p_target_id">
                <input type="hidden" name="equipo_id" value="<?php echo $equipo_id; ?>">
                
                <div class="bg-black/40 p-4 border border-white/10 mb-2"><label class="flex items-center gap-4 cursor-pointer"><input type="checkbox" name="reembolsar" value="1" id="p_check_reembolso" checked onchange="togglePRef()" class="w-5 h-5 accent-green-500"><span class="text-[10px] font-black text-gray-300 uppercase"><?php echo $txt['STAFF_VER_INVENTARIO']['LBL_DEV_DINERO']; ?></span></label></div>
                <div id="p_vehiculos_container" class="bg-red-950/20 p-4 border border-red-900/30 mb-8 hidden"><label class="flex items-center gap-4 cursor-pointer"><input type="checkbox" name="purgar_vehiculos" value="1" id="p_check_vehiculos" onchange="togglePRef()" class="w-5 h-5 accent-red-500"><span class="text-[10px] font-black text-red-400 uppercase"><?php echo $txt['STAFF_VER_INVENTARIO']['LBL_PURGAR_VEH_1']; ?><span id="p_stock_txt" class="text-white"></span><?php echo $txt['STAFF_VER_INVENTARIO']['LBL_PURGAR_VEH_2']; ?></span></label></div>
                
                <div id="p_preview" class="space-y-2 mb-10 text-[10px] font-mono font-black transition-opacity">
                    <div class="flex justify-between p-2 bg-white/5 border border-white/5"><span><?php echo $txt['STAFF_VER_INVENTARIO']['LBL_CASH']; ?></span><div class="flex gap-2"><span id="p_d_old" class="text-gray-500"></span><span id="p_d_add" class="text-green-500"></span><span class="text-white">→</span><span id="p_d_new" class="text-green-500"></span></div></div>
                    <div class="flex justify-between p-2 bg-white/5 border border-white/5"><span><?php echo $txt['STAFF_VER_INVENTARIO']['LBL_STEEL']; ?></span><div class="flex gap-2"><span id="p_a_old" class="text-gray-500"></span><span id="p_a_add" class="text-white"></span><span class="text-white">→</span><span id="p_a_new" class="text-white"></span></div></div>
                    <div class="flex justify-between p-2 bg-white/5 border border-white/5"><span><?php echo $txt['STAFF_VER_INVENTARIO']['LBL_FUEL']; ?></span><div class="flex gap-2"><span id="p_p_old" class="text-gray-500"></span><span id="p_p_add" class="text-yellow-500"></span><span class="text-white">→</span><span id="p_p_new" class="text-yellow-500"></span></div></div>
                </div>
                <div class="grid grid-cols-2 gap-4"><button type="submit" class="bg-red-600 text-black py-5 font-black uppercase text-[11px] hover:bg-red-500 transition"><?php echo $txt['STAFF_VER_INVENTARIO']['BTN_CONFIRMAR']; ?></button><button type="button" onclick="cerrarModal('modalPatente')" class="border border-white/10 text-gray-500 py-5 font-black uppercase"><?php echo $txt['STAFF_VER_INVENTARIO']['BTN_ABORTAR']; ?></button></div>
            </form>
        </div>
    </div>

    <div id="modalVehiculo" class="hidden fixed inset-0 bg-black/98 z-[200] flex items-center justify-center p-4 backdrop-blur-md">
        <div class="m-panel w-full max-w-lg glass-panel p-10 border-red-500/30 relative">
            <button onclick="cerrarModal('modalVehiculo')" class="btn-close-modal">&times;</button>
            <h2 class="text-red-500 font-black text-center text-[10px] uppercase mb-6 tracking-[0.3em]"><?php echo $txt['STAFF_VER_INVENTARIO']['MODAL_GESTION_VEHICULO']; ?></h2>
            <div class="bg-black/40 p-4 border border-white/5 mb-6 text-center">
                <span id="v_nombre" class="text-white font-black text-2xl uppercase font-['Cinzel'] block"></span>
                <span class="text-[9px] text-gray-500 uppercase tracking-widest"><?php echo $txt['STAFF_VER_INVENTARIO']['LBL_STOCK_FISICO']; ?> <span id="v_max_txt" class="text-white font-bold"></span></span>
            </div>
            
            <div class="mb-8 border border-blue-900/30 bg-blue-950/10 p-4 text-center">
                <span class="block text-[9px] text-blue-400 font-black uppercase tracking-widest mb-3"><?php echo $txt['STAFF_VER_INVENTARIO']['LBL_AJUSTE_RAPIDO']; ?></span>
                <div class="flex gap-4">
                    <form action="../logic/modificar_stock_rapido.php" method="POST" class="w-1/2">
                        <input type="hidden" name="inv_id" id="v_inv_id_restar">
                        <input type="hidden" name="equipo_id" value="<?php echo $equipo_id; ?>">
                        <input type="hidden" name="accion" value="restar">
                        <button type="submit" class="w-full bg-black hover:bg-red-900 text-gray-400 hover:text-white py-3 font-black text-[11px] border border-gray-800 transition"><?php echo $txt['STAFF_VER_INVENTARIO']['BTN_RESTAR_UNIDAD']; ?></button>
                    </form>
                    <form action="../logic/modificar_stock_rapido.php" method="POST" class="w-1/2">
                        <input type="hidden" name="catalogo_id" id="v_cat_id_sumar">
                        <input type="hidden" name="inv_id" id="v_inv_id_sumar">
                        <input type="hidden" name="equipo_id" value="<?php echo $equipo_id; ?>">
                        <input type="hidden" name="accion" value="sumar">
                        <button type="submit" class="w-full bg-blue-900/30 hover:bg-blue-600 text-blue-400 hover:text-white py-3 font-black text-[11px] border border-blue-900 transition"><?php echo $txt['STAFF_VER_INVENTARIO']['BTN_SUMAR_UNIDAD']; ?></button>
                    </form>
                </div>
            </div>

            <form action="../logic/procesar_reembolso_staff.php" method="POST" class="border-t border-red-900/30 pt-6">
                <span class="block text-[9px] text-red-500 font-black uppercase tracking-widest mb-3 text-center"><?php echo $txt['STAFF_VER_INVENTARIO']['LBL_PURGA_OFICIAL']; ?></span>
                <input type="hidden" name="tipo" value="vehiculo">
                <input type="hidden" name="target_id" id="v_target_id">
                <input type="hidden" name="equipo_id" value="<?php echo $equipo_id; ?>">
                <input type="hidden" name="cantidad_final" id="v_qty_form">
                
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div><label class="stat-label block mb-2"><?php echo $txt['STAFF_VER_INVENTARIO']['LBL_CANT_PURGAR']; ?></label><input type="number" id="v_qty" value="1" min="1" oninput="calcVImpact()" class="terminal-input text-2xl font-black text-red-500"><div id="v_error_msg" class="text-red-500 text-[9px] font-black uppercase text-center mt-2 hidden animate-pulse"><?php echo $txt['STAFF_VER_INVENTARIO']['ERR_SUPERA_STOCK']; ?></div></div>
                    <div class="flex flex-col justify-end"><label class="flex items-center gap-3 cursor-pointer bg-black/40 p-3 border border-white/10 h-[50px]"><input type="checkbox" name="reembolsar" value="1" id="v_check" checked onchange="calcVImpact()" class="w-4 h-4 accent-green-500"><span class="text-[8px] font-black text-gray-400 uppercase leading-tight"><?php echo $txt['STAFF_VER_INVENTARIO']['LBL_DEV_FONDOS']; ?></span></label></div>
                </div>
                <div id="v_preview" class="space-y-2 mb-10 text-[10px] font-mono font-black transition-opacity">
                    <div class="flex justify-between p-2 bg-white/5 border border-white/5"><span><?php echo $txt['STAFF_VER_INVENTARIO']['LBL_CASH']; ?></span><div class="flex gap-2"><span id="v_d_old" class="text-gray-500"></span><span id="v_d_add" class="text-green-500"></span><span class="text-white">→</span><span id="v_d_new" class="text-green-500"></span></div></div>
                    <div class="flex justify-between p-2 bg-white/5 border border-white/5"><span><?php echo $txt['STAFF_VER_INVENTARIO']['LBL_STEEL']; ?></span><div class="flex gap-2"><span id="v_a_old" class="text-gray-500"></span><span id="v_a_add" class="text-white"></span><span class="text-white">→</span><span id="v_a_new" class="text-white"></span></div></div>
                    <div class="flex justify-between p-2 bg-white/5 border border-white/5"><span><?php echo $txt['STAFF_VER_INVENTARIO']['LBL_FUEL']; ?></span><div class="flex gap-2"><span id="v_p_old" class="text-gray-500"></span><span id="v_p_add" class="text-yellow-500"></span><span class="text-white">→</span><span id="v_p_new" class="text-yellow-500"></span></div></div>
                </div>
                <div class="grid grid-cols-2 gap-4"><button type="submit" id="btn_v_confirm" class="bg-red-600 text-black py-5 font-black uppercase text-[11px] hover:bg-red-500 transition"><?php echo $txt['STAFF_VER_INVENTARIO']['BTN_EJECUTAR_PURGA']; ?></button><button type="button" onclick="cerrarModal('modalVehiculo')" class="border border-white/10 text-gray-500 py-5 font-black uppercase text-[10px]"><?php echo $txt['STAFF_VER_INVENTARIO']['BTN_ABORTAR']; ?></button></div>
            </form>
        </div>
    </div>

    <div id="modalOtorgar" class="hidden fixed inset-0 bg-black/98 z-[200] flex items-center justify-center p-4 backdrop-blur-md">
        <div class="m-panel w-full max-w-md glass-panel p-10 border-green-500/30 relative">
            <button onclick="cerrarModal('modalOtorgar')" class="btn-close-modal">&times;</button>
            <h2 id="mo_titulo" class="text-green-500 font-black text-center text-[10px] uppercase mb-8 tracking-[0.3em]"><?php echo $txt['STAFF_VER_INVENTARIO']['MODAL_OTORGAR_TITULO']; ?></h2>
            <div class="text-center mb-6"><span id="mo_nombre" class="text-white font-black text-2xl uppercase font-['Cinzel']"></span></div>
            
            <form action="../logic/procesar_otorgar_staff.php" method="POST">
                <input type="hidden" name="catalogo_id" id="mo_cat_id">
                <input type="hidden" name="equipo_id" value="<?php echo $equipo_id; ?>">
                <input type="hidden" name="tipo_entrega" id="mo_tipo_entrega">
                
                <div id="mo_unidades_div" class="mb-6 hidden">
                    <label class="stat-label block mb-2 text-center"><?php echo $txt['STAFF_VER_INVENTARIO']['LBL_CANT_OTORGAR']; ?></label>
                    <input type="number" name="cantidad" value="1" min="1" class="terminal-input text-2xl font-black text-green-500 w-full">
                </div>

                <div class="bg-black/40 p-4 border border-white/10 mb-8">
                    <label class="flex items-center gap-4 cursor-pointer">
                        <input type="checkbox" name="cobrar" value="1" class="w-5 h-5 accent-red-500">
                        <span class="text-[10px] font-black text-gray-300 uppercase"><?php echo $txt['STAFF_VER_INVENTARIO']['LBL_COBRAR_VALOR']; ?></span>
                    </label>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <button type="submit" class="bg-green-600 text-black py-5 font-black uppercase text-[11px] hover:bg-green-500 transition"><?php echo $txt['STAFF_VER_INVENTARIO']['BTN_CONFIRMAR_ENTREGA']; ?></button>
                    <button type="button" onclick="cerrarModal('modalOtorgar')" class="border border-white/10 text-gray-500 py-5 font-black uppercase"><?php echo $txt['STAFF_VER_INVENTARIO']['BTN_ABORTAR']; ?></button>
                </div>
            </form>
        </div>
    </div>



    <script>
        const txtJS = {
            otorPatente: "<?php echo $txt['STAFF_VER_INVENTARIO']['JS_OTORGAR_PATENTE']; ?>",
            darUnidades: "<?php echo $txt['STAFF_VER_INVENTARIO']['JS_DAR_UNIDADES']; ?>",
            sinNaciones: "<?php echo $txt['STAFF_VER_INVENTARIO']['JS_SIN_NACIONES']; ?>"
        };

        const resEq = { dinero: <?php echo $equipo['dinero']; ?>, acero: <?php echo $equipo['acero']; ?>, petroleo: <?php echo $equipo['petroleo']; ?> };
        const todasLasNaciones = <?php echo json_encode($lista_naciones); ?>;
        let filtroTipoActual = 'tanque';
        let filtroNacionActual = todasLasNaciones.length > 0 ? todasLasNaciones[0] : '';
        let itemSel = null;

        function initFiltros() { renderBotonesNaciones(); aplicarFiltrosTabla(); }

        function setFiltroTipo(t) { 
            filtroTipoActual = t; 
            
            document.getElementById('btn_tipo_tanque').classList.toggle('grayscale', t !== 'tanque'); 
            document.getElementById('btn_tipo_tanque').classList.toggle('opacity-70', t !== 'tanque'); 
            document.getElementById('btn_tipo_avion').classList.toggle('grayscale', t !== 'avion'); 
            document.getElementById('btn_tipo_avion').classList.toggle('opacity-70', t !== 'avion'); 
            
            const btnFlotas = document.getElementById('btn_tipo_flotas');
            if(btnFlotas) {
                btnFlotas.classList.toggle('grayscale', t !== 'flotas'); 
                btnFlotas.classList.toggle('opacity-70', t !== 'flotas');
            }

            if(t === 'flotas') {
                document.getElementById('cont_hangar').style.display = 'none';
                document.getElementById('mensaje_vacio').classList.add('hidden');
                document.getElementById('cont_flotas').classList.remove('hidden');
                
                document.getElementById('contenedor_naciones').style.opacity = '0.2';
                document.getElementById('contenedor_naciones').style.pointerEvents = 'none';
            } else {
                document.getElementById('cont_flotas').classList.add('hidden');
                document.getElementById('contenedor_naciones').style.opacity = '1';
                document.getElementById('contenedor_naciones').style.pointerEvents = 'auto';
                aplicarFiltrosTabla(); 
            }
        }

        function renderBotonesNaciones() {
            const cont = document.getElementById('contenedor_naciones');
            cont.innerHTML = '';
            if (todasLasNaciones.length === 0) {
                cont.innerHTML = `<span class="text-gray-500 text-[10px]">${txtJS.sinNaciones}</span>`;
                return;
            }
            todasLasNaciones.forEach(n => {
                const btn = document.createElement('button'); 
                btn.innerText = n;
                btn.className = `px-4 py-1 text-[10px] font-black uppercase border transition ${n === filtroNacionActual ? 'bg-blue-900 text-white border-blue-500 shadow-[0_0_10px_rgba(59,130,246,0.5)]' : 'bg-black/50 text-gray-500 border-gray-800 hover:text-white'}`;
                btn.onclick = () => { filtroNacionActual = n; renderBotonesNaciones(); aplicarFiltrosTabla(); };
                cont.appendChild(btn);
            });
        }

        function aplicarFiltrosTabla() {
            let totalVisibles = 0; 
            document.querySelectorAll('.bloque-nacion').forEach(b => {
                if (b.dataset.nacion !== filtroNacionActual) { 
                    b.style.display = 'none'; 
                    return; 
                }
                b.style.display = 'block';
                b.querySelectorAll('.tier-container').forEach(tier => {
                    let hayAlgo = false;
                    tier.querySelectorAll('.seccion-tipo').forEach(sec => {
                        if (sec.dataset.tipo !== filtroTipoActual) { 
                            sec.style.display = 'none'; 
                        } else {
                            let tarjetas = sec.querySelectorAll('.clase-container');
                            if (tarjetas.length > 0) { 
                                sec.style.display = 'block'; 
                                hayAlgo = true; 
                                totalVisibles++; 
                            } else { 
                                sec.style.display = 'none'; 
                            }
                        }
                    });
                    tier.style.display = hayAlgo ? 'block' : 'none';
                });
            });

            const msgVacio = document.getElementById('mensaje_vacio');
            const contHangar = document.getElementById('cont_hangar');

            if (totalVisibles === 0) {
                msgVacio.classList.remove('hidden');
                contHangar.style.display = 'none';
            } else {
                msgVacio.classList.add('hidden');
                contHangar.style.display = 'block';
            }
        }

        function toggleTier(el) {
            const content = el.nextElementSibling;
            const arrow = el.querySelector('span');
            content.style.display = (content.style.display === 'none') ? 'block' : 'none';
            arrow.innerText = (content.style.display === 'none') ? '▼' : '▲';
        }

        function abrirModalPatente(p) {
            itemSel = p; document.getElementById('p_target_id').value = p.plano_id; document.getElementById('p_nombre').innerText = p.nombre_vehiculo;
            const stock = parseInt(p.stock_actual) || 0;
            if (stock > 0) { document.getElementById('p_vehiculos_container').classList.remove('hidden'); document.getElementById('p_stock_txt').innerText = stock; document.getElementById('p_check_vehiculos').checked = false; } 
            else { document.getElementById('p_vehiculos_container').classList.add('hidden'); document.getElementById('p_check_vehiculos').checked = false; }
            togglePRef(); abrirModal('modalPatente');
        }
        function togglePRef() {
            const checkRef = document.getElementById('p_check_reembolso').checked, checkVeh = document.getElementById('p_check_vehiculos').checked;
            let addD = 0, addA = 0, addP = 0;
            if (checkRef) { addD += parseInt(itemSel.costo_dinero); if (checkVeh) { const qty = parseInt(itemSel.stock_actual); addD += qty * parseInt(itemSel.costo_dinero); addA += qty * parseInt(itemSel.costo_acero); addP += qty * parseInt(itemSel.costo_petroleo); } }
            const up = (id, old, add, sym) => { document.getElementById('p_'+id+'_old').innerText = old.toLocaleString() + sym; document.getElementById('p_'+id+'_add').innerText = (add > 0 ? "+" : "") + add.toLocaleString() + sym; document.getElementById('p_'+id+'_new').innerText = (old + add).toLocaleString() + sym; };
            up('d', resEq.dinero, addD, '$'); up('a', resEq.acero, addA, 'T'); up('p', resEq.petroleo, addP, 'L');
            document.getElementById('p_preview').style.opacity = checkRef ? "1" : "0.3";
        }
        function abrirModalVehiculo(i) { 
            itemSel = i; 
            document.getElementById('v_target_id').value = i.inv_id; 
            document.getElementById('v_inv_id_restar').value = i.inv_id;
            document.getElementById('v_inv_id_sumar').value = i.inv_id;
            document.getElementById('v_cat_id_sumar').value = i.id;

            document.getElementById('v_nombre').innerText = i.nombre_vehiculo; 
            document.getElementById('v_max_txt').innerText = i.stock_actual; 
            document.getElementById('v_qty').max = i.stock_actual; 
            document.getElementById('v_qty').value = 1; 
            calcVImpact(); 
            abrirModal('modalVehiculo'); 
        }
        function calcVImpact() {
            const q = parseInt(document.getElementById('v_qty').value) || 0, max = parseInt(itemSel.stock_actual), check = document.getElementById('v_check').checked, btn = document.getElementById('btn_v_confirm'), err = document.getElementById('v_error_msg');
            if (q > max || q <= 0) { document.getElementById('v_qty').classList.add('input-error'); btn.disabled = true; btn.style.opacity = "0.3"; err.classList.remove('hidden'); } else { document.getElementById('v_qty').classList.remove('input-error'); btn.disabled = false; btn.style.opacity = "1"; err.classList.add('hidden'); }
            document.getElementById('v_qty_form').value = q;
            const addD = check ? q * parseInt(itemSel.costo_dinero) : 0, addA = check ? q * parseInt(itemSel.costo_acero) : 0, addP = check ? q * parseInt(itemSel.costo_petroleo) : 0;
            const up = (id, old, add, sym) => { document.getElementById('v_'+id+'_old').innerText = old.toLocaleString() + sym; document.getElementById('v_'+id+'_add').innerText = (add > 0 ? "+" : "") + add.toLocaleString() + sym; document.getElementById('v_'+id+'_new').innerText = (old + add).toLocaleString() + sym; };
            up('d', resEq.dinero, addD, '$'); up('a', resEq.acero, addA, 'T'); up('p', resEq.petroleo, addP, 'L');
            document.getElementById('v_preview').style.opacity = check ? "1" : "0.3";
        }

        function abrirModalOtorgar(i, tipo) {
            document.getElementById('mo_cat_id').value = i.id;
            document.getElementById('mo_tipo_entrega').value = tipo;
            document.getElementById('mo_nombre').innerText = i.nombre_vehiculo;
            
            if(tipo === 'patente') {
                document.getElementById('mo_titulo').innerText = txtJS.otorPatente;
                document.getElementById('mo_unidades_div').classList.add('hidden');
            } else {
                document.getElementById('mo_titulo').innerText = txtJS.darUnidades;
                document.getElementById('mo_unidades_div').classList.remove('hidden');
            }
            abrirModal('modalOtorgar');
        }

        function abrirModal(id) { document.getElementById(id).classList.remove('hidden'); document.body.classList.add('modal-active'); }
        function cerrarModal(id) { document.getElementById(id).classList.add('hidden'); document.body.classList.remove('modal-active'); }

        function prepararDestruccionFlota(id) {
            document.getElementById('form_flota_id_destruir').value = id;
            abrirModal('modalDestruirFlota');
        }
    </script>
</body>
</html>