<?php

namespace user;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

use general\Tools;
use custom_twig\DateDiff;

class SubscribeProvider
{
    public $csvPath;
    public $csv;
    
    public function __construct()
    {
        $now = new \DateTime('now');
        $now = $now->format('Y-m-d');
        
        $this->csvPath = CSV_EMAIL;
        
        $this->csv = array(
            $this->csvPath . "/Buy & Strong Buy alerts ( $now ).csv", 
            $this->csvPath . "/Sell & Strong Sell alerts ( $now ).csv", 
            $this->csvPath . "/Watchlist ( $now ).csv", 
            $this->csvPath . "/Portfolio ( $now ).csv", 
            $this->csvPath . "/Top 10 Actives-Gainers-Losers ( $now ).csv"
        );
    }
    
	public static function routing(Application $app)
	{
		$ui = $app['controllers_factory'];
		// Overviews
		$ui->match('/', 'user\SubscribeProvider::index')->bind('subscribe-overview');
        
        $ui->match('/export', 'user\SubscribeProvider::exportReports');
		$ui->match('/sell-buy', 'user\SubscribeProvider::sellBuy');
        $ui->match('/user-subscriptions', 'user\SubscribeProvider::userSubscriptions');
		$ui->match('/watchlist-portfolio', 'user\SubscribeProvider::watchlistPortfolio');
		$ui->match('/actives-losers-gainers', 'user\SubscribeProvider::activesLosersGainers');
        
		return $ui;
	}

    public function exportReports(Request $req, Application $app)
    {
        // Remove old csv
        $files = glob(CSV_EMAIL . '/*');
        foreach($files as $file)
        {
            if(is_file($file))
                unlink($file);
        }
        
        foreach ($this->csv as $csv)
        {
            $file_ = @fopen($csv, "w");
            @fclose($file_);
        }
        
        $data = array();
        $data['sb'][0] = self::sellBuy($req, $app, 13); // Sell & Strong Sell
        $data['sb'][1] = self::sellBuy($req, $app, 24); // Buy & Strong Buy
        
        $data['wp'][0] = self::watchlistPortfolio($req, $app, 1); // Watchlist
        $data['wp'][1] = self::watchlistPortfolio($req, $app, 2); // Portfolio
        
        $data['alg'] =  self::activesLosersGainers($req, $app, 1); // Top 10 Actives, Gainers, Losers
        
        // Generate CSV
        self::sellBuyWatchPortExp($app, $data['sb'][0], $this->csv[0]);
        self::sellBuyWatchPortExp($app, $data['sb'][1], $this->csv[1]);
        
        self::sellBuyWatchPortExp($app, $data['wp'][0][0], $this->csv[2]);
        self::sellBuyWatchPortExp($app, $data['wp'][1], $this->csv[3], 1);
        
        self::activeGainLostExp($app, $data['alg'], $this->csv[4]);
        
        return 'done';
    }
    
    public function activeGainLostExp(Application $app, $data, $csv)
    {
        $x = 0;
        $row = array();
        $columnName = array(
            'SYM', 'PRICE', 'VOLUME', 'SUMMARY', 'MA', 'TI', 'VOLUME', 'TRADED', ' ',
            'SYM', 'PRICE', 'GAIN', 'SUMMARY', 'MA', 'TI', 'VOLUME', 'TRADED', ' ',
            'SYM', 'PRICE', 'LOSE', 'SUMMARY', 'MA', 'TI', 'VOLUME', 'TRADED',
        );
        
        $fp = fopen($csv, 'w');
        ini_set("auto_detect_line_endings", true);
        
        fputcsv($fp, $columnName);
        
        for ($y=0;$y<10;$y++)
        {
            for ($z=0;$z<3;$z++)
            {
                $val = $data[$z];
                $summary = $val{$y}->getSummary();
                if ($summary == 1)
                {
                    $summary = "SELL";
                } else if ($summary == 2)
                {
                    $summary = "BUY";
                } else if ($summary == 3)
                {
                    $summary = "STRONG SELL";
                } else if ($summary == 4)
                {
                    $summary = "STRONG BUY";
                } else if ($summary == 5)
                {
                    $summary = "NEUTRAL";
                }
                
                $traded = $val{$y}->getVolume() * $val{$y}->getCloseTotal();
                
                $row[] = $val{$y}->getAbbr();
                $row[] = number_format($val{$y}->getCloseTotal(), 2, '.', ',');
                $row[] = ($z==0) ? number_format($val{$y}->getVolume(), 2, '.', ',') : $val{$y}->getChangePercent() ."%";
                $row[] = $summary;
                $row[] = $val{$y}->getMovingAverages() . " of " . $val{$y}->getMovingAveragesTotal();
                $row[] = $val{$y}->getTechnicalIndicators() . " of " . $val{$y}->getTechnicalIndicatorsTotal();
                $row[] = number_format($val{$y}->getVolume(), 2, '.', ',');
                $row[] = number_format($traded, 2, '.', ',');
                $row[] = '';
            }
            
            fputcsv($fp, $row);
            unset($row);
        }
        
        fclose($fp);
        
        return TRUE;
    }
    
