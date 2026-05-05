<?php
session_start();
include('../includes/db.php');

// Restrict to admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
  header("Location: ../login.php");
  exit;
}

$bus_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch bus details
$query = $conn->prepare("SELECT * FROM buses WHERE id = ?");
$query->bind_param("i", $bus_id);
$query->execute();
$bus = $query->get_result()->fetch_assoc();

if (!$bus) {
  die("<h2>❌ Invalid Bus ID.</h2>");
}

// Update bus details
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $bus_name = trim($_POST['bus_name']);
  $driver_name = trim($_POST['driver_name']);
  $driver_phone = trim($_POST['driver_phone']);
  $route_from = trim($_POST['route_from']);
  $route_to = trim($_POST['route_to']);
  $departure_time = $_POST['departure_time'];
  $ticket_price = floatval($_POST['ticket_price']);
  $total_seats = intval($_POST['total_seats']);
  $available_seats = intval($_POST['available_seats']);

  // Validate driver phone number
  if (!preg_match('/^(97|98)\d{8}$/', $driver_phone)) {
    $error = "❌ Invalid driver phone number. It must start with 97 or 98 and contain 10 digits.";
  } else {
    $update = $conn->prepare("
      UPDATE buses 
      SET bus_name=?, driver_name=?, driver_phone=?, route_from=?, route_to=?, departure_time=?, ticket_price=?, total_seats=?, available_seats=? 
      WHERE id=?
    ");
    $update->bind_param("ssssssdiii", $bus_name, $driver_name, $driver_phone, $route_from, $route_to, $departure_time, $ticket_price, $total_seats, $available_seats, $bus_id);
    
    if ($update->execute()) {
      $success = "✅ Bus details updated successfully!";
      // Refresh the data
      $query->execute();
      $bus = $query->get_result()->fetch_assoc();
    } else {
      $error = "❌ Failed to update bus details. Please try again.";
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Bus | Admin Panel</title>
<style>
  body { font-family: 'Segoe UI', Arial; background: #f5f7fa; margin: 0; }
  header { background: #009432; color: white; padding: 15px; display: flex; justify-content: space-between; align-items: center; }
  header h2 { margin: 0; }
  a.back { color: white; text-decoration: none; font-weight: bold; }
  .container {
    max-width: 700px; 
    margin: 40px auto; 
    background: #fff; 
    padding: 30px; 
    border-radius: 10px; 
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
  }
  h3 { color: #009432; border-bottom: 2px solid #009432; padding-bottom: 8px; }
  form input, form select {
    width: 100%;
    padding: 10px;
    margin: 10px 0;
    border: 1px solid #ccc;
    border-radius: 6px;
    outline: none;
  }
  input:focus { border-color: #009432; box-shadow: 0 0 5px rgba(0,148,50,0.3); }
  button {
    background: #009432; color: white; border: none; padding: 12px 25px;
    border-radius: 6px; cursor: pointer; font-weight: bold; display: block; margin: 20px auto;
  }
  button:hover { background: #007b28; transform: scale(1.03); }
  .message { text-align: center; font-weight: bold; margin-top: 10px; }
  .success { color: green; }
  .error { color: red; }
</style>
</head>
<body>

<header>
  <h2>✏️ Edit Bus Details</h2>
  <a href="manage_buses.php" class="back">← Back to Manage Buses</a>
</header>

<div class="container">
  <h3>Update Bus Information</h3>

  <?php if(isset($success)) echo "<p class='message success'>$success</p>"; ?>
  <?php if(isset($error)) echo "<p class='message error'>$error</p>"; ?>

  <form method="POST" autocomplete="off">
    <label>Bus Name</label>
    <input type="text" name="bus_name" value="<?= htmlspecialchars($bus['bus_name']); ?>" required>

    <label>Driver Full Name</label>
    <input type="text" name="driver_name" value="<?= htmlspecialchars($bus['driver_name']); ?>" required>

    <label>Driver Phone Number</label>
    <input type="text" name="driver_phone" value="<?= htmlspecialchars($bus['driver_phone']); ?>" required placeholder="98XXXXXXXX">

    <label>From (Route Start)</label>
    <input type="text" name="route_from" value="<?= htmlspecialchars($bus['route_from']); ?>" required>

    <label>To (Route Destination)</label>
    <input type="text" name="route_to" value="<?= htmlspecialchars($bus['route_to']); ?>" required>

    <label>Departure Time</label>
    <input type="datetime-local" name="departure_time" 
           value="<?= date('Y-m-d\TH:i', strtotime($bus['departure_time'])); ?>" required>

    <label>Ticket Price (Rs)</label>
    <input type="number" name="ticket_price" min="0" step="0.01" value="<?= htmlspecialchars($bus['ticket_price']); ?>" required>

    <label>Total Seats</label>
    <input type="number" name="total_seats" min="1" value="<?= htmlspecialchars($bus['total_seats']); ?>" required>

    <label>Available Seats</label>
    <input type="number" name="available_seats" min="0" value="<?= htmlspecialchars($bus['available_seats']); ?>" required>

    <button type="submit">Update Bus</button>
  </form>
</div>

</body>
</html>
