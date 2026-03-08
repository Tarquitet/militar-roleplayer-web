<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') { header("Location: ../login.php"); exit(); }
require_once '../config/conexion.php';
$root_path = "../";
$txt = require '../config/textos.php';
$equipo_id = (int)$_GET['id'];

try {
    // 1. Datos del equipo
    $stmt_eq = $pdo->prepare("SELECT nombre_equipo, dinero, acero, petroleo FROM cuentas WHERE id = :id");
    $stmt_eq->execute([':id' => $equipo_id]);
    $equipo = $stmt_eq->fetch(PDO::FETCH_ASSOC);

    // 2. Inteligencia Global: ¿Cuántos grupos tienen cada patente?
    $stmt_p_count = $pdo->query("SELECT catalogo_id, COUNT(*) as total FROM planos_desbloqueados GROUP BY catalogo_id");
    $conteo_patentes = $stmt_p_count->fetchAll(PDO::FETCH_KEY_PAIR);

    // 3. Patentes Pagadas
    $stmt_p = $pdo->prepare("SELECT p.id as plano_id, c.* FROM planos_desbloqueados p JOIN catalogo_tienda c ON p.catalogo_id = c.id WHERE p.cuenta_id = :id");
    $stmt_p->execute([':id' => $equipo_id]);
    $planos_pagados = $stmt_p->fetchAll(PDO::FETCH_ASSOC);

    // 4. Inventario Físico
    $stmt_inv = $pdo->prepare("SELECT i.id as inv_id, i.cantidad as stock_actual, c.* FROM inventario i JOIN catalogo_tienda c ON i.catalogo_id = c.id WHERE i.cuenta_id = :id");
    $stmt_inv->execute([':id' => $equipo_id]);
    $inventario = $stmt_inv->fetchAll(PDO::FETCH_ASSOC);

    // 5. Flotas (Lista Dinámica)
    $stmt_f = $pdo->prepare("SELECT * FROM flotas WHERE cuenta_id = :id ORDER BY slot ASC");
    $stmt_f->execute([':id' => $equipo_id]);
    $flotas_listado = $stmt_f->fetchAll(PDO::FETCH_ASSOC);

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
        .btn-close-modal { position: absolute; top: 15px; right: 15px; width: 32px; height: 32px; background: #222; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 1px solid #333; font-size: 18px; }
        .ownership-tag { background: rgba(59, 130, 246, 0.2); color: #60a5fa; font-size: 8px; font-weight: 900; padding: 2px 6px; border: 1px solid rgba(59, 130, 246, 0.3); }
        
        /* TU ESTILO DE ERROR MANTENIDO */
        .input-error { color: #ff0000 !important; border-color: #ff0000 !important; }
    </style>
</head>
<body class="bg-[#0a0b08] text-[var(--text-main)] min-h-screen pb-20">
    <?php include '../includes/nav_staff.php'; ?>

    <main class="p-8 max-w-[1400px] mx-auto">
        <div class="mb-12 flex justify-between items-center">
            <a href="staff_dashboard.php" class="btn-m !bg-none !border-white/10 !text-gray-500 hover:!text-[var(--aoe-gold)] !py-2 !px-6 text-[10px] uppercase font-black transition">⬅️ VOLVER</a>
            <div class="text-right">
                <span class="stat-label">FONDOS DE LA FACCIÓN:</span>
                <div class="flex gap-6 mt-1 font-black">
                    <span class="text-green-500">$<?php echo number_format($equipo['dinero']); ?></span>
                    <span class="text-white"><?php echo number_format($equipo['acero']); ?>T</span>
                    <span class="text-yellow-500"><?php echo number_format($equipo['petroleo']); ?>L</span>
                </div>
            </div>
        </div>

        <h1 class="text-3xl font-black mb-12 uppercase italic text-white">INSPECCIONANDO: <span class="text-[var(--aoe-gold)]"><?php echo htmlspecialchars($equipo['nombre_equipo']); ?></span></h1>

        <div class="mb-20">
            <h2 class="text-[10px] font-black uppercase tracking-[0.4em] mb-8 text-[#c5a059] border-b border-yellow-900/30 pb-2">⚓ FLOTAS OPERATIVAS</h2>
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
            <h2 class="text-[10px] font-black uppercase tracking-[0.4em] mb-8 text-blue-400 border-b border-blue-900/30 pb-2">📜 PATENTES TECNOLÓGICAS</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-6">
                <?php foreach($planos_pagados as $p): 
                    $p_json = htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8');
                    $otros = ($conteo_patentes[$p['id']] ?? 1) - 1;
                ?>
                    <div class="m-panel !p-0 overflow-hidden bg-[#0d0e0a] border border-white/5 group">
                        <div class="h-28 bg-black relative">
                            <img src="../<?php echo $p['imagen_url']; ?>" class="w-full h-full object-cover grayscale opacity-40">
                            <div class="absolute top-2 left-2">
                                <span class="ownership-tag">POSEÍDO POR <?php echo $otros + 1; ?> GRUPOS</span>
                            </div>
                        </div>
                        <div class="p-4 text-center">
                            <span class="block text-[11px] text-white font-black uppercase mb-3"><?php echo htmlspecialchars($p['nombre_vehiculo']); ?></span>
                            <button onclick='abrirModalPatente(<?php echo $p_json; ?>)' class="w-full bg-red-950/20 text-red-500 border border-red-900 text-[9px] py-2 font-black uppercase hover:bg-red-700 transition">PURGAR TECNOLOGÍA</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <h2 class="text-[10px] font-black uppercase tracking-[0.4em] mb-8 text-[var(--aoe-gold)] border-b border-yellow-900/30 pb-2">🏭 ACTIVOS EN HANGAR</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-6">
            <?php foreach($inventario as $i): $i_json = htmlspecialchars(json_encode($i), ENT_QUOTES, 'UTF-8'); ?>
                <div class="m-panel !p-0 overflow-hidden bg-[#0d0e0a] border border-white/5">
                    <div class="h-32 bg-black"><img src="../<?php echo $i['imagen_url']; ?>" class="w-full h-full object-cover"></div>
                    <div class="p-4">
                        <span class="text-[11px] text-white font-black uppercase block mb-3"><?php echo htmlspecialchars($i['nombre_vehiculo']); ?></span>
                        <div class="flex justify-between items-center mb-4"><span class="stat-label">STOCK:</span><span class="text-2xl font-black text-[var(--aoe-gold)] font-['Cinzel']"><?php echo $i['stock_actual']; ?>x</span></div>
                        <button onclick='abrirModalVehiculo(<?php echo $i_json; ?>)' class="w-full bg-red-800 text-white border border-red-600 py-3 text-[10px] font-black uppercase hover:bg-red-500 transition">PURGAR UNIDADES</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <div id="modalPatente" class="hidden fixed inset-0 bg-black/98 z-[200] flex items-center justify-center p-4">
        <div class="m-panel w-full max-w-md glass-panel p-10 border-red-500/30 relative">
            <button onclick="cerrarModal('modalPatente')" class="btn-close-modal">&times;</button>
            <h2 class="text-red-500 font-black text-center text-[10px] uppercase mb-10 tracking-[0.3em]">PURGA ESTRATÉGICA DE PATENTE</h2>
            <div class="text-center mb-8"><span id="p_nombre" class="text-white font-black text-3xl uppercase font-['Cinzel']"></span></div>
            
            <form action="../logic/procesar_reembolso_staff.php" method="POST">
                <input type="hidden" name="tipo" value="plano"><input type="hidden" name="target_id" id="p_target_id"><input type="hidden" name="equipo_id" value="<?php echo $equipo_id; ?>">
                
                <div class="bg-black/40 p-4 border border-white/10 mb-8">
                    <label class="flex items-center gap-4 cursor-pointer">
                        <input type="checkbox" name="reembolsar" value="1" id="p_check" checked onchange="togglePRef()" class="w-5 h-5 accent-green-500">
                        <span class="text-[10px] font-black text-gray-300 uppercase">¿REALIZAR REEMBOLSO DE RECURSOS?</span>
                    </label>
                </div>

                <div id="p_preview" class="space-y-3 mb-10">
                    <div class="flex justify-between items-center text-[10px] font-black bg-white/5 p-3">
                        <span class="text-gray-500">PROYECCIÓN SALDO:</span>
                        <div class="flex gap-2"><span id="p_cash_old" class="text-gray-400"></span><span class="text-green-500">+$<span id="p_cash_add"></span></span><span class="text-white">→</span><span id="p_cash_new" class="text-green-500"></span></div>
                    </div>
                </div>

                <button type="submit" class="bg-red-600 text-black w-full py-5 font-black uppercase text-[11px] tracking-widest hover:bg-red-400 transition">EJECUTAR PURGA</button>
            </form>
        </div>
    </div>

    <div id="modalVehiculo" class="hidden fixed inset-0 bg-black/98 z-[200] flex items-center justify-center p-4">
        <div class="m-panel w-full max-w-lg glass-panel p-10 border-red-500/30 relative">
            <button onclick="cerrarModal('modalVehiculo')" class="btn-close-modal">&times;</button>
            <h2 class="text-red-500 font-black text-center text-[10px] uppercase mb-10 tracking-[0.3em]">PROTOCOLO DE REINTEGRO TÁCTICO</h2>
            <div class="bg-black/40 p-4 border border-white/5 mb-8 text-center">
                <span id="v_nombre" class="text-white font-black text-2xl uppercase font-['Cinzel'] block"></span>
                <span class="text-[9px] text-gray-500 uppercase tracking-widest">STOCK DISPONIBLE: <span id="v_max_txt" class="text-white font-bold"></span></span>
            </div>
            
            <form action="../logic/procesar_reembolso_staff.php" method="POST">
                <input type="hidden" name="tipo" value="vehiculo"><input type="hidden" name="target_id" id="v_target_id"><input type="hidden" name="equipo_id" value="<?php echo $equipo_id; ?>"><input type="hidden" name="cantidad_final" id="v_qty_form">

                <div class="mb-6">
                    <label class="stat-label block mb-2">CANTIDAD A PURGAR</label>
                    <input type="number" id="v_qty" value="1" min="1" oninput="calcVImpact()" class="terminal-input text-2xl font-black text-red-500">
                    <div id="v_error_msg" class="text-red-500 text-[10px] font-black uppercase text-center mt-2 hidden animate-pulse">⚠️ ALERTA: LA CANTIDAD SUPERA EL STOCK DISPONIBLE</div>
                </div>

                <div class="bg-black/40 p-4 border border-white/10 mb-8">
                    <label class="flex items-center gap-4 cursor-pointer">
                        <input type="checkbox" name="reembolsar" value="1" id="v_check" checked onchange="calcVImpact()" class="w-5 h-5 accent-green-500">
                        <span class="text-[10px] font-black text-gray-300 uppercase">¿DEVOLVER RECURSOS A LA FACCIÓN?</span>
                    </label>
                </div>

                <div id="v_preview" class="space-y-2 mb-10 text-[10px] font-mono font-black">
                    <div class="flex justify-between p-2 bg-white/5 border border-white/5"><span>CASH</span><div class="flex gap-2"><span id="v_d_old" class="text-gray-500"></span><span id="v_d_add" class="text-green-500"></span><span class="text-white">→</span><span id="v_d_new" class="text-green-500"></span></div></div>
                    <div class="flex justify-between p-2 bg-white/5 border border-white/5"><span>STEEL</span><div class="flex gap-2"><span id="v_a_old" class="text-gray-500"></span><span id="v_a_add" class="text-white"></span><span class="text-white">→</span><span id="v_a_new" class="text-white"></span></div></div>
                    <div class="flex justify-between p-2 bg-white/5 border border-white/5"><span>FUEL</span><div class="flex gap-2"><span id="v_p_old" class="text-gray-500"></span><span id="v_p_add" class="text-yellow-500"></span><span class="text-white">→</span><span id="v_p_new" class="text-yellow-500"></span></div></div>
                </div>

                <button type="submit" id="btn_v_confirm" class="bg-red-600 text-black w-full py-5 font-black uppercase text-[11px] hover:bg-red-400 transition">CONFIRMAR PURGA</button>
            </form>
        </div>
    </div>

    <div id="modalDestroyFlota" class="hidden fixed inset-0 bg-black/98 z-[300] flex items-center justify-center p-4">
        <div class="m-panel w-full max-w-md border-red-600 bg-[#0a0a0a] p-10 text-center relative">
            <button onclick="cerrarModal('modalDestroyFlota')" class="btn-close-modal">&times;</button>
            <div class="text-red-600 text-5xl mb-6">☢️</div>
            <h2 class="text-white font-black uppercase tracking-[0.2em] mb-4">ANIQUILACIÓN DE FLOTA</h2>
            <p class="text-gray-400 text-xs font-bold leading-relaxed mb-10 uppercase">Confirmar destrucción de la Flota #<span id="txt_slot"></span>.</p>
            <form action="../logic/borrar_flota_staff.php" method="POST">
                <input type="hidden" name="flota_id" id="hid_flota_id"><input type="hidden" name="lider_id" value="<?php echo $equipo_id; ?>">
                <button type="submit" class="bg-red-600 text-black w-full py-4 font-black uppercase text-[11px] hover:bg-red-400 transition">EJECUTAR</button>
            </form>
        </div>
    </div>

    <script>
        const resEq = { dinero: <?php echo $equipo['dinero']; ?>, acero: <?php echo $equipo['acero']; ?>, petroleo: <?php echo $equipo['petroleo']; ?> };
        let itemSel = null;

        function abrirModalPatente(p) {
            itemSel = p; document.getElementById('p_target_id').value = p.plano_id;
            document.getElementById('p_nombre').innerText = p.nombre_vehiculo;
            togglePRef(); abrirModal('modalPatente');
        }

        function togglePRef() {
            const check = document.getElementById('p_check').checked;
            const cost = check ? parseInt(itemSel.costo_dinero) : 0;
            document.getElementById('p_cash_old').innerText = "$" + resEq.dinero.toLocaleString();
            document.getElementById('p_cash_add').innerText = (check ? "+" : "") + "$" + cost.toLocaleString();
            document.getElementById('p_cash_new').innerText = "$" + (resEq.dinero + cost).toLocaleString();
            document.getElementById('p_preview').style.opacity = check ? "1" : "0.3";
        }

        function abrirModalVehiculo(i) {
            itemSel = i; 
            document.getElementById('v_target_id').value = i.inv_id;
            document.getElementById('v_nombre').innerText = i.nombre_vehiculo;
            document.getElementById('v_max_txt').innerText = i.stock_actual;
            document.getElementById('v_qty').max = i.stock_actual; 
            document.getElementById('v_qty').value = 1;
            calcVImpact(); 
            abrirModal('modalVehiculo');
        }

        function calcVImpact() {
            const q = parseInt(document.getElementById('v_qty').value) || 0;
            const max = parseInt(itemSel.stock_actual);
            const check = document.getElementById('v_check').checked;
            const btn = document.getElementById('btn_v_confirm');
            const err = document.getElementById('v_error_msg');
            
            // VALIDACIÓN: BLOQUEO SI SE EXCEDE EL STOCK
            if (q > max || q <= 0) {
                document.getElementById('v_qty').classList.add('input-error');
                btn.disabled = true; btn.style.opacity = "0.3";
                err.classList.remove('hidden');
            } else {
                document.getElementById('v_qty').classList.remove('input-error');
                btn.disabled = false; btn.style.opacity = "1";
                err.classList.add('hidden');
            }

            document.getElementById('v_qty_form').value = q;
            
            const addD = check ? q * parseInt(itemSel.costo_dinero) : 0;
            const addA = check ? q * parseInt(itemSel.costo_acero) : 0;
            const addP = check ? q * parseInt(itemSel.costo_petroleo) : 0;

            const up = (id, old, add, sym) => {
                document.getElementById('v_'+id+'_old').innerText = old.toLocaleString() + sym;
                document.getElementById('v_'+id+'_add').innerText = (add > 0 ? "+" : "") + add.toLocaleString() + sym;
                document.getElementById('v_'+id+'_new').innerText = (old + add).toLocaleString() + sym;
            };
            
            up('d', resEq.dinero, addD, '$'); 
            up('a', resEq.acero, addA, 'T'); 
            up('p', resEq.petroleo, addP, 'L');
            
            document.getElementById('v_preview').style.opacity = check ? "1" : "0.3";
        }

        function confirmarBorradoFlota(id, slot) { document.getElementById('hid_flota_id').value = id; document.getElementById('txt_slot').innerText = slot; abrirModal('modalDestroyFlota'); }
        function abrirModal(id) { document.getElementById(id).classList.remove('hidden'); document.body.classList.add('modal-active'); }
        function cerrarModal(id) { document.getElementById(id).classList.add('hidden'); document.body.classList.remove('modal-active'); }
    </script>
</body>
</html>