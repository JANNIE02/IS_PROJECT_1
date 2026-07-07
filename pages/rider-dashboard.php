<?php
include '../config.php';
session_start();

// Protect page - only riders can access
if (!isset($_SESSION["user_id"]) || !in_array("rider", $_SESSION["effective_roles"] ?? [])) {
    header("Location: login.php");
    exit();
}

// Rider accepts an available delivery (self-assign)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["claim_id_to_accept"])) {
    $claim_id = $_POST["claim_id_to_accept"];
    $rider_id = $_SESSION["user_id"];

    // Guard against two riders accepting the same claim at the same time:
    // only insert if no delivery already exists for this claim.
    pg_query_params($conn,
        "INSERT INTO deliveries (claim_id, rider_id, status)
         SELECT $1, $2, 'assigned'
         WHERE NOT EXISTS (SELECT 1 FROM deliveries WHERE claim_id = $1)",
        array($claim_id, $rider_id)
    );

    // Move the claim from pending to confirmed now that a rider has it
    pg_query_params($conn,
        "UPDATE claims SET status = 'confirmed' WHERE id = $1 AND status = 'pending'",
        array($claim_id)
    );
}

// Handle status update when rider marks picked up or delivered
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delivery_id"])) {
    $delivery_id = $_POST["delivery_id"];
    $new_status = $_POST["new_status"];

    if ($new_status == "delivered") {
        pg_query_params($conn,
            "UPDATE deliveries SET status = $1, delivered_at = CURRENT_TIMESTAMP WHERE id = $2",
            array($new_status, $delivery_id)
        );

        $delivery = pg_query_params($conn,
            "SELECT claim_id FROM deliveries WHERE id = $1",
            array($delivery_id)
        );
        $delivery_row = pg_fetch_assoc($delivery);
        $claim_id = $delivery_row["claim_id"];

        pg_query_params($conn,
            "UPDATE claims SET status = 'collected' WHERE id = $1",
            array($claim_id)
        );

        $claim = pg_query_params($conn,
            "SELECT listing_id FROM claims WHERE id = $1",
            array($claim_id)
        );
        $claim_row = pg_fetch_assoc($claim);
        pg_query_params($conn,
            "UPDATE food_listings SET status = 'collected' WHERE id = $1",
            array($claim_row["listing_id"])
        );

    } else {
        pg_query_params($conn,
            "UPDATE deliveries SET status = $1 WHERE id = $2",
            array($new_status, $delivery_id)
        );
    }
}

// AVAILABLE deliveries: claims with no delivery row yet (anyone can accept these)
$available_result = pg_query(
    $conn,
    "SELECT
        claims.id AS claim_id,
        claims.pickup_date,
        food_listings.food_name,
        food_listings.quantity,
        food_listings.unit,
        food_listings.notes,
        donor.full_name AS donor_name,
        donor.location AS pickup_location,
        recipient.full_name AS recipient_name,
        recipient.location AS dropoff_location
     FROM claims
     JOIN food_listings ON claims.listing_id = food_listings.id
     JOIN users AS donor ON food_listings.donor_id = donor.id
     JOIN users AS recipient ON claims.recipient_id = recipient.id
     WHERE claims.status = 'pending'
       AND NOT EXISTS (SELECT 1 FROM deliveries WHERE deliveries.claim_id = claims.id)
     ORDER BY claims.created_at ASC"
);
$available = pg_fetch_all($available_result) ?: [];

// MY deliveries: ones this rider has already accepted
$deliveries_result = pg_query_params($conn,
    "SELECT 
        deliveries.id AS delivery_id,
        deliveries.status AS delivery_status,
        deliveries.assigned_at,
        deliveries.delivered_at,
        claims.pickup_date,
        claims.recipient_phone,
        food_listings.food_name,
        food_listings.quantity,
        food_listings.unit,
        food_listings.notes,
        donor.full_name AS donor_name,
        donor.location AS pickup_location,
        donor.latitude AS donor_lat,
        donor.longitude AS donor_lng,
        recipient.full_name AS recipient_name,
        recipient.location AS dropoff_location,
        recipient.latitude AS recipient_lat,
        recipient.longitude AS recipient_lng
     FROM deliveries
     JOIN claims ON deliveries.claim_id = claims.id
     JOIN food_listings ON claims.listing_id = food_listings.id
     JOIN users AS donor ON food_listings.donor_id = donor.id
     JOIN users AS recipient ON claims.recipient_id = recipient.id
     WHERE deliveries.rider_id = $1
     ORDER BY deliveries.assigned_at DESC",
    array($_SESSION["user_id"])
);
$deliveries = pg_fetch_all($deliveries_result) ?: [];

