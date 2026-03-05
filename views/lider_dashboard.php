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
    $stmt_mio = $pdo->prepare("SELECT id, nombre_equipo, bandera_url, dinero, acero, petroleo, naciones_activas FROM cuentas WHERE id = :id");
    $stmt_mio->execute([':id' => $lider_id]);
    $mi_equipo = $stmt_mio->fetch(PDO::FETCH_ASSOC);

    $stmt_otros = $pdo->prepare("SELECT nombre_equipo, bandera_url, dinero, acero, petroleo, naciones_activas FROM cuentas WHERE rol = 'lider' AND id != :id ORDER BY dinero DESC");
    $stmt_otros->execute([':id' => $lider_id]);
    $otros_equipos = $stmt_otros->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error en el radar: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo $txt['GLOBAL']['MANDO_LIDER']; ?> - Operaciones</title>
    <?php include '../includes/head.php'; ?>
    <style>
        .modal-active { overflow: hidden; }
        
        /* BLOQUEO CRÍTICO: Eliminamos el color blanco intruso de todos los bordes */
        * { border-color: rgba(74, 44, 42, 0.3) !important; }

        /* Estilo para los países (Badges Tácticos) */
        .badge-territorio {
            background-color: rgba(0, 0, 0, 0.6);
            border: 1px solid var(--wood-border) !important;
            color: var(--aoe-gold);
            padding: 2px 10px;
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 900;
            letter-spacing: 1px;
            box-shadow: inset 0 0 10px rgba(0,0,0,0.5);
            display: inline-block;
            font-family: 'Cinzel', serif;
        }

        /* Forzar visibilidad de bordes internos de tabla */
        .table-m td, .table-m th {
            border-right: 1px solid rgba(74, 44, 42, 0.3) !important;
            border-bottom: 1px solid rgba(74, 44, 42, 0.3) !important;
        }
        .table-m tr td:last-child { border-right: none !important; }
    </style>
