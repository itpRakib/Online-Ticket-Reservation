<?php
include 'db_connect.php';
session_start();

// Get search parameters
$from = isset($_GET['from']) ? trim($_GET['from']) : '';
$to = isset($_GET['to']) ? trim($_GET['to']) : '';
$date = isset($_GET['date']) ? $_GET['date'] : '';
$type = isset($_GET['type']) ? intval($_GET['type']) : 0;
$passengers = isset($_GET['passengers']) ? max(1, intval($_GET['passengers'])) : 1;

// Debug output (remove in production)
echo "<!-- DEBUG: from=$from, to=$to, date=$date, type=$type, passengers=$passengers -->\n";

// Check database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Build query
$sql = "SELECT s.*, r.departure_city, r.arrival_city, p.name as provider_name, t.name as transport_type 
        FROM schedules s 
        JOIN routes r ON s.route_id = r.route_id 
        JOIN providers p ON s.provider_id = p.provider_id 
        JOIN transport_types t ON p.type_id = t.type_id 
        WHERE r.departure_city LIKE ? AND r.arrival_city LIKE ? AND DATE(s.departure_time) = ?";
        
$params = ["%$from%", "%$to%", $date];

if ($type > 0) {
    $sql .= " AND t.type_id = ?";
    $params[] = $type;
}

$sql .= " AND s.available_seats >= ?";
$params[] = $passengers;

$sql .= " ORDER BY s.departure_time";

// Debug output (remove in production)
echo "<!-- DEBUG: SQL = $sql -->\n";
echo "<!-- DEBUG: Params = " . implode(", ", $params) . " -->\n";

// Check if we have any data in the tables
$check_data = $conn->query("SELECT COUNT(*) as count FROM schedules");
if ($check_data) {
    $row = $check_data->fetch_assoc();
    echo "<!-- DEBUG: Schedules table has " . $row['count'] . " records -->\n";
}

// Execute search query
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo "<!-- DEBUG: Prepare failed: " . $conn->error . " -->\n";
    die("Database error. Please try again later.");
}

// Bind parameters dynamically
$types = str_repeat('s', count($params));
$stmt->bind_param($types, ...$params);

if (!$stmt->execute()) {
    echo "<!-- DEBUG: Execute failed: " . $stmt->error . " -->\n";
    die("Query error. Please try again.");
}

$result = $stmt->get_result();

