<?php
// =====================================================
// Billing API
//   GET    billing.php                 list. Filters: ?payment_status= &appointment_id= &owner_id=
//   GET    billing.php?summary=1       totals grouped by payment status
//   GET    billing.php?id=1            one bill
//   POST   billing.php                 create {appointment_id, amount?, payment_status?, payment_method?, bill_date?}
//                                      (amount defaults to the sum of the appointment's treatment costs)
//   PUT    billing.php?id=1            update {amount?, payment_status?, payment_method?, bill_date?}
//   DELETE billing.php?id=1            delete
// =====================================================
require_once __DIR__ . '/../includes/helpers.php';
$method = init_api();
require_once __DIR__ . '/../config/db.php';

const PAY_STATUSES = ['Pending', 'Paid', 'Partial'];
const PAY_METHODS  = ['Cash', 'Card', 'UPI'];

function check_method_required(string $status, ?string $payMethod): void
{
    if (in_array($status, ['Paid', 'Partial'], true) && $payMethod === null) {
        error_response('payment_method is required when payment_status is Paid or Partial', 400);
    }
}

$baseSql = "SELECT b.bill_id, b.appointment_id, b.amount, b.payment_status, b.payment_method, b.bill_date,
                   a.appointment_date, a.reason,
                   p.pet_id, p.name AS pet_name,
                   o.owner_id, o.name AS owner_name, o.phone AS owner_phone,
                   v.name AS vet_name
            FROM Billing b
            JOIN Appointment a ON b.appointment_id = a.appointment_id
            JOIN Pet   p ON a.pet_id   = p.pet_id
            JOIN Owner o ON p.owner_id = o.owner_id
            JOIN Vet   v ON a.vet_id   = v.vet_id";

try {
    switch ($method) {
        case 'GET':
            if (!empty($_GET['summary'])) {
                $rows = $pdo->query(
                    "SELECT payment_status, COUNT(*) AS bills, COALESCE(SUM(amount), 0) AS total_amount
                     FROM Billing GROUP BY payment_status"
                )->fetchAll();
                json_response($rows);
            }

            $id = get_id();
            if ($id !== null) {
                $stmt = $pdo->prepare("$baseSql WHERE b.bill_id = ?");
                $stmt->execute([$id]);
                $row = $stmt->fetch();
                if (!$row) {
                    error_response('Bill not found', 404);
                }
                json_response($row);
            }

            $where = [];
            $params = [];
            if (!empty($_GET['payment_status'])) {
                $where[] = "b.payment_status = ?";
                $params[] = validate_enum($_GET['payment_status'], PAY_STATUSES, 'payment_status');
            }
            if (($apptId = get_int_param('appointment_id')) !== null) {
                $where[] = "b.appointment_id = ?";
                $params[] = $apptId;
            }
            if (($ownerId = get_int_param('owner_id')) !== null) {
                $where[] = "o.owner_id = ?";
                $params[] = $ownerId;
            }
            $sql = $baseSql . ($where ? " WHERE " . implode(' AND ', $where) : "")
                 . " ORDER BY b.bill_date DESC, b.bill_id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            json_response($stmt->fetchAll());

        case 'POST':
            $d = get_input();
            require_fields($d, ['appointment_id']);
            $apptId = validate_id($d['appointment_id'], 'appointment_id');
            if (!record_exists($pdo, 'Appointment', 'appointment_id', $apptId)) {
                error_response('Appointment not found', 404);
            }

            if (isset($d['amount']) && $d['amount'] !== '') {
                $amount = validate_money($d['amount'], 'amount');
            } else {
                $s = $pdo->prepare("SELECT COALESCE(SUM(cost), 0) FROM Treatment WHERE appointment_id = ?");
                $s->execute([$apptId]);
                $amount = (float)$s->fetchColumn();
            }
            $status = validate_enum($d['payment_status'] ?? 'Pending', PAY_STATUSES, 'payment_status');
            $method_ = nullable($d['payment_method'] ?? null);
            $method_ = $method_ !== null ? validate_enum($method_, PAY_METHODS, 'payment_method') : null;
            check_method_required($status, $method_);
            $billDate = isset($d['bill_date']) && $d['bill_date'] !== ''
                ? validate_date($d['bill_date'], 'bill_date')
                : date('Y-m-d');

            $stmt = $pdo->prepare(
                "INSERT INTO Billing (appointment_id, amount, payment_status, payment_method, bill_date)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$apptId, $amount, $status, $method_, $billDate]);
            json_response(['message' => 'Bill created', 'bill_id' => (int)$pdo->lastInsertId()], 201);

        case 'PUT':
            $id = get_id();
            if ($id === null) {
                error_response('id is required', 400);
            }
            $s = $pdo->prepare("SELECT payment_status, payment_method FROM Billing WHERE bill_id = ?");
            $s->execute([$id]);
            $current = $s->fetch();
            if (!$current) {
                error_response('Bill not found', 404);
            }

            $d = get_input();
            $fields = [];
            if (array_key_exists('amount', $d))         $fields['amount']         = validate_money($d['amount'], 'amount');
            if (array_key_exists('payment_status', $d)) $fields['payment_status'] = validate_enum($d['payment_status'], PAY_STATUSES, 'payment_status');
            if (array_key_exists('payment_method', $d)) {
                $m = nullable($d['payment_method']);
                $fields['payment_method'] = $m !== null ? validate_enum($m, PAY_METHODS, 'payment_method') : null;
            }
            if (array_key_exists('bill_date', $d))      $fields['bill_date']      = validate_date($d['bill_date'], 'bill_date');

            // Check the final state (new values merged over the current ones)
            $finalStatus = $fields['payment_status'] ?? $current['payment_status'];
            $finalMethod = array_key_exists('payment_method', $fields) ? $fields['payment_method'] : $current['payment_method'];
            check_method_required($finalStatus, $finalMethod);

            update_record($pdo, 'Billing', 'bill_id', $id, $fields);
            json_response(['message' => 'Bill updated']);

        case 'DELETE':
            $id = get_id();
            if ($id === null) {
                error_response('id is required', 400);
            }
            delete_record($pdo, 'Billing', 'bill_id', $id, 'Bill');

        default:
            error_response('Method not allowed', 405);
    }
} catch (PDOException $e) {
    handle_pdo_exception($e);
}