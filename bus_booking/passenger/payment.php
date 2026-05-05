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
$bus_id = isset($_GET['bus_id']) ? intval($_GET['bus_id']) : 0;
$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$success = $error = $support_success = $support_error = '';

// ========== CLEANUP EXPIRED BOOKINGS ==========
$conn->query("
    UPDATE buses b
    JOIN (
        SELECT bus_id, SUM(total_seats) AS seat_count 
        FROM bookings 
        WHERE payment_status='Pending' 
          AND ride_status='Upcoming' 
          AND TIMESTAMPDIFF(MINUTE, created_at, NOW()) >= 10
        GROUP BY bus_id
    ) expired ON b.id = expired.bus_id
    SET b.available_seats = b.available_seats + expired.seat_count
");

$conn->query("
    DELETE FROM bookings 
    WHERE payment_status='Pending' 
      AND ride_status='Upcoming' 
      AND TIMESTAMPDIFF(MINUTE, created_at, NOW()) >= 10
");

// ========== VALIDATE ACCESS ==========
if (!isset($_SESSION['recently_booked_seats']) || 
    empty($_SESSION['recently_booked_seats']) || 
    $_SESSION['recent_booking_bus_id'] != $bus_id) {
    
    if ($booking_id > 0) {
        $check_booking = $conn->prepare("
            SELECT user_id, bus_id, seat_numbers 
            FROM bookings 
            WHERE booking_id = ? 
            AND payment_status = 'Pending'
        ");
        $check_booking->bind_param("i", $booking_id);
        $check_booking->execute();
        $booking_check = $check_booking->get_result()->fetch_assoc();
        $check_booking->close();
        
        if ($booking_check && $booking_check['user_id'] == $user['id']) {
            $_SESSION['recently_booked_seats'] = array_map('trim', explode(',', $booking_check['seat_numbers']));
            $_SESSION['recent_booking_bus_id'] = $booking_check['bus_id'];
            $_SESSION['booking_time'] = time();
        } else {
            header("Location: home.php?timeout=1");
            exit;
        }
    } else {
        header("Location: home.php?timeout=1");
        exit;
    }
}

$recent_seats = $_SESSION['recently_booked_seats'];
$seat_count = count($recent_seats);
$seat_list = implode(', ', $recent_seats);
$seat_numbers_str = implode(', ', $recent_seats);

// ========== FETCH BUS DETAILS ==========
$bus_query = $conn->prepare("
    SELECT bus_name, route_from, route_to, departure_time, ticket_price 
    FROM buses WHERE id = ?
");
$bus_query->bind_param("i", $bus_id);
$bus_query->execute();
$bus = $bus_query->get_result()->fetch_assoc();
$bus_query->close();

if (!$bus) {
    header("Location: home.php?timeout=1");
    exit;
}

// ========== FIND THE EXISTING PENDING BOOKING ==========
if ($booking_id > 0) {
    $find_booking_query = "SELECT booking_id, total_seats, total_amount, seat_numbers, created_at 
                          FROM bookings 
                          WHERE booking_id = ? 
                          AND user_id = ? 
                          AND payment_status = 'Pending' 
                          AND ride_status = 'Upcoming'";
    
    $find_stmt = $conn->prepare($find_booking_query);
    $find_stmt->bind_param("ii", $booking_id, $user['id']);
} else {
    $find_booking_query = "SELECT booking_id, total_seats, total_amount, seat_numbers, created_at 
                          FROM bookings 
                          WHERE user_id = ? 
                          AND bus_id = ? 
                          AND seat_numbers = ? 
                          AND payment_status = 'Pending' 
                          AND ride_status = 'Upcoming'
                          ORDER BY created_at DESC LIMIT 1";
    
    $find_stmt = $conn->prepare($find_booking_query);
    $find_stmt->bind_param("iis", $user['id'], $bus_id, $seat_numbers_str);
}

$find_stmt->execute();
$find_result = $find_stmt->get_result();

if ($row = $find_result->fetch_assoc()) {
    $booking_id = $row['booking_id'];
    $total_seats = $row['total_seats'];
    $total_amount = $row['total_amount'];
    
    if (!isset($_SESSION['booking_time'])) {
        $_SESSION['booking_time'] = strtotime($row['created_at']);
    }
} else {
    header("Location: home.php?error=nobooking&bus_id=" . $bus_id);
    exit;
}
$find_stmt->close();

// ========== CALCULATE PAYMENT DETAILS ==========
$ticket_price = $bus['ticket_price'];
$booking_time = isset($_SESSION['booking_time']) ? $_SESSION['booking_time'] : time();
$remaining = (10 * 60) - (time() - $booking_time);

// ========== CHECK FOR EXPIRED SESSION ==========
if ($remaining <= 0) {
    if ($booking_id) {
        $update_bus = $conn->prepare("UPDATE buses SET available_seats = available_seats + ? WHERE id = ?");
        $update_bus->bind_param("ii", $total_seats, $bus_id);
        $update_bus->execute();
        $update_bus->close();
        
        $delete_query = "DELETE FROM bookings WHERE booking_id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param("i", $booking_id);
        $delete_stmt->execute();
        $delete_stmt->close();
    }
    
    unset($_SESSION['recently_booked_seats'], $_SESSION['recent_booking_bus_id'], $_SESSION['booking_time']);
    header("Location: home.php?timeout=1");
    exit;
}

// ========== HANDLE REGULAR PAYMENT METHODS ==========
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['payment_method']) && $booking_id) {
        $method = $_POST['payment_method'];
        
        if ($method != 'eSewa') {
            $update_query = "UPDATE bookings 
                            SET payment_status = 'Paid', 
                                payment_method = ?,
                                paid_at = NOW()
                            WHERE booking_id = ?";
            
            $update_stmt = $conn->prepare($update_query);
            
            if ($update_stmt) {
                $update_stmt->bind_param("si", $method, $booking_id);
                
                if ($update_stmt->execute()) {
                    $success = "✅ Payment successful via $method! Thank you for booking with us.";
                    unset($_SESSION['recently_booked_seats'], $_SESSION['recent_booking_bus_id'], $_SESSION['booking_time']);
                } else {
                    $error = "❌ Payment update failed. Error: " . $update_stmt->error;
                }
                $update_stmt->close();
            } else {
                $error = "❌ Database error. Please try again.";
            }
        }
    }
    
    // Removed support message handling section
}

// ========== PREPARE DISPLAY DATA ==========
$booking = [
    'bus_name' => $bus['bus_name'],
    'route_from' => $bus['route_from'],
    'route_to' => $bus['route_to'],
    'departure_time' => $bus['departure_time'],
    'ticket_price' => $ticket_price,
    'total_amount' => $total_amount,
    'payment_status' => 'Pending',
    'seat_numbers' => $seat_list,
    'booking_id' => $booking_id
];

// Generate unique transaction ID for eSewa
$transaction_uuid = 'BUS_' . $booking_id . '_' . time() . '_' . rand(1000, 9999);

// eSewa Configuration
$esewa_config = [
    'merchant_code' => 'EPAYTEST',
    'secret_key' => '8gBm/:&EnhH.1/q',
    'test_mode' => true,
    'gateway_url' => 'https://rc-epay.esewa.com.np/api/epay/main/v2/form',
    'success_url' => (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/payment_success.php?booking_id=' . $booking_id . '&bus_id=' . $bus_id,
    'failure_url' => (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/payment_failure.php?booking_id=' . $booking_id . '&bus_id=' . $bus_id
];

// Prepare eSewa parameters - SIMPLIFIED VERSION
$tax_amount = 0;
$service_charge = 0;
$delivery_charge = 0;

// Use simpler parameter names that eSewa expects
$esewa_params = [
    'amount' => $total_amount,
    'tax_amount' => $tax_amount,
    'total_amount' => $total_amount,
    'transaction_uuid' => $transaction_uuid,
    'product_code' => 'EPAYTEST', // Hardcode for testing
    'product_service_charge' => $service_charge,
    'product_delivery_charge' => $delivery_charge,
    'success_url' => $esewa_config['success_url'],
    'failure_url' => $esewa_config['failure_url'],
    'signed_field_names' => 'total_amount,transaction_uuid,product_code',
    
    // Add these for better compatibility
    'product_name' => 'Bus Ticket Booking #' . $booking_id,
    'product_id' => 'BUS_' . $booking_id,
    'callback_url' => $esewa_config['success_url'], // Some eSewa versions use this
];

// Generate signature
$signature_data = "total_amount={$esewa_params['total_amount']},transaction_uuid={$esewa_params['transaction_uuid']},product_code={$esewa_params['product_code']}";
$signature = hash_hmac('sha256', $signature_data, $esewa_config['secret_key'], true);
$esewa_params['signature'] = base64_encode($signature);

// Log for debugging
error_log("eSewa Form Parameters: " . json_encode($esewa_params));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment | Bus Booking System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>
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
            padding: 0;
            margin: 0;
        }
        
        header { 
            background: #009432; 
            color: #fff; 
            padding: 15px 30px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            border-radius: 0;
            margin: 0;
            width: 100%;
        }

        header h1 { 
            margin: 0; 
            font-size: 20px; 
        }

        nav a { 
            color: #fff; 
            text-decoration: none; 
            margin-left: 18px; 
            font-weight: bold; 
        }

        nav a:hover {
            text-decoration: underline;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            padding: 20px;
        }
        
        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
            }
        }
        
        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: var(--shadow);
        }
        
        .card-title {
            color: var(--primary);
            border-bottom: 2px solid var(--primary);
            padding-bottom: 15px;
            margin-bottom: 25px;
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-title i {
            font-size: 24px;
        }
        
        .info-grid {
            display: grid;
            gap: 15px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .info-label {
            color: #666;
            font-weight: 500;
        }
        
        .info-value {
            color: var(--dark);
            font-weight: 600;
        }
        
        .highlight {
            background: linear-gradient(135deg, #009432, #4CAF50);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 18px;
        }
        
        .timer-card {
            background: linear-gradient(135deg, #ff9800, #ff5722);
            color: white;
            text-align: center;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
        }
        
        .timer-card h3 {
            margin-bottom: 10px;
            font-size: 18px;
        }
        
        #countdown {
            font-size: 28px;
            font-weight: bold;
            letter-spacing: 2px;
        }
        
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 15px;
            margin: 25px 0;
        }
        
        .payment-method {
            border: 2px solid var(--border);
            border-radius: 10px;
            padding: 20px 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
        }
        
        .payment-method:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 10px 20px rgba(0,148,50,0.15);
        }
        
        .payment-method.selected {
            border-color: var(--primary);
            background: linear-gradient(135deg, rgba(0,148,50,0.1), rgba(76,175,80,0.1));
        }
        
        .payment-method img {
            height: 40px;
            margin-bottom: 10px;
        }
        
        .payment-method .name {
            font-weight: 600;
            color: var(--dark);
        }
        
        input[type="radio"] {
            display: none;
        }
        
        .btn {
            display: inline-block;
            background: var(--primary);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s;
            width: 100%;
            font-size: 16px;
        }
        
        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,148,50,0.3);
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
        
        .btn-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 20px;
        }
        
        .message {
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
            text-align: center;
            font-weight: 600;
        }
        
        .success-message {
            background: rgba(76, 175, 80, 0.15);
            color: var(--success);
            border-left: 4px solid var(--success);
        }
        
        .error-message {
            background: rgba(244, 67, 54, 0.15);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }
        
        .ticket-actions {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            margin-top: 20px;
        }
        
        .ticket-actions h3 {
            color: var(--primary);
            margin-bottom: 15px;
        }
        
        .ticket-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .ticket-btn {
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .ticket-btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .ticket-btn-secondary {
            background: var(--secondary);
            color: white;
        }
        
        .ticket-btn-light {
            background: white;
            color: var(--dark);
            border: 2px solid var(--border);
        }
        
        footer {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            margin-top: 40px;
            box-shadow: var(--shadow);
            color: var(--dark);
        }
        
        .payment-success {
            grid-column: 1 / -1;
            text-align: center;
            padding: 40px;
        }
        
        .success-icon {
            font-size: 80px;
            color: var(--success);
            margin-bottom: 20px;
        }
        
        .esewa-form {
            background: linear-gradient(135deg, rgba(85, 161, 70, 0.1), rgba(85, 161, 70, 0.05));
            padding: 20px;
            border-radius: 10px;
            border: 2px solid #55a146;
            margin: 20px 0;
        }
        
        .esewa-btn {
            background: linear-gradient(135deg, #55a146, #4a8c3d);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .esewa-btn:hover {
            background: linear-gradient(135deg, #4a8c3d, #3d7532);
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .expiring {
            animation: pulse 1s infinite;
            color: #ff4444 !important;
        }
        
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 40px;
            max-width: 500px;
            width: 90%;
            text-align: center;
            transform: translateY(-50px);
            transition: transform 0.3s ease;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }
        
        .modal-overlay.active .modal-content {
            transform: translateY(0);
        }
        
        .modal-icon {
            font-size: 60px;
            color: var(--primary);
            margin-bottom: 20px;
        }
        
        .modal-title {
            font-size: 24px;
            color: var(--dark);
            margin-bottom: 15px;
        }
        
        .modal-message {
            font-size: 16px;
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .modal-amount {
            font-size: 28px;
            color: var(--primary);
            font-weight: bold;
            margin: 15px 0;
        }
        
        .modal-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 25px;
        }
        
        .modal-btn {
            padding: 12px 20px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .modal-btn-confirm {
            background: var(--primary);
            color: white;
        }
        
        .modal-btn-confirm:hover {
            background: var(--primary-dark);
        }
        
        .modal-btn-cancel {
            background: var(--light);
            color: var(--dark);
            border: 2px solid var(--border);
        }
        
        .modal-btn-cancel:hover {
            background: #e2e6ea;
        }
        
        .khaliti-box {
            background: linear-gradient(135deg, #5C2D91, #7733B5);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin: 20px 0;
            text-align: center;
        }
        
        .khaliti-title {
            font-size: 22px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .khaliti-title img {
            height: 30px;
        }
        
        .khaliti-amount {
            font-size: 32px;
            font-weight: bold;
            margin: 15px 0;
        }
        
        .simple-modal {
            background: white;
            border-radius: 8px;
            padding: 30px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }
        
        .simple-modal-title {
            font-size: 20px;
            color: #333;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .simple-modal-message {
            font-size: 16px;
            color: #666;
            margin-bottom: 25px;
            line-height: 1.5;
        }
        
        .simple-modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .simple-modal-btn {
            padding: 10px 25px;
            border-radius: 6px;
            border: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            min-width: 100px;
            transition: all 0.2s;
        }
        
        .simple-modal-btn-cancel {
            background: #f5f5f5;
            color: #333;
            border: 1px solid #ddd;
        }
        
        .simple-modal-btn-cancel:hover {
            background: #eaeaea;
        }
        
        .simple-modal-btn-confirm {
            background: #dc3545;
            color: white;
        }
        
        .simple-modal-btn-confirm:hover {
            background: #c82333;
        }
        
        .esewa-info {
            background: linear-gradient(135deg, rgba(85, 161, 70, 0.1), rgba(85, 161, 70, 0.05));
            border-left: 4px solid #55a146;
            padding: 15px;
            margin: 15px 0;
            border-radius: 8px;
        }
        
        .esewa-info h4 {
            color: #55a146;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .esewa-test-credentials {
            background: #f8f9fa;
            border: 1px dashed #55a146;
            padding: 15px;
            margin: 15px 0;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .esewa-test-credentials h5 {
            color: #55a146;
            margin-bottom: 8px;
        }
        
        .esewa-test-credentials ul {
            list-style: none;
            padding-left: 0;
        }
        
        .esewa-test-credentials li {
            padding: 5px 0;
            border-bottom: 1px dashed #ddd;
        }
        
        .esewa-test-credentials li:last-child {
            border-bottom: none;
        }
        
        /* DEBUG BUTTON CSS REMOVED
        .debug-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #ff9800;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            z-index: 9999;
            font-size: 14px;
        }
        */
    </style>
</head>
<body>
    <header>
        <h1>🚌 Payment</h1>
        <nav>
            <a href="../index.php">Home</a>
            <a href="home.php">Dashboard</a>
            <a href="search.php">Search</a>
            <a href="../logout.php">Logout</a>
        </nav>
    </header>

    <div class="container">
        <!-- Left Column: Booking Details -->
        <div class="card">
            <h2 class="card-title"><i class="fas fa-receipt"></i> Booking Details</h2>
            
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Booking ID:</span>
                    <span class="info-value" style="font-family: monospace; color: var(--primary);">#<?= $booking_id ?></span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Bus Name:</span>
                    <span class="info-value"><?= htmlspecialchars($booking['bus_name']) ?></span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Route:</span>
                    <span class="info-value"><?= htmlspecialchars($booking['route_from']) ?> → <?= htmlspecialchars($booking['route_to']) ?></span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Departure:</span>
                    <span class="info-value"><?= date("d M Y, h:i A", strtotime($booking['departure_time'])) ?></span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Ticket Price:</span>
                    <span class="info-value">Rs <?= number_format($ticket_price) ?> per seat</span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Seats Booked:</span>
                    <span class="info-value"><?= $seat_count ?> seat(s)</span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Seat Numbers:</span>
                    <span class="info-value highlight"><?= htmlspecialchars($seat_list) ?></span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Total Amount:</span>
                    <span class="info-value" style="font-size: 24px; color: var(--primary);">Rs <?= number_format($total_amount) ?></span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Payment Status:</span>
                    <span class="info-value" style="color: var(--warning); font-weight: bold;">Pending</span>
                </div>
            </div>
            
            <div class="timer-card">
                <h3><i class="fas fa-clock"></i> Time Remaining to Complete Payment</h3>
                <div id="countdown">10:00</div>
                <p style="margin-top: 10px; font-size: 14px; opacity: 0.9;">Complete payment before time expires</p>
            </div>
        </div>

        <!-- Right Column: Payment -->
        <div class="card">
            <h2 class="card-title"><i class="fas fa-credit-card"></i> Payment</h2>
            
            <?php if($success): ?>
                <div class="payment-success">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h2 style="color: var(--success); margin-bottom: 15px;">Payment Successful!</h2>
                    <p style="font-size: 18px; margin-bottom: 30px; color: var(--dark);">
                        Your booking #<?= $booking_id ?> has been confirmed. You can now download your e-ticket.
                    </p>
                    
                    <div class="ticket-actions">
                        <h3>Download Your E-Ticket</h3>
                        <div class="ticket-buttons">
                            <a href="ticket.php?id=<?= $booking_id ?>" class="ticket-btn ticket-btn-primary">
                                <i class="fas fa-ticket-alt"></i> View E-Ticket
                            </a>
                            <a href="ticket.php?id=<?= $booking_id ?>&autoprint=1" class="ticket-btn ticket-btn-secondary">
                                <i class="fas fa-print"></i> Print Ticket
                            </a>
                            <a href="home.php" class="ticket-btn ticket-btn-light">
                                <i class="fas fa-home"></i> Dashboard
                            </a>
                        </div>
                        <p style="margin-top: 15px; color: #666; font-size: 14px;">
                            <i class="fas fa-info-circle"></i> Please save or print your e-ticket to show the bus driver
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <!-- Payment Form for non-eSewa methods -->
                <form method="POST" id="paymentForm">
                    <?php if($error): ?>
                        <div class="message error-message">
                            <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                        </div>
                    <?php endif; ?>
                    
                    <h3 style="margin-bottom: 20px; color: var(--dark);">Select Payment Method</h3>
                    
                    <div class="payment-methods">
                        <label class="payment-method">
                            <input type="radio" name="payment_method" value="eSewa" required>
                            <img src="https://fonepoints.com/images/logos/esewa-logo.png" alt="eSewa">
                            <div class="name">eSewa</div>
                        </label>
                        
                        <label class="payment-method">
                            <input type="radio" name="payment_method" value="Khalti" required>
                            <img src="https://khaltibyime.khalti.com/wp-content/uploads/2025/07/Logo-for-Blog.png" alt="Khalti">
                            <div class="name">Khalti</div>
                        </label>
                        
                        <label class="payment-method">
                            <input type="radio" name="payment_method" value="Card" required>
                            <img src="https://cdn-icons-png.flaticon.com/512/2331/2331966.png" alt="Card" style="height: 35px;">
                            <div class="name">Credit/Debit Card</div>
                        </label>
                    </div>
                    
                    <!-- Submit Button for all payments -->
                    <button type="submit" id="submitBtn" class="btn" style="display: none; margin-top: 20px;">
                        <i class="fas fa-lock"></i> Complete Payment
                    </button>
                </form>
                
                <!-- SEPARATE eSewa Form -->
                <form id="esewaForm" method="POST" action="<?= $esewa_config['gateway_url'] ?>">
                    <?php foreach($esewa_params as $key => $value): ?>
                        <input type="hidden" name="<?= $key ?>" value="<?= htmlspecialchars($value) ?>">
                    <?php endforeach; ?>
                </form>
                
                <!-- Cancel Booking Button -->
                <div class="btn-group">
                    <button type="button" class="btn btn-light" id="cancelBookingBtn">
                        <i class="fas fa-times"></i> Cancel Booking
                    </button>
                    <button type="button" onclick="window.print()" class="btn btn-secondary">
                        <i class="fas fa-print"></i> Print Details
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <p>© <?= date('Y') ?> Bus Booking System | Passenger Panel</p>
        <p style="margin-top: 10px; font-size: 14px; color: #666;">
            <i class="fas fa-shield-alt"></i> Secure Payment | 
            <i class="fas fa-headset"></i> 24/7 Support | 
            <i class="fas fa-ticket-alt"></i> Instant E-Ticket
        </p>
    </footer>

    <!-- Payment Confirmation Modal -->
    <div class="modal-overlay" id="paymentModal">
        <div class="modal-content">
            <div class="modal-icon">
                <i class="fas fa-wallet"></i>
            </div>
            <h2 class="modal-title">Confirm Payment</h2>
            
            <!-- eSewa specific modal content -->
            <div id="esewaPaymentBox" style="display: none;">
                <div style="background: linear-gradient(135deg, #55a146, #4a8c3d); color: white; padding: 20px; border-radius: 10px; margin: 20px 0;">
                    <h3 style="margin-bottom: 15px; display: flex; align-items: center; justify-content: center; gap: 10px;">
                        <img src="https://fonepoints.com/images/logos/esewa-logo.png" alt="eSewa" style="height: 30px; filter: brightness(0) invert(1);">
                        <span>eSewa</span>
                    </h3>
                    <div class="modal-message" style="color: rgba(255,255,255,0.9);">
                        You will be redirected to eSewa's secure payment gateway to complete your payment.
                    </div>
                    <div style="font-size: 32px; font-weight: bold; margin: 15px 0;">Rs <?= number_format($total_amount) ?></div>
                </div>
                <p style="font-size: 14px; color: #666; margin: 10px 0;">
                    <i class="fas fa-info-circle"></i> Use test ID: <strong>9806800001</strong> and password: <strong>Nepal@123</strong>
                </p>
            </div>
            
            <!-- Regular payment methods modal content -->
            <div id="regularPaymentBox">
                <div class="modal-message">
                    Confirm payment of Rs <span class="modal-amount"><?= number_format($total_amount) ?></span> 
                    via <span id="paymentMethodName" style="font-weight: bold; color: var(--primary);"></span>?
                </div>
            </div>
            
            <div class="modal-buttons">
                <button type="button" class="modal-btn modal-btn-cancel" id="modalCancelBtn">
                    Cancel
                </button>
                <button type="button" class="modal-btn modal-btn-confirm" id="modalConfirmBtn">
                    OK
                </button>
            </div>
        </div>
    </div>

    <!-- Simple Cancel Booking Confirmation Modal -->
    <div class="modal-overlay" id="cancelModal">
        <div class="simple-modal">
            <h3 class="simple-modal-title">Cancel Booking?</h3>
            <div class="simple-modal-message">
                Are you sure you want to cancel this booking?<br>
                <span style="color: #dc3545; font-size: 14px; margin-top: 10px; display: inline-block;">
                    This action cannot be undone.
                </span>
            </div>
            <div class="simple-modal-buttons">
                <button type="button" class="simple-modal-btn simple-modal-btn-cancel" id="cancelModalNoBtn">
                    No
                </button>
                <button type="button" class="simple-modal-btn simple-modal-btn-confirm" id="cancelModalYesBtn">
                    Yes, Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- DEBUG BUTTON REMOVED -->
    <!-- <button class="debug-btn" onclick="submitEsewaForm()">
        <i class="fas fa-bug"></i> Debug: Submit eSewa
    </button> -->

    <script>
        // ========== COUNTDOWN TIMER ==========
        let remaining = <?= max(0, $remaining) ?>;
        const countdownElement = document.getElementById('countdown');
        
        function updateTimer() {
            const minutes = Math.floor(remaining / 60);
            const seconds = remaining % 60;
            countdownElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            if (remaining <= 60) {
                countdownElement.classList.add('expiring');
                if (remaining <= 30) {
                    countdownElement.style.animation = 'pulse 1s infinite';
                }
            }
            
            if (remaining <= 0) {
                clearInterval(timerInterval);
                alert("Your payment session has expired! You'll be redirected to your dashboard.");
                window.location.href = "home.php?timeout=1";
            }
            remaining--;
        }
        
        const timerInterval = setInterval(updateTimer, 1000);
        updateTimer();
        
        // ========== PAYMENT METHOD SELECTION ==========
        const paymentMethods = document.querySelectorAll('.payment-method');
        const submitBtn = document.getElementById('submitBtn');
        const paymentForm = document.getElementById('paymentForm');
        const esewaForm = document.getElementById('esewaForm');
        let selectedPaymentMethod = '';
        let selectedPaymentMethodValue = '';
        
        paymentMethods.forEach(method => {
            method.addEventListener('click', function() {
                paymentMethods.forEach(m => m.classList.remove('selected'));
                this.classList.add('selected');
                
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
                selectedPaymentMethod = this.querySelector('.name').textContent;
                selectedPaymentMethodValue = radio.value;
                
                submitBtn.style.display = 'block';
            });
        });
        
        // ========== MODAL FUNCTIONS ==========
        const paymentModal = document.getElementById('paymentModal');
        const modalCancelBtn = document.getElementById('modalCancelBtn');
        const modalConfirmBtn = document.getElementById('modalConfirmBtn');
        const esewaPaymentBox = document.getElementById('esewaPaymentBox');
        const regularPaymentBox = document.getElementById('regularPaymentBox');
        const paymentMethodName = document.getElementById('paymentMethodName');
        
        function showPaymentModal() {
            if (selectedPaymentMethodValue === 'eSewa') {
                esewaPaymentBox.style.display = 'block';
                regularPaymentBox.style.display = 'none';
            } else {
                esewaPaymentBox.style.display = 'none';
                regularPaymentBox.style.display = 'block';
                paymentMethodName.textContent = selectedPaymentMethod;
            }
            
            paymentModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function hidePaymentModal() {
            paymentModal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
        
        // Debug function to submit eSewa form (function remains but button is removed)
        function submitEsewaForm() {
            console.log('Debug: Submitting eSewa form...');
            console.log('Form action:', esewaForm.action);
            console.log('Form data:', new FormData(esewaForm));
            esewaForm.submit();
        }
        
        // ========== FORM SUBMISSION HANDLING ==========
        paymentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
            
            if (!selectedMethod) {
                alert('Please select a payment method');
                return false;
            }
            
            showPaymentModal();
            return false;
        });
        
        // ========== MODAL BUTTON HANDLERS ==========
        modalCancelBtn.addEventListener('click', hidePaymentModal);
        
        modalConfirmBtn.addEventListener('click', function() {
            hidePaymentModal();
            
            const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
            
            if (selectedMethod && selectedMethod.value === 'eSewa') {
                console.log('eSewa payment selected, submitting form...');
                submitEsewaForm();
            } else {
                const submitBtn = document.getElementById('submitBtn');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                paymentForm.submit();
            }
        });
        
        // Close modal when clicking outside
        paymentModal.addEventListener('click', function(e) {
            if (e.target === paymentModal) {
                hidePaymentModal();
            }
        });
        
        // ========== CANCEL BOOKING MODAL ==========
        const cancelModal = document.getElementById('cancelModal');
        const cancelBookingBtn = document.getElementById('cancelBookingBtn');
        const cancelModalNoBtn = document.getElementById('cancelModalNoBtn');
        const cancelModalYesBtn = document.getElementById('cancelModalYesBtn');

        cancelBookingBtn.addEventListener('click', function(e) {
            e.preventDefault();
            hidePaymentModal();
            cancelModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        });

        cancelModalNoBtn.addEventListener('click', function() {
            cancelModal.classList.remove('active');
            document.body.style.overflow = 'auto';
        });

        cancelModalYesBtn.addEventListener('click', function() {
            window.location.href = "cancel_booking.php?booking_id=<?= $booking_id ?>&bus_id=<?= $bus_id ?>";
        });

        cancelModal.addEventListener('click', function(e) {
            if (e.target === cancelModal) {
                cancelModal.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        });

        function hideAllModals() {
            paymentModal.classList.remove('active');
            cancelModal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
        
        // Auto-select first payment method
        if (paymentMethods.length > 0) {
            paymentMethods[0].click();
        }
        
        // Direct eSewa form test
        console.log('Payment page loaded');
        console.log('Booking ID:', <?= $booking_id ?>);
        console.log('Total Amount:', <?= $total_amount ?>);
        console.log('eSewa Form exists:', esewaForm ? 'Yes' : 'No');
        console.log('eSewa URL:', '<?= $esewa_config["gateway_url"] ?>');
    </script>
</body>
</html>