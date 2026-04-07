<?php
$username = "user1";
$password = "oracle123";
$connection_string = "localhost:1521/XEPDB1";

$conn = oci_connect($username, $password, $connection_string);

if (!$conn) {
    $e = oci_error();
    die("Connection failed: " . $e['message']);
}
?>