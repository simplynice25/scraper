<?php

namespace plugins;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Silex\Application;

class UserProvider implements UserProviderInterface
{
	private $app;
	
	public function __construct(Application $app) {
		$this->app = $app;
	}

	public function loadUserByUsername($username)
    {
		if (isset($_POST["recaptcha_response_field"]))
		{
			$privatekey = "6Ldy3eISAAAAAHtuTiDny2buEpUOVcM6J_YUXyD0";
			require_once(__DIR__ . '/recaptcha/recaptchalib.php');
	
			$resp = recaptcha_check_answer ($privatekey,
											$_SERVER["REMOTE_ADDR"],
											$_POST["recaptcha_challenge_field"],
											$_POST["recaptcha_response_field"]);

			if ( ! $resp->is_valid) {
                
                new User('a', 'a', array('ROLE_NONE'), true, true, true, true);
				
				$error = "The CAPTCHA wasn't entered correctly. Please try it again.";
				$this->app['session']->getFlashBag()->set('err_', $error);

				$url = $this->app['url_generator']->generate('login');
				return $this->app->redirect($url);
			}
		}

		$user = $this->app['orm.em']->getRepository('models\Users')->findOneBy(array('email' => $username, 'view_status' => 5));

		if (!is_object($user)) {
			throw new UsernameNotFoundException(sprintf('Username "%s" does not exist.', $username));
		}

		return new User($user->getEmail(), $user->getPassword(), explode(',', $user->getRoles()), true, true, true, true);
    }

    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }
 
        return $this->loadUserByUsername($user->getUsername());
    }
 
    public function supportsClass($class)
    {
        return $class === 'Symfony\Component\Security\Core\User\User';
    }
}