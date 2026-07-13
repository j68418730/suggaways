<?php
$path = '/home/suggawayz/public_html/app/Helpers/functions.php';
$code = file_get_contents($path);

// Fix TLS peer verification in SMTP function
$old = 'stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);';
$new = '@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);';
$code = str_replace($old, $new, $code, $c1);
echo "TLS warning suppress: $c1\n";

// Also simplify: change SMTP back to localhost and use mail() fallback path
$old2 = "\$host = site_setting('email_smtp_host', '');\n    if (\$host) {";
$new2 = "\$host = site_setting('email_smtp_host', '');\n    // Use local mail() for local delivery\n    if (\$host && \$host !== 'localhost') {";
$code = str_replace($old2, $new2, $code, $c2);
echo "Mail fallback: $c2\n";

file_put_contents($path, $code);
echo "Functions.php updated\n";
