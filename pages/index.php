<?php
// Food Connect — Landing Page
// Drop into your existing project root (same level as login.php / register.php).
// Update the href values below if your auth routes live somewhere else.
//
// Feedback form now posts back to this same file. The block below handles the
// insert, then redirects (POST -> redirect -> GET) so refreshing the page
// never resubmits the form.

require_once '../config.php'; // Provides $conn as a pg_connect() resource

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {

    $name    = trim($_POST['name'] ?? '');
    $role    = trim($_POST['role'] ?? 'visitor');
    $email   = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    $allowedRoles = ['visitor', 'donor', 'recipient', 'rider', 'admin'];
    if (!in_array($role, $allowedRoles)) {
        $role = 'visitor';
    }

    if ($message === '') {
        header("Location: " . basename(__FILE__) . "?feedback=error#feedback");
        exit();
    }

    $result = pg_query_params(
        $conn,
        "INSERT INTO feedback (name, role, email, message, created_at)
         VALUES ($1, $2, $3, $4, NOW())",
        array(
            $name !== '' ? $name : null,
            $role,
            $email !== '' ? $email : null,
            $message
        )
    );

    header("Location: " . basename(__FILE__) . "?feedback=" . ($result ? "success" : "error") . "#feedback");
    exit();
}

$feedbackStatus = $_GET['feedback'] ?? null;

