<?php 
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start the session
session_start();
include('../includes/db.php');
// Clean up expired pending bookings (older than 10 minutes) //DYNAMIC SEAT ALLOCATION ALGORITHM
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

// Redirect if not logged in or not passenger
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'passenger') {
  header("Location: ../login.php");
  exit;
}

$user = $_SESSION['user'];

// Check if the database connection is successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle ride cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    $booking_id = intval($_POST['booking_id']);
    
    // Get booking details to update available seats
    $get_booking_query = "SELECT bus_id, total_seats FROM bookings WHERE booking_id = ? AND user_id = ?";
    $get_stmt = $conn->prepare($get_booking_query);
    $get_stmt->bind_param("ii", $booking_id, $user['id']);
    $get_stmt->execute();
    $get_result = $get_stmt->get_result();
    
    if ($get_result->num_rows > 0) {
        $booking_data = $get_result->fetch_assoc();
        $bus_id = $booking_data['bus_id'];
        $total_seats = $booking_data['total_seats'];
        
        // Update available seats in buses table
        $update_bus_stmt = $conn->prepare("UPDATE buses SET available_seats = available_seats + ? WHERE id = ?");
        $update_bus_stmt->bind_param("ii", $total_seats, $bus_id);
        $update_bus_stmt->execute();
        $update_bus_stmt->close();
        
        // Delete the booking
        $delete_stmt = $conn->prepare("DELETE FROM bookings WHERE booking_id = ?");
        $delete_stmt->bind_param("i", $booking_id);
        
        if ($delete_stmt->execute()) {
            $success_msg = "Booking cancelled successfully!";
        } else {
            $error_msg = "Error cancelling booking. Please try again.";
        }
        $delete_stmt->close();
    } else {
        $error_msg = "Booking not found or you don't have permission to cancel it.";
    }
    $get_stmt->close();
}

// Prepare the SQL query to fetch user bookings from NEW table
$stmt = $conn->prepare("
  SELECT b.booking_id, b.bus_id, bs.bus_name, bs.route_from, bs.route_to, bs.departure_time, 
         b.seat_numbers, b.total_seats, b.total_amount, b.ride_status, b.payment_status, 
         bs.driver_name, bs.driver_phone, b.created_at
  FROM bookings b 
  JOIN buses bs ON b.bus_id = bs.id 
  WHERE b.user_id = ? 
  ORDER BY b.created_at DESC
");

// Check if the prepare() was successful
if ($stmt === false) {
    die('MySQL prepare failed: ' . $conn->error);
}

// Bind the user ID parameter
$stmt->bind_param("i", $user['id']);

// Execute the query
$stmt->execute();

// Get the result of the query
$bookings = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Passenger Dashboard | Bus Booking System</title>
<style>
  body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background: #f4f7fa;
    margin: 0;
    padding: 0;
  }

  header {
    background: #009432;
    color: #fff;
    padding: 15px 30px;
    position: sticky;
    top: 0;
    z-index: 999;
  }

  .header-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  header h1 {
    margin: 0;
    font-size: 22px;
  }

  nav {
    display: flex;
    align-items: center;
    gap: 20px;
  }

  nav a {
    color: #fff;
    text-decoration: none;
    font-weight: bold;
    transition: 0.3s;
  }

  nav a:hover {
    text-decoration: underline;
  }

  .container {
    max-width: 1200px;
    margin: 40px auto;
    background: #fff;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
  }

  h2 {
    color: #009432;
    border-bottom: 2px solid #009432;
    padding-bottom: 8px;
    margin-bottom: 20px;
  }

  table {
    width: 100%;
    border-collapse: collapse;
  }

  th, td {
    border: 1px solid #ddd;
    padding: 12px;
    text-align: center;
  }

  th {
    background: #009432;
    color: #fff;
  }

  tr:nth-child(even) {
    background: #f9f9f9;
  }

  .no-bookings {
    text-align: center;
    color: #555;
    padding: 20px;
  }

  .btn {
    background: #009432;
    color: #fff;
    padding: 8px 15px;
    border-radius: 5px;
    text-decoration: none;
    font-weight: bold;
    transition: 0.3s;
    cursor: pointer;
    border: none;
    margin: 2px;
    font-size: 14px;
  }

  .btn:hover {
    background: #007b2a;
  }

  .btn-cancel {
    background: #e74c3c;
  }

  .btn-cancel:hover {
    background: #c0392b;
  }

  .btn-ticket {
    background: #3498db;
  }

  .btn-ticket:hover {
    background: #2980b9;
  }

  #detailsModal, #cancelModal {
    display: none;
    position: fixed;
    top:0; left:0;
    width:100%; height:100%;
    background: rgba(0,0,0,0.6);
    justify-content: center;
    align-items: center;
    z-index: 1000;
  }

  .modal-content {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    width: 350px;
    max-width: 90%;
  }

  .modal-content h3 {
    margin-top: 0;
    color: #009432;
  }

  .cancel-modal .modal-content h3 {
    color: #e74c3c;
  }

  .modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
  }

  .alert {
    padding: 12px 15px;
    border-radius: 5px;
    margin-bottom: 20px;
  }

  .alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
  }

  .alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
  }

  .cancelled {
    color: #e74c3c;
    font-weight: bold;
  }

  .btn-group {
    display: flex;
    gap: 5px;
    justify-content: center;
    flex-wrap: wrap;
  }

  .status-paid {
    color: #27ae60;
    font-weight: bold;
  }

  .status-pending {
    color: #f39c12;
    font-weight: bold;
  }

  .status-cancelled {
    color: #e74c3c;
    font-weight: bold;
  }

  .status-upcoming {
    color: #3498db;
    font-weight: bold;
  }

  .status-completed {
    color: #7f8c8d;
    font-weight: bold;
  }
  
  .seat-numbers {
    font-weight: bold;
    color: #009432;
    background: #e8f5e9;
    padding: 3px 8px;
    border-radius: 4px;
  }
  
  .total-seats {
    color: #666;
    font-size: 0.9em;
  }
  
  .total-amount {
    color: #009432;
    font-weight: bold;
    font-size: 1.1em;
  }
