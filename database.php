<?php

$hostName = "localhost";
$dbUser = "root";
$dbPassword = "senha";
$dbName = "conecta";

$conn = mysqli_connect($hostName, $dbUser, $dbPassword, $dbName);

if (!$conn) {
    die("Something went wrong;");
}
?>