<?php
session_start();
include('../includes/db.php');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Restrict to logged-in passengers
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'passenger') {
    header("Location: ../login.php");
    exit;
}

$user = $_SESSION['user'];
$bus_id = isset($_GET['bus_id']) ? intval($_GET['bus_id']) : 0;

// Clear any old pending bookings for this user (older than 15 minutes)
// Change from 15 to 10 minutes
$cleanup_query = $conn->prepare("DELETE FROM bookings WHERE user_id = ? AND payment_status='Pending' AND TIMESTAMPDIFF(MINUTE, created_at, NOW()) >= 10");
$cleanup_query->bind_param("i", $user['id']);
$cleanup_query->execute();
$cleanup_query->close();

// Fetch bus details
$bus_query = $conn->prepare("
    SELECT 
        b.*, 
        (b.total_seats - IFNULL((
            SELECT SUM(total_seats) 
            FROM bookings 
            WHERE bus_id = b.id 
                AND payment_status = 'Paid' 
                AND ride_status != 'Cancelled'
        ), 0)) AS available_seats_live
    FROM buses b
    WHERE b.id = ?
");

if (!$bus_query) {
    die("SQL Error: " . $conn->error);
}

$bus_query->bind_param("i", $bus_id);
$bus_query->execute();
$bus_result = $bus_query->get_result();
$bus = $bus_result->fetch_assoc();

if (!$bus) {
    die("<h2>❌ Invalid Bus ID.</h2>");
}
$bus_query->close();

// Fetch booked and pending seats
$bookedSeats = [];
$pendingSeats = [];

$seat_query = $conn->prepare("
    SELECT seat_numbers, payment_status, user_id
    FROM bookings 
    WHERE bus_id = ? 
    AND ride_status != 'Cancelled'
    AND (payment_status = 'Paid' OR payment_status = 'Pending')
");

if (!$seat_query) {
    die("SQL Error: " . $conn->error);
}

$seat_query->bind_param("i", $bus_id);
$seat_query->execute();
$seat_result = $seat_query->get_result();

while ($row = $seat_result->fetch_assoc()) {
    $seats = array_map('trim', explode(',', $row['seat_numbers']));
    
    foreach ($seats as $seat) {
        if (!empty($seat)) {
            if ($row['payment_status'] === 'Paid') {
                $bookedSeats[] = $seat;
            } elseif ($row['payment_status'] === 'Pending') {
                $pendingSeats[] = ['seat' => $seat, 'user_id' => $row['user_id']];
            }
        }
    }
}
$seat_query->close();

// Handle multi-seat booking
$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['selected_seats'])) {
    $selected_seats_str = trim($_POST['selected_seats']);
    
    if (!empty($selected_seats_str)) {
        $selected_seats = array_filter(explode(',', $selected_seats_str));
        $selected_seats = array_map('trim', $selected_seats);
        
        if (count($selected_seats) > 0) {
            // Check if any selected seat is already booked or pending
            $invalid_seats = [];
            foreach ($selected_seats as $seat) {
                if (in_array($seat, $bookedSeats)) {
                    $invalid_seats[] = $seat . " (already booked)";
                }
                
                // Check if seat is pending for another user //SOFT LOCKING ALGORITHM
                foreach ($pendingSeats as $pending) {
                    if ($pending['seat'] == $seat && $pending['user_id'] != $user['id']) {
                        $invalid_seats[] = $seat . " (being booked by another user)";
                    }
                }
            }
            
            if (!empty($invalid_seats)) {
                $error = "❌ Some seats are not available: " . implode(", ", $invalid_seats);
            } else {
                // Combine all seats into one string WITH SPACE after comma (matches database format)
                $seat_numbers = implode(', ', $selected_seats);
                $total_seats = count($selected_seats);
                $total_amount = $bus['ticket_price'] * $total_seats;
                
                // Insert booking //SOFT LOCKING ALGORITHM
                $insert = $conn->prepare("
                    INSERT INTO bookings 
                    (user_id, bus_id, seat_numbers, total_seats, total_amount, payment_status, ride_status, created_at) 
                    VALUES (?, ?, ?, ?, ?, 'Pending', 'Upcoming', NOW())
                ");
                
                if (!$insert) {
                    $error = "SQL Error: " . $conn->error;
                } else {
                    $insert->bind_param("iisid", $user['id'], $bus_id, $seat_numbers, $total_seats, $total_amount);
                    
                    if ($insert->execute()) {
                        $booking_id = $conn->insert_id;
                        
                        // SET THE SESSION DATA THAT payment.php EXPECTS
                        $_SESSION['recently_booked_seats'] = $selected_seats;
                        $_SESSION['recent_booking_bus_id'] = $bus_id;
                        $_SESSION['booking_time'] = time(); // This is CRITICAL for payment.php timer
                        $_SESSION['booking_id'] = $booking_id; // Store booking_id for easy access
                        
                        // REDIRECT TO PAYMENT PAGE with booking_id
                        header("Location: payment.php?bus_id=" . $bus_id . "&booking_id=" . $booking_id);
                        exit();
                        
                    } else {
                        $error = "❌ Booking failed. Error: " . $insert->error;
                    }
                    $insert->close();
                }
            }
        } else {
            $error = "❌ No seats selected!";
        }
    } else {
        $error = "❌ No seats selected!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Book Seat | Bus Booking System</title>
<style>
    body { 
        font-family: 'Segoe UI', Arial, sans-serif; 
        background: #f4f7fa; 
        margin:0; 
        padding:0; 
    }
    header { 
        background:#009432; 
        color:#fff; 
        padding:15px 30px; 
        display:flex; 
        justify-content:space-between; 
        align-items:center; 
    }
    header h1 { 
        margin:0; 
        font-size:20px; 
    }
    nav a { 
        color:#fff; 
        text-decoration:none; 
        margin-left:18px; 
        font-weight:bold; 
    }
    nav a:hover {
        text-decoration: underline;
    }
    .container { 
        max-width:950px; 
        margin:30px auto; 
        background:#fff; 
        padding:28px; 
        border-radius:10px; 
        box-shadow:0 4px 15px rgba(0,0,0,0.08); 
    }
    .bus-info p { 
        margin:6px 0; 
        font-size:1rem; 
    }
    .bus-visual { 
        display:flex; 
        gap:30px; 
        align-items:flex-start; 
        flex-wrap:wrap; 
    }
    
    /* bus outer */
    .bus-frame {
        background: #e9eef2;
        border-radius: 12px;
        padding: 50px 18px 18px 18px;
        position: relative;
        width: 420px;
        min-height: 620px;
        box-shadow: inset 0 -8px 12px rgba(0,0,0,0.03);
    }

    /* Driver seat */
    .driver {
        position: absolute;
        top: 5px;
        right: 22px;
        width: 64px;
        height: 48px;
        background: #ffeb3b;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        box-shadow: 0 2px 6px rgba(0,0,0,0.12);
    }
    
    /* seat rows */
    .seat-row { 
        display:flex; 
        align-items:center; 
        justify-content:space-between; 
        margin:12px 6px; 
    }
    .seat-col { 
        display:flex; 
        gap:10px; 
        align-items:center; 
    }
    .aisle { 
        width:56px; 
        height:1px; 
    }
    .seat {
        width:56px; 
        height:56px; 
        border-radius:8px; 
        background:#d1d3d4; 
        display:flex; 
        align-items:center; 
        justify-content:center;
        font-weight:700; 
        cursor:pointer; 
        transition:all .15s ease;
        user-select: none;
    }
    .seat:hover { 
        transform:translateY(-3px); 
        background:#a9abae; 
    }
    .seat.booked { 
        background:#ff5252 !important; 
        color:#fff !important; 
        cursor:not-allowed !important; 
        transform:none !important; 
    }
    .seat.pending { 
        background:#ffcc00 !important; 
        color:#222 !important; 
        cursor:not-allowed !important; 
        transform:none !important; 
    }
    .seat.selected { 
        background:#009432 !important; 
        color:#fff !important; 
    }
    .legend { 
        display:flex; 
        gap:12px; 
        align-items:center; 
        margin-left:10px; 
    }
    .legend span { 
        display:inline-flex; 
        width:18px; 
        height:18px; 
        border-radius:4px; 
        align-items:center; 
        justify-content:center; 
        font-size:12px; 
        color:#fff; 
    }
    .leg-available { 
        background:#d1d3d4; 
        color:#222; 
        border:1px solid #c2c4c5; 
    }
    .leg-booked { 
        background:#ff5252; 
    }
    .leg-pending { 
        background:#ffcc00; 
        color:#222; 
    }
    .leg-selected { 
        background:#009432; 
    }
    .price-display { 
        text-align:center; 
        font-size:1.15rem; 
        margin-top:16px; 
        color:#333; 
    }
    button { 
        background:#009432; 
        color:#fff; 
        border:none; 
        padding:12px 26px; 
        border-radius:8px; 
        cursor:pointer; 
        font-weight:700; 
        display:block; 
        margin:20px auto 0; 
        transition: background 0.3s;
    }
    button:hover:not(:disabled) {
        background:#007b28;
    }
    button:disabled { 
        background:#ccc; 
        cursor:not-allowed; 
    }
    .message { 
        text-align:center; 
        margin:12px 0; 
        font-weight:700; 
        padding: 10px; 
        border-radius: 5px; 
    }
    .error { 
        background: #fee; 
        color:#c0392b; 
        border: 1px solid #fcc; 
    }
    .success { 
        background: #efe; 
        color:#16a085; 
        border: 1px solid #cfc; 
    }
    footer { 
        background:#009432; 
        color:#fff; 
        text-align:center; 
        padding:14px; 
        margin-top:26px; 
        border-radius:0 0 12px 12px; 
    }
    .selected-seats {
        background: #e8f5e9;
        padding: 10px;
        border-radius: 8px;
        margin: 10px 0;
        display: none;
    }
    .selected-seats.active {
        display: block;
    }
    .selected-seats span {
        background: #009432;
        color: white;
        padding: 4px 8px;
        border-radius: 4px;
        margin: 0 5px 5px 0;
        display: inline-block;
    }

    @media (max-width:600px){ 
        .bus-frame{ width:100%; } 
        .seat{ width:46px;height:46px; } 
        .aisle{ width:40px; } 
    }
</style>
</head>
<body>
<header>
    <h1>🚌 Book Seat</h1>
    <nav>
        <a href="../index.php">Home</a>
        <a href="home.php">Dashboard</a>
        <a href="search.php">Search</a>
        <a href="../logout.php">Logout</a>
    </nav>
</header>

<div class="container">
    <h2>Bus Details</h2>
    <div class="bus-info">
        <p><strong>Bus Name:</strong> <?= htmlspecialchars($bus['bus_name']) ?></p>
        <p><strong>Route:</strong> <?= htmlspecialchars($bus['route_from']) ?> → <?= htmlspecialchars($bus['route_to']) ?></p>
        <p><strong>Departure Time:</strong> <?= date("d M Y, h:i A", strtotime($bus['departure_time'])) ?></p>
        <p><strong>Price per seat:</strong> Rs <?= number_format($bus['ticket_price']) ?></p>
        <p><strong>Available Seats:</strong> <span style="color: <?= intval($bus['available_seats_live']) > 5 ? '#009432' : '#ff5252' ?>; font-weight: bold;">
            <?= intval($bus['available_seats_live']) ?>
        </span> of <?= intval($bus['total_seats']) ?></p>
    </div>

    <?php if(!empty($error)): ?>
        <div class="message error"><?= $error ?></div>
    <?php endif; ?>
    
    <?php if(isset($_GET['success'])): ?>
        <div class="message success">Booking created! Redirecting to payment...</div>
    <?php endif; ?>

    <h2>Select Your Seat(s)</h2>
    
    <div class="selected-seats" id="selectedSeatsDisplay">
        <strong>Selected Seats:</strong> <span id="selectedSeatsList"></span>
    </div>

    <div class="bus-visual">
        <form method="POST" id="seatForm" style="width:100%">
            <div style="display:flex; align-items:flex-start; gap:24px; flex-wrap:wrap;">
                <div class="bus-frame" id="seatLayout">
                    <div class="driver">Driver</div>

                    <?php
                    // Dynamically generate seat layout
                    $totalSeats = intval($bus['total_seats']);
                    $seatNum = 1;

                    function render_seat($code, $bookedSeats, $pendingSeats) {
                        $seatClass = 'seat';
                        if (in_array($code, $bookedSeats)) {
                            $seatClass .= ' booked';
                        } elseif (in_array($code, array_column($pendingSeats, 'seat'))) {
                            $seatClass .= ' pending';
                        }
                        return "<div class='$seatClass' data-seat='$code'>$code</div>";
                    }

                    // FRONT ROW: 2 left + 1 right
                    echo "<div class='seat-row'>";
                    echo "<div class='seat-col'>";
                    for ($i = 0; $i < 2 && $seatNum <= $totalSeats; $i++) {
                        $code = 'S' . $seatNum++;
                        echo render_seat($code, $bookedSeats, $pendingSeats);
                    }
                    echo "</div>";
                    echo "<div class='aisle'></div>";
                    echo "<div class='seat-col'>";
                    if ($seatNum <= $totalSeats) {
                        $code = 'S' . $seatNum++;
                        echo render_seat($code, $bookedSeats, $pendingSeats);
                    }
                    echo "</div>";
                    echo "</div>";

                    // Regular rows
                    while ($seatNum <= $totalSeats) {
                        echo "<div class='seat-row'>";
                        echo "<div class='seat-col'>";
                        for ($i = 0; $i < 2 && $seatNum <= $totalSeats; $i++) {
                            $code = 'S' . $seatNum++;
                            echo render_seat($code, $bookedSeats, $pendingSeats);
                        }
                        echo "</div>";
                        echo "<div class='aisle'></div>";
                        echo "<div class='seat-col'>";
                        for ($i = 0; $i < 2 && $seatNum <= $totalSeats; $i++) {
                            $code = 'S' . $seatNum++;
                            echo render_seat($code, $bookedSeats, $pendingSeats);
                        }
                        echo "</div>";
                        echo "</div>";
                    }
                    ?>
                </div>

                <div style="min-width:200px;">
                    <div style="margin-bottom:12px;">
                        <strong>Legend</strong>
                        <div class="legend" style="margin-top:8px;">
                            <div style="display:flex; flex-direction:column; gap:8px;">
                                <div style="display:flex; gap:8px; align-items:center;">
                                    <span class="leg-available"></span> Available
                                </div>
                                <div style="display:flex; gap:8px; align-items:center;">
                                    <span class="leg-booked"></span> Booked (Paid)
                                </div>
                                <div style="display:flex; gap:8px; align-items:center;">
                                    <span class="leg-pending"></span> Pending (Held)
                                </div>
                                <div style="display:flex; gap:8px; align-items:center;">
                                    <span class="leg-selected"></span> Selected
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top:18px;">
                        <div class="price-display" id="priceDisplay">Total: Rs 0</div>
                        <input type="hidden" name="selected_seats" id="selected_seats" required>
                        <button type="submit" id="confirmBtn" disabled>Confirm Booking</button>
                        <button type="button" id="clearBtn" style="background: #ff5252; margin-top: 10px; display: none;">Clear Selection</button>
                    </div>
                    
                    <div style="margin-top:20px; padding:15px; background:#f8f9fa; border-radius:8px; border:1px solid #ddd;">
                        <p style="margin:0 0 8px 0; font-weight:bold; color:#333;">Tips:</p>
                        <ul style="margin:0; padding-left:20px; font-size:0.9rem; color:#555;">
                            <li>Click on seats to select/deselect</li>
                            <li>Red seats are already booked</li>
                            <li>Yellow seats are temporarily held by other users</li>
                            <li>You have 10 minutes to complete payment</li>
                        </ul>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <p style="margin-top:18px; font-size:0.95rem; color:#444;">
        <em>Showing <?= intval($bus['total_seats']) ?> seats (S1 to S<?= intval($bus['total_seats']) ?>).</em>
    </p>

</div>

<footer>
    <p>© <?= date('Y') ?> Bus Booking System | Passenger Panel</p>
</footer>

<script>
    // JS: seat selection and price calc
    (function(){
        const seats = document.querySelectorAll('.seat');
        const selectedSeatsInput = document.getElementById('selected_seats');
        const priceDisplay = document.getElementById('priceDisplay');
        const confirmBtn = document.getElementById('confirmBtn');
        const clearBtn = document.getElementById('clearBtn');
        const selectedSeatsDisplay = document.getElementById('selectedSeatsDisplay');
        const selectedSeatsList = document.getElementById('selectedSeatsList');
        const pricePerSeat = <?= json_encode(intval($bus['ticket_price'])); ?>;
        let selectedSeats = [];

        // Seat selection logic
        seats.forEach(seat => {
            seat.addEventListener('click', () => {
                if (seat.classList.contains('booked') || seat.classList.contains('pending')) {
                    return;
                }

                const seatNo = seat.dataset.seat;
                if (seat.classList.contains('selected')) {
                    seat.classList.remove('selected');
                    selectedSeats = selectedSeats.filter(s => s !== seatNo);
                } else {
                    seat.classList.add('selected');
                    selectedSeats.push(seatNo);
                }

                updateSelection();
            });
        });

        function updateSelection() {
            selectedSeatsInput.value = selectedSeats.join(',');
            const total = selectedSeats.length * pricePerSeat;
            priceDisplay.textContent = `Total: Rs ${total}`;
            
            // Update selected seats display
            if (selectedSeats.length > 0) {
                selectedSeatsList.innerHTML = selectedSeats.map(seat => 
                    `<span>${seat}</span>`
                ).join('');
                selectedSeatsDisplay.classList.add('active');
                clearBtn.style.display = 'block';
            } else {
                selectedSeatsDisplay.classList.remove('active');
                clearBtn.style.display = 'none';
            }
            
            // Enable/disable confirm button
            confirmBtn.disabled = selectedSeats.length === 0;
        }

        // Clear selection button
        clearBtn.addEventListener('click', () => {
            seats.forEach(seat => {
                if (!seat.classList.contains('booked') && !seat.classList.contains('pending')) {
                    seat.classList.remove('selected');
                }
            });
            selectedSeats = [];
            updateSelection();
        });

        // Form submission validation
        document.getElementById('seatForm').addEventListener('submit', function(e) {
            if (selectedSeats.length === 0) {
                e.preventDefault();
                alert("Please select at least one seat!");
                return false;
            }
            
            // Validate seat availability
            const unavailableSeats = [];
            selectedSeats.forEach(seatNo => {
                const seatElement = document.querySelector(`.seat[data-seat="${seatNo}"]`);
                if (seatElement && (seatElement.classList.contains('booked') || seatElement.classList.contains('pending'))) {
                    unavailableSeats.push(seatNo);
                }
            });
            
            if (unavailableSeats.length > 0) {
                e.preventDefault();
                alert(`Some seats are no longer available: ${unavailableSeats.join(', ')}. Please refresh the page and try again.`);
                return false;
            }
            
            // Show loading state
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = 'Processing...';
            return true;
        });

        // Initialize
        updateSelection();

    })();
</script>
</body>
</html>