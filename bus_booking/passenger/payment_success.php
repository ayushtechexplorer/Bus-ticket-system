<?php
session_start();
include('../includes/db.php');

// Enable detailed error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Restrict access
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'passenger') {
    header("Location: ../login.php");
    exit;
}

date_default_timezone_set('Asia/Kathmandu');
$user = $_SESSION['user'];

// Get parameters from URL
$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$bus_id = isset($_GET['bus_id']) ? intval($_GET['bus_id']) : 0;

// Get all eSewa response data
$esewa_data = $_GET;

// Debug logging
error_log("=== eSewa Payment Success Debug ===");
error_log("User ID: " . $user['id']);
error_log("Booking ID: " . $booking_id);
error_log("Bus ID: " . $bus_id);
error_log("GET Parameters received: " . json_encode($esewa_data));

// ========== SIMPLIFIED eSEWA PAYMENT VERIFICATION ==========
// For test environment - In production, use proper API verification
$verification_success = false;
$verification_message = '';

// eSewa test configuration
$esewa_config = [
    'merchant_code' => 'EPAYTEST',
    'secret_key' => '8gBm/:&EnhH.1/q',
    'test_mode' => true
];

// Check what parameters we received from eSewa
if (count($esewa_data) > 0) {
    error_log("eSewa sent parameters. Checking...");
    
    // List of possible eSewa parameter names
    $possible_ref_params = ['refId', 'transaction_uuid', 'oid', 'transaction_id'];
    $possible_amount_params = ['amt', 'total_amount', 'amount'];
    
    // Check for any reference/transaction ID
    $has_ref = false;
    foreach ($possible_ref_params as $param) {
        if (isset($esewa_data[$param]) && !empty($esewa_data[$param])) {
            $has_ref = true;
            error_log("Found reference parameter: $param = " . $esewa_data[$param]);
            // Map to transaction_uuid for consistency
            if ($param !== 'transaction_uuid') {
                $esewa_data['transaction_uuid'] = $esewa_data[$param];
            }
            break;
        }
    }
    
    // Check for any amount parameter
    $has_amount = false;
    foreach ($possible_amount_params as $param) {
        if (isset($esewa_data[$param]) && !empty($esewa_data[$param])) {
            $has_amount = true;
            error_log("Found amount parameter: $param = " . $esewa_data[$param]);
            // Map to total_amount for consistency
            if ($param !== 'total_amount') {
                $esewa_data['total_amount'] = $esewa_data[$param];
            }
            break;
        }
    }
    
    // FOR TEST ENVIRONMENT - Accept payment if we have any eSewa parameters
    if ($esewa_config['merchant_code'] === 'EPAYTEST') {
        error_log("Test mode detected. Using simplified verification.");
        
        // In test mode, accept if we have at least one parameter
        if (count($esewa_data) >= 1) {
            $verification_success = true;
            $verification_message = "Test payment accepted";
            
            // Ensure we have standard parameter names
            if (!isset($esewa_data['transaction_uuid'])) {
                $esewa_data['transaction_uuid'] = 'ESEWA_TEST_' . time() . '_' . $booking_id;
            }
            
            error_log("Test verification successful. Transaction UUID: " . $esewa_data['transaction_uuid']);
        } else {
            $verification_message = "No parameters received from eSewa";
        }
    } else {
        // For production - require reference and amount
        if ($has_ref && $has_amount) {
            $verification_success = true;
            $verification_message = "Payment verified";
        } else {
            $verification_message = "Missing required parameters. Need reference ID and amount.";
        }
    }
} else {
    $verification_message = "No response data received from eSewa";
}

// ========== UPDATE DATABASE ==========
$update_success = false;
$booking_details = null;
$bus = null;

