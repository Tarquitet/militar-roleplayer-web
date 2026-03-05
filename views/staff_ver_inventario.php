<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') {
    header("Location: ../login.php");
    exit();
}
require_once '../config/conexion.php';

if (!isset($_GET['id'])) {
    header("Location: staff_dashboard.php");
    exit();
}

$equipo_id = (int)$_GET['id'];

try {
    // 1. Datos del equipo
    $stmt_eq = $pdo->prepare("SELECT nombre_equipo FROM cuentas WHERE id = :id AND rol = 'lider'");
    $stmt_eq->execute([':id' => $equipo_id]);
    $equipo = $stmt_eq->fetch(PDO::FETCH_ASSOC);

    if (!$equipo) die("Equipo no encontrado.");

    // 2. Lista Global de Naciones para los botones
    $stmt_naciones = $pdo->query("SELECT nombre FROM naciones ORDER BY nombre ASC");
    $todas_las_naciones = $stmt_naciones->fetchAll(PDO::FETCH_COLUMN);

    // 3. Inventario completo del equipo
    $stmt_inv = $pdo->prepare("
        SELECT i.cantidad, c.nombre_vehiculo, c.tipo, c.subtipo, c.rango, c.nacion, c.imagen_url 
        FROM inventario i
        JOIN catalogo_tienda c ON i.catalogo_id = c.id
        WHERE i.cuenta_id = :id
        ORDER BY c.rango DESC
    ");
    $stmt_inv->execute([':id' => $equipo_id]);
    $inventario = $stmt_inv->fetchAll(PDO::FETCH_ASSOC);

    // 4. Flotas
    $stmt_flotas = $pdo->prepare("SELECT * FROM flotas WHERE cuenta_id = :id");
    $stmt_flotas->execute([':id' => $equipo_id]);
    $flotas_db = $stmt_flotas->fetchAll(PDO::FETCH_ASSOC);
    
    $flotas = [1 => null, 2 => null, 3 => null];
    foreach ($flotas_db as $f) { $flotas[$f['slot']] = $f; }

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario: <?php echo htmlspecialchars($equipo['nombre_equipo']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-200 min-h-screen pb-10" onload="initInventario()">

    <?php include '../includes/nav_staff.php'; ?>

    <main class="p-8 max-w-7xl mx-auto">
        <div class="mb-8 flex justify-between items-center">
            <div class="flex items-center gap-6">
                <a href="staff_dashboard.php" class="bg-gray-800 hover:bg-gray-700 text-gray-400 p-2 rounded-full transition border border-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-white">Inventario de <span class="text-blue-400"><?php echo htmlspecialchars($equipo['nombre_equipo']); ?></span></h1>
                    <p class="text-gray-500 text-sm uppercase tracking-widest">Activos militares de la facción</p>
                </div>
            </div>
        </div>

        <div class="mb-6 bg-gray-800 p-4 rounded-lg border border-gray-700 shadow-xl">
            <div class="flex gap-4 mb-4 border-b border-gray-700 pb-4">
                <button id="btn_tipo_tanque" onclick="setCategoria('tanque')" class="px-6 py-2 bg-purple-600 text-white font-bold rounded shadow transition flex items-center gap-2 text-xs">
                    TANQUES
                </button>
                <button id="btn_tipo_avion" onclick="setCategoria('avion')" class="px-6 py-2 bg-gray-900 text-gray-500 font-bold border border-gray-700 rounded hover:bg-gray-700 transition flex items-center gap-2 text-xs">
                    AVIONES
                </button>
                <button id="btn_tipo_flota" onclick="setCategoria('flota')" class="px-6 py-2 bg-gray-900 text-gray-500 font-bold border border-gray-700 rounded hover:bg-gray-700 transition flex items-center gap-2 text-xs">
                    FLOTAS NAVALES
                </button>
            </div>

            <div id="nav_naciones" class="flex items-center gap-2">
                <span class="text-gray-600 text-[10px] uppercase font-black tracking-widest mr-2">Disponibilidad Global:</span>
                <div id="contenedor_naciones" class="flex flex-wrap gap-2"></div>
            </div>
        </div>

        <div id="vista_inventario" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6"></div>

        <div id="vista_flotas" class="hidden grid grid-cols-1 lg:grid-cols-3 gap-8">
            <?php for ($i = 1; $i <= 3; $i++): ?>
                <div class="bg-gray-800 rounded border border-gray-700 shadow-2xl overflow-hidden flex flex-col">
                    <div class="p-3 bg-gray-900 border-b border-gray-700 text-blue-400 font-black text-[10px] uppercase tracking-tighter">
                        Slot Naval 0<?php echo $i; ?>
                    </div>
                    <div class="p-6 flex-grow">
                        <?php if ($flotas[$i]): ?>
                            <div class="space-y-4">
                                <div>
                                    <label class="text-[9px] text-gray-500 uppercase font-bold block mb-1">Insignia</label>
                                    <div class="bg-gray-900 p-2 rounded border border-gray-700 text-white font-bold text-xs"><?php echo htmlspecialchars($flotas[$i]['insignia']); ?></div>
                                </div>
                                <div>
                                    <label class="text-[9px] text-gray-500 uppercase font-bold block mb-1">Escoltas</label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <?php for($j=1; $j<=4; $j++): ?>
                                            <div class="bg-gray-900/50 p-2 rounded border border-gray-800 text-gray-400 text-[10px]">
                                                <?php echo htmlspecialchars($flotas[$i]["escolta_$j"] ?? '-'); ?>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="text-[10px] text-gray-600 text-center italic py-10">Sin flota desplegada</p>
                        <?php endif; ?>
                    </div>
                    <?php if ($flotas[$i]): ?>
                        <div class="p-4 bg-gray-900/50 border-t border-gray-700">
                            <form action="../logic/destruir_flota.php" method="POST" onsubmit="return confirm('¿Destruir flota?');">
                                <input type="hidden" name="flota_id" value="<?php echo $flotas[$i]['id']; ?>">
                                <input type="hidden" name="equipo_id" value="<?php echo $equipo_id; ?>">
                                <button type="submit" class="w-full bg-red-600/10 hover:bg-red-600 text-red-500 hover:text-white border border-red-600/30 font-black py-2 rounded text-[10px] uppercase transition">
                                    Destruir Flota
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    </main>

    <script>
        const todasLasNaciones = <?php echo json_encode($todas_las_naciones); ?>;
        const inventarioFull = <?php echo json_encode($inventario); ?>;
        let catActual = 'tanque';
        let nacionActual = '';

        function initInventario() {
            setCategoria('tanque');
        }

        function setCategoria(cat) {
            catActual = cat;
            ['tanque', 'avion', 'flota'].forEach(c => {
                const btn = document.getElementById('btn_tipo_' + c);
                btn.className = (c === cat) ? "px-6 py-2 bg-purple-600 text-white font-bold rounded shadow transition text-xs" : "px-6 py-2 bg-gray-900 text-gray-500 font-bold border border-gray-700 rounded hover:bg-gray-700 transition text-xs";
            });

            if (cat === 'flota') {
                document.getElementById('nav_naciones').classList.add('hidden');
                document.getElementById('vista_inventario').classList.add('hidden');
                document.getElementById('vista_flotas').classList.remove('hidden');
            } else {
                document.getElementById('nav_naciones').classList.remove('hidden');
                document.getElementById('vista_inventario').classList.remove('hidden');
                document.getElementById('vista_flotas').classList.add('hidden');
                
                // Buscar la primera nación que tenga contenido para mostrar algo al inicio
                const conContenido = [...new Set(inventarioFull.filter(i => i.tipo === cat).map(i => i.nacion))];
                nacionActual = conContenido.length > 0 ? conContenido[0] : todasLasNaciones[0];
                
                renderNaciones(conContenido);
                renderInventario();
            }
        }

        function renderNaciones(nacionesConContenido) {
            const cont = document.getElementById('contenedor_naciones');
            cont.innerHTML = '';
            
            todasLasNaciones.forEach(n => {
                const tieneAlgo = nacionesConContenido.includes(n);
                const btn = document.createElement('button');
                btn.innerText = n;
                
                if (n === nacionActual) {
                    btn.className = "px-4 py-1 bg-blue-600 text-white text-[10px] font-black rounded transition shadow-lg";
                } else if (tieneAlgo) {
                    btn.className = "px-4 py-1 bg-gray-800 text-blue-400 text-[10px] font-black border border-blue-900/50 rounded hover:bg-gray-700 transition";
                } else {
                    // Estilo Deshabilitado / Gris
                    btn.className = "px-4 py-1 bg-gray-900/50 text-gray-700 text-[10px] font-bold border border-gray-800 rounded cursor-not-allowed opacity-50";
                }
                
                btn.onclick = () => { nacionActual = n; renderNaciones(nacionesConContenido); renderInventario(); };
                cont.appendChild(btn);
            });
        }

        function renderInventario() {
            const cont = document.getElementById('vista_inventario');
            cont.innerHTML = '';
            const filtrados = inventarioFull.filter(i => i.tipo === catActual && i.nacion === nacionActual);
            
            if (filtrados.length === 0) {
                cont.innerHTML = '<div class="col-span-full py-20 text-center text-gray-700 text-xs font-bold uppercase tracking-widest border-2 border-dashed border-gray-800 rounded-lg">Sin activos militares en ' + nacionActual + '</div>';
                return;
            }

            filtrados.forEach(i => {
                cont.innerHTML += `
                    <div class="bg-gray-800 rounded border border-gray-700 overflow-hidden shadow-xl hover:border-purple-500/50 transition group">
                        <div class="h-28 bg-gray-900 relative">
                            ${i.imagen_url ? `<img src="../${i.imagen_url}" class="w-full h-full object-cover">` : `<div class="h-full flex items-center justify-center text-[9px] text-gray-700">NO IMG</div>`}
                            <div class="absolute top-2 right-2 bg-black/80 text-white text-[9px] px-2 py-0.5 rounded border border-white/10 font-black">T-${i.rango}</div>
                        </div>
                        <div class="p-3">
                            <span class="text-[8px] text-purple-500 font-black uppercase">${i.subtipo}</span>
                            <h3 class="text-sm font-bold text-white truncate">${i.nombre_vehiculo}</h3>
                            <div class="flex justify-between items-center mt-2 border-t border-gray-700 pt-2">
                                <span class="text-gray-600 text-[9px] font-bold">CANTIDAD</span>
                                <span class="text-green-400 font-black text-lg">${i.cantidad}</span>
                            </div>
                        </div>
                    </div>`;
            });
        }
    </script>
</body>
</html>