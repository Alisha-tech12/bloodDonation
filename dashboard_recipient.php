<?php
require_once 'functions.php';
if (!isset($_SESSION['user_id']) || strcasecmp($_SESSION['role'],'Recipient')!==0) {
    header('Location: index.php'); exit;
}
$uid = $_SESSION['user_id'];
$userReqs = getUserRequests($uid);
?>
<!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Recipient Dashboard</title>
<link rel="stylesheet" href="style.css"></head>
<body>
<header><div class="logo">BLOODLIFE - Recipient</div><nav><a href="logout.php">Logout</a></nav></header>
<main class="section">
  <h1>Recipient Dashboard</h1>

  <section class="panel">
    <h3>Request Blood</h3>
    <form method="POST" action="dashboard_recipient.php">
      <input name="request_blood_group" placeholder="Blood Group (A+, O-, ...)" required>
      <input name="request_quantity" placeholder="Quantity (ml)" type="number" required>
      <label>Required By (optional)</label>
      <input name="required_date" type="date">
      <button name="submit_request" class="btn" type="submit">Submit Request</button>
    </form>
  </section>

  <section class="panel">
    <h3>Your Requests</h3>
    <table><thead><tr><th>ID</th><th>Qty</th><th>Type</th><th>Required</th></tr></thead><tbody>
    <?php foreach($userReqs as $ur): ?>
      <tr><td><?php echo htmlspecialchars($ur['request_id']);?></td><td><?php echo htmlspecialchars($ur['quantity']);?></td><td><?php echo htmlspecialchars($ur['blood_group']);?></td><td><?php echo htmlspecialchars($ur['required_date']);?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  </section>

</main><footer style="text-align:center;padding:12px;">&copy; <?php echo date('Y'); ?> BLOODLIFE</footer>
</body></html>

<?php
// handle request post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $bg = trim($_POST['request_blood_group'] ?? '');
    $qty = intval($_POST['request_quantity'] ?? 0);
    $reqd = $_POST['required_date'] ?? null;
    createRequest($uid, $qty, $bg, null, $reqd);
    header('Location: dashboard_recipient.php'); exit;
}
?>
