<?php
session_start();
include('../includes/db.php');
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
  header("Location: ../login.php");
  exit;
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Update ride status manually (from dropdown)
if (isset($_POST['update'])) {
  $id = intval($_POST['booking_id']);
  $status = $_POST['ride_status'];

  // Fetch booking info
  $booking_q = $conn->prepare("SELECT bus_id, seat_numbers, ride_status, payment_status FROM bookings WHERE booking_id = ?");
  $booking_q->bind_param("i", $id);
  $booking_q->execute();
  $booking = $booking_q->get_result()->fetch_assoc();

  if ($booking) {
    $bus_id = $booking['bus_id'];
    $seat_numbers = $booking['seat_numbers'];
    $payment_status = $booking['payment_status'];

    // 🟢 If payment done, auto-confirm the booking
    if ($payment_status == 'Paid' && $status != 'Cancelled') {
      $status = 'Confirmed';
    }

    if ($status == 'Cancelled') {
      // Get total seats to free up
      $seats_q = $conn->prepare("SELECT total_seats FROM bookings WHERE booking_id = ?");
      $seats_q->bind_param("i", $id);
      $seats_q->execute();
      $seats_result = $seats_q->get_result()->fetch_assoc();
      $total_seats = $seats_result['total_seats'] ?? 1;
      
      // Free seat and delete booking
      $update_bus = $conn->prepare("UPDATE buses SET available_seats = available_seats + ? WHERE id = ?");
      $update_bus->bind_param("ii", $total_seats, $bus_id);
      $update_bus->execute();
      
      $delete_stmt = $conn->prepare("DELETE FROM bookings WHERE booking_id = ?");
      $delete_stmt->bind_param("i", $id);
      $delete_stmt->execute();
    } else {
      // Normal status update
      $update_stmt = $conn->prepare("UPDATE bookings SET ride_status = ? WHERE booking_id = ?");
      $update_stmt->bind_param("si", $status, $id);
      $update_stmt->execute();
    }
  }
}

// Delete ride manually
if (isset($_GET['delete'])) {
  $id = intval($_GET['delete']);

  $booking_q = $conn->prepare("SELECT bus_id, total_seats FROM bookings WHERE booking_id = ?");
  $booking_q->bind_param("i", $id);
  $booking_q->execute();
  $booking = $booking_q->get_result()->fetch_assoc();

  if ($booking) {
    $bus_id = $booking['bus_id'];
    $total_seats = $booking['total_seats'] ?? 1;
    
    // Update bus seats
    $update_bus = $conn->prepare("UPDATE buses SET available_seats = available_seats + ? WHERE id = ?");
    $update_bus->bind_param("ii", $total_seats, $bus_id);
    $update_bus->execute();
    
    // Delete booking
    $delete_stmt = $conn->prepare("DELETE FROM bookings WHERE booking_id = ?");
    $delete_stmt->bind_param("i", $id);
    $delete_stmt->execute();
  }
  
  header("Location: manage_rides.php");
  exit;
}

// 🟡 NEW: Automatically confirm rides where payment is already done
$conn->query("UPDATE bookings SET ride_status='Confirmed' WHERE payment_status='Paid' AND ride_status='Pending'");

// FIXED QUERY - using correct column names
$rides_query = "SELECT b.booking_id, u.name, u.email, bs.bus_name, b.seat_numbers, b.ride_status, b.payment_status 
  FROM bookings b
  JOIN users u ON b.user_id = u.id
  JOIN buses bs ON b.bus_id = bs.id
  ORDER BY b.booking_id DESC";

$rides_result = $conn->query($rides_query);

// Check for query error
if (!$rides_result) {
    die("Query failed: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Rides</title>
<style>
  body { font-family: Arial; background: #f5f7fa; margin: 0; }
  table { width: 95%; margin: 40px auto; border-collapse: collapse; background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
  th, td { border: 1px solid #ccc; padding: 10px; text-align: center; }
  th { background: #009432; color: white; }
  select, button { padding: 5px; border-radius: 5px; border: 1px solid #009432; }
  button { background: #009432; color: white; cursor: pointer; }
  button:hover { background: #007b28; }
  a.btn { background: red; color: white; padding: 5px 10px; text-decoration: none; border-radius: 4px; }
  a.btn:hover { background: darkred; }
  header { background: #009432; padding: 15px; color: white; display: flex; justify-content: space-between; align-items: center; }
  a.back { color: white; text-decoration: none; font-weight: bold; }
  .message { padding: 10px; margin: 10px auto; width: 95%; border-radius: 5px; text-align: center; }
  .error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
  .success { background: #e8f5e8; color: #2e7d32; border: 1px solid #c8e6c9; }
</style>
</head>
<body>

<header>
  <h2>Manage Rides</h2>
  <a href="dashboard.php" class="back">← Back to Dashboard</a>
</header>

<?php if(isset($_GET['success'])): ?>
  <div class="message success">✅ Operation completed successfully!</div>
<?php endif; ?>

<table>
<tr>
  <th>ID</th>
  <th>Passenger</th>
  <th>Email</th>
  <th>Bus Name</th>
  <th>Seat No</th>
  <th>Payment</th>
  <th>Status</th>
  <th>Action</th>
</tr>

<?php 
if ($rides_result->num_rows > 0) {
    while($r = $rides_result->fetch_assoc()) { 
?>
<tr>
  <td><?php echo $r['booking_id']; ?></td>
  <td><?php echo htmlspecialchars($r['name']); ?></td>
  <td><?php echo htmlspecialchars($r['email']); ?></td>
  <td><?php echo htmlspecialchars($r['bus_name']); ?></td>
  <td><?php echo htmlspecialchars($r['seat_numbers']); ?></td>
  <td>
    <span style="color: <?php echo $r['payment_status'] == 'Paid' ? '#009432' : '#ff9800'; ?>; font-weight: bold;">
      <?php echo htmlspecialchars($r['payment_status']); ?>
    </span>
  </td>
  <td>
    <form method="POST" style="display:inline;">
      <input type="hidden" name="booking_id" value="<?php echo $r['booking_id']; ?>">
      <select name="ride_status" onchange="this.form.submit()">
        <option value="Pending" <?php if($r['ride_status']=='Pending') echo 'selected'; ?>>Pending</option>
        <option value="Confirmed" <?php if($r['ride_status']=='Confirmed') echo 'selected'; ?>>Confirmed</option>
        <option value="Completed" <?php if($r['ride_status']=='Completed') echo 'selected'; ?>>Completed</option>
        <option value="Cancelled" <?php if($r['ride_status']=='Cancelled') echo 'selected'; ?>>Cancelled</option>
      </select>
      <input type="hidden" name="update" value="1">
    </form>
  </td>
  <td><a href="?delete=<?php echo $r['booking_id']; ?>" class="btn" onclick="return confirm('Delete this ride?')">Delete</a></td>
</tr>
<?php 
    }
} else {
    echo '<tr><td colspan="8" style="text-align: center; padding: 20px;">No rides found.</td></tr>';
}
?>

</table>

</body>
</html>