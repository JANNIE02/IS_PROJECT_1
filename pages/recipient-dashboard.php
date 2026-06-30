<?php
include '../config.php';
session_start();

// Protect page
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] != "recipient") {
    header("Location: login.php");
    exit();
}

// Handle location update FIRST before any queries
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["latitude"])) {
    $latitude = $_POST["latitude"];
    $longitude = $_POST["longitude"];

    pg_query_params($conn,
        "UPDATE users SET latitude = $1, longitude = $2 WHERE id = $3",
        array($latitude, $longitude, $_SESSION["user_id"])
    );
}

// Get all available food listings
$listings_result = pg_query($conn,
    "SELECT food_listings.*, users.full_name AS donor_name, users.location AS donor_location
     FROM food_listings
     JOIN users ON food_listings.donor_id = users.id
     WHERE food_listings.status = 'available'
     ORDER BY food_listings.created_at DESC"
);
$listings = pg_fetch_all($listings_result) ?: [];

// Get current recipient location
$loc = pg_query_params($conn,
    "SELECT location, latitude, longitude FROM users WHERE id = $1",
    array($_SESSION["user_id"])
);
$loc_row = pg_fetch_assoc($loc);

// Get this recipient's claims for the stats row
$claims_result = pg_query_params($conn,
    "SELECT claims.status FROM claims WHERE recipient_id = $1",
    array($_SESSION["user_id"])
);
$claims = pg_fetch_all($claims_result) ?: [];
$pending_count = 0;
$confirmed_count = 0;
$collected_count = 0;
foreach ($claims as $c) {
    if ($c["status"] === "pending") $pending_count++;
    if ($c["status"] === "confirmed") $confirmed_count++;
    if ($c["status"] === "collected") $collected_count++;
}

// Get full claim history with delivery + rider info for "My Claims" table
$my_claims_result = pg_query_params($conn,
    "SELECT 
        claims.id AS claim_id,
        claims.status AS claim_status,
        claims.pickup_date,
        claims.created_at AS claimed_at,
        food_listings.food_name,
        food_listings.quantity,
        food_listings.unit,
        food_listings.food_condition,
        users.full_name AS donor_name,
        users.location AS donor_location,
        deliveries.status AS delivery_status,
        rider.full_name AS rider_name
     FROM claims
     JOIN food_listings ON claims.listing_id = food_listings.id
     JOIN users ON food_listings.donor_id = users.id
     LEFT JOIN deliveries ON deliveries.claim_id = claims.id
     LEFT JOIN users rider ON deliveries.rider_id = rider.id
     WHERE claims.recipient_id = $1
     ORDER BY claims.created_at DESC",
    array($_SESSION["user_id"])
);
$my_claims = pg_fetch_all($my_claims_result) ?: [];

function conditionLabel($c) {
    return ucfirst(str_replace('_', ' ', $c ?? ''));
}

