<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'lider') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/conexion.php';
$txt = require '../config/textos.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $lider_id = $_SESSION['usuario_id'];
    $accion = $_POST['accion'] ?? '';

    try {
        $pdo->beginTransaction();

        // ==========================================
        // ACCIÓN 1: REDACTAR Y PUBLICAR CONTRATO
        // ==========================================
        if ($accion === 'crear') {
            $ofrecido_id = (int)$_POST['vehiculo_ofrecido_id'];
            $cant_ofrecida = (int)$_POST['cantidad_ofrecida'];
            $requerido_id = (int)$_POST['vehiculo_requerido_id'];
            $cant_requerida = (int)$_POST['cantidad_requerida'];

            if ($cant_ofrecida <= 0 || $cant_requerida <= 0) {
                throw new Exception("Cantidades inválidas.");
            }

            // 1. Verificar si el ofertante realmente tiene esos vehículos (Bloqueamos fila para seguridad)
            $stmt_inv = $pdo->prepare("SELECT cantidad FROM inventario WHERE cuenta_id = :uid AND catalogo_id = :cid FOR UPDATE");
            $stmt_inv->execute([':uid' => $lider_id, ':cid' => $ofrecido_id]);
            $mi_item = $stmt_inv->fetch(PDO::FETCH_ASSOC);

            if (!$mi_item || $mi_item['cantidad'] < $cant_ofrecida) {
                $pdo->rollBack();
                $pdo = null;
                header("Location: ../views/lider_mercado.php?error=recursos_insuficientes");
                exit();
            }

            // 2. Restar (Bloquear) las unidades del inventario del ofertante
            $stmt_restar = $pdo->prepare("UPDATE inventario SET cantidad = cantidad - :cant WHERE cuenta_id = :uid AND catalogo_id = :cid");
            $stmt_restar->execute([':cant' => $cant_ofrecida, ':uid' => $lider_id, ':cid' => $ofrecido_id]);

            // 3. Crear la oferta en el mercado
            $stmt_crear = $pdo->prepare("INSERT INTO mercado_tradeos (ofertante_id, vehiculo_ofrecido_id, cantidad_ofrecida, vehiculo_requerido_id, cantidad_requerida) VALUES (:uid, :off_id, :off_cant, :req_id, :req_cant)");
            $stmt_crear->execute([
                ':uid' => $lider_id,
                ':off_id' => $ofrecido_id,
                ':off_cant' => $cant_ofrecida,
                ':req_id' => $requerido_id,
                ':req_cant' => $cant_requerida
            ]);

            $pdo->commit();
            $stmt_inv = null; $stmt_restar = null; $stmt_crear = null; $pdo = null;
            header("Location: ../views/lider_mercado.php?status=oferta_creada");
            exit();
        }

        // ==========================================
        // ACCIÓN 2: CANCELAR CONTRATO (Solo el dueño)
        // ==========================================
        elseif ($accion === 'cancelar') {
            $oferta_id = (int)$_POST['oferta_id'];

            // 1. Buscar la oferta y bloquearla para que nadie la acepte mientras se cancela
            $stmt_oferta = $pdo->prepare("SELECT * FROM mercado_tradeos WHERE id = :id AND estado = 'activo' FOR UPDATE");
            $stmt_oferta->execute([':id' => $oferta_id]);
            $oferta = $stmt_oferta->fetch(PDO::FETCH_ASSOC);

            // Si no existe, no es suya, o ya no está activa
            if (!$oferta || $oferta['ofertante_id'] != $lider_id) {
                $pdo->rollBack();
                $pdo = null;
                header("Location: ../views/lider_mercado.php?error=oferta_no_disponible");
                exit();
            }

            // 2. Marcar como cancelada
            $stmt_cancelar = $pdo->prepare("UPDATE mercado_tradeos SET estado = 'cancelado' WHERE id = :id");
            $stmt_cancelar->execute([':id' => $oferta_id]);

            // 3. Devolver los vehículos al inventario del dueño
            $stmt_devolver = $pdo->prepare("UPDATE inventario SET cantidad = cantidad + :cant WHERE cuenta_id = :uid AND catalogo_id = :cid");
            $stmt_devolver->execute([
                ':cant' => $oferta['cantidad_ofrecida'],
                ':uid' => $lider_id,
                ':cid' => $oferta['vehiculo_ofrecido_id']
            ]);

            $pdo->commit();
            $stmt_oferta = null; $stmt_cancelar = null; $stmt_devolver = null; $pdo = null;
            header("Location: ../views/lider_mercado.php?status=oferta_cancelada");
            exit();
        }

        // ==========================================
        // ACCIÓN 3: ACEPTAR TRATO (Otro líder)
        // ==========================================
        elseif ($accion === 'aceptar') {
            $oferta_id = (int)$_POST['oferta_id'];

            // 1. Buscar la oferta y bloquearla
            $stmt_oferta = $pdo->prepare("SELECT * FROM mercado_tradeos WHERE id = :id AND estado = 'activo' FOR UPDATE");
            $stmt_oferta->execute([':id' => $oferta_id]);
            $oferta = $stmt_oferta->fetch(PDO::FETCH_ASSOC);

            // Si no existe o el ofertante intenta aceptar su propia oferta
            if (!$oferta || $oferta['ofertante_id'] == $lider_id) {
                $pdo->rollBack();
                $pdo = null;
                header("Location: ../views/lider_mercado.php?error=oferta_no_disponible");
                exit();
            }

            // 2. Verificar que el COMPRADOR (el que acepta) tiene los vehículos requeridos
            $stmt_inv_comp = $pdo->prepare("SELECT cantidad FROM inventario WHERE cuenta_id = :uid AND catalogo_id = :cid FOR UPDATE");
            $stmt_inv_comp->execute([
                ':uid' => $lider_id, 
                ':cid' => $oferta['vehiculo_requerido_id']
            ]);
            $comp_item = $stmt_inv_comp->fetch(PDO::FETCH_ASSOC);

            if (!$comp_item || $comp_item['cantidad'] < $oferta['cantidad_requerida']) {
                $pdo->rollBack();
                $pdo = null;
                header("Location: ../views/lider_mercado.php?error=recursos_insuficientes");
                exit();
            }

            // 3. Ejecutar el intercambio (A nivel de inventarios)

            // A) Restar lo exigido al comprador
            $stmt_restar_comp = $pdo->prepare("UPDATE inventario SET cantidad = cantidad - :cant WHERE cuenta_id = :uid AND catalogo_id = :cid");
            $stmt_restar_comp->execute([
                ':cant' => $oferta['cantidad_requerida'],
                ':uid' => $lider_id,
                ':cid' => $oferta['vehiculo_requerido_id']
            ]);

            // B) Dar lo ofrecido al comprador (INSERT o UPDATE si ya tiene de ese tipo)
            $stmt_dar_comp = $pdo->prepare("INSERT INTO inventario (cuenta_id, catalogo_id, cantidad) VALUES (:uid, :cid, :cant) ON DUPLICATE KEY UPDATE cantidad = cantidad + :cant");
            $stmt_dar_comp->execute([
                ':uid' => $lider_id,
                ':cid' => $oferta['vehiculo_ofrecido_id'],
                ':cant' => $oferta['cantidad_ofrecida']
            ]);

            // C) Dar lo exigido al ofertante original (El vendedor)
            $stmt_dar_vend = $pdo->prepare("INSERT INTO inventario (cuenta_id, catalogo_id, cantidad) VALUES (:uid, :cid, :cant) ON DUPLICATE KEY UPDATE cantidad = cantidad + :cant");
            $stmt_dar_vend->execute([
                ':uid' => $oferta['ofertante_id'],
                ':cid' => $oferta['vehiculo_requerido_id'],
                ':cant' => $oferta['cantidad_requerida']
            ]);

            // 4. Marcar la oferta como completada
            $stmt_completar = $pdo->prepare("UPDATE mercado_tradeos SET estado = 'completado' WHERE id = :id");
            $stmt_completar->execute([':id' => $oferta_id]);

            $pdo->commit();
            $stmt_oferta = null; $stmt_inv_comp = null; $stmt_restar_comp = null; $stmt_dar_comp = null; $stmt_dar_vend = null; $stmt_completar = null; $pdo = null;
            header("Location: ../views/lider_mercado.php?status=oferta_aceptada");
            exit();
        }

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        $pdo = null;
        die("Error crítico en transferencia: " . $e->getMessage());
    }
} else {
    header("Location: ../views/lider_mercado.php");
    exit();
}
?>