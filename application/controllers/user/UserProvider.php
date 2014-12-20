<?php

namespace user;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

use general\Tools;
use user\ScrapeProvider;

class UserProvider
{
	public static function routing(Application $app)
	{
		$ui = $app['controllers_factory'];
		// Overviews
		$ui->match('/', 'user\UserProvider::index')->bind('overview');
		$ui->match('/actives-losers-gainers', 'user\UserProvider::losersGainers')->bind('actives-losers-gainers');
		$ui->match('/links', 'user\UserProvider::showLinks')->bind('show-links');
		
		// Actions
		$ui->match('/link/action', 'user\UserProvider::linkAction')->bind('link-action');
		$ui->match('/data/delete', 'user\UserProvider::dataDelete')->bind('data-delete');
		$ui->match('/data/actions', 'user\UserProvider::dataActions')->bind('data-actions');
		$ui->match('/link/delete/{id}/{type}', 'user\UserProvider::deleteLink')->bind('delete-link');

		// History
		$ui->match('/data/past', 'user\UserProvider::pastData')->bind('past-data');
		$ui->match('/data/show-all-data', 'user\UserProvider::showAllData')->bind('show-all-data');

		$before = function (Request $request, Application $app)
        {
            $subscribed = array();
            $username = $granted = NULL;
            $msg = $request->get('subscription_msg');
            $token = $app['security']->getToken();

            if( null !== $token ) {
                $user = $token->getUser();
                
                $roles = $user->getRoles();
                $username = $user->getUsername();

                if (! $app['security']->isGranted("ROLE_USER") )
                {
                  return Tools::redirect($app, 'login', array('message' => 'not_logged_in'));
                }

                $user = Tools::findBy($app, '\Users', array('email' => $username, 'view_status' => 5));
                $subscriptions = Tools::findBy($app, "\Subscribers", array("user" => $user, "view_status" => 5));
                
                foreach ($subscriptions as $s)
                {
                    $subscribed[$s->getSubscribedFor()] = $s->getSubscribedFor();
                }
                
                $granted = $roles[0];
            }
            
            $app['session']->set('is_granted', $granted);
            $app['session']->set('credential', $username);
            $app['session']->set('custom_message', $msg);
            $app['session']->set('subscriptions', $subscribed);
            
			return NULL;
		};

		$ui->before($before);

		return $ui;
	}

	public function losersGainers(Request $req, Application $app)
	{
		$data = array();
		$lastDate = NULL;
		$lastData = Tools::findOneBy($app, "\Scraped", array('view_status' => 5), array("created_at" => "DESC"));
		if ( ! empty($lastData))
		{
			$lastDate = $lastData->getCreatedAt();
		}
		
		$operators = array(">", "<");
		$sortOrders = array(", s.change_percent DESC", ", s.change_percent ASC");

		for ($i=0;$i<2;$i++)
		{
			$dql = "SELECT s FROM models\Scraped s 
					WHERE s.change_percent " . $operators[$i] . " 0 AND s.created_at = :lastDate ORDER BY s.created_at DESC" . $sortOrders[$i];
	
			$dql = $app['orm.em']->createQuery($dql);
			$dql->setParameter("lastDate", $lastDate);
			$data[] = $dql->getResult();
		}

        $dql = "SELECT s FROM models\Scraped s 
                WHERE s.created_at = :lastDate ORDER BY s.created_at DESC, s.volume DESC";

        $dql = $app['orm.em']->createQuery($dql);
        $dql->setParameter("lastDate", $lastDate);
        $data[2] = $dql->getResult();
		
		//echo "<pre>";
		//print_r($data);
		//exit;
        
        $lastDate = (isset($lastDate)) ? $lastDate->format("l, F j, Y") : "---";
		
		$view = array(
			'title' => 'Actives, Gainers & Losers for today, ' . $lastDate,
			'scrapes' => $data
		);
		
		return $app['twig']->render('front/gain-loss.twig', $view);
	}

