<?php

namespace general;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class Tools
{
	public static function summary($txt, $percent = NULL)
	{
		switch($txt)
		{
			case "SELL":
			case 1:
			$val = 1;
			$txt = "SELL";
			$class = "summ-danger";
			break;
			
			case "BUY":
			case 2:
			$val = 2;
			$txt = "BUY";
			$class = "summ-success";
			break;
			
			case "STRONG SELL":
			case 3:
			$val = 3;
			$txt = "STRONG SELL";
			$class = "summ-danger";
			break;
			
			case "STRONG BUY":
			case 4:
			$val = 4;
			$txt = "STRONG BUY";
			$class = "summ-success";
			break;
			
			case "NEUTRAL":
			case 5:
			$val = 5;
			$txt = "NEUTRAL";
			$class = "summ-neutral";
			break;
			
			default:
			$val = 0;
			$txt = "N/A";
			break;
		}

		//$txt = ( ! is_null($percent) && $percent == 0.00) ? "NEUTRAL" : $txt;
		//$class = ( ! is_null($percent) && $percent == 0.00) ? "summ-neutral" : $class;
		
		return array($val, $txt, $class);
	}

	public static function findBy(
		Application $app, 
		$model, 
		$criteria = array('view_status' => 5), 
		$sort = NULL, 
		$limit = NULL, 
		$offset = NULL
	)
	{
		$object = $app['orm.em']->getRepository('models' . $model)->findBy($criteria, $sort, $limit, $offset);
		
		return $object;
	}

	public static function findOneBy(
		Application $app, 
		$model, 
		$criteria = array('view_status' => 5), 
		$sort = NULL
	)
	{
		$object = $app['orm.em']->getRepository('models' . $model)->findOneBy($criteria, $sort);
		
		return $object;
	}
	
	public static function redirect(Application $app, $link, $params)
	{	
		$url = $app['url_generator']->generate($link, $params);

		return $app->redirect($url);
	}

	public static function connectedUser(Application $app)
    {
		$token = $app['security']->getToken();
		if (null !== $token)
        {
			$email = $token->getUser()->getUsername();
            $user = self::findOneBy($app, '\Users', array('email'=> $email));

			return $user;
		}

		return FALSE;
	}

}