<?php
// Admin dashboard: user directory with search, role filter, pagination and enable/disable.
require __DIR__ . '/includes/bootstrap.php';
start_secure_session();

$me       = require_role(['admin']);
$roles    = ['patient' => 'Patient', 'dentist' => 'Dentist', 'staff' => 'Staff', 'admin' => 'Admin'];
$perPage  = 10;
$error    = flash_take('flash_error');
$success  = flash_take('flash_success');

// ---- Enable / disable an account (POST) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = 'admin_dashboard.php' . (!empty($_POST['return']) ? '?' . ltrim((string) $_POST['return'], '?') : '');
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        flash_set('flash_error', 'Your session has expired. Please try again.');
    } else {
        $targetId  = (int) ($_POST['user_id'] ?? 0);
        $newStatus = ($_POST['status'] ?? '') === 'active' ? 'active' : 'disabled';
        if ($targetId === (int) $me['id']) {
            flash_set('flash_error', 'You cannot change your own account status.');
        } else {
            try {
                $stmt = db()->prepare('UPDATE users SET status = ? WHERE id = ?');
                $stmt->execute([$newStatus, $targetId]);
                flash_set('flash_success', $stmt->rowCount() ? 'Account updated.' : 'No changes were made.');
            } catch (Throwable $e) {
                error_log('admin status: ' . $e->getMessage());
                flash_set('flash_error', 'Unable to update the account right now.');
            }
        }
    }
    header('Location: ' . $back);
    exit;
}

// ---- Filters ----
$q      = trim((string) ($_GET['q'] ?? ''));
$role   = (string) ($_GET['role'] ?? '');
$role   = isset($roles[$role]) ? $role : '';
$page   = max(1, (int) ($_GET['page'] ?? 1));

$counts = array_fill_keys(array_keys($roles), 0);
$users  = [];
$total  = 0;

try {
    $pdo = db();

    foreach ($pdo->query('SELECT role, COUNT(*) AS n FROM users GROUP BY role') as $row) {
        if (isset($counts[$row['role']])) {
            $counts[$row['role']] = (int) $row['n'];
        }
    }

    $where = [];
    $args  = [];
    if ($q !== '') {
        $like    = '%' . addcslashes($q, '%_\\') . '%';
        $where[] = '(full_name LIKE ? OR email LIKE ?)';
        array_push($args, $like, $like);
    }
    if ($role !== '') {
        $where[] = 'role = ?';
        $args[]  = $role;
    }
    $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users' . $sqlWhere);
    $stmt->execute($args);
    $total = (int) $stmt->fetchColumn();

    $pages  = max(1, (int) ceil($total / $perPage));
    $page   = min($page, $pages);
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare(
        'SELECT id, full_name, email, role, status, created_at FROM users'
        . $sqlWhere . ' ORDER BY created_at DESC, id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset
    );
    $stmt->execute($args);
    $users = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('admin dashboard: ' . $e->getMessage());
    $error = $error ?: 'Unable to load users right now. Check that the users table has a "status" column (see database/migration_status.sql).';
    $pages = 1;
}

