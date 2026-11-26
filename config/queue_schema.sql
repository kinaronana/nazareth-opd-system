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
