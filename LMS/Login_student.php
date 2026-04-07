<?php
session_start();

// Handle logout FIRST before anything else
if (isset($_GET['logout'])) {
    session_destroy();
    session_start();
}

require 'database/database.php';

// If already logged in redirect to dashboard
if (isset($_SESSION['student_id'])) {
    header("Location: student.php");
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = trim($_POST['student_id'] ?? '');
    $password   = trim($_POST['password']   ?? '');

    if (!$student_id || !$password) {
        $error = "Please fill all fields.";
    } else {
        $sql = oci_parse($conn,
            "SELECT STUDENT_ID, F_NAME, L_NAME, PASSWORD, STATUS, LOGIN_STATUS
             FROM STUDENTS WHERE STUDENT_ID = :sid");
        oci_bind_by_name($sql, ':sid', $student_id);
        oci_execute($sql);
        $student = oci_fetch_assoc($sql);

        if (!$student) {
            $error = "Student ID not found. Please check your ID.";
        } elseif ($student['LOGIN_STATUS'] === 'PENDING') {
            $error = "Your account is pending approval. Please collect your Student ID and Password from the admin.";
        } elseif ($student['STATUS'] === 'PENDING') {
            $error = "Your account is not yet activated. Please contact admin.";
        } elseif (!password_verify($password, $student['PASSWORD'])) {
            $error = "Incorrect password. Please try again.";
        } else {
            $_SESSION['student_id']   = $student['STUDENT_ID'];
            $_SESSION['student_name'] = $student['F_NAME'] . ' ' . $student['L_NAME'];
            header("Location: student.php");
            exit();
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
<title>Login — Transcendant LMS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'DM Sans', sans-serif;
    min-height: 100vh;
    display: grid;
    grid-template-columns: 1fr 1fr;
    background: #f7f3ee;
}
.left-panel {
    background: #1c2b3a;
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 48px;
    overflow: hidden;
}
.left-panel::before {
    content: '';
    position: absolute;
    width: 500px; height: 500px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(212,163,115,0.15) 0%, transparent 70%);
    top: -100px; left: -100px;
}
.left-panel::after {
    content: '';
    position: absolute;
    width: 300px; height: 300px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(212,163,115,0.10) 0%, transparent 70%);
    bottom: 50px; right: -50px;
}
.brand { display: flex; align-items: center; gap: 12px; z-index: 2; }
.brand-icon {
    width: 44px; height: 44px;
    background: #d4a373; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem; color: #1c2b3a;
}
.brand-name { font-family: 'Playfair Display', serif; font-size: 1.3rem; color: #f7f3ee; font-weight: 700; }
.left-content { z-index: 2; }
.left-content h1 { font-family: 'Playfair Display', serif; font-size: 2.8rem; color: #f7f3ee; line-height: 1.2; margin-bottom: 20px; }
.left-content h1 span { color: #d4a373; }
.left-content p { color: rgba(247,243,238,0.55); font-size: 1rem; line-height: 1.7; max-width: 320px; }
.stats-row { display: flex; gap: 24px; z-index: 2; }
.stat-item { border-top: 1px solid rgba(247,243,238,0.15); padding-top: 16px; }
.stat-num { font-family: 'Playfair Display', serif; font-size: 1.8rem; color: #d4a373; font-weight: 700; }
.stat-lbl { font-size: 0.78rem; color: rgba(247,243,238,0.45); text-transform: uppercase; letter-spacing: 1px; }
.right-panel {
    display: flex; align-items: center; justify-content: center;
    padding: 48px; background: #f7f3ee;
}
.login-box { width: 100%; max-width: 400px; animation: fadeUp 0.5s ease forwards; }
.login-box h2 { font-family: 'Playfair Display', serif; font-size: 2rem; color: #1c2b3a; margin-bottom: 6px; }
.login-box .subtitle { color: #8a8a8a; font-size: 0.92rem; margin-bottom: 36px; }
.field-group { margin-bottom: 20px; }
.field-group label { display: block; font-size: 0.82rem; font-weight: 600; color: #1c2b3a; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
.field-wrap { position: relative; }
.field-wrap i.icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #b0a898; font-size: 0.9rem; }
.field-wrap input {
    width: 100%; padding: 14px 16px 14px 42px;
    border: 1.5px solid #e0d9d0; border-radius: 12px;
    font-family: 'DM Sans', sans-serif; font-size: 0.95rem;
    color: #1c2b3a; background: #fff; transition: all 0.25s; outline: none;
}
.field-wrap input:focus { border-color: #d4a373; box-shadow: 0 0 0 3px rgba(212,163,115,0.15); }
.field-wrap input::placeholder { color: #c4bdb5; }
.toggle-btn { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #b0a898; cursor: pointer; font-size: 0.9rem; padding: 4px; }
.toggle-btn:hover { color: #1c2b3a; }
.error-box { background: #fff0f0; border: 1px solid #ffcdd2; border-radius: 10px; padding: 12px 16px; font-size: 0.87rem; color: #c0392b; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 10px; }
.info-box { background: #f0f7ff; border: 1px solid #c0d8f0; border-radius: 10px; padding: 12px 16px; font-size: 0.84rem; color: #1e4d7b; margin-bottom: 24px; display: flex; align-items: flex-start; gap: 10px; }
.btn-login {
    width: 100%; padding: 14px;
    background: #1c2b3a; color: #f7f3ee;
    border: none; border-radius: 12px;
    font-family: 'DM Sans', sans-serif; font-size: 1rem; font-weight: 600;
    cursor: pointer; transition: all 0.25s;
    display: flex; align-items: center; justify-content: center; gap: 10px;
    margin-bottom: 20px;
}
.btn-login:hover { background: #d4a373; color: #1c2b3a; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(28,43,58,0.2); }
.divider { text-align: center; position: relative; margin: 20px 0; color: #c4bdb5; font-size: 0.82rem; }
.divider::before, .divider::after { content: ''; position: absolute; top: 50%; width: 42%; height: 1px; background: #e0d9d0; }
.divider::before { left: 0; } .divider::after { right: 0; }
.btn-register {
    width: 100%; padding: 13px;
    background: transparent; color: #1c2b3a;
    border: 1.5px solid #1c2b3a; border-radius: 12px;
    font-family: 'DM Sans', sans-serif; font-size: 0.95rem; font-weight: 600;
    cursor: pointer; transition: all 0.25s; text-decoration: none;
    display: flex; align-items: center; justify-content: center; gap: 10px;
}
.btn-register:hover { background: #1c2b3a; color: #f7f3ee; }
@media (max-width: 768px) {
    body { grid-template-columns: 1fr; }
    .left-panel { display: none; }
    .right-panel { padding: 32px 24px; }
}
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>
</head>
<body>

<!-- LEFT PANEL -->
<div class="left-panel">
    <div class="brand">
        <div class="brand-icon"><i class="fas fa-graduation-cap"></i></div>
        <div class="brand-name">Transcendant</div>
    </div>
    <div class="left-content">
        <h1>Learn without <span>limits.</span></h1>
        <p>Access your courses, track your progress, and manage your learning journey all in one place.</p>
    </div>
    <div class="stats-row">
        <div class="stat-item">
            <div class="stat-num">6+</div>
            <div class="stat-lbl">Courses</div>
        </div>
        <div class="stat-item">
            <div class="stat-num">4</div>
            <div class="stat-lbl">Instructors</div>
        </div>
        <div class="stat-item">
            <div class="stat-num">100%</div>
            <div class="stat-lbl">Online</div>
        </div>
    </div>
</div>

<!-- RIGHT PANEL -->
<div class="right-panel">
    <div class="login-box">
        <h2>Sign in</h2>
        <p class="subtitle">Use your Student ID and Password given by admin.</p>

        <?php if ($error): ?>
        <div class="error-box">
            <i class="fas fa-exclamation-circle mt-1"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
        <?php endif; ?>

        <div class="info-box">
            <i class="fas fa-info-circle mt-1"></i>
            <span>First time? Register first. After admin approval, collect your <strong>Student ID & Password</strong> from the admin office.</span>
        </div>

        <form method="POST" action="Login_student.php">

            <div class="field-group">
                <label>Student ID</label>
                <div class="field-wrap">
                    <i class="fas fa-id-card icon"></i>
                    <input type="number"
                           name="student_id"
                           placeholder="Enter your Student ID"
                           value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>"
                           required>
                </div>
            </div>

            <div class="field-group">
                <label>Password</label>
                <div class="field-wrap">
                    <i class="fas fa-lock icon"></i>
                    <input type="password"
                           name="password"
                           id="pwdInput"
                           placeholder="Enter your password"
                           required>
                    <button type="button" class="toggle-btn" onclick="togglePwd()">
                        <i class="fas fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-arrow-right"></i> Sign In
            </button>

        </form>

        <div class="divider">New to Transcendant?</div>

        <a href="Registration.php" class="btn-register">
            <i class="fas fa-user-plus"></i> Register Now
        </a>

        <p style="text-align:center;color:#c4bdb5;font-size:0.78rem;margin-top:28px;">
            &copy; 2026 Transcendant LMS. All rights reserved.
        </p>
    </div>
</div>

<script>
function togglePwd() {
    const input = document.getElementById('pwdInput');
    const icon  = document.getElementById('eyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}
</script>
</body>
</html>