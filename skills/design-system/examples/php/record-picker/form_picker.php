<?php
declare(strict_types=1);

/**
 * app/ui.php — the row helper for applications that render forms through form_row_open()/form_row_close() (the
 * col-lg-4 label / col-lg-8 control grid). Applications that write form rows by hand call picker_field() directly
 * inside their own label markup and point the label's `for` at "{id}-open".
 */
function form_picker(string $prefix, string $name, string $label, string $source, mixed $value, array $errors = [], array $opts = []): string
{
    $id = field_id($prefix, $name);
    return form_row_open($prefix, $name, $label, !empty($opts['last']))
        . picker_field(['id' => $id, 'name' => $opts['name'] ?? $name, 'source' => $source, 'value' => $value, 'invalid' => isset($errors[$name]), 'labelledby' => $id . '-label'] + $opts)
        . (isset($errors[$name]) ? '<div class="invalid-feedback d-block">' . e($errors[$name]) . '</div>' : '')
        . form_row_close($opts['help'] ?? null);
}
