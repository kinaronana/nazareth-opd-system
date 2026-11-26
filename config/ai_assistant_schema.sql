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
