<?php
session_start();
include('../includes/db.php');

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

// Get eSewa response data
$esewa_data = $_GET;

// Log failure for debugging
error_log("eSewa Failure Response: " . json_encode($esewa_data));

// Try to get the reason for failure
$failure_reason = "Payment was cancelled or failed";
if (isset($esewa_data['message'])) {
    $failure_reason = htmlspecialchars($esewa_data['message']);
} elseif (isset($esewa_data['reason'])) {
    $failure_reason = htmlspecialchars($esewa_data['reason']);
}

// Get booking details if available
$booking_details = null;
$bus = null;

if ($booking_id > 0) {
    $booking_query = "SELECT * FROM bookings WHERE booking_id = ? AND user_id = ?";
    $booking_stmt = $conn->prepare($booking_query);
    $booking_stmt->bind_param("ii", $booking_id, $user['id']);
    $booking_stmt->execute();
    $booking_result = $booking_stmt->get_result();
    
    if ($booking_result->num_rows > 0) {
        $booking_details = $booking_result->fetch_assoc();
        
        // Get bus details
        if ($booking_details['bus_id']) {
            $bus_query = $conn->prepare("SELECT bus_name, route_from, route_to FROM buses WHERE id = ?");
            $bus_query->bind_param("i", $booking_details['bus_id']);
            $bus_query->execute();
            $bus = $bus_query->get_result()->fetch_assoc();
            $bus_query->close();
        }
    }
    $booking_stmt->close();
}