$loginUrl        = "login.php";
$registerUrl     = "register.php?role=donor";
$registerRecipient = "register.php?role=recipient";
$registerRider   = "register.php?role=rider";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Food Connect — Move surplus food before it goes to waste</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Work+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  :root{
    --forest-deep:#06392f;
    --forest-shadow:#04241c;
    --harvest-gold:#d98e2b;
    --harvest-gold-bright:#e8a33d;
    --paper-warm:#f3efe1;
    --sage-mist:#dce5da;
    --ink:#111815;
    --ink-soft:#33403a;

    --font-display:'Work Sans', sans-serif;
    --font-body:'Work Sans', sans-serif;
    --font-mono:'IBM Plex Mono', monospace;

    --max-w:1180px;
  }

  *{box-sizing:border-box; margin:0; padding:0;}

  html{scroll-behavior:smooth;}

  body{
    font-family:var(--font-body);
    background:var(--paper-warm);
    color:var(--ink);
    font-size:18px;
    line-height:1.6;
    -webkit-font-smoothing:antialiased;
  }

  a{color:inherit; text-decoration:none;}

  .wrap{max-width:var(--max-w); margin:0 auto; padding:0 24px;}

  /* Focus visibility */
  a:focus-visible, button:focus-visible, summary:focus-visible{
    outline:2px solid var(--harvest-gold-bright);
    outline-offset:3px;
  }

  @media (prefers-reduced-motion: reduce){
    *{animation-duration:0.001ms !important; transition-duration:0.001ms !important;}
  }

  /* ---------- NAV ---------- */
  .nav{
    position:sticky; top:0; z-index:50;
    background:var(--forest-deep);
    color:var(--paper-warm);
  }
  .nav .wrap{
    display:flex; align-items:center; justify-content:space-between;
    padding-top:16px; padding-bottom:16px;
  }
  .nav-brand{
    font-family:var(--font-display);
    font-weight:600;
    font-size:1.3rem;
    letter-spacing:0.01em;
    color:var(--paper-warm);
  }
  .nav-brand span{color:var(--harvest-gold-bright);}
  .nav-links{display:flex; gap:28px; align-items:center;}
  .nav-links a{
    font-size:0.92rem;
    color:var(--sage-mist);
    transition:color .15s ease;
  }
  .nav-links a:hover{color:var(--paper-warm);}
  .nav-cta{
    background:var(--harvest-gold);
    color:var(--forest-shadow) !important;
    padding:9px 18px;
    border-radius:6px;
    font-weight:600;
    font-size:0.9rem;
  }
  .nav-cta:hover{background:var(--harvest-gold-bright);}
  .nav-toggle{display:none;}

  /* ---------- HERO ---------- */
  .hero{
    background:var(--forest-deep);
    color:var(--paper-warm);
    padding-top:56px;
  }
  .hero .wrap{
    max-width:700px;
    align-items:center;
    padding-bottom:72px;
  }
  .hero-eyebrow{
    font-family:var(--font-mono);
    font-size:0.78rem;
    letter-spacing:0.14em;
    text-transform:uppercase;
    color:var(--harvest-gold-bright);
    margin-bottom:18px;
    display:block;
  }
  .hero h1{
    font-family:var(--font-display);
    font-weight:600;
    font-size:clamp(2.2rem, 4.2vw, 3.4rem);
    line-height:1.08;
    letter-spacing:-0.01em;
    margin-bottom:22px;
  }
  .hero h1 em{
    font-style:normal;
    color:var(--harvest-gold-bright);
  }
  .hero p.lede{
    font-size:1.08rem;
    color:var(--sage-mist);
    max-width:46ch;
    margin-bottom:32px;
  }
  .hero-actions{display:flex; gap:14px; flex-wrap:wrap;}
  .btn{
    display:inline-block;
    padding:13px 26px;
    border-radius:6px;
    font-weight:600;
    font-size:0.95rem;
    border:1.5px solid transparent;
    cursor:pointer;
  }
  .btn-primary{background:var(--harvest-gold); color:var(--forest-shadow);}
  .btn-primary:hover{background:var(--harvest-gold-bright);}
  .btn-ghost{border-color:rgba(243,239,225,0.4); color:var(--paper-warm);}
  .btn-ghost:hover{border-color:var(--paper-warm); background:rgba(243,239,225,0.08);}

  /* ---------- SECTIONS ---------- */
  .section{padding:88px 0;}
  .section-head{max-width:60ch; margin-bottom:56px;}
  .section-eyebrow{
    font-family:var(--font-mono);
    font-size:0.78rem;
    letter-spacing:0.12em;
    text-transform:uppercase;
    color:var(--forest-deep);
    display:block;
    margin-bottom:12px;
  }
  .section-head h2{
    font-family:var(--font-display);
    font-weight:600;
    font-size:clamp(1.7rem, 3vw, 2.3rem);
    color:var(--forest-shadow);
  }

  /* ---------- ROLES / CTA CARDS ---------- */
  .roles{
    background:var(--sage-mist);
  }
  .role-cards{
    display:grid;
    grid-template-columns:repeat(3, 1fr);
    gap:20px;
  }
  .role-card{
    background:var(--paper-warm);
    border-radius:10px;
    padding:30px 26px;
    border:1px solid rgba(6,57,47,0.1);
    display:flex;
    flex-direction:column;
  }
  .role-card h3{
    font-family:var(--font-display);
    font-size:1.2rem;
    font-weight:600;
    color:var(--forest-shadow);
    margin-bottom:8px;
  }
  .role-card p{
    font-size:0.9rem;
    color:var(--ink-soft);
    margin-bottom:20px;
    flex-grow:1;
  }
  .role-card .btn{
    align-self:flex-start;
    background:var(--forest-deep);
    color:var(--paper-warm);
    padding:10px 20px;
    font-size:0.88rem;
  }
  .role-card .btn:hover{background:var(--forest-shadow);}

  /* ---------- FAQ ---------- */
  .faq-list{max-width:760px;}
  details{
    border-bottom:1px solid var(--sage-mist);
    padding:20px 0;
  }
  summary{
    cursor:pointer;
    font-family:var(--font-display);
    font-weight:600;
    font-size:1.05rem;
    color:var(--forest-shadow);
    list-style:none;
    display:flex;
    justify-content:space-between;
    align-items:center;
  }
  summary::-webkit-details-marker{display:none;}
  summary::after{
    content:"+";
    font-family:var(--font-mono);
    font-size:1.3rem;
    color:var(--harvest-gold);
    margin-left:16px;
    flex-shrink:0;
  }
  details[open] summary::after{content:"–";}
  details p{
    margin-top:14px;
    font-size:0.94rem;
    color:var(--ink-soft);
    max-width:62ch;
  }

  /* ---------- FEEDBACK ---------- */
  .feedback-section{background:var(--forest-deep); color:var(--paper-warm);}
  .feedback-section .section-eyebrow{color:var(--harvest-gold-bright);}
  .feedback-section .section-head h2{color:var(--paper-warm);}
  .feedback-section .section-head p{color:var(--sage-mist); margin-top:10px;}

  .feedback-banner{
    padding:14px 18px;
    border-radius:8px;
    margin-bottom:24px;
    font-size:0.92rem;
    border:1px solid transparent;
  }
  .feedback-banner.success{
    background:rgba(217,142,43,0.15);
    border-color:var(--harvest-gold);
    color:var(--paper-warm);
  }
  .feedback-banner.error{
    background:rgba(255,90,90,0.15);
    border-color:#ff5a5a;
    color:var(--paper-warm);
  }

  .feedback-form{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:16px;
    max-width:720px;
  }
  .feedback-form .full{grid-column:1 / -1;}
  .feedback-form label{
    display:block;
    font-family:var(--font-mono);
    font-size:0.75rem;
    letter-spacing:0.05em;
    text-transform:uppercase;
    color:var(--sage-mist);
    margin-bottom:8px;
  }
  .feedback-form input,
  .feedback-form select,
  .feedback-form textarea{
    width:100%;
    background:var(--forest-shadow);
    border:1px solid rgba(243,239,225,0.2);
    border-radius:6px;
    padding:12px 14px;
    color:var(--paper-warm);
    font-family:var(--font-body);
    font-size:0.95rem;
  }
  .feedback-form textarea{resize:vertical; min-height:110px;}
  .feedback-form ::placeholder{color:rgba(243,239,225,0.45);}
  .feedback-form button{
    justify-self:start;
    background:var(--harvest-gold);
    color:var(--forest-shadow);
    border:none;
  }
  .feedback-form button:hover{background:var(--harvest-gold-bright);}
  .feedback-note{
    font-size:0.82rem;
    color:var(--sage-mist);
    margin-top:14px;
    max-width:62ch;
  }

  /* ---------- FOOTER ---------- */
  footer{
    background:var(--forest-shadow);
    color:var(--sage-mist);
    padding:48px 0 28px;
    font-size:0.88rem;
  }
  .footer-grid{
    display:grid;
    grid-template-columns:1.4fr 1fr 1fr;
    gap:32px;
    margin-bottom:32px;
  }
  .footer-grid h4{
    font-family:var(--font-mono);
    font-size:0.75rem;
    text-transform:uppercase;
    letter-spacing:0.06em;
    color:var(--harvest-gold-bright);
    margin-bottom:14px;
  }
  .footer-grid a{
    display:block;
    color:var(--sage-mist);
    margin-bottom:8px;
  }
  .footer-grid a:hover{color:var(--paper-warm);}
  .footer-bottom{
    border-top:1px solid rgba(243,239,225,0.12);
    padding-top:20px;
    display:flex;
    justify-content:space-between;
    flex-wrap:wrap;
    gap:10px;
    color:rgba(220,229,218,0.6);
    font-size:0.8rem;
  }

  /* ---------- RESPONSIVE ---------- */
  @media (max-width: 860px){
    .hero{background:var(--forest-deep);}
    .steps{grid-template-columns:1fr 1fr; row-gap:28px;}
    .step{border-right:none; border-bottom:1px solid var(--sage-mist); padding-bottom:24px;}
    .role-cards{grid-template-columns:1fr;}
    .feedback-form{grid-template-columns:1fr;}
    .footer-grid{grid-template-columns:1fr;}
    .nav-links{
      display:none;
      position:absolute; top:100%; left:0; right:0;
      background:var(--forest-deep);
      flex-direction:column;
      padding:16px 24px 20px;
      gap:16px;
      border-top:1px solid rgba(243,239,225,0.1);
    }
    .nav-links.open{display:flex;}
    .nav-toggle{
      display:inline-block;
      background:none; border:none;
      color:var(--paper-warm);
      font-size:1.4rem;
      cursor:pointer;
    }
  }
