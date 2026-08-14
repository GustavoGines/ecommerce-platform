<?php
$im = imagecreatefrompng('public/images/logo.png');
$rgb = imagecolorat($im, 5, 5);
$r = ($rgb >> 16) & 0xFF;
$g = ($rgb >> 8) & 0xFF;
$b = $rgb & 0xFF;
printf('#%02x%02x%02x', $r, $g, $b);
?>
