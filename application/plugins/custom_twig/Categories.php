<?php

namespace custom_twig;

use general\Tools;

class Categories extends \Twig_Extension
{
    public function getName()
	{
        return "categories";
    }

    public function getFilters()
	{
        return array(
            "categories" => new \Twig_Filter_Method($this, "categories"),
        );
    }

    public function categories($category)
	{
        /*
        * Getting sectors
        */

        $sectors = Tools::sectors();

        if (strpos($category, '-sector') !== false) {
            $cat = str_replace('-sector', '', $category);
            if ( ! empty($cat))
            {
                return $sectors[$cat[0]];

            } else {
                return '';
            }
        }

        /*
        * Getting sub-sectors
        */
        $subsectors = Tools::subsectors();

        $cat = (string) $category;

        return $subsectors[$cat];
    }
}

/* End of file */