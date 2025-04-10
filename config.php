<?php 
return [
    'created_at'                 => 1744023851,
    'group'                      => 'other',
    'status'                     => true,
    'meta'                       => [
        'name'    => 'Blackwall (BotGuard) Website Protection',
        'version' => '1.1',
        'author'  => 'Zencommerce India',
        'logo'    => 'assets/img/blackwall-logo.svg',
    ],
    'settings'                   => [
        'api_key'          => '2ad71e32-8c3a-4cac-836e-252151edb882',
        'primary_server'   => 'de-nbg-ko1.botguard.net',
        'secondary_server' => 'de-nbg-ko2.botguard.net',
    ],
    'configurable-option-params' => [],
    'requirements'               => [
        'user_domain' => [
            'name'        => 'Domain to Protect',
            'description' => 'Enter the domain you want to protect with Blackwall',
            'type'        => 'text',
            'placeholder' => 'example.com',
            'necessity'   => 'required',
        ],
    ],
];