</style>
</head>
<body>

<header>
  <div class="header-nav">
    <h1>Welcome, <?php echo htmlspecialchars($user['name']); ?> 👋</h1>
    <nav>
      <a href="../index.php">Home</a>
      <a href="search.php">Search Buses</a>
      <a href="profile.php">Profile</a>
      <a href="../logout.php">Logout</a>
    </nav>
  </div>
</header>

<div class="container">
  <h2>Your Bookings & Tickets</h2>

  <?php if (isset($success_msg)): ?>
    <div class="alert alert-success">
      <?php echo htmlspecialchars($success_msg); ?>
    </div>
  <?php endif; ?>

  <?php if (isset($error_msg)): ?>
    <div class="alert alert-error">
      <?php echo htmlspecialchars($error_msg); ?>
    </div>
  <?php endif; ?>

  <?php if ($bookings->num_rows > 0): ?>
  <table>
    <tr>
      <th>Booking ID</th>
      <th>Bus Name</th>
      <th>Route</th>
      <th>Departure</th>
      <th>Seats</th>
      <th>Total Amount</th>
      <th>Payment Status</th>
      <th>Ride Status</th>
      <th>Actions</th>
    </tr>
    <?php while ($row = $bookings->fetch_assoc()): ?>
    <tr>
      <td>#<?php echo $row['booking_id']; ?></td>
      <td><?php echo $row['bus_name']; ?></td>
      <td><?php echo $row['route_from'] . " → " . $row['route_to']; ?></td>
      <td><?php echo date("d M Y, h:i A", strtotime($row['departure_time'])); ?></td>
      <td>
        <span class="seat-numbers"><?php echo htmlspecialchars($row['seat_numbers']); ?></span><br>
        <span class="total-seats">(<?php echo $row['total_seats']; ?> seat<?php echo $row['total_seats'] > 1 ? 's' : ''; ?>)</span>
      </td>
      <td class="total-amount">Rs <?php echo number_format($row['total_amount']); ?></td>
      <td>
        <?php if ($row['payment_status'] == 'Paid'): ?>
          <span class="status-paid">✅ <?php echo $row['payment_status']; ?></span>
        <?php elseif ($row['payment_status'] == 'Pending'): ?>
          <span class="status-pending">⏳ <?php echo $row['payment_status']; ?></span>
        <?php else: ?>
          <?php echo $row['payment_status']; ?>
        <?php endif; ?>
      </td>
      <td>
        <?php if ($row['ride_status'] == 'Upcoming'): ?>
          <span class="status-upcoming">🚌 <?php echo $row['ride_status']; ?></span>
        <?php elseif ($row['ride_status'] == 'Completed'): ?>
          <span class="status-completed">✅ <?php echo $row['ride_status']; ?></span>
        <?php elseif ($row['ride_status'] == 'Cancelled'): ?>
          <span class="status-cancelled">❌ <?php echo $row['ride_status']; ?></span>
        <?php else: ?>
          <?php echo $row['ride_status']; ?>
        <?php endif; ?>
      </td>
      <td>
        <div class="btn-group">
          <button class="btn" onclick="showDetails('<?php echo addslashes($row['bus_name']); ?>', '<?php echo addslashes($row['driver_name']); ?>', '<?php echo $row['driver_phone']; ?>')">Details</button>
          
          <?php if ($row['payment_status'] == 'Paid'): ?>
            <a href="ticket.php?id=<?php echo $row['booking_id']; ?>" class="btn btn-ticket">View Ticket</a>
          <?php endif; ?>
          
          <?php if ($row['ride_status'] == 'Upcoming' && $row['payment_status'] == 'Paid'): ?>
            <button class="btn btn-cancel" onclick="showCancelModal(<?php echo $row['booking_id']; ?>, '<?php echo addslashes($row['bus_name']); ?>')">Cancel</button>
          <?php endif; ?>
        </div>
      </td>
    </tr>
    <?php endwhile; ?>
  </table>
  <?php else: ?>
    <p class="no-bookings">You have no bookings yet. <a href="search.php" class="btn">Book Now</a></p>
  <?php endif; ?>
