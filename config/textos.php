<?php
// config/textos.php
// ==============================================================================
// DICCIONARIO TÁCTICO CENTRALIZADO
// Este archivo controla todos los textos, títulos y alertas del sistema.
// ==============================================================================

return [
    // ---------------------------------------------------------
    // 1. TEXTOS GLOBALES Y COMPARTIDOS
    // Usados en múltiples vistas (Header, Footer, Títulos base)
    // ---------------------------------------------------------
    'GLOBAL' => [
        'NOMBRE_PROYECTO' => 'RADAR DE FACCIONES GLOBAL',
        'SITUACION_TITULO' => 'SITUACIÓN DE OPERACIONES',
        'VOLVER_RADAR' => 'SALIR AL RADAR PÚBLICO',
        'MANDO_STAFF' => 'ADMINISTRACIÓN GLOBAL',
        'MANDO_LIDER' => 'PANEL DE MANDO OPERATIVO',
        'FOOTER_COPY' => 'Radar de Inteligencia Pública &copy; 2026 - Desarrollado por tarquitet.com',
    ],

    // ---------------------------------------------------------
    // 2. BOTONES GENÉRICOS
    // Usados en la interfaz pública y accesos
    // ---------------------------------------------------------
    'BOTONES' => [
        'DISCORD' => 'COMUNICACIONES DISCORD',
        'INGRESAR' => 'INGRESAR AL SISTEMA',
        'CONFIRMAR' => 'CONFIRMAR IDENTIDAD',
        'ENTRAR' => 'ENTRAR AL PANEL',
    ],

    // ---------------------------------------------------------
    // 3. PANTALLA DE ACCESO (login.php)
    // ---------------------------------------------------------
    'LOGIN' => [
        'TITULO' => 'ACCESO AL SISTEMA',
        'USUARIO' => 'IDENTIDAD DE USUARIO',
        'CLAVE' => 'CLAVE DE ENLACE',
        'ERR_CREDENCIALES' => 'Usuario o contraseña incorrectos.',
        'ERR_CAMPOS' => 'Por favor, completa todos los campos.',
    ],

    // ---------------------------------------------------------
    // 4. RADAR PÚBLICO (index.php)
    // ---------------------------------------------------------
    'RADAR' => [
        'COL_ESTANDARTE' => 'ESTANDARTE OPERATIVO',
        'COL_IDENTIDAD' => 'IDENTIDAD DE LA FACCIÓN',
        'COL_TERRITORIO' => 'JURISDICCIÓN TERRITORIAL',
        'SIN_DATOS' => 'SIN SEÑALES EN EL RADAR...',
        'NEUTRAL' => 'TERRITORIO NEUTRAL',
    ],

    // ---------------------------------------------------------
    // 5. BARRAS DE NAVEGACIÓN (includes/nav_staff.php y nav_lider.php)
    // ---------------------------------------------------------
    'NAV' => [
        'GESTION_GRUPOS' => 'GESTIÓN DE GRUPOS',
        'CATALOGO' => 'CATÁLOGO DE TIENDA',
        'MAPA_GLOBAL' => 'MAPA GLOBAL',
        'RESUMEN' => 'RESUMEN DE ESTADO',
        'TIENDA' => 'TIENDA MILITAR',
        'INVENTARIO' => 'INVENTARIO Y FLOTAS',
        'LOGOUT' => 'CERRAR SESIÓN',
    ],

    // ==============================================================================
    // VISTAS DEL ALTO MANDO (STAFF / JACKY)
    // ==============================================================================

    // Textos para la vista de inspección de inventarios (staff_ver_inventario.php)
    'INVENTARIO_STAFF' => [
        'TITULO' => 'INVENTARIO DE FACCIÓN',
        'SUBTITULO' => 'INSPECCIÓN DE ACTIVOS MILITARES',
        'BTN_VOLVER' => 'VOLVER AL PANEL DE MANDO',
        'CAT_TANQUES' => 'BLINDADOS',
        'CAT_AVIONES' => 'FUERZA AÉREA',
        'CAT_FLOTAS' => 'GRUPOS NAVALES',
        'DISPONIBILIDAD' => 'SECTOR GLOBAL:',
        'SLOT_NAVAL' => 'SLOT NAVAL',
        'LBL_INSIGNIA' => 'UNIDAD INSIGNIA',
        'LBL_ESCOLTAS' => 'UNIDADES DE ESCOLTA',
        'SIN_FLOTA' => 'SLOT INACTIVO / SIN DESPLIEGUE',
        'BTN_DESTRUIR' => 'ELIMINAR GRUPO TÁCTICO',
        'SIN_ACTIVOS' => 'SIN UNIDADES REGISTRADAS EN',
        'LBL_CANTIDAD' => 'CANTIDAD OPERATIVA',
        'NO_IMG' => 'NO VISUAL'
    ],

    // Textos para la gestión del catálogo y editor de precios (staff_tienda.php)
    'STAFF_TIENDA' => [
        'BTN_PRECIOS' => 'AJUSTAR PRECIOS',
        'BTN_ANADIR' => 'AÑADIR ACTIVO',
        'MSJ_EXITO' => 'Operación confirmada. Catálogo actualizado.',
        'CAT_TANQUES' => 'BLINDADOS',
        'CAT_AVIONES' => 'FUERZA AÉREA',
        'FILTRO_PAIS' => 'SECTOR NACIONAL:',
        'TH_RANGO' => 'NIVEL',
        'TH_CLASE' => 'CLASE',
        'TH_VEHICULO' => 'DENOMINACIÓN',
        'TH_DINERO' => 'FONDO ($)',
        'TH_ACERO' => 'ACERO',
        'TH_PETROLEO' => 'COMB.',
        'TH_ACCION' => 'ORDEN',
        'BTN_BORRAR' => 'ELIMINAR',
        'SIN_VEHICULOS' => 'SIN ACTIVOS REGISTRADOS EN ESTA CLASIFICACIÓN.',
        'NO_IMG' => 'NO VISUAL',
        'MODAL_ADD_TITULO' => 'REGISTRAR NUEVO ACTIVO',
        'LBL_TIPO' => 'CATEGORÍA',
        'LBL_SUBTIPO' => 'CLASIFICACIÓN',
        'LBL_NACION' => 'NACIÓN ASIGNADA',
        'LBL_RANGO' => 'RANGO (1-8)',
        'LBL_COSTO_SYS' => 'COSTOS ASIGNADOS AUTOMÁTICAMENTE',
        'LBL_NOMBRE' => 'NOMBRE OPERATIVO',
        'LBL_IMG' => 'FOTOGRAFÍA TÁCTICA',
        'LBL_PREMIUM_TIT' => 'ESTATUS DE ÉLITE',
        'LBL_PREMIUM_DESC' => '¿Clasificar como unidad Premium?',
        'BTN_CONFIRMAR' => 'CONFIRMAR Y REGISTRAR',
        'MODAL_PR_TITULO' => 'AJUSTE DE ECONOMÍA GLOBAL',
        'MODAL_PR_DESC' => 'Modifica los costos base para la producción de activos.',
        'LBL_ACTUAL' => 'ACTUAL',
        'BTN_ACTUALIZAR_PR' => 'SOBREESCRIBIR VALORES',
        'SIN_PAISES' => 'SIN CONEXIÓN A NACIONES',
    ],

    // Textos para la gestión de territorios (staff_paises.php)
    'STAFF_PAISES' => [
        'TITULO' => 'CATÁLOGO DE NACIONES',
        'SUBTITULO' => 'Gestión de territorios y fronteras disponibles para las facciones.',
        'MSJ_AGREGADO' => 'Territorio anexado correctamente al sistema.',
        'MSJ_ELIMINADO' => 'Territorio purgado del mapa global.',
        'MSJ_DUPLICADO' => 'Alerta: La denominación de esta nación ya existe.',
        'PANEL_ADD_TITULO' => 'REGISTRAR TERRITORIO',
        'LBL_NOMBRE' => 'DENOMINACIÓN DE LA NACIÓN',
        'BTN_ADD' => 'AÑADIR AL MAPA GLOBAL',
        'TH_ID' => 'ID',
        'TH_NACION' => 'TERRITORIO',
        'TH_ACCION' => 'ORDEN TÁCTICA',
        'BTN_BORRAR' => 'PURGAR',
        'CONFIRMAR_BORRAR' => '¿Autorizar purga territorial? Esta acción no se puede deshacer.',
    ],

    // Textos para el panel central de administración y NUKE (staff_dashboard.php)
    'STAFF_DASHBOARD' => [
        'TITULO' => 'MANDO CENTRAL DE FACCIONES',
        'SUBTITULO' => 'Administración global de recursos, inventarios y fronteras territoriales.',
        'BTN_PAISES' => 'MAPA GLOBAL',
        'BTN_NUKE' => 'PROTOCOLO NUKE',
        'TH_EQUIPO' => 'FACCIÓN OPERATIVA',
        'TH_DINERO' => 'CAPITAL ($)',
        'TH_ACERO' => 'ACERO (t)',
        'TH_PETROLEO' => 'COMB. (L)',
        'TH_NACIONES' => 'JURISDICCIÓN HABILITADA',
        'TH_GESTION' => 'FRONTERAS',
        'TH_ACCIONES' => 'ORDEN TÁCTICA',
        'SIN_NACIONES' => 'TERRITORIO NEUTRAL',
        'BTN_EDITAR' => 'EDITAR',
        'BTN_GUARDAR' => 'SOBREESCRIBIR',
        'BTN_INVENTARIO' => 'INSPECCIONAR',
        'MODAL_NAC_TITULO' => 'REASIGNACIÓN DE TERRITORIOS',
        'BTN_CONFIRMAR' => 'CONFIRMAR ANEXIÓN',
        'MODAL_PAISES_TITULO' => 'REGISTRO DE NACIONES GLOBALES',
        'PH_NUEVO_PAIS' => 'Nueva nación...',
        'BTN_ADD_PAIS' => 'ANEXAR',
        'BTN_DEL_PAIS' => 'PURGAR',
        'MODAL_NUKE_TITULO' => 'SISTEMA DE ANIQUILACIÓN GLOBAL',
        'NUKE_CONFIRMAR' => '¿AUTORIZAR DESTRUCCIÓN TOTAL?',
        'NUKE_DESC' => 'Esta acción borrará inventarios, flotas y recursos de todas las facciones. No hay marcha atrás.',
        'NUKE_INSTRUCCION' => 'Escribe REINICIAR para confirmar código de lanzamiento:',
        'BTN_ACTIVAR_NUKE' => 'ACTIVAR SECUENCIA',
        'ERR_NUKE' => 'Código de lanzamiento denegado. Secuencia abortada.',
        'TH_PASSWORD' => 'ACCESO (PASS)',
        'PH_PASSWORD' => 'Nueva clave...',
    ],

    // ==============================================================================
    // VISTAS DE LOS LÍDERES DE FACCIÓN
    // ==============================================================================

    // Textos para la adquisición de vehículos en el Árbol Tecnológico (lider_tienda.php)
    'LIDER_TIENDA' => [
        'TITULO' => 'HANGAR TECNOLÓGICO',
        'BTN_TANQUES' => 'BLINDADOS',
        'BTN_AVIONES' => 'FUERZA AÉREA',
        'BTN_ADD_ARBOL' => 'REGISTRAR SUMINISTRO',
        'LBL_CAPITAL' => 'FONDOS',
        'LBL_ACERO' => 'RESERVA ACERO',
        'LBL_COMBUSTIBLE' => 'COMBUSTIBLE',
        'SECCION_INVESTIGACION' => 'UNIDADES INVESTIGABLES',
        'SECCION_PREMIUM' => 'ACTIVOS DE ÉLITE',
        'SIN_PREMIUM' => 'SIN ACTIVOS DE ÉLITE EN ESTE SECTOR',
        'BTN_ADQUIRIR' => 'ADQUIRIR',
        'LBL_CANTIDAD' => 'CANT.',
        'NO_IMG' => 'NO VISUAL',
        'PREMIUM_TAG' => 'PREMIUM',
        'MODAL_TITULO' => 'AÑADIR SUMINISTRO AL ÁRBOL',
        'LBL_CATEGORIA' => 'CATEGORÍA',
        'LBL_CLASE' => 'CLASIFICACIÓN',
        'LBL_NACION' => 'NACIÓN DESTINO',
        'LBL_RANGO' => 'RANGO (1-8)',
        'LBL_COSTOS' => 'COSTOS ASIGNADOS AUTOMÁTICAMENTE',
        'LBL_NOMBRE' => 'NOMBRE OPERATIVO',
        'LBL_IMG' => 'FOTOGRAFÍA TÁCTICA',
        'LBL_PREMIUM_TIT' => 'ESTATUS ESPECIAL',
        'LBL_PREMIUM_DESC' => '¿Clasificar como unidad Premium?',
        'BTN_CONFIRMAR' => 'CONFIRMAR REGISTRO',
        'SECTOR_BLOQUEADO' => 'SECTOR FUERA DE JURISDICCIÓN',
        'SECTOR_BLOQUEADO_DESC' => 'Contacte con el Alto Mando para obtener derechos de despliegue en esta región.',
    ],

    // Textos para la vista de unidades poseídas y despliegue de flotas (lider_inventario.php)
    'LIDER_INVENTARIO' => [
        'TITULO' => 'HANGAR OPERATIVO',
        'CAT_TANQUES' => 'BLINDADOS',
        'CAT_AVIONES' => 'FUERZA AÉREA',
        'CAT_FLOTAS' => 'FLOTAS',
        'SEC_RESERVA' => 'UNIDADES EN RESERVA',
        'SEC_PREMIUM' => 'ACTIVOS DE ÉLITE',
        'TH_SLOT' => 'SLOT',
        'TH_INSIGNIA' => 'UNIDAD INSIGNIA',
        'TH_ESCOLTA' => 'ESCOLTA',
        'TH_ESTADO' => 'ESTADO',
        'ESTADO_OPERATIVO' => 'OPERATIVO',
        'ESTADO_STANDBY' => 'EN ESPERA',
        'VACIO' => 'VACÍO',
        'MODAL_TITULO' => 'CONFIGURACIÓN TÁCTICA 0',
        'LBL_INSIGNIA' => 'UNIDAD INSIGNIA PRINCIPAL',
        'PH_UNIDAD' => 'Designación táctica...',
        'BTN_DESPLIEGUE' => 'ORDENAR DESPLIEGUE',
        'LBL_STOCK' => 'UNIDADES',
        'NO_IMG' => 'NO VISUAL'
    ],

    // Textos para el panel principal y radar de enemigos (lider_dashboard.php)
    'LIDER_DASHBOARD' => [
        'TITULO_PROPIO' => 'ESTADO DE MI FACCIÓN',
        'TH_IDENTIDAD' => 'IDENTIDAD',
        'TH_RECURSOS' => 'RECURSOS ACTUALES',
        'TH_ESTANDARTE' => 'ESTANDARTE',
        'TH_ACCIONES' => 'ACCIONES',
        'SIN_ASIGNAR' => 'SIN ASIGNAR',
        'ID_MANDO' => 'ID DE MANDO: #',
        'LBL_DINERO' => 'DINERO',
        'LBL_ACERO' => 'ACERO',
        'LBL_PETROLEO' => 'PETRÓLEO',
        'NO_FLAG' => 'SIN VISUAL',
        'BTN_CONFIGURAR' => 'CONFIGURAR IDENTIDAD',
        'TITULO_RADAR' => 'RADAR DE FACCIONES ENEMIGAS',
        'TH_ENEMIGO' => 'ENEMIGO',
        'TH_JURISDICCION' => 'JURISDICCIÓN',
        'MODAL_TITULO' => 'PROTOCOLO DE IDENTIDAD',
        'LBL_NOMBRE' => 'NOMBRE DE FACCIÓN',
        'LBL_ESTANDARTE' => 'ESTANDARTE TÁCTICO',
        'BTN_CONFIRMAR' => 'CONFIRMAR IDENTIDAD'
    ],

    // ==============================================================================
    // LÓGICA DE SERVIDOR Y ALERTAS INTERNAS (CARPETA logic/)
    // ==============================================================================
    'LOGIC' => [
        'ERR_CRITICO_SUMINISTRO' => 'ERROR CRÍTICO EN LOGÍSTICA DE SUMINISTRO: ',
        'ERR_ACCESO_DENEGADO' => 'ACCESO DENEGADO. Rango insuficiente.',
        'ERR_DB_CATALOGO_STAFF' => 'FALLO CRÍTICO EN LA ACTUALIZACIÓN DEL CATÁLOGO GLOBAL: ',
        'ERR_DB_PAISES_STAFF' => 'FALLO CRÍTICO EN EL REGISTRO TERRITORIAL: ',
        'ERR_ACTIVO_NO_ENCONTRADO' => 'Activo militar no encontrado en los registros.',
        'ERR_CADENA_SUMINISTRO' => 'FALLO EN LA CADENA DE SUMINISTRO TÁCTICO: ',
        'ERR_NUKE_CRITICO' => 'FALLO CATASTRÓFICO EN LA SECUENCIA DE ANIQUILACIÓN: ',
        'LOG_NUKE' => '☢️ REBOOT TOTAL: Se ha reiniciado la temporada. Todos los activos territoriales y militares han sido purgados.',
        'ERR_ESCRITURA_PRECIOS' => 'ERROR: Permisos de escritura denegados en el sector de configuración (config/).',
        'ERR_COMUNICACIONES_NAVALES' => 'ERROR EN LA RED DE COMUNICACIONES NAVALES: ',
        'ERR_DESTRUIR_FLOTA' => 'FALLO EN EL PROTOCOLO DE DESTRUCCIÓN NAVAL: ',
        'LOG_FLOTA_DESTRUIDA' => '💥 REPORTE DE COMBATE: Una flota operativa ha sido aniquilada por orden directa del Alto Mando. Objetivo: ',
        'ERR_ACTUALIZAR_RECURSOS' => 'FALLO DE SINCRONIZACIÓN EN LA TRANSFERENCIA DE RECURSOS: ',
        'ERR_ACTUALIZAR_PERFIL' => 'FALLO EN EL PROTOCOLO DE IDENTIDAD: No se pudo registrar el nuevo estandarte. ',
    ]
];