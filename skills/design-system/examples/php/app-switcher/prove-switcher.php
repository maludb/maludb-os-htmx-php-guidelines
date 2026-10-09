<?php
declare(strict_types=1);
// The in-process half of scripts/prove-switcher.sh: OS mode forced on, the kernel asked as the first super-admin (a read with this
// checkout's application token), the partial rendered from the answer. Never writes anywhere.
putenv('OS_ENABLED=1'); $_ENV['OS_ENABLED'] = '1'; $_SERVER['OS_ENABLED'] = '1';
require_once dirname(__DIR__) . '/app/bootstrap.php';
$ok = static function (bool $c, string $l): void { echo ($c ? 'ok   ' : 'FAIL ') . $l . "\n"; };
$ok(os_enabled() && os_switcher_enabled(), 'OS mode is on for this process');
$pdo = db();
// the first super-admin the kernel knows, as this application's user row (any user row will do: only os_member_id matters)
$kernelMember = 1;
$pdo->exec('UPDATE app.users SET os_member_id = NULL, os_capability = NULL WHERE os_member_id = 1');   // an aborted earlier run
$uid = (int) $pdo->query("SELECT id FROM app.users WHERE role = 'owner' ORDER BY id LIMIT 1")->fetchColumn();
$pdo->prepare("UPDATE app.users SET os_member_id = :m, os_capability = 'admin' WHERE id = :id")->execute(['m' => $kernelMember, 'id' => $uid]);
$_SESSION['user_id'] = $uid;
os_session_open($pdo, $kernelMember, session_id());          // under the OS a session lives only while listed
$u = current_user();
$ok(($u['os_member_id'] ?? 0) == $kernelMember, 'the signed-in user carries the kernel member id');
$feed = os_my_applications(true);
$ok(is_array($feed) && !empty($feed['applications']), 'the kernel answered the feed (' . count($feed['applications'] ?? []) . ' applications)');
$ok(isset($_SESSION['os_apps']['feed']) && $_SESSION['os_apps']['at'] > time() - 5, 'the answer is cached in the session');
$again = os_my_applications();
$ok($again === $feed, 'a second call within five minutes is the cache');
$keys = array_column($feed['applications'] ?? [], 'key');
$ok(in_array(os_app_key(), $keys, true) && in_array('helpdesk', $keys, true), 'the feed names this application and helpdesk');
$current = array_values(array_filter($feed['applications'], static fn ($a) => !empty($a['current'])));
$ok(count($current) === 1 && $current[0]['key'] === os_app_key(), 'exactly one row is current: ' . os_app_key());
if ($current === []) { echo "FAIL no current row: the rest cannot run\n"; exit(1); }
$html = view('shared/app-switcher.php', ['feed' => $feed]);
$base = rtrim(os_launcher_url(), '/');
$hd = array_values(array_filter($feed['applications'], static fn ($a) => $a['key'] === 'helpdesk'))[0];
$ok(str_contains($html, 'id="header-helpdesk-btn"') && str_contains($html, 'href="' . $base . '/launch/' . $hd['id'] . '"'), 'the Helpdesk button links to the launcher\'s /launch/<helpdesk id>');
$ok(str_contains($html, 'id="header-apps-toggle"') && str_contains($html, 'id="header-apps-menu"'), 'the applications menu is there');
$ok(str_contains($html, '<span class="dropdown-item active" id="header-apps-item-' . $current[0]['id'] . '"'), 'the current application is marked and not a link');
foreach ($feed['applications'] as $a) {
    if (!empty($a['current'])) { continue; }
    if (!empty($a['scopes'])) {
        $s = $a['scopes'][0];
        $ok(str_contains($html, 'id="header-apps-item-' . $a['id'] . '-' . $s['id'] . '"') && str_contains($html, $base . '/launch/' . $a['id'] . '?scope=' . $s['id']), 'a scoped application has one row per scope with ?scope= (' . $a['name'] . ')');
    } else {
        $ok(str_contains($html, 'href="' . $base . '/launch/' . $a['id'] . '"'), 'a row links to /launch/<id> (' . $a['name'] . ')');
    }
}
$ok(str_contains($html, 'id="header-apps-launcher"') && str_contains($html, 'href="' . e(os_switcher_launcher_url()) . '"'), 'the foot links to the launcher');
$ok(empty($feed['os_url']) || str_contains($html, 'id="header-apps-os"'), 'the operating system link shows for a super-admin');
$ok(!preg_match('/hx-(get|post)=/', $html), 'no link is an HTMX swap');
$asHelpdesk = $feed; foreach ($asHelpdesk['applications'] as $i => $a) { $asHelpdesk['applications'][$i]['current'] = $a['key'] === 'helpdesk'; }
$ok(!str_contains(view('shared/app-switcher.php', ['feed' => $asHelpdesk]), 'header-helpdesk-btn'), 'inside the Help Desk the button is not shown');
$noHd = $feed; $noHd['applications'] = array_values(array_filter($noHd['applications'], static fn ($a) => $a['key'] !== 'helpdesk'));
$ok(!str_contains(view('shared/app-switcher.php', ['feed' => $noHd]), 'header-helpdesk-btn'), 'a person without the Help Desk sees no button');
$ok(trim(view('shared/app-switcher.php', ['feed' => ['applications' => []]])) === '', 'an empty feed renders nothing');
$pdo->prepare('UPDATE app.users SET os_member_id = NULL, os_capability = NULL WHERE id = :id')->execute(['id' => $uid]);
os_end_session($pdo, session_id(), 'member');
