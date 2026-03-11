<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') { header("Location: ../login.php"); exit(); }
require_once '../config/conexion.php';
$root_path = "../";
$txt = require '../config/textos.php';

try {
    // 1. EQUIPOS: Usamos 'username' según militar_rp.sql
    $stmt_eq = $pdo->query("SELECT id, username, nombre_equipo, bandera_url, dinero, acero, petroleo, naciones_activas FROM cuentas WHERE rol = 'lider' ORDER BY nombre_equipo ASC");
    $equipos = $stmt_eq->fetchAll(PDO::FETCH_ASSOC);

    // 2. SOLICITUDES: Nomenclatura táctica para el modal
    $stmt_sol = $pdo->query("
        SELECT s.*, u.nombre_equipo, u.dinero as cash_actual, u.acero as steel_actual, u.petroleo as fuel_actual, c.nombre_vehiculo 
        FROM solicitudes_reembolso s
        JOIN cuentas u ON s.cuenta_id = u.id
        JOIN inventario i ON s.inventario_id = i.id
        JOIN catalogo_tienda c ON i.catalogo_id = c.id
        WHERE s.estado = 'pendiente'
        ORDER BY s.fecha DESC
    ");
    $solicitudes = $stmt_sol->fetchAll(PDO::FETCH_ASSOC);

    // NUEVO: Obtener historial de tradeos cerrados
    $stmt_hist = $pdo->query("
        SELECT m.*, o.nombre_equipo as ofertante, r.nombre_equipo as receptor, c.nombre_vehiculo 
        FROM mercado_tradeos m 
        JOIN cuentas o ON m.ofertante_id = o.id 
        JOIN cuentas r ON m.receptor_id = r.id 
        LEFT JOIN catalogo_tienda c ON m.vehiculo_ofrecido_id = c.id 
        WHERE m.estado IN ('completado', 'cancelado')
        ORDER BY m.fecha_creacion DESC LIMIT 15
    ");
    $historial_tradeos = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);

    // OBTENER TODA LA LISTA MAESTRA DE NACIONES
    $stmt_nac = $pdo->query("SELECT nombre FROM naciones ORDER BY nombre ASC");
    $todas_las_naciones = $stmt_nac->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) { die("FALLO EN ENLACE SATELITAL: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title><?php echo $txt['STAFF_DASHBOARD']['TITULO_PAGINA']; ?></title>
    <?php include '../includes/head.php'; ?>
    <style>
        :root {
            --m-bg: #20231a;
            --m-gold: #c5a059;
            --m-green: #10b981;
        }
        .modal-active { overflow: hidden; }
        
        /* Eliminamos blurs y dejamos paneles sólidos */
        .glass-panel {
            background: #0d0e0a;
            border: 1px solid rgba(197, 160, 89, 0.4);
            box-shadow: 0 0 40px rgba(0,0,0,0.9);
        }

        .terminal-input { 
            background: #000; 
            border: 1px solid #333; 
            color: #fff; 
            padding: 12px; 
            border-radius: 2px;
            font-family: 'Space Mono', monospace;
            width: 100%;
        }
        .terminal-input:focus { border-color: var(--m-gold); outline: none; }
        
        .tactical-label { 
            font-size: 9px; 
            color: rgba(255,255,255,0.5); 
            font-weight: 900; 
            text-transform: uppercase; 
            letter-spacing: 2px; 
            margin-bottom: 8px; 
            display: block; 
        }

        .btn-nacion-toggle {
            background: #0a0a0a;
            border: 1px solid #333;
            color: #555;
            padding: 8px 14px;
            font-size: 9px;
            font-weight: 900;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-nacion-toggle:hover { border-color: #777; color: #fff; }
        .btn-nacion-toggle.active {
            background: rgba(197, 160, 89, 0.15);
            border-color: var(--m-gold);
            color: var(--m-gold);
            box-shadow: 0 0 10px rgba(197, 160, 89, 0.4);
        }
    </style>
</head>
<body class="bg-[#0a0b08] text-[var(--text-main)] min-h-screen pb-20">
    <?php include '../includes/nav_staff.php'; ?>

    <main class="p-8 max-w-[1400px] mx-auto">
        <header class="mb-12 border-b border-white/5 pb-8">
            <h1 class="text-3xl font-black tracking-tighter text-white uppercase italic font-['Cinzel']"><?php echo $txt['STAFF_DASHBOARD']['TITULO_CENTRO']; ?></h1>
        </header>

        <?php if (isset($_GET['msg'])): ?>
            <div class="mb-8 p-4 text-[10px] font-black uppercase tracking-[0.2em] text-center shadow-lg backdrop-blur-md border">
                <?php 
                    if ($_GET['msg'] === 'update_ok') {
                        echo '<div class="text-green-500 border-green-900/50 bg-green-900/10 p-2">'. $txt['STAFF_DASHBOARD']['MSG_UPDATE_OK'] .'</div>';
                    }
                    if ($_GET['msg'] === 'censura_ok') {
                        echo '<div class="text-red-500 border-red-900/50 bg-red-900/10 p-2">'. $txt['STAFF_DASHBOARD']['MSG_CENSURA_OK'] .'</div>';
                    }
                    if ($_GET['msg'] === 'err_nuke') {
                        echo '<div class="text-red-500 border-red-900 bg-red-900/10 p-2">'. $txt['STAFF_DASHBOARD']['ERR_NUKE'] .'</div>';
                    }
                ?>
            </div>
        <?php endif; ?>

        <section class="mb-20">
            <h2 class="text-[10px] font-black uppercase tracking-[0.4em] mb-8 text-blue-400"><?php echo $txt['STAFF_DASHBOARD']['TIT_SOLICITUDES']; ?></h2>
            <?php if(empty($solicitudes)): ?>
                <div class="m-panel border-dashed border-white/5 text-center py-10 opacity-30">
                    <p class="text-gray-500 text-[10px] uppercase font-black"><?php echo $txt['STAFF_DASHBOARD']['SIN_SOLICITUDES']; ?></p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 gap-4">
                    <?php foreach($solicitudes as $s): ?>
                        <div class="m-panel !p-0 overflow-hidden bg-[#11130f] border border-white/5 flex justify-between items-center group">
                            <div class="p-6">
                                <span class="text-[10px] text-blue-500 font-black uppercase mb-1 block"><?php echo htmlspecialchars($s['nombre_equipo']); ?></span>
                                <h3 class="text-white font-black text-xl uppercase tracking-tighter"><?php echo htmlspecialchars($s['nombre_vehiculo']); ?> <span class="text-blue-500 ml-3">x<?php echo $s['cantidad']; ?></span></h3>
                            </div>
                            <button onclick='abrirModalImpacto(<?php echo json_encode($s); ?>)' class="h-full px-12 bg-blue-900/20 hover:bg-blue-600 text-blue-400 hover:text-white font-black text-[11px] uppercase tracking-widest transition-all"><?php echo $txt['STAFF_DASHBOARD']['BTN_ANALIZAR']; ?></button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <h2 class="text-[10px] font-black uppercase tracking-[0.4em] mb-8 text-[var(--m-gold)]"><?php echo $txt['STAFF_DASHBOARD']['TIT_EXPEDIENTES']; ?></h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            <?php foreach($equipos as $e): ?>
                <div class="m-panel !p-0 overflow-hidden bg-[#0d0e0a] border border-white/5 hover:border-yellow-500/30 transition-all group flex flex-col h-full shadow-lg">
                    <div class="p-6 flex flex-col flex-grow">
                        
                        <div class="flex justify-between items-start mb-6">
                            <div class="flex gap-4 overflow-hidden">
                                <div class="w-12 h-12 bg-black border border-white/10 rounded shrink-0 overflow-hidden">
                                    <?php if($e['bandera_url']): ?>
                                        <img src="../<?php echo $e['bandera_url']; ?>" class="w-full h-full object-cover grayscale-[0.5] group-hover:grayscale-0 transition">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center text-gray-800 text-xs font-black">?</div>
                                    <?php endif; ?>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-white font-black text-lg uppercase leading-tight font-['Cinzel'] truncate" title="<?php echo htmlspecialchars($e['nombre_equipo']); ?>">
                                        <?php echo htmlspecialchars($e['nombre_equipo']); ?>
                                    </h3>
                                    <p class="text-[9px] text-gray-500 font-bold uppercase tracking-widest mt-1"><?php echo $txt['STAFF_DASHBOARD']['LBL_ID']; ?> <span class="text-gray-300"><?php echo htmlspecialchars($e['username']); ?></span></p>
                                </div>
                            </div>
                            <div class="flex flex-col gap-1 shrink-0 ml-4">
                                <button onclick='abrirModalEditar(<?php echo htmlspecialchars(json_encode($e), ENT_QUOTES, "UTF-8"); ?>)' class="text-[9px] font-black uppercase text-yellow-500 border border-yellow-500/20 px-3 py-1 hover:bg-yellow-500 hover:text-black transition tracking-widest"><?php echo $txt['STAFF_DASHBOARD']['BTN_EDITAR']; ?></button>
                                <a href="staff_ver_inventario.php?id=<?php echo $e['id']; ?>" class="text-[9px] font-black uppercase text-blue-400 border border-blue-500/20 px-3 py-1 hover:bg-blue-600 hover:text-white transition text-center tracking-widest"><?php echo $txt['STAFF_DASHBOARD']['BTN_INV']; ?></a>
                            </div>
                        </div>

                        <div class="mb-6 flex-grow">
                            <span class="text-[8px] text-gray-600 font-black uppercase block mb-2 tracking-widest"><?php echo $txt['STAFF_DASHBOARD']['LBL_PAIS_ASIGNADO']; ?></span>
                            <div class="flex flex-wrap gap-1.5">
                                <?php 
                                // Limpiamos el string de países y lo convertimos en array
                                $nacs = array_filter(array_map('trim', explode(',', $e['naciones_activas'] ?? '')));
                                
                                if(!empty($nacs)): 
                                    foreach($nacs as $n): 
                                ?>
                                    <span class="bg-[#111] border border-[#333] text-[#c5a059] text-[9px] font-black px-2 py-0.5 uppercase tracking-widest shadow-sm"><?php echo htmlspecialchars($n); ?></span>
                                <?php 
                                    endforeach; 
                                else: 
                                ?>
                                    <span class="bg-red-950/20 border border-red-900/30 text-red-500 text-[8px] font-black px-2 py-0.5 uppercase tracking-widest"><?php echo $txt['STAFF_DASHBOARD']['LBL_NO_PAIS']; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 border-t border-white/5 pt-4 font-['Cinzel'] mt-auto">
                            <div class="text-center bg-black/40 p-2 rounded border border-white/5"><span class="tactical-label !mb-1">CASH</span><span class="text-green-500 text-xs font-black">$<?php echo number_format($e['dinero']); ?></span></div>
                            <div class="text-center bg-black/40 p-2 rounded border border-white/5"><span class="tactical-label !mb-1">STEEL</span><span class="text-white text-xs font-black"><?php echo number_format($e['acero']); ?>T</span></div>
                            <div class="text-center bg-black/40 p-2 rounded border border-white/5"><span class="tactical-label !mb-1">FUEL</span><span class="text-yellow-500 text-xs font-black"><?php echo number_format($e['petroleo']); ?>L</span></div>
                        </div>
                        
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <section class="mt-20">
            <h2 class="text-[10px] font-black uppercase tracking-[0.4em] mb-8 text-green-500"><?php echo $txt['STAFF_DASHBOARD']['TIT_BITACORA']; ?></h2>
            <div class="m-panel bg-black/40 border-green-900/30 overflow-hidden">
                <table class="w-full text-[10px] font-black uppercase text-left">
                    <thead>
                        <tr class="text-gray-500 border-b border-white/5 bg-black/60">
                            <th class="p-4"><?php echo $txt['STAFF_DASHBOARD']['TH_OFERTANTE']; ?></th>
                            <th class="p-4"><?php echo $txt['STAFF_DASHBOARD']['TH_RECEPTOR']; ?></th>
                            <th class="p-4"><?php echo $txt['STAFF_DASHBOARD']['TH_CONTENIDO']; ?></th>
                            <th class="p-4 text-right"><?php echo $txt['STAFF_DASHBOARD']['TH_FECHA']; ?></th>
                            <th class="p-4 text-center">ACCIÓN</th> </tr>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($historial_tradeos)): ?>
                            <tr><td colspan="5" class="p-10 text-center text-gray-700 italic"><?php echo $txt['STAFF_DASHBOARD']['SIN_MOVIMIENTOS']; ?></td></tr>
                        <?php else: foreach($historial_tradeos as $h): ?>
                            <tr class="border-b border-white/5 hover:bg-white/5 transition">
                                <td class="p-4 text-white font-bold"><?php echo htmlspecialchars($h['ofertante']); ?></td>
                                
                                <td class="p-4 text-blue-400"><?php echo htmlspecialchars($h['receptor']); ?></td>
                                
                                <td class="p-4">
                                    <?php if($h['estado'] === 'completado'): ?>
                                        <span class="px-2 py-1 rounded-sm bg-green-900/30 text-green-500 border border-green-500/30 text-[8px] font-black tracking-widest">ACEPTADO</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 rounded-sm bg-red-900/30 text-red-500 border border-red-500/30 text-[8px] font-black tracking-widest">RECHAZADO</span>
                                    <?php endif; ?>
                                </td>

                                <td class="p-4">
                                    <div class="flex flex-wrap gap-3">
                                        <?php if($h['ofrece_dinero'] > 0): ?> <span class="text-green-500">$<?php echo number_format($h['ofrece_dinero']); ?></span> <?php endif; ?>
                                        <?php if($h['ofrece_acero'] > 0): ?> <span class="text-white"><?php echo $h['ofrece_acero']; ?>T</span> <?php endif; ?>
                                        <?php if($h['ofrece_petroleo'] > 0): ?> <span class="text-yellow-500"><?php echo $h['ofrece_petroleo']; ?>L</span> <?php endif; ?>
                                        <?php if($h['nombre_vehiculo']): ?> 
                                            <span class="text-blue-300 border border-blue-900/30 px-2 rounded bg-blue-900/10">
                                                <?php echo $txt['STAFF_DASHBOARD']['LBL_UNIDAD']; ?> <?php echo htmlspecialchars($h['nombre_vehiculo']); ?>
                                            </span> 
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td class="p-4 text-center">
                                    <?php if($h['estado'] === 'completado'): ?>
                                        <button onclick='abrirModalRevertir(<?php echo htmlspecialchars(json_encode($h), ENT_QUOTES, "UTF-8"); ?>)' class="bg-red-900/20 text-red-500 border border-red-900 px-3 py-1 text-[8px] font-black uppercase hover:bg-red-600 hover:text-white transition tracking-widest">REVERTIR</button>
                                    <?php else: ?>
                                        <span class="text-gray-600 text-[8px]">-</span>
                                    <?php endif; ?>
                                </td>

                                <td class="p-4 text-right text-gray-600 font-mono"><?php echo date('d/m H:i', strtotime($h['fecha_creacion'])); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <section class="mt-20 border-t border-red-900/30 pt-10">
            <div class="m-panel bg-red-950/5 border-red-900/20 p-8 flex justify-between items-center shadow-[inset_0_0_20px_rgba(220,38,38,0.05)]">
                <div>
                    <h3 class="text-red-500 font-black uppercase tracking-[0.3em] mb-1 font-['Cinzel']"><?php echo $txt['STAFF_DASHBOARD']['MODAL_NUKE_TITULO']; ?></h3>
                    <p class="text-[10px] text-gray-500 uppercase font-bold tracking-widest"><?php echo $txt['STAFF_DASHBOARD']['MODAL_NUKE_DESC']; ?></p>
                </div>
                <button onclick="abrirModal('modalNuke')" class="bg-red-600 text-black px-10 py-4 font-black uppercase text-[11px] hover:bg-red-500 transition shadow-[0_0_30px_rgba(220,38,38,0.2)]">
                    <?php echo $txt['STAFF_DASHBOARD']['BTN_NUKE']; ?>
                </button>
            </div>
        </section>
    </main>

    <div id="modalEdit" class="hidden fixed inset-0 bg-black/95 z-[200] flex items-center justify-center p-4">
        <div class="m-panel w-full max-w-2xl glass-panel p-10">
            <h2 class="text-2xl font-black text-white uppercase italic mb-10 border-b border-white/5 pb-4"><?php echo $txt['STAFF_DASHBOARD']['MODAL_EDIT_TIT']; ?></h2>
            <form action="../logic/actualizar_equipo_staff.php" method="POST" class="space-y-8">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="grid grid-cols-2 gap-8">
                    <div>
                        <label class="tactical-label"><?php echo $txt['STAFF_DASHBOARD']['LBL_NOM_FACCION']; ?></label>
                        <input type="text" name="nombre_equipo" id="edit_equipo" class="terminal-input">
                    </div>
                    <div>
                        <label class="tactical-label"><?php echo $txt['STAFF_DASHBOARD']['LBL_ID_ACCESO']; ?></label>
                        <input type="text" name="username" id="edit_username" class="terminal-input text-yellow-500/40" readonly>
                    </div>
                </div>

                <div class="mt-8 bg-[#0a0a0a] p-5 border border-white/5 shadow-inner">
                    <label class="tactical-label text-blue-400"><?php echo $txt['STAFF_DASHBOARD']['BTN_CONF_PAISES']; ?></label>
                    <input type="hidden" name="naciones_activas" id="edit_naciones">
                    
                    <div class="flex flex-wrap gap-2 mt-3" id="contenedor_naciones">
                        <?php foreach($todas_las_naciones as $nacion): ?>
                            <button type="button" 
                                    class="btn-nacion-toggle" 
                                    data-nacion="<?php echo htmlspecialchars($nacion); ?>"
                                    onclick="toggleNacion(this)">
                                <?php echo htmlspecialchars($nacion); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-8">
                    <div>
                        <label class="tactical-label"><?php echo $txt['STAFF_DASHBOARD']['LBL_CAMBIAR_PASS']; ?></label>
                        <div class="relative">
                            <input type="password" name="password" id="pass_input" placeholder="<?php echo $txt['STAFF_DASHBOARD']['PH_NUEVA_CLAVE']; ?>" class="terminal-input pr-10">
                            <button type="button" onclick="togglePass()" class="absolute right-3 top-3 text-gray-500">👁️</button>
                        </div>
                    </div>
                    <div>
                        <label class="tactical-label"><?php echo $txt['STAFF_DASHBOARD']['LBL_URL_INSIGNIA']; ?></label>
                        <div class="flex gap-2">
                            <input type="text" name="bandera_url" id="edit_img" class="terminal-input flex-1 text-[10px]">
                            <button type="button" onclick="window.open('../' + document.getElementById('edit_img').value, '_blank')" class="bg-blue-900/20 text-blue-400 px-4 border border-blue-500/20 text-[10px] font-black uppercase"><?php echo $txt['STAFF_DASHBOARD']['BTN_VER']; ?></button>
                            <button type="button" onclick="borrarBanderaStaff()" class="bg-red-900/20 text-red-500 px-4 border border-red-900/50 text-[10px] font-black uppercase hover:bg-red-600 hover:text-white transition"><?php echo $txt['STAFF_DASHBOARD']['BTN_ELIMINAR']; ?></button>
                        </div>
                    </div>
                </div>

                <div class="bg-black/60 p-6 border border-white/5">
                    <span class="tactical-label !text-white mb-4 block"><?php echo $txt['STAFF_DASHBOARD']['LBL_RECURSOS_OP']; ?></span>
                    <div class="grid grid-cols-3 gap-6">
                        <div><label class="stat-label text-green-700"><?php echo $txt['STAFF_TIENDA']['LBL_CASH']; ?></label><input type="number" name="dinero" id="edit_money" class="terminal-input !text-green-500 font-black"></div>
                        <div><label class="stat-label text-gray-500"><?php echo $txt['STAFF_TIENDA']['LBL_STEEL']; ?></label><input type="number" name="acero" id="edit_steel" class="terminal-input font-black"></div>
                        <div><label class="stat-label text-yellow-700"><?php echo $txt['STAFF_TIENDA']['LBL_FUEL']; ?></label><input type="number" name="petroleo" id="edit_oil" class="terminal-input !text-yellow-500 font-black"></div>
                    </div>
                </div>

                <div class="flex gap-4 pt-6">
                    <button type="submit" class="btn-m flex-1 !py-5 uppercase font-black bg-yellow-600 text-black hover:bg-yellow-500 tracking-[0.2em]"><?php echo $txt['STAFF_DASHBOARD']['BTN_GRABAR']; ?></button>
                    <button type="button" onclick="cerrarModal('modalEdit')" class="flex-1 !py-5 border border-white/10 text-gray-500 font-black uppercase text-[10px]"><?php echo $txt['STAFF_DASHBOARD']['BTN_ABORTAR']; ?></button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalImpacto" class="hidden fixed inset-0 bg-black/95 z-[200] flex items-center justify-center p-4">
        <div class="m-panel w-full max-w-md glass-panel p-10">
            <h2 class="text-blue-400 font-black text-center text-[10px] tracking-[0.4em] uppercase mb-10"><?php echo $txt['STAFF_DASHBOARD']['MODAL_IMP_TIT']; ?></h2>
            <div class="text-center mb-10">
                <span id="imp_faccion" class="tactical-label mb-2 block"></span>
                <span id="imp_item" class="text-white font-black text-2xl uppercase tracking-tighter"></span>
            </div>
            <div class="space-y-4 mb-10 font-mono">
                <div class="flex justify-between items-center bg-white/5 p-3"><span class="stat-label !mb-0"><?php echo $txt['STAFF_DASHBOARD']['LBL_CASH']; ?></span><div class="flex gap-2 text-xs"><span id="cash_old"></span><span class="text-green-500">→</span><span id="cash_new" class="text-green-500"></span></div></div>
                <div class="flex justify-between items-center bg-white/5 p-3"><span class="stat-label !mb-0"><?php echo $txt['STAFF_DASHBOARD']['LBL_STEEL']; ?></span><div class="flex gap-2 text-xs"><span id="steel_old"></span><span class="text-white">→</span><span id="steel_new" class="text-white"></span></div></div>
                <div class="flex justify-between items-center bg-white/5 p-3"><span class="stat-label !mb-0"><?php echo $txt['STAFF_DASHBOARD']['LBL_FUEL']; ?></span><div class="flex gap-2 text-xs"><span id="oil_old"></span><span class="text-yellow-500">→</span><span id="oil_new" class="text-yellow-500"></span></div></div>
            </div>
            <form action="../logic/gestionar_reembolso_staff.php" method="POST" class="grid grid-cols-2 gap-4">
                <input type="hidden" name="solicitud_id" id="imp_id">
                <button name="accion" value="aprobar" class="bg-green-600 text-black py-4 font-black uppercase text-[10px]"><?php echo $txt['STAFF_DASHBOARD']['BTN_AUTORIZAR']; ?></button>
                <button name="accion" value="rechazar" class="bg-red-900/20 text-red-500 border border-red-900 py-4 font-black uppercase text-[10px]"><?php echo $txt['STAFF_DASHBOARD']['BTN_RECHAZAR']; ?></button>
            </form>
        </div>
    </div>

    <div id="modalStaffBorrar" class="hidden fixed inset-0 bg-black/98 z-[300] flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="m-panel w-full max-w-sm border-red-600 bg-[#0a0a0a] p-10 text-center relative shadow-2xl">
            <div class="text-red-600 text-5xl mb-6">⚠️</div>
            <h2 class="text-white font-black uppercase tracking-[0.2em] mb-4"><?php echo $txt['STAFF_DASHBOARD']['MODAL_CENSURA_TIT']; ?></h2>
            <p class="text-gray-400 text-[10px] font-bold leading-relaxed mb-10 uppercase">
                <?php echo $txt['STAFF_DASHBOARD']['MSG_CONFIRM_CENSURA']; ?>
            </p>
            <div class="flex flex-col gap-3">
                <button onclick="ejecutarBorradoStaff()" class="bg-red-600 text-black w-full py-4 font-black uppercase text-[11px] hover:bg-red-500 transition"><?php echo $txt['STAFF_DASHBOARD']['BTN_CONF_CENSURA']; ?></button>
                <button onclick="cerrarModal('modalStaffBorrar')" class="text-gray-500 font-black uppercase text-[9px] hover:text-white transition"><?php echo $txt['STAFF_DASHBOARD']['BTN_ABORTAR']; ?></button>
            </div>
        </div>
    </div>

    <div id="modalNuke" class="hidden fixed inset-0 bg-black/98 z-[500] flex items-center justify-center p-4 backdrop-blur-md">
        <div class="m-panel w-full max-w-lg border-red-600 bg-[#0a0a0a] p-10 text-center relative shadow-2xl">
            <div class="text-red-600 text-6xl mb-6 animate-pulse">☢️</div>
            
            <h2 class="text-white font-black uppercase tracking-[0.3em] text-2xl mb-2 font-['Cinzel']">
                <?php echo $txt['STAFF_DASHBOARD']['MODAL_NUKE_TITULO']; ?>
            </h2>
            <p class="text-red-500 text-[10px] font-black uppercase mb-6 tracking-widest">
                <?php echo $txt['STAFF_DASHBOARD']['NUKE_DESC']; ?>
            </p>
            
            <form action="../logic/nuke_reboot.php" method="POST" id="formNuke">
                <div class="bg-red-900/10 border border-red-900/30 p-6 mb-8 text-center">
                    <label class="text-white font-black uppercase text-[10px] block mb-4 tracking-widest">
                        <?php echo $txt['STAFF_DASHBOARD']['NUKE_INSTRUCCION']; ?>
                    </label>
                    
                    <input type="text" 
                        id="confirm_word" 
                        name="confirm_word" 
                        autocomplete="off"
                        oninput="validarNuke(this.value)"
                        class="terminal-input !text-center !text-2xl !border-red-900 !text-red-500 uppercase" 
                        placeholder="...">
                </div>

                <div class="flex flex-col gap-4">
                    <button type="submit" 
                            id="btnNukeFinal" 
                            disabled 
                            class="w-full bg-gray-800 text-gray-500 py-5 font-black uppercase text-[12px] transition tracking-[0.4em] cursor-not-allowed">
                        <?php echo $txt['STAFF_DASHBOARD']['BTN_ACTIVAR_NUKE']; ?>
                    </button>
                    
                    <button type="button" onclick="cerrarModal('modalNuke')" class="text-gray-500 font-black uppercase text-[9px] hover:text-white transition">
                        CANCELAR
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalRevertir" class="hidden fixed inset-0 bg-black/98 z-[400] flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="m-panel w-full max-w-lg border-red-600 bg-[#0a0a0a] p-8 relative shadow-2xl">
            <button onclick="cerrarModal('modalRevertir')" class="absolute top-4 right-4 text-gray-500 hover:text-white text-xl">&times;</button>
            <div class="flex items-center gap-3 mb-6 border-b border-red-900/30 pb-4">
                <span class="text-red-600 text-3xl">⚠️</span>
                <div>
                    <h2 class="text-white font-black uppercase tracking-[0.2em] text-lg font-['Cinzel']">ANULAR TRANSACCIÓN</h2>
                    <span class="text-gray-500 text-[9px] font-bold tracking-widest uppercase">Protocolo de Reembolso Selectivo</span>
                </div>
            </div>

            <div class="bg-black/50 p-4 border border-white/5 mb-6 text-center">
                <span class="text-blue-400 font-black text-xs uppercase" id="rev_ofertante"></span>
                <span class="text-gray-600 mx-2">VS</span>
                <span class="text-yellow-500 font-black text-xs uppercase" id="rev_receptor"></span>
            </div>

            <form action="../logic/revertir_tradeo_staff.php" method="POST" id="formRevertir">
                <input type="hidden" name="tradeo_id" id="rev_tradeo_id">
                <input type="hidden" name="id_ofertante" id="rev_id_ofertante">
                <input type="hidden" name="id_receptor" id="rev_id_receptor">

                <p class="text-gray-400 text-[10px] uppercase font-bold mb-4">Seleccione qué elementos desea revertir a sus dueños originales:</p>

                <div class="space-y-3 mb-8">
                    <label id="lbl_rev_vehiculo" class="flex items-center p-3 border border-gray-800 hover:border-red-900 bg-[#111] cursor-pointer transition group hidden">
                        <input type="checkbox" name="rev_vehiculo" value="1" class="mr-3 w-4 h-4 accent-red-600 bg-black border-gray-700">
                        <div class="flex flex-col">
                            <span class="text-white text-[10px] font-black uppercase">RECUPERAR VEHÍCULO</span>
                            <span class="text-gray-500 text-[9px]" id="txt_rev_vehiculo"></span>
                        </div>
                    </label>

                    <label id="lbl_rev_enviados" class="flex items-center p-3 border border-gray-800 hover:border-red-900 bg-[#111] cursor-pointer transition group hidden">
                        <input type="checkbox" name="rev_enviados" value="1" class="mr-3 w-4 h-4 accent-red-600 bg-black border-gray-700">
                        <div class="flex flex-col">
                            <span class="text-white text-[10px] font-black uppercase">REVERTIR OFERTA MONETARIA</span>
                            <span class="text-gray-500 text-[9px]" id="txt_rev_enviados"></span>
                        </div>
                    </label>

                    <label id="lbl_rev_cobrados" class="flex items-center p-3 border border-gray-800 hover:border-red-900 bg-[#111] cursor-pointer transition group hidden">
                        <input type="checkbox" name="rev_cobrados" value="1" class="mr-3 w-4 h-4 accent-red-600 bg-black border-gray-700">
                        <div class="flex flex-col">
                            <span class="text-white text-[10px] font-black uppercase">REVERTIR COBRO (PAGO RECIBIDO)</span>
                            <span class="text-gray-500 text-[9px]" id="txt_rev_cobrados"></span>
                        </div>
                    </label>
                </div>

                <button type="submit" class="w-full bg-red-600 text-black py-4 font-black uppercase text-[11px] hover:bg-red-500 transition shadow-[0_0_20px_rgba(220,38,38,0.2)] tracking-[0.2em]">
                    EJECUTAR REEMBOLSO SELECTIVO
                </button>
            </form>
        </div>
    </div>

    <script>
        function togglePass() { const i = document.getElementById('pass_input'); i.type = i.type === 'password' ? 'text' : 'password'; }
        
        function abrirModalEditar(e) {
            document.getElementById('edit_id').value = e.id;
            document.getElementById('edit_equipo').value = e.nombre_equipo;
            document.getElementById('edit_username').value = e.username; 
            document.getElementById('edit_img').value = e.bandera_url;
            
            // --- LÓGICA DE ILUMINACIÓN DE NACIONES ---
            document.getElementById('edit_naciones').value = e.naciones_activas || ''; 
            
            // Creamos un array limpio con las naciones que el equipo YA tiene
            const nacionesEquipo = e.naciones_activas ? e.naciones_activas.split(',').map(n => n.trim()) : [];
            
            // Recorremos TODOS los 10 botones del modal
            document.querySelectorAll('.btn-nacion-toggle').forEach(btn => {
                const nombreNacion = btn.getAttribute('data-nacion');
                // Si el equipo tiene esta nación, encendemos el botón. Si no, lo apagamos.
                if (nacionesEquipo.includes(nombreNacion)) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
            // -----------------------------------------

            document.getElementById('edit_money').value = e.dinero;
            document.getElementById('edit_steel').value = e.acero;
            document.getElementById('edit_oil').value = e.petroleo;
            document.getElementById('modalEdit').classList.remove('hidden');
            document.body.classList.add('modal-active');
        }

        // FUNCIÓN CUANDO EL STAFF HACE CLIC EN UN BOTÓN DE PAÍS
        function toggleNacion(btn) {
            // Cambia visualmente el botón (lo enciende o lo apaga)
            btn.classList.toggle('active');
            
            // Recolectamos los nombres de TODOS los botones que quedaron encendidos
            const activas = [];
            document.querySelectorAll('.btn-nacion-toggle.active').forEach(b => {
                activas.push(b.getAttribute('data-nacion'));
            });
            
            // Juntamos los nombres con comas y los metemos al input oculto para enviarlos por PHP
            document.getElementById('edit_naciones').value = activas.join(', ');
        }

        function abrirModalImpacto(s) {
            document.getElementById('imp_id').value = s.id;
            document.getElementById('imp_faccion').innerText = s.nombre_equipo;
            document.getElementById('imp_item').innerText = s.nombre_vehiculo + " X" + s.cantidad;
            document.getElementById('cash_old').innerText = "$"+parseInt(s.cash_actual).toLocaleString();
            document.getElementById('cash_new').innerText = "$"+(parseInt(s.cash_actual)+parseInt(s.dinero_total)).toLocaleString();
            document.getElementById('steel_old').innerText = parseInt(s.steel_actual)+"T";
            document.getElementById('steel_new').innerText = (parseInt(s.steel_actual)+parseInt(s.acero_total))+"T";
            document.getElementById('oil_old').innerText = parseInt(s.fuel_actual)+"L";
            document.getElementById('oil_new').innerText = (parseInt(s.fuel_actual)+parseInt(s.petroleo_total))+"L";
            document.getElementById('modalImpacto').classList.remove('hidden');
            document.body.classList.add('modal-active');
        }
        
        function abrirModal(id) { 
            document.getElementById(id).classList.remove('hidden'); 
            document.body.classList.add('modal-active'); 
        }
        
        function cerrarModal(id) { 
            document.getElementById(id).classList.add('hidden'); 
            document.body.classList.remove('modal-active'); 
            
            // FIX ISSUE #30: Protocolo de seguridad del Nuke
            if (id === 'modalNuke') {
                const inputNuke = document.getElementById('confirm_word');
                if (inputNuke) {
                    inputNuke.value = ''; // Borramos la palabra
                    validarNuke('');      // Volvemos a bloquear el botón rojo
                }
            }
        }

        function borrarBanderaStaff() {
            // Abrimos el modal de confirmación
            document.getElementById('modalStaffBorrar').classList.remove('hidden');
        }

        function ejecutarBorradoStaff() {
            // Obtenemos el ID de la cuenta que se está editando actualmente en el modalEdit
            const idCuenta = document.getElementById('edit_id').value;

            const f = document.createElement('form');
            f.method = 'POST';
            f.action = '../logic/actualizar_equipo_staff.php';
            
            // Señal específica para que el PHP sepa que solo debe borrar la bandera
            const i_id = document.createElement('input');
            i_id.type = 'hidden'; i_id.name = 'id'; i_id.value = idCuenta;
            
            const i_task = document.createElement('input');
            i_task.type = 'hidden'; i_task.name = 'solo_borrar_bandera_staff'; i_task.value = '1';
            
            f.appendChild(i_id);
            f.appendChild(i_task);
            document.body.appendChild(f);
            f.submit();
        }

        // Función para desbloquear el botón solo si escriben REINICIAR
        function validarNuke(valor) {
            const btn = document.getElementById('btnNukeFinal');
            if(valor.toUpperCase() === 'REINICIAR') {
                btn.disabled = false;
                btn.classList.remove('bg-gray-800', 'text-gray-500', 'cursor-not-allowed');
                btn.classList.add('bg-red-600', 'text-black', 'hover:bg-red-500', 'shadow-lg');
            } else {
                btn.disabled = true;
                btn.classList.add('bg-gray-800', 'text-gray-500', 'cursor-not-allowed');
                btn.classList.remove('bg-red-600', 'text-black', 'hover:bg-red-500', 'shadow-lg');
            }
        }

        function abrirModalRevertir(t) {
            document.getElementById('rev_tradeo_id').value = t.id;
            document.getElementById('rev_id_ofertante').value = t.ofertante_id;
            document.getElementById('rev_id_receptor').value = t.receptor_id;
            
            document.getElementById('rev_ofertante').innerText = t.ofertante;
            document.getElementById('rev_receptor').innerText = t.receptor;

            // 1. Check de Vehículo (Si hubo vehículo involucrado)
            const lblVeh = document.getElementById('lbl_rev_vehiculo');
            if (t.vehiculo_ofrecido_id && t.vehiculo_ofrecido_id > 0) {
                lblVeh.classList.remove('hidden');
                document.getElementById('txt_rev_vehiculo').innerText = `Restar x${t.cantidad_ofrecida} ${t.nombre_vehiculo} a ${t.receptor} y dárselo a ${t.ofertante}`;
            } else {
                lblVeh.classList.add('hidden');
            }

            // 2. Check de Oferta (Lo que dio el Ofertante al inicio)
            const lblEnv = document.getElementById('lbl_rev_enviados');
            if (t.ofrece_dinero > 0 || t.ofrece_acero > 0 || t.ofrece_petroleo > 0) {
                lblEnv.classList.remove('hidden');
                document.getElementById('txt_rev_enviados').innerText = `Restar recursos a ${t.receptor} y devolver a ${t.ofertante}`;
            } else {
                lblEnv.classList.add('hidden');
            }

            // 3. Check de Cobro (Lo que pagó el Receptor)
            const lblCob = document.getElementById('lbl_rev_cobrados');
            if (t.pide_dinero > 0 || t.pide_acero > 0 || t.pide_petroleo > 0) {
                lblCob.classList.remove('hidden');
                document.getElementById('txt_rev_cobrados').innerText = `Restar pago a ${t.ofertante} y devolver dinero/recursos a ${t.receptor}`;
            } else {
                lblCob.classList.add('hidden');
            }

            // Limpiamos los checks antes de abrir
            document.getElementById('formRevertir').reset();
            abrirModal('modalRevertir');
        }
    </script>
</body>
</html>