	public function index(Request $req, Application $app)
	{
		$title = "Scraped";
        $heading = "Updates";
		$sinceClose = array();
		$dates = array();
		$latestFive = array();
		$view = (int) $req->get('view');
		$show = $req->get('show');
        $label = $req->get('label');
		$aView = (isset($view)) ? $view : 0;
		$aShow = (isset($show) && ! empty($show)) ? $show : "24";
        
        $user = Tools::connectedUser($app);

		$lastDate = NULL;
		$lastData = Tools::findOneBy($app, "\Scraped", array('view_status' => 5), array("created_at" => "DESC"));
		if ( ! empty($lastData))
		{
			$lastDate = $lastData->getCreatedAt();
		}

		// Get all abbr
        if ( ! empty($label))
        {
            /*
            $latestData = "SELECT DISTINCT l.code, l.id FROM models\Links l
                            LEFT JOIN models\Scraped s WITH s.abbr = l.code 
                            WHERE l.label LIKE '%" . $label . "%' AND s.created_at = :lastDate  ORDER BY s.created_at DESC, s.volume DESC";
                            */
            $latestData = "SELECT l.code, l.id FROM models\Labels as label 
                            JOIN label.link l
                            LEFT JOIN models\Scraped s WITH s.abbr = l.code 
                            WHERE label.label LIKE '%" . $label . "%' AND label.user = :user AND s.created_at = :lastDate  ORDER BY s.created_at DESC, s.volume DESC";
        }
        else if (empty($view) || $view > 3 || ! is_int($view))
		{
			$latestData = "SELECT DISTINCT s.abbr, s.id FROM models\Scraped s WHERE s.created_at = :lastDate ORDER BY s.volume DESC";
            // s.created_at DESC
		}
		else if ($view == 1)
		{
			$latestData = "SELECT DISTINCT s.abbreviation FROM models\Watchlist s WHERE s.view_status = 1 AND s.user = :user ORDER BY s.abbreviation ASC";
		}
		else if ($view == 2)
		{
			$latestData = "SELECT DISTINCT s.abbreviation, s.closing_price, s.since FROM models\Portfolio s WHERE s.view_status = 1 AND s.user = :user ORDER BY s.abbreviation ASC";
		}
		else if ($view == 3)
		{
			$latestData = "SELECT DISTINCT s.abbreviation FROM models\Hidden s WHERE s.view_status = 1 AND s.user = :user ORDER BY s.abbreviation ASC";
		}
		$latestScraped = $app['orm.em']->createQuery($latestData);
		if (empty($view) || $view > 3 || ! is_int($view))
		{
            if (! empty($label))
            {
                $latestScraped->setParameter("user", $user);
            }
            
			$latestScraped->setParameter("lastDate", $lastDate);
		} else if ($view >= 1 && $view <= 3)
        {
            $latestScraped->setParameter("user", $user);
        }
		
		//echo "<pre>";
		//print_r($latestScraped->getResult());
		//exit;

		// Get all 5 latest history
		if($aShow == "00")
		{
			$latestFive = self::sortOut($app, $latestScraped->getResult(), $aShow, 1, $view);
		}
		else
		{
			$latestFive = self::sortOut($app, $latestScraped->getResult(), $aShow, 2, $view);
		}

		if ($view == 2)
		{
			foreach ($latestScraped->getResult() as $l)
			{
				$sinceClose[] = array($l['closing_price'], $l['since']);
			}
		}

		// Get all dates
		foreach ($latestFive as $lf)
		{
			if (count($dates) > 4) break;
			foreach($lf as $l)
			{
                if ($l->getViewStatus() == 1) break;
				if ( ! in_array($l->getCreatedAt(), $dates))
					$dates[] = $l->getCreatedAt();
			}
		}
        
        $latestDate = (isset($dates[0])) ? $dates[0]->format('l, F j, Y') : "---";

        if ( ! empty($label))
        {
            $title =
            $heading = ucfirst($label);
        } else if ($aShow == "00" && empty($view))
        {
			$title =
			$heading = "Updates as of " . $latestDate;
        }
        else if ($aShow == "00" && $view == 1)
        {
            $title =
            $heading = "Watchlist";
        }
        else if ($aShow == "00" && $view == 2)
        {
            $title =
            $heading = "Portfolio";
        }
        else if ($aShow == "00" && $view == 3)
        {
            $title =
            $heading = "Hidden";
        }
        else if ($aShow == "13")
		{
			$title =
			$heading = "SELL alerts as of " . $latestDate;
		}
		else if ($aShow == "24")
		{
			$title = 
			$heading = "BUY alerts as of " . $latestDate;	
		}

		$view = array(
			'title' => $title,
			'scrapes' => $latestFive,
			'dates' => $dates,
			'statuses' => self::checkStatusAttr($app, $latestFive, 1),
			'heading' => $heading,
			'view' => $view,
			'sinceClose' => $sinceClose,
            'role' => $app['session']->get('is_granted')
		);
		
		//echo "<pre>";
		//print_r($dates);
		//exit;

		return $app['twig']->render('front/index.twig', $view);
	}

