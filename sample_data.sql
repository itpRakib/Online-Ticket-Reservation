-- Insert transport types
INSERT INTO transport_types (name) VALUES 
('Bus'),
('Train'),
('Plane');

-- Insert vehicles
INSERT INTO vehicles (name, number, type_id) VALUES 
('Green Line', 'GL-101', 1),
('Shohagh Paribahan', 'SP-202', 1),
('Hanif Enterprise', 'HE-303', 1),
('Sundarban Express', 'SE-404', 2),
('Turna Nishitha', 'TN-505', 2),
('Biman Bangladesh', 'BB-606', 3),
('US-Bangla Airlines', 'US-707', 3);

-- Insert routes
INSERT INTO routes (departure_city, arrival_city, departure_datetime, arrival_datetime, price, available_seats, vehicle_id) VALUES 
('Dhaka', 'Chittagong', '2025-09-03 08:00:00', '2025-09-03 12:00:00', 1200, 25, 1),
('Dhaka', 'Chittagong', '2025-09-03 10:30:00', '2025-09-03 14:30:00', 1100, 18, 2),
('Dhaka', 'Chittagong', '2025-09-03 14:00:00', '2025-09-03 14:45:00', 4500, 50, 6),
('Dhaka', 'Khulna', '2025-09-03 09:15:00', '2025-09-03 15:30:00', 900, 22, 3),
('Dhaka', 'Khulna', '2025-09-04 07:30:00', '2025-09-04 13:45:00', 850, 30, 2),
('Dhaka', 'Rajshahi', '2025-09-03 08:45:00', '2025-09-03 14:15:00', 800, 20, 1),
('Dhaka', 'Rajshahi', '2025-09-04 10:00:00', '2025-09-04 16:30:00', 750, 15, 3),
('Chittagong', 'Cox''s Bazar', '2025-09-03 09:30:00', '2025-09-03 12:00:00', 500, 28, 1),
('Chittagong', 'Cox''s Bazar', '2025-09-04 11:00:00', '2025-09-04 13:30:00', 550, 25, 2),
('Chittagong', 'Dhaka', '2025-09-03 16:00:00', '2025-09-03 20:00:00', 1200, 20, 1),
('Chittagong', 'Dhaka', '2025-09-03 18:30:00', '2025-09-03 18:45:00', 4200, 45, 7),
('Khulna', 'Dhaka', '2025-09-04 14:00:00', '2025-09-04 20:15:00', 900, 25, 3),
('Rajshahi', 'Dhaka', '2025-09-04 15:30:00', '2025-09-04 21:00:00', 800, 18, 1),
('Dhaka', 'Sylhet', '2025-09-03 11:30:00', '2025-09-03 17:45:00', 1000, 22, 2),
('Dhaka', 'Sylhet', '2025-09-03 13:15:00', '2025-09-03 13:45:00', 3800, 40, 6),
('Sylhet', 'Dhaka', '2025-09-04 16:45:00', '2025-09-04 17:15:00', 3800, 35, 7);