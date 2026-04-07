<?php
require 'database/database.php'; // establish DB connection first
?>


<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bootstrap demo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  </head>
  <body >

 <style>
      body {
        margin: 0;
        padding: 0;
      }
      </style>
    
    <nav class="navbar navbar-dark bg-dark fixed-top">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">Trancendant

    </a>

    <form class="d-flex mt-3" role="search">
          <input class="form-control me-2 flex-grow-1" type="search" placeholder="Search" aria-label="Search"/>
          <button class="btn btn-success" type="submit">Search</button>
    </form>

    <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasDarkNavbar" aria-controls="offcanvasDarkNavbar" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="offcanvas offcanvas-end text-bg-dark" tabindex="-1" id="offcanvasDarkNavbar" aria-labelledby="offcanvasDarkNavbarLabel">
      <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvasDarkNavbarLabel">Menu</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>
      <div class="offcanvas-body">
        <ul class="navbar-nav justify-content-end flex-grow-1 pe-3">
          <li class="nav-item">
            <a class="nav-link active" aria-current="page" href="#">About</a>
          </li>
          
           <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              fast links
            </a>
            <ul class="dropdown-menu dropdown-menu-dark">
              <li><a class="dropdown-item" href="#" onclick="navigateTo('result.php')">Results</a></li>
              <li><a class="dropdown-item" href="#">Programs</a></li>
              <li><a class="dropdown-item" href="#">Exams</a></li>
              <li><a class="dropdown-item" href="#">surpport</a></li>
             
            </ul>
          </li>

          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              Sign in as
            </a>
            <ul class="dropdown-menu dropdown-menu-dark">
              <li><a class="dropdown-item" href="#" onclick="navigateTo('registration.php')">Student</a></li>
             <li><a class="dropdown-item" href="#" onclick="navigateTo('admin_dash.php')">Admin</a></li>
             <li><a class="dropdown-item" href="#" onclick="navigateTo('instructor.php')">Instructor</a></li>
             
            </ul>
          </li>
        </ul>s
       
      </div>
    </div>
  </div>
</nav>
<div id="carouselExampleCaptions" class="carousel slide">
  <div class="carousel-indicators">
    <button type="button" data-bs-target="#carouselExampleCaptions" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
    <button type="button" data-bs-target="#carouselExampleCaptions" data-bs-slide-to="1" aria-label="Slide 2"></button>
    <button type="button" data-bs-target="#carouselExampleCaptions" data-bs-slide-to="2" aria-label="Slide 3"></button>
  </div>
  <div class="carousel-inner">
    <div class="carousel-item active">
      <img src="images/img1.jpg" class="d-block w-100 " alt="Campus aerial view"style="height:500px; object-fit:cover;">
      <div class="carousel-caption d-none d-md-block">
        <h5>First slide label</h5>
        <p>Some representative placeholder content for the first slide.</p>
      </div>
    </div>
    <div class="carousel-item">
      <img src="images/img2.jpg" class="d-block w-100 " alt="..."style="height:500px; object-fit:cover;">
      <div class="carousel-caption d-none d-md-block">
        <h5>Second slide label</h5>
        <p>Some representative placeholder content for the second slide.</p>
      </div>
    </div>
    <div class="carousel-item">
      <img src="images/img3.jpg" class="d-block w-100 " alt="..."style="height:500px; object-fit:cover;">
      <div class="carousel-caption d-none d-md-block">
        <h5>Third slide label</h5>
        <p>Some representative placeholder content for the third slide.</p>
      </div>
    </div>
  </div>
  <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleCaptions" data-bs-slide="prev">
    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
    <span class="visually-hidden">Previous</span>
  </button>
  <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleCaptions" data-bs-slide="next">
    <span class="carousel-control-next-icon" aria-hidden="true"></span>
    <span class="visually-hidden">Next</span>
  </button>
</div>

  <div class="text-center mb-4">
    <h2 class="fw-bold">Our Featured Courses</h2>
    <p class="text-muted">Explore some of the most popular courses in our LMS</p>
  </div>

