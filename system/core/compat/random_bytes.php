<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 5.2.4 or newer
 *
 * NOTICE OF LICENSE
 *
 * Licensed under the Open Software License version 3.0
 *
 * This source file is subject to the Open Software License (OSL 3.0) that is
 * bundled with this package in the files license.txt / license.rst.  It is
 * also available through the world wide web at this URL:
 * http://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to obtain it
 * through the world wide web, please send an email to
 * licensing@ellislab.com so we can send you a copy immediately.
 *
 * @package		CodeIgniter
 * @author		EllisLab Dev Team
 * @copyright	Copyright (c) 2008 - 2014, EllisLab, Inc. (http://ellislab.com/)
 * @copyright	Copyright (c) 2014 - 2015, British Columbia Institute of Technology (http://bcit.ca/)
 * @license		http://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * @link		http://codeigniter.com
 * @since		Version 2.3.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * random_bytes() compatibility
 *
 * PHP 7 ships random_bytes(); this fills the gap on PHP 5.x with the best
 * source available: OpenSSL, /dev/urandom, or (PHP < 7.2) mcrypt.
 * Like the native function, it throws an Exception when it cannot
 * return cryptographically secure bytes.
 *
 * @package		CodeIgniter
 * @subpackage	Compat
 * @category	Compat
 * @link		http://php.net/random_bytes
 */
if ( ! function_exists('random_bytes'))
{
	/**
	 * @param	int	number of bytes
	 * @return	string
	 */
	function random_bytes($length)
	{
		if ( ! is_numeric($length) OR (int) $length < 1)
		{
			throw new Exception('Length must be greater than 0');
		}

		$length = (int) $length;

		if (function_exists('openssl_random_pseudo_bytes'))
		{
			$bytes = openssl_random_pseudo_bytes($length, $strong);
			if ($bytes !== FALSE && $strong === TRUE && strlen($bytes) === $length)
			{
				return $bytes;
			}
		}

		if (is_readable('/dev/urandom') && ($fp = @fopen('/dev/urandom', 'rb')) !== FALSE)
		{
			$bytes = fread($fp, $length);
			fclose($fp);
			if ($bytes !== FALSE && strlen($bytes) === $length)
			{
				return $bytes;
			}
		}

		if (function_exists('mcrypt_create_iv') && defined('MCRYPT_DEV_URANDOM'))
		{
			$bytes = @mcrypt_create_iv($length, MCRYPT_DEV_URANDOM);
			if ($bytes !== FALSE && strlen($bytes) === $length)
			{
				return $bytes;
			}
		}

		throw new Exception('Could not gather sufficient random data');
	}
}

/* End of file random_bytes.php */
/* Location: ./system/core/compat/random_bytes.php */
