<?php
// =====================================================
// Appointments API
//   GET    appointments.php              list. Filters: ?status= &date= &vet_id= &pet_id= &upcoming=1
//   GET    appointments.php?id=1         one appointment
//   POST   appointments.php              create {pet_id, vet_id, appointment_date, appointment_time, reason?, status?}
//   PUT    appointments.php?id=1         update any of the fields above
//   DELETE appointments.php?id=1         delete (also deletes its treatment and bill)
// =====================================================
require_once __DIR__ . '/../includes/helpers.php';
$method = init_api();
require_once __DIR__ . '/../config/db.php';

const APPT_STATUSES = ['Scheduled', 'Completed', 'Cancelled'];

$baseSql = "SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.reason, a.status,
                   p.pet_id, p.name AS pet_name, p.species,
                   o.owner_id, o.name AS owner_name, o.phone AS owner_phone,
                   v.vet_id, v.name AS vet_name, v.specialization
            FROM Appointment a
            JOIN Pet   p ON a.pet_id   = p.pet_id
            JOIN Owner o ON p.owner_id = o.owner_id
            JOIN Vet   v ON a.vet_id   = v.vet_id";

try {
    switch ($method) {
        case 'GET':
            $id = get_id();
            if ($id !== null) {
                $stmt = $pdo->prepare("$baseSql WHERE a.appointment_id = ?");
                $stmt->execute([$id]);
                $row = $stmt->fetch();
                if (!$row) {
                    error_response('Appointment not found', 404);
                }
                json_response($row);
            }

            $where = [];
            $params = [];
            if (!empty($_GET['status'])) {
                $where[] = "a.status = ?";
                $params[] = validate_enum($_GET['status'], APPT_STATUSES, 'status');
            }
            if (!empty($_GET['date'])) {
                $where[] = "a.appointment_date = ?";
                $params[] = validate_date($_GET['date'], 'date');
            }
            if (($vetId = get_int_param('vet_id')) !== null) {
                $where[] = "a.vet_id = ?";
                $params[] = $vetId;
            }
            if (($petId = get_int_param('pet_id')) !== null) {
                $where[] = "a.pet_id = ?";
                $params[] = $petId;
            }
            if (!empty($_GET['upcoming'])) {
                $where[] = "a.status = 'Scheduled' AND a.appointment_date >= CURDATE()";
            }
            $sql = $baseSql . ($where ? " WHERE " . implode(' AND ', $where) : "")
                 . " ORDER BY a.appointment_date, a.appointment_time";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            json_response($stmt->fetchAll());

        case 'POST':
            $d = get_input();
            require_fields($d, ['pet_id', 'vet_id', 'appointment_date', 'appointment_time']);
            $petId  = validate_id($d['pet_id'], 'pet_id');
            $vetId  = validate_id($d['vet_id'], 'vet_id');
            $date   = validate_date($d['appointment_date'], 'appointment_date');
            $time   = validate_time($d['appointment_time'], 'appointment_time');
            $reason = nullable($d['reason'] ?? null);
            $status = validate_enum($d['status'] ?? 'Scheduled', APPT_STATUSES, 'status');

            $stmt = $pdo->prepare(
                "INSERT INTO Appointment (pet_id, vet_id, appointment_date, appointment_time, reason, status)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$petId, $vetId, $date, $time, $reason, $status]);
            json_response(['message' => 'Appointment created', 'appointment_id' => (int)$pdo->lastInsertId()], 201);

        case 'PUT':
            $id = get_id();
            if ($id === null) {
                error_response('id is required', 400);
            }
            if (!record_exists($pdo, 'Appointment', 'appointment_id', $id)) {
                error_response('Appointment not found', 404);
            }
            $d = get_input();
            $fields = [];
            if (array_key_exists('pet_id', $d))           $fields['pet_id']           = validate_id($d['pet_id'], 'pet_id');
            if (array_key_exists('vet_id', $d))           $fields['vet_id']           = validate_id($d['vet_id'], 'vet_id');
            if (array_key_exists('appointment_date', $d)) $fields['appointment_date'] = validate_date($d['appointment_date'], 'appointment_date');
            if (array_key_exists('appointment_time', $d)) $fields['appointment_time'] = validate_time($d['appointment_time'], 'appointment_time');
            if (array_key_exists('reason', $d))           $fields['reason']           = nullable($d['reason']);
            if (array_key_exists('status', $d))           $fields['status']           = validate_enum($d['status'], APPT_STATUSES, 'status');

            update_record($pdo, 'Appointment', 'appointment_id', $id, $fields);
            json_response(['message' => 'Appointment updated']);

        case 'DELETE':
            $id = get_id();
            if ($id === null) {
                error_response('id is required', 400);
            }
            delete_record($pdo, 'Appointment', 'appointment_id', $id, 'Appointment');

        default:
            error_response('Method not allowed', 405);
    }
} catch (PDOException $e) {
    handle_pdo_exception($e);
}