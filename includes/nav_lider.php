<?php $pagina_actual = basename($_SERVER['PHP_SELF']); ?>
<nav class="bg-gray-800 p-4 shadow-md border-b border-gray-700 flex justify-between items-center overflow-x-auto">
    <div class="text-xl font-bold text-blue-400 whitespace-nowrap mr-8">Panel de Mando</div>
    <div class="flex gap-6 min-w-max">
        
        <a href="lider_dashboard.php" class="<?php echo $pagina_actual == 'lider_dashboard.php' ? 'text-blue-400 font-bold border-b-2 border-blue-400 pb-1' : 'text-gray-500 hover:text-gray-400 transition'; ?>">
            Resumen
        </a>
        
        <a href="lider_tienda.php" class="<?php echo $pagina_actual == 'lider_tienda.php' ? 'text-blue-400 font-bold border-b-2 border-blue-400 pb-1' : 'text-gray-500 hover:text-gray-400 transition'; ?>">
            Tienda Militar
        </a>
        
        <a href="lider_inventario.php" class="<?php echo $pagina_actual == 'lider_inventario.php' ? 'text-blue-400 font-bold border-b-2 border-blue-400 pb-1' : 'text-gray-500 hover:text-gray-400 transition'; ?>">
            Inventario y Flotas
        </a>
        
        <a href="../logout.php" class="text-red-400 hover:text-red-300 transition pl-4 border-l border-gray-600">
            Cerrar Sesión
        </a>
        
    </div>
</nav>