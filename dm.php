<?php
require_once '/home/suggawayz/public_html/app/bootstrap.php';
db()->prepare("UPDATE site_settings SET setting_value='0' WHERE setting_key='maintenance_mode'")->execute();
echo "Maintenance mode disabled\n";
