<?php

namespace App\Service;

use HTMLPurifier;
use HTMLPurifier_Config;

class NoXSS
{
    public function nettoyage($text): string
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', '');
        $purifier = new HTMLPurifier($config);

        return $purifier->purify($text);
    }

}
