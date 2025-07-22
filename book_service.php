<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'customer') {
    header("Location: login.php");
    exit;
}

$error = '';
$success = '';
$providers = [];
$services = [];

// Get all providers who offer at least one service
$provider_query = $conn->query("
    SELECT DISTINCT u.id, u.name 
    FROM users u 
    JOIN provider_services ps ON ps.provider_id = u.id 
    WHERE u.role = 'provider'
");
if ($provider_query) {
    $providers = $provider_query->fetch_all(MYSQLI_ASSOC);
}

// Get all services
$service_query = $conn->query("
    SELECT s.id, s.name, s.category_id, sc.name as category_name 
    FROM services s 
    JOIN service_categories sc ON s.category_id = sc.id
");
if ($service_query) {
    $services = $service_query->fetch_all(MYSQLI_ASSOC);
}

// Booking handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $provider_id = intval($_POST['provider_id']);
    $service_id = intval($_POST['service_id']);
    $date = $_POST['date'];
    $customer_id = $_SESSION['user']['id'];
    $address = $_SESSION['user']['address'] ?? '';

    if (empty($provider_id) || empty($service_id) || empty($date)) {
        $error = "Please fill all required fields";
    } else {
        // Check if this provider offers this service
        $check = $conn->prepare("SELECT price FROM provider_services WHERE provider_id = ? AND service_id = ?");
        $check->bind_param("ii", $provider_id, $service_id);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows === 0) {
            $error = "Selected provider does not offer this service.";
        } else {
            $row = $result->fetch_assoc();
            $price = $row['price'];

            $stmt = $conn->prepare("INSERT INTO bookings (user_id, provider_id, service_id, booking_date, address, amount, status, payment_type) VALUES (?, ?, ?, ?, ?, ?, 'pending', 'cash')");
            $stmt->bind_param("iiisds", $customer_id, $provider_id, $service_id, $date, $address, $price);

            if ($stmt->execute()) {
                $success = "Booking submitted successfully!";
            } else {
                $error = "Booking failed: " . $conn->error;
            }
        }
    }
}

// CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book Service | UrbanServe</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 30px auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 30px;
        }
        h2 { color: #2d3748; border-bottom: 2px solid #f76d2b; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        label { font-weight: 500; display: block; margin-bottom: 6px; }
        input, select, textarea {
            width: 100%; padding: 12px; font-size: 16px; border-radius: 6px;
            border: 1px solid #e2e8f0; transition: all 0.3s;
        }
        input:focus, select:focus, textarea:focus {
            border-color: #f76d2b; box-shadow: 0 0 0 3px rgba(247,109,43,0.2);
        }
        .btn {
            background-color: #f76d2b; color: #fff; padding: 12px 24px;
            border: none; border-radius: 6px; cursor: pointer; font-size: 16px;
        }
        .btn:hover { background-color: #e05b1a; }
        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .alert-success { background: #e6fffa; color: #2c7a7b; border-left: 5px solid #2c7a7b; }
        .alert-error { background: #ffe5e5; color: #c53030; border-left: 5px solid #c53030; }
        .back-link { color: #f76d2b; text-decoration: none; margin-top: 20px; display: inline-block; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Book a Service</h2>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php elseif ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="form-group">
                <label for="provider_id">Service Provider *</label>
                <select name="provider_id" id="provider_id" required>
                    <option value="">Select Provider</option>
                    <?php foreach ($providers as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= isset($_POST['provider_id']) && $_POST['provider_id'] == $p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="service_id">Service *</label>
                <select name="service_id" id="service_id" required>
                    <option value="">Select Service</option>
                    <?php foreach ($services as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= isset($_POST['service_id']) && $_POST['service_id'] == $s['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['category_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="date">Date & Time *</label>
                <input type="datetime-local" id="date" name="date" required 
                       min="<?= date('Y-m-d\TH:i') ?>"
                       value="<?= isset($_POST['date']) ? htmlspecialchars($_POST['date']) : '' ?>">
            </div>

            <div class="form-group">
                <label>Your Address</label>
                <p><?= htmlspecialchars($_SESSION['user']['address'] ?? 'Not specified') ?></p>
                <small><a href="edit_profile.php">Update your profile address</a></small>
            </div>

            <button type="submit" class="btn">Confirm Booking</button>
        </form>

        <a href="services.php" class="back-link">← Back </a>
    </div>
</body>
</html>
