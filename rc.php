<?php
$path = '/home/suggawayz/public_html/app/Views/render.php';
$code = file_get_contents($path);

$old = '    <div id="dropCountdown" style="text-align:center;padding:16px;margin-bottom:20px;background:rgba(0,200,255,0.05);border:1px solid rgba(0,200,255,0.15);border-radius:8px;font-family:var(--mono)">
      <p style="font-size:12px;color:var(--cyan);margin-bottom:6px">🚀 SITE GOES LIVE IN</p>
      <div style="display:flex;justify-content:center;gap:16px;font-size:28px;font-weight:800;color:var(--text)">
        <span><span id="cdDays">00</span><span style="display:block;font-size:10px;color:var(--text2);font-weight:400">DAYS</span></span>
        <span style="color:var(--cyan)">:</span>
        <span><span id="cdHours">00</span><span style="display:block;font-size:10px;color:var(--text2);font-weight:400">HOURS</span></span>
        <span style="color:var(--cyan)">:</span>
        <span><span id="cdMins">00</span><span style="display:block;font-size:10px;color:var(--text2);font-weight:400">MINS</span></span>
        <span style="color:var(--cyan)">:</span>
        <span><span id="cdSecs">00</span><span style="display:block;font-size:10px;color:var(--text2);font-weight:400">SECS</span></span>
      </div>
    </div>
    <script>
    (function(){
      var now = new Date();
      var t = new Date(now.getFullYear(), now.getMonth(), 16, 13, 0, 0);
      if (now.getDate() > 16 || (now.getDate() === 16 && now.getHours() >= 13)) { document.getElementById(\'dropCountdown\').style.display = \'none\'; return; }
      function pad(n) { return n < 10 ? \'0\' + n : n; }
      function tick() {
        var d = t - new Date();
        if (d <= 0) { document.getElementById(\'dropCountdown\').style.display = \'none\'; return; }
        document.getElementById(\'cdDays\').textContent = pad(Math.floor(d / 86400000));
        document.getElementById(\'cdHours\').textContent = pad(Math.floor((d % 86400000) / 3600000));
        document.getElementById(\'cdMins\').textContent = pad(Math.floor((d % 3600000) / 60000));
        document.getElementById(\'cdSecs\').textContent = pad(Math.floor((d % 60000) / 1000));
      }
      tick(); setInterval(tick, 1000);
    })();
    </script>';

$new = '';

$code = str_replace($old, $new, $code, $c);
echo "Removed countdown: $c\n";

file_put_contents($path, $code);
echo "Done\n";
