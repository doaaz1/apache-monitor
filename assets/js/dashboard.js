/* dashboard.js - Sentinel front-end.
 * Reads the six PHP API endpoints (see docs/FRONTEND_INTEGRATION.md) and updates the page.
 * Open index.php?demo=1 to test the design with fake data (no backend needed). */

const API      = "api";      // relative path: works because the dashboard is served by the same PHP server (port 8080)
const INTERVAL = 5000;       // auto-refresh every 5 seconds
const CPU_WARN = 80, MEM_WARN = 85;
const DEMO = new URLSearchParams(location.search).has("demo");

const $ = id => document.getElementById(id);
const history = [];
let timer = null, busy = false;

/* ---------- API ---------- */
// Returns {data} on success or {error} on failure, so one broken endpoint never breaks the whole page.
async function getJSON(endpoint){
  try{
    const res  = await fetch(`${API}/${endpoint}`, {cache: "no-store"});
    const json = await res.json();
    if(!json.success) return {error: json.error.message};
    return {data: json.data};
  }catch(e){
    return {error: "Cannot reach the backend (" + e.message + ")"};
  }
}

/* ---------- Helpers ---------- */
function setCard(id, level, big, sub){
  $("c"+id).className = "card " + level;
  $("v"+id).textContent = big;
  $("s"+id).textContent = sub;
}
function setBar(id, pct){ $(id).style.width = Math.min(100, Math.max(0, pct)) + "%"; }
function levelFor(p, warn){ return p >= 95 ? "bad" : p >= warn ? "warn" : "ok"; }
function tag(text, level){
  const s = document.createElement("span"); s.className = "tag t-" + level; s.textContent = text; return s;
}
function fillTable(tbodyId, rows, cols, emptyText){
  const tb = $(tbodyId); tb.textContent = "";
  if(!rows || !rows.length){
    const td = tb.insertRow().insertCell();
    td.colSpan = cols.length; td.className = "empty"; td.textContent = emptyText; return;
  }
  rows.forEach(r => {
    const tr = tb.insertRow();
    cols.forEach(c => {
      const td = tr.insertCell(), v = c(r);
      if(v instanceof Node) td.appendChild(v); else td.textContent = v;
    });
  });
}
const num = (v, d = 0) => (typeof v === "number" ? v.toFixed(d) : "–");

/* ---------- Demo data (same shape as the real API) ---------- */
function demoResponses(){
  const down = Math.random() < 0.15;
  return {
    status: {data: {service:"apache2", apache_status: down ? "stopped" : "running", raw_state: down ? "inactive" : "active", uptime_human: down ? null : "1d 2h 3m 4s"}},
    health: {data: down ? {http_status:"offline", http_code:null, response_time_ms:null, error:"Connection failed."}
                        : {http_status:"online",  http_code:200,  response_time_ms:3.4,  error:null}},
    cpu:    {data: {cpu_usage_percent: Math.round(10 + Math.random()*75), cpu_cores: 2}},
    memory: {data: {total_mb:3992.27, available_mb:3200, used_mb:792, usage_percent: Math.round(30 + Math.random()*40)}},
    errors: {data: {entries: [
      {timestamp:"2026-11-22 15:22:00", level:"notice", message:"AH00491: caught SIGTERM, shutting down"},
      {timestamp:"2026-11-22 15:20:41", level:"error",  message:"File does not exist: /var/www/html/favicon.ico"}]}},
    access: {data: {entries: [
      {parsed:true, ip:"192.168.56.1", timestamp:"2026-11-22 15:21:15", method:"GET", path:"/", status_code:200},
      {parsed:true, ip:"192.168.56.1", timestamp:"2026-11-22 15:21:10", method:"GET", path:"/missing.html", status_code:404}]}}
  };
}

