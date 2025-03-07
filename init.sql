CREATE DATABASE IF NOT EXISTS complaint_system;
USE complaint_system;

CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('complainant', 'respondent', 'admin') NOT NULL,
    rsp_type VARCHAR(255),
    rsp_level INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type_name VARCHAR(255) NOT NULL,
    subtype VARCHAR(255) NOT NULL,
    UNIQUE(type_name, subtype)
);

CREATE TABLE complaints (
    id INT PRIMARY KEY AUTO_INCREMENT,
    complainant_id INT NOT NULL,
    type_id INT NOT NULL,
    current_respondent_id INT,
    status ENUM('Open', 'In Progress', 'Resolved', 'Escalated') DEFAULT 'Open',
    subject VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complainant_id) REFERENCES users(id),
    FOREIGN KEY (type_id) REFERENCES types(id)
);

INSERT INTO types (type_name, subtype) VALUES
('Network', 'Connectivity'),
('Software', 'Bug'),
('Hardware', 'Printer');

ALTER TABLE complaints
ADD COLUMN feedback_token VARCHAR(64) NULL,
ADD COLUMN token_expiry DATETIME NULL;