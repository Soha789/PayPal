<?php
session_start();
const DB_FILE = __DIR__ . '/db.json';
if (!file_exists(DB_FILE)) {
  file_put_contents(DB_FILE, json_encode(["users"=>[]], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
}
function db_read(){return json_decode(file_get_contents(DB_FILE), true);}
function db_write($d){file_put_contents(DB_FILE, json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));}

$stage = $_SESSION['login_stage'] ?? 'creds'; // 'creds' or '2fa'
$err = "";

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? 'creds';
  if ($action==='creds') {
    $identifier = trim($_POST['identifier'] ?? ""); // email or username
    $password = $_POST['password'] ?? "";
    if ($identifier===""||$password==="") { $err="Please fill all fields."; }
    else {
      $db=db_read();
      $foundUser=null;
      foreach($db['users'] as $u=>$data){
        if (strcasecmp($u,$identifier)===0 || strcasecmp($data['email'],$identifier)===0){
          $foundUser=$u; break;
        }
      }
      if(!$foundUser){ $err="Account not found."; }
      else {
        if (!password_verify($password, $db['users'][$foundUser]['password'])) {
          $err="Incorrect password.";
        } else {
          // Ensure default balance exists
          if (!isset($db['users'][$foundUser]['balance'])) {
            $db['users'][$foundUser]['balance']=100.00;
            if (!isset($db['users'][$foundUser]['transactions'])) $db['users'][$foundUser]['transactions']=[];
            db_write($db);
          }
          // Create 2FA code for demo
          $code = random_int(100000,999999);
          $_SESSION['2fa_code']=$code;
          $_SESSION['2fa_user']=$foundUser;
          $_SESSION['login_stage']='2fa';
          $stage='2fa';
        }
      }
    }
  } elseif ($action==='2fa') {
    $entered = trim($_POST['code'] ?? "");
    if ($entered==="" || !isset($_SESSION['2fa_code'])) { $err="Enter your 6-digit code."; $stage='2fa'; }
    else {
      if ($entered === strval($_SESSION['2fa_code'])) {
        $_SESSION['user']=$_SESSION['2fa_user'];
        unset($_SESSION['2fa_code'], $_SESSION['2fa_user'], $_SESSION['login_stage']);
        echo "<script>location.href='dashboard.php';</script>";
        exit;
      } else { $err="Invalid code. Try again."; $stage='2fa'; }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — PayFlow Demo</title>
<style>
:root{--bg:#0b1020;--card:#121a33;--acc:#4f8cff;--acc2:#69e6a6;--text:#eaf0ff;--muted:#99a4c2;--danger:#ff6b6b;}
*{box-sizing:border-box} body{margin:0;font-family:Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif;background:radial-gradient(1200px 800px at 80% -10%,#1d2b5a 0%,#0b1020 45%),var(--bg);color:var(--text);min-height:100vh;display:grid;place-items:center;padding:24px}
.card{width:100%;max-width:420px;background:linear-gradient(180deg,rgba(255,255,255,.04),rgba(255,255,255,.01));border:1px solid rgba(255,255,255,.1);border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,.45);padding:26px 24px 22px}
h1{font-size:26px;margin:0 0 6px}.subtitle{color:var(--muted);font-size:14px;margin:0 0 18px}
label{display:block;font-size:13px;color:#cfe0ff;margin:14px 0 6px}
input{width:100%;padding:12px 14px;border-radius:12px;border:1px solid rgba(255,255,255,.12);background:#0f1530;color:var(--text);outline:none}
input:focus{border-color:var(--acc);box-shadow:0 0 0 4px rgba(79,140,255,.2)}
.btn{width:100%;margin-top:16px;padding:12px 14px;border:0;border-radius:14px;background:linear-gradient(135deg,var(--acc),#7aa5ff);color:white;font-weight:700;cursor:pointer}
.error{background:rgba(255,107,107,.12);border:1px solid rgba(255,107,107,.4);color:#ffdede;padding:10px 12px;border-radius:12px;margin-bottom:12px;font-size:13px}
.badge{font-size:11px;color:#8fb6ff;background:rgba(79,140,255,.12);border:1px solid rgba(79,140,255,.35);padding:4px 8px;border-radius:999px}
.info{font-size:13px;color:#b9c8ff;background:rgba(105,230,166,.08);border:1px dashed rgba(105,230,166,.4);padding:10px 12px;border-radius:12px;margin-top:8px}
.linkrow{display:flex;justify-content:space-between;gap:8px;margin-top:14px} a{color:var(--acc2);text-decoration:none}
</style>
</head>
<body>
  <div class="card">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none"><path d="M4 12a8 8 0 1116 0 8 8 0 01-16 0Zm3.5 0h9M12 7v10" stroke="#69e6a6" stroke-width="2" stroke-linecap="round"/></svg>
      <h1 style="margin:0;">PayFlow — Login</h1><span class="badge">2FA Enabled</span>
    </div>
    <?php if($err): ?><div class="error"><?=htmlspecialchars($err)?></div><?php endif; ?>

    <?php if($stage==='creds'): ?>
      <p class="subtitle">Sign in with your <b>username or email</b> and password.</p>
      <form method="post" onsubmit="return checkCreds()">
        <input type="hidden" name="action" value="creds">
        <label>Username or Email</label>
        <input type="text" name="identifier" id="i" required>
        <label>Password</label>
        <input type="password" name="password" id="p" required minlength="6">
        <button class="btn" type="submit">Continue</button>
      </form>
      <div class="linkrow"><span>New here?</span><a href="signup.php">Create account</a></div>
    <?php else: ?>
      <p class="subtitle">Enter the 6-digit 2FA code.</p>
      <div class="info">Demo note: Your one-time code is <b><?=$_SESSION['2fa_code']??'******'?></b>. In production this is sent by email/SMS.</div>
      <form method="post" style="margin-top:10px" onsubmit="return check2FA()">
        <input type="hidden" name="action" value="2fa">
        <label>2FA Code</label>
        <input type="text" name="code" id="c" inputmode="numeric" pattern="\d{6}" maxlength="6" required>
        <button class="btn" type="submit">Verify & Sign In</button>
      </form>
    <?php endif; ?>
  </div>

<script>
function checkCreds(){
  const i=document.getElementById('i').value.trim();
  const p=document.getElementById('p').value;
  if(i===""||p===""){alert("Fill all fields.");return false;}
  return true;
}
function check2FA(){
  const c=document.getElementById('c').value.trim();
  if(!/^\d{6}$/.test(c)){alert("Enter 6 digits.");return false;}
  return true;
}
</script>
</body>
</html>
