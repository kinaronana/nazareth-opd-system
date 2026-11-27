-- Deployment-readiness migration: five feature tables absent from the inspected
-- InfinityFree export dated 2026-11-26. Take a fresh full backup before running.
-- Do NOT rerun schema_additions.sql: its seven tables already exist.
-- Nazareth OPD smart queue management
-- Run once in phpMyAdmin against the application database before using the queue.
CREATE TABLE IF NOT EXISTS patient_queue (
    queue_id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    queue_date DATE NOT NULL,
    queue_number INT NOT NULL,
    status ENUM('Waiting','Called','In Consultation','Completed','Cancelled') NOT NULL DEFAULT 'Waiting',
    priority ENUM('Normal','Urgent') NOT NULL DEFAULT 'Normal',
    priority_set_by INT NULL,
    checked_in_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    called_at DATETIME NULL,
    consultation_started_at DATETIME NULL,
    completed_at DATETIME NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_queue_appointment (appointment_id),
    UNIQUE KEY uq_doctor_daily_number (doctor_id, queue_date, queue_number),
    KEY idx_queue_live (doctor_id, queue_date, status, priority, queue_number),
    KEY idx_patient_queue (patient_id, queue_date),
    CONSTRAINT fk_queue_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE CASCADE,
    CONSTRAINT fk_queue_patient FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
    CONSTRAINT fk_queue_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE CASCADE,
    CONSTRAINT fk_queue_priority_user FOREIGN KEY (priority_set_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS patient_ai_conversations (
 conversation_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 patient_id INT NOT NULL, user_id INT NOT NULL,
 title VARCHAR(120) NOT NULL DEFAULT 'New conversation',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_ai_owner (user_id, patient_id, updated_at),
 CONSTRAINT fk_ai_patient FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
 CONSTRAINT fk_ai_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS patient_ai_messages (
 message_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 conversation_id BIGINT UNSIGNED NOT NULL,
 sender ENUM('patient','assistant') NOT NULL,
 message_text TEXT NOT NULL, safety_flag VARCHAR(40) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_ai_messages (conversation_id, message_id),
 CONSTRAINT fk_ai_conversation FOREIGN KEY (conversation_id) REFERENCES patient_ai_conversations(conversation_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS doctor_ai_drafts (
 draft_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 doctor_id INT NOT NULL,
 patient_id INT NOT NULL,
 appointment_id INT NOT NULL,
 draft_type ENUM('History Summary','Consultation Note','Follow-up Summary') NOT NULL,
 source_text MEDIUMTEXT NOT NULL,
 draft_text MEDIUMTEXT NOT NULL,
 status ENUM('Draft','Reviewed','Approved') NOT NULL DEFAULT 'Draft',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 reviewed_at TIMESTAMP NULL,
 approved_at TIMESTAMP NULL,
 medical_record_id INT NULL UNIQUE,
 KEY idx_doctor_ai_owner (doctor_id, patient_id, created_at),
 KEY idx_doctor_ai_appointment (appointment_id, status),
 CONSTRAINT fk_doctor_ai_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE CASCADE,
 CONSTRAINT fk_doctor_ai_patient FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
 CONSTRAINT fk_doctor_ai_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE CASCADE,
 CONSTRAINT fk_doctor_ai_medical_record FOREIGN KEY (medical_record_id) REFERENCES medical_records(record_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS doctor_ai_audit (
 audit_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 draft_id BIGINT UNSIGNED NOT NULL,
 doctor_id INT NOT NULL,
 action ENUM('Created','Reviewed','Approved') NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_doctor_ai_audit (doctor_id, draft_id, created_at),
 CONSTRAINT fk_doctor_ai_audit_draft FOREIGN KEY (draft_id) REFERENCES doctor_ai_drafts(draft_id) ON DELETE CASCADE,
 CONSTRAINT fk_doctor_ai_audit_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Required for Unicode AI-generated notes in medical_records.description.
-- DDL auto-commits; this cannot be rolled back with ROLLBACK.
ALTER TABLE medical_records CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
