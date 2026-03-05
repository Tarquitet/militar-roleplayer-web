<?php $pagina_actual = basename($_SERVER['PHP_SELF']); ?>
<nav class="bg-gray-800 p-4 shadow-md border-b border-gray-700 flex justify-between items-center overflow-x-auto">
    <div class="text-xl font-bold text-purple-400 whitespace-nowrap mr-8">Administración Global</div>
    <div class="flex gap-6 min-w-max">
        
        <a href="staff_dashboard.php" class="<?php echo $pagina_actual == 'staff_dashboard.php' ? 'text-purple-400 font-bold border-b-2 border-purple-400 pb-1' : 'text-gray-500 hover:text-gray-400 transition'; ?>">
            Gestion de Grupos
        </a>
        
        <a href="staff_tienda.php" class="<?php echo $pagina_actual == 'staff_tienda.php' ? 'text-purple-400 font-bold border-b-2 border-purple-400 pb-1' : 'text-gray-500 hover:text-gray-400 transition'; ?>">
            Catálogo de Tienda
        </a>
        
        <a href="../logout.php" class="text-red-400 hover:text-red-300 transition pl-4 border-l border-gray-600">
            Cerrar Sesión
        </a>
        
    </div>
</nav>