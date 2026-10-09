<?php
declare(strict_types=1);

/**
 * The record picker (design-system reference record-picker.md): a form chooses a RECORD — a supplier, an item, a
 * customer, anything a person adds rows to without limit — through one searchable modal instead of a <select>.
 *
 *   picker_field([...])                 the field: a hidden input under the select's old name and id + the button that opens the picker
 *   form_picker(...)  (app/ui.php)      the same inside the standard col-lg-4 / col-lg-8 form row
 *   picker_source($key)                 one entry of app/pickers.php — the registry of what can be picked
 *   picker_label($pdo, $key, $id)       the chosen record's label (first render, a 422 re-render)
 *   GET /pick/?source=&q=&page=         html/pick/index.php answers shared/picker-rows.php; assets/js/record-picker.js drives the modal
 *
 * A source (app/pickers.php) is ['title', 'noun', 'roles' => [...], 'params' => [declared filter keys],
 *   'search' => fn(PDO, string $q, array $params, int $page): array{rows, total, page, pages},   rows: [id, label, detail?, badge?, color?]
 *   'label'  => fn(PDO, int $id): ?string].
 * `search` reuses the feature's own query function; the endpoint whitelists `params` and never sees anything else.
 */

function picker_sources(): array
{
    if (!isset($GLOBALS['__picker_sources'])) {
        $GLOBALS['__picker_sources'] = require __DIR__ . '/pickers.php';
    }
    return $GLOBALS['__picker_sources'];
}

function picker_source(string $key): ?array
{
    return picker_sources()[$key] ?? null;
}

/** The label of the chosen record, or null when the id names nothing (the form then shows the placeholder). */
function picker_label(PDO $pdo, string $source, int|string|null $id): ?string
{
    $spec = picker_source($source);
    if ($spec === null || $id === null || $id === '' || !ctype_digit((string) $id)) {
        return null;
    }
    return ($spec['label'])($pdo, (int) $id);
}

/**
 * The field. $spec: id (the hidden input's id — what hx-include selectors name), name, source, value; optional label
 * (looked up through the source when absent), placeholder, title (the modal's), search (the search box's placeholder),
 * params (fixed filters, key => value), include (CSS selectors whose live values filter the search; their names must
 * be declared params), required (no Clear button), disabled, invalid, labelledby (the row label's id), extra
 * (attributes on the hidden input — a dependent refresh's hx-get … hx-trigger="change"), append (more input-group
 * buttons after the search button), create (['label', 'panel' => a selector to open] or ['label', 'url']).
 */
function picker_field(array $spec): string
{
    $id = (string) $spec['id'];
    $source = picker_source((string) $spec['source']) ?? throw new InvalidArgumentException('Unknown picker source ' . $spec['source']);
    $value = $spec['value'] ?? null;
    $value = $value === null || $value === '' ? '' : (string) $value;
    $label = $spec['label'] ?? ($value !== '' ? picker_label(db(), (string) $spec['source'], $value) : null);
    if ($value !== '' && $label === null) {
        $value = '';                                         // an id that names nothing is no choice
    }
    $placeholder = (string) ($spec['placeholder'] ?? ('Choose ' . ($source['noun'] === 'item' ? 'an' : 'a') . ' ' . $source['noun']));
    $required = !empty($spec['required']);
    $disabled = !empty($spec['disabled']);
    $data = ' data-picker-source="' . e($spec['source']) . '" data-picker-title="' . e($spec['title'] ?? $source['title']) . '"'
        . ' data-picker-placeholder="' . e($placeholder) . '" data-picker-search="' . e($spec['search'] ?? ('Search ' . lcfirst($source['title']) . '…')) . '"';
    if (!empty($spec['params'])) {
        $data .= ' data-picker-params="' . e(json_encode($spec['params'], JSON_THROW_ON_ERROR)) . '"';
    }
    if (!empty($spec['include'])) {
        $data .= ' data-picker-include="' . e($spec['include']) . '"';
    }
    if (!empty($spec['create']['label'])) {
        $data .= ' data-picker-create-label="' . e($spec['create']['label']) . '"'
            . (isset($spec['create']['panel']) ? ' data-picker-create-panel="' . e($spec['create']['panel']) . '"' : '')
            . (isset($spec['create']['url']) ? ' data-picker-create-url="' . e($spec['create']['url']) . '"' : '');
    }
    $invalid = !empty($spec['invalid']) ? ' is-invalid' : '';
    $labelledby = isset($spec['labelledby']) ? ' aria-labelledby="' . e($spec['labelledby']) . ' ' . e($id) . '-open"' : '';
    return '<div class="input-group record-picker-field" id="' . e($id) . '-picker"' . $data . '>'
        . '<input type="hidden" name="' . e($spec['name']) . '" id="' . e($id) . '" value="' . e($value) . '" data-picker-value' . ($spec['extra'] ?? '') . ' />'
        . '<button type="button" class="form-control text-start record-picker-open' . $invalid . '" id="' . e($id) . '-open" aria-haspopup="dialog" aria-controls="record-picker"'
        . $labelledby . ($required ? ' aria-required="true"' : '') . ($disabled ? ' disabled' : '') . '>'
        . '<span class="record-picker-label' . ($value === '' ? ' text-muted' : '') . '">' . e($value === '' ? $placeholder : $label) . '</span></button>'
        . ($required || $disabled ? '' : '<button type="button" class="btn btn-light-brand record-picker-clear' . ($value === '' ? ' d-none' : '') . '" id="' . e($id) . '-clear" aria-label="Clear"><i class="feather-x"></i></button>')
        . '<button type="button" class="btn btn-light-brand record-picker-search-btn" id="' . e($id) . '-search-btn" aria-label="Search"' . ($disabled ? ' disabled' : '') . '><i class="feather-search"></i></button>'
        . ($spec['append'] ?? '')
        . '</div>';
}
