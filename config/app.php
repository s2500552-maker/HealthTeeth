<?php
/*
 * HealthTeeth application settings.
 *
 * Security: this folder must not be publicly reachable (see config/.htaccess).
 * For production, move this file outside htdocs and update the require path
 * in includes/bootstrap.php.
 */
return [
    'db' => [
        'host'    => '127.0.0.1',
        'name'    => 'healthteeth',
        'user'    => 'root',
        'pass'    => '',          // XAMPP default is empty; set a real password in production
        'charset' => 'utf8mb4',
    ],

    'pin' => [
        'ttl_seconds'      => 600, // code expires after 10 minutes
        'resend_cooldown'  => 60,  // seconds before another code may be requested
        'max_attempts'     => 5,   // wrong entries before the code is locked
        'max_sends_per_hour' => 5,
    ],

    'reset' => [
        'auth_ttl_seconds' => 900, // time allowed to finish a reset after the PIN is verified
    ],

    'app' => [
        // Used to build the link in invitation emails. No trailing slash. Never taken from the request.
        'base_url' => 'http://localhost/healthteeth',
    ],

    'invite' => [
        'ttl_seconds'     => 172800, // invitation link expires after 48 hours
        'resend_cooldown' => 60,     // seconds before the same user can be sent another invitation
    ],

    'mail' => [
        'from_email' => 'no-reply@healthteeth.local',
        'from_name'  => 'HealthTeeth',
        // true  = write emails to logs/mail_outbox.log (local development only)
        // false = send with PHP mail(), configured via php.ini / sendmail.ini or SMTP
        'dev_mode'   => true,
    ],
];
