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

    $user_lookup = pg_query_params($conn,
        "SELECT full_name, email FROM users WHERE id = $1",
        array($user_id)
    );
    $user_row = pg_fetch_assoc($user_lookup);

    pg_query_params($conn,
        "UPDATE users SET status = $1 WHERE id = $2",
        array($new_status, $user_id)
    );

    if ($new_status === "approved" && $user_row) {
        require_once 'mail.php';
        sendApprovalMail($user_row["email"], $user_row["full_name"]);
    }
}

// Handle listing removal by admin
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["remove_listing_id"])) {
    pg_query_params($conn,
        "DELETE FROM food_listings WHERE id = $1",
        array($_POST["remove_listing_id"])
    );
}

// Handle extra-roles grant/revoke
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_extra_roles_user_id"])) {
    $target_user_id = $_POST["update_extra_roles_user_id"];
    $new_roles = $_POST["extra_roles"] ?? [];

    pg_query_params($conn, "DELETE FROM user_extra_roles WHERE user_id = $1", array($target_user_id));
    foreach ($new_roles as $r) {
        if (in_array($r, ['donor', 'recipient', 'rider'])) {
            pg_query_params($conn,
                "INSERT INTO user_extra_roles (user_id, role, granted_by) VALUES ($1, $2, $3)",
                array($target_user_id, $r, $_SESSION["user_id"])
            );
        }
    }
}

