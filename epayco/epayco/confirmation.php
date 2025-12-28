<?php
/**
 * ePayco Confirmation Handler
 * Procesa las confirmaciones de pago desde ePayco
 */

/**
 * Función para registrar transacciones en archivo log
 */
function logTransaction($order_id, $event_type, $message, $epayco_state) {
    $log_dir = dirname(dirname(dirname(dirname(__DIR__)))) . '/administrator/logs';
    $log_file = $log_dir . '/epayco_transactions.log';
    
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] Order: {$order_id} | Type: {$event_type} | ePayco State: {$epayco_state} | Message: {$message}\n";
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

// Incluir configuración de Joomla
require_once('../../../../configuration.php');
$objConf = new JConfig();

// Configuración de base de datos
$host = $objConf->host;
$login = $objConf->user;
$password = $objConf->password;
$basedatos = $objConf->db;
$pf = $objConf->dbprefix;

// Crear conexión
$conn = mysqli_connect($host, $login, $password, $basedatos);

// Verificar conexión
if (!$conn) {
    error_log("ePayco Confirmation - Error de conexión BD: " . mysqli_connect_error());
    http_response_code(500);
    die("Connection failed");
}

// Configurar charset
mysqli_set_charset($conn, "utf8mb4");

/**
 * RECIBIR DATOS DE EPAYCO
 */
// Usar $_POST en lugar de $_REQUEST (más seguro)
$params = array_map('trim', $_POST ?? $_REQUEST);

$x_signature = $params['x_signature'] ?? '';
$x_cod_transaction_state = intval($params['x_cod_transaction_state'] ?? 0);
$x_ref_payco = $params['x_ref_payco'] ?? '';
$x_transaction_id = $params['x_transaction_id'] ?? '';
$x_amount = floatval($params['x_amount'] ?? 0);
$x_currency_code = $params['x_currency_code'] ?? '';
$x_test_request = $params['x_test_request'] ?? '';
$x_approval_code = $params['x_approval_code'] ?? '';
$x_franchise = $params['x_franchise'] ?? '';
$x_extra1 = $params['x_extra1'] ?? '';  // ID de factura/orden
$x_extra2 = $params['x_extra2'] ?? '';
$x_extra3 = $params['x_extra3'] ?? '';

// El ID de la orden puede venir en x_extra1 o x_extra2
$order_id = !empty($x_extra1) ? $x_extra1 : $x_extra2;

if (empty($order_id)) {
    error_log("ePayco Confirmation - No se encontró ID de orden en los parámetros");
    http_response_code(400);
    die("Order ID not found");
}
/**
 * CONSULTAR DATOS DE LA ORDEN
 */
