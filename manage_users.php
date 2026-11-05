<?php
// Manage User Accounts: the admin creates dentist and staff accounts, edits them,
// resends invitations, deactivates or deletes them. Admin and patient accounts are not handled here.
require __DIR__ . '/includes/bootstrap.php';
start_secure_session();
$me = require_role(['admin']);

const CLINIC_ROLES = ['dentist' => 'Dentist', 'staff' => 'Staff'];

function go(): void
{
    header('Location: manage_users.php');
    exit;
}

/** Validates the add/edit form. Returns [cleanValues, errors]. */
function clean_clinic_input(array $in): array
{
    $data = [
        'full_name'      => trim((string) ($in['full_name'] ?? '')),
        'email'          => strtolower(trim((string) ($in['email'] ?? ''))),
        'role'           => (string) ($in['role'] ?? ''),
        'license_number' => trim((string) ($in['license_number'] ?? '')),
    ];
    $errors = [];

    if ($data['full_name'] === '' || mb_strlen($data['full_name']) > 100) {
        $errors['full_name'] = 'Please enter a full name (up to 100 characters).';
    }
    if ($data['email'] === '' || strlen($data['email']) > 255 || filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if (!isset(CLINIC_ROLES[$data['role']])) {
        $errors['role'] = 'Choose Dentist or Staff.';
    }
    if ($data['role'] !== 'dentist') {
        $data['license_number'] = '';
    } elseif ($data['license_number'] !== '' && !preg_match('/^[A-Za-z0-9 .\/-]{1,50}$/', $data['license_number'])) {
        $errors['license_number'] = 'Use letters, numbers, spaces, dots, slashes or dashes (up to 50 characters).';
    }
    return [$data, $errors];
}

function find_clinic_user(int $id): ?array
{
    $stmt = db()->prepare("SELECT id, full_name, email, role, status, license_number FROM users WHERE id = ? AND role IN ('dentist','staff') LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Turns an issue_invite() result into a flash message. */
function flash_invite_result(string $result, string $email, bool $justCreated): void
{
    $hours = (int) ceil(app_config()['invite']['ttl_seconds'] / 3600);
    switch ($result) {
        case 'sent':
        case 'outbox':
            flash_set('flash_success', ($justCreated ? 'Account created. ' : '') . "Invitation sent to {$email}. The link works once and expires in {$hours} hours.");
            if ($result === 'outbox') {
                flash_set('flash_dev', INVITE_DEV_NOTICE);
            }
            break;
        case 'throttled':
            flash_set('flash_error', 'An invitation was just sent. Please wait a minute before sending another.');
            break;
        default:
            flash_set('flash_error', $justCreated
                ? 'The account was created, but the invitation email could not be sent. Use “Resend invite” once email is working.'
                : 'Unable to send the invitation right now. Please try again.');
    }
}

$mode   = null;                  // 'add' | 'edit' | null
$errors = [];
$values = ['full_name' => '', 'email' => '', 'role' => 'dentist', 'license_number' => ''];
$editId = 0;
$formError = null;

// ---------------- POST actions ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        flash_set('flash_error', 'Your session has expired. Please try again.');
        go();
    }

    $action = (string) ($_POST['action'] ?? '');
    $id     = (int) ($_POST['user_id'] ?? 0);

    try {
        $pdo = db();

        if ($action === 'create') {
            [$data, $errors] = clean_clinic_input($_POST);
            $newId = 0;
            if ($errors === []) {
                try {
                    // The password is random and unknown to everyone until the invitation link is used.
                    $pdo->prepare('INSERT INTO users (full_name, email, password, role, status, license_number) VALUES (?, ?, ?, ?, ?, ?)')
                        ->execute([$data['full_name'], $data['email'], password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), $data['role'], 'active', $data['license_number'] === '' ? null : $data['license_number']]);
                    $newId = (int) $pdo->lastInsertId();
                } catch (PDOException $e) {
                    if ($e->getCode() !== '23000') {
                        throw $e;
                    }
                    $errors['email'] = 'An account with this email already exists.';
                }
            }
            if ($errors === []) {
                flash_invite_result(issue_invite($newId), $data['email'], true);
                go();
            }
            $mode = 'add';
            $values = $data;

        } elseif ($action === 'update') {
            $target = find_clinic_user($id);
            if (!$target) {
                flash_set('flash_error', 'That account could not be found.');
                go();
            }
            [$data, $errors] = clean_clinic_input($_POST);
            if ($errors === []) {
                try {
                    $pdo->prepare("UPDATE users SET full_name = ?, email = ?, role = ?, license_number = ? WHERE id = ? AND role IN ('dentist','staff')")
                        ->execute([$data['full_name'], $data['email'], $data['role'], $data['license_number'] === '' ? null : $data['license_number'], $id]);
                } catch (PDOException $e) {
                    if ($e->getCode() !== '23000') {
                        throw $e;
                    }
                    $errors['email'] = 'An account with this email already exists.';
                }
            }
            if ($errors === []) {
                $msg = 'Account updated.';
                if ($target['email'] !== $data['email'] && invite_state($id) !== 'done') {
                    $msg .= ' The email changed, so resend the invitation.';
                }
                flash_set('flash_success', $msg);
                go();
            }
            $mode = 'edit';
            $editId = $id;
            $values = $data;

        } elseif ($action === 'resend') {
            $target = find_clinic_user($id);
            if (!$target) {
                flash_set('flash_error', 'That account could not be found.');
            } elseif ($target['status'] !== 'active') {
                flash_set('flash_error', 'Enable the account before sending an invitation.');
            } elseif (!in_array(invite_state($id), ['pending', 'expired'], true)) {
                flash_set('flash_error', 'This person has already set a password. They can use “Forgot password” on the login page.');
            } else {
                flash_invite_result(issue_invite($id), $target['email'], false);
            }
            go();

        } elseif ($action === 'set_status') {
            $new  = ($_POST['status'] ?? '') === 'active' ? 'active' : 'disabled';
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND role IN ('dentist','staff')");
            $stmt->execute([$new, $id]);
            flash_set('flash_success', $new === 'active' ? 'Account enabled.' : 'Account disabled. They are signed out on their next request.');
            go();

        } elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role IN ('dentist','staff')");
            $stmt->execute([$id]);
            flash_set($stmt->rowCount() ? 'flash_success' : 'flash_error', $stmt->rowCount() ? 'Account deleted.' : 'That account could not be found.');
            go();

        } else {
            flash_set('flash_error', 'Unknown action.');
            go();
        }
    } catch (Throwable $e) {
        error_log('manage_users: ' . $e->getMessage());
        $formError = 'Something went wrong. Check that the database migrations have been run (see database/migration_accounts.sql).';
        $mode = $mode ?? ($action === 'create' ? 'add' : null);
    }
}