function claimStatusBadge($delivery_status) {
    $map = [
        'delivered'  => ['Collected', 'live'],
        'picked_up'  => ['Rider on the way', 'booked-awaiting'],
        'assigned'   => ['Rider assigned', 'booked-awaiting'],
    ];
    return $map[$delivery_status] ?? ['Waiting for rider', 'pending'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5" />
    <title>Recipient Dashboard - Food Connect</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Roboto, system-ui, -apple-system, sans-serif;
            background: #f4f7fa;
            color: #1e293b;
            line-height: 1.6;
            padding: 20px;
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 32px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            padding: 24px 28px 32px;
        }

        .app-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 16px;
            border-bottom: 2px solid #e9edf2;
            margin-bottom: 28px;
        }
        .logo-area { display: flex; align-items: center; gap: 12px; }
        .logo-icon {
            background: #06392f;
            color: white;
            width: 44px; height: 44px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px;
        }
        .logo-text { font-size: 1.7rem; font-weight: 700; letter-spacing: -0.5px; }
        .logo-text span { color: #06392f; }
        .header-actions { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
        .badge-role {
            background: #eef2f6;
            padding: 6px 18px;
            border-radius: 40px;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex; align-items: center; gap: 8px;
        }

        .btn {
            background: #06392f;
            border: none;
            color: white;
            padding: 8px 20px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.15s;
            display: inline-flex; align-items: center; gap: 8px;
            text-decoration: none;
        }
        .btn:hover { background: #0a5240; }
        .btn-outline {
            background: transparent;
            border: 2px solid #06392f;
            color: #06392f;
        }
        .btn-outline:hover { background: #06392f; color: white; }
        .btn-sm { padding: 5px 14px; font-size: 0.8rem; }
        .btn-block { width: 100%; justify-content: center; }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: #f8fafc;
            border-radius: 18px;
            padding: 18px 20px;
            border-left: 5px solid #06392f;
        }
        .stat-card .stat-number { font-size: 2rem; font-weight: 700; line-height: 1.2; }
        .stat-card .stat-label { font-size: 0.85rem; color: #475569; }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin: 24px 0 12px;
            display: flex; align-items: center; gap: 10px;
        }

        .banner {
            border-radius: 14px;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin: 12px 0;
            font-size: 0.9rem;
        }
        .banner-success { background: #dcfce7; color: #166534; }
        .banner-warning { background: #fef9c3; color: #854d0e; }

        .listings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
            margin: 12px 0;
        }
        .listing-card {
            background: #fafcff;
            border: 1px solid #e9edf2;
            border-radius: 18px;
            padding: 18px 20px;
            display: flex;
            flex-direction: column;
        }
        .listing-card-title {
            font-weight: 600;
            font-size: 1.05rem;
            margin-bottom: 10px;
        }
        .listing-row {
            font-size: 0.88rem;
            margin-bottom: 4px;
            color: #334155;
        }
        .listing-row strong { color: #1e293b; }
        .listing-card .btn { margin-top: 14px; }

        .table-wrap {
            overflow-x: auto;
            background: #fafcff;
            border-radius: 18px;
            border: 1px solid #e9edf2;
            padding: 4px 0;
            margin: 12px 0;
        }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th {
            text-align: left;
            padding: 14px 16px;
            background: #f1f5f9;
            font-weight: 600;
            color: #1e293b;
        }
        td {
            padding: 14px 16px;
            border-top: 1px solid #ecf1f7;
            vertical-align: middle;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 40px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-pending   { background: #fef9c3; color: #854d0e; }
        .badge-approved, .badge-live   { background: #dcfce7; color: #166534; }
        .badge-claimed, .badge-booked-awaiting   { background: #dbeafe; color: #1e40af; }
        .badge-delivered { background: #e0e7ff; color: #3730a3; }
        .badge-cancelled { background: #f1f5f9; color: #475569; }
        .badge-expired   { background: #fee2e2; color: #991b1b; }

        .empty-state {
            text-align: center;
            padding: 40px 24px;
            color: #64748b;
        }
        .empty-state i { font-size: 2.5rem; margin-bottom: 12px; display: block; color: #cbd5e1; }
        .empty-state p { font-size: 0.95rem; }

        .form-card {
            background: #fafcff;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid #e9edf2;
            margin: 16px 0;
        }

        .text-muted { color: #64748b; }
        .flex-between {
            display: flex; flex-wrap: wrap;
            align-items: center; justify-content: space-between;
            gap: 12px;
        }

        @media (max-width: 600px) {
            .dashboard-container { padding: 16px; }
        }
    </style>
</head>
<body>
<div class="dashboard-container">

    <!-- Header -->
    <header class="app-header">
        <div class="logo-area">
            <div class="logo-icon"><i class="fas fa-hands-helping"></i></div>
            <div class="logo-text"><span>Food Connect</span></div>
        </div>
        <div class="header-actions">
            <span class="badge-role"><i class="fas fa-user-circle"></i> Recipient</span>
            <span class="text-muted" style="font-size:0.85rem;">Welcome, <?php echo htmlspecialchars($_SESSION["user_name"]); ?></span>
            <a href="logout.php" class="btn btn-outline btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-number"><?php echo count($listings); ?></div>
            <div class="stat-label">Available now</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $pending_count; ?></div>
            <div class="stat-label">Pending claims</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $confirmed_count; ?></div>
            <div class="stat-label">Rider on the way</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $collected_count; ?></div>
            <div class="stat-label">Collected</div>
        </div>
    </div>

    <!-- Location banner -->
    <?php if ($loc_row["latitude"] && $loc_row["longitude"]): ?>
        <div class="banner banner-success">
            <i class="fas fa-map-marker-alt"></i>
            <span>Exact location is set — riders can find you on Google Maps. Coordinates: <?php echo $loc_row["latitude"]; ?>, <?php echo $loc_row["longitude"]; ?></span>
        </div>
    <?php else: ?>
        <div class="banner banner-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <span>No exact location set yet. Riders will use your text address instead.</span>
            <button class="btn btn-sm" onclick="getLocation()"><i class="fas fa-crosshairs"></i> Detect now</button>
        </div>
    <?php endif; ?>

    <!-- Available Listings -->
    <div class="flex-between">
        <h3 class="section-title"><i class="fas fa-box-open"></i> Available food listings</h3>
        <span class="text-muted" style="font-size:0.85rem;"><?php echo count($listings); ?> listing<?php echo count($listings) !== 1 ? 's' : ''; ?></span>
    </div>

    <?php if (count($listings) === 0): ?>
        <div class="empty-state">
            <i class="fas fa-utensils"></i>
            <p>No food listings available right now. Check back later.</p>
        </div>
    <?php else: ?>
        <div class="listings-grid">
            <?php foreach ($listings as $row): ?>
                <div class="listing-card">
                    <div class="listing-card-title"><?php echo htmlspecialchars($row["food_name"]); ?></div>
                    <p class="listing-row"><strong>Quantity:</strong> <?php echo htmlspecialchars($row["quantity"] . " " . $row["unit"]); ?></p>
                    <?php if (!empty($row["food_condition"])): ?>
                        <p class="listing-row"><strong>Condition:</strong> <?php echo htmlspecialchars(conditionLabel($row["food_condition"])); ?></p>
                    <?php endif; ?>
                    <p class="listing-row"><strong>Expires:</strong> <?php echo date("d M Y", strtotime($row["expiry_date"])); ?></p>
                    <p class="listing-row"><strong>Location:</strong> <?php echo htmlspecialchars($row["donor_location"]); ?></p>
                    <p class="listing-row"><strong>Donor:</strong> <?php echo htmlspecialchars($row["donor_name"]); ?></p>
                    <?php if ($row["notes"]): ?>
                        <p class="listing-row"><strong>Notes:</strong> <?php echo htmlspecialchars($row["notes"]); ?></p>
                    <?php endif; ?>
                    <a href="claim.php?listing_id=<?php echo $row['id']; ?>" class="btn btn-block">
                         Claim this
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- My Claims -->
    <div class="flex-between">
        <h3 class="section-title"><i class="fas fa-clipboard-list"></i> My claims</h3>
        <span class="text-muted" style="font-size:0.85rem;"><?php echo count($my_claims); ?> claim<?php echo count($my_claims) !== 1 ? 's' : ''; ?></span>
    </div>

    <?php if (count($my_claims) === 0): ?>
        <div class="empty-state">
            <i class="fas fa-clipboard"></i>
            <p>You haven't claimed any food listings yet.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Food</th><th>Quantity</th><th>Donor</th>
                        <th>Pickup location</th><th>Pickup date</th>
                        <th>Rider</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($my_claims as $row): ?>
                        <?php list($status_label, $status_class) = claimStatusBadge($row["delivery_status"]); ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row["food_name"]); ?></strong></td>
                            <td><?php echo htmlspecialchars($row["quantity"] . " " . $row["unit"]); ?></td>
                            <td><?php echo htmlspecialchars($row["donor_name"]); ?></td>
                            <td style="font-size:0.85rem;"><?php echo htmlspecialchars($row["donor_location"]); ?></td>
                            <td style="font-size:0.85rem;"><?php echo date("d M Y", strtotime($row["pickup_date"])); ?></td>
                            <td><?php echo $row["rider_name"] ? htmlspecialchars($row["rider_name"]) : '<span class="text-muted">Unassigned</span>'; ?></td>
                            <td><span class="badge badge-<?php echo $status_class; ?>"><?php echo $status_label; ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- My Location -->
    <h3 class="section-title"><i class="fas fa-location-dot"></i> My location</h3>
    <div class="form-card">
        <p class="text-muted" style="margin-bottom:14px;">Set your exact location so riders can find you easily.</p>
        <button class="btn" onclick="getLocation()"><i class="fas fa-crosshairs"></i> Detect my location automatically</button>
    </div>

</div>

<!-- Hidden location form -->
<form method="POST" action="recipient-dashboard.php" id="location-form">
    <input type="hidden" name="latitude" id="latitude">
    <input type="hidden" name="longitude" id="longitude">
</form>

<script>
function getLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            document.getElementById("latitude").value = position.coords.latitude;
            document.getElementById("longitude").value = position.coords.longitude;
            document.getElementById("location-form").submit();
        }, function() {
            alert("Could not get location. Please allow location access in your browser.");
        });
    } else {
        alert("Your browser does not support location detection.");
    }
}
</script>
</body>
</html>