$ready_count = 0;
$delivered_count = 0;
foreach ($deliveries as $d) {
    if ($d["delivery_status"] === "assigned" || $d["delivery_status"] === "picked_up") $ready_count++;
    if ($d["delivery_status"] === "delivered") $delivered_count++;
}

function statusBadge($status) {
    $map = [
        'assigned' => ['pending', 'Assigned'],
        'picked_up' => ['claimed', 'Picked up'],
        'delivered' => ['delivered', 'Delivered']
    ];
    return $map[$status] ?? ['pending', ucfirst($status)];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5" />
    <title>Rider Dashboard - Food Connect</title>
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
        .btn-secondary { background: #475569; }
        .btn-secondary:hover { background: #334155; }
        .btn-accept { background: #0f6e56; }
        .btn-accept:hover { background: #085041; }

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
        .section-sub {
            font-size: 0.85rem;
            color: #64748b;
            margin: -8px 0 14px;
        }

        .delivery-card {
            background: #fafcff;
            border: 1px solid #e9edf2;
            border-radius: 18px;
            padding: 20px 22px;
            margin: 14px 0;
        }
        .delivery-card.available {
            border: 1.5px dashed #cbd5e1;
            background: #fcfdff;
        }
        .delivery-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #ecf1f7;
        }
        .delivery-card-title { font-weight: 600; font-size: 1.05rem; }

        .leg-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .leg-label {
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            color: #94a3b8;
            text-transform: uppercase;
            margin-bottom: 8px;
            display: flex; align-items: center; gap: 6px;
        }
        .leg-row { font-size: 0.9rem; margin-bottom: 4px; }
        .leg-row strong { color: #334155; }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 40px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-pending   { background: #fef9c3; color: #854d0e; }
        .badge-claimed   { background: #dbeafe; color: #1e40af; }
        .badge-delivered { background: #dcfce7; color: #166534; }
        .badge-open      { background: #e0e7ff; color: #3730a3; }

        .empty-state {
            text-align: center;
            padding: 32px 24px;
            color: #64748b;
        }
        .empty-state i { font-size: 2.2rem; margin-bottom: 10px; display: block; color: #cbd5e1; }
        .empty-state p { font-size: 0.9rem; }

        .text-muted { color: #64748b; }

        @media (max-width: 700px) {
            .leg-grid { grid-template-columns: 1fr; }
            .dashboard-container { padding: 16px; }
        }
    </style>
</head>
<body>
<div class="dashboard-container">

    <!-- Header -->
    <header class="app-header">
        <div class="logo-area">
            <div class="logo-icon"><i class="fas fa-motorcycle"></i></div>
            <div class="logo-text"><span>Food Connect</span></div>
        </div>
        <div class="header-actions">
            <span class="badge-role"><i class="fas fa-user-circle"></i> Rider</span>
            <span class="text-muted" style="font-size:0.85rem;">Welcome, <?php echo htmlspecialchars($_SESSION["user_name"]); ?></span>
           <?php if (count($_SESSION["effective_roles"] ?? []) > 1): ?>
    <?php if (in_array("donor", $_SESSION["effective_roles"]) && basename($_SERVER['PHP_SELF']) !== 'donor-dashboard.php'): ?>
        <a href="donor-dashboard.php" class="btn btn-outline btn-sm"><i class="fas fa-hand-holding-heart"></i> Donor view</a>
    <?php endif; ?>
    <?php if (in_array("recipient", $_SESSION["effective_roles"]) && basename($_SERVER['PHP_SELF']) !== 'recipient-dashboard.php'): ?>
        <a href="recipient-dashboard.php" class="btn btn-outline btn-sm"><i class="fas fa-hands-helping"></i> Recipient view</a>
    <?php endif; ?>
    <?php if (in_array("rider", $_SESSION["effective_roles"]) && basename($_SERVER['PHP_SELF']) !== 'rider-dashboard.php'): ?>
        <a href="rider-dashboard.php" class="btn btn-outline btn-sm"><i class="fas fa-motorcycle"></i> Rider view</a>
    <?php endif; ?>
<?php endif; ?>
            <?php include 'profile.php'; ?>
            <a href="logout.php" class="btn btn-outline btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <!-- Statistics -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-number"><?php echo count($available); ?></div>
            <div class="stat-label">Open deliveries</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $ready_count; ?></div>
            <div class="stat-label">My active jobs</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $delivered_count; ?></div>
            <div class="stat-label">Completed</div>
        </div>
    </div>

    <!-- AVAILABLE: claims with no rider yet, anyone can accept -->
    <h3 class="section-title"><i class="fas fa-inbox"></i> Available deliveries</h3>
    <p class="section-sub">These are confirmed claims waiting for a rider. First to accept gets the job.</p>

    <?php if (count($available) === 0): ?>
        <div class="empty-state">
            <i class="fas fa-check-circle"></i>
            <p>No open deliveries right now. Check back soon.</p>
        </div>
    <?php else: ?>
        <?php foreach ($available as $row): ?>
            <div class="delivery-card available">
                <div class="delivery-card-header">
                    <div class="delivery-card-title">
                        <?php echo htmlspecialchars($row["food_name"]); ?> —
                        <?php echo htmlspecialchars($row["quantity"] . " " . $row["unit"]); ?>
                    </div>
                    <span class="badge badge-open"><i class="fas fa-hourglass-half"></i> Unassigned</span>
                </div>

                <div class="leg-grid">
                    <div>
                        <div class="leg-label"><i class="fas fa-warehouse"></i> Pickup</div>
                        <p class="leg-row"><strong>Donor:</strong> <?php echo htmlspecialchars($row["donor_name"]); ?></p>
                        <p class="leg-row"><strong>Location:</strong> <?php echo htmlspecialchars($row["pickup_location"]); ?></p>
                        <?php if ($row["notes"]): ?>
                            <p class="leg-row"><strong>Notes:</strong> <?php echo htmlspecialchars($row["notes"]); ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="leg-label"><i class="fas fa-flag-checkered"></i> Drop off</div>
                        <p class="leg-row"><strong>Recipient:</strong> <?php echo htmlspecialchars($row["recipient_name"]); ?></p>
                        <p class="leg-row"><strong>Location:</strong> <?php echo htmlspecialchars($row["dropoff_location"]); ?></p>
                        <?php if ($row["pickup_date"]): ?>
                            <p class="leg-row"><strong>Requested for:</strong> <?php echo date("d M Y", strtotime($row["pickup_date"])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="margin-top:16px;">
                    <form method="POST" action="rider-dashboard.php">
                        <input type="hidden" name="claim_id_to_accept" value="<?php echo $row['claim_id']; ?>">
                        <button type="submit" class="btn btn-accept"><i class="fas fa-hand-paper"></i> Accept this delivery</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- MY DELIVERIES: ones this rider has accepted -->
    <h3 class="section-title"><i class="fas fa-truck"></i> My deliveries</h3>

    <?php if (count($deliveries) === 0): ?>
        <div class="empty-state">
            <i class="fas fa-route"></i>
            <p>You haven't accepted any deliveries yet.</p>
        </div>
    <?php else: ?>
        <?php foreach ($deliveries as $row): ?>
            <?php list($badgeClass, $badgeLabel) = statusBadge($row["delivery_status"]); ?>
            <div class="delivery-card">
                <div class="delivery-card-header">
                    <div class="delivery-card-title">
                        <?php echo htmlspecialchars($row["food_name"]); ?> —
                        <?php echo htmlspecialchars($row["quantity"] . " " . $row["unit"]); ?>
                    </div>
                    <span class="badge badge-<?php echo $badgeClass; ?>"><?php echo $badgeLabel; ?></span>
                </div>

                <div class="leg-grid">
                    <div>
                        <div class="leg-label"><i class="fas fa-warehouse"></i> Pickup</div>
                        <p class="leg-row"><strong>Donor:</strong> <?php echo htmlspecialchars($row["donor_name"]); ?></p>
                        <p class="leg-row"><strong>Location:</strong> <?php echo htmlspecialchars($row["pickup_location"]); ?></p>
                        <?php if ($row["pickup_date"]): ?>
                            <p class="leg-row"><strong>Pickup date:</strong> <?php echo date("d M Y", strtotime($row["pickup_date"])); ?></p>
                        <?php endif; ?>
                        <?php if ($row["notes"]): ?>
                            <p class="leg-row"><strong>Notes:</strong> <?php echo htmlspecialchars($row["notes"]); ?></p>
                        <?php endif; ?>
                        <?php if ($row["donor_lat"] && $row["donor_lng"]): ?>
                            <a href="https://www.google.com/maps?q=<?php echo $row['donor_lat']; ?>,<?php echo $row['donor_lng']; ?>"
                               target="_blank" class="btn btn-outline btn-sm" style="margin-top:8px;">
                               <i class="fas fa-location-arrow"></i> Open exact pickup
                            </a>
                        <?php else: ?>
                            <a href="https://www.google.com/maps/search/<?php echo urlencode($row['pickup_location']); ?>"
                               target="_blank" class="btn btn-outline btn-sm" style="margin-top:8px;">
                               <i class="fas fa-search-location"></i> Search pickup
                            </a>
                        <?php endif; ?>
                    </div>

                    <div>
                        <div class="leg-label"><i class="fas fa-flag-checkered"></i> Drop off</div>
                        <p class="leg-row"><strong>Recipient:</strong> <?php echo htmlspecialchars($row["recipient_name"]); ?></p>
                        <p class="leg-row"><strong>Location:</strong> <?php echo htmlspecialchars($row["dropoff_location"]); ?></p>
                        <?php if ($row["recipient_phone"]): ?>
                            <p class="leg-row">
                                <strong>Phone:</strong>
                                <a href="tel:<?php echo htmlspecialchars($row['recipient_phone']); ?>"><?php echo htmlspecialchars($row['recipient_phone']); ?></a>
                            </p>
                        <?php endif; ?>
                        <?php if ($row["delivered_at"]): ?>
                            <p class="leg-row"><strong>Delivered at:</strong> <?php echo date("d M Y H:i", strtotime($row["delivered_at"])); ?></p>
                        <?php endif; ?>
                        <?php if ($row["recipient_lat"] && $row["recipient_lng"]): ?>
                            <a href="https://www.google.com/maps?q=<?php echo $row['recipient_lat']; ?>,<?php echo $row['recipient_lng']; ?>"
                               target="_blank" class="btn btn-outline btn-sm" style="margin-top:8px;">
                               <i class="fas fa-location-arrow"></i> Open exact dropoff
                            </a>
                        <?php else: ?>
                            <a href="https://www.google.com/maps/search/<?php echo urlencode($row['dropoff_location']); ?>"
                               target="_blank" class="btn btn-outline btn-sm" style="margin-top:8px;">
                               <i class="fas fa-search-location"></i> Search dropoff
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="margin-top:16px;">
                    <?php if ($row["delivery_status"] === "assigned"): ?>
                        <form method="POST" action="rider-dashboard.php" style="display:inline;">
                            <input type="hidden" name="delivery_id" value="<?php echo $row['delivery_id']; ?>">
                            <input type="hidden" name="new_status" value="picked_up">
                            <button type="submit" class="btn btn-secondary"><i class="fas fa-box"></i> Mark as picked up</button>
                        </form>
                    <?php elseif ($row["delivery_status"] === "picked_up"): ?>
                        <form method="POST" action="rider-dashboard.php" style="display:inline;">
                            <input type="hidden" name="delivery_id" value="<?php echo $row['delivery_id']; ?>">
                            <input type="hidden" name="new_status" value="delivered">
                            <button type="submit" class="btn"><i class="fas fa-check"></i> Mark as delivered</button>
                        </form>
                    <?php else: ?>
                        <span style="color:#166534; font-weight:600;"><i class="fas fa-check-circle"></i> Delivery complete</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>
</body>
</html>