<?php
// =====================================================
// Shared helpers for every API file
// Location: backend/includes/helpers.php
// =====================================================

/** Sets JSON + CORS headers, answers preflight, returns the HTTP method. */
function init_api(): string
{
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');   // development only; restrict this in production
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
    return $method;
}

function json_response($data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function error_response(string $message, int $status = 400): void
{
    json_response(['error' => $message], $status);
}

/** Reads the request body (JSON, or form data as a fallback). */
function get_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw !== false && trim($raw) !== '') {
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            error_response('Invalid JSON body', 400);
        }
        return $data;
    }
    return $_POST;
}

/** ?id=5 -> 5, missing -> null, invalid -> 400. */
function get_id(): ?int
{
    if (!isset($_GET['id'])) {
        return null;
    }
    if (!ctype_digit((string)$_GET['id']) || (int)$_GET['id'] < 1) {
        error_response('Invalid id', 400);
    }
    return (int)$_GET['id'];
}

/** Optional integer query filter such as ?vet_id=2. */
function get_int_param(string $name): ?int
{
    if (!isset($_GET[$name]) || $_GET[$name] === '') {
        return null;
    }
    if (!ctype_digit((string)$_GET[$name])) {
        error_response("Invalid $name", 400);
    }
    return (int)$_GET[$name];
}

// ---------- Validation ----------

function require_fields(array $data, array $fields): void
{
    $missing = [];
    foreach ($fields as $f) {
        if (!isset($data[$f]) || (is_string($data[$f]) && trim($data[$f]) === '')) {
            $missing[] = $f;
        }
    }
    if ($missing) {
        error_response('Missing required field(s): ' . implode(', ', $missing), 400);
    }
}

/** Trimmed string, or null when empty / missing. */
function nullable($v): ?string
{
    if ($v === null || !is_scalar($v)) {
        return null;
    }
    $v = trim((string)$v);
    return $v === '' ? null : $v;
}

function validate_text($v, string $field, int $max): string
{
    if (!is_scalar($v)) {
        error_response("$field must be text", 400);
    }
    $v = trim((string)$v);
    if ($v === '') {
        error_response("$field cannot be empty", 400);
    }
    if (mb_strlen($v) > $max) {
        error_response("$field must be at most $max characters", 400);
    }
    return $v;
}

function validate_email($v): string
{
    $v = is_scalar($v) ? trim((string)$v) : '';
    if (!filter_var($v, FILTER_VALIDATE_EMAIL)) {
        error_response('Invalid email address', 400);
    }
    return $v;
}

function validate_phone($v): string
{
    $v = is_scalar($v) ? preg_replace('/[\s-]/', '', (string)$v) : '';
    if (!preg_match('/^\+?[0-9]{7,15}$/', $v)) {
        error_response('Invalid phone number (7-15 digits, optional leading +)', 400);
    }
    return $v;
}

function validate_date($v, string $field): string
{
    $v = is_scalar($v) ? trim((string)$v) : '';
    $d = DateTime::createFromFormat('Y-m-d', $v);
    if (!$d || $d->format('Y-m-d') !== $v) {
        error_response("$field must be a valid date in YYYY-MM-DD format", 400);
    }
    return $v;
}

function validate_time($v, string $field): string
{
    $v = is_scalar($v) ? trim((string)$v) : '';
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $v)) {
        error_response("$field must be a valid time (HH:MM or HH:MM:SS)", 400);
    }
    return strlen($v) === 5 ? $v . ':00' : $v;
}

function validate_enum($v, array $allowed, string $field): string
{
    if (!is_scalar($v) || !in_array((string)$v, $allowed, true)) {
        error_response("$field must be one of: " . implode(', ', $allowed), 400);
    }
    return (string)$v;
}

function validate_money($v, string $field): float
{
    if (!is_numeric($v) || (float)$v < 0 || (float)$v > 99999999.99) {
        error_response("$field must be a non-negative number", 400);
    }
    return round((float)$v, 2);
}

function validate_id($v, string $field): int
{
    if (!is_scalar($v) || !ctype_digit((string)$v) || (int)$v < 1) {
        error_response("$field must be a positive integer", 400);
    }
    return (int)$v;
}

// ---------- Small DB helpers (table/column names are hardcoded by the callers) ----------

function record_exists(PDO $pdo, string $table, string $idCol, int $id): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM $table WHERE $idCol = ?");
    $stmt->execute([$id]);
    return (bool)$stmt->fetchColumn();
}

/** UPDATE only the columns present in $fields. */
function update_record(PDO $pdo, string $table, string $idCol, int $id, array $fields): void
{
    if (!$fields) {
        error_response('No valid fields to update', 400);
    }
    $sets = [];
    $params = [];
    foreach ($fields as $col => $val) {
        $sets[] = "$col = ?";
        $params[] = $val;
    }
    $params[] = $id;
    $pdo->prepare("UPDATE $table SET " . implode(', ', $sets) . " WHERE $idCol = ?")->execute($params);
}

function delete_record(PDO $pdo, string $table, string $idCol, int $id, string $label): void
{
    if (!record_exists($pdo, $table, $idCol, $id)) {
        error_response("$label not found", 404);
    }
    $pdo->prepare("DELETE FROM $table WHERE $idCol = ?")->execute([$id]);
    json_response(['message' => "$label deleted"]);
}

/** Turns common MySQL errors into friendly JSON responses. */
function handle_pdo_exception(PDOException $e): void
{
    $code = $e->errorInfo[1] ?? 0;
    $msg  = $e->getMessage();

    switch ($code) {
        case 1062: // duplicate entry
            if (strpos($msg, 'uq_vet_slot') !== false) {
                error_response('This vet already has an appointment at that date and time', 409);
            }
            if (strpos($msg, 'email') !== false) {
                error_response('This email is already registered', 409);
            }
            if (strpos($msg, 'appointment_id') !== false) {
                error_response('A bill already exists for this appointment', 409);
            }
            error_response('Duplicate entry', 409);
        case 1451: // parent row referenced elsewhere
            error_response('Cannot delete: this record is still referenced by other records', 409);
        case 1452: // child row refers to a missing parent
            error_response('Invalid reference: the related record does not exist', 400);
        case 3819: // CHECK constraint
            error_response('A value violates a database rule (for example a negative amount)', 400);
        case 1406: // data too long
            error_response('One of the values is too long', 400);
        default:
            error_log('DB error: ' . $msg);
            error_response('Database error', 500);
    }
}