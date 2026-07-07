<?php
include '../config.php';
session_start();

// Protect page - only donors can access
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] != "donor") {
    header("Location: login.php");
    exit();
}

// Handle location update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["latitude"])) {
    $latitude = $_POST["latitude"];
    $longitude = $_POST["longitude"];

    pg_query_params($conn,
        "UPDATE users SET latitude = $1, longitude = $2 WHERE id = $3",
        array($latitude, $longitude, $_SESSION["user_id"])
    );
}

// Handle new food listing submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["food_name"])) {
    $food_name = trim($_POST["food_name"]);
    $quantity = $_POST["quantity"];
    $unit = $_POST["unit"];
    $expiry_date = $_POST["expiry_date"];
    $location = trim($_POST["location"]);
    $notes = trim($_POST["notes"]);
    $food_condition = $_POST["food_condition"];
    $urgency = $_POST["urgency"];
    $pickup_window = $_POST["pickup_window"];
    $donor_id = $_SESSION["user_id"];

    pg_query_params($conn,
        "INSERT INTO food_listings (donor_id, food_name, quantity, unit, expiry_date, location, notes, food_condition, urgency, pickup_window)
         VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10)",
        array($donor_id, $food_name, $quantity, $unit, $expiry_date, $location, $notes, $food_condition, $urgency, $pickup_window)
    );
}

// Handle listing cancellation (only if still available)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["cancel_listing_id"])) {
    pg_query_params($conn,
        "DELETE FROM food_listings WHERE id = $1 AND donor_id = $2 AND status = 'available'",
        array($_POST["cancel_listing_id"], $_SESSION["user_id"])
    );
}

// Get this donor's listings
$listings_result = pg_query_params($conn,
    "SELECT * FROM food_listings WHERE donor_id = $1 ORDER BY created_at DESC",
    array($_SESSION["user_id"])
);
$listings = pg_fetch_all($listings_result) ?: [];

// Get donor location
$loc = pg_query_params($conn,
    "SELECT location, latitude, longitude FROM users WHERE id = $1",
    array($_SESSION["user_id"])
);
$loc_row = pg_fetch_assoc($loc);

// Stats for the summary cards
$stats = pg_query_params($conn,
    "SELECT
        COUNT(*) AS total,
        COUNT(*) FILTER (WHERE status = 'available') AS available,
        COUNT(*) FILTER (WHERE status = 'claimed') AS claimed,
        COUNT(*) FILTER (WHERE status = 'collected') AS collected
     FROM food_listings WHERE donor_id = $1",
    array($_SESSION["user_id"])
);
$stats_row = pg_fetch_assoc($stats);

function statusBadgeClass($status) {
    $map = [
        'available' => 'live',
        'claimed' => 'booked-awaiting',
        'collected' => 'delivered'
    ];
    return $map[$status] ?? 'pending';
}

function conditionLabel($c) {
    return ucfirst(str_replace('_', ' ', $c ?? ''));
}

