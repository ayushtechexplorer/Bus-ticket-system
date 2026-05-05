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

// Get parameters
$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$bus_id = isset($_GET['bus_id']) ? intval($_GET['bus_id']) : 0;
$error_code = isset($_GET['error_code']) ? $_GET['error_code'] : '';
$error_message = isset($_GET['error_message']) ? $_GET['error_message'] : '';

// Error messages mapping
$error_messages = [
    'user_cancelled' => 'Payment was cancelled by you.',
    'timeout' => 'Payment timed out. Please try again.',
    'insufficient_balance' => 'Insufficient balance in your Khalti account.',
    'invalid_mpin' => 'Invalid MPIN entered.',
    'transaction_declined' => 'Transaction was declined by Khalti.',
    'technical_error' => 'Technical error occurred. Please try again.',
    'network_error' => 'Network error. Please check your internet connection.'
];

// Get user-friendly error message
$user_error = isset($error_messages[$error_code]) 
    ? $error_messages[$error_code] 
    : ($error_message ?: 'Payment failed. Please try again.');

// Fetch booking details if available
$booking = null;
if ($booking_id) {
    $booking_query = $conn->prepare("
        SELECT b.*, bus.bus_name, bus.route_from, bus.route_to 
        FROM bookings b 
        JOIN buses bus ON b.bus_id = bus.id 
        WHERE b.booking_id = ? AND b.user_id = ?
    ");
    $booking_query->bind_param("ii", $booking_id, $user['id']);
    $booking_query->execute();
    $booking_result = $booking_query->get_result();
    $booking = $booking_result->fetch_assoc();
    $booking_query->close();
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
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .container {
            max-width: 800px;
            width: 100%;
        }
        
        .card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: var(--shadow);
            text-align: center;
        }
        
        .error-icon {
            font-size: 100px;
            color: var(--danger);
            margin-bottom: 30px;
            animation: shake 0.5s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% {transform: translateX(0);}
            25% {transform: translateX(-10px);}
            75% {transform: translateX(10px);}
        }
        
        .khalti-icon {
            font-size: 60px;
            color: #5C2D91;
            margin: 20px 0;
            opacity: 0.7;
        }
        
        h1 {
            color: var(--danger);
            margin-bottom: 20px;
            font-size: 36px;
        }
        
        h2 {
            color: var(--dark);
            margin-bottom: 30px;
            font-size: 24px;
        }
        
        .message {
            padding: 25px;
            border-radius: 10px;
            margin: 25px 0;
            font-size: 18px;
            text-align: left;
        }
        
        .error-message {
            background: rgba(244, 67, 54, 0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }
        
        .warning-message {
            background: rgba(255, 152, 0, 0.1);
            color: var(--warning);
            border-left: 4px solid var(--warning);
        }
        
        .info-message {
            background: rgba(33, 150, 243, 0.1);
            color: var(--secondary);
            border-left: 4px solid var(--secondary);
        }
        
        .booking-details {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            padding: 25px;
            border-radius: 15px;
            margin: 25px 0;
            text-align: left;
            border: 2px dashed var(--warning);
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        
        .detail-label {
            color: #666;
            font-weight: 500;
        }
        
        .detail-value {
            color: var(--dark);
            font-weight: 600;
        }
        
        .expiry-warning {
            background: linear-gradient(135deg, #ff9800, #ff5722);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin: 25px 0;
            text-align: center;
        }
        
        .timer {
            font-size: 28px;
            font-weight: bold;
            margin: 10px 0;
            letter-spacing: 2px;
        }
        
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 40px 0 20px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: white;
            border: none;
            padding: 18px 30px;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 16px;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }
        
        .btn-khalti {
            background: linear-gradient(135deg, #5C2D91, #7733B5);
        }
        
        .btn-khalti:hover {
            background: linear-gradient(135deg, #4a2474, #5e2a8f);
        }
        
        .btn-primary {
            background: var(--primary);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .btn-secondary {
            background: var(--secondary);
        }
        
        .btn-secondary:hover {
            background: #0d8bf2;
        }
        
        .btn-light {
            background: var(--light);
            color: var(--dark);
        }
        
        .btn-light:hover {
            background: #e2e6ea;
        }
        
        .error-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 25px 0;
            text-align: left;
            font-family: monospace;
            font-size: 14px;
        }
        
        .troubleshooting {
            background: #e3f2fd;
            padding: 25px;
            border-radius: 15px;
            margin: 25px 0;
            text-align: left;
        }
        
        .troubleshooting h3 {
            color: var(--secondary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .troubleshooting ol {
            padding-left: 25px;
            margin: 15px 0;
        }
        
        .troubleshooting li {
            margin-bottom: 10px;
            line-height: 1.6;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            color: #666;
            font-size: 14px;
        }
        
        .error-code {
            font-family: monospace;
            background: #f5f5f5;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 14px;
            margin: 0 5px;
        }
        
        @media (max-width: 600px) {
            .card {
                padding: 25px;
            }
            
            h1 {
                font-size: 28px;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="error-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            
            <div class="khalti-icon">
                <i class="fas fa-wallet"></i>
            </div>
            
            <h1>Payment Failed</h1>
            <h2>Khalti payment was not successful</h2>
            
            <div class="message error-message">
                <p><i class="fas fa-exclamation-triangle"></i> <strong><?= htmlspecialchars($user_error) ?></strong></p>
                <p style="margin-top: 10px;">Your booking is still pending. Please complete the payment to confirm your seats.</p>
            </div>
            
            <?php if($error_code): ?>
            <div class="error-details">
                <p><strong>Error Code:</strong> <span class="error-code"><?= htmlspecialchars($error_code) ?></span></p>
                <?php if($error_message): ?>
                <p><strong>Error Message:</strong> <?= htmlspecialchars($error_message) ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if($booking): ?>
            <div class="booking-details">
                <h3>Your Booking Details</h3>
                <div class="detail-item">
                    <span class="detail-label">Booking ID:</span>
                    <span class="detail-value">#<?= $booking_id ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Bus Name:</span>
                    <span class="detail-value"><?= htmlspecialchars($booking['bus_name']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Route:</span>
                    <span class="detail-value"><?= htmlspecialchars($booking['route_from']) ?> → <?= htmlspecialchars($booking['route_to']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Seats:</span>
                    <span class="detail-value"><?= htmlspecialchars($booking['seat_numbers']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Total Amount:</span>
                    <span class="detail-value">Rs <?= number_format($booking['total_amount']) ?></span>
                </div>
            </div>
            
            <div class="expiry-warning">
                <h3><i class="fas fa-clock"></i> Payment Time Remaining</h3>
                <div class="timer" id="countdown">10:00</div>
                <p>Complete your payment within the time limit to secure your seats</p>
            </div>
            <?php endif; ?>
            
            <div class="troubleshooting">
                <h3><i class="fas fa-tools"></i> Troubleshooting Tips</h3>
                <ol>
                    <li>Ensure you have sufficient balance in your Khalti account</li>
                    <li>Check your internet connection</li>
                    <li>Verify your MPIN is correct</li>
                    <li>Try using a different payment method if problem persists</li>
                    <li>Contact Khalti support at 1660-01-55555 if issue is with Khalti account</li>
                </ol>
            </div>
            
            <div class="action-buttons">
                <?php if($booking_id && $bus_id): ?>
                <a href="payment.php?booking_id=<?= $booking_id ?>&bus_id=<?= $bus_id ?>" class="btn btn-khalti">
                    <i class="fas fa-redo"></i> Try Khalti Again
                </a>
                <?php endif; ?>
                
                <a href="payment.php?booking_id=<?= $booking_id ?>&bus_id=<?= $bus_id ?>&method=esewa" class="btn btn-primary">
                    <i class="fas fa-exchange-alt"></i> Try eSewa Instead
                </a>
                
                <a href="home.php" class="btn btn-light">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                
                <a href="support.php" class="btn btn-secondary">
                    <i class="fas fa-headset"></i> Contact Support
                </a>
            </div>
            
            <div class="message info-message">
                <p><i class="fas fa-info-circle"></i> <strong>Note:</strong> No money has been deducted from your account. Your seats will be held for a limited time only.</p>
            </div>
            
            <div class="footer">
                <p>© <?= date('Y') ?> Bus Booking System | Need help? Contact: support@busbookingsystem.com | Phone: 01-1234567</p>
                <p style="margin-top: 10px; font-size: 12px;">
                    Reference: KHALTIFL<?= time() ?> | User ID: <?= $user['id'] ?>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Countdown timer for payment expiry
        let remaining = 600; // 10 minutes in seconds
        
        function updateTimer() {
            const minutes = Math.floor(remaining / 60);
            const seconds = remaining % 60;
            document.getElementById('countdown').textContent = 
                `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            if (remaining <= 60) {
                document.getElementById('countdown').style.color = '#ff4444';
                document.getElementById('countdown').style.animation = 'pulse 1s infinite';
            }
            
            if (remaining <= 0) {
                clearInterval(timerInterval);
                document.getElementById('countdown').textContent = 'EXPIRED';
                alert('Payment time has expired. You will be redirected to dashboard.');
                window.location.href = 'home.php?timeout=1';
            }
            remaining--;
        }
        
        <?php if($booking): ?>
        const timerInterval = setInterval(updateTimer, 1000);
        updateTimer();
        <?php endif; ?>
        
        // Auto-redirect after 30 seconds
        setTimeout(() => {
            if (!window.location.href.includes('home.php')) {
                console.log('Auto-redirecting to payment page...');
            }
        }, 30000);
    </script>
</body>
</html>