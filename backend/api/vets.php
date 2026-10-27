<?php
// =====================================================
// Vets API
//   GET    vets.php            list (optional ?search=)
//   GET    vets.php?id=1       one vet
//   POST   vets.php            create {name, specialization, phone?, email?}
//   PUT    vets.php?id=1       update any of the fields above
//   DELETE vets.php?id=1       delete (blocked if the vet has appointments)
// =====================================================
require_once __DIR__ . '/../includes/helpers.php';
$method = init_api();
require_once __DIR__ . '/../config/db.php';

try {
    switch ($method) {
        case 'GET':
            $id = get_id();
            if ($id !== null) {
                $stmt = $pdo->prepare("SELECT * FROM Vet WHERE vet_id = ?");
                $stmt->execute([$id]);
                $vet = $stmt->fetch();
                if (!$vet) {
                    error_response('Vet not found', 404);
                }
                json_response($vet);
            }

            $sql = "SELECT v.*, COUNT(a.appointment_id) AS appointment_count
                    FROM Vet v
                    LEFT JOIN Appointment a ON a.vet_id = v.vet_id";
            $params = [];
            $search = trim($_GET['search'] ?? '');
            if ($search !== '') {
                $like = "%$search%";
                $sql .= " WHERE v.name LIKE ? OR v.specialization LIKE ?";
                $params = [$like, $like];
            }
            $sql .= " GROUP BY v.vet_id ORDER BY v.name";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            json_response($stmt->fetchAll());

        case 'POST':
            $d = get_input();
            require_fields($d, ['name', 'specialization']);
            $name  = validate_text($d['name'], 'name', 100);
            $spec  = validate_text($d['specialization'], 'specialization', 100);
            $phone = nullable($d['phone'] ?? null);
            $phone = $phone !== null ? validate_phone($phone) : null;
            $email = nullable($d['email'] ?? null);
            $email = $email !== null ? validate_email($email) : null;

            $stmt = $pdo->prepare("INSERT INTO Vet (name, specialization, phone, email) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $spec, $phone, $email]);
            json_response(['message' => 'Vet created', 'vet_id' => (int)$pdo->lastInsertId()], 201);

        case 'PUT':
            $id = get_id();
            if ($id === null) {
                error_response('id is required', 400);
            }
            if (!record_exists($pdo, 'Vet', 'vet_id', $id)) {
                error_response('Vet not found', 404);
            }
            $d = get_input();
            $fields = [];
            if (array_key_exists('name', $d))           $fields['name']           = validate_text($d['name'], 'name', 100);
            if (array_key_exists('specialization', $d)) $fields['specialization'] = validate_text($d['specialization'], 'specialization', 100);
            if (array_key_exists('phone', $d)) {
                $p = nullable($d['phone']);
                $fields['phone'] = $p !== null ? validate_phone($p) : null;
            }
            if (array_key_exists('email', $d)) {
                $e = nullable($d['email']);
                $fields['email'] = $e !== null ? validate_email($e) : null;
            }

            update_record($pdo, 'Vet', 'vet_id', $id, $fields);
            json_response(['message' => 'Vet updated']);

        case 'DELETE':
            $id = get_id();
            if ($id === null) {
                error_response('id is required', 400);
            }
            delete_record($pdo, 'Vet', 'vet_id', $id, 'Vet');

        default:
            error_response('Method not allowed', 405);
    }
} catch (PDOException $e) {
    handle_pdo_exception($e);
}