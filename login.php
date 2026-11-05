<?php
// Login page. Role tabs: Patient / Dentist / Staff / Admin.
require __DIR__ . '/includes/bootstrap.php';
start_secure_session();

if (!empty($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

const LOGIN_MAX_FAILS = 5;
const LOGIN_WINDOW_MIN = 15;
const ROLES = ['patient' => 'Patient', 'dentist' => 'Dentist', 'staff' => 'Staff', 'admin' => 'Admin'];
const ROLE_ICONS = ['patient' => 'user', 'dentist' => 'tooth', 'staff' => 'users', 'admin' => 'gear'];

$error   = flash_take('flash_error');
$success = flash_take('flash_success');
$email   = '';
$role    = 'patient';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $role  = (string) ($_POST['role'] ?? 'patient');
    if (!isset(ROLES[$role])) {
        $role = 'patient';
    }
    $password = (string) ($_POST['password'] ?? '');
    $ip       = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $error = 'Your session has expired. Please try again.';
    } elseif ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        try {
            $pdo = db();

            $fails = $pdo->prepare(
                'SELECT COUNT(*) FROM login_attempts
                 WHERE email = ? AND attempted_at > NOW() - INTERVAL ' . LOGIN_WINDOW_MIN . ' MINUTE'
            );
            $fails->execute([$email]);

            if ((int) $fails->fetchColumn() >= LOGIN_MAX_FAILS) {
                $error = 'Too many failed attempts. Please try again in ' . LOGIN_WINDOW_MIN . ' minutes.';
            } else {
                $stmt = $pdo->prepare('SELECT id, full_name, email, password, role, status FROM users WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                // Always run password_verify so response time doesn't reveal whether the email exists.
                $hash = $user['password'] ?? '$2y$10$usesomesillystringforsalt$2y$10$abcdefghijklmnopqrstuv';
                $ok   = password_verify($password, $hash) && $user && $user['role'] === $role && $user['status'] === 'active';

                if ($ok) {
                    $pdo->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$email]);
                    session_regenerate_id(true);
                    $_SESSION['user'] = [
                        'id'    => (int) $user['id'],
                        'name'  => $user['full_name'],
                        'email' => $user['email'],
                        'role'  => $user['role'],
                    ];
                    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                        $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
                            ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
                    }
                    if (!empty($_POST['remember'])) {
                        // Keep the session cookie for 30 days (server-side session lifetime is set to match).
                        setcookie(session_name(), session_id(), [
                            'expires'  => time() + 30 * 86400,
                            'path'     => '/',
                            'secure'   => !empty($_SERVER['HTTPS']),
                            'httponly' => true,
                            'samesite' => 'Strict',
                        ]);
                    }
                    header('Location: dashboard.php');
                    exit;
                }

                $pdo->prepare('INSERT INTO login_attempts (email, ip) VALUES (?, ?)')->execute([$email, $ip]);
                $error = 'Invalid email or password.';
            }
        } catch (Throwable $e) {
            error_log('login: ' . $e->getMessage());
            $error = 'Unable to log in right now. Please try again later.';
        }
    }
}

