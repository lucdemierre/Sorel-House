<?php

declare(strict_types=1);

$sessionPath = dirname(__DIR__) . '/storage/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0775, true);
}
session_save_path($sessionPath);
session_start();

$defaults = [
    'app_name' => 'Sorel House',
    'timezone' => 'Europe/London',
    'dsn' => 'sqlite:' . dirname(__DIR__) . '/storage/sorel-house.sqlite',
    'db_user' => '',
    'db_password' => '',
    'seed_demo_data' => true,
    'landlord_email' => 'landlord@example.com',
    'landlord_password' => 'change-me',
    'ai_provider' => 'openrouter',
    'openrouter_api_key' => '',
    'openrouter_model' => 'nvidia/nemotron-nano-12b-v2-vl:free',
    'openrouter_site_url' => 'http://localhost:8080',
    'anthropic_api_key' => '',
    'anthropic_model' => 'claude-sonnet-4-6',
];

$configFile = dirname(__DIR__) . '/config.php';
$fileConfig = file_exists($configFile) ? require $configFile : [];
$config = array_merge($defaults, is_array($fileConfig) ? $fileConfig : []);
date_default_timezone_set($config['timezone']);

function db(): PDO
{
    global $config;
    static $pdo;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = new PDO($config['dsn'], $config['db_user'], $config['db_password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    migrate($pdo);
    if ($config['seed_demo_data']) {
        seed($pdo);
    } else {
        ensureCurrentPayments($pdo);
    }
    return $pdo;
}

function migrate(PDO $pdo): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $id = $driver === 'mysql' ? 'INTEGER PRIMARY KEY AUTO_INCREMENT' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $text = $driver === 'mysql' ? 'VARCHAR(255)' : 'TEXT';
    $longText = $driver === 'mysql' ? 'LONGTEXT' : 'TEXT';
    $timestamp = $driver === 'mysql' ? 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP' : "TEXT DEFAULT CURRENT_TIMESTAMP";

    $queries = [
        "CREATE TABLE IF NOT EXISTS properties (
            id $id, address $text NOT NULL, postcode $text NOT NULL, created_at $timestamp
        )",
        "CREATE TABLE IF NOT EXISTS certificates (
            id $id, property_id INTEGER NOT NULL, type $text NOT NULL, expires_on DATE NOT NULL,
            notes $longText NOT NULL DEFAULT '', created_at $timestamp,
            FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS tenants (
            id $id, property_id INTEGER NOT NULL, name $text NOT NULL, email $text NOT NULL DEFAULT '',
            monthly_rent DECIMAL(10,2) NOT NULL, rent_due_day INTEGER NOT NULL DEFAULT 1, created_at $timestamp,
            FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS payments (
            id $id, tenant_id INTEGER NOT NULL, rent_month DATE NOT NULL, amount DECIMAL(10,2) NOT NULL,
            status $text NOT NULL DEFAULT 'pending', paid_on DATE NULL, created_at $timestamp,
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS messages (
            id $id, tenant_id INTEGER NOT NULL, sender $text NOT NULL, body $longText NOT NULL,
            status $text NOT NULL DEFAULT 'received', created_at $timestamp,
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS agreements (
            id $id, tenant_name $text NOT NULL, property_address $text NOT NULL, rent_amount DECIMAL(10,2) NOT NULL,
            rent_due_day INTEGER NOT NULL, start_date DATE NOT NULL, draft $longText NOT NULL, created_at $timestamp
        )",
        "CREATE TABLE IF NOT EXISTS maintenance_requests (
            id $id, property_id INTEGER NOT NULL, tenant_id INTEGER NULL, title $text NOT NULL, description $longText NOT NULL,
            priority $text NOT NULL DEFAULT 'normal', status $text NOT NULL DEFAULT 'reported', created_at $timestamp,
            FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL
        )",
        "CREATE TABLE IF NOT EXISTS documents (
            id $id, property_id INTEGER NOT NULL, name $text NOT NULL, category $text NOT NULL DEFAULT 'General',
            notes $longText NOT NULL DEFAULT '', created_at $timestamp,
            FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS reminders (
            id $id, property_id INTEGER NULL, title $text NOT NULL, due_on DATE NOT NULL,
            status $text NOT NULL DEFAULT 'open', created_at $timestamp,
            FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
        )",
    ];

    foreach ($queries as $query) {
        $pdo->exec($query);
    }

    ensureColumn($pdo, 'tenants', 'portal_token', "$text NOT NULL DEFAULT ''");
    ensureColumn($pdo, 'tenants', 'status', "$text NOT NULL DEFAULT 'active'");
    ensureColumn($pdo, 'properties', 'address_line_2', "$text NOT NULL DEFAULT ''");
    ensureColumn($pdo, 'properties', 'town_city', "$text NOT NULL DEFAULT ''");
    ensureColumn($pdo, 'properties', 'county', "$text NOT NULL DEFAULT ''");
    ensureColumn($pdo, 'properties', 'property_type', "$text NOT NULL DEFAULT 'House'");
    ensureColumn($pdo, 'properties', 'bedrooms', 'INTEGER NOT NULL DEFAULT 1');
    ensureColumn($pdo, 'properties', 'bathrooms', 'INTEGER NOT NULL DEFAULT 1');
    ensureColumn($pdo, 'properties', 'local_authority', "$text NOT NULL DEFAULT ''");
    ensureColumn($pdo, 'properties', 'council_tax_band', "$text NOT NULL DEFAULT ''");
    ensureColumn($pdo, 'properties', 'ownership_reference', "$text NOT NULL DEFAULT ''");
    ensureColumn($pdo, 'properties', 'access_notes', "$longText NOT NULL DEFAULT ''");
    ensureColumn($pdo, 'properties', 'emergency_notes', "$longText NOT NULL DEFAULT ''");
    ensureColumn($pdo, 'messages', 'source_message_id', 'INTEGER NULL');
    ensureColumn($pdo, 'messages', 'review_note', "$longText NOT NULL DEFAULT ''");
    ensureColumn($pdo, 'messages', 'generated_by', "$text NOT NULL DEFAULT ''");
    foreach ($pdo->query("SELECT id FROM tenants WHERE portal_token = ''")->fetchAll() as $tenant) {
        $pdo->prepare('UPDATE tenants SET portal_token = ? WHERE id = ?')
            ->execute([bin2hex(random_bytes(16)), $tenant['id']]);
    }

    if ($driver === 'sqlite') {
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS payments_tenant_month_unique ON payments (tenant_id, rent_month)');
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS tenants_portal_token_unique ON tenants (portal_token)');
    } else {
        if (!$pdo->query("SHOW INDEX FROM payments WHERE Key_name = 'payments_tenant_month_unique'")->fetch()) {
            $pdo->exec('CREATE UNIQUE INDEX payments_tenant_month_unique ON payments (tenant_id, rent_month)');
        }
        if (!$pdo->query("SHOW INDEX FROM tenants WHERE Key_name = 'tenants_portal_token_unique'")->fetch()) {
            $pdo->exec('CREATE UNIQUE INDEX tenants_portal_token_unique ON tenants (portal_token)');
        }
    }
}

function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $columns = $pdo->query("PRAGMA table_info($table)")->fetchAll();
        if (!array_filter($columns, fn(array $item): bool => $item['name'] === $column)) {
            $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
        }
        return;
    }

    $statement = $pdo->prepare("SHOW COLUMNS FROM $table LIKE ?");
    $statement->execute([$column]);
    if (!$statement->fetch()) {
        $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
    }
}

