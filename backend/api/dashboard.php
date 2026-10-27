<?php
// =====================================================
// Dashboard API (read-only)
//   GET dashboard.php  -> everything the dashboard page needs in one response
//
// "collected" = bills marked Paid.
// "outstanding" = bills marked Pending or Partial (the full bill amount).
// =====================================================
require_once __DIR__ . '/../includes/helpers.php';
$method = init_api();
require_once __DIR__ . '/../config/db.php';

if ($method !== 'GET') {
    error_response('Method not allowed', 405);
}

$apptSelect = "SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.reason, a.status,
                      p.name AS pet_name, p.species,
                      o.name AS owner_name,
                      v.name AS vet_name
               FROM Appointment a
               JOIN Pet   p ON a.pet_id   = p.pet_id
               JOIN Owner o ON p.owner_id = o.owner_id
               JOIN Vet   v ON a.vet_id   = v.vet_id";

try {
    $counts = $pdo->query(
        "SELECT
            (SELECT COUNT(*) FROM Owner) AS total_owners,
            (SELECT COUNT(*) FROM Pet)   AS total_pets,
            (SELECT COUNT(*) FROM Vet)   AS total_vets,
            (SELECT COUNT(*) FROM Appointment WHERE status = 'Scheduled') AS scheduled_appointments,
            (SELECT COUNT(*) FROM Appointment WHERE appointment_date = CURDATE()) AS appointments_today"
    )->fetch();
    foreach ($counts as $k => $v) {
        $counts[$k] = (int)$v;
    }

    $today = $pdo->query(
        "$apptSelect WHERE a.appointment_date = CURDATE() ORDER BY a.appointment_time"
    )->fetchAll();

    $upcoming = $pdo->query(
        "$apptSelect
         WHERE a.status = 'Scheduled' AND a.appointment_date >= CURDATE()
         ORDER BY a.appointment_date, a.appointment_time
         LIMIT 5"
    )->fetchAll();

    $recent = $pdo->query(
        "$apptSelect ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT 5"
    )->fetchAll();

    $statusBreakdown = $pdo->query(
        "SELECT status, COUNT(*) AS total FROM Appointment GROUP BY status"
    )->fetchAll();

    $speciesBreakdown = $pdo->query(
        "SELECT species, COUNT(*) AS total FROM Pet GROUP BY species ORDER BY total DESC"
    )->fetchAll();

    $revenue = $pdo->query(
        "SELECT
            COALESCE(SUM(CASE WHEN payment_status = 'Paid' THEN amount END), 0) AS collected,
            COALESCE(SUM(CASE WHEN payment_status IN ('Pending', 'Partial') THEN amount END), 0) AS outstanding
         FROM Billing"
    )->fetch();
    $revenue = ['collected' => (float)$revenue['collected'], 'outstanding' => (float)$revenue['outstanding']];

    $vetRevenue = $pdo->query(
        "SELECT v.vet_id, v.name AS vet_name,
                COUNT(DISTINCT a.appointment_id) AS completed_appointments,
                COALESCE(SUM(t.cost), 0) AS total_revenue
         FROM Vet v
         LEFT JOIN Appointment a ON a.vet_id = v.vet_id AND a.status = 'Completed'
         LEFT JOIN Treatment  t ON t.appointment_id = a.appointment_id
         GROUP BY v.vet_id, v.name
         ORDER BY total_revenue DESC"
    )->fetchAll();

    $monthly = $pdo->query(
        "SELECT DATE_FORMAT(bill_date, '%Y-%m') AS month, SUM(amount) AS revenue
         FROM Billing
         WHERE bill_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY DATE_FORMAT(bill_date, '%Y-%m')
         ORDER BY month"
    )->fetchAll();

    json_response([
        'counts'                => $counts,
        'revenue'               => $revenue,
        'appointments_today'    => $today,
        'upcoming_appointments' => $upcoming,
        'recent_appointments'   => $recent,
        'appointments_by_status' => $statusBreakdown,
        'pets_by_species'       => $speciesBreakdown,
        'vet_revenue'           => $vetRevenue,
        'monthly_revenue'       => $monthly,
    ]);
} catch (PDOException $e) {
    handle_pdo_exception($e);
}