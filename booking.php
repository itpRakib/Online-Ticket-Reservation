<?php
include 'db_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$schedule_id = $_GET['schedule_id'] ?? 0;
$passengers = $_GET['passengers'] ?? 1;

// Get schedule details
$stmt = $conn->prepare("SELECT s.*, r.departure_city, r.arrival_city, p.name as provider_name, t.name as transport_type 
                        FROM schedules s 
                        JOIN routes r ON s.route_id = r.route_id 
                        JOIN providers p ON s.provider_id = p.provider_id 
                        JOIN transport_types t ON p.type_id = t.type_id 
                        WHERE s.schedule_id = ?");
$stmt->bind_param("i", $schedule_id);
$stmt->execute();
$schedule = $stmt->get_result()->fetch_assoc();

if (!$schedule) {
    die("Invalid schedule");
}

// Check if enough seats are available
if ($schedule['available_seats'] < $passengers) {
    die("Not enough seats available");
}

// Process booking
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    $total_amount = $schedule['price'] * $passengers;
    
    // Generate seat numbers (simple implementation)
    $seat_numbers = implode(", ", range(1, $passengers));
    
    // Create booking
    $stmt = $conn->prepare("INSERT INTO bookings (user_id, schedule_id, passengers, total_amount, seat_numbers) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iiids", $user_id, $schedule_id, $passengers, $total_amount, $seat_numbers);
    
    if ($stmt->execute()) {
        $booking_id = $conn->insert_id;
        
        // Update available seats
        $new_seats = $schedule['available_seats'] - $passengers;
        $update_stmt = $conn->prepare("UPDATE schedules SET available_seats = ? WHERE schedule_id = ?");
        $update_stmt->bind_param("ii", $new_seats, $schedule_id);
        $update_stmt->execute();
        
        header("Location: payment.php?booking_id=" . $booking_id);
        exit();
    } else {
        $error = "Error creating booking: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking - Ticket Reservation System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <h2>Confirm Your Booking</h2>
        
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="booking-summary">
            <h3>Journey Details</h3>
            <p><strong>Route:</strong> <?php echo $schedule['departure_city'] . " to " . $schedule['arrival_city']; ?></p>
            <p><strong>Provider:</strong> <?php echo $schedule['provider_name']; ?></p>
            <p><strong>Departure:</strong> <?php echo date('M j, Y g:i A', strtotime($schedule['departure_time'])); ?></p>
            <p><strong>Arrival:</strong> <?php echo date('M j, Y g:i A', strtotime($schedule['arrival_time'])); ?></p>
            <p><strong>Transport Type:</strong> <?php echo $schedule['transport_type']; ?></p>
            <p><strong>Passengers:</strong> <?php echo $passengers; ?></p>
            <p><strong>Total Amount:</strong> ৳<?php echo $schedule['price'] * $passengers; ?></p>
        </div>
        
        <form method="POST" action="">
            <h3>Passenger Details</h3>
            
            <?php for ($i = 1; $i <= $passengers; $i++): ?>
                <div class="passenger-form">
                    <h4>Passenger <?php echo $i; ?></h4>
                    
                    <div class="form-group">
                        <label for="name<?php echo $i; ?>">Full Name</label>
                        <input type="text" id="name<?php echo $i; ?>" name="passenger_name[]" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="age<?php echo $i; ?>">Age</label>
                        <input type="number" id="age<?php echo $i; ?>" name="passenger_age[]" min="1" max="120" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="gender<?php echo $i; ?>">Gender</label>
                        <select id="gender<?php echo $i; ?>" name="passenger_gender[]" required>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
            <?php endfor; ?>
            
            <button type="submit" class="btn-primary">Confirm Booking</button>
        </form>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>