// Check if we have results
if ($result->num_rows === 0) {
    // Let's try a more flexible search
    $flexible_sql = "SELECT s.*, r.departure_city, r.arrival_city, p.name as provider_name, t.name as transport_type 
                    FROM schedules s 
                    JOIN routes r ON s.route_id = r.route_id 
                    JOIN providers p ON s.provider_id = p.provider_id 
                    JOIN transport_types t ON p.type_id = t.type_id 
                    WHERE r.departure_city LIKE ? OR r.arrival_city LIKE ?
                    ORDER BY s.departure_time LIMIT 5";
    
    $flexible_params = ["%$from%", "%$to%"];
    $flexible_stmt = $conn->prepare($flexible_sql);
    $flexible_stmt->bind_param("ss", ...$flexible_params);
    $flexible_stmt->execute();
    $flexible_result = $flexible_stmt->get_result();
    
    echo "<!-- DEBUG: Flexible search found " . $flexible_result->num_rows . " results -->\n";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results - Ticket Reservation System</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .debug-panel {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            font-family: monospace;
            font-size: 14px;
        }
        .debug-toggle {
            background: #6c757d;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            margin-bottom: 10px;
        }
        .suggestion-list {
            list-style-type: none;
            padding: 0;
        }
        .suggestion-list li {
            padding: 8px 12px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
        }
        .suggestion-list li:hover {
            background-color: #f8f9fa;
        }
        .alternative-routes {
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <h2>Search Results</h2>
        
        <!-- Search parameters summary -->
        <div class="search-params">
            <h3>Your Search</h3>
            <div class="param-item"><strong>From:</strong> <?php echo htmlspecialchars($from); ?></div>
            <div class="param-item"><strong>To:</strong> <?php echo htmlspecialchars($to); ?></div>
            <div class="param-item"><strong>Date:</strong> <?php echo htmlspecialchars($date); ?></div>
            <div class="param-item"><strong>Passengers:</strong> <?php echo $passengers; ?></div>
            <?php if ($type > 0): 
                $type_name = "";
                if ($type == 1) $type_name = "Bus";
                elseif ($type == 2) $type_name = "Train";
                elseif ($type == 3) $type_name = "Plane";
            ?>
                <div class="param-item"><strong>Transport:</strong> <?php echo $type_name; ?></div>
            <?php endif; ?>
        </div>
        
        <?php if ($result->num_rows > 0): ?>
            <div class="results-list">
                <?php while ($row = $result->fetch_assoc()): 
                    $departure_time = strtotime($row['departure_time']);
                    $arrival_time = strtotime($row['arrival_time']);
                    $duration = $arrival_time - $departure_time;
                    $hours = floor($duration / 3600);
                    $minutes = floor(($duration % 3600) / 60);
                ?>
                    <div class="result-card">
                        <div class="result-info">
                            <h3>
                                <i class="fas 
                                    <?php 
                                    if ($row['transport_type'] == 'Bus') echo 'fa-bus';
                                    elseif ($row['transport_type'] == 'Train') echo 'fa-train';
                                    else echo 'fa-plane';
                                    ?>
                                "></i>
                                <?php echo htmlspecialchars($row['departure_city']) . " to " . htmlspecialchars($row['arrival_city']); ?>
                            </h3>
                            <p><strong>Provider:</strong> <?php echo htmlspecialchars($row['provider_name']); ?></p>
                            <p><strong>Departure:</strong> <?php echo date('M j, Y g:i A', $departure_time); ?></p>
                            <p><strong>Arrival:</strong> <?php echo date('M j, Y g:i A', $arrival_time); ?></p>
                            <p><strong>Duration:</strong> <?php echo $hours . "h " . $minutes . "m"; ?></p>
                            <p><strong>Type:</strong> <?php echo htmlspecialchars($row['transport_type']); ?></p>
                            <p><strong>Available Seats:</strong> <?php echo $row['available_seats']; ?></p>
                            <p class="price">৳<?php echo number_format($row['price'], 2); ?></p>
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
            <div class="no-results">
                <i class="fas fa-search"></i>
                <h3>No Results Found</h3>
                <p>We couldn't find any trips matching your search criteria.</p>
                
                <div class="suggestions">
                    <h4>Possible reasons:</h4>
                    <ul>
                        <li>No routes available for your selected cities and date</li>
                        <li>The transport type you selected might not be available</li>
                        <li>There might be no available seats for your selected date</li>
                        <li>Check if you've entered the correct city names</li>
                    </ul>
                    
                    <h4>Try these suggestions:</h4>
                    <ul class="suggestion-list">
                        <li onclick="document.getElementById('from').value='Dhaka'; document.getElementById('to').value='Chittagong'; document.getElementById('date').value='<?php echo date('Y-m-d', strtotime('+1 day')); ?>'">
                            <i class="fas fa-route"></i> Dhaka to Chittagong (Tomorrow)
                        </li>
                        <li onclick="document.getElementById('from').value='Dhaka'; document.getElementById('to').value='Rajshahi'; document.getElementById('date').value='<?php echo date('Y-m-d', strtotime('+2 days')); ?>'">
                            <i class="fas fa-route"></i> Dhaka to Rajshahi (Day after tomorrow)
                        </li>
                        <li onclick="document.getElementById('transport-type').selectedIndex = 0;">
                            <i class="fas fa-filter"></i> Try all transport types
                        </li>
                    </ul>
                </div>
                
                <div class="alternative-routes">
                    <h4>Popular Routes:</h4>
                    <?php
                    // Show popular routes from the database
                    $popular_routes = $conn->query("
                        SELECT r.departure_city, r.arrival_city, COUNT(*) as bookings 
                        FROM routes r 
                        JOIN schedules s ON r.route_id = s.route_id 
                        JOIN bookings b ON s.schedule_id = b.schedule_id 
                        GROUP BY r.departure_city, r.arrival_city 
                        ORDER BY bookings DESC 
                        LIMIT 5
                    ");
                    
                    if ($popular_routes && $popular_routes->num_rows > 0) {
                        echo "<ul class='suggestion-list'>";
                        while ($route = $popular_routes->fetch_assoc()) {
                            echo "<li onclick=\"document.getElementById('from').value='{$route['departure_city']}'; document.getElementById('to').value='{$route['arrival_city']}'\">";
                            echo "<i class='fas fa-star'></i> {$route['departure_city']} to {$route['arrival_city']}";
                            echo "</li>";
                        }
                        echo "</ul>";
                    }
                    ?>
                </div>
                
                <a href="index.php" class="btn-primary" style="margin-top: 20px;">Search Again</a>
            </div>
            
            <!-- Debug panel (remove in production) -->
            <button class="debug-toggle" onclick="document.getElementById('debug-panel').style.display = document.getElementById('debug-panel').style.display === 'none' ? 'block' : 'none'">
                Show Debug Info
            </button>
            <div id="debug-panel" class="debug-panel" style="display: none;">
                <h4>Debug Information:</h4>
                <p><strong>SQL Query:</strong> <?php echo htmlspecialchars($sql); ?></p>
                <p><strong>Parameters:</strong> <?php echo implode(", ", $params); ?></p>
                <p><strong>Total Schedules in DB:</strong> 
                    <?php 
                    $count = $conn->query("SELECT COUNT(*) as count FROM schedules");
                    if ($count) {
                        $row = $count->fetch_assoc();
                        echo $row['count'];
                    } else {
                        echo "Error: " . $conn->error;
                    }
                    ?>
                </p>
                <p><strong>Sample Routes:</strong>
                    <?php
                    $sample_routes = $conn->query("SELECT departure_city, arrival_city FROM routes LIMIT 5");
                    if ($sample_routes) {
                        $routes = [];
                        while ($route = $sample_routes->fetch_assoc()) {
                            $routes[] = $route['departure_city'] . " to " . $route['arrival_city'];
                        }
                        echo implode(", ", $routes);
                    }
                    ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script>
        // Prefill the search form with previous values
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('from').value = "<?php echo $from; ?>";
            document.getElementById('to').value = "<?php echo $to; ?>";
            document.getElementById('date').value = "<?php echo $date; ?>";
            document.getElementById('transport-type').value = "<?php echo $type; ?>";
            document.getElementById('passengers').value = "<?php echo $passengers; ?>";
        });
    </script>
</body>
</html>