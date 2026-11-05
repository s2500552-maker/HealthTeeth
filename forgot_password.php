<?php
// Receives the email from the login page's "Forgot password" form.
// Always responds the same way, so it never reveals whether an account exists.
require __DIR__ . '/includes/bootstrap.php';
start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

if (!csrf_valid($_POST['csrf'] ?? null)) {
    flash_set('flash_error', 'Your session has expired. Please try again.');
    header('Location: login.php');
    exit;
}

$email = strtolower(trim((string) ($_POST['email'] ?? '')));
if ($email === '' || strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    flash_set('flash_error', 'Please enter a valid email address.');
    header('Location: login.php');
    exit;
}

$_SESSION['pending_reset_email'] = $email;
$result = issue_reset_pin($email);

if ($result === 'failed') {
    flash_set('flash_error', 'Unable to send a verification code. Please try again later.');
} else {
    start_resend_cooldown();
    flash_set('flash_notice', 'If an account matches this email, we’ve sent a verification code.');
    if ($result === 'outbox') {
        flash_set('flash_dev', DEV_MAIL_NOTICE);
    }
}

header('Location: verify_email.php');
exit;
