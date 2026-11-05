<?php
// Placeholder landing page after login. Replace with the real role dashboards.
require __DIR__ . '/includes/bootstrap.php';
start_secure_session();

// Re-checks the account on every request: disabled accounts are signed out, roles are always current.
$user = require_role(['patient', 'dentist', 'staff', 'admin']);

if ($user['role'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit;
}

render_auth_start('Dashboard', 'Your smile matters.', 'Dashboards for each role are coming in the next increment.');
?>
<p class="eyebrow"><?= e(ucfirst($user['role'])) ?></p>
<h1>Welcome, <?= e($user['name']) ?>!</h1>
<p class="lead">You are logged in as <?= e($user['email']) ?>. This is a placeholder page.</p>
<form method="post" action="logout.php">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <button class="btn-primary" type="submit">Log out</button>
</form>
<?php render_auth_end();
