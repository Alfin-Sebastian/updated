<?php
include 'db.php';
session_start();

// Get filter inputs
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 12; // Results per page

// Fetch service categories
$categories = [];
$cat_query = $conn->query("SELECT id, name FROM service_categories");
if ($cat_query) {
    $categories = $cat_query->fetch_all(MYSQLI_ASSOC);
}

// Base query for counting total results
$count_sql = "
    SELECT COUNT(*) as total
    FROM users u
    JOIN providers p ON u.id = p.user_id
    WHERE u.role = 'provider' AND p.is_verified = 1
";

// Base query for fetching results
$sql = "
    SELECT u.id, u.name, u.profile_image, u.city, u.state, p.experience, p.bio, p.avg_rating
    FROM users u
    JOIN providers p ON u.id = p.user_id
    WHERE u.role = 'provider' AND p.is_verified = 1
";

// Apply filters to both queries
if (!empty($search)) {
    $safe_search = $conn->real_escape_string($search);
    $search_condition = " AND (u.name LIKE '%$safe_search%' OR u.city LIKE '%$safe_search%' OR u.state LIKE '%$safe_search%' OR p.bio LIKE '%$safe_search%')";
    $count_sql .= $search_condition;
    $sql .= $search_condition;
}

if ($category_id > 0) {
    $category_condition = "
        AND EXISTS (
            SELECT 1 FROM provider_services ps
            WHERE ps.provider_id = u.id AND ps.service_id IN (
                SELECT id FROM services WHERE category_id = $category_id
            )
        )
    ";
    $count_sql .= $category_condition;
    $sql .= $category_condition;
}

// Get total count for pagination
$total_result = $conn->query($count_sql)->fetch_assoc();
$total_providers = $total_result['total'];
$total_pages = ceil($total_providers / $per_page);

// Add pagination and sorting to main query
$offset = ($page - 1) * $per_page;
$sql .= " ORDER BY p.avg_rating DESC, u.name ASC LIMIT $offset, $per_page";

