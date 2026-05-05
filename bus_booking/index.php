<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Include database connection
include('includes/db.php');

// Contact form handling
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['contact_submit'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $message = $_POST['message'];
    $success = true; 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Lane - Premium Bus Booking | Nepal's Smart Travel Platform</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --primary: #009432;
      --primary-dark: #007a2a;
      --primary-light: #00b347;
      --secondary: #1a237e;
      --accent: #ff6d00;
      --dark: #121212;
      --light: #f8f9fa;
      --gray: #6c757d;
      --card-bg: rgba(255, 255, 255, 0.95);
      --glass-bg: rgba(255, 255, 255, 0.15);
      --glass-border: rgba(255, 255, 255, 0.2);
      --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      --shadow-hover: 0 15px 40px rgba(0, 0, 0, 0.2);
      --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    * { 
      box-sizing: border-box; 
      margin: 0;
      padding: 0;
    }

    html, body {
      width: 100%;
      height: 100%;
      overflow-x: hidden;
      font-family: 'Poppins', 'Segoe UI', Arial, sans-serif;
      scroll-behavior: smooth;
      background-color: var(--light);
      color: var(--dark);
      line-height: 1.6;
    }

    ::-webkit-scrollbar {
      width: 8px;
    }

    ::-webkit-scrollbar-track {
      background: #000000ff;
    }

    ::-webkit-scrollbar-thumb {
      background: grey;
      border-radius: 10px;
    }

    header {
      position: fixed;
      top: 0;
      left: 0;
      width: 99.5%;
      background:#009432;
      backdrop-filter: blur(10px);
      color: #fff;
      padding: 18px 40px;
      z-index: 1000;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 3px 15px rgba(0, 0, 0, 0.1);
      transition: var(--transition);
    }

    header.scrolled {
      background: rgba(0, 122, 42, 0.98);
      padding: 12px 40px;
    }

    .logo {
      display: flex;
      align-items: center;
      font-weight: 700;
      font-size: 1.5rem;
      gap: 10px;
    }

    .logo-img {
      height: 40px;
      width: auto;
    }

    nav {
      display: flex;
      gap: 30px;
      align-items: center;
    }

    nav a {
      color: #fff;
      text-decoration: none;
      font-weight: 500;
      transition: var(--transition);
      padding: 8px 0;
    }

    .auth-buttons {
      display: flex;
      gap: 15px;
      margin-left: 20px;
    }

    .auth-buttons a {
      padding: 8px 20px;
      border-radius: 50px;
      font-weight: 600;
      transition: var(--transition);
    }

    .auth-buttons a:first-child {
      background: #b4fa04ff;
      border: 1px solid rgba(255, 255, 255, 0.3);
      color: black;
    }

    .auth-buttons a:last-child {
      background: #ccff00ff;
      color: black;
      border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .auth-buttons a:hover {
      transform: translateY(-3px);
    }

    .user-menu {
      display: flex;
      align-items: center;
      gap: 20px;
    }

    .welcome-badge {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 6px 12px;
      background: rgba(255, 255, 255, 0.15);
      border-radius: 20px;
      font-weight: 500;
      font-size: 0.9rem;
    }

    .welcome-badge i {
      color: #b4fa04;
    }

    #menu-toggle {
      display: none;
      font-size: 1.8rem;
      cursor: pointer;
    }

    @media (max-width: 992px) {
      nav {
        position: fixed;
        top: 70px;
        right: 0;
        width: 280px;
        height: calc(100vh - 70px);
        background: rgba(0, 148, 50, 0.98);
        flex-direction: column;
        padding: 40px 30px;
        transform: translateX(100%);
        transition: transform 0.4s ease;
      }
      
      nav.show {
        transform: translateX(0);
      }
      
      #menu-toggle {
        display: block;
      }
      
      .auth-buttons, .user-menu {
        flex-direction: column;
        width: 100%;
        gap: 10px;
      }
      
      .logo {
        font-size: 1.3rem;
      }
    }

    .hero {
      width: 100%;
      min-height: 100vh;
      background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), 
                  url('https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2069&q=80') no-repeat center center;
      background-size: cover;
      color: #fff;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      text-align: center;
      padding: 0 20px;
    }

    .hero-content {
      max-width: 900px;
      z-index: 1;
      margin-top: 60px;
    }

    .hero h2 {
      font-size: 3.5rem;
      margin-bottom: 15px;
      font-weight: 700;
    }

    .hero p {
      font-size: 1.4rem;
      margin-bottom: 30px;
      opacity: 0.9;
    }

    .search-box {
      background: var(--glass-bg);
      backdrop-filter: blur(10px);
      border: 1px solid var(--glass-border);
      padding: 30px;
      border-radius: 15px;
      max-width: 800px;
      width: 90%;
      margin: 20px auto 0;
      box-shadow: var(--shadow);
      
    }

    .search-form {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 15px;
    }

    .form-group {
      display: flex;
      flex-direction: column;
    }

    .form-group label {
      margin-bottom: 8px;
      font-weight: 500;
      text-align: left;
    }

    .search-box input, .search-box select {
      padding: 14px 15px;
      border: none;
      border-radius: 10px;
      background: rgba(255, 255, 255, 0.9);
      font-size: 1rem;
    }

    .search-box button {
      background: var(--accent);
      color: #fff;
      border: none;
      padding: 14px 25px;
      border-radius: 10px;
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .search-box button:hover {
      background: #ff8500;
      transform: translateY(-3px);
    }

    section {
      width: 100%;
      padding: 100px 20px;
    }

    .section-title {
      text-align: center;
      margin-bottom: 60px;
    }

    .section-title h2 {
      font-size: 2.5rem;
      color: var(--dark);
      font-weight: 700;
    }

    .about {
      background: #fff;
      border-radius: 20px;
      padding: 50px;
      box-shadow: var(--shadow);
      max-width: 1200px;
      margin: auto;
      text-align: center;
    }

    .about p {
      font-size: 1.2rem;
      max-width: 800px;
      margin: 0 auto 40px;
      color: var(--gray);
    }

    .features {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 30px;
      margin-top: 40px;
    }

    .feature {
      background: var(--card-bg);
      border-radius: 15px;
      padding: 35px 25px;
      text-align: center;
      box-shadow: var(--shadow);
      transition: var(--transition);
    }

    .feature:hover {
      transform: translateY(-10px);
      box-shadow: var(--shadow-hover);
    }

    .feature i {
      font-size: 3rem;
      color: var(--primary);
      margin-bottom: 20px;
    }

    .feature h3 {
      font-size: 1.4rem;
      margin-bottom: 15px;
    }

    .contact {
  background: #fff;
  border-radius: 20px;
  padding: 40px;
  box-shadow: var(--shadow);
  max-width: 1000px;
  margin: auto;
  position: relative;
  border: 1px solid rgba(0, 148, 50, 0.1);
}

.contact::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 4px;
  background: linear-gradient(90deg, var(--primary), var(--accent), var(--secondary));
  border-radius: 20px 20px 0 0;
}

