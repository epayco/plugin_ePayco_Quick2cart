
<?php

/**
 * @package     Joomla_Payments
 * @subpackage  plg_payments_epayco
 *
 * @author      Techjoomla <extensions@techjoomla.com>
 * @copyright   Copyright (C) 2009 - 2018 Techjoomla. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
defined('_JEXEC') or die('Restricted access');
//JHtml::_('script', 'https://epayco-checkout-testing.s3.amazonaws.com/checkout.preprod-v2.js');
// JHtml::_('script', 'https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js');
jimport('joomla.plugin.plugin');
$lang = JFactory::getLanguage();

// Cargar vendor/autoload.php (Composer) desde posibles ubicaciones
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

use Omnipay\Omnipay;


/**
 * PlgPaymentEpayco
 *
 * @package     CPG
 * @subpackage  site
 * @since       2.2
 */
class PlgPaymentEpayco extends JPlugin
{
	/**
	 * Constructor
	 *
	 * @param   string  &$subject  subject
	 *
	 * @param   string  $config    config
	 */
	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);

		// Inicializar la propiedad params correctamente
		if (isset($config) && isset($config->params)) {
			$this->params = $config->params;
		} elseif (isset($this->params)) {
			// Ya está inicializada
		} else {
			$this->params = new stdClass();
		}

		// Set the language in the class
		$configJ = JFactory::getConfig();

		// Define Payment Status codes in Paypal  And Respective Alias in Framework
		$this->responseStatus = array(
			'deposited' => 'C',
			'pending' => 'P',
			'approved' => 'C',
			'declined' => 'X',
			'Refunded' => 'RF',
			'ERROR' => 'E'
		);

		$path = JPATH_SITE . '/components/com_quick2cart/helper.php';

		if (!class_exists('comquick2cartHelper')) {
			JLoader::register('comquick2cartHelper', $path);
			JLoader::load('comquick2cartHelper');
		}

		$this->qtcmainHelper = new comquick2cartHelper;
	}

	public function getIp()
	{
		$ipaddress = '';
		if (isset($_SERVER['HTTP_CLIENT_IP']))
			$ipaddress = $_SERVER['HTTP_CLIENT_IP'];
		else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
			$ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
		else if (isset($_SERVER['HTTP_X_FORWARDED']))
			$ipaddress = $_SERVER['HTTP_X_FORWARDED'];
		else if (isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']))
			$ipaddress = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
		else if (isset($_SERVER['HTTP_FORWARDED_FOR']))
			$ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
		else if (isset($_SERVER['HTTP_FORWARDED']))
			$ipaddress = $_SERVER['HTTP_FORWARDED'];
		else if (isset($_SERVER['REMOTE_ADDR']))
			$ipaddress = $_SERVER['REMOTE_ADDR'];
		else
			$ipaddress = 'UNKNOWN';
		return $ipaddress;
	}

	/**
	 * Internal use functions
	 *
	 * @param   string  $layout  layout
	 *
	 * @since   2.2
	 *
	 * @return   string  layout
	 */
	public function buildLayoutPath()
	{
		$core_file = dirname(__FILE__) . '/' . $this->_name . '/tmpl/checkout.php';
		return  $core_file;
	}

	/**
	 * Builds the layout to be shown, along with hidden fields.
	 *
	 * @param   string  $vars    vars
	 *
	 * @param   string  $layout  layout
	 *
	 * @since   2.2
	 *
	 * @return   string  vars
	 */
	public function buildLayout($vars)
	{
		// Load the layout & push variables
		ob_start();
		$layout = $this->buildLayoutPath();
		include $layout;
		$html = ob_get_contents();
		ob_end_clean();

		return $html;
	}

	/**
	 * onTP_GetInfo - Used to Build List of Payment Gateway in the respective Components
	 *
	 * @param   string  $config  config
	 *
	 * @since   2.2
	 *
	 * @return   string  config
	 */
	public function onTP_GetInfo($config)
	{
		if (!in_array($this->_name, $config)) {
			return;
		}

		$obj = new stdClass;
		$obj->name = $this->params->get('plugin_name');
		$obj->id = $this->_name;

		return $obj;
	}

	/**
	 * onTP_GetHTML - Constructs the Payment form in case of On Site Payment gateways like Auth.net & constructs the
	 * Submit button in case of offsite ones like Paypal
	 *
	 * @param   string  $vars  array
	 *
	 * @since   2.2
	 *
	 * @return   string  data
	 */
	public function onTP_GetHTML($vars)
	{
		JModelLegacy::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_activitystream/models');
		$Quick2cartModelcart = JModelLegacy::getInstance('cart', 'Quick2CartModel');
		$prefix = substr($vars->order_id, 0, -5);
		$db   = JFactory::getDBO();
		$query = "SELECT id FROM #__kart_orders WHERE prefix LIKE '%" . $prefix . "%';";
		$db->setQuery($query);
		$orderId = $db->loadResult();

		$orderInfo = $this->qtcmainHelper->getorderinfo($orderId);
		$tax = 0;
		$orderItems = $orderInfo['items'];
		$descripcionParts = array();
		foreach ($orderItems  as $orderItem) {
			$tax += floatval($orderItem->item_tax);
			$descripcionParts[] = $orderItem->order_item_name;
		}
		$descripcion = implode(' - ', $descripcionParts);
		$tax_base = floatval($vars->amount) - $tax;
		// Fix for sameSite cookie attribute in chrome.
		header('Set-Cookie: ' . session_name() . '=' . JFactory::getApplication()->input->cookie->get(session_name()) .
			'; SameSite=None; Secure; HttpOnly');
		$vars->publicKey = $this->params->get('epayco_public_key', '');
		$vars->privateKey = $this->params->get('epayco_private_key', '');
		$url = $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
		$server_name = str_replace('/index.php', '/plugins/payment/epayco/epayco/confirmation.php', $url);
		$new_url = $server_name;
		$variable = substr($new_url, 0, strpos($new_url, "confirmation.php"));
		$vars->confirmUrl = $variable . "confirmation.php";
		$vars->orderId = $orderId;
		$vars->tax = $tax;
		$vars->tax_base = $tax_base;
		$vars->descripcion = $descripcion;
		if ($this->params->get('p_test_request') == '1') {
			$test = "true";
		} else {
			$test = "false";
		}
		if ($this->params->get('p_external_request') == '1') {
			$external = "false";
		} else {
			$external = "true";
		}
		$vars->test = $test;
		$vars->external = $external;
		$vars->ip = $this->getIp();
		$html = $this->buildLayout($vars);

		return $html;
	}


	
	/**
	 * onTP_ProcessSubmit
	 *
	 * @param   object  $data  Data
	 * @param   string  $vars  array
	 *
	 * @since   2.2
	 *
	 * @return   string  data
	 */
	public function onTP_ProcessSubmit($data, $vars)
	{
		// code here
	}

	/**
	 * onTP_Processpayment
	 *
	 * @param   object  $data  Data
	 * @param   string  $vars  array
	 *
	 * @since   2.2
	 *
	 * @return   string  data
	 */
	public function onTP_Processpayment($data, $vars = array())
	{
		$result = array();
		return $result;
	}

	/**
	 * translateResponse
	 *
	 * @param   STRING  $invoice_status  invoice_status
	 *
	 * @since   2.2
	 *
	 * @return   string  payment_status
	 */
	public function translateResponse($invoice_status)
	{
		foreach ($this->responseStatus as $key => $value) {
			if ($key == $invoice_status) {
				return $value;
			}
		}
	}

	/**
	 * Builds the layout to be shown, along with hidden fields.
	 *
	 * @param   object  $data  Data
	 *
	 * @since   2.2
	 *
	 * @return   string  data
	 */
	public function onTP_Storelog($data)
	{
		$log_write = $this->params->get('log_write', '0');

		if ($log_write == 1) {
			$plgPaymentHelper = new PlgPaymentEpaycoHelper;
			$plgPaymentHelper->Storelog($this->_name, $data);
		}
	}


	/**
	 * Crea el pago en ePayco usando Omnipay
	 * @param object $vars
	 * @param object $gateway
	 * @return string|null URL de redirección o null en caso de error
	 */
	public function createEpaycoPayment($vars, $gateway){
		try {
			$publicKey = $this->params->get('epayco_public_key', '');
			$privateKey = $this->params->get('epayco_private_key', '');
			$gateway->setUsername('epayco');
			$gateway->setPkey($publicKey);
			$gateway->setPrivatekey($privateKey);
			$gateway->setPublicKey($publicKey);
			$gateway->setLang('en');
			$gateway->setTestMode(true);
			$gateway->setCheckoutMode('onpage');
			$response = $response = $gateway->purchase(
				[
					'amount' => floatval($vars->amount),
					'subTotal' => floatval($vars->tax_base),
					'tax' => floatval($vars->tax),
					'ico' => 0,
					'currency' => $vars->currency_code,
					'cancelUrl' => $vars->return,
					'returnUrl' => $vars->return,
					'notifyUrl' => $vars->confirmUrl,
					'transactionId' => $vars->orderId,
					'description' => $vars->descripcion,
					'firstName' => $vars->user_firstname,
					'lastName' => $vars->user_lastname,
					'email' => $vars->user_email,
					'address' => $vars->user_address,
					'country' => $vars->country ?? 'CO',
					'hasCvv' => true,
					'extras' => [
						'extra1' => $vars->orderId,
					],
					'extraepayco' =>  "P33"
					//'epaycopaymentmethoddisable' => [],
					//'cart' => $cart,
				]
			)->send();
			return $response;
		} catch (Exception $e) {
			error_log("Error al crear el gateway Omnipay Epayco: " . $e->getMessage());
			echo '<pre style="color:red">Error al crear el gateway Omnipay Epayco: ' . htmlspecialchars($e->getMessage()) . '</pre>';
			die();
			return;
		}
	}

}
