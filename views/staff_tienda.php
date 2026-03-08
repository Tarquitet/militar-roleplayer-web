<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') {
    header("Location: ../login.php");
    exit();
}

$root_path = "../";
require_once '../config/conexion.php';
$txt = require '../config/textos.php';

// Cargamos el archivo de precios para pasarlo a JavaScript
$precios_base = require '../config/precios.php';
$precios_json = json_encode($precios_base);

try {
    $stmt_catalogo = $pdo->query("SELECT * FROM catalogo_tienda ORDER BY tipo ASC, nacion ASC, rango ASC");
    $catalogo = $stmt_catalogo->fetchAll(PDO::FETCH_ASSOC);

    $stmt_naciones = $pdo->query("SELECT nombre FROM naciones ORDER BY nombre ASC");
    $lista_naciones = $stmt_naciones->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    die("Fallo de enlace con el catálogo: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title><?php echo $txt['GLOBAL']['MANDO_STAFF']; ?> - Tienda</title>
    <?php include '../includes/head.php'; ?>
    <style>
        .modal-active { overflow: hidden; }
        .img-purga { filter: sepia(1) hue-rotate(-50deg) saturate(2) brightness(0.7); }
    </style>
</head>
<body class="bg-[#0d0e0a] text-[var(--text-main)] min-h-screen pb-20" onload="initTienda()">

    <?php include '../includes/nav_staff.php'; ?>

    <main class="p-8 max-w-[1600px] mx-auto relative">
        <div class="flex gap-4 mb-6">
            <button onclick="document.getElementById('modalEditorPrecios').classList.remove('hidden')" 
                    class="btn-m !bg-none !border-[var(--khaki-beige)] !text-[var(--parchment)] hover:!text-[var(--aoe-gold)] hover:!border-[var(--aoe-gold)]">
                ⚙️ <?php echo $txt['STAFF_TIENDA']['BTN_PRECIOS']; ?>
            </button>
            <button onclick="document.getElementById('modalNuevoVehiculo').classList.remove('hidden')" 
                    class="btn-m">
                ➕ <?php echo $txt['STAFF_TIENDA']['BTN_ANADIR']; ?>
            </button>
        </div>

        <?php if (isset($_GET['mensaje'])): ?>
            <div class="bg-[var(--olive-drab)] text-[var(--aoe-gold)] border border-[var(--aoe-gold)] p-3 mb-6 text-xs font-bold tracking-widest uppercase shadow-lg text-center">
                <?php echo $txt['STAFF_TIENDA']['MSJ_EXITO']; ?>
            </div>
        <?php endif; ?>

        <div class="m-panel mb-8 p-4 bg-black/40 shadow-2xl backdrop-blur-md">
            <div class="flex gap-4 mb-4 border-b border-[var(--wood-border)] pb-4">
                <button id="btn_tipo_tanque" onclick="setFiltroTipo('tanque')" class="btn-m !text-[10px]">
                    <?php echo $txt['STAFF_TIENDA']['CAT_TANQUES']; ?>
                </button>
                <button id="btn_tipo_avion" onclick="setFiltroTipo('avion')" class="btn-m !text-[10px] grayscale opacity-70">
                    <?php echo $txt['STAFF_TIENDA']['CAT_AVIONES']; ?>
                </button>
            </div>
            
            <div class="flex items-center gap-4">
                <span class="text-[var(--aoe-gold)] text-[10px] font-black uppercase tracking-widest">
                    <?php echo $txt['STAFF_TIENDA']['FILTRO_PAIS']; ?>
                </span>
                <div id="contenedor_naciones" class="flex flex-wrap gap-2"></div>
            </div>
        </div>

        <div id="cuerpo_tabla" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-6 mt-4 relative">
            <div id="mensaje_vacio" class="col-span-full m-panel p-10 text-center text-gray-500 italic font-bold uppercase tracking-widest" style="display:none;">
                <?php echo $txt['STAFF_TIENDA']['SIN_VEHICULOS']; ?>
            </div>
            
            <?php foreach ($catalogo as $item): ?>
                <div class="fila-vehiculo flex flex-col bg-[#111] border border-[var(--wood-border)] shadow-2xl relative transition hover:brightness-110" 
                     data-tipo="<?php echo $item['tipo']; ?>" 
                     data-nacion="<?php echo htmlspecialchars($item['nacion']); ?>">
                    
                    <div class="absolute top-0 right-0 bg-black/80 text-[var(--aoe-gold)] font-black text-[10px] px-2 py-1 border-b border-l border-[var(--wood-border)] z-10">
                        T-<?php echo $item['rango']; ?>
                    </div>

                    <div class="h-32 bg-[#0a0a0a] relative overflow-hidden border-b border-[var(--wood-border)]">
                        <img src="../<?php echo $item['imagen_url']; ?>" class="w-full h-full object-cover">
                    </div>

                    <div class="p-4 flex-grow">
                        <span class="text-[8px] text-[var(--parchment)] uppercase font-bold tracking-widest block mb-1"><?php echo htmlspecialchars($item['subtipo'] ?? '-'); ?></span>
                        <h3 class="text-sm font-black text-white font-['Cinzel'] uppercase truncate">
                            <?php echo htmlspecialchars($item['nombre_vehiculo']); ?>
                        </h3>
                    </div>

                    <div class="p-2 bg-black/60 border-t border-[var(--wood-border)]">
                        <button type="button" 
                                onclick="abrirPurgaSeguridad(<?php echo $item['id']; ?>, '<?php echo addslashes($item['nombre_vehiculo']); ?>', '../<?php echo $item['imagen_url']; ?>')" 
                                class="btn-m !bg-red-950/30 !border-red-800 !text-red-500 hover:!bg-red-800 hover:!text-white !py-2 !px-3 text-[9px] w-full font-black uppercase">
                            🗑️ ELIMINAR ACTIVO
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="modalConfirmarBorrado" class="hidden fixed inset-0 bg-black/95 z-[200] flex items-center justify-center p-4">
            <div class="m-panel w-full max-w-2xl relative border-red-800 border-2 shadow-[0_0_50px_rgba(220,38,38,0.2)]">
                <button onclick="document.getElementById('modalConfirmarBorrado').classList.add('hidden')" class="absolute top-4 right-4 text-gray-500 hover:text-white font-bold text-xl">&times;</button>
                
                <h3 class="text-red-500 font-black text-lg mb-2 tracking-[0.2em] uppercase text-center border-b border-red-900/40 pb-2">⚠️ PROTOCOLO DE PURGA DE ACTIVOS</h3>
                
                <div class="flex gap-6 mb-6 mt-6 bg-red-950/10 p-4 border border-red-900/30">
                    <div class="w-1/3 h-24 bg-black border border-red-900/50 overflow-hidden">
                        <img id="purga_img" src="" class="w-full h-full object-cover img-purga">
                    </div>
                    <div class="w-2/3">
                        <span class="text-[9px] text-gray-500 font-bold uppercase tracking-widest block mb-1">Identificación del Activo:</span>
                        <h4 id="purga_nombre" class="text-white font-black text-2xl font-['Cinzel'] uppercase"></h4>
                        <p class="text-[10px] text-red-400 font-bold mt-2 uppercase tracking-tighter italic">¿Está seguro de eliminar este registro del sistema global?</p>
                    </div>
                </div>

                <div class="mb-8">
                    <span class="text-[10px] text-gray-500 font-black uppercase tracking-widest block mb-3">📡 ESCANEO DE IMPACTO EN FACCIONES:</span>
                    <div id="impacto_lista" class="max-h-40 overflow-y-auto space-y-2 bg-black/50 border border-gray-800 p-4 custom-scrollbar">
                        <p class="text-gray-600 text-[10px] text-center italic py-4 animate-pulse">Escaneando inventarios de todos los equipos...</p>
                    </div>
                </div>

                <form action="../logic/procesar_tienda_staff.php" method="POST" class="grid grid-cols-2 gap-4">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" id="purga_id" name="id">
                    
                    <button type="submit" name="reembolsar" value="0" class="btn-m !bg-none !border-gray-700 !text-gray-500 hover:!text-white hover:!border-white !py-4 text-[10px] font-black uppercase">
                        BORRAR SIN REEMBOLSO
                    </button>
                    <button type="submit" name="reembolsar" value="1" class="btn-m !bg-red-800 !border-red-600 !text-white !py-4 text-[10px] font-black uppercase animate-pulse shadow-[0_0_20px_rgba(220,38,38,0.4)]">
                        PURGAR Y REEMBOLSAR A TODOS
                    </button>
                </form>
            </div>
        </div>

        <div id="modalNuevoVehiculo" class="hidden fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-4">
            <div class="m-panel w-full max-w-lg relative border-[var(--aoe-gold)]">
                <button onclick="document.getElementById('modalNuevoVehiculo').classList.add('hidden')" class="absolute top-4 right-4 text-[var(--parchment)] hover:text-white font-bold text-xl">&times;</button>
                <h2 class="m-title text-2xl mb-6 border-b border-[var(--wood-border)] pb-2"><?php echo $txt['STAFF_TIENDA']['MODAL_ADD_TITULO']; ?></h2>
                
                <form action="../logic/procesar_tienda.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="accion" value="agregar">
                    
                    <div class="mb-4 flex gap-4">
                        <div class="w-1/2">
                            <label class="block text-[10px] text-[var(--parchment)] uppercase font-bold mb-1 tracking-widest"><?php echo $txt['STAFF_TIENDA']['LBL_TIPO']; ?></label>
                            <select id="tipo_vehiculo" name="tipo" onchange="actualizarSubtipos()" class="m-input w-full outline-none focus:border-[var(--aoe-gold)]">
                                <option value="tanque">Tanque</option>
                                <option value="avion">Avión</option>
                            </select>
                        </div>
                        <div class="w-1/2">
                            <label class="block text-[10px] text-[var(--parchment)] uppercase font-bold mb-1 tracking-widest"><?php echo $txt['STAFF_TIENDA']['LBL_SUBTIPO']; ?></label>
                            <select id="subtipo_vehiculo" name="subtipo" onchange="actualizarPrecioPreview()" class="m-input w-full outline-none focus:border-[var(--aoe-gold)]"></select>
                        </div>
                    </div>

                    <div class="mb-4 flex gap-4">
                        <div class="w-1/2">
                            <label class="block text-[10px] text-[var(--parchment)] uppercase font-bold mb-1 tracking-widest"><?php echo $txt['STAFF_TIENDA']['LBL_NACION']; ?></label>
                            <select name="nacion" class="m-input w-full outline-none focus:border-[var(--aoe-gold)]">
                                <?php foreach ($lista_naciones as $nacion): ?>
                                    <option value="<?php echo htmlspecialchars($nacion); ?>"><?php echo htmlspecialchars($nacion); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="w-1/2">
                            <label class="block text-[10px] text-[var(--parchment)] uppercase font-bold mb-1 tracking-widest"><?php echo $txt['STAFF_TIENDA']['LBL_RANGO']; ?></label>
                            <input type="number" id="rango_vehiculo" name="rango" min="1" max="8" value="1" onchange="actualizarPrecioPreview()" onkeyup="actualizarPrecioPreview()" required class="m-input w-full text-center outline-none focus:border-[var(--aoe-gold)]">
                        </div>
                    </div>

                    <div class="mb-4 bg-black/40 p-4 border border-[var(--wood-border)] shadow-inner">
                        <p class="text-[9px] text-[var(--aoe-gold)] uppercase tracking-[0.2em] mb-3 font-black text-center"><?php echo $txt['STAFF_TIENDA']['LBL_COSTO_SYS']; ?></p>
                        <div class="flex justify-between items-center text-sm font-['Cinzel']">
                            <div class="text-center"><span class="block text-[var(--parchment)] text-[8px] font-sans font-bold uppercase">DINERO</span><span id="prev_dinero" class="text-green-500 font-black text-lg">...</span></div>
                            <div class="text-center border-l border-[var(--wood-border)] pl-4"><span class="block text-[var(--parchment)] text-[8px] font-sans font-bold uppercase">ACERO</span><span id="prev_acero" class="text-white font-black text-lg">...</span></div>
                            <div class="text-center border-l border-[var(--wood-border)] pl-4"><span class="block text-[var(--parchment)] text-[8px] font-sans font-bold uppercase">FUEL</span><span id="prev_petroleo" class="text-yellow-500 font-black text-lg">...</span></div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-[10px] text-[var(--parchment)] uppercase font-bold mb-1 tracking-widest"><?php echo $txt['STAFF_TIENDA']['LBL_NOMBRE']; ?></label>
                        <input type="text" name="nombre_vehiculo" required placeholder="..." class="m-input w-full outline-none focus:border-[var(--aoe-gold)]">
                    </div>

                    <div class="mb-6">
                        <label class="block text-[9px] text-[var(--parchment)] uppercase font-bold mb-1 tracking-widest">
                            IMAGEN DEL ACTIVO
                            <span class="text-red-500 normal-case ml-2 font-bold">(Máx. 1MB)</span>
                        </label>
                        <input type="file" name="imagen" accept="image/*" class="w-full text-[10px] border border-gray-800 p-2 bg-black/40">
                    </div>

                    <button type="submit" class="btn-m w-full py-4 text-xs tracking-[0.2em] font-black uppercase">
                        <?php echo $txt['STAFF_TIENDA']['BTN_CONFIRMAR']; ?>
                    </button>
                </form>
            </div>
        </div>

        <div id="modalEditorPrecios" class="hidden fixed inset-0 bg-black/90 z-[60] flex items-center justify-center p-4">
            <div class="m-panel w-full max-w-lg relative border-[var(--aoe-gold)]">
                <button onclick="document.getElementById('modalEditorPrecios').classList.add('hidden')" class="absolute top-4 right-4 text-[var(--parchment)] hover:text-white font-bold text-xl">&times;</button>
                <h2 class="m-title text-2xl mb-1 uppercase font-black italic"><?php echo $txt['STAFF_TIENDA']['MODAL_PR_TITULO']; ?></h2>
                <p class="text-[var(--parchment)] text-[10px] uppercase tracking-widest mb-6 border-b border-[var(--wood-border)] pb-2"><?php echo $txt['STAFF_TIENDA']['MODAL_PR_DESC']; ?></p>
                <form action="../logic/actualizar_precio_unico.php" method="POST" class="space-y-6">
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="stat-label block mb-1">TIPO</label>
                            <select id="edit_tipo" name="tipo" onchange="updateEditSubtipos()" class="m-input w-full text-[10px]"></select>
                        </div>
                        <div>
                            <label class="stat-label block mb-1">SUBTIPO</label>
                            <select id="edit_subtipo" name="subtipo" onchange="refreshEditValues()" class="m-input w-full text-[10px]"></select>
                        </div>
                        <div>
                            <label class="stat-label block mb-1">RANGO</label>
                            <input type="number" id="edit_rango" name="rango" min="1" max="8" value="1" onchange="refreshEditValues()" class="m-input w-full text-center">
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between bg-black/50 p-3 border border-gray-800">
                            <span class="text-[10px] font-black text-green-500 uppercase">DINERO ($)</span>
                            <input type="number" id="new_dinero" name="dinero" class="m-input w-32 text-right text-green-500 font-black">
                        </div>
                        <div class="flex items-center justify-between bg-black/50 p-3 border border-gray-800">
                            <span class="text-[10px] font-black text-white uppercase">ACERO (T)</span>
                            <input type="number" id="new_acero" name="acero" class="m-input w-32 text-right text-white font-black">
                        </div>
                        <div class="flex items-center justify-between bg-black/50 p-3 border border-gray-800">
                            <span class="text-[10px] font-black text-yellow-500 uppercase">PETRÓLEO (L)</span>
                            <input type="number" id="new_petroleo" name="petroleo" class="m-input w-32 text-right text-yellow-500 font-black">
                        </div>
                    </div>
                    <button type="submit" class="btn-m w-full py-4 uppercase font-black tracking-widest"><?php echo $txt['STAFF_TIENDA']['BTN_ACTUALIZAR_PR']; ?></button>
                </form>
            </div>
        </div>

        <div id="modalErrorArchivo" class="hidden fixed inset-0 bg-black/90 z-[200] flex items-center justify-center p-4">
            <div class="m-panel w-full max-w-sm relative border-red-800 border-2">
                <h3 class="text-red-500 font-black text-lg mb-4 text-center uppercase tracking-widest">❌ ERROR DE CARGA</h3>
                <p id="errorArchivoMsg" class="text-[10px] text-gray-400 text-center uppercase mb-6"></p>
                <button onclick="document.getElementById('modalErrorArchivo').classList.add('hidden')" class="btn-m w-full">ENTENDIDO</button>
            </div>
        </div>

    </main>

    <script>
        const preciosBase = <?php echo $precios_json; ?>;
        const todasLasNaciones = <?php echo json_encode($lista_naciones); ?>;
        let filtroTipoActual = 'tanque';
        let filtroNacionActual = todasLasNaciones[0] || '';

        function initTienda() {
            actualizarSubtipos();
            updateEditSubtipos();
            renderBotonesNaciones();
            aplicarFiltrosTabla();
        }

        // --- PURGA DE SEGURIDAD (LA NUEVA LÓGICA) ---
        function abrirPurgaSeguridad(id, nombre, img) {
            document.getElementById('purga_id').value = id;
            document.getElementById('purga_nombre').innerText = nombre;
            document.getElementById('purga_img').src = img;
            document.getElementById('modalConfirmarBorrado').classList.remove('hidden');
            
            const lista = document.getElementById('impacto_lista');
            lista.innerHTML = '<p class="text-gray-600 text-[10px] text-center italic py-4 animate-pulse uppercase tracking-widest">Iniciando escaneo de registros...</p>';

            fetch(`../logic/obtener_detalles_borrado.php?id=${id}`)
                .then(r => r.json())
                .then(data => {
                    lista.innerHTML = '';
                    if (data.length === 0) {
                        lista.innerHTML = '<p class="text-green-500 text-[10px] text-center font-black uppercase py-6 tracking-widest">✅ ACTIVO LIMPIO: Sin impacto en inventarios.</p>';
                    } else {
                        data.forEach(item => {
                            const div = document.createElement('div');
                            // MEJORA: Añadimos border-l para estilo de registro y colores vivos
                            div.className = "flex justify-between items-center bg-white/5 p-3 border-l-2 border-red-500/50 mb-1 text-[10px] font-black uppercase tracking-wider";
                            
                            let status = item.tipo === 'unidad' 
                                ? `<span class="text-[var(--aoe-gold)] font-['Cinzel']">${item.cantidad}x UNIDADES</span>` 
                                : `<span class="text-blue-400 border border-blue-500/30 px-2 py-0.5 bg-blue-500/5">PATENTE</span>`;
                            
                            // SOLUCIÓN: span class="text-white" para que se vea el grupo
                            div.innerHTML = `<span class="text-white font-black">${item.equipo}</span> ${status}`;
                            lista.appendChild(div);
                        });
                    }
                })
                .catch(() => {
                    lista.innerHTML = '<p class="text-red-500 text-[10px] text-center py-4 uppercase font-black">⚠️ ERROR DE COMUNICACIÓN CON LA BASE DE DATOS</p>';
                });
        }

        // --- FILTROS Y UI ---
        function setFiltroTipo(t) {
            filtroTipoActual = t;
            document.getElementById('btn_tipo_tanque').classList.toggle('grayscale', t !== 'tanque');
            document.getElementById('btn_tipo_avion').classList.toggle('grayscale', t !== 'avion');
            aplicarFiltrosTabla();
        }

        function renderBotonesNaciones() {
            const cont = document.getElementById('contenedor_naciones');
            cont.innerHTML = '';
            todasLasNaciones.forEach(n => {
                const btn = document.createElement('button');
                btn.innerText = n;
                btn.className = `px-4 py-1 text-[10px] font-black uppercase border transition ${n === filtroNacionActual ? 'bg-[var(--dark-olive)] text-[var(--aoe-gold)] border-[var(--aoe-gold)]' : 'bg-black/50 text-gray-500 border-gray-800 hover:text-white'}`;
                btn.onclick = () => { filtroNacionActual = n; renderBotonesNaciones(); aplicarFiltrosTabla(); };
                cont.appendChild(btn);
            });
        }

        function aplicarFiltrosTabla() {
            let count = 0;
            document.querySelectorAll('.fila-vehiculo').forEach(f => {
                const show = f.dataset.tipo === filtroTipoActual && f.dataset.nacion === filtroNacionActual;
                f.style.display = show ? '' : 'none';
                if(show) count++;
            });
            document.getElementById('mensaje_vacio').style.display = count === 0 ? '' : 'none';
        }

        // --- LÓGICA EDITOR ---
        function actualizarSubtipos() {
            const tipo = document.getElementById('tipo_vehiculo').value;
            const sel = document.getElementById('subtipo_vehiculo');
            sel.innerHTML = '';
            let ops = tipo === 'tanque' ? ['Ligero', 'AAA', 'Mediano', 'Pesado'] : ['Caza', 'Interceptor', 'Bombardero', 'Avión de ataque'];
            ops.forEach(o => sel.add(new Option(o, o)));
            actualizarPrecioPreview();
        }

        function actualizarPrecioPreview() {
            const t = document.getElementById('tipo_vehiculo').value;
            const s = document.getElementById('subtipo_vehiculo').value;
            const r = document.getElementById('rango_vehiculo').value;
            const data = preciosBase[t][s][r];
            document.getElementById('prev_dinero').innerText = '$' + data.dinero;
            document.getElementById('prev_acero').innerText = data.acero;
            document.getElementById('prev_petroleo').innerText = data.petroleo + 'L';
        }

        function updateEditSubtipos() {
            const t = document.getElementById('edit_tipo');
            if(t.options.length === 0) {
                t.add(new Option('Tanque', 'tanque'));
                t.add(new Option('Avión', 'avion'));
            }
            const sel = document.getElementById('edit_subtipo');
            sel.innerHTML = '';
            let ops = t.value === 'tanque' ? ['Ligero', 'AAA', 'Mediano', 'Pesado'] : ['Caza', 'Interceptor', 'Bombardero', 'Avión de ataque'];
            ops.forEach(o => sel.add(new Option(o, o)));
            refreshEditValues();
        }

        function refreshEditValues() {
            const t = document.getElementById('edit_tipo').value;
            const s = document.getElementById('edit_subtipo').value;
            const r = document.getElementById('edit_rango').value;
            const data = preciosBase[t][s][r];
            document.getElementById('new_dinero').value = data.dinero;
            document.getElementById('new_acero').value = data.acero;
            document.getElementById('new_petroleo').value = data.petroleo;
        }
    </script>
</body>
</html>