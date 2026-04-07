<?php
session_start();
require 'database/database.php';

// Redirect to login if not logged in
if (!isset($_SESSION['student_id'])) {
   header("Location: Login_student.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// ── Get student details ───────────────────────────────────────
$sql_student = oci_parse($conn,
    "SELECT STUDENT_ID, F_NAME, L_NAME, EMAIL, PHONE,
            BRANCH, BATCH_NAME, REGISTER_DATE, STATUS,
            LOGIN_STATUS, PAYMENT_STATUS
     FROM STUDENTS
     WHERE STUDENT_ID = :sid");
oci_bind_by_name($sql_student, ':sid', $student_id);
oci_execute($sql_student);
$student = oci_fetch_assoc($sql_student);

if (!$student) {
    session_destroy();
    header("Location: Login_student.php");
    exit();
}

// ── Get enrolled courses ──────────────────────────────────────
$sql_enroll = oci_parse($conn,
    "SELECT e.ENTROLLMENT_ID, e.JOINED_DATE, e.STATUS,
            c.COURSE_NAME, c.DURATION, c.FEE
     FROM ENTROLLMENT e
     JOIN COURSE c ON e.COURSE_ID = c.COURSE_ID
     WHERE e.STUDENT_ID = :sid
     ORDER BY e.JOINED_DATE");
oci_bind_by_name($sql_enroll, ':sid', $student_id);
oci_execute($sql_enroll);
$enrollments = [];
while ($row = oci_fetch_assoc($sql_enroll)) $enrollments[] = $row;

// ── Get payments ──────────────────────────────────────────────
$sql_payment = oci_parse($conn,
    "SELECT PAYMENT_ID, AMOUNT, PAID_AMOUNT, TOTAL_AMOUNT,
            BALANCE, PAYMENT_DATE, PAYMENT_METHOD,
            PAYMENT_TYPE, STATUS, TRANSACTION_REF
     FROM PAYMENT
     WHERE STUDENT_ID = :sid
     ORDER BY PAYMENT_DATE DESC");
oci_bind_by_name($sql_payment, ':sid', $student_id);
oci_execute($sql_payment);
$payments = [];
while ($row = oci_fetch_assoc($sql_payment)) $payments[] = $row;

// ── Get certificates ──────────────────────────────────────────
$sql_cert = oci_parse($conn,
    "SELECT cert.CERTIFICATE_ID, cert.ISSUED_DATE,
            cert.STATUS, cert.GRADE, cert.CERTIFICATE_URL,
            c.COURSE_NAME
     FROM CERTIFICATE cert
     JOIN COURSE c ON cert.COURSE_ID = c.COURSE_ID
     WHERE cert.STUDENT_ID = :sid");
oci_bind_by_name($sql_cert, ':sid', $student_id);
oci_execute($sql_cert);
$certificates = [];
while ($row = oci_fetch_assoc($sql_cert)) $certificates[] = $row;

// ── Get assignments for enrolled courses ─────────────────────
$sql_assign = oci_parse($conn,
    "SELECT DISTINCT a.ASSIGNMENT_ID, a.TITLE, a.DESCRIPTION,
            a.DUE_DATE, a.MAX_MARKS, c.COURSE_NAME,
            m.MODULE_NAME
     FROM ASSIGNMENT a
     JOIN COURSE c ON a.COURSE_ID = c.COURSE_ID
     LEFT JOIN MODULES m ON a.MODULE_ID = m.MODULE_ID
     WHERE a.COURSE_ID IN (
         SELECT COURSE_ID FROM ENTROLLMENT WHERE STUDENT_ID = :sid
     )
     ORDER BY a.DUE_DATE");
oci_bind_by_name($sql_assign, ':sid', $student_id);
oci_execute($sql_assign);
$assignments = [];
while ($row = oci_fetch_assoc($sql_assign)) $assignments[] = $row;

// ── Payment summary ───────────────────────────────────────────
$total_paid    = array_sum(array_column($payments, 'PAID_AMOUNT'));
$total_balance = array_sum(array_column($payments, 'BALANCE'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard — Transcendant LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --sidebar-width:260px; --primary:#1a237e; --primary-light:#0d47a1; }
        body { background:#f4f6f9; font-family:'Segoe UI',sans-serif; }
        /* SIDEBAR */
        .sidebar {
            width:var(--sidebar-width);
            background:linear-gradient(180deg,var(--primary),var(--primary-light));
            min-height:100vh; position:fixed; top:0; left:0;
            z-index:100; box-shadow:4px 0 15px rgba(0,0,0,0.15);
        }
        .sidebar-brand { padding:25px 20px; border-bottom:1px solid rgba(255,255,255,0.1); }
        .profile-avatar {
            width:70px; height:70px; border-radius:50%;
            background:rgba(255,255,255,0.2);
            display:flex; align-items:center; justify-content:center;
            font-size:1.8rem; color:white; margin:0 auto 10px;
            border:3px solid rgba(255,255,255,0.3);
        }
        .sidebar .nav-link {
            color:rgba(255,255,255,0.75); padding:12px 20px;
            border-radius:10px; margin:3px 10px; font-size:0.9rem;
            transition:all 0.3s; cursor:pointer;
        }
        .sidebar .nav-link:hover { background:rgba(255,255,255,0.15); color:white; transform:translateX(5px); }
        .sidebar .nav-link.active { background:rgba(255,255,255,0.25); color:white; font-weight:600; }
        .sidebar .nav-link i { width:20px; margin-right:10px; }
        /* MAIN */
        .main-content { margin-left:var(--sidebar-width); padding:25px; min-height:100vh; }
        .topbar {
            background:white; border-radius:15px; padding:15px 25px;
            margin-bottom:25px; box-shadow:0 2px 10px rgba(0,0,0,0.05);
            display:flex; justify-content:space-between; align-items:center;
        }
        /* CARDS */
        .stat-card { border:none; border-radius:15px; padding:20px; box-shadow:0 4px 15px rgba(0,0,0,0.08); transition:transform 0.3s; height:100%; }
        .stat-card:hover { transform:translateY(-5px); }
        .stat-icon { width:55px; height:55px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.4rem; margin-bottom:15px; }
        /* SECTIONS */
        .content-section { display:none; }
        .content-section.active { display:block; }
        .content-card { background:white; border-radius:15px; padding:25px; box-shadow:0 2px 10px rgba(0,0,0,0.05); border:none; margin-bottom:20px; }
        /* TABLE */
        .table thead th { background:var(--primary); color:white; border:none; padding:12px 15px; }
        .table tbody tr:hover { background:#f8f9ff; }
        /* COURSE CARD */
        .course-card { border:none; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.06); padding:18px; transition:transform 0.3s; margin-bottom:12px; border-left:4px solid #1a237e; }
        .course-card:hover { transform:translateY(-3px); }
        /* ASSIGNMENT CARD */
        .assignment-card { border:none; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.06); transition:transform 0.3s; }
        .assignment-card:hover { transform:translateY(-3px); }
        /* CERT CARD */
        .cert-card { border:none; border-radius:15px; box-shadow:0 4px 15px rgba(0,0,0,0.08); text-align:center; padding:30px; transition:transform 0.3s; }
        .cert-card:hover { transform:translateY(-5px); }
        .cert-icon { font-size:4rem; color:#ffc107; margin-bottom:15px; }
        /* Balance warning */
        .balance-alert { background:linear-gradient(135deg,#fff3cd,#ffeaa7); border:1px solid #ffc107; border-radius:12px; padding:14px 18px; margin-bottom:16px; font-size:0.88rem; color:#856404; }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-brand text-center text-white">
        <div class="profile-avatar">
            <i class="fas fa-user-graduate"></i>
        </div>
        <h6 class="fw-bold mb-0"><?= htmlspecialchars($student['F_NAME'].' '.$student['L_NAME']) ?></h6>
        <small class="opacity-75"><?= htmlspecialchars($student['BATCH_NAME'] ?? '') ?></small>
        <div class="mt-2">
            <span class="badge bg-<?= $student['STATUS']==='ACTIVE'?'success':'warning text-dark' ?> px-3">
                <?= htmlspecialchars($student['STATUS']) ?>
            </span>
        </div>
    </div>
    <nav class="nav flex-column mt-3">
        <a class="nav-link active" onclick="showSection('overview',this)"><i class="fas fa-th-large"></i> Overview</a>
        <a class="nav-link" onclick="showSection('profile',this)"><i class="fas fa-user"></i> My Profile</a>
        <a class="nav-link" onclick="showSection('enrollment',this)"><i class="fas fa-clipboard-list"></i> My Courses</a>
        <a class="nav-link" onclick="showSection('payments',this)"><i class="fas fa-credit-card"></i> Payments</a>
        <a class="nav-link" onclick="showSection('assignments',this)"><i class="fas fa-tasks"></i> Assignments</a>
        <a class="nav-link" onclick="showSection('certificates',this)"><i class="fas fa-certificate"></i> Certificates</a>
        <hr style="border-color:rgba(255,255,255,0.2);margin:10px 20px;">
        <a class="nav-link text-danger" href="Login_student.php?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">

    <!-- TOPBAR -->
    <div class="topbar">
        <div>
            <h5 class="fw-bold mb-0">Student Dashboard</h5>
            <small class="text-muted">Welcome back, <?= htmlspecialchars($student['F_NAME']) ?>!</small>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted small"><i class="fas fa-calendar me-1"></i><?= date('D, d M Y') ?></span>
            <div class="rounded-circle bg-primary text-white d-flex align-items:center justify-content-center"
                 style="width:40px;height:40px;font-weight:bold;display:flex;align-items:center;justify-content:center;">
                <?= strtoupper(substr($student['F_NAME'], 0, 1)) ?>
            </div>
        </div>
    </div>

    <!-- ══ OVERVIEW ══ -->
    <div id="overview" class="content-section active">
        <div class="row g-4 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card" style="background:linear-gradient(135deg,#1a237e,#0d47a1);">
                    <div class="stat-icon" style="background:rgba(255,255,255,0.2)"><i class="fas fa-book text-white"></i></div>
                    <h6 class="text-white opacity-75">Courses</h6>
                    <h3 class="text-white fw-bold"><?= count($enrollments) ?></h3>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card" style="background:linear-gradient(135deg,#1b5e20,#388e3c);">
                    <div class="stat-icon" style="background:rgba(255,255,255,0.2)"><i class="fas fa-money-bill text-white"></i></div>
                    <h6 class="text-white opacity-75">Total Paid</h6>
                    <h4 class="text-white fw-bold">Rs. <?= number_format($total_paid, 0) ?></h4>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card" style="background:linear-gradient(135deg,#e65100,#f57c00);">
                    <div class="stat-icon" style="background:rgba(255,255,255,0.2)"><i class="fas fa-exclamation-circle text-white"></i></div>
                    <h6 class="text-white opacity-75">Balance Due</h6>
                    <h4 class="text-white fw-bold">Rs. <?= number_format($total_balance, 0) ?></h4>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card" style="background:linear-gradient(135deg,#880e4f,#c2185b);">
                    <div class="stat-icon" style="background:rgba(255,255,255,0.2)"><i class="fas fa-certificate text-white"></i></div>
                    <h6 class="text-white opacity-75">Certificates</h6>
                    <h3 class="text-white fw-bold"><?= count($certificates) ?></h3>
                </div>
            </div>
        </div>

        <?php if ($total_balance > 0): ?>
        <div class="balance-alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Outstanding Balance:</strong> You have Rs. <?= number_format($total_balance, 0) ?> remaining. Please contact admin to clear your balance.
        </div>
        <?php endif; ?>

        <div class="content-card">
            <div class="d-flex align-items-center mb-4">
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-3"
                     style="width:55px;height:55px;font-size:1.5rem;font-weight:bold;">
                    <?= strtoupper(substr($student['F_NAME'], 0, 1)) ?>
                </div>
                <div>
                    <h5 class="fw-bold mb-0"><?= htmlspecialchars($student['F_NAME'].' '.$student['L_NAME']) ?></h5>
                    <small class="text-muted"><?= htmlspecialchars($student['EMAIL']) ?></small>
                </div>
                <span class="badge bg-<?= $student['STATUS']==='ACTIVE'?'success':'warning text-dark' ?> ms-auto px-3 py-2">
                    <?= htmlspecialchars($student['STATUS']) ?>
                </span>
            </div>
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="p-3 rounded-3" style="background:#f8f9ff;">
                        <small class="text-muted d-block">Branch</small>
                        <strong><?= htmlspecialchars($student['BRANCH'] ?? 'N/A') ?></strong>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 rounded-3" style="background:#f8f9ff;">
                        <small class="text-muted d-block">Qualification</small>
                        <strong><?= htmlspecialchars($student['BATCH_NAME'] ?? 'N/A') ?></strong>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 rounded-3" style="background:#f8f9ff;">
                        <small class="text-muted d-block">Phone</small>
                        <strong><?= htmlspecialchars($student['PHONE'] ?? 'N/A') ?></strong>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 rounded-3" style="background:#f8f9ff;">
                        <small class="text-muted d-block">Register Date</small>
                        <strong><?= $student['REGISTER_DATE'] ? date('d M Y', strtotime($student['REGISTER_DATE'])) : 'N/A' ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ PROFILE ══ -->
    <div id="profile" class="content-section">
        <div class="content-card">
            <h5 class="fw-bold mb-4"><i class="fas fa-user me-2 text-primary"></i>My Profile</h5>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="text-muted small fw-bold text-uppercase">First Name</label>
                    <div class="p-3 rounded-3 bg-light mt-1"><?= htmlspecialchars($student['F_NAME']) ?></div>
                </div>
                <div class="col-md-6">
                    <label class="text-muted small fw-bold text-uppercase">Last Name</label>
                    <div class="p-3 rounded-3 bg-light mt-1"><?= htmlspecialchars($student['L_NAME']) ?></div>
                </div>
                <div class="col-md-6">
                    <label class="text-muted small fw-bold text-uppercase">Email</label>
                    <div class="p-3 rounded-3 bg-light mt-1"><?= htmlspecialchars($student['EMAIL']) ?></div>
                </div>
                <div class="col-md-6">
                    <label class="text-muted small fw-bold text-uppercase">Phone</label>
                    <div class="p-3 rounded-3 bg-light mt-1"><?= htmlspecialchars($student['PHONE'] ?? 'N/A') ?></div>
                </div>
                <div class="col-md-6">
                    <label class="text-muted small fw-bold text-uppercase">Branch</label>
                    <div class="p-3 rounded-3 bg-light mt-1"><?= htmlspecialchars($student['BRANCH'] ?? 'N/A') ?></div>
                </div>
                <div class="col-md-6">
                    <label class="text-muted small fw-bold text-uppercase">Qualification</label>
                    <div class="p-3 rounded-3 bg-light mt-1"><?= htmlspecialchars($student['BATCH_NAME'] ?? 'N/A') ?></div>
                </div>
                <div class="col-md-6">
                    <label class="text-muted small fw-bold text-uppercase">Account Status</label>
                    <div class="mt-1">
                        <span class="badge bg-<?= $student['STATUS']==='ACTIVE'?'success':'warning text-dark' ?> px-3 py-2 fs-6">
                            <?= htmlspecialchars($student['STATUS']) ?>
                        </span>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="text-muted small fw-bold text-uppercase">Payment Status</label>
                    <div class="mt-1">
bg-<?= $student['PAYMENT_STATUS']==='PAID'?'success':($student['PAYMENT_STATUS']==='PENDING'?'warning text-dark':'danger') ?> px-3 py-2 fs-6">
                            <?= htmlspecialchars($student['PAYMENT_STATUS'] ?? 'N/A') ?>
                        </span>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="text-muted small fw-bold text-uppercase">Register Date</label>
                    <div class="p-3 rounded-3 bg-light mt-1">
                        <?= $student['REGISTER_DATE'] ? date('d M Y', strtotime($student['REGISTER_DATE'])) : 'N/A' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ MY COURSES ══ -->
    <div id="enrollment" class="content-section">
        <div class="content-card">
            <h5 class="fw-bold mb-4"><i class="fas fa-clipboard-list me-2 text-primary"></i>My Enrolled Courses</h5>
            <?php if (empty($enrollments)): ?>
            <div class="text-center text-muted py-4">
                <i class="fas fa-inbox fa-3x mb-3 d-block opacity-25"></i>
                No courses enrolled yet.
            </div>
            <?php else: ?>
            <?php foreach ($enrollments as $enroll): ?>
            <div class="course-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($enroll['COURSE_NAME']) ?></h6>
                        <div class="row g-3 mt-1">
                            <div class="col-auto">
                                <small class="text-muted"><i class="fas fa-clock me-1"></i><?= htmlspecialchars($enroll['DURATION'] ?? 'N/A') ?></small>
                            </div>
                            <div class="col-auto">
                                <small class="text-success fw-bold"><i class="fas fa-money-bill me-1"></i>Rs. <?= number_format($enroll['FEE'] ?? 0, 0) ?></small>
                            </div>
                            <div class="col-auto">
                                <small class="text-muted"><i class="fas fa-calendar me-1"></i>
                                    Joined: <?= $enroll['JOINED_DATE'] ? date('d M Y', strtotime($enroll['JOINED_DATE'])) : 'N/A' ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <span class="badge rounded-pill bg-<?= $enroll['STATUS']==='ACTIVE'?'success':($enroll['STATUS']==='COMPLETED'?'primary':'warning text-dark') ?>">
                        <?= htmlspecialchars($enroll['STATUS']) ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ══ PAYMENTS ══ -->
    <div id="payments" class="content-section">
        <div class="content-card">
            <h5 class="fw-bold mb-4"><i class="fas fa-credit-card me-2 text-primary"></i>Payment History</h5>
            <?php if (empty($payments)): ?>
            <div class="text-center text-muted py-4">
                <i class="fas fa-inbox fa-3x mb-3 d-block opacity-25"></i>No payment records found.
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th><th>Type</th><th>Paid</th><th>Total</th>
                            <th>Balance</th><th>Method</th><th>Date</th><th>Ref</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($payments as $pay): ?>
                    <tr>
                        <td><span class="badge bg-secondary">#<?= $pay['PAYMENT_ID'] ?></span></td>
                        <td><span class="badge bg-info text-dark"><?= htmlspecialchars($pay['PAYMENT_TYPE'] ?? '—') ?></span></td>
                        <td class="fw-bold text-success">Rs. <?= number_format($pay['PAID_AMOUNT'] ?? $pay['AMOUNT'], 0) ?></td>
                        <td>Rs. <?= number_format($pay['TOTAL_AMOUNT'] ?? 0, 0) ?></td>
                        <td class="<?= ($pay['BALANCE'] ?? 0) > 0 ? 'text-danger fw-bold' : 'text-success' ?>">
                            Rs. <?= number_format($pay['BALANCE'] ?? 0, 0) ?>
                        </td>
                        <td><?= htmlspecialchars($pay['PAYMENT_METHOD'] ?? '—') ?></td>
                        <td><?= $pay['PAYMENT_DATE'] ? date('d M Y', strtotime($pay['PAYMENT_DATE'])) : '—' ?></td>
                        <td><code><?= htmlspecialchars($pay['TRANSACTION_REF'] ?? '—') ?></code></td>
                        <td>
bg-<?= $pay['STATUS']==='PAID'?'success':($pay['STATUS']==='PENDING'?'warning text-dark':'danger') ?>">
                                <?= htmlspecialchars($pay['STATUS']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ══ ASSIGNMENTS ══ -->
    <div id="assignments" class="content-section">
        <div class="content-card">
            <h5 class="fw-bold mb-4"><i class="fas fa-tasks me-2 text-primary"></i>My Assignments</h5>
            <?php if (empty($assignments)): ?>
            <div class="text-center text-muted py-4">
                <i class="fas fa-inbox fa-3x mb-3 d-block opacity-25"></i>No assignments yet.
            </div>
            <?php else: ?>
            <div class="row g-3">
            <?php foreach ($assignments as $assign): ?>
                <div class="col-md-6">
                    <div class="assignment-card card p-4">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="fw-bold mb-0"><?= htmlspecialchars($assign['TITLE']) ?></h6>
                            <span class="badge bg-primary"><?= $assign['MAX_MARKS'] ?> marks</span>
                        </div>
                        <p class="text-muted small mb-3"><?= htmlspecialchars($assign['DESCRIPTION'] ?? '') ?></p>
                        <div class="d-flex justify-content-between align-items-center">
                            <?php if ($assign['MODULE_NAME']): ?>
                            <span class="badge bg-info text-dark">
                                <i class="fas fa-cube me-1"></i><?= htmlspecialchars($assign['MODULE_NAME']) ?>
                            </span>
                            <?php else: ?>
                            <span class="badge bg-secondary">
                                <i class="fas fa-book me-1"></i><?= htmlspecialchars($assign['COURSE_NAME']) ?>
                            </span>
                            <?php endif; ?>
                            <small class="text-danger fw-bold">
                                <i class="fas fa-calendar me-1"></i>
                                Due: <?= $assign['DUE_DATE'] ? date('d M Y', strtotime($assign['DUE_DATE'])) : '—' ?>
                            </small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ══ CERTIFICATES ══ -->
    <div id="certificates" class="content-section">
        <div class="content-card">
            <h5 class="fw-bold mb-4"><i class="fas fa-certificate me-2 text-primary"></i>My Certificates</h5>
            <?php if (empty($certificates)): ?>
            <div class="text-center text-muted py-4">
                <i class="fas fa-certificate fa-3x mb-3 d-block opacity-25"></i>No certificates issued yet.
            </div>
            <?php else: ?>
            <div class="row g-4">
            <?php foreach ($certificates as $cert): ?>
                <div class="col-md-4">
                    <div class="cert-card">
                        <div class="cert-icon"><i class="fas fa-certificate"></i></div>
                        <h6 class="fw-bold"><?= htmlspecialchars($cert['COURSE_NAME']) ?></h6>
                        <p class="text-muted small">
                            Issued: <?= $cert['ISSUED_DATE'] ? date('d M Y', strtotime($cert['ISSUED_DATE'])) : 'Pending' ?>
                        </p>
                        <?php if ($cert['GRADE']): ?>
                        <div class="mb-3">
                            <span class="badge bg-primary fs-5 px-4 py-2">Grade: <?= htmlspecialchars($cert['GRADE']) ?></span>
                        </div>
                        <?php endif; ?>
                        <span class="badge rounded-pill bg-<?= $cert['STATUS']==='Issued'?'success':'warning text-dark' ?> px-4 py-2 mb-3">
                            <i class="fas fa-<?= $cert['STATUS']==='Issued'?'check-circle':'clock' ?> me-1"></i>
                            <?= htmlspecialchars($cert['STATUS']) ?>
                        </span>
                        <?php if ($cert['CERTIFICATE_URL']): ?>
                        <div>
                            <a href="<?= htmlspecialchars($cert['CERTIFICATE_URL']) ?>" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-download me-1"></i>Download
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /main-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showSection(name, el) {
    document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
    document.getElementById(name).classList.add('active');
    el.classList.add('active');
}
</script>
</body>
</html>
<?php oci_close($conn); ?>