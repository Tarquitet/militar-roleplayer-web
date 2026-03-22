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
    $naciones_base = !empty($user['naciones_activas']) ? array_map('trim', explode(',', $user['naciones_activas'])) : [];
    
    $stmt_extra = $pdo->prepare("SELECT DISTINCT c.nacion FROM inventario i JOIN catalogo_tienda c ON i.catalogo_id = c.id WHERE i.cuenta_id = :id AND i.cantidad > 0");
    $stmt_extra->execute([':id' => $lider_id]);
    $naciones_mercado = $stmt_extra->fetchAll(PDO::FETCH_COLUMN);

    $mis_naciones = array_values(array_filter(array_unique(array_merge($naciones_base, $naciones_mercado))));

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
    foreach($reembolsos_activos as $ra) { 
        $en_proceso[$ra['inventario_id']] = ($en_proceso[$ra['inventario_id']] ?? 0) + $ra['cantidad']; 
    }
    
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

        // NUEVA LECTURA SEPARANDO ORÍGENES DE INVENTARIO
        $stmt_inv = $pdo->prepare("SELECT id as inv_id, catalogo_id, cantidad, origen FROM inventario WHERE cuenta_id = :id");
        $stmt_inv->execute([':id' => $lider_id]);
        foreach($stmt_inv->fetchAll(PDO::FETCH_ASSOC) as $ri) { 
            $mi_stock[$ri['catalogo_id']][$ri['origen']] = $ri; 
        }

        foreach($catalogo_nacional as $cn) { 
            $s_tienda = $mi_stock[$cn['id']]['tienda']['cantidad'] ?? 0;
            $s_tradeo = $mi_stock[$cn['id']]['tradeo']['cantidad'] ?? 0;
            $mi_catalogo_js[$cn['id']] = ['nombre' => $cn['nombre_vehiculo'], 'dinero' => $cn['costo_dinero'], 'acero' => $cn['costo_acero'], 'petroleo' => $cn['costo_petroleo'], 'stock_total' => ($s_tienda + $s_tradeo)]; 
            
            $tier = $cn['rango'] ?? 1;
            $tipo = $cn['tipo'] ?? 'tanque';
            $clase = !empty($cn['subtipo']) ? $cn['subtipo'] : (!empty($cn['clase']) ? $cn['clase'] : 'No Clasificado'); 
            $hangar_agrupado[$tier][$tipo][$clase][] = $cn;
        }
        ksort($hangar_agrupado); 
    }

    $orden_tanques = ['Ligero', 'Mediano', 'Pesado', 'Caza Tanques', 'AAA'];
    $orden_aviones = ['Caza', 'Interceptor', 'Avion de Ataque', 'Bombardero en Picado', 'Bombardero de Pimera Línea'];

    $stmt_rivales = $pdo->prepare("SELECT id, nombre_equipo, bandera_url, naciones_activas FROM cuentas WHERE rol = 'lider' AND id != :id ORDER BY nombre_equipo ASC");
    $stmt_rivales->execute([':id' => $lider_id]);
    $rivales = $stmt_rivales->fetchAll(PDO::FETCH_ASSOC);

    $stmt_inv_global = $pdo->prepare("SELECT i.cuenta_id, c.*, i.cantidad as stock_actual FROM inventario i JOIN catalogo_tienda c ON i.catalogo_id = c.id WHERE i.cuenta_id != :id AND i.cantidad > 0");
    $stmt_inv_global->execute([':id' => $lider_id]);
    $inventario_global = $stmt_inv_global->fetchAll(PDO::FETCH_ASSOC);

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
    <title><?php echo $txt['LIDER_INVENTARIO']['TITULO_PAGINA']; ?></title>
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
                <span class="text-[#c5a059] text-[10px] font-black uppercase"><?php echo $txt['LIDER_INVENTARIO']['LBL_TERRITORIOS']; ?></span>
                <div class="flex gap-2">
                    <?php foreach ($mis_naciones as $n): ?>
                        <button onclick="setNacion('<?php echo htmlspecialchars($n); ?>')" data-nacion="<?php echo htmlspecialchars($n); ?>" class="tab-nacion px-4 py-1 text-[10px] font-black uppercase"><?php echo htmlspecialchars($n); ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="flex gap-4">
                <button onclick="abrirModal('modalHistorial')" class="btn-m !bg-red-950/20 !border-red-900 !text-red-500 !py-2 !px-6 text-[10px] font-black uppercase"><?php echo $txt['LIDER_INVENTARIO']['BTN_SOLICITUDES']; ?> (<?php echo count($reembolsos_activos); ?>)</button>
                <button onclick="abrirModal('modalMercado')" class="btn-m !bg-blue-900/30 !border-blue-700 !text-blue-400 !py-2 !px-6 text-[10px] font-black uppercase <?php echo $tiene_contratos ? 'btn-trade-active' : ''; ?>"><?php echo $txt['LIDER_INVENTARIO']['BTN_MERCADO']; ?></button>
            </div>
        </div>
    </nav>

    <main class="p-8 max-w-[1600px] mx-auto mt-4">
        <div class="flex gap-4 mb-10 border-b border-gray-800 pb-4">
            <button onclick="setSeccion('tanque')" id="nav_tanque" class="nav-btn px-10 py-2 text-[10px] uppercase active"><?php echo $txt['LIDER_INVENTARIO']['TAB_TANQUES']; ?></button>
            <button onclick="setSeccion('avion')" id="nav_avion" class="nav-btn px-10 py-2 text-[10px] uppercase"><?php echo $txt['LIDER_INVENTARIO']['TAB_AVIONES']; ?></button>
            <button onclick="setSeccion('flotas')" id="nav_flotas" class="nav-btn px-10 py-2 text-[10px] uppercase"><?php echo $txt['LIDER_INVENTARIO']['TAB_FLOTAS']; ?></button>
        </div>

        <div id="cont_hangar" class="space-y-6">
            <?php foreach($hangar_agrupado as $tier => $tipos): ?>
                <div class="tier-container shadow-2xl">
                    <div class="tier-header" onclick="toggleTier(this)">
                        <h2 class="text-red-500 font-black uppercase text-xl tracking-[0.3em] m-0"><?php echo $txt['LIDER_INVENTARIO']['LBL_RANGO_TIER']; ?> <?php echo $tier; ?></h2>
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
                                                
                                                // Separar orígenes
                                                $stock_tienda = $mi_stock[$item['id']]['tienda'] ?? null;
                                                $stock_tradeo = $mi_stock[$item['id']]['tradeo'] ?? null;
                                                
                                                $proc_t = $stock_tienda ? ($en_proceso[$stock_tienda['inv_id']] ?? 0) : 0;
                                                $proc_tr = $stock_tradeo ? ($en_proceso[$stock_tradeo['inv_id']] ?? 0) : 0;
                                                
                                                $neto_tienda = $stock_tienda ? ($stock_tienda['cantidad'] - $proc_t) : 0;
                                                $neto_tradeo = $stock_tradeo ? ($stock_tradeo['cantidad'] - $proc_tr) : 0;
                                                
                                                $stock_total = $neto_tienda + $neto_tradeo;
                                                $proc_total = $proc_t + $proc_tr;
                                                
                                                $is_premium = isset($item['is_premium']) && $item['is_premium'] == 1;
                                            ?>
                                                <div class="fila-v flex-shrink-0 w-64 flex flex-col bg-[#111] border <?php echo $is_premium ? 'card-premium' : 'border-[#1a1a1a]'; ?> relative hover:brightness-110 transition shadow-lg" data-nacion="<?php echo $item['nacion']; ?>">
                                                    <?php if($is_premium): ?><div class="tag-premium"><?php echo $txt['LIDER_INVENTARIO']['TAG_PREMIUM']; ?></div><?php endif; ?>
                                                    <div class="tag-br"><?php echo $txt['LIDER_INVENTARIO']['LBL_BR']; ?> <?php echo htmlspecialchars($item['br'] ?? '1.0'); ?></div>
                                                    
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
                                                            <div class="border-r border-gray-800"><span class="stat-grid-label block"><?php echo $txt['LIDER_INVENTARIO']['LBL_CASH']; ?></span><span class="stat-grid-value text-green-500">$<?php echo number_format($item['costo_dinero']); ?></span></div>
                                                            <div class="border-r border-gray-800"><span class="stat-grid-label block"><?php echo $txt['LIDER_INVENTARIO']['LBL_STEEL']; ?></span><span class="stat-grid-value text-white"><?php echo number_format($item['costo_acero']); ?>T</span></div>
                                                            <div><span class="stat-grid-label block"><?php echo $txt['LIDER_INVENTARIO']['LBL_FUEL']; ?></span><span class="stat-grid-value text-yellow-500"><?php echo number_format($item['costo_petroleo']); ?>L</span></div>
                                                        </div>

                                                        <div class="mt-auto pt-2 border-t border-gray-800/50 px-1 flex justify-between items-center">
                                                            <span class="text-gray-500 text-[8px] font-black uppercase tracking-widest"><?php echo $txt['LIDER_INVENTARIO']['LBL_STOCK_LIBRE']; ?></span>
                                                            <div class="flex flex-col items-end">
                                                                <span class="text-xl font-black <?php echo $stock_total > 0 ? 'text-[#c5a059]' : 'text-gray-800'; ?>"><?php echo $stock_total; ?>x</span>
                                                                <?php if($proc_total > 0): ?>
                                                                    <span class="badge-process">-<?php echo $proc_total; ?> <?php echo $txt['LIDER_INVENTARIO']['LBL_TRANSITO']; ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="p-2 bg-black/80 border-t border-[#1a1a1a]">
                                                        <?php if(!in_array($item['id'], $mis_planos)): ?>
                                                            <div class="py-2.5 text-center text-[9px] text-red-600 font-black uppercase border border-red-900/30 bg-red-950/10"><?php echo $txt['LIDER_INVENTARIO']['BTN_REQ_PATENTE']; ?></div>
                                                        <?php else: ?>
                                                            
                                                            <?php if($neto_tienda > 0): 
                                                                $json_tienda = htmlspecialchars(json_encode(array_merge($item, $stock_tienda, ['neto' => $neto_tienda])), ENT_QUOTES, 'UTF-8');
                                                            ?>
                                                                <button onclick='abrirModalReembolso(<?php echo $json_tienda; ?>)' class="btn-m w-full !py-1.5 !mb-1 !text-[8px] !bg-red-950/30 !text-red-500 border-red-900 font-black uppercase hover:bg-red-700 hover:text-white transition tracking-widest">
                                                                    <?php echo $txt['LIDER_INVENTARIO']['BTN_DEVOLVER']; ?> TIENDA (<?php echo $neto_tienda; ?>)
                                                                </button>
                                                            <?php endif; ?>

                                                            <?php if($neto_tradeo > 0): 
                                                                $json_tradeo = htmlspecialchars(json_encode(array_merge($item, $stock_tradeo, ['neto' => $neto_tradeo])), ENT_QUOTES, 'UTF-8');
                                                            ?>
                                                                <button onclick='abrirModalAnularTradeo(<?php echo $json_tradeo; ?>)' class="btn-m w-full !py-1.5 !text-[8px] !bg-yellow-950/30 !text-yellow-500 border-yellow-900 font-black uppercase hover:bg-yellow-700 hover:text-black transition tracking-widest">
                                                                    SOLICITAR ANULAR TRADEO (<?php echo $neto_tradeo; ?>)
                                                                </button>
                                                            <?php endif; ?>

                                                            <?php if($neto_tienda <= 0 && $neto_tradeo <= 0): ?>
                                                                <div class="py-2.5 text-center text-[9px] text-gray-700 font-black uppercase border border-white/5"><?php echo $txt['LIDER_INVENTARIO']['BTN_SIN_ACTIVOS']; ?></div>
                                                            <?php endif; ?>

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
                        <span class="text-[11px] font-black text-gray-700 uppercase tracking-[0.3em]"><?php echo $txt['LIDER_INVENTARIO']['LBL_SLOT_VACIO']; ?> <?php echo $s; ?> <?php echo $txt['LIDER_INVENTARIO']['LBL_VACIO']; ?></span>
                    <?php else: ?>
                        <button onclick="desmantelarFlotaLider(event, <?php echo $fl['id']; ?>, <?php echo $s; ?>)" class="absolute top-4 right-4 bg-black/80 text-red-500 border border-red-900/50 px-3 py-1 text-[8px] font-black uppercase hover:bg-red-600 hover:text-white transition z-10"><?php echo $txt['LIDER_INVENTARIO']['BTN_DESMANTELAR']; ?></button>
                        <div class="w-full text-center border-b border-yellow-900/20 pb-4 mb-8 text-[#c5a059] font-black text-[11px] uppercase tracking-widest"><?php echo $txt['LIDER_INVENTARIO']['LBL_FLOTA_ACTIVA']; ?> <?php echo $s; ?></div>
                        <div class="w-full space-y-6">
                            <div><span class="stat-label block mb-1"><?php echo $txt['LIDER_INVENTARIO']['LBL_UNIDAD_INSIGNIA']; ?></span><div class="bg-black p-3 border border-white/5 text-white font-bold uppercase text-sm"><?php echo htmlspecialchars($fl['insignia']); ?></div></div>
                            <div class="grid grid-cols-2 gap-3 text-gray-400 uppercase text-[10px]">
                                <?php for($esc=1;$esc<=4;$esc++): ?>
                                    <div><span class="stat-label block mb-1"><?php echo $txt['LIDER_INVENTARIO']['LBL_ESC']; ?> <?php echo $esc; ?></span><div class="bg-black p-2 border border-white/5"><?php echo htmlspecialchars($fl['escolta_'.$esc] ?: '-'); ?></div></div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <span class="mt-auto text-[9px] text-blue-500 font-black uppercase"><?php echo $txt['LIDER_INVENTARIO']['BTN_EDITAR_CONF']; ?></span>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    </main>

    <div id="modalDestroyFlota" class="hidden fixed inset-0 bg-black/98 z-[300] flex items-center justify-center p-4">
        <div class="m-panel w-full max-w-md border-red-600 bg-[#0a0a0a] p-10 text-center relative shadow-2xl">
            <button onclick="cerrarModal('modalDestroyFlota')" class="btn-close-modal">&times;</button>
            <div class="text-red-600 text-5xl mb-6">☢️</div>
            <h2 class="text-white font-black uppercase tracking-[0.2em] mb-4"><?php echo $txt['LIDER_INVENTARIO']['MODAL_DEL_FLOTA_TIT']; ?></h2>
            <p class="text-gray-400 text-xs font-bold leading-relaxed mb-10 uppercase"><?php echo $txt['LIDER_INVENTARIO']['MODAL_DEL_FLOTA_DESC']; ?><span id="txt_del_slot"></span>.</p>
            <form action="../logic/borrar_flota.php" method="POST"><input type="hidden" name="flota_id" id="del_flota_id"><button type="submit" class="bg-red-600 text-black w-full py-4 font-black uppercase text-[11px] hover:bg-red-500 transition tracking-widest"><?php echo $txt['LIDER_INVENTARIO']['BTN_CONFIRMAR_ORDEN']; ?></button></form>
        </div>
    </div>

    <div id="modalCancelarTradeo" class="hidden fixed inset-0 bg-black/98 z-[300] flex items-center justify-center p-4">
        <div class="m-panel w-full max-w-md border-red-600 bg-[#0a0a0a] p-10 text-center relative shadow-2xl">
            <button onclick="cerrarModal('modalCancelarTradeo')" class="btn-close-modal">&times;</button>
            <div class="text-red-600 text-5xl mb-6">⚠️</div>
            <h2 class="text-white font-black uppercase tracking-[0.2em] mb-4"><?php echo $txt['LIDER_INVENTARIO']['MODAL_CANC_TR_TIT']; ?></h2>
            <p class="text-gray-400 text-xs font-bold leading-relaxed mb-10 uppercase"><?php echo $txt['LIDER_INVENTARIO']['MODAL_CANC_TR_DESC']; ?></p>
            <form action="../logic/procesar_tradeo.php" method="POST"><input type="hidden" name="accion" value="cancelar"><input type="hidden" name="tradeo_id" id="del_tradeo_id_cancel"><button type="submit" class="bg-red-600 text-black w-full py-4 font-black uppercase text-[11px] hover:bg-red-500 transition tracking-widest"><?php echo $txt['LIDER_INVENTARIO']['BTN_CONFIRMAR_ANULACION']; ?></button></form>
        </div>
    </div>

    <div id="modalDecisionTradeo" class="hidden fixed inset-0 bg-black/98 z-[300] flex items-center justify-center p-4 backdrop-blur-md">
        <div class="m-panel w-full max-w-md border-blue-600 bg-[#0a0a0a] p-10 relative shadow-2xl">
            <button onclick="cerrarModal('modalDecisionTradeo')" class="btn-close-modal">&times;</button>
            <h2 class="text-blue-500 font-black text-center text-[10px] uppercase mb-8 tracking-[0.3em]"><?php echo $txt['LIDER_INVENTARIO']['MODAL_DECISION_TIT']; ?></h2>
            
            <div class="text-center mb-8">
                <span class="stat-label"><?php echo $txt['LIDER_INVENTARIO']['LBL_REMITENTE']; ?></span>
                <div id="dec_remitente" class="text-white font-black text-2xl uppercase font-['Cinzel']"></div>
            </div>
            
            <div class="bg-black/60 border border-white/5 p-6 mb-10 text-center">
                <div id="dec_contenido" class="space-y-3 font-mono font-black text-sm uppercase"></div>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <form id="formAceptarTradeo" action="../logic/procesar_tradeo.php" method="POST">
                    <input type="hidden" name="accion" value="aceptar">
                    <input type="hidden" id="dec_id_acep" name="tradeo_id">
                    <button type="button" onclick="abrirModalConfirmacion()" class="w-full bg-green-600 text-black py-5 font-black uppercase text-[11px] hover:bg-green-500 transition shadow-lg">
                        <?php echo $txt['LIDER_INVENTARIO']['BTN_ACEPTAR']; ?>
                    </button>
                </form>
                <form action="../logic/procesar_tradeo.php" method="POST">
                    <input type="hidden" name="accion" value="rechazar">
                    <input type="hidden" id="dec_id_rech" name="tradeo_id">
                    <button type="submit" class="w-full border border-red-600 text-red-500 py-5 font-black uppercase text-[11px] hover:bg-red-900 transition">
                        <?php echo $txt['LIDER_INVENTARIO']['BTN_RECHAZAR']; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div id="modalHistorial" class="hidden fixed inset-0 bg-black/95 z-[100] flex items-center justify-center p-4"><div class="m-panel w-full max-w-2xl border-red-800 bg-[#0d0e0a] p-10 relative shadow-2xl"><button onclick="cerrarModal('modalHistorial')" class="btn-close-modal">&times;</button><h2 class="text-red-500 font-black text-center text-[10px] uppercase mb-8 border-b border-red-900/50 pb-2 tracking-widest"><?php echo $txt['LIDER_INVENTARIO']['MODAL_PETICIONES_TIT']; ?></h2><div class="space-y-4 max-h-[50vh] overflow-y-auto pr-2"><?php if(empty($reembolsos_activos)): ?><p class="text-center text-gray-600 uppercase font-black text-xs py-10"><?php echo $txt['LIDER_INVENTARIO']['MSG_SIN_PETICIONES']; ?></p><?php else: foreach($reembolsos_activos as $ra): ?><div class="flex justify-between items-center bg-black/40 p-4 border border-white/5 group hover:border-red-900 transition"><div><span class="text-white font-black uppercase text-sm"><?php echo htmlspecialchars($ra['nombre_vehiculo']); ?></span><span class="text-red-500 font-black ml-4">x<?php echo $ra['cantidad']; ?></span></div><form action="../logic/cancelar_reembolso.php" method="POST"><input type="hidden" name="id" value="<?php echo $ra['id']; ?>"><button type="submit" class="bg-red-900/20 text-red-500 border border-red-900 px-5 py-2 text-[9px] font-black uppercase hover:bg-red-700 hover:text-white transition"><?php echo $txt['LIDER_INVENTARIO']['BTN_CANCELAR']; ?></button></form></div><?php endforeach; endif; ?></div></div></div>
    
    <div id="modalReembolso" class="hidden fixed inset-0 bg-black/95 z-[100] flex items-center justify-center p-4"><div class="m-panel w-full max-w-md border-red-800 bg-[#0d0e0a] p-10 shadow-2xl relative"><button onclick="cerrarModal('modalReembolso')" class="btn-close-modal">&times;</button><h2 class="text-red-500 font-black text-center text-[10px] uppercase mb-10 border-b border-red-900/50 pb-2 tracking-widest"><?php echo $txt['LIDER_INVENTARIO']['MODAL_REEMBOLSO_TIT']; ?></h2><form action="../logic/solicitar_reembolso.php" method="POST"><input type="hidden" id="re_inv_id" name="inventario_id"><div class="text-center mb-8"><span id="re_nombre" class="text-white font-black uppercase text-2xl"></span></div><div class="mb-4 text-center"><label class="stat-label block mb-2"><?php echo $txt['LIDER_INVENTARIO']['LBL_CANT_DISP']; ?> <span id="re_max_display" class="text-white"></span></label><input type="number" id="re_qty" name="cantidad" value="1" min="1" oninput="calcReMath()" class="f-input !text-4xl py-6 text-center font-black"></div><div id="re_stock_error" class="hidden text-red-500 text-[10px] font-black uppercase text-center mb-6 animate-pulse"><?php echo $txt['LIDER_INVENTARIO']['ERR_CANT_SUPERA']; ?></div><div class="bg-red-950/20 p-6 border border-red-900/30 text-center mb-10"><div class="flex justify-around font-black font-mono"><div><span class="stat-label !mb-1"><?php echo $txt['LIDER_INVENTARIO']['LBL_CASH']; ?></span><span id="re_res_d" class="text-green-500 text-xl">$0</span></div><div><span class="stat-label !mb-1"><?php echo $txt['LIDER_INVENTARIO']['LBL_STEEL']; ?></span><span id="re_res_a" class="text-white text-xl">0T</span></div><div><span class="stat-label !mb-1"><?php echo $txt['LIDER_INVENTARIO']['LBL_FUEL']; ?></span><span id="re_res_p" class="text-yellow-500 text-xl">0L</span></div></div></div><button type="submit" id="btnEnviarRe" class="btn-m w-full py-5 !bg-red-700 !text-white border-red-500 font-black uppercase"><?php echo $txt['LIDER_INVENTARIO']['BTN_ENVIAR_PETICION']; ?></button></form></div></div>
    
    <div id="modalAnularTradeo" class="hidden fixed inset-0 bg-black/95 z-[100] flex items-center justify-center p-4">
        <div class="m-panel w-full max-w-md border-yellow-800 bg-[#0d0e0a] p-10 shadow-2xl relative">
            <button onclick="cerrarModal('modalAnularTradeo')" class="btn-close-modal">&times;</button>
            <h2 class="text-yellow-500 font-black text-center text-[10px] uppercase mb-6 border-b border-yellow-900/50 pb-2 tracking-widest">SOLICITAR REVERSIÓN DE TRADEO</h2>
            
            <div class="bg-yellow-950/20 border border-yellow-900/50 p-4 mb-6 text-center text-[9px] text-yellow-600 font-bold uppercase">
                ⚠️ Al devolver un vehículo obtenido por Tradeo, NO recibirás dinero. El Staff revertirá el trato original y devolverá los fondos a sus dueños.
            </div>

            <form action="../logic/solicitar_reembolso.php" method="POST">
                <input type="hidden" id="anular_inv_id" name="inventario_id">
                <input type="hidden" name="es_tradeo" value="1">
                
                <div class="text-center mb-8"><span id="anular_nombre" class="text-white font-black uppercase text-2xl font-['Cinzel']"></span></div>
                
                <div class="mb-4 text-center">
                    <label class="stat-label block mb-2">UNIDADES A DEVOLVER <span id="anular_max_display" class="text-white"></span></label>
                    <input type="number" id="anular_qty" name="cantidad" value="1" min="1" class="f-input !text-4xl py-6 text-center font-black !text-yellow-500 !border-yellow-900">
                </div>
                
                <button type="submit" class="btn-m w-full py-5 mt-6 !bg-yellow-600 !text-black border-yellow-500 uppercase font-black tracking-widest hover:bg-yellow-500 transition">ENVIAR PETICIÓN AL STAFF</button>
            </form>
        </div>
    </div>

    <div id="modalError" class="hidden fixed inset-0 bg-black/98 z-[300] flex items-center justify-center p-4"><div class="m-panel w-full max-w-sm border-red-600 bg-[#120505] p-10 text-center shadow-2xl relative"><button onclick="cerrarModal('modalError')" class="btn-close-modal">&times;</button><div class="text-red-500 text-5xl mb-6">⚠️</div><h3 class="text-white font-black uppercase tracking-widest mb-4"><?php echo $txt['LIDER_INVENTARIO']['MODAL_ERROR_TIT']; ?></h3><p id="error_msg_text" class="text-gray-400 text-xs uppercase font-bold mb-8 leading-relaxed"></p><button onclick="cerrarModal('modalError')" class="btn-m w-full !bg-red-900/20 !border-red-600 !text-red-500 py-3 font-black uppercase"><?php echo $txt['LIDER_INVENTARIO']['BTN_ENTENDIDO']; ?></button></div></div>

    <div id="modalConfirmarAceptacion" class="hidden fixed inset-0 bg-black/98 z-[400] flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="m-panel w-full max-w-sm border-green-600 bg-[#0a0a0a] p-8 text-center relative shadow-2xl">
            <div class="text-green-500 text-5xl mb-4">🤝</div>
            <h2 class="text-white font-black uppercase tracking-[0.2em] mb-4 text-sm"><?php echo $txt['LIDER_INVENTARIO']['MODAL_CONFIRMAR_TIT']; ?></h2>
            <p class="text-gray-400 text-xs font-bold leading-relaxed mb-8 uppercase"><?php echo $txt['LIDER_INVENTARIO']['MODAL_CONFIRMAR_DESC']; ?></p>
            
            <div class="flex gap-4">
                <button type="button" onclick="enviarFormularioAceptar()" class="flex-1 bg-green-600 text-black py-3 font-black uppercase text-[10px] hover:bg-green-500 transition tracking-widest"><?php echo $txt['LIDER_INVENTARIO']['BTN_SI_FIRMAR']; ?></button>
                <button type="button" onclick="cerrarModal('modalConfirmarAceptacion')" class="flex-1 border border-gray-600 text-gray-500 py-3 font-black uppercase text-[10px] hover:bg-gray-800 hover:text-white transition tracking-widest"><?php echo $txt['LIDER_INVENTARIO']['BTN_CANCELAR']; ?></button>
            </div>
        </div>
    </div>

    <div id="modalMercado" class="hidden fixed inset-0 bg-black/98 z-[150] flex items-center justify-center p-4">
        <div class="m-panel w-full max-w-3xl h-auto max-h-[90vh] relative border-blue-900 overflow-hidden flex flex-col shadow-2xl">
            <button onclick="cerrarModal('modalMercado')" class="btn-close-modal">&times;</button>
            <div class="flex border-b border-blue-900/30 bg-black/40">
                <button onclick="subTabMercado('crear')" id="sm_crear" class="flex-1 py-4 text-[9px] font-black uppercase tracking-widest border-b-2 border-blue-500 bg-blue-500/10 text-white"><?php echo $txt['LIDER_INVENTARIO']['TAB_NUEVO_ENLACE']; ?></button>
                <button onclick="subTabMercado('ordenes')" id="sm_ordenes" class="flex-1 py-4 text-[9px] font-black uppercase tracking-widest border-b-2 border-transparent text-gray-500"><?php echo $txt['LIDER_INVENTARIO']['TAB_MIS_ORDENES']; ?> (<?php echo count($mis_ordenes); ?>)</button>
                <button onclick="subTabMercado('recibidas')" id="sm_recibidas" class="flex-1 py-4 text-[9px] font-black uppercase tracking-widest border-b-2 border-transparent text-yellow-500"><?php echo $txt['LIDER_INVENTARIO']['TAB_ENTRANTES']; ?> (<?php echo count($ofertas_recibidas); ?>)</button>
            </div>
            <div id="sec_m_crear" class="flex-grow overflow-y-auto p-8 bg-[#05070a] relative custom-scrollbar">
                <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')] opacity-10 pointer-events-none"></div>
                <form action="../logic/procesar_tradeo.php" method="POST" onsubmit="return validarTradeo(event)" class="relative z-10 max-w-2xl mx-auto">
                    <input type="hidden" name="accion" value="crear"><input type="hidden" name="receptor_id" id="t_receptor_id"><input type="hidden" name="vehiculo_requerido_id" value="0"><input type="hidden" name="cantidad_requerida" value="0">
                    <div class="mb-8 p-6 border border-blue-900/30 bg-black/60 text-center">
                        <h3 class="text-blue-500 font-black text-[10px] uppercase mb-4 tracking-widest"><?php echo $txt['LIDER_INVENTARIO']['PASO_1_DEST']; ?></h3>
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
                        <h3 class="text-blue-500 font-black text-[10px] uppercase mb-2 tracking-widest text-center border-b border-blue-900/30 pb-2"><?php echo $txt['LIDER_INVENTARIO']['PASO_2_OFERTA']; ?></h3>
                        
                        <div id="sec_recursos" class="grid grid-cols-3 gap-4">
                            <div class="text-center"><label class="stat-label block mb-2"><?php echo $txt['LIDER_INVENTARIO']['LBL_CASH']; ?></label><input type="number" name="ofrece_dinero" id="off_d" value="0" min="0" oninput="projBal()" class="f-input !p-3 !text-lg text-green-500 text-center font-black"><div class="mt-2"><span id="bal_d" class="balance-pill text-green-500"><?php echo $txt['LIDER_INVENTARIO']['LBL_SALDO']; ?> $<?php echo number_format($user['dinero']); ?></span></div></div>
                            <div class="text-center"><label class="stat-label block mb-2"><?php echo $txt['LIDER_INVENTARIO']['LBL_STEEL']; ?></label><input type="number" name="ofrece_acero" id="off_a" value="0" min="0" oninput="projBal()" class="f-input !p-3 !text-lg text-white text-center font-black"><div class="mt-2"><span id="bal_a" class="balance-pill text-white"><?php echo $txt['LIDER_INVENTARIO']['LBL_SALDO']; ?> <?php echo $user['acero']; ?>T</span></div></div>
                            <div class="text-center"><label class="stat-label block mb-2"><?php echo $txt['LIDER_INVENTARIO']['LBL_FUEL']; ?></label><input type="number" name="ofrece_petroleo" id="off_p" value="0" min="0" oninput="projBal()" class="f-input !p-3 !text-lg text-yellow-500 text-center font-black"><div class="mt-2"><span id="bal_p" class="balance-pill text-yellow-500"><?php echo $txt['LIDER_INVENTARIO']['LBL_SALDO']; ?> <?php echo $user['petroleo']; ?>L</span></div></div>
                        </div>

                        <div id="sec_vehiculo" class="bg-black/40 p-5 border border-white/5 mt-4">
                            <label class="stat-label block mb-3 text-blue-400"><?php echo $txt['LIDER_INVENTARIO']['LBL_TRANSFERIR_VEH']; ?></label>
                            <div class="flex gap-4 items-center">
                                <select name="inv_ofrecido_id" id="select_mio" onchange="actualizarOfertaMio()" class="f-input !text-[11px] flex-grow !p-3 font-black uppercase">
                                    <option value="" data-d="0" data-a="0" data-p="0" data-stock="0"><?php echo $txt['LIDER_INVENTARIO']['OPT_NINGUNO']; ?></option>
                                    <?php 
                                    // Iteramos sobre mi stock real para que elijan si dan el de Tienda o el de Tradeo
                                    foreach($mi_stock as $cat_id => $origenes):
                                        foreach($origenes as $origen => $data):
                                            if($data['cantidad'] > 0):
                                                $veh_info = $mi_catalogo_js[$cat_id];
                                                $label_origen = $origen === 'tienda' ? 'TIENDA' : 'TRADEO';
                                    ?>
                                        <option value="<?php echo $data['inv_id']; ?>" 
                                                data-d="<?php echo $veh_info['dinero']; ?>" 
                                                data-a="<?php echo $veh_info['acero']; ?>" 
                                                data-p="<?php echo $veh_info['petroleo']; ?>"
                                                data-stock="<?php echo $data['cantidad']; ?>">
                                            <?php echo htmlspecialchars($veh_info['nombre']); ?> (<?php echo $data['cantidad']; ?>x - Origen: <?php echo $label_origen; ?>)
                                        </option>
                                    <?php 
                                            endif;
                                        endforeach;
                                    endforeach; 
                                    ?>
                                </select>
                            </div>
                            <div id="mio_extra" class="hidden mt-4 bg-[#0a0a0a] border border-gray-800 p-4">
                                <div class="flex justify-between items-center mb-4 border-b border-gray-800 pb-3">
                                    <span class="stat-label !text-blue-500"><?php echo $txt['LIDER_INVENTARIO']['LBL_VALOR_ACTIVO']; ?></span>
                                    <div id="mio_valor" class="flex gap-4 text-[11px] font-black tracking-widest font-mono text-right"></div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="stat-label"><?php echo $txt['LIDER_INVENTARIO']['LBL_CANT_TRANSFERIR']; ?></span>
                                    <input type="number" name="cantidad_ofrecida" id="ofre_qty" value="1" min="1" oninput="multiValMio()" class="w-24 f-input !text-blue-400 text-center !p-2 font-black text-xl">
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 p-5 border border-blue-900/30 bg-[#050505]">
                            <h3 class="text-blue-500 font-black text-[10px] uppercase mb-4 tracking-widest text-center border-b border-blue-900/30 pb-2"><?php echo $txt['LIDER_INVENTARIO']['PASO_3_PIDES']; ?></h3>
                            <p class="text-gray-500 text-[9px] uppercase text-center mb-4"><?php echo $txt['LIDER_INVENTARIO']['PASO_3_DESC']; ?></p>
                            <div class="grid grid-cols-3 gap-4">
                                <div class="text-center"><label class="stat-label block mb-2"><?php echo $txt['LIDER_INVENTARIO']['LBL_DINERO_SOLO']; ?></label><input type="number" name="requiere_dinero" id="req_d" value="0" min="0" class="f-input !p-3 !text-lg text-red-500 text-center font-black"></div>
                                <div class="text-center"><label class="stat-label block mb-2"><?php echo $txt['LIDER_INVENTARIO']['LBL_ACERO_SOLO']; ?></label><input type="number" name="requiere_acero" id="req_a" value="0" min="0" class="f-input !p-3 !text-lg text-red-400 text-center font-black"></div>
                                <div class="text-center"><label class="stat-label block mb-2"><?php echo $txt['LIDER_INVENTARIO']['LBL_PETROLEO_SOLO']; ?></label><input type="number" name="requiere_petroleo" id="req_p" value="0" min="0" class="f-input !p-3 !text-lg text-orange-500 text-center font-black"></div>
                            </div>
                        </div>
                    </div>
                    <button type="submit" id="btn_enviar_trato" class="btn-m w-full py-5 !bg-gray-800 !text-gray-500 border-gray-600 text-[10px] font-black uppercase mt-10 tracking-widest cursor-not-allowed" disabled><?php echo $txt['LIDER_INVENTARIO']['BTN_SELECCIONE_DEST']; ?></button>
                </form>
            </div>
            <div id="sec_m_ordenes" class="hidden flex-grow overflow-y-auto p-10 bg-[#05070a]">
                <?php if(empty($mis_ordenes)): ?>
                    <p class="text-center text-gray-600 uppercase font-black text-xs py-10"><?php echo $txt['LIDER_INVENTARIO']['MSG_SIN_OFERTAS']; ?></p>
                <?php else: foreach($mis_ordenes as $o): ?>
                    <div class="m-panel bg-black/60 border-l-4 border-l-blue-600 p-6 flex justify-between items-center mb-4">
                        <div class="flex-grow pr-6">
                            <span class="stat-label block mb-2 uppercase font-bold text-blue-500"><?php echo $txt['LIDER_INVENTARIO']['LBL_ENVIADO_A']; ?> <?php echo htmlspecialchars($o['receptor']); ?></span>
                            
                            <div class="mt-2 pt-2 border-t border-blue-900/30">
                                <span class="text-[8px] text-gray-500 uppercase tracking-widest block mb-1"><?php echo $txt['LIDER_INVENTARIO']['PASO_2_OFERTA']; ?>:</span>
                                <div class="flex flex-wrap gap-3 text-[10px] font-black font-mono">
                                    <?php if($o['ofrece_dinero'] > 0): ?><span class="text-green-500">+$<?php echo number_format($o['ofrece_dinero']); ?></span><?php endif; ?>
                                    <?php if($o['ofrece_acero'] > 0): ?><span class="text-white">+<?php echo number_format($o['ofrece_acero']); ?>T</span><?php endif; ?>
                                    <?php if($o['ofrece_petroleo'] > 0): ?><span class="text-yellow-500">+<?php echo number_format($o['ofrece_petroleo']); ?>L</span><?php endif; ?>
                                    <?php if(!empty($o['v_ofrecido'])): ?><span class="text-blue-400 bg-blue-900/20 px-2 py-0.5 rounded border border-blue-900/50">+<?php echo htmlspecialchars($o['v_ofrecido']); ?> (x<?php echo $o['cantidad_ofrecida']; ?>)</span><?php endif; ?>
                                </div>
                            </div>

                            <div class="mt-2 pt-2 border-t border-gray-800">
                                <span class="text-[8px] text-gray-500 uppercase tracking-widest block mb-1"><?php echo $txt['LIDER_INVENTARIO']['LBL_GANARAS']; ?></span>
                                <div class="flex flex-wrap gap-3 text-[10px] font-black font-mono">
                                    <?php 
                                    $pide_algo = false;
                                    if($o['pide_dinero'] > 0): $pide_algo = true; ?><span class="text-green-500">+$<?php echo number_format($o['pide_dinero']); ?></span><?php endif; ?>
                                    <?php if($o['pide_acero'] > 0): $pide_algo = true; ?><span class="text-white">+<?php echo number_format($o['pide_acero']); ?>T</span><?php endif; ?>
                                    <?php if($o['pide_petroleo'] > 0): $pide_algo = true; ?><span class="text-yellow-500">+<?php echo number_format($o['pide_petroleo']); ?>L</span><?php endif; ?>
                                    <?php if(!$pide_algo): ?><span class="text-green-600 bg-green-900/20 px-2 py-0.5 rounded border border-green-500/30"><?php echo $txt['LIDER_INVENTARIO']['LBL_REGALO']; ?></span><?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <button type="button" onclick="confirmarCancelarTradeo(<?php echo $o['id']; ?>)" class="bg-red-900/20 text-red-500 border border-red-900 px-6 py-2 text-[10px] font-black uppercase hover:bg-red-700 hover:text-white transition">
                            <?php echo $txt['LIDER_INVENTARIO']['BTN_CANCELAR']; ?>
                        </button>
                    </div>
                <?php endforeach; endif; ?>
            </div>
            <div id="sec_m_recibidas" class="hidden flex-grow overflow-y-auto p-10 bg-[#05070a] custom-scrollbar">
                <?php if(empty($ofertas_recibidas)): ?><p class="text-center text-gray-600 uppercase font-black text-xs py-10"><?php echo $txt['LIDER_INVENTARIO']['MSG_SIN_PROPUESTAS']; ?></p>
                <?php else: foreach($ofertas_recibidas as $or): $or_json = htmlspecialchars(json_encode($or), ENT_QUOTES, 'UTF-8'); ?>
                    <div class="m-panel bg-blue-900/10 border-l-4 border-l-blue-600 p-6 flex justify-between items-center mb-4">
                        <div><span class="stat-label text-yellow-600"><?php echo $txt['LIDER_INVENTARIO']['LBL_REMITENTE']; ?> <?php echo htmlspecialchars($or['remitente']); ?></span><h4 class="text-white font-black uppercase text-sm mt-1"><?php echo $txt['LIDER_INVENTARIO']['LBL_TRANSMISION_ABIERTA']; ?></h4></div>
                        <button onclick='abrirDecisionTradeo(<?php echo $or_json; ?>)' class="btn-m !bg-blue-600 !text-white px-6 py-2 text-[10px] font-black uppercase"><?php echo $txt['LIDER_INVENTARIO']['BTN_REVISAR_PROP']; ?></button>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <div id="modalFlota" class="hidden fixed inset-0 bg-black/98 z-[300] flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="m-panel w-full max-w-md border-blue-600 bg-[#0a0a0a] p-10 relative shadow-2xl">
            <button type="button" onclick="cerrarModal('modalFlota')" class="btn-close-modal">&times;</button>
            <h2 class="text-blue-500 font-black text-center text-[10px] uppercase mb-8 tracking-[0.3em] border-b border-blue-900/50 pb-2"><?php echo $txt['LIDER_INVENTARIO']['GESTION_FLOTA']; ?><span id="slot_num"></span></h2>
            
            <form action="../logic/gestionar_flota.php" method="POST">
                <input type="hidden" name="slot" id="slot_input">
                
                <div class="space-y-4">
                    <div>
                        <label class="stat-label block mb-2"><?php echo $txt['LIDER_INVENTARIO']['LBL_UNIDAD_INSIGNIA']; ?></label>
                        <input type="text" name="insignia" id="in_ins" class="f-input border-blue-900/50 focus:border-blue-500" required placeholder="Ej: Acorazado Bismarck">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="stat-label block mb-2"><?php echo $txt['LIDER_INVENTARIO']['LBL_ESC']; ?> 1</label>
                            <input type="text" name="escolta_1" id="in_e1" class="f-input" placeholder="Opcional">
                        </div>
                        <div>
                            <label class="stat-label block mb-2"><?php echo $txt['LIDER_INVENTARIO']['LBL_ESC']; ?> 2</label>
                            <input type="text" name="escolta_2" id="in_e2" class="f-input" placeholder="Opcional">
                        </div>
                        <div>
                            <label class="stat-label block mb-2"><?php echo $txt['LIDER_INVENTARIO']['LBL_ESC']; ?> 3</label>
                            <input type="text" name="escolta_3" id="in_e3" class="f-input" placeholder="Opcional">
                        </div>
                        <div>
                            <label class="stat-label block mb-2"><?php echo $txt['LIDER_INVENTARIO']['LBL_ESC']; ?> 4</label>
                            <input type="text" name="escolta_4" id="in_e4" class="f-input" placeholder="Opcional">
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="w-full bg-blue-600 text-white py-5 font-black uppercase text-[11px] hover:bg-blue-500 transition shadow-lg mt-8 tracking-widest">
                    <?php echo $txt['LIDER_INVENTARIO']['GUARDAR_ORDEN_FLOTA']; ?>
                </button>
            </form>
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
        function setSeccion(s) { 
            if(s === 'flotas') { 
                document.getElementById('cont_hangar').style.display = 'none'; 
                document.getElementById('cont_flotas').classList.remove('hidden'); 
                
                document.querySelectorAll('.tab-nacion').forEach(b => {
                    b.style.opacity = '0.2';
                    b.style.pointerEvents = 'none';
                });
                
            } else { 
                tipoActual = s; 
                document.getElementById('cont_hangar').style.display = 'block'; 
                document.getElementById('cont_flotas').classList.add('hidden'); 
                
                document.querySelectorAll('.tab-nacion').forEach(b => {
                    b.style.opacity = '1';
                    b.style.pointerEvents = 'auto';
                });
                
                aplicarFiltros(); 
            } 
            document.querySelectorAll('.nav-btn').forEach(b => b.classList.toggle('active', b.id === 'nav_'+s)); 
        }

        function aplicarFiltros() {
            let total = 0;
            document.querySelectorAll('.tier-container').forEach(tier => {
                let tierVisible = false;
                tier.querySelectorAll('.seccion-tipo').forEach(sec => {
                    // Verificamos si estamos en la pestaña Tanques, Aviones o Flotas
                    if (sec.dataset.tipo === (typeof tipoActual !== 'undefined' ? tipoActual : 'tanque')) {
                        sec.style.display = 'block';
                        
                        sec.querySelectorAll('.clase-container').forEach(clase => {
                            let hayElementos = false;
                            
                            // 🔥 LA CORRECCIÓN: Filtrado estricto por Nación
                            clase.querySelectorAll('.fila-v').forEach(item => {
                                if (item.dataset.nacion === nacActual) {
                                    item.style.display = 'flex'; // Mostramos el de la nación seleccionada
                                    hayElementos = true;
                                    tierVisible = true;
                                } else {
                                    item.style.display = 'none'; // Ocultamos los de las demás naciones
                                }
                            });
                            
                            // Si no quedó ningún vehículo visible en esta clase, ocultamos el título de la clase
                            clase.style.display = hayElementos ? 'block' : 'none';
                        });
                    } else {
                        sec.style.display = 'none'; // Ocultamos si no es el tipo de vehículo actual
                    }
                });
                
                tier.style.display = tierVisible ? 'block' : 'none';
                if(tierVisible) total++;
            });
        }

        function projBal() {
            const offD = parseInt(document.getElementById('off_d').value) || 0, offA = parseInt(document.getElementById('off_a').value) || 0, offP = parseInt(document.getElementById('off_p').value) || 0;
            const update = (id, cur, off, sym) => { const el = document.getElementById(id), rem = cur - off; el.innerText = `<?php echo $txt['LIDER_INVENTARIO']['LBL_SALDO']; ?> ${rem.toLocaleString()}${sym}`; el.style.color = rem < 0 ? '#ff0000' : ''; };
            update('bal_d', resActual.d, offD, '$'); update('bal_a', resActual.a, offA, 'T'); update('bal_p', resActual.p, offP, 'L');
        }

        function abrirModalReembolso(i) { itemReSel = i; document.getElementById('re_nombre').innerText = i.nombre_vehiculo; document.getElementById('re_max_display').innerText = i.neto + " <?php echo $txt['LIDER_INVENTARIO']['LBL_UNIDADES_LIBRES']; ?>"; document.getElementById('re_inv_id').value = i.inv_id; document.getElementById('re_qty').max = i.neto; document.getElementById('re_qty').value = 1; calcReMath(); abrirModal('modalReembolso'); }
        function abrirModalAnularTradeo(i) { document.getElementById('anular_nombre').innerText = i.nombre_vehiculo; document.getElementById('anular_max_display').innerText = "(Max: " + i.neto + ")"; document.getElementById('anular_inv_id').value = i.inv_id; document.getElementById('anular_qty').max = i.neto; document.getElementById('anular_qty').value = 1; abrirModal('modalAnularTradeo'); }

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
            
            let hRecibe = "";
            if(parseInt(data.ofrece_dinero) > 0) hRecibe += `<div class='text-green-500'>+ $${parseInt(data.ofrece_dinero).toLocaleString()}</div>`;
            if(parseInt(data.ofrece_acero) > 0) hRecibe += `<div class='text-white'>+ ${data.ofrece_acero}T <?php echo $txt['LIDER_INVENTARIO']['LBL_ACERO_TXT']; ?></div>`;
            if(parseInt(data.ofrece_petroleo) > 0) hRecibe += `<div class='text-yellow-500'>+ ${data.ofrece_petroleo}L <?php echo $txt['LIDER_INVENTARIO']['LBL_PETROLEO_TXT']; ?></div>`;
            if(data.v_ofrecido_nombre) hRecibe += `<div class='text-blue-400 mt-2 border-t border-white/5 pt-2'>+ ${data.cantidad_ofrecida}x ${data.v_ofrecido_nombre}</div>`;
            
            if(hRecibe === "") hRecibe = "<div class='text-gray-600 text-[10px]'><?php echo $txt['LIDER_INVENTARIO']['LBL_NINGUNO']; ?></div>";

            let hDa = "";
            if(parseInt(data.pide_dinero) > 0) hDa += `<div class='text-red-500'>- $${parseInt(data.pide_dinero).toLocaleString()}</div>`;
            if(parseInt(data.pide_acero) > 0) hDa += `<div class='text-red-400'>- ${data.pide_acero}T <?php echo $txt['LIDER_INVENTARIO']['LBL_ACERO_TXT']; ?></div>`;
            if(parseInt(data.pide_petroleo) > 0) hDa += `<div class='text-orange-500'>- ${data.pide_petroleo}L <?php echo $txt['LIDER_INVENTARIO']['LBL_PETROLEO_TXT']; ?></div>`;
            if(data.v_requerido_nombre) hDa += `<div class='text-blue-400 mt-2 border-t border-white/5 pt-2'>- ${data.cantidad_requerida}x ${data.v_requerido_nombre}</div>`;
            
            if(hDa === "") hDa = "<div class='text-green-400 text-xs font-black uppercase bg-green-900/20 inline-block px-2 py-1 border border-green-500/50 mt-1'><?php echo $txt['LIDER_INVENTARIO']['LBL_ES_REGALO']; ?></div>";

            document.getElementById('dec_contenido').innerHTML = `
                <div class="mb-4 text-left">
                    <span class="text-blue-500 text-[9px] uppercase tracking-widest block mb-2"><?php echo $txt['LIDER_INVENTARIO']['LBL_RECIBIRAS']; ?></span>
                    ${hRecibe}
                </div>
                <div class="border-t border-gray-800 pt-4 text-left">
                    <span class="text-red-500 text-[9px] uppercase tracking-widest block mb-2"><?php echo $txt['LIDER_INVENTARIO']['LBL_PAGARAS']; ?></span>
                    ${hDa}
                </div>
            `;
            
            abrirModal('modalDecisionTradeo');
        }

        function abrirEditorFlota(s, d) { document.getElementById('slot_num').innerText = s; document.getElementById('slot_input').value = s; document.getElementById('in_ins').value = d ? d.insignia : ''; document.getElementById('in_e1').value = d ? d.escolta_1 : ''; document.getElementById('in_e2').value = d ? d.escolta_2 : ''; document.getElementById('in_e3').value = d ? d.escolta_3 : ''; document.getElementById('in_e4').value = d ? d.escolta_4 : ''; abrirModal('modalFlota'); }
        function abrirModal(id) { document.getElementById(id).classList.remove('hidden'); document.body.classList.add('modal-active'); }
        function cerrarModal(id) { document.getElementById(id).classList.add('hidden'); document.body.classList.remove('modal-active'); }
        function confirmarCancelarTradeo(id) { document.getElementById('del_tradeo_id_cancel').value = id; abrirModal('modalCancelarTradeo'); }
        function desmantelarFlotaLider(e, id, slot) { e.stopPropagation(); document.getElementById('del_flota_id').value = id; document.getElementById('txt_del_slot').innerText = slot; abrirModal('modalDestroyFlota'); }

        function seleccionarRival(r) { rSel = r; document.querySelectorAll('.btn-rival-selector').forEach(b => { b.classList.remove('active', 'border-blue-500', 'bg-blue-900/20'); b.classList.add('border-gray-800', 'bg-[#0a0a0a]'); }); const btn = document.getElementById('btn-rival-'+r.id); btn.classList.remove('border-gray-800', 'bg-[#0a0a0a]'); btn.classList.add('active', 'border-blue-500', 'bg-blue-900/20'); document.getElementById('t_receptor_id').value = r.id; document.getElementById('rival_seleccionado_box').classList.remove('hidden'); document.getElementById('rival_seleccionado_txt').innerText = r.nombre_equipo; const btnEnviar = document.getElementById('btn_enviar_trato'); btnEnviar.disabled = false; btnEnviar.classList.remove('!bg-gray-800', '!text-gray-500', 'border-gray-600', 'cursor-not-allowed'); btnEnviar.classList.add('!bg-blue-600', '!text-white', '!border-blue-400'); btnEnviar.innerText = "<?php echo $txt['LIDER_INVENTARIO']['BTN_ENVIAR_PROPUESTA']; ?>"; }
        
        function actualizarOfertaMio() { 
            const sel = document.getElementById('select_mio');
            const opt = sel.options[sel.selectedIndex];
            
            if(sel.value !== "") { 
                document.getElementById('mio_extra').classList.remove('hidden'); 
                document.getElementById('ofre_qty').max = parseInt(opt.dataset.stock); 
                document.getElementById('ofre_qty').value = 1; 
                multiValMio(); 
            } else {
                document.getElementById('mio_extra').classList.add('hidden'); 
            }
        }
        
        function multiValMio() { 
            const sel = document.getElementById('select_mio');
            const opt = sel.options[sel.selectedIndex];
            const q = parseInt(document.getElementById('ofre_qty').value) || 0; 
            const max = parseInt(opt.dataset.stock) || 0;
            
            if(q > max) {
                document.getElementById('ofre_qty').value = max;
            }

            if(sel.value !== "") { 
                const d = { dinero: parseInt(opt.dataset.d), acero: parseInt(opt.dataset.a), petroleo: parseInt(opt.dataset.p) };
                const unitario = `<span class='text-gray-500 block text-[8px] mb-1'><?php echo $txt['LIDER_INVENTARIO']['LBL_UNITARIO']; ?> $${d.dinero.toLocaleString()} | ${d.acero.toLocaleString()}T | ${d.petroleo.toLocaleString()}L</span>`;
                const total = `<div><span class='text-green-500'>$${(q * d.dinero).toLocaleString()}</span> <span class='text-white ml-2'>${(q * d.acero).toLocaleString()}T</span> <span class='text-yellow-500 ml-2'>${(q * d.petroleo).toLocaleString()}L</span></div>`;
                
                document.getElementById('mio_valor').innerHTML = `<div class='flex flex-col items-end'>${unitario}${total}</div>`; 
            } 
        }
        
        function mostrarError(txt) { document.getElementById('error_msg_text').innerText = txt; abrirModal('modalError'); }
        function validarTradeo(e) { 
            if(!rSel) { 
                e.preventDefault(); mostrarError("<?php echo $txt['LIDER_INVENTARIO']['ERR_JS_SIN_FAC']; ?>"); return false; 
            } 
            
            const offD = parseInt(document.getElementById('off_d').value) || 0;
            const offA = parseInt(document.getElementById('off_a').value) || 0;
            const offP = parseInt(document.getElementById('off_p').value) || 0;
            const vehId = document.getElementById('select_mio').value;

            if(offD > resActual.d || offA > resActual.a || offP > resActual.p) { 
                e.preventDefault(); mostrarError("<?php echo $txt['LIDER_INVENTARIO']['ERR_JS_FONDOS']; ?>"); return false; 
            } 
            
            if(offD === 0 && offA === 0 && offP === 0 && vehId === "") { 
                e.preventDefault(); mostrarError("<?php echo $txt['LIDER_INVENTARIO']['ERR_JS_VACIA']; ?>"); return false; 
            } 
            
            return true; 
        }
        let tipoActual = 'tanque';
        setNacion(nacActual); setSeccion(tipoActual);

        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const msg = urlParams.get('msg');
            
            if (msg) {
                let mensajeTexto = "";
                if (msg === 'oferta_enviada') {
                    mensajeTexto = "<?php echo $txt['LIDER_INVENTARIO']['MSG_OFERTA_ENVIADA']; ?>";
                } else if (msg === 'oferta_aceptada') {
                    mensajeTexto = "<?php echo $txt['LIDER_INVENTARIO']['MSG_OFERTA_ACEPTADA']; ?>";
                } else if (msg === 'oferta_rechazada') {
                    mensajeTexto = "<?php echo $txt['LIDER_INVENTARIO']['MSG_OFERTA_RECHAZADA']; ?>";
                } else if (msg === 'oferta_cancelada') {
                    mensajeTexto = "<?php echo $txt['LIDER_INVENTARIO']['MSG_OFERTA_CANCELADA']; ?>";
                }
                
                if (mensajeTexto !== "") {
                    document.querySelector('#modalError h3').innerText = "<?php echo $txt['LIDER_INVENTARIO']['MODAL_INFORME_TIT']; ?>";
                    document.querySelector('#modalError .text-red-500').innerText = "📝"; 
                    mostrarError(mensajeTexto);
                }
                
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });

        function abrirModalConfirmacion() {
            abrirModal('modalConfirmarAceptacion');
        }

        function enviarFormularioAceptar() {
            const btnSubmit = document.querySelector('#modalConfirmarAceptacion button[onclick="enviarFormularioAceptar()"]');
            btnSubmit.innerText = '<?php echo $txt['LIDER_INVENTARIO']['BTN_PROCESANDO']; ?>';
            btnSubmit.disabled = true;
            document.getElementById('formAceptarTradeo').submit();
        }
    </script>
</body>
</html>