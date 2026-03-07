<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'lider') {
    header("Location: ../login.php");
    exit();
}

$root_path = "../";
require_once '../config/conexion.php';
$txt = require '../config/textos.php';

$precios_base = require '../config/precios.php';
$precios_json = json_encode($precios_base);

$lider_id = $_SESSION['usuario_id'];

try {
    $stmt_user = $pdo->prepare("SELECT dinero, acero, petroleo, naciones_activas FROM cuentas WHERE id = :id");
    $stmt_user->execute([':id' => $lider_id]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

    $naciones_mando = !empty($user['naciones_activas']) ? array_map('trim', explode(',', $user['naciones_activas'])) : [];

    $stmt_naciones = $pdo->query("SELECT nombre FROM naciones ORDER BY nombre ASC");
    $naciones_totales = $stmt_naciones->fetchAll(PDO::FETCH_COLUMN);

    $stmt_cat = $pdo->query("SELECT id, tipo, subtipo, nacion, rango, nombre_vehiculo, costo_dinero, costo_acero, costo_petroleo, imagen_url, es_premium FROM catalogo_tienda ORDER BY rango ASC, es_premium ASC");
    $catalogo_completo = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

    // NUEVO: Obtenemos los IDs de los planos que el líder ya posee
    $stmt_planos = $pdo->prepare("SELECT catalogo_id FROM planos_desbloqueados WHERE cuenta_id = :id");
    $stmt_planos->execute([':id' => $lider_id]);
    $mis_planos = $stmt_planos->fetchAll(PDO::FETCH_COLUMN);

    $stmt_user = null; $stmt_naciones = null; $stmt_cat = null; $stmt_planos = null; $pdo = null;

} catch (PDOException $e) { die("Error de enlace: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title><?php echo $txt['LIDER_TIENDA']['TITULO']; ?> - <?php echo htmlspecialchars($_SESSION['username']); ?></title>
    <?php include '../includes/head.php'; ?>
    <style>
        .modal-active { overflow: hidden; }
        .rank-title { writing-mode: vertical-lr; transform: rotate(180deg); }
        .btn-nacion-tienda { transition: all 0.2s; cursor: pointer; }
        .btn-nacion-tienda.active { background-color: var(--dark-olive) !important; border-color: var(--aoe-gold) !important; color: var(--aoe-gold) !important; }
        .sector-locked { position: relative; filter: grayscale(1) brightness(0.2) !important; pointer-events: none !important; user-select: none; }
        .m-panel.locked-overlay::after {
            content: "ZONA FUERA DE JURISDICCIÓN"; position: absolute; top: 65%; left: 50%;
            transform: translate(-50%, -50%) rotate(-10deg); font-family: 'Cinzel', serif;
            color: rgba(255, 204, 0, 0.15); font-size: 3rem; font-weight: 900;
            white-space: nowrap; z-index: 50; border: 2px solid rgba(255, 204, 0, 0.1);
            padding: 1rem 3rem; pointer-events: none;
        }
    </style>
</head>
<body class="bg-[#0d0e0a] text-[var(--text-main)] min-h-screen pb-20" onload="initTienda()">

    <?php include '../includes/nav_lider.php'; ?>

    <nav class="bg-[#1a1c11] border-b border-[var(--wood-border)] sticky top-0 z-40 overflow-x-auto shadow-xl">
        <div class="flex px-4 py-2 items-center gap-4">
            <span class="text-[var(--aoe-gold)] text-[10px] font-black uppercase tracking-widest pl-4">INTELIGENCIA NACIONAL:</span>
            <div class="flex gap-2">
                <?php foreach ($naciones_totales as $n): 
                    $bajo_mando = in_array($n, $naciones_mando); ?>
                    <button onclick="setNacion('<?php echo $n; ?>')" data-nacion-btn="<?php echo $n; ?>"
                            class="btn-nacion-tienda px-4 py-1 text-[10px] font-black uppercase tracking-widest border border-[var(--wood-border)] 
                            <?php echo $bajo_mando ? 'bg-black/40 text-[var(--parchment)]' : 'bg-black/10 text-gray-700 opacity-60'; ?>">
                        <?php echo $n; ?> <?php echo $bajo_mando ? '' : '🔒'; ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </nav>

    <div class="m-panel !p-4 !border-t-0 !border-x-0 sticky top-12 z-30 flex justify-between items-center bg-black/40 shadow-2xl">
        <div class="max-w-[1600px] w-full mx-auto flex justify-between items-center px-4">
            <div class="flex gap-4">
                <button id="btn_tipo_tanque" onclick="setFiltroTipo('tanque')" class="btn-m !text-[10px] grayscale opacity-70"><?php echo $txt['LIDER_TIENDA']['BTN_TANQUES']; ?></button>
                <button id="btn_tipo_avion" onclick="setFiltroTipo('avion')" class="btn-m !text-[10px] grayscale opacity-70"><?php echo $txt['LIDER_TIENDA']['BTN_AVIONES']; ?></button>
                <button id="btn_tipo_plano" onclick="setFiltroTipo('plano')" class="btn-m !text-[10px] !border-blue-800 !text-blue-400">📜 PLANOS</button>
                
                <button onclick="abrirModal('modalNuevoVehiculo')" class="btn-m !bg-none !border-[var(--parchment)] !text-[var(--parchment)] hover:!border-[var(--aoe-gold)] hover:!text-[var(--aoe-gold)] !text-[9px] ml-4">
                    <?php echo $txt['LIDER_TIENDA']['BTN_ADD_ARBOL']; ?>
                </button>
            </div>
            <div class="flex gap-10 font-['Cinzel'] font-black">
                <div class="text-right border-r border-[var(--wood-border)] pr-6 text-green-500">$<?php echo number_format($user['dinero']); ?></div>
                <div class="text-right border-r border-[var(--wood-border)] pr-6 text-white"><?php echo number_format($user['acero']); ?> T</div>
                <div class="text-right text-yellow-500"><?php echo number_format($user['petroleo']); ?> L</div>
            </div>
        </div>
    </div>

    <main class="p-8 max-w-[1600px] mx-auto mt-6">
        <div id="panel_tabla" class="m-panel !p-0 overflow-hidden shadow-2xl relative">
            <table class="w-full text-left border-collapse table-m">
                <thead>
                    <tr class="text-[9px] uppercase tracking-widest bg-black/40 text-[var(--aoe-gold)]">
                        <th class="p-4 text-center">RANK</th>
                        <th class="p-4">CLASE</th>
                        <th class="p-4">IDENTIFICACIÓN</th>
                        <th class="p-4 text-center">COSTOS BASE</th>
                        <th class="p-4 text-right">ORDEN TÁCTICA</th>
                    </tr>
                </thead>
                <tbody id="cuerpo_tabla">
                    <?php foreach ($catalogo_completo as $item): 
                        // Verificamos si tiene el plano
                        $tiene_plano = in_array($item['id'], $mis_planos);
                    ?>
                        <tr class="fila-vehiculo transition hover:bg-white/5 border-b border-[var(--wood-border)]/10" 
                            data-tipo="<?php echo $item['tipo']; ?>" 
                            data-nacion="<?php echo htmlspecialchars($item['nacion']); ?>"
                            data-tiene-plano="<?php echo $tiene_plano ? 'true' : 'false'; ?>">
                            
                            <td class="p-4 text-center font-black font-['Cinzel'] text-sm">T-<?php echo $item['rango']; ?></td>
                            <td class="p-4 text-[10px] text-[var(--parchment)] uppercase font-bold"><?php echo htmlspecialchars($item['subtipo']); ?></td>
                            
                            <td class="p-4 flex items-center gap-3">
                                <div class="w-12 h-12 bg-black border border-[var(--wood-border)] relative">
                                    <?php if($item['imagen_url']): ?>
                                        <img src="../<?php echo $item['imagen_url']; ?>" class="w-full h-full object-cover <?php echo !$tiene_plano ? 'opacity-40 sepia brightness-150 hue-rotate-180' : ''; ?>">
                                    <?php endif; ?>
                                </div>
                                <div class="flex flex-col">
                                    <span class="font-bold text-white uppercase tracking-wide text-xs"><?php echo htmlspecialchars($item['nombre_vehiculo']); ?></span>
                                    <?php if($item['es_premium']): ?><span class="text-[8px] text-[var(--aoe-gold)] font-black uppercase tracking-tighter">UNIDAD DE ÉLITE</span><?php endif; ?>
                                </div>
                            </td>

                            <td class="p-4 text-center font-black">
                                <div class="text-green-500">$<?php echo number_format($item['costo_dinero']); ?></div>
                                <div class="text-[9px] opacity-60 text-[var(--parchment)]"><?php echo number_format($item['costo_acero']); ?>T | <?php echo number_format($item['costo_petroleo']); ?>L</div>
                            </td>

                            <td class="p-4 text-right">
                                <?php if ($tiene_plano): ?>
                                    <form action="../logic/procesar_compra.php" method="POST" class="flex items-center justify-end gap-2">
                                        <input type="number" name="cantidad" value="1" min="1" class="m-input w-16 text-center text-xs font-black">
                                        <input type="hidden" name="catalogo_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="btn-m !py-1 !px-4 text-[9px]"><?php echo $txt['LIDER_TIENDA']['BTN_ADQUIRIR']; ?></button>
                                    </form>
                                <?php else: ?>
                                    <form action="../logic/procesar_plano.php" method="POST" class="flex flex-col items-end gap-1">
                                        <input type="hidden" name="catalogo_id" value="<?php echo $item['id']; ?>">
                                        <span class="text-[8px] text-blue-400 font-bold uppercase tracking-widest">
                                            Costo Patente: <span class="text-green-500">$<?php echo number_format($item['costo_dinero']); ?></span>
                                        </span>
                                        <button type="submit" class="btn-m !py-1 !px-4 text-[9px] !bg-blue-900/40 !border-blue-500 !text-blue-300 hover:!bg-blue-800 hover:!text-white">
                                            ADQUIRIR PLANO
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div id="modalNuevoVehiculo" class="hidden fixed inset-0 bg-black/90 z-[100] flex items-center justify-center p-4">
        <div class="m-panel w-full max-w-md relative border-[var(--aoe-gold)]">
            <button onclick="cerrarModal('modalNuevoVehiculo')" class="absolute top-4 right-4 text-[var(--parchment)] hover:text-white font-bold text-xl">&times;</button>
            <h2 class="m-title text-xl mb-6 border-b border-[var(--wood-border)] pb-2"><?php echo $txt['LIDER_TIENDA']['MODAL_TITULO']; ?></h2>
            <form action="../logic/procesar_tienda_lider.php" method="POST" enctype="multipart/form-data">
                <div class="mb-4 flex gap-4">
                    <div class="w-1/2">
                        <label class="block text-[9px] text-[var(--parchment)] uppercase font-bold mb-1 tracking-widest"><?php echo $txt['LIDER_TIENDA']['LBL_CATEGORIA']; ?></label>
                        <select id="tipo_vehiculo" name="tipo" onchange="actualizarSubtipos()" class="m-input w-full">
                            <option value="tanque">Tanque</option>
                            <option value="avion">Avión</option>
                        </select>
                    </div>
                    <div class="w-1/2">
                        <label class="block text-[9px] text-[var(--parchment)] uppercase font-bold mb-1 tracking-widest"><?php echo $txt['LIDER_TIENDA']['LBL_CLASE']; ?></label>
                        <select id="subtipo_vehiculo" name="subtipo" onchange="actualizarPrecioPreview()" class="m-input w-full"></select>
                    </div>
                </div>
                <div class="mb-4 flex gap-4">
                    <div class="w-1/2">
                        <label class="block text-[9px] text-[var(--parchment)] uppercase font-bold mb-1 tracking-widest"><?php echo $txt['LIDER_TIENDA']['LBL_NACION']; ?></label>
                        <select name="nacion" class="m-input w-full">
                            <?php foreach ($naciones_mando as $n): ?>
                                <option value="<?php echo htmlspecialchars($n); ?>"><?php echo htmlspecialchars($n); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-1/2">
                        <label class="block text-[9px] text-[var(--parchment)] uppercase font-bold mb-1 tracking-widest"><?php echo $txt['LIDER_TIENDA']['LBL_RANGO']; ?></label>
                        <input type="number" id="rango_vehiculo" name="rango" min="1" max="8" value="1" onchange="actualizarPrecioPreview()" class="m-input w-full text-center">
                    </div>
                </div>
                <div class="mb-4 bg-black/40 p-4 border border-[var(--wood-border)] shadow-inner text-center">
                    <p class="text-[8px] text-[var(--aoe-gold)] uppercase tracking-[0.2em] mb-2 font-black"><?php echo $txt['LIDER_TIENDA']['LBL_COSTOS']; ?></p>
                    <div class="flex justify-between items-center text-sm font-['Cinzel']">
                        <div><span class="block text-[var(--parchment)] text-[8px] font-sans font-bold uppercase">DINERO</span><span id="prev_dinero" class="text-green-500 font-black">...</span></div>
                        <div class="border-l border-[var(--wood-border)] pl-4"><span class="block text-[var(--parchment)] text-[8px] font-sans font-bold uppercase">ACERO</span><span id="prev_acero" class="text-white font-black">...</span></div>
                        <div class="border-l border-[var(--wood-border)] pl-4"><span class="block text-[var(--parchment)] text-[8px] font-sans font-bold uppercase">COMB.</span><span id="prev_petroleo" class="text-yellow-500 font-black">...</span></div>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-[9px] text-[var(--parchment)] uppercase font-bold mb-1 tracking-widest"><?php echo $txt['LIDER_TIENDA']['LBL_NOMBRE']; ?></label>
                    <input type="text" name="nombre_vehiculo" required class="m-input w-full">
                </div>
                <div class="mb-6">
                    <label class="block text-[9px] text-[var(--parchment)] uppercase font-bold mb-1 tracking-widest">
                        <?php echo $txt['LIDER_TIENDA']['LBL_IMG']; ?>
                        <span class="text-red-500 normal-case ml-2 font-bold">(Máx. 500KB | JPG, PNG, WEBP)</span>
                    </label>
                    <div class="m-input p-1">
                        <input type="file" name="imagen" accept="image/jpeg, image/png, image/webp" onchange="validarImagen(this)" class="w-full text-xs cursor-pointer">
                    </div>
                </div>
                <button type="submit" class="btn-m w-full py-3 text-[10px] tracking-widest">
                    <?php echo $txt['LIDER_TIENDA']['BTN_CONFIRMAR']; ?>
                </button>
            </form>
        </div>
    </div>

<div id="modalErrorArchivo" class="hidden fixed inset-0 bg-black/90 z-[200] flex items-center justify-center p-4">
    <div class="m-panel w-full max-w-sm relative border-red-800 border-2 shadow-[0_0_15px_rgba(220,38,38,0.3)]">
        <h3 class="text-red-500 font-black text-lg mb-4 tracking-widest uppercase text-center border-b border-red-900/50 pb-2">
            ❌ ACCESO DENEGADO
        </h3>
        <p id="errorArchivoMsg" class="text-[10px] text-[var(--parchment)] text-center uppercase tracking-widest mb-6 leading-relaxed">
            </p>
        <button type="button" onclick="cerrarModalError()" class="btn-m w-full !bg-red-950/40 !border-red-800 !text-red-500 hover:!bg-red-900 hover:!text-white py-3 text-xs tracking-widest">
            ENTENDIDO
        </button>
    </div>
</div>

    <script>
        const txt = <?php echo json_encode($txt['LIDER_TIENDA']); ?>;
        const nacionesMando = <?php echo json_encode($naciones_mando); ?>;
        const preciosBase = <?php echo $precios_json; ?>;
        
        let nacionActual = nacionesMando.length > 0 ? nacionesMando[0] : '<?php echo $naciones_totales[0] ?? ""; ?>';
        // Iniciamos por defecto en Planos para que vean qué deben desbloquear primero
        let tipoActual = 'plano'; 

        function initTienda() { 
            actualizarSubtipos(); 
            setNacion(nacionActual); 
        }

        function setFiltroTipo(t) {
            tipoActual = t;
            // Estilos de botones
            document.getElementById('btn_tipo_tanque').className = t === 'tanque' ? 'btn-m !text-[10px]' : 'btn-m !text-[10px] grayscale opacity-70';
            document.getElementById('btn_tipo_avion').className = t === 'avion' ? 'btn-m !text-[10px]' : 'btn-m !text-[10px] grayscale opacity-70';
            document.getElementById('btn_tipo_plano').className = t === 'plano' ? 'btn-m !text-[10px] !border-blue-500 !text-blue-400' : 'btn-m !text-[10px] !border-blue-900 !text-blue-800 opacity-70';
            aplicarFiltros();
        }

        function setNacion(n) {
            nacionActual = n;
            document.querySelectorAll('.btn-nacion-tienda').forEach(b => {
                b.classList.toggle('active', b.getAttribute('data-nacion-btn') === n);
            });
            aplicarFiltros();
        }

        function aplicarFiltros() {
            const cuerpo = document.getElementById('cuerpo_tabla');
            const panel = document.getElementById('panel_tabla');
            const esMio = nacionesMando.includes(nacionActual);

            if (!esMio) {
                cuerpo.classList.add('sector-locked');
                panel.classList.add('locked-overlay');
            } else {
                cuerpo.classList.remove('sector-locked');
                panel.classList.remove('locked-overlay');
            }

            // NUEVA LÓGICA DE FILTRADO PARA PLANOS
            document.querySelectorAll('.fila-vehiculo').forEach(f => {
                const tienePlano = f.dataset.tienePlano === 'true';
                
                if (tipoActual === 'plano') {
                    // Si estamos en la pestaña Planos, mostrar SOLO los que NO tienen plano
                    f.style.display = (!tienePlano && f.dataset.nacion === nacionActual) ? '' : 'none';
                } else {
                    // Si estamos en Tanques/Aviones, mostrar SOLO los que SI tienen plano y coinciden en tipo
                    f.style.display = (tienePlano && f.dataset.tipo === tipoActual && f.dataset.nacion === nacionActual) ? '' : 'none';
                }
            });
        }

        function abrirModal(id) { document.getElementById(id).classList.remove('hidden'); document.body.classList.add('modal-active'); }
        function cerrarModal(id) { document.getElementById(id).classList.add('hidden'); document.body.classList.remove('modal-active'); }
        function actualizarSubtipos() {
            const select = document.getElementById('subtipo_vehiculo');
            select.innerHTML = '';
            let opciones = tipoActual === 'avion' ? ['Caza', 'Bombardero', 'Interceptor', 'Ataque'] : ['Ligero', 'Mediano', 'Pesado', 'AAA'];
            opciones.forEach(op => { select.add(new Option(op, op)); });
            actualizarPrecioPreview();
        }
        function actualizarPrecioPreview() {
            const subtipo = document.getElementById('subtipo_vehiculo').value;
            const rango = document.getElementById('rango_vehiculo').value;
            let tipoPrecio = tipoActual === 'avion' ? 'avion' : 'tanque'; // Seguridad por si están en pestaña 'plano'
            if (preciosBase[tipoPrecio] && preciosBase[tipoPrecio][subtipo] && preciosBase[tipoPrecio][subtipo][rango]) {
                const c = preciosBase[tipoPrecio][subtipo][rango];
                document.getElementById('prev_dinero').innerText = '$' + c.dinero;
                document.getElementById('prev_acero').innerText = c.acero + 't';
                document.getElementById('prev_petroleo').innerText = c.petroleo + 'L';
            }
        }

        function validarImagen(input) {
            const maxSize = 500 * 1024; // Límite de 500 KB
            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

            if (input.files && input.files[0]) {
                const file = input.files[0];

                if (!allowedTypes.includes(file.type)) {
                    mostrarErrorArchivo("El archivo seleccionado no es válido.<br><br>Solo se permiten formatos: <span class='text-white font-bold'>JPG, PNG o WEBP</span>.");
                    input.value = ''; // Solo vacía el selector de imagen, NO el resto del formulario
                    return;
                }

                if (file.size > maxSize) {
                    let pesoReal = (file.size / 1024).toFixed(1);
                    mostrarErrorArchivo("Carga excesiva detectada: <span class='text-red-400 font-bold'>" + pesoReal + " KB</span>.<br><br>El límite máximo de seguridad del servidor es de <span class='text-white font-bold'>500 KB</span>.<br><br>Por favor, comprime el archivo y vuelve a seleccionarlo.");
                    input.value = ''; // Solo vacía el selector de imagen, NO el resto del formulario
                    return;
                }
            }
        }

        function mostrarErrorArchivo(mensaje) {
            document.getElementById('errorArchivoMsg').innerHTML = mensaje;
            document.getElementById('modalErrorArchivo').classList.remove('hidden');
        }

        function cerrarModalError() {
            document.getElementById('modalErrorArchivo').classList.add('hidden');
        }
    </script>
</body>
</html>