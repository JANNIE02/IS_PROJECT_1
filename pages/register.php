<?php
include '../config.php';
session_start();

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Get form data
    $full_name = trim($_POST["full_name"]);
    $email = trim($_POST["email"]); 
    $password = $_POST["password"];
    $role = $_POST["role"];
    $location = trim($_POST["location"]);

    // Basic validation
    if (empty($full_name) || empty($email) || empty($password) || empty($role)) {
        $error = "Please fill in all required fields.";
    } else {
        // Check if email already exists
        $check = pg_query_params($conn, "SELECT id FROM users WHERE email = $1", array($email));
        
        if (pg_num_rows($check) > 0) {
            $error = "An account with that email already exists.";
        } else {

            // ---- Handle supporting document upload (optional) ----
            $doc_path = null;

            if (isset($_FILES["verification_doc"]) && $_FILES["verification_doc"]["error"] !== UPLOAD_ERR_NO_FILE) {

                $file = $_FILES["verification_doc"];

                if ($file["error"] !== UPLOAD_ERR_OK) {
                    $error = "There was a problem uploading your document. Please try again.";
                } else {
                    $allowed_ext = ["pdf", "jpg", "jpeg", "png"];
                    $allowed_mime = [
                        "application/pdf",
                        "image/jpeg",
                        "image/png"
                    ];
                    $max_size = 5 * 1024 * 1024; // 5MB

                    $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $file["tmp_name"]);
                    finfo_close($finfo);

                    if (!in_array($ext, $allowed_ext) || !in_array($mime, $allowed_mime)) {
                        $error = "Supporting document must be a PDF, JPG, or PNG file.";
                    } elseif ($file["size"] > $max_size) {
                        $error = "Supporting document must be smaller than 5MB.";
                    } else {
                        $upload_dir = __DIR__ . "/uploads/verification_docs/";
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }

                        // Unique filename so we never overwrite / leak other users' docs
                        $unique_name = bin2hex(random_bytes(16)) . "." . $ext;
                        $destination = $upload_dir . $unique_name;

                        if (move_uploaded_file($file["tmp_name"], $destination)) {
                            // Store the relative path (used to build a link in the admin dashboard)
                            $doc_path = "uploads/verification_docs/" . $unique_name;
                        } else {
                            $error = "Could not save your document. Please try again.";
                        }
                    }
                }
            }

            // ---- Create the account if no error occurred above ----
            if (empty($error)) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                $result = pg_query_params($conn, 
                    "INSERT INTO users (full_name, email, password_hash, role, location, verification_doc) 
                     VALUES ($1, $2, $3, $4, $5, $6)",
                    array($full_name, $email, $password_hash, $role, $location, $doc_path)
                );

                if ($result) {
                    $success = "Account created successfully! Wait for admin approval before logging in.";
                } else {
                    $error = "Something went wrong. Please try again.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Food Redistribution System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body class="bg-light">

<div class="container d-flex justify-content-center align-items-center min-vh-100">
    <div class="card shadow p-4" style="width: 100%; max-width: 500px;">
        
        <h3 class="text-center mb-4">Create an Account</h3>

        <!-- Show error or success messages -->
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" action="register.php" enctype="multipart/form-data">
            
            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">I am registering as</label>
                <select name="role" class="form-select" required>
                    <option value="">-- Select Role --</option>
                    <option value="donor">Donor</option>
                    <option value="recipient">Recipient NGO</option>
                    <option value="rider">Volunteer Rider</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Location</label>
                <input type="text" name="location" class="form-control" placeholder="e.g. Westlands, Nairobi">
            </div>

            <div class="mb-3">
                <label class="form-label">Supporting document <span class="text-muted">(optional)</span></label>
                <input type="file" name="verification_doc" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                <div class="form-text">
                    e.g. an ID, business permit, or NGO registration certificate — helps the admin verify your account faster. PDF, JPG or PNG, max 5MB.
                </div>
            </div>

            <button type="submit" class="btn btn-success w-100">Register</button>
            
            <p class="text-center mt-3">Already have an account? <a href="login.php">Login here</a></p>

        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>