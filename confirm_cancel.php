<?php
// confirm_cancel.php - Booking Cancellation Confirmation
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

include 'db_connect.php';

$booking_id = $_GET['booking_id'] ?? 0;
$return_url = $_GET['return_url'] ?? 'bookings.php';

// Get booking details
$sql = "SELECT b.*, r.departure_city, r.arrival_city, r.departure_datetime, 
               v.name as vehicle_name, v.number as vehicle_number, t.name as transport_type 
        FROM bookings b 
        JOIN routes r ON b.route_id = r.route_id 
        JOIN vehicles v ON r.vehicle_id = v.vehicle_id 
        JOIN transport_types t ON v.type_id = t.type_id 
        WHERE b.booking_id = ? AND b.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $booking_id, $_SESSION['user_id']);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    $_SESSION['message'] = "Booking not found.";
    header("Location: $return_url");
    exit();
}

// Handle cancellation confirmation
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['confirm_cancel'])) {
        // Get booking details to restore seats
        $sql = "SELECT b.passengers, r.route_id, r.available_seats 
                FROM bookings b 
                JOIN routes r ON b.route_id = r.route_id 
                WHERE b.booking_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $booking_details = $stmt->get_result()->fetch_assoc();
        
        if ($booking_details) {
            // Update booking status
            $sql = "UPDATE bookings SET status = 'cancelled' WHERE booking_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $booking_id);
            $stmt->execute();
            
            // Restore available seats
            $new_seats = $booking_details['available_seats'] + $booking_details['passengers'];
            $sql = "UPDATE routes SET available_seats = ? WHERE route_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $new_seats, $booking_details['route_id']);
            $stmt->execute();
            
            $_SESSION['message'] = "Booking cancelled successfully.";
            header("Location: $return_url");
            exit();
        }
    } else {
        // User chose not to cancel
        header("Location: $return_url");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Cancellation - Ticket Reservation System</title>
    <style>
        body { background-color: #f5f7fa; color: #333; line-height: 1.6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .container { width: 90%; max-width: 800px; margin: 50px auto; padding: 20px; }
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 20px; }
        .btn { display: inline-block; padding: 10px 20px; background: #1e88e5; color: white; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; }
        .btn:hover { background: #1565c0; }
        .btn-secondary { background: #78909c; }
        .btn-secondary:hover { background: #546e7a; }
        .btn-danger { background: #e53935; }
        .btn-danger:hover { background: #c62828; }
        .booking-details { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .form-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>Confirm Cancellation</h1>
            
            <div class="booking-details">
                <h2>Booking Details</h2>
                <p><strong>Booking ID:</strong> #<?php echo $booking['booking_id']; ?></p>
                <p><strong>Route:</strong> <?php echo $booking['departure_city'] . ' to ' . $booking['arrival_city']; ?></p>
                <p><strong>Departure:</strong> <?php echo date('M j, Y g:i A', strtotime($booking['departure_datetime'])); ?></p>
                <p><strong>Vehicle:</strong> <?php echo $booking['vehicle_name'] . ' (' . $booking['vehicle_number'] . ')'; ?></p>
                <p><strong>Passengers:</strong> <?php echo $booking['passengers']; ?></p>
                <p><strong>Total Price:</strong> $<?php echo $booking['total_price']; ?></p>
            </div>
            
            <p>Are you sure you want to cancel this booking? This action cannot be undone.</p>
            
            <form method="POST">
                <div class="form-actions">
                    <a href="<?php echo $return_url; ?>" class="btn btn-secondary">No, Keep Booking</a>
                    <button type="submit" name="confirm_cancel" class="btn btn-danger">Yes, Cancel Booking</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>