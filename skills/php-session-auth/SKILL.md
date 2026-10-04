---
name: php-session-auth
description: Use when building login, registration, logout, or session handling in vanilla PHP (no framework) with CSRF-protected forms — covers password hashing, secure session config, session fixation, CSRF tokens, brute-force lockout, and user-enumeration defense
---

> **Scope (2026-10-04):** this skill is for a **standalone product** — one that signs people in itself. An application of
> the MaluDB Business OS has **no login form, no password and no account of its own**: the kernel signs people in with a
> hand-off token (`os-application` skill; `maludb-os-integration` → `php-sign-on-kit.md`). A product that must do both
> keeps everything here behind `OS_ENABLED=0` and sends every path into a session to the kernel's launcher when the flag is on.

# Vanilla PHP Session Auth + CSRF

## Overview

Building session auth in vanilla PHP is mostly solved by `password_hash`, `hash_equals`, and `session_regenerate_id` — agents apply those reliably. **The failures are judgment calls this skill exists to fix:** missing brute-force protection, un-normalized emails, CSRF guards that only cover POST, and "hardening" that is actually an anti-pattern.

**Core principle:** the cryptography is the easy part. The security comes from the policy around it — rate limits, normalization, what you choose *not* to do.

## When to Use

- Implementing login / registration / logout in PHP without a framework
- Adding CSRF protection to state-changing forms
- Reviewing hand-rolled PHP auth for security gaps

