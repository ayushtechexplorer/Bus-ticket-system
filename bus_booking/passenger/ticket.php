<?php
session_start();
include('../includes/db.php');

// Restrict access
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'passenger') {
  header("Location: ../login.php");
  exit;
}

$user = $_SESSION['user'];
$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($booking_id <= 0) {
    die("Invalid ticket ID!");
}

// Query to get ticket details from NEW bookings table
$query = "
    SELECT 
        b.*,
        u.name as passenger_name,
        u.phone,
        bus.bus_name,
        bus.bus_number as plate_number,
        bus.driver_name,
        bus.driver_phone,
        bus.route_from,
        bus.route_to,
        bus.departure_time,
        bus.ticket_price
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN buses bus ON b.bus_id = bus.id
    WHERE b.booking_id = ? AND b.user_id = ?
    AND b.payment_status = 'Paid'
";

$stmt = $conn->prepare($query);

if (!$stmt) {
    die("Database error. Please try again.");
}

$stmt->bind_param("ii", $booking_id, $user['id']);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();

if (!$booking) {
    die("Ticket not found or unauthorized access!");
}
$stmt->close();

// Calculate total amount
$total_amount = $booking['total_amount'];

// Generate booking reference
$booking_ref = "BBS" . str_pad($booking['booking_id'], 6, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket - <?= $booking_ref ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }
        
        body {
            background: white;
            padding: 10mm;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
        }
        
        .ticket {
            width: 148mm; /* A5 width */
            height: auto;
            min-height: 105mm; /* A5 height */
            background: white;
            border: 1.5px solid #000;
            padding: 6mm;
            position: relative;
            font-size: 10px;
            line-height: 1.3;
        }
        
        /* Header Section */
        .header {
            text-align: center;
            border-bottom: 1.5px solid #000;
            padding-bottom: 4mm;
            margin-bottom: 4mm;
        }
        
        .company {
            font-size: 14px;
            font-weight: bold;
            color: #009432;
            margin-bottom: 2mm;
        }
        
        .title {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 2mm;
        }
        
        .booking-id {
            background: #009432;
            color: white;
            padding: 2mm 5mm;
            font-weight: bold;
            font-size: 11px;
            display: inline-block;
            border-radius: 2mm;
        }
        
        /* Compact Layout */
        .compact-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            margin-bottom: 3mm;
        }
        
        .compact-table td {
            padding: 1.2mm;
            vertical-align: top;
            border: 0.7px solid #ddd;
        }
        
        .compact-label {
            font-weight: bold;
            color: #333;
            width: 28%;
            background: #f8f9fa;
            font-size: 10px;
        }
        
        .compact-value {
            color: #000;
            font-weight: normal;
            font-size: 10px;
        }
        
        /* Highlighted sections */
        .highlight-box {
            background: #f0f8ff;
            border: 1px solid #87CEEB;
            padding: 1.5mm;
            margin: 1.5mm 0;
            text-align: center;
            font-weight: bold;
            font-size: 10px;
        }
        
        .seat-highlight {
            background: #e3f2fd;
            border: 1px solid #2196F3;
            padding: 1.5mm 3mm;
            font-weight: bold;
            color: #2196F3;
            display: inline-block;
            font-size: 12px;
            border-radius: 2mm;
        }
        
        .amount-highlight {
            background: #4CAF50;
            color: white;
            padding: 3mm;
            text-align: center;
            font-weight: bold;
            font-size: 13px;
            margin: 3mm 0;
            border-radius: 2mm;
        }
        
        /* Instructions */
        .instructions {
            border: 1px solid #ccc;
            padding: 3mm;
            margin-top: 4mm;
            font-size: 9px;
            line-height: 1.4;
        }
        
        .instructions-title {
            font-weight: bold;
            color: #333;
            margin-bottom: 2mm;
            font-size: 11px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 1mm;
        }
        
        .instructions-list {
            padding-left: 4mm;
        }
        
        .instructions-list li {
            margin-bottom: 1mm;
        }
        
        /* Status badge */
        .status {
            position: absolute;
            top: 3mm;
            right: 3mm;
            background: #009432;
            color: white;
            padding: 1mm 3mm;
            font-weight: bold;
            font-size: 9px;
            border-radius: 2mm;
        }
        
        /* Footer */
        .footer {
            text-align: center;
            margin-top: 3mm;
            padding-top: 2mm;
            border-top: 1px solid #ccc;
            color: #666;
            font-size: 8px;
        }
        
        /* Print styles */
        @media print {
            @page {
                margin: 0;
                size: A5 portrait;
            }
            
            body {
                margin: 0;
                padding: 0;
                background: white;
                font-size: 10px !important;
            }
            
            .ticket {
                width: 148mm;
                height: auto;
                min-height: 105mm;
                border: 1.5px solid #000;
                padding: 6mm;
                margin: 0;
                box-shadow: none;
                font-size: 10px;
            }
            
            .no-print {
                display: none;
            }
            
            /* Remove all headers and footers */
            @page {
                @top-left { content: none; }
                @top-center { content: none; }
                @top-right { content: none; }
                @bottom-left { content: none; }
                @bottom-center { content: none; }
                @bottom-right { content: none; }
            }
        }
        
        /* Screen buttons */
        .screen-buttons {
            text-align: center;
            margin-top: 8mm;
            font-size: 11px;
        }
        
        .print-btn {
            background: #009432;
            color: white;
            border: none;
            padding: 3mm 8mm;
            font-size: 11px;
            font-weight: bold;
            cursor: pointer;
            margin: 2mm;
            border-radius: 3mm;
        }
        
        .back-btn {
            background: #666;
            color: white;
            padding: 3mm 8mm;
            font-size: 11px;
            text-decoration: none;
            font-weight: bold;
            display: inline-block;
            margin: 2mm;
            border-radius: 3mm;
        }
        
        /* Additional styling */
        .section-divider {
            height: 1px;
            background: #eee;
            margin: 2mm 0;
        }
        
        .important-note {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 2mm;
            margin: 2mm 0;
            font-size: 9px;
            color: #856404;
            border-radius: 2mm;
        }
        
        .contact-info {
            background: #e7f3ff;
            border: 1px solid #bbdefb;
            padding: 2mm;
            margin: 2mm 0;
            font-size: 9px;
            border-radius: 2mm;
        }
        
        .seat-info {
            background: #e8f5e9;
            border: 1px solid #81c784;
            padding: 2mm;
            margin: 2mm 0;
            font-size: 10px;
            border-radius: 2mm;
            text-align: center;
        }
        
        .seat-count {
            font-weight: bold;
            color: #009432;
        }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="status">CONFIRMED</div>
        
        <div class="header">
            <div class="company">BUS BOOKING SYSTEM</div>
            <div class="title">E-TICKET / BOARDING PASS</div>
            <div class="booking-id">BOOKING ID: <?= $booking_ref ?></div>
        </div>
        
        <!-- Passenger & Bus Info in Compact Table -->
        <table class="compact-table">
            <tr>
                <td class="compact-label">PASSENGER NAME:</td>
                <td class="compact-value" colspan="3" style="font-size: 11px; font-weight: bold;">
                    <?= htmlspecialchars($booking['passenger_name']) ?>
                </td>
            </tr>
            <tr>
                <td class="compact-label">PHONE NO:</td>
                <td class="compact-value"><?= htmlspecialchars($booking['phone']) ?></td>
                <td class="compact-label">SEATS:</td>
                <td class="compact-value">
                    <span class="seat-highlight"><?= htmlspecialchars($booking['seat_numbers']) ?></span>
                </td>
            </tr>
            <tr>
                <td class="compact-label">BUS NAME:</td>
                <td class="compact-value"><?= htmlspecialchars($booking['bus_name']) ?></td>
                <td class="compact-label">PLATE NO:</td>
                <td class="compact-value"><?= htmlspecialchars($booking['plate_number']) ?></td>
            </tr>
            <tr>
                <td class="compact-label">DRIVER NAME:</td>
                <td class="compact-value"><?= htmlspecialchars($booking['driver_name']) ?></td>
                <td class="compact-label">DRIVER CONTACT:</td>
                <td class="compact-value"><?= htmlspecialchars($booking['driver_phone']) ?></td>
            </tr>
        </table>
        
        <!-- Seat Information -->
        <div class="seat-info">
            <strong>SEAT INFORMATION:</strong> 
            <span class="seat-count"><?= $booking['total_seats'] ?> SEAT<?= $booking['total_seats'] > 1 ? 'S' : '' ?></span> 
            - Total: <?= htmlspecialchars($booking['seat_numbers']) ?>
        </div>
        
        <div class="section-divider"></div>
        
        <!-- Journey Details -->
        <table class="compact-table">
            <tr>
                <td class="compact-label">FROM (ORIGIN):</td>
                <td class="compact-value" style="font-size: 11px; font-weight: bold; color: #009432;">
                    <?= htmlspecialchars($booking['route_from']) ?>
                </td>
                <td class="compact-label">TO (DESTINATION):</td>
                <td class="compact-value" style="font-size: 11px; font-weight: bold; color: #009432;">
                    <?= htmlspecialchars($booking['route_to']) ?>
                </td>
            </tr>
            <tr>
                <td class="compact-label">DEPARTURE DATE:</td>
                <td class="compact-value"><?= date("d M Y (D)", strtotime($booking['departure_time'])) ?></td>
                <td class="compact-label">DEPARTURE TIME:</td>
                <td class="compact-value" style="font-weight: bold;"><?= date("h:i A", strtotime($booking['departure_time'])) ?></td>
            </tr>
            <tr>
                <td class="compact-label">BOOKED ON:</td>
                <td class="compact-value"><?= date("d/m/Y H:i", strtotime($booking['created_at'])) ?></td>
                <td class="compact-label">PAYMENT STATUS:</td>
                <td class="compact-value" style="color: #009432; font-weight: bold;">PAID & CONFIRMED</td>
            </tr>
            <tr>
                <td class="compact-label">PAYMENT METHOD:</td>
                <td class="compact-value"><?= htmlspecialchars($booking['payment_method']) ?></td>
                <td class="compact-label">PAID ON:</td>
                <td class="compact-value">
                    <?= $booking['paid_at'] ? date("d/m/Y H:i", strtotime($booking['paid_at'])) : 'N/A' ?>
                </td>
            </tr>
        </table>
        
        <!-- Amount Section -->
        <div class="amount-highlight">
            TOTAL AMOUNT PAID: Rs <?= number_format($total_amount) ?>
            <div style="font-size: 11px; margin-top: 1mm;">
                (<?= $booking['total_seats'] ?> seat<?= $booking['total_seats'] > 1 ? 's' : '' ?> × Rs <?= number_format($booking['ticket_price']) ?> each)
            </div>
        </div>
        
        <!-- Contact Information -->
        <div class="contact-info">
            <strong>CONTACT INFORMATION:</strong> For any queries or emergencies, contact driver: 
            <?= htmlspecialchars($booking['driver_name']) ?> - <?= htmlspecialchars($booking['driver_phone']) ?>
            | Boarding Point: Main Bus Park, <?= htmlspecialchars($booking['route_from']) ?>
        </div>
        
        <!-- Important Note -->
        <div class="important-note">
            <strong>⚠️ IMPORTANT:</strong> This is a GROUP BOOKING for <?= $booking['total_seats'] ?> passenger<?= $booking['total_seats'] > 1 ? 's' : '' ?>. 
            All passengers listed above must travel together. Please show this ticket at boarding.
        </div>
        
        <!-- All Instructions -->
        <div class="instructions">
            <div class="instructions-title">IMPORTANT INSTRUCTIONS & TERMS & CONDITIONS:</div>
            <ul class="instructions-list">
                <li><strong>Arrival Time:</strong> Reach boarding point 30 minutes before departure</li>
                <li><strong>Document Required:</strong> This ticket + Valid Photo ID Proof</li>
                <li><strong>Group Travel:</strong> All <?= $booking['total_seats'] ?> passengers must travel together</li>
                <li><strong>Validity:</strong> Only for mentioned date, time, and passenger(s)</li>
                <li><strong>Cancellation:</strong> As per bus operator policy (No refund if missed)</li>
                <li><strong>Luggage:</strong> 8kg allowance per passenger</li>
                <li><strong>Prohibited:</strong> Smoking, alcohol, and illegal substances</li>
            </ul>
        </div>
        
        
    </div>
    
    <!-- Screen Buttons -->
    <div class="screen-buttons no-print">
        <button onclick="printTicket()" class="print-btn">📄 PRINT / SAVE TICKET</button>
        <a href="home.php" class="back-btn">🏠 BACK TO DASHBOARD</a>
        <div style="font-size: 10px; color: #666; margin-top: 3mm; padding: 2mm; background: #f8f9fa; border-radius: 2mm;">
            <strong>To Save as PDF:</strong> Click Print → Choose "Save as PDF" → Set paper size to A5 → Save
        </div>
    </div>
    
    <script>
    function printTicket() {
        // Set print-specific styles
        const style = document.createElement('style');
        style.innerHTML = `
            @media print {
                body * { visibility: hidden; }
                .ticket, .ticket * { visibility: visible; }
                .ticket { 
                    position: absolute; 
                    left: 0; 
                    top: 0;
                    width: 148mm;
                    height: auto;
                }
                .no-print { display: none !important; }
            }
        `;
        document.head.appendChild(style);
        
        window.print();
        
        // Clean up
        setTimeout(() => {
            document.head.removeChild(style);
        }, 1000);
    }
    
    // Auto-print
    if (window.location.search.includes('autoprint=1')) {
        setTimeout(() => printTicket(), 500);
    }
    </script>
</body>
</html>