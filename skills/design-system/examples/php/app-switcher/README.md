# The application switcher — the PHP side, verbatim from the exemplar (ProcessCore, 2026-10-09)

The Helpdesk button and the dropdown of every application the person may open, in `header-right` before the dark-mode
toggle (the kernel's `docs/build-specs/kernel-app-switcher.md`; the integration plugin's `sign-on-and-directory.md` §8).

| File here | Where it goes | What it is |
|---|---|---|
| `switcher.php` | `app/switcher.php`, required by the bootstrap after the OS helpers | `os_my_applications()` (the kernel's `GET /api/v1/apps/mine.php` as this person, cached five minutes in the session), `os_launch_href()`, the shape-agnostic `os_switcher_*()` helpers — one file for an adopted application (`os_enabled()`, `current_user()['os_member_id']`) and a kit one (`env('OS_APPLICATION_TOKEN')`, `current_member_id()`) |
| `app-switcher.php` | `app/views/shared/app-switcher.php`, rendered by the layout: `<?= view('shared/app-switcher.php') ?>` before `div.nxl-h-item.dark-light-theme` | the partial: the button, the menu, nothing standalone |
| `prove-switcher.sh` + `prove-switcher.php` | `scripts/` | the proof: the standalone shell carries nothing; in process against the real kernel with the install's token the feed arrives, caches, marks this application current, renders every row and scope, the foot, the two hidden-button cases |

The CSS is the APPLICATION SWITCHER section of `examples/assets/css/app-overrides.css`; the markup is also in `examples/index.html`
and `references/layout-skeleton.html`. The kit applications' ad-hoc "All applications" icon (`header-launcher-link`) is replaced by this.
