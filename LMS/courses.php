course                                                                                                                      <?php include 'components/navbar.php'; ?>
<?php include 'database/db.php'; ?>

<div class="container my-5">
  <h2>All Courses</h2>
  <div class="row">
    <?php
    $sql = "SELECT * FROM courses";
    $result = $conn->query($sql);
    if($result->num_rows > 0){
      while($course = $result->fetch_assoc()){
        echo '
        <div class="col-md-4 mb-4">
          <div class="card h-100">
            <img src="'.$course['thumbnail'].'" class="card-img-top">
            <div class="card-body">
              <h5>'.$course['title'].'</h5>
              <p>Instructor: '.$course['instructor'].'</p>
              <p>Category: '.$course['category'].'</p>
              <p>⭐ '.$course['rating'].' | Students: '.$course['students'].'</p>
              <p>$'.$course['price'].'</p>
              <a href="course-details.php?id='.$course['id'].'" class="btn btn-primary">View Course</a>
            </div>
          </div>
        </div>';
      }
    }
    ?>
  </div>
</div>

<?php include 'components/footer.php'; ?>