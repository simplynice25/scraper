<?php

namespace custom_twig;

use Silex\Application;

class Labels extends \Twig_Extension
{
	private $app;
	
	public function __construct(Application $app) {
		$this->app = $app;
	}
    
    public function getName()
	{
        return "label_explode";
    }

    public function getFilters()
	{
        return array(
            "label_explode" => new \Twig_Filter_Method($this, "label_explode"),
        );
    }

    public function label_explode($label)
	{
        $anchors = NULL;
		$labelAnchors = explode(",", $label);
        $labelCount = count($labelAnchors);
        $url = $this->app['url_generator']->generate('overview');
        foreach ($labelAnchors as $k => $l)
        {
            $anchors .= '<a href="'.$url .'?show=00&label='. strtolower(trim($l)).'" target="_blank">' . $l . '</a>';
            $anchors .= ($k == $labelCount-1) ? '' : ',';
        }
        
        return $anchors;
    }
}

/* End of file */