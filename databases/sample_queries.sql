-- =====================================================
-- Pet Clinic Management System - Sample Data
-- Run AFTER schema.sql. All data is fictional.
-- Order matters: parents first, then children.
-- =====================================================

USE petclinic;

-- 1. Owners
INSERT INTO Owner (name, phone, email, city) VALUES
('Rohan Mehta',   '9820011111', 'rohan.mehta@example.com',   'Mumbai'),
('Priya Nair',    '9833022222', 'priya.nair@example.com',    'Thane'),
('Aditya Sharma', '9867033333', 'aditya.sharma@example.com', 'Navi Mumbai'),
('Sneha Iyer',    '9892044444', 'sneha.iyer@example.com',    'Mumbai'),
('Farhan Khan',   '9930055555', 'farhan.khan@example.com',   'Pune'),
('Meera Joshi',   '9769066666', 'meera.joshi@example.com',   'Mumbai');

-- 2. Vets
INSERT INTO Vet (name, specialization, phone, email) VALUES
('Dr. Anjali Deshmukh', 'General Practice', '9820101010', 'anjali.d@petclinic.example'),
('Dr. Karan Shah',      'Surgery',          '9820202020', 'karan.s@petclinic.example'),
('Dr. Neha Kulkarni',   'Dermatology',      '9820303030', 'neha.k@petclinic.example'),
('Dr. Vikram Rao',      'Dentistry',        '9820404040', 'vikram.r@petclinic.example'),
('Dr. Sana Qureshi',    'Exotic Pets',      '9820505050', 'sana.q@petclinic.example');

-- 3. Pets
INSERT INTO Pet (name, species, breed, dob, owner_id) VALUES
('Bruno',  'Dog',    'Labrador Retriever', '2019-03-12', 1),
('Misty',  'Cat',    'Persian',            '2020-07-21', 2),
('Coco',   'Dog',    'Beagle',             '2021-01-15', 3),
('Simba',  'Cat',    'Domestic Shorthair', '2018-11-02', 4),
('Tweety', 'Bird',   'Budgerigar',         '2022-05-09', 5),
('Max',    'Dog',    'German Shepherd',    '2017-09-30', 1),
('Snowy',  'Rabbit', 'Holland Lop',        '2022-12-01', 6),
('Luna',   'Cat',    'Siamese',            '2023-02-14', 2);

-- 4. Appointments
INSERT INTO Appointment (pet_id, vet_id, appointment_date, appointment_time, reason, status) VALUES
(1, 1, '2026-10-05', '10:00:00', 'Annual vaccination',            'Completed'),
(2, 3, '2026-10-07', '11:30:00', 'Skin allergy',                  'Completed'),
(3, 1, '2026-10-10', '09:30:00', 'Limping on front leg',          'Completed'),
(4, 4, '2026-10-12', '15:00:00', 'Dental cleaning',               'Completed'),
(5, 5, '2026-10-15', '12:00:00', 'Feather plucking',              'Completed'),
(6, 2, '2026-10-20', '10:30:00', 'Hip dysplasia consultation',    'Completed'),
(7, 5, '2026-10-28', '16:00:00', 'Routine checkup',               'Scheduled'),
(8, 1, '2026-10-29', '10:00:00', 'Vaccination',                   'Scheduled'),
(1, 3, '2026-11-02', '14:00:00', 'Ear infection',                 'Scheduled'),
(3, 2, '2026-10-18', '11:00:00', 'Follow-up X-ray',               'Cancelled');

-- 5. Treatments (only for completed appointments)
INSERT INTO Treatment (appointment_id, diagnosis, notes, cost) VALUES
(1, 'Healthy - routine vaccination', 'DHPP and rabies booster given. Next due in 12 months.', 1200.00),
(2, 'Allergic dermatitis',           'Medicated shampoo prescribed. Review if no improvement in 2 weeks.', 1800.00),
(3, 'Mild ligament sprain',          'Rest for 10 days and anti-inflammatory course.', 1500.00),
(4, 'Tartar buildup',                'Scaling and polishing done under light sedation.', 2500.00),
(5, 'Stress-related feather plucking','Diet and cage environment changes advised.', 900.00),
(6, 'Early hip dysplasia',           'Joint supplements started. Weight management plan given.', 3000.00);

-- 6. Billing (one bill per completed appointment)
INSERT INTO Billing (appointment_id, amount, payment_status, payment_method, bill_date) VALUES
(1, 1200.00, 'Paid',    'UPI',  '2026-10-05'),
(2, 1800.00, 'Paid',    'Card', '2026-10-07'),
(3, 1500.00, 'Pending',  NULL,  '2026-10-10'),
(4, 2500.00, 'Paid',    'Cash', '2026-10-12'),
(5,  900.00, 'Partial', 'UPI',  '2026-10-15'),
(6, 3000.00, 'Pending',  NULL,  '2026-10-20');