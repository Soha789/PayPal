<?php
session_start();
if(!isset($_SESSION['user'])){ echo "<script>location.href='login.php';</script>"; exit; }

const DB_FILE = __DIR__ . '/db.json';
function db_read(){return json_decode(file_get_contents(DB_FILE), true);}
function db_write($d){file_put_contents(DB_FILE, json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));}

$db = db_read();
$user = $_SESSION['user'];
if(!isset($db['users'][$user])){ echo "<script>alert('User not found');location.href='login.php';</script>"; exit; }
$me = $db['users'][$user];

// Handle add-funds (dummy)
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['addFunds'])){
  $amt = floatval($_POST['amount'] ?? 0);
  if($amt>0){
    $db['users'][$user]['balance'] = round(($db['users'][$user]['balance'] + $amt), 2);
    $db['users'][$user]['transactions'][]=[
      "type"=>"credit","amount"=>$amt,"note"=>"Added funds (Demo)","time"=>time(),"counterparty"=>"Wallet Top-up"
    ];
    db_write($db);
    echo "<script>alert('Funds added: $'+(".json_encode(number_format($amt,2))."));location.href='dashboard.php';</script>";
    exit;
  } else {
    echo "<script>alert('Enter a valid amount');</script>";
  }
}
// Handle logout
if(isset($_GET['logout'])){ session_destroy(); echo "<script>location.href='login.php';</script>"; exit; }

$me = db_read()['users'][$user]; // refresh after any change
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard — PayFlow</title>
<style>
:root{--bg:#0b1020;--card:#121a33;--acc:#4f8cff;--acc2:#69e6a6;--text:#eaf0ff;--muted:#99a4c2;--danger:#ff6b6b;}
*{box-sizing:border-box} body{margin:0;font-family:Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif;background:
radial-gradient(1200px 800px at 90% -10%,#1d2b5a 0%,#0b1020 45%),var(--bg);color:var(--text);min-height:100vh}
.nav{display:flex;justify-content:space-between;align-items:center;padding:16px 22px;border-bottom:1px solid rgba(255,255,255,.08)}
.brand{display:flex;align-items:center;gap:10px}
.brand b{font-size:18px}
.user{color:#cfe0ff}
.container{max-width:1000px;margin:24px auto;padding:0 20px;display:grid;grid-template-columns:1fr 1fr;gap:20px}
.card{background:linear-gradient(180deg,rgba(255,255,255,.04),rgba(255,255,255,.01));border:1px solid rgba(255,255,255,.1);border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,.45);padding:22px}
h2{margin:0 0 10px;font-size:20px}
.balance{font-size:34px;font-weight:800;letter-spacing:.5px;margin:6px 0 16px}
.actions{display:flex;gap:10px;flex-wrap:wrap}
.btn{padding:10px 14px;border-radius:12px;border:0;background:linear-gradient(135deg,var(--acc),#7aa5ff);color:#fff;font-weight:700;cursor:pointer}
.btn.secondary{background:linear-gradient(135deg,var(--acc2),#91f5c7);color:#06220f}
.btn.danger{background:linear-gradient(135deg,#ff7b7b,#ff5252)}
label{display:block;font-size:13px;color:#cfe0ff;margin:10px 0 6px}
input{width:100%;padding:10px 12px;border-radius:12px;border:1px solid rgba(255,255,255,.12);background:#0f1530;color:var(--text)}
.txs{margin-top:10px;display:grid;gap:10px;max-height:380px;overflow:auto}
.tx{display:flex;justify-content:space-between;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06);padding:12px;border-radius:12px}
.tx .note{color:#cfe0ff;font-size:13px}
.tx .meta{color:#99a4c2;font-size:12px}
.footer{margin-top:8px;color:#99a4c2;font-size:12px}
@media(max-width:900px){.container{grid-template-columns:1fr}}
</style>
</head>
<body>
  <div class="nav">
    <div class="brand">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M4 12a8 8 0 1116 0 8 8 0 01-16 0Zm3.5 0h9M12 7v10" stroke="#69e6a6" stroke-width="2" stroke-linecap="round"/></svg>
      <b>PayFlow</b>
      <span class="footer" style="margin-left:10px">Secure wallet & payments (Demo)</span>
    </div>
    <div class="user">
      Logged in as <b><?=htmlspecialchars($user)?></b> &nbsp;•&nbsp; <a href="?logout=1" style="color:#69e6a6;text-decoration:none">Logout</a>
    </div>
  </div>

  <div class="container">
    <div class="card">
      <h2>Wallet</h2>
      <div class="balance">$<?=number_format($me['balance'],2)?></div>
      <div class="actions">
        <button class="btn" onclick="location.href='transfer.php'">Send Money</button>
        <button class="btn secondary" onclick="document.getElementById('topup').showModal()">Add Funds</button>
      </div>
      <p class="footer">Tip: Use “Send Money” to transfer to <i>username</i> or <i>email</i> of another registered user.</p>
    </div>

    <div class="card">
      <h2>Recent Transactions</h2>
      <div class="txs">
        <?php
          $txs = array_reverse($me['transactions']);
          if (count($txs)===0){
            echo '<div class="tx"><div class="note">No transactions yet.</div><div class="meta"></div></div>';
          } else {
            foreach(array_slice($txs, 0, 12) as $t){
              $sign = $t['type']==='debit' ? '-' : '+';
              $clr  = $t['type']==='debit' ? '#ff9a9a' : '#69e6a6';
              $time = date('Y-m-d H:i', $t['time']);
              echo '<div class="tx">
                      <div>
                        <div class="note">'.htmlspecialchars($t['note']).'</div>
                        <div class="meta">'.$time.' • '.htmlspecialchars($t['counterparty']).'</div>
                      </div>
                      <div style="font-weight:700;color:'.$clr.'">'.$sign.'$'.number_format($t['amount'],2).'</div>
                    </div>';
            }
          }
        ?>
      </div>
    </div>
  </div>

  <dialog id="topup" style="border:0;border-radius:16px;max-width:420px;width:90%;background:#121a33;color:#eaf0ff;padding:18px;border:1px solid rgba(255,255,255,.12)">
    <form method="post">
      <h3 style="margin:0 0 10px">Add Funds (Demo)</h3>
      <label>Amount (USD)</label>
      <input type="number" name="amount" step="0.01" min="1" placeholder="e.g., 25.00" required>
      <div style="display:flex;gap:10px;margin-top:12px">
        <button class="btn secondary" type="submit" name="addFunds" value="1">Add</button>
        <button class="btn danger" type="button" onclick="document.getElementById('topup').close()">Cancel</button>
      </div>
      <p class="footer">Dummy payment methods used for demo (no real charge).</p>
    </form>
  </dialog>

<script>
// purely cosmetic JS could go here if needed
</script>
</body>
</html>
