-- Test-only core schema. All records are fictional and created in a disposable CI database.
CREATE TABLE roles (
  role_id INT AUTO_INCREMENT PRIMARY KEY,
  role_name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  role_id INT NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'Active',
  FOREIGN KEY (role_id) REFERENCES roles(role_id)
) ENGINE=InnoDB;

CREATE TABLE departments (
  department_id INT AUTO_INCREMENT PRIMARY KEY,
  department_name VARCHAR(120) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE patients (
  patient_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  full_name VARCHAR(150) NOT NULL,
  gender VARCHAR(20) NOT NULL,
  dob DATE NOT NULL,
  phone VARCHAR(30) NOT NULL,
  address VARCHAR(255) NULL,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE doctors (
  doctor_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  department_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (department_id) REFERENCES departments(department_id)
) ENGINE=InnoDB;

CREATE TABLE appointments (
  appointment_id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  doctor_id INT NOT NULL,
  appointment_date DATE NOT NULL,
  appointment_time TIME NOT NULL,
  reason TEXT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'Pending',
  FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
  FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notifications (
  notification_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  message VARCHAR(255) NOT NULL,
  link VARCHAR(255) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE activity_log (
  activity_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  icon VARCHAR(30) NOT NULL DEFAULT 'circle-info',
  description VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO roles (role_name) VALUES ('Admin'), ('Doctor'), ('Patient');
INSERT INTO users (username,password,email,role_id,status) VALUES
('ci_admin','test-hash','admin@example.test',1,'Active'),
('ci_doctor','test-hash','doctor@example.test',2,'Active'),
('ci_patient','test-hash','patient@example.test',3,'Active'),
('ci_patient_two','test-hash','patient2@example.test',3,'Active');
INSERT INTO departments (department_name) VALUES ('General Outpatient');
INSERT INTO patients (user_id,full_name,gender,dob,phone,address) VALUES
(3,'Fictional Patient One','Other','1990-01-01','0700000001','Test address'),
(4,'Fictional Patient Two','Other','1992-02-02','0700000002','Test address');
INSERT INTO doctors (user_id,department_id,name) VALUES (2,1,'Dr Test Clinician');
INSERT INTO appointments (patient_id,doctor_id,appointment_date,appointment_time,reason,status) VALUES
(1,1,CURDATE(),'09:00:00','Fictional test booking one','Approved'),
(2,1,CURDATE(),'09:30:00','Fictional test booking two','Approved'),
(1,1,CURDATE(),'10:00:00','Fictional pending booking','Pending');
