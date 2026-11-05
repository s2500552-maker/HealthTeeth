<?php
// Landing page for the emailed "set your password" link (dentist and staff invitations).
// A valid, unexpired link starts a verified session and hands over to reset_password.php.
require __DIR__ . '/includes/bootstrap.php';
start_secure_session();

$invite = null;
$token  = (string) ($_GET['token'] ?? '');
if ($token !== '') {
    try {
        $invite = find_valid_invite($token);
        if ($invite !== null) {
            start_invite_session((int) $invite['user_id']);
            header('Location: reset_password.php');
            exit;
        }
    } catch (Throwable $e) {
        error_log('set_password: ' . $e->getMessage());
    }
}

render_split_start(
    'Invitation link',
    ['A healthier', 'smile leads to', 'a brighter you.'],
    ['Your clinic account is', 'set up by an administrator.'],
    'shield'
);
?>
    <a class="back-link" href="login.php"><?= icon('arrow-left', 16) ?> Back to Login</a>
    <h1>This link can’t be used</h1>
    <p class="lead">The invitation link is invalid, has already been used, or has expired. Ask your administrator to send you a new invitation.</p>
    <a class="btn-primary" href="login.php">Back to Login <?= icon('arrow', 20) ?></a>
<?php render_split_end();
