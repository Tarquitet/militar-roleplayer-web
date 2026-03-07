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
</head>
<body class="pb-10" onload="initTienda()">

    <?php include '../includes/nav_staff.php'; ?>

    <main class="p-8 max-w-7xl mx-auto relative">
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

        <div class="m-panel mb-8 p-4">
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

        <div class="m-panel !p-0 overflow-hidden">
            <table class="w-full text-left border-collapse table-m">
                <thead>
                    <tr class="text-[9px] uppercase tracking-widest">
                        <th class="p-4 text-center"><?php echo $txt['STAFF_TIENDA']['TH_RANGO']; ?></th>
                        <th class="p-4"><?php echo $txt['STAFF_TIENDA']['TH_CLASE']; ?></th>
                        <th class="p-4"><?php echo $txt['STAFF_TIENDA']['TH_VEHICULO']; ?></th>
                        <th class="p-4 text-green-500"><?php echo $txt['STAFF_TIENDA']['TH_DINERO']; ?></th>
                        <th class="p-4 text-white"><?php echo $txt['STAFF_TIENDA']['TH_ACERO']; ?></th>
                        <th class="p-4 text-yellow-500"><?php echo $txt['STAFF_TIENDA']['TH_PETROLEO']; ?></th>
                        <th class="p-4 text-right"><?php echo $txt['STAFF_TIENDA']['TH_ACCION']; ?></th>
                    </tr>
                </thead>
                <tbody class="text-[var(--text-main)] text-sm" id="cuerpo_tabla">
                    <tr id="mensaje_vacio" style="display:none;">
                        <td colspan="7" class="p-10 text-center text-gray-500 italic font-bold uppercase tracking-widest">
                            <?php echo $txt['STAFF_TIENDA']['SIN_VEHICULOS']; ?>
                        </td>
                    </tr>
                    
                    <?php foreach ($catalogo as $item): ?>
                        <tr class="fila-vehiculo transition hover:bg-white/5" 
                            data-tipo="<?php echo $item['tipo']; ?>" 
                            data-nacion="<?php echo htmlspecialchars($item['nacion']); ?>">
                            
                            <td class="p-4 text-center font-black text-[var(--aoe-gold)] font-['Cinzel']">T-<?php echo $item['rango']; ?></td>
                            <td class="p-4 text-[10px] text-[var(--parchment)] uppercase font-bold tracking-widest"><?php echo htmlspecialchars($item['subtipo'] ?? '-'); ?></td>
                            <td class="p-4 flex items-center gap-3">
                                <?php if($item['imagen_url']): ?>
                                    <div class="w-12 h-12 border border-[var(--wood-border)] shadow-inner bg-black">
                                        <img src="../<?php echo $item['imagen_url']; ?>" class="w-full h-full object-cover">
                                    </div>
                                <?php else: ?>
                                    <div class="w-12 h-12 bg-black border border-[var(--wood-border)] flex items-center justify-center text-[8px] text-gray-600 font-bold uppercase"><?php echo $txt['STAFF_TIENDA']['NO_IMG']; ?></div>
                                <?php endif; ?>
                                <div class="flex flex-col">
                                    <span class="font-bold text-white tracking-wide"><?php echo htmlspecialchars($item['nombre_vehiculo']); ?></span>
                                    <?php if($item['es_premium']): ?>
                                        <span class="text-[8px] text-[var(--aoe-gold)] bg-[var(--aoe-gold)]/10 px-1 border border-[var(--aoe-gold)]/30 w-fit font-black uppercase">PREMIUM</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="p-4 font-black text-green-500">$<?php echo number_format($item['costo_dinero']); ?></td>
                            <td class="p-4 font-black"><?php echo number_format($item['costo_acero']); ?>t</td>
                            <td class="p-4 font-black text-yellow-500"><?php echo number_format($item['costo_petroleo']); ?>L</td>
                            <td class="p-4 text-right">
                                <form action="../logic/procesar_tienda.php" method="POST" onsubmit="return confirm('¿Autorizar eliminación del catálogo?');">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="btn-m !bg-none !border-red-900 !text-red-500 hover:!bg-red-950 !py-1 !px-2 text-[10px] shadow-none">
                                        <?php echo $txt['STAFF_TIENDA']['BTN_BORRAR']; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
                            <div class="text-center"><span class="block text-[var(--parchment)] text-[8px] font-sans font-bold">DINERO</span><span id="prev_dinero" class="text-green-500 font-black text-lg">...</span></div>
                            <div class="text-center border-l border-[var(--wood-border)] pl-4"><span class="block text-[var(--parchment)] text-[8px] font-sans font-bold">ACERO</span><span id="prev_acero" class="text-white font-black text-lg">...</span></div>
                            <div class="text-center border-l border-[var(--wood-border)] pl-4"><span class="block text-[var(--parchment)] text-[8px] font-sans font-bold">COMBUSTIBLE</span><span id="prev_petroleo" class="text-yellow-500 font-black text-lg">...</span></div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-[10px] text-[var(--parchment)] uppercase font-bold mb-1 tracking-widest"><?php echo $txt['STAFF_TIENDA']['LBL_NOMBRE']; ?></label>
                        <input type="text" name="nombre_vehiculo" required placeholder="..." class="m-input w-full outline-none focus:border-[var(--aoe-gold)]">
                    </div>

                    <div class="mb-6">
                        <label class="block text-[9px] text-[var(--parchment)] uppercase font-bold mb-1 tracking-widest">
                            <?php echo $txt['STAFF_TIENDA']['LBL_IMG']; ?>
                            <span class="text-red-500 normal-case ml-2 font-bold">(Máx. 500KB | JPG, PNG, WEBP)</span>
                        </label>
                        <div class="m-input p-1">
                            <input type="file" name="imagen" accept="image/jpeg, image/png, image/webp" onchange="validarImagen(this)" class="w-full text-xs cursor-pointer">
                        </div>
                    </div>

                    <div class="mb-6 p-4 bg-black/60 border border-[var(--aoe-gold)]/40 flex items-center justify-between shadow-inner">
                        <div class="flex flex-col">
                            <span class="text-[11px] text-[var(--aoe-gold)] font-black uppercase tracking-widest"><?php echo $txt['STAFF_TIENDA']['LBL_PREMIUM_TIT']; ?></span>
                            <span class="text-[9px] text-[var(--parchment)] italic"><?php echo $txt['STAFF_TIENDA']['LBL_PREMIUM_DESC']; ?></span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="es_premium" value="1" class="sr-only peer">
                            <div class="w-11 h-6 bg-[#1a1c11] border-2 border-[var(--wood-border)] peer-focus:outline-none peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-[var(--parchment)] after:border-gray-300 after:border after:h-4 after:w-4 after:transition-all peer-checked:bg-[var(--olive-drab)]"></div>
                        </label>
                    </div>

                    <button type="submit" class="btn-m w-full py-4 text-xs tracking-widest">
                        <?php echo $txt['STAFF_TIENDA']['BTN_CONFIRMAR']; ?>
                    </button>
                </form>
            </div>
        </div>

        <div id="modalEditorPrecios" class="hidden fixed inset-0 bg-black/90 z-[60] flex items-center justify-center p-4">
            <div class="m-panel w-full max-w-lg relative border-[var(--aoe-gold)]">
                <button onclick="document.getElementById('modalEditorPrecios').classList.add('hidden')" class="absolute top-4 right-4 text-[var(--parchment)] hover:text-white font-bold text-xl">&times;</button>
                
                <h2 class="m-title text-2xl mb-1"><?php echo $txt['STAFF_TIENDA']['MODAL_PR_TITULO']; ?></h2>
                <p class="text-[var(--parchment)] text-[10px] uppercase tracking-widest mb-6 border-b border-[var(--wood-border)] pb-2"><?php echo $txt['STAFF_TIENDA']['MODAL_PR_DESC']; ?></p>

                <form action="../logic/actualizar_precio_unico.php" method="POST">
                    <div class="grid grid-cols-3 gap-4 mb-6">
                        <div>
                            <label class="block text-[10px] text-[var(--parchment)] uppercase font-bold mb-1 tracking-widest"><?php echo $txt['STAFF_TIENDA']['LBL_TIPO']; ?></label>
                            <select id="edit_tipo" name="tipo" onchange="updateEditSubtipos()" class="m-input w-full outline-none focus:border-[var(--aoe-gold)] text-center">
                                <option value="tanque">Tanque</option>
                                <option value="avion">Avión</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] text-[var(--parchment)] uppercase font-bold mb-1 tracking-widest"><?php echo $txt['STAFF_TIENDA']['LBL_SUBTIPO']; ?></label>
                            <select id="edit_subtipo" name="subtipo" onchange="refreshEditValues()" class="m-input w-full outline-none focus:border-[var(--aoe-gold)] text-center"></select>
                        </div>
                        <div>
                            <label class="block text-[10px] text-[var(--parchment)] uppercase font-bold mb-1 tracking-widest"><?php echo $txt['STAFF_TIENDA']['LBL_RANGO']; ?></label>
                            <input type="number" id="edit_rango" name="rango" min="1" max="8" value="1" onchange="refreshEditValues()" class="m-input w-full text-center outline-none focus:border-[var(--aoe-gold)]">
                        </div>
                    </div>

                    <div class="space-y-4 mb-8 font-['Cinzel'] font-black">
                        <div class="bg-black/40 p-3 border border-[var(--wood-border)] shadow-inner flex items-center">
                            <span class="text-[var(--parchment)] text-xs w-1/4">DINERO</span>
                            <div class="w-1/4 text-center border-x border-[var(--wood-border)]">
                                <span class="block text-gray-600 text-[8px] font-sans"><?php echo $txt['STAFF_TIENDA']['LBL_ACTUAL']; ?></span>
                                <span id="cur_dinero" class="text-white"></span>
                            </div>
                            <div class="w-1/4 px-2 text-center">
                                <span id="diff_dinero" class="text-sm"></span>
                            </div>
                            <input type="number" id="new_dinero" name="dinero" oninput="calcDiffs()" class="m-input w-1/4 text-center text-green-500 py-1">
                        </div>

                        <div class="bg-black/40 p-3 border border-[var(--wood-border)] shadow-inner flex items-center">
                            <span class="text-[var(--parchment)] text-xs w-1/4">ACERO</span>
                            <div class="w-1/4 text-center border-x border-[var(--wood-border)]">
                                <span class="block text-gray-600 text-[8px] font-sans"><?php echo $txt['STAFF_TIENDA']['LBL_ACTUAL']; ?></span>
                                <span id="cur_acero" class="text-white"></span>
                            </div>
                            <div class="w-1/4 px-2 text-center">
                                <span id="diff_acero" class="text-sm"></span>
                            </div>
                            <input type="number" id="new_acero" name="acero" oninput="calcDiffs()" class="m-input w-1/4 text-center text-white py-1">
                        </div>

                        <div class="bg-black/40 p-3 border border-[var(--wood-border)] shadow-inner flex items-center">
                            <span class="text-[var(--parchment)] text-xs w-1/4">PETRÓLEO</span>
                            <div class="w-1/4 text-center border-x border-[var(--wood-border)]">
                                <span class="block text-gray-600 text-[8px] font-sans"><?php echo $txt['STAFF_TIENDA']['LBL_ACTUAL']; ?></span>
                                <span id="cur_petroleo" class="text-white"></span>
                            </div>
                            <div class="w-1/4 px-2 text-center">
                                <span id="diff_petroleo" class="text-sm"></span>
                            </div>
                            <input type="number" id="new_petroleo" name="petroleo" oninput="calcDiffs()" class="m-input w-1/4 text-center text-yellow-500 py-1">
                        </div>
                    </div>

                    <button type="submit" class="btn-m w-full py-4 text-xs tracking-widest">
                        <?php echo $txt['STAFF_TIENDA']['BTN_ACTUALIZAR_PR']; ?>
                    </button>
                </form>
            </div>
        </div>
    </main>

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
        const txt = <?php echo json_encode($txt['STAFF_TIENDA']); ?>;
        const todasLasNaciones = <?php echo json_encode($lista_naciones); ?>;
        const preciosBase = <?php echo $precios_json; ?>;

        function actualizarSubtipos() {
            const tipo = document.getElementById('tipo_vehiculo').value;
            const selectSubtipo = document.getElementById('subtipo_vehiculo');
            selectSubtipo.innerHTML = '';
            let opciones = tipo === 'tanque' ? ['Ligero', 'AAA', 'Mediano', 'Pesado'] : ['Caza', 'Interceptor', 'Bombardero', 'Avión de ataque'];
            opciones.forEach(opcion => { selectSubtipo.add(new Option(opcion, opcion)); });
            actualizarPrecioPreview();
        }

        function actualizarPrecioPreview() {
            const tipo = document.getElementById('tipo_vehiculo').value;
            const subtipo = document.getElementById('subtipo_vehiculo').value;
            const rango = document.getElementById('rango_vehiculo').value;

            const prevDinero = document.getElementById('prev_dinero');
            const prevAcero = document.getElementById('prev_acero');
            const prevPetroleo = document.getElementById('prev_petroleo');

            if (preciosBase[tipo] && preciosBase[tipo][subtipo] && preciosBase[tipo][subtipo][rango]) {
                const costo = preciosBase[tipo][subtipo][rango];
                prevDinero.innerText = '$' + costo.dinero;
                prevAcero.innerText = costo.acero;
                prevPetroleo.innerText = costo.petroleo + 'L';
            } else {
                prevDinero.innerText = '-'; prevAcero.innerText = '-'; prevPetroleo.innerText = '-';
            }
        }

        let filtroTipoActual = 'tanque';
        let filtroNacionActual = todasLasNaciones.length > 0 ? todasLasNaciones[0] : '';
        const filasVehiculos = Array.from(document.querySelectorAll('.fila-vehiculo'));

        function initTienda() {
            actualizarSubtipos(); 
            setFiltroTipo('tanque'); 
        }

        function setFiltroTipo(tipo) {
            filtroTipoActual = tipo;
            
            document.getElementById('btn_tipo_tanque').className = tipo === 'tanque' ? 'btn-m !text-[10px]' : 'btn-m !text-[10px] grayscale opacity-70';
            document.getElementById('btn_tipo_avion').className = tipo === 'avion' ? 'btn-m !text-[10px]' : 'btn-m !text-[10px] grayscale opacity-70';

            renderBotonesNaciones(todasLasNaciones);
            aplicarFiltrosTabla();
        }

        function renderBotonesNaciones(naciones) {
            const contenedor = document.getElementById('contenedor_naciones');
            contenedor.innerHTML = '';
            
            if (naciones.length === 0) {
                contenedor.innerHTML = `<span class="text-gray-600 text-[10px] font-bold uppercase tracking-widest italic">${txt.SIN_PAISES}</span>`;
                return;
            }

            naciones.forEach(nacion => {
                let btn = document.createElement('button');
                btn.innerText = nacion;
                
                // Mismo estilo táctico que en el inventario
                btn.className = "px-4 py-1 text-[10px] font-black uppercase tracking-widest border transition shadow-lg ";
                if (nacion === filtroNacionActual) {
                    btn.className += "bg-[var(--dark-olive)] text-[var(--aoe-gold)] border-[var(--aoe-gold)]";
                } else {
                    btn.className += "bg-black/50 text-[var(--parchment)] border-[var(--wood-border)] hover:brightness-125";
                }
                
                btn.onclick = () => {
                    filtroNacionActual = nacion;
                    renderBotonesNaciones(naciones);
                    aplicarFiltrosTabla();
                };
                contenedor.appendChild(btn);
            });
        }

        function aplicarFiltrosTabla() {
            let visibles = 0;
            filasVehiculos.forEach(fila => {
                if (fila.dataset.tipo === filtroTipoActual && fila.dataset.nacion === filtroNacionActual) {
                    fila.style.display = '';
                    visibles++;
                } else {
                    fila.style.display = 'none';
                }
            });
            document.getElementById('mensaje_vacio').style.display = visibles === 0 ? '' : 'none';
        }

        // Lógica Editor Precios
        function updateEditSubtipos() {
            const tipo = document.getElementById('edit_tipo').value;
            const select = document.getElementById('edit_subtipo');
            select.innerHTML = '';
            let opciones = tipo === 'tanque' ? ['Ligero', 'AAA', 'Mediano', 'Pesado'] : ['Caza', 'Interceptor', 'Bombardero', 'Avión de ataque'];
            opciones.forEach(op => select.add(new Option(op, op)));
            refreshEditValues();
        }

        function refreshEditValues() {
            const t = document.getElementById('edit_tipo').value;
            const s = document.getElementById('edit_subtipo').value;
            const r = document.getElementById('edit_rango').value;
            const actual = preciosBase[t][s][r];

            document.getElementById('cur_dinero').innerText = actual.dinero;
            document.getElementById('cur_acero').innerText = actual.acero;
            document.getElementById('cur_petroleo').innerText = actual.petroleo;

            document.getElementById('new_dinero').value = actual.dinero;
            document.getElementById('new_acero').value = actual.acero;
            document.getElementById('new_petroleo').value = actual.petroleo;

            calcDiffs();
        }

        function calcDiffs() {
            const fields = ['dinero', 'acero', 'petroleo'];
            fields.forEach(f => {
                const cur = parseInt(document.getElementById('cur_' + f).innerText);
                const nxt = parseInt(document.getElementById('new_' + f).value) || 0;
                const diff = nxt - cur;
                const target = document.getElementById('diff_' + f);

                if (diff > 0) { target.innerText = '+' + diff; target.className = 'text-green-500'; }
                else if (diff < 0) { target.innerText = diff; target.className = 'text-red-500'; }
                else { target.innerText = '-'; target.className = 'text-gray-600'; }
            });
        }

        document.addEventListener('DOMContentLoaded', () => { updateEditSubtipos(); });

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