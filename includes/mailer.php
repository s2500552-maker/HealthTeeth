<?php
// Sends the verification PIN. The PIN is never echoed to the page or to JavaScript.

const DEV_MAIL_NOTICE = 'Local development mode: email delivery is not configured, so the code was written to logs/mail_outbox.log instead of being emailed. Turn off mail.dev_mode and configure sendmail or SMTP before going live.';

/**
 * Returns 'sent' (handed to PHP mail()), 'outbox' (dev mode, written to logs/)
 * or 'failed'. Never claims an email was sent when delivery failed.
 */
function send_pin_email(string $to, string $pin, int $ttlMinutes): string
{
    $mail    = app_config()['mail'];
    $subject = 'Your HealthTeeth verification code';
    $body    = "Your HealthTeeth verification code is: {$pin}\r\n\r\n"
             . "This code expires in {$ttlMinutes} minutes. Never share it with anyone.\r\n"
             . "If you did not request a password reset, you can ignore this email.\r\n";

    if ($mail['dev_mode']) {
        $dir = dirname(__DIR__) . '/logs';
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            return 'failed';
        }
        // Development only: the outbox contains the PIN in plain text. Keep logs/ blocked from the web.
        $entry = '[' . date('Y-m-d H:i:s') . "] To: {$to}\n{$subject}\n{$body}---\n";
        return file_put_contents($dir . '/mail_outbox.log', $entry, FILE_APPEND | LOCK_EX) === false
            ? 'failed'
            : 'outbox';
    }

    $headers = 'From: ' . $mail['from_name'] . ' <' . $mail['from_email'] . ">\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n";

    return mail($to, $subject, $body, $headers) ? 'sent' : 'failed';
}

const INVITE_DEV_NOTICE = 'Local development mode: email delivery is not configured, so the invitation was written to logs/mail_outbox.log instead of being emailed. Open that file to copy the link.';

/** Shared delivery: 'sent' | 'outbox' (dev mode) | 'failed'. */
function deliver_mail(string $to, string $subject, string $body): string
{
    $mail = app_config()['mail'];

    if ($mail['dev_mode']) {
        $dir = dirname(__DIR__) . '/logs';
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            return 'failed';
        }
        $entry = '[' . date('Y-m-d H:i:s') . "] To: {$to}\n{$subject}\n{$body}---\n";
        return file_put_contents($dir . '/mail_outbox.log', $entry, FILE_APPEND | LOCK_EX) === false ? 'failed' : 'outbox';
    }

    $headers = 'From: ' . $mail['from_name'] . ' <' . $mail['from_email'] . ">\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n";
    return mail($to, $subject, $body, $headers) ? 'sent' : 'failed';
}

/** Invitation with a one-time "set your password" link. */
function send_invite_email(string $to, string $name, string $roleLabel, string $link, int $ttlHours): string
{
    $body = "Hello {$name},\r\n\r\n"
          . "An administrator created a HealthTeeth {$roleLabel} account for you.\r\n"
          . "Choose your password with this link. It works once and expires in {$ttlHours} hours:\r\n\r\n"
          . "{$link}\r\n\r\n"
          . "Then log in from the {$roleLabel} tab on the login page.\r\n"
          . "If you were not expecting this email, you can ignore it.\r\n";
    return deliver_mail($to, 'Set up your HealthTeeth account', $body);
}
