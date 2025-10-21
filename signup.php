<?php
session_start();

/* ---------- Simple JSON "DB" helpers ---------- */
const DB_FILE = __DIR__ . '/db.json';
if (!file_exists(DB_FILE)) {
  file_put_contents(DB_FILE, json_encode(["users"=>[]], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
}
function db_read() {
  return json_decode(file_get_contents(DB_FILE), true);
}
function db_write($data) {
  file_put_contents(DB_FILE, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
}

/* ---------- Handle Signup POST ---------- */
$err = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? "");
  $email    = trim($_POST['email'] ?? "");
  $password = $_POST['password'] ?? "";

  if ($username === "" || $email === "" || $password === "") {
    $err = "All fields are required.";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $err = "Please enter a valid email.";
  } else {
    $db = db_read();
    // Check uniqueness
    foreach ($db['users'] as $u => $data) {
      if (strcasecmp($u, $username) === 0) { $err = "Username already taken."; break; }
      if (strcasecmp($data['email'], $email) === 0) { $err = "Email already registered."; break; }
    }
    if ($err === "") {
      $db['users'][$username] = [
        "email" => $email,
        "password" => password_hash($password, PASSWORD_DEFAULT),
        "balance" => 100.00, // default
        "transactions" => [] // each: ["type"=>"credit/debit","amount"=>float,"note"=>string,"time"=>timestamp,"counterparty"=>string]
      ];
      db_write($db);
      $_SESSION['user'] = $username;

      // JS redirect to dashboard
      echo "<script>location.href='dashboard.php';</script>";
      exit;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Sign Up — PayFlow Demo</title>
<style>
:root{--bg:#0b1020;--card:#121a33;--acc:#4f8cff;--acc2:#69e6a6;--text:#eaf0ff;--muted:#99a4c2;--danger:#ff6b6b;}
*{box-sizing:border-box}
body{margin:0;font-family:Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif;background:radial-gradient(1200px 800px at 80% -10%,#1d2b5a 0%,#0b1020 45%),var(--bg);color:var(--text);min-height:100vh;display:grid;place-items:center;padding:24px}
.card{width:100%;max-width:420px;background:linear-gradient(180deg,rgba(255,255,255,.04),rgba(255,255,255,.01));border:1px solid rgba(255,255,255,.1);border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,.45);padding:26px 24px 22px}
h1{font-size:26px;margin:0 0 6px}
.subtitle{color:var(--muted);font-size:14px;margin:0 0 18px}
label{display:block;font-size:13px;color:#cfe0ff;margin:14px 0 6px}
input{width:100%;padding:12px 14px;border-radius:12px;border:1px solid rgba(255,255,255,.12);background:#0f1530;color:var(--text);outline:none}
input:focus{border-color:var(--acc);box-shadow:0 0 0 4px rgba(79,140,255,.2)}
.btn{width:100%;margin-top:16px;padding:12px 14px;border:0;border-radius:14px;background:linear-gradient(135deg,var(--acc),#7aa5ff);color:white;font-weight:700;cursor:pointer;transition:transform .05s ease}
.btn:active{transform:translateY(1px)}
.linkrow{display:flex;justify-content:space-between;gap:8px;margin-top:14px}
a{color:var(--acc2);text-decoration:none}
.error{background:rgba(255,107,107,.12);border:1px solid rgba(255,107,107,.4);color:#ffdede;padding:10px 12px;border-radius:12px;margin-bottom:12px;font-size:13px}
.logo{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.badge{font-size:11px;color:#8fb6ff;background:rgba(79,140,255,.12);border:1px solid rgba(79,140,255,.35);padding:4px 8px;border-radius:999px}
</style>
</head>
<body>
  <div class="card">
    <div class="logo">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none"><path d="M4 12a8 8 0 1116 0 8 8 0 01-16 0Zm3.5 0h9M12 7v10" stroke="#69e6a6" stroke-width="2" stroke-linecap="round"/></svg>
      <h1 style="margin:0;">PayFlow — Sign Up</h1>
      <span class="badge">Demo</span>
    </div>
    <p class="subtitle">Create your account. New users start with <strong>$100</strong> in their wallet.</p>

    <?php if($err): ?><div class="error"><?=htmlspecialchars($err)?></div><?php endif; ?>

    <form method="post" onsubmit="return validate()">
      <label>Username</label>
      <input type="text" name="username" id="u" maxlength="32" required>

      <label>Email</label>
      <input type="email" name="email" id="e" required>

      <label>Password</label>
      <input type="password" name="password" id="p" minlength="6" required>

      <button class="btn" type="submit">Create Account</button>
    </form>

    <div class="linkrow">
      <span>Already have an account?</span> <a href="login.php">Log in</a>
    </div>
  </div>

<script>
function validate(){
  const u=document.getElementById('u').value.trim();
  const e=document.getElementById('e').value.trim();
  const p=document.getElementById('p').value;
  if(u.length<3){alert("Username must be at least 3 characters.");return false;}
  if(!e.includes('@')){alert("Please enter a valid email.");return false;}
  if(p.length<6){alert("Password must be at least 6 characters.");return false;}
  return true;
}
</script>
</body>
</html>
