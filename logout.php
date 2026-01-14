<?php
session_start();
session_unset();
session_destroy();

// Redirigir a la landing
header("Location: index.php?page=landing");
exit;
