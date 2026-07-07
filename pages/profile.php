<?php
// profile.php
// Include this near the top of any dashboard, inside the <body>, after session_start().
// Usage:  <?php include 'profile.php'; 
//
// One file that does everything:
// - Shows a clickable avatar (photo, or first-letter initial if no photo yet)
// - Clicking it opens a small popup to edit name / phone / location / bio / picture
// - The popup's form submits back to this same file (profile.php), which saves
//   the changes and redirects back to whichever dashboard you were on.

// Make sure we have a session and a database connection, whether this file was
// included by a dashboard (which already has both) or loaded directly by the
// form submission (which needs to set both up itself).
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($conn)) {
    require_once '../config.php'; // Provides $conn as a pg_connect() resource
}

if (!isset($_SESSION['user_id'])) {
    // If this was loaded directly (not included), send to login.
    // If included inside a dashboard that already checked login, this won't be reached.
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$uploadDir = '../uploads/profile_pictures/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// --- Handle the popup form submission ---
// This only runs when the form actually posts here — an ordinary page view
// (i.e. this file being included by a dashboard) skips straight past it.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['full_name'])) {

    $returnTo = $_SERVER['HTTP_REFERER'] ?? 'login.php';

    $full_name = trim($_POST['full_name']);
    $phone     = trim($_POST['phone'] ?? '');
    $location  = trim($_POST['location'] ?? '');
    $bio       = trim($_POST['bio'] ?? '');

    if ($full_name !== '') {

        $profile_picture_path = null;

        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png'];
            $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));

            if (in_array($ext, $allowed) && $_FILES['profile_picture']['size'] <= 2 * 1024 * 1024) {
                $newFileName = 'user_' . $user_id . '_' . time() . '.' . $ext;
                $destination = $uploadDir . $newFileName;

                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $destination)) {
                    $profile_picture_path = $uploadDir . $newFileName;
                }
            }
        }

        if ($profile_picture_path !== null) {
            pg_query_params(
                $conn,
                "UPDATE users
                 SET full_name = $1, phone = $2, location = $3, bio = $4, profile_picture = $5
                 WHERE id = $6",
                array($full_name, $phone, $location, $bio, $profile_picture_path, $user_id)
            );
        } else {
            pg_query_params(
                $conn,
                "UPDATE users
                 SET full_name = $1, phone = $2, location = $3, bio = $4
                 WHERE id = $5",
                array($full_name, $phone, $location, $bio, $user_id)
            );
        }

        $_SESSION['user_name'] = $full_name;
    }

    header("Location: " . $returnTo);
    exit();
}

// --- Fetch current profile details for pre-filling the popup ---
$res = pg_query_params(
    $conn,
    "SELECT full_name, email, phone, location, bio, profile_picture FROM users WHERE id = $1 LIMIT 1",
    array($user_id)
);
$profile = pg_fetch_assoc($res);