// Get all non-admin users, along with any extra roles granted to them
$users_result = pg_query($conn,
    "SELECT u.id, u.full_name, u.email, u.role, u.location, u.status, u.created_at, u.verification_doc,
            COALESCE(ARRAY_AGG(er.role) FILTER (WHERE er.role IS NOT NULL), '{}') AS extra_roles
     FROM users u
     LEFT JOIN user_extra_roles er ON er.user_id = u.id
     WHERE u.role != 'admin'
     GROUP BY u.id
     ORDER BY u.created_at DESC"
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

// ============ IMPACT ANALYTICS QUERIES ============

// Total kg of food rescued (collected listings only)
// Any unit not explicitly listed falls back to 0.25kg (matches disclaimer: pieces/portions/packs ≈ 0.25kg)
$kg_result = pg_query($conn,
    "SELECT COALESCE(SUM(
        CASE WHEN unit IN ('kg', 'Kg', 'KG') THEN quantity
             WHEN unit IN ('bags', 'Bags') THEN quantity * 50
             WHEN unit IN ('boxes', 'Boxes') THEN quantity * 10
             WHEN unit IN ('litres', 'Litres', 'liters', 'Liters') THEN quantity
             WHEN unit IN ('pieces', 'Pieces', 'portions', 'Portions', 'packs', 'Packs', 'pcs') THEN quantity * 0.25
             ELSE quantity * 0.25
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

// Collected listings with no matching completed delivery (direct pickups / skipped rider step)
$recon_result = pg_query($conn,
    "SELECT COUNT(DISTINCT food_listings.id) AS cnt
     FROM food_listings
     LEFT JOIN claims ON claims.listing_id = food_listings.id
     LEFT JOIN deliveries ON deliveries.claim_id = claims.id
     WHERE food_listings.status = 'collected'
       AND (deliveries.status IS DISTINCT FROM 'delivered')"
);
$recon_row = pg_fetch_assoc($recon_result);
$collected_without_delivery = (int)($recon_row["cnt"] ?? 0);

// Monthly donation trend (last 6 months), zero-filled so quiet months still show a bar
$trend_result = pg_query($conn,
    "SELECT TO_CHAR(created_at, 'Mon YYYY') AS month,
            DATE_TRUNC('month', created_at) AS month_start,
            COUNT(*) AS listings,
            COUNT(*) FILTER (WHERE status = 'collected') AS collected
     FROM food_listings
     WHERE created_at >= NOW() - INTERVAL '6 months'
     GROUP BY TO_CHAR(created_at, 'Mon YYYY'), DATE_TRUNC('month', created_at)
     ORDER BY DATE_TRUNC('month', created_at) ASC"
);
$trend_raw = pg_fetch_all($trend_result) ?: [];

$trend_by_key = [];
foreach ($trend_raw as $row) {
    $key = date('Y-m', strtotime($row['month_start']));
    $trend_by_key[$key] = $row;
}

$trend = [];
for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime("-$i months", strtotime(date('Y-m-01')));
    $key = date('Y-m', $ts);
    if (isset($trend_by_key[$key])) {
        $trend[] = [
            'month'     => $trend_by_key[$key]['month'],
            'listings'  => (int)$trend_by_key[$key]['listings'],
            'collected' => (int)$trend_by_key[$key]['collected'],
        ];
    } else {
        $trend[] = [
            'month'     => date('M Y', $ts),
            'listings'  => 0,
            'collected' => 0,
        ];
    }
}

// Feedback submissions
$feedback_result = pg_query($conn,
    "SELECT id, name, role, email, message, created_at FROM feedback ORDER BY created_at DESC"
);
$feedback_rows = pg_fetch_all($feedback_result) ?: [];
$total_feedback = count($feedback_rows);

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

// Parse a Postgres text[] array literal like {donor,rider} into a PHP array
function parsePgArray($pgArray) {
    $pgArray = trim($pgArray, '{}');
    return $pgArray === '' ? [] : explode(',', $pgArray);
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

        .section-title {
            font-size: 1.15rem;
            font-weight: 600;
            margin: 0 0 14px;
            display: flex; align-items: center; gap: 10px;
        }

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
            margin: 2px 4px 2px 0;
        }
        .role-tag.donor     { background: #dcfce7; color: #166534; }
        .role-tag.recipient { background: #dbeafe; color: #1e40af; }
        .role-tag.rider     { background: #fef9c3; color: #854d0e; }
        .role-tag.admin     { background: #ede9fe; color: #5b21b6; }
        .role-tag.visitor   { background: #e9edf2; color: #475569; }

        .action-group { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }

        .extra-roles-form {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px dashed #e9edf2;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }
        .extra-roles-form label {
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: #475569;
        }

        .empty-state {
            text-align: center;
            padding: 40px 24px;
            color: #64748b;
        }
        .empty-state i { font-size: 2.5rem; margin-bottom: 12px; display: block; color: #cbd5e1; }
        .empty-state p { font-size: 0.95rem; }

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

        /* --- Charts --- */
        .chart-row {
            display: grid;
            grid-template-columns: 1fr 1.4fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        @media (max-width: 800px) { .chart-row { grid-template-columns: 1fr; } }

        .chart-card {
            background: #fafcff;
            border: 1px solid #e9edf2;
            border-radius: 18px;
            padding: 20px;
        }
        .chart-card h4 { margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }

        .donut-legend { display: flex; flex-direction: column; gap: 10px; margin-top: 14px; }
        .donut-legend-item { display: flex; align-items: center; gap: 8px; font-size: 0.85rem; }
        .legend-dot { width: 12px; height: 12px; border-radius: 50%; display: inline-block; }

        .bar-chart { display: flex; align-items: flex-end; gap: 14px; height: 180px; padding: 10px 4px 0; }
        .bar-group { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px; }
        .bar-pair { display: flex; align-items: flex-end; gap: 3px; height: 140px; }
        .bar { width: 14px; border-radius: 4px 4px 0 0; }
        .bar-listings { background: #a5b4fc; }
        .bar-collected { background: #06392f; }
        .bar-month-label { font-size: 0.72rem; color: #64748b; }

        @media (max-width: 600px) {
            .dashboard-container { padding: 16px; }
        }

        /* --- Print mode: force-show only the Impact tab --- */
        @media print {
            body { background: white; padding: 0; }
            .dashboard-container { box-shadow: none; border-radius: 0; padding: 0; max-width: 100%; }
            .app-header .header-actions, .tabs, .stats-row, .btn, .action-group, form { display: none !important; }
            .tab-panel { display: none !important; }
            #tab-impact.tab-panel { display: block !important; }
            .impact-stats, .chart-row, .impact-grid { break-inside: avoid; }
        }
    </style>
</head>
<body>
<div class="dashboard-container">

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
        <button class="tab-btn" onclick="switchTab('feedback', this)">
            <i class="fas fa-comment-dots"></i> Feedback
            <?php if ($total_feedback > 0): ?>
                <span class="badge badge-grey" style="font-size:0.7rem; padding:2px 8px;"><?php echo $total_feedback; ?></span>
            <?php endif; ?>
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
                            <th>Location</th><th>Registered</th><th>Document</th><th>Status</th><th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $row):
                            $extra_roles = parsePgArray($row["extra_roles"] ?? '{}');
                        ?>
                            <tr>
                                <td class="text-muted" style="font-size:0.82rem;"><?php echo $row["id"]; ?></td>
                                <td><strong><?php echo htmlspecialchars($row["full_name"]); ?></strong></td>
                                <td class="text-muted" style="font-size:0.85rem;"><?php echo htmlspecialchars($row["email"]); ?></td>
                                <td>
                                    <span class="role-tag <?php echo strtolower($row['role']); ?>">
                                        <?php echo ucfirst($row["role"]); ?> (primary)
                                    </span>
                                    <?php foreach ($extra_roles as $r): ?>
                                        <span class="role-tag <?php echo strtolower($r); ?>"><?php echo ucfirst($r); ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td style="font-size:0.85rem;"><?php echo htmlspecialchars($row["location"] ?? '—'); ?></td>
                                <td style="font-size:0.85rem;"><?php echo date("d M Y", strtotime($row["created_at"])); ?></td>
                                <td>
                                    <?php if (!empty($row["verification_doc"])): ?>
                                        <a href="<?php echo htmlspecialchars($row["verification_doc"]); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline">
                                            <i class="fas fa-file-lines"></i> View
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size:0.8rem;">None</span>
                                    <?php endif; ?>
                                </td>
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

                                    <form method="POST" action="admin-dashboard.php" class="extra-roles-form">
                                        <input type="hidden" name="update_extra_roles_user_id" value="<?php echo $row['id']; ?>">
                                        <?php foreach (['donor', 'recipient', 'rider'] as $roleOption):
                                            if ($roleOption === $row['role']) continue; // skip their primary role, already have it
                                            $checked = in_array($roleOption, $extra_roles) ? 'checked' : '';
                                        ?>
                                            <label>
                                                <input type="checkbox" name="extra_roles[]" value="<?php echo $roleOption; ?>" <?php echo $checked; ?>>
                                                <?php echo ucfirst($roleOption); ?>
                                            </label>
                                        <?php endforeach; ?>
                                        <button type="submit" class="btn btn-sm btn-outline">Save roles</button>
                                    </form>
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

        <?php
        // Chart data prep
        $role_total = array_sum($role_counts) ?: 1;
        $role_colors = ['donor' => '#166534', 'recipient' => '#1e40af', 'rider' => '#854d0e'];
        $role_labels = ['donor' => 'Donors', 'recipient' => 'Recipients', 'rider' => 'Riders'];

        $circumference = 2 * M_PI * 70;
        $offset = 0;
        $donut_segments = [];
        foreach ($role_counts as $role => $count) {
            $pct = $count / $role_total;
            $dash = $pct * $circumference;
            $donut_segments[] = ['color' => $role_colors[$role], 'dash' => $dash, 'offset' => $offset];
            $offset += $dash;
        }

        $trend_max = 1;
        foreach ($trend as $t) { $trend_max = max($trend_max, $t['listings']); }
        ?>

        <div class="flex-between">
            <h4 class="section-title" style="margin:0;"><i class="fas fa-print"></i> Printable summary</h4>
            <button class="btn btn-sm" onclick="window.print()">
                <i class="fas fa-print"></i> Print report
            </button>
        </div>

        <div class="chart-row">
            <div class="chart-card">
                <h4><i class="fas fa-chart-pie"></i> Active users by role</h4>
                <?php if ($role_total <= 0): ?>
                    <p class="text-muted" style="font-size:0.85rem;">No approved users yet.</p>
                <?php else: ?>
                    <div style="display:flex; align-items:center; gap:24px; flex-wrap:wrap;">
                        <svg width="160" height="160" viewBox="0 0 160 160">
                            <circle cx="80" cy="80" r="70" fill="none" stroke="#e9edf2" stroke-width="20" />
                            <?php foreach ($donut_segments as $seg): ?>
                                <circle cx="80" cy="80" r="70" fill="none"
                                    stroke="<?php echo $seg['color']; ?>"
                                    stroke-width="20"
                                    stroke-dasharray="<?php echo round($seg['dash'], 1); ?> <?php echo round($circumference, 1); ?>"
                                    stroke-dashoffset="<?php echo round(-$seg['offset'], 1); ?>"
                                    transform="rotate(-90 80 80)" />
                            <?php endforeach; ?>
                            <text x="80" y="76" text-anchor="middle" font-size="22" font-weight="700" fill="#1e293b"><?php echo $role_total; ?></text>
                            <text x="80" y="94" text-anchor="middle" font-size="11" fill="#64748b">active users</text>
                        </svg>
                        <div class="donut-legend">
                            <?php foreach ($role_counts as $role => $count): ?>
                                <div class="donut-legend-item">
                                    <span class="legend-dot" style="background:<?php echo $role_colors[$role]; ?>;"></span>
                                    <?php echo $role_labels[$role]; ?>: <strong><?php echo $count; ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="chart-card">
                <h4><i class="fas fa-chart-bar"></i> Listings vs. collected, last 6 months</h4>
                <?php if (count($trend) === 0): ?>
                    <p class="text-muted" style="font-size:0.85rem;">Not enough data yet.</p>
                <?php else: ?>
                    <div class="bar-chart">
                        <?php foreach ($trend as $t):
                            $h_listings = round(($t['listings'] / $trend_max) * 140);
                            $h_collected = round(($t['collected'] / $trend_max) * 140);
                        ?>
                            <div class="bar-group">
                                <div class="bar-pair">
                                    <div class="bar bar-listings" style="height:<?php echo $h_listings; ?>px;" title="Listings: <?php echo $t['listings']; ?>"></div>
                                    <div class="bar bar-collected" style="height:<?php echo $h_collected; ?>px;" title="Collected: <?php echo $t['collected']; ?>"></div>
                                </div>
                                <div class="bar-month-label"><?php echo $t['month']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="donut-legend" style="flex-direction:row; gap:20px; margin-top:16px;">
                        <div class="donut-legend-item"><span class="legend-dot" style="background:#a5b4fc;"></span> Listings added</div>
                        <div class="donut-legend-item"><span class="legend-dot" style="background:#06392f;"></span> Collected</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="impact-grid">

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
            <?php if ($collected_without_delivery > 0): ?>
                <br><i class="fas fa-circle-info"></i>
                <?php echo $collected_without_delivery; ?> of the collected listings above were picked up directly and don't have a matching rider delivery record — that's why "Food rescued" and "Deliveries completed" won't match exactly.
            <?php endif; ?>
        </p>

    </div>

    <!-- TAB: Feedback -->
    <div class="tab-panel" id="tab-feedback">
        <?php if (count($feedback_rows) === 0): ?>
            <div class="empty-state">
                <i class="fas fa-comment-slash"></i>
                <p>No feedback submitted yet.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th><th>Name</th><th>Role</th><th>Email</th><th>Message</th><th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feedback_rows as $row): ?>
                            <tr>
                                <td class="text-muted" style="font-size:0.82rem;"><?php echo $row["id"]; ?></td>
                                <td><strong><?php echo htmlspecialchars($row["name"] ?: '—'); ?></strong></td>
                                <td>
                                    <span class="role-tag <?php echo strtolower($row['role']); ?>">
                                        <?php echo ucfirst($row["role"]); ?>
                                    </span>
                                </td>
                                <td style="font-size:0.85rem;"><?php echo htmlspecialchars($row["email"] ?: '—'); ?></td>
                                <td style="font-size:0.85rem; max-width:320px;"><?php echo nl2br(htmlspecialchars($row["message"])); ?></td>
                                <td style="font-size:0.85rem;"><?php echo date("d M Y, H:i", strtotime($row["created_at"])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
function switchTab(tabName, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + tabName).classList.add('active');
    btn.classList.add('active');
}
</script>
</body>
</html>