<?php
require_once 'functions.php';
if (!isset($_SESSION['user_id']) || strcasecmp($_SESSION['role'],'Admin')!==0) {
    header('Location: index.php'); exit;
}
// fetch admin data
$users = adminGetAllUsers();
$stock = adminGetStock();
$dons = adminGetAllDonations();
$reqs = adminGetAllRequests();
$results = adminGetAllDonors();
$employees = adminGetAllEmployees();
$deList = adminGetDEAssignments();
?>
<!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Dashboard</title>
<link rel="stylesheet" href="style.css"></head>
<body>
<header><div class="logo">BLOODLIFE - Admin</div><nav><a href="logout.php">Logout</a></nav></header>
<main class="section">
  <h1>Admin Dashboard</h1>

  <section class="panel"><h3>Users</h3>
    <table><thead><tr><th>ID</th><th>Username</th><th>Role</th></tr></thead><tbody>
    <?php foreach($users as $u): ?>
      <tr><td><?php echo htmlspecialchars($u['user_id']); ?></td><td><?php echo htmlspecialchars($u['username']); ?></td><td><?php echo htmlspecialchars($u['role']); ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  </section>

  <section class="panel"><h3>Stock</h3>
    <table><thead><tr><th>ID</th><th>Type</th><th>Qty (ml)</th><th>Status</th></tr></thead><tbody>
    <?php foreach($stock as $s): ?>
      <tr><td><?php echo htmlspecialchars($s['inventory_id']);?></td><td><?php echo htmlspecialchars($s['blood_type']);?></td><td><?php echo htmlspecialchars($s['blood_quantity_ml']);?></td><td><?php echo htmlspecialchars($s['status']);?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  </section>

  <section class="panel"><h3>Donations</h3>
    <table><thead><tr><th>ID</th><th>Donor</th><th>Date</th><th>Qty</th><th>Status</th></tr></thead><tbody>
    <?php foreach($dons as $d): ?>
      <tr><td><?php echo htmlspecialchars($d['donation_id']);?></td><td><?php echo htmlspecialchars($d['name']);?></td><td><?php echo htmlspecialchars($d['date']);?></td><td><?php echo htmlspecialchars($d['quantity']);?></td><td><?php echo htmlspecialchars($d['status']);?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  </section>

  <section class="panel"><h3>Requests</h3>
    <table><thead><tr><th>ID</th><th>Recipient</th><th>Type</th><th>Qty</th><th>Required</th></tr></thead><tbody>
    <?php foreach($reqs as $r): ?>
      <tr><td><?php echo htmlspecialchars($r['request_id']);?></td><td><?php echo htmlspecialchars($r['name']);?></td><td><?php echo htmlspecialchars($r['blood_group']);?></td><td><?php echo htmlspecialchars($r['quantity']);?></td><td><?php echo htmlspecialchars($r['required_date']);?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  </section>

  <section class="panel"><h3>Assign Employee to Donor</h3>
    <form method="POST" action="dashboard_admin.php">
      <select name="assign_donor_id" required><option value="">Select donor</option>
        <?php foreach($results as $dd): ?>
          <option value="<?php echo htmlspecialchars($dd['donor_id']); ?>"><?php echo htmlspecialchars($dd['donor_id'].' — '.$dd['name']);?></option>
        <?php endforeach; ?>
      </select>
      <select name="assign_employee_id" required><option value="">Select employee</option>
        <?php foreach($employees as $ee): ?>
          <option value="<?php echo htmlspecialchars($ee['employee_id']); ?>"><?php echo htmlspecialchars($ee['employee_id'].' — '.$ee['first_name'].' '.$ee['last_name']);?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" name="assign_de" class="btn">Assign</button>
    </form>

    <h4>Current Assignments</h4>
    <table><thead><tr><th>Donor</th><th>Employee</th><th>Action</th></tr></thead><tbody>
      <?php foreach($deList as $row): ?>
        <tr>
          <td><?php echo htmlspecialchars($row['donor_name']);?></td>
          <td><?php echo htmlspecialchars($row['emp_first'].' '.$row['emp_last']);?></td>
          <td>
            <form method="POST" action="dashboard_admin.php">
              <input type="hidden" name="remove_donor_id" value="<?php echo htmlspecialchars($row['donor_id']); ?>">
              <input type="hidden" name="remove_employee_id" value="<?php echo htmlspecialchars($row['employee_id']); ?>">
              <button name="remove_de" class="btn-outline" type="submit">Remove</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody></table>
  </section>

</main>
<footer style="text-align:center;padding:12px;">&copy; <?php echo date('Y'); ?> BLOODLIFE</footer>
</body></html>

<?php
// handle assign/remove posted to this page
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_de'])) {
        assignEmployeeToDonor($_POST['assign_donor_id'] ?? '', $_POST['assign_employee_id'] ?? '');
        header('Location: dashboard_admin.php'); exit;
    }
    if (isset($_POST['remove_de'])) {
        removeEmployeeAssignment($_POST['remove_donor_id'] ?? '', $_POST['remove_employee_id'] ?? '');
        header('Location: dashboard_admin.php'); exit;
    }
}
?>
