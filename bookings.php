<?php
// bookings.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

include 'db_connect.php';
$user_id = $_SESSION['user_id'];

// Fetch user bookings
$sql = "SELECT b.*, r.departure_city, r.arrival_city, r.departure_datetime, r.arrival_datetime, 
               r.price, v.name as vehicle_name, v.number as vehicle_number, t.name as transport_type 
        FROM bookings b 
        JOIN routes r ON b.route_id = r.route_id 
        JOIN vehicles v ON r.vehicle_id = v.vehicle_id 
        JOIN transport_types t ON v.type_id = t.type_id 
        WHERE b.user_id = ? 
        ORDER BY b.booking_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_booking'])) {
    $booking_id = $_POST['booking_id'];
    
    // Get booking details to restore seats
    $sql = "SELECT b.passengers, r.route_id, r.available_seats 
            FROM bookings b 
            JOIN routes r ON b.route_id = r.route_id 
            WHERE b.booking_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    
    if ($booking) {
        // Update booking status
        $sql = "UPDATE bookings SET status = 'cancelled' WHERE booking_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        
        // Restore available seats
        $new_seats = $booking['available_seats'] + $booking['passengers'];
        $sql = "UPDATE routes SET available_seats = ? WHERE route_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $new_seats, $booking['route_id']);
        $stmt->execute();
        
        $_SESSION['message'] = "Booking cancelled successfully.";
        header("Location: bookings.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - Ticket Reservation System</title>
    <style>
        body { background-color: #f5f7fa; color: #333; line-height: 1.6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
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
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f1f5f9; font-weight: 600; }
        tr:hover { background-color: #f9f9f9; }
        .message { padding: 15px; margin: 20px 0; border-radius: 5px; }
        .success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .transport-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; color: white; font-size: 12px; }
        .bus { background: #43a047; }
        .train { background: #fb8c00; }
        .plane { background: #e53935; }
        footer { text-align: center; margin-top: 40px; padding: 20px; color: #78909c; }
        .status-confirmed { color: #43a047; font-weight: 500; }
        .status-cancelled { color: #e53935; font-weight: 500; }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="logo">Ticket Reservation System</div>
            <div class="user-info">
                <span>Welcome, <?php echo $_SESSION['full_name']; ?></span>
                <a href="index.php" class="btn">Home</a>
                <a href="logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </div>
    </header>

    <div class="container">
        <h1>My Bookings</h1>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="message success"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
        <?php endif; ?>
        
        <div class="card">
            <?php if (count($bookings) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Transport</th>
                            <th>Route</th>
                            <th>Departure</th>
                            <th>Passengers</th>
                            <th>Total Price</th>
                            <th>Booking Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td>#<?php echo $booking['booking_id']; ?></td>
                                <td>
                                    <span class="transport-badge <?php echo strtolower($booking['transport_type']); ?>">
                                        <?php echo $booking['transport_type']; ?>
                                    </span><br>
                                    <?php echo $booking['vehicle_name'] . ' (' . $booking['vehicle_number'] . ')'; ?>
                                </td>
                                <td><?php echo $booking['departure_city'] . ' to ' . $booking['arrival_city']; ?></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($booking['departure_datetime'])); ?></td>
                                <td><?php echo $booking['passengers']; ?></td>
                                <td>$<?php echo $booking['total_price']; ?></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($booking['booking_date'])); ?></td>
                                <td class="status-<?php echo $booking['status']; ?>"><?php echo ucfirst($booking['status']); ?></td>
                                <td>
                                    <?php if ($booking['status'] == 'confirmed'): ?>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                            <button type="submit" name="cancel_booking" class="btn btn-danger">Cancel</button>
                                        </form>
                                    <?php else: ?>
                                        <span>-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>You have no bookings yet. <a href="index.php">Book your first trip!</a></p>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>Ticket Reservation System &copy; <?php echo date('Y'); ?></p>
        </div>
    </footer>
</body>
</html>