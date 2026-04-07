<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require 'database/database.php';

function safe_oci_execute($sql) {
    if (!oci_execute($sql)) {
        $e = oci_error($sql);
        die("OCI Error: " . $e['message']);
    }
    return true;
}

$section  = $_GET['section'] ?? 'dashboard';
$action   = $_GET['action']  ?? '';
$id       = $_GET['id']      ?? '';
$search   = $_GET['search']  ?? '';
$message  = '';
$msg_type = '';
$approved_student = null;

// ============================================================
// POST ACTIONS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // APPROVE STUDENT
    if (isset($_POST['approve_student'])) {
        $sid        = (int)$_POST['student_id'];
        $plain_pass = 'TRN@' . str_pad($sid, 4, '0', STR_PAD_LEFT);
        $hashed     = password_hash($plain_pass, PASSWORD_DEFAULT);

        $sql = oci_parse($conn,
            "UPDATE STUDENTS SET STATUS='ACTIVE', LOGIN_STATUS='ACTIVE',
             PASSWORD=:pwd, GENERATED_PASS=:plain WHERE STUDENT_ID=:sid");
        oci_bind_by_name($sql, ':pwd',   $hashed);
        oci_bind_by_name($sql, ':plain', $plain_pass);
        oci_bind_by_name($sql, ':sid',   $sid);

        if (oci_execute($sql, OCI_COMMIT_ON_SUCCESS)) {
            $esql = oci_parse($conn, "UPDATE ENTROLLMENT SET STATUS='ACTIVE' WHERE STUDENT_ID=:sid");
            oci_bind_by_name($esql, ':sid', $sid);
            oci_execute($esql, OCI_COMMIT_ON_SUCCESS);

            $gsql = oci_parse($conn, "SELECT F_NAME, L_NAME, EMAIL, PHONE FROM STUDENTS WHERE STUDENT_ID=:sid");
            oci_bind_by_name($gsql, ':sid', $sid);
            oci_execute($gsql);
            $approved_student               = oci_fetch_assoc($gsql);
            $approved_student['STUDENT_ID'] = $sid;
            $approved_student['PASSWORD']   = $plain_pass;
            $message  = "Student approved! Credentials generated successfully.";
            $msg_type = "success";
        } else {
            $e = oci_error($sql);
            $message = $e['message']; $msg_type = "danger";
        }
        $section = 'enrollments';
    }

    // REJECT STUDENT
    if (isset($_POST['reject_student'])) {
        $sid = (int)$_POST['student_id'];
        $d1  = oci_parse($conn, "DELETE FROM PAYMENT WHERE STUDENT_ID=:sid");
        oci_bind_by_name($d1, ':sid', $sid); oci_execute($d1, OCI_COMMIT_ON_SUCCESS);
        $d2  = oci_parse($conn, "DELETE FROM ENTROLLMENT WHERE STUDENT_ID=:sid");
        oci_bind_by_name($d2, ':sid', $sid); oci_execute($d2, OCI_COMMIT_ON_SUCCESS);
        $d3  = oci_parse($conn, "DELETE FROM STUDENTS WHERE STUDENT_ID=:sid AND STATUS='PENDING'");
        oci_bind_by_name($d3, ':sid', $sid); oci_execute($d3, OCI_COMMIT_ON_SUCCESS);
        $message = "Student registration rejected and removed."; $msg_type = "warning";
        $section = 'enrollments';
    }

    // ADD INSTRUCTOR
    if (isset($_POST['add_instructor'])) {
        $seq = oci_parse($conn, "SELECT seq_instructor.NEXTVAL as nid FROM dual");
        oci_execute($seq); $seq_row = oci_fetch_assoc($seq); $new_id = $seq_row['NID'];
        $sql = oci_parse($conn,
            "INSERT INTO instructors (instructor_id,f_name,l_name,email,phone,branch,specialization,joined_date,status)
             VALUES (:id,:fn,:ln,:em,:ph,:br,:sp,SYSDATE,:st)");
        oci_bind_by_name($sql, ':id', $new_id);
        oci_bind_by_name($sql, ':fn', $_POST['f_name']);
        oci_bind_by_name($sql, ':ln', $_POST['l_name']);
        oci_bind_by_name($sql, ':em', $_POST['email']);
        oci_bind_by_name($sql, ':ph', $_POST['phone']);
        oci_bind_by_name($sql, ':br', $_POST['branch']);
        oci_bind_by_name($sql, ':sp', $_POST['specialization']);
        oci_bind_by_name($sql, ':st', $_POST['status']);
        if (oci_execute($sql, OCI_COMMIT_ON_SUCCESS)) {
            $message = "Instructor added!"; $msg_type = "success";
        } else { $e = oci_error($sql); $message = $e['message']; $msg_type = "danger"; }
    }

    // UPDATE INSTRUCTOR
    if (isset($_POST['update_instructor'])) {
        $sql = oci_parse($conn,
            "UPDATE instructors SET f_name=:fn,l_name=:ln,email=:em,phone=:ph,
             branch=:br,specialization=:sp,status=:st WHERE instructor_id=:id");
        oci_bind_by_name($sql, ':fn', $_POST['f_name']);
        oci_bind_by_name($sql, ':ln', $_POST['l_name']);
        oci_bind_by_name($sql, ':em', $_POST['email']);
        oci_bind_by_name($sql, ':ph', $_POST['phone']);
        oci_bind_by_name($sql, ':br', $_POST['branch']);
        oci_bind_by_name($sql, ':sp', $_POST['specialization']);
        oci_bind_by_name($sql, ':st', $_POST['status']);
        oci_bind_by_name($sql, ':id', $_POST['instructor_id']);
        if (oci_execute($sql, OCI_COMMIT_ON_SUCCESS)) {
            $message = "Instructor updated!"; $msg_type = "success";
        } else { $e = oci_error($sql); $message = $e['message']; $msg_type = "danger"; }
    }

    // ADD COURSE
    if (isset($_POST['add_course'])) {
        $seq = oci_parse($conn, "SELECT seq_course.NEXTVAL as nid FROM dual");
        oci_execute($seq); $seq_row = oci_fetch_assoc($seq); $new_id = $seq_row['NID'];
        $sql = oci_parse($conn,
            "INSERT INTO course (course_id,course_name,duration,fee,status,instructor_id)
             VALUES (:id,:cn,:du,:fe,:st,:ii)");
        oci_bind_by_name($sql, ':id', $new_id);
        oci_bind_by_name($sql, ':cn', $_POST['course_name']);
        oci_bind_by_name($sql, ':du', $_POST['duration']);
        oci_bind_by_name($sql, ':fe', $_POST['fee']);
        oci_bind_by_name($sql, ':st', $_POST['status']);
        oci_bind_by_name($sql, ':ii', $_POST['instructor_id']);
        if (oci_execute($sql, OCI_COMMIT_ON_SUCCESS)) {
            $message = "Course added!"; $msg_type = "success";
        } else { $e = oci_error($sql); $message = $e['message']; $msg_type = "danger"; }
    }

    // UPDATE COURSE
    if (isset($_POST['update_course'])) {
        $sql = oci_parse($conn,
            "UPDATE course SET course_name=:cn,duration=:du,fee=:fe,
             status=:st,instructor_id=:ii WHERE course_id=:id");
        oci_bind_by_name($sql, ':cn', $_POST['course_name']);
        oci_bind_by_name($sql, ':du', $_POST['duration']);
        oci_bind_by_name($sql, ':fe', $_POST['fee']);
        oci_bind_by_name($sql, ':st', $_POST['status']);
        oci_bind_by_name($sql, ':ii', $_POST['instructor_id']);
        oci_bind_by_name($sql, ':id', $_POST['course_id']);
        if (oci_execute($sql, OCI_COMMIT_ON_SUCCESS)) {
            $message = "Course updated!"; $msg_type = "success";
        } else { $e = oci_error($sql); $message = $e['message']; $msg_type = "danger"; }
    }

    // ADD MODULE
    if (isset($_POST['add_module'])) {
        $seq = oci_parse($conn, "SELECT seq_module.NEXTVAL as nid FROM dual");
        oci_execute($seq); $seq_row = oci_fetch_assoc($seq); $new_id = $seq_row['NID'];
        $sql = oci_parse($conn,
            "INSERT INTO modules (module_id,module_name,credit,duration_time,module_order,course_id)
             VALUES (:id,:mn,:cr,:du,:mo,:ci)");
        oci_bind_by_name($sql, ':id', $new_id);
        oci_bind_by_name($sql, ':mn', $_POST['module_name']);
        oci_bind_by_name($sql, ':cr', $_POST['credit']);
        oci_bind_by_name($sql, ':du', $_POST['duration_time']);
        oci_bind_by_name($sql, ':mo', $_POST['module_order']);
        oci_bind_by_name($sql, ':ci', $_POST['course_id']);
        if (oci_execute($sql, OCI_COMMIT_ON_SUCCESS)) {
            $message = "Module added!"; $msg_type = "success";
        } else { $e = oci_error($sql); $message = $e['message']; $msg_type = "danger"; }
    }

    // ADD PAYMENT
    if (isset($_POST['add_payment'])) {
        $seq = oci_parse($conn, "SELECT seq_payment.NEXTVAL as nid FROM dual");
        oci_execute($seq); $seq_row = oci_fetch_assoc($seq); $new_id = $seq_row['NID'];
        $sql = oci_parse($conn,
            "INSERT INTO payment (payment_id,amount,payment_date,payment_method,status,transaction_ref,student_id,entrollment_id)
             VALUES (:id,:am,SYSDATE,:pm,:st,:tx,:si,:ei)");
        oci_bind_by_name($sql, ':id', $new_id);
        oci_bind_by_name($sql, ':am', $_POST['amount']);
        oci_bind_by_name($sql, ':pm', $_POST['payment_method']);
        oci_bind_by_name($sql, ':st', $_POST['status']);
        oci_bind_by_name($sql, ':tx', $_POST['transaction_ref']);
        oci_bind_by_name($sql, ':si', $_POST['student_id']);
        oci_bind_by_name($sql, ':ei', $_POST['entrollment_id']);
        if (oci_execute($sql, OCI_COMMIT_ON_SUCCESS)) {
            $message = "Payment added!"; $msg_type = "success";
        } else { $e = oci_error($sql); $message = $e['message']; $msg_type = "danger"; }
    }

    // UPDATE CERTIFICATE
    if (isset($_POST['update_certificate'])) {
        $sql = oci_parse($conn,
            "UPDATE certificate SET status=:st,grade=:gr,certificate_url=:ur,
             issued_date=SYSDATE WHERE certificate_id=:id");
        oci_bind_by_name($sql, ':st', $_POST['status']);
        oci_bind_by_name($sql, ':gr', $_POST['grade']);
        oci_bind_by_name($sql, ':ur', $_POST['certificate_url']);
        oci_bind_by_name($sql, ':id', $_POST['certificate_id']);
        if (oci_execute($sql, OCI_COMMIT_ON_SUCCESS)) {
            $message = "Certificate updated!"; $msg_type = "success";
        } else { $e = oci_error($sql); $message = $e['message']; $msg_type = "danger"; }
    }

    // ADD ASSIGNMENT
    if (isset($_POST['add_assignment'])) {
        $seq = oci_parse($conn, "SELECT seq_assignment.NEXTVAL as nid FROM dual");
        oci_execute($seq); $seq_row = oci_fetch_assoc($seq); $new_id = $seq_row['NID'];
        $sql = oci_parse($conn,
            "INSERT INTO assignment (assignment_id,title,description,due_date,max_marks,course_id,module_id,instructor_id)
             VALUES (:id,:ti,:de,TO_DATE(:du,'YYYY-MM-DD'),:ma,:ci,:mi,:ii)");
        oci_bind_by_name($sql, ':id', $new_id);
        oci_bind_by_name($sql, ':ti', $_POST['title']);
        oci_bind_by_name($sql, ':de', $_POST['description']);
        oci_bind_by_name($sql, ':du', $_POST['due_date']);
        oci_bind_by_name($sql, ':ma', $_POST['max_marks']);
        oci_bind_by_name($sql, ':ci', $_POST['course_id']);
        oci_bind_by_name($sql, ':mi', $_POST['module_id']);
        oci_bind_by_name($sql, ':ii', $_POST['instructor_id']);
        if (oci_execute($sql, OCI_COMMIT_ON_SUCCESS)) {
            $message = "Assignment added!"; $msg_type = "success";
        } else { $e = oci_error($sql); $message = $e['message']; $msg_type = "danger"; }
    }
}

// DELETE
if ($action == 'delete') {
    $table_map = [
        'instructors'  => ['table'=>'instructors',  'pk'=>'instructor_id'],
        'courses'      => ['table'=>'course',        'pk'=>'course_id'],
        'modules'      => ['table'=>'modules',       'pk'=>'module_id'],
        'enrollments'  => ['table'=>'entrollment',   'pk'=>'entrollment_id'],
        'payments'     => ['table'=>'payment',       'pk'=>'payment_id'],
        'assignments'  => ['table'=>'assignment',    'pk'=>'assignment_id'],
        'certificates' => ['table'=>'certificate',   'pk'=>'certificate_id'],
        'students'     => ['table'=>'students',      'pk'=>'student_id'],
    ];
    if (isset($table_map[$section])) {
        $t  = $table_map[$section]['table'];
        $pk = $table_map[$section]['pk'];

        // If deleting a student, delete child records first
        if ($section === 'students') {
            $d1 = oci_parse($conn, "DELETE FROM PAYMENT WHERE STUDENT_ID=:id");
            oci_bind_by_name($d1, ':id', $id); oci_execute($d1, OCI_NO_AUTO_COMMIT);
            $d2 = oci_parse($conn, "DELETE FROM ENTROLLMENT WHERE STUDENT_ID=:id");
            oci_bind_by_name($d2, ':id', $id); oci_execute($d2, OCI_NO_AUTO_COMMIT);
            $d3 = oci_parse($conn, "DELETE FROM CERTIFICATE WHERE STUDENT_ID=:id");
            oci_bind_by_name($d3, ':id', $id); oci_execute($d3, OCI_NO_AUTO_COMMIT);
        }

        $sql = oci_parse($conn, "DELETE FROM $t WHERE $pk = :id");
        oci_bind_by_name($sql, ':id', $id);
        if (oci_execute($sql, OCI_COMMIT_ON_SUCCESS)) {
            $message = "Deleted successfully!"; $msg_type = "success";
        } else { $e = oci_error($sql); $message = $e['message']; $msg_type = "danger"; }
    }
}

// EDIT FETCH
$edit_row = null;
if ($action == 'edit' && $id) {
    $edit_queries = [
        'instructors'  => "SELECT * FROM instructors  WHERE instructor_id  = :id",
        'courses'      => "SELECT * FROM course        WHERE course_id      = :id",
        'modules'      => "SELECT * FROM modules       WHERE module_id      = :id",
        'payments'     => "SELECT * FROM payment       WHERE payment_id     = :id",
        'certificates' => "SELECT * FROM certificate   WHERE certificate_id = :id",
        'assignments'  => "SELECT * FROM assignment    WHERE assignment_id  = :id",
    ];
    if (isset($edit_queries[$section])) {
        $esql = oci_parse($conn, $edit_queries[$section]);
        oci_bind_by_name($esql, ':id', $id);
        oci_execute($esql);
        $edit_row = oci_fetch_assoc($esql);
    }
}

// COUNTS
$counts = [];
foreach (['instructors'=>'instructors','courses'=>'course','modules'=>'modules',
          'students'=>'students','enrollments'=>'entrollment','payments'=>'payment',
          'certificates'=>'certificate','assignments'=>'assignment'] as $k=>$t) {
    $cs = oci_parse($conn, "SELECT COUNT(*) AS CNT FROM $t");
    oci_execute($cs); $cr = oci_fetch_assoc($cs); $counts[$k] = $cr['CNT'];
}
$pending_count = 0;
$pc = oci_parse($conn, "SELECT COUNT(*) AS CNT FROM STUDENTS WHERE STATUS='PENDING'");
oci_execute($pc); $pr = oci_fetch_assoc($pc); $pending_count = $pr['CNT'];

// DROPDOWNS
$instructors_list = [];
$is = oci_parse($conn, "SELECT instructor_id,f_name,l_name FROM instructors ORDER BY f_name");
oci_execute($is); while($r=oci_fetch_assoc($is)) $instructors_list[]=$r;

$courses_list = [];
$cs = oci_parse($conn, "SELECT course_id,course_name FROM course ORDER BY course_name");
oci_execute($cs); while($r=oci_fetch_assoc($cs)) $courses_list[]=$r;

$modules_list = [];
$ms = oci_parse($conn, "SELECT module_id,module_name FROM modules ORDER BY module_name");
oci_execute($ms); while($r=oci_fetch_assoc($ms)) $modules_list[]=$r;

$students_list = [];
$ss = oci_parse($conn, "SELECT student_id,f_name,l_name FROM students WHERE STATUS='ACTIVE' ORDER BY f_name");
oci_execute($ss); while($r=oci_fetch_assoc($ss)) $students_list[]=$r;

$enrollments_list = [];
$es = oci_parse($conn, "SELECT entrollment_id FROM entrollment ORDER BY entrollment_id");
oci_execute($es); while($r=oci_fetch_assoc($es)) $enrollments_list[]=$r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Panel — Transcendant LMS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root{--sidebar-w:255px;--brand:#e94560;--brand2:#f5a623;--darker:#0d1117;--border:rgba(255,255,255,0.08);--muted:rgba(255,255,255,0.4);}
*{box-sizing:border-box;}
body{font-family:'Sora',sans-serif;background:#f0f2f5;margin:0;}
.sidebar{width:var(--sidebar-w);background:var(--darker);min-height:100vh;position:fixed;top:0;left:0;z-index:200;display:flex;flex-direction:column;border-right:1px solid var(--border);}
.sidebar-brand{padding:24px 20px;border-bottom:1px solid var(--border);}
.brand-logo{font-size:1.25rem;font-weight:800;background:linear-gradient(135deg,var(--brand),var(--brand2));-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.brand-sub{font-size:0.72rem;color:var(--muted);margin-top:2px;}
.nav-section{padding:8px 12px 4px;font-size:0.65rem;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:var(--muted);}
.nav-link{display:flex;align-items:center;gap:10px;color:rgba(255,255,255,0.55);padding:10px 16px;border-radius:10px;margin:2px 8px;font-size:0.875rem;font-weight:500;text-decoration:none;transition:all 0.2s;}
.nav-link:hover{background:rgba(255,255,255,0.06);color:#fff;}
.nav-link.active{background:rgba(233,69,96,0.15);color:#fff;border-left:3px solid var(--brand);}
.nav-link i{width:18px;text-align:center;font-size:0.9rem;}
.nav-badge{margin-left:auto;background:var(--brand);color:#fff;border-radius:10px;padding:1px 8px;font-size:0.68rem;font-weight:700;}
.sidebar-footer{padding:16px;border-top:1px solid var(--border);margin-top:auto;}
.main{margin-left:var(--sidebar-w);min-height:100vh;}
.topbar{background:#fff;border-bottom:1px solid #e5e7eb;padding:14px 28px;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:100;}
.topbar-title{font-size:1.1rem;font-weight:700;color:#111827;}
.topbar-sub{font-size:0.78rem;color:#6b7280;}
.content{padding:24px 28px;}
.stat-card{border-radius:16px;padding:20px;color:white;border:none;transition:transform 0.2s,box-shadow 0.2s;position:relative;overflow:hidden;}
.stat-card::after{content:'';position:absolute;right:-20px;top:-20px;width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,0.08);}
.stat-card:hover{transform:translateY(-4px);box-shadow:0 12px 30px rgba(0,0,0,0.15);}
.stat-card .stat-num{font-family:'Space Mono',monospace;font-size:2rem;font-weight:700;}
.stat-card .stat-lbl{font-size:0.8rem;opacity:0.75;margin-top:4px;}
.stat-card .stat-icon{font-size:1.8rem;opacity:0.3;position:absolute;right:20px;bottom:20px;}
.content-card{background:#fff;border-radius:16px;padding:24px;box-shadow:0 1px 4px rgba(0,0,0,0.06);border:1px solid #e5e7eb;margin-bottom:20px;}
.card-title{font-size:1rem;font-weight:700;color:#111827;display:flex;align-items:center;gap:8px;margin-bottom:20px;padding-bottom:14px;border-bottom:2px solid #f3f4f6;}
.card-title i{color:var(--brand);}
.admin-table{width:100%;border-collapse:collapse;}
.admin-table thead th{background:#f9fafb;color:#374151;font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;padding:12px 14px;border-bottom:2px solid #e5e7eb;white-space:nowrap;}
.admin-table tbody td{padding:12px 14px;border-bottom:1px solid #f3f4f6;font-size:0.875rem;color:#374151;vertical-align:middle;}
.admin-table tbody tr:hover td{background:#fafafa;}
.admin-table tbody tr:last-child td{border-bottom:none;}
.badge-active{background:#dcfce7;color:#166534;border-radius:20px;padding:3px 10px;font-size:0.72rem;font-weight:700;}
.badge-pending{background:#fef9c3;color:#854d0e;border-radius:20px;padding:3px 10px;font-size:0.72rem;font-weight:700;}
.badge-inactive{background:#fee2e2;color:#991b1b;border-radius:20px;padding:3px 10px;font-size:0.72rem;font-weight:700;}
.badge-info{background:#dbeafe;color:#1e40af;border-radius:20px;padding:3px 10px;font-size:0.72rem;font-weight:700;}
.btn-primary-custom{background:var(--brand);color:#fff;border:none;border-radius:10px;padding:9px 18px;font-size:0.875rem;font-weight:600;cursor:pointer;transition:all 0.2s;display:inline-flex;align-items:center;gap:6px;text-decoration:none;}
.btn-primary-custom:hover{background:#c23152;color:#fff;transform:translateY(-1px);}
.btn-sm-action{padding:5px 10px;font-size:0.78rem;border-radius:7px;border:none;cursor:pointer;transition:all 0.2s;display:inline-flex;align-items:center;gap:4px;}
.btn-edit{background:#fef9c3;color:#854d0e;}.btn-edit:hover{background:#fde047;}
.btn-delete{background:#fee2e2;color:#991b1b;}.btn-delete:hover{background:#fca5a5;}
.btn-approve{background:#dcfce7;color:#166534;font-weight:700;}.btn-approve:hover{background:#86efac;}
.btn-reject{background:#fee2e2;color:#991b1b;}.btn-reject:hover{background:#fca5a5;}
.modal-content{border-radius:16px;border:none;}
.modal-header{border-bottom:1px solid #e5e7eb;padding:18px 24px;}
.modal-header .modal-title{font-weight:700;color:#111827;}
.modal-body{padding:24px;}
.modal-footer{border-top:1px solid #e5e7eb;padding:16px 24px;}
.form-label{font-size:0.82rem;font-weight:600;color:#374151;margin-bottom:6px;}
.form-control,.form-select{border-radius:10px;border:1.5px solid #e5e7eb;font-size:0.875rem;padding:10px 12px;}
.form-control:focus,.form-select:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(233,69,96,0.1);}
.pending-banner{background:linear-gradient(135deg,#fff7ed,#fffbeb);border:1px solid #fed7aa;border-radius:12px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:12px;font-size:0.88rem;color:#92400e;}
/* CREDENTIALS POPUP */
.cred-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:9999;display:flex;align-items:center;justify-content:center;animation:fadeIn 0.2s ease;}
@keyframes fadeIn{from{opacity:0;}to{opacity:1;}}
.cred-box{background:#fff;border-radius:24px;padding:40px;max-width:480px;width:90%;text-align:center;box-shadow:0 30px 80px rgba(0,0,0,0.3);animation:slideUp 0.3s ease;}
@keyframes slideUp{from{transform:translateY(30px);opacity:0;}to{transform:translateY(0);opacity:1;}}
.cred-success-icon{width:70px;height:70px;background:#dcfce7;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2rem;margin:0 auto 16px;}
.cred-box h3{font-size:1.4rem;font-weight:800;color:#111827;margin-bottom:4px;}
.cred-subtitle{color:#6b7280;font-size:0.9rem;margin-bottom:24px;}
.cred-card{background:#f9fafb;border:2px dashed #e5e7eb;border-radius:16px;padding:24px;margin-bottom:20px;}
.cred-row{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid #e5e7eb;}
.cred-row:last-child{border-bottom:none;padding-bottom:0;}
.cred-label{font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#6b7280;}
.cred-value{font-family:'Space Mono',monospace;font-size:1.3rem;font-weight:700;color:#111827;background:#fff;border:1.5px solid #e5e7eb;border-radius:10px;padding:8px 16px;letter-spacing:2px;}
.cred-value.highlight{color:var(--brand);border-color:rgba(233,69,96,0.3);background:rgba(233,69,96,0.04);}
.cred-warning{background:#fffbeb;border:1px solid #fed7aa;border-radius:10px;padding:12px 16px;font-size:0.83rem;color:#92400e;margin-bottom:20px;display:flex;align-items:flex-start;gap:10px;text-align:left;}
.btn-print{background:#111827;color:#fff;border:none;border-radius:12px;padding:13px 20px;font-weight:700;font-size:0.95rem;width:100%;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:all 0.2s;margin-bottom:10px;}
.btn-print:hover{background:#1f2937;}
.btn-done{background:#f3f4f6;color:#374151;border:none;border-radius:12px;padding:12px 20px;font-weight:600;font-size:0.9rem;width:100%;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:all 0.2s;text-decoration:none;}
.btn-done:hover{background:#e5e7eb;color:#111827;}
@media print{body *{visibility:hidden;}#printArea,#printArea *{visibility:visible;}#printArea{position:fixed;top:0;left:0;width:100%;padding:40px;box-shadow:none;}.btn-print,.btn-done{display:none!important;}}
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo">🎓 Transcendant</div>
        <div class="brand-sub">Admin Control Panel</div>
    </div>
    <nav style="flex:1;padding:8px 0;overflow-y:auto;">
        <div class="nav-section">Main</div>
        <a class="nav-link <?= $section=='dashboard'?'active':'' ?>" href="?section=dashboard"><i class="fas fa-th-large"></i> Dashboard</a>
        <div class="nav-section">Students</div>
        <a class="nav-link <?= $section=='enrollments'?'active':'' ?>" href="?section=enrollments">
            <i class="fas fa-user-clock"></i> Enrollments
            <?php if ($pending_count > 0): ?><span class="nav-badge"><?= $pending_count ?></span><?php endif; ?>
        </a>
        <a class="nav-link <?= $section=='students'?'active':'' ?>" href="?section=students"><i class="fas fa-users"></i> All Students</a>
        <a class="nav-link <?= $section=='payments'?'active':'' ?>" href="?section=payments"><i class="fas fa-credit-card"></i> Payments</a>
        <a class="nav-link <?= $section=='certificates'?'active':'' ?>" href="?section=certificates"><i class="fas fa-certificate"></i> Certificates</a>
        <div class="nav-section">Academic</div>
        <a class="nav-link <?= $section=='instructors'?'active':'' ?>" href="?section=instructors"><i class="fas fa-chalkboard-teacher"></i> Instructors</a>
        <a class="nav-link <?= $section=='courses'?'active':'' ?>" href="?section=courses"><i class="fas fa-book"></i> Courses</a>
        <a class="nav-link <?= $section=='modules'?'active':'' ?>" href="?section=modules"><i class="fas fa-cubes"></i> Modules</a>
        <a class="nav-link <?= $section=='assignments'?'active':'' ?>" href="?section=assignments"><i class="fas fa-tasks"></i> Assignments</a>
    </nav>
    <div class="sidebar-footer">
        <a href="homepage.php" class="nav-link" style="margin:0;padding:8px 4px;"><i class="fas fa-home"></i> Homepage</a>
        <a href="Login.php" class="nav-link" style="margin:0;padding:8px 4px;color:#ef4444;"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>

<!-- MAIN -->
<div class="main">
<div class="topbar">
    <div>
        <div class="topbar-title"><?= ucfirst($section) ?> Management</div>
        <div class="topbar-sub">Transcendant LMS · <?= date('D, d M Y') ?></div>
    </div>
    <div class="d-flex align-items-center gap-3">
        <?php if ($pending_count > 0): ?>
        <a href="?section=enrollments" class="btn btn-warning btn-sm rounded-pill fw-bold">
            <i class="fas fa-bell me-1"></i><?= $pending_count ?> Pending
        </a>
        <?php endif; ?>
        <div style="width:38px;height:38px;border-radius:50%;background:var(--brand);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;">A</div>
    </div>
</div>

<div class="content">

<!-- ALERT -->
<?php if ($message): ?>
<div class="alert alert-<?= $msg_type ?> alert-dismissible fade show rounded-3 mb-4">
    <i class="fas fa-<?= $msg_type=='success'?'check-circle':'exclamation-circle' ?> me-2"></i>
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- CREDENTIALS POPUP -->
<?php if ($approved_student): ?>
<div class="cred-overlay">
    <div class="cred-box" id="printArea">
        <div class="cred-success-icon">✅</div>
        <h3>Student Approved!</h3>
        <p class="cred-subtitle"><?= htmlspecialchars($approved_student['F_NAME'].' '.$approved_student['L_NAME']) ?> has been approved successfully.</p>
        <div class="cred-card">
            <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:#6b7280;margin-bottom:16px;">🔐 Login Credentials — Give to Student</div>
            <div class="cred-row">
                <span class="cred-label">Student ID</span>
                <span class="cred-value highlight"><?= str_pad($approved_student['STUDENT_ID'], 4, '0', STR_PAD_LEFT) ?></span>
            </div>
            <div class="cred-row">
                <span class="cred-label">Password</span>
                <span class="cred-value highlight"><?= htmlspecialchars($approved_student['PASSWORD']) ?></span>
            </div>
            <div class="cred-row">
                <span class="cred-label">Full Name</span>
                <span style="font-weight:600;color:#374151;"><?= htmlspecialchars($approved_student['F_NAME'].' '.$approved_student['L_NAME']) ?></span>
            </div>
            <div class="cred-row">
                <span class="cred-label">Phone</span>
                <span style="font-weight:600;color:#374151;"><?= htmlspecialchars($approved_student['PHONE'] ?? '—') ?></span>
            </div>
        </div>
        <div class="cred-warning">
            <i class="fas fa-exclamation-triangle" style="color:#f59e0b;margin-top:2px;flex-shrink:0;"></i>
            <span>Write down or print these credentials and hand to the student in person. Student uses <strong>ID</strong> and <strong>Password</strong> to login.</span>
        </div>
        <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print Credentials</button>
        <a href="?section=enrollments" class="btn-done"><i class="fas fa-check"></i> Done — Close</a>
    </div>
</div>
<?php endif; ?>

<!-- ══ DASHBOARD ══ -->
<?php if ($section == 'dashboard'): ?>
<div class="row g-3 mb-4">
<?php
$cards = [
    ['enrollments','Pending',      $pending_count,        'user-clock',         'linear-gradient(135deg,#f59e0b,#d97706)'],
    ['students',   'Students',     $counts['students'],   'users',              'linear-gradient(135deg,#3b82f6,#1d4ed8)'],
    ['instructors','Instructors',  $counts['instructors'],'chalkboard-teacher', 'linear-gradient(135deg,#10b981,#059669)'],
    ['courses',    'Courses',      $counts['courses'],    'book',               'linear-gradient(135deg,#8b5cf6,#7c3aed)'],
    ['payments',   'Payments',     $counts['payments'],   'credit-card',        'linear-gradient(135deg,#ef4444,#dc2626)'],
    ['certificates','Certificates',$counts['certificates'],'certificate',       'linear-gradient(135deg,#f97316,#ea580c)'],
    ['assignments','Assignments',  $counts['assignments'], 'tasks',             'linear-gradient(135deg,#06b6d4,#0891b2)'],
    ['modules',    'Modules',      $counts['modules'],    'cubes',              'linear-gradient(135deg,#ec4899,#db2777)'],
];
foreach ($cards as $c): ?>
<div class="col-6 col-md-3">
    <a href="?section=<?= $c[0] ?>" style="text-decoration:none;">
        <div class="stat-card" style="background:<?= $c[4] ?>;">
            <div class="stat-num"><?= $c[2] ?></div>
            <div class="stat-lbl"><?= $c[1] ?></div>
            <i class="fas fa-<?= $c[3] ?> stat-icon"></i>
        </div>
    </a>
</div>
<?php endforeach; ?>
</div>

<?php if ($pending_count > 0): ?>
<div class="pending-banner">
    <i class="fas fa-bell" style="color:#f59e0b;font-size:1.2rem;"></i>
    <div><strong><?= $pending_count ?> student(s)</strong> waiting for enrollment approval.</div>
    <a href="?section=enrollments" class="btn btn-warning btn-sm rounded-pill ms-auto fw-bold">Review Now →</a>
</div>
<?php endif; ?>

<div class="content-card">
    <div class="card-title"><i class="fas fa-users"></i>Recent Students</div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Branch</th><th>Qualification</th><th>Login</th><th>Payment</th></tr></thead>
            <tbody>
            <?php
            $rsql = oci_parse($conn,
                "SELECT STUDENT_ID,F_NAME,L_NAME,EMAIL,BRANCH,QUALIFICATION,LOGIN_STATUS,PAYMENT_STATUS
                 FROM STUDENTS WHERE ROWNUM<=10 ORDER BY STUDENT_ID DESC");
            oci_execute($rsql);
            while ($row = oci_fetch_assoc($rsql)):
            ?>
            <tr>
                <td><span style="font-family:monospace;font-size:0.8rem;color:#6b7280;">#<?= str_pad($row['STUDENT_ID'],4,'0',STR_PAD_LEFT) ?></span></td>
                <td style="font-weight:600;"><?= htmlspecialchars($row['F_NAME'].' '.$row['L_NAME']) ?></td>
                <td style="font-size:0.82rem;"><?= htmlspecialchars($row['EMAIL']) ?></td>
                <td><?= htmlspecialchars($row['BRANCH']) ?></td>
                <td><?= htmlspecialchars($row['QUALIFICATION'] ?? '—') ?></td>
                <td><span class="badge-<?= $row['LOGIN_STATUS']==='ACTIVE'?'active':'pending' ?>"><?= $row['LOGIN_STATUS'] ?></span></td>
                <td><span class="badge-<?= $row['PAYMENT_STATUS']==='Paid'?'active':($row['PAYMENT_STATUS']==='Partial'?'info':'pending') ?>"><?= $row['PAYMENT_STATUS'] ?></span></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ══ ENROLLMENTS ══ -->
<?php elseif ($section == 'enrollments'): ?>
<?php
$psql = oci_parse($conn,
    "SELECT s.STUDENT_ID, s.F_NAME, s.L_NAME, s.EMAIL, s.PHONE,
            s.BRANCH, s.QUALIFICATION, s.REGISTER_DATE, s.PAYMENT_STATUS,
            LISTAGG(c.COURSE_NAME,', ') WITHIN GROUP (ORDER BY c.COURSE_NAME) AS COURSES,
            SUM(p.PAID_AMOUNT) AS TOTAL_PAID,
            SUM(p.TOTAL_AMOUNT) AS TOTAL_FEE,
            SUM(p.BALANCE) AS TOTAL_BAL,
            MAX(p.PAYMENT_TYPE) AS PAY_TYPE
     FROM STUDENTS s
     LEFT JOIN ENTROLLMENT e ON s.STUDENT_ID=e.STUDENT_ID
     LEFT JOIN COURSE c      ON e.COURSE_ID=c.COURSE_ID
     LEFT JOIN PAYMENT p     ON s.STUDENT_ID=p.STUDENT_ID
     WHERE s.STATUS='PENDING'
     GROUP BY s.STUDENT_ID,s.F_NAME,s.L_NAME,s.EMAIL,s.PHONE,
              s.BRANCH,s.QUALIFICATION,s.REGISTER_DATE,s.PAYMENT_STATUS
     ORDER BY s.REGISTER_DATE DESC");
safe_oci_execute($psql);
$pending_students = [];
while ($row=oci_fetch_assoc($psql)) $pending_students[]=$row;

$asql = oci_parse($conn,
    "SELECT e.ENTROLLMENT_ID, e.JOINED_DATE, e.STATUS,
            s.F_NAME, s.L_NAME, s.STUDENT_ID, c.COURSE_NAME
     FROM ENTROLLMENT e
     JOIN STUDENTS s ON e.STUDENT_ID=s.STUDENT_ID
     JOIN COURSE c   ON e.COURSE_ID=c.COURSE_ID
     WHERE s.STATUS!='PENDING'
     ORDER BY e.ENTROLLMENT_ID DESC");
safe_oci_execute($asql);
$active_enrollments = [];
while ($row=oci_fetch_assoc($asql)) $active_enrollments[]=$row;
?>

<!-- PENDING APPROVALS -->
<div class="content-card">
    <div class="card-title">
        <i class="fas fa-user-clock" style="color:#f59e0b;"></i>
        Pending Approvals
        <?php if (count($pending_students)>0): ?>
        <span class="badge-pending ms-1"><?= count($pending_students) ?></span>
        <?php endif; ?>
    </div>
    <?php if (empty($pending_students)): ?>
    <div class="text-center py-5" style="color:#9ca3af;">
        <i class="fas fa-check-circle fa-3x mb-3" style="color:#10b981;opacity:0.5;"></i><br>
        <strong>No pending registrations!</strong><br>
        <span style="font-size:0.88rem;">All students have been processed.</span>
    </div>
    <?php else: ?>
    <p style="font-size:0.84rem;color:#6b7280;margin-bottom:16px;">
        <i class="fas fa-info-circle me-1" style="color:#3b82f6;"></i>
        Click <strong>Approve</strong> to generate Student ID and Password. Give credentials to student in person.
    </p>
    <div class="table-responsive">
        <table class="admin-table">
            <thead><tr>
                <th>ID</th><th>Name</th><th>Email</th><th>Phone</th>
                <th>Courses</th><th>Branch</th><th>Qualification</th>
                <th>Total Fee</th><th>Paid</th><th>Balance</th>
                <th>Pay Type</th><th>Registered</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($pending_students as $s): ?>
            <tr>
                <td><span style="font-family:monospace;font-size:0.8rem;color:#6b7280;">#<?= str_pad($s['STUDENT_ID'],4,'0',STR_PAD_LEFT) ?></span></td>
                <td style="font-weight:600;white-space:nowrap;"><?= htmlspecialchars($s['F_NAME'].' '.$s['L_NAME']) ?></td>
                <td style="font-size:0.82rem;"><?= htmlspecialchars($s['EMAIL']) ?></td>
                <td><?= htmlspecialchars($s['PHONE'] ?? '—') ?></td>
                <td style="font-size:0.8rem;max-width:160px;"><?= htmlspecialchars($s['COURSES']??'—') ?></td>
                <td><?= htmlspecialchars($s['BRANCH'] ?? '—') ?></td>
                <td><?= htmlspecialchars($s['QUALIFICATION'] ?? '—') ?></td>
                <td style="font-family:monospace;font-size:0.82rem;">Rs.<?= number_format($s['TOTAL_FEE']??0,0) ?></td>
                <td style="font-family:monospace;font-size:0.82rem;color:#059669;font-weight:700;">Rs.<?= number_format($s['TOTAL_PAID']??0,0) ?></td>
                <td style="font-family:monospace;font-size:0.82rem;color:#dc2626;font-weight:700;">Rs.<?= number_format($s['TOTAL_BAL']??0,0) ?></td>
                <td><span class="badge-info"><?= htmlspecialchars($s['PAY_TYPE']??'—') ?></span></td>
                <td style="font-size:0.8rem;white-space:nowrap;"><?= $s['REGISTER_DATE']?date('d M Y',strtotime($s['REGISTER_DATE'])):'—' ?></td>
                <td style="white-space:nowrap;">
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="student_id" value="<?= $s['STUDENT_ID'] ?>">
                        <button type="submit" name="approve_student" class="btn-sm-action btn-approve"
                                onclick="return confirm('Approve <?= htmlspecialchars(addslashes($s['F_NAME'].' '.$s['L_NAME'])) ?> and generate credentials?')">
                            <i class="fas fa-check"></i> Approve
                        </button>
                    </form>
                    <form method="POST" style="display:inline;margin-left:4px;">
                        <input type="hidden" name="student_id" value="<?= $s['STUDENT_ID'] ?>">
                        <button type="submit" name="reject_student" class="btn-sm-action btn-reject"
                                onclick="return confirm('Reject and delete <?= htmlspecialchars(addslashes($s['F_NAME'])) ?>?')">
                            <i class="fas fa-times"></i> Reject
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ACTIVE ENROLLMENTS -->
<div class="content-card">
    <div class="card-title"><i class="fas fa-clipboard-list"></i>Active Enrollments</div>
    <?php if (empty($active_enrollments)): ?>
    <p style="color:#9ca3af;">No active enrollments yet.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="admin-table">
            <thead><tr><th>Enrollment ID</th><th>Student ID</th><th>Student Name</th><th>Course</th><th>Joined</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($active_enrollments as $row): ?>
            <tr>
                <td><span style="font-family:monospace;font-size:0.8rem;color:#6b7280;">#<?= $row['ENTROLLMENT_ID'] ?></span></td>
                <td><span style="font-family:monospace;font-size:0.8rem;font-weight:700;color:var(--brand);">#<?= str_pad($row['STUDENT_ID'],4,'0',STR_PAD_LEFT) ?></span></td>
                <td style="font-weight:600;"><?= htmlspecialchars($row['F_NAME'].' '.$row['L_NAME']) ?></td>
                <td><?= htmlspecialchars($row['COURSE_NAME']) ?></td>
                <td><?= $row['JOINED_DATE']?date('d M Y',strtotime($row['JOINED_DATE'])):'—' ?></td>
                <td><span class="badge-<?= $row['STATUS']==='ACTIVE'?'active':($row['STATUS']==='COMPLETED'?'info':'inactive') ?>"><?= $row['STATUS'] ?></span></td>
                <td>
                    <a href="?section=enrollments&action=delete&id=<?= $row['ENTROLLMENT_ID'] ?>"
                       class="btn-sm-action btn-delete" onclick="return confirm('Delete this enrollment?')">
                        <i class="fas fa-trash"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ══ ALL STUDENTS ══ -->
<?php elseif ($section == 'students'): ?>
<div class="content-card">
    <div class="card-title"><i class="fas fa-users"></i>All Students</div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead><tr>
                <th>Student ID</th><th>Name</th><th>Email</th><th>Phone</th>
                <th>Branch</th><th>Qualification</th>
                <th>Login</th><th>Payment</th><th>Password</th><th>Action</th>
            </tr></thead>
            <tbody>
            <?php
            $stlist = oci_parse($conn,
                "SELECT STUDENT_ID,F_NAME,L_NAME,EMAIL,PHONE,BRANCH,QUALIFICATION,
                        LOGIN_STATUS,PAYMENT_STATUS,GENERATED_PASS,STATUS
                 FROM STUDENTS ORDER BY STUDENT_ID DESC");
            oci_execute($stlist);
            while ($row=oci_fetch_assoc($stlist)):
            ?>
            <tr>
                <td><span style="font-family:monospace;font-weight:700;color:var(--brand);">#<?= str_pad($row['STUDENT_ID'],4,'0',STR_PAD_LEFT) ?></span></td>
                <td style="font-weight:600;"><?= htmlspecialchars($row['F_NAME'].' '.$row['L_NAME']) ?></td>
                <td style="font-size:0.82rem;"><?= htmlspecialchars($row['EMAIL']) ?></td>
                <td><?= htmlspecialchars($row['PHONE'] ?? '—') ?></td>
                <td><?= htmlspecialchars($row['BRANCH'] ?? '—') ?></td>
                <td><?= htmlspecialchars($row['QUALIFICATION'] ?? '—') ?></td>
                <td><span class="badge-<?= $row['LOGIN_STATUS']==='ACTIVE'?'active':'pending' ?>"><?= $row['LOGIN_STATUS'] ?></span></td>
                <td><span class="badge-<?= $row['PAYMENT_STATUS']==='Paid'?'active':($row['PAYMENT_STATUS']==='Partial'?'info':'pending') ?>"><?= $row['PAYMENT_STATUS'] ?></span></td>
                <td><span style="font-family:monospace;font-size:0.82rem;color:#f59e0b;font-weight:700;"><?= htmlspecialchars($row['GENERATED_PASS'] ?? '—') ?></span></td>
                <td>
                    <a href="?section=students&action=delete&id=<?= $row['STUDENT_ID'] ?>"
                       class="btn-sm-action btn-delete" onclick="return confirm('Delete this student?')">
                        <i class="fas fa-trash"></i>
                    </a>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ══ INSTRUCTORS ══ -->
<?php elseif ($section == 'instructors'): ?>
<div class="content-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="card-title mb-0"><i class="fas fa-chalkboard-teacher"></i>Instructors</div>
        <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addModal"><i class="fas fa-plus"></i> Add Instructor</button>
    </div>
    <form method="GET" class="mb-3">
        <input type="hidden" name="section" value="instructors">
        <div class="input-group" style="max-width:320px;">
            <input type="text" name="search" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
            <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
        </div>
    </form>
    <div class="table-responsive">
        <table class="admin-table">
            <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Branch</th><th>Specialization</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php
            $where = $search ? "WHERE UPPER(f_name||' '||l_name) LIKE UPPER('%$search%') OR UPPER(email) LIKE UPPER('%$search%')" : '';
            $ilist = oci_parse($conn, "SELECT * FROM instructors $where ORDER BY instructor_id");
            oci_execute($ilist);
            while ($row=oci_fetch_assoc($ilist)):
            ?>
            <tr>
                <td>#<?= $row['INSTRUCTOR_ID'] ?></td>
                <td style="font-weight:600;"><?= htmlspecialchars($row['F_NAME'].' '.$row['L_NAME']) ?></td>
                <td style="font-size:0.82rem;"><?= htmlspecialchars($row['EMAIL']) ?></td>
                <td><?= htmlspecialchars($row['PHONE']) ?></td>
                <td><?= htmlspecialchars($row['BRANCH']) ?></td>
                <td><?= htmlspecialchars($row['SPECIALIZATION']) ?></td>
                <td><span class="badge-<?= $row['STATUS']==='Active'?'active':'inactive' ?>"><?= $row['STATUS'] ?></span></td>
                <td>
                    <a href="?section=instructors&action=edit&id=<?= $row['INSTRUCTOR_ID'] ?>" class="btn-sm-action btn-edit"><i class="fas fa-edit"></i></a>
                    <a href="?section=instructors&action=delete&id=<?= $row['INSTRUCTOR_ID'] ?>" class="btn-sm-action btn-delete" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></a>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Add Instructor</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST"><div class="modal-body"><div class="row g-3">
        <div class="col-md-6"><label class="form-label">First Name *</label><input type="text" name="f_name" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Last Name *</label><input type="text" name="l_name" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">Branch</label><input type="text" name="branch" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">Specialization</label><input type="text" name="specialization" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">Status</label><select name="status" class="form-select"><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div>
    </div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="add_instructor" class="btn-primary-custom"><i class="fas fa-save"></i> Save</button></div>
    </form>
</div></div></div>

<?php if ($action=='edit' && $edit_row): ?>
<div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.5)"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Edit Instructor</h5><a href="?section=instructors" class="btn-close"></a></div>
    <form method="POST"><input type="hidden" name="instructor_id" value="<?= $edit_row['INSTRUCTOR_ID'] ?>"><div class="modal-body"><div class="row g-3">
        <div class="col-md-6"><label class="form-label">First Name</label><input type="text" name="f_name" class="form-control" value="<?= htmlspecialchars($edit_row['F_NAME']) ?>" required></div>
        <div class="col-md-6"><label class="form-label">Last Name</label><input type="text" name="l_name" class="form-control" value="<?= htmlspecialchars($edit_row['L_NAME']) ?>" required></div>
        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($edit_row['EMAIL']) ?>"></div>
        <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($edit_row['PHONE']) ?>"></div>
        <div class="col-md-6"><label class="form-label">Branch</label><input type="text" name="branch" class="form-control" value="<?= htmlspecialchars($edit_row['BRANCH']) ?>"></div>
        <div class="col-md-6"><label class="form-label">Specialization</label><input type="text" name="specialization" class="form-control" value="<?= htmlspecialchars($edit_row['SPECIALIZATION']) ?>"></div>
        <div class="col-md-6"><label class="form-label">Status</label><select name="status" class="form-select"><option value="Active" <?= $edit_row['STATUS']==='Active'?'selected':'' ?>>Active</option><option value="Inactive" <?= $edit_row['STATUS']==='Inactive'?'selected':'' ?>>Inactive</option></select></div>
    </div></div>
    <div class="modal-footer"><a href="?section=instructors" class="btn btn-secondary">Cancel</a><button type="submit" name="update_instructor" class="btn-primary-custom"><i class="fas fa-save"></i> Update</button></div>
    </form>
</div></div></div>
<?php endif; ?>

<!-- ══ COURSES ══ -->
<?php elseif ($section == 'courses'): ?>
<div class="content-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="card-title mb-0"><i class="fas fa-book"></i>Courses</div>
        <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addModal"><i class="fas fa-plus"></i> Add Course</button>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead><tr><th>ID</th><th>Course Name</th><th>Duration</th><th>Fee</th><th>Instructor</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php
            $clist = oci_parse($conn,"SELECT c.*,i.f_name,i.l_name FROM course c LEFT JOIN instructors i ON c.instructor_id=i.instructor_id ORDER BY c.course_id");
            oci_execute($clist);
            while($row=oci_fetch_assoc($clist)):
            ?>
            <tr>
                <td>#<?= $row['COURSE_ID'] ?></td>
                <td style="font-weight:600;"><?= htmlspecialchars($row['COURSE_NAME']) ?></td>
                <td><?= htmlspecialchars($row['DURATION']) ?></td>
                <td style="font-family:monospace;">Rs.<?= number_format($row['FEE'],0) ?></td>
                <td><?= htmlspecialchars(($row['F_NAME']??'').' '.($row['L_NAME']??'')) ?></td>
                <td><span class="badge-<?= $row['STATUS']==='Active'?'active':'inactive' ?>"><?= $row['STATUS'] ?></span></td>
                <td>
                    <a href="?section=courses&action=edit&id=<?= $row['COURSE_ID'] ?>" class="btn-sm-action btn-edit"><i class="fas fa-edit"></i></a>
                    <a href="?section=courses&action=delete&id=<?= $row['COURSE_ID'] ?>" class="btn-sm-action btn-delete" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></a>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Add Course</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST"><div class="modal-body"><div class="row g-3">
        <div class="col-12"><label class="form-label">Course Name *</label><input type="text" name="course_name" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Duration</label><input type="text" name="duration" class="form-control" placeholder="e.g. 6 Months"></div>
        <div class="col-md-6"><label class="form-label">Fee (Rs.)</label><input type="number" name="fee" class="form-control" step="0.01"></div>
        <div class="col-md-6"><label class="form-label">Instructor</label><select name="instructor_id" class="form-select"><?php foreach($instructors_list as $i): ?><option value="<?= $i['INSTRUCTOR_ID'] ?>"><?= htmlspecialchars($i['F_NAME'].' '.$i['L_NAME']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label">Status</label><select name="status" class="form-select"><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div>
    </div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="add_course" class="btn-primary-custom"><i class="fas fa-save"></i> Save</button></div>
    </form>
</div></div></div>

<?php if ($action=='edit' && $edit_row): ?>
<div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.5)"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Edit Course</h5><a href="?section=courses" class="btn-close"></a></div>
    <form method="POST"><input type="hidden" name="course_id" value="<?= $edit_row['COURSE_ID'] ?>"><div class="modal-body"><div class="row g-3">
        <div class="col-12"><label class="form-label">Course Name</label><input type="text" name="course_name" class="form-control" value="<?= htmlspecialchars($edit_row['COURSE_NAME']) ?>" required></div>
        <div class="col-md-6"><label class="form-label">Duration</label><input type="text" name="duration" class="form-control" value="<?= htmlspecialchars($edit_row['DURATION']) ?>"></div>
        <div class="col-md-6"><label class="form-label">Fee</label><input type="number" name="fee" class="form-control" value="<?= $edit_row['FEE'] ?>" step="0.01"></div>
        <div class="col-md-6"><label class="form-label">Instructor</label><select name="instructor_id" class="form-select"><?php foreach($instructors_list as $i): ?><option value="<?= $i['INSTRUCTOR_ID'] ?>" <?= $i['INSTRUCTOR_ID']==$edit_row['INSTRUCTOR_ID']?'selected':'' ?>><?= htmlspecialchars($i['F_NAME'].' '.$i['L_NAME']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label">Status</label><select name="status" class="form-select"><option value="Active" <?= $edit_row['STATUS']==='Active'?'selected':'' ?>>Active</option><option value="Inactive" <?= $edit_row['STATUS']==='Inactive'?'selected':'' ?>>Inactive</option></select></div>
    </div></div>
    <div class="modal-footer"><a href="?section=courses" class="btn btn-secondary">Cancel</a><button type="submit" name="update_course" class="btn-primary-custom"><i class="fas fa-save"></i> Update</button></div>
    </form>
</div></div></div>
<?php endif; ?>

<!-- ══ MODULES ══ -->
<?php elseif ($section == 'modules'): ?>
<div class="content-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="card-title mb-0"><i class="fas fa-cubes"></i>Modules</div>
        <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addModal"><i class="fas fa-plus"></i> Add Module</button>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead><tr><th>ID</th><th>Module Name</th><th>Credits</th><th>Duration</th><th>Order</th><th>Course</th><th>Actions</th></tr></thead>
            <tbody>
            <?php
            $mlist = oci_parse($conn,"SELECT m.*,c.course_name FROM modules m LEFT JOIN course c ON m.course_id=c.course_id ORDER BY m.module_id");
            oci_execute($mlist);
            while($row=oci_fetch_assoc($mlist)):
            ?>
            <tr>
                <td>#<?= $row['MODULE_ID'] ?></td>
                <td style="font-weight:600;"><?= htmlspecialchars($row['MODULE_NAME']) ?></td>
                <td><span class="badge-info"><?= $row['CREDIT'] ?> credits</span></td>
                <td><?= htmlspecialchars($row['DURATION_TIME']) ?></td>
                <td><?= $row['MODULE_ORDER'] ?></td>
                <td><?= htmlspecialchars($row['COURSE_NAME']??'—') ?></td>
                <td><a href="?section=modules&action=delete&id=<?= $row['MODULE_ID'] ?>" class="btn-sm-action btn-delete" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></a></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Add Module</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST"><div class="modal-body"><div class="row g-3">
        <div class="col-12"><label class="form-label">Module Name *</label><input type="text" name="module_name" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Credits</label><input type="number" name="credit" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">Duration</label><input type="text" name="duration_time" class="form-control" placeholder="e.g. 4 Weeks"></div>
        <div class="col-md-6"><label class="form-label">Order</label><input type="number" name="module_order" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">Course</label><select name="course_id" class="form-select"><?php foreach($courses_list as $c): ?><option value="<?= $c['COURSE_ID'] ?>"><?= htmlspecialchars($c['COURSE_NAME']) ?></option><?php endforeach; ?></select></div>
    </div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="add_module" class="btn-primary-custom"><i class="fas fa-save"></i> Save</button></div>
    </form>
</div></div></div>

<!-- ══ PAYMENTS ══ -->
<?php elseif ($section == 'payments'): ?>
<div class="content-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="card-title mb-0"><i class="fas fa-credit-card"></i>Payments</div>
        <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addModal"><i class="fas fa-plus"></i> Add Payment</button>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead><tr><th>ID</th><th>Student</th><th>Paid</th><th>Total</th><th>Balance</th><th>Type</th><th>Method</th><th>Date</th><th>Ref</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php
            $plist = oci_parse($conn,"SELECT p.*,s.f_name,s.l_name FROM payment p JOIN students s ON p.student_id=s.student_id ORDER BY p.payment_id DESC");
            oci_execute($plist);
            while($row=oci_fetch_assoc($plist)):
            ?>
            <tr>
                <td>#<?= $row['PAYMENT_ID'] ?></td>
                <td style="font-weight:600;"><?= htmlspecialchars($row['F_NAME'].' '.$row['L_NAME']) ?></td>
                <td style="font-family:monospace;color:#059669;font-weight:700;">Rs.<?= number_format($row['PAID_AMOUNT']??$row['AMOUNT'],0) ?></td>
                <td style="font-family:monospace;">Rs.<?= number_format($row['TOTAL_AMOUNT']??0,0) ?></td>
                <td style="font-family:monospace;color:#dc2626;">Rs.<?= number_format($row['BALANCE']??0,0) ?></td>
                <td><span class="badge-info"><?= htmlspecialchars($row['PAYMENT_TYPE']??'—') ?></span></td>
                <td><?= htmlspecialchars($row['PAYMENT_METHOD']) ?></td>
                <td style="font-size:0.82rem;"><?= $row['PAYMENT_DATE']?date('d M Y',strtotime($row['PAYMENT_DATE'])):'—' ?></td>
                <td style="font-size:0.8rem;font-family:monospace;"><?= htmlspecialchars($row['TRANSACTION_REF']??'—') ?></td>
                <td><span class="badge-<?= $row['STATUS']==='Paid'?'active':($row['STATUS']==='Partial'?'info':'pending') ?>"><?= $row['STATUS'] ?></span></td>
                <td><a href="?section=payments&action=delete&id=<?= $row['PAYMENT_ID'] ?>" class="btn-sm-action btn-delete" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></a></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Add Payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST"><div class="modal-body"><div class="row g-3">
        <div class="col-12"><label class="form-label">Student</label><select name="student_id" class="form-select"><?php foreach($students_list as $s): ?><option value="<?= $s['STUDENT_ID'] ?>"><?= htmlspecialchars($s['F_NAME'].' '.$s['L_NAME']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label">Enrollment</label><select name="entrollment_id" class="form-select"><?php foreach($enrollments_list as $e): ?><option value="<?= $e['ENTROLLMENT_ID'] ?>">#<?= $e['ENTROLLMENT_ID'] ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label">Amount (Rs.)</label><input type="number" name="amount" class="form-control" step="0.01" required></div>
        <div class="col-md-6"><label class="form-label">Method</label><select name="payment_method" class="form-select"><option value="Cash">Cash</option><option value="Bank Transfer">Bank Transfer</option><option value="Card">Card</option><option value="Online">Online</option></select></div>
        <div class="col-md-6"><label class="form-label">Status</label><select name="status" class="form-select"><option value="Paid">Paid</option><option value="Partial">Partial</option><option value="Pending">Pending</option></select></div>
        <div class="col-12"><label class="form-label">Transaction Ref</label><input type="text" name="transaction_ref" class="form-control"></div>
    </div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="add_payment" class="btn-primary-custom"><i class="fas fa-save"></i> Save</button></div>
    </form>
</div></div></div>

<!-- ══ CERTIFICATES ══ -->
<?php elseif ($section == 'certificates'): ?>
<div class="content-card">
    <div class="card-title"><i class="fas fa-certificate"></i>Certificates</div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead><tr><th>ID</th><th>Student</th><th>Course</th><th>Grade</th><th>Issued</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php
            $certlist = oci_parse($conn,"SELECT cert.*,s.f_name,s.l_name,c.course_name FROM certificate cert JOIN students s ON cert.student_id=s.student_id JOIN course c ON cert.course_id=c.course_id ORDER BY cert.certificate_id DESC");
            oci_execute($certlist);
            while($row=oci_fetch_assoc($certlist)):
            ?>
            <tr>
                <td>#<?= $row['CERTIFICATE_ID'] ?></td>
                <td style="font-weight:600;"><?= htmlspecialchars($row['F_NAME'].' '.$row['L_NAME']) ?></td>
                <td><?= htmlspecialchars($row['COURSE_NAME']) ?></td>
                <td><?= $row['GRADE']?('<span class="badge-info">'.$row['GRADE'].'</span>'):'—' ?></td>
                <td><?= $row['ISSUED_DATE']?date('d M Y',strtotime($row['ISSUED_DATE'])):'Pending' ?></td>
                <td><span class="badge-<?= $row['STATUS']==='Issued'?'active':'pending' ?>"><?= $row['STATUS'] ?></span></td>
                <td>
                    <a href="?section=certificates&action=edit&id=<?= $row['CERTIFICATE_ID'] ?>" class="btn-sm-action btn-edit"><i class="fas fa-edit"></i></a>
                    <a href="?section=certificates&action=delete&id=<?= $row['CERTIFICATE_ID'] ?>" class="btn-sm-action btn-delete" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></a>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($action=='edit' && $edit_row): ?>
<div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.5)"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Update Certificate</h5><a href="?section=certificates" class="btn-close"></a></div>
    <form method="POST"><input type="hidden" name="certificate_id" value="<?= $edit_row['CERTIFICATE_ID'] ?>"><div class="modal-body"><div class="row g-3">
        <div class="col-md-6"><label class="form-label">Status</label><select name="status" class="form-select"><option value="Pending" <?= $edit_row['STATUS']==='Pending'?'selected':'' ?>>Pending</option><option value="Issued" <?= $edit_row['STATUS']==='Issued'?'selected':'' ?>>Issued</option></select></div>
        <div class="col-md-6"><label class="form-label">Grade</label><select name="grade" class="form-select"><option value="">—</option><?php foreach(['A+','A','B+','B','C+','C','D','F'] as $g): ?><option value="<?= $g ?>" <?= $edit_row['GRADE']==$g?'selected':'' ?>><?= $g ?></option><?php endforeach; ?></select></div>
        <div class="col-12"><label class="form-label">Certificate URL</label><input type="text" name="certificate_url" class="form-control" value="<?= htmlspecialchars($edit_row['CERTIFICATE_URL']??'') ?>"></div>
    </div></div>
    <div class="modal-footer"><a href="?section=certificates" class="btn btn-secondary">Cancel</a><button type="submit" name="update_certificate" class="btn-primary-custom"><i class="fas fa-save"></i> Update</button></div>
    </form>
</div></div></div>
<?php endif; ?>

<!-- ══ ASSIGNMENTS ══ -->
<?php elseif ($section == 'assignments'): ?>
<div class="content-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="card-title mb-0"><i class="fas fa-tasks"></i>Assignments</div>
        <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addModal"><i class="fas fa-plus"></i> Add Assignment</button>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead><tr><th>ID</th><th>Title</th><th>Course</th><th>Module</th><th>Max Marks</th><th>Due Date</th><th>Actions</th></tr></thead>
            <tbody>
            <?php
            $alist = oci_parse($conn,"SELECT a.*,c.course_name,m.module_name FROM assignment a JOIN course c ON a.course_id=c.course_id LEFT JOIN modules m ON a.module_id=m.module_id ORDER BY a.assignment_id DESC");
            oci_execute($alist);
            while($row=oci_fetch_assoc($alist)):
            ?>
            <tr>
                <td>#<?= $row['ASSIGNMENT_ID'] ?></td>
                <td style="font-weight:600;"><?= htmlspecialchars($row['TITLE']) ?></td>
                <td><?= htmlspecialchars($row['COURSE_NAME']) ?></td>
                <td><?= htmlspecialchars($row['MODULE_NAME']??'—') ?></td>
                <td><span class="badge-active"><?= $row['MAX_MARKS'] ?></span></td>
                <td><?= $row['DUE_DATE']?date('d M Y',strtotime($row['DUE_DATE'])):'—' ?></td>
                <td><a href="?section=assignments&action=delete&id=<?= $row['ASSIGNMENT_ID'] ?>" class="btn-sm-action btn-delete" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></a></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Add Assignment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST"><div class="modal-body"><div class="row g-3">
        <div class="col-12"><label class="form-label">Title *</label><input type="text" name="title" class="form-control" required></div>
        <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
        <div class="col-md-6"><label class="form-label">Due Date *</label><input type="date" name="due_date" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Max Marks</label><input type="number" name="max_marks" class="form-control" value="100"></div>
        <div class="col-md-4"><label class="form-label">Course</label><select name="course_id" class="form-select"><?php foreach($courses_list as $c): ?><option value="<?= $c['COURSE_ID'] ?>"><?= htmlspecialchars($c['COURSE_NAME']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Module</label><select name="module_id" class="form-select"><?php foreach($modules_list as $m): ?><option value="<?= $m['MODULE_ID'] ?>"><?= htmlspecialchars($m['MODULE_NAME']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Instructor</label><select name="instructor_id" class="form-select"><?php foreach($instructors_list as $i): ?><option value="<?= $i['INSTRUCTOR_ID'] ?>"><?= htmlspecialchars($i['F_NAME'].' '.$i['L_NAME']) ?></option><?php endforeach; ?></select></div>
    </div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="add_assignment" class="btn-primary-custom"><i class="fas fa-save"></i> Save</button></div>
    </form>
</div></div></div>

<?php endif; ?>

</div><!-- /content -->
</div><!-- /main -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php oci_close($conn); ?>