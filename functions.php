<?php
// functions.php
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/* ----------------- Helpers ----------------- */
function generateId(string $prefix, string $table, int $width = 5): string {
    $pdo = dbConnect();
    // count approach (sufficient for local/dev)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$table`");
    $stmt->execute();
    $count = (int)$stmt->fetchColumn();
    $num = $count + 1;
    return sprintf("%s%0{$width}d", $prefix, $num);
}
function nowDate(): string { return date('Y-m-d'); }

/* ----------------- Auth & Registration ----------------- */
function registerUser(string $username, string $password, string $role, array $extra = []): array {
    $pdo = dbConnect();
    try {
        $pdo->beginTransaction();
        $uid = generateId('U', 'users', 5);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (user_id, username, password_hash, role) VALUES (:uid,:u,:ph,:r)");
        $stmt->execute([':uid'=>$uid, ':u'=>$username, ':ph'=>$hash, ':r'=>$role]);

        if (strcasecmp($role, 'Donor') === 0) {
            $stmt = $pdo->prepare("INSERT INTO donor (donor_id, cnic, name, age, gender, blood_group, location, last_donation_date, availability, phone_number)
                VALUES (:did, :cnic, :name, :age, :gender, :bg, :loc, NULL, :avail, :phone)");
            $stmt->execute([
                ':did'=>$uid,
                ':cnic'=>$extra['cnic'] ?? null,
                ':name'=>$extra['name'] ?? $username,
                ':age'=>$extra['age'] ?? null,
                ':gender'=>$extra['gender'] ?? 'Male',
                ':bg'=>$extra['blood_group'] ?? null,
                ':loc'=>$extra['location'] ?? null,
                ':avail'=>isset($extra['availability']) ? (int)$extra['availability'] : 1,
                ':phone'=>$extra['phone'] ?? null
            ]);
        } elseif (strcasecmp($role, 'Recipient') === 0) {
            $stmt = $pdo->prepare("INSERT INTO recipient (recipient_id, name, blood_group, location, urgency, required_date)
                VALUES (:rid, :name, :bg, :loc, :urgency, :reqd)");
            $stmt->execute([
                ':rid'=>$uid,
                ':name'=>$extra['name'] ?? $username,
                ':bg'=>$extra['blood_group'] ?? null,
                ':loc'=>$extra['location'] ?? null,
                ':urgency'=>$extra['urgency'] ?? 'Medium',
                ':reqd'=>$extra['required_date'] ?? null
            ]);
        }

        $pdo->commit();
        return ['success'=>true, 'message'=>'Registration successful. Please login.'];
    } catch (PDOException $e) {
        $pdo->rollBack();
        if (isset($e->errorInfo[1]) && $e->errorInfo[1] === 1062) {
            return ['success'=>false, 'message'=>'Duplicate entry (username or unique field).'];
        }
        return ['success'=>false, 'message'=>'Registration error: ' . $e->getMessage()];
    }
}

function loginUser(string $username, string $password, ?string $expectedRole = null): array {
    $pdo = dbConnect();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :u LIMIT 1");
    $stmt->execute([':u'=>$username]);
    $u = $stmt->fetch();
    if (!$u) return ['success'=>false, 'message'=>'Invalid username or password.'];
    if (!password_verify($password, $u['password_hash'])) return ['success'=>false, 'message'=>'Invalid username or password.'];
    if ($expectedRole && strcasecmp($u['role'], $expectedRole) !== 0) {
        return ['success'=>false, 'message'=>"This account is not a $expectedRole account."];
    }

    $_SESSION['user_id'] = $u['user_id'];
    $_SESSION['username'] = $u['username'];
    $_SESSION['role'] = $u['role'];

    // attach display name from donor/recipient if present
    if (strcasecmp($u['role'],'Donor')===0) {
        $s = $pdo->prepare("SELECT name FROM donor WHERE donor_id = :id LIMIT 1");
        $s->execute([':id'=>$u['user_id']]);
        $r = $s->fetch();
        $_SESSION['name'] = $r['name'] ?? $u['username'];
    } elseif (strcasecmp($u['role'],'Recipient')===0) {
        $s = $pdo->prepare("SELECT name FROM recipient WHERE recipient_id = :id LIMIT 1");
        $s->execute([':id'=>$u['user_id']]);
        $r = $s->fetch();
        $_SESSION['name'] = $r['name'] ?? $u['username'];
    } else {
        $_SESSION['name'] = $u['username'];
    }

    return ['success'=>true, 'message'=>'Login successful.'];
}

function logoutUser(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* --------------- Donations & Inventory --------------- */
function createDonation(string $donor_id, string $donation_date, int $quantity_ml, ?string $blood_type = null, string $screening_status = 'Pending'): bool {
    $pdo = dbConnect();
    try {
        $did = generateId('DON', 'blood_donation', 5);
        $stmt = $pdo->prepare("INSERT INTO blood_donation (donation_id, donation_date, blood_type, blood_quantity_ml, screening_status, donor_id)
            VALUES (:id, :d, :b, :q, :s, :donor)");
        $stmt->execute([':id'=>$did, ':d'=>$donation_date, ':b'=>$blood_type, ':q'=>$quantity_ml, ':s'=>$screening_status, ':donor'=>$donor_id]);

        // If screening passed, add inventory automatically
        if (strcasecmp($screening_status, 'Passed') === 0) {
            $inv = generateId('I', 'blood_inventory', 5);
            $storage = nowDate();
            $expiry = date('Y-m-d', strtotime($storage . ' +42 days'));
            $stmt2 = $pdo->prepare("INSERT INTO blood_inventory (inventory_id, donor_id, blood_type, blood_quantity_ml, storage_date, expiry_date, status)
                VALUES (:iid, :donor, :b, :q, :storage, :expiry, 'Available')");
            $stmt2->execute([':iid'=>$inv, ':donor'=>$donor_id, ':b'=>$blood_type, ':q'=>$quantity_ml, ':storage'=>$storage, ':expiry'=>$expiry]);
        }

        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/* --------------- Requests --------------- */
function createRequest(string $recipient_id, int $quantity_ml, string $blood_type, ?string $facility_id = null, ?string $required_date = null): bool {
    $pdo = dbConnect();
    try {
        $rid = generateId('REQ', 'request', 5);
        $stmt = $pdo->prepare("INSERT INTO `request` (request_id, request_date, quantity_requested_ml, type_requested, recipient_id, facility_id, required_date)
            VALUES (:rid, :rdate, :qty, :type, :rec, :fac, :reqd)");
        $stmt->execute([':rid'=>$rid, ':rdate'=>nowDate(), ':qty'=>$quantity_ml, ':type'=>$blood_type, ':rec'=>$recipient_id, ':fac'=>$facility_id, ':reqd'=>$required_date]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/* --------------- Admin & Lookup --------------- */
function adminGetAllEmployees(): array {
    return dbConnect()->query("SELECT * FROM employee ORDER BY first_name, last_name")->fetchAll();
}
function adminGetAllDonors(): array {
    return dbConnect()->query("SELECT donor_id, name, blood_group, location, phone_number FROM donor ORDER BY name")->fetchAll();
}
function adminGetDEAssignments(): array {
    $sql = "SELECT d.donor_id, dn.name AS donor_name, e.employee_id, e.first_name AS emp_first, e.last_name AS emp_last
            FROM de d
            LEFT JOIN donor dn ON d.donor_id = dn.donor_id
            LEFT JOIN employee e ON d.employee_id = e.employee_id
            ORDER BY dn.name";
    return dbConnect()->query($sql)->fetchAll();
}
function assignEmployeeToDonor(string $donor_id, string $employee_id): bool {
    try {
        $stmt = dbConnect()->prepare("INSERT INTO de (donor_id, employee_id) VALUES (:d,:e)");
        $stmt->execute([':d'=>$donor_id, ':e'=>$employee_id]);
        return true;
    } catch (PDOException $e) { return false; }
}
function removeEmployeeAssignment(string $donor_id, string $employee_id): bool {
    try {
        $stmt = dbConnect()->prepare("DELETE FROM de WHERE donor_id = :d AND employee_id = :e LIMIT 1");
        $stmt->execute([':d'=>$donor_id, ':e'=>$employee_id]);
        return true;
    } catch (PDOException $e) { return false; }
}
function adminGetAllUsers(): array { return dbConnect()->query("SELECT * FROM users ORDER BY username")->fetchAll(); }
function adminGetAllDonations(): array {
    $sql = "SELECT bd.donation_id, bd.donation_date AS date, bd.blood_quantity_ml AS quantity, bd.screening_status AS status, dn.name
            FROM blood_donation bd
            LEFT JOIN donor dn ON bd.donor_id = dn.donor_id
            ORDER BY bd.donation_date DESC";
    return dbConnect()->query($sql)->fetchAll();
}
function adminGetAllRequests(): array {
    $sql = "SELECT r.request_id, r.request_date AS date, r.quantity_requested_ml AS quantity, r.type_requested AS blood_group, rc.name, r.required_date
            FROM request r
            LEFT JOIN recipient rc ON r.recipient_id = rc.recipient_id
            ORDER BY r.request_date DESC";
    return dbConnect()->query($sql)->fetchAll();
}
function adminGetStock(): array { return dbConnect()->query("SELECT * FROM blood_inventory ORDER BY blood_type")->fetchAll(); }

/* --------------- Public search & user retrievals --------------- */
function findDonor(string $blood_group = '', string $location = ''): array {
    $pdo = dbConnect();
    $sql = "SELECT donor_id, name, blood_group, location, phone_number FROM donor WHERE 1=1";
    $params = [];
    if ($blood_group !== '') { $sql .= " AND blood_group = :bg"; $params[':bg'] = $blood_group; }
    if ($location !== '') { $sql .= " AND location LIKE :loc"; $params[':loc'] = "%$location%"; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
function getUserDonations(string $user_id): array {
    $stmt = dbConnect()->prepare("SELECT donation_id, donation_date AS date, blood_quantity_ml AS quantity, screening_status AS status FROM blood_donation WHERE donor_id = :d ORDER BY donation_date DESC");
    $stmt->execute([':d'=>$user_id]);
    return $stmt->fetchAll();
}
function getUserRequests(string $user_id): array {
    $stmt = dbConnect()->prepare("SELECT request_id, quantity_requested_ml AS quantity, type_requested AS blood_group, required_date, request_date AS date FROM request WHERE recipient_id = :r ORDER BY request_date DESC");
    $stmt->execute([':r'=>$user_id]);
    return $stmt->fetchAll();
}
