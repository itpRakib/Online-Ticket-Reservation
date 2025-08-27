<?php
include 'db_connect.php';
session_start();

// Get search parameters
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$date = $_GET['date'] ?? '';
$type = $_GET['type'] ?? '';
$passengers = $_GET['passengers'] ?? 1;

// Build query
$sql = "SELECT s.*, r.departure_city, r.arrival_city, p.name as provider_name, t.name as transport_type 
        FROM schedules s 
        JOIN routes r ON s.route_id = r.route_id 
        JOIN providers p ON s.provider_id = p.provider_id 
        JOIN transport_types t ON p.type_id = t.type_id 
        WHERE r.departure_city LIKE ? AND r.arrival_city LIKE ? AND DATE(s.departure_time) = ?";
        
$params = ["%$from%", "%$to%", $date];

if (!empty($type)) {
    $sql .= " AND t.type_id = ?";
    $params[] = $type;
}

$sql .= " ORDER BY s.departure_time";

$stmt = $conn->prepare($sql);

// Bind parameters dynamically
$types = str_repeat('s', count($params));
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results - Ticket Reservation System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <h2>Search Results</h2>
        
        <?php if ($result->num_rows > 0): ?>
            <div class="results-list">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="result-card">
                        <div class="result-info">
                            <h3><?php echo $row['departure_city'] . " to " . $row['arrival_city']; ?></h3>
                            <p><strong>Provider:</strong> <?php echo $row['provider_name']; ?></p>
                            <p><strong>Departure:</strong> <?php echo date('M j, Y g:i A', strtotime($row['departure_time'])); ?></p>
                            <p><strong>Arrival:</strong> <?php echo date('M j, Y g:i A', strtotime($row['arrival_time'])); ?></p>
                            <p><strong>Type:</strong> <?php echo $row['transport_type']; ?></p>
                            <p><strong>Available Seats:</strong> <?php echo $row['available_seats']; ?></p>
                            <p class="price">৳<?php echo $row['price']; ?></p>
                        </div>
                        <div class="result-action">
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <a href="booking.php?schedule_id=<?php echo $row['schedule_id']; ?>&passengers=<?php echo $passengers; ?>" class="btn-primary">Book Now</a>
                            <?php else: ?>
                                <a href="login.php" class="btn-primary">Login to Book</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p>No results found for your search. Please try different criteria.</p>
        <?php endif; ?>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>