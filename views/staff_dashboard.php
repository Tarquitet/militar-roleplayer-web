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
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach($equipos as $e): ?>
                <div class="m-panel !p-0 overflow-hidden bg-[#0d0e0a] border border-white/5 hover:border-yellow-500/30 transition-all group">
                    <div class="p-6">
                        <div class="flex justify-between items-start mb-6">
                            <div class="flex gap-4">
                                <div class="w-14 h-14 bg-black border border-white/10 rounded overflow-hidden">
                                    <img src="../<?php echo $e['bandera_url']; ?>" class="w-full h-full object-cover grayscale-[0.5] group-hover:grayscale-0">
                                </div>
                                <div>
                                    <h3 class="text-white font-black text-lg uppercase leading-none mb-2 font-['Cinzel']"><?php echo htmlspecialchars($e['nombre_equipo']); ?></h3>
                                    <p class="text-[10px] text-gray-500 font-bold uppercase"><?php echo $txt['STAFF_DASHBOARD']['LBL_ID']; ?> <span class="text-white"><?php echo htmlspecialchars($e['username']); ?></span></p>
                                </div>
                            </div>
                            <div class="flex flex-col gap-1">
                                <button onclick='abrirModalEditar(<?php echo json_encode($e); ?>)' class="text-[9px] font-black uppercase text-yellow-500 border border-yellow-500/10 px-3 py-1.5 hover:bg-yellow-500 hover:text-black transition"><?php echo $txt['STAFF_DASHBOARD']['BTN_EDITAR']; ?></button>
                                <a href="staff_ver_inventario.php?id=<?php echo $e['id']; ?>" class="text-[9px] font-black uppercase text-blue-400 border border-blue-500/10 px-3 py-1.5 hover:bg-blue-600 hover:text-white transition text-center"><?php echo $txt['STAFF_DASHBOARD']['BTN_INV']; ?></a>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-4 border-t border-white/5 pt-6 font-['Cinzel']">
                            <div class="text-center"><span class="tactical-label"><?php echo $txt['STAFF_DASHBOARD']['LBL_CASH']; ?></span><span class="text-green-500 text-sm font-black">$<?php echo number_format($e['dinero']); ?></span></div>
                            <div class="text-center border-x border-white/5"><span class="tactical-label"><?php echo $txt['STAFF_DASHBOARD']['LBL_STEEL']; ?></span><span class="text-white text-sm font-black"><?php echo number_format($e['acero']); ?>T</span></div>
                            <div class="text-center"><span class="tactical-label"><?php echo $txt['STAFF_DASHBOARD']['LBL_FUEL']; ?></span><span class="text-yellow-500 text-sm font-black"><?php echo number_format($e['petroleo']); ?>L</span></div>
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

                                <td class="p-4 text-right text-gray-600 font-mono"><?php echo date('d/m H:i', strtotime($h['fecha_creacion'])); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
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

    <script>
        function togglePass() { const i = document.getElementById('pass_input'); i.type = i.type === 'password' ? 'text' : 'password'; }
        function abrirModalEditar(e) {
            document.getElementById('edit_id').value = e.id;
            document.getElementById('edit_equipo').value = e.nombre_equipo;
            document.getElementById('edit_username').value = e.username; 
            document.getElementById('edit_img').value = e.bandera_url;
            document.getElementById('edit_money').value = e.dinero;
            document.getElementById('edit_steel').value = e.acero;
            document.getElementById('edit_oil').value = e.petroleo;
            document.getElementById('modalEdit').classList.remove('hidden');
            document.body.classList.add('modal-active');
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
        function cerrarModal(id) { document.getElementById(id).classList.add('hidden'); document.body.classList.remove('modal-active'); }

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
    </script>
</body>
</html>