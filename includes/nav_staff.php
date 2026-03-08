<?php $pagina_actual = basename($_SERVER['PHP_SELF']); ?>
<nav class="m-panel !p-2 !rounded-none border-x-0 border-t-0 flex justify-between items-center overflow-x-auto sticky top-0 z-50 shadow-2xl mb-6 bg-[#0a0f0a]/90 backdrop-blur-md border-b border-[var(--wood-border)]">
    <div class="flex items-center gap-6">
        <div class="m-title text-sm whitespace-nowrap ml-4 border-r border-[var(--wood-border)] pr-6 text-[var(--aoe-gold)] flex items-center gap-2">
            ⭐ <?php echo $txt['GLOBAL']['MANDO_STAFF']; ?>
        </div>
        
        <div class="flex gap-2 min-w-max">
            <a href="staff_dashboard.php" class="btn-m !py-1 !px-4 text-[10px] transition-all <?php echo ($pagina_actual == 'staff_dashboard.php' || $pagina_actual == 'staff_ver_inventario.php') ? 'bg-[var(--dark-olive)] border-[var(--aoe-gold)] text-[var(--aoe-gold)] shadow-[inset_0_0_10px_rgba(255,204,0,0.2)]' : 'grayscale opacity-60 hover:grayscale-0 hover:opacity-100'; ?>">
                🌐 <?php echo $txt['NAV']['GESTION_GRUPOS']; ?>
            </a>
            
            <a href="staff_tienda.php" class="btn-m !py-1 !px-4 text-[10px] transition-all <?php echo $pagina_actual == 'staff_tienda.php' ? 'bg-[var(--dark-olive)] border-[var(--aoe-gold)] text-[var(--aoe-gold)] shadow-[inset_0_0_10px_rgba(255,204,0,0.2)]' : 'grayscale opacity-60 hover:grayscale-0 hover:opacity-100'; ?>">
                ⚙️ <?php echo $txt['NAV']['CATALOGO']; ?>
            </a>
        </div>
    </div>

    <div class="flex items-center gap-4 mr-4">
        <a href="../logout.php" class="btn-m !bg-none !border-red-900/50 !text-red-500 hover:!bg-red-950 hover:!border-red-600 hover:!text-white !py-1 !px-3 text-[9px] shadow-none transition">
            🚪 <?php echo $txt['NAV']['LOGOUT']; ?>
        </a>
    </div>
</nav>