<?php
session_start();
header('Content-Type: application/json');

// 1. Configuración de Base de Datos centralizada
require __DIR__ . '/../conexion.php';

// 2. Verificación de Seguridad
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autenticado']);
    exit();
}

$usuario_nombre = $_SESSION['usuario'];
$id_liga = $_POST['id_liga'] ?? null;
$pack_id = $_POST['pack_id'] ?? null;

if (!$id_liga || !$pack_id) {
    echo json_encode(['status' => 'error', 'message' => 'Datos de compra incompletos.']);
    exit();
}

// 3. Consultar ID del Usuario
$stmtUser = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE nombre = :nombre");
$stmtUser->execute(['nombre' => $usuario_nombre]);
$userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$userData) {
    echo json_encode(['status' => 'error', 'message' => 'Usuario no encontrado.']);
    exit();
}
$id_usuario = $userData['id_usuario'];

// 4. Definición de los Packs de la Tienda
$packs_disponibles = [
    'pack1' => ['precio' => 1.99, 'monedas' => 1000000, 'nombre' => '1 Millón'],
    'pack2' => ['precio' => 4.99, 'monedas' => 3500000, 'nombre' => '3.5 Millones'],
    'pack3' => ['precio' => 9.99, 'monedas' => 8000000, 'nombre' => '8 Millones'],
    'pack4' => ['precio' => 19.99, 'monedas' => 20000000, 'nombre' => '20 Millones']
];

if (!array_key_exists($pack_id, $packs_disponibles)) {
    echo json_encode(['status' => 'error', 'message' => 'El pack seleccionado no es válido.']);
    exit();
}

$monedas_a_sumar = $packs_disponibles[$pack_id]['monedas'];

// 5. Inyectar los fondos en la base de datos para la liga y usuario específicos
try {
    $stmtUpdate = $conexion->prepare("
        UPDATE equipos_fantasy 
        SET presupuesto_disponible = presupuesto_disponible + :monedas 
        WHERE id_usuario = :id_usuario AND id_liga = :id_liga
    ");
    
    $stmtUpdate->execute([
        'monedas'    => $monedas_a_sumar,
        'id_usuario' => $id_usuario,
        'id_liga'    => $id_liga
    ]);

    if ($stmtUpdate->rowCount() === 0) {
        echo json_encode(['status' => 'error', 'message' => 'No se encontró tu equipo en esta liga para añadir los fondos.']);
        exit();
    }

    echo json_encode([
        'status' => 'success',
        'message' => "¡Compra exitosa! Se han añadido " . number_format($monedas_a_sumar, 0, ',', '.') . " € a tu presupuesto."
    ]);
    exit();

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error al procesar el pago en la base de datos.']);
    exit();
}