<?php
declare(strict_types=1);

return [

    /***********************************************************
     * OPTIONS THAT WILL MOST LIKELY CHANGE
     **********************************************************/

    // Slim
    'displayErrorDetails' => true, // Set to false in production
    'logErrors' => true,
    'logErrorDetails' => true,
    'debugEngine' => 'Whoops', // Error | Whoops

    // Glued globals
    'glued' => [
        'timezone' => 'Europe/Prague'
    ],

    // Database
    'db' => [
        'host' => $_ENV['MYSQL_HOST'] || 'db_host',
        'database' => $_ENV['MYSQL_DATABASE'] || 'db_name',
        'username' => $_ENV['MYSQL_USERNAME'] || 'db_user',
        'password' => $_ENV['MYSQL_PASSWORD'] || 'db_pass',
        'charset' => ' utf8mb4',
        'collation' => ' utf8mb4_unicode_ci'
    ],

    /**
     * Session cookies configuration (consumed by the @see
     * SessionMiddleware). Changing these defaults may compromise
     * security (i.e. break CSRF protection). See 
     * @link https://scotthelme.co.uk/csrf-is-really-dead/.
     */
    'auth' => [
        'cookie' => [
            // Common cookie config for both session and jwt cookies
            'lifetime'  => 0,     // 0 = until browser is closed
            'path'      => '/',
            'domain'    => $_SERVER['SERVER_NAME'] ?? null,
            'secure'    => true,
            'httponly'  => true,
            'samesite'  => 'Lax',
        ],
        'session' => [
            // middleware params
            'cookie'    => 'g_sid', // session cookie name
            'callback'  => function () {},
        ],
        'jwt' => [
            // token params
            'expiry'    => '30 minute',
            'secret'    => 'some-secret', // a config.d fragment file will be generated with a correct key
            'algorithm' => 'HS512',
            // middleware params
            'path'      => [ '/api' ],
            'ignore'    => [ '/api/core/v1/auth-status', '/api/core/v1/signin' ],
            'attribute' => 'auth_jwt',
            'secure'    => 'true', // require jwt over https (NOTE You can really screw up your security with this)
            'relaxed'   => ["localhost", "127.0.0.1" ], // https not enforced for requests from relaxed whitelist (NOTE You can really screw up your security with this)
            "cookie"    => 'g_tok', // jwt cookie name
            "before" => function ($response, $params) use (&$decoded, &$token) {},
            "after" => function ($response, $params) use (&$decoded, &$token) {},
            "error" => function ($response, $arguments) {
                $data['api'] =  'core/auth/jwt';
                $data['version'] = '1';
                $data['response_ts'] = time();
                $data['response_id'] = uniqid();
                $data['status'] = 'Forbidden.';
                $data['message'] = 'You must be signed in to do this, please provide a valid token.';
                $data['code'] = 403;
                $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                return $response->withHeader("Content-Type", "application/json");
            },
        ],
    ],

    // Geoip
    'geoip' => [
        'geoip_engine' => false,    // Providers: [ false, 'maxmind']. Override to 'maxmind' when maxmind license key is set.
        'maxmind_licence_key' => '' // Maxmind GeoLite2 Licence key (its free, you just need to sign up for an account).
    ],

    // Monolog
    'logger' => [
        'name' => 'glued',
        'path' =>  __ROOT__ . '/private/log/app.log',
        'level' => \Monolog\Logger::DEBUG,
    ],

    // E-mail (see swiftmailser)
    'smtp' => [
        'smtp' => 'smtp.example.com',
        'port' => 465,
        'encr' => 'ssl',
        'user' => 'you@example.com',
        'pass' => 'very-secret',
        'reconnect.after' => '100',  // reconnect after x emails
        'reconnect.delay' => '15',   // wait for x seconds between connections
        'throttle.count' => '50',    // number of emails per minute
        'throttle.data' => '',       // number of bytes per minute
    ],

    // Cryptography keys
    'crypto' => [
        'mail' => 'mail-encryption-key',
        'reqparams' => 'reqparams-encryption-key'
    ],

    // Api keys
    // TODO: get this out of the config
    // see https://www.codementor.io/@ccornutt/keeping-credentials-secure-in-php-kvcbrk55z
    'apis' => [
        'google' => '',
        'facebook' => '',
        'aliexpress' => '',
        'matrix' => '',
        'mailtrain' => '',
        'twilio' => '',
    ],

    // cURL presets
    'curl' => [
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:81.0) Gecko/20100101 Firefox/81.0',
        CURLOPT_COOKIEJAR => __ROOT__.'/private/cache/cookies.txt',
        CURLOPT_COOKIEFILE => __ROOT__.'/private/cache/cookies.txt',
        CURLOPT_POST => 0,
    ],


    /***********************************************************
     * OPTIONS TO TWEAK ONLY IF YOU REALLY NEED TO / KNOW HOW TO
     **********************************************************/

    'php' => [
        /** 
         * password_hash() configuration.
         */
        'password_hash_algo' => PASSWORD_ARGON2ID,
        'password_hash_opts' => [ 
            'memory_cost' => 2 * PASSWORD_ARGON2_DEFAULT_MEMORY_COST,
            'time_cost' => 2 * PASSWORD_ARGON2_DEFAULT_TIME_COST,
            'threads' => PASSWORD_ARGON2_DEFAULT_THREADS 
        ],
    ],

    'headers' => [
        /**
         * Feature-policy http header configuration (consumed by the 
         * @see HeadersMiddleware). Changing these defaults may compromise
         * security (i.e. enable unwanted browser apis/features). See 
         * @link https://scotthelme.co.uk/a-new-security-header-feature-policy/
         */ 
        'feature-policy' => [
            'geolocation' => "'self'",
            'midi' => "'self'",
            'notifications' => "'self'",
            'push' => "'self'",
            'sync-xhr' => "'self'",
            'microphone' => "'self'",
            'camera' => "'self'",
            'magnetometer' => "'self'",
            'gyroscope' => "'self'",
            'speaker' => "'self'",
            'vibrate' => "'self'",
            'fullscreen' => "'self'",
            'payment' => "'self'",
        ],

        /**
         * Referrer-policy and content-type-options http header configuration
         * (consumed by the @see HeadersMiddleware). Changing these defaults
         * may compromise security. See 
         * https://scotthelme.co.uk/a-new-security-header-referrer-policy/
         * https://scotthelme.co.uk/hardening-your-http-response-headers/#x-content-type-options
         */ 
        'referrer-policy' => 'strict-origin-when-cross-origin',
        'content-type-options' => 'nosniff',
        // TODO remove unsafe-eval once odan/twig-assets works with csp
        'csp' => [
            'script-src' => [ 'self' => true, 'allow' => [  'https://' . ( $_SERVER['SERVER_NAME'] ?? 'cli-run-or-unsupported-webserver' ) ], 'strict-dynamic' => true, 'unsafe-eval' => true ],
            'object-src' => [ 'default-src' => 'false' ],
            'frame-ancestors' => [ 'self' => true, 'allow' => [ 'https://' . ( $_SERVER['SERVER_NAME'] ?? 'cli-run-or-unsupported-webserver' ) ] ],
            'base-uri' => 'self',
            'require-trusted-types-for' => 'script' // TODO not yet supported https://github.com/paragonie/csp-builder/issues/47
        ],

        /** Optimal production hsts values (see https://hstspreload.org/
         * before setting things up this)
         *   'enable' => false,
         *   'max-age' => 31536000,
         *   'include-sub-domains' => true,
         *   'preload' => true,
         */
        'hsts' => [
            'enable' => true,
            'max-age' => 15552,//552000,
            'include-sub-domains' => false,
            'preload' => false,
        ]
    ],

    /***********************************************************
     * OPTIONS THAT YOU SHOULDN'T HAVE A REASON TO TOUCH UNLESS
     * YOU ARE A GLUED DEVELOPER
     **********************************************************/

    // Twig (set 'cache' to false to disable caching)
    'twig' => [
        'cache' => __ROOT__ . '/private/cache/twig',
        'auto_reload' => true,
        'debug' => false
    ],

    // Twig-translation
    'locale' => [
        'path' => __ROOT__ . '/private/locale',
        'cache' => __ROOT__ . '/private/cache/locale',
        'locale' => 'en_US',
        'domain' => 'messages',
    ],

    // Odan-assets
    'assets' => [
        'path' => __ROOT__ . '/public/assets/cache',
        'url_base_path' => '/assets/cache/',
        // Cache settings
        'cache_enabled' => true,
        'cache_path' => __ROOT__ . '/private/cache',
        'cache_name' => 'assets',
        // Enable JavaScript and CSS compression
        'minify' => 1,
    ]

];
