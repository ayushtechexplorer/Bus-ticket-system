<?php
session_start();
include('../includes/db.php');

// Restrict access
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'passenger') {
    header("Location: ../login.php");
    exit;
}

// Get booking_id and bus_id from URL
$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$bus_id = isset($_GET['bus_id']) ? intval($_GET['bus_id']) : 0;
$user_id = $_SESSION['user']['id'];

// Validate input
if ($booking_id <= 0 || $bus_id <= 0) {
    $_SESSION['error'] = "Invalid booking details";
    header("Location: home.php");
    exit;
}

// Check if booking belongs to current user and is still pending
$check_query = $conn->prepare("
    SELECT b.*, bus.ticket_price 
    FROM bookings b
    JOIN buses bus ON b.bus_id = bus.id
    WHERE b.booking_id = ? 
    AND b.user_id = ? 
    AND b.bus_id = ?
    AND b.payment_status = 'Pending'
    AND b.ride_status = 'Upcoming'
");

$check_query->bind_param("iii", $booking_id, $user_id, $bus_id);
$check_query->execute();
$booking = $check_query->get_result()->fetch_assoc();
$check_query->close();

if (!$booking) {
    $_SESSION['error'] = "Booking not found or already processed";
    header("Location: home.php");
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // 1. Release seats back to bus
    $release_seats = $conn->prepare("
        UPDATE buses 
        SET available_seats = available_seats + ? 
        WHERE id = ?
    ");
    $release_seats->bind_param("ii", $booking['total_seats'], $bus_id);
    $release_seats->execute();
    $release_seats->close();

    // 2. Delete the pending booking
    $delete_booking = $conn->prepare("
        DELETE FROM bookings 
        WHERE booking_id = ?
    ");
    $delete_booking->bind_param("i", $booking_id);
    $delete_booking->execute();
    $delete_booking->close();

    // 3. Clear session data
    if (isset($_SESSION['recently_booked_seats'])) {
        unset($_SESSION['recently_booked_seats']);
    }
    if (isset($_SESSION['recent_booking_bus_id'])) {
        unset($_SESSION['recent_booking_bus_id']);
    }
    if (isset($_SESSION['booking_time'])) {
        unset($_SESSION['booking_time']);
    }

    // Commit transaction
    $conn->commit();

    $_SESSION['success'] = "Booking cancelled successfully. Seats have been released.";
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    $_SESSION['error'] = "Failed to cancel booking. Please try again.";
}

// Redirect to dashboard
header("Location: home.php");
exit;
?>
