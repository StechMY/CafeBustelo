<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Human Verification Strictness
    |--------------------------------------------------------------------------
    */

    'allow_score' => 24,

    'verified_ttl_minutes' => 525600,

    'remember_device_days' => 365,

    'min_dwell_ms' => 1200,

    'min_interactions' => 1,

    'verify_rate_limit_per_ip_per_minute' => 20,

    /*
    |--------------------------------------------------------------------------
    | Company Contact Details
    |--------------------------------------------------------------------------
    |
    | 改这里就可以。
    |
    */

    'company' => [
        'name' => 'Your Company Sdn. Bhd.',
        'phone' => '+60 12-345 6789',
        'email' => 'hello@example.com',
        'address' => 'Kuala Lumpur, Malaysia',
        'whatsapp' => 'https://wa.me/60123456789',
        'og_title' => 'Contact Our Company',
        'og_description' => 'Open this secure page to view our company contact details.',
        'og_image' => '/images/company-contact-preview.png',
    ],

    /*
    |--------------------------------------------------------------------------
    | Social Preview Bots
    |--------------------------------------------------------------------------
    |
    | 这些允许看到 OG preview，不直接给真正 contact view。
    |
    */

    'social_bots' => [
        'facebookexternalhit',
        'facebot',
        'facebookbot',
        'meta-externalagent',
        'meta-externalfetcher',

        'whatsapp',

        'twitterbot',
        'xbot',

        'linkedinbot',
        'telegrambot',
        'discordbot',
        'slackbot',
        'pinterestbot',
        'skypeuripreview',
        'line/',
        'line-poker',
        'viber',
        'snapchat',
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Crawlers
    |--------------------------------------------------------------------------
    |
    | 你的要求：不是 social bot，也不是真人，一律 404。
    | 所以 search crawler 直接 404。
    |
    */

    'search_bots' => [
        'googlebot',
        'google-inspectiontool',
        'googleother',
        'google-safety',
        'storebot-google',
        'adsbot-google',
        'adsbot-google-mobile',
        'adidxbot',
        'adsbot-google',
        'mediapartners-google',
        'bingbot',
        'msnbot',
        'yandexbot',
        'baiduspider',
        'duckduckbot',
        'applebot',
        'semrushbot',
        'ahrefsbot',
        'mj12bot',
        'dotbot',
        'petalbot',
        'sogou',
        'exabot',
        'seznambot',
        'blexbot',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Scanners / Headless / CLI / Libraries
    |--------------------------------------------------------------------------
    */

    'hard_block_ua' => [
        'curl',
        'wget',
        'python-requests',
        'python-urllib',
        'urllib',
        'httpclient',
        'http client',
        'go-http-client',
        'java/',
        'okhttp',
        'aiohttp',
        'node-fetch',
        'axios',
        'got ',
        'libwww-perl',
        'perl',
        'ruby',
        'mechanize',
        'scrapy',

        'phantomjs',
        'headlesschrome',
        'puppeteer',
        'playwright',
        'selenium',
        'webdriver',
        'nightmare',

        'censys',
        'shodan',
        'binaryedge',
        'masscan',
        'zgrab',
        'zmap',
        'nikto',
        'sqlmap',
        'acunetix',
        'nessus',
        'qualys',
        'rapid7',
        'nuclei',
        'openvas',
        'wpscan',
        'dirbuster',
        'gobuster',
        'ffuf',
        'whatweb',
        'httpx',
        'jaeles',
        'arachni',
        'burp',
        'zap',
        'netsparker',
        'appscan',
        'fuzz',
    ],

    /*
    |--------------------------------------------------------------------------
    | Suspicious Header Rules
    |--------------------------------------------------------------------------
    */

    'required_browser_headers' => [
        'accept',
        'accept-language',
    ],
];