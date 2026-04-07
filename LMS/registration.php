<?php
require 'database/database.php';

$message  = "";
$msg_type = "";

// Fetch all active courses from COURSE table
$courses = [];
$csql = oci_parse($conn, "SELECT COURSE_ID, COURSE_NAME, FEE, DURATION FROM COURSE WHERE STATUS='Active' ORDER BY COURSE_NAME");
oci_execute($csql);
while ($row = oci_fetch_assoc($csql)) $courses[] = $row;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f_name           = trim($_POST['f_name']          ?? '');
    $l_name           = trim($_POST['l_name']          ?? '');
    $email            = trim($_POST['email']           ?? '');
    $phone            = trim($_POST['phone']           ?? '');
    $branch           = trim($_POST['branch']          ?? '');
    $qualification    = trim($_POST['qualification']   ?? '');
    $selected_courses = $_POST['courses']              ?? [];
    $payment_type     = $_POST['payment_type']         ?? 'REGISTRATION';
    $payment_method   = $_POST['payment_method']       ?? 'CASH';
    $txn_ref          = trim($_POST['txn_ref']         ?? '');

    // Validations
    if (!$f_name || !$l_name || !$email || !$phone || !$branch) {
        $message  = "Please fill all required fields!";
        $msg_type = "danger";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message  = "Please enter a valid email address!";
        $msg_type = "danger";
    } elseif (empty($qualification)) {
        $message  = "Please select your qualification!";
        $msg_type = "danger";
    } elseif (empty($selected_courses)) {
        $message  = "Please select at least one course!";
        $msg_type = "danger";
    } elseif (empty($txn_ref)) {
        $message  = "Please enter transaction / receipt reference!";
        $msg_type = "danger";
    } else {
        // Check duplicate email
        $check = oci_parse($conn, "SELECT COUNT(*) AS CNT FROM STUDENTS WHERE EMAIL = :email");
        oci_bind_by_name($check, ':email', $email);
        oci_execute($check);
        $chk_row = oci_fetch_assoc($check);

        if ($chk_row['CNT'] > 0) {
            $message  = "This email is already registered! Please use a different email.";
            $msg_type = "danger";
        } else {
            // Calculate total fee directly from DB
            $total_fee = 0;
            if (!empty($selected_courses)) {
                $course_ids = implode(',', array_map('intval', $selected_courses));
                $fee_sql    = oci_parse($conn, "SELECT SUM(FEE) AS TOTAL FROM COURSE WHERE COURSE_ID IN ($course_ids)");
                oci_execute($fee_sql);
                $fee_row   = oci_fetch_assoc($fee_sql);
                $total_fee = (float)($fee_row['TOTAL'] ?? 0);
            }

            // Amount based on payment type
            if ($payment_type === 'FULL') {
                $amount_paid = $total_fee;
            } elseif ($payment_type === 'HALF') {
                $amount_paid = $total_fee * 0.5;
            } else {
                $amount_paid = $total_fee * 0.25;
            }

            $balance    = $total_fee - $amount_paid;
            $pay_status = ($balance <= 0) ? 'PAID' : 'PENDING';

            // Get next student ID from sequence
            $seq        = oci_parse($conn, "SELECT SEQ_STUDENT.NEXTVAL AS NID FROM DUAL");
            oci_execute($seq);
            $seq_row    = oci_fetch_assoc($seq);
            $student_id = (int)$seq_row['NID'];

            // Insert STUDENT
            $ins_sql = "INSERT INTO STUDENTS
                            (STUDENT_ID, F_NAME, L_NAME, EMAIL, PHONE,
                             BRANCH, QUALIFICATION,
                             REGISTER_DATE, STATUS, LOGIN_STATUS, PAYMENT_STATUS)
                        VALUES
                            (:sid, :fname, :lname, :email, :phone,
                             :branch, :qual,
                             SYSDATE, 'PENDING', 'PENDING', 'PENDING')";

            $ins_stmt = oci_parse($conn, $ins_sql);
            oci_bind_by_name($ins_stmt, ':sid',    $student_id);
            oci_bind_by_name($ins_stmt, ':fname',  $f_name);
            oci_bind_by_name($ins_stmt, ':lname',  $l_name);
            oci_bind_by_name($ins_stmt, ':email',  $email);
            oci_bind_by_name($ins_stmt, ':phone',  $phone);
            oci_bind_by_name($ins_stmt, ':branch', $branch);
            oci_bind_by_name($ins_stmt, ':qual',   $qualification);

            if (!oci_execute($ins_stmt, OCI_NO_AUTO_COMMIT)) {
                $e        = oci_error($ins_stmt);
                $message  = "Student insert failed: " . $e['message'];
                $msg_type = "danger";
            } else {
                $error_occurred = false;
                $first_enr_id   = null;
                $course_list    = array_values($selected_courses);

                // Insert ENTROLLMENT — one row per course
                foreach ($course_list as $idx => $cid) {
                    $cid = (int)$cid;

                    $enr_seq  = oci_parse($conn, "SELECT SEQ_ENTROLLMENT.NEXTVAL AS NID FROM DUAL");
                    oci_execute($enr_seq);
                    $enr_row  = oci_fetch_assoc($enr_seq);
                    $enr_id   = (int)$enr_row['NID'];

                    if ($idx === 0) $first_enr_id = $enr_id;

                    $enr_sql  = "INSERT INTO ENTROLLMENT
                                    (ENTROLLMENT_ID, STUDENT_ID, COURSE_ID, JOINED_DATE, STATUS)
                                 VALUES (:eid, :sid, :cid, SYSDATE, 'PENDING')";
                    $enr_stmt = oci_parse($conn, $enr_sql);
                    oci_bind_by_name($enr_stmt, ':eid', $enr_id);
                    oci_bind_by_name($enr_stmt, ':sid', $student_id);
                    oci_bind_by_name($enr_stmt, ':cid', $cid);

                    if (!oci_execute($enr_stmt, OCI_NO_AUTO_COMMIT)) {
                        $e              = oci_error($enr_stmt);
                        $message        = "Enrollment insert failed: " . $e['message'];
                        $msg_type       = "danger";
                        $error_occurred = true;
                        oci_rollback($conn);
                        break;
                    }
                }

                if (!$error_occurred) {
                    // Insert PAYMENT
                    $pay_seq  = oci_parse($conn, "SELECT SEQ_PAYMENT.NEXTVAL AS NID FROM DUAL");
                    oci_execute($pay_seq);
                    $pay_row  = oci_fetch_assoc($pay_seq);
                    $pay_id   = (int)$pay_row['NID'];

                    $pay_sql  = "INSERT INTO PAYMENT
                                    (PAYMENT_ID, AMOUNT, PAYMENT_DATE, PAYMENT_METHOD,
                                     STATUS, TRANSACTION_REF, STUDENT_ID, ENTROLLMENT_ID,
                                     PAYMENT_TYPE, TOTAL_AMOUNT, PAID_AMOUNT, BALANCE)
                                 VALUES
                                    (:pid, :amt, SYSDATE, :method,
                                     :pstatus, :txn, :sid, :eid,
                                     :ptype, :total, :paid, :bal)";

                    $pay_stmt = oci_parse($conn, $pay_sql);
                    oci_bind_by_name($pay_stmt, ':pid',     $pay_id);
                    oci_bind_by_name($pay_stmt, ':amt',     $amount_paid);
                    oci_bind_by_name($pay_stmt, ':method',  $payment_method);
                    oci_bind_by_name($pay_stmt, ':pstatus', $pay_status);
                    oci_bind_by_name($pay_stmt, ':txn',     $txn_ref);
                    oci_bind_by_name($pay_stmt, ':sid',     $student_id);
                    oci_bind_by_name($pay_stmt, ':eid',     $first_enr_id);
                    oci_bind_by_name($pay_stmt, ':ptype',   $payment_type);
                    oci_bind_by_name($pay_stmt, ':total',   $total_fee);
                    oci_bind_by_name($pay_stmt, ':paid',    $amount_paid);
                    oci_bind_by_name($pay_stmt, ':bal',     $balance);

                    if (!oci_execute($pay_stmt, OCI_NO_AUTO_COMMIT)) {
                        $e        = oci_error($pay_stmt);
                        $message  = "Payment insert failed: " . $e['message'];
                        $msg_type = "danger";
                        oci_rollback($conn);
                    } else {
                        oci_commit($conn);
                        $message  = "success";
                        $msg_type = "success";
                    }
                }
            }
        }
    }
}
oci_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration — Transcendant LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background:#121212; color:#fff; font-family:'Segoe UI',sans-serif; }
        .main-card { background:#1e1e1e; border:1px solid #333; border-radius:16px; }
        .section-header {
            background:#2a2a2a; border-radius:10px; padding:10px 16px;
            margin-bottom:16px; font-size:0.85rem; font-weight:700;
            text-transform:uppercase; letter-spacing:1px; color:#f5a623;
        }
        .form-control, .form-select {
            background:#2a2a2a; border:1px solid #444; color:#fff; border-radius:8px;
        }
        .form-control:focus, .form-select:focus {
            background:#333; border-color:#f5a623; color:#fff;
            box-shadow:0 0 0 3px rgba(245,166,35,0.15);
        }
        .form-control::placeholder { color:#666; }
        .form-select option { background:#1e1e1e; }
        .form-label { color:#aaa; font-size:0.85rem; font-weight:600; }
        .course-label { cursor:pointer; display:block; height:100%; }
        .course-card {
            background:#2a2a2a; border:2px solid #444;
            border-radius:12px; padding:14px; transition:all 0.2s; height:100%;
        }
        .course-card:hover  { border-color:#f5a623; background:#333; }
        .course-card.selected { border-color:#f5a623; background:rgba(245,166,35,0.1); }
        .check-box {
            width:20px; height:20px; border:2px solid #555; border-radius:5px;
            display:inline-flex; align-items:center; justify-content:center;
            margin-bottom:8px; transition:all 0.2s;
        }
        .course-card.selected .check-box { background:#f5a623; border-color:#f5a623; }
        .course-name { font-weight:700; font-size:0.9rem; color:#fff; }
        .course-fee  { font-size:0.85rem; color:#f5a623; font-weight:700; margin-top:6px; }
        .course-dur  { font-size:0.78rem; color:#888; }
        .pay-card {
            background:#2a2a2a; border:2px solid #444; border-radius:12px;
            padding:16px; transition:all 0.2s; text-align:center; cursor:pointer;
        }
        .pay-card:hover    { border-color:#f5a623; }
        .pay-card.selected { border-color:#f5a623; background:rgba(245,166,35,0.1); }
        .fee-summary { background:#2a2a2a; border:1px solid #444; border-radius:10px; padding:14px; }
        .btn-register {
            background:linear-gradient(135deg,#f5a623,#e94560); border:none;
            border-radius:10px; padding:13px; font-weight:700; font-size:1rem;
            color:#fff; width:100%; transition:all 0.3s;
        }
        .btn-register:hover { opacity:0.9; transform:translateY(-2px); color:#fff; }
        .success-box {
            background:#1a2e1a; border:1px solid #2d5a2d;
            border-radius:16px; padding:40px; text-align:center;
        }
    </style>
</head>
<body>
<div class="container py-5">
<div class="row justify-content-center">
<div class="col-lg-8">

    <div class="text-center mb-4">
        <h3 class="fw-bold text-white">
            <i class="fas fa-graduation-cap me-2" style="color:#f5a623;"></i>Transcendant LMS
        </h3>
        <p class="text-secondary">Student Registration & Enrollment</p>
    </div>

    <?php if ($msg_type === 'success'): ?>
    <div class="success-box">
        <div style="font-size:3rem;">✅</div>
        <h4 class="fw-bold text-white mt-3">Registration Submitted!</h4>
        <p class="text-secondary mt-2">Your registration has been received successfully.</p>
        <div class="alert mt-3" style="background:#2a3a2a;border:1px solid #3d6b3d;border-radius:10px;color:#aed6a0;font-size:0.9rem;">
            <i class="fas fa-info-circle me-2"></i>
            Your account is <strong>pending admin approval</strong>.<br>
            Once approved, the admin will give you your <strong>Student ID and Password</strong> to login.
        </div>
        <a href="Login_student.php?logout=1" class="btn btn-warning mt-3 px-5 fw-bold rounded-pill">Go to Login</a>
    </div>

    <?php else: ?>

    <div class="main-card p-4">

        <?php if ($message && $msg_type === 'danger'): ?>
        <div class="alert alert-danger rounded-3 mb-4">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="Registration.php">

            <!-- STEP 1: Personal Info -->
            <div class="section-header"><i class="fas fa-user me-2"></i>Step 1 — Personal Information</div>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">First Name *</label>
                    <input type="text" name="f_name" class="form-control" placeholder="Enter first name"
                           value="<?= htmlspecialchars($_POST['f_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Last Name *</label>
                    <input type="text" name="l_name" class="form-control" placeholder="Enter last name"
                           value="<?= htmlspecialchars($_POST['l_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email Address *</label>
                    <input type="email" name="email" class="form-control" placeholder="your@email.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone Number *</label>
                    <input type="text" name="phone" class="form-control" placeholder="07X XXX XXXX"
                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Branch *</label>
                    <input type="text" name="branch" class="form-control" placeholder="e.g. Colombo"
                           value="<?= htmlspecialchars($_POST['branch'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Qualification *</label>
                    <select name="qualification" class="form-select" required>
                        <option value="">-- Select Qualification --</option>
                        <option value="O/L"     <?= ($_POST['qualification'] ?? '') === 'O/L'     ? 'selected' : '' ?>>O/L (Ordinary Level)</option>
                        <option value="A/L"     <?= ($_POST['qualification'] ?? '') === 'A/L'     ? 'selected' : '' ?>>A/L (Advanced Level)</option>
                        <option value="Diploma" <?= ($_POST['qualification'] ?? '') === 'Diploma' ? 'selected' : '' ?>>Diploma</option>
                        <option value="Degree"  <?= ($_POST['qualification'] ?? '') === 'Degree'  ? 'selected' : '' ?>>Degree</option>
                        <option value="HND"     <?= ($_POST['qualification'] ?? '') === 'HND'     ? 'selected' : '' ?>>HND</option>
                        <option value="Other"   <?= ($_POST['qualification'] ?? '') === 'Other'   ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
            </div>

            <!-- STEP 2: Course Selection -->
            <div class="section-header"><i class="fas fa-graduation-cap me-2"></i>Step 2 — Select Courses</div>
            <p style="font-size:0.83rem;color:#888;" class="mb-3">Click to select one or more courses. Fees update automatically.</p>

            <?php if (empty($courses)): ?>
                <div class="alert alert-warning">No active courses available at this time.</div>
            <?php else: ?>
            <div class="row g-3 mb-3">
                <?php foreach ($courses as $c):
                    $sel = isset($_POST['courses']) && in_array($c['COURSE_ID'], $_POST['courses']);
                ?>
                <div class="col-md-6">
                    <label class="course-label">
                        <input type="checkbox" name="courses[]"
                               value="<?= $c['COURSE_ID'] ?>"
                               data-fee="<?= $c['FEE'] ?>"
                               class="course-cb d-none"
                               <?= $sel ? 'checked' : '' ?>>
                        <div class="course-card <?= $sel ? 'selected' : '' ?>">
                            <div class="check-box">
                                <i class="fas fa-check text-dark" style="font-size:10px;"></i>
                            </div>
                            <div class="course-name"><?= htmlspecialchars($c['COURSE_NAME']) ?></div>
                            <div class="course-dur"><i class="fas fa-clock me-1"></i><?= htmlspecialchars($c['DURATION'] ?? 'N/A') ?></div>
                            <div class="course-fee">Rs. <?= number_format($c['FEE'], 0) ?></div>
                        </div>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>

            <div id="feeSummary" class="fee-summary mb-4" style="display:none;">
                <div class="row text-center g-2">
                    <div class="col-4">
                        <div style="font-size:0.75rem;color:#888;">Total Fee</div>
                        <div id="totalFee" style="font-weight:700;color:#f5a623;">Rs. 0</div>
                    </div>
                    <div class="col-4">
                        <div style="font-size:0.75rem;color:#888;">Reg. Fee (25%)</div>
                        <div id="regFee" style="font-weight:700;color:#4fc3f7;">Rs. 0</div>
                    </div>
                    <div class="col-4">
                        <div style="font-size:0.75rem;color:#888;">Half (50%)</div>
                        <div id="halfFee" style="font-weight:700;color:#81c784;">Rs. 0</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- STEP 3: Payment -->
            <div class="section-header"><i class="fas fa-credit-card me-2"></i>Step 3 — Payment</div>
            <p style="font-size:0.83rem;color:#888;" class="mb-3">Choose your payment option.</p>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="d-block">
                        <input type="radio" name="payment_type" value="REGISTRATION" class="pay-radio d-none" checked>
                        <div class="pay-card selected">
                            <i class="fas fa-file-invoice" style="color:#f5a623;font-size:1.3rem;"></i>
                            <div class="fw-bold text-white mt-2" style="font-size:0.9rem;">Registration Fee</div>
                            <div id="disp_reg" style="color:#f5a623;font-weight:700;margin-top:4px;font-size:0.85rem;">25% of total</div>
                            <div style="font-size:0.75rem;color:#888;margin-top:4px;">Pay balance later</div>
                        </div>
                    </label>
                </div>
                <div class="col-md-4">
                    <label class="d-block">
                        <input type="radio" name="payment_type" value="HALF" class="pay-radio d-none">
                        <div class="pay-card">
                            <i class="fas fa-adjust" style="color:#f5a623;font-size:1.3rem;"></i>
                            <div class="fw-bold text-white mt-2" style="font-size:0.9rem;">Half Payment</div>
                            <div id="disp_half" style="color:#f5a623;font-weight:700;margin-top:4px;font-size:0.85rem;">50% of total</div>
                            <div style="font-size:0.75rem;color:#888;margin-top:4px;">Pay remaining later</div>
                        </div>
                    </label>
                </div>
                <div class="col-md-4">
                    <label class="d-block">
                        <input type="radio" name="payment_type" value="FULL" class="pay-radio d-none">
                        <div class="pay-card">
                            <i class="fas fa-star" style="color:#f5a623;font-size:1.3rem;"></i>
                            <div class="fw-bold text-white mt-2" style="font-size:0.9rem;">Full Payment</div>
                            <div id="disp_full" style="color:#f5a623;font-weight:700;margin-top:4px;font-size:0.85rem;">100% of total</div>
                            <div style="font-size:0.75rem;color:#888;margin-top:4px;">No balance remaining</div>
                        </div>
                    </label>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Payment Method *</label>
                    <select name="payment_method" class="form-select">
                        <option value="CASH"         <?= ($_POST['payment_method'] ?? '') === 'CASH'         ? 'selected' : '' ?>>Cash</option>
                        <option value="BANK_TRANSFER" <?= ($_POST['payment_method'] ?? '') === 'BANK_TRANSFER' ? 'selected' : '' ?>>Bank Transfer</option>
                        <option value="CARD"          <?= ($_POST['payment_method'] ?? '') === 'CARD'          ? 'selected' : '' ?>>Debit / Credit Card</option>
                        <option value="ONLINE"        <?= ($_POST['payment_method'] ?? '') === 'ONLINE'        ? 'selected' : '' ?>>Online Payment</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Transaction / Receipt Number *</label>
                    <input type="text" name="txn_ref" class="form-control"
                           placeholder="e.g. TXN-2025-00123"
                           value="<?= htmlspecialchars($_POST['txn_ref'] ?? '') ?>" required>
                    <div style="font-size:0.75rem;color:#666;margin-top:4px;">Bank slip reference or cash receipt number</div>
                </div>
            </div>

            <button type="submit" class="btn-register">
                <i class="fas fa-paper-plane me-2"></i>Submit Registration
            </button>

            <p class="text-center mt-3 text-secondary" style="font-size:0.85rem;">
                Already have an account?
                <a href="Login_student.php?logout=1" class="text-warning">Login here</a>
            </p>

        </form>
    </div>
    <?php endif; ?>

</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Course toggle
document.querySelectorAll('.course-label').forEach(function(label) {
    label.addEventListener('click', function(e) {
        e.preventDefault();
        var cb = this.querySelector('.course-cb');
        cb.checked = !cb.checked;
        this.querySelector('.course-card').classList.toggle('selected', cb.checked);
        updateFees();
    });
});

// Make sure checkboxes submit correctly
document.querySelector('form').addEventListener('submit', function() {
    document.querySelectorAll('.course-cb').forEach(function(cb) {
        cb.disabled = false;
    });
});

// Payment option toggle
document.querySelectorAll('.pay-radio').forEach(function(r) {
    r.closest('label').addEventListener('click', function() {
        r.checked = true;
        document.querySelectorAll('.pay-card').forEach(function(c) { c.classList.remove('selected'); });
        this.querySelector('.pay-card').classList.add('selected');
    });
});

// Fee calculator
function updateFees() {
    var total = 0;
    document.querySelectorAll('.course-cb:checked').forEach(function(cb) {
        total += parseFloat(cb.dataset.fee || 0);
    });
    var fmt = function(v) { return 'Rs. ' + Math.round(v).toLocaleString(); };
    document.getElementById('totalFee').textContent  = fmt(total);
    document.getElementById('regFee').textContent    = fmt(total * 0.25);
    document.getElementById('halfFee').textContent   = fmt(total * 0.50);
    document.getElementById('disp_reg').textContent  = total > 0 ? fmt(total * 0.25)  : '25% of total';
    document.getElementById('disp_half').textContent = total > 0 ? fmt(total * 0.50)  : '50% of total';
    document.getElementById('disp_full').textContent = total > 0 ? fmt(total)          : '100% of total';
    document.getElementById('feeSummary').style.display = total > 0 ? 'block' : 'none';
}
updateFees();
</script>
</body>
</html>