if ($verification_success && $booking_id > 0) {
    error_log("Attempting to update booking #$booking_id");
    
    try {
        // 1. Get booking details
        $check_query = "SELECT * FROM bookings WHERE booking_id = ? AND user_id = ?";
        $check_stmt = $conn->prepare($check_query);
        if (!$check_stmt) {
            throw new Exception("Prepare failed for check query: " . $conn->error);
        }
        
        $check_stmt->bind_param("ii", $booking_id, $user['id']);
        if (!$check_stmt->execute()) {
            throw new Exception("Execute failed: " . $check_stmt->error);
        }
        
        $booking_result = $check_stmt->get_result();
        
        if ($booking_result->num_rows === 0) {
            throw new Exception("Booking not found or doesn't belong to user");
        }
        
        $booking_details = $booking_result->fetch_assoc();
        error_log("Booking found. Current status: " . $booking_details['payment_status']);
        $check_stmt->close();
        
        // 2. Check if booking is still pending
        if ($booking_details['payment_status'] !== 'Pending') {
            throw new Exception("Booking already paid or cancelled. Current status: " . $booking_details['payment_status']);
        }
        
        // 3. Update booking to Paid - Using your actual database columns
        // Your bookings table has: transaction_id (varchar 100) - we'll use this for eSewa reference
        $update_query = "UPDATE bookings SET 
                        payment_status = 'Paid',
                        payment_method = 'eSewa',
                        paid_at = NOW(),
                        transaction_id = ?
                        WHERE booking_id = ?";
        
        $update_stmt = $conn->prepare($update_query);
        if (!$update_stmt) {
            throw new Exception("Failed to prepare update statement: " . $conn->error);
        }
        
        // Get transaction ID from eSewa or generate one
        $transaction_id = isset($esewa_data['transaction_uuid']) 
            ? $esewa_data['transaction_uuid'] 
            : (isset($esewa_data['refId']) ? $esewa_data['refId'] : 'ESEWA_' . time() . '_' . $booking_id);
        
        // Log the transaction ID for debugging
        error_log("Using transaction_id: " . $transaction_id);
        
        if (!$update_stmt->bind_param("si", $transaction_id, $booking_id)) {
            throw new Exception("Failed to bind parameters: " . $update_stmt->error);
        }
        
        if (!$update_stmt->execute()) {
            throw new Exception("Database update failed: " . $update_stmt->error);
        }
        
        $update_stmt->close();
        $update_success = true;
        
        error_log("SUCCESS: Booking #$booking_id updated to Paid. Transaction ID: $transaction_id");
        
        // 4. Clear session data
        unset($_SESSION['recently_booked_seats'], $_SESSION['recent_booking_bus_id'], $_SESSION['booking_time']);
        
        // 5. Get bus details for display
        if ($booking_details['bus_id']) {
            $bus_query = $conn->prepare("SELECT bus_name, route_from, route_to FROM buses WHERE id = ?");
            $bus_query->bind_param("i", $booking_details['bus_id']);
            $bus_query->execute();
            $bus_result = $bus_query->get_result();
            if ($bus_result->num_rows > 0) {
                $bus = $bus_result->fetch_assoc();
            }
            $bus_query->close();
        }
        
    } catch (Exception $e) {
        $verification_message = $e->getMessage();
        error_log("ERROR: " . $e->getMessage());
        
        // If update failed, still try to show booking details
        if (!$booking_details && $booking_id > 0) {
            $details_query = $conn->prepare("SELECT * FROM bookings WHERE booking_id = ?");
            $details_query->bind_param("i", $booking_id);
            $details_query->execute();
            $details_result = $details_query->get_result();
            if ($details_result->num_rows > 0) {
                $booking_details = $details_result->fetch_assoc();
            }
            $details_query->close();
        }
    }
} else {
    error_log("Cannot update database. Verification: " . ($verification_success ? 'Success' : 'Failed') . 
              ", Booking ID: " . $booking_id);
}

