<?php
// Validar sintaxe do MobileController
$file = '/var/www/html/app/Controllers/MobileController.php';
$output = shell_exec("php -l $file");
echo $output;
