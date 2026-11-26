-- =========================================================================
-- Nazareth OPD - Schema additions for the full patient dashboard
-- Run this once in phpMyAdmin (SQL tab) against your existing database.
-- Safe to re-run: uses CREATE TABLE IF NOT EXISTS throughout.
-- =========================================================================

-- Medical Records: notes/diagnoses a doctor attaches to a patient
CREATE TABLE IF NOT EXISTS medical_records (
    record_id      INT AUTO_INCREMENT PRIMARY KEY,
    patient_id     INT NOT NULL,
    doctor_id      INT NOT NULL,
    appointment_id INT NULL,
    record_type    VARCHAR(50) NOT NULL DEFAULT 'Consultation Note',
    title          VARCHAR(150) NOT NULL,
    description    TEXT NOT NULL,
    record_date    DATE NOT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Prescriptions: medication issued by a doctor to a patient
CREATE TABLE IF NOT EXISTS prescriptions (
    prescription_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id      INT NOT NULL,
    doctor_id       INT NOT NULL,
    appointment_id  INT NULL,
    medication_name VARCHAR(150) NOT NULL,
    dosage          VARCHAR(100) NOT NULL,
    frequency       VARCHAR(100) NOT NULL,
    duration        VARCHAR(100) NOT NULL,
    notes           TEXT NULL,
    issued_date     DATE NOT NULL,
    status          ENUM('Active','Completed') NOT NULL DEFAULT 'Active',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Test Results: lab/diagnostic results, optionally with an uploaded file
CREATE TABLE IF NOT EXISTS test_results (
    result_id       INT AUTO_INCREMENT PRIMARY KEY,
    patient_id      INT NOT NULL,
    doctor_id       INT NOT NULL,
    appointment_id  INT NULL,
    test_name       VARCHAR(150) NOT NULL,
    result_summary  TEXT NOT NULL,
    file_path       VARCHAR(255) NULL,
    result_date     DATE NOT NULL,
    status          ENUM('Pending','Completed') NOT NULL DEFAULT 'Completed',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Invoices: billing records tied to a consultation
CREATE TABLE IF NOT EXISTS invoices (
    invoice_id     INT AUTO_INCREMENT PRIMARY KEY,
    patient_id     INT NOT NULL,
    appointment_id INT NULL,
    description    VARCHAR(255) NOT NULL,
    amount         DECIMAL(10,2) NOT NULL,
    status         ENUM('Unpaid','Paid') NOT NULL DEFAULT 'Unpaid',
    issued_date    DATE NOT NULL,
    paid_date      DATE NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Messages: simple inbox between patients, doctors, and admins (by user_id)
CREATE TABLE IF NOT EXISTS messages (
    message_id  INT AUTO_INCREMENT PRIMARY KEY,
    sender_id   INT NOT NULL,
    receiver_id INT NOT NULL,
    subject     VARCHAR(150) NOT NULL,
    body        TEXT NOT NULL,
    is_read     TINYINT(1) NOT NULL DEFAULT 0,
    sent_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Notifications: system-generated alerts per user (by user_id)
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    message         VARCHAR(255) NOT NULL,
    link            VARCHAR(255) NULL,
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Activity Log: feeds the "Recent Activity" widget on the patient dashboard
CREATE TABLE IF NOT EXISTS activity_log (
    activity_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    icon        VARCHAR(30) NOT NULL DEFAULT 'circle-info',
    description VARCHAR(255) NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;
