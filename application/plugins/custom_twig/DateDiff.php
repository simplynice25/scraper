<?php

namespace custom_twig;

use general\Tools;

class DateDiff extends \Twig_Extension
{
    public function getName()
	{
        return "date_diff";
    }

    public function getFilters()
	{
        return array(
            "date_diff" => new \Twig_Filter_Method($this, "date_diff"),
        );
    }

    public static function date_diff($dates)
	{
		$dateArray = explode("*", $dates);
		if(!empty($dateArray[0]) && !empty($dateArray[1]))
		{
			$datetime1 = new \DateTime($dateArray[0]);
			$datetime2 = new \DateTime($dateArray[1]);
			$interval = $datetime1->diff($datetime2);
			return $interval->format('%a day(s) ago');
		}
		
		return "N/A";
    }
}

/* End of file */