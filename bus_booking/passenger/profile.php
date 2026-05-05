<?php
session_start();
include('../includes/db.php');

// Redirect if not logged in or not a passenger
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'passenger') {
  header("Location: ../login.php");
  exit;
}

$user = $_SESSION['user'];

// Fetch user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$result = $stmt->get_result();
$userDetails = $result->fetch_assoc();

// Update user details
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = $_POST['name'];
  $email = $_POST['email'];
  $phone = $_POST['phone'];
  $password = $_POST['password'];

  $errors = [];

  if (empty($name)) {
    $errors[] = "Name is required.";
  }

  if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Valid email is required.";
  }

  if (!empty($phone) && !preg_match("/^\+?[0-9]*$/", $phone)) {
    $errors[] = "Please enter a valid phone number.";
  }

  if (!empty($password) && strlen($password) < 6) {
    $errors[] = "Password must be at least 6 characters.";
  }

  if (empty($errors)) {
    $passwordHash = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : $userDetails['password'];

    // Update user info, including phone number
    $updateStmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, password = ? WHERE id = ?");
    $updateStmt->bind_param("ssssi", $name, $email, $phone, $passwordHash, $user['id']);
    $updateStmt->execute();

    $_SESSION['user']['name'] = $name; // Update session with new name

    echo "<div style='background:#d4edda; padding:10px; text-align:center;'>Profile updated successfully!</div>";
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Profile | Bus Booking System</title>
  <style>
    body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f7fa; margin: 0; padding: 0; }
    header { background: #009432; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 999; }
    header h1 { margin: 0; font-size: 22px; }
    nav a { color: white; text-decoration: none; margin-left: 20px; font-weight: bold; }
    nav a:hover { text-decoration: underline; }
    .container { max-width: 1000px; margin: 40px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
    h2 { color: #009432; border-bottom: 2px solid #009432; padding-bottom: 8px; margin-bottom: 20px; }
    form input, form button { padding: 10px; margin-bottom: 15px; width: 100%; }
    form button { background-color: #009432; color: white; border: none; cursor: pointer; font-weight: bold; }
    footer { text-align: center; padding: 15px; background: #009432; color: white; margin-top: 40px; }
  </style>
</head>
<body>
  <header>
    <h1>Edit Your Profile</h1>
    <nav>
      <a href="../index.php">Home</a>
      <a href="search.php">Search Buses</a>
      <a href="profile.php">Profile</a>
      <a href="../logout.php">Logout</a>
    </nav>
  </header>

  <div class="container">
    <h2>Your Profile</h2>
    <?php if (!empty($errors)): ?>
      <div style="background:#f8d7da; color:#721c24; padding:10px;">
        <?php foreach ($errors as $error): ?>
          <p><?php echo $error; ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST">
      <label for="name">Full Name</label>
      <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($userDetails['name']); ?>" required>

      <label for="email">Email Address</label>
      <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($userDetails['email']); ?>" required>

      <label for="phone">Phone Number</label>
      <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($userDetails['phone']); ?>" required>

      <label for="password">New Password (Leave empty to keep current password)</label>
      <input type="password" name="password" id="password" placeholder="Enter new password if you want to change it">

      <button type="submit">Update Profile</button>
    </form>
  </div>

  

</body>
</html>