function seed(PDO $pdo): void
{
    if ((int) $pdo->query('SELECT COUNT(*) FROM properties')->fetchColumn() > 0) {
        ensureCurrentPayments($pdo);
        seedOperations($pdo);
        return;
    }

    $properties = [
        ['12 Albert Road', 'N7 8QJ'],
        ['4 Camden Mews', 'NW1 9BY'],
        ['77 Holloway Street', 'N7 6JP'],
        ['9 Oak House', 'E8 3RT'],
    ];
    $propertyStatement = $pdo->prepare('INSERT INTO properties (address, postcode) VALUES (?, ?)');
    foreach ($properties as $property) {
        $propertyStatement->execute($property);
    }

    $certificateStatement = $pdo->prepare('INSERT INTO certificates (property_id, type, expires_on) VALUES (?, ?, ?)');
    foreach ([
        [1, 'Gas safety', date('Y-m-d', strtotime('+13 days'))],
        [2, 'EICR', date('Y-m-d', strtotime('+18 months'))],
        [3, 'EPC', date('Y-m-d', strtotime('-6 days'))],
        [4, 'Smoke alarm check', date('Y-m-d', strtotime('+6 days'))],
    ] as $certificate) {
        $certificateStatement->execute($certificate);
    }

    $tenantStatement = $pdo->prepare('INSERT INTO tenants (property_id, name, email, monthly_rent, rent_due_day, portal_token) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ([
        [2, 'Sarah Lee', 'sarah@example.com', 1450, 1, bin2hex(random_bytes(16))],
        [1, 'Daniel Ross', 'daniel@example.com', 1125, 1, bin2hex(random_bytes(16))],
        [4, 'Maya Khan', 'maya@example.com', 1600, 28, bin2hex(random_bytes(16))],
        [3, 'Emily Carter', 'emily@example.com', 1325, 1, bin2hex(random_bytes(16))],
    ] as $tenant) {
        $tenantStatement->execute($tenant);
    }

    ensureCurrentPayments($pdo);
    $pdo->exec("UPDATE payments SET status = 'received', paid_on = CURRENT_DATE WHERE tenant_id = 1");
    $pdo->exec("UPDATE payments SET status = 'late' WHERE tenant_id = 2");

    $message = $pdo->prepare('INSERT INTO messages (tenant_id, sender, body, status) VALUES (?, ?, ?, ?)');
    foreach ([
        [4, 'tenant', 'The boiler has stopped working again.', 'received'],
        [2, 'tenant', 'Can you confirm when the engineer is coming?', 'received'],
        [3, 'tenant', 'Rent will be two days late this month.', 'received'],
    ] as $row) {
        $message->execute($row);
    }
    seedOperations($pdo);
}

