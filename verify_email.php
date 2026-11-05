<?php
// Screen 3: Verify Email (six-digit PIN).
require __DIR__ . '/includes/bootstrap.php';
start_secure_session();

$email = $_SESSION['pending_reset_email'] ?? null;
if ($email === null) {
    flash_set('flash_error', 'Please request a verification code first.');
    header('Location: login.php');
    exit;
}

$error    = flash_take('flash_error');
$notice   = flash_take('flash_notice');
$devNote  = flash_take('flash_dev');
$pinError = null;   // message under the boxes
$digitsBack = [];     // digits to show again after a wrong code

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'verify');

    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $error = 'Your session has expired. Please try again.';

    } elseif ($action === 'resend') {
        if (resend_cooldown_left() > 0) {
            $error = 'Please wait for the timer to finish before requesting a new code.';
        } else {
            $result = issue_reset_pin($email);
            if ($result === 'failed') {
                $error = 'Unable to send a verification code. Please try again later.';
            } else {
                start_resend_cooldown();
                $notice = 'If an account matches this email, a new verification code has been sent.';
                if ($result === 'outbox') {
                    $devNote = DEV_MAIL_NOTICE;
                }
            }
        }

    } else {
        $digits = $_POST['pin'] ?? [];
        $pin    = is_array($digits) ? implode('', array_map('strval', $digits)) : '';

        if (!preg_match('/^\d{6}$/', $pin)) {
            $pinError = 'Please enter the complete 6-digit verification code.';
        } else {
            $verified = verify_reset_pin($email, $pin);

            switch ($verified['status']) {
                case 'ok':
                    $_SESSION['reset_auth'] = [
                        'reset_id' => $verified['reset_id'],
                        'user_id'  => $verified['user_id'],
                        'token'    => $verified['token'],
                    ];
                    flash_set('flash_success', 'Your email has been verified.');
                    header('Location: reset_password.php');
                    exit;

                case 'expired':
                    $error    = 'The verification code has expired. Please request a new one.';
                    $pinError = 'The code has expired. Please resend a new code.';
                    break;

                case 'locked':
                    $error = 'Too many attempts. Please request a new verification code later.';
                    break;

                default:
                    $error      = 'Invalid code. Please check and try again.';
                    $pinError   = 'The code you entered is incorrect.';
                    $digitsBack = str_split($pin);
            }
        }
    }
}

$cooldown = resend_cooldown_left();
$timerText = $cooldown > 0 ? sprintf(' (%02d:%02d)', intdiv($cooldown, 60), $cooldown % 60) : '';

render_split_start(
    'Verify Your Email',
    ['Your smile', 'matters. Let’s', 'keep it healthy!'],
    ['Verify your email to continue', 'and regain access to your account.'],
    'ring'
);
?>
<?php if ($error): ?><?= alert_box('error', $error) ?><?php endif; ?>
<?php if ($notice): ?><?= alert_box('success', $notice) ?><?php endif; ?>
<?php if ($devNote): ?><?= alert_box('dev', $devNote) ?><?php endif; ?>

<a class="back-link" href="login.php"><?= icon('arrow-left', 16) ?> Back to Login</a>
<h1>Verify Your Email</h1>
<p class="lead">We’ve sent a 6-digit verification code to your registered email address.</p>

<div class="email-chip">
  <?= icon('mail', 20) ?>
  <span class="email-text"><?= e($email) ?></span>
  <a href="login.php">Change</a>
</div>

<form method="post" action="verify_email.php" id="verify-form" novalidate>
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="verify">

  <div class="pin-group" role="group" aria-label="6-digit verification code">
    <?php for ($i = 0; $i < 6; $i++): ?>
      <input class="pin-box<?= $digitsBack ? ' is-invalid' : '' ?>" type="text" name="pin[]"
             inputmode="numeric" pattern="[0-9]" maxlength="1" placeholder="–"
             value="<?= e($digitsBack[$i] ?? '') ?>"
             autocomplete="<?= $i === 0 ? 'one-time-code' : 'off' ?>"
             aria-label="Digit <?= $i + 1 ?> of 6" aria-describedby="pin-error">
    <?php endfor; ?>
  </div>

  <p class="pin-message<?= $pinError ? ' field-error' : '' ?>" id="pin-error" aria-live="polite"><?= e($pinError ?? 'Enter the 6-digit code') ?></p>

  <button class="btn-primary" type="submit">Verify Code <?= icon('arrow', 20) ?></button>
</form>

<div class="resend-row">
  Didn’t receive the code?
  <form method="post" action="verify_email.php" class="inline-form">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="resend">
    <button type="submit" class="link-button" id="resend-btn"
            data-seconds="<?= $cooldown ?>"<?= $cooldown > 0 ? ' disabled' : '' ?>>Resend code<span id="resend-timer"><?= e($timerText) ?></span></button>
  </form>
</div>
<?php render_split_end(['assets/js/verify_email.js']);