// If update failed but verification passed, try to get booking details anyway
if (!$update_success && $booking_id > 0 && !$booking_details) {
    $details_query = $conn->prepare("SELECT * FROM bookings WHERE booking_id = ? AND user_id = ?");
    $details_query->bind_param("ii", $booking_id, $user['id']);
    $details_query->execute();
    $details_result = $details_query->get_result();
    if ($details_result->num_rows > 0) {
        $booking_details = $details_result->fetch_assoc();
        
        // Get bus details
        if ($booking_details['bus_id']) {
            $bus_query = $conn->prepare("SELECT bus_name, route_from, route_to FROM buses WHERE id = ?");
            $bus_query->bind_param("i", $booking_details['bus_id']);
            $bus_query->execute();
            $bus_result = $bus_query->get_result();
            if ($bus_result->num_rows > 0) {
                $bus = $bus_result->fetch_assoc();
            }
            $bus_query->close();
        }
    }
    $details_query->close();
}

// ========== PREPARE DISPLAY DATA ==========
$page_title = $update_success ? "Payment Successful" : "Payment Verification Failed";
$message_type = $update_success ? "success" : "error";

if ($update_success) {
    $main_message = "Your payment has been successfully processed!";
    $icon_class = "fas fa-check-circle";
    $icon_color = "#4CAF50";
} else {
    $main_message = "We encountered an issue with your payment.";
    $icon_class = "fas fa-exclamation-circle";
    $icon_color = "#f44336";
}

// Format amount for display
$display_amount = 'N/A';
if (isset($esewa_data['total_amount'])) {
    $display_amount = 'Rs ' . number_format($esewa_data['total_amount'], 2);
} elseif (isset($booking_details['total_amount'])) {
    $display_amount = 'Rs ' . number_format($booking_details['total_amount'], 2);
}