// Check if booking is still pending (to allow retry)
$can_retry = false;
if ($booking_details && $booking_details['payment_status'] == 'Pending') {
    $can_retry = true;
    
    // Check if booking has expired (10 minutes)
    $created_time = strtotime($booking_details['created_at']);
    $current_time = time();
    $minutes_passed = ($current_time - $created_time) / 60;
    
    if ($minutes_passed >= 10) {
        $can_retry = false;
        $failure_reason .= " (Booking expired after 10 minutes)";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed | Bus Booking System</title>
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
            background: linear-gradient(135deg, #fff5f5 0%, #ffeaea 100%);
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
            color: var(--danger);
            font-size: 36px;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 18px;
        }
        
        .failure-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: var(--shadow);
            text-align: center;
            margin-bottom: 30px;
            border-top: 5px solid var(--danger);
        }
        
        .failure-icon {
            font-size: 100px;
            margin-bottom: 30px;
            color: var(--danger);
        }
        
        .failure-title {
            font-size: 28px;
            margin-bottom: 20px;
            color: var(--dark);
        }
        
        .failure-message {
            font-size: 18px;
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .reason-box {
            background: #fff5f5;
            border: 2px dashed #ffcccc;
            border-radius: 10px;
            padding: 20px;
            margin: 30px 0;
            text-align: left;
        }
        
        .reason-box h3 {
            color: var(--danger);
            margin-bottom: 15px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .reason-text {
            color: #d32f2f;
            font-size: 16px;
            padding: 10px;
            background: white;
            border-radius: 5px;
            border-left: 4px solid var(--danger);
        }
        
        .booking-info {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin: 30px 0;
            text-align: left;
        }
        
        .booking-info h3 {
            color: var(--dark);
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
            border-bottom: 1px solid var(--border);
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            color: #666;
            font-weight: 500;
        }
        
        .detail-value {
            color: var(--dark);
            font-weight: 600;
        }
        
        .timer-warning {
            background: linear-gradient(135deg, #ff9800, #ff5722);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        
        .timer-warning h4 {
            margin-bottom: 10px;
            font-size: 18px;
        }
        
        #retryTimer {
            font-size: 28px;
            font-weight: bold;
            margin: 10px 0;
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
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #d32f2f;
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
        
        .esewa-brand {
            background: linear-gradient(135deg, #ff6b6b, #ff4757);
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
        
        .tips {
            background: #e3f2fd;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        
        .tips h4 {
            color: var(--secondary);
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .tips ul {
            padding-left: 20px;
            color: #666;
        }
        
        .tips li {
            margin-bottom: 10px;
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
            
            .failure-card {
                padding: 25px;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
            }
        }
        
        .debug-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
            font-size: 14px;
            color: #856404;
            text-align: left;
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Payment Failed</h1>
            <p>Bus Booking System - eSewa Payment</p>
        </div>
        
        <div class="failure-card">
            <div class="failure-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            
            <h2 class="failure-title">Payment Not Completed</h2>
            
            <div class="failure-message">
                We were unable to process your payment through eSewa.<br>
                Don't worry - your seats are still reserved for a short time.
            </div>
            
            <div class="esewa-brand">
                <img src="https://fonepoints.com/images/logos/esewa-logo.png" alt="eSewa">
                <span>Payment processing failed</span>
            </div>
            
            <div class="reason-box">
                <h3><i class="fas fa-exclamation-triangle"></i> Reason for Failure</h3>
                <div class="reason-text">
                    <?= $failure_reason ?>
                </div>
            </div>
            
            <?php if($booking_details && $bus): ?>
                <div class="booking-info">
                    <h3><i class="fas fa-bus"></i> Your Booking</h3>
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
                        <span class="detail-value">Rs <?= number_format($booking_details['total_amount']) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Status:</span>
                        <span class="detail-value" style="color: var(--warning);">Payment Pending</span>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if($can_retry): ?>
                <?php
                // Calculate remaining time for retry
                $created_time = strtotime($booking_details['created_at']);
                $current_time = time();
                $seconds_remaining = (10 * 60) - ($current_time - $created_time);
                $minutes_remaining = ceil($seconds_remaining / 60);
                ?>
                
                <div class="timer-warning">
                    <h4><i class="fas fa-clock"></i> Time to Complete Payment</h4>
                    <div id="retryTimer"><?= max(0, $minutes_remaining) ?> minutes</div>
                    <p>Complete payment within this time to keep your seats</p>
                </div>
            <?php else: ?>
                <div class="timer-warning" style="background: linear-gradient(135deg, #f44336, #d32f2f);">
                    <h4><i class="fas fa-ban"></i> Booking Expired</h4>
                    <p>Your booking reservation has expired. Please start a new booking.</p>
                </div>
            <?php endif; ?>
            
            <div class="tips">
                <h4><i class="fas fa-lightbulb"></i> Tips for Successful Payment</h4>
                <ul>
                    <li>Ensure you have sufficient balance in your eSewa account</li>
                    <li>Check your internet connection</li>
                    <li>Use the correct eSewa ID and password</li>
                    <li>For test environment, use: ID <strong>9806800001</strong>, Password <strong>Nepal@123</strong></li>
                    <li>Try using a different payment method if issues persist</li>
                </ul>
            </div>
            
            <div class="action-buttons">
                <?php if($can_retry): ?>
                    <a href="payment.php?booking_id=<?= $booking_id ?>&bus_id=<?= $bus_id ?>" class="btn btn-primary">
                        <i class="fas fa-redo"></i> Retry Payment
                    </a>
                    <a href="payment.php?booking_id=<?= $booking_id ?>&bus_id=<?= $bus_id ?>&change_method=1" class="btn btn-secondary">
                        <i class="fas fa-exchange-alt"></i> Change Payment Method
                    </a>
                    <a href="cancel_booking.php?booking_id=<?= $booking_id ?>&bus_id=<?= $bus_id ?>" class="btn btn-danger">
                        <i class="fas fa-times"></i> Cancel Booking
                    </a>
                <?php else: ?>
                    <a href="search.php" class="btn btn-primary">
                        <i class="fas fa-search"></i> Book Again
                    </a>
                    <a href="home.php" class="btn btn-light">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a href="../support.php" class="btn btn-secondary">
                        <i class="fas fa-headset"></i> Contact Support
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Debug information -->
            <div class="debug-info">
                <h4><i class="fas fa-bug"></i> Debug Information</h4>
                <p><strong>eSewa Response:</strong> <?= htmlspecialchars(json_encode($esewa_data)) ?></p>
                <p><strong>Booking ID:</strong> <?= $booking_id ?></p>
                <p><strong>Can Retry:</strong> <?= $can_retry ? 'Yes' : 'No' ?></p>
                <button class="btn btn-light" onclick="this.parentElement.style.display='none'" style="padding: 5px 10px; font-size: 12px; margin-top: 10px;">
                    Hide Debug Info
                </button>
            </div>
        </div>
        
        <footer>
            <p>© <?= date('Y') ?> Bus Booking System | Need help? Contact our support team</p>
            <p style="margin-top: 10px; font-size: 12px;">
                <i class="fas fa-phone"></i> Support: 01-1234567 | 
                <i class="fas fa-envelope"></i> support@busbooking.com
            </p>
        </footer>
    </div>
    
    <?php if($can_retry): ?>
    <script>
        // Countdown timer for retry
        let remainingSeconds = <?= max(0, $seconds_remaining) ?>;
        const retryTimerElement = document.getElementById('retryTimer');
        
        function updateRetryTimer() {
            const minutes = Math.floor(remainingSeconds / 60);
            const seconds = remainingSeconds % 60;
            
            if (minutes > 0) {
                retryTimerElement.textContent = `${minutes}m ${seconds.toString().padStart(2, '0')}s`;
            } else {
                retryTimerElement.textContent = `${seconds}s`;
            }
            
            if (remainingSeconds <= 60) {
                retryTimerElement.style.color = '#ff4444';
                retryTimerElement.style.animation = 'pulse 1s infinite';
            }
            
            if (remainingSeconds <= 0) {
                clearInterval(retryTimerInterval);
                alert("Your booking reservation has expired!");
                window.location.href = "home.php?timeout=1";
            }
            
            remainingSeconds--;
        }
        
        const retryTimerInterval = setInterval(updateRetryTimer, 1000);
        updateRetryTimer();
        
        // Auto-refresh the page when time is very low
        if (remainingSeconds <= 60) {
            setTimeout(function() {
                window.location.reload();
            }, 30000); // Refresh every 30 seconds when time is low
        }
    </script>
    <?php endif; ?>
    
    <script>
        // Show debug info on Ctrl+D
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'd') {
                e.preventDefault();
                document.querySelector('.debug-info').style.display = 'block';
            }
        });
    </script>
</body>
</html>