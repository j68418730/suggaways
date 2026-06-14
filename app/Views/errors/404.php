<?php http_response_code(404); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Page Not Found &mdash; SUGGAWAYZ</title>
<link rel="stylesheet" href="/assets/css/style.css">
<style>
.error-page{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:60vh;text-align:center;padding:40px 20px}
.error-page h1{font-size:72px;color:var(--red);margin-bottom:4px}
.error-page h2{font-size:20px;margin-bottom:12px}
.error-page p{color:var(--muted);max-width:400px;margin-bottom:24px}
.error-page .button{display:inline-block}
</style>
</head>
<body>
<div class="error-page">
  <h1>404</h1>
  <h2>Page Not Found</h2>
  <p>The page you are looking for does not exist or has been moved.</p>
  <a href="/" class="button primary">Go Home</a>
  <a href="javascript:history.back()" class="button" style="margin-left:8px">Go Back</a>
</div>
</body>
</html>