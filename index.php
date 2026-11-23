<?php /* index.php - Sentinel dashboard (front-end). Served by the PHP server on port 8080. */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sentinel | Apache monitoring</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header>
  <div class="brand">
    <svg width="42" height="42" viewBox="0 0 42 42" aria-hidden="true">
      <path d="M21 3 36 9v11c0 9-6 16-15 19C12 36 6 29 6 20V9z" fill="#0f1b15" stroke="#3ddc84" stroke-width="2.5"/>
      <path d="M13 22h5l3-7 4 12 3-5h3" fill="none" stroke="#3ddc84" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <div><h1>Sentinel</h1><small id="serverName">Server: connecting…</small></div>
  </div>
  <div class="tools">
    <span>Last update: <b id="lastUpdate">never</b></span>
    <label><input type="checkbox" id="auto" checked> Auto-refresh (5 s)</label>
    <button id="refresh">Refresh</button>
  </div>
</header>

<div id="banner" role="alert"></div>

<div class="cards">
  <div class="card" id="cApache"><h2>Apache status</h2><div class="big" id="vApache">–</div><div class="sub" id="sApache">waiting for data</div></div>
  <div class="card" id="cHttp"><h2>HTTP health</h2><div class="big" id="vHttp">–</div><div class="sub" id="sHttp">waiting for data</div></div>
  <div class="card" id="cCpu"><h2>CPU usage</h2><div class="big" id="vCpu">–</div><div class="sub" id="sCpu">waiting for data</div><div class="bar"><i id="bCpu"></i></div></div>
  <div class="card" id="cMem"><h2>Memory usage</h2><div class="big" id="vMem">–</div><div class="sub" id="sMem">waiting for data</div><div class="bar"><i id="bMem"></i></div></div>
</div>

<div class="grid">
  <section><h3>Latest errors</h3><div class="scroll"><table><thead><tr><th>Time</th><th>Level</th><th>Message</th></tr></thead><tbody id="tErr"></tbody></table></div></section>
  <section><h3>Latest access requests</h3><div class="scroll"><table><thead><tr><th>Time</th><th>IP</th><th>Request</th><th>Code</th></tr></thead><tbody id="tAcc"></tbody></table></div></section>
</div>

<div class="grid">
  <section><h3>Alerts</h3><div id="alerts"></div></section>
  <section><h3>Check history</h3><div class="scroll"><table><thead><tr><th>Time</th><th>Apache</th><th>HTTP</th><th>CPU</th><th>Memory</th></tr></thead><tbody id="tHist"></tbody></table></div></section>
</div>

<script src="assets/js/dashboard.js"></script>
</body>
</html>
