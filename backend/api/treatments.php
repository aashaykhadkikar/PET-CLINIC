<?php
// =====================================================
// Treatments API
//   GET    treatments.php              list. Filters: ?appointment_id= &pet_id=
//   GET    treatments.php?id=1         one treatment
//   POST   treatments.php              create {appointment_id, diagnosis, cost, notes?}
//   PUT    treatments.php?id=1         update {diagnosis?, notes?, cost?}
//   DELETE treatments.php?id=1         delete
//
// Side effects (done inside one transaction):
//   POST   -> marks the appointment Completed and creates/updates its bill
//   PUT    -> re-syncs the bill amount if a bill exists
//   DELETE -> re-syncs the bill amount if a bill exists
// =====================================================
require_once __DIR__ . '/../includes/helpers.php';
$method = init_api();
require_once __DIR__ . '/../config/db.php';

/** Bill amount = sum of all treatment costs for the appointment. */
function sync_bill(PDO $pdo, int $appointmentId, bool $createIfMissing): void
{
    $s = $pdo->prepare("SELECT COALESCE(SUM(cost), 0) FROM Treatment WHERE appointment_id = ?");
    $s->execute([$appointmentId]);
    $total = $s->fetchColumn();

    $c = $pdo->prepare("SELECT bill_id FROM Billing WHERE appointment_id = ?");
    $c->execute([$appointmentId]);

    if ($c->fetchColumn()) {
        $pdo->prepare("UPDATE Billing SET amount = ? WHERE appointment_id = ?")
            ->execute([$total, $appointmentId]);
    } elseif ($createIfMissing) {
        $pdo->prepare(
            "INSERT INTO Billing (appointment_id, amount, payment_status, bill_date)
             VALUES (?, ?, 'Pending', CURDATE())"
        )->execute([$appointmentId, $total]);
    }
}

$baseSql = "SELECT t.treatment_id, t.appointment_id, t.diagnosis, t.notes, t.cost,
                   a.appointment_date, a.reason,
                   p.pet_id, p.name AS pet_name,
                   o.name AS owner_name,
                   v.name AS vet_name
            FROM Treatment t
            JOIN Appointment a ON t.appointment_id = a.appointment_id
            JOIN Pet   p ON a.pet_id   = p.pet_id
            JOIN Owner o ON p.owner_id = o.owner_id
            JOIN Vet   v ON a.vet_id   = v.vet_id";

try {
    switch ($method) {
        case 'GET':
            $id = get_id();
            if ($id !== null) {
                $stmt = $pdo->prepare("$baseSql WHERE t.treatment_id = ?");
                $stmt->execute([$id]);
                $row = $stmt->fetch();
                if (!$row) {
                    error_response('Treatment not found', 404);
                }
                json_response($row);
            }

            $where = [];
            $params = [];
            if (($apptId = get_int_param('appointment_id')) !== null) {
                $where[] = "t.appointment_id = ?";
                $params[] = $apptId;
            }
            if (($petId = get_int_param('pet_id')) !== null) {
                $where[] = "a.pet_id = ?";
                $params[] = $petId;
            }
            $sql = $baseSql . ($where ? " WHERE " . implode(' AND ', $where) : "")
                 . " ORDER BY a.appointment_date DESC, t.treatment_id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            json_response($stmt->fetchAll());

        case 'POST':
            $d = get_input();
            require_fields($d, ['appointment_id', 'diagnosis', 'cost']);
            $apptId    = validate_id($d['appointment_id'], 'appointment_id');
            $diagnosis = validate_text($d['diagnosis'], 'diagnosis', 255);
            $cost      = validate_money($d['cost'], 'cost');
            $notes     = nullable($d['notes'] ?? null);

            $s = $pdo->prepare("SELECT status FROM Appointment WHERE appointment_id = ?");
            $s->execute([$apptId]);
            $status = $s->fetchColumn();
            if ($status === false) {
                error_response('Appointment not found', 404);
            }
            if ($status === 'Cancelled') {
                error_response('Cannot add a treatment to a cancelled appointment', 400);
            }

            $pdo->beginTransaction();
            try {
                $pdo->prepare("INSERT INTO Treatment (appointment_id, diagnosis, notes, cost) VALUES (?, ?, ?, ?)")
                    ->execute([$apptId, $diagnosis, $notes, $cost]);
                $newId = (int)$pdo->lastInsertId();

                $pdo->prepare("UPDATE Appointment SET status = 'Completed' WHERE appointment_id = ?")
                    ->execute([$apptId]);
                sync_bill($pdo, $apptId, true);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
            json_response(['message' => 'Treatment recorded', 'treatment_id' => $newId], 201);

        case 'PUT':
            $id = get_id();
            if ($id === null) {
                error_response('id is required', 400);
            }
            $s = $pdo->prepare("SELECT appointment_id FROM Treatment WHERE treatment_id = ?");
            $s->execute([$id]);
            $apptId = $s->fetchColumn();
            if ($apptId === false) {
                error_response('Treatment not found', 404);
            }

            $d = get_input();
            $fields = [];
            if (array_key_exists('diagnosis', $d)) $fields['diagnosis'] = validate_text($d['diagnosis'], 'diagnosis', 255);
            if (array_key_exists('notes', $d))     $fields['notes']     = nullable($d['notes']);
            if (array_key_exists('cost', $d))      $fields['cost']      = validate_money($d['cost'], 'cost');

            $pdo->beginTransaction();
            try {
                update_record($pdo, 'Treatment', 'treatment_id', $id, $fields);
                sync_bill($pdo, (int)$apptId, false);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
            json_response(['message' => 'Treatment updated']);

        case 'DELETE':
            $id = get_id();
            if ($id === null) {
                error_response('id is required', 400);
            }
            $s = $pdo->prepare("SELECT appointment_id FROM Treatment WHERE treatment_id = ?");
            $s->execute([$id]);
            $apptId = $s->fetchColumn();
            if ($apptId === false) {
                error_response('Treatment not found', 404);
            }

            $pdo->beginTransaction();
            try {
                $pdo->prepare("DELETE FROM Treatment WHERE treatment_id = ?")->execute([$id]);
                sync_bill($pdo, (int)$apptId, false);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
            json_response(['message' => 'Treatment deleted']);

        default:
            error_response('Method not allowed', 405);
    }
} catch (PDOException $e) {
    handle_pdo_exception($e);
}