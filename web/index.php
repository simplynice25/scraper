<?php

	define('ENVIRONMENT', 'production'); // development // production

	if (defined('ENVIRONMENT'))
	{
		switch (ENVIRONMENT)
		{
			case 'production':
				error_reporting(0);
				ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_WARNING & ~E_STRICT & ~E_DEPRECATED);
				ini_set('display_errors','Off');
			break;
			
			case 'development':
			default:
				error_reporting(E_ALL);
			break;
		}
	}
	
	require_once(__DIR__ . '/../application/bootstrap.php');

	$app->run();
?>