    public function sellBuyWatchPortExp(Application $app, $data, $csv, $action = null)
    {
        $row = array();
        if (is_null($action))
        {
            $columnName = array('SYM', 'PRICE', 'SUMMARY', 'MA', 'TI', 'VOLUME', 'TRADED', 'DATE');
        } else 
        {
            $sinceClose = $data[1];
            $data = $data[0];
            $columnName = array('SYM', 'PRICE', 'GAIN/LOSS SINCE', 'SUMMARY', 'MA', 'TI', 'VOLUME', 'TRADED', 'DATE');
        }
        
        $fp = fopen($csv, 'w');
        ini_set("auto_detect_line_endings", true);
        
        fputcsv($fp, $columnName);
        
        foreach ($data as $k => $d)
        {
            $traded = $d->getVolume()*$d->getCloseTotal();
            $row[] = $d->getAbbr();
            $row[] = $d->getCloseTotal();
            
            if (!is_null($action))
            {
                $sc = $sinceClose[$d->getAbbr()];
                $gainLoss = ( ( $d->getCloseTotal() / $sc[0] ) - 1 ) * 100;
                $sinceDay = (isset($sc[1]) && !empty($sc[1])) ? $sc[1]->format('Y-m-d') : null;
                $daysAgo = DateDiff::date_diff($d->getCreatedAt()->format('Y-m-d') . "*" . $sinceDay);
                $status = ($gainLoss > 0) ? "gained" : "lost";
                
                $gl = "You have " . $status;
                $gl .= " / " . number_format($gainLoss, 2, '.', ',') . "%";
                $gl .= " / Since " . $sinceDay;
                $gl .= " / " . $daysAgo;
                
                $row[] = $gl;
            }
            
            $summary = $d->getSummary();
            if ($summary == 1)
            {
                $summary = "SELL";
            } else if ($summary == 2)
            {
                $summary = "BUY";
            } else if ($summary == 3)
            {
                $summary = "STRONG SELL";
            } else if ($summary == 4)
            {
                $summary = "STRONG BUY";
            } else if ($summary == 5)
            {
                $summary = "NEUTRAL";
            }
            $row[] = $summary;
            
            $row[] = $d->getMovingAverages() . " of " . $d->getMovingAveragesTotal();
            $row[] = $d->getTechnicalIndicators() . " of " . $d->getTechnicalIndicatorsTotal();
            $row[] = number_format($d->getVolume());
            $row[] = number_format($traded);
            $row[] = $d->getCreatedAt()->format('Y-m-d');
            
            fputcsv($fp, $row);
            
            unset($d);
            unset($row);
        }
        
        fclose($fp);
        
        return TRUE;
    }
    
