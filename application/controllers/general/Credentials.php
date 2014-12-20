<?php

namespace general;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Silex\ControllerProviderInterface;

use general\Tools;

class Credentials
{
    public static function routing(Application $app)
    {
        $ui = $app['controllers_factory'];
        $ui->match('/', 'general\Credentials::resetPasswordSubmit')->bind('reset-pass');
        $ui->match('/reset-pass-process', 'general\Credentials::resetPasswordProcess')->bind('reset-pass-process');

        return $ui;
    }
    
    public function resetPasswordSubmit(Request $req, Application $app)
    {
		$msg = "invalid_email";
		$email = $req->get('email');
		$generatedUrl = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
		
		$user = Tools::findOneBy($app, "\Users", array("email" => $email, "view_status" => 5));
		if ( ! empty($user))
		{
			$token = md5(uniqid()) . md5($email);
			$url = $generatedUrl . 'reset-pass-process?token=' . $token;
			$html = $app['twig']->render('general/msg.forgot.password.twig', array( 'url' => $url ));
	
			$message = \Swift_Message::newInstance()
						->setSubject("Reset Password")
						->setFrom(array("admin@psemonitor.com" => "PSE Monitor"))
						->setTo(array($email))
						->addPart( $html , 'text/html' );
	
			if ( $app['mailer']->send($message))
			{
				$user->setToken($token);
				$user->setModifiedAt("now");
				$app['orm.em']->persist($user);
				$app['orm.em']->flush();
				
				$msg = "reset_sent";
			} else 
            {
                $msg = "reset_failed";
            }
		}

		return Tools::redirect($app, 'login', array('message' => $msg));
    }
    
    public function resetPasswordProcess(Request $req, Application $app)
    {
        $msg = NULL;
        $token = $req->get('token');
        $password = $req->get('password');
        $confirmPassword = $req->get('confirm_password');

        $user = Tools::findOneBy($app, '\Users', array('token' => $token, 'view_status' => 5));
        if (empty($user))
        {
            return Tools::redirect($app, 'login', array('message' => 'invalid_token'));
        }
        
        if ( ! empty($password) && $password == $confirmPassword)
        {
			$password = $app['security.encoder.digest']->encodePassword($password, '');
			
			$user->setPassword($password);
			$user->setToken(NULL);
			$user->setModifiedAt("now");
			$app['orm.em']->persist($user);
			$app['orm.em']->flush();

            return Tools::redirect($app, 'login', array('message' => 'reset_pass_success'));
        } else if ( ! empty($password))
        {
            $msg = "password_did_not_match";
        }

        $view = array(
            'title' => 'Reset password',
            'token' => $token,
            'message' => $msg,
        );
        
        return $app['twig']->render('general/form.forgot.password.twig', $view);
    }
}

/* End of file */