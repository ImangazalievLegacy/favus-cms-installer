<?php

require 'helpers.php';
require 'boot.php';
require 'Installer.php';
require 'InstallerException.php';
require 'ConfigRewriter.php';
require 'Ajax.php';

try {

	$ajax = new Ajax;

	$result = $ajax->handle();

	$response = json_encode(['result' => $result]);

} catch (InstallerException $e) {

	$fatalError = $e->getMessage();
	
	header('HTTP/1.1 400 Bad Request', true, 400);
	$response =  json_encode(['message' => $fatalError, 'field' => $e->field]);

} catch (Exception $e) {

	$fatalError = sprintf('%s on line %d in file %s', $e->getMessage(), $e->getLine(), $e->getFile());

	header('HTTP/1.1 400 Bad Request', true, 400);
	$response = json_encode(['message' => mb_convert_encoding($fatalError, 'windows-1251', 'utf-8')], JSON_UNESCAPED_UNICODE);
}

$output = ob_get_clean();

if (isset($ajax) && $ajax->getInstaller())
{
	$ajax->getInstaller()->log('.============================ OUTPUT ==========================.' . PHP_EOL, false);
	$ajax->getInstaller()->log($output . $response . PHP_EOL);
	$ajax->getInstaller()->log('.============================ RESPONSE ==========================.' . PHP_EOL, false);
	$ajax->getInstaller()->log($response . PHP_EOL);

	if (json_last_error() !== JSON_ERROR_NONE)
	{
		$ajax->getInstaller()->log('JSON encoding error: %s', json_last_error());
	}
}

echo $response;