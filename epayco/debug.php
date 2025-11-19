<?php
/**
 * Debug Helper para ePayco Plugin
 * Coloca este archivo en la raíz del plugin para ver los logs
 * URL: http://tudominio.com/plugins/payment/epayco/debug.php
 */

// No permitir acceso directo desde web en producción
if (!isset($_GET['key']) || $_GET['key'] !== 'debug_epayco_2025') {
    die('No autorizado');
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ePayco Debug Helper</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 14px;
        }
        
        .controls {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        button {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }
        
        button:hover {
            background: #764ba2;
        }
        
        button.danger {
            background: #ff6b6b;
        }
        
        button.danger:hover {
            background: #ee5a52;
        }
        
        .logs-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .section-title {
            background: #f8f9fa;
            padding: 15px;
            border-bottom: 2px solid #667eea;
            font-weight: bold;
            color: #333;
        }
        
        .log-content {
            padding: 20px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 400px;
            overflow-y: auto;
            background: #f8f9fa;
            line-height: 1.6;
            color: #333;
        }
        
        .log-empty {
            color: #999;
            font-style: italic;
            padding: 40px 20px;
            text-align: center;
        }
        
        .success {
            color: #28a745;
        }
        
        .error {
            color: #dc3545;
        }
        
        .warning {
            color: #ffc107;
        }
        
        .info {
            color: #17a2b8;
        }
        
        .footer {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            color: #666;
            font-size: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .status-ok {
            background: #d4edda;
            color: #155724;
        }
        
        .status-error {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 ePayco Plugin - Debug Helper</h1>
            <p>Herramienta para monitorear y depurar el plugin de pago ePayco</p>
            <p style="margin-top: 10px; color: #ff6b6b;">⚠️ Asegúrate de eliminar este archivo en producción</p>
        </div>
        
        <div class="controls">
            <button onclick="reloadLogs()">🔄 Actualizar Logs</button>
            <button onclick="autoRefresh()" id="autoRefreshBtn">▶️ Auto-actualizar (Desactivado)</button>
            <button class="danger" onclick="clearLogs()">🗑️ Limpiar Logs</button>
        </div>
        
        <div class="logs-section">
            <div class="section-title">
                📋 Logs del Sistema (PHP)
                <span class="status-badge status-ok" id="phpStatus">Comprobando...</span>
            </div>
            <div class="log-content" id="phpLogs">
                <div class="log-empty">Cargando logs...</div>
            </div>
        </div>
        
        <div class="logs-section">
            <div class="section-title">
                ⚙️ Información de Configuración
            </div>
            <div class="log-content" id="configInfo">
                <div class="log-empty">Cargando información...</div>
            </div>
        </div>
        
        <div class="footer">
            <p>Actualizado cada <span id="autoRefreshInterval">Manual</span> • Última actualización: <span id="lastUpdate">-</span></p>
        </div>
    </div>

    <script>
        let autoRefreshActive = false;
        let autoRefreshInterval = null;
        
        function updateTime() {
            const now = new Date();
            document.getElementById('lastUpdate').textContent = now.toLocaleTimeString('es-CO');
        }
        
        function reloadLogs() {
            const phpLogPath = '<?php echo ini_get("error_log"); ?>';
            
            // Cargar logs PHP
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=get_logs'
            })
            .then(response => response.json())
            .then(data => {
                const phpLogsDiv = document.getElementById('phpLogs');
                if (data.php_logs) {
                    phpLogsDiv.innerHTML = '<pre>' + escapeHtml(data.php_logs) + '</pre>';
                    document.getElementById('phpStatus').textContent = '✓ OK';
                    document.getElementById('phpStatus').className = 'status-badge status-ok';
                } else {
                    phpLogsDiv.innerHTML = '<div class="log-empty">No hay logs disponibles o el archivo es inaccesible.</div>';
                }
                
                // Cargar info de configuración
                if (data.config_info) {
                    document.getElementById('configInfo').innerHTML = '<pre>' + escapeHtml(data.config_info) + '</pre>';
                }
                
                updateTime();
            })
            .catch(error => {
                document.getElementById('phpLogs').innerHTML = '<div class="log-empty error">Error al cargar logs: ' + error + '</div>';
                document.getElementById('phpStatus').textContent = '✗ Error';
                document.getElementById('phpStatus').className = 'status-badge status-error';
            });
        }
        
        function clearLogs() {
            if (confirm('¿Estás seguro de que deseas limpiar los logs?')) {
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'action=clear_logs'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✓ Logs limpiados correctamente');
                        reloadLogs();
                    } else {
                        alert('✗ Error al limpiar logs: ' + data.message);
                    }
                });
            }
        }
        
        function autoRefresh() {
            autoRefreshActive = !autoRefreshActive;
            const btn = document.getElementById('autoRefreshBtn');
            
            if (autoRefreshActive) {
                btn.style.background = '#28a745';
                btn.textContent = '⏸️ Auto-actualizar (Activado)';
                document.getElementById('autoRefreshInterval').textContent = '2 segundos';
                autoRefreshInterval = setInterval(reloadLogs, 2000);
                reloadLogs();
            } else {
                btn.style.background = '#667eea';
                btn.textContent = '▶️ Auto-actualizar (Desactivado)';
                document.getElementById('autoRefreshInterval').textContent = 'Manual';
                clearInterval(autoRefreshInterval);
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Cargar logs al iniciar
        reloadLogs();
    </script>
</body>
</html>

<?php
// Backend para cargar logs
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'get_logs') {
        $php_error_log = ini_get('error_log');
        $php_logs = '';
        $config_info = '';
        
        // Leer logs PHP
        if ($php_error_log && file_exists($php_error_log)) {
            $php_logs = file_get_contents($php_error_log);
            // Mostrar solo las últimas 100 líneas
            $lines = explode("\n", $php_logs);
            $php_logs = implode("\n", array_slice($lines, -100));
        } else {
            $php_logs = "No se pudo acceder al archivo de logs: " . ($php_error_log ?: 'No configurado');
        }
        
        // Información de configuración
        $config_info = "PHP Error Log: " . ($php_error_log ?: 'No configurado') . "\n";
        $config_info .= "PHP Version: " . phpversion() . "\n";
        $config_info .= "cURL Disponible: " . (function_exists('curl_init') ? 'SÍ' : 'NO') . "\n";
        $config_info .= "Extensiones cargadas: " . implode(', ', get_loaded_extensions()) . "\n";
        
        header('Content-Type: application/json');
        echo json_encode([
            'php_logs' => $php_logs,
            'config_info' => $config_info
        ]);
        exit;
    }
    
    if ($action === 'clear_logs') {
        $php_error_log = ini_get('error_log');
        if ($php_error_log && file_exists($php_error_log)) {
            if (file_put_contents($php_error_log, '') !== false) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'No se pudo escribir en el archivo']);
            }
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Archivo de logs no encontrado']);
        }
        exit;
    }
}
?>
