<?php
include '../config.php';
session_start();

// Protect page - only admin can access
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] != "admin") {
    header("Location: login.php");
    exit();
}

// Handle user status update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["user_id"])) {
    $user_id = $_POST["user_id"];
    $new_status = $_POST["new_status"];

    pg_query_params($conn,
        "UPDATE users SET status = $1 WHERE id = $2",
        array($new_status, $user_id)
    );
}

// Handle listing removal by admin
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["remove_listing_id"])) {
    pg_query_params($conn,
        "DELETE FROM food_listings WHERE id = $1",
        array($_POST["remove_listing_id"])
    );
}

// Get all non-admin users
$users_result = pg_query($conn,
    "SELECT id, full_name, email, role, location, status, created_at 
     FROM users 
     WHERE role != 'admin' 
     ORDER BY created_at DESC"
);
$users = pg_fetch_all($users_result) ?: [];

// Get all food listings with donor name
$listings_result = pg_query($conn,
    "SELECT 
        food_listings.id,
        food_listings.food_name,
        food_listings.quantity,
        food_listings.unit,
        food_listings.food_condition,
        food_listings.urgency,
        food_listings.expiry_date,
        food_listings.location,
        food_listings.status,
        food_listings.created_at,
        users.full_name AS donor_name
     FROM food_listings
     JOIN users ON food_listings.donor_id = users.id
     ORDER BY food_listings.created_at DESC"
);
$listings = pg_fetch_all($listings_result) ?: [];

// Get all claims with listing and recipient details
$claims_result = pg_query($conn,
    "SELECT
        claims.id AS claim_id,
        claims.status AS claim_status,
        claims.pickup_date,
        claims.created_at,
        food_listings.food_name,
        food_listings.quantity,
        food_listings.unit,
        donor.full_name AS donor_name,
        recipient.full_name AS recipient_name,
        deliveries.status AS delivery_status,
        deliveries.delivered_at
     FROM claims
     JOIN food_listings ON claims.listing_id = food_listings.id
     JOIN users AS donor ON food_listings.donor_id = donor.id
     JOIN users AS recipient ON claims.recipient_id = recipient.id
     LEFT JOIN deliveries ON deliveries.claim_id = claims.id
     ORDER BY claims.created_at DESC"
);
$claims = pg_fetch_all($claims_result) ?: [];

// Impact analytics queries
// Total kg of food rescued (collected listings only)
$kg_result = pg_query($conn,
    "SELECT COALESCE(SUM(
        CASE WHEN unit IN ('kg', 'Kg') THEN quantity
             WHEN unit IN ('bags', 'Bags') THEN quantity * 50
             WHEN unit IN ('boxes', 'Boxes') THEN quantity * 10
             WHEN unit IN ('litres', 'Litres') THEN quantity
             ELSE quantity
        END
    ), 0) AS total_kg
     FROM food_listings WHERE status = 'collected'"
);
$kg_row = pg_fetch_assoc($kg_result);
$total_kg = round($kg_row["total_kg"], 1);

// Estimated meals (1 kg = ~4 portions, 1 portion = 1 meal)
$total_meals = round($total_kg * 4);

// Active users by role (approved only)
$role_result = pg_query($conn,
    "SELECT role, COUNT(*) AS count FROM users
     WHERE role != 'admin' AND status = 'approved'
     GROUP BY role"
);
$role_counts = ['donor' => 0, 'recipient' => 0, 'rider' => 0];
while ($r = pg_fetch_assoc($role_result)) {
    $role_counts[$r["role"]] = $r["count"];
}

// Top donors by number of collected listings
$top_donors_result = pg_query($conn,
    "SELECT users.full_name, COUNT(*) AS donations
     FROM food_listings
     JOIN users ON food_listings.donor_id = users.id
     WHERE food_listings.status = 'collected'
     GROUP BY users.full_name
     ORDER BY donations DESC
     LIMIT 5"
);
$top_donors = pg_fetch_all($top_donors_result) ?: [];

// Monthly donation trend (last 6 months)
$trend_result = pg_query($conn,
    "SELECT TO_CHAR(created_at, 'Mon YYYY') AS month,
            COUNT(*) AS listings,
            COUNT(*) FILTER (WHERE status = 'collected') AS collected
     FROM food_listings
     WHERE created_at >= NOW() - INTERVAL '6 months'
     GROUP BY TO_CHAR(created_at, 'Mon YYYY'), DATE_TRUNC('month', created_at)
     ORDER BY DATE_TRUNC('month', created_at) ASC"
);
$trend = pg_fetch_all($trend_result) ?: [];

