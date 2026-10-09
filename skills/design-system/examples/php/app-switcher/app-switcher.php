<?php
/**
 * The application switcher (K31; the design-system's "Header: the application switcher"): in `header-right`, before the
 * dark-mode toggle — the Helpdesk button (when the person holds the Help Desk and this is not it) and the dropdown of every
 * application the person may open, the current one marked, the launcher and (a super-admin) the operating system at the
 * foot. Every link leaves this application, so none is an HTMX swap. Renders nothing standalone or before the kernel answered.
 * @var ?array $feed  os_my_applications()'s answer (looked up when absent)
 */
$feed = $feed ?? os_my_applications();   // app/switcher.php
if (!is_array($feed) || empty($feed['applications'])) {
    return;
}
$apps = $feed['applications'];
$helpdesk = null;
foreach ($apps as $a) {
    if (($a['key'] ?? '') === 'helpdesk' && empty($a['current'])) {
        $helpdesk = $a;
    }
}
$item = static function (array $a, ?array $scope = null): string {
    $id = 'header-apps-item-' . (int) $a['id'] . ($scope !== null ? '-' . (int) $scope['id'] : '');
    $label = e($a['name']) . ($scope !== null ? ' <span class="text-muted">· ' . e($scope['name']) . '</span>' : '');
    $icon = '<i class="' . e($a['icon'] ?? 'feather-grid') . '"></i>';
    if (!empty($a['current'])) {            // this application — every row of it, a scoped one's sites too (its own site switcher changes the site)
        return '<span class="dropdown-item active" id="' . $id . '" aria-current="page">' . $icon . '<span>' . $label . '</span></span>';
    }
    return '<a href="' . e(os_launch_href(($scope ?? $a)['launch_path'])) . '" class="dropdown-item" id="' . $id . '">' . $icon . '<span>' . $label . '</span></a>';
};
?>
<?php if ($helpdesk !== null): ?>
<div class="nxl-h-item">
    <a href="<?= e(os_launch_href($helpdesk['launch_path'])) ?>" class="nxl-head-link me-0 header-helpdesk" id="header-helpdesk-btn" title="Help Desk"><i class="<?= e($helpdesk['icon'] ?? 'feather-life-buoy') ?>"></i><span class="d-none d-sm-inline">Helpdesk</span></a>
</div>
<?php endif; ?>
<div class="dropdown nxl-h-item">
    <a href="javascript:void(0);" class="nxl-head-link me-0" data-bs-toggle="dropdown" role="button" data-bs-auto-close="outside" id="header-apps-toggle" aria-label="Your applications" title="Your applications"><i class="feather-grid"></i></a>
    <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown header-apps-menu" id="header-apps-menu">
        <div class="dropdown-header"><h6 class="text-dark mb-0">Your applications</h6></div>
        <div class="dropdown-divider"></div>
        <div class="header-apps-list" id="header-apps-list">
        <?php foreach ($apps as $a): ?>
            <?php if (!empty($a['scopes'])): foreach ($a['scopes'] as $s): ?><?= $item($a, $s) ?><?php endforeach; else: ?><?= $item($a) ?><?php endif; ?>
        <?php endforeach; ?>
        </div>
        <div class="dropdown-divider"></div>
        <a href="<?= e(os_switcher_launcher_url()) ?>" class="dropdown-item" id="header-apps-launcher"><i class="feather-layout"></i><span>All applications</span></a>
        <?php if (!empty($feed['os_url'])): ?><a href="<?= e($feed['os_url']) ?>" class="dropdown-item" id="header-apps-os"><i class="feather-cpu"></i><span>Operating system</span></a><?php endif; ?>
    </div>
</div>
