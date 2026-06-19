<?php
include '../config.php';
session_start();

// Protect page - only riders can access
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] != "rider") {
    header("Location: login.php");
    exit();
}

// Handle status update when rider marks picked up or delivered
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $delivery_id = $_POST["delivery_id"];
    $new_status = $_POST["new_status"];

    if ($new_status == "delivered") {
        // If delivered update both delivery and claim and food listing
        pg_query_params($conn,
            "UPDATE deliveries SET status = $1, delivered_at = CURRENT_TIMESTAMP WHERE id = $2",
            array($new_status, $delivery_id)
        );

        // Get claim id from delivery
        $delivery = pg_query_params($conn,
            "SELECT claim_id FROM deliveries WHERE id = $1",
            array($delivery_id)
        );
        $delivery_row = pg_fetch_assoc($delivery);
        $claim_id = $delivery_row["claim_id"];

        // Update claim status to collected
        pg_query_params($conn,
            "UPDATE claims SET status = 'collected' WHERE id = $1",
            array($claim_id)
        );

        // Get listing id from claim and update to collected
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
        // Just update delivery status
        pg_query_params($conn,
            "UPDATE deliveries SET status = $1 WHERE id = $2",
            array($new_status, $delivery_id)
        );
    }
}

// Get all deliveries assigned to this rider
$deliveries = pg_query_params($conn,
    "SELECT 
        deliveries.id AS delivery_id,
        deliveries.status AS delivery_status,
        deliveries.assigned_at,
        deliveries.delivered_at,
        claims.pickup_date,
        food_listings.food_name,
        food_listings.quantity,
        food_listings.unit,
        food_listings.notes,
        donor.full_name AS donor_name,
        donor.location AS pickup_location,
        recipient.full_name AS recipient_name,
        recipient.location AS dropoff_location
     FROM deliveries
     JOIN claims ON deliveries.claim_id = claims.id
     JOIN food_listings ON claims.listing_id = food_listings.id
     JOIN users AS donor ON food_listings.donor_id = donor.id
     JOIN users AS recipient ON claims.recipient_id = recipient.id
     WHERE deliveries.rider_id = $1
     ORDER BY deliveries.assigned_at DESC",
    array($_SESSION["user_id"])
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rider Dashboard | Food Redistribution System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-warning">
    <div class="container">
        <a class="navbar-brand text-dark" href="#">Food Relief System — Rider</a>
        <div class="ms-auto">
            <span class="text-dark me-3">Welcome, <?php echo $_SESSION["user_name"]; ?></span>
            <a href="logout.php" class="btn btn-outline-dark btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <h4 class="mb-4">My Assigned Deliveries</h4>

    <?php if (pg_num_rows($deliveries) == 0): ?>
        <div class="alert alert-info">You have no deliveries assigned yet.</div>
    <?php else: ?>
        <?php while ($row = pg_fetch_assoc($deliveries)): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><?php echo $row["food_name"]; ?> — <?php echo $row["quantity"] . " " . $row["unit"]; ?></strong>
                    <?php if ($row["delivery_status"] == "assigned"): ?>
                        <span class="badge bg-warning text-dark">Assigned</span>
                    <?php elseif ($row["delivery_status"] == "picked_up"): ?>
                        <span class="badge bg-primary">Picked Up</span>
                    <?php else: ?>
                        <span class="badge bg-success">Delivered</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="row">

                        <!-- Pickup details -->
                        <div class="col-md-6">
                            <h6 class="text-muted">PICKUP</h6>
                            <p class="mb-1"><strong>Donor:</strong> <?php echo $row["donor_name"]; ?></p>
                            <p class="mb-1"><strong>Location:</strong> <?php echo $row["pickup_location"]; ?></p>
                            <p class="mb-1"><strong>Pickup Date:</strong> <?php echo date("d M Y", strtotime($row["pickup_date"])); ?></p>
                            <?php if ($row["notes"]): ?>
                                <p class="mb-1"><strong>Notes:</strong> <?php echo $row["notes"]; ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- Dropoff details -->
                        <div class="col-md-6">
                            <h6 class="text-muted">DROP OFF</h6>
                            <p class="mb-1"><strong>Recipient:</strong> <?php echo $row["recipient_name"]; ?></p>
                            <p class="mb-1"><strong>Location:</strong> <?php echo $row["dropoff_location"]; ?></p>
                            <?php if ($row["delivered_at"]): ?>
                                <p class="mb-1"><strong>Delivered At:</strong> <?php echo date("d M Y H:i", strtotime($row["delivered_at"])); ?></p>
                            <?php endif; ?>
                        </div>

                    </div>

                    <!-- Action buttons -->
                    <div class="mt-3">
                        <?php if ($row["delivery_status"] == "assigned"): ?>
                            <form method="POST" action="rider-dashboard.php" class="d-inline">
                                <input type="hidden" name="delivery_id" value="<?php echo $row["delivery_id"]; ?>">
                                <input type="hidden" name="new_status" value="picked_up">
                                <button type="submit" class="btn btn-primary">Mark as Picked Up</button>
                            </form>
                        <?php elseif ($row["delivery_status"] == "picked_up"): ?>
                            <form method="POST" action="rider-dashboard.php" class="d-inline">
                                <input type="hidden" name="delivery_id" value="<?php echo $row["delivery_id"]; ?>">
                                <input type="hidden" name="new_status" value="delivered">
                                <button type="submit" class="btn btn-success">Mark as Delivered</button>
                            </form>
                        <?php else: ?>
                            <span class="text-success"><strong>✓ Delivery Complete</strong></span>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>