<?php
// Shared page frame for the admin screens.

function admin_head(string $title, array $user, string $active): void
{
    $nav = ['users' => ['admin_dashboard.php', 'Users'], 'manage' => ['manage_users.php', 'Manage accounts']];
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · HealthTeeth Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;family=Plus+Jakarta+Sans:wght@700;800&amp;display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<header class="topbar">
  <div class="brand">
    <span class="brand-mark"><?= icon('tooth', 22) ?></span>
    <span class="brand-name">HealthTeeth</span>
    <span class="brand-tag">Admin</span>
  </div>
  <nav class="nav" aria-label="Admin">
    <?php foreach ($nav as $key => [$href, $label]): ?>
      <a href="<?= e($href) ?>"<?= $key === $active ? ' class="is-active" aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="topbar-user">
    <span class="who"><?= e($user['name']) ?><small><?= e($user['email']) ?></small></span>
    <form method="post" action="logout.php">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <button class="btn btn-ghost" type="submit">Log out</button>
    </form>
  </div>
</header>
<main class="wrap">
<?php
}

function admin_foot(array $scripts = []): void
{
?>
</main>
<?php foreach (array_merge(['assets/js/ui.js'], $scripts) as $src): ?>
<script src="<?= e($src) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
<?php
}
