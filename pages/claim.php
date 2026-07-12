<?php
include '../config.php';
include 'mail.php';
session_start();

// Protect page - only recipients can access
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] != "recipient") {
    header("Location: login.php");
    exit();
}

// Get listing_id from the URL
$listing_id = $_GET["listing_id"];

// Get the food listing details
$result = pg_query_params($conn,
    "SELECT food_listings.*, users.full_name AS donor_name, users.location AS donor_location ,users.email AS donor_email
     FROM food_listings
     JOIN users ON food_listings.donor_id = users.id
     WHERE food_listings.id = $1 AND food_listings.status = 'available'",
    array($listing_id)
);

$listing = pg_fetch_assoc($result);

// If listing doesn't exist or already claimed redirect back
if (!$listing) {
    header("Location: recipient-dashboard.php");
    exit();
}

$success = "";
$error = "";

// Handle claim form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pickup_date = $_POST["pickup_date"];
    $recipient_phone = trim($_POST["recipient_phone"]);
    $recipient_id = $_SESSION["user_id"];

    // Handle location if provided
    if (isset($_POST["latitude"]) && $_POST["latitude"] != "") {
        pg_query_params($conn,
            "UPDATE users SET latitude = $1, longitude = $2 WHERE id = $3",
            array($_POST["latitude"], $_POST["longitude"], $recipient_id)
        );
    }

    // Insert claim
    $claim = pg_query_params($conn,
        "INSERT INTO claims (listing_id, recipient_id, pickup_date, recipient_phone)
         VALUES ($1, $2, $3, $4)",
        array($listing_id, $recipient_id, $pickup_date, $recipient_phone)
    );

    // Update food listing status to claimed
    pg_query_params($conn,
        "UPDATE food_listings SET status = 'claimed' WHERE id = $1",
        array($listing_id)
    );

    if ($claim) {
        $success = "You have successfully claimed this listing! The donor will be notified.";
        sendClaimMail($listing["donor_email"], $listing["donor_name"], $listing["food_name"], $pickup_date);
    } else {
        $error = "Something went wrong: " . pg_last_error($conn);
    }
}
function conditionLabel($c) {
    return ucfirst(str_replace('_', ' ', $c ?? ''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5" />
    <title>Claim Food - Food Connect</title>
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

        .page-container {
            max-width: 560px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 32px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            padding: 28px 32px 32px;
        }

        .app-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 16px;
            border-bottom: 2px solid #e9edf2;
            margin-bottom: 24px;
        }
        .logo-area { display: flex; align-items: center; gap: 12px; }
        .logo-icon {
            background: #06392f;
            color: white;
            width: 40px; height: 40px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
        }
        .logo-text { font-size: 1.4rem; font-weight: 700; letter-spacing: -0.5px; }
        .logo-text span { color: #06392f; }
        .header-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }

        .page-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 18px;
            display: flex; align-items: center; gap: 10px;
        }

        .btn {
            background: #06392f;
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.15s;
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
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
        .btn-block { width: 100%; }

        .banner {
            border-radius: 14px;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin: 0 0 18px;
            font-size: 0.9rem;
        }
        .banner-success { background: #dcfce7; color: #166534; }
        .banner-danger  { background: #fee2e2; color: #991b1b; }

        .listing-summary {
            background: #fafcff;
            border: 1px solid #e9edf2;
            border-radius: 16px;
            padding: 18px 20px;
            margin-bottom: 22px;
        }
        .listing-summary-title {
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

        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 4px;
        }
        .form-group input {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #dce2ea;
            border-radius: 12px;
            font-size: 0.95rem;
            background: white;
            font-family: inherit;
        }
        .form-hint {
            font-size: 0.78rem;
            color: #64748b;
            margin-top: 4px;
        }

        #location-status { font-size: 0.82rem; margin-top: 8px; }
        #location-status.detecting { color: #64748b; }
        #location-status.success { color: #166534; }
        #location-status.error { color: #991b1b; }

        .actions { display: flex; flex-direction: column; gap: 10px; margin-top: 18px; }

        @media (max-width: 600px) {
            .page-container { padding: 20px; }
        }
    </style>
</head>
<body>
<div class="page-container">

    <!-- Header -->
    <header class="app-header">
        <div class="logo-area">
            <div class="logo-icon"><i class="fas fa-hands-helping"></i></div>
            <div class="logo-text"><span>Food Connect</span></div>
        </div>
        <div class="header-actions">
            <span class="text-muted" style="font-size:0.82rem; color:#64748b;">Welcome, <?php echo htmlspecialchars($_SESSION["user_name"]); ?></span>
            <a href="logout.php" class="btn btn-outline btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <h3 class="page-title"><i class="fas fa-hand-holding-heart"></i> Claim food listing</h3>

    <?php if ($success): ?>
        <div class="banner banner-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($success); ?></span>
        </div>
        <a href="recipient-dashboard.php" class="btn btn-block"><i class="fas fa-arrow-left"></i> Back to dashboard</a>
    <?php else: ?>

        <?php if ($error): ?>
            <div class="banner banner-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Listing summary -->
        <div class="listing-summary">
            <div class="listing-summary-title"><?php echo htmlspecialchars($listing["food_name"]); ?></div>
            <p class="listing-row"><strong>Quantity:</strong> <?php echo htmlspecialchars($listing["quantity"] . " " . $listing["unit"]); ?></p>
            <?php if (!empty($listing["food_condition"])): ?>
                <p class="listing-row"><strong>Condition:</strong> <?php echo htmlspecialchars(conditionLabel($listing["food_condition"])); ?></p>
            <?php endif; ?>
            <p class="listing-row"><strong>Expires:</strong> <?php echo date("d M Y", strtotime($listing["expiry_date"])); ?></p>
            <p class="listing-row"><strong>Pickup location:</strong> <?php echo htmlspecialchars($listing["donor_location"]); ?></p>
            <p class="listing-row"><strong>Donor:</strong> <?php echo htmlspecialchars($listing["donor_name"]); ?></p>
            <?php if ($listing["notes"]): ?>
                <p class="listing-row"><strong>Notes:</strong> <?php echo htmlspecialchars($listing["notes"]); ?></p>
            <?php endif; ?>
        </div>

        <!-- Claim form -->
        <form method="POST" action="claim.php?listing_id=<?php echo $listing_id; ?>" id="claim-form">

            <div class="form-group">
                <label>Preferred pickup date</label>
                <input type="date" name="pickup_date" required />
            </div>

            <div class="form-group">
                <label>Your phone number</label>
                <input type="text" name="recipient_phone" placeholder="e.g. 0712345678" required />
                <p class="form-hint">The rider will call you on arrival.</p>
            </div>

            <div class="form-group">
                <label>Your dropoff location</label>
                <input type="text" name="manual_location" id="manual_location" placeholder="e.g. Kibera, Nairobi" />
                <p class="form-hint">Type your location or detect it automatically below.</p>
            </div>

            <button type="button" class="btn btn-outline btn-block" onclick="getLocation()">
                <i class="fas fa-crosshairs"></i> Detect my location automatically
            </button>
            <div id="location-status"></div>

            <input type="hidden" name="latitude" id="latitude">
            <input type="hidden" name="longitude" id="longitude">

            <div class="actions">
                <button type="submit" class="btn btn-block"><i class="fas fa-check"></i> Confirm claim</button>
                <a href="recipient-dashboard.php" class="btn btn-outline btn-block">Cancel</a>
            </div>

        </form>

    <?php endif; ?>

</div>

<script>
function getLocation() {
    const statusEl = document.getElementById("location-status");
    if (navigator.geolocation) {
        statusEl.className = "detecting";
        statusEl.innerHTML = "Detecting location...";
        navigator.geolocation.getCurrentPosition(function(position) {
            document.getElementById("latitude").value = position.coords.latitude;
            document.getElementById("longitude").value = position.coords.longitude;
            statusEl.className = "success";
            statusEl.innerHTML = '<i class="fas fa-check-circle"></i> Location detected successfully!';
        }, function() {
            statusEl.className = "error";
            statusEl.innerHTML = "Could not detect location. Please type it manually.";
        });
    } else {
        statusEl.className = "error";
        statusEl.innerHTML = "Your browser does not support location detection.";
    }
}
</script>
</body>
</html>