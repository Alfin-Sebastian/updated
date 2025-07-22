
<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'provider') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user']['id'];
$message = '';
$message_type = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'update') {
        $ps_id = intval($_POST['provider_service_id']);
        $service_id = intval($_POST['service_id']);
        $price = floatval($_POST['price']);

        $stmt = $conn->prepare("UPDATE provider_services SET service_id = ?, price = ? WHERE id = ? AND provider_id = ?");
        $stmt->bind_param("idii", $service_id, $price, $ps_id, $user_id);
        if ($stmt->execute()) {
            $message = 'Service updated successfully!';
            $message_type = 'success';
        } else {
            $message = 'Error updating service: ' . $conn->error;
            $message_type = 'error';
        }
    }

    if ($_POST['action'] === 'delete') {
        $ps_id = intval($_POST['provider_service_id']);

        $stmt = $conn->prepare("DELETE FROM provider_services WHERE id = ? AND provider_id = ?");
        $stmt->bind_param("ii", $ps_id, $user_id);
        if ($stmt->execute()) {
            $message = 'Service removed successfully!';
            $message_type = 'success';
        } else {
            $message = 'Error removing service: ' . $conn->error;
            $message_type = 'error';
        }
    }
}

// Fetch services for dropdown
$services = $conn->query("SELECT id, name FROM services");

// Fetch provider's current service mappings
$sql = "
    SELECT ps.id AS ps_id, ps.price, s.id AS service_id, s.name AS service_name
    FROM provider_services ps
    JOIN services s ON ps.service_id = s.id
    WHERE ps.provider_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$results = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Services | UrbanServe Provider</title>
    <style>
        :root {
            --primary: #f76d2b;
            --primary-dark: #e05b1a;
            --secondary: #2d3748;
            --accent: #f0f4f8;
            --text: #2d3748;
            --light-text: #718096;
            --border: #e2e8f0;
            --white: #ffffff;
            --black: #000000;
            --success: #38a169;
            --error: #e53e3e;
            --warning: #dd6b20;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            color: var(--text);
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
        }

        .container {
            width: 90%;
            max-width: 1000px;
            margin: 0 auto;
            padding: 30px 0;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        h2 {
            font-size: 28px;
            color: var(--secondary);
            margin: 0;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .alert-success {
            background-color: rgba(56, 161, 105, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .alert-error {
            background-color: rgba(229, 62, 62, 0.1);
            color: var(--error);
            border-left: 4px solid var(--error);
        }

        .service-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .service-item {
            background-color: var(--white);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }

        .service-form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            flex: 1;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        label {
            font-size: 14px;
            color: var(--light-text);
            font-weight: 500;
        }

        select, input[type="number"] {
            padding: 12px 15px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 15px;
            color: var(--text);
            background-color: var(--white);
            transition: border-color 0.2s;
            min-width: 200px;
        }

        input[type="number"] {
            width: 120px;
        }

        select:focus, input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .btn {
            padding: 12px 20px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }

        .btn-primary {
            background-color: var(--primary);
            color: var(--white);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-danger {
            background-color: var(--error);
            color: var(--white);
        }

        .btn-danger:hover {
            background-color: #c53030;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 30px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
            padding: 10px 15px;
            border-radius: 6px;
        }

        .back-link:hover {
            color: var(--primary-dark);
            background-color: rgba(247, 109, 43, 0.1);
            text-decoration: none;
        }

        .empty-state {
            background-color: var(--white);
            padding: 40px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
        }

        .empty-state p {
            color: var(--light-text);
            margin-bottom: 20px;
        }

        .add-service-btn {
            margin-top: 30px;
        }

        @media (max-width: 768px) {
            .service-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .service-form {
                width: 100%;
            }
            
            select, input[type="number"] {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Manage My Services</h2>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <?php if ($message_type === 'success'): ?>
                        <path d="M10 0C4.48 0 0 4.48 0 10C0 15.52 4.48 20 10 20C15.52 20 20 15.52 20 10C20 4.48 15.52 0 10 0ZM8 15L3 10L4.41 8.59L8 12.17L15.59 4.58L17 6L8 15Z" fill="<?= $message_type === 'success' ? '#38A169' : '#E53E3E' ?>"/>
                    <?php else: ?>
                        <path d="M10 0C4.48 0 0 4.48 0 10C0 15.52 4.48 20 10 20C15.52 20 20 15.52 20 10C20 4.48 15.52 0 10 0ZM11 15H9V13H11V15ZM11 11H9V5H11V11Z" fill="<?= $message_type === 'success' ? '#38A169' : '#E53E3E' ?>"/>
                    <?php endif; ?>
                </svg>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($results->num_rows > 0): ?>
            <ul class="service-list">
                <?php while ($row = $results->fetch_assoc()): ?>
                    <li class="service-item">
                        <form method="POST" class="service-form">
                            <div class="form-group">
                                <label>Service</label>
                                <select name="service_id" required>
                                    <?php foreach ($services as $s): ?>
                                        <option value="<?= $s['id'] ?>" <?= ($s['id'] == $row['service_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($s['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Price (₹)</label>
                                <input type="number" name="price" step="0.01" min="0" value="<?= $row['price'] ?>" required>
                            </div>
                            
                            <input type="hidden" name="provider_service_id" value="<?= $row['ps_id'] ?>">
                            <input type="hidden" name="action" value="update">
                            <button type="submit" class="btn btn-primary">Update</button>
                        </form>

                        <form method="POST" onsubmit="return confirm('Are you sure you want to remove this service? This action cannot be undone.');">
                            <input type="hidden" name="provider_service_id" value="<?= $row['ps_id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn btn-danger">Remove</button>
                        </form>
                    </li>
                <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <div class="empty-state">
                <p>You are not offering any services yet.</p>
                <a href="add_service.php" class="btn btn-primary">Add New Service</a>
            </div>
        <?php endif; ?>

    <?php if (!empty($_SERVER['HTTP_REFERER'])): ?>
  <a href="<?= htmlspecialchars($_SERVER['HTTP_REFERER']) ?>" class="back-link">← Go Back</a>
<?php else: ?>
  <a href="index.php" class="back-link">← Back to Home</a>
<?php endif; ?>
    </div>

    <script>
        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>