    public function userSubscriptions(Request $req, Application $app)
    {
        $dummy = array(
            1 => 'Buy & Strong Buy alerts', 
            2 => 'Sell & Strong Sell alerts', 
            3 => 'Watchlist', 
            4 => 'Portfolio', 
            5 => 'Top 10 Actives / Gainers / Losers'
        );

        $data = array();
        $perEmail = 15;
        $lastEmail = 0;
        
        $fql = "SELECT DISTINCT u.id FROM models\Subscribers s INNER JOIN s.user u WHERE s.view_status = 5";
        $fql = $app['orm.em']->createQuery($fql);
        $fqlResult = $fql->getResult();
        
        foreach ($fqlResult as $k => $f)
        {
            $dql = "SELECT u FROM models\Subscribers u WHERE u.user = :user AND u.view_status = 5 ORDER BY u.subscribed_for ASC";
            $dql = $app['orm.em']->createQuery($dql);
			$dql->setParameter("user", $f['id']);
            $dqlResult = $dql->getResult();
            
            if ( ! empty($dqlResult))
            {
                self::sendReports($app, $dqlResult);
            }
            
            unset($f);
        }
        
        return "done";
    }
    
    public function sendReports(Application $app, $result)
    {
        $now = new \DateTime('now');
        $now = $now->format('l, F j, Y');
        $email = $result{0}->getUser()->getEmail();
        $html = $app['twig']->render('general/msg.email.report.twig', array('now' => $now));
        
        foreach ($result as $val)
        {
            switch ($val->getSubscribedFor())
            {
                case 1:
                    $path = $this->csv[0];
                break;
                
                case 2:
                    $path = $this->csv[1];
                break;
                
                case 3:
                    $path = $this->csv[2];
                break;
                
                case 4:
                    $path = $this->csv[3];
                break;
                
                case 5:
                    $path = $this->csv[4];
                break;
            }
            
            $attachments[] = $path;
        }

        $message = \Swift_Message::newInstance()
                    ->setSubject("Report as of " . $now)
                    ->setFrom(array("admin@psemonitor.com" => "Reports - PSE Monitor"))
                    ->setTo(array($email))
                    ->addPart( $html , 'text/html' );

        foreach ($attachments as $attach)
        {
            $message->attach(\Swift_Attachment::fromPath($attach));
        }
        
        $app['mailer']->send($message);
        
        return TRUE;
    }
    
    public function activesLosersGainers(Request $req, Application $app, $method = NULL)
    {
        // Fetch last date
        $dates = "SELECT DISTINCT s.created_at FROM models\Scraped s ORDER BY s.created_at DESC";
        $dates = $app['orm.em']->createQuery($dates);
        $dates->setMaxResults(1);
        $dateResult = $dates->getResult();
        
		$operators = array("", ">", "<");
		$sortOrders = array("", ", s.change_percent DESC", ", s.change_percent ASC");

		for ($i=1;$i<3;$i++)
		{
			$dql = "SELECT s FROM models\Scraped s 
					WHERE s.change_percent " . $operators[$i] . " 0 AND s.created_at = :lastDate ORDER BY s.created_at DESC" . $sortOrders[$i];
			$dql = $app['orm.em']->createQuery($dql);
			$dql->setParameter("lastDate", $dateResult[0]['created_at']);
            $dql->setMaxResults(10);
			$data[$i] = $dql->getResult();
		}

        $dql = "SELECT s FROM models\Scraped s 
                WHERE s.created_at = :lastDate ORDER BY s.created_at DESC, s.volume DESC";
        $dql = $app['orm.em']->createQuery($dql);
        $dql->setParameter("lastDate", $dateResult[0]['created_at']);
        $dql->setMaxResults(10);
        $data[0] = $dql->getResult();

        if (is_null($method))
        {
            echo "<pre>";
            print_r($data);
            exit;
        }
        
        return $data;
    }
    
