<?php

namespace user;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

use general\Tools;

class ScrapeProvider
{
	
	public $links;
	
	public function __construct()
	{
		$this->per_scrape = 30;
	}

	public static function routing(Application $app)
	{
		$ui = $app['controllers_factory'];
		$ui->match('/', 'user\ScrapeProvider::index')->bind("scrape");
        $ui->match('/extra', 'user\ScrapeProvider::scrapeExtra')->bind("scrape-extra");
        //$ui->match('/is-scrape', 'user\ScrapeProvider::isScrape');

		return $ui;
	}

    public function isScrape($app, $now)
    {
        $msg = "Please try again";
        $amPm = $now->format('a'); // am, pm
        $hourNoZero = $now->format('g'); // Hours 1, 2, 3 up to 12
        $MinWithZero = $now->format('i'); // 00, 01, 02 up to 60
        $dayNoZero = $now->format('j'); // 1, 2, 3, 4 up to 31
        $dayTxt = strtolower($now->format('D')); // mon, tue, wed, thur, friday, sat and sun
        $monthText = strtolower($now->format('M')); // jan, feb, march to dec

        // List of fixed holidays
        $fixedHolidays = array('jan1','feb25','apr9','may1','jun12','aug21','nov1','nov2','nov30','dec24','dec25','dec30','dec31');
        // Scraping hours
        $scrapingHours = array('4','5','6','7','8','9','10','11');

        // No scrape for sat and sun
        if ($dayTxt == 'sat' || $dayTxt == 'sun')
        {
            //exit('No scraping today!');
            exit($msg);
        }

        // No scrape if not 4 pm to 11 pm
        if ( $amPm != 'pm' || ! in_array($hourNoZero, $scrapingHours) || $hourNoZero == '11' && $MinWithZero != '00' )
        {
            //exit('No scraping yet!');
            exit($msg);
        }

        // No scraping for holidays
        if (in_array($monthText.$dayNoZero, $fixedHolidays))
        {
            //exit('No scraping today, because it\'s holiday!');
            exit($msg);
        }

        $skipOrNot = Tools::findOneBy($app, '\Dates', array('skip_date' => $now));
        if ( ! empty($skipOrNot))
        {
            //exit('No data scraping today: ' . $now->format('l, F d Y'));
            exit($msg);
        }

        return true;
    }
    
    public function scrapeExtra(Request $req, Application $app)
    {
        $nextReq = $req->get('next');
        $next = (!empty($nextReq)) ? (int) $nextReq : 0;

		ini_set('memory_limit', '-1');
		ini_set('user_agent', 'Mozilla/5.0 (Windows NT 6.1; rv:19.0) Gecko/20100101 Firefox/19.0');
		include( __DIR__ . '/../../plugins/simplehtmldom/simple_html_dom.php');

        $links = Tools::findBy($app, "\Links", array("nothing" => 0), array("id" => "ASC"), $this->per_scrape, $next);
		if (empty($links))
		{
			return "No more link to scrape.";
		}
        
        
        foreach($links as $k => $l)
        {
            $data = file_get_html(urldecode($l->getLink()));
            $text = $data->find('h1.[itemprop=name]', 0)->innertext;
            // CODE
            $code = self::extractParenthesis($text);
            // NAME
            $nameArr = explode('(', $text);
            $name = trim($nameArr[0]);
            
            $l->setCode($code);
            $l->setName($name);
			$l->setModifiedAt("now");

			$app['orm.em']->persist($l);
			$app['orm.em']->flush();

			$data->clear();
			unset($data);
        }
    }

