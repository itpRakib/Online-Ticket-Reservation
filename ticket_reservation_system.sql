-- ticket_reservation_system.sql - Database Schema
CREATE DATABASE IF NOT EXISTS ticket_reservation_system;
USE ticket_reservation_system;

-- Transport types table
CREATE TABLE transport_types (
    type_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(20) NOT NULL UNIQUE
);

-- Vehicles table (buses, trains, planes)
CREATE TABLE vehicles (
    vehicle_id INT AUTO_INCREMENT PRIMARY KEY,
    type_id INT NOT NULL,
    number VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    capacity INT NOT NULL,
    facilities TEXT,
    FOREIGN KEY (type_id) REFERENCES transport_types(type_id)
);

-- Routes table
CREATE TABLE routes (
    route_id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    departure_city VARCHAR(100) NOT NULL,
    arrival_city VARCHAR(100) NOT NULL,
    departure_datetime DATETIME NOT NULL,
    arrival_datetime DATETIME NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    available_seats INT NOT NULL,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id)
);

-- Users table
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Bookings table
CREATE TABLE bookings (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    route_id INT NOT NULL,
    passengers INT NOT NULL DEFAULT 1,
    total_price DECIMAL(10,2) NOT NULL,
    booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('confirmed', 'cancelled', 'pending') DEFAULT 'confirmed',
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (route_id) REFERENCES routes(route_id)
);

-- Payments table
CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    payment_status ENUM('success', 'failed', 'pending') DEFAULT 'pending',
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id)
);

-- Insert transport types
INSERT INTO transport_types (name) VALUES 
('Bus'), ('Train'), ('Plane');

-- Insert sample vehicles
INSERT INTO vehicles (type_id, number, name, capacity, facilities) VALUES
(1, 'BUS-001', 'Luxury Coach', 40, 'AC, WiFi, Refreshments, Toilet'),
(1, 'BUS-002', 'Express Shuttle', 35, 'AC, Charging Points'),
(2, 'TRN-101', 'Express Train', 300, 'AC Coach, Dining Car, WiFi'),
(2, 'TRN-102', 'Superfast Express', 280, 'AC, Sleeper Berths'),
(3, 'PLN-201', 'Boeing 737', 180, 'In-flight Entertainment, Meals'),
(3, 'PLN-202', 'Airbus A320', 160, 'WiFi, Refreshments');

-- Insert routes (Updated dates to September 3-30, 2025)
INSERT INTO routes (vehicle_id, departure_city, arrival_city, departure_datetime, arrival_datetime, price, available_seats) VALUES
-- Bus Routes
(1, 'Dhaka', 'Chittagong', '2025-09-03 09:00:00', '2025-09-03 15:00:00', 680.00, 40),
(1, 'Dhaka', 'Rajshahi', '2025-09-04 10:30:00', '2025-09-04 17:00:00', 700.00, 35),
(1, 'Dhaka', 'Sylhet', '2025-09-05 08:30:00', '2025-09-05 14:30:00', 650.00, 40),

-- Train Routes
(3, 'Dhaka', 'Chittagong', '2025-09-06 07:45:00', '2025-09-06 13:50:00', 500.00, 300),
(3, 'Dhaka', 'Dinajpur', '2025-09-07 10:00:00', '2025-09-07 18:50:00', 550.00, 280),
(4, 'Sylhet', 'Dhaka', '2025-09-08 22:00:00', '2025-09-09 05:10:00', 520.00, 300),

-- Flight Routes
(5, 'Dhaka', 'Cox''s Bazar', '2025-09-09 11:00:00', '2025-09-09 12:05:00', 3500.00, 180),
(6, 'Dhaka', 'Sylhet', '2025-09-10 14:00:00', '2025-09-10 15:00:00', 3200.00, 160);

-- Insert sample users
INSERT INTO users (username, password, email, full_name, phone) VALUES
('john_doe', 'hashed_password_1', 'john@example.com', 'John Doe', '+8801712345678'),
('jane_smith', 'hashed_password_2', 'jane@example.com', 'Jane Smith', '+8801812345678'),
('mike_wilson', 'hashed_password_3', 'mike@example.com', 'Mike Wilson', '+8801912345678');

-- Insert sample bookings (Updated to match new route dates)
INSERT INTO bookings (user_id, route_id, passengers, total_price, status) VALUES
(1, 1, 2, 1360.00, 'confirmed'),
(2, 4, 1, 500.00, 'confirmed'),
(3, 7, 3, 10500.00, 'confirmed'),
(1, 2, 1, 700.00, 'cancelled');



-- Insert sample payments
INSERT INTO payments (booking_id, amount, payment_method, payment_status) VALUES
(1, 1360.00, 'Credit Card', 'success'),
(2, 500.00, 'bKash', 'success'),
(3, 10500.00, 'Bank Transfer', 'success'),
(4, 700.00, 'Nagad', 'failed');