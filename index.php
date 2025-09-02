<?php
// index.php - Main Application File
session_start();
date_default_timezone_set('Asia/Dhaka');
include 'db_connect.php';

// Handle user authentication
$auth_error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['login'])) {
        // Handle login
        $email = $_POST['email'];
        $password = $_POST['password'];
        
        $sql = "SELECT * FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                header("Location: index.php");
                exit();
            } else {
                $auth_error = "Invalid password.";
            }
        } else {
            $auth_error = "User not found.";
        }
    } elseif (isset($_POST['register'])) {
        // Handle registration
        $full_name = $_POST['full_name'];
        $username = $_POST['username'];
        $email = $_POST['email'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        // Check if user already exists
        $check_sql = "SELECT * FROM users WHERE email = ? OR username = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $email, $username);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $auth_error = "User with this email or username already exists.";
        } else {
            $sql = "INSERT INTO users (full_name, username, email, password) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $full_name, $username, $email, $password);
            
            if ($stmt->execute()) {
                $_SESSION['user_id'] = $stmt->insert_id;
                $_SESSION['username'] = $username;
                $_SESSION['full_name'] = $full_name;
                header("Location: index.php");
                exit();
            } else {
                $auth_error = "Error creating account. Please try again.";
            }
        }
    }
}

// Handle search and booking if user is logged in
$results = [];
$error = '';
if (isset($_SESSION['user_id'])) {
    // Handle search form submission
    if (isset($_GET['departure']) || isset($_GET['arrival'])) {
        $departure = trim($_GET['departure'] ?? '');
        $arrival = trim($_GET['arrival'] ?? '');
        $date = $_GET['date'] ?? '';
        $type = $_GET['type'] ?? 'all';
        
        // Build the SQL query
        $sql = "SELECT r.*, v.name as vehicle_name, v.number as vehicle_number, t.name as transport_type 
                FROM routes r 
                JOIN vehicles v ON r.vehicle_id = v.vehicle_id 
                JOIN transport_types t ON v.type_id = t.type_id 
                WHERE (r.departure_city LIKE ? OR r.departure_city LIKE ?) 
                AND (r.arrival_city LIKE ? OR r.arrival_city LIKE ?)";
        
        // Only add date condition if a date is provided
        if (!empty($date)) {
            $sql .= " AND DATE(r.departure_datetime) = ?";
        }
        
        if ($type != 'all') {
            $sql .= " AND t.name = ?";
        }
        
        $sql .= " AND r.available_seats > 0 ORDER BY r.departure_datetime";
        
        $stmt = $conn->prepare($sql);
        
        // Create search patterns for partial matching
        $departure_pattern1 = "%$departure%";
        $departure_pattern2 = "%" . substr($departure, 0, 3) . "%";
        $arrival_pattern1 = "%$arrival%";
        $arrival_pattern2 = "%" . substr($arrival, 0, 3) . "%";
        
        // Bind parameters based on conditions
        $param_types = "ssss"; // Start with departure and arrival patterns
        $params = [$departure_pattern1, $departure_pattern2, $arrival_pattern1, $arrival_pattern2];
        
        if (!empty($date)) {
            $param_types .= "s";
            $params[] = $date;
        }
        
        if ($type != 'all') {
            $param_types .= "s";
            $params[] = $type;
        }
        
        // Fix for bind_param warning - create references
        $bind_params = array($param_types);
        foreach ($params as $key => $value) {
            $bind_params[] = &$params[$key];
        }
        
        // Use call_user_func_array to bind parameters
        call_user_func_array(array($stmt, 'bind_param'), $bind_params);
        
        $stmt->execute();
        $result = $stmt->get_result();
        $results = $result->fetch_all(MYSQLI_ASSOC);
    }

    // Handle booking
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book'])) {
        $route_id = $_POST['route_id'];
        $passengers = $_POST['passengers'];
        $user_id = $_SESSION['user_id'];
        
        // Verify user still exists in database
        $user_check_sql = "SELECT * FROM users WHERE user_id = ?";
        $user_check_stmt = $conn->prepare($user_check_sql);
        $user_check_stmt->bind_param("i", $user_id);
        $user_check_stmt->execute();
        $user_result = $user_check_stmt->get_result();
        
        if ($user_result->num_rows == 0) {
            // User doesn't exist, destroy session and redirect to login
            session_destroy();
            header("Location: index.php");
            exit();
        }
        
        // Get route details
        $sql = "SELECT * FROM routes WHERE route_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $route_id);
        $stmt->execute();
        $route = $stmt->get_result()->fetch_assoc();
        
        if ($route && $route['available_seats'] >= $passengers) {
            $total_price = $route['price'] * $passengers;
            
            // Create booking
            $sql = "INSERT INTO bookings (user_id, route_id, passengers, total_price) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiid", $user_id, $route_id, $passengers, $total_price);
            
            if ($stmt->execute()) {
                $booking_id = $conn->insert_id;
                
                // Update available seats
                $new_seats = $route['available_seats'] - $passengers;
                $sql = "UPDATE routes SET available_seats = ? WHERE route_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $new_seats, $route_id);
                $stmt->execute();
                
                // Create payment record with pending status
                $sql = "INSERT INTO payments (booking_id, amount, payment_method, payment_status) 
                        VALUES (?, ?, 'Pending', 'pending')";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("id", $booking_id, $total_price);
                $stmt->execute();
                
                // After creating a booking, redirect to payment page instead of showing message
                $_SESSION['message'] = "Booking created successfully! Please complete your payment.";
                header("Location: payment.php?booking_id=" . $booking_id);
                exit();
            } else {
                $error = "Error creating booking. Please try again.";
            }
        } else {
            $error = "Not enough seats available for this booking.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Reservation System</title>
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
        .search-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 500; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f1f5f9; font-weight: 600; }
        tr:hover { background-color: #f9f9f9; }
        .message { padding: 15px; margin: 20px 0; border-radius: 5px; }
        .success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        .booking-form { display: flex; gap: 10px; align-items: center; }
        .transport-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; color: white; font-size: 12px; }
        .bus { background: #43a047; }
        .train { background: #fb8c00; }
        .plane { background: #e53935; }
        footer { text-align: center; margin-top: 40px; padding: 20px; color: #78909c; }
        
        /* Auth forms */
        .auth-container { display: flex; justify-content: center; margin-top: 50px; }
        .auth-forms { display: flex; gap: 20px; }
        .auth-form { background: white; padding: 20px; width: 400px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .auth-form h2 { margin-bottom: 20px; text-align: center; }
        .auth-form input { margin-bottom: 15px; }
        
        /* Search tips */
        .search-tips {
            background: #e3f2fd;
            padding: 10px 15px;
            border-radius: 4px;
            margin-top: 10px;
            font-size: 14px;
        }
        .search-tips ul {
            margin: 5px 0 0 20px;
        }
        
        /* Popular routes */
        .popular-routes {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }
        .popular-route {
            background: #e3f2fd;
            padding: 8px 15px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
        }
        .popular-route:hover {
            background: #bbdefb;
        }
        
        /* Quick actions */
        .quick-actions {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        .quick-action {
            flex: 1;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .quick-action:hover {
            transform: translateY(-5px);
        }
        .quick-action i {
            font-size: 24px;
            margin-bottom: 10px;
            display: block;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="logo">Ticket Reservation System</div>
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION['full_name']; ?></span>
                    <a href="bookings.php" class="btn">My Bookings</a>
                    <a href="confirm_cancel.php" class="btn btn-secondary">Confirm/Cancel</a>
                    <a href="logout.php" class="btn btn-danger">Logout</a>
                </div>
            <?php else: ?>
                <div class="user-info">
                    <a href="#login" class="btn">Login</a>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <div class="container">
        <?php if (!isset($_SESSION['user_id'])): ?>
            <!-- Authentication Section -->
            <div class="auth-container">
                <div class="auth-forms">
                    <div class="auth-form" id="login-form">
                        <h2>Login to Your Account</h2>
                        <?php if ($auth_error): ?>
                            <div class="message error"><?php echo $auth_error; ?></div>
                        <?php endif; ?>
                        <form method="POST">
                            <input type="hidden" name="login" value="1">
                            <input type="email" name="email" placeholder="Email" required>
                            <input type="password" name="password" placeholder="Password" required>
                            <button type="submit" class="btn" style="width: 100%;">Login</button>
                        </form>
                    </div>
                    
                    <div class="auth-form" id="register-form">
                        <h2>Create New Account</h2>
                        <?php if ($auth_error): ?>
                            <div class="message error"><?php echo $auth_error; ?></div>
                        <?php endif; ?>
                        <form method="POST">
                            <input type="hidden" name="register" value="1">
                            <input type="text" name="full_name" placeholder="Full Name" required>
                            <input type="text" name="username" placeholder="Username" required>
                            <input type="email" name="email" placeholder="Email" required>
                            <input type="password" name="password" placeholder="Password" required>
                            <button type="submit" class="btn" style="width: 100%;">Register</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Main Application Content -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="message success"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
            <?php endif; ?>
            
            <!-- Quick Actions -->
            <div class="quick-actions">
                <div class="quick-action" onclick="location.href='index.php'">
                    <i class="fas fa-search"></i>
                    <h3>Search Tickets</h3>
                    <p>Find available routes</p>
                </div>
                <div class="quick-action" onclick="location.href='bookings.php'">
                    <i class="fas fa-ticket-alt"></i>
                    <h3>My Bookings</h3>
                    <p>View your reservations</p>
                </div>
                <div class="quick-action" onclick="location.href='confirm_cancel.php'">
                    <i class="fas fa-check-circle"></i>
                    <h3>Confirm/Cancel</h3>
                    <p>Manage your bookings</p>
                </div>
                <div class="quick-action" onclick="location.href='payment.php'">
                    <i class="fas fa-credit-card"></i>
                    <h3>Payment</h3>
                    <p>Complete your payments</p>
                </div>
            </div>
            
            <div class="card">
                <h2>Search for Tickets</h2>
                <form method="get" action="" class="search-form">
                    <div class="form-group">
                        <label for="departure">From</label>
                        <input type="text" id="departure" name="departure" required value="<?php echo $_GET['departure'] ?? ''; ?>" placeholder="e.g. Dhaka">
                    </div>
                    <div class="form-group">
                        <label for="arrival">To</label>
                        <input type="text" id="arrival" name="arrival" required value="<?php echo $_GET['arrival'] ?? ''; ?>" placeholder="e.g. Khulna">
                    </div>
                    <div class="form-group">
                        <label for="date">Date</label>
                        <input type="date" id="date" name="date" value="<?php echo $_GET['date'] ?? date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="type">Transport Type</label>
                        <select id="type" name="type">
                            <option value="all">All</option>
                            <option value="Bus" <?php if (($_GET['type'] ?? '') == 'Bus') echo 'selected'; ?>>Bus</option>
                            <option value="Train" <?php if (($_GET['type'] ?? '') == 'Train') echo 'selected'; ?>>Train</option>
                            <option value="Plane" <?php if (($_GET['type'] ?? '') == 'Plane') echo 'selected'; ?>>Plane</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn">Search</button>
                    </div>
                </form>
                
                <div class="search-tips">
                    <strong>Popular Routes:</strong>
                    <div class="popular-routes">
                        <div class="popular-route" onclick="setRoute('Dhaka', 'Chittagong')">Dhaka → Chittagong</div>
                        <div class="popular-route" onclick="setRoute('Dhaka', 'Khulna')">Dhaka → Khulna</div>
                        <div class="popular-route" onclick="setRoute('Chittagong', 'Cox\'s Bazar')">Chittagong → Cox's Bazar</div>
                        <div class="popular-route" onclick="setRoute('Dhaka', 'Rajshahi')">Dhaka → Rajshahi</div>
                        <div class="popular-route" onclick="setRoute('Dhaka', 'Sylhet')">Dhaka → Sylhet</div>
                    </div>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="message error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if (isset($_GET['departure']) || isset($_GET['arrival'])): ?>
                <div class="card">
                    <h2>Available Routes</h2>
                    <?php if (count($results) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Vehicle</th>
                                    <th>Departure</th>
                                    <th>Arrival</th>
                                    <th>Date & Time</th>
                                    <th>Duration</th>
                                    <th>Price</th>
                                    <th>Seats</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $route): ?>
                                    <tr>
                                        <td><span class="transport-badge <?php echo strtolower($route['transport_type']); ?>"><?php echo $route['transport_type']; ?></span></td>
                                        <td><?php echo $route['vehicle_name'] . ' (' . $route['vehicle_number'] . ')'; ?></td>
                                        <td><?php echo $route['departure_city']; ?></td>
                                        <td><?php echo $route['arrival_city']; ?></td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($route['departure_datetime'])); ?></td>
                                        <?php
                                        $departure = new DateTime($route['departure_datetime']);
                                        $arrival = new DateTime($route['arrival_datetime']);
                                        $interval = $departure->diff($arrival);
                                        $duration = $interval->format('%hh %im');
                                        ?>
                                        <td><?php echo $duration; ?></td>
                                        <td>৳<?php echo $route['price']; ?></td>
                                        <td><?php echo $route['available_seats']; ?></td>
                                        <td>
                                            <form method="POST" class="booking-form">
                                                <input type="hidden" name="route_id" value="<?php echo $route['route_id']; ?>">
                                                <input type="number" name="passengers" min="1" max="<?php echo $route['available_seats']; ?>" value="1" style="width: 60px;">
                                                <button type="submit" name="book" class="btn">Book</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No routes found matching your criteria. Try searching for routes between popular Bangladeshi cities.</p>
                        <div class="search-tips">
                            <strong>Try these search examples:</strong>
                            <ul>
                                <li>From: Dhaka, To: Chittagong</li>
                                <li>From: Dhaka, To: Khulna</li>
                                <li>From: Chittagong, To: Cox's Bazar</li>
                                <li>Make sure to select a future date</li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <footer>
        <div class="container">
            <p>Ticket Reservation System &copy; <?php echo date('Y'); ?></p>
            <p>Need help? <a href="confirm_cancel.php">Confirm or cancel your booking</a> | Contact support: support@ticketreservation.com</p>
        </div>
    </footer>

    <script>
        function setRoute(departure, arrival) {
            document.getElementById('departure').value = departure;
            document.getElementById('arrival').value = arrival;
        }
    </script>
</body>
</html>