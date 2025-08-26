-- Create the database
DROP DATABASE IF EXISTS ticket_reservation;
CREATE DATABASE ticket_reservation;
USE ticket_reservation;

-- Users table
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    user_type ENUM('customer', 'admin') DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Transport types table
CREATE TABLE transport_types (
    type_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(20) NOT NULL UNIQUE,
    description TEXT
);

-- Transport providers table
CREATE TABLE providers (
    provider_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    contact_info TEXT,
    type_id INT,
    FOREIGN KEY (type_id) REFERENCES transport_types(type_id)
);

-- Routes table
CREATE TABLE routes (
    route_id INT AUTO_INCREMENT PRIMARY KEY,
    departure_city VARCHAR(100) NOT NULL,
    arrival_city VARCHAR(100) NOT NULL,
    distance_km DECIMAL(10,2),
    estimated_duration TIME,
    type_id INT,
    FOREIGN KEY (type_id) REFERENCES transport_types(type_id)
);

-- Schedules table
CREATE TABLE schedules (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    route_id INT,
    provider_id INT,
    departure_time DATETIME NOT NULL,
    arrival_time DATETIME NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    total_seats INT NOT NULL,
    available_seats INT NOT NULL,
    status ENUM('scheduled', 'delayed', 'cancelled', 'completed') DEFAULT 'scheduled',
    FOREIGN KEY (route_id) REFERENCES routes(route_id),
    FOREIGN KEY (provider_id) REFERENCES providers(provider_id)
);

-- Bookings table
CREATE TABLE bookings (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    schedule_id INT,
    booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    passengers INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('confirmed', 'pending', 'cancelled') DEFAULT 'confirmed',
    seat_numbers VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (schedule_id) REFERENCES schedules(schedule_id)
);

-- Payments table
CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('credit_card', 'debit_card', 'paypal', 'bank_transfer') NOT NULL,
    payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    transaction_id VARCHAR(255),
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id)
);

-- Insert initial data
INSERT INTO transport_types (name, description) VALUES 
('Bus', 'Intercity and intracity bus services'),
('Train', 'Rail transport services'),
('Plane', 'Air travel services');

INSERT INTO providers (name, contact_info, type_id) VALUES
('Green Line', 'Phone: 123-456-7890, Email: info@greenline.com', 1),
('Shohagh Paribahan', 'Phone: 123-456-7891, Email: info@shohagh.com', 1),
('Bangladesh Railway', 'Phone: 123-456-7892, Email: info@railway.gov.bd', 2),
('Biman Bangladesh Airlines', 'Phone: 123-456-7893, Email: info@biman.com', 3),
('US-Bangla Airlines', 'Phone: 123-456-7894, Email: info@usbangla.com', 3);

INSERT INTO routes (departure_city, arrival_city, distance_km, estimated_duration, type_id) VALUES
('Dhaka', 'Chittagong', 250, '04:30:00', 1),
('Dhaka', 'Chittagong', 250, '05:15:00', 2),
('Dhaka', 'Chittagong', 250, '01:00:00', 3),
('Dhaka', 'Rajshahi', 240, '05:00:00', 1),
('Dhaka', 'Rajshahi', 240, '06:00:00', 2),
('Dhaka', 'Rajshahi', 240, '01:15:00', 3),
('Dhaka', 'Khulna', 270, '06:30:00', 1),
('Dhaka', 'Khulna', 270, '07:00:00', 2),
('Dhaka', 'Khulna', 270, '01:10:00', 3);

INSERT INTO schedules (route_id, provider_id, departure_time, arrival_time, price, total_seats, available_seats) VALUES
(1, 1, '2023-12-01 08:00:00', '2023-12-01 12:30:00', 800, 40, 40),
(1, 2, '2023-12-01 10:00:00', '2023-12-01 14:30:00', 750, 40, 40),
(2, 3, '2023-12-01 09:00:00', '2023-12-01 14:15:00', 500, 300, 300),
(3, 4, '2023-12-01 07:30:00', '2023-12-01 08:30:00', 3500, 150, 150),
(3, 5, '2023-12-01 11:00:00', '2023-12-01 12:00:00', 3200, 150, 150);

-- Create an admin user (password: admin123)
INSERT INTO users (username, password, email, full_name, phone, user_type) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@ticket.com', 'System Administrator', '123-456-7890', 'admin');

-- Create a sample customer (password: customer123)
INSERT INTO users (username, password, email, full_name, phone) VALUES
('customer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer@example.com', 'John Doe', '123-456-7891');