$name = $profile['full_name'] ?? ($_SESSION['user_name'] ?? 'User');
$initial = strtoupper(substr(trim($name), 0, 1));
$avatarPicture = !empty($profile['profile_picture']) ? $profile['profile_picture'] : null;
?>
<style>
  .pa-trigger{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:42px;
    height:42px;
    border-radius:50%;
    background:#06392f;
    color:#fff;
    font-family:'Work Sans', Arial, sans-serif;
    font-weight:600;
    font-size:1.05rem;
    overflow:hidden;
    border:2px solid #d98e2b;
    cursor:pointer;
  }
  .pa-trigger img{width:100%; height:100%; object-fit:cover;}

  .pa-overlay{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(4,36,28,0.55);
    z-index:1000;
    align-items:center;
    justify-content:center;
  }
  .pa-overlay.open{display:flex;}

  .pa-modal{
    background:#fff;
    width:100%;
    max-width:440px;
    max-height:88vh;
    overflow-y:auto;
    border-radius:16px;
    padding:28px;
    font-family:'Work Sans', Arial, sans-serif;
    color:#111815;
    position:relative;
  }
  .pa-close{
    position:absolute;
    top:16px; right:18px;
    background:none;
    border:none;
    font-size:1.3rem;
    color:#33403a;
    cursor:pointer;
    line-height:1;
  }
  .pa-modal h2{font-size:1.25rem; color:#06392f; margin:0 0 20px;}

  .pa-avatar-row{display:flex; align-items:center; gap:16px; margin-bottom:22px;}
  .pa-avatar-row img{
    width:64px; height:64px; border-radius:50%;
    object-fit:cover; border:2px solid #dce5da;
  }
  .pa-avatar-row label{font-size:0.85rem; color:#33403a; display:block; margin-bottom:4px;}
  .pa-avatar-row input[type="file"]{font-size:0.8rem;}

  .pa-field{margin-bottom:16px;}
  .pa-field label{display:block; font-weight:600; font-size:0.88rem; margin-bottom:5px;}
  .pa-field input, .pa-field textarea{
    width:100%;
    padding:9px 12px;
    border:1.5px solid #dce5da;
    border-radius:7px;
    font-size:0.95rem;
    font-family:inherit;
    color:#111815;
  }
  .pa-field input:disabled{background:#f2f2f2; color:#777;}
  .pa-field textarea{resize:vertical; min-height:70px;}

  .pa-save{
    background:#06392f;
    color:#fff;
    border:none;
    padding:11px 24px;
    border-radius:8px;
    font-weight:600;
    font-size:0.95rem;
    cursor:pointer;
    width:100%;
  }
  .pa-save:hover{background:#04241c;}
</style>

<div class="pa-trigger" onclick="document.getElementById('paOverlay').classList.add('open')" title="Edit my profile">
  <?php if ($avatarPicture): ?>
    <img src="<?= htmlspecialchars($avatarPicture) ?>" alt="<?= htmlspecialchars($name) ?>">
  <?php else: ?>
    <?= htmlspecialchars($initial) ?>
  <?php endif; ?>
</div>

<div class="pa-overlay" id="paOverlay">
  <div class="pa-modal">
    <button type="button" class="pa-close" onclick="document.getElementById('paOverlay').classList.remove('open')">&times;</button>
    <h2>My Profile</h2>

    <form method="POST" action="profile.php" enctype="multipart/form-data">

      <div class="pa-avatar-row">
        <?php if ($avatarPicture): ?>
          <img src="<?= htmlspecialchars($avatarPicture) ?>" alt="Current picture">
        <?php endif; ?>
        <div>
          <label for="pa_picture">Change picture</label>
          <input type="file" id="pa_picture" name="profile_picture" accept=".jpg,.jpeg,.png">
        </div>
      </div>

      <div class="pa-field">
        <label for="pa_name">Full name</label>
        <input type="text" id="pa_name" name="full_name" value="<?= htmlspecialchars($profile['full_name'] ?? '') ?>" required>
      </div>

      <div class="pa-field">
        <label for="pa_email">Email</label>
        <input type="email" id="pa_email" value="<?= htmlspecialchars($profile['email'] ?? '') ?>" disabled>
      </div>

      <div class="pa-field">
        <label for="pa_phone">Phone number</label>
        <input type="text" id="pa_phone" name="phone" value="<?= htmlspecialchars($profile['phone'] ?? '') ?>" placeholder="e.g. 0712 345 678">
      </div>

      <div class="pa-field">
        <label for="pa_location">Location</label>
        <input type="text" id="pa_location" name="location" value="<?= htmlspecialchars($profile['location'] ?? '') ?>" placeholder="e.g. Westlands, Nairobi">
      </div>

      <div class="pa-field">
        <label for="pa_bio">Short bio</label>
        <textarea id="pa_bio" name="bio" placeholder="A line or two about yourself"><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea>
      </div>

      <button type="submit" class="pa-save">Save changes</button>
    </form>
  </div>
</div>