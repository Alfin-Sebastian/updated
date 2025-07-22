
<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['user'];
$role = $user['role'];
$user_id = $user['id'];
$message = "";
$message_type = "";

// Handle update submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone']);
    $password = trim($_POST['password']);
    $address  = ($role === 'customer') ? trim($_POST['address']) : null;
    $city     = trim($_POST['city']);
    $state    = trim($_POST['state']);
    $pincode  = trim($_POST['pincode']);

    if (!empty($password)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET name=?, email=?, phone=?, address=?, city=?, state=?, pincode=?, password=? WHERE id=?");
        $stmt->bind_param("ssssssssi", $name, $email, $phone, $address, $city, $state, $pincode, $hashed, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET name=?, email=?, phone=?, address=?, city=?, state=?, pincode=? WHERE id=?");
        $stmt->bind_param("sssssssi", $name, $email, $phone, $address, $city, $state, $pincode, $user_id);
    }

    if ($stmt->execute()) {
        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['email'] = $email;
        $_SESSION['user']['phone'] = $phone;
        $_SESSION['user']['address'] = $address;
        $message = "Profile updated successfully!";
        $message_type = "success";
    } else {
        $message = "Error updating profile: " . $conn->error;
        $message_type = "error";
    }

    // If provider, update provider details
    if ($role === 'provider') {
        $experience = trim($_POST['experience']);
        $location   = trim($_POST['location']);
        $bio        = trim($_POST['bio']);
        $services   = $_POST['services'] ?? [];

        $check = $conn->prepare("SELECT id FROM providers WHERE user_id=?");
        $check->bind_param("i", $user_id);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $update = $conn->prepare("UPDATE providers SET experience=?, location=?, bio=? WHERE user_id=?");
            $update->bind_param("sssi", $experience, $location, $bio, $user_id);
            $update->execute();
        } else {
            $insert = $conn->prepare("INSERT INTO providers (user_id, experience, location, bio) VALUES (?, ?, ?, ?)");
            $insert->bind_param("isss", $user_id, $experience, $location, $bio);
            $insert->execute();
        }

        // Reset services
        $conn->query("DELETE FROM provider_services WHERE provider_id = $user_id");

        if (!empty($services)) {
            $stmt = $conn->prepare("INSERT INTO provider_services (provider_id, service_id, price) VALUES (?, ?, ?)");
            foreach ($services as $service_id) {
                $price = 500.00;
                $stmt->bind_param("iid", $user_id, $service_id, $price);
                $stmt->execute();
            }
        }
    }
}

// Fetch current user info
$query = $conn->prepare("SELECT * FROM users WHERE id=?");
$query->bind_param("i", $user_id);
$query->execute();
$current_user = $query->get_result()->fetch_assoc();

if ($role === 'provider') {
    $prov = $conn->query("SELECT * FROM providers WHERE user_id = $user_id")->fetch_assoc();
    $services = $conn->query("SELECT id, name FROM services");
    $myServices = $conn->query("SELECT service_id FROM provider_services WHERE provider_id = $user_id");

    $service_ids = [];
    while ($row = $myServices->fetch_assoc()) {
        $service_ids[] = $row['service_id'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8f9fa; padding: 20px; }
        .container { max-width: 800px; margin: auto; background: white; padding: 30px; border-radius: 8px; }
        h2 { border-bottom: 2px solid #f76d2b; padding-bottom: 10px; color: #2d3748; }
        .form-group { margin-bottom: 15px; }
        label { font-weight: bold; display: block; margin-bottom: 5px; }
        input, textarea { width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #ccc; }
        .btn { background: #f76d2b; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; }
        .btn:hover { background: #e05b1a; }
        .alert { padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
<div class="container">
    <h2>Edit My Profile</h2>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Name</label>
            <input type="text" name="name" required value="<?= htmlspecialchars($current_user['name']) ?>">
        </div>

        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required value="<?= htmlspecialchars($current_user['email']) ?>">
        </div>

        <div class="form-group">
            <label>Phone</label>
            <input type="text" name="phone" value="<?= htmlspecialchars($current_user['phone'] ?? '') ?>">
        </div>

        <?php if ($role === 'customer'): ?>
            <div class="form-group">
                <label>Address</label>
                <input type="text" name="address" value="<?= htmlspecialchars($current_user['address'] ?? '') ?>">
            </div>
        <?php endif; ?>

        <div class="form-group">
            <label>City</label>
            <input type="text" name="city" value="<?= htmlspecialchars($current_user['city'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label>State</label>
            <input type="text" name="state" value="<?= htmlspecialchars($current_user['state'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label>Pincode</label>
            <input type="text" name="pincode" value="<?= htmlspecialchars($current_user['pincode'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label>New Password (leave blank to keep current)</label>
            <input type="password" name="password" placeholder="••••••••">
        </div>

        <?php if ($role === 'provider'): ?>
            <hr>
            <h3>Provider Service Details</h3>

            <div class="form-group">
                <label>Experience</label>
                <input type="text" name="experience" value="<?= htmlspecialchars($prov['experience'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Location</label>
                <input type="text" name="location" value="<?= htmlspecialchars($prov['location'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Bio</label>
                <textarea name="bio" rows="4"><?= htmlspecialchars($prov['bio'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label>Services Offered</label>
                <?php while ($s = $services->fetch_assoc()): ?>
                    <div>
                        <input type="checkbox" name="services[]" value="<?= $s['id'] ?>" id="s<?= $s['id'] ?>"
                            <?= in_array($s['id'], $service_ids) ? 'checked' : '' ?>>
                        <label for="s<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></label>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>

        <button type="submit" class="btn">Update Profile</button>
    </form>

    <p style="margin-top: 20px;">
  
  <a href="index.php" class="back-link">← Back to Home</a>

    </p>
</div>
</body>
</html>
