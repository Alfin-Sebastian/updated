<?php
include 'db.php';
session_start();
if ($_SESSION['user']['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}
$result = $conn->query("SELECT id, name, email, role FROM users");
?>

<h2>All Users</h2>
<table border="1">
    <tr><th>Name</th><th>Email</th><th>Role</th></tr>
    <?php while ($row = $result->fetch_assoc()): ?>
    <tr>
        <td><?= $row['name'] ?></td>
        <td><?= $row['email'] ?></td>
        <td><?= $row['role'] ?></td>
    </tr>
    <?php endwhile; ?>
</table>

<a href="admin_dashboard.php">Back</a>
