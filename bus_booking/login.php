<?php
session_start();
include('includes/db.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $query = $conn->prepare("SELECT * FROM users WHERE email=?");
    $query->bind_param("s", $email);
    $query->execute();
    $result = $query->get_result();

    if ($user = $result->fetch_assoc()) {
        if ($password === $user['password']) {
            $_SESSION['user'] = $user;
            header("Location: " . ($user['role'] == 'admin' ? "admin/dashboard.php" : "passenger/home.php"));
            exit;
        }
    }
    $error = "Invalid email or password!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | Lane - Premium Bus Booking</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
  --primary: #009432;
  --primary-dark: #007a2a;
  --secondary: #1a237e;
  --accent: #ff6d00;
  --dark: #121212;
  --light: #f8f9fa;
  --gray: #6c757d;
  --card-bg: rgba(255, 255, 255, 0.95);
  --shadow: 0 10px 30px rgba(0,0,0,0.1);
}

* { box-sizing: border-box; margin:0; padding:0; }
html, body { width:100%; height:100%; font-family: 'Poppins', sans-serif; background-color: var(--light); overflow: hidden; }

.main-content {
  width:100%; height:100vh;
  display:flex; justify-content:center; align-items:center;
  background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), 
              url('https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?ixlib=rb-4.0.3&auto=format&fit=crop&w=2069&q=80') no-repeat center center;
  background-size: cover;
  position: relative;
}

.main-content::before {
  content:''; position:absolute; top:0; left:0; width:100%; height:100%;
  background: linear-gradient(135deg, rgba(0,148,50,0.3) 0%, rgba(26,35,126,0.3) 100%);
  z-index:0;
}

.login-container {
  max-width:420px; width:100%; background: var(--card-bg);
  backdrop-filter: blur(10px); border-radius:16px;
  padding:35px 30px; box-shadow: var(--shadow);
  border:1px solid rgba(255,255,255,0.2); position: relative; z-index:1;
}

.login-header { text-align:center; margin-bottom:25px; }
.login-header i { font-size:2.5rem; color:var(--primary); margin-bottom:12px; display:block; }
.login-header h2 { font-size:1.8rem; color:var(--dark); font-weight:700; margin-bottom:8px; }
.login-header p { color: var(--gray); font-size:0.95rem; }

.form-group { margin-bottom:18px; position:relative; }
.form-group label { display:block; margin-bottom:6px; font-weight:500; color:var(--dark); font-size:0.95rem; }
.form-control {
  width:100%; padding:12px 12px 12px 40px;
  border:1px solid #ddd; border-radius:8px; font-size:0.95rem;
  background-color: rgba(255,255,255,0.9);
}
.form-control:focus { outline:none; border-color: var(--primary); box-shadow:0 0 0 3px rgba(0,148,50,0.1); }
.input-icon { position:absolute; left:12px; top:38px; color:var(--gray); font-size:1.1rem; }

.show-password { display:flex; align-items:center; margin-top:8px; font-size:0.85rem; }
.show-password input { width:auto; margin-right:6px; }

.btn-login {
  width:100%; padding:13px; background:var(--primary); color:#fff;
  border:none; border-radius:8px; font-size:1rem; font-weight:600;
  cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; margin-top:8px;
  transition: background 0.3s;
}
.btn-login:hover { background: var(--primary-dark); }

.btn-back {
  width:100%; padding:13px; background:#6c757d; color:#fff;
  border:none; border-radius:8px; font-size:1rem; font-weight:600;
  cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; margin-top:8px;
  transition: background 0.3s; text-decoration:none;
}
.btn-back:hover { background: #5a6268; }

.error-message {
  background: rgba(220,53,69,0.1); border:1px solid rgba(220,53,69,0.3);
  border-radius:8px; padding:12px; margin-bottom:18px; text-align:center; color:#dc3545;
  font-weight:500; font-size:0.9rem;
}
.error-message i { margin-right:6px; }

.login-footer { text-align:center; margin-top:20px; padding-top:18px; border-top:1px solid rgba(0,0,0,0.1); }
.login-footer p { color:var(--gray); font-size:0.9rem; }
.login-footer a { color:var(--primary); text-decoration:none; font-weight:600; }
.login-footer a:hover { color:var(--primary-dark); }

.brand-header { position:absolute; top:20px; left:20px; display:flex; align-items:center; gap:8px; color:white; font-weight:600; font-size:1.2rem; z-index:2; }
.brand-header i { font-size:1.5rem; }

.page-footer { position:absolute; bottom:15px; width:100%; text-align:center; color: rgba(255,255,255,0.7); font-size:0.85rem; z-index:2; }

/* Responsive */
@media (max-width:576px) {
  .login-container { padding:25px 20px; max-width:350px; }
  .login-header h2 { font-size:1.6rem; }
  .main-content { padding:15px; }
  .brand-header { font-size:1.1rem; top:15px; left:15px; }
}

@media (max-height:700px) {
  .login-container { transform: scale(0.9); }
}
</style>
</head>
<body>

<div class="brand-header">
  <i class="fas fa-bus"></i>
  <span>Lane</span>
</div>

<div class="main-content">
  <div class="login-container">
    <div class="login-header">
      <i class="fas fa-user-circle"></i>
      <h2>Welcome Back</h2>
      <p>Sign in to continue your journey</p>
    </div>

    <?php if(isset($error)): ?>
      <div class="error-message">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo $error; ?>
      </div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label for="email">Email Address</label>
        <i class="fas fa-envelope input-icon"></i>
        <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" required>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <i class="fas fa-lock input-icon"></i>
        <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
        <div class="show-password">
          <input type="checkbox" id="togglePassword" onclick="togglePass()">
          <label for="togglePassword">Show Password</label>
        </div>
      </div>

      <button type="submit" class="btn-login">
        <i class="fas fa-sign-in-alt"></i>
        Login to Account
      </button>
      
      <a href="index.php" class="btn-back">
        <i class="fas fa-arrow-left"></i> Back to Home
      </a>
    </form>

    <div class="login-footer">
      <p>Don't have an account? <a href="register.php">Create one here</a></p>
    </div>
  </div>
</div>


<script>
function togglePass() {
  const passField = document.getElementById('password');
  passField.type = passField.type === 'password' ? 'text' : 'password';
}

// Auto-focus on email field
document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('email').focus();
});

// Prevent resubmission on refresh
if(window.history.replaceState){ window.history.replaceState(null, null, window.location.href); }
</script>

</body>
</html>