<!-- Dynamic Cards -->
<?php
$sql = "SELECT course_name, duration, fee FROM course";
$stid = oci_parse($conn, $sql);
oci_execute($stid);
?>
<div class="container mt-4">
    <div class="row">
        <?php while ($row = oci_fetch_assoc($stid)): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100 shadow-sm">
                    
                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($row['COURSE_NAME']) ?></h5>
                        <p class="card-text"><?= htmlspecialchars($row['DURATION']) ?></p>
                        <p class="card-text"><?=htmlspecialchars($row['FEE'])?></p>
                        <a href="registration.php" class="btn btn-primary">Register</a>
                        <a href="#" class="btn btn-secondary">Learn More</a>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<?php
oci_free_statement($stid);
oci_close($conn);
?>



<div class="container mt-5 p-4 bg-light rounded">
  <div class="text-center mb-4">
    <h2 class="fw-bold">What Our Students Say</h2>
    <p class="text-muted">Hear from some of our students about their learning experience</p>
  </div>

  <div class="card mb-3 shadow-sm">
    <div class="card-body">
      <blockquote class="blockquote mb-0">
        <p>"This LMS has completely changed the way I learn. The courses are very engaging!"</p>
        <footer class="blockquote-footer">Jane Doe, <cite title="Source Title">Computer Science</cite></footer>
      </blockquote>
    </div>
  </div>

  <div class="card mb-3 shadow-sm">
    <div class="card-body">
      <blockquote class="blockquote mb-0">
        <p>"The lecturers are very helpful, and the platform is easy to use. Highly recommended."</p>
        <footer class="blockquote-footer">John Smith, <cite title="Source Title">Engineering</cite></footer>
      </blockquote>
    </div>
  </div>

  <div class="card mb-3 shadow-sm">
    <div class="card-body">
      <blockquote class="blockquote mb-0">
        <p>"I love the variety of courses available. Learning online has never been easier!"</p>
        <footer class="blockquote-footer">Emily Brown, <cite title="Source Title">Business</cite></footer>
      </blockquote>
    </div>
  </div>
</div>

 <footer class="bg-dark text-white mt-5" style="width: 100%; margin: 0; padding: 0;">
  <div class="d-flex flex-wrap justify-content-between py-5 px-4">
    <!-- About -->
    <div class="flex-fill p-3">
      <h5 class="text-warning">Trancendant LMS</h5>
      <p>Providing top-quality online learning experiences.</p>
    </div>

    <!-- Quick Links -->
    <div class="flex-fill p-3">
      <h5 class="text-warning">Quick Links</h5>
      <p><a href="#" class="text-white text-decoration-none">Home</a></p>
      <p><a href="#" class="text-white text-decoration-none">Courses</a></p>
      <p><a href="#" class="text-white text-decoration-none">Results</a></p>
    </div>

    <!-- Contact -->
    <div class="flex-fill p-3">
      <h5 class="text-warning">Contact</h5>
      <p>info@trancendant.com</p>
      <p>+123 456 7890</p>
    </div>

    <!-- Social Media -->
    <div class="flex-fill p-3">
      <h5 class="text-warning">Follow Us</h5>
      <a href="#" class="btn btn-outline-light btn-sm m-1"><i class="bi bi-facebook"></i></a>
      <a href="#" class="btn btn-outline-light btn-sm m-1"><i class="bi bi-twitter"></i></a>
      <a href="#" class="btn btn-outline-light btn-sm m-1"><i class="bi bi-instagram"></i></a>
    </div>
  </div>

  <div class="text-center py-3 border-top border-secondary">
    © 2026 Trancendant LMS
  </div>
</footer>  

<script>
function navigateTo(url) {
    
    var offcanvas = document.getElementById('offcanvasDarkNavbar');
    var bsOffcanvas = bootstrap.Offcanvas.getInstance(offcanvas);
    if (bsOffcanvas) {
        bsOffcanvas.hide();
    }
    setTimeout(function() {
        window.location.href = url;
    }, 300);
}
</script>

 <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
  </body>
</html>