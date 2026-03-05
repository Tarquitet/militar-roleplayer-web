<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'staff') {
    header("Location: ../login.php");
    exit();
}
require_once '../config/conexion.php';

try {
    $stmt = $pdo->query("SELECT id, nombre_equipo, dinero, acero, petroleo, naciones_activas FROM cuentas WHERE rol = 'lider' ORDER BY id ASC");
    $equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt_naciones = $pdo->query("SELECT id, nombre FROM naciones ORDER BY nombre ASC");
    $lista_naciones = $stmt_naciones->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al cargar los datos: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Staff - Administración Global</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .modal-active { overflow: hidden; }
        .input-recurso { width: 80px; }
        .col-naciones { max-width: 180px; }
    </style>
</head>
<body class="bg-gray-900 text-gray-200 min-h-screen pb-10">

    <?php include '../includes/nav_staff.php'; ?>

    <main class="p-8 max-w-[98%] mx-auto">
        <div class="mb-8 flex justify-between items-end">
            <div>
                <h1 class="text-3xl font-bold text-white mb-2">Gestión de Facciones</h1>
                <p class="text-gray-400">Administra recursos y naciones desde un solo panel técnico.</p>
            </div>
            <div class="flex gap-3">
                <button onclick="abrirModal('modalGlobalPaises')" class="bg-blue-600 hover:bg-blue-500 text-white font-bold py-2 px-4 rounded shadow-lg transition text-xs flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    GESTIONAR PAÍSES
                </button>
                <form id="formNuke" action="../logic/nuke_reboot.php" method="POST">
                    <button type="button" onclick="abrirModal('modalNuke')" 
                            class="bg-red-600 hover:bg-red-500 text-white font-bold py-2 px-4 rounded border border-red-700 transition text-xs flex items-center gap-2 shadow-[0_0_15px_rgba(220,38,38,0.2)]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        NUKE
                    </button>
                </form>
            </div>
        </div>

        <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-xl overflow-hidden mb-32">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-700 text-gray-300 text-[10px] uppercase tracking-widest">
                        <th class="p-4 border-b border-gray-600">Equipo</th>
                        <th class="p-4 border-b border-gray-600 text-center">Dinero ($)</th>
                        <th class="p-4 border-b border-gray-600 text-center">Acero</th>
                        <th class="p-4 border-b border-gray-600 text-center">Petróleo (L)</th>
                        <th class="p-4 border-b border-gray-600">Naciones Habilitadas</th>
                        <th class="p-4 border-b border-gray-600 text-center">Gestión</th>
                        <th class="p-4 border-b border-gray-600 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="text-gray-300">
                    <?php foreach ($equipos as $equipo): ?>
                        <tr class="hover:bg-gray-750 border-b border-gray-700 transition">
                            <form action="../logic/actualizar_recursos.php" method="POST">
                                <input type="hidden" name="equipo_id" value="<?php echo $equipo['id']; ?>">
                                
                                <td class="p-3">
                                    <input type="text" name="nombre_equipo" value="<?php echo htmlspecialchars($equipo['nombre_equipo']); ?>" 
                                           class="w-full bg-gray-900 border border-gray-600 rounded px-2 py-1 text-white text-xs outline-none focus:border-purple-500">
                                </td>
                                <td class="p-3 text-center">
                                    <input type="number" name="dinero" value="<?php echo $equipo['dinero']; ?>" 
                                           class="input-recurso bg-gray-900 border border-gray-600 rounded px-1 py-1 text-green-400 font-bold text-center text-xs outline-none">
                                </td>
                                <td class="p-3 text-center">
                                    <input type="number" name="acero" value="<?php echo $equipo['acero']; ?>" 
                                           class="input-recurso bg-gray-900 border border-gray-600 rounded px-1 py-1 text-white text-center text-xs outline-none">
                                </td>
                                <td class="p-3 text-center">
                                    <input type="number" name="petroleo" value="<?php echo $equipo['petroleo']; ?>" 
                                           class="input-recurso bg-gray-900 border border-gray-600 rounded px-1 py-1 text-yellow-500 font-bold text-center text-xs outline-none">
                                </td>
                                <td class="p-3 col-naciones">
                                    <span id="text-naciones-<?php echo $equipo['id']; ?>" class="text-[11px] text-blue-300 block truncate" title="<?php echo htmlspecialchars($equipo['naciones_activas']); ?>">
                                        <?php echo !empty($equipo['naciones_activas']) ? htmlspecialchars($equipo['naciones_activas']) : 'Ninguna'; ?>
                                    </span>
                                    <input type="hidden" 
                                        id="input-naciones-<?php echo $equipo['id']; ?>" 
                                        name="naciones_activas_string" 
                                        value="<?php echo htmlspecialchars($equipo['naciones_activas'] ?? ''); ?>">
                                </td>
                                <td class="p-3 text-center">
                                    <button type="button" onclick="abrirModalNaciones(<?php echo $equipo['id']; ?>, '<?php echo htmlspecialchars($equipo['nombre_equipo']); ?>')" 
                                            class="bg-gray-700 hover:bg-gray-600 text-[10px] text-gray-300 px-2 py-1 rounded border border-gray-600 transition font-bold">
                                        EDITAR
                                    </button>
                                </td>
                                <td class="p-3 text-center flex justify-center gap-2">
                                    <button type="submit" class="bg-purple-600 hover:bg-purple-500 text-white text-[10px] font-bold py-1.5 px-3 rounded shadow transition">
                                        GUARDAR
                                    </button>
                                    <a href="staff_ver_inventario.php?id=<?php echo $equipo['id']; ?>" class="bg-blue-600 hover:bg-blue-500 text-white text-[10px] font-bold py-1.5 px-3 rounded shadow transition flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                                        INVENTARIO
                                    </a>
                                </td>
                            </form>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div id="modalSeleccionNaciones" class="hidden fixed inset-0 bg-black bg-opacity-80 z-[70] flex items-center justify-center p-4">
        <div class="bg-gray-800 border border-purple-500/30 w-full max-w-xs rounded shadow-2xl overflow-hidden">
            <div class="p-3 border-b border-gray-700 flex justify-between items-center bg-gray-900">
                <h3 class="font-bold text-white text-[10px] uppercase tracking-widest" id="tituloModalNaciones">Configurar Naciones</h3>
                <button onclick="cerrarModal('modalSeleccionNaciones')" class="text-gray-500 hover:text-white">&times;</button>
            </div>
            <div class="p-4">
                <div id="listaCheckboxesNaciones" class="max-h-60 overflow-y-auto space-y-1 mb-4 pr-2">
                    <?php foreach ($lista_naciones as $n): ?>
                    <label class="flex items-center gap-3 p-2 hover:bg-gray-700 rounded cursor-pointer transition group">
                        <input type="checkbox" class="check-nacion form-checkbox h-4 w-4 text-purple-600 rounded bg-gray-900 border-gray-600 focus:ring-0" value="<?php echo htmlspecialchars($n['nombre']); ?>">
                        <span class="text-xs text-gray-400 group-hover:text-white"><?php echo htmlspecialchars($n['nombre']); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <button onclick="confirmarSeleccionNaciones()" class="w-full bg-purple-600 hover:bg-purple-500 text-white font-bold py-2 rounded text-[10px] transition uppercase tracking-widest">
                    Confirmar Cambios
                </button>
            </div>
        </div>
    </div>

    <div id="modalGlobalPaises" class="hidden fixed inset-0 bg-black bg-opacity-80 z-[60] flex items-center justify-center p-4">
        <div class="bg-gray-800 border border-gray-700 w-full max-w-sm rounded shadow-2xl overflow-hidden">
            <div class="p-3 border-b border-gray-700 flex justify-between items-center bg-gray-900">
                <h3 class="font-bold text-white text-[10px] uppercase tracking-widest">Catálogo de Países</h3>
                <button onclick="cerrarModal('modalGlobalPaises')" class="text-gray-500 hover:text-white">&times;</button>
            </div>
            <div class="p-5">
                <form action="../logic/procesar_pais.php" method="POST" class="flex gap-2 mb-4">
                    <input type="hidden" name="accion" value="agregar">
                    <input type="text" name="nombre_pais" required placeholder="Nuevo país..." class="flex-1 bg-gray-900 border border-gray-600 rounded px-3 py-1.5 text-xs text-white outline-none focus:border-blue-500">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-500 px-3 py-1.5 rounded text-[10px] font-bold uppercase">Añadir</button>
                </form>
                <div class="max-h-48 overflow-y-auto border border-gray-700 rounded bg-gray-900">
                    <table class="w-full text-xs">
                        <?php foreach ($lista_naciones as $n): ?>
                        <tr class="border-b border-gray-800 last:border-0 hover:bg-gray-800 transition">
                            <td class="p-2 text-gray-400"><?php echo htmlspecialchars($n['nombre']); ?></td>
                            <td class="p-2 text-right">
                                <form action="../logic/procesar_pais.php" method="POST" onsubmit="return confirm('¿Eliminar país?');">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id_pais" value="<?php echo $n['id']; ?>">
                                    <button class="text-red-500 hover:text-red-400 font-bold uppercase text-[9px]">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>

<script>
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
        document.getElementById('text-naciones-' + equipoEditandoId).innerText = seleccionadas.length > 0 ? stringResultado : 'Ninguna';
        document.getElementById('input-naciones-' + equipoEditandoId).value = stringResultado;
        cerrarModal('modalSeleccionNaciones');
    }

    // Función para validar y enviar el NUKE desde el modal
    function ejecutarNukeFinal() {
        const input = document.getElementById('inputConfirmarNuke');
        const valor = input.value.trim();

        if (valor === 'REINICIAR') {
            // Si es correcto, enviamos el formulario real
            document.getElementById('formNuke').submit();
        } else {
            // Efecto visual de error (sacudir el input o borde rojo intenso)
            input.classList.add('border-red-500', 'bg-red-900/10');
            alert("Palabra de confirmación incorrecta. La secuencia ha sido abortada.");
            input.value = '';
            input.focus();
        }
    }

    // Opcional: Permitir cerrar con la tecla ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            cerrarModal('modalNuke');
            cerrarModal('modalSeleccionNaciones');
            cerrarModal('modalGlobalPaises');
        }
    });

    window.onclick = function(event) {
        if (event.target.classList.contains('fixed')) {
            event.target.classList.add('hidden');
            document.body.classList.remove('modal-active');
        }
    }
