<?php
/**
 * AutoBacklink - API Profiles
 * Multiple named Chat / Image API configurations. Each backlink website can
 * pick its own chat + image profile (empty = the default from the API Keys panel).
 * Stored in app_data: chat_profiles / image_profiles (JSON arrays).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

function bkMaskKey($key) {
    $key = (string)$key;
    if ($key === '') return '';
    if (strlen($key) <= 8) return str_repeat('•', strlen($key));
    return substr($key, 0, 4) . '••••••••' . substr($key, -4);
}

/** List profiles (masked) + the default profile's status. $kind = chat|image */
function bkListProfiles($kind) {
    $raw = json_decode((string)getData($kind . '_profiles', '[]'), true) ?: [];
    $profiles = [];
    foreach ($raw as $p) {
        if (!is_array($p) || empty($p['id'])) continue;
        $profiles[] = [
            'id' => $p['id'],
            'name' => $p['name'] ?? $p['id'],
            'provider' => $p['provider'] ?? 'custom',
            'model' => $p['model'] ?? '',
            'key_masked' => bkMaskKey($p['api_key'] ?? ''),
        ];
    }
    $def = json_decode((string)getData('api_' . $kind, '{}'), true) ?: [];
    return [
        'default' => [
            'configured' => !empty($def['api_key']),
            'provider' => $def['provider'] ?? 'custom',
            'model' => $def['model'] ?? '',
            'key_masked' => bkMaskKey($def['api_key'] ?? ''),
        ],
        'profiles' => $profiles,
    ];
}

/** Save (create or update) a profile. Returns ['id' => ...]. */
function bkSaveProfile($kind, array $data) {
    $raw = json_decode((string)getData($kind . '_profiles', '[]'), true) ?: [];
    $id = trim((string)($data['id'] ?? ''));
    if ($id === '') $id = 'p' . substr(md5(uniqid((string)microtime(), true)), 0, 8);
    $profile = [
        'id' => $id,
        'name' => trim((string)($data['name'] ?? '')) ?: $id,
        'provider' => trim((string)($data['provider'] ?? 'custom')) ?: 'custom',
        'api_key' => (string)($data['api_key'] ?? ''),
        'model' => trim((string)($data['model'] ?? '')),
        'endpoint' => trim((string)($data['endpoint'] ?? '')),
    ];
    $found = false;
    foreach ($raw as $i => $p) {
        if (is_array($p) && ($p['id'] ?? '') === $id) { $raw[$i] = $profile; $found = true; break; }
    }
    if (!$found) $raw[] = $profile;
    putData($kind . '_profiles', json_encode($raw));
    return ['id' => $id];
}

function bkDeleteProfile($kind, $id) {
    $raw = json_decode((string)getData($kind . '_profiles', '[]'), true) ?: [];
    $raw = array_values(array_filter($raw, fn($p) => is_array($p) && ($p['id'] ?? '') !== $id));
    putData($kind . '_profiles', json_encode($raw));
    // un-select it on any target that was using it
    $db = getDB();
    $col = $kind . '_profile';
    $db->exec("UPDATE targets SET $col = '' WHERE $col = " . $db->quote($id));
    return true;
}

/**
 * Resolve the credentials a target should use.
 * $profileId = '' → default (the API Keys panel's saved key).
 */
function bkResolveProfile($kind, $profileId) {
    $profileId = trim((string)$profileId);
    if ($profileId !== '') {
        $raw = json_decode((string)getData($kind . '_profiles', '[]'), true) ?: [];
        foreach ($raw as $p) {
            if (is_array($p) && ($p['id'] ?? '') === $profileId) return $p;
        }
    }
    return json_decode((string)getData('api_' . $kind, '{}'), true) ?: [];
}