$totalUsers = array_sum($counts);
$query = fn(array $extra = []) => http_build_query(array_filter(
    array_merge(['q' => $q, 'role' => $role, 'page' => $page], $extra),
    fn($v) => $v !== '' && $v !== null && $v !== 0
));
$returnQs = $query();
admin_head('Users', $me, 'users');
?>
  <div class="page-head">
    <div>
      <p class="eyebrow">Directory</p>
      <h1>Users</h1>
      <p class="muted">Everyone with a HealthTeeth account, across all roles.</p>
    </div>
  </div>

  <?php if ($error): ?><?= alert_box('error', $error) ?><?php endif; ?>
  <?php if ($success): ?><?= alert_box('success', $success) ?><?php endif; ?>

  <section class="stats" aria-label="User totals">
    <a class="stat<?= $role === '' ? ' is-active' : '' ?>" href="admin_dashboard.php">
      <span class="stat-icon"><?= icon('users', 20) ?></span>
      <span class="stat-num"><?= $totalUsers ?></span>
      <span class="stat-label">All users</span>
    </a>
    <?php foreach ($roles as $key => $label): ?>
    <a class="stat<?= $role === $key ? ' is-active' : '' ?>" href="admin_dashboard.php?role=<?= e($key) ?>">
      <span class="stat-icon"><?= icon(['patient' => 'user', 'dentist' => 'tooth', 'staff' => 'users', 'admin' => 'gear'][$key], 20) ?></span>
      <span class="stat-num"><?= $counts[$key] ?></span>
      <span class="stat-label"><?= e($label) ?>s</span>
    </a>
    <?php endforeach; ?>
  </section>

  <section class="card">
    <form class="toolbar" method="get" action="admin_dashboard.php">
      <div class="search">
        <?= icon('user', 18) ?>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by name or email" aria-label="Search users">
      </div>
      <select name="role" aria-label="Filter by role">
        <option value="">All roles</option>
        <?php foreach ($roles as $key => $label): ?>
          <option value="<?= e($key) ?>"<?= $role === $key ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-primary" type="submit">Search</button>
      <?php if ($q !== '' || $role !== ''): ?><a class="btn btn-ghost" href="admin_dashboard.php">Clear</a><?php endif; ?>
    </form>

    <div class="table-scroll">
      <table>
        <thead>
          <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th class="right">Action</th></tr>
        </thead>
        <tbody>
        <?php if (!$users): ?>
          <tr><td colspan="6" class="empty">No users match your search.</td></tr>
        <?php endif; ?>
        <?php foreach ($users as $u):
            $isMe   = (int) $u['id'] === (int) $me['id'];
            $active = $u['status'] === 'active';
            $initial = strtoupper(mb_substr(trim((string) $u['full_name']) ?: (string) $u['email'], 0, 1));
        ?>
          <tr>
            <td><span class="person"><span class="avatar"><?= e($initial) ?></span><?= e($u['full_name'] ?: '—') ?><?php if ($isMe): ?> <em class="you">you</em><?php endif; ?></span></td>
            <td class="email"><?= e($u['email']) ?></td>
            <td><span class="pill pill-<?= e($u['role']) ?>"><?= e($roles[$u['role']] ?? ucfirst($u['role'])) ?></span></td>
            <td><span class="status <?= $active ? 'on' : 'off' ?>"><?= $active ? 'Active' : 'Disabled' ?></span></td>
            <td class="muted"><?= e(date('M j, Y', strtotime((string) $u['created_at']))) ?></td>
            <td class="right">
              <?php if ($isMe): ?>
                <span class="muted">—</span>
              <?php else: ?>
                <form method="post" action="admin_dashboard.php" onsubmit="return confirm('<?= $active ? 'Disable' : 'Enable' ?> this account?');">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                  <input type="hidden" name="status" value="<?= $active ? 'disabled' : 'active' ?>">
                  <input type="hidden" name="return" value="<?= e($returnQs) ?>">
                  <button class="btn btn-small <?= $active ? 'btn-danger' : 'btn-ok' ?>" type="submit"><?= $active ? 'Disable' : 'Enable' ?></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="pager">
      <span class="muted"><?= $total ?> user<?= $total === 1 ? '' : 's' ?> · page <?= $page ?> of <?= $pages ?></span>
      <span class="pager-links">
        <?php if ($page > 1): ?><a class="btn btn-ghost btn-small" href="?<?= e($query(['page' => $page - 1])) ?>">Previous</a><?php endif; ?>
        <?php if ($page < $pages): ?><a class="btn btn-ghost btn-small" href="?<?= e($query(['page' => $page + 1])) ?>">Next</a><?php endif; ?>
      </span>
    </div>
  </section>
<?php admin_foot();
