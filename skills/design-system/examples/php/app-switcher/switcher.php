<?php
declare(strict_types=1);

/**
 * The application switcher — the Helpdesk button and the dropdown of the person's applications in every application's
 * header (K31, 2026-10-09; the kernel's docs/build-specs/kernel-app-switcher.md; the integration plugin's
 * sign-on-and-directory.md §8). One file for both shapes of application: an adopted one (`app/os.php`: os_enabled(),
 * os_launcher_url(), kernel_call(), current_user()['os_member_id']) and a kit one (env('OS_APPLICATION_TOKEN'),
 * env('OS_LAUNCHER_URL'), kernel_call(), current_member_id() — the kernel's member id IS the member id). Required by the
 * bootstrap after the OS helpers; rendered by app/views/shared/app-switcher.php.
 */

/** The kernel's id of the signed-in person, or 0 when there is none (standalone, anonymous, an agent's token). */
function os_switcher_member_id(): int
{
    if (function_exists('current_user')) {
        return (int) (current_user()['os_member_id'] ?? 0);
    }
    if (function_exists('current_member_id')) {
        return (int) (current_member_id() ?? 0);
    }
    return 0;
}

/** Is this installation under the kernel? An adopted application says so with OS_ENABLED; a kit one is only ever under it. */
function os_switcher_enabled(): bool
{
    if (function_exists('os_enabled')) {
        return os_enabled();
    }
    return (string) env('OS_APPLICATION_TOKEN', '') !== '';
}

/** The launcher's address (OS_LAUNCHER_URL, the installer's — scheme and all), with a trailing slash. */
function os_switcher_launcher_url(): string
{
    $url = function_exists('os_launcher_url') ? os_launcher_url() : (string) env('OS_LAUNCHER_URL', '');
    return $url === '' ? '' : rtrim($url, '/') . '/';
}

/** A launch path from the kernel's feed (`/launch/<id>`, `?scope=<id>`) on the launcher's address. */
function os_launch_href(string $path): string
{
    return os_switcher_launcher_url() . ltrim($path, '/');
}

/**
 * The applications the signed-in person may open, as the kernel's launcher would list them (GET /api/v1/apps/mine.php as
 * this person): {launcher_url, os_url, applications[{id, key, name, icon, business_area, current, launch_path, scopes[{id,
 * name, role, launch_path}]}]}. Cached in the session for five minutes (a fresh hand-off starts a fresh session, so a new
 * grant shows at the next sign-on at the latest); a kernel that does not answer leaves the last answer in place and is
 * asked again after a minute. Null standalone, for a person the kernel does not know, or before the first answer.
 */
function os_my_applications(bool $refresh = false): ?array
{
    if (!os_switcher_enabled()) {
        return null;
    }
    $memberId = os_switcher_member_id();
    if ($memberId <= 0) {
        return null;
    }
    $cached = $_SESSION['os_apps'] ?? null;
    if (is_array($cached) && (int) ($cached['member'] ?? 0) === $memberId) {
        $fresh = ($cached['at'] ?? 0) > time() - (($cached['feed'] ?? null) === null ? 60 : 300);
        if (!$refresh && $fresh) {
            return $cached['feed'];
        }
    } else {
        $cached = null;
    }
    $answer = kernel_call('GET', '/api/v1/apps/mine.php', null, ['X-Acting-Member: ' . $memberId], 5);
    $feed = $answer !== null && ($answer['status'] ?? 0) === 200 && is_array($answer['body'] ?? null) ? ($answer['body']['data'] ?? $answer['body']) : null;
    if (!is_array($feed) || !isset($feed['applications'])) {
        $_SESSION['os_apps'] = ['member' => $memberId, 'at' => time(), 'feed' => $cached['feed'] ?? null];
        return $cached['feed'] ?? null;
    }
    $_SESSION['os_apps'] = ['member' => $memberId, 'at' => time(), 'feed' => $feed];
    return $feed;
}
