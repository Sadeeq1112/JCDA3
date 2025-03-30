<?php
// config.php
// $db_host = 'sql12.freesqldatabase.com';
// $db_user = 'sql12729944';
// $db_pass = 'EVQSwPt3iD';
// $db_name = 'sql12729944';

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'jcda_database';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>