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
