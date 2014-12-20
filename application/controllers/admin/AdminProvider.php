<?php

namespace admin;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

use general\Tools;

class AdminProvider
{

	public static function routing(Application $app)
	{
		$ui = $app['controllers_factory'];
		$ui->match('/', 'admin\AdminProvider::index')->bind("admin");
        
        // User
		$ui->match('/add-user', 'admin\AdminProvider::addUser')->bind("add-user");
		$ui->match('/ban-user/{id}', 'admin\AdminProvider::banUser')->bind("ban-user");

		$before = function (Request $request, Application $app) {

            $username = $granted = NULL;
            $token = $app['security']->getToken();

            if( null !== $token ) {
                $user = $token->getUser();
                
                $roles = $user->getRoles();
                $username = $user->getUsername();

                if (! $app['security']->isGranted("ROLE_ADMIN"))
                {
                  return Tools::redirect($app, 'login', array('message' => 'not_logged_in'));  
                }
                
                $granted = $roles[0];
            }
            
            $app['session']->set('is_granted', $granted);
            $app['session']->set('credential', $username);
            
			return NULL;
		};

		$ui->before($before);

		return $ui;
	}
    
    public function addUser(Request $req, Application $app)
    {
        $userId = $req->get('user_id');
        $email = $req->get('email');
        $password = $req->get('password');
        $confirmPassword = $req->get('confirm_password');
        $role = $req->get('role');
        
        /**
        * Fetch the user if edit is active
        */
        if ( ! empty($userId))
        {
            $user = Tools::findOneBy($app, '\Users', array('id' => $userId));
        }

        /**
        * If email is in used and not edit redirect
        */
        $emailExist = Tools::findOneBy($app, '\Users', array('email' => $email));
        if ( ! empty($emailExist) && empty($userId))
        {
            return Tools::redirect($app, 'admin', array('message' => 'email_exist'));
        }
        /**
        * If email is in used and edit active and current user email not equal to submitted email then redirect
        */
        else if ( ! empty($emailExist) && ! empty($user) && $user->getEmail() != $email)
        {
            return Tools::redirect($app, 'admin', array('message' => 'email_exist'));
        }

        // If create user is active and password is empty throw an error
        if (empty($userId) && empty($password))
        {
            return Tools::redirect($app, 'admin', array('message' => 'password_did_not_match'));
        }
        
        // If password is not empty and password not equal to confirm password
        if ( ! empty($password) && $password != $confirmPassword)
        {
            return Tools::redirect($app, 'admin', array('message' => 'password_did_not_match'));
        }

        // If password is not empty
        if (!empty($password))
        {
            $password = $app['security.encoder.digest']->encodePassword($password, '');
        }
        
        if (empty($userId)) $user = new \models\Users;
        $user->setEmail($email);
        if ( ! empty($password)) $user->setPassword($password);
        $user->setRoles($role);
        $user->setViewStatus(5);
        if (empty($userId)) $user->setCreatedAt('now');
        $user->setModifiedAt('now');
        
        $app['orm.em']->persist($user);
        $app['orm.em']->flush();
        
        $msg = (empty($userId)) ? 'user_created_success' : 'user_edit_success';
        
        return Tools::redirect($app, 'admin', array('message' => $msg));
    }
	
	public function index(Request $req, Application $app)
	{
        $msg = $req->get('message');
        $dql = "SELECT u FROM models\Users u 
                WHERE u.view_status > 1 ORDER BY u.created_at DESC";

        $dql = $app['orm.em']->createQuery($dql);
        $users = $dql->getResult();

        $view = array(
            'title' => 'Dashboard',
            'message' => $msg,
            'users' => $users, // Tools::findBy($app, '\Users', array('view_status' => 5))
            'role' => $app['session']->get('is_granted')
        );
        
		return $app['twig']->render('dashboard/index.twig', $view);
	}
    
    public function banUser(Request $req, Application $app, $id = NULL)
    {
        if (is_null($id))
        {
            return Tools::redirect($app, 'admin', array('message' => 'invalid_id'));
        }
        
        $user = Tools::findOneBy($app, '\Users', array('id' => $id));
        if (empty($user))
        {
            return Tools::redirect($app, 'admin', array('message' => 'invalid_id'));
        }
        
        $status = $req->get('status');

        $user->setViewStatus($status);
        $user->setModifiedAt("now");
        
        $app['orm.em']->persist($user);
        $app['orm.em']->flush();
        
        if ($status == 1)
        {
            $msg = "deletion_success";
        } else if ($status == 2)
        {
            $msg = "ban_success";
        } else if ($status == 5)
        {
            $msg = "restore_success";
        }

        return Tools::redirect($app, 'admin', array('message' => $msg));
    }
}