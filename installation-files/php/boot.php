<?php

/*
 * Check PHP version
 */
if (version_compare(PHP_VERSION, '5.4.0', '<')) exit('You need at least PHP 5.4.0 to install Favus CMS.');

/*
 * PHP headers
 */
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Content-Type: application/json');

/*
 * Debug mode
 */
$isDebug = array_key_exists('debug', $_REQUEST);

if ($isDebug)
{
	ini_set('display_errors', 1);
	error_reporting(1);
	ob_start();
}
else 
{
	ini_set('display_errors', 0);
	error_reporting(0);
}

/*
 * Constants
 */
define('PATH_INSTALL', str_replace("\\", "/", realpath(dirname(__FILE__)."/../../direct")));

/*
 * Address timeout limits
 */
@set_time_limit(3600);

/*
 * Prevent PCRE engine from crashing
 */
ini_set('pcre.recursion_limit', '524'); // 256KB stack. Win32 Apache

/*
 * Handle fatal errors with AJAX
 */
register_shutdown_function('installerShutdown');

function installerShutdown()
{
	$error = error_get_last();

	if ($error['type'] == 1)
	{
		header('HTTP/1.1 500 Internal Server Error', true, 500);

		$errorMsg = htmlspecialchars_decode(strip_tags($error['message']));
		$message = sprintf('Fatal error: %s on line %d in file %s', $errorMsg, $error['line'], $error['file']);
		$response = json_encode(['message' => mb_convert_encoding($message, 'windows-1251', 'utf-8')], JSON_UNESCAPED_UNICODE);
		$output = ob_get_clean();

		if (isset($GLOBALS['ajax']) && ($GLOBALS['ajax']->getInstaller()))
		{
			$GLOBALS['ajax']->getInstaller()->log('Fatal error: %s on line %s in file %s', $errorMsg, $error['line'], $error['file']);
			
			if ($GLOBALS['isDebug'])
			{
				$GLOBALS['ajax']->getInstaller()->log('.============================ OUTPUT ==========================.' . PHP_EOL, false);
				$GLOBALS['ajax']->getInstaller()->log($output . $response . PHP_EOL);
			}

			$GLOBALS['ajax']->getInstaller()->log('.============================ RESPONSE ==========================.' . PHP_EOL, false);
			$GLOBALS['ajax']->getInstaller()->log($response);
		}

		echo $response;

		exit;
	}
}