// Stats
$total_users   = count($users);
$pending_users = count(array_filter($users, fn($u) => $u["status"] === "pending"));
$total_listings = count($listings);
$available_listings = count(array_filter($listings, fn($l) => $l["status"] === "available"));
$total_claims   = count($claims);
$delivered_count = count(array_filter($claims, fn($c) => $c["delivery_status"] === "delivered"));

function conditionLabel($c) {
    return ucfirst(str_replace('_', ' ', $c ?? '—'));
}

function urgencyBadge($u) {
    if (!$u) return '<span class="badge badge-grey">—</span>';
    $map = [
        'high'   => 'badge-urgency-high',
        'medium' => 'badge-urgency-medium',
        'low'    => 'badge-urgency-low'
    ];
    return '<span class="badge ' . ($map[$u] ?? '') . '">' . ucfirst($u) . '</span>';
}

function listingStatusBadge($s) {
    $map = [
        'available' => 'badge-live',
        'claimed'   => 'badge-claimed',
        'collected' => 'badge-delivered'
    ];
    return '<span class="badge ' . ($map[$s] ?? 'badge-grey') . '">' . ucfirst($s) . '</span>';
}

function claimStatusBadge($s) {
    $map = [
        'pending'   => 'badge-pending',
        'confirmed' => 'badge-claimed',
        'collected' => 'badge-delivered'
    ];
    return '<span class="badge ' . ($map[$s] ?? 'badge-grey') . '">' . ucfirst($s) . '</span>';
}

