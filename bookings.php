<?php
// bookings.php - View and manage user bookings
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

include 'db_connect.php';

// Handle booking cancellation
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_booking'])) {
    $booking_id = $_POST['booking_id'];
    $user_id = $_SESSION['user_id'];
    
    // Get booking details
    $sql = "SELECT b.*, r.departure_datetime, r.route_id, r.available_seats, p.payment_id 
            FROM bookings b 
            JOIN routes r ON b.route_id = r.route_id 
            JOIN payments p ON b.booking_id = p.booking_id 
            WHERE b.booking_id = ? AND b.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $booking_id, $user_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    
    if ($booking) {
        // Check if cancellation is allowed (at least 3 hours before departure)
        $departure_time = new DateTime($booking['departure_datetime']);
        $current_time = new DateTime();
        $time_diff = $current_time->diff($departure_time);
        
        if ($time_diff->h + ($time_diff->days * 24) >= 3) {
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Update booking status to cancelled
                $sql = "UPDATE bookings SET status = 'cancelled' WHERE booking_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $booking_id);
                $stmt->execute();
                
                // Update payment status to refunded
                $sql = "UPDATE payments SET payment_status = 'refunded' WHERE payment_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $booking['payment_id']);
                $stmt->execute();
                
                // Restore available seats
                $new_seats = $booking['available_seats'] + $booking['passengers'];
                $sql = "UPDATE routes SET available_seats = ? WHERE route_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $new_seats, $booking['route_id']);
                $stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $message = "Booking #$booking_id has been cancelled successfully. Amount will be refunded within 5-7 business days.";
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $error = "Error cancelling booking: " . $e->getMessage();
            }
        } else {
            $error = "Cancellation is only allowed at least 3 hours before departure.";
        }
    } else {
        $error = "Booking not found or you don't have permission to cancel it.";
    }
}

// Get user bookings
$user_id = $_SESSION['user_id'];
$sql = "SELECT b.*, r.departure_city, r.arrival_city, r.departure_datetime, r.arrival_datetime, 
               r.price, v.name as vehicle_name, v.number as vehicle_number, 
               t.name as transport_type, p.payment_status
        FROM bookings b
        JOIN routes r ON b.route_id = r.route_id
        JOIN vehicles v ON r.vehicle_id = v.vehicle_id
        JOIN transport_types t ON v.type_id = t.type_id
        JOIN payments p ON b.booking_id = p.booking_id
        WHERE b.user_id = ?
        ORDER BY b.booking_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - Ticket Reservation System</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f5f7fa; color: #333; line-height: 1.6; }
        .container { width: 90%; max-width: 1200px; margin: 0 auto; padding: 20px; }
        header { background: linear-gradient(135deg, #1e88e5, #0d47a1); color: white; padding: 20px 0; margin-bottom: 30px; }
        header .container { display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 24px; font-weight: bold; }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .btn { display: inline-block; padding: 10px 20px; background: #1e88e5; color: white; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; }
        .btn:hover { background: #1565c0; }
        .btn-secondary { background: #78909c; }
        .btn-secondary:hover { background: #546e7a; }
        .btn-danger { background: #e53935; }
        .btn-danger:hover { background: #c62828; }
        .btn-success { background: #43a047; }
        .btn-success:hover { background: #388e3c; }
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f1f5f9; font-weight: 600; }
        tr:hover { background-color: #f9f9f9; }
        .message { padding: 15px; margin: 20px 0; border-radius: 5px; }
        .success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        .booking-status { display: inline-block; padding: 4px 8px; border-radius: 4px; color: white; font-size: 12px; }
        .confirmed { background: #43a047; }
        .cancelled { background: #e53935; }
        .payment-status { display: inline-block; padding: 4px 8px; border-radius: 4px; color: white; font-size: 12px; }
        .success { background: #43a047; }
        .refunded { background: #fb8c00; }
        .no-bookings { text-align: center; padding: 40px; color: #78909c; }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="logo">Ticket Reservation System</div>
            <div class="user-info">
                <span>Welcome, <?php echo $_SESSION['full_name']; ?></span>
                <a href="index.php" class="btn">Book Tickets</a>
                <a href="logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </div>
    </header>

    <div class="container">
        <h1>My Bookings</h1>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (count($bookings) > 0): ?>
            <div class="card">
                <table>
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Route</th>
                            <th>Departure</th>
                            <th>Vehicle</th>
                            <th>Passengers</th>
                            <th>Total Price</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td>#<?php echo $booking['booking_id']; ?></td>
                                <td><?php echo $booking['departure_city'] . ' to ' . $booking['arrival_city']; ?></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($booking['departure_datetime'])); ?></td>
                                <td><?php echo $booking['vehicle_name'] . ' (' . $booking['vehicle_number'] . ')'; ?></td>
                                <td><?php echo $booking['passengers']; ?></td>
                                <td>$<?php echo $booking['total_price']; ?></td>
                                <td>
                                    <span class="booking-status <?php echo strtolower($booking['status'] ?? 'confirmed'); ?>">
                                        <?php echo ucfirst($booking['status'] ?? 'confirmed'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="payment-status <?php echo strtolower($booking['payment_status']); ?>">
                                        <?php echo ucfirst($booking['payment_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (($booking['status'] ?? 'confirmed') == 'confirmed'): ?>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                            <button type="submit" name="cancel_booking" class="btn btn-danger">Cancel</button>
                                        </form>
                                    <?php else: ?>
                                        <span>Cancelled</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="card no-bookings">
                <h2>You don't have any bookings yet.</h2>
                <p>Go to the <a href="index.php">home page</a> to book your tickets.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>