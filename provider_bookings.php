<?php
include 'db.php';
session_start();
if ($_SESSION['user']['role'] !== 'provider') {
    header("Location: login.php");
    exit;
}
$provider_id = $_SESSION['user']['id'];
$result = $conn->query("SELECT b.id, u.name AS customer, s.name AS service, b.date_requested, b.status 
                        FROM bookings b
                        JOIN users u ON b.customer_id = u.id
                        JOIN services s ON b.service_id = s.id
                        WHERE b.provider_id = $provider_id");
?>

<h2>Your Bookings</h2>
<table border="1">
    <tr><th>Customer</th><th>Service</th><th>Date</th><th>Status</th></tr>
    <?php while ($row = $result->fetch_assoc()): ?>
    <tr>
        <td><?= $row['customer'] ?></td>
        <td><?= $row['service'] ?></td>
        <td><?= $row['date_requested'] ?></td>
        <td><?= $row['status'] ?></td>
    </tr>
    <?php endwhile; ?>
</table>

<a href="provider_dashboard.php">Back</a>