</div>

<!-- Details Modal -->
<div id="detailsModal">
  <div class="modal-content">
    <h3>Bus & Driver Details</h3>
    <p><strong>Bus Name:</strong> <span id="modalBusName"></span></p>
    <p><strong>Driver Name:</strong> <span id="modalDriverName"></span></p>
    <p><strong>Driver Phone:</strong> <span id="modalDriverPhone"></span></p>
    <button class="btn" onclick="closeModal()">Close</button>
  </div>
</div>

<!-- Cancel Confirmation Modal -->
<div id="cancelModal" class="cancel-modal">
  <div class="modal-content">
    <h3>Cancel Ride</h3>
    <p>Are you sure you want to cancel your ride on <strong id="cancelBusName"></strong>?</p>
    <p class="cancelled">This action cannot be undone!</p>
    
    <form id="cancelForm" method="POST" action="">
      <input type="hidden" name="booking_id" id="cancelBookingId">
      <input type="hidden" name="cancel_booking" value="1">
      
      <div class="modal-actions">
        <button type="button" class="btn" onclick="closeCancelModal()">No, Keep It</button>
        <button type="submit" class="btn btn-cancel">Yes, Cancel Ride</button>
      </div>
    </form>
  </div>
</div>

<script>
let currentBookingId = null;

function showDetails(busName, driverName, driverPhone) {
    document.getElementById('modalBusName').innerText = busName;
    document.getElementById('modalDriverName').innerText = driverName;
    document.getElementById('modalDriverPhone').innerText = driverPhone;
    document.getElementById('detailsModal').style.display = 'flex';
}

function showCancelModal(bookingId, busName) {
    currentBookingId = bookingId;
    document.getElementById('cancelBookingId').value = bookingId;
    document.getElementById('cancelBusName').innerText = busName;
    document.getElementById('cancelModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('detailsModal').style.display = 'none';
}

function closeCancelModal() {
    document.getElementById('cancelModal').style.display = 'none';
    currentBookingId = null;
}

// Close modals when clicking outside
window.onclick = function(event) {
    const detailsModal = document.getElementById('detailsModal');
    const cancelModal = document.getElementById('cancelModal');
    
    if (event.target == detailsModal) {
        detailsModal.style.display = 'none';
    }
    if (event.target == cancelModal) {
        cancelModal.style.display = 'none';
    }
}
</script>

</body>
</html>