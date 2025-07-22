<?php
session_start();
include 'db.php';

// Authorization check
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Validate service ID
if (!isset($_GET['id'])) {
    header("Location: admin_dashboard.php?error=no_id");
    exit;
}

$service_id = intval($_GET['id']);

// Load service details with category info
$stmt = $conn->prepare("
    SELECT s.*, sc.name AS category_name 
    FROM services s
    LEFT JOIN service_categories sc ON s.category_id = sc.id
    WHERE s.id = ?
");
$stmt->bind_param("i", $service_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: admin_dashboard.php?error=not_found");
    exit;
}
$service = $result->fetch_assoc();

// Get all categories for dropdown
$categories = [];
$cat_query = $conn->query("SELECT * FROM service_categories ORDER BY name");
if ($cat_query) {
    $categories = $cat_query->fetch_all(MYSQLI_ASSOC);
}

// Update logic
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $name = trim($_POST['name']);
    $category_id = intval($_POST['category_id']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $duration = intval($_POST['duration']);

    // Validate inputs
    if (empty($name) || empty($category_id) || empty($description) || $price <= 0 || $duration <= 0) {
        $error = "Please fill all fields with valid values";
    } else {
        $update = $conn->prepare("UPDATE services SET name=?, category_id=?, description=?, base_price=?, duration_minutes=? WHERE id=?");
        $update->bind_param("sisdii", $name, $category_id, $description, $price, $duration, $service_id);
        
        if ($update->execute()) {
            $success = "Service updated successfully!";
            // Refresh service data
            $stmt->execute();
            $result = $stmt->get_result();
            $service = $result->fetch_assoc();
        } else {
            $error = "Error updating service: " . $conn->error;
        }
    }
}

// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Service | UrbanServe Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #f76d2b;
            --primary-light: rgba(247, 109, 43, 0.1);
            --secondary: #2d3748;
            --accent: #f0f4f8;
            --text: #2d3748;
            --light-text: #718096;
            --border: #e2e8f0;
            --white: #ffffff;
            --success: #38a169;
            --error: #e53e3e;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
            color: var(--text);
            line-height: 1.6;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 30px auto;
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 30px;
        }

        h2 {
            color: var(--secondary);
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary);
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--secondary);
        }

        input[type="text"],
        input[type="number"],
        textarea,
        select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 16px;
            transition: all 0.3s;
        }

        input[type="text"]:focus,
        input[type="number"]:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        textarea {
            min-height: 150px;
            resize: vertical;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: var(--primary);
            color: var(--white);
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background-color: #e05b1a;
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
        }

        .btn-outline:hover {
            background-color: var(--primary-light);
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
        }

        .alert-success {
            background-color: rgba(56, 161, 105, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .alert-error {
            background-color: rgba(226, 66, 66, 0.1);
            color: var(--error);
            border-left: 4px solid var(--error);
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2><i class="fas fa-edit"></i> Edit Service</h2>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            
            <div class="form-group">
                <label for="name">Service Name *</label>
                <input type="text" id="name" name="name" required 
                       value="<?= htmlspecialchars($service['name']) ?>">
            </div>
            
            <div class="form-group">
                <label for="category_id">Category *</label>
                <select id="category_id" name="category_id" required>
                    <option value="">Select a category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>" 
                            <?= ($service['category_id'] == $category['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="description">Description *</label>
                <textarea id="description" name="description" required><?= 
                    htmlspecialchars($service['description']) 
                ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="price">Base Price (₹) *</label>
                <input type="number" id="price" name="price" min="0.01" step="0.01" required 
                       value="<?= htmlspecialchars($service['base_price']) ?>">
            </div>
            
            <div class="form-group">
                <label for="duration">Duration (minutes) *</label>
                <input type="number" id="duration" name="duration" min="1" required 
                       value="<?= htmlspecialchars($service['duration_minutes']) ?>">
            </div>
            
            <div class="action-buttons">
                <button type="submit" class="btn">
                    <i class="fas fa-save"></i> Update Service
                </button>
                <a href="services.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</body>
</html>
