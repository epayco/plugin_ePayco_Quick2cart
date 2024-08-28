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

?>
<center>
    <a id="btn_epayco" href="#">
        <img src="https://multimedia.epayco.co/epayco-landing/btns/Boton-epayco-color1.png">
    </a>
</center>
<form>
    <script src="https://epayco-checkout-testing.s3.amazonaws.com/checkout.preprod.js"></script>
    <script>
        var handler = ePayco.checkout.configure({
            key: "<?php echo $vars->publicKey; ?>",
            test: "<?php echo $vars->test; ?>".toString()
        })
        var extras_epayco = {
            extra5: "P33"
        }
        var data = {
            name: "<?php echo "Order # " . $vars->orderId; ?>",
            description: "<?php echo $vars->descripcion; ?>",
            invoice: "<?php echo "Order # " . $vars->orderId; ?>",
            currency: "<?php echo $vars->currency_code; ?>",
            amount: "<?php echo sprintf('%02.2f', $vars->amount) ?>".toString(),
            tax_base: "<?php echo sprintf('%02.2f', $vars->tax_base) ?>".toString(),
            tax: "<?php echo sprintf('%02.2f', $vars->tax) ?>".toString(),
            taxIco: "0".toString(),
            country: "CO",
            lang: "es",
            extra2: "<?php echo $vars->orderId; ?>",
            external: "<?php echo $vars->external; ?>",
            confirmation: "<?php echo $vars->confirmUrl; ?>",
            response: "<?php echo $vars->return; ?>",
            name_billing: "<?php echo $vars->user_firstname . " " . $vars->user_lastname; ?>",
            address_billing: "",
            email_billing: "<?php echo $vars->user_email; ?>",
            autoclick: "true",
            ip: "<?php echo $vars->ip; ?>",
            test: "<?php echo $vars->test; ?>".toString()
        }
        const apiKey = "<?php echo $vars->publicKey; ?>";
        const privateKey = "<?php echo $vars->privateKey; ?>";;
        var openChekout = function() {
            if (localStorage.getItem("invoicePayment") == null) {
                localStorage.setItem("invoicePayment", data.invoice);
                makePayment(privateKey, apiKey, data, data.external == "true" ? true : false)
            } else {
                if (localStorage.getItem("invoicePayment") != data.invoice) {
                    localStorage.removeItem("invoicePayment");
                    localStorage.setItem("invoicePayment", data.invoice);
                    makePayment(privateKey, apiKey, data, data.external == "true" ? true : false)
                } else {
                    makePayment(privateKey, apiKey, data, data.external == "true" ? true : false)
                }
            }
        }
        var makePayment = function(privatekey, apikey, info, external) {
            const headers = {
                "Content-Type": "application/json"
            };
            headers["privatekey"] = privatekey;
            headers["apikey"] = apikey;
            var payment = function() {
                return fetch("https://cms.epayco.io/checkout/payment/session", {
                        method: "POST",
                        body: JSON.stringify(info),
                        headers
                    })
                    .then(res => res.json())
                    .catch(err => err);
            }
            payment()
                .then(session => {
                    if (session.data.sessionId != undefined) {
                        localStorage.removeItem("sessionPayment");
                        localStorage.setItem("sessionPayment", session.data.sessionId);
                        const handlerNew = window.ePayco.checkout.configure({
                            sessionId: session.data.sessionId,
                            external: external,
                        });
                        handlerNew.openNew()
                    } else {
                        handler.open(data)
                    }
                })
                .catch(error => {
                    error.message;
                });
        }
        var bntPagar = document.getElementById("btn_epayco");
        bntPagar.addEventListener("click", openChekout);
        openChekout()
        jQuery(document).ready(function($) {
            document.addEventListener("contextmenu", function(e) {
                e.preventDefault();
            }, false);
        })
        jQuery(document).keydown(function(event) {
            if (event.keyCode == 123) {
                return false;
            } else if (event.ctrlKey && event.shiftKey && event.keyCode == 73) {
                return false;
            }
        });
    </script>
</form>