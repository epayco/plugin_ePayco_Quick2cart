<?php
/**
 * @copyright  Copyright (c) 2009-2013 TechJoomla. All rights reserved.
 * @license    GNU General Public License version 2, or later
 */
defined('_JEXEC') or die(';)');
jimport('joomla.html.html');
jimport('joomla.plugin.helper');

/**
 * PlgPaymentEpaycoHelper
 *
 * @package     CPG
 * @subpackage  component
 * @since       1.0
 */
class PlgPaymentEpaycoHelper
{
	/**
	 * Store log for Blank Plugin posted data to IPN url
	 *
	 * @param   string  $name     name of plugin
	 * @param   string  $logdata  data passed in post
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	public function Storelog($name, $logdata)
	{
		jimport('joomla.error.log');
		$options = array('format' => "{DATE}\t{TIME}\t{USER}\t{DESC}");

		$my = JFactory::getUser();
		$logs = JLog::getInstance($logdata['JT_CLIENT'] . '_' . $name . '.php', $options);
		$logs->addEntry(
			array(
			'user' => $my->name . '(' . $my->id . ')',
			'desc' => json_encode($logdata['raw_data'])
			)
		);
	}
}