</script>

<div id="modalNuke" class="hidden fixed inset-0 bg-black bg-opacity-90 z-[100] flex items-center justify-center p-4">
    <div class="bg-gray-900 border-2 border-red-600 w-full max-w-sm rounded-lg shadow-[0_0_30px_rgba(220,38,38,0.4)] overflow-hidden">
        
        <div class="p-4 bg-red-600 text-white flex justify-between items-center">
            <h3 class="font-black text-xs uppercase tracking-[0.2em]">Protocolo de Reinicio</h3>
            <button onclick="cerrarModal('modalNuke')" class="text-white hover:text-gray-200 text-xl font-bold">&times;</button>
        </div>

        <div class="p-6 text-center">
            <div class="mb-4 text-red-500">
                <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
            
            <h4 class="text-white font-bold mb-2 uppercase text-sm">¿Confirmar Destrucción Total?</h4>
            <p class="text-gray-400 text-xs mb-6 leading-relaxed">
                Esta acción borrará inventarios, flotas y recursos de todos los equipos. <br>
                <span class="text-red-400 font-bold">No hay marcha atrás.</span>
            </p>

            <label class="block text-[10px] text-gray-500 uppercase mb-2 font-bold">Escribe <span class="text-white">REINICIAR</span> para proceder:</label>
            <input type="text" id="inputConfirmarNuke" placeholder="..." 
                   class="w-full bg-black border border-gray-700 rounded px-3 py-2 text-center text-red-500 font-black tracking-widest outline-none focus:border-red-600 mb-4 uppercase">

            <button onclick="ejecutarNukeFinal()" 
                    class="w-full bg-red-600 hover:bg-red-500 text-white font-black py-3 rounded text-xs transition shadow-lg uppercase tracking-widest">
                Activar Secuencia
            </button>
        </div>
    </div>
</div>

</body>
</html>