<?php
session_start();
require 'database/database.php';

// ── INSTRUCTOR AUTH ───────────────────────────────────────────
$login_error = '';
if (!isset($_SESSION['instructor_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['instructor_login'])) {
        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');

        $sql  = oci_parse($conn, "SELECT INSTRUCTOR_ID, F_NAME, L_NAME, EMAIL, PASSWORD, STATUS FROM INSTRUCTORS WHERE EMAIL = :email");
        oci_bind_by_name($sql, ':email', $email);
        oci_execute($sql);
        $inst = oci_fetch_assoc($sql);

        if (!$inst) {
            $login_error = "No account found with this email.";
        } elseif ($inst['STATUS'] !== 'Active') {
            $login_error = "Your account is inactive. Contact admin.";
        } elseif (!password_verify($password, $inst['PASSWORD'] ?? '')) {
            $login_error = "Incorrect password.";
        } else {
            $_SESSION['instructor_id']   = $inst['INSTRUCTOR_ID'];
            $_SESSION['instructor_name'] = $inst['F_NAME'] . ' ' . $inst['L_NAME'];
            $_SESSION['instructor_email']= $inst['EMAIL'];
            header("Location: instructor.php");
            exit();
        }
    }
    showInstructorLogin($login_error);
    exit();
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: instructor.php");
    exit();
}

$instructor_id    = $_SESSION['instructor_id'];
$instructor_name  = $_SESSION['instructor_name'];
$section          = $_GET['section'] ?? 'dashboard';
$message          = '';
$msg_type         = '';

// ── POST ACTIONS ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['add_assignment'])) {
        $seq = oci_parse($conn, "SELECT SEQ_ASSIGNMENT.NEXTVAL AS NID FROM DUAL");
        oci_execute($seq);
        $seq_row = oci_fetch_assoc($seq);
        $new_id  = $seq_row['NID'];

        $sql = oci_parse($conn,
            "INSERT INTO ASSIGNMENT (ASSIGNMENT_ID, TITLE, DESCRIPTION, DUE_DATE, MAX_MARKS, COURSE_ID, MODULE_ID, INSTRUCTOR_ID)
             VALUES (:id, :ti, :de, TO_DATE(:du,'YYYY-MM-DD'), :ma, :ci, :mi, :ii)");
        oci_bind_by_name($sql, ':id', $new_id);
        oci_bind_by_name($sql, ':ti', $_POST['title']);
        oci_bind_by_name($sql, ':de', $_POST['description']);
        oci_bind_by_name($sql, ':du', $_POST['due_date']);
        oci_bind_by_name($sql, ':ma', $_POST['max_marks']);
        oci_bind_by_name($sql, ':ci', $_POST['course_id']);
        oci_bind_by_name($sql, ':mi', $_POST['module_id']);
        oci_bind_by_name($sql, ':ii', $instructor_id);
        if (oci_execute($sql, OCI_COMMIT_ON_SUCCESS)) {
            $message = "Assignment created successfully!"; $msg_type = "success";
        } else {
            $e = oci_error($sql); $message = $e['message']; $msg_type = "danger";
        }
        $section = 'assignments';
    }

    if (isset($_POST['delete_assignment'])) {
        $aid = (int)$_POST['assignment_id'];
        $sql = oci_parse($conn, "DELETE FROM ASSIGNMENT WHERE ASSIGNMENT_ID=:id AND INSTRUCTOR_ID=:iid");
        oci_bind_by_name($sql, ':id',  $aid);
        oci_bind_by_name($sql, ':iid', $instructor_id);
        if (oci_execute($sql, OCI_COMMIT_ON_SUCCESS)) {
            $message = "Assignment deleted!"; $msg_type = "success";
        } else {
            $e = oci_error($sql); $message = $e['message']; $msg_type = "danger";
        }
        $section = 'assignments';
    }
}

// ── FETCH DATA ────────────────────────────────────────────────

// Instructor info
$isql = oci_parse($conn, "SELECT * FROM INSTRUCTORS WHERE INSTRUCTOR_ID = :id");
oci_bind_by_name($isql, ':id', $instructor_id);
oci_execute($isql);
$instructor = oci_fetch_assoc($isql);

