<?php
session_start();
if(!isset($_SESSION['user'])){ echo "<script>location.href='login.php';</script>"; exit; }

const DB_FILE = __DIR__ . '/db.json';
function db_read(){return json_decode(file_get_contents(DB_FILE), true);}
function db_write($d){file_put_contents(DB_FILE, json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));}

$db = db_read();
$meUser = $_SESSION['user'];
if(!isset($db['users'][$meUser])){ echo "<script>alert('User not found');location.href='login.php';</script>"; exit; }
$me = $db['users'][$meUser];

$notice = $err = "";
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $to = trim($_POST['to'] ?? "");
  $amt = floatval($_POST['amount'] ?? 0);
  $note= trim($_POST['note'] ?? "Payment");

  if($to===""||$amt<=0){ $err="Enter a valid recipient and amount."; }
  else {
    $recipient = null;
    foreach($db['users'] as $u=>$data){
      if (strcasecmp($u,$to)===0 || strcasecmp($data['email'],$to)===0){ $recipient=$u; break; }
    }
    if(!$recipient){ $err="Recipient not found. Ask them to sign up first."; }
    elseif ($recipient === $meUser){ $err="You cannot send money to yourself."; }
    elseif ($db['users'][$meUser]['balance'] < $amt){ $err="Insufficient balance."; }
    else {
      // Perform transfer
      $db['users'][$meUser]['balance'] = round($db['users'][$meUser]['balance'] - $amt, 2);
      $db['users'][$recipient]['balance'] = round(($db['users'][$recipient]['balance'] ?? 100) + $amt, 2);

      $now = time();
      $db['users'][$meUser]['transactions'][]=[
        "type"=>"debit","amount"=>$amt,"note"=>$note,"time"=>$now,"counterparty"=>$recipient
      ];
      $db['users'][$recipient]['transactions'][]=[
        "type"=>"credit","amount"=>$amt,"note"=>$note,"time"=>$now,"counterparty"=>$meUser
      ];
      db_write($db);

      // Attempt to send email (may be disabled on many hosts)
      $rEmail = $db['users'][$recipient]['email'] ?? null;
      if ($rEmail) {
        @mail($rEmail, "You received $$amt on PayFlow (Demo)",
              "Hi $recipient,\n\nYou received $$amt from $meUser.\nNote: $note\n\n— PayFlow Demo",
              "From: no-reply@payflow-demo.local");
      }

      $notice = "Money sent successfully to $recipient.";
      echo "<script>alert(".json_encode($notice)."); location.href='dashboard.php';</script>";
      exit;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Send Money — PayFlow</title>
<style>
:root{--bg:#0b1020;--card:#121a33;--acc:#4f8cff;--acc2:#69e6a6;--text:#eaf0ff;--muted:#99a4c2;--danger:#ff6b6b;}
*{box-sizing:border-box} body{margin:0;font-family:Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif;background:
radial-gradient(1200px 800px at 90% -10%,#1d2b5a 0%,#0b1020 45%),var(--bg);color:var(--text);min-height:100vh;display:grid;place-items:center;padding:24px}
.card{width:100%;max-width:520px;background:linear-gradient(180deg,rgba(255,255,255,.04),rgba(255,255,255,.01));border:1px solid rgba(255,255,255,.1);border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,.45);padding:24px}
h2{margin:0 0 6px}
.subtitle{color:var(--muted);font-size:14px;margin:0 0 18px}
label{display:block;font-size:13px;color:#cfe0ff;margin:10px 0 6px}
input,textarea{width:100%;padding:12px;border-radius:12px;border:1px solid rgba(255,255,255,.12);background:#0f1530;color:var(--text)}
.btn{margin-top:14px;padding:12px 16px;border-radius:12px;border:0;background:linear-gradient(135deg,var(--acc),#7aa5ff);color:#fff;font-weight:700;cursor:pointer}
.back{margin-top:10px;display:inline-block;color:#69e6a6;text-decoration:none}
.error{background:rgba(255,107,107,.12);border:1px solid rgba(255,107,107,.4);color:#ffdede;padding:10px 12px;border-radius:12px;margin-bottom:12px;font-size:13px}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media(max-width:560px){.row{grid-template-columns:1fr}}
</style>
</head>
<body>
  <div class="card">
    <h2>Send Money</h2>
    <p class="subtitle">Your balance: <b>$<?=number_format($me['balance'],2)?></b></p>
    <?php if($err): ?><div class="error"><?=htmlspecialchars($err)?></div><?php endif; ?>
    <form method="post" onsubmit="return v()">
      <label>To (username or email)</label>
      <input type="text" name="to" id="to" placeholder="e.g., soha or soha@example.com" required>
      <div class="row">
        <div>
          <label>Amount (USD)</label>
          <input type="number" name="amount" id="amt" step="0.01" min="0.01" required>
        </div>
        <div>
          <label>Note</label>
          <input type="text" name="note" id="note" maxlength="80" placeholder="Payment for..." value="Payment">
        </div>
      </div>
      <button class="btn" type="submit">Send</button>
    </form>
    <a class="back" href="dashboard.php">← Back to Dashboard</a>
  </div>

<script>
function v(){
  const to=document.getElementById('to').value.trim();
  const amt=parseFloat(document.getElementById('amt').value);
  if(to==="" || !isFinite(amt) || amt<=0){ alert("Enter valid recipient and amount."); return false; }
  return confirm("Send $"+amt.toFixed(2)+" to "+to+"?");
}
</script>
</body>
</html>