</style>
</head>
<body>

<nav class="nav">
  <div class="wrap">
    <a href="#" class="nav-brand">Food<span>Connect</span></a>
    <button class="nav-toggle" id="navToggle" aria-label="Toggle menu" aria-expanded="false">☰</button>
    <div class="nav-links" id="navLinks">
      <a href="#roles">Get involved</a>
      <a href="#faq">FAQ</a>
      <a href="#feedback">Feedback</a>
      <a href="<?= htmlspecialchars($loginUrl) ?>" class="nav-cta">Log in</a>
    </div>
  </div>
</nav>

<header class="hero">
  <div class="wrap">
    <div>
      <span class="hero-eyebrow">Nairobi · Food redistribution network</span>
      <h1>Surplus food finds a plate <em>before</em> it finds a bin.</h1>
      <p class="lede">Food Connect links farms, markets, and retailers with recipients and volunteer riders across Nairobi so good food that would go to waste gets collected, matched, and delivered the same day.</p>
      <div class="hero-actions">
        <a href="<?= htmlspecialchars($registerUrl) ?>" class="btn btn-primary">Register as a donor</a>
        <a href="#roles" class="btn btn-ghost">See all roles</a>
      </div>
    </div>
  </div>
</header>

<section class="section roles" id="roles">
  <div class="wrap">
    <div class="section-head">
      <span class="section-eyebrow">Get involved</span>
      <h2>Pick the role that fits you</h2>
    </div>
    <div class="role-cards">
      <div class="role-card">
        <h3>Donor</h3>
        <p>Farms, market traders, and retailers with surplus food to give away instead of throwing out.</p>
        <a href="<?= htmlspecialchars($registerUrl) ?>" class="btn">Register as donor</a>
      </div>
      <div class="role-card">
        <h3>Recipient</h3>
        <p>Shelters, schools, and households that can put surplus food to use the same day it's collected.</p>
        <a href="<?= htmlspecialchars($registerRecipient) ?>" class="btn">Register as recipient</a>
      </div>
      <div class="role-card">
        <h3>Rider</h3>
        <p>Volunteers who collect listings and deliver them  pick your own pickups from the pool.</p>
        <a href="<?= htmlspecialchars($registerRider) ?>" class="btn">Register as rider</a>
      </div>
    </div>
  </div>
