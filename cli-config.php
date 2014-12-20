<?php

require_once "application/bootstrap.php";

$helperSet = new \Symfony\Component\Console\Helper\HelperSet(
	array(
		'db' => new \Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper($app['orm.em']->getConnection()),
		'em' => new \Doctrine\ORM\Tools\Console\Helper\EntityManagerHelper($app['orm.em'])
	)
);