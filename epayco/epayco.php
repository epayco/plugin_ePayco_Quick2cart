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
	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);

		// Set the language in the class
		$config = JFactory::getConfig();

		// Define Payment Status codes in Paypal  And Respective Alias in Framework
		$this->responseStatus = array(
			'deposited' => 'C',
			'pending' => 'P',
			'approved' => 'C',
			'declined' => 'X',
			'Refunded' => 'RF', 'ERROR' => 'E');
		
		$path = JPATH_SITE . '/components/com_quick2cart/helper.php';

		if (!class_exists('comquick2cartHelper'))
		{
			JLoader::register('comquick2cartHelper', $path);
			JLoader::load('comquick2cartHelper');
		}

		$this->qtcmainHelper = new comquick2cartHelper;
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
	public function buildLayout($vars )
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
		if (!in_array($this->_name, $config))
		{
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
		foreach ($orderItems  as $orderItem)
		{
			$tax += floatval($orderItem->item_tax);
			$descripcionParts[] = $orderItem->order_item_name;
		}
		$descripcion = implode(' - ', $descripcionParts);
		$tax_base = floatval($vars->amount)-$tax;
		// Fix for sameSite cookie attribute in chrome.
		header('Set-Cookie: ' . session_name() . '=' . JFactory::getApplication()->input->cookie->get(session_name()) .
			'; SameSite=None; Secure; HttpOnly');
		$vars->publicKey = $this->params->get('epayco_public_key', '');
		$url = $_SERVER['REQUEST_SCHEME']."://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
		$server_name = str_replace('/index.php','/plugins/payment/epayco/epayco/confirmation.php',$url);
        $new_url = $server_name;
        $variable = substr($new_url, 0, strpos($new_url, "confirmation.php"));
		$vars->confirmUrl = $variable."confirmation.php";
		$vars->orderId = $orderId;
		$vars->tax = $tax;
		$vars->tax_base = $tax_base;
		$vars->descripcion=$descripcion;
		if($this->params->get('p_test_request') == '1'){
		    $test = "true";
		}else{
		    $test = "false";
		}
		if($this->params->get('p_external_request') == '1'){
		    $external = "false";
		}else{
		    $external = "true";
		}
		$vars->test = $test;
		$vars->external = $external;
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
		foreach ($this->responseStatus as $key => $value)
		{
			if ($key == $invoice_status)
			{
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

		if ($log_write == 1)
		{
			$plgPaymentHelper = new PlgPaymentEpaycoHelper;
			$plgPaymentHelper->Storelog($this->_name, $data);
		}
	}
}