    public function watchlistPortfolio(Request $req, Application $app, $method = NULL)
    {
        $data = $sinceClose = array();
        $action = (is_null($method)) ? (int) $req->get('action') : $method;

        // Fetch last date
        $dates = "SELECT DISTINCT s.created_at FROM models\Scraped s ORDER BY s.created_at DESC";
        $dates = $app['orm.em']->createQuery($dates);
        $dates->setMaxResults(1);
        $dateResult = $dates->getResult();
        
        if ($action == 1)
        {
            $fql = "SELECT DISTINCT s.abbreviation FROM models\Watchlist s 
                    WHERE s.view_status = 1 ORDER BY s.abbreviation ASC";
        } else {
            $fql = "SELECT DISTINCT s.abbreviation, s.closing_price, s.since FROM models\Portfolio s 
                    WHERE s.view_status = 1 ORDER BY s.abbreviation ASC";
        }
        
        $fql = $app['orm.em']->createQuery($fql);
        
        foreach ($fql->getResult() as $f)
        {
            $dql = "SELECT s FROM models\Scraped s WHERE s.abbr = :abbr AND s.created_at = :latestDate AND s.view_status = 5";
            $dql = $app['orm.em']->createQuery($dql);
            $dql->setParameter("abbr", $f['abbreviation']);
            $dql->setParameter("latestDate", $dateResult[0]['created_at']);
			$dql->setMaxResults(1);
            $result = $dql->getResult();
            
            if ( ! empty($result))
                $data[] = $result{0};
            
            if ($action == 2)
                $sinceClose[$f['abbreviation']] = array($f['closing_price'], $f['since']);
            
            unset($f);
            unset($result);
        }

        if (is_null($method))
        {
            echo "<pre>";
            print_r($data);
            exit;
        }
        
        return array($data, $sinceClose);
    }
    
    public function sellBuy(Request $req, Application $app, $method = NULL)
    {
        $data = array();
        $action = (is_null($method)) ? $req->get('action') : $method;
        $opt = str_split($action);

        // Fetch last two date
        $dates = "SELECT DISTINCT s.created_at FROM models\Scraped s ORDER BY s.created_at DESC";
        $dates = $app['orm.em']->createQuery($dates);
        $dates->setMaxResults(2);
        $dateResult = $dates->getResult();
        
        $fql = "SELECT DISTINCT s FROM models\Scraped s 
        WHERE s.created_at = :lastDate AND ( s.summary = :alert1 OR s.summary = :alert2 ) ORDER BY s.volume DESC";
        $fql = $app['orm.em']->createQuery($fql);
        $fql->setParameter("lastDate", $dateResult[0]['created_at']);
        $fql->setParameter("alert1", $opt[0]);
        $fql->setParameter("alert2", $opt[1]);

        foreach ($fql->getResult() as $f)
        {
            $dql = "SELECT s.id FROM models\Scraped s WHERE
            s.abbr = :abbr AND s.created_at = :beforeDate AND 
            ( s.summary != :alert1 AND s.summary != :alert2 )";
            $dql = $app['orm.em']->createQuery($dql);
            $dql->setParameter("abbr", $f->getAbbr());
            $dql->setParameter("beforeDate", $dateResult[1]['created_at']);
            $dql->setParameter("alert1", $opt[0]);
            $dql->setParameter("alert2", $opt[1]);
			$dql->setMaxResults(1);
            $result = $dql->getResult();

            if (!empty($result))
                $data[] = $f;

            unset($f);
            unset($result);
        }
        
        if (is_null($method))
        {
            echo "<pre>";
            print_r($data);
            exit;
        }
        
        return $data;
    }

    public function index(Request $req, Application $app)
    {
        $subscribeVal = $req->get('subscribe');
        $username = $app['session']->get('credential');
        $user = Tools::findOneBy($app, "\Users", array("email" => $username, "view_status" => 5));

        /**
        * Fetch all active subscriptions and unsubscribe them
        */
        $unsubscribe = Tools::findBy($app, "\Subscribers", array("user" => $user, "view_status" => 5));
        foreach ($unsubscribe as $u)
        {
            $u->setViewStatus(1);
            $u->setCreatedAt('now');
            
            
            $app['orm.em']->persist($u);
            $app['orm.em']->flush();
        }
        
        /**
        * Fetch subscription which matches the current foreach iteration
        */
        foreach ($subscribeVal as $s)
        {
           $subscribe = Tools::findOneBy($app, "\Subscribers", array("user" => $user, "subscribed_for" => $s));
           if (empty($subscribe))
           {
               $subscribe = new \models\Subscribers;
           }
           
            $subscribe->setSubscribedFor((int) $s);
            $subscribe->setUser($user);
            $subscribe->setViewStatus(5);
            $subscribe->setCreatedAt('now');
            $subscribe->setModifiedAt('now');
            
            
            $app['orm.em']->persist($subscribe);
            $app['orm.em']->flush();            
        }
        
        return Tools::redirect($app, 'overview', array('subscription_msg' => 'subscription_updated'));
    }
}