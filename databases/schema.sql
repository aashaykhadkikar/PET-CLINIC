-- =====================================================
-- Pet Clinic Management System - Schema (MySQL 8.0)
-- Run this file first.
-- =====================================================

CREATE DATABASE IF NOT EXISTS petclinic
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE petclinic;

-- Drop in reverse dependency order so the script can be re-run safely
DROP TABLE IF EXISTS Billing;
DROP TABLE IF EXISTS Treatment;
DROP TABLE IF EXISTS Appointment;
DROP TABLE IF EXISTS Pet;
DROP TABLE IF EXISTS Vet;
DROP TABLE IF EXISTS Owner;

-- 1. Owner
CREATE TABLE Owner (
  owner_id  INT AUTO_INCREMENT PRIMARY KEY,
  name      VARCHAR(100) NOT NULL,
  phone     VARCHAR(15)  NOT NULL,
  email     VARCHAR(100) UNIQUE,
  city      VARCHAR(50)
) ENGINE=InnoDB;

-- 2. Vet
CREATE TABLE Vet (
  vet_id          INT AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(100) NOT NULL,
  specialization  VARCHAR(100) NOT NULL,
  phone           VARCHAR(15),
  email           VARCHAR(100) UNIQUE
) ENGINE=InnoDB;

-- 3. Pet (belongs to an Owner)
CREATE TABLE Pet (
  pet_id    INT AUTO_INCREMENT PRIMARY KEY,
  name      VARCHAR(50)  NOT NULL,
  species   VARCHAR(30)  NOT NULL,
  breed     VARCHAR(50),
  dob       DATE,
  owner_id  INT NOT NULL,
  CONSTRAINT fk_pet_owner
    FOREIGN KEY (owner_id) REFERENCES Owner(owner_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 4. Appointment (a Pet sees a Vet)
CREATE TABLE Appointment (
  appointment_id    INT AUTO_INCREMENT PRIMARY KEY,
  pet_id            INT NOT NULL,
  vet_id            INT NOT NULL,
  appointment_date  DATE NOT NULL,
  appointment_time  TIME NOT NULL,
  reason            VARCHAR(255),
  status            ENUM('Scheduled','Completed','Cancelled') NOT NULL DEFAULT 'Scheduled',
  CONSTRAINT fk_appt_pet
    FOREIGN KEY (pet_id) REFERENCES Pet(pet_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_appt_vet
    FOREIGN KEY (vet_id) REFERENCES Vet(vet_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  -- A vet cannot be double-booked at the same date and time
  CONSTRAINT uq_vet_slot UNIQUE (vet_id, appointment_date, appointment_time)
) ENGINE=InnoDB;

-- 5. Treatment (recorded after an Appointment)
CREATE TABLE Treatment (
  treatment_id    INT AUTO_INCREMENT PRIMARY KEY,
  appointment_id  INT NOT NULL,
  diagnosis       VARCHAR(255) NOT NULL,
  notes           TEXT,
  cost            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  CONSTRAINT chk_treatment_cost CHECK (cost >= 0),
  CONSTRAINT fk_treatment_appt
    FOREIGN KEY (appointment_id) REFERENCES Appointment(appointment_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 6. Billing (one bill per Appointment)
CREATE TABLE Billing (
  bill_id         INT AUTO_INCREMENT PRIMARY KEY,
  appointment_id  INT NOT NULL UNIQUE,
  amount          DECIMAL(10,2) NOT NULL,
  payment_status  ENUM('Pending','Paid','Partial') NOT NULL DEFAULT 'Pending',
  payment_method  ENUM('Cash','Card','UPI') NULL,
  bill_date       DATE NOT NULL,
  CONSTRAINT chk_bill_amount CHECK (amount >= 0),
  CONSTRAINT fk_bill_appt
    FOREIGN KEY (appointment_id) REFERENCES Appointment(appointment_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Indexes for common lookups
CREATE INDEX idx_pet_owner       ON Pet(owner_id);
CREATE INDEX idx_appt_date       ON Appointment(appointment_date);
CREATE INDEX idx_appt_status     ON Appointment(status);
CREATE INDEX idx_treatment_appt  ON Treatment(appointment_id);