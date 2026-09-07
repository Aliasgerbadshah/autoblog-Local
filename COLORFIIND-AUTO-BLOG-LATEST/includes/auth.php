<?php
/**
 * AutoBlog SaaS - Security Vault (Authentication, OTP, Credentials)
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';

class SecurityVault {

    public static function registerUser($username, $email, $password) {
        $db = getDB();
        $now = nowString();
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        try {
            $db->beginTransaction();
            $stmt = $db->prepare('INSERT INTO users (username, email, password_hash, created_at) VALUES (?, ?, ?, ?)');
            $stmt->execute([$username, $email, $passwordHash, $now]);
            $userId = $db->lastInsertId();

            for ($slot = 1; $slot <= 5; $slot++) {
                $slotName = $slot > 1 ? "Site Slot #$slot" : "Primary Site Profile";
                $stmt = $db->prepare('INSERT INTO user_workspace_slots (user_id, slot_number, slot_name, created_at) VALUES (?, ?, ?, ?)');
                $stmt->execute([$userId, $slot, $slotName, $now]);
            }

            $db->commit();
            return ['success' => true, 'user_id' => $userId];
        } catch (PDOException $e) {
            $db->rollBack();
            if (strpos($e->getMessage(), 'username') !== false) {
                return ['success' => false, 'error' => 'Username already exists.'];
            }
            return ['success' => false, 'error' => 'Email address already registered.'];
        }
    }

    public static function authenticateCredentials($usernameOrEmail, $password) {
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $activeSlot = $user['active_slot_id'] ?? 1;
            return [
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'active_slot_id' => $activeSlot
                ]
            ];
        }
        return ['success' => false, 'error' => 'Invalid username/email or password.'];
    }

    public static function generateUniqueOtp($userId) {
        $db = getDB();
        $otpCode = generateOtp();
        $now = new DateTime();
        $expiresAt = (clone $now)->modify('+10 minutes')->format('Y-m-d H:i:s');
        $createdAt = $now->format('Y-m-d H:i:s');

        $stmt = $db->prepare('INSERT INTO email_otps (user_id, otp_code, expires_at, created_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userId, $otpCode, $expiresAt, $createdAt]);

        return $otpCode;
    }

    public static function sendBrevoOtpEmail($toEmail, $otpCode, $brevoApiKey = null, $senderEmail = null, $senderName = null) {
        $apiKey = $brevoApiKey ?: DEFAULT_BREVO_API_KEY;
        $sEmail = $senderEmail ?: DEFAULT_BREVO_SENDER_EMAIL;
        $sName = $senderName ?: DEFAULT_BREVO_SENDER_NAME;

        $htmlContent = <<<HTML
<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 28px; max-width: 520px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 16px; background: #ffffff; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
    <div style="text-align: center; margin-bottom: 20px;">
        <span style="font-size: 2.5rem;">🔒</span>
        <h2 style="color: #0f172a; font-size: 1.5rem; font-weight: 800; margin-top: 8px;">Login Security Code</h2>
    </div>
    <p style="color: #475569; font-size: 0.95rem; line-height: 1.5; text-align: center;">Use the following unique verification code to complete your workspace sign-in:</p>
    <div style="background: linear-gradient(135deg, #1b57f6, #3b82f6); color: #ffffff; font-size: 2.4rem; font-weight: 800; letter-spacing: 8px; padding: 18px; text-align: center; border-radius: 12px; margin: 24px 0; box-shadow: 0 4px 14px rgba(27, 87, 246, 0.3);">
        {$otpCode}
    </div>
    <p style="color: #64748b; font-size: 0.85rem; text-align: center; margin-bottom: 0;">This OTP is unique for this login session and valid for <strong>10 minutes</strong>. Never share this code with anyone.</p>
</div>
HTML;

        $payload = [
            'sender' => ['name' => $sName, 'email' => $sEmail],
            'to' => [['email' => $toEmail]],
            'subject' => "🔒 Your Security Login OTP: $otpCode",
            'htmlContent' => $htmlContent
        ];

        $result = curlPost('https://api.brevo.com/v3/smtp/email', $payload, [
            'api-key: ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ]);

        if ($result['success'] && in_array($result['http_code'], [200, 201, 202])) {
            return ['success' => true, 'mode' => 'brevo_live', 'message_id' => $result['data']['messageId'] ?? null];
        }
        $errorMsg = $result['data']['message'] ?? ($result['error'] ?? 'Unknown error');
        return ['success' => false, 'error' => "Brevo Error ({$result['http_code']}): $errorMsg"];
    }

    public static function verifyUserOtp($userId, $otpCode) {
        $db = getDB();
        $now = nowString();
        $stmt = $db->prepare('SELECT * FROM email_otps WHERE user_id = ? AND otp_code = ? AND is_used = 0 AND expires_at >= ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$userId, trim($otpCode), $now]);
        $row = $stmt->fetch();

        if ($row) {
            $stmt = $db->prepare('UPDATE email_otps SET is_used = 1 WHERE id = ?');
            $stmt->execute([$row['id']]);
            return ['success' => true];
        }
        return ['success' => false, 'error' => 'Invalid or expired OTP code. Please request a new code.'];
    }

    public static function saveApiCredentials($userId, $serviceName, $credentialsDict, $accountAlias = 'Primary Account') {
        $db = getDB();
        $now = nowString();
        $encodedData = base64_encode(json_encode($credentialsDict));

        $stmt = $db->prepare('SELECT id FROM user_credentials_vault WHERE user_id = ? AND service_name = ? AND account_alias = ?');
        $stmt->execute([$userId, $serviceName, $accountAlias]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $db->prepare('UPDATE user_credentials_vault SET account_alias = ?, credential_data = ?, updated_at = ? WHERE id = ?');
            $stmt->execute([$accountAlias, $encodedData, $now, $existing['id']]);
        } else {
            $stmt = $db->prepare('INSERT INTO user_credentials_vault (user_id, service_name, account_alias, credential_data, updated_at) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$userId, $serviceName, $accountAlias, $encodedData, $now]);
        }
        return ['success' => true];
    }

    public static function getServiceCredentials($userId, $serviceName) {
        $db = getDB();
        $stmt = $db->prepare('SELECT id, account_alias, credential_data, updated_at FROM user_credentials_vault WHERE user_id = ? AND service_name = ? ORDER BY id DESC');
        $stmt->execute([$userId, $serviceName]);
        $rows = $stmt->fetchAll();

        $accounts = [];
        foreach ($rows as $r) {
            $decoded = base64_decode($r['credential_data']);
            if ($decoded !== false) {
                $data = json_decode($decoded, true);
                if ($data !== null) {
                    $accounts[] = [
                        'id' => $r['id'],
                        'account_alias' => $r['account_alias'],
                        'data' => $data,
                        'updated_at' => $r['updated_at']
                    ];
                }
            }
        }
        return $accounts;
    }

    public static function getApiCredentials($userId, $serviceName, $accountAlias = null) {
        $accounts = self::getServiceCredentials($userId, $serviceName);
        if ($accountAlias) {
            foreach ($accounts as $account) {
                if ($account['account_alias'] === $accountAlias) {
                    return $account['data'];
                }
            }
        }
        return !empty($accounts) ? $accounts[0]['data'] : [];
    }

    public static function getApiCredentialsById($userId, $serviceName, $credentialId) {
        if (empty($credentialId)) return [];
        $accounts = self::getServiceCredentials($userId, $serviceName);
        foreach ($accounts as $account) {
            if (strval($account['id']) === strval($credentialId)) {
                return $account['data'];
            }
        }
        return [];
    }

    public static function getAllUserVaultSummary($userId) {
        $db = getDB();
        $stmt = $db->prepare('SELECT id, service_name, account_alias, updated_at FROM user_credentials_vault WHERE user_id = ? ORDER BY id DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function getUserWorkspaceSlots($userId) {
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM user_workspace_slots WHERE user_id = ? ORDER BY slot_number ASC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function switchActiveSlot($userId, $slotNumber) {
        $db = getDB();
        $stmt = $db->prepare('UPDATE users SET active_slot_id = ? WHERE id = ?');
        $stmt->execute([$slotNumber, $userId]);
        return ['success' => true, 'active_slot_number' => $slotNumber];
    }

    public static function getSlotDetails($userId, $slotNumber) {
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM user_workspace_slots WHERE user_id = ? AND slot_number = ?');
        $stmt->execute([$userId, $slotNumber]);
        $row = $stmt->fetch();
        if ($row) return $row;
        return [
            'slot_number' => $slotNumber,
            'slot_name' => "Slot #$slotNumber",
            'domain_url' => '',
            'target_goal' => 'Organic Search Traffic',
            'word_count_target' => '1500-2000',
            'destination_platform' => 'local'
        ];
    }

    public static function updateWorkspaceSlot($userId, $slotNumber, $slotName, $domainUrl, $targetGoal, $wordCountTarget = '1500-2000', $destinationPlatform = 'local') {
        $db = getDB();
        $now = nowString();

        // Try INSERT OR REPLACE
        $stmt = $db->prepare('SELECT id FROM user_workspace_slots WHERE user_id = ? AND slot_number = ?');
        $stmt->execute([$userId, $slotNumber]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $db->prepare('UPDATE user_workspace_slots SET slot_name = ?, domain_url = ?, target_goal = ?, word_count_target = ?, destination_platform = ? WHERE user_id = ? AND slot_number = ?');
            $stmt->execute([$slotName, $domainUrl, $targetGoal, $wordCountTarget, $destinationPlatform, $userId, $slotNumber]);
        } else {
            $stmt = $db->prepare('INSERT INTO user_workspace_slots (user_id, slot_number, slot_name, domain_url, target_goal, word_count_target, destination_platform, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$userId, $slotNumber, $slotName, $domainUrl, $targetGoal, $wordCountTarget, $destinationPlatform, $now]);
        }
        return ['success' => true];
    }
}