	public function sortOut(Application $app, $objects, $aShow, $action = NULL, $view = NULL)
	{
		$data = array();
		if ($action == 1)
		{
			foreach ($objects as $o)
			{
				// $abbr = (isset($o['abbr'])) ? $o['abbr'] : $o['abbreviation'];
                if (isset($o['abbr']))
                {
                    $abbr = $o['abbr'];
                } else if (isset($o['abbreviation']))
                {
                    $abbr = $o['abbreviation'];
                } else
                {
                    $abbr = $o['code'];
                }
				$data[] = Tools::findBy($app, "\Scraped", array("abbr" => $abbr), array("created_at" => "DESC"), 5);
                // $orderBy
                // array("created_at" => "DESC")
				// , "view_status" => 5
			}
		}
		else if ($action == 2)
		{
			$lastDate = NULL;
			$lastData = Tools::findOneBy($app, "\Scraped", array('view_status' => 5), array("created_at" => "DESC"));
			if ( ! empty($lastData))
			{
				$lastDate = $lastData->getCreatedAt();
			}

			foreach ($objects as $o)
			{
				$abbr = (isset($o['abbr'])) ? $o['abbr'] : $o['abbreviation'];
				// One data
				$dql = "SELECT s FROM models\Scraped s 
							WHERE s.abbr = :abbr
							AND (s.summary = $aShow[0] OR s.summary = $aShow[1])
							AND s.created_at = :lastDate
							ORDER BY s.created_at DESC";
                            //  . $orderBy
							// AND s.view_status = 5 
			
				//if ($aShow == "24")
					//$dql .= ", s.moving_averages DESC, s.technical_indicators DESC";
				//else if ($aShow == "13")
					//$dql .= ", s.moving_averages ASC, s.technical_indicators ASC";
		
				$dql = $app['orm.em']->createQuery($dql);
				$dql->setParameter("abbr", $abbr);
				$dql->setParameter("lastDate", $lastDate);
				$dql->setMaxResults(1);
				$firstData = $dql->getResult();

				if ( ! empty($firstData))
				{	
					// Four data
					$items = "SELECT s FROM models\Scraped s 
								WHERE s.abbr = :abbr AND s.created_at != :lastDate ORDER BY s.created_at DESC"; // s.created_at DESC
                                //  . $orderBy
								// AND s.view_status = 5
				
					//if ($aShow == "24")
						//$items .= ", s.moving_averages DESC, s.technical_indicators DESC";
					//else if ($aShow == "13")
						//$items .= ", s.moving_averages ASC, s.technical_indicators ASC";
			
					$items = $app['orm.em']->createQuery($items);
					$items->setParameter("abbr", $abbr);
					$items->setParameter("lastDate", $lastDate);
					$items->setMaxResults(4);
					$data[] = array_merge((array) $firstData, (array) $items->getResult());
				}
			}
		}
		
		return $data;
	}
	
