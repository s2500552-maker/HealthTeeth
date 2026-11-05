<?php
// Screen 4: Create New Password. Only reachable after a verified PIN (server-side check below).
require __DIR__ . '/includes/bootstrap.php';
start_secure_session();

// After a successful reset we redirect here once to show the confirmation.
if (!empty($_SESSION['reset_done'])) {
    unset($_SESSION['reset_done']);
    render_split_start(
        'Password Reset Successful',
        ['A healthier', 'smile leads to', 'a brighter you.'],
        ['Set a new password to', 'secure your account.'],
        'shield'
    );
?>
    <div class="success-state">
      <div class="success-check" aria-hidden="true"><?= icon('check', 36) ?></div>
      <h1>Password Reset Successful!</h1>
      <p class="lead">Your password has been updated. You can now log in using your new password.</p>
      <a class="btn-primary" href="login.php">Back to Login <?= icon('arrow', 20) ?></a>
    </div>
<?php
    render_split_end();
    exit;
}

// Authorization: the session must hold a token that matches an unexpired, verified reset record.
$auth   = $_SESSION['reset_auth'] ?? null;
$active = is_array($auth) ? find_active_reset_auth($auth) : null;

if ($active === null) {
    unset($_SESSION['reset_auth']);
    flash_set('flash_error', 'Your password reset session has expired. Please verify your email again.');
    header('Location: verify_email.php');
    exit;
}

$formError = null;
$errors    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $formError = 'Your session has expired. Please refresh the page and try again.';
    } else {
        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['confirm_password'] ?? '');
        $errors   = validate_new_password($password, $confirm);

        if ($errors === []) {
            try {
                complete_password_reset((int) $active['user_id'], $password);
                unset($_SESSION['reset_auth']);
                $_SESSION['reset_done'] = true;
                header('Location: reset_password.php');
                exit;
            } catch (Throwable $e) {
                error_log('reset_password: ' . $e->getMessage());
                $formError = 'Unable to reset your password. Please try again.';
            }
        }
    }
}

$success = flash_take('flash_success');
$pwError = $errors['password'] ?? null;
$cfError = $errors['confirm'] ?? null;

render_split_start(
    'Create New Password',
    ['A healthier', 'smile leads to', 'a brighter you.'],
    ['Set a new password to', 'secure your account.'],
    'shield'
);

// Banner text: the first problem found. The inline line under "confirm" is worded as in the design.
$banner = $formError ?? $pwError ?? $cfError;
?>
<?= alert_box('error', (string) $banner, 'form-alert', $banner === null) ?>
<?php if ($success): ?><?= alert_box('success', $success) ?><?php endif; ?>

<a class="back-link" href="login.php"><?= icon('arrow-left', 16) ?> Back to Login</a>
<h1>Create New Password</h1>
<p class="lead">Your new password must be strong and secure.</p>

<form method="post" action="reset_password.php" id="reset-form" novalidate>
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

  <div class="field">
    <label for="password">New Password</label>
    <div class="input-wrap no-icon">
      <input id="password" name="password" type="password" autocomplete="new-password" required
             class="<?= $pwError ? 'is-invalid' : '' ?>"
             aria-invalid="<?= $pwError ? 'true' : 'false' ?>"
             aria-describedby="password-reqs">
      <?= eye_toggle('password') ?>
    </div>
    <p class="field-error" id="password-error" hidden></p>
    <ul class="req-list" id="password-reqs">
      <li data-rule="length">At least 8 characters</li>
      <li data-rule="upper">One uppercase letter</li>
      <li data-rule="lower">One lowercase letter</li>
      <li data-rule="number">One number</li>
      <li data-rule="special">One special character</li>
    </ul>
  </div>

  <div class="field">
    <label for="confirm_password">Confirm New Password</label>
    <div class="input-wrap no-icon">
      <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required
             class="<?= $cfError ? 'is-invalid' : '' ?>"
             aria-invalid="<?= $cfError ? 'true' : 'false' ?>"
             aria-describedby="confirm-error">
      <?= eye_toggle('confirm_password') ?>
    </div>
    <p class="field-error" id="confirm-error"<?= $cfError ? '' : ' hidden' ?>><?= $cfError ? 'The passwords you entered do not match.' : '' ?></p>
  </div>

  <button class="btn-primary" type="submit">Reset Password <?= icon('arrow', 20) ?></button>
</form>
<?php render_split_end(['assets/js/toggle_password.js', 'assets/js/reset_password.js']);
