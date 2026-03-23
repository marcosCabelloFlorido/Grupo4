<?php
session_start();
session_unset();
session_destroy();
// Redirigir a index.php que es tu login
header("Location: index.php");
exit();
?>