// My courses — show ALL enrollments not just active
$my_courses = [];
$mcsql = oci_parse($conn,
    "SELECT c.COURSE_ID, c.COURSE_NAME, c.DURATION, c.FEE, c.STATUS,
            COUNT(DISTINCT e.STUDENT_ID) AS STUDENT_COUNT,
            COUNT(DISTINCT m.MODULE_ID)  AS MODULE_COUNT
     FROM COURSE c
     LEFT JOIN ENTROLLMENT e ON c.COURSE_ID = e.COURSE_ID
     LEFT JOIN MODULES m     ON c.COURSE_ID = m.COURSE_ID
     WHERE c.INSTRUCTOR_ID = :id
     GROUP BY c.COURSE_ID, c.COURSE_NAME, c.DURATION, c.FEE, c.STATUS
     ORDER BY c.COURSE_ID");
oci_bind_by_name($mcsql, ':id', $instructor_id);
oci_execute($mcsql);
while ($row = oci_fetch_assoc($mcsql)) $my_courses[] = $row;

// My assignments
$my_assignments = [];
$asql = oci_parse($conn,
    "SELECT a.*, c.COURSE_NAME, m.MODULE_NAME
     FROM ASSIGNMENT a
     JOIN COURSE c  ON a.COURSE_ID = c.COURSE_ID
     LEFT JOIN MODULES m ON a.MODULE_ID = m.MODULE_ID
     WHERE a.INSTRUCTOR_ID = :id
     ORDER BY a.DUE_DATE ASC");
oci_bind_by_name($asql, ':id', $instructor_id);
oci_execute($asql);
while ($row = oci_fetch_assoc($asql)) $my_assignments[] = $row;

// My students — show ALL statuses
$my_students = [];
$stsql = oci_parse($conn,
    "SELECT DISTINCT s.STUDENT_ID, s.F_NAME, s.L_NAME, s.EMAIL, s.PHONE,
            s.BRANCH, s.PAYMENT_STATUS, c.COURSE_NAME, e.JOINED_DATE,
            e.STATUS AS ENROLL_STATUS, s.STATUS AS STUDENT_STATUS
     FROM STUDENTS s
     JOIN ENTROLLMENT e ON s.STUDENT_ID = e.STUDENT_ID
     JOIN COURSE c      ON e.COURSE_ID  = c.COURSE_ID
     WHERE c.INSTRUCTOR_ID = :id
     AND e.STATUS IN ('ACTIVE','PENDING','COMPLETED')
     ORDER BY s.STUDENT_ID DESC");
oci_bind_by_name($stsql, ':id', $instructor_id);
oci_execute($stsql);
while ($row = oci_fetch_assoc($stsql)) $my_students[] = $row;

// Modules
$my_modules = [];
$modsql = oci_parse($conn,
    "SELECT m.*, c.COURSE_NAME
     FROM MODULES m
     JOIN COURSE c ON m.COURSE_ID = c.COURSE_ID
     WHERE c.INSTRUCTOR_ID = :id
     ORDER BY m.COURSE_ID, m.MODULE_ORDER");
oci_bind_by_name($modsql, ':id', $instructor_id);
oci_execute($modsql);
while ($row = oci_fetch_assoc($modsql)) $my_modules[] = $row;

$total_courses     = count($my_courses);
$total_students    = count($my_students);
$total_assignments = count($my_assignments);
$total_modules     = count($my_modules);
$course_list       = $my_courses;
$module_list       = $my_modules;

// ── LOGIN PAGE FUNCTION ───────────────────────────────────────
function showInstructorLogin($error = '') {
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Instructor Login — Transcendant LMS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
body { background:#121212; font-family:'Segoe UI',sans-serif; min-height:100vh; display:flex; align-items:center; justify-content:center; }
.login-card { background:#1e1e1e; border:1px solid #333; border-radius:16px; padding:40px; width:100%; max-width:420px; }
.form-control { background:#2a2a2a; border:1px solid #444; color:#fff; border-radius:8px; }
.form-control:focus { background:#333; border-color:#f5a623; color:#fff; box-shadow:0 0 0 3px rgba(245,166,35,0.15); }
.form-control::placeholder { color:#666; }
.form-label { color:#aaa; font-size:0.85rem; font-weight:600; }
.btn-login { background:linear-gradient(135deg,#f5a623,#e94560); border:none; border-radius:10px; padding:13px; font-weight:700; color:#fff; width:100%; transition:all 0.3s; }
.btn-login:hover { opacity:0.9; transform:translateY(-2px); color:#fff; }
.toggle-btn { background:#2a2a2a; border:1px solid #444; border-left:none; color:#888; cursor:pointer; }
.toggle-btn:hover { color:#f5a623; }
</style>
</head>
<body>
<div class="login-card text-white">
    <div class="text-center mb-4">
        <div style="width:60px;height:60px;background:linear-gradient(135deg,#f5a623,#e94560);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;margin:0 auto 16px;">
            <i class="fas fa-chalkboard-teacher"></i>
        </div>
        <h4 class="fw-bold">Instructor Login</h4>
        <p style="color:#666;font-size:0.9rem;">Transcendant LMS — Instructor Portal</p>
    </div>

    <?php if ($error): ?>
    <div class="alert rounded-3 mb-3" style="background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);color:#fca5a5;font-size:0.87rem;">
        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="instructor_login" value="1">
        <div class="mb-3">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" placeholder="your@email.com" required>
        </div>
        <div class="mb-4">
            <label class="form-label">Password</label>
            <div class="input-group">
                <input type="password" name="password" id="pwd" class="form-control" placeholder="Enter password" required>
                <button type="button" class="btn toggle-btn px-3" onclick="t=document.getElementById('pwd');t.type=t.type==='password'?'text':'password'">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>
        <button type="submit" class="btn-login">
            <i class="fas fa-sign-in-alt me-2"></i>Login to Dashboard
        </button>
    </form>
    <p class="text-center mt-3" style="color:#555;font-size:0.82rem;">&copy; 2026 Transcendant LMS</p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Instructor Dashboard — Transcendant LMS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root { --sidebar-w:255px; --brand:#f5a623; --brand2:#e94560; --dark:#0d1117; --card:#1e1e1e; --border:#333; }
* { box-sizing:border-box; }
body { font-family:'Segoe UI',sans-serif; background:#121212; color:#fff; margin:0; }
.sidebar { width:var(--sidebar-w); background:var(--dark); min-height:100vh; position:fixed; top:0; left:0; z-index:200; display:flex; flex-direction:column; border-right:1px solid var(--border); }
.sidebar-brand { padding:22px 20px; border-bottom:1px solid var(--border); }
.brand-logo { font-size:1.1rem; font-weight:800; background:linear-gradient(135deg,var(--brand),var(--brand2)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
.brand-sub { font-size:0.7rem; color:#555; margin-top:2px; }
.instructor-info { padding:16px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:12px; }
.inst-avatar { width:42px; height:42px; border-radius:50%; background:linear-gradient(135deg,var(--brand),var(--brand2)); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:1rem; flex-shrink:0; }
.inst-name { font-weight:700; font-size:0.88rem; color:#fff; }
.inst-role { font-size:0.72rem; color:#555; }
.nav-section { padding:8px 12px 4px; font-size:0.63rem; font-weight:700; text-transform:uppercase; letter-spacing:2px; color:#444; }
.nav-link { display:flex; align-items:center; gap:10px; color:rgba(255,255,255,0.5); padding:10px 16px; border-radius:10px; margin:2px 8px; font-size:0.875rem; font-weight:500; text-decoration:none; transition:all 0.2s; }
.nav-link:hover { background:rgba(255,255,255,0.06); color:#fff; }
.nav-link.active { background:rgba(245,166,35,0.12); color:#fff; border-left:3px solid var(--brand); }
.nav-link i { width:18px; text-align:center; }
.sidebar-footer { padding:16px; border-top:1px solid var(--border); margin-top:auto; }
.main { margin-left:var(--sidebar-w); min-height:100vh; }
.topbar { background:#1a1a1a; border-bottom:1px solid var(--border); padding:14px 28px; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:100; }
.topbar-title { font-size:1.05rem; font-weight:700; color:#fff; }
.topbar-sub { font-size:0.77rem; color:#555; }
.content { padding:24px 28px; }
.stat-card { border-radius:14px; padding:20px; border:none; transition:transform 0.2s; position:relative; overflow:hidden; }
.stat-card:hover { transform:translateY(-4px); }
.stat-card::after { content:''; position:absolute; right:-15px; top:-15px; width:80px; height:80px; border-radius:50%; background:rgba(255,255,255,0.08); }
.stat-num { font-size:2rem; font-weight:800; color:#fff; }
.stat-lbl { font-size:0.78rem; color:rgba(255,255,255,0.65); margin-top:4px; }
.stat-icon { font-size:1.6rem; opacity:0.25; position:absolute; right:18px; bottom:18px; }
.content-card { background:var(--card); border-radius:14px; padding:22px; border:1px solid var(--border); margin-bottom:20px; }
.card-title { font-size:0.95rem; font-weight:700; color:#fff; display:flex; align-items:center; gap:8px; margin-bottom:18px; padding-bottom:12px; border-bottom:1px solid var(--border); }
.card-title i { color:var(--brand); }
.inst-table { width:100%; border-collapse:collapse; }
.inst-table thead th { background:#2a2a2a; color:#888; font-size:0.73rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; padding:12px 14px; border-bottom:1px solid var(--border); white-space:nowrap; }
.inst-table tbody td { padding:12px 14px; border-bottom:1px solid #1a1a1a; font-size:0.875rem; color:rgba(255,255,255,0.8); vertical-align:middle; }
.inst-table tbody tr:hover td { background:rgba(255,255,255,0.02); }
.inst-table tbody tr:last-child td { border-bottom:none; }
.badge-active   { background:rgba(74,222,128,0.12); color:#4ade80; border:1px solid rgba(74,222,128,0.25); border-radius:20px; padding:3px 10px; font-size:0.72rem; font-weight:700; }
.badge-pending  { background:rgba(251,191,36,0.12); color:#fbbf24; border:1px solid rgba(251,191,36,0.25); border-radius:20px; padding:3px 10px; font-size:0.72rem; font-weight:700; }
.badge-inactive { background:rgba(239,68,68,0.12); color:#f87171; border:1px solid rgba(239,68,68,0.25); border-radius:20px; padding:3px 10px; font-size:0.72rem; font-weight:700; }
.badge-info     { background:rgba(96,165,250,0.12); color:#60a5fa; border:1px solid rgba(96,165,250,0.25); border-radius:20px; padding:3px 10px; font-size:0.72rem; font-weight:700; }
.btn-add { background:linear-gradient(135deg,var(--brand),var(--brand2)); border:none; border-radius:10px; padding:9px 18px; font-size:0.875rem; font-weight:700; color:#fff; cursor:pointer; transition:all 0.2s; display:inline-flex; align-items:center; gap:6px; text-decoration:none; }
.btn-add:hover { opacity:0.9; transform:translateY(-1px); color:#fff; }
.btn-sm-del { background:rgba(239,68,68,0.12); border:1px solid rgba(239,68,68,0.25); color:#f87171; border-radius:7px; padding:5px 10px; font-size:0.78rem; cursor:pointer; transition:all 0.2s; }
.btn-sm-del:hover { background:rgba(239,68,68,0.22); }
.modal-content { background:#1e1e1e; border:1px solid var(--border); border-radius:16px; color:#fff; }
.modal-header { border-bottom:1px solid var(--border); }
.modal-footer { border-top:1px solid var(--border); }
.form-label { color:#aaa; font-size:0.82rem; font-weight:600; }
.form-control, .form-select { background:#2a2a2a; border:1px solid #444; color:#fff; border-radius:8px; }
.form-control:focus, .form-select:focus { background:#333; border-color:var(--brand); color:#fff; box-shadow:0 0 0 3px rgba(245,166,35,0.15); }
.form-control::placeholder { color:#555; }
.form-select option { background:#1e1e1e; }
.course-item { background:#2a2a2a; border:1px solid #333; border-radius:12px; padding:18px; transition:all 0.2s; margin-bottom:12px; }
.course-item:hover { border-color:var(--brand); }
.course-item-name { font-weight:700; font-size:1rem; color:#fff; }
.course-item-meta { font-size:0.8rem; color:#666; margin-top:4px; }
.profile-field { background:#2a2a2a; border-radius:10px; padding:14px 16px; margin-bottom:12px; }
.profile-label { font-size:0.75rem; color:#666; text-transform:uppercase; letter-spacing:1px; font-weight:700; margin-bottom:4px; }
.profile-value { font-size:0.95rem; color:#fff; font-weight:500; }
.due-soon { color:#fbbf24; }
.due-overdue { color:#f87171; }
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo">🎓 Transcendant</div>
        <div class="brand-sub">Instructor Portal</div>
    </div>
    <div class="instructor-info">
        <div class="inst-avatar"><?= strtoupper(substr($instructor['F_NAME'] ?? 'I', 0, 1)) ?></div>
        <div>
            <div class="inst-name"><?= htmlspecialchars(($instructor['F_NAME'] ?? '').' '.($instructor['L_NAME'] ?? '')) ?></div>
            <div class="inst-role"><?= htmlspecialchars($instructor['SPECIALIZATION'] ?? 'Instructor') ?></div>
        </div>
    </div>
    <nav style="flex:1;padding:8px 0;overflow-y:auto;">
        <div class="nav-section">Main</div>
        <a class="nav-link <?= $section=='dashboard'?'active':'' ?>" href="?section=dashboard"><i class="fas fa-th-large"></i> Dashboard</a>
        <div class="nav-section">Academic</div>
        <a class="nav-link <?= $section=='courses'?'active':'' ?>" href="?section=courses"><i class="fas fa-book"></i> My Courses</a>
        <a class="nav-link <?= $section=='modules'?'active':'' ?>" href="?section=modules"><i class="fas fa-cubes"></i> Modules</a>
        <a class="nav-link <?= $section=='assignments'?'active':'' ?>" href="?section=assignments"><i class="fas fa-tasks"></i> Assignments</a>
        <div class="nav-section">Students</div>
        <a class="nav-link <?= $section=='students'?'active':'' ?>" href="?section=students"><i class="fas fa-users"></i> My Students</a>
        <div class="nav-section">Account</div>
        <a class="nav-link <?= $section=='profile'?'active':'' ?>" href="?section=profile"><i class="fas fa-user"></i> My Profile</a>
    </nav>
    <div class="sidebar-footer">
        <a href="homepage.php" class="nav-link" style="margin:0;padding:8px 4px;"><i class="fas fa-home"></i> Homepage</a>
        <a href="?logout" class="nav-link" style="margin:0;padding:8px 4px;color:#f87171;"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>

<!-- MAIN -->
<div class="main">
    <div class="topbar">
        <div>
            <div class="topbar-title">
                <?php
                $titles = ['dashboard'=>'Dashboard','courses'=>'My Courses','modules'=>'Modules',
                           'assignments'=>'Assignments','students'=>'My Students','profile'=>'My Profile'];
                echo $titles[$section] ?? 'Dashboard';
                ?>
            </div>
            <div class="topbar-sub">Transcendant LMS · <?= date('D, d M Y') ?></div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--brand),var(--brand2));display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.9rem;">
                <?= strtoupper(substr($instructor['F_NAME'] ?? 'I', 0, 1)) ?>
            </div>
        </div>
    </div>

    <div class="content">

    <?php if ($message): ?>
    <div class="alert alert-dismissible fade show rounded-3 mb-4" style="background:<?= $msg_type=='success'?'rgba(74,222,128,0.12)':'rgba(239,68,68,0.12)' ?>;border:1px solid <?= $msg_type=='success'?'rgba(74,222,128,0.25)':'rgba(239,68,68,0.25)' ?>;color:<?= $msg_type=='success'?'#4ade80':'#f87171' ?>;">
        <i class="fas fa-<?= $msg_type=='success'?'check-circle':'exclamation-circle' ?> me-2"></i>
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- DASHBOARD -->
    <?php if ($section == 'dashboard'): ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card" style="background:linear-gradient(135deg,#1a3a5c,#1e4d7b);">
                <div class="stat-num"><?= $total_courses ?></div>
                <div class="stat-lbl">My Courses</div>
                <i class="fas fa-book stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card" style="background:linear-gradient(135deg,#1a3a1a,#1e5c1e);">
                <div class="stat-num"><?= $total_students ?></div>
                <div class="stat-lbl">My Students</div>
                <i class="fas fa-users stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card" style="background:linear-gradient(135deg,#3a1a1a,#5c1e1e);">
                <div class="stat-num"><?= $total_assignments ?></div>
                <div class="stat-lbl">Assignments</div>
                <i class="fas fa-tasks stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card" style="background:linear-gradient(135deg,#2a1a3a,#3d1e5c);">
                <div class="stat-num"><?= $total_modules ?></div>
                <div class="stat-lbl">Modules</div>
                <i class="fas fa-cubes stat-icon"></i>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="content-card">
                <div class="card-title"><i class="fas fa-book"></i>My Courses</div>
                <?php if (empty($my_courses)): ?>
                    <p style="color:#555;">No courses assigned yet.</p>
                <?php else: foreach ($my_courses as $c): ?>
                <div class="course-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="course-item-name"><?= htmlspecialchars($c['COURSE_NAME']) ?></div>
                            <div class="course-item-meta">
                                <i class="fas fa-clock me-1"></i><?= htmlspecialchars($c['DURATION'] ?? 'N/A') ?>
                                &nbsp;·&nbsp;<i class="fas fa-users me-1"></i><?= $c['STUDENT_COUNT'] ?> students
                                &nbsp;·&nbsp;<i class="fas fa-cubes me-1"></i><?= $c['MODULE_COUNT'] ?> modules
                            </div>
                        </div>
                        <span class="badge-<?= $c['STATUS']==='Active'?'active':'inactive' ?>"><?= $c['STATUS'] ?></span>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <div class="col-md-6">
            <div class="content-card">
                <div class="card-title"><i class="fas fa-tasks"></i>Upcoming Assignments</div>
                <?php if (empty($my_assignments)): ?>
                    <p style="color:#555;">No assignments created yet.</p>
                <?php else:
                    foreach (array_slice($my_assignments, 0, 5) as $a):
                        $due     = $a['DUE_DATE'] ? strtotime($a['DUE_DATE']) : null;
                        $days    = $due ? ceil(($due - time()) / 86400) : null;
                        $due_cls = $days !== null ? ($days < 0 ? 'due-overdue' : ($days <= 3 ? 'due-soon' : '')) : '';
                ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #1a1a1a;">
                    <div>
                        <div style="font-weight:600;font-size:0.88rem;"><?= htmlspecialchars($a['TITLE']) ?></div>
                        <div style="font-size:0.77rem;color:#555;"><?= htmlspecialchars($a['COURSE_NAME']) ?></div>
                    </div>
                    <div class="text-end">
                        <div class="<?= $due_cls ?>" style="font-size:0.8rem;font-weight:700;"><?= $a['DUE_DATE'] ? date('d M', strtotime($a['DUE_DATE'])) : '—' ?></div>
                        <div style="font-size:0.72rem;color:#555;"><?= $a['MAX_MARKS'] ?> marks</div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <div class="col-12">
            <div class="content-card">
                <div class="card-title"><i class="fas fa-users"></i>Recent Students</div>
                <?php if (empty($my_students)): ?>
                <p style="color:#555;">No students enrolled yet.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="inst-table">
                        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Course</th><th>Joined</th><th>Enroll</th><th>Payment</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($my_students, 0, 8) as $s): ?>
                        <tr>
                            <td style="font-family:monospace;color:#555;">#<?= str_pad($s['STUDENT_ID'],4,'0',STR_PAD_LEFT) ?></td>
                            <td style="font-weight:600;"><?= htmlspecialchars($s['F_NAME'].' '.$s['L_NAME']) ?></td>
                            <td style="font-size:0.82rem;"><?= htmlspecialchars($s['EMAIL']) ?></td>
                            <td><?= htmlspecialchars($s['COURSE_NAME']) ?></td>
                            <td style="font-size:0.82rem;"><?= $s['JOINED_DATE'] ? date('d M Y', strtotime($s['JOINED_DATE'])) : '—' ?></td>
                            <td><span class="badge-<?= $s['ENROLL_STATUS']==='ACTIVE'?'active':'pending' ?>"><?= $s['ENROLL_STATUS'] ?></span></td>
                            <td><span class="badge-<?= $s['PAYMENT_STATUS']==='PAID'?'active':($s['PAYMENT_STATUS']==='Paid'?'active':'pending') ?>"><?= $s['PAYMENT_STATUS'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- MY COURSES -->
    <?php elseif ($section == 'courses'): ?>
    <div class="content-card">
        <div class="card-title"><i class="fas fa-book"></i>My Courses</div>
        <?php if (empty($my_courses)): ?>
            <div class="text-center py-5" style="color:#555;"><i class="fas fa-book-open fa-3x mb-3" style="opacity:0.3;"></i><br>No courses assigned yet.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="inst-table">
                <thead><tr><th>ID</th><th>Course Name</th><th>Duration</th><th>Fee</th><th>Students</th><th>Modules</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($my_courses as $c): ?>
                <tr>
                    <td style="font-family:monospace;color:#555;">#<?= $c['COURSE_ID'] ?></td>
                    <td style="font-weight:700;"><?= htmlspecialchars($c['COURSE_NAME']) ?></td>
                    <td><?= htmlspecialchars($c['DURATION'] ?? 'N/A') ?></td>
                    <td style="font-family:monospace;color:var(--brand);">Rs.<?= number_format($c['FEE'], 0) ?></td>
                    <td><span class="badge-info"><?= $c['STUDENT_COUNT'] ?> students</span></td>
                    <td><span class="badge-info"><?= $c['MODULE_COUNT'] ?> modules</span></td>
                    <td><span class="badge-<?= $c['STATUS']==='Active'?'active':'inactive' ?>"><?= $c['STATUS'] ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- MODULES -->
    <?php elseif ($section == 'modules'): ?>
    <div class="content-card">
        <div class="card-title"><i class="fas fa-cubes"></i>Course Modules</div>
        <?php if (empty($my_modules)): ?>
            <div class="text-center py-5" style="color:#555;"><i class="fas fa-cubes fa-3x mb-3" style="opacity:0.3;"></i><br>No modules found.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="inst-table">
                <thead><tr><th>ID</th><th>Module Name</th><th>Course</th><th>Credits</th><th>Duration</th><th>Order</th></tr></thead>
                <tbody>
                <?php foreach ($my_modules as $m): ?>
                <tr>
                    <td style="font-family:monospace;color:#555;">#<?= $m['MODULE_ID'] ?></td>
                    <td style="font-weight:600;"><?= htmlspecialchars($m['MODULE_NAME']) ?></td>
                    <td><?= htmlspecialchars($m['COURSE_NAME']) ?></td>
                    <td><span class="badge-info"><?= $m['CREDIT'] ?? '—' ?> cr</span></td>
                    <td><?= htmlspecialchars($m['DURATION_TIME'] ?? 'N/A') ?></td>
                    <td><?= $m['MODULE_ORDER'] ?? '—' ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- ASSIGNMENTS -->
    <?php elseif ($section == 'assignments'): ?>
    <div class="content-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="card-title mb-0"><i class="fas fa-tasks"></i>My Assignments</div>
            <button class="btn-add" data-bs-toggle="modal" data-bs-target="#addAssignModal"><i class="fas fa-plus"></i> Create Assignment</button>
        </div>
        <?php if (empty($my_assignments)): ?>
            <div class="text-center py-5" style="color:#555;"><i class="fas fa-tasks fa-3x mb-3" style="opacity:0.3;"></i><br>No assignments yet.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="inst-table">
                <thead><tr><th>ID</th><th>Title</th><th>Course</th><th>Module</th><th>Max Marks</th><th>Due Date</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($my_assignments as $a):
                    $due  = $a['DUE_DATE'] ? strtotime($a['DUE_DATE']) : null;
                    $days = $due ? ceil(($due - time()) / 86400) : null;
                    $due_cls = $days !== null ? ($days < 0 ? 'due-overdue' : ($days <= 3 ? 'due-soon' : '')) : '';
                ?>
                <tr>
                    <td style="font-family:monospace;color:#555;">#<?= $a['ASSIGNMENT_ID'] ?></td>
                    <td style="font-weight:600;"><?= htmlspecialchars($a['TITLE']) ?></td>
                    <td><?= htmlspecialchars($a['COURSE_NAME']) ?></td>
                    <td><?= htmlspecialchars($a['MODULE_NAME'] ?? '—') ?></td>
                    <td><span class="badge-active"><?= $a['MAX_MARKS'] ?></span></td>
                    <td class="<?= $due_cls ?>" style="font-weight:600;">
                        <?= $a['DUE_DATE'] ? date('d M Y', strtotime($a['DUE_DATE'])) : '—' ?>
                        <?php if ($days !== null): ?>
                        <div style="font-size:0.73rem;font-weight:400;color:#555;"><?= $days < 0 ? 'Overdue' : ($days == 0 ? 'Due today' : "in $days days") ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="assignment_id" value="<?= $a['ASSIGNMENT_ID'] ?>">
                            <button type="submit" name="delete_assignment" class="btn-sm-del" onclick="return confirm('Delete this assignment?')"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="modal fade" id="addAssignModal" tabindex="-1">
        <div class="modal-dialog modal-lg"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title text-white">Create Assignment</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <form method="POST"><div class="modal-body"><div class="row g-3">
                <div class="col-12"><label class="form-label">Title *</label><input type="text" name="title" class="form-control" placeholder="e.g. ER Diagram Design" required></div>
                <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3" placeholder="Instructions..."></textarea></div>
                <div class="col-md-6"><label class="form-label">Course *</label><select name="course_id" class="form-select" required><option value="">Select course</option><?php foreach ($course_list as $c): ?><option value="<?= $c['COURSE_ID'] ?>"><?= htmlspecialchars($c['COURSE_NAME']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label class="form-label">Module</label><select name="module_id" class="form-select"><option value="">Select module (optional)</option><?php foreach ($module_list as $m): ?><option value="<?= $m['MODULE_ID'] ?>"><?= htmlspecialchars($m['MODULE_NAME']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label class="form-label">Due Date *</label><input type="date" name="due_date" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Max Marks</label><input type="number" name="max_marks" class="form-control" value="100"></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="add_assignment" class="btn-add"><i class="fas fa-save"></i> Create</button></div>
            </form>
        </div></div>
    </div>

    <!-- MY STUDENTS -->
    <?php elseif ($section == 'students'): ?>
    <div class="content-card">
        <div class="card-title"><i class="fas fa-users"></i>My Students</div>
        <?php if (empty($my_students)): ?>
            <div class="text-center py-5" style="color:#555;"><i class="fas fa-users fa-3x mb-3" style="opacity:0.3;"></i><br>No students enrolled yet.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="inst-table">
                <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Branch</th><th>Course</th><th>Joined</th><th>Enroll</th><th>Payment</th></tr></thead>
                <tbody>
                <?php foreach ($my_students as $s): ?>
                <tr>
                    <td style="font-family:monospace;color:#555;">#<?= str_pad($s['STUDENT_ID'],4,'0',STR_PAD_LEFT) ?></td>
                    <td style="font-weight:600;"><?= htmlspecialchars($s['F_NAME'].' '.$s['L_NAME']) ?></td>
                    <td style="font-size:0.82rem;"><?= htmlspecialchars($s['EMAIL']) ?></td>
                    <td><?= htmlspecialchars($s['PHONE'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($s['BRANCH'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($s['COURSE_NAME']) ?></td>
                    <td style="font-size:0.82rem;"><?= $s['JOINED_DATE'] ? date('d M Y', strtotime($s['JOINED_DATE'])) : '—' ?></td>
                    <td><span class="badge-<?= $s['ENROLL_STATUS']==='ACTIVE'?'active':'pending' ?>"><?= $s['ENROLL_STATUS'] ?></span></td>
                    <td><span class="badge-<?= ($s['PAYMENT_STATUS']==='PAID'||$s['PAYMENT_STATUS']==='Paid')?'active':'pending' ?>"><?= $s['PAYMENT_STATUS'] ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- PROFILE -->
    <?php elseif ($section == 'profile'): ?>
    <div class="row g-4">
        <div class="col-md-4">
            <div class="content-card text-center">
                <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--brand),var(--brand2));display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:800;margin:0 auto 16px;">
                    <?= strtoupper(substr($instructor['F_NAME'] ?? 'I', 0, 1)) ?>
                </div>
                <h5 class="fw-bold"><?= htmlspecialchars(($instructor['F_NAME'] ?? '').' '.($instructor['L_NAME'] ?? '')) ?></h5>
                <p style="color:#555;font-size:0.88rem;"><?= htmlspecialchars($instructor['SPECIALIZATION'] ?? '') ?></p>
                <span class="badge-<?= $instructor['STATUS']==='Active'?'active':'inactive' ?>"><?= $instructor['STATUS'] ?></span>
                <hr style="border-color:#333;margin:16px 0;">
                <div class="row g-2 text-center">
                    <div class="col-4"><div style="font-size:1.4rem;font-weight:800;color:var(--brand);"><?= $total_courses ?></div><div style="font-size:0.72rem;color:#555;">Courses</div></div>
                    <div class="col-4"><div style="font-size:1.4rem;font-weight:800;color:var(--brand);"><?= $total_students ?></div><div style="font-size:0.72rem;color:#555;">Students</div></div>
                    <div class="col-4"><div style="font-size:1.4rem;font-weight:800;color:var(--brand);"><?= $total_assignments ?></div><div style="font-size:0.72rem;color:#555;">Assignments</div></div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="content-card">
                <div class="card-title"><i class="fas fa-user"></i>Profile Information</div>
                <div class="profile-field"><div class="profile-label">Full Name</div><div class="profile-value"><?= htmlspecialchars(($instructor['F_NAME'] ?? '').' '.($instructor['L_NAME'] ?? '')) ?></div></div>
                <div class="profile-field"><div class="profile-label">Email Address</div><div class="profile-value"><?= htmlspecialchars($instructor['EMAIL'] ?? '—') ?></div></div>
                <div class="profile-field"><div class="profile-label">Phone Number</div><div class="profile-value"><?= htmlspecialchars($instructor['PHONE'] ?? '—') ?></div></div>
                <div class="profile-field"><div class="profile-label">Branch</div><div class="profile-value"><?= htmlspecialchars($instructor['BRANCH'] ?? '—') ?></div></div>
                <div class="profile-field"><div class="profile-label">Specialization</div><div class="profile-value"><?= htmlspecialchars($instructor['SPECIALIZATION'] ?? '—') ?></div></div>
                <div class="profile-field"><div class="profile-label">Joined Date</div><div class="profile-value"><?= $instructor['JOINED_DATE'] ? date('d M Y', strtotime($instructor['JOINED_DATE'])) : '—' ?></div></div>
                <div class="profile-field"><div class="profile-label">Status</div><div class="profile-value"><span class="badge-<?= $instructor['STATUS']==='Active'?'active':'inactive' ?>"><?= $instructor['STATUS'] ?></span></div></div>
            </div>
        </div>
    </div>

    <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php oci_close($conn); ?>