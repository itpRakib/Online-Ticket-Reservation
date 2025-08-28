<?php
// payment.php - Handle payment processing with local payment methods
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

include 'db_connect.php';

// Get booking details if booking_id is provided
$booking = null;
$error = '';
$success = '';

if (isset($_GET['booking_id'])) {
    $booking_id = $_GET['booking_id'];
    $user_id = $_SESSION['user_id'];
    
    $sql = "SELECT b.*, r.departure_city, r.arrival_city, r.departure_datetime, 
                   r.price, v.name as vehicle_name, v.number as vehicle_number, 
                   t.name as transport_type
            FROM bookings b
            JOIN routes r ON b.route_id = r.route_id
            JOIN vehicles v ON r.vehicle_id = v.vehicle_id
            JOIN transport_types t ON v.type_id = t.type_id
            WHERE b.booking_id = ? AND b.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $booking_id, $user_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    
    if (!$booking) {
        $error = "Booking not found or you don't have permission to view it.";
    }
}

// Handle payment form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_payment'])) {
    $booking_id = $_POST['booking_id'];
    $payment_method = $_POST['payment_method'];
    
    // Validate payment details based on method
    if ($payment_method == 'bKash' || $payment_method == 'Nagad' || $payment_method == 'Rocket') {
        $mobile_number = $_POST['mobile_number'];
        $transaction_id = $_POST['transaction_id'];
        $pin = $_POST['pin'];
        
        if (empty($mobile_number) || empty($transaction_id) || empty($pin)) {
            $error = "Please fill in all payment details for " . $payment_method . ".";
        } else if (!preg_match('/^(01[3-9]\d{8})$/', $mobile_number)) {
            $error = "Please enter a valid Bangladeshi mobile number.";
        } else if (strlen($pin) != 5) {
            $error = "PIN must be 5 digits.";
        } else {
            // Process payment
            $sql = "UPDATE payments SET payment_method = ?, payment_status = 'success' WHERE booking_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $payment_method, $booking_id);
            
            if ($stmt->execute()) {
                $success = "Payment processed successfully via $payment_method! Your booking is now confirmed.";
            } else {
                $error = "Error processing payment. Please try again.";
            }
        }
    } else if ($payment_method == 'Bank Transfer') {
        $bank_name = $_POST['bank_name'];
        $transaction_id = $_POST['bank_transaction_id'];
        
        if (empty($bank_name) || empty($transaction_id)) {
            $error = "Please fill in all bank transfer details.";
        } else {
            // Process payment
            $sql = "UPDATE payments SET payment_method = ?, payment_status = 'success' WHERE booking_id = ?";
            $stmt = $conn->prepare($sql);
            $payment_method_text = "Bank Transfer ($bank_name)";
            $stmt->bind_param("si", $payment_method_text, $booking_id);
            
            if ($stmt->execute()) {
                $success = "Payment processed successfully via Bank Transfer! Your booking is now confirmed.";
            } else {
                $error = "Error processing payment. Please try again.";
            }
        }
    } else {
        $error = "Please select a valid payment method.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Ticket Reservation System</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f5f7fa; color: #333; line-height: 1.6; }
        .container { width: 90%; max-width: 800px; margin: 0 auto; padding: 20px; }
        header { background: linear-gradient(135deg, #1e88e5, #0d47a1); color: white; padding: 20px 0; margin-bottom: 30px; }
        header .container { display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 24px; font-weight: bold; }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .btn { display: inline-block; padding: 10px 20px; background: #1e88e5; color: white; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; }
        .btn:hover { background: #1565c0; }
        .btn-secondary { background: #78909c; }
        .btn-secondary:hover { background: #546e7a; }
        .btn-success { background: #43a047; }
        .btn-success:hover { background: #388e3c; }
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 20px; }
        .message { padding: 15px; margin: 20px 0; border-radius: 5px; }
        .success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: 500; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .booking-details { background: #f1f8e9; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .payment-methods { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; }
        .payment-method { flex: 1; min-width: 150px; text-align: center; padding: 15px; border: 2px solid #ddd; border-radius: 5px; cursor: pointer; }
        .payment-method.selected { border-color: #1e88e5; background: #e3f2fd; }
        .payment-method input { display: none; }
        .payment-details { display: none; background: #f9f9f9; padding: 15px; border-radius: 5px; margin-top: 15px; }
        .payment-details.active { display: block; }
        .payment-logo { width: 80px; height: 40px; object-fit: contain; margin-bottom: 10px; }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="logo">Ticket Reservation System</div>
            <div class="user-info">
                <span>Welcome, <?php echo $_SESSION['full_name']; ?></span>
                <a href="index.php" class="btn">Book Tickets</a>
                <a href="bookings.php" class="btn">My Bookings</a>
                <a href="logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </div>
    </header>

    <div class="container">
        <h1>Payment</h1>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="message success"><?php echo $success; ?></div>
            <div class="card">
                <p>Your payment has been processed successfully. <a href="bookings.php">View your bookings</a>.</p>
            </div>
        <?php elseif ($booking): ?>
            <div class="card">
                <h2>Booking Details</h2>
                <div class="booking-details">
                    <p><strong>Booking ID:</strong> #<?php echo $booking['booking_id']; ?></p>
                    <p><strong>Route:</strong> <?php echo $booking['departure_city'] . ' to ' . $booking['arrival_city']; ?></p>
                    <p><strong>Departure:</strong> <?php echo date('M j, Y g:i A', strtotime($booking['departure_datetime'])); ?></p>
                    <p><strong>Vehicle:</strong> <?php echo $booking['vehicle_name'] . ' (' . $booking['vehicle_number'] . ')'; ?></p>
                    <p><strong>Passengers:</strong> <?php echo $booking['passengers']; ?></p>
                    <p><strong>Total Amount:</strong> ৳<?php echo $booking['total_price']; ?></p>
                </div>
                
                <h2>Select Payment Method</h2>
                <form method="POST" id="paymentForm">
                    <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                    
                    <div class="form-group">
                        <div class="payment-methods">
                            <label class="payment-method selected" data-method="bKash">
                                <input type="radio" name="payment_method" value="bKash" checked> 
                                <div class="payment-logo" style="background-color: #e2136e; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold;">bKash</div>
                                bKash
                            </label>
                            <label class="payment-method" data-method="Nagad">
                                <input type="radio" name="payment_method" value="Nagad">
                                <div class="payment-logo" style="background-color: #f60; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold;">Nagad</div>
                                Nagad
                            </label>
                            <label class="payment-method" data-method="Rocket">
                                <input type="radio" name="payment_method" value="Rocket">
                                <div class="payment-logo" style="background-color: #7c40ff; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold;">Rocket</div>
                                Rocket
                            </label>
                            <label class="payment-method" data-method="Bank Transfer">
                                <input type="radio" name="payment_method" value="Bank Transfer">
                                <div class="payment-logo" style="background-color: #006a4e; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px;">Bank Transfer</div>
                                Bank Transfer
                            </label>
                        </div>
                    </div>
                    
                    <!-- bKash/Nagad/Rocket Payment Details -->
                    <div class="payment-details active" id="mobilePaymentDetails">
                        <h3>Mobile Payment Details</h3>
                        <div class="form-group">
                            <label for="mobile_number">Mobile Number</label>
                            <input type="text" id="mobile_number" name="mobile_number" placeholder="01XXXXXXXXX" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="transaction_id">Transaction ID</label>
                            <input type="text" id="transaction_id" name="transaction_id" placeholder="Enter transaction ID" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="pin">PIN Number</label>
                            <input type="password" id="pin" name="pin" placeholder="Enter 5-digit PIN" maxlength="5" required>
                        </div>
                    </div>
                    
                    <!-- Bank Transfer Details -->
                    <div class="payment-details" id="bankPaymentDetails">
                        <h3>Bank Transfer Details</h3>
                        <div class="form-group">
                            <label for="bank_name">Bank Name</label>
                            <select id="bank_name" name="bank_name">
                                <option value="">Select Bank</option>
                                <option value="Sonali Bank">Sonali Bank</option>
                                <option value="Janata Bank">Janata Bank</option>
                                <option value="Agrani Bank">Agrani Bank</option>
                                <option value="Rupali Bank">Rupali Bank</option>
                                <option value="Bangladesh Krishi Bank">Bangladesh Krishi Bank</option>
                                <option value="Islami Bank Bangladesh">Islami Bank Bangladesh</option>
                                <option value="Dutch-Bangla Bank">Dutch-Bangla Bank</option>
                                <option value="BRAC Bank">BRAC Bank</option>
                                <option value="Eastern Bank">Eastern Bank</option>
                                <option value="City Bank">City Bank</option>
                                <option value="Other">Other Bank</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="bank_transaction_id">Transaction ID/Reference Number</label>
                            <input type="text" id="bank_transaction_id" name="bank_transaction_id" placeholder="Enter transaction reference" required>
                        </div>
                    </div>
                    
                    <button type="submit" name="process_payment" class="btn btn-success">Confirm Payment</button>
                </form>
            </div>
        <?php else: ?>
            <div class="card">
                <p>No booking found. <a href="index.php">Book tickets first</a>.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Payment method selection
        document.querySelectorAll('.payment-method').forEach(method => {
            method.addEventListener('click', () => {
                document.querySelectorAll('.payment-method').forEach(m => {
                    m.classList.remove('selected');
                });
                method.classList.add('selected');
                method.querySelector('input').checked = true;
                
                // Show appropriate payment details
                const paymentMethod = method.getAttribute('data-method');
                document.getElementById('mobilePaymentDetails').classList.remove('active');
                document.getElementById('bankPaymentDetails').classList.remove('active');
                
                if (paymentMethod === 'Bank Transfer') {
                    document.getElementById('bankPaymentDetails').classList.add('active');
                } else {
                    document.getElementById('mobilePaymentDetails').classList.add('active');
                }
            });
        });

        // Form validation
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
            
            if (paymentMethod === 'Bank Transfer') {
                const bankName = document.getElementById('bank_name').value;
                const bankTransactionId = document.getElementById('bank_transaction_id').value;
                
                if (!bankName) {
                    e.preventDefault();
                    alert('Please select a bank name.');
                    return false;
                }
                
                if (!bankTransactionId) {
                    e.preventDefault();
                    alert('Please enter a transaction reference number.');
                    return false;
                }
            } else {
                const mobileNumber = document.getElementById('mobile_number').value;
                const transactionId = document.getElementById('transaction_id').value;
                const pin = document.getElementById('pin').value;
                
                if (!mobileNumber) {
                    e.preventDefault();
                    alert('Please enter your mobile number.');
                    return false;
                }
                
                if (!/^(01[3-9]\d{8})$/.test(mobileNumber)) {
                    e.preventDefault();
                    alert('Please enter a valid Bangladeshi mobile number.');
                    return false;
                }
                
                if (!transactionId) {
                    e.preventDefault();
                    alert('Please enter the transaction ID.');
                    return false;
                }
                
                if (!pin || pin.length !== 5) {
                    e.preventDefault();
                    alert('PIN must be 5 digits.');
                    return false;
                }
            }
        });
    </script>
</body>
</html>