function deliveryStatusBadge($s) {
    if (!$s) return '<span class="badge badge-grey">No rider yet</span>';
    $map = [
        'assigned'  => 'badge-pending',
        'picked_up' => 'badge-claimed',
        'delivered' => 'badge-delivered'
    ];
    return '<span class="badge ' . ($map[$s] ?? 'badge-grey') . '">' . ucfirst(str_replace('_', ' ', $s)) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5" />
    <title>Admin Dashboard - Food Connect</title>
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
        .btn-danger { background: #b91c1c; border: none; color: white; }
        .btn-danger:hover { background: #991b1b; }
        .btn-warning { background: #b45309; border: none; color: white; }
        .btn-warning:hover { background: #92400e; }

        /* Stats */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: #f8fafc;
            border-radius: 18px;
            padding: 18px 20px;
            border-left: 5px solid #06392f;
        }
        .stat-card .stat-number { font-size: 2rem; font-weight: 700; line-height: 1.2; }
        .stat-card .stat-label { font-size: 0.85rem; color: #475569; }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            border-bottom: 2px solid #e9edf2;
            padding-bottom: 0;
        }
        .tab-btn {
            background: none;
            border: none;
            padding: 10px 20px;
            font-size: 0.9rem;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            display: inline-flex; align-items: center; gap: 8px;
            border-radius: 0;
            transition: color 0.15s;
        }
        .tab-btn:hover { color: #06392f; }
        .tab-btn.active {
            color: #06392f;
            border-bottom-color: #06392f;
        }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        /* Section title */
        .section-title {
            font-size: 1.15rem;
            font-weight: 600;
            margin: 0 0 14px;
            display: flex; align-items: center; gap: 10px;
        }

        /* Table */
        .table-wrap {
            overflow-x: auto;
            background: #fafcff;
            border-radius: 18px;
            border: 1px solid #e9edf2;
            padding: 4px 0;
        }
        table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
        th {
            text-align: left;
            padding: 13px 16px;
            background: #f1f5f9;
            font-weight: 600;
            color: #1e293b;
            white-space: nowrap;
        }
        td {
            padding: 13px 16px;
            border-top: 1px solid #ecf1f7;
            vertical-align: middle;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 40px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-live      { background: #dcfce7; color: #166534; }
        .badge-claimed   { background: #dbeafe; color: #1e40af; }
        .badge-delivered { background: #e0e7ff; color: #3730a3; }
        .badge-pending   { background: #fef9c3; color: #854d0e; }
        .badge-approved  { background: #dcfce7; color: #166534; }
        .badge-rejected  { background: #fee2e2; color: #991b1b; }
        .badge-grey      { background: #e9edf2; color: #475569; }
        .badge-urgency-high   { background: #fee2e2; color: #991b1b; }
        .badge-urgency-medium { background: #fef9c3; color: #854d0e; }
        .badge-urgency-low    { background: #dcfce7; color: #166534; }

        .role-tag {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 40px;
            font-size: 0.75rem;
            font-weight: 600;
            background: #e0e7ff;
            color: #3730a3;
        }
        .role-tag.donor     { background: #dcfce7; color: #166534; }
        .role-tag.recipient { background: #dbeafe; color: #1e40af; }
        .role-tag.rider     { background: #fef9c3; color: #854d0e; }

        .action-group { display: flex; gap: 6px; flex-wrap: wrap; }

        .empty-state {
            text-align: center;
            padding: 40px 24px;
            color: #64748b;
        }
        .empty-state i { font-size: 2.5rem; margin-bottom: 12px; display: block; color: #cbd5e1; }
        .empty-state p { font-size: 0.95rem; }

        /* Impact analytics */
        .impact-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }
        .impact-card {
            border-radius: 18px;
            padding: 18px 20px;
            text-align: center;
        }
        .impact-card i { font-size: 1.6rem; margin-bottom: 8px; display: block; }
        .impact-number { font-size: 1.8rem; font-weight: 700; line-height: 1.2; }
        .impact-label  { font-size: 0.8rem; margin-top: 4px; }
        .impact-green  { background: #dcfce7; color: #166534; }
        .impact-blue   { background: #dbeafe; color: #1e40af; }
        .impact-purple { background: #e0e7ff; color: #3730a3; }
        .impact-yellow { background: #fef9c3; color: #854d0e; }
        .impact-orange { background: #ffedd5; color: #9a3412; }
        .impact-teal   { background: #ccfbf1; color: #0f766e; }

        .impact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 800px) {
            .impact-grid { grid-template-columns: 1fr; }
        }

        .text-muted { color: #64748b; }
        .flex-between {
            display: flex; flex-wrap: wrap;
            align-items: center; justify-content: space-between;
            gap: 12px; margin-bottom: 14px;
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
            <div class="logo-icon"><i class="fas fa-seedling"></i></div>
            <div class="logo-text"><span>Food Connect</span></div>
        </div>
        <div class="header-actions">
            <span class="badge-role"><i class="fas fa-user-shield"></i> Admin</span>
            <span class="text-muted" style="font-size:0.85rem;">Welcome, <?php echo htmlspecialchars($_SESSION["user_name"]); ?></span>
            <a href="logout.php" class="btn btn-outline btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-number"><?php echo $total_users; ?></div>
            <div class="stat-label">Total users</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $pending_users; ?></div>
            <div class="stat-label">Pending approval</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $total_listings; ?></div>
            <div class="stat-label">Total listings</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $available_listings; ?></div>
            <div class="stat-label">Available now</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $total_claims; ?></div>
            <div class="stat-label">Total claims</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $delivered_count; ?></div>
            <div class="stat-label">Delivered</div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('users', this)">
            <i class="fas fa-users"></i> Users
            <?php if ($pending_users > 0): ?>
                <span class="badge badge-pending" style="font-size:0.7rem; padding:2px 8px;"><?php echo $pending_users; ?></span>
            <?php endif; ?>
        </button>
        <button class="tab-btn" onclick="switchTab('listings', this)">
            <i class="fas fa-box-open"></i> Food listings
        </button>
        <button class="tab-btn" onclick="switchTab('claims', this)">
            <i class="fas fa-hand-holding-heart"></i> Claims & deliveries
        </button>
        <button class="tab-btn" onclick="switchTab('impact', this)">
            <i class="fas fa-chart-bar"></i> Impact analytics
        </button>
    </div>

    <!-- TAB: Users -->
    <div class="tab-panel active" id="tab-users">
        <?php if (count($users) === 0): ?>
            <div class="empty-state">
                <i class="fas fa-user-slash"></i>
                <p>No users registered yet.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th><th>Full name</th><th>Email</th><th>Role</th>
                            <th>Location</th><th>Registered</th><th>Status</th><th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $row): ?>
                            <tr>
                                <td class="text-muted" style="font-size:0.82rem;"><?php echo $row["id"]; ?></td>
                                <td><strong><?php echo htmlspecialchars($row["full_name"]); ?></strong></td>
                                <td class="text-muted" style="font-size:0.85rem;"><?php echo htmlspecialchars($row["email"]); ?></td>
                                <td>
                                    <span class="role-tag <?php echo strtolower($row['role']); ?>">
                                        <?php echo ucfirst($row["role"]); ?>
                                    </span>
                                </td>
                                <td style="font-size:0.85rem;"><?php echo htmlspecialchars($row["location"] ?? '—'); ?></td>
                                <td style="font-size:0.85rem;"><?php echo date("d M Y", strtotime($row["created_at"])); ?></td>
                                <td>
                                    <?php if ($row["status"] === "approved"): ?>
                                        <span class="badge badge-approved">Approved</span>
                                    <?php elseif ($row["status"] === "pending"): ?>
                                        <span class="badge badge-pending">Pending</span>
                                    <?php else: ?>
                                        <span class="badge badge-rejected">Rejected</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-group">
                                        <?php if ($row["status"] === "pending"): ?>
                                            <form method="POST" action="admin-dashboard.php">
                                                <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="new_status" value="approved">
                                                <button type="submit" class="btn btn-sm"><i class="fas fa-check"></i> Approve</button>
                                            </form>
                                            <form method="POST" action="admin-dashboard.php">
                                                <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="new_status" value="rejected">
                                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-times"></i> Reject</button>
                                            </form>
                                        <?php elseif ($row["status"] === "approved"): ?>
                                            <form method="POST" action="admin-dashboard.php">
                                                <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="new_status" value="rejected">
                                                <button type="submit" class="btn btn-sm btn-warning"><i class="fas fa-ban"></i> Revoke</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" action="admin-dashboard.php">
                                                <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="new_status" value="approved">
                                                <button type="submit" class="btn btn-sm"><i class="fas fa-redo"></i> Re-approve</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB: Food Listings -->
    <div class="tab-panel" id="tab-listings">
        <?php if (count($listings) === 0): ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <p>No food listings yet.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th><th>Food</th><th>Quantity</th><th>Condition</th>
                            <th>Urgency</th><th>Expiry</th><th>Location</th>
                            <th>Donor</th><th>Status</th><th>Listed</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($listings as $row): ?>
                            <tr>
                                <td class="text-muted" style="font-size:0.82rem;"><?php echo $row["id"]; ?></td>
                                <td><strong><?php echo htmlspecialchars($row["food_name"]); ?></strong></td>
                                <td><?php echo htmlspecialchars($row["quantity"] . " " . $row["unit"]); ?></td>
                                <td style="font-size:0.85rem;"><?php echo htmlspecialchars(conditionLabel($row["food_condition"])); ?></td>
                                <td><?php echo urgencyBadge($row["urgency"]); ?></td>
                                <td style="font-size:0.85rem;">
                                    <?php echo $row["expiry_date"] ? date("d M Y", strtotime($row["expiry_date"])) : '—'; ?>
                                </td>
                                <td style="font-size:0.85rem;"><?php echo htmlspecialchars($row["location"] ?? '—'); ?></td>
                                <td style="font-size:0.85rem;"><?php echo htmlspecialchars($row["donor_name"]); ?></td>
                                <td><?php echo listingStatusBadge($row["status"]); ?></td>
                                <td style="font-size:0.85rem;"><?php echo date("d M Y", strtotime($row["created_at"])); ?></td>
                                <td>
                                    <?php if ($row["status"] === "available"): ?>
                                        <form method="POST" action="admin-dashboard.php"
                                              onsubmit="return confirm('Remove this listing?');">
                                            <input type="hidden" name="remove_listing_id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i> Remove
                                            </button>
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

    <!-- TAB: Claims & Deliveries -->
    <div class="tab-panel" id="tab-claims">
        <?php if (count($claims) === 0): ?>
            <div class="empty-state">
                <i class="fas fa-hand-holding-heart"></i>
                <p>No claims have been made yet.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th><th>Food</th><th>Quantity</th><th>Donor</th>
                            <th>Recipient</th><th>Pickup date</th>
                            <th>Claim status</th><th>Delivery status</th><th>Delivered at</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($claims as $row): ?>
                            <tr>
                                <td class="text-muted" style="font-size:0.82rem;"><?php echo $row["claim_id"]; ?></td>
                                <td><strong><?php echo htmlspecialchars($row["food_name"]); ?></strong></td>
                                <td><?php echo htmlspecialchars($row["quantity"] . " " . $row["unit"]); ?></td>
                                <td style="font-size:0.85rem;"><?php echo htmlspecialchars($row["donor_name"]); ?></td>
                                <td style="font-size:0.85rem;"><?php echo htmlspecialchars($row["recipient_name"]); ?></td>
                                <td style="font-size:0.85rem;">
                                    <?php echo $row["pickup_date"] ? date("d M Y", strtotime($row["pickup_date"])) : '—'; ?>
                                </td>
                                <td><?php echo claimStatusBadge($row["claim_status"]); ?></td>
                                <td><?php echo deliveryStatusBadge($row["delivery_status"]); ?></td>
                                <td style="font-size:0.85rem;">
                                    <?php echo $row["delivered_at"] ? date("d M Y H:i", strtotime($row["delivered_at"])) : '—'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB: Impact Analytics -->
    <div class="tab-panel" id="tab-impact">

        <!-- Impact stat cards -->
        <div class="impact-stats">
            <div class="impact-card impact-green">
                <i class="fas fa-weight-hanging"></i>
                <div class="impact-number"><?php echo number_format($total_kg, 1); ?> kg</div>
                <div class="impact-label">Food rescued</div>
            </div>
            <div class="impact-card impact-blue">
                <i class="fas fa-utensils"></i>
                <div class="impact-number"><?php echo number_format($total_meals); ?></div>
                <div class="impact-label">Estimated meals provided</div>
            </div>
            <div class="impact-card impact-purple">
                <i class="fas fa-hand-holding-heart"></i>
                <div class="impact-number"><?php echo $role_counts["donor"]; ?></div>
                <div class="impact-label">Active donors</div>
            </div>
            <div class="impact-card impact-yellow">
                <i class="fas fa-users"></i>
                <div class="impact-number"><?php echo $role_counts["recipient"]; ?></div>
                <div class="impact-label">Active recipients</div>
            </div>
            <div class="impact-card impact-orange">
                <i class="fas fa-motorcycle"></i>
                <div class="impact-number"><?php echo $role_counts["rider"]; ?></div>
                <div class="impact-label">Active riders</div>
            </div>
            <div class="impact-card impact-teal">
                <i class="fas fa-truck"></i>
                <div class="impact-number"><?php echo $delivered_count; ?></div>
                <div class="impact-label">Deliveries completed</div>
            </div>
        </div>

        <div class="impact-grid">

            <!-- Top donors -->
            <div>
                <h4 class="section-title" style="margin-top:0;"><i class="fas fa-star"></i> Top donors by deliveries</h4>
                <?php if (count($top_donors) === 0): ?>
                    <div class="empty-state" style="padding:20px;">
                        <i class="fas fa-medal"></i>
                        <p>No completed deliveries yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr><th>Donor</th><th>Completed donations</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_donors as $i => $row): ?>
                                    <tr>
                                        <td>
                                            <?php if ($i === 0): ?>
                                                <i class="fas fa-trophy" style="color:#f59e0b; margin-right:6px;"></i>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($row["full_name"]); ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-approved"><?php echo $row["donations"]; ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Monthly trend -->
            <div>
                <h4 class="section-title" style="margin-top:0;"><i class="fas fa-calendar-alt"></i> Monthly trend (last 6 months)</h4>
                <?php if (count($trend) === 0): ?>
                    <div class="empty-state" style="padding:20px;">
                        <i class="fas fa-chart-line"></i>
                        <p>Not enough data yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr><th>Month</th><th>Listings added</th><th>Collected</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trend as $row): ?>
                                    <tr>
                                        <td style="font-weight:600;"><?php echo htmlspecialchars($row["month"]); ?></td>
                                        <td><?php echo $row["listings"]; ?></td>
                                        <td>
                                            <span class="badge badge-delivered"><?php echo $row["collected"]; ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <p class="text-muted" style="font-size:0.8rem; margin-top:18px;">
            <i class="fas fa-info-circle"></i>
            Meal estimate based on 4 portions per kg. Kg equivalents used: 1 bag = 50kg, 1 box = 10kg, portions/pieces counted as 0.25kg each.
        </p>

    </div>

</div>

<script>
function switchTab(tabName, btn) {
    // Hide all panels
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    // Deactivate all tab buttons
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    // Show selected panel and activate button
    document.getElementById('tab-' + tabName).classList.add('active');
    btn.classList.add('active');
}
</script>
</body>
</html>