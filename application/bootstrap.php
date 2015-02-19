<?php

use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder;

// Defines
define('CSV_EMAIL', __DIR__ . '/csv/email');

include_once('settings.php');
require_once(__DIR__ . '/../vendor/autoload.php');

$app_debug = TRUE;
$app = new Silex\Application();

$app['debug'] = $app_debug;
$config = Setup::createAnnotationMetadataConfiguration(array(__DIR__ . "/models"), $app_debug);

// Registrations
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new Silex\Provider\TwigServiceProvider(), $twig_conf);
$app->register(new Silex\Provider\MonologServiceProvider(), $monolog_conf);
$app->register(new DerAlex\Silex\YamlConfigServiceProvider($database_conf));
$app->register(new Silex\Provider\TranslationServiceProvider(), $trans_conf);
$app['translator'] = $app->share($app->extend('translator', function($translator, $app) {
	 $translator->addLoader('yaml', new YamlFileLoader());
	 $translator->addResource('yaml', __DIR__.'/../src/locales/en.yml', 'en');
	 return $translator;
}));

$app["twig"] = $app->share($app->extend("twig", function (\Twig_Environment $twig, Silex\Application $app) {
    $twig->addExtension(new custom_twig\Colors($app));
	$twig->addExtension(new custom_twig\Captcha($app));
	$twig->addExtension(new custom_twig\DateDiff($app));
	$twig->addExtension(new custom_twig\Categories($app));

    return $twig;
}));

$app->register(new Silex\Provider\DoctrineServiceProvider(), array( 'db.options' => $app['config']['database'] ));
$app['orm.em'] = EntityManager::create($app['db'], $config);
$app['security.encoder.digest'] = $app->share(function ($app) {
	return new MessageDigestPasswordEncoder('sha1', false, 1);
});

$app->register(new Silex\Provider\SessionServiceProvider());
$app['session.storage.handler'] = null;

$app->register(new Silex\Provider\SecurityServiceProvider(), array(
	'security.firewalls' => array(
		'admin' => array(
			'pattern'   => '^.*$',
			'anonymous' => true,
			'form'      => array('login_path' => '/', 'check_path' => '/login_check'),
			'logout'    => array('logout_path' => '/logout'),
			'users'     => $app->share(function() use($app) {
				return new plugins\UserProvider($app);
			})
		)
	),
	'security.access_rules' => array(
		array('/u/data/show-all-data', 'ROLE_ADMIN'),
		array('^/u/', 'ROLE_USER'),
        array('^/subscribe/', 'IS_AUTHENTICATED_ANONYMOUSLY'),
		array('/subscribe', 'ROLE_USER'),
		array('^/dashboard/', 'ROLE_ADMIN'),
	),
	'security.role_hierarchy' => array(
	    'ROLE_ADMIN' => array('ROLE_USER'),
	)
));

$app->register(new Silex\Provider\SwiftmailerServiceProvider());
$app['swiftmailer.options'] = $mail_conf;

$app['locale'] = 'en';

$app->mount('/credentials', general\Credentials::routing($app));
$app->mount('/', general\UserLogin::routing($app));
//$app->mount('/register', general\UserRegistration::routing($app));
$app->mount('/u', user\UserProvider::routing($app));
$app->mount('/scanner', user\ScrapeProvider::routing($app));
$app->mount('/dashboard', admin\AdminProvider::routing($app));
$app->mount('/subscribe', user\SubscribeProvider::routing($app));

//echo $app['security.encoder.digest']->encodePassword('psep@55w0rd','');

/* End of bootstrap file */