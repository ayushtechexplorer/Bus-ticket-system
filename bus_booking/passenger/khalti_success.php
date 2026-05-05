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
$pidx = isset($_GET['pidx']) ? $_GET['pidx'] : '';
$transaction_id = isset($_GET['transaction_id']) ? $_GET['transaction_id'] : '';
$amount = isset($_GET['amount']) ? $_GET['amount'] : '';

// Validate parameters
if (!$booking_id || !$bus_id) {
    header("Location: home.php?error=invalid_params");
    exit;
}

// Fetch booking details
$booking_query = $conn->prepare("
    SELECT b.*, bus.bus_name, bus.route_from, bus.route_to, bus.departure_time 
    FROM bookings b 
    JOIN buses bus ON b.bus_id = bus.id 
    WHERE b.booking_id = ? AND b.user_id = ?
");
$booking_query->bind_param("ii", $booking_id, $user['id']);
$booking_query->execute();
$booking_result = $booking_query->get_result();
$booking = $booking_result->fetch_assoc();
$booking_query->close();

if (!$booking) {
    header("Location: home.php?error=booking_not_found");
    exit;
}

// If booking is already paid, redirect to ticket
if ($booking['payment_status'] == 'Paid') {
    header("Location: ticket.php?id=" . $booking_id);
    exit;
}

// If payment is still pending, verify with Khalti API
if ($booking['payment_status'] == 'Pending' && $pidx) {
    // Khalti Configuration (same as in payment.php)
    $khalti_config = [
        'test_mode' => true,
        'public_key' => 'test_public_key_dc74e0fd57cb46cd93832aee0a507256',
        'secret_key' => 'test_secret_key_f493ae4f7d8e4de1b3a8c0c5f5a3b9c3',
        'verify_url' => 'https://a.khalti.com/api/v2/epayment/lookup/'
    ];
    
    // Verify payment with Khalti
    $verify_url = $khalti_config['verify_url'] . $pidx . '/';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $verify_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Key ' . $khalti_config['secret_key']
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if (isset($result['status']) && $result['status'] == 'Completed') {
        // Verify amount matches
        $booking_amount_in_paisa = $booking['total_amount'] * 100;
        
        if ($result['total_amount'] == $booking_amount_in_paisa) {
            // Update booking status
            $update_query = "UPDATE bookings 
                            SET payment_status = 'Paid', 
                                payment_method = 'Khalti',
                                payment_reference = ?,
                                pidx = ?,
                                paid_at = NOW()
                            WHERE booking_id = ?";
            
            $update_stmt = $conn->prepare($update_query);
            $pidx_value = $pidx ?: $result['idx'];
            $update_stmt->bind_param("ssi", $transaction_id, $pidx_value, $booking_id);
            
            if ($update_stmt->execute()) {
                // Clear session booking data
                unset($_SESSION['recently_booked_seats'], $_SESSION['recent_booking_bus_id'], $_SESSION['booking_time']);
                
                // Success - continue to show success page
                $success = true;
            } else {
                $error = "Database update failed. Please contact support.";
                $success = false;
            }
            $update_stmt->close();
        } else {
            $error = "Payment amount mismatch. Expected: Rs " . $booking['total_amount'] . ", Received: Rs " . ($result['total_amount'] / 100);
            $success = false;
        }
    } else {
        $error = "Khalti payment verification failed. Status: " . ($result['status'] ?? 'Unknown');
        $success = false;
    }
} else {
    // If no pidx but booking is paid (manual verification already done)
    if ($booking['payment_status'] == 'Paid') {
        $success = true;
    } else {
        $error = "Payment verification parameters missing.";
        $success = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful | Bus Booking System</title>
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
        
        .success-icon {
            font-size: 100px;
            color: var(--success);
            margin-bottom: 30px;
            animation: bounce 1s ease-in-out;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
            40% {transform: translateY(-30px);}
            60% {transform: translateY(-15px);}
        }
        
        .khalti-icon {
            font-size: 60px;
            color: #5C2D91;
            margin: 20px 0;
        }
        
        h1 {
            color: var(--success);
            margin-bottom: 20px;
            font-size: 36px;
        }
        
        h2 {
            color: var(--dark);
            margin-bottom: 30px;
            font-size: 24px;
        }
        
        .message {
            padding: 20px;
            border-radius: 10px;
            margin: 25px 0;
            font-size: 18px;
        }
        
        .success-message {
            background: rgba(76, 175, 80, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
            text-align: left;
        }
        
        .error-message {
            background: rgba(244, 67, 54, 0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
            text-align: left;
        }
        
        .booking-details {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            padding: 25px;
            border-radius: 15px;
            margin: 25px 0;
            text-align: left;
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
            background: var(--primary);
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
            background: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,148,50,0.3);
        }
        
        .btn-secondary {
            background: var(--secondary);
        }
        
        .btn-secondary:hover {
            background: #0d8bf2;
        }
        
        .btn-khalti {
            background: linear-gradient(135deg, #5C2D91, #7733B5);
        }
        
        .btn-khalti:hover {
            background: linear-gradient(135deg, #4a2474, #5e2a8f);
        }
        
        .btn-light {
            background: var(--light);
            color: var(--dark);
        }
        
        .btn-light:hover {
            background: #e2e6ea;
        }
        
        .payment-info {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 10px;
            margin: 25px 0;
            text-align: left;
        }
        
        .payment-info h3 {
            color: #5C2D91;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px dashed #ddd;
        }
        
        .receipt {
            background: white;
            border: 2px solid var(--primary);
            border-radius: 10px;
            padding: 25px;
            margin: 25px 0;
            text-align: center;
        }
        
        .receipt h3 {
            color: var(--primary);
            margin-bottom: 20px;
        }
        
        .receipt-number {
            font-family: monospace;
            font-size: 20px;
            color: var(--dark);
            background: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
            margin: 15px 0;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            color: #666;
            font-size: 14px;
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
            <?php if(isset($success) && $success): ?>
                <!-- Success Content -->
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                
                <div class="khalti-icon">
                    <i class="fas fa-wallet"></i>
                </div>
                
                <h1>Payment Successful!</h1>
                <h2>Thank you for using Khalti</h2>
                
                <div class="message success-message">
                    <p><i class="fas fa-check"></i> Your payment of <strong>Rs <?= number_format($booking['total_amount']) ?></strong> has been confirmed.</p>
                    <p><i class="fas fa-check"></i> Your booking is now confirmed and your seats are reserved.</p>
                </div>
                
                <div class="receipt">
                    <h3>Payment Receipt</h3>
                    <div class="receipt-number">
                        #<?= $booking_id ?>
                    </div>
                    <p>Payment Method: <strong>Khalti</strong></p>
                    <p>Transaction ID: <strong><?= htmlspecialchars($pidx) ?></strong></p>
                    <p>Date: <?= date('d M Y, h:i A') ?></p>
                </div>
                
                <div class="booking-details">
                    <h3>Booking Details</h3>
                    <div class="detail-item">
                        <span class="detail-label">Bus Name:</span>
                        <span class="detail-value"><?= htmlspecialchars($booking['bus_name']) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Route:</span>
                        <span class="detail-value"><?= htmlspecialchars($booking['route_from']) ?> → <?= htmlspecialchars($booking['route_to']) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Departure:</span>
                        <span class="detail-value"><?= date("d M Y, h:i A", strtotime($booking['departure_time'])) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Seats:</span>
                        <span class="detail-value"><?= htmlspecialchars($booking['seat_numbers']) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Total Paid:</span>
                        <span class="detail-value" style="color: var(--success); font-size: 18px;">Rs <?= number_format($booking['total_amount']) ?></span>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <a href="ticket.php?id=<?= $booking_id ?>" class="btn">
                        <i class="fas fa-ticket-alt"></i> View E-Ticket
                    </a>
                    <a href="ticket.php?id=<?= $booking_id ?>&autoprint=1" class="btn btn-secondary">
                        <i class="fas fa-print"></i> Print Ticket
                    </a>
                    <a href="home.php" class="btn btn-light">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </div>
                
                <div class="payment-info">
                    <h3><i class="fas fa-info-circle"></i> Important Information</h3>
                    <p>1. Please show your e-ticket to the bus driver before boarding.</p>
                    <p>2. Arrive at the boarding point at least 30 minutes before departure.</p>
                    <p>3. Keep this receipt for any future reference.</p>
                </div>
                
            <?php else: ?>
                <!-- Error Content -->
                <div class="success-icon" style="color: var(--danger);">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                
                <h1 style="color: var(--danger);">Payment Verification Failed</h1>
                
                <div class="message error-message">
                    <p><i class="fas fa-exclamation-triangle"></i> <strong>Payment could not be verified.</strong></p>
                    <p><?= isset($error) ? $error : 'An unknown error occurred.' ?></p>
                    <p style="margin-top: 15px;">Please check your Khalti account to confirm if the payment was deducted.</p>
                </div>
                
                <div class="action-buttons">
                    <a href="payment.php?booking_id=<?= $booking_id ?>&bus_id=<?= $bus_id ?>" class="btn btn-khalti">
                        <i class="fas fa-redo"></i> Try Payment Again
                    </a>
                    <a href="home.php" class="btn btn-light">
                        <i class="fas fa-home"></i> Return to Dashboard
                    </a>
                    <a href="support.php" class="btn btn-secondary">
                        <i class="fas fa-headset"></i> Contact Support
                    </a>
                </div>
                
                <div class="payment-info">
                    <h3><i class="fas fa-question-circle"></i> What to do next?</h3>
                    <p>1. Check your Khalti transaction history</p>
                    <p>2. If amount was deducted, contact our support with your transaction ID</p>
                    <p>3. If amount was not deducted, you can try the payment again</p>
                </div>
            <?php endif; ?>
            
            <div class="footer">
                <p>© <?= date('Y') ?> Bus Booking System | For any queries, contact: support@busbookingsystem.com</p>
                <p style="margin-top: 10px; font-size: 12px;">
                    Transaction Reference: <?= htmlspecialchars($pidx) ?>
                </p>
            </div>
        </div>
    </div>
</body>
</html>