$query = $conn->query($sql);
$providers = $query ? $query->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Providers | UrbanClap</title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 0;
        }

        .section-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
            text-align: center;
            color: var(--secondary);
            position: relative;
            padding-bottom: 15px;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background-color: var(--primary);
        }

        .section-subtitle {
            color: var(--light-text);
            text-align: center;
            max-width: 700px;
            margin: 0 auto 40px;
            font-size: 16px;
        }

        .filters-container {
            background-color: var(--white);
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 40px;
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            justify-content: center;
        }

        .filter-input {
            flex: 1;
            min-width: 250px;
            padding: 12px 15px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 15px;
            color: var(--text);
            background-color: var(--white);
            transition: border-color 0.2s;
        }

        .filter-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(247, 109, 43, 0.1);
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
            border: none;
        }

        .btn-primary {
            background-color: var(--primary);
            color: var(--white);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .providers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }

        .provider-card {
            background-color: var(--white);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .provider-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .provider-image {
            height: 180px;
            background-color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(rgba(247, 109, 43, 0.1), rgba(247, 109, 43, 0.05));
        }

        .provider-image img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid var(--white);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .provider-info {
            padding: 20px;
            text-align: center;
        }

        .provider-info h3 {
            margin: 0 0 5px;
            font-size: 18px;
            color: var(--primary);
        }

        .provider-location {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            color: var(--light-text);
            font-size: 14px;
            margin-bottom: 10px;
        }

        .provider-rating {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background-color: rgba(247, 109, 43, 0.1);
            color: var(--primary);
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .empty-state {
            grid-column: 1 / -1;
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

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 40px;
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
            .filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-input {
                width: 100%;
            }
            
            .providers-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }

        /* Previous CSS remains the same, add these new styles */
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 40px;
            gap: 8px;
        }
        
        .pagination a, .pagination span {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            color: var(--text);
            border: 1px solid var(--border);
        }
        
        .pagination a:hover {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .pagination .active {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .pagination .disabled {
            color: var(--light-text);
            pointer-events: none;
        }
        
        .search-summary {
            text-align: center;
            margin-bottom: 20px;
            color: var(--light-text);
            font-size: 15px;
        }
        
        .highlight {
            color: var(--primary);
            font-weight: 600;
        }
        
        .filter-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .filter-tag {
            display: inline-flex;
            align-items: center;
            background-color: rgba(247, 109, 43, 0.1);
            color: var(--primary);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
        }
        
        .filter-tag button {
            background: none;
            border: none;
            color: var(--primary);
            margin-left: 8px;
            cursor: pointer;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="section-title">Our Verified Service Providers</h2>
        <p class="section-subtitle">Search and filter trusted professionals near you</p>

        <div class="filters-container">
            <form class="filters" method="GET">
                <input 
                    type="text" 
                    name="search" 
                    placeholder="Search by name, location or skills" 
                    value="<?= htmlspecialchars($search) ?>" 
                    class="filter-input"
                    autocomplete="off"
                >
                
                <select name="category" class="filter-input">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit" class="btn btn-primary">Search</button>
            </form>
        </div>

        <?php if (!empty($search) || $category_id > 0): ?>
            <div class="search-summary">
                Found <span class="highlight"><?= $total_providers ?></span> providers matching 
                <?php if (!empty($search)): ?>
                    <span class="highlight">"<?= htmlspecialchars($search) ?>"</span>
                <?php endif; ?>
                <?php if ($category_id > 0): ?>
                    in <span class="highlight">
                        <?= htmlspecialchars(array_column($categories, 'name', 'id')[$category_id] ?? '') ?>
                    </span> category
                <?php endif; ?>
            </div>
            
            <div class="filter-tags">
                <?php if (!empty($search)): ?>
                    <div class="filter-tag">
                        Search: <?= htmlspecialchars($search) ?>
                        <button onclick="window.location.href='?<?= http_build_query(array_merge($_GET, ['search' => ''])) ?>'">
                            ×
                        </button>
                    </div>
                <?php endif; ?>
                
                <?php if ($category_id > 0): ?>
                    <div class="filter-tag">
                        Category: <?= htmlspecialchars(array_column($categories, 'name', 'id')[$category_id] ?? '') ?>
                        <button onclick="window.location.href='?<?= http_build_query(array_merge($_GET, ['category' => 0])) ?>'">
                            ×
                        </button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($search) || $category_id > 0): ?>
                    <div class="filter-tag">
                        <a href="browse_providers.php" style="color: var(--primary); text-decoration: none;">
                            Clear all filters
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="providers-grid">
            <?php if (count($providers) > 0): ?>
                <?php foreach ($providers as $provider): ?>
                    <div class="provider-card">
                        <div class="provider-image">
                            <img src="<?= htmlspecialchars($provider['profile_image'] ?: 'default-avatar.png') ?>" alt="<?= htmlspecialchars($provider['name']) ?>">
                        </div>
                        <div class="provider-info">
                            <h3><?= htmlspecialchars($provider['name']) ?></h3>
                            <div class="provider-location">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 2C8.13 2 5 5.13 5 9C5 14.25 12 22 12 22C12 22 19 14.25 19 9C19 5.13 15.87 2 12 2ZM12 11.5C10.62 11.5 9.5 10.38 9.5 9C9.5 7.62 10.62 6.5 12 6.5C13.38 6.5 14.5 7.62 14.5 9C14.5 10.38 13.38 11.5 12 11.5Z" fill="#718096"/>
                                </svg>
                                <?= htmlspecialchars($provider['city'] . ', ' . $provider['state']) ?>
                            </div>
                            <div class="provider-rating">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 17.27L18.18 21L16.54 13.97L22 9.24L14.81 8.63L12 2L9.19 8.63L2 9.24L7.46 13.97L5.82 21L12 17.27Z" fill="#F76D2B"/>
                                </svg>
                                <?= number_format($provider['avg_rating'], 1) ?>
                            </div>
                            <a href="provider_profile.php?id=<?= $provider['id'] ?>" class="btn btn-primary">View Profile</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <?php if (!empty($search) || $category_id > 0): ?>
                        <p>No providers found matching your search criteria.</p>
                        <a href="browse_providers.php" class="btn btn-primary">Reset Filters</a>
                    <?php else: ?>
                        <p>Currently no providers available in this category.</p>
                        <a href="services.php" class="btn btn-primary">Browse Services</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Previous</a>
                <?php else: ?>
                    <span class="disabled">Previous</span>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" <?= $i == $page ? 'class="active"' : '' ?>>
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
                <?php else: ?>
                    <span class="disabled">Next</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
<a href="index.php" class="back-link">← Back to Home</a>
 
    </div>
</body>
</html>