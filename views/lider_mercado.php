<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'lider') {
    header("Location: ../login.php");
    exit();
}

$root_path = "../";
require_once '../config/conexion.php';
// Cargamos el diccionario central
$txt = require '../config/textos.php';

$lider_id = $_SESSION['usuario_id'];

try {
    // 1. INVENTARIO PROPIO
    $stmt_inv = $pdo->prepare("
        SELECT i.catalogo_id, i.cantidad, c.nombre_vehiculo 
        FROM inventario i 
        JOIN catalogo_tienda c ON i.catalogo_id = c.id 
        WHERE i.cuenta_id = :uid AND i.cantidad > 0
    ");
    $stmt_inv->execute([':uid' => $lider_id]);
    $mi_inventario = $stmt_inv->fetchAll(PDO::FETCH_ASSOC);

    // 2. CATÁLOGO GLOBAL
    $stmt_cat = $pdo->query("SELECT id, nombre_vehiculo FROM catalogo_tienda ORDER BY nombre_vehiculo ASC");
    $catalogo = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

    // 3. TABLÓN DE OFERTAS ACTIVAS
    $stmt_ofertas = $pdo->query("
        SELECT m.id, m.ofertante_id, m.cantidad_ofrecida, m.cantidad_requerida,
               u.nombre_equipo, u.bandera_url,
               c1.nombre_vehiculo AS vehiculo_ofrecido, c1.imagen_url AS img_ofrecido,
               c2.nombre_vehiculo AS vehiculo_requerido, c2.imagen_url AS img_requerido
        FROM mercado_tradeos m
        JOIN cuentas u ON m.ofertante_id = u.id
        JOIN catalogo_tienda c1 ON m.vehiculo_ofrecido_id = c1.id
        JOIN catalogo_tienda c2 ON m.vehiculo_requerido_id = c2.id
        WHERE m.estado = 'activo'
        ORDER BY m.fecha_creacion DESC
    ");
    $ofertas = $stmt_ofertas->fetchAll(PDO::FETCH_ASSOC);

    // CIERRE TÁCTICO
    $stmt_inv = null; $stmt_cat = null; $stmt_ofertas = null; $pdo = null;

} catch (PDOException $e) { 
    die($txt['LOGIC']['ERR_CADENA_SUMINISTRO'] . $e->getMessage()); 
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title><?php echo $txt['MERCADO']['TITULO']; ?> - <?php echo htmlspecialchars($_SESSION['username']); ?></title>
    <?php include '../includes/head.php'; ?>
    <style>
        .modal-active { overflow: hidden; }
        .trade-card { border-left: 4px solid var(--aoe-gold); }
        .trade-card.mine { border-left: 4px solid #3b82f6; }
    </style>
</head>
<body class="bg-[#0d0e0a] text-[var(--text-main)] min-h-screen pb-20">

    <?php include '../includes/nav_lider.php'; ?>

    <header class="p-8 max-w-[1200px] mx-auto border-b border-[var(--wood-border)] flex justify-between items-end">
        <div>
            <h1 class="text-3xl font-black text-[var(--aoe-gold)] font-['Cinzel'] tracking-widest uppercase">
                <?php echo $txt['MERCADO']['TITULO']; ?>
            </h1>
            <p class="text-[10px] text-[var(--parchment)] tracking-[0.2em] font-bold uppercase mt-1">
                <?php echo $txt['MERCADO']['SUBTITULO']; ?>
            </p>
        </div>
        <button onclick="abrirModal('modalCrearOferta')" class="btn-m !text-[10px] flex items-center gap-2">
            <?php echo $txt['MERCADO']['BTN_NUEVO_CONTRATO']; ?>
        </button>
    </header>

    <main class="p-8 max-w-[1200px] mx-auto mt-4">
        
        <?php if (isset($_GET['status'])): ?>
            <div class="bg-[var(--olive-drab)] text-[var(--aoe-gold)] border border-[var(--aoe-gold)] p-3 mb-6 text-xs font-bold tracking-widest uppercase shadow-lg text-center">
                <?php 
                if($_GET['status'] == 'oferta_creada') echo "Contrato publicado en la red pública.";
                if($_GET['status'] == 'oferta_aceptada') echo "Transacción completada. Activos transferidos al hangar.";
                if($_GET['status'] == 'oferta_cancelada') echo "Contrato anulado. Activos devueltos a tu inventario.";
                ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="bg-red-950 text-red-500 border border-red-700 p-3 mb-6 text-xs font-bold tracking-widest uppercase shadow-lg text-center">
                <?php 
                if($_GET['error'] == 'recursos_insuficientes') echo "No tienes suficientes unidades en tu hangar para esta operación.";
                if($_GET['error'] == 'oferta_no_disponible') echo "Este contrato ya fue aceptado por otra facción o fue cancelado.";
                ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php if(empty($ofertas)): ?>
                <div class="col-span-full m-panel p-10 text-center text-[var(--parchment)] opacity-50 uppercase tracking-widest text-xs font-bold">
                    <?php echo $txt['MERCADO']['MSG_SIN_OFERTAS']; ?>
                </div>
            <?php else: ?>
                <?php foreach($ofertas as $oferta): 
                    $es_mia = ($oferta['ofertante_id'] == $lider_id);
                ?>
                    <div class="m-panel !p-0 overflow-hidden shadow-2xl trade-card <?php echo $es_mia ? 'mine' : ''; ?>">
                        
                        <div class="bg-black/60 p-3 border-b border-[var(--wood-border)] flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-5 bg-black border border-[var(--wood-border)] overflow-hidden">
                                    <?php if($oferta['bandera_url']): ?>
                                        <img src="../<?php echo $oferta['bandera_url']; ?>" class="w-full h-full object-cover">
                                    <?php endif; ?>
                                </div>
                                <span class="text-[10px] font-black text-white tracking-widest uppercase font-['Cinzel']">
                                    <?php echo htmlspecialchars($oferta['nombre_equipo']); ?>
                                </span>
                            </div>
                            <span class="text-[8px] uppercase tracking-[0.2em] <?php echo $es_mia ? 'text-blue-400' : 'text-gray-500'; ?> font-bold">
                                <?php echo $es_mia ? $txt['MERCADO']['LBL_TU_CONTRATO'] : $txt['MERCADO']['LBL_OFERTA_PUBLICA']; ?>
                            </span>
                        </div>

                        <div class="p-5 flex justify-between items-center bg-gradient-to-r from-black/20 to-black/5">
                            <div class="text-center w-2/5">
                                <span class="block text-[8px] text-[var(--aoe-gold)] uppercase tracking-widest mb-1 font-bold"><?php echo $txt['MERCADO']['LBL_OFRECE']; ?></span>
                                <div class="text-xl font-black text-white font-['Cinzel']"><?php echo $oferta['cantidad_ofrecida']; ?>x</div>
                                <div class="text-[10px] text-[var(--parchment)] uppercase font-bold mt-1"><?php echo htmlspecialchars($oferta['vehiculo_ofrecido']); ?></div>
                            </div>

                            <div class="text-gray-600 w-1/5 text-center">
                                <svg class="w-8 h-8 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                            </div>

                            <div class="text-center w-2/5">
                                <span class="block text-[8px] text-gray-500 uppercase tracking-widest mb-1 font-bold"><?php echo $txt['MERCADO']['LBL_PIDE']; ?></span>
                                <div class="text-xl font-black text-green-500 font-['Cinzel']"><?php echo $oferta['cantidad_requerida']; ?>x</div>
                                <div class="text-[10px] text-[var(--parchment)] uppercase font-bold mt-1"><?php echo htmlspecialchars($oferta['vehiculo_requerido']); ?></div>
                            </div>
                        </div>

                        <div class="p-3 border-t border-[var(--wood-border)] bg-black/40 flex justify-end">
                            <?php if($es_mia): ?>
                                <form action="../logic/procesar_tradeo.php" method="POST">
                                    <input type="hidden" name="accion" value="cancelar">
                                    <input type="hidden" name="oferta_id" value="<?php echo $oferta['id']; ?>">
                                    <button type="submit" class="btn-m !bg-none !border-red-800 !text-red-500 hover:!bg-red-900 hover:!text-white !py-1 !px-4 !text-[9px]">
                                        <?php echo $txt['MERCADO']['BTN_CANCELAR']; ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <form action="../logic/procesar_tradeo.php" method="POST">
                                    <input type="hidden" name="accion" value="aceptar">
                                    <input type="hidden" name="oferta_id" value="<?php echo $oferta['id']; ?>">
                                    <button type="submit" class="btn-m !py-1 !px-6 !text-[9px]" onclick="return confirm('<?php echo $txt['MERCADO']['CONFIRMAR_ACEPTAR']; ?>');">
                                        <?php echo $txt['MERCADO']['BTN_ACEPTAR']; ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <div id="modalCrearOferta" class="hidden fixed inset-0 bg-black/90 z-[100] flex items-center justify-center p-4">
        <div class="m-panel w-full max-w-lg relative border-[var(--aoe-gold)]">
            <button onclick="cerrarModal('modalCrearOferta')" class="absolute top-4 right-4 text-[var(--parchment)] hover:text-white font-bold text-xl">&times;</button>
            <h2 class="m-title text-xl mb-6 border-b border-[var(--wood-border)] pb-2"><?php echo $txt['MERCADO']['MODAL_TITULO']; ?></h2>
            
            <form action="../logic/procesar_tradeo.php" method="POST">
                <input type="hidden" name="accion" value="crear">
                
                <div class="flex gap-6 mb-6">
                    <div class="w-1/2 bg-black/40 p-4 border border-[var(--wood-border)] shadow-inner">
                        <h3 class="text-[9px] text-[var(--aoe-gold)] font-black uppercase tracking-widest mb-3 text-center"><?php echo $txt['MERCADO']['PANEL_DAS']; ?></h3>
                        
                        <label class="block text-[8px] text-[var(--parchment)] uppercase font-bold mb-1"><?php echo $txt['MERCADO']['LBL_VEHICULO_HANGAR']; ?></label>
                        <select name="vehiculo_ofrecido_id" required class="m-input w-full mb-3 text-[10px]">
                            <option value=""><?php echo $txt['MERCADO']['LBL_SELECCIONAR']; ?></option>
                            <?php foreach($mi_inventario as $inv): ?>
                                <option value="<?php echo $inv['catalogo_id']; ?>">
                                    <?php echo htmlspecialchars($inv['nombre_vehiculo']); ?> (Disp: <?php echo $inv['cantidad']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label class="block text-[8px] text-[var(--parchment)] uppercase font-bold mb-1"><?php echo $txt['MERCADO']['LBL_CANTIDAD_ENTREGAR']; ?></label>
                        <input type="number" name="cantidad_ofrecida" min="1" value="1" required class="m-input w-full text-center text-lg font-black font-['Cinzel']">
                    </div>

                    <div class="w-1/2 bg-black/40 p-4 border border-[var(--wood-border)] shadow-inner">
                        <h3 class="text-[9px] text-green-500 font-black uppercase tracking-widest mb-3 text-center"><?php echo $txt['MERCADO']['PANEL_PIDES']; ?></h3>
                        
                        <label class="block text-[8px] text-[var(--parchment)] uppercase font-bold mb-1"><?php echo $txt['MERCADO']['LBL_VEHICULO_DESEADO']; ?></label>
                        <select name="vehiculo_requerido_id" required class="m-input w-full mb-3 text-[10px]">
                            <option value=""><?php echo $txt['MERCADO']['LBL_SELECCIONAR']; ?></option>
                            <?php foreach($catalogo as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>">
                                    <?php echo htmlspecialchars($cat['nombre_vehiculo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label class="block text-[8px] text-[var(--parchment)] uppercase font-bold mb-1"><?php echo $txt['MERCADO']['LBL_CANTIDAD_EXIGIDA']; ?></label>
                        <input type="number" name="cantidad_requerida" min="1" value="1" required class="m-input w-full text-center text-lg font-black text-green-500 font-['Cinzel']">
                    </div>
                </div>

                <div class="text-[8px] text-gray-500 uppercase tracking-widest text-center mb-6 font-bold">
                    <?php echo $txt['MERCADO']['ADVERTENCIA_BLOQUEO']; ?>
                </div>

                <button type="submit" class="btn-m w-full py-4 text-xs tracking-widest">
                    <?php echo $txt['MERCADO']['BTN_PUBLICAR']; ?>
                </button>
            </form>
        </div>
    </div>

    <script>
        function abrirModal(id) { document.getElementById(id).classList.remove('hidden'); document.body.classList.add('modal-active'); }
        function cerrarModal(id) { document.getElementById(id).classList.add('hidden'); document.body.classList.remove('modal-active'); }

        function validarImagen(input) {
            // Límite de 500 KB (500 * 1024 bytes)
            const maxSize = 500 * 1024; 
            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

            if (input.files && input.files[0]) {
                const file = input.files[0];

                // 1. Validar Tipo de Archivo
                if (!allowedTypes.includes(file.type)) {
                    alert("❌ PROTOCOLO DENEGADO: Solo se permiten formatos JPG, PNG o WEBP.");
                    input.value = ''; // Borra el archivo del input
                    return;
                }

                // 2. Validar Tamaño del Archivo
                if (file.size > maxSize) {
                    // Calcula cuánto pesa realmente para decírselo al usuario
                    let pesoReal = (file.size / 1024).toFixed(1);
                    alert("❌ CARGA EXCESIVA: La imagen pesa " + pesoReal + "KB. \nEl límite máximo de seguridad es de 500KB.\n\nPor favor, comprime el archivo y vuelve a intentarlo.");
                    input.value = ''; // Borra el archivo del input
                    return;
                }
            }
        }
    </script>
</body>
</html>