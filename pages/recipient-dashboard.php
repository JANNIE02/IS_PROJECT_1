<?php
include '../config.php';
session_start();

// Check if user is logged in and is a recipient
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] != "recipient") {
    header("Location: login.php");
    exit();
}

// Get all available food listings
$listings = pg_query($conn, 
    "SELECT food_listings.*, users.full_name AS donor_name, users.location AS donor_location 
     FROM food_listings 
     JOIN users ON food_listings.donor_id = users.id 
     WHERE food_listings.status = 'available' 
     ORDER BY food_listings.created_at DESC"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recipient Dashboard | Food Redistribution System</title>
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
    <h4 class="mb-4">Available Food Listings</h4>

    <?php if (pg_num_rows($listings) == 0): ?>
        <div class="alert alert-info">No food listings available right now. Check back later.</div>
    <?php else: ?>
        <div class="row">
            <?php while ($row = pg_fetch_assoc($listings)): ?>
                <div class="col-md-4 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo $row["food_name"]; ?></h5>
                            <p class="card-text">
                                <strong>Quantity:</strong> <?php echo $row["quantity"] . " " . $row["unit"]; ?><br>
                                <strong>Expires:</strong> <?php echo $row["expiry_date"]; ?><br>
                                <strong>Location:</strong> <?php echo $row["donor_location"]; ?><br>
                                <strong>Donor:</strong> <?php echo $row["donor_name"]; ?><br>
                                <?php if ($row["notes"]): ?>
                                    <strong>Notes:</strong> <?php echo $row["notes"]; ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="card-footer">
                            <a href="claim.php?listing_id=<?php echo $row["id"]; ?>" class="btn btn-success w-100">Claim This</a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>