/* ---------- Render ---------- */
function render(r){
  const now = new Date();
  $("lastUpdate").textContent = now.toLocaleTimeString();
  const alerts = [];
  const alert = (lvl, msg) => alerts.push([lvl, msg]);

  /* Apache status */
  let apacheText = "N/A", apacheOk = false;
  if(r.status.data){
    const s = r.status.data; apacheText = s.apache_status;
    $("serverName").textContent = "Server: " + s.service + " on " + location.hostname;
    const up = s.uptime_human ? " · up " + s.uptime_human : "";
    if(s.apache_status === "running"){ apacheOk = true; setCard("Apache","ok","Running","systemd: " + s.raw_state + up); }
    else if(s.apache_status === "stopped"){ setCard("Apache","bad","Stopped","systemd: " + s.raw_state); alert("bad","Apache is stopped. Start it with: sudo systemctl start apache2"); }
    else { setCard("Apache","warn","Unknown", s.message || "state could not be read"); alert("warn","Apache state is unknown."); }
  } else { setCard("Apache","bad","N/A", r.status.error); alert("bad","Status check failed: " + r.status.error); }

  /* HTTP health */
  let httpOk = false;
  if(r.health.data){
    const h = r.health.data; httpOk = h.http_status === "online";
    setCard("Http", httpOk ? "ok" : "bad", httpOk ? "Online" : "Offline",
      httpOk ? "HTTP " + h.http_code + " in " + num(h.response_time_ms, 1) + " ms" : (h.error || "no response from port 80"));
    if(!httpOk) alert("bad","The website is not answering on port 80.");
  } else { setCard("Http","bad","N/A", r.health.error); alert("bad","Health check failed: " + r.health.error); }

  /* CPU */
  let cpu = null;
  if(r.cpu.data){
    cpu = r.cpu.data.cpu_usage_percent;
    setCard("Cpu", levelFor(cpu, CPU_WARN), num(cpu, 1) + "%", r.cpu.data.cpu_cores + " core(s)"); setBar("bCpu", cpu);
    if(cpu >= CPU_WARN) alert("warn","CPU usage is high (" + num(cpu,1) + "%).");
  } else { setCard("Cpu","bad","N/A", r.cpu.error); setBar("bCpu", 0); }

  /* Memory */
  let mem = null;
  if(r.memory.data){
    const m = r.memory.data; mem = m.usage_percent;
    setCard("Mem", levelFor(mem, MEM_WARN), num(mem, 1) + "%", num(m.used_mb) + " MB of " + num(m.total_mb) + " MB"); setBar("bMem", mem);
    if(mem >= MEM_WARN) alert("warn","Memory usage is high (" + num(mem,1) + "%).");
  } else { setCard("Mem","bad","N/A", r.memory.error); setBar("bMem", 0); }

  /* Error log table */
  if(r.errors.data){
    fillTable("tErr", r.errors.data.entries, [
      e => e.timestamp || "–",
      e => e.level ? tag(e.level, ["error","crit","alert","emerg"].includes(e.level) ? "bad" : e.level === "warn" ? "warn" : "ok") : "–",
      e => e.message || e.raw
    ], "No errors recorded.");
  } else { fillTable("tErr", [], [x=>x,x=>x,x=>x], "Could not load error log: " + r.errors.error); alert("warn","Error log unavailable: " + r.errors.error); }

  /* Access log table */
  if(r.access.data){
    fillTable("tAcc", r.access.data.entries, [
      a => a.parsed ? a.timestamp : "–",
      a => a.parsed ? a.ip : "–",
      a => a.parsed ? a.method + " " + a.path : a.raw,
      a => a.parsed ? tag(a.status_code, a.status_code >= 500 ? "bad" : a.status_code >= 400 ? "warn" : "ok") : "–"
    ], "No access requests yet.");
  } else { fillTable("tAcc", [], [x=>x,x=>x,x=>x,x=>x], "Could not load access log: " + r.access.error); alert("warn","Access log unavailable: " + r.access.error); }

  /* Alerts */
  const al = $("alerts"); al.textContent = "";
  if(!alerts.length) alerts.push(["ok","All checks passed. No active alerts."]);
  alerts.forEach(([lvl, msg]) => { const d = document.createElement("div"); d.className = "alert " + lvl; d.textContent = msg; al.appendChild(d); });

  /* Check history (last 10 checks, kept in the browser only) */
  history.unshift({time: now.toLocaleTimeString(), apacheText, apacheOk, httpOk, cpu, mem});
  if(history.length > 10) history.pop();
  fillTable("tHist", history, [
    h => h.time,
    h => tag(h.apacheText, h.apacheOk ? "ok" : "bad"),
    h => tag(h.httpOk ? "Online" : "Offline", h.httpOk ? "ok" : "bad"),
    h => h.cpu === null ? "N/A" : num(h.cpu, 1) + "%",
    h => h.mem === null ? "N/A" : num(h.mem, 1) + "%"
  ], "No checks yet.");
}

/* ---------- Load ---------- */
async function load(){
  if(busy) return; busy = true;
  try{
    let r;
    if(DEMO) r = demoResponses();
    else{
      const [status, health, cpu, memory, errors, access] = await Promise.all(
        ["status.php","health.php","cpu.php","memory.php","errors.php","access.php"].map(getJSON));
      r = {status, health, cpu, memory, errors, access};
    }
    // If the four main checks all failed, the backend itself is down.
    const allDown = !r.status.data && !r.health.data && !r.cpu.data && !r.memory.data;
    $("banner").style.display = allDown ? "block" : "none";
    if(allDown) $("banner").textContent = "Cannot reach the monitoring backend (" + r.status.error + "). Start it with ./scripts/start_backend.sh, then press Refresh.";
    render(r);
  } finally { busy = false; }
}
function schedule(){
  clearInterval(timer);
  if($("auto").checked) timer = setInterval(load, INTERVAL);
}
$("refresh").addEventListener("click", load);
$("auto").addEventListener("change", schedule);
load(); schedule();
