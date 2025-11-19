<?php

/**
 * @package     Joomla_Payments
 * @subpackage  plg_payments_epayco
 *
 * @author      Techjoomla <extensions@techjoomla.com>
 * @copyright   Copyright (C) 2009 - 2018 Techjoomla. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICEWWWWNSE.txt
 */
defined('_JEXEC') or die('Restricted access');
$order_id_explode = explode('=', $vars->notify_url);
$order_id = substr($order_id_explode[3], 0, strpos($order_id_explode[3], "&processor"));


$sessionId = $this->SessionId($vars);
echo '<pre>SessionId debug: ' . var_export($sessionId, true) . '</pre>';
if ($sessionId === null || $sessionId === '') {
    echo '<pre style="color:red">No se obtuvo sessionId. Revisa los logs de PHP y la función SessionId.</pre>';
}




?>

<h1><?php echo $sessionId; ?></h1>

<center>

    <a id="payBtn" href="#">
        <img src="https://multimedia.epayco.co/epayco-landing/btns/Boton-epayco-color1.png">
    </a>
</center>

<!-- <script src="https://epayco-checkout-testing.s3.amazonaws.com/checkout.preprod-v2.js"></script>
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script> -->

<script>
    // Función que se ejecuta al hacer clic
    async function handlePayment() {
        console.log("🔄 Iniciando proceso de pago...");

        // 1. Obtener sessionId del backend (generado en PHP)
        const sessionId = "<?php echo $sessionId; ?>";

        if (!sessionId || sessionId === "") {
            alert("❌ No se pudo obtener el sessionId. Verifica la configuración.");
            return;
        }

        console.log("✅ SessionId obtenido:", sessionId);

        // 2. Configurar checkout
        const checkout = window.ePayco.checkout.configure({
            sessionId: sessionId,
            type: "onepage",
            test: true
        });

        // 3. Eventos
        checkout.onCreated(() => console.log("✅ Checkout creado"));
        checkout.onErrors(e => console.error("❌ Error:", e));
        checkout.onClosed(() => console.log("🔒 Checkout cerrado"));

        // 4. Abrir checkout
        checkout.open();
    }

    var bntPagar = document.getElementById("payBtn");

    // Asignar evento al botón
    document.addEventListener('DOMContentLoaded', () => {
        // document.getElementById("payBtn").onclick = handlePayment;
        console.log("Archivo JS cargado correctamente...");
        bntPagar.addEventListener('click', function(event) {
            // . Evita que el navegador realice la acción por defecto del enlace (navegar)
            event.preventDefault();

        });
    });
</script>