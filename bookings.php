<?php
// bookings.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

include 'db_connect.php';
$user_id = $_SESSION['user_id'];

// Initialize search variables
$search_from = '';
$search_to = '';
$search_date = '';
$search_transport = 'all';
$filtered = false;

// Pagination settings
$items_per_page = 5;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;
$offset = ($current_page - 1) * $items_per_page;

// Process search form if submitted
if ($_SERVER['REQUEST_METHOD'] == 'GET' && (isset($_GET['from']) || isset($_GET['to']) || isset($_GET['date']) || isset($_GET['transport_type']))) {
    // Get search parameters from GET
    $search_from = trim($_GET['from'] ?? '');
    $search_to = trim($_GET['to'] ?? '');
    $search_date = $_GET['date'] ?? '';
    $search_transport = $_GET['transport_type'] ?? 'all';
    
    $filtered = true;
    
    // Build the SQL query with search filters
    $count_sql = "SELECT COUNT(*) as total FROM bookings b 
                JOIN routes r ON b.route_id = r.route_id 
                JOIN vehicles v ON r.vehicle_id = v.vehicle_id 
                JOIN transport_types t ON v.type_id = t.type_id 
                WHERE b.user_id = ?";
    
    $sql = "SELECT b.*, r.departure_city, r.arrival_city, r.departure_datetime, r.arrival_datetime, 
                r.price, v.name as vehicle_name, v.number as vehicle_number, t.name as transport_type 
            FROM bookings b 
            JOIN routes r ON b.route_id = r.route_id 
            JOIN vehicles v ON r.vehicle_id = v.vehicle_id 
            JOIN transport_types t ON v.type_id = t.type_id 
            WHERE b.user_id = ?";
    
    $params = ["i", $user_id];
    
    // Add search conditions
    if (!empty($search_from)) {
        $sql .= " AND r.departure_city LIKE ?";
        $count_sql .= " AND r.departure_city LIKE ?";
        $params[0] .= "s";
        $params[] = "%$search_from%";
    }
    
    if (!empty($search_to)) {
        $sql .= " AND r.arrival_city LIKE ?";
        $count_sql .= " AND r.arrival_city LIKE ?";
        $params[0] .= "s";
        $params[] = "%$search_to%";
    }
    
    if (!empty($search_date)) {
        $sql .= " AND DATE(r.departure_datetime) = ?";
        $count_sql .= " AND DATE(r.departure_datetime) = ?";
        $params[0] .= "s";
        $params[] = $search_date;
    }
    
    if ($search_transport != 'all') {
        $sql .= " AND t.name = ?";
        $count_sql .= " AND t.name = ?";
        $params[0] .= "s";
        $params[] = $search_transport;
    }
    
    // Get total count for pagination
    $count_stmt = $conn->prepare($count_sql);
    call_user_func_array([$count_stmt, 'bind_param'], $params);
    $count_stmt->execute();
    $total_results = $count_stmt->get_result()->fetch_assoc()['total'];
    $total_pages = ceil($total_results / $items_per_page);
    
    // Add pagination to the main query
    $sql .= " ORDER BY b.booking_date DESC LIMIT ?, ?";
    $params[0] .= "ii";
    $params[] = $offset;
    $params[] = $items_per_page;
    
    // Execute the search query
    $stmt = $conn->prepare($sql);
    call_user_func_array([$stmt, 'bind_param'], $params);
} else {
    // Default query to fetch all bookings with pagination
    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM bookings WHERE user_id = ?";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $total_results = $count_stmt->get_result()->fetch_assoc()['total'];
    $total_pages = ceil($total_results / $items_per_page);
    
    // Main query with pagination
    $sql = "SELECT b.*, r.departure_city, r.arrival_city, r.departure_datetime, r.arrival_datetime, 
                r.price, v.name as vehicle_name, v.number as vehicle_number, t.name as transport_type 
            FROM bookings b 
            JOIN routes r ON b.route_id = r.route_id 
            JOIN vehicles v ON r.vehicle_id = v.vehicle_id 
            JOIN transport_types t ON v.type_id = t.type_id 
            WHERE b.user_id = ? 
            ORDER BY b.booking_date DESC LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $user_id, $offset, $items_per_page);
}

$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Ensure current page is valid
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
    // Redirect to the correct page
    $redirect_url = "bookings.php?page=$current_page";
    if ($filtered) {
        $redirect_url .= "&from=" . urlencode($search_from);
        $redirect_url .= "&to=" . urlencode($search_to);
        $redirect_url .= "&date=" . urlencode($search_date);
        $redirect_url .= "&transport_type=" . urlencode($search_transport);
    }
    header("Location: $redirect_url");
    exit();
}

// Handle cancellation with confirmation page
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

