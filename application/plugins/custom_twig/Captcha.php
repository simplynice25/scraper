<?php

namespace custom_twig;

class Captcha extends \Twig_Extension
{
    public function getName()
	{
        return "captcha";
    }

    public function getFilters()
	{
        return array(
            "captcha" => new \Twig_Filter_Method($this, "captcha"),
        );
    }

    public function captcha($publickey)
	{
		require_once(__DIR__ . '/../recaptcha/recaptchalib.php');

		return recaptcha_get_html($publickey);
    }
}

/* End of file */