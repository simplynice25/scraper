<?php

namespace custom_twig;

use general\Tools;

class Colors extends \Twig_Extension
{
    public function getName()
	{
        return "c_";
    }

    public function getFilters()
	{
        return array(
            "c_" => new \Twig_Filter_Method($this, "c_"),
        );
    }

    public function c_($int)
	{
		if($int < 0)
		{
			return "red-txt";
		}
		else if ($int > 0)
		{
			return "green-txt";
		}
		else if ($int == 0)
		{
			return "black-txt";
		}
    }
}

/* End of file */