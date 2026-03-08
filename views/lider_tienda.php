<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'lider') {
    header("Location: ../login.php");
    exit();
}
require_once '../config/conexion.php';
$root_path = "../";
$txt = require '../config/textos.php';
$lider_id = $_SESSION['usuario_id'];

try {
    $stmt_u = $pdo->prepare("SELECT dinero, acero, petroleo, naciones_activas FROM cuentas WHERE id = :id");
    $stmt_u->execute([':id' => $lider_id]);
    $user = $stmt_u->fetch(PDO::FETCH_ASSOC);
    
    $naciones_raw = explode(',', $user['naciones_activas'] ?? '');
    $naciones_mando = array_filter(array_map('trim', $naciones_raw));

    $catalogo = [];
    if (!empty($naciones_mando)) {
        $placeholders = str_repeat('?,', count($naciones_mando) - 1) . '?';
        $stmt_cat = $pdo->prepare("SELECT * FROM catalogo_tienda WHERE nacion IN ($placeholders) ORDER BY rango ASC");
        $stmt_cat->execute(array_values($naciones_mando));
        $catalogo = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);
    }

    $stmt_p = $pdo->prepare("SELECT catalogo_id FROM planos_desbloqueados WHERE cuenta_id = :id");
    $stmt_p->execute([':id' => $lider_id]);
    $mis_planos = $stmt_p->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) { die("Error: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title><?php echo $txt['LIDER_TIENDA']['TITULO']; ?></title>
    <?php include '../includes/head.php'; ?>
    <style>
        .modal-active { overflow: hidden; }
        .btn-nacion.active { background-color: var(--dark-olive) !important; color: var(--aoe-gold) !important; border-color: var(--aoe-gold) !important; }
        .img-locked { filter: grayscale(1) brightness(0.3) sepia(1) hue-rotate(150deg); }
    </style>
</head>
<body class="bg-[#0d0e0a] text-[var(--text-main)] min-h-screen pb-20" onload="initTienda()">
    <?php include '../includes/nav_lider.php'; ?>

    <nav class="bg-[#1a1c11] border-b border-[var(--wood-border)] sticky top-0 z-40 shadow-xl">
        <div class="flex px-4 py-2 items-center gap-4">
            <span class="text-[var(--aoe-gold)] text-[10px] font-black uppercase tracking-widest pl-4">LÍNEA DE PRODUCCIÓN:</span>
            <div class="flex gap-2">
                <?php foreach ($naciones_mando as $n): ?>
                    <button onclick="setNacion('<?php echo htmlspecialchars($n); ?>')" data-nav-nacion="<?php echo htmlspecialchars($n); ?>" class="btn-nacion px-4 py-1 text-[10px] font-black uppercase border border-[var(--wood-border)] bg-black/40">
                        <?php echo htmlspecialchars($n); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </nav>

    <div class="m-panel !p-4 !border-t-0 !border-x-0 sticky top-12 z-30 flex justify-between items-center bg-black/80 backdrop-blur-md">
        <div class="max-w-[1600px] w-full mx-auto flex flex-col md:flex-row justify-between items-center px-4 gap-4">
            <div class="flex gap-4">
                <button id="btn_tanque" onclick="setTipo('tanque')" class="btn-m !text-[10px]">TANQUES</button>
                <button id="btn_avion" onclick="setTipo('avion')" class="btn-m !text-[10px] grayscale opacity-70">AVIONES</button>
            </div>
            <div class="flex gap-10 font-black font-['Cinzel'] text-sm">
                <span class="text-green-500">$<?php echo number_format($user['dinero']); ?></span>
                <span class="text-white"><?php echo number_format($user['acero']); ?>T</span>
                <span class="text-yellow-500"><?php echo number_format($user['petroleo']); ?>L</span>
            </div>
        </div>
    </div>

    <main class="p-8 max-w-[1600px] mx-auto">
        <div id="vista_grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-6 mt-6">
            <?php foreach ($catalogo as $item): 
                $tiene_plano = in_array($item['id'], $mis_planos); ?>
                <div class="fila-v flex flex-col bg-[#111] border border-[var(--wood-border)] shadow-2xl relative transition hover:brightness-110" 
                     data-tipo="<?php echo $item['tipo']; ?>" data-nacion="<?php echo htmlspecialchars($item['nacion']); ?>" id="v-<?php echo $item['id']; ?>">
                    
                    <div class="absolute top-0 right-0 bg-black/80 text-[var(--aoe-gold)] font-black text-[10px] px-2 py-1 z-10 border-b border-l border-[var(--wood-border)] font-['Cinzel']">T-<?php echo $item['rango']; ?></div>
                    <div class="h-32 bg-black relative border-b border-[var(--wood-border)]">
                        <img src="../<?php echo $item['imagen_url']; ?>" class="w-full h-full object-cover <?php echo !$tiene_plano ? 'img-locked' : ''; ?>">
                    </div>

                    <div class="p-4 flex-grow">
                        <span class="text-[8px] text-[var(--parchment)] uppercase font-bold tracking-widest"><?php echo htmlspecialchars($item['subtipo']); ?></span>
                        <h3 class="text-sm font-black text-white truncate font-['Cinzel'] uppercase"><?php echo htmlspecialchars($item['nombre_vehiculo']); ?></h3>
                    </div>

                    <div class="info-precios flex justify-between text-xs font-black border-t border-gray-800 pt-3 px-2 tracking-tighter">
                        <div class="flex flex-col items-center">
                            <span class="text-green-500">$<?php echo number_format($item['costo_dinero']); ?></span>
                        </div>
                        <div class="flex flex-col items-center border-x border-gray-800 px-3">
                            <span class="text-white"><?php echo number_format($item['costo_acero']); ?>T</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <span class="text-yellow-500"><?php echo number_format($item['costo_petroleo']); ?>L</span>
                        </div>
                    </div>

                    <div class="p-2 bg-black/60">
                        <?php if($tiene_plano): ?>
                            <div class="flex gap-1">
                                <input type="number" id="qty-<?php echo $item['id']; ?>" value="1" min="1" class="m-input w-14 text-center text-xs font-black">
                                <button onclick="prepararCompraVehiculo(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['nombre_vehiculo']); ?>', <?php echo $item['costo_dinero']; ?>, <?php echo $item['costo_acero']; ?>, <?php echo $item['costo_petroleo']; ?>)" 
                                        class="btn-m flex-1 !py-2 !text-[9px] uppercase font-black tracking-tighter">Adquirir Unidades</button>
                            </div>
                        <?php else: ?>
                            <div class="text-center">
                                <p class="text-[9px] text-blue-400 font-black mb-1 uppercase">PATENTE: <span class="text-green-500 font-black">$<?php echo number_format($item['costo_dinero']); ?></span></p>
                                <button onclick="prepararCompraPlano(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['nombre_vehiculo']); ?>', <?php echo $item['costo_dinero']; ?>)" 
                                        class="btn-m w-full !py-2 !text-[9px] !bg-blue-900/40 !text-blue-300 border-blue-600 hover:!bg-blue-700 transition uppercase font-black tracking-widest">ADQUIRIR PLANO</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <div id="modalConfirm" class="hidden fixed inset-0 bg-black/95 z-[200] flex items-center justify-center p-4">
        <div class="m-panel w-full max-w-sm relative border-[var(--aoe-gold)] border-2 shadow-[0_0_50px_rgba(255,204,0,0.1)]">
            <h3 class="text-[var(--aoe-gold)] font-black text-xs mb-4 tracking-[0.3em] uppercase text-center border-b border-[var(--wood-border)] pb-2">AUTORIZACIÓN DE MANDO</h3>
            <div id="confirmText" class="text-[11px] text-[var(--parchment)] text-center uppercase tracking-widest mb-8 leading-relaxed"></div>
            
            <form id="formConfirm" method="POST" class="flex flex-col gap-3">
                <input type="hidden" name="catalogo_id" id="modal_cat_id">
                <input type="hidden" name="cantidad" id="modal_qty">
                <button type="submit" class="btn-m w-full py-4 text-[10px] font-black uppercase tracking-[0.2em] bg-[var(--dark-olive)] shadow-lg">CONFIRMAR DESPLIEGUE</button>
                <button type="button" onclick="cerrarModal()" class="text-gray-500 text-[9px] font-black uppercase hover:text-white transition tracking-widest">CANCELAR OPERACIÓN</button>
            </form>
        </div>
    </div>

    <script>
        const urlParams = new URLSearchParams(window.location.search);
        let nacActual = urlParams.get('nacion') || '<?php echo $naciones_mando[0] ?? ""; ?>';
        let tipoActual = urlParams.get('tipo') || 'tanque';
        const targetId = urlParams.get('target');

        function initTienda() { 
            if(nacActual) setNacion(nacActual);
            if(tipoActual) setTipo(tipoActual);
            if(targetId) {
                const el = document.getElementById('v-'+targetId);
                if(el) {
                    el.classList.add('animate-pulse', 'border-[var(--aoe-gold)]');
                    setTimeout(() => el.scrollIntoView({ behavior: 'smooth', block: 'center' }), 300);
                }
            }
        }

        function setNacion(n) {
            nacActual = n;
            document.querySelectorAll('.btn-nacion').forEach(b => b.classList.toggle('active', b.dataset.navNacion === n));
            renderTienda();
        }

        function setTipo(t) {
            tipoActual = t;
            ['tanque', 'avion'].forEach(id => {
                const btn = document.getElementById('btn_'+id);
                if(btn) btn.classList.toggle('grayscale', id !== t);
            });
            renderTienda();
        }

        function renderTienda() {
            document.querySelectorAll('.fila-v').forEach(f => {
                const vTipo = f.dataset.tipo;
                const vNac = f.dataset.nacion;
                f.style.display = (vNac === nacActual && vTipo === tipoActual) ? '' : 'none';
            });
        }

        function prepararCompraPlano(id, nombre, costo) {
            document.getElementById('modal_cat_id').value = id;
            document.getElementById('modal_qty').value = 1;
            document.getElementById('formConfirm').action = '../logic/procesar_plano.php';
            document.getElementById('confirmText').innerHTML = `
                <div class="mb-4">¿AUTORIZA EL DESEMBOLSO PARA LA PATENTE DEL <br><span class='text-white font-black text-sm uppercase'>${nombre}</span>?</div>
                <div class="bg-black/50 p-4 border border-blue-900/30">
                    <span class='text-[9px] text-gray-500 font-bold uppercase block mb-1'>COSTO DE INVESTIGACIÓN:</span>
                    <span class='text-green-500 font-black text-lg'>$${costo.toLocaleString()}</span>
                </div>
            `;
            document.getElementById('modalConfirm').classList.remove('hidden');
            document.body.classList.add('modal-active');
        }

        function prepararCompraVehiculo(id, nombre, d, a, p) {
            const qtyInput = document.getElementById('qty-' + id);
            const qty = qtyInput ? parseInt(qtyInput.value) : 1;
            
            const totalD = (d * qty).toLocaleString();
            const totalA = (a * qty).toLocaleString();
            const totalP = (p * qty).toLocaleString();

            document.getElementById('modal_cat_id').value = id;
            document.getElementById('modal_qty').value = qty;
            document.getElementById('formConfirm').action = '../logic/procesar_compra.php';
            
            document.getElementById('confirmText').innerHTML = `
                <div class="mb-4">¿CONFIRMA LA PRODUCCIÓN DE <span class='text-[var(--aoe-gold)] font-black text-sm'>${qty} UNIDADES</span><br>DEL ACTIVO <span class='text-white font-black text-sm uppercase'>${nombre}</span>?</div>
                <div class="bg-black/50 p-4 border border-[var(--wood-border)]">
                    <span class='text-[9px] text-gray-500 font-bold uppercase tracking-widest block mb-2'>COSTO TOTAL ESTIMADO:</span>
                    <div class='flex justify-around items-center font-black text-base'>
                        <span class='text-green-500'>$${totalD}</span>
                        <span class='text-gray-700'>|</span>
                        <span class='text-white'>${totalA}T</span>
                        <span class='text-gray-700'>|</span>
                        <span class='text-yellow-500'>${totalP}L</span>
                    </div>
                </div>
            `;
            
            document.getElementById('modalConfirm').classList.remove('hidden');
            document.body.classList.add('modal-active');
        }

        function cerrarModal() {
            document.getElementById('modalConfirm').classList.add('hidden');
            document.body.classList.remove('modal-active');
        }
    </script>
</body>
</html>