	public function checkStatusAttr($app, $objects, $action = NULL)
	{
        $user = Tools::connectedUser($app);
        $userId = $user->getId();
		$status = array();
		foreach ($objects as $k => $obj)
		{
			$abbr = ( ! is_null($action)) ? $obj{0}->getAbbr() : $obj->getAbbr();
            $notDeleted = Tools::findBy($app, "\Scraped", array("abbr" => $abbr, "view_status" => 5));
			$deleted = Tools::findBy($app, "\Scraped", array("abbr" => $abbr, "view_status" => 1));
			$watchlist = Tools::findOneBy($app, "\Watchlist", array("abbreviation" => $abbr, "user" => $user, "view_status" => 1));
			$portfolio = Tools::findOneBy($app, "\Portfolio", array("abbreviation" => $abbr, "user" => $user, "view_status" => 1));
			$hidden = Tools::findOneBy($app, "\Hidden", array("abbreviation" => $abbr, "user" => $user, "view_status" => 1));

			if ( ! empty($watchlist))
				$status[$k][0] = array("btn-danger", "Remove from watchlist", 2);
			else
				$status[$k][0] = array("btn-default", "Watchlist", 1);

			if ( ! empty($portfolio))
				$status[$k][1] = array("btn-danger", "Remove from portfolio", 2);
			else
				$status[$k][1] = array("btn-default", "Portfolio", 1);

			if ( ! empty($hidden))
				$status[$k][2] = array("btn-danger", "Unhide", 2);
			else
				$status[$k][2] = array("btn-default", "Hide", 1);

			if ( ! empty($deleted) && count($notDeleted) < 5)
				$status[$k][3] = 'inactive';
			else
				$status[$k][3] = 'active';
			
			unset($obj);
		}

		return $status;
	}

	public function dataDelete(Request $req, Application $app)
	{
		$msg = array("message" => "err");
		$id = (int) $req->get("id");
		//$status = (int) $req->get("type");
		
		$scraped = Tools::findOneBy($app, "\Scraped", array("id" => $id));
		if (! empty($scraped))
		{
			$datas = Tools::findBy($app, "\Scraped", array("abbr" => $scraped->getAbbr()));
            if ( ! empty($datas))
            {
                foreach ($datas as $d)
                {
                    $d->setViewStatus(1);
                    $d->setModifiedAt("now");
                    $app['orm.em']->persist($d);
                    $app['orm.em']->flush();
                }
            }

			$links = Tools::findBy($app, "\Links", array("code" => $scraped->getAbbr()));
            if ( ! empty($links))
            {
                foreach ($links as $l)
                {
                    $l->setViewStatus(1);
                    $l->setModifiedAt("now");
                    $app['orm.em']->persist($l);
                    $app['orm.em']->flush();
                }
            }

			$watchlist = Tools::findBy($app, "\Watchlist", array("abbreviation" => $scraped->getAbbr()));
            if ( ! empty($watchlist))
            {
                foreach ($watchlist as $w)
                {
                    $w->setViewStatus(2);
                    $w->setModifiedAt("now");
                    $app['orm.em']->persist($w);
                    $app['orm.em']->flush();
                }
            }

			$portfolio = Tools::findBy($app, "\Portfolio", array("abbreviation" => $scraped->getAbbr()));
            if ( ! empty($portfolio))
            {
                foreach ($portfolio as $p)
                {
                    $p->setViewStatus(2);
                    $p->setModifiedAt("now");
                    $app['orm.em']->persist($p);
                    $app['orm.em']->flush();
                }
            }

			$hidden = Tools::findBy($app, "\Hidden", array("abbreviation" => $scraped->getAbbr()));
            if ( ! empty($hidden))
            {
                foreach ($hidden as $h)
                {
                    $h->setViewStatus(2);
                    $h->setModifiedAt("now");
                    $app['orm.em']->persist($h);
                    $app['orm.em']->flush();
                }
            }
            
			$msg = array("message" => "ok");
		}

		return json_encode($msg);
	}

