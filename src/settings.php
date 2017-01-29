<?php

date_default_timezone_set('Europe/Paris');

return [
    'settings' => [
        'displayErrorDetails'    => true, // set to false in production
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header
        'maintenance'            => false,

        // Renderer settings
        'renderer' => [
            'template_path' => __DIR__ . '/../templates/',
        ],

        'ent' => [
            'entLoginUrl'  => 'https://cas.univ-tours.fr/cas/login?service=http%3A%2F%2Fnotes-portail.iut.univ-tours.fr%2Fnotes%2F',
            'entGradesUrl' => 'http://notes-portail.iut.univ-tours.fr/notes/',
            'userAgent'    => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.95 Safari/537.36',
            'cookieFile'   => 'cookie.txt',
        ],
    ],
];