function pickupLabel($p) {
    $map = [
        'within_1h' => 'Within 1 hour',
        'within_2h' => 'Within 2 hours',
        'today' => 'Today (flexible)',
        'tomorrow_am' => 'Tomorrow morning',
        'tomorrow_pm' => 'Tomorrow afternoon'
    ];
    return $map[$p] ?? ucfirst(str_replace('_', ' ', $p ?? ''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5" />
    <title>Donor Dashboard - Food Connect</title>
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
        .btn-danger { background: #b91c1c; }
        .btn-danger:hover { background: #991b1b; }

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

        .form-card {
            background: #fafcff;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid #e9edf2;
            margin: 16px 0;
        }
        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 4px;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #dce2ea;
            border-radius: 12px;
            font-size: 0.95rem;
            background: white;
            font-family: inherit;
        }
        .form-group textarea { min-height: 70px; resize: vertical; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .input-combo { display: flex; gap: 8px; }
        .input-combo input { flex: 2; }
        .input-combo select { flex: 1; }

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

        .urgency-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 40px;
            font-size: 0.72rem;
            font-weight: 700;
        }
        .urgency-high   { background: #fee2e2; color: #991b1b; }
        .urgency-medium { background: #fef9c3; color: #854d0e; }
        .urgency-low    { background: #dcfce7; color: #166534; }

        .empty-state {
            text-align: center;
            padding: 40px 24px;
            color: #64748b;
        }
        .empty-state i { font-size: 2.5rem; margin-bottom: 12px; display: block; color: #cbd5e1; }
        .empty-state p { font-size: 0.95rem; }

        .flex-between {
            display: flex; flex-wrap: wrap;
            align-items: center; justify-content: space-between;
            gap: 12px;
        }
        .text-muted { color: #64748b; }

        @media (max-width: 600px) {
            .form-row { grid-template-columns: 1fr; }
            .dashboard-container { padding: 16px; }
            .input-combo { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="dashboard-container">

    <!-- Header -->
    <header class="app-header">
        <div class="logo-area">
            <div class="logo-icon"><i class="fas fa-seedling"></i></div>
            <div class="logo-text"><span>Food Connect</span></div>
        </div>
        <div class="header-actions">
            <span class="badge-role"><i class="fas fa-hand-holding-heart"></i> Donor</span>
            <span class="text-muted" style="font-size:0.85rem;">Welcome, <?php echo htmlspecialchars($_SESSION["user_name"]); ?></span>
            <?php include 'profile.php'; ?>
            <a href="logout.php" class="btn btn-outline btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats_row["total"]; ?></div>
            <div class="stat-label">Total donations</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats_row["available"]; ?></div>
            <div class="stat-label">Available</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats_row["claimed"]; ?></div>
            <div class="stat-label">Claimed</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats_row["collected"]; ?></div>
            <div class="stat-label">Collected</div>
        </div>
    </div>

    <!-- Location banner -->
    <?php if ($loc_row["latitude"] && $loc_row["longitude"]): ?>
        <div class="banner banner-success">
            <i class="fas fa-map-marker-alt"></i>
            <span>Exact pickup location is set — riders can find you on Google Maps. Coordinates: <?php echo $loc_row["latitude"]; ?>, <?php echo $loc_row["longitude"]; ?></span>
        </div>
    <?php else: ?>
        <div class="banner banner-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <span>No exact location set yet. Riders will use your text address instead.</span>
            <button class="btn btn-sm" onclick="getLocation()"><i class="fas fa-crosshairs"></i> Detect now</button>
        </div>
    <?php endif; ?>

    <!-- Add donation -->
    <div class="flex-between">
        <h3 class="section-title"><i class="fas fa-plus-circle"></i> Add a food donation</h3>
    </div>

    <div class="form-card">
        <form method="POST" action="donor-dashboard.php">
            <div class="form-row">
                <div class="form-group">
                    <label>Food name</label>
                    <input type="text" name="food_name" placeholder="e.g. Cooked rice, Tomatoes" required />
                </div>
                <div class="form-group">
                    <label>Quantity</label>
                    <div class="input-combo">
                        <input type="number" name="quantity" placeholder="e.g. 10" min="0.1" step="0.1" required />
                        <select name="unit" required>
                            <option value="">Unit</option>
                            <option value="kg">Kg</option>
                            <option value="litres">Litres</option>
                            <option value="portions">Portions</option>
                            <option value="boxes">Boxes</option>
                            <option value="bags">Bags</option>
                            <option value="pieces">Pieces</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Condition</label>
                    <select name="food_condition" required>
                        <option value="">Select condition</option>
                        <option value="freshly_cooked">Freshly cooked (today)</option>
                        <option value="fresh_produce">Fresh produce</option>
                        <option value="packaged">Packaged (within best-before)</option>
                        <option value="near_expiry">Near expiry (still safe)</option>
                        <option value="leftover">Leftover (same-day)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Urgency</label>
                    <select name="urgency" required>
                        <option value="high">High — expires soon / perishable</option>
                        <option value="medium" selected>Medium — within 24 hours</option>
                        <option value="low">Low — non-perishable / flexible</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Pickup window</label>
                    <select name="pickup_window" required>
                        <option value="">When can a rider collect?</option>
                        <option value="within_1h">Within 1 hour</option>
                        <option value="within_2h">Within 2 hours</option>
                        <option value="today">Today (flexible)</option>
                        <option value="tomorrow_am">Tomorrow morning</option>
                        <option value="tomorrow_pm">Tomorrow afternoon</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Expiry date</label>
                    <input type="date" name="expiry_date" required />
                </div>
            </div>

            <div class="form-group">
                <label>Pickup location</label>
                <input type="text" name="location" placeholder="e.g. Westlands, Nairobi" required />
            </div>

            <div class="form-group">
                <label>Additional notes</label>
                <textarea name="notes" placeholder="e.g. Fragile containers, call on arrival."></textarea>
            </div>

            <button type="submit" class="btn">Submit donation</button>
        </form>
    </div>

    <!-- My Donations -->
    <div class="flex-between">
        <h3 class="section-title"><i class="fas fa-list-ul"></i> My donations</h3>
        <span class="text-muted" style="font-size:0.85rem;"><?php echo count($listings); ?> listing<?php echo count($listings) !== 1 ? 's' : ''; ?></span>
    </div>

    <?php if (count($listings) === 0): ?>
        <div class="empty-state">
            <i class="fas fa-box-open"></i>
            <p>No active donations yet. Add one above to get started.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Food</th><th>Quantity</th><th>Condition</th>
                        <th>Urgency</th><th>Pickup window</th><th>Location</th>
                        <th>Status</th><th>Listed</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($listings as $row): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row["food_name"]); ?></strong></td>
                            <td><?php echo htmlspecialchars($row["quantity"] . " " . $row["unit"]); ?></td>
                            <td><?php echo htmlspecialchars(conditionLabel($row["food_condition"])); ?></td>
                            <td>
                                <?php $u = $row["urgency"] ?? 'medium'; ?>
                                <span class="urgency-tag urgency-<?php echo $u; ?>"><?php echo ucfirst($u); ?></span>
                            </td>
                            <td style="font-size:0.85rem;"><?php echo htmlspecialchars(pickupLabel($row["pickup_window"])); ?></td>
                            <td style="font-size:0.85rem;"><?php echo htmlspecialchars($row["location"]); ?></td>
                            <td><span class="badge badge-<?php echo statusBadgeClass($row["status"]); ?>"><?php echo ucfirst($row["status"]); ?></span></td>
                            <td style="font-size:0.85rem;"><?php echo date("d M Y", strtotime($row["created_at"])); ?></td>
                            <td>
                                <?php if ($row["status"] === "available"): ?>
                                    <form method="POST" action="donor-dashboard.php" onsubmit="return confirm('Cancel this donation?');">
                                        <input type="hidden" name="cancel_listing_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Cancel</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<!-- Hidden location form -->
<form method="POST" action="donor-dashboard.php" id="location-form">
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