	public function index(Application $app)
	{
        $dateToday = new \DateTime("now");
        self::isScrape($app, $dateToday);

		//$dump = array();
		ini_set('memory_limit', '-1');
		ini_set('user_agent', 'Mozilla/5.0 (Windows NT 6.1; rv:19.0) Gecko/20100101 Firefox/19.0');
		include( __DIR__ . '/../../plugins/simplehtmldom/simple_html_dom.php');

		//Check for the last scraped data TODAY
		$lastScrapedVal = 0;
		$now = $dateToday->format("Y-m-d");
		$lastScraped = Tools::findOneBy($app, "\ScrapedLog", array("created_at" => $dateToday, "view_status" => 5));
		if ( ! empty($lastScraped))
		{
			$lastScrapedVal = (int) $lastScraped->getLastScrape();
		}
		
		//return $lastScrapedVal;

		$links = Tools::findBy($app, "\Links", array("view_status" => 5), array("id" => "ASC"), $this->per_scrape, $lastScrapedVal);
		if (empty($links))
		{
			return "No more link to scrape.";
		}
		//echo "<pre>";
		//print_r($links);
		//exit;
		
		foreach($links as $k => $l)
		{
			$home = null;
            $url_ = urldecode($l->getLink());
            if (strpos($url_, '-technical') !== false) {
	            $home = file_get_html(str_replace('-technical', '', $url_));
            }

            $data = file_get_html($url_);
            
            $code = $l->getCode();
            $abbr = (empty($code)) ? self::extractParenthesis($data->find('h1.[itemprop=name]', 0)->innertext) : $code;
			
			//$abbr = self::extractParenthesis($data->find('h1.[itemprop=name]', 0)->innertext);
			$summary = Tools::summary($data->find('div#techStudiesInnerBoxRightBottom div span', 0)->innertext);
			$movingAverageBuy = (int) self::extractParenthesis($data->find('span#maBuy', 0)->innertext);
			$movingAverageSell = (int) self::extractParenthesis($data->find('span#maSell', 0)->innertext);
			$technicalIndicatorBuy = (int) self::extractParenthesis($data->find('span#tiBuy', 0)->innertext);
			$technicalIndicatorSell = (int) self::extractParenthesis($data->find('span#tiSell', 0)->innertext);
			$openH1 = str_replace(',', '', $data->find('span.arial_26', 0)->innertext);
			$openSpan = $data->find('span.arial_20', 0)->innertext;
			$changePerc = self::extractParenthesis($data->find('span.arial_20', 1)->innertext, "%");
			
			$highLowRange = explode(" - ", $data->find('div#quotes_summary_secondary_data div ul li span', 5)->innertext);
			$hLRange = ($highLowRange[0] > $highLowRange[1]) ? array($highLowRange[0], $highLowRange[1]) : array($highLowRange[1], $highLowRange[0]);
			
			$volume = str_replace(',', '', $data->find('div#quotes_summary_secondary_data div ul li span', 1)->innertext);

			$scraped = new \models\Scraped;

			$scraped->setAbbr($abbr);
			$scraped->setSummary($summary[0]);
            $scraped->setAverageBuy($movingAverageBuy);
            $scraped->setAverageSell($movingAverageSell);
			$scraped->setMovingAverages($movingAverageBuy-$movingAverageSell);
			$scraped->setMovingAveragesTotal($movingAverageBuy+$movingAverageSell);
            $scraped->setTechnicalBuy($technicalIndicatorBuy);
            $scraped->setTechnicalSell($technicalIndicatorSell);
			$scraped->setTechnicalIndicators($technicalIndicatorBuy-$technicalIndicatorSell);
			$scraped->setTechnicalIndicatorsTotal($technicalIndicatorBuy+$technicalIndicatorSell);
			$scraped->setOpenTotal($openH1-($openSpan));
			$scraped->setCloseTotal($openH1);
			$scraped->setChangePercent($changePerc);
			$scraped->setHighRange(str_replace(',', '', $hLRange[0]));
			$scraped->setLowRange(str_replace(',', '', $hLRange[1]));
			$scraped->setVolume($volume);
			$scraped->setActionsView(0);
			$scraped->setViewStatus(5);
			$scraped->setCreatedAt("now");
			$scraped->setModifiedAt("now");

			$app['orm.em']->persist($scraped);
			$app['orm.em']->flush();
            
            if ( ! is_null($home)) {
            	self::homeExtract($app, $home, $scraped);
            }
			
			//$dump[] = $scraped;

			// clean up memory
			$data->clear();
			unset($data);

			if ( ! is_null($home)) {
            	$home->clear();
            	unset($home);
			}
		}

		// Save last scraped url
		if (empty($lastScraped))
		{
			$lastScraped = new \models\ScrapedLog;
		}

        $counter = count($links)+$lastScrapedVal;

		$lastScraped->setLastScrape($counter); // $this->per_scrape+$lastScrapedVal
		$lastScraped->setViewStatus(5);
		$lastScraped->setCreatedAt("now");
		$lastScraped->setModifiedAt("now");
		
		$app['orm.em']->persist($lastScraped);
		$app['orm.em']->flush();
		
		//echo '<pre>';
		//print_r($dump);
		
		return $counter . " entries scanned as of " . date('l d, Y') . "."; // February 4, 2015
	}

    public function homeExtract($app, $data, $scraped)
    {
        $wkRange = $data->find('div.overviewDataTable div.inlineblock span.float_lang_base_2', 4)->plaintext;
        $eps = $data->find('div.overviewDataTable div.inlineblock span.float_lang_base_2', 5)->plaintext;
        $peRatio = $data->find('div.overviewDataTable div.inlineblock span.float_lang_base_2', 10)->plaintext;
        $wkRange = explode(' - ', $wkRange);
        
        $one_ = (float) $wkRange[0];
        $two_ = (float) $wkRange[1];
        
        $scraped->setWkHighRange(($one_ > $two_) ? $one_ : $two_);
        $scraped->setWkLowRange(($one_ < $two_) ? $one_ : $two_);
        $scraped->setPeRatio($peRatio);
        $scraped->setEps($eps);
		$scraped->setModifiedAt("now");
		
		$app['orm.em']->persist($scraped);
		$app['orm.em']->flush();
        
        return TRUE;
    }
	
	public static function extractParenthesis($str, $remove = NULL)
	{
		preg_match('#\((.*?)\)#', $str, $str);
		$str = $str[1];

		if ( ! is_null($remove))
		{
			$str = str_replace($remove, '', $str);
		}
		
		return $str;
	}
}