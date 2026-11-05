<?php
// Invitations for admin-created accounts (dentist, staff).
// The emailed link carries a random token; only its SHA-256 hash is stored.
// Opening a valid link starts the same verified-reset session the PIN flow uses,
// so the existing reset_password.php page sets the password.

/** Latest invitation state for a user: 'pending' | 'expired' | 'done' | null (never invited). */
function invite_state(int $userId): ?string
{
    $stmt = db()->prepare(
        "SELECT CASE WHEN used_at IS NOT NULL THEN 'done'
                     WHEN expires_at <= NOW() THEN 'expired'
                     ELSE 'pending' END
         FROM account_invites WHERE user_id = ? ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$userId]);
    $state = $stmt->fetchColumn();
    return $state === false ? null : (string) $state;
}

/**
 * Creates a fresh invitation (replacing any earlier one) and emails it.
 * Returns 'sent' | 'outbox' | 'throttled' | 'failed'.
 */
function issue_invite(int $userId): string
{
    $cfg = app_config();
    $pdo = db();

    $stmt = $pdo->prepare("SELECT full_name, email, role FROM users WHERE id = ? AND role IN ('dentist','staff') LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        return 'failed';
    }

    $recent = $pdo->prepare('SELECT COUNT(*) FROM account_invites WHERE user_id = ? AND created_at > NOW() - INTERVAL ? SECOND');
    $recent->bindValue(1, $userId, PDO::PARAM_INT);
    $recent->bindValue(2, (int) $cfg['invite']['resend_cooldown'], PDO::PARAM_INT);
    $recent->execute();
    if ((int) $recent->fetchColumn() > 0) {
        return 'throttled';
    }

    $token    = bin2hex(random_bytes(32));
    $inviteId = 0;

    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE account_invites SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')->execute([$userId]);

        $insert = $pdo->prepare('INSERT INTO account_invites (user_id, token_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL ? SECOND)');
        $insert->bindValue(1, $userId, PDO::PARAM_INT);
        $insert->bindValue(2, hash('sha256', $token));
        $insert->bindValue(3, (int) $cfg['invite']['ttl_seconds'], PDO::PARAM_INT);
        $insert->execute();
        $inviteId = (int) $pdo->lastInsertId();

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('issue_invite: ' . $e->getMessage());
        return 'failed';
    }

    $link   = rtrim($cfg['app']['base_url'], '/') . '/set_password.php?token=' . $token;
    $label  = $user['role'] === 'dentist' ? 'Dentist' : 'Staff';
    $result = send_invite_email($user['email'], $user['full_name'] ?: $user['email'], $label, $link, (int) ceil($cfg['invite']['ttl_seconds'] / 3600));

    if ($result === 'failed') {
        // Do not leave a live link the person never received (shows as "expired" so the admin can resend).
        $pdo->prepare('UPDATE account_invites SET expires_at = NOW() WHERE id = ?')->execute([$inviteId]);
        error_log("issue_invite: mail delivery failed for user {$userId}");
    }
    return $result;
}

/** Returns ['id', 'user_id'] for a usable invitation token, or null. */
function find_valid_invite(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $stmt = db()->prepare(
        "SELECT i.id, i.user_id
         FROM account_invites i
         JOIN users u ON u.id = i.user_id
         WHERE i.token_hash = ? AND i.used_at IS NULL AND i.expires_at > NOW() AND u.status = 'active'
         LIMIT 1"
    );
    $stmt->execute([hash('sha256', $token)]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Turns a valid invitation into the verified-reset session that reset_password.php expects.
 * The invitation itself stays valid until the password is set (or it expires).
 */
function start_invite_session(int $userId): void
{
    $pdo   = db();
    $token = bin2hex(random_bytes(32));

    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE password_resets SET used_at = COALESCE(used_at, NOW()), auth_token_hash = NULL, auth_expires_at = NULL WHERE user_id = ?')
            ->execute([$userId]);

        $insert = $pdo->prepare(
            'INSERT INTO password_resets (user_id, pin_hash, expires_at, used_at, verified_at, auth_token_hash, auth_expires_at)
             VALUES (?, ?, NOW(), NOW(), NOW(), ?, NOW() + INTERVAL ? SECOND)'
        );
        $insert->bindValue(1, $userId, PDO::PARAM_INT);
        $insert->bindValue(2, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)); // no PIN exists for invitations
        $insert->bindValue(3, hash('sha256', $token));
        $insert->bindValue(4, (int) app_config()['reset']['auth_ttl_seconds'], PDO::PARAM_INT);
        $insert->execute();
        $resetId = (int) $pdo->lastInsertId();

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    session_regenerate_id(true);
    $_SESSION['reset_auth'] = ['reset_id' => $resetId, 'user_id' => $userId, 'token' => $token];
}