	public function dataActions(Request $req, Application $app)
	{
        $user = Tools::connectedUser($app);
		$msg = array("message" => "err");
		$id = (int) $req->get("id");
		$method = (int) $req->get("method");
		$status = (int) $req->get("type");
		
		$scraped = Tools::findOneBy($app, "\Scraped", array("id" => $id, "view_status" => 5));
		if (! empty($scraped))
		{
			if ($method == 1)
			{
				$model = array("\Watchlist", new \models\Watchlist);
			} else if ($method == 2)
			{
				$model = array("\Portfolio", new \models\Portfolio);
			} else if ($method == 3)
			{
				$model = array("\Hidden", new \models\Hidden);
			}
			$action = Tools::findOneBy($app, $model[0], array("abbreviation" => $scraped->getAbbr(), "user" => $user));
			
			// Update if there's a record
			// 1 = Active / 2 = Removed
			if (! empty($action))
			{
				$action->setViewStatus($status);
			}
			else
			{
				// Create if no record
				$action = $model[1];
				$action->setAbbreviation($scraped->getAbbr());
				$action->setViewStatus(1);
			}

			if ($model[0] == "\Portfolio")
			{
				$action->setClosingPrice($scraped->getCloseTotal());
				$action->setSince($scraped->getCreatedAt()->format("Y-m-d"));
			}

            $action->setUser($user);
			$action->setCreatedAt("now");
			$action->setModifiedAt("now");

			$app['orm.em']->persist($action);
			$app['orm.em']->flush();
			
			$msg = array("message" => "ok", "date" => $scraped->getCreatedAt()->format("Y-m-d"));
		}
		
		return json_encode($msg);
	}

	public function pastData(Request $req, Application $app)
	{
		$abbr = $req->get("abbr");
		$pastData = Tools::findBy($app, "\Scraped", array("abbr" => $abbr), array("created_at" => "DESC"), 30);
        
        $view = array('scrapes' => $pastData, 'dataTitle' => self::dataTitle($app, $pastData{0}));

		return $app['twig']->render('front/appends/past-data.twig', $view);
	}

	public function showAllData(Request $req, Application $app)
	{
		$abbr = $req->get("abbr");
		$pastData = Tools::findBy($app, "\Scraped", array("abbr" => $abbr), array("created_at" => "DESC"));
		
		$view = array(
			"title" => $abbr . " DATA",
			"scrapes" => $pastData,
            "dataTitle" => self::dataTitle($app, $pastData{0})
		);

		return $app['twig']->render('front/show-all.twig', $view);
	}
    
    public function dataTitle(Application $app, $data)
    {
        $title = 'N/A';
        $object = Tools::findOneBy($app, "\Links", array("code" => $data->getAbbr()));
        if ( ! empty($object))
        {
            $title = '('.$object->getCode().') '. $object->getName();
        }

        return $title;
    }

	public function deleteLink(Request $req, Application $app, $id = NULL, $type = NULL)
	{
		$link = Tools::findOneBy($app, "\Links", array("id" => (int) $id));
		if (! empty($link))
		{
			$link->setViewStatus((int) $type);
			$link->setModifiedAt("now");
			$app['orm.em']->persist($link);
			$app['orm.em']->flush();
		}

		$url = $app['url_generator']->generate("show-links", array("msg" => ($type == 5) ? "link_restore" : "link_deleted"));
		return $app->redirect($url);
	}

