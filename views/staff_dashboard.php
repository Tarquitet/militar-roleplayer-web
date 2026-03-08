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

} catch (PDOException $e) { die("FALLO EN ENLACE SATELITAL: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Estado Mayor - Cuartel General</title>
    <?php include '../includes/head.php'; ?>
    <style>
        :root {
            --m-bg: #0a0b08;
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
            <h1 class="text-3xl font-black tracking-tighter text-white uppercase italic font-['Cinzel']">Centro de Mando e Inteligencia</h1>
        </header>

        <section class="mb-20">
            <h2 class="text-[10px] font-black uppercase tracking-[0.4em] mb-8 text-blue-400">📡 SOLICITUDES DE RETORNO</h2>
            <?php if(empty($solicitudes)): ?>
                <div class="m-panel border-dashed border-white/5 text-center py-10 opacity-30">
                    <p class="text-gray-500 text-[10px] uppercase font-black">Sin solicitudes logísticas.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 gap-4">
                    <?php foreach($solicitudes as $s): ?>
                        <div class="m-panel !p-0 overflow-hidden bg-[#11130f] border border-white/5 flex justify-between items-center group">
                            <div class="p-6">
                                <span class="text-[10px] text-blue-500 font-black uppercase mb-1 block"><?php echo htmlspecialchars($s['nombre_equipo']); ?></span>
                                <h3 class="text-white font-black text-xl uppercase tracking-tighter"><?php echo htmlspecialchars($s['nombre_vehiculo']); ?> <span class="text-blue-500 ml-3">x<?php echo $s['cantidad']; ?></span></h3>
                            </div>
                            <button onclick='abrirModalImpacto(<?php echo json_encode($s); ?>)' class="h-full px-12 bg-blue-900/20 hover:bg-blue-600 text-blue-400 hover:text-white font-black text-[11px] uppercase tracking-widest transition-all">ANALIZAR</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <h2 class="text-[10px] font-black uppercase tracking-[0.4em] mb-8 text-[var(--m-gold)]">Expedientes de Facciones</h2>
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
                                    <p class="text-[10px] text-gray-500 font-bold uppercase">ID: <span class="text-white"><?php echo htmlspecialchars($e['username']); ?></span></p>
                                </div>
                            </div>
                            <div class="flex flex-col gap-1">
                                <button onclick='abrirModalEditar(<?php echo json_encode($e); ?>)' class="text-[9px] font-black uppercase text-yellow-500 border border-yellow-500/10 px-3 py-1.5 hover:bg-yellow-500 hover:text-black transition">EDITAR</button>
                                <a href="staff_ver_inventario.php?id=<?php echo $e['id']; ?>" class="text-[9px] font-black uppercase text-blue-400 border border-blue-500/10 px-3 py-1.5 hover:bg-blue-600 hover:text-white transition text-center">INV</a>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-4 border-t border-white/5 pt-6 font-['Cinzel']">
                            <div class="text-center"><span class="tactical-label">Cash</span><span class="text-green-500 text-sm font-black">$<?php echo number_format($e['dinero']); ?></span></div>
                            <div class="text-center border-x border-white/5"><span class="tactical-label">Steel</span><span class="text-white text-sm font-black"><?php echo number_format($e['acero']); ?>T</span></div>
                            <div class="text-center"><span class="tactical-label">Fuel</span><span class="text-yellow-500 text-sm font-black"><?php echo number_format($e['petroleo']); ?>L</span></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <div id="modalEdit" class="hidden fixed inset-0 bg-black/95 z-[200] flex items-center justify-center p-4">
        <div class="m-panel w-full max-w-2xl glass-panel p-10">
            <h2 class="text-2xl font-black text-white uppercase italic mb-10 border-b border-white/5 pb-4">EXPEDIENTE DE INTELIGENCIA</h2>
            <form action="../logic/actualizar_equipo_staff.php" method="POST" class="space-y-8">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="grid grid-cols-2 gap-8">
                    <div>
                        <label class="tactical-label">Nombre de la Facción</label>
                        <input type="text" name="nombre_equipo" id="edit_equipo" class="terminal-input">
                    </div>
                    <div>
                        <label class="tactical-label">ID de Acceso (Usuario)</label>
                        <input type="text" name="username" id="edit_username" class="terminal-input text-yellow-500/40" readonly>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-8">
                    <div>
                        <label class="tactical-label">Cambiar Contraseña</label>
                        <div class="relative">
                            <input type="password" name="password" id="pass_input" placeholder="Nueva clave..." class="terminal-input pr-10">
                            <button type="button" onclick="togglePass()" class="absolute right-3 top-3 text-gray-500">👁️</button>
                        </div>
                    </div>
                    <div>
                        <label class="tactical-label">URL Insignia</label>
                        <div class="flex gap-2">
                            <input type="text" name="bandera_url" id="edit_img" class="terminal-input flex-1 text-[10px]">
                            <button type="button" onclick="window.open(document.getElementById('edit_img').value, '_blank')" class="bg-blue-900/20 text-blue-400 px-4 border border-blue-500/20 text-[10px] font-black uppercase">VER</button>
                        </div>
                    </div>
                </div>

                <div class="bg-black/60 p-6 border border-white/5">
                    <span class="tactical-label !text-white mb-4 block">Recursos Operativos</span>
                    <div class="grid grid-cols-3 gap-6">
                        <div><label class="stat-label text-green-700">CASH</label><input type="number" name="dinero" id="edit_money" class="terminal-input !text-green-500 font-black"></div>
                        <div><label class="stat-label text-gray-500">STEEL</label><input type="number" name="acero" id="edit_steel" class="terminal-input font-black"></div>
                        <div><label class="stat-label text-yellow-700">FUEL</label><input type="number" name="petroleo" id="edit_oil" class="terminal-input !text-yellow-500 font-black"></div>
                    </div>
                </div>

                <div class="flex gap-4 pt-6">
                    <button type="submit" class="btn-m flex-1 !py-5 uppercase font-black bg-yellow-600 text-black hover:bg-yellow-500 tracking-[0.2em]">GRABAR DATOS</button>
                    <button type="button" onclick="cerrarModal('modalEdit')" class="flex-1 !py-5 border border-white/10 text-gray-500 font-black uppercase text-[10px]">ABORTAR</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalImpacto" class="hidden fixed inset-0 bg-black/95 z-[200] flex items-center justify-center p-4">
        <div class="m-panel w-full max-w-md glass-panel p-10">
            <h2 class="text-blue-400 font-black text-center text-[10px] tracking-[0.4em] uppercase mb-10">ANÁLISIS DE IMPACTO</h2>
            <div class="text-center mb-10">
                <span id="imp_faccion" class="tactical-label mb-2 block"></span>
                <span id="imp_item" class="text-white font-black text-2xl uppercase tracking-tighter"></span>
            </div>
            <div class="space-y-4 mb-10 font-mono">
                <div class="flex justify-between items-center bg-white/5 p-3"><span class="stat-label !mb-0">Cash</span><div class="flex gap-2 text-xs"><span id="cash_old"></span><span class="text-green-500">→</span><span id="cash_new" class="text-green-500"></span></div></div>
                <div class="flex justify-between items-center bg-white/5 p-3"><span class="stat-label !mb-0">Steel</span><div class="flex gap-2 text-xs"><span id="steel_old"></span><span class="text-white">→</span><span id="steel_new" class="text-white"></span></div></div>
                <div class="flex justify-between items-center bg-white/5 p-3"><span class="stat-label !mb-0">Fuel</span><div class="flex gap-2 text-xs"><span id="oil_old"></span><span class="text-yellow-500">→</span><span id="oil_new" class="text-yellow-500"></span></div></div>
            </div>
            <form action="../logic/gestionar_reembolso_staff.php" method="POST" class="grid grid-cols-2 gap-4">
                <input type="hidden" name="solicitud_id" id="imp_id">
                <button name="accion" value="aprobar" class="bg-green-600 text-black py-4 font-black uppercase text-[10px]">AUTORIZAR</button>
                <button name="accion" value="rechazar" class="bg-red-900/20 text-red-500 border border-red-900 py-4 font-black uppercase text-[10px]">RECHAZAR</button>
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
    </script>
</body>
</html>