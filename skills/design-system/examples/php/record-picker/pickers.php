<?php
declare(strict_types=1);

/**
 * app/pickers.php — the registry of what a form can pick (app/picker.php has the contract). One entry per record kind.
 * `search` reuses the feature's own query function (every list screen has one with ILIKE search and paging — reuse it,
 * never a second query), `label` names one record, `roles` gates the endpoint like the screens that use the field,
 * `params` are the only filter keys the endpoint forwards (integers; a field's `include` selectors must name them).
 * Rows: id, label, detail (one muted line: code, email, city…), badge + color (the status-color vocabulary) — optional.
 */
return [
    'customer' => [
        'title' => 'Customers', 'noun' => 'customer', 'roles' => ['sales', 'billing'], 'params' => [],
        'search' => static function (PDO $pdo, string $q, array $params, int $page): array {
            require_once __DIR__ . '/features/customers/queries.php';
            $result = find_customers($pdo, $q, 'name', $page);                   // ['rows', 'total', 'page', 'page_size', 'pages']
            $result['rows'] = array_map(static fn(array $c): array => [
                'id' => (int) $c['id'], 'label' => $c['name'],
                'detail' => implode(' · ', array_filter([$c['email'], $c['city']])),
                'badge' => $c['status'] === 'active' ? null : ucfirst($c['status']), 'color' => $c['status'] === 'active' ? 'success' : 'secondary',
            ], $result['rows']);
            return $result;
        },
        'label' => static function (PDO $pdo, int $id): ?string {
            require_once __DIR__ . '/features/customers/queries.php';
            return find_customer($pdo, $id)['name'] ?? null;
        },
    ],
    // A dependent source: the field declares include => '#order-form-field-customer-id' and the rows change with it.
    'contact' => [
        'title' => 'Contacts', 'noun' => 'contact', 'roles' => ['sales'], 'params' => ['customer_id'],
        'search' => static function (PDO $pdo, string $q, array $params, int $page): array {
            require_once __DIR__ . '/features/contacts/queries.php';
            $result = find_contacts($pdo, $q, $params['customer_id'] ?? null, $page);
            $result['rows'] = array_map(static fn(array $c): array => ['id' => (int) $c['id'], 'label' => $c['name'], 'detail' => $c['role']], $result['rows']);
            return $result;
        },
        'label' => static function (PDO $pdo, int $id): ?string {
            require_once __DIR__ . '/features/contacts/queries.php';
            return find_contact($pdo, $id)['name'] ?? null;
        },
    ],
];
