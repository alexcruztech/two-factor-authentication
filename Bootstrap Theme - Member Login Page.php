<?php
/**
 * This widget follows BD code standards
 * Widget Name: Bootstrap Theme - Member Login Page
 * Short Description: Widget that checks if the credentials to login are correct
 */

/// Setting to protect the login page from being included in an Iframe
if ($w['protect_login_from_iframe'] == 1){

    header('Content-Type: text/html; charset=utf-8');
    header("Content-Security-Policy: frame-ancestors 'self'");
    header("X-Frame-Options: DENY");
    if (stristr($_SERVER['HTTP_USER_AGENT'],"Go-http-client")) {
        /// Check for x-bypass https://github.com/niutech/x-frame-bypass
        echo "Blocked this type of request. Please try from a real browser.";
        exit;
    }
}

global $loginModalCounter;

$loginModalCounter++;

if ($_POST['action'] != "") {
    $json['result'] = "error";
    $json['message'] = $label["enter_valid_parameters"];

    if ($_POST['action'] == "login") {
        $json['message'] = $label["member_incorrect_id_pw"]." <br /> ".$label["member_login_try_again"];
        $_POST['email'] = stripslashes($_POST['email']);
        /// Nothing was entered or the email address is invalid.
      if (form_controller::validateRecaptcha($_POST['formname']) === false) {
       $json['message'] = $label['form_error_recaptcha'];
        echo json_encode($json);
            exit;

      } else if (trim($_POST['pass']) == "" || filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL) === false) {
            echo json_encode($json);
            exit;
      }

        $val = mysql(brilliantDirectories::getDatabaseConfiguration('database'), 'SELECT
                1
            FROM
                `password_retrieval_sessions`
            LIMIT
                1');

        if ($val !== FALSE) {
            //DO SOMETHING! IT EXISTS!
            $passwordVar = brilliantDirectories::encryptPassword(trim($_POST['pass']));

        } else {
            $passwordVar = trim($_POST['pass']);
        }

        $lresults = mysql(brilliantDirectories::getDatabaseConfiguration('database'), "SELECT
                *
            FROM
                `users_data`
            WHERE
                `email`='" . mysql_real_escape_string(trim($_POST['email'])) . "'
            AND
                `password` = '" . mysql_real_escape_string($passwordVar) . "'
            ORDER BY
                `user_id` DESC
            LIMIT
                1");

        while ($user = mysql_fetch_assoc($lresults)) {

            if ($w['auto_suspend_accounts'] == 1) {
                user::runOverDue($user['user_id']);
            }

            if ($user['active'] == 3) {
                $json['message'] = $label['member_account_not_active_message'];

            } else {

// two factor authentication
// generate code
$code = '';
$characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
$length = strlen($characters);

for ($i = 0; $i < 6; $i++) {
    $index = mt_rand(0, $length - 1);
    $code .= $characters[$index];
}

// generate token
$token = hash(md5,rand(1,100000000));

// unused codes will be automatically disabled
mysql($w['database'], "UPDATE two_factor_authentication SET used = 1 WHERE user_id = " . $user['user_id']);

// save code
$insert = mysql($w['database'], "INSERT INTO two_factor_authentication (user_id, code, token) VALUES (" . $user['user_id'] . ", '". $code . "', '" . $token . "')");

// set email with the code
$w['user_code'] = $code;
$email_template = "two-factor-authentication";
$email = prepareEmail($email_template, $w);
$emailSender = $w['website_email'];

sendEmailTemplate($emailSender, $user['email'], $email['subject'], $email['html'], $email['text'], $email['priority'], $w, $email);

// engine responses
$json['result'] = "success";
$json['message'] = $label["member_successful_login"] . $_REQUEST['loc'];
$json['redirect_url'] = "/login/verification?token=" . $token;

echo json_encode($json);
exit;


                setcookie("token", $user['token'], time() + 3600000, "/");
                setcookie("useractive", $user['active'], time() + 3600000, "/");
                setcookie("userid", $user['user_id'], time() + 3600000, "/");
                setcookie("subscription_id", $user['subscription_id'], time() + 3600000, "/");
                setcookie("profession_id", $user['profession_id'], time() + 3600000, "/");
                setcookie("location_value", $user['location'], time() + 3600000, "/");
                setcookie("loggedin", "1", time() + 3600000, "/");
                $subscription = getSubscription($user['subscription_id'], $w);
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
                $json['result'] = "success";
                $json['message'] = $label["member_successful_login"] . $_REQUEST['loc'];
                if ($_REQUEST['loc'] != "") {
                    $json['redirect_url'] = $_REQUEST['loc'];

                } else if (isset($_POST['login_direct_url']) && !empty($_POST['login_direct_url'])) {
                    $json['redirect_url'] = $_POST['login_direct_url'];
                } else if ($subscription['login_redirect'] != "") {
                    $json['redirect_url'] = $subscription['login_redirect'];

                } else {
                    $json['redirect_url'] = "/account/home";
                }
            }
        }

    } else if ($_POST['action'] == "retrievepassword" && !filter_var($_POST['email_r'], FILTER_VALIDATE_EMAIL) === false) {
        $json['result'] = "error";
        $json['message'] = $label["member_email_invalid"];

        $key = $w['recaptcha_secret_key'];

        if (!empty($key) && isset($_POST["g-recaptcha-response"]) && reCaptchaCheck($_POST['g-recaptcha-response'], $w) !== true) {
            $json['message'] = $label['form_security_unsuccessful'];
            $json['result'] = "error";
        } else {
            $lresults = mysql(brilliantDirectories::getDatabaseConfiguration('database'), "SELECT
          `user_id`
        FROM
          `users_data`
        WHERE
          `email` = '" . mysql_real_escape_string(trim(strip_tags($_POST['email_r']))) . "'
        AND
          `active` != '3'
        ORDER BY
          `active` DESC
        LIMIT
          1");

            while ($user = mysql_fetch_assoc($lresults)) {
                $currentUser = getUser($user['user_id'], $w);
                $currentSubscription = getSubscription($currentUser['subscription_id'], $w);
                if (empty($currentSubscription) || $currentSubscription['profile_type'] != 'claim') {

                    $val = mysql(brilliantDirectories::getDatabaseConfiguration('database'), 'SELECT
            1
          FROM
            `password_retrieval_sessions`
          LIMIT
            1');

                    if ($val !== FALSE) {
                        //DO SOMETHING! IT EXISTS!
                        //check if there is already a session created for this member
                        $checkSessionQuery = mysql(brilliantDirectories::getDatabaseConfiguration('database'), "SELECT
              *
            FROM
              `password_retrieval_sessions`
            WHERE
              user_token = '" . $currentUser['token'] . "'");

                        if (mysql_num_rows($checkSessionQuery) > 0) {
                            mysql(brilliantDirectories::getDatabaseConfiguration('database'), "DELETE FROM
                `password_retrieval_sessions`
              WHERE
                user_token = '" . $currentUser['token'] . "'");
                        }
                        //create the password retrieval session for this member
                        $passwordRetrieveHash = hash("md5", $currentUser['token'] . $currentUser['email'] . rand(0, 1000));
                        //log this sesssion
                        mysql(brilliantDirectories::getDatabaseConfiguration('database'), "INSERT INTO
              `password_retrieval_sessions`
            SET
              user_token = '" . $currentUser['token'] . "',
              retrieval_session_token = '" . $passwordRetrieveHash . "'");
                        $w['password_retrieve_hash'] = $passwordRetrieveHash;
                        $json['message'] = $label["member_pw_reset"];

                        if ($_ENV['https_on'] == 1) {
                            $w['whttp'] = "https";

                        } else {
                            $w['whttp'] = "http";
                        }
                        $w['protocol_website_url'] = brilliantDirectories::getWebsiteUrl();
                        $w = array_merge($w, $currentUser);
                        $email = prepareEmail('member-password-retrieval', $w);  /// Send Welcome email based on account type
                        sendEmailTemplate($email['sender'], $currentUser['email'], $email['subject'], $email['html'], $email['text'], $email['priority'], $w);   //// Send Email
                        $json['result'] = "success";

                    } else {
                        $json['message'] = $label["member_pw_reset"];
                        $w = array_merge($w, $currentUser);
                        $email = prepareEmail('password-retrieval', $w);  /// Send Welcome email based on account type
                        sendEmailTemplate($email['sender'], $currentUser['email'], $email['subject'], $email['html'], $email['text'], $email['priority'], $w);   //// Send Email
                        $json['result'] = "success";
                    }
                }
            }
        }
    } else if ($_POST['action'] == "check_email_duplication" && $_POST['sent_email'] != "") {
        //check the amount of members with the given email
        $checkEmailQuery = mysql(brilliantDirectories::getDatabaseConfiguration('database'), "SELECT
                user_id
            FROM
                `users_data`
            WHERE
                email = '" . $_POST['sent_email'] . "'");

        if (mysql_num_rows($checkEmailQuery) <= 1) {
            $json['status'] = "success";

        } else {
            $json['status'] = "error";
            $json['title'] = "Error";
            $json['message'] = $label["member_duplicate_email"];
        }
    }
    echo json_encode($json);
    exit;
}
if(!isset($_COOKIE['userid']) || $_COOKIE['userid'] <= 0){

  /// Code added to update the URL to the correct login URL from emailsss
  if ($pars[1] == "token" && $pars[3] != "" && $_GET['login_direct_url'] == "") {
    $_GET['login_direct_url'] = "account";
    if (isset($_GET['sized'])) { unset($_GET['sized']); }
    for ($i=3;$i<count($pars);$i++) {
      $_GET['login_direct_url'] .= "/".$pars[$i];
    }
    Header("Location: /".$pars[0]."?".http_build_query($_GET));
    exit;
  }

    $_SERVER['form_element_id'] = "myform"; //// Sets the name of the form id element in the code and javascript
    ?>
    <style>
        .member-login-container:not(.modal .module) {
            width: 555px;
            max-width: 100%;
            margin-left: auto;
            margin-right: auto;
            margin-bottom:0;
            background-color: <?php echo $wa['custom_71']?>!important;
            border-color: <?php echo $wa['custom_72']?>!important;
            color: <?php echo $wa['custom_73']?>!important;
        }
        .login-register-tabs, .login-register-content {
            width: 555px!important;
            max-width: 100%;
            margin-left: auto!important;
            margin-right: auto!important;
            background-color: <?php echo $wa['custom_72']?>;
        }
        .login-register-content {
            background: transparent;
            padding: 0;
            border: none;
        }
        .login-register-content h2, .login-register-content h2+hr, .login-register-content .account-menu-title {
            display: none!important;
        }
        .express_login_create_account_prefix hr {
            margin: 15px 0 10px;
        }
        .modal-content #containerFBLogin, .modal-content #containerGoogleLogin {
            margin:15px 0;
        }

        /* CSS When Login Form and Express Registration Rendered in Sidebar */
        .col-md-3 .bd-chat-well-container,.col-md-4 .bd-chat-well-container {
            padding: 15px 10px;
        }
        .col-md-3 .bd-chat-center-text,.col-md-4 .bd-chat-center-text {
            margin: 0;
            font-size: 20px;
            padding: 0 15px;
        }
        .col-md-3 .member-login-page-container .login-register-tabs *, .col-md-4 .member-login-page-container .login-register-tabs * {
            font-size: 12px;
            line-height: 1.2em;
            vertical-align: bottom;
        }
        .col-md-3 .member-login-page-container .login-register-tabs a, .col-md-4 .member-login-page-container .login-register-tabs a {
            padding: 5px !important;
            height: 50px;
            vertical-align: middle;
            display: table-cell !important;
            width: 1%;
        }
        .col-md-3 .member-login-page-container .login-register-content, .col-md-4  .member-login-page-container .login-register-content {
            padding: 0;
        }
        .col-md-3 .member-login-container, .col-md-4 .member-login-container {
            padding: 15px !important;
            font-size: 13px;
        }
        .col-md-3 .member-login-page-container .input-lg, .col-md-4 .member-login-page-container .input-lg {
            height: 34px;
            padding: 6px 12px;
            font-size: 14px;
        }
        .col-md-3 .member-login-page-container .security_question_label, .col-md-4 .member-login-page-container .security_question_label {
            transform: scale(.85);
            margin: -1.15em -1.15em 0;
        }
        .col-md-3 #containerFBLogin, .col-md-4 #containerFBLogin, .col-md-3 #containerGoogleLogin, .col-md-4 #containerGoogleLogin, .col-md-3 .login-cta-buttons li, .col-md-4 .login-cta-buttons li {
            width: 100%;
            display: block;
            margin-top:5px;
        }
        .col-md-3 .login-cta-buttons li, .col-md-4 .login-cta-buttons li {
            padding:0
        }
        .col-md-3 .login-cta-buttons ul.nav, .col-md-4 .login-cta-buttons ul.nav {
            margin-top: -10px;
        }
        .col-md-3 #googleAction, .col-md-3 #facebookAction, .col-md-4 #googleAction, .col-md-4 #facebookAction {
            padding: 0;
            min-height: 0;
            font-size: 14px;
            margin: 0;
        }
        .col-md-3 #googleAction img, .col-md-3 #facebookAction img, .col-md-4 #googleAction img, .col-md-4 #facebookAction img {
            height: 36px !important;
            margin-right: 5px;
            position: relative!important;
            display: inline-block;
        }
        @media only screen and (max-width: 767px) {
            .col-md-3 .member-login-page-container .login-register-tabs a, .col-md-4 .member-login-page-container .login-register-tabs a {
                display: block !important;
                width: 100%;
                line-height: 40px;
            }

            #containerGoogleLogin {
                text-align: center;
                margin-top: 10px;
            }

            #containerGoogleLogin #gBtn {
                display: inline-block;
                width: 202px;

            }
            #containerFBLogin .btn-facebook {
                box-sizing: border-box;
                width: 191px;
                font-size: 14px;
                position: relative;
                left: -4px;
                padding-left: 44px;
                white-space: nowrap !important;

            }
        }
    </style>
    <div class="row member-login-page-container">
        <div class="fpad-lg novpad">

            <?php
            $platform = 'http://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];

            if (false !== strpos($platform, 'members_only')) { ?>
                <div class="alert alert-warning alert-dismissible" role="alert">
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h3 class="nomargin text-center">
                        <i class="fa fa-exclamation-circle"></i>
                        <?php echo $label['member_only_page'];?>
                    </h3>
                </div>
            <?php }
            if ($pars[1] == "retrieval") { ?>
                <div class="module fpad-xl member-login-container password-retrieval-container">
                    <?php echo form("password_retrieval", "", $w['website_id'], $w); ?>
                </div>

            <?php } else { ?>

                <?php
                $metaWhere = array(
                    array('value' => 'disallow_signups' , 'column' => 'key', 'logic' => '='),
                    array('value' => '1' , 'column' => 'value', 'logic' => '='),
                    array('value' => 'subscription_types' , 'column' => 'database', 'logic' => '=')
                );

                $disallowSignUpsMeta = bd_controller::users_meta()->get($metaWhere);
                if($disallowSignUpsMeta !== false){
                    $disallowSignUpsMeta = (!is_array($disallowSignUpsMeta) && $disallowSignUpsMeta !== false)?array($disallowSignUpsMeta):$disallowSignUpsMeta;
                    foreach($disallowSignUpsMeta as $disallowSignUp) {
                        $disallowIdsArray[] = $disallowSignUp->database_id;
                    }
                }
                $disabledSignUps = false;
                $freeMembershipPlanCheck = bd_controller::subscription_types()->get($w['express_signup_membership_plan'], 'subscription_id');
                if(in_array($freeMembershipPlanCheck->subscription_id, $disallowIdsArray)){
                    $disabledSignUps = true;
                }
                $flagRegistrationTabActive = false;
                if ($w['express_signup_registration_tab_selected'] == 1) {
                    $flagRegistrationTabActive = true;
                }

                if ($freeMembershipPlanCheck !== false && !$disabledSignUps && !is_array($freeMembershipPlanCheck) && $freeMembershipPlanCheck->profile_type == 'free' && ($w['https_redirect'] ==1 && $w['website_url'] == $w['secure_url'])){ ?>
                    <style>.member-login-container{border-top:none;}</style>
                    <ul role="tablist" aria-label="Login Tabs" class="nav nav-tabs fpad nobpad nav-justified bold font-lg login-register-tabs">
                        <li class="<?php if(!$flagRegistrationTabActive){echo 'active';}?>" role="presentation">
                            <a href="#login-tab-<?php echo $loginModalCounter;?>" rel="nofollow" aria-controls="t1" role="tab" data-toggle="tab" aria-label="<?php echo $label['member_login_title'];?>" aria-selected="false">
                                <?php echo $label['member_login_title'];?>
                            </a>
                        </li>
                        <li class="<?php if($flagRegistrationTabActive){echo 'active';}?>" role="presentation">
                            <a class="no-radius-bottom" href="#register-tab-<?php echo $loginModalCounter;?>" rel=" nofollow" aria-controls="t2" role="tab" data-toggle="tab" aria-label="<?php echo $label['express_login_register_new_account'];?>" aria-selected="false">
                                <?php echo $label['express_login_register_new_account'];?>
                            </a>
                        </li>
                    </ul>
                    <div class="tab-content login-register-content nomargin">
                        <div role="tabpanel" class="login-tab tab-pane <?php if(!$flagRegistrationTabActive){echo 'active';}?>" id="login-tab-<?php echo $loginModalCounter;?>">
                            <div class="module fpad-xl member-login-container">
                                <?php echo form("member_login", "", $w['website_id'], $w); ?>
                            </div>
                            <div class="clearfix"></div>
                        </div>
                        <div role="tabpanel" class="register-tab tab-pane <?php if($flagRegistrationTabActive){echo 'active';}?>" id="register-tab-<?php echo $loginModalCounter;?>">
                            <div class="module fpad-xl member-login-container">
                                <div class="express_login_create_account_prefix">
                                    <?php echo $label['express_login_create_account_prefix'];?>
                                    <div class="clearfix"></div>
                                </div>
                                <?php
                                $_GET['subaction']="createaccount";
                                $_GET['subscription_id']= $w['express_signup_membership_plan'];
                                $_GET['sid']= $w['express_signup_membership_plan'];
                                $_GET['signup_date']=date("YmdHis");
                                $_GET['active']="1";
                                $w['form_url']="/checkout/".$w['express_signup_membership_plan'];
                                echo form("signup_free", "", $w['website_id'], $w, "WHMCS2 - Bootstrap Theme - Function - Form"); ?>
                                <div class="clearfix"></div>
                                <?php
                                echo $label['express_login_create_account_suffix'];
                                unset($_GET['subaction'], $_GET['subscription_id'], $_GET['sid'], $_GET['signup_date'], $_GET['active']);
                                ?>
                            </div>
                            <div class="clearfix"></div>
                        </div>
                    </div>
                <?php } else { ?>
                    <div class="module fpad-xl member-login-container">
                        <?php echo form("member_login", "", $w['website_id'], $w); ?>
                        <div class="clearfix"></div>
                    </div>
                <?php } ?>

            <?php } ?>

        </div>
    </div>
<?php }else{
    $user_data = getUser($_COOKIE['userid'], $w);
    echo widget("Bootstrap Theme - Sign Up Page - Already Logged In Message");
} ?>