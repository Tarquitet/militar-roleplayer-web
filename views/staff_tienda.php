<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') {
    header("Location: ../login.php");
    exit();
}
require_once '../config/conexion.php';

// Cargamos el archivo de precios para pasarlo a JavaScript
$precios_base = require '../config/precios.php';
$precios_json = json_encode($precios_base);

try {
    $stmt_catalogo = $pdo->query("SELECT * FROM catalogo_tienda ORDER BY tipo ASC, nacion ASC, rango ASC");
    $catalogo = $stmt_catalogo->fetchAll(PDO::FETCH_ASSOC);

    $stmt_naciones = $pdo->query("SELECT nombre FROM naciones ORDER BY nombre ASC");
    $lista_naciones = $stmt_naciones->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    die("Error al cargar la tienda: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Staff - Tienda</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-200 min-h-screen pb-10" onload="initTienda()">

    <?php include '../includes/nav_staff.php'; ?>

    <main class="p-8 max-w-7xl mx-auto relative">
        <div class="flex gap-3">
            <button onclick="document.getElementById('modalEditorPrecios').classList.remove('hidden')" 
                    class="bg-gray-700 hover:bg-gray-600 text-gray-300 font-bold py-3 px-6 rounded shadow-lg transition flex items-center gap-2 border border-gray-600">
                <span>⚙️</span> Ajustar Precios
            </button>
            <button onclick="document.getElementById('modalNuevoVehiculo').classList.remove('hidden')" 
                    class="bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 px-6 rounded shadow-lg transition flex items-center gap-2">
                <span>➕</span> Añadir Vehículo
            </button>
        </div>

        <?php if (isset($_GET['mensaje'])): ?>
            <div class="bg-green-600 text-white p-3 rounded mb-6 text-sm font-medium">Operación exitosa. Catálogo actualizado.</div>
        <?php endif; ?>

        <div class="mb-6 bg-gray-800 p-4 rounded-lg border border-gray-700 shadow-lg">
            <div class="flex gap-4 mb-4 border-b border-gray-700 pb-4">
                <button id="btn_tipo_tanque" onclick="setFiltroTipo('tanque')" class="px-6 py-2 bg-purple-600 text-white font-bold rounded shadow transition">🛡️ Tanques</button>
                <button id="btn_tipo_avion" onclick="setFiltroTipo('avion')" class="px-6 py-2 bg-gray-900 hover:bg-gray-700 text-gray-400 font-bold border border-gray-600 rounded transition">✈️ Aviones</button>
            </div>
            
            <div class="flex items-center gap-2">
                <span class="text-gray-500 text-sm font-bold uppercase tracking-wider mr-2">País:</span>
                <div id="contenedor_naciones" class="flex flex-wrap gap-2">
                    </div>
            </div>
        </div>

        <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-lg overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-700 text-gray-300 text-xs uppercase tracking-wider">
                        <th class="p-4 border-b border-gray-600 text-center">Rango</th>
                        <th class="p-4 border-b border-gray-600">Clasificación</th>
                        <th class="p-4 border-b border-gray-600">Vehículo</th>
                        <th class="p-4 border-b border-gray-600 text-green-400">Dinero</th>
                        <th class="p-4 border-b border-gray-600 text-gray-400">Acero</th>
                        <th class="p-4 border-b border-gray-600 text-yellow-500">Petróleo</th>
                        <th class="p-4 border-b border-gray-600 text-right">Acción</th>
                    </tr>
                </thead>
                <tbody class="text-gray-300" id="cuerpo_tabla">
                    <tr id="mensaje_vacio" style="display:none;"><td colspan="7" class="p-6 text-center text-gray-500">No hay vehículos en esta categoría.</td></tr>
                    
                    <?php foreach ($catalogo as $item): ?>
                        <tr class="fila-vehiculo hover:bg-gray-750 border-b border-gray-700 transition" 
                            data-tipo="<?php echo $item['tipo']; ?>" 
                            data-nacion="<?php echo htmlspecialchars($item['nacion']); ?>">
                            
                            <td class="p-4 text-center font-bold text-blue-400">T-<?php echo $item['rango']; ?></td>
                            <td class="p-4 text-sm text-gray-400 uppercase"><?php echo htmlspecialchars($item['subtipo'] ?? '-'); ?></td>
                            <td class="p-4 flex items-center gap-3">
                                <?php if($item['imagen_url']): ?>
                                    <img src="../<?php echo $item['imagen_url']; ?>" class="w-10 h-10 object-cover rounded border border-gray-600">
                                <?php else: ?>
                                    <div class="w-10 h-10 bg-gray-700 rounded border border-gray-600 flex items-center justify-center text-xs">Sin img</div>
                                <?php endif; ?>
                                <span class="font-bold text-white"><?php echo htmlspecialchars($item['nombre_vehiculo']); ?></span>
                            </td>
                            <td class="p-4 font-bold text-green-400">$<?php echo number_format($item['costo_dinero']); ?></td>
                            <td class="p-4 font-bold"><?php echo number_format($item['costo_acero']); ?></td>
                            <td class="p-4 font-bold text-yellow-500"><?php echo number_format($item['costo_petroleo']); ?>L</td>
                            <td class="p-4 text-right">
                                <form action="../logic/procesar_tienda.php" method="POST" onsubmit="return confirm('¿Eliminar este vehículo?');">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="text-red-400 hover:text-red-300 text-sm font-bold bg-red-900 bg-opacity-30 px-3 py-1 rounded">Borrar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="modalNuevoVehiculo" class="hidden fixed inset-0 bg-black bg-opacity-70 z-50 flex items-center justify-center">
            <div class="bg-gray-800 p-8 rounded-lg border border-gray-600 shadow-2xl w-full max-w-md relative">
                <button onclick="document.getElementById('modalNuevoVehiculo').classList.add('hidden')" class="absolute top-4 right-4 text-gray-400 hover:text-white font-bold text-xl">&times;</button>
                <h2 class="text-2xl font-bold text-white mb-6 border-b border-gray-700 pb-2">Añadir al Catálogo</h2>
                
                <form action="../logic/procesar_tienda.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="accion" value="agregar">
                    
                    <div class="mb-4 flex gap-4">
                        <div class="w-1/2">
                            <label class="block text-gray-400 text-sm mb-1">Tipo</label>
                            <select id="tipo_vehiculo" name="tipo" onchange="actualizarSubtipos()" class="w-full bg-gray-900 border border-gray-600 rounded px-3 py-2 text-white focus:outline-none focus:border-purple-500">
                                <option value="tanque">Tanque</option>
                                <option value="avion">Avión</option>
                            </select>
                        </div>
                        <div class="w-1/2">
                            <label class="block text-gray-400 text-sm mb-1">Clasificación</label>
                            <select id="subtipo_vehiculo" name="subtipo" onchange="actualizarPrecioPreview()" class="w-full bg-gray-900 border border-gray-600 rounded px-3 py-2 text-white focus:outline-none focus:border-purple-500">
                            </select>
                        </div>
                    </div>

                    <div class="mb-4 flex gap-4">
                        <div class="w-1/2">
                            <label class="block text-gray-400 text-sm mb-1">Nación</label>
                            <select name="nacion" class="w-full bg-gray-900 border border-gray-600 rounded px-3 py-2 text-white focus:outline-none focus:border-purple-500">
                                <?php foreach ($lista_naciones as $nacion): ?>
                                    <option value="<?php echo htmlspecialchars($nacion); ?>"><?php echo htmlspecialchars($nacion); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="w-1/2">
                            <label class="block text-gray-400 text-sm mb-1">Rango (1-8)</label>
                            <input type="number" id="rango_vehiculo" name="rango" min="1" max="8" value="1" onchange="actualizarPrecioPreview()" onkeyup="actualizarPrecioPreview()" required class="w-full bg-gray-900 border border-gray-600 rounded px-3 py-2 text-white text-center focus:outline-none focus:border-purple-500">
                        </div>
                    </div>

                    <div class="mb-4 bg-gray-900 p-3 rounded border border-gray-700 shadow-inner">
                        <p class="text-xs text-gray-500 uppercase tracking-widest mb-2 font-bold">Costo Asignado por el Sistema</p>
                        <div class="flex justify-between items-center text-sm">
                            <div class="text-center"><span class="block text-gray-500 text-xs">Dinero</span><span id="prev_dinero" class="text-green-400 font-bold text-lg">...</span></div>
                            <div class="text-center"><span class="block text-gray-500 text-xs">Acero</span><span id="prev_acero" class="text-white font-bold text-lg">...</span></div>
                            <div class="text-center"><span class="block text-gray-500 text-xs">Petróleo</span><span id="prev_petroleo" class="text-yellow-500 font-bold text-lg">...</span></div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-400 text-sm mb-1">Nombre del Vehículo</label>
                        <input type="text" name="nombre_vehiculo" required placeholder="Ej: M4 Sherman" class="w-full bg-gray-900 border border-gray-600 rounded px-3 py-2 text-white focus:outline-none focus:border-purple-500">
                    </div>

                    <div class="mb-6">
                        <label class="block text-gray-400 text-sm mb-1">Imagen del Vehículo</label>
                        <input type="file" name="imagen" accept="image/*" class="w-full bg-gray-900 border border-gray-600 rounded px-3 py-2 text-gray-400 file:mr-4 file:py-1 file:px-3 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-purple-600 file:text-white hover:file:bg-purple-500 cursor-pointer">
                    </div>

                    <div class="mb-6 p-3 bg-indigo-900/20 border border-indigo-500/30 rounded flex items-center justify-between">
                        <div class="flex flex-col">
                            <span class="text-[10px] text-indigo-400 font-black uppercase tracking-widest">Estatus Especial</span>
                            <span class="text-[8px] text-gray-500 italic">¿Este vehículo es de edición Premium?</span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="es_premium" value="1" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-700 rounded-full peer peer-checked:bg-indigo-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                        </label>
                    </div>

                    <button type="submit" class="w-full bg-purple-600 hover:bg-purple-500 text-white font-bold py-3 px-4 rounded transition">
                        Confirmar y Guardar
                    </button>
                </form>
            </div>
        </div>

        <div id="modalEditorPrecios" class="hidden fixed inset-0 bg-black bg-opacity-80 z-[60] flex items-center justify-center">
            <div class="bg-gray-800 p-8 rounded-lg border border-purple-500/50 shadow-2xl w-full max-w-lg relative">
                <button onclick="document.getElementById('modalEditorPrecios').classList.add('hidden')" class="absolute top-4 right-4 text-gray-400 hover:text-white font-bold text-xl">&times;</button>
                
                <h2 class="text-2xl font-bold text-white mb-2">Ajustar Precios Base</h2>
                <p class="text-gray-400 text-sm mb-6">Selecciona una categoría para modificar sus costos automáticos.</p>

                <form action="../logic/actualizar_precio_unico.php" method="POST">
                    <div class="grid grid-cols-3 gap-3 mb-6">
                        <div>
                            <label class="block text-gray-500 text-xs uppercase mb-1">Tipo</label>
                            <select id="edit_tipo" name="tipo" onchange="updateEditSubtipos()" class="w-full bg-gray-900 border border-gray-600 rounded p-2 text-sm text-white">
                                <option value="tanque">Tanque</option>
                                <option value="avion">Avión</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-500 text-xs uppercase mb-1">Subtipo</label>
                            <select id="edit_subtipo" name="subtipo" onchange="refreshEditValues()" class="w-full bg-gray-900 border border-gray-600 rounded p-2 text-sm text-white"></select>
                        </div>
                        <div>
                            <label class="block text-gray-500 text-xs uppercase mb-1">Rango</label>
                            <input type="number" id="edit_rango" name="rango" min="1" max="8" value="1" onchange="refreshEditValues()" class="w-full bg-gray-900 border border-gray-600 rounded p-2 text-sm text-center text-white">
                        </div>
                    </div>

                    <div class="space-y-4 mb-8">
                        <div class="bg-gray-900 p-3 rounded border border-gray-700">
                            <div class="flex justify-between text-xs mb-2"><span class="text-gray-500 uppercase">Dinero ($)</span> <span id="diff_dinero" class="font-bold"></span></div>
                            <div class="flex items-center gap-4">
                                <div class="w-1/2 text-center border-r border-gray-700"><span class="block text-gray-600 text-[10px]">ACTUAL</span><span id="cur_dinero" class="text-white font-bold"></span></div>
                                <input type="number" id="new_dinero" name="dinero" oninput="calcDiffs()" class="w-1/2 bg-gray-800 border border-purple-500/30 rounded p-1 text-center text-green-400 font-bold">
                            </div>
                        </div>
                        <div class="bg-gray-900 p-3 rounded border border-gray-700">
                            <div class="flex justify-between text-xs mb-2"><span class="text-gray-500 uppercase">Acero</span> <span id="diff_acero" class="font-bold"></span></div>
                            <div class="flex items-center gap-4">
                                <div class="w-1/2 text-center border-r border-gray-700"><span class="block text-gray-600 text-[10px]">ACTUAL</span><span id="cur_acero" class="text-white font-bold"></span></div>
                                <input type="number" id="new_acero" name="acero" oninput="calcDiffs()" class="w-1/2 bg-gray-800 border border-purple-500/30 rounded p-1 text-center font-bold">
                            </div>
                        </div>
                        <div class="bg-gray-900 p-3 rounded border border-gray-700">
                            <div class="flex justify-between text-xs mb-2"><span class="text-gray-500 uppercase">Petróleo</span> <span id="diff_petroleo" class="font-bold"></span></div>
                            <div class="flex items-center gap-4">
                                <div class="w-1/2 text-center border-r border-gray-700"><span class="block text-gray-600 text-[10px]">ACTUAL</span><span id="cur_petroleo" class="text-white font-bold"></span></div>
                                <input type="number" id="new_petroleo" name="petroleo" oninput="calcDiffs()" class="w-1/2 bg-gray-800 border border-purple-500/30 rounded p-1 text-center text-yellow-500 font-bold">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-purple-600 hover:bg-purple-500 text-white font-bold py-3 rounded shadow-lg transition">
                        Actualizar Precio Global
                    </button>
                </form>
            </div>
        </div>
    </main>

    <script>
        // Pasamos TODA la lista de naciones desde PHP a JavaScript
        const todasLasNaciones = <?php echo json_encode($lista_naciones); ?>;
        
        // === LÓGICA DE PREVISIÓN DE PRECIOS EN EL MODAL ===
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
                prevDinero.innerText = 'N/A';
                prevAcero.innerText = 'N/A';
                prevPetroleo.innerText = 'N/A';
            }
        }

        // === LÓGICA DE LAS PESTAÑAS Y FILTROS EN LA TIENDA ===
        let filtroTipoActual = 'tanque';
        // Seleccionamos la primera nación de la lista global por defecto (si hay alguna)
        let filtroNacionActual = todasLasNaciones.length > 0 ? todasLasNaciones[0] : '';
        const filasVehiculos = Array.from(document.querySelectorAll('.fila-vehiculo'));

        function initTienda() {
            actualizarSubtipos(); 
            setFiltroTipo('tanque'); 
        }

        function setFiltroTipo(tipo) {
            filtroTipoActual = tipo;
            
            document.getElementById('btn_tipo_tanque').className = tipo === 'tanque' ? 'px-6 py-2 bg-purple-600 text-white font-bold rounded shadow transition' : 'px-6 py-2 bg-gray-900 hover:bg-gray-800 text-gray-400 font-bold border border-gray-700 rounded transition';
            document.getElementById('btn_tipo_avion').className = tipo === 'avion' ? 'px-6 py-2 bg-purple-600 text-white font-bold rounded shadow transition' : 'px-6 py-2 bg-gray-900 hover:bg-gray-800 text-gray-400 font-bold border border-gray-700 rounded transition';

            // Ahora siempre mandamos a renderizar la lista completa de países
            renderBotonesNaciones(todasLasNaciones);
            aplicarFiltrosTabla();
        }

        function renderBotonesNaciones(naciones) {
            const contenedor = document.getElementById('contenedor_naciones');
            contenedor.innerHTML = '';
            
            if (naciones.length === 0) {
                contenedor.innerHTML = '<span class="text-gray-600 text-sm italic">No hay países registrados en el sistema</span>';
                return;
            }

            naciones.forEach(nacion => {
                let btn = document.createElement('button');
                btn.innerText = nacion;
                btn.className = nacion === filtroNacionActual 
                    ? 'px-4 py-1.5 bg-blue-600 text-white text-sm font-bold rounded shadow transition' 
                    : 'px-4 py-1.5 bg-gray-900 hover:bg-gray-700 text-gray-400 border border-gray-600 text-sm font-bold rounded transition';
                
                btn.onclick = () => {
                    filtroNacionActual = nacion;
                    renderBotonesNaciones(naciones); // Re-renderiza para actualizar colores
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
            // Mostrar mensaje si ese país no tiene vehículos en esa categoría
            document.getElementById('mensaje_vacio').style.display = visibles === 0 ? '' : 'none';
        }

        // Funciones para el Editor de Precios
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
                else { target.innerText = ''; }
            });
        }

        // Inicializar el modal del editor al cargar
        document.addEventListener('DOMContentLoaded', () => {
            updateEditSubtipos();
        });
    </script>
</body>
</html>