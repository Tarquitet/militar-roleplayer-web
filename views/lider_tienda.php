<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'lider') {
    header("Location: ../login.php");
    exit();
}
require_once '../config/conexion.php';

$precios_base = require '../config/precios.php';
$precios_json = json_encode($precios_base);

$lider_id = $_SESSION['usuario_id'];

try {
    // 1. Recursos y naciones del líder
    $stmt_user = $pdo->prepare("SELECT dinero, acero, petroleo, naciones_activas FROM cuentas WHERE id = :id");
    $stmt_user->execute([':id' => $lider_id]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

    $naciones_mando = !empty($user['naciones_activas']) ? array_map('trim', explode(',', $user['naciones_activas'])) : [];

    // 2. Traer TODAS las naciones del servidor para el menú
    $stmt_naciones = $pdo->query("SELECT nombre FROM naciones ORDER BY nombre ASC");
    $naciones_totales = $stmt_naciones->fetchAll(PDO::FETCH_COLUMN);

    // 3. Traer el catálogo COMPLETO sin filtros iniciales
    $stmt_cat = $pdo->query("SELECT * FROM catalogo_tienda ORDER BY rango ASC, es_premium ASC");
    $catalogo_completo = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Hangar Tecnológico - <?php echo $_SESSION['usuario']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .modal-active { overflow: hidden; }
        .nation-grayscale { filter: grayscale(1) opacity(0.4); }
        .premium-bg { background: linear-gradient(135deg, #1e1b1e 0%, #111827 100%); border-color: rgba(234, 179, 8, 0.3); }
        .rank-title { writing-mode: vertical-lr; transform: rotate(180deg); }
    </style>
</head>
<body class="bg-[#0b1120] text-gray-300 min-h-screen pb-20" onload="initTienda()">

    <?php include '../includes/nav_lider.php'; ?>

    <nav class="bg-[#111827] border-b border-gray-800 sticky top-0 z-40 overflow-x-auto">
        <div class="flex px-4">
            <?php foreach ($naciones_totales as $n): 
                $bajo_mando = in_array($n, $naciones_mando); ?>
                <button onclick="setNacion('<?php echo $n; ?>')" 
                        data-nacion-btn="<?php echo $n; ?>"
                        class="px-6 py-4 text-[10px] font-black uppercase tracking-widest border-b-2 transition-all duration-300
                        <?php echo $bajo_mando ? 'text-white border-transparent' : 'nation-grayscale border-transparent'; ?>">
                    <?php echo $n; ?>
                </button>
            <?php endforeach; ?>
        </div>
    </nav>

    <div class="bg-[#0f172a] border-b border-gray-800 p-4 shadow-xl">
        <div class="max-w-[1600px] mx-auto flex justify-between items-center">
            <div class="flex gap-4">
                <button id="btn_tipo_tanque" onclick="setFiltroTipo('tanque')" class="px-6 py-1 bg-blue-600 text-white text-[10px] font-black rounded uppercase italic">🛡️ Tanques</button>
                <button id="btn_tipo_avion" onclick="setFiltroTipo('avion')" class="px-6 py-1 bg-gray-900 text-gray-500 text-[10px] font-black border border-gray-700 rounded uppercase italic">✈️ Aviones</button>
                <button onclick="abrirModal('modalNuevoVehiculo')" class="ml-4 bg-indigo-900/40 hover:bg-indigo-600 text-indigo-400 hover:text-white text-[9px] font-black px-4 py-1 rounded border border-indigo-500/30 transition uppercase">Añadir al Árbol</button>
            </div>
            <div class="flex gap-10">
                <div class="text-right"><span class="block text-[8px] text-gray-500 uppercase font-bold">Capital</span><span class="text-green-400 font-black text-sm">$<?php echo number_format($user['dinero']); ?></span></div>
                <div class="text-right"><span class="block text-[8px] text-gray-500 uppercase font-bold">Reserva Acero</span><span class="text-white font-black text-sm"><?php echo number_format($user['acero']); ?> t</span></div>
                <div class="text-right"><span class="block text-[8px] text-gray-500 uppercase font-bold">Combustible</span><span class="text-yellow-500 font-black text-sm"><?php echo number_format($user['petroleo']); ?> L</span></div>
            </div>
        </div>
    </div>

    <main class="p-8 max-w-[1600px] mx-auto">
        <div class="flex flex-col lg:flex-row gap-10">
            
            <div class="flex-1">
                <h2 class="text-[10px] font-black uppercase tracking-[0.4em] text-gray-600 mb-8 flex items-center gap-4">
                    <span class="h-px bg-gray-800 flex-1"></span> Investigables <span class="h-px bg-gray-800 flex-1"></span>
                </h2>
                
                <div id="contenedor_investigacion" class="space-y-12">
                    </div>
            </div>

            <div class="w-full lg:w-80">
                <h2 class="text-[10px] font-black uppercase tracking-[0.4em] text-yellow-600/50 mb-8 text-center">Premium</h2>
                <div id="contenedor_premium" class="grid grid-cols-1 gap-4">
                    </div>
            </div>

        </div>
    </main>

    <div id="modalNuevoVehiculo" class="hidden fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="bg-gray-800 p-8 rounded-lg border border-gray-600 shadow-2xl w-full max-w-md relative">
            <button onclick="cerrarModal('modalNuevoVehiculo')" class="absolute top-4 right-4 text-gray-500 hover:text-white font-bold text-xl">&times;</button>
            <h2 class="text-xl font-black text-white mb-6 border-b border-gray-700 pb-2 uppercase italic">Añadir Suministro al Árbol</h2>
            <form action="../logic/procesar_tienda_lider.php" method="POST" enctype="multipart/form-data">
                <div class="mb-4 flex gap-4">
                    <div class="w-1/2">
                        <label class="block text-gray-500 text-[9px] uppercase font-black mb-1">Categoría</label>
                        <select id="tipo_vehiculo" name="tipo" onchange="actualizarSubtipos()" class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-2 text-white text-xs outline-none focus:border-blue-500">
                            <option value="tanque">Tanque</option>
                            <option value="avion">Avión</option>
                        </select>
                    </div>
                    <div class="w-1/2">
                        <label class="block text-gray-500 text-[9px] uppercase font-black mb-1">Clasificación</label>
                        <select id="subtipo_vehiculo" name="subtipo" onchange="actualizarPrecioPreview()" class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-2 text-white text-xs outline-none"></select>
                    </div>
                </div>
                <div class="mb-4 flex gap-4">
                    <div class="w-1/2">
                        <label class="block text-gray-500 text-[9px] uppercase font-black mb-1">Nación</label>
                        <select name="nacion" class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-2 text-white text-xs">
                            <?php foreach ($naciones_mando as $n): ?>
                                <option value="<?php echo htmlspecialchars($n); ?>"><?php echo htmlspecialchars($n); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-1/2">
                        <label class="block text-gray-500 text-[9px] uppercase font-black mb-1">Rango (1-8)</label>
                        <input type="number" id="rango_vehiculo" name="rango" min="1" max="8" value="1" onchange="actualizarPrecioPreview()" class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-2 text-white text-center text-xs outline-none">
                    </div>
                </div>
                <div class="mb-4 bg-gray-900 p-4 rounded border border-gray-700 shadow-inner">
                    <div class="flex justify-between items-center text-center">
                        <div><span class="block text-[8px] text-gray-500 uppercase">Dinero</span><span id="prev_dinero" class="text-green-400 font-black text-sm">...</span></div>
                        <div><span class="block text-[8px] text-gray-500 uppercase">Acero</span><span id="prev_acero" class="text-white font-black text-sm">...</span></div>
                        <div><span class="block text-[8px] text-gray-500 uppercase">Petróleo</span><span id="prev_petroleo" class="text-yellow-500 font-black text-sm">...</span></div>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-500 text-[9px] uppercase font-black mb-1">Nombre</label>
                    <input type="text" name="nombre_vehiculo" required class="w-full bg-gray-900 border border-gray-700 rounded px-3 py-2 text-white text-xs">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-500 text-[9px] uppercase font-black mb-1">Imagen</label>
                    <input type="file" name="imagen" accept="image/*" class="w-full bg-gray-900 border border-gray-700 rounded p-1 text-[9px] text-gray-500 file:bg-blue-600 file:border-0 file:text-white file:px-2 file:py-1 file:rounded">
                </div>
                <div class="mb-6 p-3 bg-indigo-900/20 border border-indigo-500/30 rounded flex items-center justify-between">
                    <span class="text-[9px] text-indigo-400 font-black uppercase">¿Activo Premium?</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="es_premium" value="1" class="sr-only peer">
                        <div class="w-9 h-5 bg-gray-700 rounded-full peer peer-checked:bg-indigo-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full"></div>
                    </label>
                </div>
                <button type="submit" class="w-full bg-green-600 text-white font-black py-3 rounded text-[10px] uppercase tracking-widest transition shadow-xl">Confirmar Registro</button>
            </form>
        </div>
    </div>

    

    <script>
        const catalogoCompleto = <?php echo json_encode($catalogo_completo); ?>;
        const nacionesMando = <?php echo json_encode($naciones_mando); ?>;
        const preciosBase = <?php echo $precios_json; ?>;
        
        let nacionActual = nacionesMando[0] || '<?php echo $naciones_totales[0]; ?>';
        let tipoActual = 'tanque';

        function initTienda() {
            actualizarSubtipos();
            renderTienda();
        }

        function setNacion(n) {
            nacionActual = n;
            renderTienda();
        }

        function setFiltroTipo(t) {
            tipoActual = t;
            renderTienda();
        }

        function renderTienda() {
            const investigacion = document.getElementById('contenedor_investigacion');
            const premiumCont = document.getElementById('contenedor_premium');
            investigacion.innerHTML = '';
            premiumCont.innerHTML = '';

            // Actualizar UI de botones de nación
            document.querySelectorAll('[data-nacion-btn]').forEach(btn => {
                const n = btn.getAttribute('data-nacion-btn');
                const mando = nacionesMando.includes(n);
                if (n === nacionActual) {
                    btn.classList.add('border-blue-500', 'text-white');
                    btn.classList.remove('border-transparent');
                } else {
                    btn.classList.remove('border-blue-500', 'text-white');
                    btn.classList.add('border-transparent');
                }
            });

            // Actualizar botones de tipo
            document.getElementById('btn_tipo_tanque').className = tipoActual === 'tanque' ? 'px-6 py-1 bg-blue-600 text-white text-[10px] font-black rounded uppercase italic' : 'px-6 py-1 bg-gray-900 text-gray-500 text-[10px] font-black border border-gray-700 rounded uppercase italic';
            document.getElementById('btn_tipo_avion').className = tipoActual === 'avion' ? 'px-6 py-1 bg-blue-600 text-white text-[10px] font-black rounded uppercase italic' : 'px-6 py-1 bg-gray-900 text-gray-500 text-[10px] font-black border border-gray-700 rounded uppercase italic';

            // Filtrar y agrupar
            const items = catalogoCompleto.filter(i => i.nacion === nacionActual && i.tipo === tipoActual);
            const mandoActivo = nacionesMando.includes(nacionActual);

            // Render Investigables por Rango
            for (let r = 1; r <= 8; r++) {
                const rankItems = items.filter(i => parseInt(i.rango) === r && !parseInt(i.es_premium));
                if (rankItems.length > 0) {
                    const rankDiv = document.createElement('div');
                    rankDiv.className = "flex items-center gap-6";
                    rankDiv.innerHTML = `
                        <div class="rank-title text-gray-700 font-black text-[10px] uppercase tracking-[1em] border-r border-gray-800 pr-4">Rank ${r}</div>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 flex-1">
                            ${rankItems.map(i => renderCard(i, mandoActivo)).join('')}
                        </div>
                    `;
                    investigacion.appendChild(rankDiv);
                }
            }

            // Render Premium
            const premiumItems = items.filter(i => parseInt(i.es_premium));
            premiumCont.innerHTML = premiumItems.length > 0 
                ? premiumItems.map(i => renderCard(i, mandoActivo, true)).join('') 
                : `<div class="py-10 text-center text-gray-800 text-[9px] font-bold uppercase tracking-widest border border-dashed border-gray-800 rounded">Sin Activos de Élite</div>`;
        }

        function renderCard(item, mando, isPremium = false) {
            const grayscaleClass = mando ? '' : 'filter grayscale opacity-30 pointer-events-none';
            const borderClass = isPremium ? 'border-yellow-600/40 premium-bg' : 'border-gray-800 bg-[#111827]';
            
            return `
                <div class="relative group rounded border ${borderClass} overflow-hidden shadow-2xl transition-all duration-300 hover:scale-105 hover:z-10 ${grayscaleClass}">
                    <div class="h-24 bg-black/40 overflow-hidden relative">
                        ${item.imagen_url ? `<img src="../${item.imagen_url}" class="w-full h-full object-cover">` : `<div class="w-full h-full flex items-center justify-center text-[8px] text-gray-800 font-black">NO IMAGE</div>`}
                        <div class="absolute top-1 right-1 bg-black/60 text-[8px] text-white px-1 font-black rounded border border-white/10">T-${item.rango}</div>
                        ${isPremium ? `<div class="absolute bottom-1 left-1 bg-yellow-600 text-black text-[7px] px-1 font-black rounded uppercase">Premium</div>` : ''}
                    </div>
                    <div class="p-3">
                        <div class="text-[7px] text-blue-500 font-black uppercase mb-1 truncate">${item.subtipo}</div>
                        <div class="text-[9px] text-white font-bold leading-none mb-3 h-5 overflow-hidden">${item.nombre_vehiculo}</div>
                        
                        <form action="../logic/procesar_compra.php" method="POST" class="space-y-2">
                            <input type="hidden" name="catalogo_id" value="${item.id}">
                            <div class="flex items-center bg-black/50 rounded border border-gray-800 overflow-hidden">
                                <span class="px-2 text-[8px] font-black text-gray-600">QTY</span>
                                <input type="number" name="cantidad" value="1" min="1" class="w-full bg-transparent text-[10px] text-white font-black p-1 outline-none text-center">
                            </div>
                            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white text-[8px] font-black py-1.5 rounded uppercase tracking-tighter">
                                ADQUIRIR
                            </button>
                        </form>
                    </div>
                </div>
            `;
        }

        // Modales y Precios
        function abrirModal(id) { document.getElementById(id).classList.remove('hidden'); document.body.classList.add('modal-active'); }
        function cerrarModal(id) { document.getElementById(id).classList.add('hidden'); document.body.classList.remove('modal-active'); }
        
        function actualizarSubtipos() {
            const tipo = document.getElementById('tipo_vehiculo').value;
            const select = document.getElementById('subtipo_vehiculo');
            select.innerHTML = '';
            let opciones = tipo === 'tanque' ? ['Ligero', 'AAA', 'Mediano', 'Pesado'] : ['Caza', 'Interceptor', 'Bombardero', 'Avión de ataque'];
            opciones.forEach(op => { select.add(new Option(op, op)); });
            actualizarPrecioPreview();
        }

        function actualizarPrecioPreview() {
            const tipo = document.getElementById('tipo_vehiculo').value;
            const subtipo = document.getElementById('subtipo_vehiculo').value;
            const rango = document.getElementById('rango_vehiculo').value;
            const prevD = document.getElementById('prev_dinero');
            const prevA = document.getElementById('prev_acero');
            const prevP = document.getElementById('prev_petroleo');
            if (preciosBase[tipo] && preciosBase[tipo][subtipo] && preciosBase[tipo][subtipo][rango]) {
                const c = preciosBase[tipo][subtipo][rango];
                prevD.innerText = '$' + c.dinero; prevA.innerText = c.acero + 't'; prevP.innerText = c.petroleo + 'L';
            }
        }
    </script>
</body>
</html>