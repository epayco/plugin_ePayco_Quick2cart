
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
JHtml::_('script', 'https://checkout.epayco.co/checkout.js');
// JHtml::_('script', 'https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js');
jimport('joomla.plugin.plugin');
$lang = JFactory::getLanguage();

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



	public function epaycoBearerToken()
	{
		$publicKey = $this->params->get('epayco_public_key', '');
		$privateKey = $this->params->get('epayco_private_key', '');

		if (!isset($_COOKIE[$publicKey])) {
			$token = base64_encode($publicKey . ":" . $privateKey);
			$bearer_token = $token;
			setcookie($publicKey, $bearer_token, time() + (60 * 14), "/");
		} else {
			$bearer_token = $_COOKIE[$publicKey];
		}

		error_log('BearerToken debug: ' . print_r($bearer_token, true));

		$headers = array(
			'Content-Type' => 'application/json',
			'Authorization' => "Basic " . $bearer_token
		);
		$response = $this->epayco_realizar_llamada_api("login", [], $headers);
		error_log('Epayco API response: ' . print_r($response, true));
		if ($response && isset($response['token'])) {
			return $response['token'];
		}
		return null;
	}

	public function epayco_realizar_llamada_api($path, $data, $headers, $method = 'POST')
	{
		$url = 'https://eks-apify-service.epayco.io/' . $path;
		// $url = 'https://apify.epayco.co/' . $path;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		if (!empty($data)) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		}
		if (!empty($headers)) {
			$formatted_headers = array();
			foreach ($headers as $key => $value) {
				$formatted_headers[] = $key . ': ' . $value;
			}
			curl_setopt($ch, CURLOPT_HTTPHEADER, $formatted_headers);
		}
		$response_body = curl_exec($ch);
		$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);
		if ($error) {
			error_log("Error al hacer la llamada a la API de ePayco: " . $error);
			return false;
		}
		if ($status_code == 200) {
			$responseTransaction = json_decode($response_body, true);
			return $responseTransaction;
		} else {
			error_log("Error en la respuesta de la API de ePayco, código de estado: " . $status_code);
			return false;
		}
	}

	/**
	 * Construye la data para el checkout de ePayco
	 * @param object $vars
	 * @return array
	 */
	public function getCheckoutData($vars)
	{
		return array(
			'checkout_version' => '2',
			'name' => isset($vars->user_firstname) ? $vars->user_firstname . ' ' . $vars->user_lastname : 'Shops Online S.A.S',
			'description' => isset($vars->descripcion) ? $vars->descripcion : 'Descripción del producto',
			'currency' => isset($vars->currency_code) ? $vars->currency_code : 'COP',
			'amount' => isset($vars->amount) ? floatval($vars->amount) : 20000.00,
			'lang' => isset($vars->lang) ? $vars->lang : 'es',
			'ip' => '179.12.113.12',
			'test' => (isset($vars->test) && ($vars->test === true || $vars->test === 'true' || $vars->test === 1 || $vars->test === '1')) ? true : false,
			'country' => isset($vars->country) ? $vars->country : 'CO',
			'taxBase' => isset($vars->tax_base) ? floatval($vars->tax_base) : 16806.72,
			'tax' => isset($vars->tax) ? floatval($vars->tax) : 3193.28,
			'taxIco' => 0,
			'response' => isset($vars->return) ? $vars->return : 'https://mysite.com',
			'confirmation' => isset($vars->confirmUrl) ? $vars->confirmUrl : 'https://webhook.site/8b4bb363-099e-42e8-afe7-0bf11c59eeb1',
			'methodsDisable' => array(),
			'method' => 'POST',
			'dues' => 1,
			'extras' => array(
				'extra1' => 'extra1',
				'extra2' => isset($vars->orderId) ? $vars->orderId : 'extra2',
				'extra3' => 'extra3',
				'extra4' => 'extra4',
				'extra5' => 'P33',
				'extra6' => 'extra6',
				'extra7' => 'extra7',
				'extra8' => 'extra8',
				'extra9' => 'extra9',
				'extra10' => 'extra10',
				'extra11' => 'extra11'
			),
			'billing' => array(
				'email' => isset($vars->user_email) ? $vars->user_email : 'cliente@gmail.com',
				'name' => isset($vars->user_firstname) ? $vars->user_firstname . ' ' . $vars->user_lastname : 'Cliente Martinez',
				'address' => isset($vars->user_address) ? $vars->user_address : 'AV 18 # 18 - 17',
				'typeDoc' => 'CC',
				'numberDoc' => isset($vars->user_document) ? $vars->user_document : '103242123',
				'callingCode' => '+57',
				'mobilePhone' => isset($vars->user_phone) ? $vars->user_phone : '312456654'
			)
		);
	}

		/**
		 * Realiza la petición a la API de ePayco para crear la sesión y retorna el sessionId
		 * @param object $vars
		 * @return string|null sessionId
		 */
		public function SessionId($vars)
		{
			$url = 'https://eks-apify-service.epayco.io/payment/session/create';

			$bearerToken = $this->epaycoBearerToken();
			// Headers deben ser array plano tipo "Header: value"
			$headers = array(
				'Content-Type: application/json',
				'Authorization: Bearer ' . $bearerToken
			);

			error_log('Headers enviados a ePayco: ' . print_r($headers, true));
			$data = $this->getCheckoutData($vars);
			error_log('Data enviada a ePayco: ' . print_r($data, true));

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 15);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			$response_body = curl_exec($ch);
			error_log('SessionId API raw response: ' . print_r($response_body, true));
			$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$error = curl_error($ch);
			curl_close($ch);

			if ($error) {
				error_log("Error al crear la sesión de ePayco: " . $error);
				return null;
			}

			if ($status_code == 200) {
				$response = json_decode($response_body, true);
				error_log('Respuesta decodificada de ePayco: ' . print_r($response, true));
				if (isset($response['success']) && $response['success'] && isset($response['data']['sessionId'])) {
					return $response['data']['sessionId'];
				} else {
					error_log("Respuesta inesperada al crear la sesión de ePayco: " . print_r($response, true));
					return null;
				}
			} else {
				error_log("Error en la respuesta de la API de ePayco al crear sesión, código de estado: " . $status_code);
				return null;
			}
		}
}
