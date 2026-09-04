<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AutoBacklink — Setup</title>
<style>
  :root { --bg:#0b1220; --card:#111a2e; --line:#1e2a44; --txt:#e5ecf8; --mut:#8ea0c0; --acc:#4f7cff; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: radial-gradient(1200px 600px at 70% -10%, #16233f 0%, var(--bg) 55%); color: var(--txt); font-family: 'Segoe UI', Arial, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
  .card { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 36px 32px; width: 100%; max-width: 420px; box-shadow: 0 20px 60px rgba(0,0,0,.45); }
  .logo { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; }
  .logo .dot { width: 34px; height: 34px; border-radius: 10px; background: linear-gradient(135deg, #4f7cff, #22d3ee); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 18px; }
  h1 { font-size: 20px; }
  .sub { color: var(--mut); font-size: 13px; margin: 8px 0 22px; }
  label { display: block; font-size: 12px; color: var(--mut); margin: 14px 0 6px; font-weight: 600; letter-spacing: .3px; }
  input { width: 100%; background: #0d1526; border: 1px solid var(--line); color: var(--txt); border-radius: 10px; padding: 12px 14px; font-size: 14px; outline: none; }
  input:focus { border-color: var(--acc); }
  button { margin-top: 20px; width: 100%; background: var(--acc); color: #fff; border: 0; border-radius: 10px; padding: 13px; font-size: 15px; font-weight: 700; cursor: pointer; }
  .err { color: #ef4444; font-size: 13px; margin-top: 12px; min-height: 16px; }
  .step { background: #0d1526; border: 1px solid var(--line); border-radius: 10px; padding: 10px 12px; font-size: 12.5px; color: var(--mut); margin-bottom: 6px; }
</style>
</head>
<body>
  <div class="card">
    <div class="logo"><div class="dot">AB</div><h1>Setup AutoBacklink</h1></div>
    <div class="sub">First run — create your admin account for this workspace</div>
    <div class="step">1️⃣ Create the login for this software (separate from AutoBlog)</div>
    <form onsubmit="return doSetup(event)">
      <label>USERNAME</label>
      <input type="text" id="u" required minlength="3">
      <label>PASSWORD (min 6 characters)</label>
      <input type="password" id="p" required minlength="6">
      <button type="submit">Create account →</button>
      <div class="err" id="err"></div>
    </form>
  </div>
<script>
async function doSetup(e) {
  e.preventDefault();
  const err = document.getElementById('err');
  err.textContent = '';
  const r = await fetch('/api/auth/setup', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ username: document.getElementById('u').value, password: document.getElementById('p').value }) });
  const d = await r.json();
  if (d.success) location.href = '/';
  else err.textContent = d.error || 'Setup failed';
  return false;
}
</script>
</body>
</html>
