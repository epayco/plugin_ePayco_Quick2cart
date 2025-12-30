<?php
//require '../../vendor/autoload.php';
//use Omnipay\Omnipay;
/**
 * @package     Joomla_Payments
 * @subpackage  plg_payments_epayco
 *
 * @author      Techjoomla <extensions@techjoomla.com>
 * @copyright   Copyright (C) 2009 - 2018 Techjoomla. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICEWWWWNSE.txt
 */

use Omnipay\Omnipay;

defined('_JEXEC') or die('Restricted access');

try {
    $__autoload_candidates = [
        __DIR__ . '/../../vendor/autoload.php',
        __DIR__ . '/../../../vendor/autoload.php',
        (defined('JPATH_ROOT') ? JPATH_ROOT . '/vendor/autoload.php' : null),
        dirname(__DIR__, 5) . '/vendor/autoload.php'
    ];

    foreach ($__autoload_candidates as $__autoload) {
        if (!empty($__autoload) && file_exists($__autoload)) {
            require_once $__autoload;
            break;
        }
    }

    $order_id_explode = explode('=', $vars->notify_url);
    $order_id = substr($order_id_explode[3], 0, strpos($order_id_explode[3], "&processor"));

    $gateway = Omnipay::create('Epayco');
    $response = $this->createEpaycoPayment($vars,$gateway);

    if($response === null){
        echo '<pre style="color:red">No se obtuvo respuesta del gateway. Revisa los logs de PHP y la función createEpaycoPayment.</pre>';
        return;
    }else{
        // Process response
        if ($response->isRedirect()) {
            $url = $response->getRedirectUrl();
            //echo '<pre style="color:green">Respuesta del gateway obtenida correctamente: ' . htmlspecialchars($url) . '</pre>';
            // Mostrar botón interactivo con logo de Epayco
            echo '<div style="text-align:center; padding: 40px;">
                    <p style="font-size: 18px; margin-bottom: 30px;">Cargando métodos de pago...</p>
                    <a href="' . htmlspecialchars($url) . '" style="display: inline-block; cursor: pointer; transition: transform 0.2s ease;">
                        <img src="https://multimedia-epayco-preprod.s3.us-east-1.amazonaws.com/plugins-sdks/botonPagarEpayco.png" alt="Epayco" style="height: 30px; width: auto;">
                    </a>
                    <p style="font-size: 12px; color: #666; margin-top: 20px;">Si no se cargan automáticamente, de clic en el botón "Pagar con ePayco"</p>
                  </div>';
            // El JavaScript manejará la redirección automática sin interferencias
        } else {
            // Payment failed
            echo $response->getMessage();
        }
    }

} catch (Exception $e) {
    echo '<pre style="color:red">Error al crear el gateway Epayco: ' . htmlspecialchars($e->getMessage()) . '</pre>';
    die();
    return;
}
?>


<script>
(function(){
    const checkoutUrl = <?php echo json_encode($url ?? ''); ?>;
    const delay = 2000;

    function isValidUrl(u){
        try { new URL(u); return true; } catch(e){ return false; }
    }

    function showFallback(url){
        const div = document.createElement('div');
        div.style.textAlign = 'center';
        div.style.margin = '20px';
        if (url && isValidUrl(url)) {
            div.innerHTML = '<p>Si no es redirigido automáticamente, haga clic en el siguiente enlace:</p>' +
                '<a href="' + encodeURI(url) + '" id="epayco-fallback-link" class="btn btn-primary" target="_blank" rel="noopener">Ir al checkout</a>';
        } else {
            div.innerHTML = '<p style="color:crimson">No se pudo obtener la URL de checkout. Contacte soporte.</p>';
        }
        document.body.appendChild(div);
    }

    function hasNonEmptySessionId(u){
        try {
            const parsed = new URL(u);
            const sessionId = parsed.searchParams.get('sessionId');
            return sessionId !== null && String(sessionId).trim() !== '';
        } catch(e){
            return false;
        }
    }

    if (!checkoutUrl || !isValidUrl(checkoutUrl)) {
        console.warn("checkoutUrl inválida:", checkoutUrl);
        showFallback(checkoutUrl);
        return;
    }

    // Validar que la URL contiene parámetro 'sessionId' y no está vacío
    if (!hasNonEmptySessionId(checkoutUrl)) {
        console.error("La URL de checkout no contiene el parámetro 'sessionId' válido:", checkoutUrl);
        showFallback(checkoutUrl);
        return;
    }

    console.log("Redirigiendo a checkoutUrl en " + (delay/1000) + "s:", checkoutUrl);

    // Usar replace para no dejar esta página en el historial
    const timer = setTimeout(() => {
        try {
            // Re-validar justo antes de redirigir por seguridad
            if (!isValidUrl(checkoutUrl) || !hasNonEmptySessionId(checkoutUrl)) {
                throw new Error("Validación fallida antes de redirigir: sessionId ausente o inválido.");
            }
            window.location.replace(checkoutUrl);
        } catch (err) {
            console.error("Error al redirigir:", err);
            // fallback: abrir en nueva pestaña y mostrar link en la página
            window.open(checkoutUrl, '_blank', 'noopener');
            showFallback(checkoutUrl);
        }
    }, delay);

    // Si el usuario interactúa (clic, teclado) redirigir inmediatamente
    function immediateRedirectHandler(){
        clearTimeout(timer);
        try { 
            if (!isValidUrl(checkoutUrl) || !hasNonEmptySessionId(checkoutUrl)) {
                throw new Error("Validación fallida al intentar redirección inmediata: sessionId ausente o inválido.");
            }
            window.location.replace(checkoutUrl); 
        } catch (e) { 
            console.error(e);
            window.open(checkoutUrl, '_blank', 'noopener'); 
            showFallback(checkoutUrl);
        }
    }
    ['click','keydown','touchstart'].forEach(evt => window.addEventListener(evt, immediateRedirectHandler, { once: true }));

})();
</script>