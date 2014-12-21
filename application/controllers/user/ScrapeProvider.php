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
		$this->per_scrape = 20;
	}

	public static function routing(Application $app)
	{
		$ui = $app['controllers_factory'];
		$ui->match('/', 'user\ScrapeProvider::index')->bind("scrape");
        $ui->match('/extra', 'user\ScrapeProvider::scrapeExtra')->bind("scrape-extra");

		return $ui;
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
		//$dump = array();
		ini_set('memory_limit', '-1');
		ini_set('user_agent', 'Mozilla/5.0 (Windows NT 6.1; rv:19.0) Gecko/20100101 Firefox/19.0');
		include( __DIR__ . '/../../plugins/simplehtmldom/simple_html_dom.php');

		//Check for the last scraped data TODAY
		$lastScrapedVal = 0;
		$dateToday = new \DateTime("now");
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
            $url_ = urldecode($l->getLink());
            $home = file_get_html(str_replace('-technical', '', $url_));
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
			$scraped->setMovingAverages($movingAverageBuy-$movingAverageSell);
			$scraped->setMovingAveragesTotal($movingAverageBuy+$movingAverageSell);
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
            
            self::homeExtract($app, $home, $scraped);
			
			//$dump[] = $scraped;

			// clean up memory
            $home->clear();
			$data->clear();
            unset($home);
			unset($data);
		}

		// Save last scraped url
		if (empty($lastScraped))
		{
			$lastScraped = new \models\ScrapedLog;
		}

		$lastScraped->setLastScrape(count($links)+$lastScrapedVal); // $this->per_scrape+$lastScrapedVal
		$lastScraped->setViewStatus(5);
		$lastScraped->setCreatedAt("now");
		$lastScraped->setModifiedAt("now");
		
		$app['orm.em']->persist($lastScraped);
		$app['orm.em']->flush();
		
		//echo '<pre>';
		//print_r($dump);
		
		return "OK";
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