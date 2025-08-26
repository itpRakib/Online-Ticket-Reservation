<?php
include 'db_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user bookings
$stmt = $conn->prepare("SELECT b.*, s.departure_time, s.arrival_time, r.departure_city, r.arrival_city, p.name as provider_name, t.name as transport_type 
                        FROM bookings b 
                        JOIN schedules s ON b.schedule_id = s.schedule_id 
                        JOIN routes r ON s.route_id = r.route_id 
                        JOIN providers p ON s.provider_id = p.provider_id 
                        JOIN transport_types t ON p.type_id = t.type_id 
                        WHERE b.user_id = ? 
                        ORDER BY b.booking_date DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$bookings = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - Ticket Reservation System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <h2>My Bookings</h2>
        
        <?php if ($bookings->num_rows > 0): ?>
            <div class="bookings-list">
                <?php while ($booking = $bookings->fetch_assoc()): ?>
                    <div class="booking-card">
                        <div class="booking-info">
                            <h3><?php echo $booking['departure_city'] . " to " . $booking['arrival_city']; ?></h3>
                            <p><strong>Provider:</strong> <?php echo $booking['provider_name']; ?></p>
                            <p><strong>Departure:</strong> <?php echo date('M j, Y g:i A', strtotime($booking['departure_time'])); ?></p>
                            <p><strong>Arrival:</strong> <?php echo date('M j, Y g:i A', strtotime($booking['arrival_time'])); ?></p>
                            <p><strong>Type:</strong> <?php echo $booking['transport_type']; ?></p>
                            <p><strong>Passengers:</strong> <?php echo $booking['passengers']; ?></p>
                            <p><strong>Seat Numbers:</strong> <?php echo $booking['seat_numbers']; ?></p>
                            <p><strong>Total Amount:</strong> ৳<?php echo $booking['total_amount']; ?></p>
                            <p><strong>Booking Date:</strong> <?php echo date('M j, Y g:i A', strtotime($booking['booking_date'])); ?></p>
                            <p><strong>Status:</strong> <?php echo ucfirst($booking['status']); ?></p>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p>You haven't made any bookings yet.</p>
        <?php endif; ?>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>