</section>

<section class="section" id="faq">
  <div class="wrap">
    <div class="section-head">
      <span class="section-eyebrow">Questions</span>
      <h2>Frequently asked</h2>
    </div>
    <div class="faq-list">
      <details open>
        <summary>Do I need to share my location to sign up?</summary>
        <p>No. Location is optional at registration  you can add it later from your profile once you're ready to start listing or receiving donations.</p>
      </details>
      <details>
        <summary>Which cities is Food Connect available in?</summary>
        <p>Food Connect currently operates in Nairobi. We're building toward more cities and will announce new zones here as they go live.</p>
      </details>
      <details>
        <summary>Can I have more than one role on the same account?</summary>
        <p>Yes. One email address can be linked to multiple roles  for example, a market trader who also volunteers as a rider.</p>
      </details>

      <details>
        <summary>How do you verify donors and recipients?</summary>
        <p>Registration includes a document upload step for verification, and admins can review to approve accounts.</p>
      </details>
    </div>
  </div>
</section>

<section class="section feedback-section" id="feedback">
  <div class="wrap">

    <?php if ($feedbackStatus === 'success'): ?>
      <div class="feedback-banner success">✓ Thanks — your feedback was sent.</div>
    <?php elseif ($feedbackStatus === 'error'): ?>
      <div class="feedback-banner error">⚠ Something went wrong  please make sure the message field isn't empty and try again.</div>
    <?php endif; ?>

    <div class="section-head">
      <span class="section-eyebrow">Tell us</span>
      <h2>Feedback helps us fix things faster</h2>
      <p>Spotted a bug, a confusing step, or something missing? Let us know  this goes straight to the team.</p>
    </div>
    <form class="feedback-form" method="POST" action="#feedback">
      <div>
        <label for="fbName">Name (optional)</label>
        <input type="text" id="fbName" name="name" placeholder="Jane Wanjiru">
      </div>
      <div>
        <label for="fbRole">I am a</label>
        <select id="fbRole" name="role">
          <option value="visitor">Just visiting</option>
          <option value="donor">Donor</option>
          <option value="recipient">Recipient</option>
          <option value="rider">Rider</option>
        </select>
      </div>
      <div class="full">
        <label for="fbEmail">Email (optional, if you'd like a reply)</label>
        <input type="email" id="fbEmail" name="email" placeholder="you@example.com">
      </div>
      <div class="full">
        <label for="fbMessage">Your feedback</label>
        <textarea id="fbMessage" name="message" placeholder="What worked, what didn't, what you'd like to see" required></textarea>
      </div>
      <div class="full">
        <button type="submit" class="btn">Send feedback</button>
      </div>
    </form>
    <p class="feedback-note">Feedback is saved straight to the database  check the admin dashboard's Feedback tab to view submissions.</p>
  </div>
</section>

<footer>
  <div class="wrap">
    <div class="footer-grid">
      <div>
        <h4>Food Connect</h4>
        <p style="max-width:34ch; color:var(--sage-mist);">A food redistribution platform connecting Nairobi's surplus to the people who need it, before it goes to waste.</p>
      </div>
      <div>
        <h4>Platform</h4>
        <a href="#roles">Get involved</a>
        <a href="#faq">FAQ</a>
        <a href="<?= htmlspecialchars($loginUrl) ?>">Log in</a>
      </div>
      <div>
        <h4>Coverage</h4>
        <a href="#">Nairobi  live</a>
        <a href="#">More cities are coming soon</a>
        <a href="#feedback">Report an issue</a>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© <?= date("Y") ?></span>
  
    </div>
  </div>
</footer>

<script>
  // Mobile nav toggle
  const navToggle = document.getElementById('navToggle');
  const navLinks = document.getElementById('navLinks');
  navToggle.addEventListener('click', () => {
    const isOpen = navLinks.classList.toggle('open');
    navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  });
</script>

</body>
</html>