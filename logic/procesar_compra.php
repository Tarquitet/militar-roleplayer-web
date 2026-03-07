<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'lider') exit();

require_once '../config/conexion.php';

// Importamos el diccionario de textos
$txt = require '../config/textos.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $lider_id = $_SESSION['usuario_id'];
    $item_id = (int)$_POST['catalogo_id'];
    // Captura de cantidad desde el formulario del Árbol Tecnológico
    $cantidad = isset($_POST['cantidad']) ? (int)$_POST['cantidad'] : 1;

    // Validación de seguridad para cantidades negativas
    if ($cantidad <= 0) {
        header("Location: ../views/lider_tienda.php?error=cantidad_invalida");
        exit();
    }

    try {
        $pdo->beginTransaction();

        // --- 1. REGLA ESTRICTA DE PLANOS (NUEVO) ---
        // Verificar si el líder tiene el plano (patente) desbloqueado ANTES de permitir la compra física
        $stmt_check_plano = $pdo->prepare("SELECT id FROM planos_desbloqueados WHERE cuenta_id = :uid AND catalogo_id = :cid");
        $stmt_check_plano->execute([':uid' => $lider_id, ':cid' => $item_id]);
        
        if (!$stmt_check_plano->fetch()) {
            $pdo->rollBack();
            $stmt_check_plano = null;
            $pdo = null;
            header("Location: ../views/lider_tienda.php?error=plano_requerido");
            exit();
        }
        // ------------------------------------------

        // 2. Obtener precio base del vehículo
        // OPTIMIZACIÓN: Solo pedimos los costos necesarios para ahorrar memoria RAM
        $stmt_item = $pdo->prepare("SELECT costo_dinero, costo_acero, costo_petroleo FROM catalogo_tienda WHERE id = :id");
        $stmt_item->execute([':id' => $item_id]);
        $item = $stmt_item->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            $pdo->rollBack();
            $stmt_item = null;
            $pdo = null;
            // Usamos el texto centralizado para la excepción
            throw new Exception($txt['LOGIC']['ERR_ACTIVO_NO_ENCONTRADO']);
        }

        // 3. Cálculo de Costo Total (Precio x Cantidad)
        $total_dinero = $item['costo_dinero'] * $cantidad;
        $total_acero = $item['costo_acero'] * $cantidad;
        $total_petroleo = $item['costo_petroleo'] * $cantidad;

        // 4. Obtener recursos del líder con bloqueo de fila (Evita bugs si compra rápido)
        $stmt_user = $pdo->prepare("SELECT dinero, acero, petroleo FROM cuentas WHERE id = :id FOR UPDATE");
        $stmt_user->execute([':id' => $lider_id]);
        $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

        // 5. Validación de Solvencia Económica
        if ($user['dinero'] >= $total_dinero && 
            $user['acero'] >= $total_acero && 
            $user['petroleo'] >= $total_petroleo) {
            
            // A. Deducción de Recursos
            $stmt_pay = $pdo->prepare("UPDATE cuentas SET dinero = dinero - :d, acero = acero - :a, petroleo = petroleo - :p WHERE id = :id");
            $stmt_pay->execute([
                ':d' => $total_dinero,
                ':a' => $total_acero,
                ':p' => $total_petroleo,
                ':id' => $lider_id
            ]);

            // B. Incremento en Hangar (Suma la cantidad solicitada)
            // Nota: Se requiere índice UNIQUE en (cuenta_id, catalogo_id) en la tabla inventario
            $stmt_inv = $pdo->prepare("INSERT INTO inventario (cuenta_id, catalogo_id, cantidad) 
                                       VALUES (:uid, :cid, :cant) 
                                       ON DUPLICATE KEY UPDATE cantidad = cantidad + :cant");
            $stmt_inv->execute([
                ':uid' => $lider_id, 
                ':cid' => $item_id,
                ':cant' => $cantidad
            ]);

            $pdo->commit();

            // CIERRE TÁCTICO: Liberamos la conexión a InfinityFree inmediatamente
            $stmt_check_plano = null; $stmt_item = null; $stmt_user = null; $stmt_pay = null; $stmt_inv = null;
            $pdo = null;

            header("Location: ../views/lider_tienda.php?status=compra_exitosa&unidades=$cantidad");
            exit();

        } else {
            $pdo->rollBack();
            
            // CIERRE TÁCTICO EN RECHAZO
            $stmt_check_plano = null; $stmt_item = null; $stmt_user = null;
            $pdo = null;
            
            header("Location: ../views/lider_tienda.php?error=fondos_insuficientes");
            exit();
        }

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // CIERRE TÁCTICO EN ERROR
        $stmt_check_plano = null; $stmt_item = null; $stmt_user = null;
        $pdo = null;
        
        // Usamos el texto centralizado para el error crítico
        die($txt['LOGIC']['ERR_CADENA_SUMINISTRO'] . $e->getMessage());
    }
} else {
    header("Location: ../views/lider_tienda.php");
    exit();
}
?>