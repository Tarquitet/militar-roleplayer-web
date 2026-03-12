<?php
// Por precaución, si la variable $txt no existe en la página donde se llama, la cargamos.
if (!isset($txt)) {
    $txt = require __DIR__ . '/../config/textos.php';
}
?>
<footer class="mt-auto border-t border-white/5 bg-[#050505] py-6 relative z-50 w-full shadow-[0_-5px_20px_rgba(0,0,0,0.5)]">
    <div class="max-w-[1600px] mx-auto px-8 flex flex-col md:flex-row justify-between items-center gap-6">

        <div class="text-gray-500 text-[9px] uppercase font-bold tracking-[0.3em] text-center md:text-left">
            <?php echo $txt['GLOBAL']['FOOTER_COPY']; ?>
        </div>

        <div class="flex flex-wrap justify-center gap-4">
            
            <a href="https://tarquitet.com" target="_blank" class="group flex items-center gap-2 bg-[#0a0a0a] border border-gray-800 hover:border-[#c5a059] px-4 py-2 transition-all duration-300">
                <span class="text-[#c5a059] text-[12px] group-hover:scale-110 transition-transform duration-300">✦</span>
                <span class="text-[9px] text-gray-400 group-hover:text-white font-black uppercase tracking-widest">
                    <?php echo $txt['GLOBAL']['BTN_PORTAFOLIO']; ?>
                </span>
            </a>

            <a href="https://streamelements.com/tarquitet/tip" target="_blank" class="group flex items-center gap-2 bg-[#0a0a0a] border border-gray-800 hover:border-[#c5a059] px-4 py-2 transition-all duration-300 shadow-md hover:shadow-[0_0_15px_rgba(197,160,89,0.2)]">
                <span class="text-[14px] grayscale opacity-70 group-hover:grayscale-0 group-hover:opacity-100 group-hover:scale-110 transition-all duration-300">☕</span>
                <span class="text-[9px] text-gray-400 group-hover:text-[#c5a059] font-black uppercase tracking-widest">
                    <?php echo $txt['GLOBAL']['BTN_COFFEE']; ?>
                </span>
            </a>

        </div>

    </div>
</footer>