// Handle cancellation confirmation
if (isset($_GET['confirm_cancel'])) {
    $booking_id = $_GET['confirm_cancel'];
    $_SESSION['cancel_booking_id'] = $booking_id;
    header("Location: confirm_cancel.php");
    exit();
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
        
        /* Search form styles */
        .search-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .form-group { margin-bottom: 15px; position: relative; }
        label { display: block; margin-bottom: 5px; font-weight: 500; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .search-tips { background: #e3f2fd; padding: 10px 15px; border-radius: 4px; margin-top: 10px; font-size: 14px; }
        .search-tips ul { margin: 5px 0 0 20px; }
        h2 { margin-bottom: 15px; color: #1e88e5; }
        
        /* Pagination styles */
        .pagination-info { margin-bottom: 15px; color: #6c757d; font-size: 0.9em; }
        .pagination { display: flex; justify-content: center; margin: 20px 0; flex-wrap: wrap; gap: 5px; }
        .pagination-link { display: inline-block; padding: 8px 12px; background: #f8f9fa; color: #1e88e5; text-decoration: none; border-radius: 4px; border: 1px solid #dee2e6; transition: all 0.2s ease; }
        .pagination-link:hover { background: #e9ecef; color: #0d47a1; }
        .pagination-link.active { background: #1e88e5; color: white; border-color: #1e88e5; }
        
        /* Confirmation modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal {
            background: white;
            padding: 20px;
            border-radius: 8px;
            width: 400px;
            max-width: 90%;
        }
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
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
            <h2>Search for Tickets</h2>
            <form method="GET" class="search-form">
                <div class="form-group">
                    <label for="from">From</label>
                    <input type="text" id="from" name="from" value="<?php echo htmlspecialchars($search_from); ?>" placeholder="e.g. Sylhet">
                </div>
                <div class="form-group">
                    <label for="to">To</label>
                    <input type="text" id="to" name="to" value="<?php echo htmlspecialchars($search_to); ?>" placeholder="e.g. Rajshahi">
                </div>
                <div class="form-group">
                    <label for="date">Date</label>
                    <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($search_date); ?>">
                </div>
                <div class="form-group">
                    <label for="transport_type">Transport Type</label>
                    <select id="transport_type" name="transport_type">
                        <option value="all" <?php if ($search_transport == 'all') echo 'selected'; ?>>All</option>
                        <option value="Bus" <?php if ($search_transport == 'Bus') echo 'selected'; ?>>Bus</option>
                        <option value="Train" <?php if ($search_transport == 'Train') echo 'selected'; ?>>Train</option>
                        <option value="Plane" <?php if ($search_transport == 'Plane') echo 'selected'; ?>>Plane</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn">Search</button>
                </div>
            </form>
        </div>
        
        <div class="card">
            <h2>Your Bookings</h2>
            <?php if ($filtered): ?>
                <div class="search-tips">
                    <strong>Search Results:</strong> Showing bookings 
                    <?php if (!empty($search_from)): ?>from <strong><?php echo htmlspecialchars($search_from); ?></strong><?php endif; ?>
                    <?php if (!empty($search_to)): ?>to <strong><?php echo htmlspecialchars($search_to); ?></strong><?php endif; ?>
                    <?php if (!empty($search_date)): ?>on <strong><?php echo date('F j, Y', strtotime($search_date)); ?></strong><?php endif; ?>
                    <?php if ($search_transport != 'all'): ?>by <strong><?php echo htmlspecialchars($search_transport); ?></strong><?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Pagination Info -->
            <div class="pagination-info">
                <?php if ($total_results > 0): ?>
                    <p>Showing <?php echo min(($current_page - 1) * $items_per_page + 1, $total_results); ?> to <?php echo min($current_page * $items_per_page, $total_results); ?> of <?php echo $total_results; ?> bookings</p>
                <?php else: ?>
                    <p>No bookings found</p>
                <?php endif; ?>
            </div>
            
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
                                        <a href="confirm_cancel.php?booking_id=<?php echo $booking['booking_id']; ?>&return_url=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-danger">Cancel</a>
                                    <?php else: ?>
                                        <span>-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination Controls -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($current_page > 1): ?>
                        <a href="bookings.php?page=1<?php echo $filtered ? '&from='.urlencode($search_from).'&to='.urlencode($search_to).'&date='.urlencode($search_date).'&transport_type='.urlencode($search_transport) : ''; ?>" class="pagination-link">&laquo; First</a>
                        <a href="bookings.php?page=<?php echo $current_page-1; ?><?php echo $filtered ? '&from='.urlencode($search_from).'&to='.urlencode($search_to).'&date='.urlencode($search_date).'&transport_type='.urlencode($search_transport) : ''; ?>" class="pagination-link">&lsaquo; Prev</a>
                    <?php endif; ?>
                    
                    <?php 
                    // Display page numbers
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="bookings.php?page=<?php echo $i; ?><?php echo $filtered ? '&from='.urlencode($search_from).'&to='.urlencode($search_to).'&date='.urlencode($search_date).'&transport_type='.urlencode($search_transport) : ''; ?>" class="pagination-link <?php echo $i == $current_page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    
                    <?php if ($current_page < $total_pages): ?>
                        <a href="bookings.php?page=<?php echo $current_page+1; ?><?php echo $filtered ? '&from='.urlencode($search_from).'&to='.urlencode($search_to).'&date='.urlencode($search_date).'&transport_type='.urlencode($search_transport) : ''; ?>" class="pagination-link">Next &rsaquo;</a>
                        <a href="bookings.php?page=<?php echo $total_pages; ?><?php echo $filtered ? '&from='.urlencode($search_from).'&to='.urlencode($search_to).'&date='.urlencode($search_date).'&transport_type='.urlencode($search_transport) : ''; ?>" class="pagination-link">Last &raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <p>You have no bookings yet. <a href="index.php">Book your first trip!</a></p>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>Ticket Reservation System ; <?php echo date('Y'); ?></p>
        </div>
    </footer>
</body>
</html>