// ---------------- Page state (GET) ----------------
if ($mode === null && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['add'])) {
        $mode = 'add';
    } elseif (isset($_GET['edit'])) {
        try {
            $editUser = find_clinic_user((int) $_GET['edit']);
        } catch (Throwable $e) {
            $editUser = null;
        }
        if ($editUser) {
            $mode   = 'edit';
            $editId = (int) $editUser['id'];
            $values = ['full_name' => (string) $editUser['full_name'], 'email' => (string) $editUser['email'], 'role' => (string) $editUser['role'], 'license_number' => (string) ($editUser['license_number'] ?? '')];
        } else {
            flash_set('flash_error', 'That account could not be found.');
            go();
        }
    }
}

$q    = trim((string) ($_GET['q'] ?? ''));
$role = (string) ($_GET['role'] ?? '');
$role = isset(CLINIC_ROLES[$role]) ? $role : '';

$rows = [];
try {
    $where = ["u.role IN ('dentist','staff')"];
    $args  = [];
    if ($q !== '') {
        $like    = '%' . addcslashes($q, '%_\\') . '%';
        $where[] = '(u.full_name LIKE ? OR u.email LIKE ?)';
        array_push($args, $like, $like);
    }
    if ($role !== '') {
        $where[] = 'u.role = ?';
        $args[]  = $role;
    }
    $stmt = db()->prepare(
        "SELECT u.id, u.full_name, u.email, u.role, u.status, u.license_number,
                (SELECT CASE WHEN i.used_at IS NOT NULL THEN 'done' WHEN i.expires_at <= NOW() THEN 'expired' ELSE 'pending' END
                 FROM account_invites i WHERE i.user_id = u.id ORDER BY i.id DESC LIMIT 1) AS invite_state
         FROM users u WHERE " . implode(' AND ', $where) . ' ORDER BY u.created_at DESC, u.id DESC LIMIT 200'
    );
    $stmt->execute($args);
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('manage_users list: ' . $e->getMessage());
    $formError = $formError ?? 'Unable to load accounts. Run database/migration_status.sql and database/migration_accounts.sql first.';
}

