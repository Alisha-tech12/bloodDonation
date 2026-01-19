<?php
// index.php - landing + login/signup
require_once 'functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// quick redirects: if logged in, send to their dashboard
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? '';
    if (strcasecmp($role,'Admin')===0) header('Location: dashboard_admin.php');
    elseif (strcasecmp($role,'Donor')===0) header('Location: dashboard_donor.php');
    elseif (strcasecmp($role,'Recipient')===0) header('Location: dashboard_recipient.php');
    exit;
}

// handle simple signup/login here for convenience
$message_signup = $message_login = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['signup'])) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'Donor';
        $extra = [
            'name'=>trim($_POST['name'] ?? $username),
            'cnic'=>trim($_POST['cnic'] ?? null),
            'blood_group'=>trim($_POST['blood_group'] ?? null),
            'location'=>trim($_POST['location'] ?? null),
            'phone'=>trim($_POST['phone'] ?? null)
        ];
        $r = registerUser($username, $password, $role, $extra);
        $message_signup = $r['message'];
    }
    if (isset($_POST['login'])) {
        $username = trim($_POST['login_username'] ?? '');
        $password = $_POST['login_password'] ?? '';
        $expected = $_POST['login_role'] ?? null;
        $r = loginUser($username, $password, $expected);
        $message_login = $r['message'];
        if ($r['success']) {
            $role = $_SESSION['role'];
            if (strcasecmp($role,'Admin')===0) header('Location: dashboard_admin.php');
            elseif (strcasecmp($role,'Donor')===0) header('Location: dashboard_donor.php');
            elseif (strcasecmp($role,'Recipient')===0) header('Location: dashboard_recipient.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>BloodLife — Home</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header><div class="logo">BLOODLIFE</div></header>
<main class="section">
  <h1>Welcome to BloodLife</h1>
  <p>Choose an action below.</p>

  <section class="panel" style="max-width:700px;margin-bottom:18px;">
    <h2>Register (Donor or Recipient)</h2>
    <form method="POST" action="index.php">
      <input name="name" placeholder="Full name" required>
      <input name="username" placeholder="Username (for login)" required>
      <input name="password" placeholder="Password" type="password" required>
      <input name="cnic" placeholder="CNIC (optional)">
      <input name="blood_group" placeholder="Blood Group (e.g. A+)" required>
      <input name="location" placeholder="City / Address">
      <input name="phone" placeholder="Phone">
      <select name="role">
        <option value="Donor">Donor</option>
        <option value="Recipient">Recipient</option>
      </select>
      <button name="signup" class="btn" type="submit">Register</button>
    </form>
    <?php if($message_signup): ?><p class="small-note"><?php echo htmlspecialchars($message_signup); ?></p><?php endif; ?>
  </section>

  <section class="panel" style="max-width:420px;">
    <h2>Login</h2>
    <form method="POST" action="index.php">
      <input name="login_username" placeholder="Username" required>
      <input name="login_password" placeholder="Password" type="password" required>
      <select name="login_role">
        <option value="">Login as (optional)</option>
        <option value="Donor">Donor</option>
        <option value="Recipient">Recipient</option>
        <option value="Admin">Admin</option>
      </select>
      <button name="login" class="btn" type="submit">Login</button>
    </form>
    <?php if($message_login): ?><p class="small-note"><?php echo htmlspecialchars($message_login); ?></p><?php endif; ?>
  </section>
</main>
<footer style="text-align:center;padding:12px;">&copy; <?php echo date('Y'); ?> BLOODLIFE</footer>
</body>
</html>
