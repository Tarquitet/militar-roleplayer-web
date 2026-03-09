<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'lider') { header("Location: ../login.php"); exit(); }
require_once '../config/conexion.php';
$root_path = "../";
$txt = require '../config/textos.php';
$lider_id = $_SESSION['usuario_id'];

try {
    $stmt_mio = $pdo->prepare("SELECT * FROM cuentas WHERE id = :id");
    $stmt_mio->execute([':id' => $lider_id]);
    $user = $stmt_mio->fetch(PDO::FETCH_ASSOC);
    $mis_naciones = !empty($user['naciones_activas']) ? array_map('trim', explode(',', $user['naciones_activas'])) : [];

    $stmt_tr_check = $pdo->prepare("SELECT COUNT(*) FROM mercado_tradeos WHERE (ofertante_id = :id OR receptor_id = :id) AND estado = 'activo'");
    $stmt_tr_check->execute([':id' => $lider_id]);
    $tiene_contratos = $stmt_tr_check->fetchColumn() > 0;

    $stmt_mis_tr = $pdo->prepare("SELECT m.*, c.nombre_vehiculo as v_ofrecido, req.nombre_vehiculo as v_requerido, u.nombre_equipo as receptor FROM mercado_tradeos m LEFT JOIN catalogo_tienda c ON m.vehiculo_ofrecido_id = c.id LEFT JOIN catalogo_tienda req ON m.vehiculo_requerido_id = req.id JOIN cuentas u ON m.receptor_id = u.id WHERE m.ofertante_id = :id AND m.estado = 'activo'");
    $stmt_mis_tr->execute([':id' => $lider_id]);
    $mis_ordenes = $stmt_mis_tr->fetchAll(PDO::FETCH_ASSOC);

    $stmt_pend = $pdo->prepare("SELECT s.*, c.nombre_vehiculo FROM solicitudes_reembolso s JOIN inventario i ON s.inventario_id = i.id JOIN catalogo_tienda c ON i.catalogo_id = c.id WHERE s.cuenta_id = :id AND s.estado = 'pendiente'");
    $stmt_pend->execute([':id' => $lider_id]);
    $reembolsos_activos = $stmt_pend->fetchAll(PDO::FETCH_ASSOC);

    $en_proceso = [];
    foreach($reembolsos_activos as $ra) { $en_proceso[$ra['inventario_id']] = ($en_proceso[$ra['inventario_id']] ?? 0) + $ra['cantidad']; }

    $stmt_f = $pdo->prepare("SELECT * FROM flotas WHERE cuenta_id = :id ORDER BY slot ASC");
    $stmt_f->execute([':id' => $lider_id]);
    $flotas_db = $stmt_f->fetchAll(PDO::FETCH_ASSOC);
    $mis_flotas = [1 => null, 2 => null, 3 => null];
    foreach($flotas_db as $f) { $mis_flotas[$f['slot']] = $f; }

    $catalogo_nacional = []; $mi_stock = []; $mi_catalogo_js = []; $mis_planos = [];
    $hangar_agrupado = [];

    if (!empty($mis_naciones)) {
        $placeholders = str_repeat('?,', count($mis_naciones) - 1) . '?';
        $stmt_full = $pdo->prepare("SELECT * FROM catalogo_tienda WHERE nacion IN ($placeholders) ORDER BY rango ASC, CAST(br AS DECIMAL(10,1)) ASC");
        $stmt_full->execute($mis_naciones);
        $catalogo_nacional = $stmt_full->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt_p = $pdo->prepare("SELECT catalogo_id FROM planos_desbloqueados WHERE cuenta_id = :id");
        $stmt_p->execute([':id' => $lider_id]);
        $mis_planos = $stmt_p->fetchAll(PDO::FETCH_COLUMN);

        $stmt_inv = $pdo->prepare("SELECT id as inv_id, catalogo_id, cantidad FROM inventario WHERE cuenta_id = :id");
        $stmt_inv->execute([':id' => $lider_id]);
        foreach($stmt_inv->fetchAll(PDO::FETCH_ASSOC) as $ri) { $mi_stock[$ri['catalogo_id']] = $ri; }

        foreach($catalogo_nacional as $cn) { 
            $mi_catalogo_js[$cn['id']] = ['nombre' => $cn['nombre_vehiculo'], 'dinero' => $cn['costo_dinero'], 'acero' => $cn['costo_acero'], 'petroleo' => $cn['costo_petroleo'], 'stock' => $mi_stock[$cn['id']]['cantidad'] ?? 0]; 
            $tier = $cn['rango'] ?? 1;
            $tipo = $cn['tipo'] ?? 'tanque';
            $clase = !empty($cn['subtipo']) ? $cn['subtipo'] : (!empty($cn['clase']) ? $cn['clase'] : 'No Clasificado'); 
            $hangar_agrupado[$tier][$tipo][$clase][] = $cn;
        }
        ksort($hangar_agrupado); 
    }

    $orden_tanques = ['Ligero', 'Mediano', 'Pesado', 'Caza Tanques', 'AAA'];
    $orden_aviones = ['Caza', 'Interceptor', 'Avion de Ataque', 'Bombardero'];

    $stmt_rivales = $pdo->prepare("SELECT id, nombre_equipo, bandera_url, naciones_activas FROM cuentas WHERE rol = 'lider' AND id != :id ORDER BY nombre_equipo ASC");
    $stmt_rivales->execute([':id' => $lider_id]);
    $rivales = $stmt_rivales->fetchAll(PDO::FETCH_ASSOC);

    $stmt_inv_global = $pdo->prepare("SELECT i.cuenta_id, c.*, i.cantidad as stock_actual FROM inventario i JOIN catalogo_tienda c ON i.catalogo_id = c.id WHERE i.cuenta_id != :id AND i.cantidad > 0");
    $stmt_inv_global->execute([':id' => $lider_id]);
    $inventario_global = $stmt_inv_global->fetchAll(PDO::FETCH_ASSOC);

    // NUEVO: Órdenes recibidas (Privado: solo si el receptor soy yo)
    $stmt_recibidas = $pdo->prepare("
        SELECT m.*, u.nombre_equipo as remitente, c.nombre_vehiculo as v_ofrecido_nombre 
        FROM mercado_tradeos m 
        JOIN cuentas u ON m.ofertante_id = u.id 
        LEFT JOIN catalogo_tienda c ON m.vehiculo_ofrecido_id = c.id 
        WHERE m.receptor_id = :id AND m.estado = 'activo'
    ");
    $stmt_recibidas->execute([':id' => $lider_id]);
    $ofertas_recibidas = $stmt_recibidas->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) { die("FALLO CRÍTICO DE SISTEMA: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Hangar y Mercado Central</title>
    <?php include '../includes/head.php'; ?>
    <style>
        .modal-active { overflow: hidden; }
        .tab-nacion { color: #ffffff !important; border: 1px solid #444; font-weight: 900; text-transform: uppercase; transition: 0.2s; }
        .tab-nacion.active { background-color: #3f4231 !important; color: #c5a059 !important; border-color: #c5a059 !important; }
        .nav-btn { border: 1px solid #333; color: #666; font-weight: 900; }
        .nav-btn.active { border-color: #c5a059; color: #c5a059; background: rgba(255,204,0,0.05); }
        .btn-close-modal { position: absolute; top: 15px; right: 15px; width: 32px; height: 32px; background: rgba(255,255,255,0.05); border: 1px solid #444; color: #777; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; transition: 0.2s; cursor: pointer; z-index: 200; }
        .slot-box { border: 2px dashed #222; height: auto; min-height: 400px; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #090909; cursor: pointer; transition: 0.3s; }
        .slot-box:hover { border-color: #c5a059; background: #0f0f0f; }
        .slot-box.filled { border-style: solid; border-color: #1a1a1a; justify-content: flex-start; padding: 25px; }
        .f-input { background: #000; border: 1px solid #333; color: #fff !important; padding: 12px; width: 100%; margin-top: 5px; font-family: monospace; }
        .stat-label { font-size: 10px; color: #777; font-weight: 900; text-transform: uppercase; }
        .badge-process { color: #ef4444; font-size: 10px; font-weight: 900; background: rgba(239, 68, 68, 0.1); padding: 2px 6px; border: 1px solid rgba(239, 68, 68, 0.2); margin-left: 8px; }
        @keyframes pulse-trade { 0%, 100% { border-color: #3b82f6; box-shadow: 0 0 10px rgba(59, 130, 246, 0.4); } 50% { border-color: #fff; } }
        .btn-trade-active { animation: pulse-trade 1.5s infinite; background: #1e3a8a !important; color: white !important; }
        .balance-pill { font-size: 8px; font-weight: 900; padding: 2px 6px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 2px; }
        
        .custom-scrollbar::-webkit-scrollbar { height: 8px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #0a0a0a; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #333; border-radius: 4px; border: 1px solid #000; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #555; }
        
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

        .tag-premium { position: absolute; top: 0; right: 0; background: #c5a059; color: #000; font-size: 9px; font-weight: 900; padding: 3px 8px; z-index: 10; text-transform: uppercase; letter-spacing: 1px; }
        .tag-br { position: absolute; top: 0; left: 0; background: #000; border-right: 1px solid #333; border-bottom: 1px solid #333; color: #fff; font-size: 10px; font-weight: 900; padding: 3px 8px; z-index: 10; font-family: monospace; }
        .card-premium { border-color: #c5a059 !important; box-shadow: inset 0 0 15px rgba(197, 160, 89, 0.15); }

        .stat-grid-label { font-size: 7px; color: #555; font-weight: 900; text-transform: uppercase; }
        .stat-grid-value { font-size: 9px; font-weight: 900; font-family: 'Space Mono', monospace; }
    </style>
</head>
<body class="bg-[#0d0e0a] text-[var(--text-main)] min-h-screen pb-20">

    <?php include '../includes/nav_lider.php'; ?>

    <nav class="bg-[#1a1c11] border-b border-[var(--wood-border)] sticky top-0 z-40">
        <div class="flex px-8 py-3 items-center justify-between">
            <div class="flex items-center gap-6">
                <span class="text-[#c5a059] text-[10px] font-black uppercase">TERRITORIOS:</span>
                <div class="flex gap-2">
                    <?php foreach ($mis_naciones as $n): ?>
                        <button onclick="setNacion('<?php echo htmlspecialchars($n); ?>')" data-nacion="<?php echo htmlspecialchars($n); ?>" class="tab-nacion px-4 py-1 text-[10px] font-black uppercase"><?php echo htmlspecialchars($n); ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="flex gap-4">
                <button onclick="abrirModal('modalHistorial')" class="btn-m !bg-red-950/20 !border-red-900 !text-red-500 !py-2 !px-6 text-[10px] font-black uppercase">SOLICITUDES ACTIVAS (<?php echo count($reembolsos_activos); ?>)</button>
                <button onclick="abrirModal('modalMercado')" class="btn-m !bg-blue-900/30 !border-blue-700 !text-blue-400 !py-2 !px-6 text-[10px] font-black uppercase <?php echo $tiene_contratos ? 'btn-trade-active' : ''; ?>">MERCADO DIPLOMÁTICO</button>
            </div>
        </div>
    </nav>

    <main class="p-8 max-w-[1600px] mx-auto mt-4">
        <div class="flex gap-4 mb-10 border-b border-gray-800 pb-4">
            <button onclick="setSeccion('tanque')" id="nav_tanque" class="nav-btn px-10 py-2 text-[10px] uppercase active">TANQUES</button>
            <button onclick="setSeccion('avion')" id="nav_avion" class="nav-btn px-10 py-2 text-[10px] uppercase">AVIONES</button>
            <button onclick="setSeccion('flotas')" id="nav_flotas" class="nav-btn px-10 py-2 text-[10px] uppercase">FLOTAS</button>
        </div>

        <div id="cont_hangar" class="space-y-6">
            <?php foreach($hangar_agrupado as $tier => $tipos): ?>
                <div class="tier-container shadow-2xl">
                    <div class="tier-header" onclick="toggleTier(this)">
                        <h2 class="text-red-500 font-black uppercase text-xl tracking-[0.3em] m-0">RANGO / TIER <?php echo $tier; ?></h2>
                        <span class="text-red-600 text-xs font-bold transition-transform duration-300">▼</span>
                    </div>
                    <div class="tier-content p-6 space-y-8 block">
                        <?php foreach(['tanque' => $orden_tanques, 'avion' => $orden_aviones] as $tipo_vehiculo => $orden_clases_jerarquia): 
                            if(!isset($tipos[$tipo_vehiculo])) continue;
                        ?>
                            <div class="seccion-tipo" data-tipo="<?php echo $tipo_vehiculo; ?>">
                                <?php foreach($orden_clases_jerarquia as $clase_nombre): 
                                    if(!isset($tipos[$tipo_vehiculo][$clase_nombre])) continue;
                                ?>
                                    <div class="clase-container mb-8">
                                        <h3 class="text-gray-500 font-black uppercase text-[10px] tracking-[0.2em] border-b border-gray-800 pb-2 mb-4"><?php echo $clase_nombre; ?></h3>
                                        <div class="flex gap-4 overflow-x-auto pb-4 custom-scrollbar">
                                            <?php foreach($tipos[$tipo_vehiculo][$clase_nombre] as $item): 
                                                $inv = $mi_stock[$item['id']] ?? ['cantidad' => 0, 'inv_id' => 0];
                                                $proc = $en_proceso[$inv['inv_id']] ?? 0;
                                                $stock_neto = $inv['cantidad'] - $proc;
                                                $item_json = htmlspecialchars(json_encode(array_merge($item, $inv, ['neto' => $stock_neto])), ENT_QUOTES, 'UTF-8');
                                                $is_premium = isset($item['is_premium']) && $item['is_premium'] == 1;
                                            ?>
                                                <div class="fila-v flex-shrink-0 w-64 flex flex-col bg-[#111] border <?php echo $is_premium ? 'card-premium' : 'border-[#1a1a1a]'; ?> relative hover:brightness-110 transition shadow-lg" data-nacion="<?php echo $item['nacion']; ?>">
                                                    <?php if($is_premium): ?><div class="tag-premium">PREMIUM</div><?php endif; ?>
                                                    <div class="tag-br">BR: <?php echo htmlspecialchars($item['br'] ?? '1.0'); ?></div>
                                                    
                                                    <div class="h-32 bg-black relative border-b border-gray-800 overflow-hidden">
                                                        <img src="../<?php echo $item['imagen_url']; ?>" class="w-full h-full object-cover">
                                                    </div>

                                                    <div class="p-3 flex-grow flex flex-col">
                                                        <span class="text-white font-black uppercase text-[11px] block truncate text-center mb-2"><?php echo htmlspecialchars($item['nombre_vehiculo']); ?></span>
                                                        
                                                        <div class="flex justify-center gap-1 mb-3">
                                                            <span class="text-[7px] bg-blue-900/30 text-blue-400 border border-blue-900/50 px-1.5 py-0.5 rounded font-black uppercase"><?php echo htmlspecialchars($item['tipo']); ?></span>
                                                            <span class="text-[7px] bg-gray-800 text-gray-400 px-1.5 py-0.5 rounded font-black uppercase"><?php echo $clase_nombre; ?></span>
                                                        </div>

                                                        <div class="grid grid-cols-3 gap-0 bg-black border border-gray-800 p-1.5 text-center rounded mb-3">
                                                            <div class="border-r border-gray-800"><span class="stat-grid-label block">CASH</span><span class="stat-grid-value text-green-500">$<?php echo number_format($item['costo_dinero']); ?></span></div>
                                                            <div class="border-r border-gray-800"><span class="stat-grid-label block">STEEL</span><span class="stat-grid-value text-white"><?php echo number_format($item['costo_acero']); ?>T</span></div>
                                                            <div><span class="stat-grid-label block">FUEL</span><span class="stat-grid-value text-yellow-500"><?php echo number_format($item['costo_petroleo']); ?>L</span></div>
                                                        </div>

                                                        <div class="mt-auto pt-2 border-t border-gray-800/50 px-1 flex justify-between items-center">
                                                            <span class="text-gray-500 text-[8px] font-black uppercase tracking-widest">STOCK LIBRE</span>
                                                            <div class="flex flex-col items-end">
                                                                <span class="text-xl font-black <?php echo $stock_neto > 0 ? 'text-[#c5a059]' : 'text-gray-800'; ?>"><?php echo $stock_neto; ?>x</span>
                                                                <?php if($proc > 0): ?>
                                                                    <span class="badge-process">-<?php echo $proc; ?> TRÁNSITO</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="p-2 bg-black/80 border-t border-[#1a1a1a]">
                                                        <?php if(!in_array($item['id'], $mis_planos)): ?>
                                                            <div class="py-2.5 text-center text-[9px] text-red-600 font-black uppercase border border-red-900/30 bg-red-950/10">Requiere Patente</div>
                                                        <?php elseif($stock_neto <= 0): ?>
                                                            <div class="py-2.5 text-center text-[9px] text-gray-700 font-black uppercase border border-white/5">Sin Activos Libres</div>
                                                        <?php else: ?>
                                                            <button onclick='abrirModalReembolso(<?php echo $item_json; ?>)' class="btn-m w-full !py-2.5 !text-[9px] !bg-red-950/30 !text-red-500 border-red-900 font-black uppercase hover:bg-red-700 transition">DEVOLVER AL STAFF</button>
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

        <div id="cont_flotas" class="hidden grid grid-cols-1 md:grid-cols-3 gap-10">
            <?php for($s=1; $s<=3; $s++): $fl = $mis_flotas[$s]; ?>
                <div onclick='abrirEditorFlota(<?php echo $s; ?>, <?php echo json_encode($fl); ?>)' class="slot-box relative <?php echo $fl ? 'filled' : ''; ?>">
                    <?php if(!$fl): ?>
                        <span class="plus-icon">+</span>
                        <span class="text-[11px] font-black text-gray-700 uppercase tracking-[0.3em]">SLOT <?php echo $s; ?> VACÍO</span>
                    <?php else: ?>
                        <button onclick="desmantelarFlotaLider(event, <?php echo $fl['id']; ?>, <?php echo $s; ?>)" class="absolute top-4 right-4 bg-black/80 text-red-500 border border-red-900/50 px-3 py-1 text-[8px] font-black uppercase hover:bg-red-600 hover:text-white transition z-10">Desmantelar</button>
                        <div class="w-full text-center border-b border-yellow-900/20 pb-4 mb-8 text-[#c5a059] font-black text-[11px] uppercase tracking-widest">FLOTA ACTIVADA - SLOT <?php echo $s; ?></div>
                        <div class="w-full space-y-6">
                            <div><span class="stat-label block mb-1">Unidad Insignia</span><div class="bg-black p-3 border border-white/5 text-white font-bold uppercase text-sm"><?php echo htmlspecialchars($fl['insignia']); ?></div></div>
                            <div class="grid grid-cols-2 gap-3 text-gray-400 uppercase text-[10px]">
                                <?php for($esc=1;$esc<=4;$esc++): ?>
                                    <div><span class="stat-label block mb-1">Esc <?php echo $esc; ?></span><div class="bg-black p-2 border border-white/5"><?php echo htmlspecialchars($fl['escolta_'.$esc] ?: '-'); ?></div></div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <span class="mt-auto text-[9px] text-blue-500 font-black uppercase">Editar Configuración</span>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    </main>

    <div id="modalDestroyFlota" class="hidden fixed inset-0 bg-black/98 z-[300] flex items-center justify-center p-4">
        <div class="m-panel w-full max-w-md border-red-600 bg-[#0a0a0a] p-10 text-center relative shadow-2xl">
            <button onclick="cerrarModal('modalDestroyFlota')" class="btn-close-modal">&times;</button>
            <div class="text-red-600 text-5xl mb-6">☢️</div>
            <h2 class="text-white font-black uppercase tracking-[0.2em] mb-4">DESMANTELAR FLOTA</h2>
            <p class="text-gray-400 text-xs font-bold leading-relaxed mb-10 uppercase">Confirmar el desmantelamiento de la Flota #<span id="txt_del_slot"></span>.</p>
            <form action="../logic/borrar_flota.php" method="POST"><input type="hidden" name="flota_id" id="del_flota_id"><button type="submit" class="bg-red-600 text-black w-full py-4 font-black uppercase text-[11px] hover:bg-red-500 transition tracking-widest">CONFIRMAR ORDEN</button></form>
        </div>
    </div>

    <div id="modalCancelarTradeo" class="hidden fixed inset-0 bg-black/98 z-[300] flex items-center justify-center p-4">
        <div class="m-panel w-full max-w-md border-red-600 bg-[#0a0a0a] p-10 text-center relative shadow-2xl">
            <button onclick="cerrarModal('modalCancelarTradeo')" class="btn-close-modal">&times;</button>
            <div class="text-red-600 text-5xl mb-6">⚠️</div>
            <h2 class="text-white font-black uppercase tracking-[0.2em] mb-4">CANCELAR PROPUESTA</h2>
            <p class="text-gray-400 text-xs font-bold leading-relaxed mb-10 uppercase">¿Confirma la anulación inmediata de este enlace diplomático?</p>
            <form action="../logic/procesar_tradeo.php" method="POST"><input type="hidden" name="accion" value="cancelar"><input type="hidden" name="tradeo_id" id="del_tradeo_id_cancel"><button type="submit" class="bg-red-600 text-black w-full py-4 font-black uppercase text-[11px] hover:bg-red-500 transition tracking-widest">CONFIRMAR ANULACIÓN</button></form>
        </div>
    </div>

    <div id="modalHistorial" class="hidden fixed inset-0 bg-black/95 z-[100] flex items-center justify-center p-4"><div class="m-panel w-full max-w-2xl border-red-800 bg-[#0d0e0a] p-10 relative shadow-2xl"><button onclick="cerrarModal('modalHistorial')" class="btn-close-modal">&times;</button><h2 class="text-red-500 font-black text-center text-[10px] uppercase mb-8 border-b border-red-900/50 pb-2 tracking-widest">PETICIONES LOGÍSTICAS EN CURSO</h2><div class="space-y-4 max-h-[50vh] overflow-y-auto pr-2"><?php if(empty($reembolsos_activos)): ?><p class="text-center text-gray-600 uppercase font-black text-xs py-10">No hay peticiones activas.</p><?php else: foreach($reembolsos_activos as $ra): ?><div class="flex justify-between items-center bg-black/40 p-4 border border-white/5 group hover:border-red-900 transition"><div><span class="text-white font-black uppercase text-sm"><?php echo htmlspecialchars($ra['nombre_vehiculo']); ?></span><span class="text-red-500 font-black ml-4">x<?php echo $ra['cantidad']; ?></span></div><form action="../logic/cancelar_reembolso.php" method="POST"><input type="hidden" name="id" value="<?php echo $ra['id']; ?>"><button type="submit" class="bg-red-900/20 text-red-500 border border-red-900 px-5 py-2 text-[9px] font-black uppercase hover:bg-red-700 hover:text-white transition">CANCELAR</button></form></div><?php endforeach; endif; ?></div></div></div>
    <div id="modalReembolso" class="hidden fixed inset-0 bg-black/95 z-[100] flex items-center justify-center p-4"><div class="m-panel w-full max-w-md border-red-800 bg-[#0d0e0a] p-10 shadow-2xl relative"><button onclick="cerrarModal('modalReembolso')" class="btn-close-modal">&times;</button><h2 class="text-red-500 font-black text-center text-[10px] uppercase mb-10 border-b border-red-900/50 pb-2 tracking-widest">SOLICITAR REINTEGRO TÁCTICO</h2><form action="../logic/solicitar_reembolso.php" method="POST"><input type="hidden" id="re_inv_id" name="inventario_id"><div class="text-center mb-8"><span id="re_nombre" class="text-white font-black uppercase text-2xl"></span></div><div class="mb-4 text-center"><label class="stat-label block mb-2">CANTIDAD DISPONIBLE: <span id="re_max_display" class="text-white"></span></label><input type="number" id="re_qty" name="cantidad" value="1" min="1" oninput="calcReMath()" class="f-input !text-4xl py-6 text-center font-black"></div><div id="re_stock_error" class="hidden text-red-500 text-[10px] font-black uppercase text-center mb-6 animate-pulse">⚠️ CANTIDAD SUPERA EL STOCK LIBRE</div><div class="bg-red-950/20 p-6 border border-red-900/30 text-center mb-10"><div class="flex justify-around font-black font-mono"><div><span class="stat-label !mb-1">CASH</span><span id="re_res_d" class="text-green-500 text-xl">$0</span></div><div><span class="stat-label !mb-1">STEEL</span><span id="re_res_a" class="text-white text-xl">0T</span></div><div><span class="stat-label !mb-1">FUEL</span><span id="re_res_p" class="text-yellow-500 text-xl">0L</span></div></div></div><button type="submit" id="btnEnviarRe" class="btn-m w-full py-5 !bg-red-700 !text-white border-red-500 font-black uppercase">ENVIAR PETICIÓN</button></form></div></div>
    <div id="modalError" class="hidden fixed inset-0 bg-black/98 z-[300] flex items-center justify-center p-4"><div class="m-panel w-full max-w-sm border-red-600 bg-[#120505] p-10 text-center shadow-2xl relative"><button onclick="cerrarModal('modalError')" class="btn-close-modal">&times;</button><div class="text-red-500 text-5xl mb-6">⚠️</div><h3 class="text-white font-black uppercase tracking-widest mb-4">ERROR OPERATIVO</h3><p id="error_msg_text" class="text-gray-400 text-xs uppercase font-bold mb-8 leading-relaxed"></p><button onclick="cerrarModal('modalError')" class="btn-m w-full !bg-red-900/20 !border-red-600 !text-red-500 py-3 font-black uppercase">ENTENDIDO</button></div></div>

    <div id="modalMercado" class="hidden fixed inset-0 bg-black/98 z-[150] flex items-center justify-center p-4">
        <div class="m-panel w-full max-w-3xl h-auto max-h-[90vh] relative border-blue-900 overflow-hidden flex flex-col shadow-2xl">
            <button onclick="cerrarModal('modalMercado')" class="btn-close-modal">&times;</button>
            <div class="flex border-b border-blue-900/30 bg-black/40">
                <button onclick="subTabMercado('crear')" id="sm_crear" class="flex-1 py-4 text-[9px] font-black uppercase tracking-widest border-b-2 border-blue-500 bg-blue-500/10 text-white">NUEVO ENLACE CIFRADO</button>
                <button onclick="subTabMercado('ordenes')" id="sm_ordenes" class="flex-1 py-4 text-[9px] font-black uppercase tracking-widest border-b-2 border-transparent text-gray-500">MIS ÓRDENES ACTIVAS (<?php echo count($mis_ordenes); ?>)</button>
                <button onclick="subTabMercado('recibidas')" id="sm_recibidas" class="flex-1 py-4 text-[9px] font-black uppercase tracking-widest border-b-2 border-transparent text-yellow-500">ENTRANTES (<?php echo count($ofertas_recibidas); ?>)</button>
            </div>
            <div id="sec_m_crear" class="flex-grow overflow-y-auto p-8 bg-[#05070a] relative custom-scrollbar">
                <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')] opacity-10 pointer-events-none"></div>
                <form action="../logic/procesar_tradeo.php" method="POST" onsubmit="return validarTradeo(event)" class="relative z-10 max-w-2xl mx-auto">
                    <input type="hidden" name="accion" value="crear"><input type="hidden" name="receptor_id" id="t_receptor_id"><input type="hidden" name="vehiculo_requerido_id" value="0"><input type="hidden" name="cantidad_requerida" value="0">
                    <div class="mb-8 p-6 border border-blue-900/30 bg-black/60 text-center">
                        <h3 class="text-blue-500 font-black text-[10px] uppercase mb-4 tracking-widest">1. SELECCIONE DESTINATARIO</h3>
                        <div class="flex flex-wrap justify-center gap-3 mb-4" id="lista_rivales">
                            <?php foreach($rivales as $ri): ?>
                                <button type="button" onclick='seleccionarRival(<?php echo json_encode($ri); ?>)' class="btn-rival-selector flex items-center gap-2 p-2 border border-gray-800 bg-[#0a0a0a] hover:border-blue-500 transition group" id="btn-rival-<?php echo $ri['id']; ?>">
                                    <div class="w-6 h-4 bg-gray-900 overflow-hidden border border-gray-700"><img src="../<?php echo $ri['bandera_url']; ?>" class="w-full h-full object-cover grayscale group-hover:grayscale-0"></div>
                                    <span class="text-[9px] text-gray-400 font-black uppercase group-hover:text-white"><?php echo htmlspecialchars($ri['nombre_equipo']); ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <div id="rival_seleccionado_box" class="hidden mt-4 p-4 bg-blue-900/20 border border-blue-500/50 rounded"><span id="rival_seleccionado_txt" class="text-white font-black text-xl uppercase font-['Cinzel']"></span></div>
                    </div>
                    <div class="space-y-4">
                        <h3 class="text-blue-500 font-black text-[10px] uppercase mb-2 tracking-widest text-center border-b border-blue-900/30 pb-2">2. CONFIGURE SU OFERTA</h3>
                        <div class="flex gap-2 mb-6 bg-black/40 p-2 border border-gray-800">
                            <button type="button" onclick="setModoOferta('recursos')" id="btn_modo_recursos" class="btn-modo flex-1 py-3 bg-blue-900/20 border border-blue-500 text-white text-[9px] font-black uppercase">Solo Recursos</button>
                            <button type="button" onclick="setModoOferta('vehiculo')" id="btn_modo_vehiculo" class="btn-modo flex-1 py-3 bg-[#050505] border border-transparent text-gray-600 text-[9px] font-black uppercase hover:text-gray-300">Solo Vehículo</button>
                            <button type="button" onclick="setModoOferta('mixto')" id="btn_modo_mixto" class="btn-modo flex-1 py-3 bg-[#050505] border border-transparent text-gray-600 text-[9px] font-black uppercase hover:text-gray-300">Mixta</button>
                        </div>
                        <div id="sec_recursos" class="grid grid-cols-3 gap-4 transition-opacity">
                            <div class="text-center"><label class="stat-label block mb-2">CASH</label><input type="number" name="ofrece_dinero" id="off_d" value="0" min="0" oninput="projBal()" class="f-input !p-3 !text-lg text-green-500 text-center font-black"><div class="mt-2"><span id="bal_d" class="balance-pill text-green-500">Saldo: $<?php echo number_format($user['dinero']); ?></span></div></div>
                            <div class="text-center"><label class="stat-label block mb-2">STEEL</label><input type="number" name="ofrece_acero" id="off_a" value="0" min="0" oninput="projBal()" class="f-input !p-3 !text-lg text-white text-center font-black"><div class="mt-2"><span id="bal_a" class="balance-pill text-white">Saldo: <?php echo $user['acero']; ?>T</span></div></div>
                            <div class="text-center"><label class="stat-label block mb-2">FUEL</label><input type="number" name="ofrece_petroleo" id="off_p" value="0" min="0" oninput="projBal()" class="f-input !p-3 !text-lg text-yellow-500 text-center font-black"><div class="mt-2"><span id="bal_p" class="balance-pill text-yellow-500">Saldo: <?php echo $user['petroleo']; ?>L</span></div></div>
                        </div>
                        <div id="sec_vehiculo" class="bg-black/40 p-5 border border-white/5 mt-6 transition-opacity opacity-30 pointer-events-none">
                            <label class="stat-label block mb-3 text-blue-400">TRANSFERIR VEHÍCULO PROPIO (OPCIONAL):</label>
                            <div class="flex gap-4 items-center">
                                <select name="vehiculo_ofrecido_id" id="select_mio" onchange="actualizarOfertaMio()" class="f-input !text-[11px] flex-grow !p-3 font-black uppercase">
                                    <option value="">-- NINGUNO --</option>
                                    <?php foreach($mi_catalogo_js as $id => $d): if($d['stock'] > 0): ?><option value="<?php echo $id; ?>"><?php echo htmlspecialchars($d['nombre']); ?> (<?php echo $d['stock']; ?>x Disp)</option><?php endif; endforeach; ?>
                                </select>
                            </div>
                            <div id="mio_extra" class="hidden mt-4 bg-[#0a0a0a] border border-gray-800 p-4">
                                <div class="flex justify-between items-center mb-4 border-b border-gray-800 pb-3"><span class="stat-label !text-blue-500">VALOR DEL ACTIVO:</span><div id="mio_valor" class="flex gap-4 text-[11px] font-black tracking-widest font-mono"></div></div>
                                <div class="flex items-center justify-between"><span class="stat-label">CANTIDAD A TRANSFERIR:</span><input type="number" name="cantidad_ofrecida" id="ofre_qty" value="1" min="1" oninput="multiValMio()" class="w-24 f-input !text-blue-400 text-center !p-2 font-black text-xl"></div>
                            </div>
                        </div>
                    </div>
                    <button type="submit" id="btn_enviar_trato" class="btn-m w-full py-5 !bg-gray-800 !text-gray-500 border-gray-600 text-[10px] font-black uppercase mt-10 tracking-widest cursor-not-allowed" disabled>SELECCIONE DESTINATARIO PRIMERO</button>
                </form>
            </div>
            <div id="sec_m_ordenes" class="hidden flex-grow overflow-y-auto p-10 bg-[#05070a]">
                <?php if(empty($mis_ordenes)): ?><p class="text-center text-gray-600 uppercase font-black text-xs py-10">No hay ofertas en curso.</p><?php else: foreach($mis_ordenes as $o): ?>
                    <div class="m-panel bg-black/60 border-l-4 border-l-blue-600 p-6 flex justify-between items-center mb-4">
                        <div class="flex-grow pr-6">
                            <span class="stat-label block mb-1 uppercase font-bold">ENVIADO A: <?php echo htmlspecialchars($o['receptor']); ?></span>
                            <div class="mt-3 pt-3 border-t border-blue-900/30">
                                <div class="flex flex-wrap gap-4 text-[11px] font-black font-mono">
                                    <?php if($o['ofrece_dinero'] > 0): ?><span class="text-green-500">$<?php echo number_format($o['ofrece_dinero']); ?></span><?php endif; ?>
                                    <?php if($o['ofrece_acero'] > 0): ?><span class="text-white"><?php echo number_format($o['ofrece_acero']); ?>T</span><?php endif; ?>
                                    <?php if($o['ofrece_petroleo'] > 0): ?><span class="text-yellow-500"><?php echo number_format($o['ofrece_petroleo']); ?>L</span><?php endif; ?>
                                    <?php if(!empty($o['v_ofrecido'])): ?><span class="text-blue-400 bg-blue-900/20 px-2 py-1 rounded border border-blue-900/50"><?php echo htmlspecialchars($o['v_ofrecido']); ?> (x<?php echo $o['cantidad_ofrecida']; ?>)</span><?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <button type="button" onclick="confirmarCancelarTradeo(<?php echo $o['id']; ?>)" class="bg-red-900/20 text-red-500 border border-red-900 px-6 py-2 text-[10px] font-black uppercase hover:bg-red-700 transition">CANCELAR</button>
                    </div>
                <?php endforeach; endif; ?>
            </div>
            <div id="sec_m_recibidas" class="hidden flex-grow overflow-y-auto p-10 bg-[#05070a] custom-scrollbar">
                <?php if(empty($ofertas_recibidas)): ?><p class="text-center text-gray-600 uppercase font-black text-xs py-10">Sin propuestas entrantes.</p>
                <?php else: foreach($ofertas_recibidas as $or): $or_json = htmlspecialchars(json_encode($or), ENT_QUOTES, 'UTF-8'); ?>
                    <div class="m-panel bg-blue-900/10 border-l-4 border-l-blue-600 p-6 flex justify-between items-center mb-4">
                        <div><span class="stat-label text-yellow-600">REMITENTE: <?php echo htmlspecialchars($or['remitente']); ?></span><h4 class="text-white font-black uppercase text-sm mt-1">TRANSMISIÓN DIPLOMÁTICA ABIERTA</h4></div>
                        <button onclick='abrirDecisionTradeo(<?php echo $or_json; ?>)' class="btn-m !bg-blue-600 !text-white px-6 py-2 text-[10px] font-black uppercase">REVISAR PROPUESTA</button>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <div id="modalFlota" class="hidden fixed inset-0 bg-black/95 z-[100] flex items-center justify-center p-4">
        <div class="m-panel w-full max-w-lg border-blue-900 bg-[#0d0e0a] p-10 shadow-2xl relative">
            <button onclick="cerrarModal('modalFlota')" class="btn-close-modal">&times;</button>
            <h2 class="text-white font-black uppercase mb-8 border-b border-gray-800 pb-4 tracking-widest text-center">CONFIGURACIÓN DE SLOT #<span id="slot_num"></span></h2>
            <form action="../logic/actualizar_flota.php" method="POST" class="space-y-6">
                <input type="hidden" name="slot" id="slot_input">
                <div><label class="stat-label">Unidad Insignia</label><input type="text" name="insignia" id="in_ins" required class="f-input" placeholder="..."></div>
                <div class="grid grid-cols-2 gap-4">
                    <?php for($e=1;$e<=4;$e++): ?>
                        <div><label class="stat-label">Escolta #<?php echo $e; ?></label><input type="text" name="escolta_<?php echo $e; ?>" id="in_e<?php echo $e; ?>" class="f-input" placeholder="..."></div>
                    <?php endfor; ?>
                </div>
                <div class="flex gap-4 pt-6">
                    <button type="submit" class="btn-m flex-1 !bg-blue-600 !text-white !py-4 font-black">GRABAR FLOTA</button>
                    <button type="button" onclick="cerrarModal('modalFlota')" class="px-8 border border-white/10 text-gray-500 font-black text-[10px]">CANCELAR</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalDecisionTradeo" class="hidden fixed inset-0 bg-black/98 z-[300] flex items-center justify-center p-4 backdrop-blur-md">
        <div class="m-panel w-full max-w-md border-blue-600 bg-[#0a0a0a] p-10 relative shadow-2xl">
            <button onclick="cerrarModal('modalDecisionTradeo')" class="btn-close-modal">&times;</button>
            <h2 class="text-blue-500 font-black text-center text-[10px] uppercase mb-8 tracking-[0.3em]">ANÁLISIS DE PROPUESTA</h2>
            <div class="text-center mb-8"><span class="stat-label">REMITENTE:</span><div id="dec_remitente" class="text-white font-black text-2xl uppercase font-['Cinzel']"></div></div>
            <div class="bg-black/60 border border-white/5 p-6 mb-10 text-center"><div id="dec_contenido" class="space-y-3 font-mono font-black text-lg uppercase"></div></div>
            <div class="grid grid-cols-2 gap-4">
                <form action="../logic/procesar_tradeo.php" method="POST"><input type="hidden" name="accion" value="aceptar"><input type="hidden" id="dec_id_acep" name="tradeo_id"><button type="submit" class="w-full bg-green-600 text-black py-5 font-black uppercase text-[11px] hover:bg-green-500 transition shadow-lg">ACEPTAR</button></form>
                <form action="../logic/procesar_tradeo.php" method="POST"><input type="hidden" name="accion" value="rechazar"><input type="hidden" id="dec_id_rech" name="tradeo_id"><button type="submit" class="w-full border border-red-600 text-red-500 py-5 font-black uppercase text-[11px] hover:bg-red-900 transition">RECHAZAR</button></form>
            </div>
        </div>
    </div>

    <script>
        const resActual = { d: <?php echo $user['dinero']; ?>, a: <?php echo $user['acero']; ?>, p: <?php echo $user['petroleo']; ?> };
        const invGlobal = <?php echo json_encode($inventario_global); ?>;
        const miHangarPrecios = <?php echo json_encode($mi_catalogo_js); ?>;
        let itemReSel = null, nacActual = '<?php echo $mis_naciones[0] ?? ""; ?>';
        let rSel = null, modoOferta = 'recursos'; 

        function toggleTier(el) {
            const content = el.nextElementSibling;
            const arrow = el.querySelector('span');
            content.style.display = (content.style.display === 'none') ? 'block' : 'none';
            arrow.style.transform = (content.style.display === 'none') ? 'rotate(0deg)' : 'rotate(180deg)';
        }
        
        function setNacion(n) { nacActual = n; document.querySelectorAll('.tab-nacion').forEach(b => b.classList.toggle('active', b.dataset.nacion === n)); aplicarFiltros(); }
        function setSeccion(s) { if(s === 'flotas') { document.getElementById('cont_hangar').style.display = 'none'; document.getElementById('cont_flotas').classList.remove('hidden'); } else { tipoActual = s; document.getElementById('cont_hangar').style.display = 'block'; document.getElementById('cont_flotas').classList.add('hidden'); aplicarFiltros(); } document.querySelectorAll('.nav-btn').forEach(b => b.classList.toggle('active', b.id === 'nav_'+s)); }
        
        function aplicarFiltros() {
            let total = 0;
            document.querySelectorAll('.tier-container').forEach(tier => {
                let tierVisible = false;
                tier.querySelectorAll('.seccion-tipo').forEach(sec => {
                    if (sec.dataset.tipo === (typeof tipoActual !== 'undefined' ? tipoActual : 'tanque')) {
                        sec.style.display = 'block';
                        sec.querySelectorAll('.clase-container').forEach(clase => {
                            const items = Array.from(clase.querySelectorAll('.fila-v')).filter(i => i.dataset.nacion === nacActual);
                            clase.style.display = items.length > 0 ? 'block' : 'none';
                            items.forEach(i => i.style.display = 'flex');
                            if (items.length > 0) tierVisible = true;
                        });
                    } else sec.style.display = 'none';
                });
                tier.style.display = tierVisible ? 'block' : 'none';
                if(tierVisible) total++;
            });
        }

        function projBal() {
            const offD = parseInt(document.getElementById('off_d').value) || 0, offA = parseInt(document.getElementById('off_a').value) || 0, offP = parseInt(document.getElementById('off_p').value) || 0;
            const update = (id, cur, off, sym) => { const el = document.getElementById(id), rem = cur - off; el.innerText = `Saldo: ${rem.toLocaleString()}${sym}`; el.style.color = rem < 0 ? '#ff0000' : ''; };
            update('bal_d', resActual.d, offD, '$'); update('bal_a', resActual.a, offA, 'T'); update('bal_p', resActual.p, offP, 'L');
        }

        function abrirModalReembolso(i) { itemReSel = i; document.getElementById('re_nombre').innerText = i.nombre_vehiculo; document.getElementById('re_max_display').innerText = i.neto + " UNIDADES LIBRES"; document.getElementById('re_inv_id').value = i.inv_id; document.getElementById('re_qty').value = 1; calcReMath(); abrirModal('modalReembolso'); }
        function calcReMath() { const input = document.getElementById('re_qty'), q = parseInt(input.value) || 0, btn = document.getElementById('btnEnviarRe'), err = document.getElementById('re_stock_error'); if(q > itemReSel.neto) { input.classList.add('input-error'); btn.disabled = true; btn.style.opacity = '0.3'; err.classList.remove('hidden'); } else { input.classList.remove('input-error'); btn.disabled = false; btn.style.opacity = '1'; err.classList.add('hidden'); } document.getElementById('re_res_d').innerText = '$'+(q * itemReSel.costo_dinero).toLocaleString(); document.getElementById('re_res_a').innerText = (q * itemReSel.costo_acero).toLocaleString()+'T'; document.getElementById('re_res_p').innerText = (q * itemReSel.costo_petroleo).toLocaleString()+'L'; }
        
        function subTabMercado(s) {
            ['crear', 'ordenes', 'recibidas'].forEach(tab => {
                const sec = document.getElementById('sec_m_' + tab);
                const btn = document.getElementById('sm_' + tab);
                if (sec) sec.classList.toggle('hidden', tab !== s);
                if (btn) {
                    btn.classList.toggle('border-b-2', tab === s);
                    btn.classList.toggle('border-blue-500', tab === s);
                    btn.classList.toggle('bg-blue-500/10', tab === s);
                    btn.classList.toggle('text-white', tab === s);
                }
            });
        }

        function abrirDecisionTradeo(data) {
            document.getElementById('dec_id_acep').value = data.id;
            document.getElementById('dec_id_rech').value = data.id;
            document.getElementById('dec_remitente').innerText = data.remitente;
            let h = "";
            if(parseInt(data.ofrece_dinero) > 0) h += `<div class='text-green-500'>$${parseInt(data.ofrece_dinero).toLocaleString()}</div>`;
            if(parseInt(data.ofrece_acero) > 0) h += `<div class='text-white'>${data.ofrece_acero}T ACERO</div>`;
            if(parseInt(data.ofrece_petroleo) > 0) h += `<div class='text-yellow-500'>${data.ofrece_petroleo}L FUEL</div>`;
            if(data.v_ofrecido_nombre) h += `<div class='text-blue-400 mt-2 border-t border-white/5 pt-2'>1x ${data.v_ofrecido_nombre}</div>`;
            document.getElementById('dec_contenido').innerHTML = h;
            abrirModal('modalDecisionTradeo');
        }

        function abrirEditorFlota(s, d) { document.getElementById('slot_num').innerText = s; document.getElementById('slot_input').value = s; document.getElementById('in_ins').value = d ? d.insignia : ''; document.getElementById('in_e1').value = d ? d.escolta_1 : ''; document.getElementById('in_e2').value = d ? d.escolta_2 : ''; document.getElementById('in_e3').value = d ? d.escolta_3 : ''; document.getElementById('in_e4').value = d ? d.escolta_4 : ''; abrirModal('modalFlota'); }
        function abrirModal(id) { document.getElementById(id).classList.remove('hidden'); document.body.classList.add('modal-active'); }
        function cerrarModal(id) { document.getElementById(id).classList.add('hidden'); document.body.classList.remove('modal-active'); }
        function confirmarCancelarTradeo(id) { document.getElementById('del_tradeo_id_cancel').value = id; abrirModal('modalCancelarTradeo'); }
        function desmantelarFlotaLider(e, id, slot) { e.stopPropagation(); document.getElementById('del_flota_id').value = id; document.getElementById('txt_del_slot').innerText = slot; abrirModal('modalDestroyFlota'); }

        function seleccionarRival(r) { rSel = r; document.querySelectorAll('.btn-rival-selector').forEach(b => { b.classList.remove('active', 'border-blue-500', 'bg-blue-900/20'); b.classList.add('border-gray-800', 'bg-[#0a0a0a]'); }); const btn = document.getElementById('btn-rival-'+r.id); btn.classList.remove('border-gray-800', 'bg-[#0a0a0a]'); btn.classList.add('active', 'border-blue-500', 'bg-blue-900/20'); document.getElementById('t_receptor_id').value = r.id; document.getElementById('rival_seleccionado_box').classList.remove('hidden'); document.getElementById('rival_seleccionado_txt').innerText = r.nombre_equipo; const btnEnviar = document.getElementById('btn_enviar_trato'); btnEnviar.disabled = false; btnEnviar.classList.remove('!bg-gray-800', '!text-gray-500', 'border-gray-600', 'cursor-not-allowed'); btnEnviar.classList.add('!bg-blue-600', '!text-white', '!border-blue-400'); btnEnviar.innerText = "ENVIAR PROPUESTA CIFRADA"; }
        function setModoOferta(modo) { modoOferta = modo; document.querySelectorAll('.btn-modo').forEach(b => { b.classList.remove('bg-blue-900/20', 'border-blue-500', 'text-white'); b.classList.add('bg-[#050505]', 'border-transparent', 'text-gray-600'); }); const btnActivo = document.getElementById('btn_modo_'+modo); btnActivo.classList.remove('bg-[#050505]', 'border-transparent', 'text-gray-600'); btnActivo.classList.add('bg-blue-900/20', 'border-blue-500', 'text-white'); const secR = document.getElementById('sec_recursos'); const secV = document.getElementById('sec_vehiculo'); if(modo === 'recursos') { secR.style.opacity = '1'; secR.style.pointerEvents = 'auto'; secV.style.opacity = '0.3'; secV.style.pointerEvents = 'none'; document.getElementById('select_mio').value = ""; actualizarOfertaMio(); } else if(modo === 'vehiculo') { secR.style.opacity = '0.3'; secR.style.pointerEvents = 'none'; secV.style.opacity = '1'; secV.style.pointerEvents = 'auto'; document.getElementById('off_d').value = 0; document.getElementById('off_a').value = 0; document.getElementById('off_p').value = 0; projBal(); } else { secR.style.opacity = '1'; secR.style.pointerEvents = 'auto'; secV.style.opacity = '1'; secV.style.pointerEvents = 'auto'; } }
        function actualizarOfertaMio() { const id = document.getElementById('select_mio').value; if(id && miHangarPrecios[id]) { document.getElementById('mio_extra').classList.remove('hidden'); document.getElementById('ofre_qty').max = miHangarPrecios[id].stock; document.getElementById('ofre_qty').value = 1; multiValMio(); } else document.getElementById('mio_extra').classList.add('hidden'); }
        function multiValMio() { const id = document.getElementById('select_mio').value; const q = parseInt(document.getElementById('ofre_qty').value) || 0; if(id && miHangarPrecios[id]) { const d = miHangarPrecios[id]; document.getElementById('mio_valor').innerHTML = `<span class='text-green-500'>$${(q * d.dinero).toLocaleString()}</span><span class='text-white'>${(q * d.acero).toLocaleString()}T</span><span class='text-yellow-500'>${(q * d.petroleo).toLocaleString()}L</span>`; } }
        function mostrarError(txt) { document.getElementById('error_msg_text').innerText = txt; abrirModal('modalError'); }
        function validarTradeo(e) { if(!rSel) { e.preventDefault(); mostrarError("OPERACIÓN DENEGADA: Debe seleccionar una facción destinataria."); return false; } if(parseInt(document.getElementById('off_d').value) > resActual.d || parseInt(document.getElementById('off_a').value) > resActual.a || parseInt(document.getElementById('off_p').value) > resActual.p) { e.preventDefault(); mostrarError("FONDOS INSUFICIENTES: Operación cancelada por falta de liquidez."); return false; } if(modoOferta === 'recursos' && parseInt(document.getElementById('off_d').value) === 0 && parseInt(document.getElementById('off_a').value) === 0 && parseInt(document.getElementById('off_p').value) === 0) { e.preventDefault(); mostrarError("ERROR: La oferta de recursos no puede estar vacía."); return false; } if(modoOferta === 'vehiculo' && document.getElementById('select_mio').value === "") { e.preventDefault(); mostrarError("ERROR: Debe seleccionar un vehículo a transferir."); return false; } return true; }

        let tipoActual = 'tanque';
        setNacion(nacActual); setSeccion(tipoActual);
    </script>
</body>
</html>