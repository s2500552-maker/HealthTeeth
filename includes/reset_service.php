<?php
// Password-reset rules: PIN issuing, PIN verification, reset authorization, password update.
// Every query uses PDO prepared statements. Secrets are stored only as hashes.

function find_user_id_by_email(string $email): ?int
{
    $stmt = db()->prepare('SELECT id FROM users WHERE email = ? AND status = \'active\' LIMIT 1');
    $stmt->execute([$email]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
}

/** Seconds until another code may be requested (0 = allowed now). */
function resend_cooldown_left(): int
{
    return max(0, (int) ($_SESSION['resend_available_at'] ?? 0) - time());
}

function start_resend_cooldown(): void
{
    $_SESSION['resend_available_at'] = time() + app_config()['pin']['resend_cooldown'];
}

/**
 * Issues a new PIN for the account with this email, if one exists.
 * Returns 'sent' | 'outbox' | 'skipped' | 'failed'.
 * 'skipped' covers both "no such account" and "rate-limited", so callers can
 * show the same generic message and never reveal which emails are registered.
 */
function issue_reset_pin(string $email): string
{
    $cfg    = app_config()['pin'];
    $userId = find_user_id_by_email($email);
    if ($userId === null) {
        return 'skipped';
    }

    $pdo = db();

    $limits = $pdo->prepare(
        'SELECT COUNT(*) AS sent_last_hour,
                COALESCE(SUM(created_at > NOW() - INTERVAL :cooldown SECOND), 0) AS sent_recently
         FROM password_resets
         WHERE user_id = :uid AND created_at > NOW() - INTERVAL 1 HOUR'
    );
    $limits->bindValue(':cooldown', $cfg['resend_cooldown'], PDO::PARAM_INT);
    $limits->bindValue(':uid', $userId, PDO::PARAM_INT);
    $limits->execute();
    $counts = $limits->fetch();

    if ((int) $counts['sent_recently'] > 0 || (int) $counts['sent_last_hour'] >= $cfg['max_sends_per_hour']) {
        return 'skipped';
    }

    // 6-digit PIN from a CSPRNG; only its hash is stored.
    $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    $pdo->beginTransaction();
    try {
        // Invalidate any earlier unused PIN for this account.
        $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')
            ->execute([$userId]);

        $insert = $pdo->prepare(
            'INSERT INTO password_resets (user_id, pin_hash, expires_at)
             VALUES (?, ?, NOW() + INTERVAL ? SECOND)'
        );
        $insert->bindValue(1, $userId, PDO::PARAM_INT);
        $insert->bindValue(2, password_hash($pin, PASSWORD_DEFAULT));
        $insert->bindValue(3, $cfg['ttl_seconds'], PDO::PARAM_INT);
        $insert->execute();

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('issue_reset_pin: ' . $e->getMessage());
        return 'failed';
    }

    $result = send_pin_email($email, $pin, (int) ceil($cfg['ttl_seconds'] / 60));

    if ($result === 'failed') {
        // Do not leave an active PIN the user never received.
        $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')
            ->execute([$userId]);
        error_log("issue_reset_pin: mail delivery failed for user {$userId}");
    }

    return $result;
}

/**
 * Checks a submitted PIN.
 * Returns ['status' => 'ok', 'reset_id', 'user_id', 'token'] on success,
 * otherwise ['status' => 'invalid' | 'expired' | 'locked'].
 */
function verify_reset_pin(string $email, string $pin): array
{
    $cfg = app_config()['pin'];
    $pdo = db();

    $pdo->beginTransaction();
    try {
        // Only the most recent unused PIN can be verified; used or replaced PINs never match.
        $stmt = $pdo->prepare(
            'SELECT r.id, r.user_id, r.pin_hash, r.attempts,
                    (r.expires_at <= NOW()) AS is_expired
             FROM password_resets r
             JOIN users u ON u.id = r.user_id
             WHERE u.email = ? AND r.used_at IS NULL
             ORDER BY r.id DESC
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        if (!$row) {
            $pdo->rollBack();
            return ['status' => 'invalid'];
        }
        if ((int) $row['is_expired'] === 1) {
            $pdo->rollBack();
            return ['status' => 'expired'];
        }
        if ((int) $row['attempts'] >= $cfg['max_attempts']) {
            $pdo->rollBack();
            return ['status' => 'locked'];
        }

        if (!password_verify($pin, $row['pin_hash'])) {
            $attempts = (int) $row['attempts'] + 1;
            $pdo->prepare('UPDATE password_resets SET attempts = ? WHERE id = ?')
                ->execute([$attempts, $row['id']]);
            $pdo->commit();
            return ['status' => $attempts >= $cfg['max_attempts'] ? 'locked' : 'invalid'];
        }

        // Correct PIN: consume it and issue a short-lived server-side authorization token.
        $token  = bin2hex(random_bytes(32));
        $update = $pdo->prepare(
            'UPDATE password_resets
             SET used_at = NOW(), verified_at = NOW(),
                 auth_token_hash = ?, auth_expires_at = NOW() + INTERVAL ? SECOND
             WHERE id = ?'
        );
        $update->bindValue(1, hash('sha256', $token));
        $update->bindValue(2, app_config()['reset']['auth_ttl_seconds'], PDO::PARAM_INT);
        $update->bindValue(3, $row['id'], PDO::PARAM_INT);
        $update->execute();

        $pdo->commit();

        return [
            'status'   => 'ok',
            'reset_id' => (int) $row['id'],
            'user_id'  => (int) $row['user_id'],
            'token'    => $token,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('verify_reset_pin: ' . $e->getMessage());
        return ['status' => 'invalid'];
    }
}

/**
 * Confirms that the session's reset authorization is genuine and unexpired.
 * The session holds only a random token; the database holds its SHA-256 hash.
 */
function find_active_reset_auth(array $auth): ?array
{
    if (empty($auth['reset_id']) || empty($auth['user_id']) || empty($auth['token'])) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT id, user_id, auth_token_hash
         FROM password_resets
         WHERE id = ? AND user_id = ? AND auth_token_hash IS NOT NULL AND auth_expires_at > NOW()
         LIMIT 1'
    );
    $stmt->execute([(int) $auth['reset_id'], (int) $auth['user_id']]);
    $row = $stmt->fetch();

    if (!$row || !hash_equals($row['auth_token_hash'], hash('sha256', (string) $auth['token']))) {
        return null;
    }
    return $row;
}

/** Stores the new hashed password and invalidates every reset PIN and authorization for the account. */
function complete_password_reset(int $userId, string $newPassword): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
            ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);

        $pdo->prepare(
            'UPDATE password_resets
             SET used_at = COALESCE(used_at, NOW()), auth_token_hash = NULL, auth_expires_at = NULL
             WHERE user_id = ?'
        )->execute([$userId]);

        // An invitation link is single-use: once a password is set, it can no longer be opened.
        $pdo->prepare('UPDATE account_invites SET used_at = COALESCE(used_at, NOW()) WHERE user_id = ?')
            ->execute([$userId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Server-side password policy (JavaScript mirrors it for live feedback, but this is authoritative).
 * Returns at most one error, the most relevant one, keyed by field.
 */
function validate_new_password(string $password, string $confirm): array
{
    if (strlen($password) < 8) {
        return ['password' => 'Password must be at least 8 characters.'];
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return ['password' => 'Your password must contain an uppercase letter.'];
    }
    if (!preg_match('/[a-z]/', $password)) {
        return ['password' => 'Your password must contain a lowercase letter.'];
    }
    if (!preg_match('/\d/', $password)) {
        return ['password' => 'Your password must contain a number.'];
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return ['password' => 'Your password must contain a special character.'];
    }
    if (!hash_equals($password, $confirm)) {
        return ['confirm' => 'Passwords do not match.'];
    }
    return [];
}