function seedOperations(PDO $pdo): void
{
    if ((int) $pdo->query('SELECT COUNT(*) FROM maintenance_requests')->fetchColumn() === 0) {
        $statement = $pdo->prepare('INSERT INTO maintenance_requests (property_id, tenant_id, title, description, priority, status) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ([
            [3, 4, 'Boiler fault', 'Tenant reports the boiler has stopped working again.', 'urgent', 'reported'],
            [1, 2, 'Hallway light', 'Replace the hallway fitting and check the switch.', 'normal', 'scheduled'],
        ] as $item) {
            $statement->execute($item);
        }
    }
    if ((int) $pdo->query('SELECT COUNT(*) FROM documents')->fetchColumn() === 0) {
        $statement = $pdo->prepare('INSERT INTO documents (property_id, name, category, notes) VALUES (?, ?, ?, ?)');
        foreach ([
            [1, 'Gas safety certificate', 'Safety', 'Latest certificate copy held offline.'],
            [2, 'Inventory and check-in record', 'Tenancy', 'Signed at move-in.'],
        ] as $item) {
            $statement->execute($item);
        }
    }
    if ((int) $pdo->query('SELECT COUNT(*) FROM reminders')->fetchColumn() === 0) {
        $statement = $pdo->prepare('INSERT INTO reminders (property_id, title, due_on) VALUES (?, ?, ?)');
        foreach ([
            [1, 'Book gas engineer', date('Y-m-d', strtotime('+5 days'))],
            [3, 'Renew EPC', date('Y-m-d', strtotime('+2 days'))],
        ] as $item) {
            $statement->execute($item);
        }
    }
}

function ensureCurrentPayments(PDO $pdo): void
{
    $month = date('Y-m-01');
    $statement = $pdo->prepare(
        'INSERT INTO payments (tenant_id, rent_month, amount, status)
         SELECT t.id, ?, t.monthly_rent, ? FROM tenants t
         WHERE NOT EXISTS (SELECT 1 FROM payments p WHERE p.tenant_id = t.id AND p.rent_month = ?)'
    );
    $statement->execute([$month, 'pending', $month]);
}

function rows(string $sql, array $params = []): array
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
}

function row(string $sql, array $params = []): ?array
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    $result = $statement->fetch();
    return $result ?: null;
}

function execute(string $sql, array $params = []): void
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrfToken(): string
{
    if (!isset($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function verifyCsrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        throw new RuntimeException('Your session expired. Please refresh the page and try again.');
    }
}

function requiredText(string $key, string $label): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    if ($value === '') {
        throw new RuntimeException("$label is required.");
    }
    return $value;
}

function requiredId(string $key, string $label): int
{
    $value = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$value) {
        throw new RuntimeException("$label is required.");
    }
    return (int) $value;
}

function requiredMoney(string $key, string $label): float
{
    $value = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_FLOAT);
    if ($value === false || $value < 0) {
        throw new RuntimeException("$label must be zero or more.");
    }
    return (float) $value;
}

function requiredDay(string $key, string $label): int
{
    $value = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 28]]);
    if (!$value) {
        throw new RuntimeException("$label must be between 1 and 28.");
    }
    return (int) $value;
}

function requiredCount(string $key, string $label): int
{
    $value = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 99]]);
    if ($value === false) {
        throw new RuntimeException("$label must be between 0 and 99.");
    }
    return (int) $value;
}

