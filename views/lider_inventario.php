<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'lider') { header("Location: ../login.php"); exit(); }
require_once '../config/conexion.php';
$root_path = "../";
$txt = require '../config/textos.php';
$lider_id = $_SESSION['usuario_id'];

try {
    // 1. DATOS DE LA CUENTA
    $stmt_mio = $pdo->prepare("SELECT * FROM cuentas WHERE id = :id");
    $stmt_mio->execute([':id' => $lider_id]);
    $user = $stmt_mio->fetch(PDO::FETCH_ASSOC);
    $mis_naciones = !empty($user['naciones_activas']) ? array_map('trim', explode(',', $user['naciones_activas'])) : [];

    // 2. ÓRDENES DE MERCADO Y PETICIONES DE REEMBOLSO PENDIENTES
    $stmt_tr_check = $pdo->prepare("SELECT COUNT(*) FROM mercado_tradeos WHERE (ofertante_id = :id OR receptor_id = :id) AND estado = 'activo'");
    $stmt_tr_check->execute([':id' => $lider_id]);
    $tiene_contratos = $stmt_tr_check->fetchColumn() > 0;

    $stmt_mis_tr = $pdo->prepare("SELECT m.*, c.nombre_vehiculo as v_ofrecido, req.nombre_vehiculo as v_requerido, u.nombre_equipo as receptor FROM mercado_tradeos m LEFT JOIN catalogo_tienda c ON m.vehiculo_ofrecido_id = c.id LEFT JOIN catalogo_tienda req ON m.vehiculo_requerido_id = req.id JOIN cuentas u ON m.receptor_id = u.id WHERE m.ofertante_id = :id AND m.estado = 'activo'");
    $stmt_mis_tr->execute([':id' => $lider_id]);
    $mis_ordenes = $stmt_mis_tr->fetchAll(PDO::FETCH_ASSOC);

    // CONSULTA DE REEMBOLSOS PARA LA RESTA VISUAL
    $stmt_pend = $pdo->prepare("SELECT s.*, c.nombre_vehiculo FROM solicitudes_reembolso s JOIN inventario i ON s.inventario_id = i.id JOIN catalogo_tienda c ON i.catalogo_id = c.id WHERE s.cuenta_id = :id AND s.estado = 'pendiente'");
    $stmt_pend->execute([':id' => $lider_id]);
    $reembolsos_activos = $stmt_pend->fetchAll(PDO::FETCH_ASSOC);

    // Mapeo de totales en proceso por ID de inventario
    $en_proceso = [];
    foreach($reembolsos_activos as $ra) { $en_proceso[$ra['inventario_id']] = ($en_proceso[$ra['inventario_id']] ?? 0) + $ra['cantidad']; }

    // 3. FLOTAS (3 SLOTS)
    $stmt_f = $pdo->prepare("SELECT * FROM flotas WHERE cuenta_id = :id ORDER BY slot ASC");
    $stmt_f->execute([':id' => $lider_id]);
    $flotas_db = $stmt_f->fetchAll(PDO::FETCH_ASSOC);
    $mis_flotas = [1 => null, 2 => null, 3 => null];
    foreach($flotas_db as $f) { $mis_flotas[$f['slot']] = $f; }

    // 4. HANGAR Y CATALOGO
    $catalogo_nacional = []; $mi_stock = []; $mi_catalogo_js = []; $mis_planos = [];
    if (!empty($mis_naciones)) {
        $placeholders = str_repeat('?,', count($mis_naciones) - 1) . '?';
        $stmt_full = $pdo->prepare("SELECT * FROM catalogo_tienda WHERE nacion IN ($placeholders) ORDER BY rango ASC");
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
        }
    }

    $stmt_rivales = $pdo->prepare("SELECT id, nombre_equipo, bandera_url, naciones_activas FROM cuentas WHERE rol = 'lider' AND id != :id ORDER BY nombre_equipo ASC");
    $stmt_rivales->execute([':id' => $lider_id]);
    $rivales = $stmt_rivales->fetchAll(PDO::FETCH_ASSOC);

    $stmt_inv_global = $pdo->prepare("SELECT i.cuenta_id, c.*, i.cantidad as stock_actual FROM inventario i JOIN catalogo_tienda c ON i.catalogo_id = c.id WHERE i.cuenta_id != :id AND i.cantidad > 0");
    $stmt_inv_global->execute([':id' => $lider_id]);
    $inventario_global = $stmt_inv_global->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) { die("Fallo de Red: " . $e->getMessage()); }
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
        .btn-close-modal:hover { background: #ef4444; color: #fff; }
        .slot-box { border: 2px dashed #222; height: auto; min-height: 400px; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #090909; cursor: pointer; transition: 0.3s; }
        .slot-box:hover { border-color: #c5a059; background: #0f0f0f; }
        .slot-box.filled { border-style: solid; border-color: #1a1a1a; justify-content: flex-start; padding: 25px; }
        .f-input { background: #000; border: 1px solid #333; color: #fff !important; padding: 12px; width: 100%; margin-top: 5px; font-family: monospace; }
        .stat-label { font-size: 10px; color: #777; font-weight: 900; text-transform: uppercase; }
        .badge-process { color: #ef4444; font-size: 10px; font-weight: 900; background: rgba(239, 68, 68, 0.1); padding: 2px 6px; border: 1px solid rgba(239, 68, 68, 0.2); margin-left: 8px; }
        @keyframes pulse-trade { 0%, 100% { border-color: #3b82f6; box-shadow: 0 0 10px rgba(59, 130, 246, 0.4); } 50% { border-color: #fff; } }
        .btn-trade-active { animation: pulse-trade 1.5s infinite; background: #1e3a8a !important; color: white !important; }
        .balance-pill { font-size: 8px; font-weight: 900; padding: 2px 6px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 2px; }
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
                        <button onclick="setNacion('<?php echo htmlspecialchars($n); ?>')" data-nacion="<?php echo htmlspecialchars($n); ?>" class="tab-nacion px-4 py-1 text-[10px]"><?php echo htmlspecialchars($n); ?></button>
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

        <div id="cont_hangar" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-6">
            <?php foreach($catalogo_nacional as $item): 
                $inv = $mi_stock[$item['id']] ?? ['cantidad' => 0, 'inv_id' => 0];
                $proc = $en_proceso[$inv['inv_id']] ?? 0;
                $stock_neto = $inv['cantidad'] - $proc;
                $item_json = htmlspecialchars(json_encode(array_merge($item, $inv, ['neto' => $stock_neto])), ENT_QUOTES, 'UTF-8');
            ?>
            <div class="fila-v flex flex-col bg-[#111] border border-[#1a1a1a] relative" data-tipo="<?php echo $item['tipo']; ?>" data-nacion="<?php echo $item['nacion']; ?>">
                <div class="h-32 bg-black"><img src="../<?php echo $item['imagen_url']; ?>" class="w-full h-full object-cover"></div>
                <div class="p-4 flex-grow">
                    <span class="text-white font-black uppercase text-[11px]"><?php echo htmlspecialchars($item['nombre_vehiculo']); ?></span>
                    <div class="mt-3 pt-3 border-t border-gray-800 flex justify-between items-center">
                        <span class="text-gray-500 text-[8px] font-black uppercase">STOCK LIBRE</span>
                        <div class="flex items-center">
                            <span class="text-xl font-black <?php echo $stock_neto > 0 ? 'text-[#c5a059]' : 'text-gray-800'; ?>"><?php echo $stock_neto; ?>x</span>
                            <?php if($proc > 0): ?>
                                <span class="badge-process">-<?php echo $proc; ?> EN TRÁNSITO</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="p-2 bg-black/60 border-t border-[#1a1a1a]">
                    <?php if(!in_array($item['id'], $mis_planos)): ?>
                        <div class="py-2.5 text-center text-[9px] text-red-600 font-black uppercase border border-red-900/30">Requiere Patente</div>
                    <?php elseif($stock_neto <= 0): ?>
                        <div class="py-2.5 text-center text-[9px] text-gray-700 font-black uppercase border border-white/5">Sin Activos Libres</div>
                    <?php else: ?>
                        <button onclick='abrirModalReembolso(<?php echo $item_json; ?>)' class="btn-m w-full !py-2.5 !text-[9px] !bg-red-950/30 !text-red-500 border-red-900 font-black uppercase">DEVOLVER AL STAFF</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div id="cont_flotas" class="hidden grid grid-cols-1 md:grid-cols-3 gap-10">
            <?php for($s=1; $s<=3; $s++): $fl = $mis_flotas[$s]; ?>
                <div onclick='abrirEditorFlota(<?php echo $s; ?>, <?php echo json_encode($fl); ?>)' class="slot-box <?php echo $fl ? 'filled' : ''; ?>">
                    <?php if(!$fl): ?>
                        <span class="plus-icon">+</span>
                        <span class="text-[11px] font-black text-gray-700 uppercase tracking-[0.3em]">SLOT <?php echo $s; ?> VACÍO</span>
                    <?php else: ?>
                        <div class="w-full text-center border-b border-yellow-900/20 pb-4 mb-8 text-[#c5a059] font-black text-[11px] uppercase tracking-widest">FLOTA ACTIVADA - SLOT <?php echo $s; ?></div>
                        <div class="w-full space-y-6">
                            <div><span class="stat-label block mb-1">Unidad Insignia</span><div class="bg-black p-3 border border-white/5 text-white font-bold uppercase text-sm"><?php echo htmlspecialchars($fl['insignia']); ?></div></div>
                            <div class="grid grid-cols-2 gap-3 text-gray-400 uppercase text-[10px]">
                                <div><span class="stat-label block mb-1">Esc 1</span><div class="bg-black p-2 border border-white/5"><?php echo htmlspecialchars($fl['escolta_1'] ?: '-'); ?></div></div>
                                <div><span class="stat-label block mb-1">Esc 2</span><div class="bg-black p-2 border border-white/5"><?php echo htmlspecialchars($fl['escolta_2'] ?: '-'); ?></div></div>
                                <div><span class="stat-label block mb-1">Esc 3</span><div class="bg-black p-2 border border-white/5"><?php echo htmlspecialchars($fl['escolta_3'] ?: '-'); ?></div></div>
                                <div><span class="stat-label block mb-1">Esc 4</span><div class="bg-black p-2 border border-white/5"><?php echo htmlspecialchars($fl['escolta_4'] ?: '-'); ?></div></div>
                            </div>
                        </div>
                        <span class="mt-auto text-[9px] text-blue-500 font-black uppercase">Editar Configuración</span>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    </main>

    <div id="modalHistorial" class="hidden fixed inset-0 bg-black/95 z-[100] flex items-center justify-center p-4">
        <div class="m-panel w-full max-w-2xl border-red-800 bg-[#0d0e0a] p-10 relative shadow-2xl">
            <button onclick="cerrarModal('modalHistorial')" class="btn-close-modal">&times;</button>
            <h2 class="text-red-500 font-black text-center text-[10px] uppercase mb-8 border-b border-red-900/50 pb-2 tracking-widest">PETICIONES LOGÍSTICAS EN CURSO</h2>
            <div class="space-y-4 max-h-[50vh] overflow-y-auto pr-2">
                <?php if(empty($reembolsos_activos)): ?>
                    <p class="text-center text-gray-600 uppercase font-black text-xs py-10">No hay peticiones activas en este sector.</p>
                <?php else: foreach($reembolsos_activos as $ra): ?>
                    <div class="flex justify-between items-center bg-black/40 p-4 border border-white/5 group hover:border-red-900 transition">
                        <div>
                            <span class="text-white font-black uppercase text-sm"><?php echo htmlspecialchars($ra['nombre_vehiculo']); ?></span>
                            <span class="text-red-500 font-black ml-4">x<?php echo $ra['cantidad']; ?></span>
                            <div class="text-[9px] text-gray-500 mt-1 uppercase font-bold">SOLICITADO EL: <?php echo date('d/m H:i', strtotime($ra['fecha'])); ?></div>
                        </div>
                        <form action="../logic/cancelar_reembolso.php" method="POST">
                            <input type="hidden" name="id" value="<?php echo $ra['id']; ?>">
                            <button type="submit" class="bg-red-900/20 text-red-500 border border-red-900 px-5 py-2 text-[9px] font-black uppercase hover:bg-red-700 hover:text-white transition">CANCELAR</button>
                        </form>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <div id="modalReembolso" class="hidden fixed inset-0 bg-black/95 z-[100] flex items-center justify-center p-4">
        <div class="m-panel w-full max-w-md border-red-800 bg-[#0d0e0a] p-10 shadow-2xl relative">
            <button onclick="cerrarModal('modalReembolso')" class="btn-close-modal">&times;</button>
            <h2 class="text-red-500 font-black text-center text-[10px] uppercase mb-10 border-b border-red-900/50 pb-2 tracking-widest">SOLICITAR REINTEGRO TÁCTICO</h2>
            <form action="../logic/solicitar_reembolso.php" method="POST">
                <input type="hidden" id="re_inv_id" name="inventario_id">
                <div class="text-center mb-8"><span id="re_nombre" class="text-white font-black uppercase text-2xl"></span></div>
                <div class="mb-4 text-center">
                    <label class="stat-label block mb-2">CANTIDAD DISPONIBLE: <span id="re_max_display" class="text-white"></span></label>
                    <input type="number" id="re_qty" name="cantidad" value="1" min="1" oninput="calcReMath()" class="f-input !text-4xl py-6 text-center font-black">
                </div>
                <div id="re_stock_error" class="hidden text-red-500 text-[10px] font-black uppercase text-center mb-6 animate-pulse">⚠️ CANTIDAD SUPERA EL STOCK LIBRE</div>
                <div class="bg-red-950/20 p-6 border border-red-900/30 text-center mb-10">
                    <div class="flex justify-around font-black font-mono">
                        <div><span class="stat-label !mb-1">CASH</span><span id="re_res_d" class="text-green-500 text-xl">$0</span></div>
                        <div><span class="stat-label !mb-1">STEEL</span><span id="re_res_a" class="text-white text-xl">0T</span></div>
                        <div><span class="stat-label !mb-1">FUEL</span><span id="re_res_p" class="text-yellow-500 text-xl">0L</span></div>
                    </div>
                </div>
                <button type="submit" id="btnEnviarRe" class="btn-m w-full py-5 !bg-red-700 !text-white border-red-500 font-black uppercase">ENVIAR PETICIÓN</button>
            </form>
        </div>
    </div>

    <div id="modalError" class="hidden fixed inset-0 bg-black/98 z-[300] flex items-center justify-center p-4">
        <div class="m-panel w-full max-w-sm border-red-600 bg-[#120505] p-10 text-center shadow-2xl relative">
            <button onclick="cerrarModal('modalError')" class="btn-close-modal">&times;</button>
            <div class="text-red-500 text-5xl mb-6">⚠️</div>
            <h3 class="text-white font-black uppercase tracking-widest mb-4">ERROR OPERATIVO</h3>
            <p id="error_msg_text" class="text-gray-400 text-xs uppercase font-bold mb-8 leading-relaxed"></p>
            <button onclick="cerrarModal('modalError')" class="btn-m w-full !bg-red-900/20 !border-red-600 !text-red-500 py-3 font-black uppercase">ENTENDIDO</button>
        </div>
    </div>

    <div id="modalMercado" class="hidden fixed inset-0 bg-black/98 z-[150] flex items-center justify-center p-4">
        <div class="m-panel w-full max-w-6xl h-[90vh] relative border-blue-900 overflow-hidden flex flex-col shadow-2xl">
            <button onclick="cerrarModal('modalMercado')" class="btn-close-modal">&times;</button>
            <div class="flex border-b border-blue-900/30 bg-black/40">
                <button onclick="subTabMercado('crear')" id="sm_crear" class="flex-1 py-4 text-[9px] font-black uppercase tracking-widest border-b-2 border-blue-500 bg-blue-500/10 text-white">NUEVA PROPUESTA</button>
                <button onclick="subTabMercado('ordenes')" id="sm_ordenes" class="flex-1 py-4 text-[9px] font-black uppercase tracking-widest border-b-2 border-transparent text-gray-500">MIS ÓRDENES ACTIVAS (<?php echo count($mis_ordenes); ?>)</button>
            </div>
            <div id="sec_m_crear" class="flex-grow flex overflow-hidden">
                <div class="w-64 bg-black/40 border-r border-blue-900/30 overflow-y-auto p-4 space-y-3">
                    <?php foreach($rivales as $ri): ?>
                        <button onclick='seleccionarRival(<?php echo json_encode($ri); ?>)' class="btn-rival-selector w-full flex items-center gap-3 p-3 border border-gray-800 group" id="btn-rival-<?php echo $ri['id']; ?>">
                            <div class="w-10 h-6 bg-gray-900 border border-gray-700 overflow-hidden"><img src="../<?php echo $ri['bandera_url']; ?>" class="w-full h-full object-cover grayscale group-hover:grayscale-0"></div>
                            <span class="text-[10px] text-gray-400 font-black uppercase truncate group-hover:text-white"><?php echo htmlspecialchars($ri['nombre_equipo']); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="flex-1 flex flex-col bg-[#05070a]">
                    <div id="m_naciones_rival" class="p-3 border-b border-blue-900/20 flex gap-2"></div>
                    <div id="m_grid_rival" class="flex-grow overflow-y-auto p-6 grid grid-cols-3 gap-6"></div>
                </div>
                <div class="w-80 border-l border-blue-900/30 p-8 flex flex-col overflow-y-auto">
                    <form action="../logic/procesar_tradeo.php" method="POST" onsubmit="return validarTradeo(event)">
                        <input type="hidden" name="accion" value="crear"><input type="hidden" name="receptor_id" id="t_receptor_id"><input type="hidden" name="vehiculo_requerido_id" id="t_cat_id">
                        <div id="item_preview" class="mb-8 bg-blue-900/20 border border-blue-500/40 p-5 text-center hidden">
                            <span id="t_nombre_item" class="text-white font-black text-sm uppercase block mb-3 font-['Cinzel']"></span>
                            <div id="t_valor_item" class="flex justify-around text-[10px] font-black border-t border-blue-900/30 pt-3 mb-5"></div>
                            <label class="stat-label">CANTIDAD REQUERIDA:</label><input type="number" name="cantidad_requerida" id="t_qty_req" value="1" min="1" oninput="multiValRival()" class="f-input !text-blue-400 text-xl font-black">
                        </div>
                        <div class="space-y-6">
                            <span class="text-[9px] text-green-500 font-black uppercase border-b border-green-900/30 pb-2 block">OFERTA DE RECURSOS:</span>
                            <div class="grid grid-cols-1 gap-4">
                                <div><div class="flex justify-between items-center mb-1"><label class="stat-label">CASH</label><span id="bal_d" class="balance-pill text-green-500">Saldo: $<?php echo number_format($user['dinero']); ?></span></div><input type="number" name="ofrece_dinero" id="off_d" value="0" oninput="projBal()" class="f-input !p-2 !text-xs text-green-500"></div>
                                <div><div class="flex justify-between items-center mb-1"><label class="stat-label">STEEL</label><span id="bal_a" class="balance-pill text-white">Saldo: <?php echo $user['acero']; ?>T</span></div><input type="number" name="ofrece_acero" id="off_a" value="0" oninput="projBal()" class="f-input !p-2 !text-xs text-white"></div>
                                <div><div class="flex justify-between items-center mb-1"><label class="stat-label">FUEL</label><span id="bal_p" class="balance-pill text-yellow-500">Saldo: <?php echo $user['petroleo']; ?>L</span></div><input type="number" name="ofrece_petroleo" id="off_p" value="0" oninput="projBal()" class="f-input !p-2 !text-xs text-yellow-500"></div>
                            </div>
                            <label class="stat-label">INTERCAMBIAR POR:</label>
                            <select name="vehiculo_ofrecido_id" id="select_mio" onchange="actualizarOfertaMio()" class="f-input !text-[10px]">
                                <option value="">-- NINGUNO --</option>
                                <?php foreach($mi_catalogo_js as $id => $d): if($d['stock'] > 0): ?>
                                    <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($d['nombre']); ?> (<?php echo $d['stock']; ?>x)</option>
                                <?php endif; endforeach; ?>
                            </select>
                            <div id="mio_extra" class="hidden">
                                <div id="mio_valor" class="flex justify-around text-[10px] font-black border-t border-gray-800 pt-3 mb-4 font-mono"></div>
                                <div class="flex items-center justify-between"><span class="stat-label">CANTIDAD:</span><input type="number" name="cantidad_ofrecida" id="ofre_qty" value="1" min="1" oninput="multiValMio()" class="w-20 f-input !text-green-500"></div>
                            </div>
                        </div>
                        <button type="submit" class="btn-m w-full py-5 !bg-blue-600 !text-white !border-blue-400 text-[10px] font-black uppercase mt-8">ENVIAR CONTRATO</button>
                    </form>
                </div>
            </div>
            <div id="sec_m_ordenes" class="hidden flex-grow overflow-y-auto p-10 bg-[#05070a]">
                <?php foreach($mis_ordenes as $o): ?>
                    <div class="m-panel bg-black/60 border-l-4 border-l-yellow-600 p-6 flex justify-between items-center mb-4">
                        <div><span class="stat-label block mb-1 uppercase font-bold">RECEPTOR: <?php echo htmlspecialchars($o['receptor']); ?></span><h4 class="text-white font-black uppercase font-['Cinzel']">PIDES: <span class="text-blue-400"><?php echo htmlspecialchars($o['v_requerido']); ?> x<?php echo $o['cantidad_requerida']; ?></span></h4></div>
                        <form action="../logic/procesar_tradeo.php" method="POST"><input type="hidden" name="accion" value="cancelar"><input type="hidden" name="tradeo_id" value="<?php echo $o['id']; ?>"><button type="submit" class="bg-red-900/20 text-red-500 border border-red-900 px-6 py-2 text-[10px] font-black uppercase hover:bg-red-700 transition">CANCELAR</button></form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div id="modalFlota" class="hidden fixed inset-0 bg-black/95 z-[100] flex items-center justify-center p-4">
        <div class="m-panel w-full max-w-lg border-blue-900 bg-[#0d0e0a] p-10 shadow-2xl relative">
            <button onclick="cerrarModal('modalFlota')" class="btn-close-modal">&times;</button>
            <h2 class="text-white font-black uppercase mb-8 border-b border-gray-800 pb-4 tracking-widest text-center">CONFIGURACIÓN DE SLOT #<span id="slot_num"></span></h2>
            <form action="../logic/actualizar_flota.php" method="POST" class="space-y-6">
                <input type="hidden" name="slot" id="slot_input">
                <div><label class="stat-label">Unidad Insignia</label><input type="text" name="insignia" id="in_ins" required class="f-input" placeholder="Nombre del buque insignia..."></div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="stat-label">Escolta #1</label><input type="text" name="escolta_1" id="in_e1" class="f-input" placeholder="..."></div>
                    <div><label class="stat-label">Escolta #2</label><input type="text" name="escolta_2" id="in_e2" class="f-input" placeholder="..."></div>
                    <div><label class="stat-label">Escolta #3</label><input type="text" name="escolta_3" id="in_e3" class="f-input" placeholder="..."></div>
                    <div><label class="stat-label">Escolta #4</label><input type="text" name="escolta_4" id="in_e4" class="f-input" placeholder="..."></div>
                </div>
                <div class="flex gap-4 pt-6">
                    <button type="submit" class="btn-m flex-1 !bg-blue-600 !text-white !py-4 font-black">GRABAR FLOTA</button>
                    <button type="button" onclick="cerrarModal('modalFlota')" class="px-8 border border-white/10 text-gray-500 font-black text-[10px]">CANCELAR</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const resActual = { d: <?php echo $user['dinero']; ?>, a: <?php echo $user['acero']; ?>, p: <?php echo $user['petroleo']; ?> };
        const invGlobal = <?php echo json_encode($inventario_global); ?>;
        const miHangarPrecios = <?php echo json_encode($mi_catalogo_js); ?>;
        let itemReSel = null, rSel = null, rivalItemSel = null, nacActual = '<?php echo $mis_naciones[0] ?? ""; ?>';

        function projBal() {
            const offD = parseInt(document.getElementById('off_d').value) || 0, offA = parseInt(document.getElementById('off_a').value) || 0, offP = parseInt(document.getElementById('off_p').value) || 0;
            const update = (id, cur, off, sym) => {
                const el = document.getElementById(id), rem = cur - off;
                el.innerText = `Saldo: ${rem.toLocaleString()}${sym}`;
                el.style.color = rem < 0 ? '#ff0000' : '';
            };
            update('bal_d', resActual.d, offD, '$'); update('bal_a', resActual.a, offA, 'T'); update('bal_p', resActual.p, offP, 'L');
        }

        function abrirModalReembolso(i) {
            itemReSel = i; document.getElementById('re_nombre').innerText = i.nombre_vehiculo;
            document.getElementById('re_max_display').innerText = i.neto + " UNIDADES LIBRES";
            document.getElementById('re_inv_id').value = i.inv_id; document.getElementById('re_qty').value = 1;
            calcReMath(); abrirModal('modalReembolso');
        }
        function calcReMath() {
            const input = document.getElementById('re_qty'), q = parseInt(input.value) || 0, btn = document.getElementById('btnEnviarRe'), err = document.getElementById('re_stock_error');
            if(q > itemReSel.neto) { input.classList.add('input-error'); btn.disabled = true; btn.style.opacity = '0.3'; err.classList.remove('hidden'); } 
            else { input.classList.remove('input-error'); btn.disabled = false; btn.style.opacity = '1'; err.classList.add('hidden'); }
            document.getElementById('re_res_d').innerText = '$'+(q * itemReSel.costo_dinero).toLocaleString();
            document.getElementById('re_res_a').innerText = (q * itemReSel.costo_acero).toLocaleString()+'T';
            document.getElementById('re_res_p').innerText = (q * itemReSel.costo_petroleo).toLocaleString()+'L';
        }

        function setNacion(n) { nacActual = n; document.querySelectorAll('.tab-nacion').forEach(b => b.classList.toggle('active', b.dataset.nacion === n)); renderTabs(); }
        function setSeccion(s) { document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active')); document.getElementById('nav_'+s).classList.add('active'); document.getElementById('cont_hangar').style.display = (s === 'flotas') ? 'none' : 'grid'; document.getElementById('cont_flotas').classList.toggle('hidden', s !== 'flotas'); renderTabs(); }
        function renderTabs() { const s = document.querySelector('.nav-btn.active').id.replace('nav_', ''); if(s !== 'flotas') { document.querySelectorAll('.fila-v').forEach(f => f.style.display = (f.dataset.tipo === s && f.dataset.nacion === nacActual) ? 'flex' : 'none'); } }
        function subTabMercado(s) { document.getElementById('sec_m_crear').classList.toggle('hidden', s !== 'crear'); document.getElementById('sec_m_ordenes').classList.toggle('hidden', s !== 'ordenes'); document.getElementById('sm_crear').className = s === 'crear' ? 'flex-1 py-4 text-[9px] font-black uppercase border-b-2 border-blue-500 bg-blue-500/10 text-white' : 'flex-1 py-4 text-[9px] font-black uppercase text-gray-500'; document.getElementById('sm_ordenes').className = s === 'ordenes' ? 'flex-1 py-4 text-[9px] font-black uppercase border-b-2 border-blue-500 bg-blue-500/10 text-white' : 'flex-1 py-4 text-[9px] font-black uppercase text-gray-500'; }
        
        // REPARADO: Carga completa de datos al formulario
        function abrirEditorFlota(s, d) { 
            document.getElementById('slot_num').innerText = s; 
            document.getElementById('slot_input').value = s; 
            document.getElementById('in_ins').value = d ? d.insignia : ''; 
            document.getElementById('in_e1').value = d ? d.escolta_1 : ''; 
            document.getElementById('in_e2').value = d ? d.escolta_2 : ''; 
            document.getElementById('in_e3').value = d ? d.escolta_3 : ''; 
            document.getElementById('in_e4').value = d ? d.escolta_4 : ''; 
            abrirModal('modalFlota'); 
        }
        function abrirModal(id) { document.getElementById(id).classList.remove('hidden'); document.body.classList.add('modal-active'); }
        function cerrarModal(id) { document.getElementById(id).classList.add('hidden'); document.body.classList.remove('modal-active'); }
        
        function seleccionarRival(r) {
            rSel = r; document.querySelectorAll('.btn-rival-selector').forEach(b => b.classList.remove('active'));
            document.getElementById('btn-rival-'+r.id).classList.add('active');
            document.getElementById('t_receptor_id').value = r.id;
            const nav = document.getElementById('m_naciones_rival'); nav.innerHTML = '';
            const nacs = r.naciones_activas ? r.naciones_activas.split(',').map(n => n.trim()) : [];
            nacs.forEach(n => {
                const b = document.createElement('button'); b.className = 'tab-nacion px-4 py-2 bg-black border border-gray-800 text-[10px]';
                b.innerText = n; b.onclick = () => { nav.querySelectorAll('button').forEach(x => x.classList.remove('active')); b.classList.add('active'); renderGridRival(n); };
                nav.appendChild(b);
            });
            if(nacs[0]) nav.querySelector('button').click();
        }
        function renderGridRival(n) {
            const grid = document.getElementById('m_grid_rival'); grid.innerHTML = '';
            const items = invGlobal.filter(i => i.cuenta_id == rSel.id && i.nacion == n);
            items.forEach(i => {
                const c = document.createElement('div'); c.className = 'slot-box h-48 border-gray-800 overflow-hidden';
                c.onclick = () => { document.querySelectorAll('.slot-box').forEach(x => x.style.borderColor = '#222'); c.style.borderColor = '#c5a059';
                    rivalItemSel = i; document.getElementById('t_cat_id').value = i.id; document.getElementById('t_nombre_item').innerText = i.nombre_vehiculo;
                    document.getElementById('item_preview').classList.remove('hidden'); multiValRival();
                };
                c.innerHTML = `<div class="h-24 w-full bg-gray-900"><img src="../${i.imagen_url}" class="w-full h-full object-cover"></div><div class="p-3 text-center"><span class="text-[10px] text-white font-black uppercase truncate block">${i.nombre_vehiculo}</span><span class="text-[9px] text-blue-400 font-bold">${i.stock_actual}x</span></div>`;
                grid.appendChild(c);
            });
        }
        function multiValRival() { const q = parseInt(document.getElementById('t_qty_req').value) || 1; document.getElementById('t_valor_item').innerHTML = `<span class='text-green-500 font-black'>$${(q * rivalItemSel.costo_dinero).toLocaleString()}</span><span class='text-white font-black'>${q * rivalItemSel.costo_acero}T</span><span class='text-yellow-500 font-black'>${q * rivalItemSel.costo_petroleo}L</span>`; }
        function actualizarOfertaMio() {
            const id = document.getElementById('select_mio').value;
            if(id && miHangarPrecios[id]) { document.getElementById('mio_extra').classList.remove('hidden'); document.getElementById('ofre_qty').max = miHangarPrecios[id].stock; document.getElementById('ofre_qty').value = 1; multiValMio(); } 
            else document.getElementById('mio_extra').classList.add('hidden');
        }
        function multiValMio() { const id = document.getElementById('select_mio').value, q = parseInt(document.getElementById('ofre_qty').value) || 0, d = miHangarPrecios[id]; document.getElementById('mio_valor').innerHTML = `<span class='text-green-500 font-black'>$${(q * d.dinero).toLocaleString()}</span><span class='text-white font-black'>${q * d.acero}T</span><span class='text-yellow-500 font-black'>${q * d.petroleo}L</span>`; }

        function mostrarError(txt) { document.getElementById('error_msg_text').innerText = txt; abrirModal('modalError'); }
        function validarTradeo(e) {
            if(!rivalItemSel) { e.preventDefault(); mostrarError("COMUNICACIÓN FALLIDA: Debe interceptar un activo del rival."); return false; }
            if(parseInt(document.getElementById('off_d').value) > resActual.d || parseInt(document.getElementById('off_a').value) > resActual.a || parseInt(document.getElementById('off_p').value) > resActual.p) { 
                e.preventDefault(); mostrarError("FONDOS INSUFICIENTES: Operación cancelada por falta de liquidez."); return false; 
            }
            return true;
        }

        setNacion(nacActual); setSeccion('tanque');
    </script>
</body>
</html>