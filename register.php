<?php
// Patient sign-up. Staff, dentist and admin accounts are created by an administrator, not here.
require __DIR__ . '/includes/bootstrap.php';
start_secure_session();

if (!empty($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$formError = null;
$errors    = [];
$name      = '';
$email     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim((string) ($_POST['full_name'] ?? ''));
    $email    = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['confirm_password'] ?? '');

    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $formError = 'Your session has expired. Please refresh the page and try again.';
    } else {
        if ($name === '' || mb_strlen($name) > 100) {
            $errors['name'] = 'Please enter your full name.';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 255) {
            $errors['email'] = 'Please enter a valid email address.';
        } elseif (($pwErrors = validate_new_password($password, $confirm)) !== []) {
            $errors += $pwErrors;
        } elseif (empty($_POST['terms'])) {
            $errors['terms'] = 'Please agree to the Terms and Conditions and Privacy Policy.';
        }

        if ($errors === []) {
            try {
                db()->prepare('INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)')
                    ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), 'patient']);
                flash_set('flash_success', 'Account created. You can now log in.');
                header('Location: login.php');
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') { // duplicate email (unique key)
                    $errors['email'] = 'An account with this email already exists.';
                } else {
                    error_log('register: ' . $e->getMessage());
                    $formError = 'Unable to create your account. Please try again.';
                }
            }
        }
    }
}

$err = static fn (string $k): ?string => $errors[$k] ?? null;
$cls = static fn (string $k): string => isset($errors[$k]) ? ' is-invalid' : '';

render_auth_start(
    'Create Account',
    '<span>A healthier smile starts</span><span>with <b class="accent">better care.</b></span>',
    "Manage your dental appointments, build better brushing habits with daily reminders, track your oral health progress, and stay connected with your dental care team. Keep everything organized and accessible in one secure, convenient place for a healthier, brighter smile."
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
<style>
/* Sign-up card has more fields: tighten spacing on shorter screens so it fits without scrolling */
@media (min-width: 961px) and (max-height: 920px) {
  .page-scene .scene-card { min-height: 0; padding-top: 34px; padding-bottom: 36px; }
  .page-scene .scene-card h1 { font-size: 28px; margin-top: 6px; }
  .page-scene .scene-card form { margin-top: 16px; }
  .page-scene .scene-card .field { margin-bottom: 12px; }
  .page-scene .scene-card label { margin-bottom: 6px; font-size: 13px; }
  .page-scene .input-wrap input { height: 44px; }
  .page-scene .role-pill { height: 34px; margin-bottom: 12px; }
  .page-scene .check.terms { margin-bottom: 14px; }
  .page-scene .btn-primary, .page-scene .btn-outline { height: 46px; }
  .page-scene .divider-text { margin: 12px 0 10px; }
}
    
  .page-scene .back-row { display: flex; align-items: center; gap: 10px; }
  .page-scene .back-row .eyebrow { margin: 0; }
  .page-scene .back-link {
   display: inline-flex;
   align-items: center;
   justify-content: center;
   width: 32px;
   height: 32px;
   border-radius: 50%;
   color: var(--primary);
   background: var(--light);
   transition: background .15s, transform .15s;
}
.page-scene .back-link:hover { background: #d6e9ff; transform: translateX(-2px); }
.page-scene .back-link:focus-visible { outline: 3px solid rgba(23, 104, 232, .35); outline-offset: 2px; }
    
    
    
</style>

<div class="back-row">
  <a class="back-link" href="login.php" aria-label="Back to log in" title="Back to log in">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M19 12H5M12 19l-7-7 7-7"/>
    </svg>
  </a>
  <p class="eyebrow">Back</p>
</div>

<p class="eyebrow" style="padding-top: 20px;">Sign up to get started</p>

<h1>Create Account</h1>
<p class="lead">Start your journey with a healthier smile.</p>

<?php if ($formError): ?><?= alert_box('error', $formError) ?><?php endif; ?>

<form method="post" action="register.php" id="reset-form" novalidate>
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <span class="role-pill"><?= icon('user', 16) ?> Patient</span>

  <div class="field">
    <label for="full_name">Full name</label>
    <div class="input-wrap">
      <?= icon('user', 18) ?>
      <input id="full_name" name="full_name" type="text" autocomplete="name" required maxlength="100"
             placeholder="Enter your name" value="<?= e($name) ?>" class="<?= trim($cls('name')) ?>">
    </div>
    <?php if ($err('name')): ?><p class="field-error"><?= e($err('name')) ?></p><?php endif; ?>
  </div>

  <div class="field">
    <label for="email">Email address</label>
    <div class="input-wrap">
      <?= icon('mail', 18) ?>
      <input id="email" name="email" type="email" autocomplete="email" required maxlength="255"
             placeholder="example@email.com" value="<?= e($email) ?>" class="<?= trim($cls('email')) ?>">
    </div>
    <?php if ($err('email')): ?><p class="field-error"><?= e($err('email')) ?></p><?php endif; ?>
  </div>

  <div class="field">
    <label for="password">Password</label>
    <div class="input-wrap">
      <?= icon('lock', 18) ?>
      <input id="password" name="password" type="password" autocomplete="new-password" required
             placeholder="Enter your password" class="<?= trim($cls('password')) ?>">
      <?= eye_toggle('password') ?>
    </div>
    <p class="field-error" id="password-error"<?= $err('password') ? '' : ' hidden' ?>><?= e($err('password')) ?></p>
    <!-- Hidden until the person starts typing, so the resting layout matches the prototype. -->
    <ul class="req-list" id="password-reqs" hidden>
      <li data-rule="length">At least 8 characters</li>
      <li data-rule="upper">One uppercase letter</li>
      <li data-rule="lower">One lowercase letter</li>
      <li data-rule="number">One number</li>
      <li data-rule="special">One special character</li>
    </ul>
  </div>

  <div class="field">
    <label for="confirm_password">Confirm password</label>
    <div class="input-wrap">
      <?= icon('lock', 18) ?>
      <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required
             placeholder="Confirm your password" class="<?= trim($cls('confirm')) ?>">
      <?= eye_toggle('confirm_password') ?>
    </div>
    <p class="field-error" id="confirm-error"<?= $err('confirm') ? '' : ' hidden' ?>><?= e($err('confirm')) ?></p>
  </div>

  <label class="check terms">
    <input type="checkbox" name="terms" value="1"<?= !empty($_POST['terms']) ? ' checked' : '' ?>>
    <span>I agree to the <a href="#">Terms and Conditions</a> and <a href="#">Privacy Policy</a></span>
  </label>
  <?php if ($err('terms')): ?><p class="field-error" style="margin:-12px 0 16px"><?= e($err('terms')) ?></p><?php endif; ?>

  <button class="btn-primary" type="submit">Create Account <?= icon('arrow', 18) ?></button>
</form>

<p class="divider-text">Already have an account?</p>
<a class="btn-outline" href="login.php"><?= icon('user', 18) ?> Log in</a>
<?php render_auth_end(['assets/js/toggle_password.js', 'assets/js/reset_password.js']);