When NOT to use: Laravel/Symfony (their auth + CSRF are built in — use those, don't hand-roll).

## Non-Negotiables (checklist)

| Requirement | Rule |
|---|---|
| Password storage | `password_hash($pw, PASSWORD_DEFAULT)` + `password_verify`. Re-hash on login via `password_needs_rehash`. |
| Password length | Min 12 chars; **max 72 bytes** (bcrypt silently truncates past 72). |
| Email | **Normalize: `strtolower(trim($email))`** before BOTH insert and lookup. Enforce a `UNIQUE` index and catch SQLSTATE `23000`. |
| Session login | `session_regenerate_id(true)` on every privilege change (login, logout, role change). |
| CSRF token | Per-session, `bin2hex(random_bytes(32))`, compared with `hash_equals`. Rotate on login. |
| CSRF guard | Validate on **every unsafe method** (POST, PUT, PATCH, DELETE) — not POST alone. |
| Brute force | **Rate-limit + lock** login attempts per account AND per IP. Non-optional. |
| Enumeration | Generic errors on login AND register; equalize timing with a precomputed dummy hash. |
| Cookies | `HttpOnly`, `Secure` (in prod), `SameSite=Lax`. |
| Headers | On auth pages: `Cache-Control: no-store`, `X-Frame-Options: DENY`, `Referrer-Policy: same-origin`. |

## Correct Patterns

**Session bootstrap (before `session_start()`):**
```php
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'secure' => $isHttps,
    'httponly' => true, 'samesite' => 'Lax',
]);
ini_set('session.use_strict_mode', '1');   // reject attacker-supplied SIDs
ini_set('session.use_only_cookies', '1');
session_name('SID');
session_start();
```

**CSRF — guard ALL unsafe methods, not just POST:**
```php
function require_csrf(): void {
    $unsafe = ['POST', 'PUT', 'PATCH', 'DELETE'];
    if (!in_array($_SERVER['REQUEST_METHOD'], $unsafe, true)) return;
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');  // form OR fetch header
    if (!hash_equals($_SESSION['csrf_token'] ?? '', (string) $sent)) {
        http_response_code(403); exit('CSRF validation failed.');
    }
}
```

**Login: normalize email, throttle, equalize timing, regenerate id:**
```php
$email = strtolower(trim((string)($_POST['email'] ?? '')));   // normalize BEFORE lookup
if (too_many_attempts($email, $_SERVER['REMOTE_ADDR'])) {     // your store: DB/Redis/APCu
    http_response_code(429); $error = 'Too many attempts. Try again later.'; 
} else {
    $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE email=:e LIMIT 1');
    $stmt->execute([':e' => $email]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    // Precomputed at the SAME cost as real hashes so timing matches; verify even when missing.
    $dummy = '$2y$12$........................................................';
    $ok = password_verify($password, $u['password_hash'] ?? $dummy) && $u;
    record_attempt($email, $_SERVER['REMOTE_ADDR'], (bool)$ok);  // log + count
    if ($ok) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$u['id'];
        unset($_SESSION['csrf_token']);                          // mint fresh token post-login
        header('Location: /dashboard.php'); exit;
    }
    $error = 'Invalid email or password.';                       // generic — never which field
}
```

## Common Mistakes (the ones agents actually make)

| Mistake | Why it's wrong | Fix |
|---|---|---|
| No rate limiting | Login is an open brute-force oracle. | Lock per-account + per-IP; return 429; log failures. |
| `trim()` but no `strtolower()` on email | `User@x.com` and `user@x.com` become two accounts; users can't log in. | Normalize to lowercase before insert AND lookup. |
| CSRF guard checks `=== 'POST'` only | PUT/PATCH/DELETE bypass it entirely. | Guard all unsafe verbs (see pattern above). |
| Binding session to User-Agent / IP | False logouts on browser/network change; weak defense (attacker replays UA). | Don't. Rely on `regenerate_id`, `HttpOnly`, `Secure`, `SameSite`. |
| Treating `SameSite=Lax` as the whole CSRF defense | Not honored by old browsers; top-level GET state-changes slip through. | SameSite is defense-in-depth; keep the token. |
| Dummy hash at wrong cost (`$2y$10$`) | Timing differs from cost-12 real hashes → enumeration leak. | Precompute dummy at the exact cost your `PASSWORD_DEFAULT` uses. |
| GET logout link | The link itself is a CSRF vector (`<img src=/logout>`). | Logout must be POST + CSRF-guarded. |
| No security headers on auth pages | Authed pages cached; clickjacking enables UI-redress CSRF. | `Cache-Control: no-store`, `X-Frame-Options: DENY`. |

## Red Flags — stop and fix

- A login handler with no attempt counter anywhere
- Email used in a query without `strtolower`
- `if ($_SERVER['REQUEST_METHOD'] === 'POST')` as the only CSRF gate
- Any `$_SESSION['ua_hash']` / IP-pinning "hardening"
- A logout reachable by GET

## CSRF over HTMX (plugin addition — the default wiring in every app)

CSRF is the default across every page, including every HTMX request. The standard wiring:

1. The shell's `<head>` renders `<meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" id="csrf-token-meta">`.
2. One listener in the shell attaches the token to every non-GET HTMX request:

```html
<script>
document.body.addEventListener('htmx:configRequest', (e) => {
    if (e.detail.verb !== 'get') {
        e.detail.headers['X-CSRF-Token'] =
            document.querySelector('#csrf-token-meta').content;
    }
});
</script>
```

3. The server-side guard accepts the token from the `X-CSRF-Token` header **or** the hidden form field, on all unsafe verbs, compared with `hash_equals`.
4. Token rotation stays login-time only. Login/logout are full page navigations (never HTMX swaps), so the fresh token always reaches the meta tag with the new page; rotating mid-session would strand swapped-in partials with a stale header source.
5. Non-HTMX forms (login itself, anything full-page) keep the classic hidden input.

## Beyond passwords (plugin additions)

- [references/google-signin.md](references/google-signin.md) — "Sign in with Google" via server-side OIDC: flow, ID-token validation, the `auth_identities` table, account-linking rules, and the nullable-`password_hash` consequence.
- [references/totp-2fa.md](references/totp-2fa.md) — authenticator-app 2FA: enrollment, the pending-2FA login state, recovery codes, replay/rate-limit rules.

Both paths converge on the same session establishment: `session_regenerate_id(true)`, set `user_id`, mint a fresh CSRF token — no method skips it, and enabled 2FA applies to Google sign-ins exactly as to passwords.