render_auth_start(
    'Log in',
    '<span>A healthier smile starts</span><span>with <b class="accent">better care.</b></span>',
    "Manage your dental appointments, build better brushing habits with daily reminders, track your oral health progress, and stay connected with your dental care team. Keep everything organized and accessible in one secure, 		convenient place for a healthier, brighter smile."
);
?>
<style>
/* ===== Visual refresh: shared by login.php and register.php (presentation only) ===== */
body.page-scene {
  --primary: #1768E8;
  --primary-2: #3a8cff;
  --navy: #123A78;
  --title: #123A78;
  --light: #EAF5FF;
  --grad: linear-gradient(90deg, #1768E8 0%, #3a8cff 100%);
  background: linear-gradient(120deg, #a9d3ff 0%, #d3e9ff 42%, #e6f3ff 100%);
  overflow-x: hidden;
}

/* Background illustration, ~15% smaller than "cover", never stretched (auto width, set height).
   Two copies of the same artwork: the left one holds the mascot, the right one holds the dental
   tools. Their inner edges fade out so the pale middle of the picture blends into the page. */
body.page-scene::before,
body.page-scene::after {
  content: "";
  position: fixed;
  inset: 0;
  z-index: 0;
  pointer-events: none;
  background-repeat: no-repeat;
  -webkit-mask-composite: source-in;
  mask-composite: intersect;
}
body.page-scene::before {
  background-image:
    radial-gradient(46% 52% at 27% 34%, rgba(23, 104, 232, .34) 0%, rgba(23, 104, 232, .16) 55%, rgba(23, 104, 232, 0) 100%),
    url("assets/img/auth-bg.png");
  background-size: 100% 100%, auto max(60.5vw, 96vh);
  background-position: 0 0, left -1vw bottom calc(0px - clamp(0px, max(60.5vw, 96vh) - 100vh, 8vh));
  -webkit-mask-image: linear-gradient(90deg, #000 0, #000 50%, transparent 78%), linear-gradient(180deg, transparent 0, #000 4%);
  mask-image: linear-gradient(90deg, #000 0, #000 50%, transparent 78%), linear-gradient(180deg, transparent 0, #000 4%);
}
body.page-scene::after {
  background-image: url("assets/img/auth-bg.png");
  background-size: auto max(60.5vw, 96vh);
  background-position: right center;
  -webkit-mask-image: linear-gradient(90deg, transparent 0, transparent 52%, #000 72%), linear-gradient(180deg, transparent 0, #000 4%);
  mask-image: linear-gradient(90deg, transparent 0, transparent 52%, #000 72%), linear-gradient(180deg, transparent 0, #000 4%);
}
body.page-scene .scene { position: relative; z-index: 1; }

/* Left side: keep copy in the upper area, clear of the mascot */
@media (min-width: 961px) {
    .page-scene .scene-hero {
  	transform: translateX(-40px);
}
  .page-scene .scene-hero { padding-top: clamp(18px, 3.5vh, 44px); gap: clamp(18px, 4vh, 44px); }
  .page-scene .scene-copy h2 {
    font-size: clamp(30px, 3.2vw, 50px);
    line-height: 1.18;
    letter-spacing: -.015em;
    text-shadow: 0 2px 14px rgba(12, 60, 160, .35);
  }
  .page-scene .scene-copy h2 .accent {
    color: #1768E8;
    text-shadow: 0 0 14px rgba(255, 255, 255, .85), 0 0 3px rgba(255, 255, 255, .7);
  }
  .page-scene .scene-copy p {
    font-weight: 500;
    line-height: 1.7;
    margin-top: 16px;
    text-shadow: 0 1px 10px rgba(12, 60, 160, .4);
  }
}

/* Mobile / tablet: single image, no overlay, navy copy (set in auth.css) stays readable */
@media (max-width: 960px) {
  body.page-scene::before {
    background-image: url("assets/img/auth-bg.png");
    background-size: auto 100vh;
    background-position: 18% bottom;
    -webkit-mask-image: linear-gradient(180deg, transparent 0, #000 5%);
    mask-image: linear-gradient(180deg, transparent 0, #000 5%);
  }
  body.page-scene::after { display: none; }
}

/* Card */
.page-scene .scene-card {
  background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
  border: 1px solid rgba(255, 255, 255, .85);
  box-shadow: 0 32px 80px rgba(18, 58, 120, .20), 0 4px 14px rgba(23, 104, 232, .10);
}
.page-scene .scene-card .eyebrow { letter-spacing: .06em; }
.page-scene .scene-card h1 { color: var(--navy); letter-spacing: -.015em; }
.page-scene .scene-card .lead { color: #7d92b4; }
.page-scene .scene-card label { color: var(--navy); }

.page-scene .input-wrap input {
  background: #fff;
  border-color: #d3e4fb;
  box-shadow: inset 0 1px 2px rgba(18, 58, 120, .04);
}
.page-scene .input-wrap input:hover { border-color: #b6d1f6; }
.page-scene .input-wrap input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(23, 104, 232, .14); }

.page-scene .role-tabs { background: var(--light); box-shadow: inset 0 0 0 1px rgba(23, 104, 232, .08); }
.page-scene .role-pill { box-shadow: 0 8px 16px rgba(23, 104, 232, .24); }
.page-scene .btn-primary { box-shadow: 0 12px 24px rgba(23, 104, 232, .30); }
.page-scene .btn-outline { border-color: rgba(23, 104, 232, .45); color: var(--primary); }
.page-scene .btn-outline:hover { background: var(--light); border-color: var(--primary); }
.page-scene .check.terms a, .page-scene .row-between .link-button { color: var(--primary); }
</style>
<p class="eyebrow">Welcome back</p>
<h1>Log in to HealthTeeth</h1>
<p class="lead">Enter your account details to continue.</p>

<?php if ($success): ?><?= alert_box('success', $success) ?><?php endif; ?>
<?php if ($error): ?><?= alert_box('error', $error) ?><?php endif; ?>

<form method="post" action="login.php" novalidate>
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

  <div class="role-tabs" role="radiogroup" aria-label="Log in as">
    <?php foreach (ROLES as $value => $label): ?>
      <label class="role-tab">
        <input type="radio" name="role" value="<?= e($value) ?>"<?= $role === $value ? ' checked' : '' ?>>
        <span><?= icon(ROLE_ICONS[$value], 16) ?><?= e($label) ?></span>
      </label>
    <?php endforeach; ?>
  </div>

  <div class="field">
    <label for="email">Email address</label>
    <div class="input-wrap">
      <?= icon('mail', 18) ?>
      <input id="email" name="email" type="email" autocomplete="email" required
             placeholder="example@email.com" value="<?= e($email) ?>">
    </div>
  </div>

  <div class="field">
    <label for="password">Password</label>
    <div class="input-wrap">
      <?= icon('lock', 18) ?>
      <input id="password" name="password" type="password" autocomplete="current-password" required
             placeholder="Enter your password">
      <?= eye_toggle('password') ?>
    </div>
  </div>

  <div class="row-between">
    <label class="check"><input type="checkbox" name="remember" value="1"> Remember me</label>
    <!-- Posts the email typed above to forgot_password.php, which sends the PIN. -->
    <button type="submit" class="link-button" formaction="forgot_password.php" formnovalidate>Forgot password?</button>
  </div>

  <button class="btn-primary" type="submit">Log In <?= icon('arrow', 18) ?></button>
</form>

<p class="divider-text">Don’t have an account?</p>
<a class="btn-outline" href="register.php"><?= icon('user', 18) ?> Create an account for patient</a>
<?php render_auth_end(['assets/js/toggle_password.js']);
