<?php
// =====================================================
// Pets API
//   GET    pets.php            list (optional ?search= &owner_id= &species=)
//   GET    pets.php?id=1       one pet
//   POST   pets.php            create {name, species, owner_id, breed?, dob?}
//   PUT    pets.php?id=1       update any of the fields above
//   DELETE pets.php?id=1       delete (also deletes its appointments)
// =====================================================
require_once __DIR__ . '/../includes/helpers.php';
$method = init_api();
require_once __DIR__ . '/../config/db.php';

function parse_dob($v): ?string
{
    $v = nullable($v);
    if ($v === null) {
        return null;
    }
    $v = validate_date($v, 'dob');
    if ($v > date('Y-m-d')) {
        error_response('dob cannot be in the future', 400);
    }
    return $v;
}

$baseSql = "SELECT p.pet_id, p.name, p.species, p.breed, p.dob, p.owner_id,
                   o.name AS owner_name, o.phone AS owner_phone
            FROM Pet p
            JOIN Owner o ON p.owner_id = o.owner_id";

try {
    switch ($method) {
        case 'GET':
            $id = get_id();
            if ($id !== null) {
                $stmt = $pdo->prepare("$baseSql WHERE p.pet_id = ?");
                $stmt->execute([$id]);
                $pet = $stmt->fetch();
                if (!$pet) {
                    error_response('Pet not found', 404);
                }
                json_response($pet);
            }

            $where = [];
            $params = [];
            $ownerId = get_int_param('owner_id');
            if ($ownerId !== null) {
                $where[] = "p.owner_id = ?";
                $params[] = $ownerId;
            }
            $species = trim($_GET['species'] ?? '');
            if ($species !== '') {
                $where[] = "p.species = ?";
                $params[] = $species;
            }
            $search = trim($_GET['search'] ?? '');
            if ($search !== '') {
                $like = "%$search%";
                $where[] = "(p.name LIKE ? OR p.breed LIKE ? OR o.name LIKE ?)";
                array_push($params, $like, $like, $like);
            }
            $sql = $baseSql . ($where ? " WHERE " . implode(' AND ', $where) : "") . " ORDER BY p.name";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            json_response($stmt->fetchAll());

        case 'POST':
            $d = get_input();
            require_fields($d, ['name', 'species', 'owner_id']);
            $name    = validate_text($d['name'], 'name', 50);
            $species = validate_text($d['species'], 'species', 30);
            $ownerId = validate_id($d['owner_id'], 'owner_id');
            $breed   = nullable($d['breed'] ?? null);
            $dob     = parse_dob($d['dob'] ?? null);

            $stmt = $pdo->prepare("INSERT INTO Pet (name, species, breed, dob, owner_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $species, $breed, $dob, $ownerId]);
            json_response(['message' => 'Pet created', 'pet_id' => (int)$pdo->lastInsertId()], 201);

        case 'PUT':
            $id = get_id();
            if ($id === null) {
                error_response('id is required', 400);
            }
            if (!record_exists($pdo, 'Pet', 'pet_id', $id)) {
                error_response('Pet not found', 404);
            }
            $d = get_input();
            $fields = [];
            if (array_key_exists('name', $d))     $fields['name']     = validate_text($d['name'], 'name', 50);
            if (array_key_exists('species', $d))  $fields['species']  = validate_text($d['species'], 'species', 30);
            if (array_key_exists('breed', $d))    $fields['breed']    = nullable($d['breed']);
            if (array_key_exists('dob', $d))      $fields['dob']      = parse_dob($d['dob']);
            if (array_key_exists('owner_id', $d)) $fields['owner_id'] = validate_id($d['owner_id'], 'owner_id');

            update_record($pdo, 'Pet', 'pet_id', $id, $fields);
            json_response(['message' => 'Pet updated']);

        case 'DELETE':
            $id = get_id();
            if ($id === null) {
                error_response('id is required', 400);
            }
            delete_record($pdo, 'Pet', 'pet_id', $id, 'Pet');

        default:
            error_response('Method not allowed', 405);
    }
} catch (PDOException $e) {
    handle_pdo_exception($e);
}