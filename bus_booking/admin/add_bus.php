<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
  header("Location: ../login.php");
  exit;
}

// Initialize form field variables
$bus_name = $bus_number = $driver_name = $driver_phone = $route_from = $route_to = $departure_time = $ticket_price = $total_seats = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $bus_name = trim($_POST['bus_name']);
  $bus_number = trim($_POST['bus_number']);
  $driver_name = trim($_POST['driver_name']);
  $driver_phone = trim($_POST['driver_phone']);
  $route_from = trim($_POST['route_from']);
  $route_to = trim($_POST['route_to']);
  $departure_time = $_POST['departure_time'];
  $ticket_price = floatval($_POST['ticket_price']);
  $total_seats = intval($_POST['total_seats']);
  
  $error = '';
  
  // Validate departure time - prevent past date/time
  $current_datetime = date('Y-m-d\TH:i');
  if (strtotime($departure_time) < time()) {
    $error = "❌ Departure time cannot be in the past.";
  }
  
  // Validate driver phone number
  elseif (!preg_match('/^(97|98)\d{8}$/', $driver_phone)) {
    $error = "❌ Invalid driver phone number. It must start with 97 or 98 and contain exactly 10 digits.";
  }
  
  if (empty($error)) {
    // Start transaction
    $conn->begin_transaction();
    
    try {
      // Insert bus
      $stmt = $conn->prepare("INSERT INTO buses (bus_name, bus_number, driver_name, driver_phone, route_from, route_to, departure_time, ticket_price, total_seats, available_seats)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
      $available_seats = $total_seats;
      $stmt->bind_param("sssssssdii", $bus_name, $bus_number, $driver_name, $driver_phone, $route_from, $route_to, $departure_time, $ticket_price, $total_seats, $available_seats);
      
      if ($stmt->execute()) {
        $conn->commit();
        $success = "✅ Bus added successfully! All $total_seats seats are available for booking.";
        // Clear form fields after successful submission
        $bus_name = $bus_number = $driver_name = $driver_phone = $route_from = $route_to = $departure_time = $ticket_price = $total_seats = '';
      } else {
        throw new Exception("Error adding bus");
      }
    } catch (Exception $e) {
      $conn->rollback();
      $error = "❌ Error adding bus. Please try again. Error: " . $e->getMessage();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Bus | Admin Panel</title>
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
  <h2>🚌 Add New Bus</h2>
  <a href="dashboard.php" class="back">← Back to Dashboard</a>
</header>

<div class="container">
  <h3>Bus Information</h3>

  <?php if(isset($success)) echo "<p class='message success'>$success</p>"; ?>
  <?php if(isset($error)) echo "<p class='message error'>$error</p>"; ?>

  <form method="POST" autocomplete="off">
    <label>Bus Name</label>
    <input type="text" name="bus_name" placeholder="Enter bus name" value="<?php echo htmlspecialchars($bus_name); ?>" required>

    <label>Bus Number</label>
    <input type="text" name="bus_number" placeholder="Enter bus number plate" value="<?php echo htmlspecialchars($bus_number); ?>" required>

    <label>Driver Full Name</label>
    <input type="text" name="driver_name" placeholder="Enter driver's full name" value="<?php echo htmlspecialchars($driver_name); ?>" required>

    <label>Driver Phone Number</label>
    <input type="text" name="driver_phone" placeholder="98XXXXXXXX" value="<?php echo htmlspecialchars($driver_phone); ?>" required>

    <label>From (Route Start)</label>
    <input type="text" name="route_from" placeholder="Enter starting city" value="<?php echo htmlspecialchars($route_from); ?>" required>

    <label>To (Route Destination)</label>
    <input type="text" name="route_to" placeholder="Enter destination city" value="<?php echo htmlspecialchars($route_to); ?>" required>

    <label>Departure Time</label>
    <input type="datetime-local" name="departure_time" value="<?php echo htmlspecialchars($departure_time); ?>" min="<?php echo date('Y-m-d\TH:i'); ?>" required>

    <label>Ticket Price (Rs)</label>
    <input type="number" name="ticket_price" min="0" step="0.01" placeholder="Enter price per seat" value="<?php echo htmlspecialchars($ticket_price); ?>" required>

    <label>Total Seats</label>
    <input type="number" name="total_seats" min="1" placeholder="Enter total seats" value="<?php echo htmlspecialchars($total_seats); ?>" required>

    <button type="submit">Add Bus</button>
  </form>
</div>

</body>
</html>