-- Create database
CREATE DATABASE IF NOT EXISTS complaint_system;
USE complaint_system;

-- Users table with role-based columns
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('complainant', 'respondent', 'admin') NOT NULL DEFAULT 'complainant',
    rsp_type VARCHAR(255),
    rsp_level INT,
    success_rate FLOAT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Complaint types table
CREATE TABLE types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type_name VARCHAR(255) NOT NULL,
    subtype VARCHAR(255) NOT NULL,
    UNIQUE(type_name, subtype)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Complaints table with escalation tracking
CREATE TABLE complaints (
    id INT PRIMARY KEY AUTO_INCREMENT,
    complainant_id INT NOT NULL,
    type_id INT NOT NULL,
    current_respondent_id INT,
    status ENUM('Open', 'In Progress', 'Resolved', 'Escalated', 'Closed') DEFAULT 'Open',
    subject VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME,
    feedback BOOLEAN,
    escalation_level INT DEFAULT 1,
    feedback_token VARCHAR(64),
    token_expiry DATETIME,
    FOREIGN KEY (complainant_id) REFERENCES users(id),
    FOREIGN KEY (type_id) REFERENCES types(id),
    FOREIGN KEY (current_respondent_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Escalation history table
CREATE TABLE escalation_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    complaint_id INT NOT NULL,
    old_respondent_id INT,
    new_respondent_id INT,
    escalated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id),
    FOREIGN KEY (old_respondent_id) REFERENCES users(id),
    FOREIGN KEY (new_respondent_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- System archives table
CREATE TABLE archives (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event TEXT NOT NULL,
    logged_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    user_id INT,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Indexes for performance
CREATE INDEX idx_complaints_status ON complaints(status);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_escalation_history ON escalation_history(complaint_id);

-- Insert initial complaint types
INSERT INTO types (type_name, subtype) VALUES
('Network', 'Connectivity Issues'),
('Network', 'Speed Problems'),
('Software', 'Bug Reports'),
('Software', 'Feature Requests'),
('Hardware', 'Printer Issues'),
('Hardware', 'Device Malfunctions');

-- Create admin user (password: admin123)
-- INSERT INTO users (username, email, password, role) 
-- VALUES ('admin', 'admin@complaintsystem.com', '$2y$10$xAzn0ql7RrDLWBV8mjbv/uhGdE0xANyPT7SQ5CoAX90Wk1jBjdzky', 'admin');

ALTER TABLE complaints
ADD COLUMN escalation_level INT DEFAULT 1,
ADD COLUMN feedback_token VARCHAR(64),
ADD COLUMN token_expiry DATETIME;