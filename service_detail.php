<?php
session_start();
include 'db.php';

if (!isset($_GET['id'])) {
    header("Location: services.php");
    exit;
}

$service_id = intval($_GET['id']);

// Fetch service details with category name
$service_stmt = $conn->prepare("
    SELECT s.*, sc.name AS category_name 
    FROM services s
    JOIN service_categories sc ON s.category_id = sc.id
    WHERE s.id = ?
");
$service_stmt->bind_param("i", $service_id);
$service_stmt->execute();
$service_result = $service_stmt->get_result();

if ($service_result->num_rows === 0) {
    header("Location: services.php?error=not_found");
    exit;
}
$service = $service_result->fetch_assoc();

// Fetch providers for this service with their pricing
$provider_stmt = $conn->prepare("
    SELECT u.id AS user_id, u.name, u.profile_image, p.experience, p.avg_rating, ps.price
    FROM provider_services ps
    JOIN users u ON ps.provider_id = u.id
    JOIN providers p ON u.id = p.user_id
    WHERE ps.service_id = ?
    ORDER BY p.avg_rating DESC
");
$provider_stmt->bind_param("i", $service_id);
$provider_stmt->execute();
$provider_result = $provider_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($service['name']) ?> | UrbanServe</title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .service-header {
            display: flex;
            gap: 30px;
            margin-bottom: 40px;
            flex-wrap: wrap;
        }

        .service-image {
            flex: 1;
            min-width: 300px;
            height: 300px;
            background-color: #eee;
            border-radius: 8px;
            overflow: hidden;
            background-size: cover;
            background-position: center;
        }

        .service-info {
            flex: 2;
            min-width: 300px;
        }

        h1 {
            color: var(--secondary);
            margin-bottom: 15px;
            font-size: 2rem;
        }

        .service-meta {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            color: var(--light-text);
        }

        .service-category {
            background-color: var(--primary-light);
            color: var(--primary);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .service-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--secondary);
            margin: 20px 0;
        }

        .service-description {
            margin-bottom: 30px;
            line-height: 1.8;
        }

        .providers-section {
            margin-top: 50px;
        }

        h2 {
            color: var(--secondary);
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary);
        }

        .providers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        .provider-card {
            background-color: var(--white);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .provider-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .provider-image {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
        }

        .provider-name {
            font-weight: 600;
            font-size: 1.1rem;
        }

        .provider-rating {
            color: var(--primary);
            font-weight: 600;
            margin-left: 5px;
        }

        .provider-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            color: var(--light-text);
            font-size: 0.9rem;
        }

        .provider-price {
            font-size: 1.2rem;
            font-weight: 700;
            margin: 15px 0;
            color: var(--primary);
        }

        .booking-form {
            margin-top: 15px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }

        input[type="datetime-local"] {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 1rem;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s;
            text-decoration: none;
        }

        .btn:hover {
            background-color: #e05b1a;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
        }

        .btn-outline:hover {
            background-color: var(--primary-light);
        }

        .back-link {
            display: inline-block;
            margin-top: 30px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .login-prompt {
            margin-top: 15px;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .service-header {
                flex-direction: column;
            }
            
            .service-image {
                min-width: 100%;
                height: 200px;
            }
            
            .providers-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="service-header">
            <div class="service-image" style="background-image: url('<?= htmlspecialchars($service['image'] ?? 'https://via.placeholder.com/300') ?>')"></div>
            <div class="service-info">
                <h1><?= htmlspecialchars($service['name']) ?></h1>
                <div class="service-meta">
                    <span class="service-category"><?= htmlspecialchars($service['category_name']) ?></span>
                    <span><i class="far fa-clock"></i> <?= htmlspecialchars($service['duration_minutes']) ?> mins</span>
                </div>
                <div class="service-price">From ₹<?= number_format($service['base_price'], 2) ?></div>
                <div class="service-description">
                    <?= nl2br(htmlspecialchars($service['description'])) ?>
                </div>
            </div>
        </div>

        <div class="providers-section">
            <h2>Available Providers</h2>
            
            <?php if ($provider_result->num_rows > 0): ?>
                <div class="providers-grid">
                    <?php while ($provider = $provider_result->fetch_assoc()): ?>
                        <div class="provider-card">
                            <div class="provider-header">
                               <img src="<?= htmlspecialchars($provider['profile_image'] ?? ('https://ui-avatars.com/api/?name=' . urlencode($provider['name']) . '&background=f76d2b&color=fff')) ?>" 
     alt="<?= htmlspecialchars($provider['name']) ?>" class="provider-image">

                                <div>
                                    <div class="provider-name">
                                        <?= htmlspecialchars($provider['name']) ?>
                                        <?php if ($provider['avg_rating'] > 0): ?>
                                            <span class="provider-rating">★ <?= number_format($provider['avg_rating'], 1) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="provider-meta">
                                        <span><i class="fas fa-briefcase"></i> <?= htmlspecialchars($provider['experience']) ?> exp</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="provider-price">₹<?= number_format($provider['price'], 2) ?></div>
                            
                           <?php if (isset($_SESSION['user'])): ?>
    <?php if ($_SESSION['user']['role'] === 'customer'): ?>
        <form method="POST" action="book_service.php" class="booking-form">
            <input type="hidden" name="provider_id" value="<?= $provider['user_id'] ?>">
            <input type="hidden" name="service_id" value="<?= $service_id ?>">
            <div class="form-group">
                <label for="date-<?= $provider['user_id'] ?>">Select Date & Time</label>
                <input type="datetime-local" id="date-<?= $provider['user_id'] ?>" 
                       name="date" required 
                       min="<?= date('Y-m-d\TH:i') ?>">
            </div>
            <button type="submit" class="btn">Book Now</button>
        </form>
    
    <?php elseif ($_SESSION['user']['role'] === 'provider' && $_SESSION['user']['id'] == $provider['user_id']): ?>
        <a href="edit_my_services.php" class="btn btn-primary">Edit My Services</a>

    <?php elseif ($_SESSION['user']['role'] === 'admin'): ?>
        <a href="edit_services.php" class="btn btn-primary">Edit Services</a>

    <?php else: ?>
        <div class="login-prompt">
            <a href="login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-outline">
                <i class="fas fa-sign-in-alt"></i> Login to Book
            </a>
        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="login-prompt">
        <a href="login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-outline">
            <i class="fas fa-sign-in-alt"></i> Login to Book
        </a>
    </div>
<?php endif; ?>

                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p>No providers available for this service at the moment.</p>
            <?php endif; ?>
        </div>
<a href="index.php" class="back-link">← Back to Home</a>
           </div>
</body>
</html>