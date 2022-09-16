<?php

require_once __DIR__ . '/vendor/autoload.php';

define('CARTTHROB_REALEX_GATEWAY_NAME', 'CartThrob Realex Redirect Gateway');
define('CARTTHROB_REALEX_GATEWAY_VERSION', '1.0.0');
define('CARTTHROB_REALEX_GATEWAY_DESC', 'Allows for payments from Realex Redirect within CartThrob');

return [
    'author' => 'Foster Made',
    'author_url' => 'https://cartthrob.com',
    'docs_url' => '',
    'name' => CARTTHROB_REALEX_GATEWAY_NAME,
    'description' => CARTTHROB_REALEX_GATEWAY_DESC,
    'version' => CARTTHROB_REALEX_GATEWAY_VERSION,
    'namespace' => 'CartThrob\RealexRedirect',
    'settings_exist' => false,
];