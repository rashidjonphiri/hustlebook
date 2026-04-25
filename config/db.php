<?php
$host = "localhost";
$dbname = "hustlebook";
$username = "root";
$password = "";

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function hb_query(mysqli $conn, string $sql)
{
    return $conn->query($sql);
}

function hb_escape(mysqli $conn, ?string $value): string
{
    return $conn->real_escape_string((string)$value);
}
?>
