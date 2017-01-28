<?php

// Constants
$app->ENT_LOGIN_URL = 'https://cas.univ-tours.fr/cas/login?service=http%3A%2F%2Fnotes-portail.iut.univ-tours.fr%2Fnotes%2F';
$app->ENT_GRADES_URL = 'http://notes-portail.iut.univ-tours.fr/notes/';
$app->USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.95 Safari/537.36';
$app->COOKIE_FILE = 'cookie.txt';

// Load libs
foreach (glob('src/libs/*.php') as $filename)
    require_once $filename;


// DIC configuration
$container = $app->getContainer();


// View renderer
$container['renderer'] = function ($c) {
    $settings = $c->get('settings')['renderer'];
    return new Slim\Views\PhpRenderer($settings['template_path']);
};


// Flash messages
$container['flash'] = function($c) {
	return new \Slim\Flash\Messages();
};


// Errors
$container['errorHandler'] = $container['notAllowedHandler'] = $container['notFoundHandler'] = function($c) {
    return function ($request, $response, $exception) use ($c) {
        return $response->withStatus(302)->withHeader('Location', '/');
    };
};
