<?php
namespace general;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class UserLogin {

	public static function routing(Application $app)
	{
		$routing = $app['controllers_factory'];
		$routing->match('/', 'general\UserLogin::index')->bind('login');

		return $routing;
	}	

	public function index(Request $request, Application $app)
	{
		$role = NULL;
		$token = $app['security']->getToken();

		if (null !== $token) {
		    $user = $token->getUser();
		}

		if ($app['security']->isGranted("ROLE_ADMIN") || $app['security']->isGranted("ROLE_USER")) {
			return $app->redirect($app['url_generator']->generate("overview"));
		}

		$view = array(
			'title' => 'Login',
            'message' => $request->get('message'),
			'err_'  => $app['session']->getFlashBag()->get('err_'),
			'error' => $app['security.last_error']($request),
			'last_username' => $app['session']->get('_security.last_username'),
		);

		return $app['twig']->render('login.twig', $view);
	}
}