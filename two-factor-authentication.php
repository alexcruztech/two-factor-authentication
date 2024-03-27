<?php
// Engine
$action = $_POST['action'];

if ($action == "login") {
  $code = $_POST['code'];
  $token = $_POST['token'];
  $query = mysql($w['database'], "SELECT * FROM two_factor_authentication WHERE code = '" . $code . "' AND token = '" . $token . "' AND used = 0");

  if (mysql_num_rows($query)) {
    $result = mysql_fetch_assoc($query);
    $user = getUser($result['user_id'], $w);
    $subscription = getSubscription($user['subscription_id'], $w);

    $json['result'] = true;
    mysql($w['database'], "UPDATE two_factor_authentication SET used = 1 WHERE token = '" . $token . "'");

    setcookie("token", $user['token'], time() + 3600000, "/");
    setcookie("useractive", $user['active'], time() + 3600000, "/");
    setcookie("userid", $user['user_id'], time() + 3600000, "/");
    setcookie("subscription_id", $user['subscription_id'], time() + 3600000, "/");
    setcookie("profession_id", $user['profession_id'], time() + 3600000, "/");
    setcookie("location_value", $user['location'], time() + 3600000, "/");
    setcookie("loggedin", "1", time() + 3600000, "/");

    $_COOKIE['token'] = $user['token'];
    $_COOKIE['useractive'] = $user['active'];
    $_COOKIE['userid'] = $user['userid'];
    $_COOKIE['subscription_id'] = $user['subscription_id'];
    $_COOKIE['profession_id'] = $user['profession_id'];
    $_COOKIE['location_value'] = $user['location'];
    $_COOKIE['loggedin'] = 1;

    mysql($w['database'], "UPDATE
            `users_data`
        SET
            `last_login` = '" . $w['date'] . "'
        WHERE
            `user_id` = '" . $user['user_id'] . "'");

    logUserActivity($user['user_id'], "Log In", $w);

    $ip = '';

    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }

    $lastLoginIP = array('last_login_ip' => $ip);
    storeMetaData("users_data", $user['user_id'], $lastLoginIP, $w);

  } else {
    $json['result'] = false;
  }

  echo json_encode($json);
  exit;
}

// Verification Code
$token = $_GET['token'];

if (!$token) {
  $redirectUrl = "/login";
  header("Location: " . $redirectUrl);
  exit;
}

$query = mysql($w['database'], "SELECT * FROM two_factor_authentication WHERE used = 0 AND token = '" . $token . "'");

if (mysql_num_rows($query)) {
  $result = mysql_fetch_assoc($query);
  $user = getUser($result['user_id'], $w);
} else {
  $redirectUrl = "/login";
  header("Location: " . $redirectUrl);
  exit;
}
?>

<style>
.code-wrapper {
  display: flex;
  flex-direction: column;
  align-items: center;
}
.code-wrapper label {
  font-size: 20px;
  font-weight: 400;
}
.code-wrapper input {
  height: 60px;
  margin: 15px;
  border: 1px solid black;
  border-radius: 10px;
  font-size: 48px;
  width: 250px;
  text-align: center;
  letter-spacing: 5px;
  font-weight: 100;
}
.validation {
  background: #fcde6d;
  padding: 10px;
  text-align: center;
  font-size: 18px;
  margin: 30px 0;
  border-radius: 8px;
}
@media (max-width: 768px) {
  .code-wrapper input {
    font-size: 30px;
  }
}
</style>

<div>
  <div class="row member-login-page-container">
    <div class="fpad-lg novpad">
      <div class="module fpad-xl member-login-container">

        <h2 style="text-align: center;font-weight: 600;">Check your email</h2>
        <hr>

        <div>
          <form action="" id="validation">
            <div class="code-wrapper">
              <label for="">Your login code</label>
              <input name="user_code" type="text" placeholder="000000" minlength="6" maxlength="6" required>
              <input type="hidden" name="token" value="<?php echo $_GET['token']; ?>">
            </div>
            <div>
              <div class="validation hidden">Not a valid code. Sure you typed it correctly?</div>
            </div>
            <div>
              <p class="text-center">We've sent a 6 digit login code to <strong><?php echo $user['email'];?></strong> Can't find it? Check your spam folder.</p>
            </div>
            <div style="margin: 20px 0;">
              <button type="submit" class="btn btn-primary btn-lg btn-block">Login</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script>

$('#validation').submit(function(e) {
  e.preventDefault();
  const action = "login";
  const token = $("input[name='token']").val();
  const code = $("input[name='user_code']").val().toUpperCase();

  $.post('/api/data/html/get/data_widgets/widget_name?name=two-factor-authentication',{ action, code, token },function(data){

    if (!data['result']) {
      $('.validation').removeClass('hidden');
    } else {
      window.location.href = "/account/home";
    }
  }, "json");
});

</script>