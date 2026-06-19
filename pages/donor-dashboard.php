<?php
include '../config.php';
session_start();

// Protect page - only donors can access
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] != "donor") {
    header("Location: login.php");
    exit();
}

// Handle new food listing submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $food_name = trim($_POST["food_name"]);
    $quantity = $_POST["quantity"];
    $unit = $_POST["unit"];
    $expiry_date = $_POST["expiry_date"];
    $location = trim($_POST["location"]);
    $notes = trim($_POST["notes"]);
    $donor_id = $_SESSION["user_id"];

    pg_query_params($conn,
        "INSERT INTO food_listings (donor_id, food_name, quantity, unit, expiry_date, location, notes)
         VALUES ($1, $2, $3, $4, $5, $6, $7)",
        array($donor_id, $food_name, $quantity, $unit, $expiry_date, $location, $notes)
    );
}

// Get this donor's listings
$listings = pg_query_params($conn,
    "SELECT * FROM food_listings WHERE donor_id = $1 ORDER BY created_at DESC",
    array($_SESSION["user_id"])
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Dashboard | Food Redistribution System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container">
        <a class="navbar-brand" href="#">Food Relief System</a>
        <div class="ms-auto">
            <span class="text-white me-3">Welcome, <?php echo $_SESSION["user_name"]; ?></span>
            <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="row">

        <!-- Left: Add food listing form -->
        <div class="col-md-4">
            <div class="card shadow-sm p-4 mb-4">
                <h5 class="mb-3">List New Food Item</h5>
                <form method="POST" action="donor-dashboard.php">

                    <div class="mb-3">
                        <label class="form-label">Food Name</label>
                        <input type="text" name="food_name" class="form-control" placeholder="e.g. Tomatoes" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="quantity" class="form-control" placeholder="e.g. 10" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Unit</label>
                        <select name="unit" class="form-select" required>
                            <option value="">-- Select Unit --</option>
                            <option value="kg">Kilograms (kg)</option>
                            <option value="litres">Litres</option>
                            <option value="boxes">Boxes</option>
                            <option value="bags">Bags</option>
                            <option value="pieces">Pieces</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expiry_date" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Pickup Location</label>
                        <input type="text" name="location" class="form-control" placeholder="e.g. Westlands, Nairobi" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Additional Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Any special handling instructions?"></textarea>
                    </div>

                    <button type="submit" class="btn btn-success w-100">Submit Listing</button>
                </form>
            </div>
        </div>

        <!-- Right: Donor's listings -->
        <div class="col-md-8">
            <h5 class="mb-3">My Food Listings</h5>

            <?php if (pg_num_rows($listings) == 0): ?>
                <div class="alert alert-info">You have not listed any food items yet.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-success">
                            <tr>
                                <th>Food Item</th>
                                <th>Quantity</th>
                                <th>Expiry Date</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Date Listed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = pg_fetch_assoc($listings)): ?>
                                <tr>
                                    <td><?php echo $row["food_name"]; ?></td>
                                    <td><?php echo $row["quantity"] . " " . $row["unit"]; ?></td>
                                    <td><?php echo date("d M Y", strtotime($row["expiry_date"])); ?></td>
                                    <td><?php echo $row["location"]; ?></td>
                                    <td>
                                        <?php if ($row["status"] == "available"): ?>
                                            <span class="badge bg-success">Available</span>
                                        <?php elseif ($row["status"] == "claimed"): ?>
                                            <span class="badge bg-warning text-dark">Claimed</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Collected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date("d M Y", strtotime($row["created_at"])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>