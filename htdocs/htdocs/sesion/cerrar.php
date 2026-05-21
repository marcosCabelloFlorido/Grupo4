<?php
session_start();
session_unset();
session_destroy();
// login.php está en la misma carpeta sesion/
header("Location: login.php");
exit();
?>
