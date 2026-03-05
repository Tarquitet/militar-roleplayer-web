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
    // Consultamos todas las naciones registradas
    $stmt = $pdo->query("SELECT id, nombre FROM naciones ORDER BY nombre ASC");
    $naciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al cargar los países: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title><?php echo $txt['GLOBAL']['MANDO_STAFF']; ?> - Naciones</title>
    <?php include '../includes/head.php'; ?>
</head>
<body class="pb-10">

    <?php include '../includes/nav_staff.php'; ?>

    <main class="p-8 max-w-5xl mx-auto">
        <div class="mb-8 border-b border-[var(--wood-border)] pb-4">
            <h1 class="m-title text-3xl font-bold mb-2"><?php echo $txt['STAFF_PAISES']['TITULO']; ?></h1>
            <p class="text-[var(--parchment)] text-xs uppercase tracking-widest font-bold">
                <?php echo $txt['STAFF_PAISES']['SUBTITULO']; ?>
            </p>
        </div>

        <?php if (isset($_GET['mensaje'])): ?>
            <?php if ($_GET['mensaje'] == 'agregado'): ?>
                <div class="bg-[var(--olive-drab)] text-[var(--aoe-gold)] border border-[var(--aoe-gold)] p-3 mb-6 text-xs font-bold tracking-widest uppercase shadow-lg text-center">
                    <?php echo $txt['STAFF_PAISES']['MSJ_AGREGADO']; ?>
                </div>
            <?php elseif ($_GET['mensaje'] == 'eliminado'): ?>
                <div class="bg-red-950/50 border border-red-800 text-red-500 p-3 mb-6 text-xs font-bold tracking-widest uppercase shadow-lg text-center">
                    <?php echo $txt['STAFF_PAISES']['MSJ_ELIMINADO']; ?>
                </div>
            <?php elseif ($_GET['mensaje'] == 'duplicado'): ?>
                <div class="bg-yellow-900/50 border border-yellow-600 text-yellow-500 p-3 mb-6 text-xs font-bold tracking-widest uppercase shadow-lg text-center">
                    <?php echo $txt['STAFF_PAISES']['MSJ_DUPLICADO']; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            
            <div class="md:col-span-1 m-panel h-fit">
                <h2 class="text-xl font-bold text-[var(--aoe-gold)] mb-6 border-b border-[var(--wood-border)] pb-2 font-['Cinzel']">
                    <?php echo $txt['STAFF_PAISES']['PANEL_ADD_TITULO']; ?>
                </h2>
                
                <form action="../logic/procesar_pais.php" method="POST">
                    <input type="hidden" name="accion" value="agregar">
                    <div class="mb-6">
                        <label class="block text-[10px] text-[var(--parchment)] uppercase font-bold mb-2 tracking-widest" for="nombre_pais">
                            <?php echo $txt['STAFF_PAISES']['LBL_NOMBRE']; ?>
                        </label>
                        <input type="text" id="nombre_pais" name="nombre_pais" required placeholder="..."
                               class="m-input w-full outline-none focus:border-[var(--aoe-gold)]">
                    </div>
                    <button type="submit" class="btn-m w-full py-3 text-[10px] tracking-widest">
                        <?php echo $txt['STAFF_PAISES']['BTN_ADD']; ?>
                    </button>
                </form>
            </div>

            <div class="md:col-span-2 m-panel !p-0 overflow-hidden">
                <table class="w-full text-left border-collapse table-m">
                    <thead>
                        <tr class="text-[9px] uppercase tracking-widest">
                            <th class="p-4 w-20 text-center border-r border-[var(--wood-border)]"><?php echo $txt['STAFF_PAISES']['TH_ID']; ?></th>
                            <th class="p-4"><?php echo $txt['STAFF_PAISES']['TH_NACION']; ?></th>
                            <th class="p-4 text-right"><?php echo $txt['STAFF_PAISES']['TH_ACCION']; ?></th>
                        </tr>
                    </thead>
                    <tbody class="text-sm font-bold text-[var(--text-main)]">
                        <?php foreach ($naciones as $nacion): ?>
                            <tr class="transition hover:bg-white/5">
                                <td class="p-4 text-center border-r border-[var(--wood-border)]/30 text-gray-500 font-black">
                                    #<?php echo $nacion['id']; ?>
                                </td>
                                <td class="p-4 text-[var(--parchment)] uppercase tracking-wide">
                                    <?php echo htmlspecialchars($nacion['nombre']); ?>
                                </td>
                                <td class="p-4 text-right">
                                    <form action="../logic/procesar_pais.php" method="POST" onsubmit="return confirm('<?php echo $txt['STAFF_PAISES']['CONFIRMAR_BORRAR']; ?>');">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id_pais" value="<?php echo $nacion['id']; ?>">
                                        <button type="submit" class="btn-m !bg-none !border-red-900 !text-red-500 hover:!bg-red-950 !py-1 !px-3 text-[9px] shadow-none">
                                            <?php echo $txt['STAFF_PAISES']['BTN_BORRAR']; ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
        </div>
    </main>
</body>
</html>