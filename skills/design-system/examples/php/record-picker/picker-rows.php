<?php
/**
 * The record picker's rows (app/picker.php; assets/js/record-picker.js swaps this into #record-picker-results).
 * @var ?string $error  @var string $source  @var string $q  @var array $params  @var ?int $selected  @var string $noun
 * @var array $result  rows [id, label, detail?, badge?, color?], total, page, pages
 * The first page renders the list; a later page (the "Show more" button's answer) renders only its rows and the next button,
 * which replace the button that asked.
 */
$error = $error ?? null;
if ($error !== null): ?>
<div class="record-picker-empty p-4 text-center text-muted" id="record-picker-error"><?= e($error) ?></div>
<?php return; endif;
$rows = $result['rows'];
$firstPage = (int) $result['page'] <= 1;
$shown = min((int) $result['total'], (int) $result['page'] * (int) $result['page_size']);
if ($firstPage && $rows === []): ?>
<div class="record-picker-empty p-4 text-center text-muted" id="record-picker-empty"><?= $q === '' ? 'Nothing to choose from yet.' : 'No ' . e($noun) . ' matches “' . e($q) . '”.' ?></div>
<?php return; endif;
if ($firstPage): ?><div class="list-group list-group-flush" id="record-picker-list" role="listbox"><?php endif; ?>
<?php foreach ($rows as $row): $active = $selected !== null && (int) $row['id'] === $selected; ?>
<button type="button" class="list-group-item list-group-item-action record-picker-row<?= $active ? ' active' : '' ?>" id="record-picker-row-<?= e($row['id']) ?>" role="option" aria-selected="<?= $active ? 'true' : 'false' ?>" data-value="<?= e($row['id']) ?>" data-label="<?= e($row['label']) ?>">
    <div class="d-flex justify-content-between align-items-center gap-2">
        <div class="min-w-0"><div class="fw-semibold text-truncate"><?= e($row['label']) ?></div><?php if (!empty($row['detail'])): ?><div class="fs-11 text-truncate record-picker-detail"><?= e($row['detail']) ?></div><?php endif; ?></div>
        <?php if (!empty($row['badge'])): ?><span class="badge bg-soft-<?= e($row['color'] ?? 'secondary') ?> text-<?= e($row['color'] ?? 'secondary') ?> flex-shrink-0"><?= e($row['badge']) ?></span><?php endif; ?>
    </div>
</button>
<?php endforeach; ?>
<?php if ((int) $result['page'] < (int) $result['pages']): ?>
<button type="button" class="list-group-item list-group-item-action text-center text-primary record-picker-more" id="record-picker-more"
        hx-get="/pick/?<?= e(http_build_query(array_filter(['source' => $source, 'q' => $q, 'selected' => $selected, 'page' => (int) $result['page'] + 1] + $params, static fn($v) => $v !== null && $v !== ''))) ?>"
        hx-target="this" hx-swap="outerHTML"><i class="feather-chevron-down me-1"></i>Show more — <?= e($shown) ?> of <?= e($result['total']) ?> shown</button>
<?php endif; ?>
<?php if ($firstPage): ?></div><?php endif; ?>