.contact p {
  text-align: center;
  margin-bottom: 30px;
  font-size: 1.1rem;
  color: var(--gray);
  max-width: 600px;
  margin-left: auto;
  margin-right: auto;
}

.contact-container {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 30px;
}

.contact-form input, .contact-form textarea {
  width: 100%;
  padding: 12px 15px;
  margin-bottom: 15px;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  font-size: 0.95rem;
  transition: var(--transition);
  background: #fafafa;
}

.contact-form input:focus, .contact-form textarea:focus {
  outline: none;
  border-color: var(--primary);
  background: #fff;
  box-shadow: 0 0 0 3px rgba(0, 148, 50, 0.1);
  transform: translateY(-2px);
}

.contact-form textarea {
  resize: vertical;
  min-height: 120px;
}

.contact-form button {
  background: linear-gradient(135deg, var(--primary), var(--primary-dark));
  color: #fff;
  border: none;
  padding: 12px 25px;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  transition: var(--transition);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
}

.contact-form button:hover {
  background: linear-gradient(135deg, var(--primary-dark), var(--primary));
  transform: translateY(-3px);
  box-shadow: 0 8px 20px rgba(0, 148, 50, 0.3);
}

.contact-info {
  background: linear-gradient(135deg, #f8f9fa, #ffffff);
  padding: 25px;
  border-radius: 12px;
  border: 1px solid rgba(0, 148, 50, 0.1);
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
}

.contact-info h3 {
  margin-bottom: 20px;
  color: var(--dark);
  font-size: 1.3rem;
  text-align: center;
}

.contact-details-grid {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.contact-detail {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.8);
  transition: var(--transition);
  border: 1px solid transparent;
}

.contact-detail:hover {
  background: #fff;
  transform: translateY(-2px);
  border-color: rgba(0, 148, 50, 0.2);
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
}

.contact-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  background: linear-gradient(135deg, var(--primary), var(--primary-light));
  color: white;
  border-radius: 50%;
  flex-shrink: 0;
  font-size: 0.9rem;
}

