<?php
$fp = @fsockopen('localhost', 587, $errno, $errstr, 5);
if ($fp) {
    echo "Connected to localhost:587\n";
    $banner = fread($fp, 512);
    echo "Banner: $banner\n";
    fwrite($fp, "EHLO test\r\n");
    fflush($fp);
    echo fread($fp, 512);
    fclose($fp);
} else {
    echo "FAILED: $errno $errstr\n";
}