	public function linkAction(Request $req, Application $app)
	{
		$invalid = array();
		$duplicated = array();
		$id = $req->get("id");
		$code = $req->get("code");
		$name = $req->get("name");
		$url = $req->get("link");
		$label = $req->get("label");
		$edit = $req->get("edit");

        if( !empty($code))
        {
            // Validate link
            if (filter_var($url, FILTER_VALIDATE_URL) === FALSE)
            {
                $msg = "invalid_url";
                return Tools::redirect($app, 'show-links', array("msg" => $msg));
            }
            
            // Check for duplicated link
            $criteria = (!empty($id)) ? array("id" => $id, "view_status" => 5) : array("link" => $url, "view_status" => 5);
            $link = Tools::findOneBy($app, "\Links", $criteria);
            if ( ! empty($link) && empty($edit))
            {
                $msg = "link_exist";
                return Tools::redirect($app, 'show-links', array("msg" => $msg));
            }
            else if (empty($link) && empty($edit))
            {
                $link = new \models\Links;
                $msg = "link_added";
            }
            else
            {
                $msg = "link_updated";
            }
    
            ini_set('memory_limit', '-1');
            ini_set('user_agent', 'Mozilla/5.0 (Windows NT 6.1; rv:19.0) Gecko/20100101 Firefox/19.0');
            include( __DIR__ . '/../../plugins/simplehtmldom/simple_html_dom.php');
            
            $data = file_get_html(urldecode($url));
            
            $text = $data->find('h1.[itemprop=name]', 0)->innertext;
            $preCode = ScrapeProvider::extractParenthesis($text);
        } else {
            $msg = "label_added";
            $link = Tools::findOneBy($app, "\Links", array('id' => $id, 'view_status' => 5));
        }

        if( !empty($code))
        {
            $link->setCode(($code != $preCode) ? $preCode : $code);
            $link->setName($name);
            $link->setLink($url);
        }
        
        if ($msg != "label_added")
        {
            //$link->setLabel($label);
            $link->setNothing(0);
            $link->setViewStatus(5);
            $link->setCreatedAt("now");
            $link->setModifiedAt("now");
            
            $app['orm.em']->persist($link);
            $app['orm.em']->flush();
        }
        
        $user = Tools::connectedUser($app);            
        $label_ = Tools::findOneBy($app, "\Labels", array('link' => $link, 'user' => $user, 'view_status' => 5));
        if (empty($label_))
        {
            $label_ = new \models\Labels;
            $label_->setLink($link);
            $label_->setUser($user);
            $label_->setViewStatus(5);
            $label_->setCreatedAt('now');
        }
        
        $label_->setLabel($label);
        $label_->setModifiedAt('now');
        
        $app['orm.em']->persist($label_);
        $app['orm.em']->flush();

		return Tools::redirect($app, 'show-links', array("msg" => $msg));
	}

	public function showLinks(Request $req, Application $app)
	{
		$links = Tools::findBy($app, "\Links", array("view_status" => 5), array("code" => "ASC"));

		$view = array(
			'title' => "SYM Information",
			'links' => $links,
			'msg' => $req->get('msg'),
            'role' => $app['session']->get('is_granted'),
            'labels' => self::userCreatedLabels($app, $links),
		);

		return $app['twig']->render('front/links.twig', $view);
	}
    
    public function userCreatedLabels($app, $links)
    {
        $labels = array();
        $user = Tools::connectedUser($app);
        
        foreach ($links as $link)
        {
            $labels_ = Tools::findOneBy($app, "\Labels", array('link' => $link, 'user' => $user, 'view_status' => 5));
            if ( ! empty($labels_))
            {
                $labels[] = array(self::labelExplode($app, $labels_->getLabel()), $labels_->getLabel());
            }
            else
            {
                $labels[] = array('', '');
            }
        }

        return $labels;
    }

    public function labelExplode($app, $labels)
	{
        $anchors = NULL;
		$labelAnchors = explode(",", $labels);
        $labelCount = count($labelAnchors);
        $url = $app['url_generator']->generate('overview');
        foreach ($labelAnchors as $k => $l)
        {
            $anchors .= '<a href="'.$url .'?show=00&label='. strtolower(trim($l)).'" target="_blank">' . $l . '</a>';
            $anchors .= ($k == $labelCount-1) ? '' : ',';
        }
        
        return $anchors;
    }
}