$stmt = $conn->prepare("SELECT id, amount, status FROM " . $pf . "kart_orders WHERE id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();
$order_data = $result->fetch_assoc();
$stmt->close();

if (!$order_data) {
    error_log("ePayco Confirmation - Orden no encontrada: " . $order_id);
    http_response_code(404);
    die("Order not found");
}

$orderAmount = floatval($order_data['amount']);
$orderStatus = $order_data['status'];
$isTestMode = ($x_test_request === 'TRUE');

/**
 * VALIDACIÓN DE DATOS
 */
$validation = false;
$validation_message = '';

// 1. Validar monto (siempre requerido)
if (floatval($x_amount) != $orderAmount) {
    $validation = false;
    $validation_message = "Amount mismatch: Expected {$orderAmount}, received {$x_amount}";
} else {
    // 2. Monto correcto - aceptar si estado es válido
    // Los estados 1, 2, 3 son manejados, otros se tratan como error
    if (in_array($x_cod_transaction_state, [1, 2, 3, 4, 6, 10, 11])) {
        $validation = true;
        
        // Mapear estado a descripción
        $state_desc = [
            1 => 'Aceptada (Confirmada)',
            2 => 'Rechazada',
            3 => 'Pendiente',
            4 => 'Cancelada',
            6 => 'Error',
            10 => 'Fallida',
            11 => 'Fallida'
        ];
        
        $validation_message = "Valid transaction state: " . $state_desc[$x_cod_transaction_state];
    } else {
        $validation = false;
        $validation_message = "Unknown transaction state: {$x_cod_transaction_state}";
    }
}

// Log de validación (en archivo y Joomla)
if (!$validation) {
    error_log("ePayco Confirmation - Order {$order_id}: " . $validation_message);
    logTransaction($order_id, 'VALIDATION', $validation_message, $x_cod_transaction_state);
}

/**
 * MAPEO DE ESTADOS DE EPAYCO A JOOMLA
 * 1 = Aceptada → C (Confirmada)
 * 2 = Rechazada → E (Error)
 * 3 = Pendiente → P (Pending)
 * 4, 6, 10, 11 = Cancelada/Error → E (Error)
 */
$status_map = array(
    1 => 'C',  // Aceptada
    2 => 'E',  // Rechazada
    3 => 'P',  // Pendiente
    4 => 'E',  // Cancelada
    6 => 'E',  // Error
    10 => 'E', // Fallida
    11 => 'E'  // Fallida
);

// Obtener el estado correspondiente
$new_status = $status_map[$x_cod_transaction_state] ?? 'E';

/**
 * PROCESAR ACTUALIZACIÓN
 */
if ($validation) {
    // Iniciar transacción
    $conn->begin_transaction();
    
    try {
        // Actualizar estado de la orden
        $sql = "UPDATE " . $pf . "kart_orders SET status = ? WHERE id = ?";
        $update_stmt = $conn->prepare($sql);
        
        // Verificar si prepare falló
        if ($update_stmt === false) {
            throw new Exception("SQL Error in prepare: " . $conn->error . " | SQL: " . $sql);
        }
        
        $update_stmt->bind_param("si", $new_status, $order_id);
        
        if (!$update_stmt->execute()) {
            throw new Exception("Error updating order status: " . $update_stmt->error);
        }
        $update_stmt->close();

        // Si el pago fue confirmado (estado = C) y anteriormente no lo era, actualizar stock
        if ($new_status === 'C' && $orderStatus !== 'C') {
            // Obtener items de la orden
            $items_stmt = $conn->prepare(
                "SELECT oi.item_id, oi.product_quantity, ki.stock 
                 FROM " . $pf . "kart_order_item oi 
                 JOIN " . $pf . "kart_items ki ON oi.item_id = ki.item_id 
                 WHERE oi.order_id = ?"
            );
            $items_stmt->bind_param("i", $order_id);
            $items_stmt->execute();
            $items_result = $items_stmt->get_result();
            
            while ($item = $items_result->fetch_assoc()) {
                $new_stock = $item['stock'] - $item['product_quantity'];
                $stock_stmt = $conn->prepare("UPDATE " . $pf . "kart_items SET stock = ? WHERE item_id = ?");
                $stock_stmt->bind_param("ii", $new_stock, $item['item_id']);
                
                if (!$stock_stmt->execute()) {
                    throw new Exception("Error updating stock: " . $stock_stmt->error);
                }
                $stock_stmt->close();
            }
            $items_stmt->close();
        }
        
        // Si el pago fue rechazado/cancelado (estado = E) pero antes era confirmado, restaurar stock
        else if ($new_status === 'E' && $orderStatus === 'C') {
            // Obtener items de la orden
            $items_stmt = $conn->prepare(
                "SELECT oi.item_id, oi.product_quantity, ki.stock 
                 FROM " . $pf . "kart_order_item oi 
                 JOIN " . $pf . "kart_items ki ON oi.item_id = ki.item_id 
                 WHERE oi.order_id = ?"
            );
            $items_stmt->bind_param("i", $order_id);
            $items_stmt->execute();
            $items_result = $items_stmt->get_result();
            
            while ($item = $items_result->fetch_assoc()) {
                $new_stock = $item['stock'] + $item['product_quantity'];
                $stock_stmt = $conn->prepare("UPDATE " . $pf . "kart_items SET stock = ? WHERE item_id = ?");
                $stock_stmt->bind_param("ii", $new_stock, $item['item_id']);
                
                if (!$stock_stmt->execute()) {
                    throw new Exception("Error updating stock: " . $stock_stmt->error);
                }
                $stock_stmt->close();
            }
            $items_stmt->close();
        }

        // Confirmar transacción
        $conn->commit();
        
        // Log de éxito
        error_log("ePayco Confirmation - Order {$order_id} confirmed (state: {$x_cod_transaction_state})");
        logTransaction($order_id, 'SUCCESS', "Order status updated to {$new_status}", $x_cod_transaction_state);
        
        // Responder con el código de estado
        http_response_code(200);
        echo $x_cod_transaction_state;
        
    } catch (Exception $e) {
        // Revertir cambios en caso de error
        $conn->rollback();
        $error_msg = $e->getMessage();
        error_log("ePayco Confirmation - Error: Order {$order_id}: " . $error_msg);
        logTransaction($order_id, 'ERROR', $error_msg, $x_cod_transaction_state);
        http_response_code(500);
        echo "ERROR: " . $error_msg;
    }
    
} else {
    // Validación fallida - no actualizar
    error_log("ePayco Confirmation - Validation failed for Order {$order_id}: " . $validation_message);
    logTransaction($order_id, 'VALIDATION_FAILED', $validation_message, $x_cod_transaction_state);
    http_response_code(400);
    echo "VALIDATION ERROR: " . $validation_message;
}

// Cerrar conexión
$conn->close();