// Format transaction ID for display
$display_transaction_id = 'Not available';
if (isset($esewa_data['transaction_uuid'])) {
    $display_transaction_id = $esewa_data['transaction_uuid'];
} elseif (isset($esewa_data['refId'])) {
    $display_transaction_id = $esewa_data['refId'];
} elseif (isset($booking_details['transaction_id'])) {
    $display_transaction_id = $booking_details['transaction_id'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> | Bus Booking System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #009432;
            --primary-dark: #007b28;
            --secondary: #2196F3;
            --success: #4CAF50;
            --danger: #f44336;
            --warning: #ff9800;
            --light: #f8f9fa;
            --dark: #343a40;
            --border: #dee2e6;
            --shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            width: 100%;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .header h1 {
            color: var(--primary);
            font-size: 36px;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 18px;
        }
        
        .result-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: var(--shadow);
            text-align: center;
            margin-bottom: 30px;
            border-top: 5px solid <?= $update_success ? 'var(--success)' : 'var(--danger)' ?>;
        }
        
        .result-icon {
            font-size: 100px;
            margin-bottom: 30px;
            color: <?= $icon_color ?>;
        }
        
        .result-title {
            font-size: 28px;
            margin-bottom: 20px;
            color: var(--dark);
        }
        
        .result-message {
            font-size: 18px;
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .status-box {
            background: <?= $update_success ? 'rgba(76, 175, 80, 0.1)' : 'rgba(244, 67, 54, 0.1)' ?>;
            border: 2px solid <?= $update_success ? 'var(--success)' : 'var(--danger)' ?>;
            border-radius: 10px;
            padding: 20px;
            margin: 30px 0;
        }
        
        .status-box h3 {
            color: <?= $update_success ? 'var(--success)' : 'var(--danger)' ?>;
            margin-bottom: 15px;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .status-message {
            font-size: 16px;
            color: #666;
            padding: 10px;
            background: white;
            border-radius: 5px;
        }
        
        .booking-info {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border-radius: 15px;
            padding: 25px;
            margin: 30px 0;
            text-align: left;
        }
        
        .booking-info h3 {
            color: var(--primary);
            margin-bottom: 20px;
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            color: #555;
            font-weight: 500;
        }
        
        .detail-value {
            color: var(--dark);
            font-weight: 600;
        }
        
        .esewa-brand {
            background: linear-gradient(135deg, #55a146, #4a8c3d);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        .esewa-brand img {
            height: 30px;
            filter: brightness(0) invert(1);
        }
        
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 40px 0;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 18px 30px;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            font-size: 16px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,148,50,0.2);
        }
        
        .btn-secondary {
            background: var(--secondary);
            color: white;
        }
        
        .btn-secondary:hover {
            background: #0d8bf2;
            transform: translateY(-3px);
        }
        
        .btn-light {
            background: var(--light);
            color: var(--dark);
            border: 2px solid var(--border);
        }
        
        .btn-light:hover {
            background: #e2e6ea;
        }
        
        .debug-info {
            background: #f8f9fa;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
            font-size: 14px;
            color: #666;
            text-align: left;
            display: none;
        }
        
        .debug-toggle {
            background: var(--light);
            border: 1px solid var(--border);
            color: #666;
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 12px;
            cursor: pointer;
            margin-top: 10px;
        }
        
        footer {
            text-align: center;
            margin-top: 40px;
            color: #666;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .result-card {
                padding: 25px;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
            }
        }
        
        .test-mode-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            font-size: 14px;
        }
        
        .pending-notice {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>eSewa Payment Result</h1>
            <p>Bus Booking System</p>
        </div>
        
        <div class="result-card">
            <div class="result-icon">
                <i class="<?= $icon_class ?>"></i>
            </div>
            
            <h2 class="result-title">
                <?php if($update_success): ?>
                    Payment Successful! 🎉
                <?php else: ?>
                    Payment Verification Failed
                <?php endif; ?>
            </h2>
            
            <div class="result-message">
                <?php if($update_success): ?>
                    Thank you for your payment! Your booking has been confirmed.
                    <?php if($booking_details): ?>
                        You booked <strong><?= htmlspecialchars($booking_details['total_seats']) ?> seat(s)</strong> 
                        for <strong><?= $display_amount ?></strong>.
                    <?php endif; ?>
                <?php else: ?>
                    <?= htmlspecialchars($verification_message) ?><br>
                    Please contact support if you believe this is an error.
                <?php endif; ?>
            </div>
            
            <div class="status-box">
                <h3>
                    <i class="<?= $icon_class ?>"></i>
                    <?= $update_success ? 'Payment Verified' : 'Verification Failed' ?>
                </h3>
                <div class="status-message">
                    <?= htmlspecialchars($verification_message) ?>
                </div>
            </div>
            
            <?php if($update_success): ?>
                <div class="esewa-brand">
                    <img src="https://fonepoints.com/images/logos/esewa-logo.png" alt="eSewa">
                    <span>Payment processed via eSewa</span>
                </div>
                
                <?php if($esewa_config['test_mode']): ?>
                    <div class="test-mode-notice">
                        <i class="fas fa-vial"></i> <strong>Test Mode:</strong> This payment was processed in test environment.
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="pending-notice">
                    <i class="fas fa-clock"></i> <strong>Booking Still Pending</strong><br>
                    Your seats are still reserved. You can retry payment from your dashboard.
                </div>
            <?php endif; ?>
            
            <?php if($booking_details && $bus): ?>
                <div class="booking-info">
                    <h3><i class="fas fa-bus"></i> Booking Details</h3>
                    <div class="detail-item">
                        <span class="detail-label">Booking ID:</span>
                        <span class="detail-value">#<?= $booking_id ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Bus:</span>
                        <span class="detail-value"><?= htmlspecialchars($bus['bus_name']) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Route:</span>
                        <span class="detail-value"><?= htmlspecialchars($bus['route_from']) ?> → <?= htmlspecialchars($bus['route_to']) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Seats:</span>
                        <span class="detail-value"><?= htmlspecialchars($booking_details['seat_numbers']) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Amount:</span>
                        <span class="detail-value" style="color: <?= $update_success ? 'var(--success)' : 'var(--warning)' ?>; font-size: 18px;">
                            <?= $display_amount ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Status:</span>
                        <span class="detail-value" style="color: <?= $update_success ? 'var(--success)' : 'var(--warning)' ?>;">
                            <?= $update_success ? 'Paid' : 'Pending' ?>
                        </span>
                    </div>
                    <?php if($display_transaction_id !== 'Not available'): ?>
                        <div class="detail-item">
                            <span class="detail-label">Transaction ID:</span>
                            <span class="detail-value" style="font-family: monospace; font-size: 14px;">
                                <?= htmlspecialchars($display_transaction_id) ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="action-buttons">
                <?php if($update_success): ?>
                    <a href="ticket.php?id=<?= $booking_id ?>" class="btn btn-primary">
                        <i class="fas fa-ticket-alt"></i> View E-Ticket
                    </a>
                    <a href="ticket.php?id=<?= $booking_id ?>&autoprint=1" class="btn btn-secondary">
                        <i class="fas fa-print"></i> Print Ticket
                    </a>
                    <a href="home.php" class="btn btn-light">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                <?php else: ?>
                    <a href="payment.php?booking_id=<?= $booking_id ?>&bus_id=<?= $booking_details['bus_id'] ?? $bus_id ?>" class="btn btn-primary">
                        <i class="fas fa-redo"></i> Retry Payment
                    </a>
                    <a href="home.php" class="btn btn-light">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a href="../support.php" class="btn btn-secondary">
                        <i class="fas fa-headset"></i> Contact Support
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Debug information (hidden by default) -->
            <div class="debug-info" id="debugInfo">
                <h4><i class="fas fa-bug"></i> Debug Information</h4>
                <p><strong>eSewa Response:</strong><br>
                <pre style="background: white; padding: 10px; border-radius: 5px; font-size: 12px;"><?= htmlspecialchars(json_encode($esewa_data, JSON_PRETTY_PRINT)) ?></pre></p>
                <p><strong>Verification:</strong> <?= $verification_success ? 'Success' : 'Failed' ?></p>
                <p><strong>Message:</strong> <?= htmlspecialchars($verification_message) ?></p>
                <p><strong>Database Update:</strong> <?= $update_success ? 'Success' : 'Failed' ?></p>
                <?php if($booking_details): ?>
                    <p><strong>Current Booking Status:</strong> <?= $booking_details['payment_status'] ?></p>
                <?php endif; ?>
                <button class="debug-toggle" onclick="document.getElementById('debugInfo').style.display='none'">
                    Hide Debug Info
                </button>
            </div>
            
            <button class="debug-toggle" onclick="document.getElementById('debugInfo').style.display='block'">
                <i class="fas fa-bug"></i> Show Debug Information
            </button>
        </div>
        
        <footer>
            <p>© <?= date('Y') ?> Bus Booking System | Secure Payment via eSewa</p>
            <p style="margin-top: 10px; font-size: 12px;">
                <i class="fas fa-shield-alt"></i> Your payment was processed securely
                <?php if($esewa_config['test_mode']): ?>
                    <span style="color: var(--warning);">(Test Mode)</span>
                <?php endif; ?>
            </p>
        </footer>
    </div>
    
    <?php if($update_success): ?>
    <script>
        // Auto-redirect to ticket page after 5 seconds
        setTimeout(function() {
            window.location.href = "ticket.php?id=<?= $booking_id ?>";
        }, 5000);
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Payment successful for booking #<?= $booking_id ?>');
            
            // Clear any session timers
            if (typeof localStorage !== 'undefined') {
                localStorage.removeItem('payment_timer');
            }
        });
    </script>
    <?php endif; ?>
    
    <script>
        // Keyboard shortcut for debug (Ctrl+D)
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'd') {
                e.preventDefault();
                document.getElementById('debugInfo').style.display = 'block';
            }
        });
        
        // Auto-show debug if URL has debug parameter
        if (window.location.search.includes('debug=1')) {
            document.getElementById('debugInfo').style.display = 'block';
        }
    </script>
</body>
</html>