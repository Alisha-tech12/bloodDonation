<?php
require_once 'functions.php';
if (!isset($_SESSION['user_id']) || strcasecmp($_SESSION['role'],'Donor')!==0) {
    header('Location: index.php'); exit;
}
$uid = $_SESSION['user_id'];
$userDons = getUserDonations($uid);
?>
<!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Donor Dashboard</title>
<link rel="stylesheet" href="style.css"></head>
<body>
<header><div class="logo">BLOODLIFE - Donor</div><nav><a href="logout.php">Logout</a></nav></header>
<main class="section">
  <h1>Donor Dashboard</h1>
  <section class="panel">
    <h3>Log Donation</h3>
    <form method="POST" action="dashboard_donor.php">
      <label>Date</label><input type="date" name="donation_date" value="<?php echo date('Y-m-d'); ?>">
      <label>Quantity (ml)</label><input type="number" name="donation_quantity" required placeholder="e.g. 450">
      <label>Blood Type (optional)</label><input name="donation_blood_type" placeholder="A+">
      <button name="submit_donation" class="btn" type="submit">Submit Donation</button>
    </form>
  </section>

  <section class="panel">
    <h3>Your Donations</h3>
    <table><thead><tr><th>ID</th><th>Date</th><th>Qty</th><th>Status</th></tr></thead><tbody>
      <?php foreach($userDons as $ud): ?>
        <tr><td><?php echo htmlspecialchars($ud['donation_id']);?></td><td><?php echo htmlspecialchars($ud['date']);?></td><td><?php echo htmlspecialchars($ud['quantity']);?></td><td><?php echo htmlspecialchars($ud['status']);?></td></tr>
      <?php endforeach; ?>
    </tbody></table>
  </section>

</main><footer style="text-align:center;padding:12px;">&copy; <?php echo date('Y'); ?> BLOODLIFE</footer>
</body></html>

<?php
// handle donation post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_donation'])) {
    $date = $_POST['donation_date'] ?? date('Y-m-d');
    $qty = intval($_POST['donation_quantity'] ?? 0);
    $btype = trim($_POST['donation_blood_type'] ?? null);
    createDonation($uid, $date, $qty, $btype, 'Pending');
    header('Location: dashboard_donor.php'); exit;
}
?>
