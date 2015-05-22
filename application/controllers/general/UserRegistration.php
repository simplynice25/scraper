<?php
namespace general;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class UserRegistration
{
    public static function routing(Application $app)
    {
        $routing = $app['controllers_factory'];
        $routing->match('/', 'general\UserRegistration::index')->bind('register');
        $routing->match('/registration-process', 'general\UserRegistration::registrationProcess')->bind('registration-process');
        $routing->match('/registration-confirmation', 'general\UserRegistration::registrationConfirmation')->bind('registration-confirmation');

        return $routing;
    }

    public function index(Request $req, Application $app)
    {
        $view = array(
            'title' => 'Registration',
            'message' => $req->get('message'),
        );

        return $app['twig']->render('registration.twig', $view);
    }
    
    public function registrationProcess(Request $req, Application $app)
    {
        $email = $req->get('email');
        $password = $req->get('password');
        $confirm_password = $req->get('confirm_password');

        // Validate captcha
        if ( ! isset($_POST["recaptcha_response_field"])) {
            return Tools::redirect($app, 'register', array('message' => 'invalid_captcha'));
        }

        $privatekey = "6Ldy3eISAAAAAHtuTiDny2buEpUOVcM6J_YUXyD0";
        require_once(__DIR__ . '/../../plugins/recaptcha/recaptchalib.php');

        $resp = recaptcha_check_answer($privatekey, $_SERVER["REMOTE_ADDR"], $_POST["recaptcha_challenge_field"], $_POST["recaptcha_response_field"]);

        if ( ! $resp->is_valid) {
            return Tools::redirect($app, 'register', array('message' => 'invalid_captcha'));
        }

        // Validate password
        if ($confirm_password != $password) {
            return Tools::redirect($app, 'register', array('message' => 'password_did_not_match'));
        }

        // Validate Email
        $isEmailExist = Tools::findOneBy($app, '\Users', array('email' => $email));
        if ( ! empty($isEmailExist)) {
            return Tools::redirect($app, 'register', array('message' => 'email_exist'));
        }

        $password = $app['security.encoder.digest']->encodePassword($password, '');

        $user = new \models\Users;
        $user->setEmail($email);
        $user->setPassword($password);
        $user->setToken(md5($email));
        $user->setRoles('ROLE_USER');
        $user->setViewStatus(2);
        $user->setCreatedAt('now');
        $user->setModifiedAt('now');
        
        $app['orm.em']->persist($user);
        $app['orm.em']->flush();

        self::emailConfirmation($app, $email, $user->getToken());
        self::notifyAdmin($app, $email);

        return Tools::redirect($app, 'register', array('message' => 'registration_success'));
    }
    
    public function emailConfirmation($app, $e, $t)
    {
        $generatedUrl = "http://$_SERVER[HTTP_HOST]".$app['url_generator']->generate('registration-confirmation') . "?token=" . $t;
        $html = $app['twig']->render('general/msg.register.twig', array( 'email' => $e, 'url' => $generatedUrl ));
    
        $message = \Swift_Message::newInstance()
                    ->setSubject("Account Registration")
                    ->setFrom(array("admin@psemonitor.com" => "PSE Monitor"))
                    ->setTo(array($e))
                    ->addPart( $html , 'text/html' );
    
        return $app['mailer']->send($message);
    }
    
    public function notifyAdmin($app, $e)
    {
        $html = $app['twig']->render('general/msg.register.notify.admin.twig', array( 'email' => $e ));
    
        $message = \Swift_Message::newInstance()
                    ->setSubject("Admin Notification")
                    ->setFrom(array("admin@psemonitor.com" => "PSE Monitor"))
                    ->setTo(array('admin@psemonitor.com'))
                    ->addPart( $html , 'text/html' );
    
        return $app['mailer']->send($message);
    }

    public function registrationConfirmation(Request $req, Application $app)
    {
        //Validate token
        $token = $req->get('token');
        $user = Tools::findOneBy($app, '\Users', array('token' => $token, 'view_status' => 2));
        if (empty($user))
        {
            return Tools::redirect($app, 'login', array('message' => 'invalid_confirm_token'));
        }

        $user->setToken(NULL);
        $user->setViewStatus(5);
        $user->setModifiedAt('now');

        $app['orm.em']->persist($user);
        $app['orm.em']->flush();

        Tools::autoLogin($app, $user->getEmail());

        return Tools::redirect($app, 'overview', array('message' => 'account_activated'));
    }
}