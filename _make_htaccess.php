<?php
file_put_contents('/tmp/.htaccess-root', "RewriteEngine On\nRewriteRule ^(.*)\$ public/\$1 [L]\n");
echo "done\n";
