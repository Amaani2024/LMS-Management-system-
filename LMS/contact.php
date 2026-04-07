<?php
include 'components/navbar.php';
include 'database/db.php';
$id = $_GET['id'] ?? 1;
$sql = "SELECT * FROM courses WHERE id=$id";
$result = $conn->query($sql);
$course = $result->fetch_assoc();
?>

<div class="container my-5">
  <div class="row">
    <div class="col-md-6"><img src="<?php echo $course['thumbnail'];?>" class="img-fluid"></div>
    <div class="col-md-6">
      <h2><?php echo $course['title'];?></h2>
      <p>Instructor: <?php echo $course['instructor'];?></p>
      <p>Category: <?php echo $course['category'];?></p>
      <p><?php echo $course['rating'];?> | Students: <?php echo $course['students'];?></p>
      <p>Price: $<?php echo $course['price'];?></p>
      <a href="course-player.php?id=<?php echo $course['id'];?>" class="btn btn-success">Start Learning</a>
    </div>
  </div>
</div>

<?php include 'components/footer.php'; ?>