<?php
include 'db.php';
session_start();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: providers.php");
    exit;
}

$provider_id = intval($_GET['id']);

// Fetch provider basic details
$stmt = $conn->prepare("
    SELECT u.name, u.profile_image, u.email, u.phone, u.city, u.state, p.experience, p.bio, p.avg_rating
    FROM users u
    JOIN providers p ON u.id = p.user_id
    WHERE u.id = ? AND u.role = 'provider' AND p.is_verified = 1
");
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$provider_result = $stmt->get_result();

if ($provider_result->num_rows === 0) {
    echo "<h3>Provider not found or not verified.</h3>";
    exit;
}

$provider = $provider_result->fetch_assoc();

// Fetch services offered by this provider
$services_stmt = $conn->prepare("
    SELECT s.id, s.name, s.description, s.duration_minutes, ps.price
    FROM provider_services ps
    JOIN services s ON ps.service_id = s.id
    WHERE ps.provider_id = ?
");
$services_stmt->bind_param("i", $provider_id);
$services_stmt->execute();
$services_result = $services_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($provider['name']) ?> | UrbanClap Provider</title>
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
            margin: 40px auto;
            background-color: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .profile-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 40px 20px;
            background: linear-gradient(rgba(247, 109, 43, 0.1), rgba(247, 109, 43, 0.05));
            text-align: center;
        }

        .profile-img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid var(--white);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .profile-name {
            font-size: 28px;
            font-weight: 700;
            color: var(--secondary);
            margin: 0 0 10px;
        }

        .profile-meta {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            margin-bottom: 20px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 15px;
            color: var(--light-text);
        }

        .rating-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background-color: rgba(247, 109, 43, 0.1);
            color: var(--primary);
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 600;
        }

        .profile-bio {
            max-width: 700px;
            margin: 0 auto;
            color: var(--text);
            font-size: 15px;
            line-height: 1.7;
            padding: 0 30px;
        }

        .section-title {
            font-size: 22px;
            font-weight: 600;
            color: var(--secondary);
            margin: 40px 0 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border);
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 80px;
            height: 2px;
            background-color: var(--primary);
        }

        .services-container {
            padding: 0 30px 30px;
        }

        .service-card {
            background-color: var(--white);
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .service-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .service-name {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary);
            margin: 0 0 10px;
        }

        .service-description {
            color: var(--text);
            font-size: 15px;
            margin-bottom: 15px;
            line-height: 1.6;
        }

        .service-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 15px;
            font-size: 14px;
            color: var(--light-text);
        }

        .service-price {
            font-size: 20px;
            font-weight: 700;
            color: var(--secondary);
            margin: 10px 0;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background-color: var(--primary);
            color: var(--white);
            border: 1px solid var(--primary);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .empty-state {
            text-align: center;
            padding: 30px;
            color: var(--light-text);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 30px;
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

        @media (max-width: 768px) {
            .profile-header {
                padding: 30px 15px;
            }
            
            .profile-img {
                width: 120px;
                height: 120px;
            }
            
            .services-container {
                padding: 0 15px 20px;
            }
            
            .service-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="profile-header">
            <img src="<?= htmlspecialchars($provider['profile_image'] ?: 'default-avatar.png') ?>" alt="Profile Picture" class="profile-img">
            <h1 class="profile-name"><?= htmlspecialchars($provider['name']) ?></h1>
            
            <div class="profile-meta">
                <div class="meta-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2C8.13 2 5 5.13 5 9C5 14.25 12 22 12 22C12 22 19 14.25 19 9C19 5.13 15.87 2 12 2ZM12 11.5C10.62 11.5 9.5 10.38 9.5 9C9.5 7.62 10.62 6.5 12 6.5C13.38 6.5 14.5 7.62 14.5 9C14.5 10.38 13.38 11.5 12 11.5Z" fill="#718096"/>
                    </svg>
                    <?= htmlspecialchars($provider['city'] . ', ' . $provider['state']) ?>
                </div>
                
                <div class="meta-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 17.27L18.18 21L16.54 13.97L22 9.24L14.81 8.63L12 2L9.19 8.63L2 9.24L7.46 13.97L5.82 21L12 17.27Z" fill="#F76D2B"/>
                    </svg>
                    <span class="rating-badge"><?= number_format($provider['avg_rating'], 1) ?>/5</span>
                </div>
                
                <div class="meta-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM12 20C7.59 20 4 16.41 4 12C4 7.59 7.59 4 12 4C16.41 4 20 7.59 20 12C20 16.41 16.41 20 12 20ZM12 6C9.79 6 8 7.79 8 10C8 12.21 9.79 14 12 14C14.21 14 16 12.21 16 10C16 7.79 14.21 6 12 6Z" fill="#718096"/>
                    </svg>
                    <?= htmlspecialchars($provider['experience']) ?> experience
                </div>
            </div>
        </div>
        
        <div class="profile-bio">
            <h2 class="section-title">About</h2>
            <p><?= nl2br(htmlspecialchars($provider['bio'])) ?></p>
        </div>
        
        <div class="services-container">
            <h2 class="section-title">Services Offered</h2>
            
            <?php if ($services_result->num_rows > 0): ?>
                <?php while ($service = $services_result->fetch_assoc()): ?>
                    <div class="service-card">
                        <h3 class="service-name"><?= htmlspecialchars($service['name']) ?></h3>
                        <p class="service-description"><?= htmlspecialchars($service['description']) ?></p>
                        
                        <div class="service-meta">
                            <div class="meta-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM12 20C7.59 20 4 16.41 4 12C4 7.59 7.59 4 12 4C16.41 4 20 7.59 20 12C20 16.41 16.41 20 12 20ZM12.5 7H11V13L16.2 16.2L17 14.9L12.5 12.2V7Z" fill="#718096"/>
                                </svg>
                                <?= (int)$service['duration_minutes'] ?> mins
                            </div>
                        </div>
                        
                        <div class="service-price">₹<?= number_format($service['price'], 2) ?></div>
                        
                        <?php if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'customer'): ?>
                            <a class="btn btn-primary" href="book_service.php?provider_id=<?= $provider_id ?>&service_id=<?= $service['id'] ?>">Book Now</a>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <p>This provider hasn't added any services yet.</p>
                </div>
            <?php endif; ?>
        </div>

        <a href="providers.php" class="back-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M20 11H7.83L13.42 5.41L12 4L4 12L12 20L13.41 18.59L7.83 13H20V11Z" fill="#F76D2B"/>
            </svg>
            Back to Providers
        </a>
    </div>
</body>
</html>