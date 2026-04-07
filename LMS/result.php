<?php
session_start();
require 'database/database.php';

// Use session student_id — redirect to login if not logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: homepage.php");
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
    header("Location: Login.php");
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

// ── Get certificates ──────────────────────────────────────────
$sql_cert = oci_parse($conn,
    "SELECT cert.CERTIFICATE_ID, cert.ISSUED_DATE, cert.STATUS,
            cert.GRADE, cert.CERTIFICATE_URL, c.COURSE_NAME
     FROM CERTIFICATE cert
     JOIN COURSE c ON cert.COURSE_ID = c.COURSE_ID
     WHERE cert.STUDENT_ID = :sid");
oci_bind_by_name($sql_cert, ':sid', $student_id);
oci_execute($sql_cert);
$certificates = [];
while ($row = oci_fetch_assoc($sql_cert)) $certificates[] = $row;
$cert = !empty($certificates) ? $certificates[0] : null;

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

// ── Get payments ──────────────────────────────────────────────
$sql_pay = oci_parse($conn,
    "SELECT SUM(PAID_AMOUNT) AS TOTAL_PAID,
            SUM(TOTAL_AMOUNT) AS TOTAL_FEE,
            SUM(BALANCE) AS TOTAL_BAL
     FROM PAYMENT WHERE STUDENT_ID = :sid");
oci_bind_by_name($sql_pay, ':sid', $student_id);
oci_execute($sql_pay);
$payment_summary = oci_fetch_assoc($sql_pay);

// Grade color helper
function gradeColor($grade) {
    switch($grade) {
        case 'A+': case 'A':  return 'success';
        case 'B+': case 'B':  return 'primary';
        case 'C+': case 'C':  return 'warning';
        default:              return 'secondary';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Results — Transcendant LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background:#f4f6f9; font-family:'Segoe UI',sans-serif; }
        .top-navbar {
            background:linear-gradient(135deg,#1c2b3a,#2d4a6a);
            padding:15px 25px;
        }
        .result-hero {
            background:linear-gradient(135deg,#1c2b3a,#0d1f2d);
            color:white; padding:50px 0 80px; position:relative;
        }
        .result-hero::after {
            content:''; position:absolute; bottom:-1px;
            left:0; right:0; height:50px; background:#f4f6f9;
            clip-path:ellipse(55% 100% at 50% 100%);
        }
        .grade-card { border:none; border-radius:20px; box-shadow:0 10px 30px rgba(0,0,0,0.1); transition:transform 0.3s; overflow:hidden; }
        .grade-card:hover { transform:translateY(-5px); }
        .grade-display { font-size:4rem; font-weight:900; line-height:1; }
        .summary-card { border:none; border-radius:15px; box-shadow:0 4px 15px rgba(0,0,0,0.07); transition:transform 0.3s; }
        .summary-card:hover { transform:translateY(-3px); }
        .result-table thead th { background:linear-gradient(135deg,#1c2b3a,#2d4a6a); color:white; border:none; padding:14px 16px; font-weight:500; }
        .result-table tbody tr:hover { background:#f0f4ff; }
        .result-table tbody td { padding:14px 16px; vertical-align:middle; }
        .section-title { font-weight:700; color:#1c2b3a; border-left:4px solid #d4a373; padding-left:12px; margin-bottom:20px; }
        .cert-badge { width:100px; height:100px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:2.5rem; margin:0 auto 15px; }
        .course-pill { background:#f0f4ff; border-radius:10px; padding:14px 18px; margin-bottom:10px; border-left:4px solid #d4a373; }
        .pay-bar { height:8px; border-radius:10px; background:#e0e0e0; overflow:hidden; }
        .pay-bar-fill { height:100%; border-radius:10px; background:linear-gradient(90deg,#d4a373,#e94560); transition:width 0.5s; }
        @media print {
            .no-print { display:none !important; }
            body { background:white; }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="top-navbar d-flex justify-content-between align-items-center no-print">
    <a href="student_dashboard.php" class="text-white text-decoration-none fw-bold fs-5">
        <i class="fas fa-graduation-cap me-2"></i>Transcendant LMS
    </a>
    <div class="d-flex gap-2">
        <a href="student_dashboard.php" class="btn btn-outline-light btn-sm rounded-pill">
            <i class="fas fa-arrow-left me-1"></i>Dashboard
        </a>
        <button onclick="window.print()" class="btn btn-light btn-sm rounded-pill no-print">
            <i class="fas fa-print me-1"></i>Print
        </button>
    </div>
</nav>

<!-- HERO -->
<div class="result-hero text-center">
    <div class="container">
        <h2 class="fw-bold mb-1">
            <i class="fas fa-chart-bar me-2"></i>Academic Results
        </h2>
        <p class="opacity-75 mb-0">
            <?= htmlspecialchars($student['F_NAME'] . ' ' . $student['L_NAME']) ?>
            &bull; <?= htmlspecialchars($student['BATCH_NAME'] ?? 'N/A') ?>
            &bull; <?= htmlspecialchars($student['BRANCH'] ?? 'N/A') ?>
        </p>
    </div>
</div>

<div class="container pb-5" style="margin-top:-20px;">

    <!-- SUMMARY CARDS -->
    <div class="row g-4 mb-4">

        <!-- Grade -->
        <div class="col-md-3">
            <div class="grade-card card h-100">
                <div class="card-body text-center p-4"
                     style="background:linear-gradient(135deg,#1c2b3a,#2d4a6a);">
                    <p class="text-white opacity-75 mb-2 small fw-bold text-uppercase">Final Grade</p>
                    <div class="grade-display text-white mb-2">
                        <?= htmlspecialchars($cert['GRADE'] ?? 'N/A') ?>
                    </div>
                    <span class="badge bg-white text-primary px-3 py-1 small">
                        <?= htmlspecialchars($cert['COURSE_NAME'] ?? 'Pending') ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Certificate Status -->
        <div class="col-md-3">
            <div class="summary-card card h-100">
                <div class="card-body text-center p-4">
                    <div class="cert-badge <?= ($cert['STATUS'] ?? '') == 'Issued' ? 'bg-success bg-opacity-10' : 'bg-warning bg-opacity-10' ?>">
                        <i class="fas fa-certificate <?= ($cert['STATUS'] ?? '') == 'Issued' ? 'text-success' : 'text-warning' ?>"></i>
                    </div>
                    <h6 class="fw-bold">Certificate</h6>
                    <span class="badge rounded-pill px-3 py-2 bg-<?= ($cert['STATUS'] ?? '') == 'Issued' ? 'success' : 'warning text-dark' ?>">
                        <i class="fas fa-<?= ($cert['STATUS'] ?? '') == 'Issued' ? 'check-circle' : 'clock' ?> me-1"></i>
                        <?= htmlspecialchars($cert['STATUS'] ?? 'PENDING') ?>
                    </span>
                    <?php if (!empty($cert['ISSUED_DATE'])): ?>
                    <p class="text-muted small mt-2 mb-0">
                        Issued: <?= date('d M Y', strtotime($cert['ISSUED_DATE'])) ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Courses Enrolled -->
        <div class="col-md-3">
            <div class="summary-card card h-100">
                <div class="card-body text-center p-4">
                    <div class="cert-badge bg-primary bg-opacity-10">
                        <i class="fas fa-book text-primary"></i>
                    </div>
                    <h6 class="fw-bold">Courses Enrolled</h6>
                    <div style="font-size:2rem;font-weight:800;color:#1c2b3a;"><?= count($enrollments) ?></div>
                    <small class="text-muted">Active courses</small>
                </div>
            </div>
        </div>

        <!-- Payment -->
        <div class="col-md-3">
            <div class="summary-card card h-100">
                <div class="card-body text-center p-4">
                    <div class="cert-badge bg-info bg-opacity-10">
                        <i class="fas fa-money-bill text-info"></i>
                    </div>
                    <h6 class="fw-bold">Payment</h6>
                    <p class="text-success fw-bold mb-1" style="font-size:0.9rem;">
                        Paid: Rs. <?= number_format($payment_summary['TOTAL_PAID'] ?? 0, 0) ?>
                    </p>
                    <?php if (($payment_summary['TOTAL_BAL'] ?? 0) > 0): ?>
                    <small class="text-danger">
                        Balance: Rs. <?= number_format($payment_summary['TOTAL_BAL'], 0) ?>
                    </small>
                    <?php else: ?>
                    <small class="text-success"><i class="fas fa-check me-1"></i>Fully Paid</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ENROLLED COURSES -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <h5 class="section-title">
                <i class="fas fa-list-alt me-2"></i>Enrolled Courses
            </h5>
            <?php if (empty($enrollments)): ?>
            <div class="text-center text-muted py-4">
                <i class="fas fa-inbox fa-2x mb-2 d-block opacity-25"></i>
                No enrollment records found
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table result-table rounded-3 overflow-hidden">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Course</th>
                            <th>Duration</th>
                            <th>Fee</th>
                            <th>Joined Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($enrollments as $i => $enroll): ?>
                    <tr>
                        <td><span class="badge bg-secondary"><?= $i + 1 ?></span></td>
                        <td>
                            <i class="fas fa-book text-primary me-2"></i>
                            <strong><?= htmlspecialchars($enroll['COURSE_NAME']) ?></strong>
                        </td>
                        <td><?= htmlspecialchars($enroll['DURATION'] ?? 'N/A') ?></td>
                        <td>Rs. <?= number_format($enroll['FEE'] ?? 0, 0) ?></td>
                        <td><?= $enroll['JOINED_DATE'] ? date('d M Y', strtotime($enroll['JOINED_DATE'])) : '—' ?></td>
                        <td>
                            <span class="badge rounded-pill bg-<?= $enroll['STATUS'] == 'ACTIVE' ? 'success' : ($enroll['STATUS'] == 'COMPLETED' ? 'primary' : 'warning text-dark') ?>">
                                <?= htmlspecialchars($enroll['STATUS']) ?>
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

    <!-- ASSIGNMENTS -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <h5 class="section-title">
                <i class="fas fa-tasks me-2"></i>Assignments
            </h5>
            <?php if (empty($assignments)): ?>
            <div class="text-center text-muted py-4">
                <i class="fas fa-inbox fa-3x mb-3 d-block opacity-25"></i>
                No assignments found
            </div>
            <?php else: ?>
            <div class="row g-3">
                <?php foreach ($assignments as $assign): ?>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-3 h-100">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <?php if ($assign['MODULE_NAME']): ?>
                                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill small">
                                    <i class="fas fa-cube me-1"></i><?= htmlspecialchars($assign['MODULE_NAME']) ?>
                                </span>
                                <?php else: ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-2 rounded-pill small">
                                    <i class="fas fa-book me-1"></i><?= htmlspecialchars($assign['COURSE_NAME']) ?>
                                </span>
                                <?php endif; ?>
                                <span class="badge bg-success px-3 py-2 rounded-pill">
                                    <?= $assign['MAX_MARKS'] ?> marks
                                </span>
                            </div>
                            <h6 class="fw-bold mb-2"><?= htmlspecialchars($assign['TITLE']) ?></h6>
                            <p class="text-muted small mb-3"><?= htmlspecialchars($assign['DESCRIPTION'] ?? '') ?></p>
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

    <!-- CERTIFICATES -->
    <?php if (!empty($certificates)): ?>
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <h5 class="section-title">
                <i class="fas fa-certificate me-2"></i>Certificate Details
            </h5>
            <?php foreach ($certificates as $cert): ?>
            <div class="row align-items-center mb-3 pb-3 border-bottom">
                <div class="col-md-2 text-center">
                    <i class="fas fa-certificate fa-4x text-warning"></i>
                </div>
                <div class="col-md-7">
                    <h5 class="fw-bold"><?= htmlspecialchars($cert['COURSE_NAME']) ?></h5>
                    <p class="text-muted mb-2">
                        <i class="fas fa-user me-2"></i>
                        <?= htmlspecialchars($student['F_NAME'] . ' ' . $student['L_NAME']) ?>
                    </p>
                    <p class="text-muted mb-0">
                        <i class="fas fa-calendar me-2"></i>
                        Issued: <?= $cert['ISSUED_DATE'] ? date('d M Y', strtotime($cert['ISSUED_DATE'])) : 'Pending' ?>
                    </p>
                </div>
                <div class="col-md-3 text-center">
                    <?php if (!empty($cert['GRADE'])): ?>
                    <div class="mb-2">
                        <span class="badge bg-<?= gradeColor($cert['GRADE']) ?> px-4 py-3 fs-4 rounded-3">
                            Grade: <?= htmlspecialchars($cert['GRADE']) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <span class="badge rounded-pill bg-<?= $cert['STATUS'] == 'Issued' ? 'success' : 'warning text-dark' ?> px-3 py-2">
                        <i class="fas fa-<?= $cert['STATUS'] == 'Issued' ? 'check-circle' : 'clock' ?> me-1"></i>
                        <?= htmlspecialchars($cert['STATUS']) ?>
                    </span>
                    <?php if (!empty($cert['CERTIFICATE_URL'])): ?>
                    <div class="mt-3">
                        <a href="<?= htmlspecialchars($cert['CERTIFICATE_URL']) ?>"
                           class="btn btn-outline-primary btn-sm rounded-pill">
                            <i class="fas fa-download me-1"></i>Download
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php oci_close($conn); ?>