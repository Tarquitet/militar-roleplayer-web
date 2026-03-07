<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') {
    header("Location: ../login.php");
    exit();
}

$root_path = "../";
require_once '../config/conexion.php';
$txt = require '../config/textos.php';

try {
    $stmt = $pdo->query("SELECT id, nombre_equipo, dinero, acero, petroleo, naciones_activas FROM cuentas WHERE rol = 'lider' ORDER BY id ASC");
    $equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_naciones = $pdo->query("SELECT id, nombre FROM naciones ORDER BY nombre ASC");
    $lista_naciones = $stmt_naciones->fetchAll(PDO::FETCH_ASSOC);

    // OPTIMIZACIÓN DE BASE DE DATOS: Cierre táctico de cursores y conexión
    $stmt = null;
    $stmt_naciones = null;
    $pdo = null;

} catch (PDOException $e) {
    die("Fallo en red de inteligencia: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title><?php echo $txt['GLOBAL']['MANDO_STAFF']; ?> - Dashboard</title>
    <?php include '../includes/head.php'; ?>
    <style>
        .modal-active { overflow: hidden; }
        .input-recurso { width: 80px; font-family: 'Cinzel', serif; }
        .col-naciones { max-width: 180px; }
    </style>
</head>
<body class="pb-10">

    <?php include '../includes/nav_staff.php'; ?>

    <main class="p-8 max-w-[98%] mx-auto">
        
        <div class="mb-8 flex justify-between items-end border-b border-[var(--wood-border)] pb-4">
            <div>
                <h1 class="m-title text-3xl font-bold mb-2"><?php echo $txt['STAFF_DASHBOARD']['TITULO']; ?></h1>
                <p class="text-[var(--parchment)] text-xs uppercase tracking-widest font-bold">
                    <?php echo $txt['STAFF_DASHBOARD']['SUBTITULO']; ?>
                </p>
            </div>
            <div class="flex gap-4">
                <button onclick="abrirModal('modalGlobalPaises')" class="btn-m !text-[10px] flex items-center gap-2">
                    🌍 <?php echo $txt['STAFF_DASHBOARD']['BTN_PAISES']; ?>
                </button>
                <form id="formNuke" action="../logic/nuke_reboot.php" method="POST">
                    <button type="button" onclick="abrirModal('modalNuke')" 
                            class="btn-m !bg-none !bg-red-950 !border-red-600 !text-red-500 hover:!bg-red-900 hover:!text-white !text-[10px] flex items-center gap-2 shadow-[0_0_15px_rgba(220,38,38,0.4)]">
                        ☢️ <?php echo $txt['STAFF_DASHBOARD']['BTN_NUKE']; ?>
                    </button>
                </form>
            </div>
        </div>

        <?php if (isset($_GET['mensaje']) && $_GET['mensaje'] == 'ok'): ?>
            <div class="bg-[var(--olive-drab)] text-[var(--aoe-gold)] border border-[var(--aoe-gold)] p-3 mb-6 text-xs font-bold tracking-widest uppercase shadow-lg text-center">
                Base de datos actualizada con éxito.
            </div>
        <?php endif; ?>

        <div class="m-panel !p-0 overflow-hidden mb-32">
            <table class="w-full text-left border-collapse table-m">
                <thead>
                    <tr class="text-[9px] uppercase tracking-widest">
                        <th class="p-4 border-b border-[var(--wood-border)]/50 text-center uppercase tracking-widest text-[9px]"><?php echo $txt['STAFF_DASHBOARD']['TH_PASSWORD']; ?></th>
                        <th class="p-4"><?php echo $txt['STAFF_DASHBOARD']['TH_EQUIPO']; ?></th>
                        <th class="p-4 text-center text-green-500"><?php echo $txt['STAFF_DASHBOARD']['TH_DINERO']; ?></th>
                        <th class="p-4 text-center text-white"><?php echo $txt['STAFF_DASHBOARD']['TH_ACERO']; ?></th>
                        <th class="p-4 text-center text-yellow-500"><?php echo $txt['STAFF_DASHBOARD']['TH_PETROLEO']; ?></th>
                        <th class="p-4"><?php echo $txt['STAFF_DASHBOARD']['TH_NACIONES']; ?></th>
                        <th class="p-4 text-center"><?php echo $txt['STAFF_DASHBOARD']['TH_GESTION']; ?></th>
                        <th class="p-4 text-center"><?php echo $txt['STAFF_DASHBOARD']['TH_ACCIONES']; ?></th>
                    </tr>
                </thead>
                <tbody class="text-[var(--text-main)] text-sm">
                    <?php foreach ($equipos as $equipo): ?>
                        <tr class="transition hover:bg-white/5">
                            <form action="../logic/actualizar_recursos.php" method="POST">
                                <input type="hidden" name="equipo_id" value="<?php echo $equipo['id']; ?>">
                                
                                <td class="p-3">
                                    <input type="text" name="nombre_equipo" value="<?php echo htmlspecialchars($equipo['nombre_equipo'] ?? ''); ?>" 
                                           class="m-input w-full text-xs outline-none focus:border-[var(--aoe-gold)]">
                                </td>
                                <td class="p-3 text-center border-r border-[var(--wood-border)]/30">
                                    <input type="text" name="nueva_password" 
                                        placeholder="<?php echo $txt['STAFF_DASHBOARD']['PH_PASSWORD']; ?>" 
                                        class="m-input w-24 text-[10px] text-center outline-none focus:border-[var(--aoe-gold)] placeholder:opacity-30">
                                </td>
                                <td class="p-3 text-center">
                                    <input type="number" name="dinero" value="<?php echo $equipo['dinero']; ?>" 
                                           class="m-input input-recurso text-green-500 font-black text-center outline-none focus:border-[var(--aoe-gold)]">
                                </td>
                                <td class="p-3 text-center">
                                    <input type="number" name="acero" value="<?php echo $equipo['acero']; ?>" 
                                           class="m-input input-recurso text-white font-black text-center outline-none focus:border-[var(--aoe-gold)]">
                                </td>
                                <td class="p-3 text-center">
                                    <input type="number" name="petroleo" value="<?php echo $equipo['petroleo']; ?>" 
                                           class="m-input input-recurso text-yellow-500 font-black text-center outline-none focus:border-[var(--aoe-gold)]">
                                </td>
                                <td class="p-3 col-naciones">
                                    <span id="text-naciones-<?php echo $equipo['id']; ?>" class="text-[10px] text-[var(--parchment)] font-bold uppercase tracking-widest block truncate" title="<?php echo htmlspecialchars($equipo['naciones_activas'] ?? ''); ?>">
                                        <?php echo !empty($equipo['naciones_activas']) ? htmlspecialchars($equipo['naciones_activas']) : $txt['STAFF_DASHBOARD']['SIN_NACIONES']; ?>
                                    </span>
                                    <input type="hidden" 
                                        id="input-naciones-<?php echo $equipo['id']; ?>" 
                                        name="naciones_activas_string" 
                                        value="<?php echo htmlspecialchars($equipo['naciones_activas'] ?? ''); ?>">
                                </td>
                                <td class="p-3 text-center">
                                    <button type="button" onclick="abrirModalNaciones(<?php echo $equipo['id']; ?>, '<?php echo htmlspecialchars($equipo['nombre_equipo'] ?? 'Comando ' . $equipo['id']); ?>')" 
                                            class="btn-m !bg-none !border-[var(--khaki-beige)] !text-[var(--parchment)] hover:!text-[var(--aoe-gold)] hover:!border-[var(--aoe-gold)] !py-1 !px-2 !text-[9px]">
                                        <?php echo $txt['STAFF_DASHBOARD']['BTN_EDITAR']; ?>
                                    </button>
                                </td>
                                <td class="p-3 text-center flex justify-center gap-2">
                                    <button type="submit" class="btn-m !py-1 !px-2 !text-[9px]">
                                        <?php echo $txt['STAFF_DASHBOARD']['BTN_GUARDAR']; ?>
                                    </button>
                                    <a href="staff_ver_inventario.php?id=<?php echo $equipo['id']; ?>" class="btn-m !bg-none !border-[#5865F2] !text-[#5865F2] hover:!bg-[#5865F2] hover:!text-white !py-1 !px-2 !text-[9px]">
                                        <?php echo $txt['STAFF_DASHBOARD']['BTN_INVENTARIO']; ?>
                                    </a>
                                </td>
                            </form>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div id="modalSeleccionNaciones" class="hidden fixed inset-0 bg-black/90 z-[70] flex items-center justify-center p-4">
        <div class="m-panel border-[var(--aoe-gold)] w-full max-w-xs">
            <div class="border-b border-[var(--wood-border)] pb-2 mb-4 flex justify-between items-center">
                <h3 class="m-title text-[10px] uppercase tracking-widest" id="tituloModalNaciones"><?php echo $txt['STAFF_DASHBOARD']['MODAL_NAC_TITULO']; ?></h3>
                <button onclick="cerrarModal('modalSeleccionNaciones')" class="text-[var(--parchment)] hover:text-white">&times;</button>
            </div>
            
            <div id="listaCheckboxesNaciones" class="max-h-60 overflow-y-auto space-y-1 mb-6 pr-2">
                <?php foreach ($lista_naciones as $n): ?>
                <label class="flex items-center gap-3 p-2 hover:bg-black/40 border border-transparent hover:border-[var(--wood-border)] cursor-pointer transition group">
                    <input type="checkbox" class="check-nacion form-checkbox h-4 w-4 bg-black border-[var(--wood-border)] checked:bg-[var(--olive-drab)]" value="<?php echo htmlspecialchars($n['nombre']); ?>">
                    <span class="text-xs text-[var(--parchment)] uppercase font-bold tracking-widest group-hover:text-[var(--aoe-gold)]"><?php echo htmlspecialchars($n['nombre']); ?></span>
                </label>
                <?php endforeach; ?>
            </div>
            
            <button onclick="confirmarSeleccionNaciones()" class="btn-m w-full py-3 text-[10px] tracking-widest">
                <?php echo $txt['STAFF_DASHBOARD']['BTN_CONFIRMAR']; ?>
            </button>
        </div>
    </div>

    <div id="modalGlobalPaises" class="hidden fixed inset-0 bg-black/90 z-[60] flex items-center justify-center p-4">
        <div class="m-panel border-[var(--aoe-gold)] w-full max-w-sm">
            <div class="border-b border-[var(--wood-border)] pb-2 mb-4 flex justify-between items-center">
                <h3 class="m-title text-[10px] uppercase tracking-widest"><?php echo $txt['STAFF_DASHBOARD']['MODAL_PAISES_TITULO']; ?></h3>
                <button onclick="cerrarModal('modalGlobalPaises')" class="text-[var(--parchment)] hover:text-white">&times;</button>
            </div>
            
            <form action="../logic/procesar_pais.php" method="POST" class="flex gap-2 mb-6">
                <input type="hidden" name="accion" value="agregar">
                <input type="text" name="nombre_pais" required placeholder="<?php echo $txt['STAFF_DASHBOARD']['PH_NUEVO_PAIS']; ?>" class="m-input flex-1 text-[10px] outline-none focus:border-[var(--aoe-gold)]">
                <button type="submit" class="btn-m !py-2 !px-3 text-[9px]"><?php echo $txt['STAFF_DASHBOARD']['BTN_ADD_PAIS']; ?></button>
            </form>
            
            <div class="max-h-48 overflow-y-auto border border-[var(--wood-border)] bg-black/50 p-2 shadow-inner">
                <table class="w-full text-xs font-bold">
                    <?php foreach ($lista_naciones as $n): ?>
                    <tr class="border-b border-[var(--wood-border)]/50 last:border-0 hover:bg-black transition">
                        <td class="p-2 text-[var(--parchment)] uppercase tracking-wide"><?php echo htmlspecialchars($n['nombre']); ?></td>
                        <td class="p-2 text-right">
                            <form action="../logic/procesar_pais.php" method="POST" onsubmit="return confirm('¿Confirmar purga territorial?');">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id_pais" value="<?php echo $n['id']; ?>">
                                <button class="text-red-600 hover:text-red-400 font-black uppercase text-[9px]"><?php echo $txt['STAFF_DASHBOARD']['BTN_DEL_PAIS']; ?></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>

    <div id="modalNuke" class="hidden fixed inset-0 bg-black/95 z-[100] flex items-center justify-center p-4">
        <div class="bg-black border-4 border-red-600 w-full max-w-sm rounded shadow-[0_0_40px_rgba(220,38,38,0.5)] overflow-hidden relative">
            
            <div class="bg-red-950/80 border-b-2 border-red-600 p-4 flex justify-between items-center">
                <h3 class="font-['Cinzel'] font-black text-red-500 text-sm uppercase tracking-[0.3em]"><?php echo $txt['STAFF_DASHBOARD']['MODAL_NUKE_TITULO']; ?></h3>
                <button onclick="cerrarModal('modalNuke')" class="text-red-500 hover:text-red-300 text-xl font-bold">&times;</button>
            </div>

            <div class="p-8 text-center bg-[#0d0a0a]">
                <div class="mb-6 text-red-600 animate-pulse">
                    <svg class="w-20 h-20 mx-auto drop-shadow-[0_0_15px_rgba(220,38,38,0.8)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                
                <h4 class="text-red-500 font-['Cinzel'] font-black mb-3 uppercase text-lg"><?php echo $txt['STAFF_DASHBOARD']['NUKE_CONFIRMAR']; ?></h4>
                <p class="text-[var(--parchment)] text-xs mb-8 leading-relaxed font-bold">
                    <?php echo $txt['STAFF_DASHBOARD']['NUKE_DESC']; ?>
                </p>

                <label class="block text-[10px] text-gray-500 uppercase mb-2 font-bold tracking-widest"><?php echo $txt['STAFF_DASHBOARD']['NUKE_INSTRUCCION']; ?></label>
                <input type="text" id="inputConfirmarNuke" placeholder="---" 
                       class="w-full bg-black border-2 border-red-900 rounded p-3 text-center text-red-500 font-black tracking-[0.4em] outline-none focus:border-red-500 mb-6 uppercase shadow-inner">

                <button onclick="ejecutarNukeFinal()" 
                        class="w-full bg-red-700 hover:bg-red-600 text-white font-['Cinzel'] font-black py-4 rounded text-xs transition shadow-[0_0_15px_rgba(220,38,38,0.6)] uppercase tracking-[0.3em]">
                    <?php echo $txt['STAFF_DASHBOARD']['BTN_ACTIVAR_NUKE']; ?>
                </button>
            </div>
        </div>
    </div>

<script>
    const TXT_NUKE_ERR = <?php echo json_encode($txt['STAFF_DASHBOARD']['ERR_NUKE']); ?>;
    let equipoEditandoId = null;

    function abrirModal(id) {
        document.getElementById(id).classList.remove('hidden');
        document.body.classList.add('modal-active');
    }

    function cerrarModal(id) {
        document.getElementById(id).classList.add('hidden');
        document.body.classList.remove('modal-active');
    }

    function abrirModalNaciones(id, nombre) {
        equipoEditandoId = id;
        document.getElementById('tituloModalNaciones').innerText = nombre;
        const actuales = document.getElementById('input-naciones-' + id).value.split(',').map(s => s.trim());
        document.querySelectorAll('.check-nacion').forEach(c => {
            c.checked = actuales.includes(c.value);
        });
        abrirModal('modalSeleccionNaciones');
    }

    function confirmarSeleccionNaciones() {
        const seleccionadas = Array.from(document.querySelectorAll('.check-nacion:checked')).map(c => c.value);
        const stringResultado = seleccionadas.join(', ');
        const displayTxt = seleccionadas.length > 0 ? stringResultado : <?php echo json_encode($txt['STAFF_DASHBOARD']['SIN_NACIONES']); ?>;
        
        document.getElementById('text-naciones-' + equipoEditandoId).innerText = displayTxt;
        document.getElementById('input-naciones-' + equipoEditandoId).value = stringResultado;
        cerrarModal('modalSeleccionNaciones');
    }

    function ejecutarNukeFinal() {
        const input = document.getElementById('inputConfirmarNuke');
        const valor = input.value.trim().toUpperCase();

        if (valor === 'REINICIAR') {
            document.getElementById('formNuke').submit();
        } else {
            input.classList.add('border-red-500', 'bg-red-900/30');
            alert(TXT_NUKE_ERR);
            input.value = '';
            input.focus();
        }
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            cerrarModal('modalNuke');
            cerrarModal('modalSeleccionNaciones');
            cerrarModal('modalGlobalPaises');
        }
    });
</script>

</body>
</html>