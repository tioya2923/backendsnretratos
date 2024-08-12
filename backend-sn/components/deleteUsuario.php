<?php
require_once '../connect/server.php';
require_once '../connect/cors.php';
include './updateUsuarios.php';


$id = $_GET['id'];
deleteUser($id);
?>
