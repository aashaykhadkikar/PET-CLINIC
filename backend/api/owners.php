<?php
// =====================================================
// Owners API
//   GET    owners.php            list (optional ?search=)
//   GET    owners.php?id=1       one owner + their pets
//   POST   owners.php            create   {name, phone, email?, city?}
//   PUT    owners.php?id=1       update any of the fields above
//   DELETE owners.php?id=1       delete (also deletes their pets)
// =====================================================
require_once __DIR__ . '/../includes/helpers.php';
$method = init_api();
require_once __DIR__ . '/../config/db.php';

try {
    switch ($method) {
        case 'GET':
            $id = get_id();
            if ($id !== null) {
                $stmt = $pdo->prepare("SELECT * FROM Owner WHERE owner_id = ?");
                $stmt->execute([$id]);
                $owner = $stmt->fetch();
                if (!$owner) {
                    error_response('Owner not found', 404);
                }
                $pets = $pdo->prepare("SELECT pet_id, name, species, breed, dob FROM Pet WHERE owner_id = ? ORDER BY name");
                $pets->execute([$id]);
                $owner['pets'] = $pets->fetchAll();
                json_response($owner);
            }

            $sql = "SELECT o.*, COUNT(p.pet_id) AS pet_count
                    FROM Owner o
                    LEFT JOIN Pet p ON p.owner_id = o.owner_id";
            $params = [];
            $search = trim($_GET['search'] ?? '');
            if ($search !== '') {
                $like = "%$search%";
                $sql .= " WHERE o.name LIKE ? OR o.phone LIKE ? OR o.email LIKE ? OR o.city LIKE ?";
                $params = [$like, $like, $like, $like];
            }
            $sql .= " GROUP BY o.owner_id ORDER BY o.name";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            json_response($stmt->fetchAll());

        case 'POST':
            $d = get_input();
            require_fields($d, ['name', 'phone']);
            $name  = validate_text($d['name'], 'name', 100);
            $phone = validate_phone($d['phone']);
            $email = nullable($d['email'] ?? null);
            $email = $email !== null ? validate_email($email) : null;
            $city  = nullable($d['city'] ?? null);

            $stmt = $pdo->prepare("INSERT INTO Owner (name, phone, email, city) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $phone, $email, $city]);
            json_response(['message' => 'Owner created', 'owner_id' => (int)$pdo->lastInsertId()], 201);

        case 'PUT':
            $id = get_id();
            if ($id === null) {
                error_response('id is required', 400);
            }
            if (!record_exists($pdo, 'Owner', 'owner_id', $id)) {
                error_response('Owner not found', 404);
            }
            $d = get_input();
            $fields = [];
            if (array_key_exists('name', $d))  $fields['name']  = validate_text($d['name'], 'name', 100);
            if (array_key_exists('phone', $d)) $fields['phone'] = validate_phone($d['phone']);
            if (array_key_exists('email', $d)) {
                $e = nullable($d['email']);
                $fields['email'] = $e !== null ? validate_email($e) : null;
            }
            if (array_key_exists('city', $d))  $fields['city']  = nullable($d['city']);

            update_record($pdo, 'Owner', 'owner_id', $id, $fields);
            json_response(['message' => 'Owner updated']);

        case 'DELETE':
            $id = get_id();
            if ($id === null) {
                error_response('id is required', 400);
            }
            delete_record($pdo, 'Owner', 'owner_id', $id, 'Owner');

        default:
            error_response('Method not allowed', 405);
    }
} catch (PDOException $e) {
    handle_pdo_exception($e);
}