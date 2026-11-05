<?php
// Shared layouts for the authentication pages.
//
//  render_auth_start()   Login and sign-up: full-bleed scene, hero copy left, floating card right.
//  render_split_start()  Verify email and reset password: one rounded split card (blue panel + form).
//
// Headline arguments are trusted, hard-coded strings (never user input).

/** Inline SVG icon (stroke style, inherits colour from CSS). */
function icon(string $name, int $size = 20): string
{
    $paths = [
        'mail'      => '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m3.5 8 8.5 6 8.5-6"/>',
        'lock'      => '<rect x="5" y="11" width="14" height="10" rx="3"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/><circle cx="12" cy="16" r="1" fill="currentColor"/>',
        'user'      => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.5-6 8-6s8 2 8 6"/>',
        'tooth'     => '<path d="M7.5 3.5C5 3.5 3.5 5.3 3.5 7.5c0 3 1.5 5 2 8 .3 2 .8 5 2.200 5 1.500 0 1.500-3.500 2.500-5.500h.1c1 2 1 5.500 2.500 5.500 1.400 0 1.900-3 2.200-5 .5-3 2-5 2-8 0-2.200-1.500-4-4-4-1.700 0-2.800.8-4.500.8S9.200 3.500 7.500 3.500z"/>',
        'users'     => '<circle cx="9" cy="8" r="3.5"/><path d="M2.500 20c0-3.500 3-5.500 6.500-5.500s6.500 2 6.500 5.500"/><circle cx="17.500" cy="9" r="2.500"/><path d="M17 14.500c2.700 0 4.500 1.700 4.500 4.500"/>',
        'gear'      => '<circle cx="12" cy="12" r="3"/><path d="M19.400 15a1.650 1.650 0 0 0 .33 1.820l.06.06a2 2 0 0 1-2.830 2.830l-.06-.06a1.650 1.650 0 0 0-1.820-.33 1.650 1.650 0 0 0-1 1.510V21a2 2 0 0 1-4 0v-.09A1.650 1.650 0 0 0 9 19.400a1.650 1.650 0 0 0-1.820.33l-.06.06a2 2 0 0 1-2.830-2.830l.06-.06A1.650 1.650 0 0 0 4.680 15a1.650 1.650 0 0 0-1.510-1H3a2 2 0 0 1 0-4h.09A1.650 1.650 0 0 0 4.600 9a1.650 1.650 0 0 0-.33-1.820l-.06-.06a2 2 0 0 1 2.830-2.830l.06.06A1.650 1.650 0 0 0 9 4.680a1.650 1.650 0 0 0 1-1.510V3a2 2 0 0 1 4 0v.09a1.650 1.650 0 0 0 1 1.510 1.650 1.650 0 0 0 1.820-.33l.06-.06a2 2 0 0 1 2.830 2.830l-.06.06A1.650 1.650 0 0 0 19.400 9a1.650 1.650 0 0 0 1.510 1H21a2 2 0 0 1 0 4h-.09a1.650 1.650 0 0 0-1.510 1z"/>',
        'eye'       => '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off'   => '<path d="M17.940 17.940A10.070 10.070 0 0 1 12 20c-7 0-11-8-11-8a18.450 18.450 0 0 1 5.060-5.940M9.900 4.240A9.120 9.120 0 0 1 12 4c7 0 11 8 11 8a18.500 18.500 0 0 1-2.160 3.190m-6.720-1.070a3 3 0 1 1-4.240-4.240"/><path d="m1 1 22 22"/>',
        'arrow'     => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'arrow-left'=> '<path d="M19 12H5m6-6-6 6 6 6"/>',
        'check'     => '<path d="m5 12.500 4.500 4.500L19 7.500"/>',
        'x'         => '<path d="M6 6l12 12M18 6 6 18"/>',
    ];
    $p = $paths[$name] ?? '';
    return '<svg class="icon icon-' . $name . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" '
         . 'stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

/** Show/hide button for a password input. Both icons are present; CSS swaps them via aria-pressed. */
function eye_toggle(string $targetId): string
{
    return '<button type="button" class="toggle-visibility" data-target="' . e($targetId) . '" aria-label="Show password" aria-pressed="false">'
         . icon('eye') . icon('eye-off') . '</button>';
}

/** Dismissible alert. $type: error | success | dev */
function alert_box(string $type, string $message, string $id = '', bool $hidden = false): string
{
    $glyph = ['error' => '!', 'success' => '✓', 'dev' => 'i'][$type] ?? '!';
    return '<div class="alert alert-' . $type . '"' . ($id !== '' ? ' id="' . e($id) . '"' : '')
         . ' role="' . ($type === 'error' ? 'alert' : 'status') . '"' . ($hidden ? ' hidden' : '') . '>'
         . '<span class="alert-icon" aria-hidden="true">' . $glyph . '</span>'
         . '<span class="alert-text">' . e($message) . '</span>'
         . '<button type="button" class="alert-close" data-dismiss aria-label="Dismiss message">' . icon('x', 16) . '</button>'
         . '</div>';
}

function auth_head(string $title, string $bodyClass): void
{
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · HealthTeeth</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;family=Plus+Jakarta+Sans:wght@700;800&amp;family=Source+Serif+4:opsz,wght@8..60,400;8..60,500&amp;display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="<?= e($bodyClass) ?>">
<?php
}

/* ---------- Login / sign-up: scene + floating card ---------- */

function render_auth_start(string $title, string $headlineHtml, string $subline): void
{
    auth_head($title, 'page-scene');
?>
<main class="scene">
  <section class="scene-hero">
    <img class="scene-logo" src="assets/img/healthteeth-logo.png" alt="HealthTeeth — your daily smile companion">
    <div class="scene-copy">
      <h2><?= $headlineHtml ?></h2>
      <p><?php foreach (explode("\n", $subline) as $line): ?><span><?= e($line) ?></span><?php endforeach; ?></p>
    </div>
  </section>
  <section class="scene-panel">
    <div class="scene-card">
<?php
}

function render_auth_end(array $scripts = []): void
{
?>
    </div>
  </section>
</main>
<?php auth_scripts(array_merge(['assets/js/ui.js'], $scripts)); ?>
</body>
</html>
<?php
}

/* ---------- Verify / reset: one split card ---------- */

/**
 * @param string[] $headlineLines  one entry per line of the serif headline
 * @param string[] $subLines       one entry per line of the supporting text
 * @param string   $art            'ring' (mascot with ring) or 'shield' (mascot with shield)
 */
function render_split_start(string $title, array $headlineLines, array $subLines, string $art = 'ring'): void
{
    auth_head($title, 'page-split');
?>
<main class="split">
  <section class="split-hero">
    <div class="split-orb" aria-hidden="true"></div>
    <div class="split-brand">
      <span class="split-brand-mark"><?= icon('tooth', 22) ?></span>
      <span class="split-brand-name">HealthTeeth</span>
    </div>
    <div class="split-copy">
      <h2><?php foreach ($headlineLines as $l): ?><span><?= e($l) ?></span><?php endforeach; ?></h2>
      <p><?php foreach ($subLines as $l): ?><span><?= e($l) ?></span><?php endforeach; ?></p>
    </div>
    <div class="split-art" aria-hidden="true">
      <svg class="split-hearts" viewBox="0 0 100 100" preserveAspectRatio="none">
        <path d="M14 18c-3-4-9-1-7 4 1.500 3 7 7 7 7s5.500-4 7-7c2-5-4-8-7-4z" fill="#fff" opacity=".28" transform="translate(2 0)"/>
        <path d="M30 28c-1.500-2-4.500-.5-3.500 2 .8 1.500 3.500 3.500 3.500 3.500s2.700-2 3.500-3.500c1-2.500-2-4-3.500-2z" fill="#fff" opacity=".35"/>
      </svg>
      <img src="assets/img/mascot.png" alt="" width="470" height="458">
<?php if ($art === 'shield'): ?>
      <svg class="split-shield" viewBox="0 0 100 118" aria-hidden="true">
        <defs>
          <linearGradient id="shield-fill" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#5aa9ff"/><stop offset="1" stop-color="#1f66e8"/>
          </linearGradient>
        </defs>
        <path d="M50 4 92 19v40c0 28-19 48-42 55C27 107 8 87 8 59V19z" fill="url(#shield-fill)" stroke="#d9ecff" stroke-width="5" stroke-linejoin="round"/>
        <path d="M33 34c-6 0-10 4-10 10 0 7 4 12 5 20 .7 5 1.500 10 5 10 3 0 3-8 5-12 2 4 2 12 5 12 3.500 0 4.300-5 5-10 1-8 5-13 5-20 0-6-4-10-10-10-4 0-6 2-8 2s-6-2-7-2z" transform="translate(5 0) scale(.95)" fill="#fff"/>
      </svg>
<?php endif; ?>
    </div>
  </section>
  <section class="split-form">
    <svg class="split-form-tooth" viewBox="0 0 48 56" aria-hidden="true">
      <path d="M12 4C8 4 6 7 6 11c0 9 2 19 4 29 1 7 4 12 8 12 3 0 4-2 5-6l1-4 1 4c1 4 2 6 5 6 4 0 7-5 8-12 2-10 4-20 4-29 0-4-2-7-6-7-3 0-6 2-9 2s-6-2-11-2z" fill="none" stroke="currentColor" stroke-width="2.200"/>
    </svg>
    <div class="split-content">
<?php
}

function render_split_end(array $scripts = []): void
{
?>
    </div>
  </section>
</main>
<?php auth_scripts(array_merge(['assets/js/ui.js'], $scripts)); ?>
</body>
</html>
<?php
}

function auth_scripts(array $scripts): void
{
    foreach ($scripts as $src): ?>
<script src="<?= e($src) ?>" defer></script>
<?php endforeach;
}
