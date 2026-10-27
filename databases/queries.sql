-- =====================================================
-- Pet Clinic Management System - Queries
-- Covers: joins, aggregates, subqueries, views, transactions
-- =====================================================

USE petclinic;

-- -----------------------------------------------------
-- A. VIEWS (reusable by the PHP API)
-- -----------------------------------------------------

CREATE OR REPLACE VIEW v_appointment_details AS
SELECT a.appointment_id,
       a.appointment_date,
       a.appointment_time,
       a.reason,
       a.status,
       p.pet_id,
       p.name  AS pet_name,
       p.species,
       o.owner_id,
       o.name  AS owner_name,
       o.phone AS owner_phone,
       v.vet_id,
       v.name  AS vet_name,
       v.specialization
FROM Appointment a
JOIN Pet   p ON a.pet_id   = p.pet_id
JOIN Owner o ON p.owner_id = o.owner_id
JOIN Vet   v ON a.vet_id   = v.vet_id;

CREATE OR REPLACE VIEW v_pet_owner AS
SELECT p.pet_id, p.name AS pet_name, p.species, p.breed, p.dob,
       o.owner_id, o.name AS owner_name, o.phone, o.email, o.city
FROM Pet p
JOIN Owner o ON p.owner_id = o.owner_id;

CREATE OR REPLACE VIEW v_vet_revenue AS
SELECT v.vet_id, v.name AS vet_name,
       COUNT(DISTINCT a.appointment_id) AS completed_appointments,
       COALESCE(SUM(t.cost), 0)         AS total_revenue
FROM Vet v
LEFT JOIN Appointment a ON a.vet_id = v.vet_id AND a.status = 'Completed'
LEFT JOIN Treatment  t ON t.appointment_id = a.appointment_id
GROUP BY v.vet_id, v.name;

-- -----------------------------------------------------
-- B. DASHBOARD QUERIES
-- -----------------------------------------------------

-- Summary counts
SELECT
  (SELECT COUNT(*) FROM Owner) AS total_owners,
  (SELECT COUNT(*) FROM Pet)   AS total_pets,
  (SELECT COUNT(*) FROM Vet)   AS total_vets,
  (SELECT COUNT(*) FROM Appointment WHERE status = 'Scheduled') AS upcoming_appointments;

-- Today's appointments
SELECT * FROM v_appointment_details
WHERE appointment_date = CURDATE()
ORDER BY appointment_time;

-- Upcoming appointments
SELECT * FROM v_appointment_details
WHERE status = 'Scheduled' AND appointment_date >= CURDATE()
ORDER BY appointment_date, appointment_time;

-- Total revenue collected vs pending
SELECT payment_status, COUNT(*) AS bills, SUM(amount) AS total_amount
FROM Billing
GROUP BY payment_status;

-- -----------------------------------------------------
-- C. JOINS
-- -----------------------------------------------------

-- All pets with their owners
SELECT * FROM v_pet_owner ORDER BY owner_name, pet_name;

-- Full visit history of one pet (change pet_id)
SELECT a.appointment_date, v.name AS vet, a.reason, t.diagnosis, t.notes, t.cost
FROM Appointment a
JOIN Vet v ON a.vet_id = v.vet_id
LEFT JOIN Treatment t ON t.appointment_id = a.appointment_id
WHERE a.pet_id = 1
ORDER BY a.appointment_date DESC;

-- Pets that have never had an appointment
SELECT p.pet_id, p.name
FROM Pet p
LEFT JOIN Appointment a ON a.pet_id = p.pet_id
WHERE a.appointment_id IS NULL;

-- -----------------------------------------------------
-- D. AGGREGATES AND SUBQUERIES
-- -----------------------------------------------------

-- Number of pets per owner
SELECT o.name AS owner, COUNT(p.pet_id) AS pets
FROM Owner o
LEFT JOIN Pet p ON p.owner_id = o.owner_id
GROUP BY o.owner_id, o.name
ORDER BY pets DESC;

-- Pets per species
SELECT species, COUNT(*) AS total FROM Pet GROUP BY species ORDER BY total DESC;

-- Revenue by vet
SELECT * FROM v_vet_revenue ORDER BY total_revenue DESC;

-- Owners who have unpaid bills
SELECT DISTINCT o.name, o.phone
FROM Owner o
WHERE o.owner_id IN (
  SELECT p.owner_id
  FROM Pet p
  JOIN Appointment a ON a.pet_id = p.pet_id
  JOIN Billing b     ON b.appointment_id = a.appointment_id
  WHERE b.payment_status IN ('Pending','Partial')
);

-- Treatments costing more than the average
SELECT * FROM Treatment
WHERE cost > (SELECT AVG(cost) FROM Treatment);

-- Monthly revenue
SELECT DATE_FORMAT(bill_date, '%Y-%m') AS month, SUM(amount) AS revenue
FROM Billing
GROUP BY month
ORDER BY month;

-- -----------------------------------------------------
-- E. SEARCH (the PHP API will use prepared statements for these)
-- -----------------------------------------------------

SELECT * FROM v_pet_owner
WHERE pet_name LIKE '%bru%' OR owner_name LIKE '%bru%';

-- -----------------------------------------------------
-- F. TRANSACTION: complete an appointment, add treatment + bill atomically
-- -----------------------------------------------------

START TRANSACTION;

UPDATE Appointment SET status = 'Completed' WHERE appointment_id = 7;

INSERT INTO Treatment (appointment_id, diagnosis, notes, cost)
VALUES (7, 'Healthy', 'Routine checkup, no issues found.', 800.00);

INSERT INTO Billing (appointment_id, amount, payment_status, payment_method, bill_date)
VALUES (7, 800.00, 'Paid', 'UPI', CURDATE());

COMMIT;
-- Use ROLLBACK; instead of COMMIT; if anything fails.

-- -----------------------------------------------------
-- G. TRIGGER: keep Billing amount in sync when a treatment cost changes
-- -----------------------------------------------------

DROP TRIGGER IF EXISTS trg_treatment_cost_update;

DELIMITER $$
CREATE TRIGGER trg_treatment_cost_update
AFTER UPDATE ON Treatment
FOR EACH ROW
BEGIN
  IF NEW.cost <> OLD.cost THEN
    UPDATE Billing
    SET amount = (SELECT COALESCE(SUM(cost), 0)
                  FROM Treatment
                  WHERE appointment_id = NEW.appointment_id)
    WHERE appointment_id = NEW.appointment_id;
  END IF;
END$$
DELIMITER ;