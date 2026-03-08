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

    $stmt_otros = $pdo->prepare("SELECT nombre_equipo, bandera_url, naciones_activas FROM cuentas WHERE rol = 'lider' AND id != :id ORDER BY nombre_equipo ASC");
    $stmt_otros->execute([':id' => $lider_id]);
    $otros_equipos = $stmt_otros->fetchAll(PDO::FETCH_ASSOC);

    $stmt_contratos = $pdo->prepare("
        SELECT m.*, 
               u.nombre_equipo as ofertante_nombre, u.bandera_url as ofertante_bandera,
               c1.nombre_vehiculo AS vehiculo_ofrecido,
               c2.nombre_vehiculo AS vehiculo_requerido
        FROM mercado_tradeos m
        JOIN cuentas u ON m.ofertante_id = u.id
        LEFT JOIN catalogo_tienda c1 ON m.vehiculo_ofrecido_id = c1.id
        LEFT JOIN catalogo_tienda c2 ON m.vehiculo_requerido_id = c2.id
        WHERE m.receptor_id = :id AND m.estado = 'activo'
    ");
    $stmt_contratos->execute([':id' => $lider_id]);
    $contratos_entrantes = $stmt_contratos->fetchAll(PDO::FETCH_ASSOC);

    $pdo = null;

} catch (PDOException $e) { die("Error en el radar: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo $txt['GLOBAL']['MANDO_LIDER']; ?> - Operaciones</title>
    <?php include '../includes/head.php'; ?>
    <style>
        .modal-active { overflow: hidden; }
        .badge-territorio { background-color: rgba(0,0,0,0.6); border: 1px solid var(--wood-border); color: var(--aoe-gold); padding: 2px 10px; font-size: 10px; text-transform: uppercase; font-weight: 900; letter-spacing: 1px; }
        .censored-data { color: #333; font-family: monospace; letter-spacing: 3px; user-select: none; }
    </style>
</head>
<body class="bg-[#0d0e0a] text-[var(--text-main)] min-h-screen pb-20">

    <?php include '../includes/nav_lider.php'; ?>

    <main class="p-8 max-w-[1600px] mx-auto">
        
        <div class="mb-8 flex justify-between items-end">
            <div>
                <h2 class="text-[var(--aoe-gold)] font-black uppercase text-[10px] tracking-[0.3em] mb-1 font-['Cinzel']">
                    <?php echo $txt['LIDER_DASHBOARD']['TITULO_PROPIO']; ?>
                </h2>
                <span class="text-[8px] text-gray-500 uppercase tracking-[2px]">Sincronización: <?php echo date("H:i:s"); ?></span>
            </div>
            <button onclick="location.reload()" class="btn-m !bg-none !border-none !text-[8px] opacity-40 hover:opacity-100">🔄 REFRESCAR</button>
        </div>

        <?php if (isset($_GET['status'])): ?>
            <div class="bg-[var(--olive-drab)] text-[var(--aoe-gold)] border border-[var(--aoe-gold)] p-3 mb-6 text-xs font-black tracking-widest uppercase shadow-lg text-center backdrop-blur-sm">
                <?php 
                    if($_GET['status'] == 'contrato_firmado') echo $txt['LIDER_DASHBOARD']['MSG_CONTRATO_FIRMADO'];
                    if($_GET['status'] == 'contrato_rechazado') echo $txt['LIDER_DASHBOARD']['MSG_CONTRATO_RECHAZADO'];
                ?>
            </div>
        <?php endif; ?>

        <div class="m-panel relative overflow-hidden shadow-2xl mb-12 flex flex-col lg:flex-row items-center justify-between gap-8 p-6 bg-black/40 border-[var(--aoe-gold)]/30 backdrop-blur-sm">
            <div class="flex items-center gap-6 w-full lg:w-auto">
                <div class="w-32 h-20 bg-black border border-[var(--wood-border)] shadow-inner flex items-center justify-center shrink-0">
                    <?php if($mi_equipo['bandera_url']): ?><img src="../<?php echo $mi_equipo['bandera_url']; ?>" class="w-full h-full object-cover"><?php else: ?><span class="text-[8px] text-gray-700 font-black uppercase"><?php echo $txt['LIDER_DASHBOARD']['NO_FLAG']; ?></span><?php endif; ?>
                </div>
                <div>
                    <h1 class="text-3xl lg:text-4xl font-black text-white italic uppercase tracking-tighter font-['Cinzel'] leading-none">
                        <?php echo htmlspecialchars($mi_equipo['nombre_equipo'] ?: $txt['LIDER_DASHBOARD']['SIN_ASIGNAR']); ?>
                    </h1>
                </div>
            </div>
            <div class="flex gap-8 lg:gap-12 font-['Cinzel'] border-y lg:border-y-0 lg:border-l border-[var(--wood-border)]/50 py-4 lg:py-0 lg:pl-12 justify-center w-full lg:w-auto">
                <div class="text-center"><span class="block text-[9px] text-[var(--parchment)] uppercase font-sans font-bold"><?php echo $txt['LIDER_DASHBOARD']['LBL_DINERO']; ?></span><span class="text-green-500 font-black text-2xl">$<?php echo number_format($mi_equipo['dinero']); ?></span></div>
                <div class="text-center"><span class="block text-[9px] text-[var(--parchment)] uppercase font-sans font-bold"><?php echo $txt['LIDER_DASHBOARD']['LBL_ACERO']; ?></span><span class="text-white font-black text-2xl"><?php echo number_format($mi_equipo['acero']); ?>T</span></div>
                <div class="text-center"><span class="block text-[9px] text-[var(--parchment)] uppercase font-sans font-bold"><?php echo $txt['LIDER_DASHBOARD']['LBL_PETROLEO']; ?></span><span class="text-yellow-500 font-black text-2xl"><?php echo number_format($mi_equipo['petroleo']); ?>L</span></div>
            </div>
            <div class="flex flex-col items-center lg:items-end w-full lg:w-auto gap-4">
                <button onclick="abrirModal()" class="btn-m !text-[9px] px-8 py-2"><?php echo $txt['LIDER_DASHBOARD']['BTN_CONFIGURAR']; ?></button>
            </div>
        </div>

        <?php if(!empty($contratos_entrantes)): ?>
        <div class="mb-12">
            <h2 class="text-blue-400 font-black uppercase text-[10px] tracking-[0.3em] mb-4 font-['Cinzel'] flex items-center gap-2 animate-pulse">
                <?php echo $txt['LIDER_DASHBOARD']['TITULO_TRANSMISIONES']; ?> (<?php echo count($contratos_entrantes); ?>)
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach($contratos_entrantes as $c): ?>
                    <div class="flex flex-col border border-blue-900/50 shadow-[0_0_20px_rgba(59,130,246,0.15)] bg-[#0a0f1c]">
                        <div class="bg-blue-900/20 p-3 border-b border-blue-900/30 flex items-center gap-3">
                            <?php if($c['ofertante_bandera']): ?><img src="../<?php echo $c['ofertante_bandera']; ?>" class="w-8 h-5 border border-white/10"><?php endif; ?>
                            <span class="text-[10px] text-white font-black uppercase tracking-widest"><?php echo htmlspecialchars($c['ofertante_nombre']); ?> <?php echo $txt['LIDER_DASHBOARD']['LBL_PROPONE']; ?></span>
                        </div>
                        <div class="p-4 flex-grow text-center flex flex-col justify-center gap-3">
                            <div>
                                <span class="block text-[8px] text-[var(--aoe-gold)] font-bold mb-1 tracking-widest uppercase"><?php echo $txt['LIDER_DASHBOARD']['LBL_TU_RECIBES']; ?></span>
                                <div class="text-sm font-black text-white leading-relaxed">
                                    <?php if($c['ofrece_dinero'] > 0) echo "<span class='text-green-500'>$".$c['ofrece_dinero']."</span><br>"; ?>
                                    <?php if($c['ofrece_acero'] > 0) echo $c['ofrece_acero']."T Acero<br>"; ?>
                                    <?php if($c['ofrece_petroleo'] > 0) echo "<span class='text-yellow-500'>".$c['ofrece_petroleo']."L Comb.</span><br>"; ?>
                                    <?php if($c['vehiculo_ofrecido_id']) echo "<span class='text-[var(--aoe-gold)] text-lg'>".$c['cantidad_ofrecida']."x ".htmlspecialchars($c['vehiculo_ofrecido'])."</span>"; ?>
                                </div>
                            </div>
                            <div class="text-gray-600 text-xs"><?php echo $txt['LIDER_DASHBOARD']['LBL_A_CAMBIO_DE']; ?></div>
                            <div>
                                <span class="block text-[8px] text-red-400 font-bold mb-1 tracking-widest uppercase"><?php echo $txt['LIDER_DASHBOARD']['LBL_TU_ENTREGAS']; ?></span>
                                <div class="text-sm font-black text-white leading-relaxed">
                                    <?php if($c['pide_dinero'] > 0) echo "<span class='text-green-500'>$".$c['pide_dinero']."</span><br>"; ?>
                                    <?php if($c['pide_acero'] > 0) echo $c['pide_acero']."T Acero<br>"; ?>
                                    <?php if($c['pide_petroleo'] > 0) echo "<span class='text-yellow-500'>".$c['pide_petroleo']."L Comb.</span><br>"; ?>
                                    <?php if($c['vehiculo_requerido_id']) echo "<span class='text-red-400 text-lg'>".$c['cantidad_requerida']."x ".htmlspecialchars($c['vehiculo_requerido'])."</span>"; ?>
                                    <?php if($c['pide_dinero']==0 && $c['pide_acero']==0 && $c['pide_petroleo']==0 && empty($c['vehiculo_requerido_id'])) echo "<span class='text-[var(--aoe-gold)]'>".$txt['LIDER_DASHBOARD']['LBL_REGALO']."</span>"; ?>
                                </div>
                            </div>
                        </div>
                        <div class="flex border-t border-blue-900/50">
                            <form action="../logic/procesar_tradeo.php" method="POST" class="w-1/2">
                                <input type="hidden" name="accion" value="aceptar">
                                <input type="hidden" name="oferta_id" value="<?php echo $c['id']; ?>">
                                <button class="w-full bg-green-900/40 hover:bg-green-700 text-green-500 hover:text-white font-black py-3 text-[9px] uppercase tracking-widest transition"><?php echo $txt['LIDER_DASHBOARD']['BTN_FIRMAR_PAGAR']; ?></button>
                            </form>
                            <form action="../logic/procesar_tradeo.php" method="POST" class="w-1/2">
                                <input type="hidden" name="accion" value="cancelar">
                                <input type="hidden" name="oferta_id" value="<?php echo $c['id']; ?>">
                                <button class="w-full bg-red-950/40 hover:bg-red-800 text-red-500 hover:text-white font-black py-3 text-[9px] uppercase tracking-widest transition"><?php echo $txt['LIDER_DASHBOARD']['BTN_RECHAZAR']; ?></button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div>
            <h2 class="text-gray-500 font-black uppercase text-[10px] tracking-[0.3em] mb-4 font-['Cinzel'] flex items-center gap-2">
                📡 <?php echo $txt['LIDER_DASHBOARD']['TITULO_RADAR']; ?> <span class="text-[8px] text-red-900 bg-red-900/20 px-2 py-1 rounded ml-2"><?php echo $txt['LIDER_DASHBOARD']['TAG_CLASIFICADO']; ?></span>
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php foreach($otros_equipos as $rival): ?>
                    <div class="flex flex-col bg-[#0a0f0a] border border-[var(--wood-border)]/50 shadow-xl opacity-90 hover:opacity-100 transition">
                        <div class="h-24 bg-black relative border-b border-[var(--wood-border)]/50 flex items-center justify-center overflow-hidden">
                            <?php if($rival['bandera_url']): ?><img src="../<?php echo $rival['bandera_url']; ?>" class="w-full h-full object-cover grayscale opacity-40 mix-blend-screen"><?php endif; ?>
                            <div class="absolute inset-0 bg-[linear-gradient(rgba(0,0,0,0)_50%,rgba(0,255,0,0.02)_50%)] bg-[length:100%_4px] pointer-events-none"></div>
                        </div>
                        <div class="p-5 flex-grow text-center">
                            <h3 class="font-black text-[var(--parchment)] uppercase font-['Cinzel'] text-sm tracking-widest mb-3 truncate">
                                <?php echo htmlspecialchars($rival['nombre_equipo']); ?>
                            </h3>
                            <div class="flex flex-wrap justify-center gap-1.5 opacity-70">
                                <?php 
                                $nacs = array_filter(explode(',', $rival['naciones_activas'] ?? ''));
                                if(!empty($nacs)): foreach($nacs as $n): ?>
                                    <span class="badge-territorio !text-[8px] !px-2 !py-0.5"><?php echo trim($n); ?></span>
                                <?php endforeach; else: ?>
                                    <span class="text-[8px] text-gray-700 italic uppercase">Nómadas</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="p-3 bg-black/80 border-t border-[var(--wood-border)]/50 text-center flex justify-between items-center px-6">
                            <span class="text-[8px] text-gray-600 font-bold uppercase tracking-widest"><?php echo $txt['LIDER_DASHBOARD']['TH_RECURSOS']; ?></span>
                            <span class="censored-data text-[10px]">████</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <div id="modalConfig" class="hidden fixed inset-0 bg-black/90 z-[100] flex items-center justify-center p-4">
        <div class="m-panel border-[var(--aoe-gold)] w-full max-w-sm relative">
            <button onclick="cerrarModal()" class="absolute top-4 right-4 text-[var(--parchment)] hover:text-white font-bold text-xl">&times;</button>
            <h3 class="m-title text-xl mb-6 border-b border-[var(--wood-border)] pb-2"><?php echo $txt['LIDER_DASHBOARD']['MODAL_TITULO']; ?></h3>
            <form action="../logic/actualizar_perfil_lider.php" method="POST" enctype="multipart/form-data" class="space-y-5">
                <div><label class="block text-[9px] text-[var(--parchment)] uppercase font-bold mb-2"><?php echo $txt['LIDER_DASHBOARD']['LBL_NOMBRE']; ?></label><input type="text" name="nombre_equipo" value="<?php echo htmlspecialchars($mi_equipo['nombre_equipo'] ?? ''); ?>" required class="m-input w-full text-center text-lg"></div>
                <div><label class="block text-[9px] text-[var(--parchment)] uppercase font-bold mb-2"><?php echo $txt['LIDER_DASHBOARD']['LBL_ESTANDARTE']; ?> (1MB)</label><div class="m-input p-2 text-center bg-black/50 shadow-inner border-dashed"><input type="file" name="bandera" class="w-full text-[10px] text-gray-500 cursor-pointer"></div></div>
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