function requiredDate(string $key, string $label): string
{
    $value = (string) ($_POST[$key] ?? '');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new RuntimeException("$label must be a valid date.");
    }
    return $value;
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function takeFlash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function redirectTo(string $anchor = ''): never
{
    header('Location: index.php' . ($anchor ? '#' . $anchor : ''));
    exit;
}

function isLandlordSignedIn(): bool
{
    return ($_SESSION['landlord_signed_in'] ?? false) === true;
}

function requireLandlordSignIn(): void
{
    if (!isLandlordSignedIn()) {
        header('Location: login.php');
        exit;
    }
}

function certificateStatus(string $date): array
{
    $days = (int) floor((strtotime($date) - strtotime(date('Y-m-d'))) / 86400);
    if ($days < 0) {
        return ['expired', 'Expired', $days];
    }
    if ($days <= 30) {
        return ['due', 'Due soon', $days];
    }
    return ['ok', 'Compliant', $days];
}

function callClaude(string $system, string $prompt, int $maxTokens = 900): ?string
{
    global $config;
    if ($config['ai_provider'] === 'openrouter') {
        return callOpenRouter($system, $prompt, $maxTokens);
    }
    if (!$config['anthropic_api_key'] || !function_exists('curl_init')) {
        return null;
    }

    $curl = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 35,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'content-type: application/json',
            'x-api-key: ' . $config['anthropic_api_key'],
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => $config['anthropic_model'],
            'max_tokens' => $maxTokens,
            'system' => $system,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ], JSON_THROW_ON_ERROR),
    ]);
    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if (!$response || $status < 200 || $status >= 300) {
        return null;
    }
    $json = json_decode($response, true);
    return $json['content'][0]['text'] ?? null;
}

function callOpenRouter(string $system, string $prompt, int $maxTokens = 900): ?string
{
    global $config;
    if (!$config['openrouter_api_key'] || !function_exists('curl_init')) {
        return null;
    }

    $curl = curl_init('https://openrouter.ai/api/v1/chat/completions');
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 35,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $config['openrouter_api_key'],
            'Content-Type: application/json',
            'HTTP-Referer: ' . $config['openrouter_site_url'],
            'X-OpenRouter-Title: Sorel House',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => $config['openrouter_model'],
            'max_tokens' => $maxTokens,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $prompt],
            ],
        ], JSON_THROW_ON_ERROR),
    ]);
    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if (!$response || $status < 200 || $status >= 300) {
        return null;
    }
    $json = json_decode($response, true);
    return $json['choices'][0]['message']['content'] ?? null;
}

function aiProviderLabel(): string
{
    global $config;
    if ($config['ai_provider'] === 'openrouter') {
        return $config['openrouter_api_key'] ? 'OpenRouter: ' . $config['openrouter_model'] : 'Local fallback';
    }
    return $config['anthropic_api_key'] ? 'Anthropic: ' . $config['anthropic_model'] : 'Local fallback';
}

function draftTenantReply(array $tenant, string $message, string $guidance = ''): string
{
    $extraGuidance = $guidance !== '' ? "\nLandlord guidance for this revision: $guidance" : '';
    return callClaude(
        'You draft concise, professional replies for an England residential landlord. Never claim that a repair is booked unless the landlord said so. Flag emergencies. Return only the reply.',
        "Tenant name: {$tenant['name']}\nTenant message: $message$extraGuidance"
    ) ?? fallbackReply($tenant['name'], $message);
}

function fallbackReply(string $tenantName, string $message): string
{
    return "Hi $tenantName,\n\nThank you for your message. I have received your update and will review it shortly. I will come back to you with the next steps as soon as possible.\n\nKind regards,\nYour landlord";
}

function fallbackAgreement(array $input): string
{
    return "ASSURED PERIODIC TENANCY - DRAFT FOR REVIEW\n\n"
        . "This draft is for a proposed assured periodic tenancy in England. It must be reviewed before signature.\n\n"
        . "Landlord: " . $input['landlord_name'] . "\n"
        . "Tenant: " . $input['tenant_name'] . "\n"
        . "Property: " . $input['property_address'] . "\n"
        . "Tenancy start date: " . $input['start_date'] . "\n"
        . "Rent: £" . number_format((float) $input['rent_amount'], 2) . " per calendar month\n"
        . "Rent due: day " . (int) $input['rent_due_day'] . " of each month\n\n"
        . "The tenancy runs on a monthly rolling basis. The tenant must pay the rent when due, use the property as their main home, take reasonable care of the property, and promptly report repairs. The landlord must meet their legal repair, safety, deposit protection and information duties.\n\n"
        . "This short fallback draft is not a complete tenancy agreement. Obtain a reviewed agreement before use.";
}
