<?php
session_start();
include('../includes/db.php');

// Redirect if not logged in or not a passenger
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'passenger') {
  header("Location: ../login.php");
  exit;
}

$user = $_SESSION['user'];
$buses = [];

// ========== CLEANUP EXPIRED PENDING BOOKINGS ==========
// Release seats from expired pending bookings (more than 10 minutes old)
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

// Delete expired pending bookings
$conn->query("
    DELETE FROM bookings 
    WHERE payment_status='Pending' 
      AND ride_status='Upcoming' 
      AND TIMESTAMPDIFF(MINUTE, created_at, NOW()) >= 10
");

// Handle bus search
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $from = trim($_POST['from']);
  $to = trim($_POST['to']);

  // Modified query to ensure we get accurate seat counts
  $stmt = $conn->prepare("
    SELECT 
      b.id,
      b.bus_name,
      b.route_from,
      b.route_to,
      b.departure_time,
      b.ticket_price,
      b.total_seats,
      (
        SELECT b.total_seats - IFNULL(SUM(bk.total_seats), 0)
        FROM bookings bk
        WHERE bk.bus_id = b.id 
          AND bk.ride_status = 'Upcoming'
          AND (
            (bk.payment_status = 'Paid') 
            OR 
            (bk.payment_status = 'Pending' AND TIMESTAMPDIFF(MINUTE, bk.created_at, NOW()) < 10)
          )
      ) as available_seats
    FROM buses b
    WHERE b.route_from LIKE ? 
      AND b.route_to LIKE ? 
      AND b.departure_time >= NOW()
    HAVING available_seats > 0
    ORDER BY b.departure_time ASC
  ");

  $like_from = "%$from%";
  $like_to = "%$to%";
  $stmt->bind_param("ss", $like_from, $like_to);
  $stmt->execute();
  $buses = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Search Buses | Bus Booking System</title>
<style>
  body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background: #f4f7fa;
    margin: 0;
    padding: 0;
  }

  header {
    background: #009432;
    color: white;
    padding: 15px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 999;
  }

  header h1 {
    margin: 0;
    font-size: 22px;
  }

  nav a {
    color: #fff;
    text-decoration: none;
    margin-left: 20px;
    font-weight: bold;
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

  form {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: center;
    margin-bottom: 25px;
  }

  select, button {
    padding: 10px;
    border-radius: 5px;
    border: 1px solid #ccc;
    font-size: 1rem;
  }

  select {
    flex: 1 1 30%;
    min-width: 200px;
  }

  button {
    background: #009432;
    color: #fff;
    font-weight: bold;
    cursor: pointer;
    transition: background 0.3s ease;
    padding: 10px 20px;
  }

  button:hover {
    background: #007b2a;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
  }

  th, td {
    border: 1px solid #ddd;
    padding: 10px;
    text-align: center;
    word-wrap: break-word;
  }

  th {
    background: #009432;
    color: white;
    position: sticky;
    top: 0;
  }

  tr:nth-child(even) {
    background: #f9f9f9;
  }

  .no-results {
    text-align: center;
    color: #555;
    padding: 15px;
    font-size: 1.1rem;
  }

  /* Button styles - fixed to prevent overlapping */
  .btn {
    background: #009432;
    color: #fff;
    padding: 8px 15px;
    border-radius: 5px;
    text-decoration: none;
    font-weight: bold;
    transition: background 0.3s ease;
    display: inline-block;
    white-space: nowrap;
    min-width: 90px;
    box-sizing: border-box;
    font-size: 0.95rem;
  }

  .btn:hover {
    background: #007b2a;
    text-decoration: none;
  }

  .btn-disabled {
    background: #ccc;
    color: #666;
    cursor: not-allowed;
    padding: 8px 15px;
    border-radius: 5px;
    text-decoration: none;
    font-weight: bold;
    display: inline-block;
    white-space: nowrap;
    min-width: 90px;
    box-sizing: border-box;
    font-size: 0.95rem;
  }

  .btn-disabled:hover {
    background: #ccc;
  }

  /* Table column widths */
  th:nth-child(1), td:nth-child(1) { width: 5%; } /* # */
  th:nth-child(2), td:nth-child(2) { width: 20%; } /* Bus Name */
  th:nth-child(3), td:nth-child(3) { width: 25%; } /* Route */
  th:nth-child(4), td:nth-child(4) { width: 20%; } /* Departure */
  th:nth-child(5), td:nth-child(5) { width: 15%; } /* Available Seats */
  th:nth-child(6), td:nth-child(6) { width: 10%; } /* Price */
  th:nth-child(7), td:nth-child(7) { width: 10%; } /* Action */

  /* Seat status styles */
  .seat-info {
    font-weight: bold;
    padding: 5px 10px;
    border-radius: 4px;
    display: inline-block;
    font-size: 0.9rem;
  }

  .seat-available {
    color: #009432;
    background: rgba(0, 148, 50, 0.1);
    border: 1px solid rgba(0, 148, 50, 0.3);
  }

  .seat-low {
    color: #ff9800;
    background: rgba(255, 152, 0, 0.1);
    border: 1px solid rgba(255, 152, 0, 0.3);
  }

  .seat-sold {
    color: #f44336;
    background: rgba(244, 67, 54, 0.1);
    border: 1px solid rgba(244, 67, 54, 0.3);
  }

  footer {
    text-align: center;
    padding: 15px;
    background: #009432;
    color: white;
    margin-top: 40px;
  }

  /* Responsive design */
  @media (max-width: 768px) {
    .container {
      padding: 15px;
      margin: 20px 10px;
    }
    
    table {
      display: block;
      overflow-x: auto;
    }
    
    form {
      flex-direction: column;
    }
    
    select {
      width: 100%;
    }
    
    th:nth-child(1), td:nth-child(1) { width: 40px; }
    th:nth-child(2), td:nth-child(2) { width: 150px; }
    th:nth-child(3), td:nth-child(3) { width: 200px; }
    th:nth-child(4), td:nth-child(4) { width: 150px; }
    th:nth-child(5), td:nth-child(5) { width: 120px; }
    th:nth-child(6), td:nth-child(6) { width: 80px; }
    th:nth-child(7), td:nth-child(7) { width: 100px; }
    
    .btn, .btn-disabled {
      padding: 6px 10px;
      min-width: 70px;
      font-size: 0.85rem;
    }
  }
</style>
</head>
<body>

<header>
  <h1>Find Your Bus 🚍</h1>
  <nav>
    <a href="../index.php">Home</a>
    <a href="home.php">My Bookings</a>
    <a href="../logout.php">Logout</a>
  </nav>
</header>

<div class="container">
  <h2>Search Buses</h2>
  <form method="POST">
    <select name="from" required>
      <option value="" disabled selected>From (City)</option>
      <option value="Kathmandu">Kathmandu</option>
      <option value="Pokhara">Pokhara</option>
      <option value="Lumbini">Lumbini</option>
      <option value="Chitwan">Chitwan</option>
      <option value="Butwal">Butwal</option>
      <option value="Janakpur">Janakpur</option>
      <option value="Baglung">Baglung</option>
      <option value="Nepalgunj">Nepalgunj</option>
      <option value="Ramechhap">Ramechhap</option>
      <option value="Gorkha">Gorkha</option>
      <option value="Tansen">Tansen</option>
      <option value="Jomsom">Jomsom</option>
    </select>

    <select name="to" required>
      <option value="" disabled selected>To (City)</option>
      <option value="Kathmandu">Kathmandu</option>
      <option value="Pokhara">Pokhara</option>
      <option value="Lumbini">Lumbini</option>
      <option value="Chitwan">Chitwan</option>
      <option value="Butwal">Butwal</option>
      <option value="Janakpur">Janakpur</option>
      <option value="Baglung">Baglung</option>
      <option value="Nepalgunj">Nepalgunj</option>
      <option value="Ramechhap">Ramechhap</option>
      <option value="Gorkha">Gorkha</option>
      <option value="Tansen">Tansen</option>
      <option value="Jomsom">Jomsom</option>
    </select>

    <button type="submit">Search</button>
  </form>

  <?php if ($_SERVER["REQUEST_METHOD"] == "POST"): ?>
    <?php if ($buses && $buses->num_rows > 0): ?>
      <div style="overflow-x: auto;">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Bus Name</th>
              <th>Route</th>
              <th>Departure</th>
              <th>Available Seats</th>
              <th>Price</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php $i = 1; while ($bus = $buses->fetch_assoc()): 
              $available_seats = $bus['available_seats'];
              
              // Determine seat status class
              if ($available_seats <= 0) {
                $seat_class = 'seat-sold';
                $seat_status = 'Sold Out';
              } elseif ($available_seats <= 5) {
                $seat_class = 'seat-low';
                $seat_status = 'Low';
              } else {
                $seat_class = 'seat-available';
                $seat_status = 'Available';
              }
            ?>
              <tr>
                <td><?php echo $i++; ?></td>
                <td><?php echo htmlspecialchars($bus['bus_name']); ?></td>
                <td><?php echo htmlspecialchars($bus['route_from']) . " → " . htmlspecialchars($bus['route_to']); ?></td>
                <td><?php echo date("d M Y, h:i A", strtotime($bus['departure_time'])); ?></td>
                <td>
                  <span class="seat-info <?php echo $seat_class; ?>">
                    <?php echo $available_seats; ?> seats
                    <?php if ($available_seats > 0): ?>
                      <br><small>(<?php echo $seat_status; ?>)</small>
                    <?php endif; ?>
                  </span>
                </td>
                <td><strong>Rs. <?php echo number_format($bus['ticket_price']); ?></strong></td>
                <td>
                  <?php if ($available_seats > 0): ?>
                    <a href="book.php?bus_id=<?php echo $bus['id']; ?>" class="btn">Book Now</a>
                  <?php else: ?>
                    <span class="btn btn-disabled">Sold Out</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="no-results">❌ No buses found for your selected route or all buses are sold out.</p>
    <?php endif; ?>
  <?php else: ?>
    <p class="no-results">🔍 Enter your travel cities above to find available buses.</p>
  <?php endif; ?>
</div>

<footer>
  <p>© <?php echo date('Y'); ?> Bus Booking System | Passenger Panel</p>
</footer>

</body>
</html>