</head>
<body class="bg-[#0d0e0a] text-[var(--text-main)] min-h-screen pb-20">

    <?php include '../includes/nav_lider.php'; ?>

    <main class="p-8 max-w-[95%] mx-auto">
        
        <div class="mb-12">
            <h2 class="text-[var(--aoe-gold)] font-black uppercase text-[10px] tracking-[0.3em] mb-4 font-['Cinzel']">
                <?php echo $txt['LIDER_DASHBOARD']['TITULO_PROPIO']; ?>
            </h2>
            <div class="m-panel !p-0 overflow-hidden shadow-2xl">
                <table class="w-full text-left table-m border-collapse border-none">
                    <thead>
                        <tr class="text-[9px] uppercase tracking-widest bg-black/40">
                            <th class="p-4 w-1/4"><?php echo $txt['LIDER_DASHBOARD']['TH_IDENTIDAD']; ?></th>
                            <th class="p-4 text-center"><?php echo $txt['LIDER_DASHBOARD']['TH_RECURSOS']; ?></th>
                            <th class="p-4 text-center"><?php echo $txt['LIDER_DASHBOARD']['TH_JURISDICCION']; ?></th>
                            <th class="p-4 text-center"><?php echo $txt['LIDER_DASHBOARD']['TH_ESTANDARTE']; ?></th>
                            <th class="p-4 text-right"><?php echo $txt['LIDER_DASHBOARD']['TH_ACCIONES']; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="bg-black/30">
                            <td class="p-6">
                                <span class="text-3xl font-black text-white italic uppercase tracking-tighter font-['Cinzel'] leading-none">
                                    <?php echo htmlspecialchars($mi_equipo['nombre_equipo'] ?: $txt['LIDER_DASHBOARD']['SIN_ASIGNAR']); ?>
                                </span>
                                <p class="text-[9px] text-[var(--aoe-gold)] font-bold mt-2 tracking-widest">
                                    <?php echo $txt['LIDER_DASHBOARD']['ID_MANDO']; ?><?php echo $mi_equipo['id']; ?>
                                </p>
                            </td>
                            <td class="p-4 text-center">
                                <div class="flex justify-center gap-8 font-['Cinzel']">
                                    <div><span class="block text-[8px] text-[var(--parchment)] uppercase font-sans font-bold"><?php echo $txt['LIDER_DASHBOARD']['LBL_DINERO']; ?></span><span class="text-green-500 font-black text-xl">$<?php echo number_format($mi_equipo['dinero']); ?></span></div>
                                    <div><span class="block text-[8px] text-[var(--parchment)] uppercase font-sans font-bold"><?php echo $txt['LIDER_DASHBOARD']['LBL_ACERO']; ?></span><span class="text-white font-black text-xl"><?php echo number_format($mi_equipo['acero']); ?>T</span></div>
                                    <div><span class="block text-[8px] text-[var(--parchment)] uppercase font-sans font-bold"><?php echo $txt['LIDER_DASHBOARD']['LBL_PETROLEO']; ?></span><span class="text-yellow-500 font-black text-xl"><?php echo number_format($mi_equipo['petroleo']); ?>L</span></div>
                                </div>
                            </td>
                            <td class="p-4 text-center">
                                <div class="flex flex-wrap justify-center gap-2">
                                    <?php 
                                    $mis_nacs = array_filter(explode(',', $mi_equipo['naciones_activas'] ?? ''));
                                    if(!empty($mis_nacs)):
                                        foreach($mis_nacs as $n): ?>
                                            <span class="badge-territorio"><?php echo trim($n); ?></span>
                                        <?php endforeach;
                                    else: ?>
                                        <span class="text-[8px] text-gray-700 italic uppercase">Sector no asegurado</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="p-4">
                                <div class="w-24 h-14 bg-black border border-[var(--wood-border)] mx-auto overflow-hidden shadow-inner flex items-center justify-center">
                                    <?php if($mi_equipo['bandera_url']): ?>
                                        <img src="../<?php echo $mi_equipo['bandera_url']; ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <span class="text-[8px] text-gray-700 font-black uppercase"><?php echo $txt['LIDER_DASHBOARD']['NO_FLAG']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="p-4 text-right">
                                <button onclick="abrirModal()" class="btn-m !text-[9px] px-6">
                                    <?php echo $txt['LIDER_DASHBOARD']['BTN_CONFIGURAR']; ?>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <h2 class="text-gray-500 font-black uppercase text-[10px] tracking-[0.3em] mb-4 font-['Cinzel']">
                <?php echo $txt['LIDER_DASHBOARD']['TITULO_RADAR']; ?>
            </h2>
            <div class="m-panel !p-0 overflow-hidden opacity-90 border-[var(--wood-border)]">
                <table class="w-full text-left table-m border-collapse">
                    <thead>
                        <tr class="text-[9px] uppercase tracking-widest text-gray-500 bg-black/40">
                            <th class="p-4"><?php echo $txt['LIDER_DASHBOARD']['TH_ENEMIGO']; ?></th>
                            <th class="p-4 text-center"><?php echo $txt['LIDER_DASHBOARD']['LBL_DINERO']; ?></th>
                            <th class="p-4 text-center"><?php echo $txt['LIDER_DASHBOARD']['LBL_ACERO']; ?></th>
                            <th class="p-4 text-center"><?php echo $txt['LIDER_DASHBOARD']['LBL_PETROLEO']; ?></th>
                            <th class="p-4 text-center"><?php echo $txt['LIDER_DASHBOARD']['TH_JURISDICCION']; ?></th>
                        </tr>
                    </thead>
                    <tbody class="text-xs font-bold">
                        <?php foreach($otros_equipos as $rival): ?>
                            <tr class="transition hover:bg-white/5 border-b border-[var(--wood-border)]/10">
                                <td class="p-4 flex items-center gap-3">
                                    <div class="w-10 h-6 bg-black border border-[var(--wood-border)] overflow-hidden shadow-inner">
                                        <?php if($rival['bandera_url']): ?>
                                            <img src="../<?php echo $rival['bandera_url']; ?>" class="w-full h-full object-cover">
                                        <?php endif; ?>
                                    </div>
                                    <span class="font-black text-[var(--parchment)] uppercase font-['Cinzel']">
                                        <?php echo htmlspecialchars($rival['nombre_equipo']); ?>
                                    </span>
                                </td>
                                <td class="p-4 text-center text-green-600/70 font-['Cinzel']">$<?php echo number_format($rival['dinero']); ?></td>
                                <td class="p-4 text-center text-gray-400 font-['Cinzel']"><?php echo number_format($rival['acero']); ?>T</td>
                                <td class="p-4 text-center text-yellow-600/70 font-['Cinzel']"><?php echo number_format($rival['petroleo']); ?>L</td>
                                <td class="p-4 text-center">
                                    <div class="flex flex-wrap justify-center gap-1">
                                        <?php 
                                        $nacs = array_filter(explode(',', $rival['naciones_activas'] ?? ''));
                                        foreach($nacs as $n): ?>
                                            <span class="badge-territorio"><?php echo trim($n); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div id="modalConfig" class="hidden fixed inset-0 bg-black/90 z-[100] flex items-center justify-center p-4">
        <div class="m-panel border-[var(--ao-gold)] w-full max-w-sm relative">
            <button onclick="cerrarModal()" class="absolute top-4 right-4 text-[var(--parchment)] hover:text-white font-bold text-xl">&times;</button>
            <h3 class="m-title text-xl mb-6 border-b border-[var(--wood-border)] pb-2"><?php echo $txt['LIDER_DASHBOARD']['MODAL_TITULO']; ?></h3>
            <form action="../logic/actualizar_perfil_lider.php" method="POST" enctype="multipart/form-data" class="space-y-5">
                <div>
                    <label class="block text-[9px] text-[var(--parchment)] uppercase font-bold mb-2 tracking-widest"><?php echo $txt['LIDER_DASHBOARD']['LBL_NOMBRE']; ?></label>
                    <input type="text" name="nombre_equipo" value="<?php echo htmlspecialchars($mi_equipo['nombre_equipo'] ?? ''); ?>" required class="m-input w-full text-center text-lg outline-none focus:border-[var(--aoe-gold)]">
                </div>
                <div>
                    <label class="block text-[9px] text-[var(--parchment)] uppercase font-bold mb-2 tracking-widest"><?php echo $txt['LIDER_DASHBOARD']['LBL_ESTANDARTE']; ?></label>
                    <div class="m-input p-2 text-center bg-black/50 shadow-inner border-dashed">
                        <input type="file" name="bandera" accept="image/*" class="w-full text-[10px] text-gray-500 file:bg-[var(--olive-drab)] file:border-0 file:text-[var(--aoe-gold)] file:font-black file:px-3 file:py-1 cursor-pointer hover:file:brightness-125 transition">
                    </div>
                </div>
                <button type="submit" class="btn-m w-full py-4 mt-4 text-xs tracking-[0.2em]"><?php echo $txt['LIDER_DASHBOARD']['BTN_CONFIRMAR']; ?></button>
            </form>
        </div>
    </div>

    <script>
        function abrirModal() { document.getElementById('modalConfig').classList.remove('hidden'); document.body.classList.add('modal-active'); }
        function cerrarModal() { document.getElementById('modalConfig').classList.add('hidden'); document.body.classList.remove('modal-active'); }
    </script>
</body>
</html>