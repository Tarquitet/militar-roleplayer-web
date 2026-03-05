<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') {
    header("Location: ../login.php");
    exit();
}

$root_path = "../"; // Ajuste para el include del head
require_once '../config/conexion.php';
$txt = require '../config/textos.php';

if (!isset($_GET['id'])) {
    header("Location: staff_dashboard.php");
    exit();
}

$equipo_id = (int)$_GET['id'];

try {
    $stmt_eq = $pdo->prepare("SELECT nombre_equipo FROM cuentas WHERE id = :id AND rol = 'lider'");
    $stmt_eq->execute([':id' => $equipo_id]);
    $equipo = $stmt_eq->fetch(PDO::FETCH_ASSOC);

    if (!$equipo) die("Fallo en localización de la facción.");

    $stmt_naciones = $pdo->query("SELECT nombre FROM naciones ORDER BY nombre ASC");
    $todas_las_naciones = $stmt_naciones->fetchAll(PDO::FETCH_COLUMN);

    $stmt_inv = $pdo->prepare("
        SELECT i.cantidad, c.nombre_vehiculo, c.tipo, c.subtipo, c.rango, c.nacion, c.imagen_url, c.es_premium
        FROM inventario i
        JOIN catalogo_tienda c ON i.catalogo_id = c.id
        WHERE i.cuenta_id = :id
        ORDER BY c.rango DESC
    ");
    $stmt_inv->execute([':id' => $equipo_id]);
    $inventario = $stmt_inv->fetchAll(PDO::FETCH_ASSOC);

    $stmt_flotas = $pdo->prepare("SELECT * FROM flotas WHERE cuenta_id = :id");
    $stmt_flotas->execute([':id' => $equipo_id]);
    $flotas_db = $stmt_flotas->fetchAll(PDO::FETCH_ASSOC);
    
    $flotas = [1 => null, 2 => null, 3 => null];
    foreach ($flotas_db as $f) { $flotas[$f['slot']] = $f; }

} catch (PDOException $e) { die("Error: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title><?php echo $txt['INVENTARIO_STAFF']['TITULO']; ?>: <?php echo htmlspecialchars($equipo['nombre_equipo']); ?></title>
    <?php include '../includes/head.php'; ?>
    <style>
        .premium-card { border-color: rgba(234, 179, 8, 0.4); background: linear-gradient(135deg, #1e1b1e 0%, #111827 100%); }
    </style>
</head>
<body>

    <?php include '../includes/nav_staff.php'; ?>

    <main class="p-8 max-w-7xl mx-auto">
        <div class="mb-8 flex justify-between items-center pb-4 border-b border-[var(--wood-border)]">
            <div class="flex items-center gap-6">
                <a href="staff_dashboard.php" class="btn-m !px-3 !py-1 text-sm" title="<?php echo $txt['INVENTARIO_STAFF']['BTN_VOLVER']; ?>">
                    ←
                </a>
                <div>
                    <h1 class="m-title text-3xl font-bold">
                        <?php echo $txt['INVENTARIO_STAFF']['TITULO']; ?>: <span class="text-[var(--text-main)] italic"><?php echo htmlspecialchars($equipo['nombre_equipo']); ?></span>
                    </h1>
                    <p class="text-[var(--parchment)] text-[10px] uppercase tracking-widest mt-1">
                        <?php echo $txt['INVENTARIO_STAFF']['SUBTITULO']; ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="m-panel mb-8 p-4">
            <div class="flex gap-4 mb-4 border-b border-[var(--wood-border)] pb-4">
                <button id="btn_tipo_tanque" onclick="setCategoria('tanque')" class="btn-m !text-[10px]">
                    <?php echo $txt['INVENTARIO_STAFF']['CAT_TANQUES']; ?>
                </button>
                <button id="btn_tipo_avion" onclick="setCategoria('avion')" class="btn-m !text-[10px] grayscale opacity-70">
                    <?php echo $txt['INVENTARIO_STAFF']['CAT_AVIONES']; ?>
                </button>
                <button id="btn_tipo_flota" onclick="setCategoria('flota')" class="btn-m !text-[10px] grayscale opacity-70">
                    <?php echo $txt['INVENTARIO_STAFF']['CAT_FLOTAS']; ?>
                </button>
            </div>

            <div id="nav_naciones" class="flex items-center gap-4">
                <span class="text-[var(--aoe-gold)] text-[10px] uppercase font-black tracking-widest">
                    <?php echo $txt['INVENTARIO_STAFF']['DISPONIBILIDAD']; ?>
                </span>
                <div id="contenedor_naciones" class="flex flex-wrap gap-2"></div>
            </div>
        </div>

        <div id="vista_inventario" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6"></div>

        <div id="vista_flotas" class="hidden grid grid-cols-1 lg:grid-cols-3 gap-8">
            <?php for ($i = 1; $i <= 3; $i++): ?>
                <div class="m-panel !p-0 overflow-hidden flex flex-col">
                    <div class="p-3 bg-black/50 border-b border-[var(--wood-border)] text-[var(--aoe-gold)] font-black text-[10px] uppercase tracking-[0.2em] text-center">
                        <?php echo $txt['INVENTARIO_STAFF']['SLOT_NAVAL']; ?> 0<?php echo $i; ?>
                    </div>
                    <div class="p-6 flex-grow">
                        <?php if ($flotas[$i]): ?>
                            <div class="space-y-4">
                                <div>
                                    <label class="text-[9px] text-[var(--parchment)] uppercase font-bold block mb-1 tracking-widest"><?php echo $txt['INVENTARIO_STAFF']['LBL_INSIGNIA']; ?></label>
                                    <div class="bg-black border border-[var(--wood-border)] p-2 text-[var(--text-main)] font-bold text-xs text-center shadow-inner">
                                        <?php echo htmlspecialchars($flotas[$i]['insignia']); ?>
                                    </div>
                                </div>
                                <div>
                                    <label class="text-[9px] text-[var(--parchment)] uppercase font-bold block mb-1 tracking-widest"><?php echo $txt['INVENTARIO_STAFF']['LBL_ESCOLTAS']; ?></label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <?php for($j=1; $j<=4; $j++): ?>
                                            <div class="bg-black/40 border border-[var(--wood-border)] p-2 text-gray-500 text-[10px] text-center italic shadow-inner">
                                                <?php echo htmlspecialchars($flotas[$i]["escolta_$j"] ?? '-'); ?>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="text-[10px] text-gray-600 text-center italic py-10 font-bold uppercase tracking-widest"><?php echo $txt['INVENTARIO_STAFF']['SIN_FLOTA']; ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if ($flotas[$i]): ?>
                        <div class="p-4 bg-black/50 border-t border-[var(--wood-border)]">
                            <form action="../logic/destruir_flota.php" method="POST" onsubmit="return confirm('¿Autorizar ataque táctico para destruir esta flota?');">
                                <input type="hidden" name="flota_id" value="<?php echo $flotas[$i]['id']; ?>">
                                <input type="hidden" name="equipo_id" value="<?php echo $equipo_id; ?>">
                                <button type="submit" class="w-full bg-red-950/50 hover:bg-red-900 border border-red-800 text-red-500 hover:text-red-300 font-black py-2 text-[10px] uppercase transition shadow-[inset_0_0_10px_rgba(220,38,38,0.3)]">
                                    <?php echo $txt['INVENTARIO_STAFF']['BTN_DESTRUIR']; ?>
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    </main>

    <script>
        const txt = <?php echo json_encode($txt['INVENTARIO_STAFF']); ?>;
        const todasLasNaciones = <?php echo json_encode($todas_las_naciones); ?>;
        const inventarioFull = <?php echo json_encode($inventario); ?>;
        let catActual = 'tanque';
        let nacionActual = '';

        function initInventario() { setCategoria('tanque'); }

        function setCategoria(cat) {
            catActual = cat;
            ['tanque', 'avion', 'flota'].forEach(c => {
                const btn = document.getElementById('btn_tipo_' + c);
                if(c === cat) {
                    btn.classList.remove('grayscale', 'opacity-70');
                } else {
                    btn.classList.add('grayscale', 'opacity-70');
                }
            });

            if (cat === 'flota') {
                document.getElementById('nav_naciones').classList.add('hidden');
                document.getElementById('vista_inventario').classList.add('hidden');
                document.getElementById('vista_flotas').classList.remove('hidden');
            } else {
                document.getElementById('nav_naciones').classList.remove('hidden');
                document.getElementById('vista_inventario').classList.remove('hidden');
                document.getElementById('vista_flotas').classList.add('hidden');
                
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
                
                // Estilo base
                btn.className = "px-4 py-1 text-[10px] font-black uppercase tracking-widest border transition shadow-lg ";
                
                if (n === nacionActual) {
                    btn.className += "bg-[var(--dark-olive)] text-[var(--aoe-gold)] border-[var(--aoe-gold)]"; // Activo
                } else if (tieneAlgo) {
                    btn.className += "bg-black/50 text-[var(--parchment)] border-[var(--wood-border)] hover:brightness-125"; // Inactivo con items
                } else {
                    btn.className += "bg-black/20 text-gray-700 border-gray-800 cursor-not-allowed opacity-40"; // Inactivo vacío
                }
                
                btn.onclick = () => { if(tieneAlgo || n === nacionActual) { nacionActual = n; renderNaciones(nacionesConContenido); renderInventario(); } };
                cont.appendChild(btn);
            });
        }

        function renderInventario() {
            const cont = document.getElementById('vista_inventario');
            cont.innerHTML = '';
            const filtrados = inventarioFull.filter(i => i.tipo === catActual && i.nacion === nacionActual);
            
            if (filtrados.length === 0) {
                cont.innerHTML = `<div class="col-span-full m-panel text-center text-[var(--aoe-gold)] text-xs font-bold uppercase tracking-[0.3em]">${txt.SIN_ACTIVOS} ${nacionActual}</div>`;
                return;
            }

            filtrados.forEach(i => {
                const isPremium = parseInt(i.es_premium) === 1;
                const borderClass = isPremium ? 'premium-card' : 'bg-black border border-[var(--wood-border)]';
                
                cont.innerHTML += `
                    <div class="relative rounded overflow-hidden shadow-2xl transition hover:brightness-110 ${borderClass}">
                        <div class="h-28 bg-[#0d0e0a] relative border-b border-[var(--wood-border)]">
                            ${i.imagen_url ? `<img src="../${i.imagen_url}" class="w-full h-full object-cover">` : `<div class="h-full flex items-center justify-center text-[9px] text-gray-700 font-bold uppercase">${txt.NO_IMG}</div>`}
                            <div class="absolute top-1 right-1 bg-black/80 text-[var(--aoe-gold)] text-[9px] px-2 py-0.5 border border-[var(--wood-border)] font-black">T-${i.rango}</div>
                        </div>
                        <div class="p-4 bg-[var(--dark-olive)]">
                            <span class="text-[8px] text-[var(--parchment)] font-black uppercase tracking-widest block mb-1">${i.subtipo}</span>
                            <h3 class="text-sm font-bold text-[var(--text-main)] truncate font-['Cinzel'] mb-3">${i.nombre_vehiculo}</h3>
                            <div class="flex justify-between items-center border-t border-[var(--wood-border)] pt-3">
                                <span class="text-gray-500 text-[8px] font-bold uppercase tracking-widest">${txt.LBL_CANTIDAD}</span>
                                <span class="text-[var(--aoe-gold)] font-black text-lg text-shadow-sm">${i.cantidad}</span>
                            </div>
                        </div>
                    </div>`;
            });
        }
    </script>
</body>
</html>