$flashError   = flash_take('flash_error');
$flashSuccess = flash_take('flash_success');
$flashDev     = flash_take('flash_dev');
$err = static fn (string $k): ?string => $errors[$k] ?? null;
$cls = static fn (string $k): string => isset($errors[$k]) ? ' is-invalid' : '';

admin_head('Manage accounts', $me, 'manage');
?>
  <div class="page-head page-head-row">
    <div>
      <p class="eyebrow">Accounts</p>
      <h1>Manage user accounts</h1>
      <p class="muted">Create dentist and staff accounts. Each person gets an emailed link to choose their own password.</p>
    </div>
    <?php if ($mode === null): ?><a class="btn btn-primary" href="manage_users.php?add=1">Add user</a><?php endif; ?>
  </div>

  <?php if ($formError): ?><?= alert_box('error', $formError) ?><?php endif; ?>
  <?php if ($flashError): ?><?= alert_box('error', $flashError) ?><?php endif; ?>
  <?php if ($flashSuccess): ?><?= alert_box('success', $flashSuccess) ?><?php endif; ?>
  <?php if ($flashDev): ?><?= alert_box('dev', $flashDev) ?><?php endif; ?>

  <?php if ($mode !== null): ?>
  <section class="card form-card">
    <h2><?= $mode === 'add' ? 'Add user' : 'Edit user' ?></h2>
    <form method="post" action="manage_users.php" novalidate>
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="<?= $mode === 'add' ? 'create' : 'update' ?>">
      <?php if ($mode === 'edit'): ?><input type="hidden" name="user_id" value="<?= $editId ?>"><?php endif; ?>

      <div class="form-grid">
        <div class="field">
          <label for="full_name">Full name</label>
          <input class="input<?= $cls('full_name') ?>" id="full_name" name="full_name" type="text" maxlength="100" required value="<?= e($values['full_name']) ?>">
          <?php if ($err('full_name')): ?><p class="field-error"><?= e($err('full_name')) ?></p><?php endif; ?>
        </div>
        <div class="field">
          <label for="email">Email</label>
          <input class="input<?= $cls('email') ?>" id="email" name="email" type="email" maxlength="255" required value="<?= e($values['email']) ?>">
          <?php if ($err('email')): ?><p class="field-error"><?= e($err('email')) ?></p><?php endif; ?>
        </div>
        <div class="field">
          <label for="role">Role</label>
          <select class="input<?= $cls('role') ?>" id="role" name="role">
            <?php foreach (CLINIC_ROLES as $key => $label): ?>
              <option value="<?= e($key) ?>"<?= $values['role'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($err('role')): ?><p class="field-error"><?= e($err('role')) ?></p><?php endif; ?>
          <?php if ($mode === 'edit'): ?><p class="hint">Changing the role changes which pages this person can reach on their next page load.</p><?php endif; ?>
        </div>
        <div class="field" id="license-field">
          <label for="license_number">License number <span class="optional">(optional)</span></label>
          <input class="input<?= $cls('license_number') ?>" id="license_number" name="license_number" type="text" maxlength="50" value="<?= e($values['license_number']) ?>">
          <?php if ($err('license_number')): ?><p class="field-error"><?= e($err('license_number')) ?></p><?php endif; ?>
        </div>
      </div>

      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= $mode === 'add' ? 'Create and send invitation' : 'Save changes' ?></button>
        <a class="btn btn-ghost" href="manage_users.php">Cancel</a>
      </div>
    </form>
  </section>
  <?php endif; ?>

  <section class="card">
    <form class="toolbar" method="get" action="manage_users.php">
      <div class="search">
        <?= icon('user', 18) ?>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by name or email" aria-label="Search accounts">
      </div>
      <select name="role" aria-label="Filter by role">
        <option value="">Dentists and staff</option>
        <?php foreach (CLINIC_ROLES as $key => $label): ?>
          <option value="<?= e($key) ?>"<?= $role === $key ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-primary" type="submit">Search</button>
      <?php if ($q !== '' || $role !== ''): ?><a class="btn btn-ghost" href="manage_users.php">Clear</a><?php endif; ?>
    </form>

    <div class="table-scroll">
      <table>
        <thead>
          <tr><th>Name</th><th>Email</th><th>Role</th><th>License</th><th>Status</th><th class="right">Actions</th></tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="6" class="empty">No dentist or staff accounts yet. Click “Add user” to create one.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $u):
            $active  = $u['status'] === 'active';
            $initial = strtoupper(mb_substr(trim((string) $u['full_name']) ?: (string) $u['email'], 0, 1));
            $state   = $u['invite_state'];
        ?>
          <tr>
            <td><span class="person"><span class="avatar"><?= e($initial) ?></span><?= e($u['full_name'] ?: '—') ?></span></td>
            <td class="email"><?= e($u['email']) ?></td>
            <td><span class="pill pill-<?= e($u['role']) ?>"><?= e(CLINIC_ROLES[$u['role']] ?? ucfirst($u['role'])) ?></span></td>
            <td class="muted"><?= e($u['license_number'] ?: '—') ?></td>
            <td>
              <span class="status <?= $active ? 'on' : 'off' ?>"><?= $active ? 'Active' : 'Disabled' ?></span>
              <?php if ($state === 'pending'): ?><span class="badge badge-wait">Invite pending</span>
              <?php elseif ($state === 'expired'): ?><span class="badge badge-bad">Invite expired</span><?php endif; ?>
            </td>
            <td class="right">
              <div class="row-actions">
                <a class="btn btn-ghost btn-small" href="manage_users.php?edit=<?= (int) $u['id'] ?>">Edit</a>

                <?php if (in_array($state, ['pending', 'expired'], true) && $active): ?>
                <form method="post" action="manage_users.php">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="resend">
                  <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                  <button class="btn btn-ghost btn-small" type="submit">Resend invite</button>
                </form>
                <?php endif; ?>

                <form method="post" action="manage_users.php" onsubmit="return confirm('<?= $active ? 'Disable' : 'Enable' ?> this account?');">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="set_status">
                  <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                  <input type="hidden" name="status" value="<?= $active ? 'disabled' : 'active' ?>">
                  <button class="btn btn-small <?= $active ? 'btn-danger' : 'btn-ok' ?>" type="submit"><?= $active ? 'Disable' : 'Enable' ?></button>
                </form>

                <form method="post" action="manage_users.php" onsubmit="return confirm('Permanently delete this account? This cannot be undone. Disable it instead if you may need the record.');">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                  <button class="btn btn-small btn-danger" type="submit">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php admin_foot(['assets/js/manage_users.js']);
