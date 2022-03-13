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
$order_id_explode = explode('=',$vars->notify_url);
$order_id = substr($order_id_explode[3], 0, strpos($order_id_explode[3], "&processor"));

?>

<form>    
      <script
            src="https://epayco-checkout-testing.s3.amazonaws.com/checkout.preprod.js?version=1643645084821"
            class="epayco-button"
            data-epayco-key= "<?php echo $vars->publicKey;?>"
            data-epayco-amount="<?php echo sprintf('%02.2f',$vars->amount) ?>"
            data-epayco-tax="<?php echo sprintf('%02.2f',$vars->tax) ?>"
            data-epayco-tax_base="<?php echo sprintf('%02.2f',$vars->tax_base) ?>"
            data-epayco-name="<?php echo "Order # ".$vars->orderId; ?>"
            data-epayco-description="<?php echo $vars->descripcion;?>"
            data-epayco-currency="<?php echo $vars->currency_code;?>"
            data-epayco-country="co"
            data-epayco-test="<?php echo $vars->test;?>"
            data-epayco-external="<?php echo $vars->external;?>"
            data-epayco-response="<?php echo $vars->return;?>"
            data-epayco-confirmation="<?php echo $vars->confirmUrl;?>"
            data-epayco-autoclick="false"
            data-epayco-button="https://multimedia.epayco.co/epayco-landing/btns/Boton-epayco-color1.png"
            data-epayco-rejected="<?php echo $vars->cancel_return;?>"
            data-epayco-email-billing="<?php echo $vars->user_email;?>"
            data-epayco-name-billing="<?php echo $vars->user_firstname." ".$vars->user_lastname;?>"
            data-epayco-address-billing="<?php echo $vars->user_address;?>"
            data-epayco-extra1="qucik2cart"
            data-epayco-extra2="<?php echo $vars->notify_url;?>"
            data-epayco-extra3="<?php echo $vars->orderId;?>"
            >
        </script>
        <script>
            jQuery(document).ready(function ($){
                document.addEventListener("contextmenu", function(e){
                    e.preventDefault();
                }, false);
            })
            jQuery(document).keydown(function (event) {
                if (event.keyCode == 123) {
                    return false;
                } else if (event.ctrlKey && event.shiftKey && event.keyCode == 73) {        
                    return false;
                }
            });
        </script>
    </form>