.contact-text h4 {
  margin-bottom: 4px;
  color: var(--dark);
  font-size: 0.95rem;
  font-weight: 600;
}

.contact-text p {
  margin: 0;
  color: var(--gray);
  text-align: left;
  font-size: 0.9rem;
  line-height: 1.4;
}

.success-message {
  background: linear-gradient(135deg, rgba(0, 148, 50, 0.05), rgba(0, 148, 50, 0.1));
  border: 1px solid rgba(0, 148, 50, 0.2);
  border-radius: 12px;
  padding: 25px;
  margin-bottom: 25px;
  text-align: center;
  backdrop-filter: blur(10px);
}

.success-message i {
  color: var(--primary);
  font-size: 2.5rem;
  margin-bottom: 15px;
}

.success-message h3 {
  color: var(--primary);
  margin-bottom: 10px;
}

@media (max-width: 768px) {
  .contact {
    padding: 25px 20px;
  }
  
  .contact-container {
    grid-template-columns: 1fr;
    gap: 25px;
  }
  
  .contact-info {
    padding: 20px;
  }
  
  .contact-detail {
    padding: 10px;
  }
}
    /* Simple but nice footer */
    footer {
      background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
      color: #fff;
      padding: 60px 20px 20px;
    }

    .footer-container {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 40px;
      max-width: 1200px;
      margin: 0 auto 40px;
    }

    .footer-section h3 {
      margin-bottom: 20px;
      font-size: 1.3rem;
      position: relative;
      display: inline-block;
    }

    .footer-section h3::after {
      content: '';
      position: absolute;
      bottom: -8px;
      left: 0;
      width: 40px;
      height: 3px;
      background: var(--accent);
    }

    .social-icons {
      display: flex;
      gap: 15px;
      margin-top: 20px;
    }

    .social-icons a {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 40px;
      height: 40px;
      background: rgba(255, 255, 255, 0.1);
      color: #fff;
      border-radius: 50%;
      text-decoration: none;
      transition: var(--transition);
    }

    .social-icons a:hover {
      background: #fff;
      color: var(--primary);
      transform: translateY(-3px);
    }

    .footer-section p {
      margin: 10px 0;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .footer-bottom {
      border-top: 1px solid rgba(255, 255, 255, 0.2);
      padding-top: 20px;
      text-align: center;
      font-size: 0.9rem;
      opacity: 0.8;
    }

    .success-message {
      background: rgba(0, 148, 50, 0.1);
      border: 1px solid rgba(0, 148, 50, 0.3);
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 30px;
      text-align: center;
    }

    @media (max-width: 768px) {
      .hero h2 {
        font-size: 2.5rem;
      }
      
      .hero p {
        font-size: 1.2rem;
      }
      
      .search-form {
        grid-template-columns: 1fr;
      }
      
      .contact-container {
        grid-template-columns: 1fr;
      }
      
      .about, .contact {
        padding: 30px 20px;
      }
      
      .section-title h2 {
        font-size: 2rem;
      }
    }
  </style>
</head>
<body>

<header id="mainHeader">
  <div class="logo">
    <img src="https://static.vecteezy.com/system/resources/previews/055/845/288/non_2x/bright-green-bus-illustration-on-a-transparent-background-free-png.png" alt="Lane Bus Logo" class="logo-img">
    <span>Lane - Premium Bus Booking</span>
  </div>
  <nav id="navbar">
    <a href="#home">Home</a>
    <a href="#about">About</a>
    <a href="#contact">Contact</a>
    <?php if(isset($_SESSION['user'])): ?>
        <div class="user-menu">
            <div class="welcome-badge">
                <i class="fas fa-user-circle"></i>
                <span>Hi, <?php echo htmlspecialchars(explode(' ', $_SESSION['user']['name'])[0]); ?>!</span>
            </div>
            <?php if($_SESSION['user']['role'] == 'passenger'): ?>
                <a href="passenger/home.php">Dashboard</a>
            <?php elseif($_SESSION['user']['role'] == 'admin'): ?>
                <a href="admin/dashboard.php">Admin Panel</a>
            <?php endif; ?>
            <a href="logout.php">Logout</a>
        </div>
    <?php else: ?>
        <div class="auth-buttons">
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
        </div>
    <?php endif; ?>
  </nav>
  <div id="menu-toggle" onclick="toggleMenu()">☰</div>
</header>

<section id="home" class="hero">
  <div class="hero-content">
    <h2>Experience Premium Bus Travel in Nepal</h2>
    <p>•Luxury • Comfort • Reliability </p>
  </div>
  <div class="search-box">
    <form class="search-form" method="POST" action="passenger/search.php">
      <div class="form-group">
        <label for="from">From</label>
        <select name="from" id="from" required>
          <option value="" disabled>Select departure</option>
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
      </div>
      
      <div class="form-group">
        <label for="to">To</label>
        <select name="to" id="to" required>
          <option value="" disabled>Select destination</option>
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
      </div>
      
      <div class="form-group">
        <label for="date">Travel Date</label>
        <input type="date" id="date" name="date" required>
      </div>
      
      <button type="submit">
        <i class="fas fa-search"></i>
        Search Buses
      </button>
    </form>
  </div>
</section>

<section id="about">
  <div class="about">
    <div class="section-title">
      <h2>Why Choose Our Service?</h2>
    </div>
    <p>Welcome to <strong>Lane - Premium Bus Booking</strong> — Nepal's most advanced digital platform connecting passengers with premium bus operators. We've redefined bus travel with cutting-edge technology and exceptional service.</p>
    <div class="features">
      <div class="feature">
        <i class="fas fa-bolt"></i>
        <h3>Real-Time Booking</h3>
        <p>Instantly view available seats, bus amenities, and confirm your journey in seconds with our lightning-fast platform.</p>
      </div>
      <div class="feature">
        <i class="fas fa-shield-alt"></i>
        <h3>Secure Payments</h3>
        <p>Bank-grade encryption protects your payments via eSewa, Khalti, credit cards, and other popular methods.</p>
      </div>
      <div class="feature">
        <i class="fas fa-route"></i>
        <h3>Smart Scheduling <small>(Coming Soon)</small></h3>
        <p>Our AI-powered route optimization feature will soon provide real-time traffic updates and accurate ETAs to make your journeys smoother than ever.</p>
      </div>
      <div class="feature">
        <i class="fas fa-headset"></i>
        <h3>24/7 Customer Support</h3>
        <p>Our dedicated support team is always ready to assist you with bookings, cancellations, or any travel-related queries.</p>
      </div>
      <div class="feature">
        <i class="fas fa-check-circle"></i>
        <h3>Verified & Trusted Operators</h3>
        <p>All buses on our platform are carefully vetted to ensure safety, punctuality, and comfort for a worry-free travel experience.</p>
      </div>
      <div class="feature">
        <i class="fas fa-chart-line"></i>
        <h3>Advanced Dashboard</h3>
        <p>Comprehensive analytics and management tools for admins to optimize operations and customer experience.</p>
      </div>
    </div>
  </div>
</section>

<section id="contact">
  <div class="contact">
    <div class="section-title">
      <h2>Get In Touch</h2>
    </div>
    <p>Have questions, feedback, or need assistance? Our dedicated support team is here to help you 24/7.</p>

    <?php if(isset($success)): ?>
      <div class="success-message">
        <i class="fas fa-check-circle"></i>
        <h3>Message Sent Successfully!</h3>
        <p>Thank you, <?php echo htmlspecialchars($name); ?>! We've received your message and will reply within 24 hours.</p>
      </div>
    <?php else: ?>
      <div class="contact-container">
        <div class="contact-form">
          <form method="POST">
            <input type="text" name="name" placeholder="Your Full Name" required>
            <input type="email" name="email" placeholder="Your Email Address" required>
            <textarea name="message" rows="4" placeholder="How can we help you?" required></textarea>
            <button type="submit" name="contact_submit">
              <i class="fas fa-paper-plane"></i>
              Send Message
            </button>
          </form>
        </div>
        
        <div class="contact-info">
          <h3>Our Office</h3>
          <div class="contact-details-grid">
            <div class="contact-detail">
              <div class="contact-icon">
                <i class="fas fa-map-marker-alt"></i>
              </div>
              <div class="contact-text">
                <h4>Address</h4>
                <p>Butwal-10, Kalikanagar, Rupandehi, Nepal</p>
              </div>
            </div>
            <div class="contact-detail">
              <div class="contact-icon">
                <i class="fas fa-phone-alt"></i>
              </div>
              <div class="contact-text">
                <h4>Phone</h4>
                <p>+977-9823644535</p>
              </div>
            </div>
            <div class="contact-detail">
              <div class="contact-icon">
                <i class="fas fa-envelope"></i>
              </div>
              <div class="contact-text">
                <h4>Email</h4>
                <p>ayushkharel301@gmail.com</p>
              </div>
            </div>
            <div class="contact-detail">
              <div class="contact-icon">
                <i class="fas fa-clock"></i>
              </div>
              <div class="contact-text">
                <h4>Business Hours</h4>
                <p>7:00 AM - 9:00 PM (Everyday)</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<footer>
  <div class="footer-container">
    <div class="footer-section">
      <h3>Connect With Us</h3>
      <p>Follow our social media channels for updates, promotions, and travel tips.</p>
      <div class="social-icons">
        <a href="https://facebook.com" target="_blank"><i class="fab fa-facebook-f"></i></a>
        <a href="https://instagram.com" target="_blank"><i class="fab fa-instagram"></i></a>
        <a href="https://twitter.com" target="_blank"><i class="fab fa-twitter"></i></a>
        <a href="https://linkedin.com" target="_blank"><i class="fab fa-linkedin-in"></i></a>
      </div>
    </div>

    <div class="footer-section">
      <h3>Contact Information</h3>
      <p><i class="fas fa-phone-alt"></i> +977-9823644535</p>
      <p><i class="fas fa-envelope"></i> ayushkharel301@gmail.com</p>
      <p><i class="fas fa-map-marker-alt"></i> Butwal-10, Kalikanagar, Rupandehi, Nepal</p>
    </div>

    <div class="footer-section">
      <h3>Quick Links</h3>
      <p><a href="#home" style="color: white; text-decoration: none;">Home</a></p>
      <p><a href="#about" style="color: white; text-decoration: none;">About Us</a></p>
      <p><a href="#contact" style="color: white; text-decoration: none;">Contact</a></p>
      <p><a href="login.php" style="color: white; text-decoration: none;">Login</a></p>
    </div>
  </div>

  <div class="footer-bottom">
    <p>© <?php echo date('Y'); ?> Lane - Premium Bus Booking System | Designed & Developed by <strong>Ayush</strong></p>
  </div>
</footer>

<script>
  window.addEventListener('scroll', function() {
    const header = document.getElementById('mainHeader');
    if (window.scrollY > 50) {
      header.classList.add('scrolled');
    } else {
      header.classList.remove('scrolled');
    }
  });

  function toggleMenu() {
    document.getElementById('navbar').classList.toggle('show');
  }

  document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('date').setAttribute('min', today);
  });
</script>

</body>
</html>