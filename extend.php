<?php

use FoF\OAuth\Extend\RegisterProvider;
use forumaker\Steam\Providers\Steam;
use Flarum\Extend;

return [
    new Extend\Locales(__DIR__ . '/resources/locale'),
    new RegisterProvider(Steam::class),

    (new Extend\Frontend('forum'))
        ->css(__DIR__ . '/resources/less/forum.less'),
];
