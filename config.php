<?php
/**
 * Constants for Blackwall
 */
 return [
    'created_at'                 => 1744023851,
    'group'                      => 'other',
    'status'                     => true,
    'meta'                       => [
        'name'    => 'Blackwall (BotGuard) Website Protection',
        'version' => '1.0',
        'author'  => 'Your Name',
        'logo'    => 'logo.png',
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

class BlackwallConstants {
    // Status constants
    const STATUS_ONLINE = 'online';
    const STATUS_PAUSED = 'paused';
    const STATUS_SETUP = 'setup';
    
    // GateKeeper node IPs
    const GATEKEEPER_NODE_1_IPV4 = '49.13.161.213';
    const GATEKEEPER_NODE_1_IPV6 = '2a01:4f8:c2c:5a72::1';
    const GATEKEEPER_NODE_2_IPV4 = '116.203.242.28';
    const GATEKEEPER_NODE_2_IPV6 = '2a01:4f8:1c1b:7008::1';
    
    /**
     * Get the required DNS records
     * 
     * @return array DNS records
     */
    public static function getDnsRecords() {
        return [
            'A' => [self::GATEKEEPER_NODE_1_IPV4, self::GATEKEEPER_NODE_2_IPV4],
            'AAAA' => [self::GATEKEEPER_NODE_1_IPV6, self::GATEKEEPER_NODE_2_IPV6]
        ];
    }
    
    /**
     * Get default website settings for GateKeeper
     * 
     * @return array Default website settings
     */
    public static function getDefaultWebsiteSettings() {
        return [
            'rulesets' => [
                'wordpress' => false,
                'joomla' => false,
                'drupal' => false,
                'cpanel' => false,
                'bitrix' => false,
                'dokuwiki' => false,
                'xenforo' => false,
                'nextcloud' => false,
                'prestashop' => false
            ],
            'rules' => [
                'search_engines' => 'grant',
                'social_networks' => 'grant',
                'services_and_payments' => 'grant',
                'humans' => 'grant',
                'security_issues' => 'deny',
                'content_scrapers' => 'deny',
                'emulated_humans' => 'captcha',
                'suspicious_behaviour' => 'captcha'
            ],
            'loadbalancer' => [
                'upstreams_use_https' => true,
                'enable_http3' => true,
                'force_https' => true,
                'cache_static_files' => true,
                'cache_dynamic_pages' => false,
                'ddos_protection' => false,
                'ddos_protection_advanced' => false,
                'botguard_protection' => true,
                'certs_issuer' => 'letsencrypt',
                'force_subdomains_redirect' => false
            ]
        ];
    }
}
