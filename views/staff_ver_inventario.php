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

    $stmt_p_count = $pdo->query("SELECT catalogo_id, COUNT(*) as total FROM planos_desbloqueados GROUP BY catalogo_id");
    $conteo_patentes = $stmt_p_count->fetchAll(PDO::FETCH_KEY_PAIR);

    $stmt_p_groups = $pdo->query("SELECT pd.catalogo_id, GROUP_CONCAT(c.nombre_equipo SEPARATOR ', ') as equipos FROM planos_desbloqueados pd JOIN cuentas c ON pd.cuenta_id = c.id GROUP BY pd.catalogo_id");
    $grupos_patentes = $stmt_p_groups->fetchAll(PDO::FETCH_KEY_PAIR);

    // Inventario ordenado por rango y BR
    $stmt_inv = $pdo->prepare("SELECT i.id as inv_id, i.cantidad as stock_actual, c.* FROM inventario i JOIN catalogo_tienda c ON i.catalogo_id = c.id WHERE i.cuenta_id = :id ORDER BY c.rango ASC, CAST(c.br AS DECIMAL(10,1)) ASC");
    $stmt_inv->execute([':id' => $equipo_id]);
    $inventario = $stmt_inv->fetchAll(PDO::FETCH_ASSOC);

    // Patentes ordenadas
    $stmt_p = $pdo->prepare("SELECT p.id as plano_id, c.* FROM planos_desbloqueados p JOIN catalogo_tienda c ON p.catalogo_id = c.id WHERE p.cuenta_id = :id ORDER BY c.rango ASC, CAST(c.br AS DECIMAL(10,1)) ASC");
    $stmt_p->execute([':id' => $equipo_id]);
    $planos_pagados = $stmt_p->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($planos_pagados as &$plano) {
        $plano['stock_actual'] = 0;
        foreach ($inventario as $inv) { if ($inv['id'] == $plano['id']) { $plano['stock_actual'] = $inv['stock_actual']; break; } }
    }
    unset($plano);

    $stmt_f = $pdo->prepare("SELECT * FROM flotas WHERE cuenta_id = :id ORDER BY slot ASC");
    $stmt_f->execute([':id' => $equipo_id]);
    $flotas_listado = $stmt_f->fetchAll(PDO::FETCH_ASSOC);

    // JERARQUÍA DE ORDENAMIENTO (ORDEN EXACTO SOLICITADO)
    $orden_tanques = ['Ligero', 'Mediano', 'Pesado', 'Caza Tanques', 'AAA'];
    $orden_aviones = ['Caza', 'Interceptor', 'Avion de Ataque', 'Bombardero'];

    // AGRUPACIÓN PARA HANGAR
    $hangar_agrupado = [];
    foreach($inventario as $cn) { 
        $tier = $cn['rango'] ?? 1;
        $tipo = $cn['tipo'] ?? 'tanque';
        $clase = !empty($cn['subtipo']) ? $cn['subtipo'] : (!empty($cn['clase']) ? $cn['clase'] : 'No Clasificado'); 
        $hangar_agrupado[$tier][$tipo][$clase][] = $cn; 
    }
    
    // AGRUPACIÓN PARA PATENTES
    $patentes_agrupadas = [];
    foreach($planos_pagados as $cn) { 
        $tier = $cn['rango'] ?? 1;
        $tipo = $cn['tipo'] ?? 'tanque';
        $clase = !empty($cn['subtipo']) ? $cn['subtipo'] : (!empty($cn['clase']) ? $cn['clase'] : 'No Clasificado'); 
        $patentes_agrupadas[$tier][$tipo][$clase][] = $cn; 
    }

} catch (PDOException $e) { die("Fallo: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Estado Mayor - Inspección Táctica</title>
    <?php include '../includes/head.php'; ?>
    <style>
        .modal-active { overflow: hidden; }
        .glass-panel { background: rgba(13, 14, 10, 0.98); backdrop-filter: blur(15px); border: 1px solid rgba(197, 160, 89, 0.3); }
        .terminal-input { background: rgba(0,0,0,0.6); border: 1px solid #444; color: #fff; padding: 10px; width: 100%; text-align: center; font-family: 'Space Mono', monospace; }
        .stat-label { font-size: 9px; color: rgba(255,255,255,0.4); font-weight: 900; text-transform: uppercase; letter-spacing: 2px; }
        .fleet-row { background: rgba(255,255,255,0.02); border: 1px solid #222; transition: 0.3s; }
        .unit-pill { font-size: 10px; color: #fff; font-weight: 700; text-transform: uppercase; background: #000; padding: 4px 10px; border: 1px solid #1a1a1a; }
        .btn-close-modal { position: absolute; top: 15px; right: 15px; width: 32px; height: 32px; background: #222; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 1px solid #333; font-size: 18px; z-index: 200; }
        .ownership-tag { background: rgba(59, 130, 246, 0.2); color: #60a5fa; font-size: 8px; font-weight: 900; padding: 2px 6px; border: 1px solid rgba(59, 130, 246, 0.3); position: absolute; top: 0; left: 0; z-index: 20; }
        .input-error { color: #ff0000 !important; border-color: #ff0000 !important; }
        
        /* ESTILO ACORDEÓN JACKY (NEGRO Y ROJO) */
        .tier-container { border: 1px solid #991b1b; background: #050505; margin-bottom: 1.5rem; }
        .tier-header { 
            background: url('https://www.transparenttextures.com/patterns/diagmonds-light.png'), #000; 
            border-bottom: 2px solid #ef4444; 
            cursor: pointer; 
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .tier-header h2 { color: #ef4444; font-weight: 900; text-transform: uppercase; letter-spacing: 3px; font-size: 1.2rem; margin: 0; text-shadow: 2px 2px 0 #000; }

        /* SCROLL HORIZONTAL Y TARJETAS */
        .custom-scrollbar::-webkit-scrollbar { height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #991b1b; border-radius: 10px; }
        
        .tag-premium { position: absolute; top: 0; right: 0; background: #c5a059; color: #000; font-size: 9px; font-weight: 900; padding: 3px 8px; z-index: 10; text-transform: uppercase; }
        .tag-br { position: absolute; top: 0; left: 0; background: #000; border-right: 1px solid #333; border-bottom: 1px solid #333; color: #fff; font-size: 10px; font-weight: 900; padding: 3px 8px; z-index: 10; font-family: monospace; }
        .card-premium { border-color: #c5a059 !important; box-shadow: inset 0 0 15px rgba(197, 160, 89, 0.15); }
        
        .stat-grid-label { font-size: 7px; color: #555; font-weight: 900; text-transform: uppercase; }
        .stat-grid-value { font-size: 9px; font-weight: 900; font-family: 'Space Mono', monospace; }
    </style>
</head>
<body class="bg-[#0a0b08] text-[var(--text-main)] min-h-screen pb-20">
    <?php include '../includes/nav_staff.php'; ?>

    <main class="p-8 max-w-[1600px] mx-auto">
        <div class="mb-12 flex justify-between items-center border-b border-white/5 pb-8">
            <a href="staff_dashboard.php" class="btn-m !bg-none !border-white/10 !text-gray-500 hover:!text-[var(--aoe-gold)] !py-2 !px-6 text-[10px] uppercase font-black transition">⬅️ VOLVER</a>
            <div class="text-right">
                <span class="stat-label">FONDOS DE LA FACCIÓN:</span>
                <div class="flex gap-6 mt-1 font-black text-lg">
                    <span class="text-green-500">$<?php echo number_format($equipo['dinero']); ?></span>
                    <span class="text-white"><?php echo number_format($equipo['acero']); ?>T</span>
                    <span class="text-yellow-500"><?php echo number_format($equipo['petroleo']); ?>L</span>
                </div>
            </div>
        </div>

        <h1 class="text-3xl font-black mb-12 uppercase italic text-white">INSPECCIONANDO: <span class="text-[var(--aoe-gold)]"><?php echo htmlspecialchars($equipo['nombre_equipo']); ?></span></h1>

        <div class="mb-20">
            <h2 class="text-[11px] font-black uppercase tracking-[0.4em] mb-8 text-[#c5a059] border-l-4 border-yellow-900 pl-4">⚓ FLOTAS OPERATIVAS</h2>
            <div class="space-y-4">
                <?php if(empty($flotas_listado)): ?>
                    <div class="p-10 border border-dashed border-white/5 text-center text-gray-600 text-[10px] font-black uppercase">Sin formaciones detectadas.</div>
                <?php else: foreach($flotas_listado as $fl): ?>
                    <div class="fleet-row p-6 flex flex-col md:flex-row justify-between items-center gap-6">
                        <div class="flex items-center gap-8">
                            <div class="text-center"><span class="stat-label">SLOT</span><div class="text-[#c5a059] font-black text-xl">#<?php echo $fl['slot']; ?></div></div>
                            <div><span class="stat-label">INSIGNIA</span><div class="text-white font-bold uppercase text-sm"><?php echo htmlspecialchars($fl['insignia']); ?></div></div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <?php for($i=1;$i<=4;$i++){ if(!empty($fl['escolta_'.$i])){ echo '<div class="unit-pill">ESC '.$i.': '.htmlspecialchars($fl['escolta_'.$i]).'</div>'; } } ?>
                        </div>
                        <button onclick="confirmarBorradoFlota(<?php echo $fl['id']; ?>, <?php echo $fl['slot']; ?>)" class="bg-red-950/20 text-red-500 border border-red-900 px-6 py-2 text-[9px] font-black uppercase hover:bg-red-600 transition">DESTRUIR</button>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <div class="mb-20">
            <h2 class="text-[11px] font-black uppercase tracking-[0.4em] mb-8 text-[var(--aoe-gold)] border-l-4 border-yellow-900 pl-4">🏭 ACTIVOS EN HANGAR</h2>
            <div class="space-y-4">
                <?php ksort($hangar_agrupado); foreach($hangar_agrupado as $tier => $tipos): ?>
                    <div class="tier-container shadow-2xl">
                        <div class="tier-header" onclick="toggleTier(this)">
                            <h2>RANGO / TIER <?php echo $tier; ?></h2>
                            <span class="text-red-500 font-bold">▼</span>
                        </div>
                        <div class="tier-content p-6 space-y-8 block">
                            <?php foreach(['tanque', 'avion'] as $tipo_v): if(!isset($tipos[$tipo_v])) continue; ?>
                                <div>
                                    <?php 
                                    $orden = ($tipo_v == 'tanque') ? $orden_tanques : $orden_aviones;
                                    foreach($orden as $clase_n): if(!isset($tipos[$tipo_v][$clase_n])) continue; 
                                    ?>
                                        <div class="mb-6">
                                            <h3 class="text-gray-600 font-black uppercase text-[9px] tracking-widest border-b border-white/5 pb-1 mb-4"><?php echo $clase_n; ?></h3>
                                            <div class="flex gap-4 overflow-x-auto pb-4 custom-scrollbar">
                                                <?php foreach($tipos[$tipo_v][$clase_n] as $i): 
                                                    $i_json = htmlspecialchars(json_encode($i), ENT_QUOTES, 'UTF-8'); 
                                                    $es_prem = isset($i['is_premium']) && $i['is_premium'] == 1;
                                                ?>
                                                    <div class="flex-shrink-0 w-64 flex flex-col bg-[#111] border <?php echo $es_prem ? 'card-premium' : 'border-gray-800'; ?> relative hover:brightness-110 transition">
                                                        <?php if($es_prem): ?><div class="tag-premium">PREMIUM</div><?php endif; ?>
                                                        <div class="tag-br">BR: <?php echo htmlspecialchars($i['br'] ?? '1.0'); ?></div>
                                                        <div class="h-28 bg-black overflow-hidden"><img src="../<?php echo $i['imagen_url']; ?>" class="w-full h-full object-cover opacity-80"></div>
                                                        <div class="p-3 flex-grow flex flex-col">
                                                            <span class="text-[11px] text-white font-black uppercase block mb-2 truncate text-center"><?php echo htmlspecialchars($i['nombre_vehiculo']); ?></span>
                                                            
                                                            <div class="flex justify-center gap-1 mb-3">
                                                                <span class="text-[7px] bg-blue-900/30 text-blue-400 px-1.5 py-0.5 rounded font-black uppercase"><?php echo htmlspecialchars($i['tipo']); ?></span>
                                                                <span class="text-[7px] bg-gray-800 text-gray-400 px-1.5 py-0.5 rounded font-black uppercase"><?php echo $clase_n; ?></span>
                                                            </div>

                                                            <div class="grid grid-cols-3 gap-0 bg-black border border-gray-800 p-1.5 text-center rounded mb-3">
                                                                <div class="border-r border-gray-800"><span class="stat-grid-label block">CASH</span><span class="stat-grid-value text-green-500">$<?php echo number_format($i['costo_dinero']); ?></span></div>
                                                                <div class="border-r border-gray-800"><span class="stat-grid-label block">STEEL</span><span class="stat-grid-value text-white"><?php echo number_format($i['costo_acero']); ?>T</span></div>
                                                                <div><span class="stat-grid-label block">FUEL</span><span class="stat-grid-value text-yellow-500"><?php echo number_format($i['costo_petroleo']); ?>L</span></div>
                                                            </div>

                                                            <div class="mt-auto flex justify-between items-center pt-2 border-t border-gray-800/50">
                                                                <span class="stat-label">STOCK:</span>
                                                                <span class="text-xl font-black text-[#c5a059] font-['Cinzel']"><?php echo $i['stock_actual']; ?>x</span>
                                                            </div>
                                                        </div>
                                                        <button onclick='abrirModalVehiculo(<?php echo $i_json; ?>)' class="w-full bg-red-800 text-white border-t border-red-600 py-3 text-[10px] font-black uppercase tracking-widest hover:bg-red-500 transition">PURGAR UNIDADES</button>
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
        </div>

        <div class="mb-20">
            <h2 class="text-[11px] font-black uppercase tracking-[0.4em] mb-8 text-blue-400 border-l-4 border-blue-900 pl-4">📜 PATENTES TECNOLÓGICAS</h2>
            <div class="space-y-4">
                <?php ksort($patentes_agrupadas); foreach($patentes_agrupadas as $tier => $tipos): ?>
                    <div class="tier-container shadow-2xl">
                        <div class="tier-header" onclick="toggleTier(this)">
                            <h2>RANGO / TIER <?php echo $tier; ?></h2>
                            <span class="text-red-500 font-bold">▼</span>
                        </div>
                        <div class="tier-content p-6 space-y-8 block">
                            <?php foreach(['tanque', 'avion'] as $tipo_v): if(!isset($tipos[$tipo_v])) continue; ?>
                                <div>
                                    <?php 
                                    $orden = ($tipo_v == 'tanque') ? $orden_tanques : $orden_aviones;
                                    foreach($orden as $clase_n): if(!isset($tipos[$tipo_v][$clase_n])) continue; 
                                    ?>
                                        <div class="mb-6">
                                            <h3 class="text-gray-500 font-black uppercase text-[10px] tracking-[0.2em] border-b border-gray-800 pb-2 mb-4"><?php echo $clase_n; ?></h3>
                                            <div class="flex gap-4 overflow-x-auto pb-4 custom-scrollbar">
                                                <?php foreach($tipos[$tipo_v][$clase_n] as $p): 
                                                    $p_json = htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8');
                                                    $poseedores = $grupos_patentes[$p['id']] ?? 'Solo esta facción';
                                                    $poseedores_json = htmlspecialchars(json_encode($poseedores), ENT_QUOTES, 'UTF-8');
                                                    $es_prem = isset($p['is_premium']) && $p['is_premium'] == 1;
                                                ?>
                                                    <div class="flex-shrink-0 w-64 flex flex-col bg-[#111] border <?php echo $es_prem ? 'card-premium' : 'border-white/5'; ?> relative hover:brightness-110 transition">
                                                        <?php if($es_prem): ?><div class="tag-premium">PREMIUM</div><?php endif; ?>
                                                        <div class="ownership-tag" title="<?php echo htmlspecialchars($poseedores); ?>">DUEÑOS: <?php echo ($conteo_patentes[$p['id']] ?? 1); ?></div>
                                                        <div class="tag-br">BR: <?php echo htmlspecialchars($p['br'] ?? '1.0'); ?></div>
                                                        <div class="h-28 bg-[#050505]"><img src="../<?php echo $p['imagen_url']; ?>" class="w-full h-full object-cover grayscale opacity-40"></div>
                                                        <div class="p-3 flex-grow flex flex-col">
                                                            <span class="text-[11px] text-white font-black uppercase block mb-2 truncate text-center"><?php echo htmlspecialchars($p['nombre_vehiculo']); ?></span>
                                                            
                                                            <div class="flex justify-center gap-1 mb-3">
                                                                <span class="text-[7px] bg-blue-900/30 text-blue-400 px-1.5 py-0.5 rounded font-black uppercase"><?php echo htmlspecialchars($p['tipo']); ?></span>
                                                                <span class="text-[7px] bg-gray-800 text-gray-400 px-1.5 py-0.5 rounded font-black uppercase"><?php echo $clase_n; ?></span>
                                                            </div>

                                                            <div class="grid grid-cols-3 gap-0 bg-black border border-gray-800 p-1.5 text-center rounded mt-auto mb-2">
                                                                <div class="border-r border-gray-800"><span class="stat-grid-label block">CASH</span><span class="stat-grid-value text-green-500">$<?php echo number_format($p['costo_dinero']); ?></span></div>
                                                                <div class="border-r border-gray-800"><span class="stat-grid-label block">STEEL</span><span class="stat-grid-value text-white"><?php echo number_format($p['costo_acero']); ?>T</span></div>
                                                                <div><span class="stat-grid-label block">FUEL</span><span class="stat-grid-value text-yellow-500"><?php echo number_format($p['costo_petroleo']); ?>L</span></div>
                                                            </div>
                                                        </div>
                                                        <button onclick='abrirModalPatente(<?php echo $p_json; ?>, <?php echo $poseedores_json; ?>)' class="w-full bg-red-950/20 text-red-500 border-t border-red-900 text-[9px] py-3 font-black uppercase hover:bg-red-700 hover:text-white transition">PURGAR TECNOLOGÍA</button>
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
        </div>
    </main>

    <div id="modalPatente" class="hidden fixed inset-0 bg-black/98 z-[200] flex items-center justify-center p-4 backdrop-blur-md">
        <div class="m-panel w-full max-w-md glass-panel p-10 border-red-500/30 relative">
            <button onclick="cerrarModal('modalPatente')" class="btn-close-modal">&times;</button>
            <h2 class="text-red-500 font-black text-center text-[10px] uppercase mb-8 tracking-[0.3em]">PURGA ESTRATÉGICA DE PATENTE</h2>
            <div class="text-center mb-6"><span id="p_nombre" class="text-white font-black text-3xl uppercase font-['Cinzel']"></span></div>
            <div class="bg-blue-900/10 border border-blue-900/30 p-3 text-center mb-8"><span class="stat-label block mb-1 text-blue-500">DISTRIBUCIÓN GLOBAL:</span><span id="p_owners_txt" class="text-white font-bold text-[10px] uppercase leading-relaxed"></span></div>
            <form action="../logic/procesar_reembolso_staff.php" method="POST">
                <input type="hidden" name="tipo" value="plano"><input type="hidden" name="target_id" id="p_target_id"><input type="hidden" name="equipo_id" value="<?php echo $equipo_id; ?>">
                <div class="bg-black/40 p-4 border border-white/10 mb-2"><label class="flex items-center gap-4 cursor-pointer"><input type="checkbox" name="reembolsar" value="1" id="p_check_reembolso" checked onchange="togglePRef()" class="w-5 h-5 accent-green-500"><span class="text-[10px] font-black text-gray-300 uppercase">¿DEVOLVER DINERO A LA FACCIÓN?</span></label></div>
                <div id="p_vehiculos_container" class="bg-red-950/20 p-4 border border-red-900/30 mb-8 hidden"><label class="flex items-center gap-4 cursor-pointer"><input type="checkbox" name="purgar_vehiculos" value="1" id="p_check_vehiculos" onchange="togglePRef()" class="w-5 h-5 accent-red-500"><span class="text-[10px] font-black text-red-400 uppercase">¿PURGAR TAMBIÉN VEHÍCULOS (<span id="p_stock_txt" class="text-white"></span>x)?</span></label></div>
                <div id="p_preview" class="space-y-2 mb-10 text-[10px] font-mono font-black transition-opacity">
                    <div class="flex justify-between p-2 bg-white/5 border border-white/5"><span>CASH</span><div class="flex gap-2"><span id="p_d_old" class="text-gray-500"></span><span id="p_d_add" class="text-green-500"></span><span class="text-white">→</span><span id="p_d_new" class="text-green-500"></span></div></div>
                    <div class="flex justify-between p-2 bg-white/5 border border-white/5"><span>STEEL</span><div class="flex gap-2"><span id="p_a_old" class="text-gray-500"></span><span id="p_a_add" class="text-white"></span><span class="text-white">→</span><span id="p_a_new" class="text-white"></span></div></div>
                    <div class="flex justify-between p-2 bg-white/5 border border-white/5"><span>FUEL</span><div class="flex gap-2"><span id="p_p_old" class="text-gray-500"></span><span id="p_p_add" class="text-yellow-500"></span><span class="text-white">→</span><span id="p_p_new" class="text-yellow-500"></span></div></div>
                </div>
                <div class="grid grid-cols-2 gap-4"><button type="submit" class="bg-red-600 text-black py-5 font-black uppercase text-[11px] hover:bg-red-500 transition">CONFIRMAR</button><button type="button" onclick="cerrarModal('modalPatente')" class="border border-white/10 text-gray-500 py-5 font-black uppercase">ABORTAR</button></div>
            </form>
        </div>
    </div>

    <div id="modalVehiculo" class="hidden fixed inset-0 bg-black/98 z-[200] flex items-center justify-center p-4 backdrop-blur-md">
        <div class="m-panel w-full max-w-lg glass-panel p-10 border-red-500/30 relative">
            <button onclick="cerrarModal('modalVehiculo')" class="btn-close-modal">&times;</button>
            <h2 class="text-red-500 font-black text-center text-[10px] uppercase mb-10 tracking-[0.3em]">PROTOCOLO DE REINTEGRO TÁCTICO</h2>
            <div class="bg-black/40 p-4 border border-white/5 mb-8 text-center"><span id="v_nombre" class="text-white font-black text-2xl uppercase font-['Cinzel'] block"></span><span class="text-[9px] text-gray-500 uppercase tracking-widest">STOCK DISPONIBLE: <span id="v_max_txt" class="text-white font-bold"></span></span></div>
            <form action="../logic/procesar_reembolso_staff.php" method="POST">
                <input type="hidden" name="tipo" value="vehiculo"><input type="hidden" name="target_id" id="v_target_id"><input type="hidden" name="equipo_id" value="<?php echo $equipo_id; ?>"><input type="hidden" name="cantidad_final" id="v_qty_form">
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div><label class="stat-label block mb-2">CANTIDAD A PURGAR</label><input type="number" id="v_qty" value="1" min="1" oninput="calcVImpact()" class="terminal-input text-2xl font-black text-red-500"><div id="v_error_msg" class="text-red-500 text-[9px] font-black uppercase text-center mt-2 hidden animate-pulse">⚠️ SUPERA EL STOCK</div></div>
                    <div class="flex flex-col justify-end"><label class="flex items-center gap-3 cursor-pointer bg-black/40 p-3 border border-white/10 h-[50px]"><input type="checkbox" name="reembolsar" value="1" id="v_check" checked onchange="calcVImpact()" class="w-4 h-4 accent-green-500"><span class="text-[8px] font-black text-gray-400 uppercase leading-tight">DEVOLVER FONDOS</span></label></div>
                </div>
                <div id="v_preview" class="space-y-2 mb-10 text-[10px] font-mono font-black transition-opacity">
                    <div class="flex justify-between p-2 bg-white/5 border border-white/5"><span>CASH</span><div class="flex gap-2"><span id="v_d_old" class="text-gray-500"></span><span id="v_d_add" class="text-green-500"></span><span class="text-white">→</span><span id="v_d_new" class="text-green-500"></span></div></div>
                    <div class="flex justify-between p-2 bg-white/5 border border-white/5"><span>STEEL</span><div class="flex gap-2"><span id="v_a_old" class="text-gray-500"></span><span id="v_a_add" class="text-white"></span><span class="text-white">→</span><span id="v_a_new" class="text-white"></span></div></div>
                    <div class="flex justify-between p-2 bg-white/5 border border-white/5"><span>FUEL</span><div class="flex gap-2"><span id="v_p_old" class="text-gray-500"></span><span id="v_p_add" class="text-yellow-500"></span><span class="text-white">→</span><span id="v_p_new" class="text-yellow-500"></span></div></div>
                </div>
                <div class="grid grid-cols-2 gap-4"><button type="submit" id="btn_v_confirm" class="bg-red-600 text-black py-5 font-black uppercase text-[11px] hover:bg-red-500 transition">EJECUTAR</button><button type="button" onclick="cerrarModal('modalVehiculo')" class="border border-white/10 text-gray-500 py-5 font-black uppercase text-[10px]">ABORTAR</button></div>
            </form>
        </div>
    </div>

    <div id="modalDestroyFlota" class="hidden fixed inset-0 bg-black/98 z-[300] flex items-center justify-center p-4">
        <div class="m-panel w-full max-w-md border-red-600 bg-[#0a0a0a] p-10 text-center relative shadow-2xl"><button onclick="cerrarModal('modalDestroyFlota')" class="btn-close-modal">&times;</button><div class="text-red-600 text-5xl mb-6">☢️</div><h2 class="text-white font-black uppercase tracking-[0.2em] mb-4">ANIQUILACIÓN DE FLOTA</h2><p class="text-gray-400 text-xs font-bold leading-relaxed mb-10 uppercase">Confirmar destrucción de la Flota #<span id="txt_slot"></span>.</p><form action="../logic/borrar_flota_staff.php" method="POST"><input type="hidden" name="flota_id" id="hid_flota_id"><input type="hidden" name="lider_id" value="<?php echo $equipo_id; ?>"><button type="submit" class="bg-red-600 text-black w-full py-4 font-black uppercase text-[11px] hover:bg-red-400 transition">EJECUTAR</button></form></div>
    </div>

    <script>
        const resEq = { dinero: <?php echo $equipo['dinero']; ?>, acero: <?php echo $equipo['acero']; ?>, petroleo: <?php echo $equipo['petroleo']; ?> };
        let itemSel = null;

        function toggleTier(el) {
            const content = el.nextElementSibling;
            const arrow = el.querySelector('span');
            content.style.display = (content.style.display === 'none') ? 'block' : 'none';
            arrow.innerText = (content.style.display === 'none') ? '▼' : '▲';
        }

        function abrirModalPatente(p, owners) {
            itemSel = p; document.getElementById('p_target_id').value = p.plano_id; document.getElementById('p_nombre').innerText = p.nombre_vehiculo; document.getElementById('p_owners_txt').innerText = owners;
            const stock = parseInt(p.stock_actual) || 0;
            if (stock > 0) { document.getElementById('p_vehiculos_container').classList.remove('hidden'); document.getElementById('p_stock_txt').innerText = stock; document.getElementById('p_check_vehiculos').checked = false; } 
            else { document.getElementById('p_vehiculos_container').classList.add('hidden'); document.getElementById('p_check_vehiculos').checked = false; }
            togglePRef(); abrirModal('modalPatente');
        }

        function togglePRef() {
            const checkRef = document.getElementById('p_check_reembolso').checked, checkVeh = document.getElementById('p_check_vehiculos').checked;
            let addD = 0, addA = 0, addP = 0;
            if (checkRef) {
                addD += parseInt(itemSel.costo_dinero);
                if (checkVeh) { const qty = parseInt(itemSel.stock_actual); addD += qty * parseInt(itemSel.costo_dinero); addA += qty * parseInt(itemSel.costo_acero); addP += qty * parseInt(itemSel.costo_petroleo); }
            }
            const up = (id, old, add, sym) => { document.getElementById('p_'+id+'_old').innerText = old.toLocaleString() + sym; document.getElementById('p_'+id+'_add').innerText = (add > 0 ? "+" : "") + add.toLocaleString() + sym; document.getElementById('p_'+id+'_new').innerText = (old + add).toLocaleString() + sym; };
            up('d', resEq.dinero, addD, '$'); up('a', resEq.acero, addA, 'T'); up('p', resEq.petroleo, addP, 'L');
            document.getElementById('p_preview').style.opacity = checkRef ? "1" : "0.3";
        }

        function abrirModalVehiculo(i) { itemSel = i; document.getElementById('v_target_id').value = i.inv_id; document.getElementById('v_nombre').innerText = i.nombre_vehiculo; document.getElementById('v_max_txt').innerText = i.stock_actual; document.getElementById('v_qty').max = i.stock_actual; document.getElementById('v_qty').value = 1; calcVImpact(); abrirModal('modalVehiculo'); }
        function calcVImpact() {
            const q = parseInt(document.getElementById('v_qty').value) || 0, max = parseInt(itemSel.stock_actual), check = document.getElementById('v_check').checked, btn = document.getElementById('btn_v_confirm'), err = document.getElementById('v_error_msg');
            if (q > max || q <= 0) { document.getElementById('v_qty').classList.add('input-error'); btn.disabled = true; btn.style.opacity = "0.3"; err.classList.remove('hidden'); } else { document.getElementById('v_qty').classList.remove('input-error'); btn.disabled = false; btn.style.opacity = "1"; err.classList.add('hidden'); }
            document.getElementById('v_qty_form').value = q;
            const addD = check ? q * parseInt(itemSel.costo_dinero) : 0, addA = check ? q * parseInt(itemSel.costo_acero) : 0, addP = check ? q * parseInt(itemSel.costo_petroleo) : 0;
            const up = (id, old, add, sym) => { document.getElementById('v_'+id+'_old').innerText = old.toLocaleString() + sym; document.getElementById('v_'+id+'_add').innerText = (add > 0 ? "+" : "") + add.toLocaleString() + sym; document.getElementById('v_'+id+'_new').innerText = (old + add).toLocaleString() + sym; };
            up('d', resEq.dinero, addD, '$'); up('a', resEq.acero, addA, 'T'); up('p', resEq.petroleo, addP, 'L');
            document.getElementById('v_preview').style.opacity = check ? "1" : "0.3";
        }
        function confirmarBorradoFlota(id, slot) { document.getElementById('hid_flota_id').value = id; document.getElementById('txt_slot').innerText = slot; abrirModal('modalDestroyFlota'); }
        function abrirModal(id) { document.getElementById(id).classList.remove('hidden'); document.body.classList.add('modal-active'); }
        function cerrarModal(id) { document.getElementById(id).classList.add('hidden'); document.body.classList.remove('modal-active'); }
    </script>
</body>
</html>