<?php

/**
 * Adaptive captcha guard for every outgoing auth email.
 * Returns null when the request may send the email, otherwise the guard result.
 */
function apMailGuard(string $action, string $email): ?array {
    $guard = turnstile_guard($action, $email);
    return $guard['pass'] ? null : $guard;
}

function apMailGuardMessage(array $guard): string {
    global $translations;
    $retryAfter = (int)($guard['retry_after'] ?? 0);
    switch ((string)($guard['reason'] ?? '')) {
        case 'too_fast':
            return $translations['captcha_too_fast'] ?? 'Form submitted too quickly. Please try again.';
        case 'rate_limited':
            return sprintf($translations['captcha_rate_limited'] ?? 'Too many requests. Try again in %d seconds.', $retryAfter);
        case 'email_cooldown':
            return sprintf($translations['captcha_email_cooldown'] ?? 'Please wait %d seconds before requesting another email.', $retryAfter);
        case 'captcha_required':
            return $translations['captcha_required'] ?? 'Please complete the security check.';
        default:
            return $translations['captcha_failed'] ?? 'Security check failed. Please try again.';
    }
}

function apMailGuardReport(array $guard, string $email): void {
    $ip       = getClientIp();
    $lockType = turnstile_lock_type((string)($guard['action'] ?? 'register'));
    $locked   = getIpLockoutSeconds($ip, $lockType) > 0;
    if (!$locked) {
        recordFailedAttempt($ip, $lockType);
    }
    notifyTelegramFailedAuth(
        $lockType === 'reset_email' ? 'reset_email_abuse' : 'register_abuse',
        array_merge(failedAuthNotifyPayload($ip, $lockType), [
            'already_locked' => $locked,
            'attempted_email' => $email,
            'guard_reason'    => (string)($guard['reason'] ?? ''),
        ])
    );
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $sessionIsAdmin = isset($_SESSION['user']['role']) && (int)$_SESSION['user']['role'] === 2;
    apSecRequireCsrf();
    if ($sessionIsAdmin || isset($_SESSION['user']['email'])) {
        apSecRequireSameOrigin();
    } elseif (isset($_POST['ajax']) || isset($_POST['login']) || isset($_POST['register_initial']) || isset($_POST['register_complete']) || isset($_POST['resend_verification']) || isset($_POST['forgot_password']) || isset($_POST['reset_password']) || isset($_POST['verify_otp_pin']) || isset($_POST['generate_otp_pin']) || isset($_POST['verify_otp'])) {
        if ((string)($_SERVER['HTTP_ORIGIN'] ?? '') !== '' || (string)($_SERVER['HTTP_REFERER'] ?? '') !== '') {
            apSecRequireSameOrigin();
        }
    }

    // Separate admin-panel gate (password → dedicated cookie token)
    if (isset($_POST['admin_panel_status']) && isset($_POST['ajax'])) {
        $email = (string)($_SESSION['user']['email'] ?? '');
        $ok = $sessionIsAdmin && adminPanelGateValid($email);
        while (ob_get_level() > 1) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'is_admin' => $sessionIsAdmin,
            'authenticated' => $ok,
            'email' => $sessionIsAdmin ? $email : '',
            'expires_at' => $ok ? (int)($_SESSION['admin_panel_gate_until'] ?? 0) : 0,
            'csrf' => apSecCsrfToken(),
        ]);
        exit;
    }
    if (isset($_POST['admin_panel_logout']) && isset($_POST['ajax'])) {
        $email = (string)($_SESSION['user']['email'] ?? '');
        adminPanelClearGate($email !== '' ? $email : null);
        adminAuditLog('admin_gate_logout', $email, 'ok');
        while (ob_get_level() > 1) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
    if (isset($_POST['admin_panel_login']) && isset($_POST['ajax'])) {
        while (ob_get_level() > 1) ob_end_clean();
        header('Content-Type: application/json');
        if (!$sessionIsAdmin) {
            echo json_encode(['success' => false, 'error' => 'not_admin']);
            exit;
        }
        if (!adminIpAllowed()) {
            echo json_encode(['success' => false, 'error' => 'admin_ip_forbidden']);
            exit;
        }
        $email = (string)($_SESSION['user']['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $clientIp = getClientIp();
        $locked = getIpLockoutSeconds($clientIp, 'admin_gate');
        if ($locked > 0) {
            echo json_encode(['success' => false, 'error' => 'rate_limited', 'retry_after' => $locked]);
            exit;
        }
        $users = loadUsers();
        $ok = $email !== '' && isset($users[$email]) && appPasswordVerify($password, (string)$users[$email]['password']);
        if (!$ok) {
            recordFailedAttempt($clientIp, 'admin_gate');
            adminAuditLog('admin_gate_login', $email, 'denied');
            echo json_encode(['success' => false, 'error' => 'invalid_credentials']);
            exit;
        }
        resetFailedAttempts($clientIp, 'admin_gate');
        $issued = adminPanelIssueGate($email);
        adminAuditLog('admin_gate_login', $email, 'ok');
        echo json_encode([
            'success' => true,
            'expires_at' => $issued['expires_at'],
            'csrf' => apSecCsrfToken(),
        ]);
        exit;
    }

    if ($sessionIsAdmin && adminPanelIsAdminRequest()
        && !isset($_POST['admin_panel_login'])
        && !isset($_POST['admin_panel_status'])
        && !isset($_POST['admin_panel_logout'])) {
        adminPanelRequireGate();
    }
    if(isset($_POST['resend_verification'])){
        $email=apNormalizeAuthEmail($_POST['email']??'');
        $users=loadUsers();
        $pendingUsers=loadPendingUsers();
        $needsVerification=(isset($users[$email])&&empty($users[$email]['verified'])&&$email!=='root@root')
            ||(isset($pendingUsers[$email])&&$email!=='root@root');
        if($needsVerification){
            $mailGuard=apMailGuard('register',$email);
            if($mailGuard!==null){
                $authError=apMailGuardMessage($mailGuard);
                $showReVerifyForm=true;
                $reVerifyEmail=$email;
                apMailGuardReport($mailGuard,$email);
            }else{
            $verificationToken=generateVerificationToken();
            saveVerificationTokenForEmail($email, $verificationToken, time() + 86400);
            if(sendVerificationEmail($email,$verificationToken)){
                $authSuccess=$translations['verification_link_sent'];
                $showVerificationSent=true;
                $verificationEmail=$email;
            }else{
                $authError='Failed to send email. Check SMTP configuration.';
                $showReVerifyForm=true;
                $reVerifyEmail=$email;
            }
            }
        }else{
            $authError=$translations['invalid_credentials'];
        }
    }
        // ── OTP: Generate (AJAX, authenticated user) ────────────────────
    if(isset($_POST['generate_otp'])&&isset($_POST['ajax'])){
        if(!isset($_SESSION['user']['email'])){
            while(ob_get_level()>1)ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['success'=>false,'error'=>'Not authenticated']);
            exit;
        }
        if(($_SESSION['user']['email']??'')==='root@root'){
            while(ob_get_level()>1)ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['success'=>false,'error'=>'otp_forbidden']);
            exit;
        }
        $ttl=(int)($_POST['ttl_seconds']??300);
        if(!in_array($ttl,[60,180,300,600]))$ttl=300;
        $pin=createOTP($_SESSION['user']['email'],$ttl);
        while(ob_get_level()>1)ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success'=>true,'pin'=>$pin,'expires_in'=>$ttl]);
        exit;
    }

        // ── OTP: Verify (POST to /one-time-pin) ───────────────────────────────────
    if(isset($_POST['verify_otp'])){
        $clientIp=getClientIp();
        $lockedSec=getIpLockoutSeconds($clientIp,'otp');
        if($lockedSec>0){
            $otpError=sprintf($translations['otp_ip_locked']??'Too many attempts. Try in %d sec.',$lockedSec);
            notifyTelegramFailedAuth('otp_pin', array_merge(failedAuthNotifyPayload($clientIp, 'otp'), [
                'already_locked' => true,
            ]));
        }else{
            $pin=trim($_POST['otp_pin']??'');
            $otpEmail=verifyAndConsumeOTP($pin);
            if($otpEmail){
                $users=loadUsers();
                if(isset($users[$otpEmail])&&!empty($users[$otpEmail]['verified'])){
                    if ($otpEmail === 'root@root') {
                        recordFailedAttempt($clientIp,'otp');
                        $otpError=$translations['admin_otp_forbidden'] ?? 'One-time PIN login is disabled for administrators.';
                    } else {
                    resetFailedAttempts($clientIp,'otp');
                    clearTrustedDeviceLogoutSuppression();
                    adminEstablishSession($otpEmail, $users[$otpEmail]);
                    $config=['language'=>$users[$otpEmail]['language']??'en','theme'=>$users[$otpEmail]['theme']??'light','color'=>$users[$otpEmail]['color']??'#007bff'];
                    $language=$config['language'];
                    $translations = ap_merge_i18n($defaultTranslations, $language);
                    scheduleTrustedDevicePromptIfNeeded($otpEmail);
                    header('Location: ' . takePostLoginRedirect('/main'));
                    exit;
                    }
                }else{
                    recordFailedAttempt($clientIp,'otp');
                    $otpError=$translations['invalid_credentials']??'Invalid credentials';
                }
            }else{
                recordFailedAttempt($clientIp,'otp');
                $lockout=getLockout($clientIp,'otp');
                $attemptsLeft=max(0,3-(int)$lockout['attempts']);
                if($attemptsLeft<=0){
                    $sec2=getIpLockoutSeconds($clientIp,'otp');
                    $otpError=sprintf($translations['otp_ip_locked']??'Too many attempts. Try in %d sec.',$sec2);
                }else{
                    $otpError=sprintf($translations['otp_invalid']??'Invalid PIN. %d attempts remaining.',$attemptsLeft);
                }
            }
        }
    }

    if(isset($_POST['login'])){
        $email=apNormalizeAuthEmail($_POST['email']??'');
        $password=$_POST['password']??'';
        $clientIp=getClientIp();
        $loginLockedSec=getIpLockoutSeconds($clientIp,'login');
        if($loginLockedSec>0){
            $authError=sprintf($translations['login_ip_locked']??'Too many failed attempts. Try again in %d seconds.',$loginLockedSec);
        }else{
            $users=loadUsers();
            if(isset($users[$email])&&userNeedsPasswordSet($users[$email])&&!empty($users[$email]['verified'])){
                $_SESSION['pending_oauth_data']=[
                    'email'=>$email,
                    'name'=>$users[$email]['name']??'',
                    'photo'=>$users[$email]['photo']??'default_avatar.png',
                    'provider'=>'email',
                    'reg_method'=>'Email',
                ];
                $_SESSION['pending_set_password_only']=true;
                header('Location: /register?step=set_password&password_required=1');
                exit;
            }
            if(isset($users[$email])&&appPasswordVerify($password, $users[$email]['password'])){
                if (appPasswordNeedsRehash((string)$users[$email]['password'])) {
                    $users[$email]['password'] = appPasswordHash($password);
                    upsertUser($email, $users[$email]);
                }
                if($email!=='root@root'&&empty($users[$email]['verified'])){
                    $authError=$translations['email_not_verified'];
                    $showReVerifyForm=true;
                    $reVerifyEmail=$email;
                }else{
                    resetFailedAttempts($clientIp,'login');
                    clearTrustedDeviceLogoutSuppression();
                    if($email==='root@root'){$users[$email]['role']=2;$users[$email]['verified']=true;upsertUser('root@root',$users[$email]);}
                    if ((int)($users[$email]['role'] ?? 0) >= 2 && adminIpAllowlist() !== [] && !adminIpAllowed($clientIp)) {
                        $authError=$translations['admin_ip_forbidden'] ?? 'Administrator access is not allowed from this IP.';
                    } else {
                    adminEstablishSession($email, $users[$email]);
                    if (isset($_POST['remember_me']) && $_POST['remember_me'] !== '' && $email !== 'root@root') rememberCurrentTrustedDevice($email, $clientIp);
                    $config=['language'=>$users[$email]['language']??'en','theme'=>$users[$email]['theme']??'light','color'=>$users[$email]['color']??'#007bff'];
                    $language=$config['language'];
                    $translations = ap_merge_i18n($defaultTranslations, $language);
                    scheduleTrustedDevicePromptIfNeeded($email);
                    if (adminIsAdminUser($users[$email])) {
                        adminAuditLog('admin_login', $email, 'ok');
                    }
                    header('Location: ' . takePostLoginRedirect('/main'));
                    exit;
                    }
                }
            }else{
                recordFailedAttempt($clientIp,'login');
                $lockout=getLockout($clientIp,'login');
                $attemptsLeft=max(0,10-(int)$lockout['attempts']);
                if($attemptsLeft<=0){
                    $sec=max(1,(int)getIpLockoutSeconds($clientIp,'login'));
                    $authError=sprintf($translations['login_ip_locked']??'Too many failed attempts. Try again in %d seconds.',$sec);
                }else{
                    $authError=$translations['invalid_credentials'];
                }
            }
        }
    }
    
    if(isset($_POST['register_initial'])){
        $email=$_POST['email']??'';
        $name=$_POST['name']??'';
        $users=loadUsers();
        if(isset($users[$email])){
            // User exists but might be unverified (admin revoked verification)
            if(empty($users[$email]['verified'])&&$email!=='root@root'){
                $authError=$translations['email_not_verified'];
                $showReVerifyForm=true;
                $reVerifyEmail=$email;
            }else{
                $authError=$translations['user_exists'];
            }
        }else{
            $_SESSION['pending_oauth_data'] = [
                'email' => $email,
                'name' => $name ?: explode('@', $email)[0],
                'photo' => 'default_avatar.png',
                'provider' => 'email',
                'reg_method' => 'Email',
            ];
            unset($_SESSION['pending_google_data']);
            header('Location: /register?step=set_password');
            exit;
        }
    }
    
    if(isset($_POST['register_complete'])){
        $email=apNormalizeAuthEmail($_POST['email']??'');
        $password=$_POST['password']??'';
        $confirmPassword=$_POST['confirm_password']??'';
        $name=$_POST['name']??explode('@', $email)[0];
        $photo=$_POST['photo']??'default_avatar.png';
        $regMethod=$_POST['reg_method']??'Email';
        
        if($password !== $confirmPassword){
            $authError=$translations['password_mismatch'];
        }elseif(($passwordErrorKey = validatePasswordPolicy($password)) !== null){
            $authError=$translations[$passwordErrorKey];
        }else{
            $mailGuard=apMailGuard('register',$email);
            if($mailGuard!==null){
                $authError=apMailGuardMessage($mailGuard);
                $showCaptchaRetry=true;
                apMailGuardReport($mailGuard,$email);
            }else{
            $users=loadUsers();
            if(isset($users[$email])&&(userNeedsPasswordSet($users[$email])||!empty($users[$email]['must_change_password']))&&!empty($users[$email]['verified'])){
                if (adminIsAdminUser($users[$email]) && ($adminPwdErr = validateAdminPasswordPolicy($password)) !== null) {
                    $authError=$translations[$adminPwdErr] ?? $translations['password_too_short'];
                } else {
                $users[$email]['password']=appPasswordHash($password);
                $users[$email]['must_change_password']=0;
                upsertUser($email,$users[$email]);
                $clearPasswordRequirement=getDB()->prepare("UPDATE users SET password=:password,must_change_password=0 WHERE email=:email");
                $clearPasswordRequirement->bindValue(':password',$users[$email]['password']);
                $clearPasswordRequirement->bindValue(':email',$email);
                $clearPasswordRequirement->execute();
                unset($_SESSION['pending_oauth_data'], $_SESSION['pending_google_data'],$_SESSION['pending_set_password_only']);
                resetFailedAttempts(getClientIp(),'login');
                clearTrustedDeviceLogoutSuppression();
                adminEstablishSession($email, $users[$email]);
                scheduleTrustedDevicePromptIfNeeded($email);
                header('Location: '.takePostLoginRedirect('/main'));
                exit;
                }
            }elseif(isset($users[$email])){
                // If existing user is unverified, offer re-verification instead of error
                if(empty($users[$email]['verified'])&&$email!=='root@root'){
                    $authError=$translations['email_not_verified'];
                    $showReVerifyForm=true;
                    $reVerifyEmail=$email;
                }else{
                    $authError=$translations['user_exists'];
                }
            }else{
                $pendingUsers = loadPendingUsers();
                $alreadyPending = isset($pendingUsers[$email]);
                $pendingUsers[$email] = [
                    'password'   => appPasswordHash($password),
                    'name'       => $name,
                    'photo'      => $photo,
                    'created_at' => $pendingUsers[$email]['created_at'] ?? date('Y-m-d H:i:s'),
                    'role'       => 0,
                    'verified'   => false,
                    'language'   => 'en',
                    'theme'      => 'light',
                    'color'      => '#007bff',
                    'reg_method' => $regMethod
                ];
                savePendingUsers($pendingUsers);
                $existingToken = findLatestVerificationTokenForEmail($email);
                if ($alreadyPending && $existingToken) {
                    $verificationToken = (string)$existingToken['token'];
                } else {
                    $verificationToken = generateVerificationToken();
                    saveVerificationTokenForEmail($email, $verificationToken, time() + 86400);
                }
                
                if(sendVerificationEmail($email, $verificationToken)){
                    if (!$alreadyPending) {
                        notifyTelegramNewUser($email, $name, $photo, $regMethod);
                        try { apCreateNotification(getDB(), '', 'registration', 'info', 'New registration', $email . ' (' . $regMethod . ')', 'registration_pending', ['email' => $email, 'name' => $name, 'method' => $regMethod]); } catch (Throwable $e) {}
                    }
                    $authSuccess = $translations['verification_link_sent'];
                    $showVerificationMessage = true;
                    $verificationEmail = $email;
                    unset($_SESSION['pending_oauth_data'], $_SESSION['pending_google_data']);
                }else{
                    $authError = 'Failed to send verification email. Check SMTP configuration.';
                }
            }
            }
        }
    }
    
    if(isset($_POST['get_shared_files'])&&isset($_POST['ajax'])){
        while(ob_get_level()>1)ob_end_clean();
        header('Content-Type: application/json');
        if (!isset($_SESSION['user']['email'])) {
            echo json_encode(['success'=>false,'error'=>'Not authenticated','files'=>[]]);
            exit;
        }
        $db = getDB();
        if ($db) {
            try { $db->exec("DELETE FROM shared_files WHERE expires IS NOT NULL AND expires < " . time()); } catch (Exception $e) {}
        }
        $currentUserEmail = $_SESSION['user']['email'];
        $isAdmin = $sessionIsAdmin;
        if ($isAdmin) {
            $files = loadSharedFiles(null, true);
        } else {
            $files = loadSharedFiles($currentUserEmail);
        }
        echo json_encode(['success'=>true,'files'=>$files]);
        exit;
    }

    if(isset($_POST['delete_shared_file'])&&isset($_POST['ajax'])){
        $shareId = $_POST['share_id'] ?? '';
        $currentUserEmail = $_SESSION['user']['email'] ?? null;
        $isAdmin = $sessionIsAdmin;
        
        $response = ['success'=>false,'message'=>''];
        if (!$currentUserEmail) {
            $response['message'] = 'Not authenticated';
        } elseif($shareId){
                        // Check whether the user has the right to delete this file
            $file = getSharedFileById($shareId);
            if ($file && ($isAdmin || ($file['user_email'] === $currentUserEmail))) {
                if(deleteSharedFile($shareId)){
                    $response['success'] = true;
                    $response['message'] = $translations['share_deleted'];
                    $response['files'] = $isAdmin ? loadSharedFiles(null, true) : loadSharedFiles($currentUserEmail);
                }
            } else {
                $response['message'] = 'Access denied';
            }
        }
        while(ob_get_level() > 1) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    if(isset($_POST['update_shared_file_expiration'])&&isset($_POST['ajax'])){
        while(ob_get_level()>1)ob_end_clean();
        header('Content-Type: application/json');
        $shareId = trim((string)($_POST['share_id'] ?? ''));
        $expiresOption = trim((string)($_POST['expires_option'] ?? 'never'));
        $currentUserEmail = $_SESSION['user']['email'] ?? null;
        $isAdmin = $sessionIsAdmin;
        $response = ['success' => false, 'message' => ''];
        if (!$currentUserEmail) {
            $response['message'] = 'Not authenticated';
            echo json_encode($response);
            exit;
        }
        $allowed = [
            'never' => null,
            '1h' => 3600,
            '1d' => 86400,
            '1w' => 7 * 86400,
            '1m' => 30 * 86400,
            '3m' => 90 * 86400,
            '6m' => 180 * 86400,
            '1y' => 365 * 86400,
        ];
        if ($shareId === '' || !array_key_exists($expiresOption, $allowed)) {
            $response['message'] = 'Invalid request';
            echo json_encode($response);
            exit;
        }
        $file = getSharedFileById($shareId);
        if (!$file || (!$isAdmin && (($file['user_email'] ?? '') !== $currentUserEmail))) {
            $response['message'] = 'Access denied';
            echo json_encode($response);
            exit;
        }
        $expires = $allowed[$expiresOption] === null ? null : (time() + $allowed[$expiresOption]);
        if (updateSharedFileExpiration($shareId, $expires, $expiresOption)) {
            $response['success'] = true;
            $response['message'] = 'Expiration updated';
            $response['files'] = $isAdmin ? loadSharedFiles(null, true) : loadSharedFiles($currentUserEmail);
        } else {
            $response['message'] = 'No changes';
            $response['files'] = $isAdmin ? loadSharedFiles(null, true) : loadSharedFiles($currentUserEmail);
        }
        echo json_encode($response);
        exit;
    }

    if(isset($_POST['delete_all_shared_files'])&&isset($_POST['ajax'])){
        $currentUserEmail = $_SESSION['user']['email'] ?? null;
        $isAdmin = $sessionIsAdmin;
        
        $response = ['success'=>false,'message'=>''];
        
        if ($isAdmin) {
            deleteAllSharedFiles();
            $response['success'] = true;
            $response['message'] = $translations['all_files_deleted'];
            $response['files'] = [];
        } elseif ($currentUserEmail) {
            $db = getDB();
            $stmt = $db->prepare("DELETE FROM shared_files WHERE user_email = :email");
            $stmt->bindValue(':email', $currentUserEmail);
            $stmt->execute();
            $response['success'] = true;
            $response['message'] = 'Your shared files deleted';
            $response['files'] = loadSharedFiles($currentUserEmail);
        } else {
            $response['message'] = 'Not authenticated';
        }
        
        while(ob_get_level() > 1) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    if(isset($_POST['delete_filtered_files']) && isset($_POST['ajax'])) {
        $fileIds = $_POST['file_ids'] ?? [];
        if(is_string($fileIds)){ $fileIds = json_decode($fileIds, true) ?? []; }
        
        $currentUserEmail = $_SESSION['user']['email'] ?? null;
        $isAdmin = $sessionIsAdmin;
        
        $response = ['success' => false, 'message' => ''];
        
        if (!empty($fileIds) && is_array($fileIds)) {
            if ($isAdmin) {
                $deleted = deleteSharedFilesBatch($fileIds);
            } elseif ($currentUserEmail) {
                $db = getDB();
                $placeholders = implode(',', array_fill(0, count($fileIds), '?'));
                $sql = "DELETE FROM shared_files WHERE share_id IN ($placeholders) AND user_email = ?";
                $stmt = $db->prepare($sql);
                foreach ($fileIds as $i => $id) {
                    $stmt->bindValue($i + 1, $id);
                }
                $stmt->bindValue(count($fileIds) + 1, $currentUserEmail);
                $stmt->execute();
                $deleted = $db->changes();
            } else {
                $response['message'] = 'Not authenticated';
                while(ob_get_level() > 1) ob_end_clean();
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            }
            $response['success'] = true;
            $response['message'] = $deleted . ' files deleted successfully';
            $response['files'] = $isAdmin ? loadSharedFiles(null, true) : loadSharedFiles($currentUserEmail);
        } else {
            $response['success'] = true;
            $response['message'] = '0 files deleted';
            $response['files'] = $isAdmin ? loadSharedFiles(null, true) : ($currentUserEmail ? loadSharedFiles($currentUserEmail) : []);
        }
        
        while(ob_get_level() > 1) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    if(isset($_POST['send_reset_code'])){
        $email=$_POST['email']??'';
        $users=loadUsers();
        if(isset($users[$email])){
            $mailGuard=apMailGuard('reset',$email);
            if($mailGuard!==null){
                $authError=apMailGuardMessage($mailGuard);
                $showResetForgot=true;
                apMailGuardReport($mailGuard,$email);
            }else{
            $resetCode=generateResetCode();
            $codes=loadResetCodes();
            $codes[$email]=['code'=>$resetCode,'expires'=>time()+900];
            saveResetCodes($codes);
            if(sendPasswordResetEmail($email,$resetCode)){
                $authSuccess=$translations['reset_code_sent'];
                $showResetForm=true;
                $resetEmail=$email;
            }else{$authError='Failed to send email. Check SMTP configuration.';}
            }
        }else{$authError=$translations['invalid_credentials'];}
    }
    if(isset($_POST['reset_password'])){
        $email=$_POST['email']??'';
        $code=$_POST['reset_code']??'';
        $newPassword=$_POST['new_password']??'';
        $confirmPassword=$_POST['confirm_password']??'';
        $users=loadUsers();
        $codes=loadResetCodes();
        if(isset($codes[$email])&&isset($codes[$email]['code'])&&hash_equals((string)$codes[$email]['code'],(string)$code)&&$codes[$email]['expires']>time()){
            if($newPassword !== $confirmPassword){
                $authError=$translations['password_mismatch'];
                $showResetForm=true;
                $resetEmail=$email;
            }elseif(($passwordErrorKey = validatePasswordPolicy($newPassword)) !== null){
                $authError=$translations[$passwordErrorKey];
                $showResetForm=true;
                $resetEmail=$email;
            }else{
                $users[$email]['password']=appPasswordHash($newPassword);
                saveUsers($users);
                unset($codes[$email]);
                saveResetCodes($codes);
                $authSuccess=$translations['password_reset_success'];
                $showResetForm=false;
            }
        }else{
            $authError=$translations['invalid_reset_code'];
            $showResetForm=true;
            $resetEmail=$email;
        }
    }
    if(isset($_POST['verify_current_password'])&&isset($_POST['ajax'])){
        if(!isset($_SESSION['user']['email'])){
            while(ob_get_level()>1)ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['success'=>false,'error'=>$translations['login']??'Not authenticated']);
            exit;
        }
        apSecRequireSameOrigin();
        $email=$_SESSION['user']['email'];
        $currentPassword=$_POST['current_password']??'';
        $users=loadUsers();
        $valid=isset($users[$email])&&appPasswordVerify($currentPassword, $users[$email]['password']);
        while(ob_get_level()>1)ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success'=>$valid,'error'=>$valid?null:($translations['wrong_current_password']??'Wrong current password')]);
        exit;
    }
    if(isset($_POST['change_password'])){
        $email=$_SESSION['user']['email']??'';
        $currentPassword=$_POST['current_password']??'';
        $newPassword=$_POST['new_password']??'';
        $confirmPassword=$_POST['confirm_password']??'';
        $users=loadUsers();
        if(!isset($users[$email])||!appPasswordVerify($currentPassword, $users[$email]['password'])){
            $profileError=$translations['wrong_current_password'];
        }elseif($newPassword !== $confirmPassword){
            $profileError=$translations['password_mismatch'];
        }elseif(($passwordErrorKey = validatePasswordPolicy($newPassword)) !== null){
            $profileError=$translations[$passwordErrorKey];
        }elseif(adminIsAdminUser($users[$email] ?? null) && ($adminPwdErr = validateAdminPasswordPolicy($newPassword)) !== null){
            $profileError=$translations[$adminPwdErr] ?? $translations['password_too_short'];
        }else{
            $users[$email]['password']=appPasswordHash($newPassword);
            $users[$email]['must_change_password']=0;
            saveUsers($users);
            $_SESSION['user']=$users[$email];
            $_SESSION['user']['email']=$email;
            $_SESSION['auth_time']=time();
            session_regenerate_id(true);
            $sessionData=getUserFromActiveSessions(session_id());
            if($sessionData){
                $sessionData['user_data']=$_SESSION['user'];
                addUserToActiveSessions($email,session_id(),$_SESSION['user']);
            }
            adminAuditLog('password_change', $email, 'ok');
            $profileSuccess=$translations['password_changed'];
        }
    }
    if(isset($_POST['generate_mcp_token'])||($_POST['action']??'')==='generate_mcp_token'){
        apSecRequireSameOrigin();
        $email=$_SESSION['user']['email']??'';
        if($email===''){
            $profileError=$translations['login']??'Not authenticated';
        }else{
            try{
                $tokenDb=getDB();
                ensureClientAuthTokensSchema($tokenDb);
                $tokenName=trim((string)($_POST['mcp_token_name']??''));
                if($tokenName==='')$tokenName='MCP';
                $tokenName=substr($tokenName,0,80);
                // Only tokens requested by the MCP setup modal are marked platform='mcp';
                // other consumers of this endpoint (e.g. mobile app pairing) keep their own platform so
                // they never show up in the MCP token list.
                $mcpPlatform=(($_POST['mcp_token_purpose']??'')==='mcp_agent')?'mcp':$tokenName;
                $tokenApp=(($_POST['mcp_token_purpose']??'')==='mcp_agent')?'mcp':appClientAuthTokenAppId($_POST['token_app']??'');
                $tokenData=createClientAuthToken($tokenDb,$email,'mcp_external',$tokenName,$mcpPlatform,$tokenApp);
                $_SESSION['mcp_generated_token']=(string)$tokenData['token'];
                $profileSuccess=$translations['mcp_setup_token_generated'];
                if(isset($_POST['ajax'])){
                    while(ob_get_level()>1)ob_end_clean();
                    header('Content-Type: application/json');
                    echo json_encode(['success'=>true,'token'=>(string)$tokenData['token'],'expires_at'=>$tokenData['expires_at']??null]);
                    exit;
                }
            }catch(Throwable $e){
                if(isset($_POST['ajax'])){
                    while(ob_get_level()>1)ob_end_clean();
                    header('Content-Type: application/json');
                    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
                    exit;
                }
                $profileError=$translations['mcp_setup_token_error'].': '.$e->getMessage();
            }
        }
    }
    if(isset($_POST['revoke_mcp_token'])||($_POST['action']??'')==='revoke_mcp_token'){
        apSecRequireSameOrigin();
        $email=$_SESSION['user']['email']??'';
        $tokenHash=trim((string)($_POST['token_hash']??''));
        $tokenApp=appClientAuthTokenAppId($_POST['token_app']??'mcp');
        if($email!==''&&($tokenHash!==''||$tokenApp==='steps')){
            try{
                $tokenDb=getDB();
                ensureClientAuthTokensSchema($tokenDb);
                if($tokenHash===''){
                    $stmt=$tokenDb->prepare("UPDATE client_auth_tokens SET revoked = 1 WHERE email = :e AND app_id = :a AND revoked = 0");
                }else{
                    $stmt=$tokenDb->prepare("UPDATE client_auth_tokens SET revoked = 1 WHERE token_hash = :h AND email = :e AND app_id = :a");
                    $stmt->bindValue(':h',$tokenHash,PDO::PARAM_STR);
                }
                $stmt->bindValue(':e',$email,PDO::PARAM_STR);
                $stmt->bindValue(':a',$tokenApp,PDO::PARAM_STR);
                $stmt->execute();
                $profileSuccess=$translations['mcp_setup_token_revoked'];
                if(isset($_POST['ajax'])){
                    while(ob_get_level()>1)ob_end_clean();
                    header('Content-Type: application/json');
                    echo json_encode(['success'=>true]);
                    exit;
                }
            }catch(Throwable $e){
                if(isset($_POST['ajax'])){
                    while(ob_get_level()>1)ob_end_clean();
                    header('Content-Type: application/json');
                    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
                    exit;
                }
                $profileError=$translations['mcp_setup_token_error'].': '.$e->getMessage();
            }
        }
    }
    if(isset($_POST['change_photo'])){
        apSecRequireSameOrigin();
        $email=$_SESSION['user']['email']??'';
        $users=loadUsers();
        if(isset($_FILES['photo'])&&$_FILES['photo']['error']===UPLOAD_ERR_OK){
            $uploadDir=appdata_uploads_base_dir().'/avatars/';
            if(!apSecEnsureWritableDir($uploadDir)){
                if((string)getenv('APP_DEBUG')==='1'){ error_log('Avatar upload directory is not writable: '.$uploadDir); }
                $profileError=$translations['photo_upload_failed'] ?? 'Failed to upload photo';
            }else{
            $tmp=(string)$_FILES['photo']['tmp_name'];
            $size=(int)($_FILES['photo']['size'] ?? 0);
            $allowedExt=['jpg','jpeg','png','gif','webp'];
            $allowedMime=['image/jpeg','image/png','image/gif','image/webp'];
            if($size<=0 || $size > 10*1024*1024){
                $profileError=$translations['photo_too_large_or_empty'] ?? 'Photo too large or empty';
            } else {
                $detectedMime=apSecDetectMimeFromFile($tmp);
                $imgInfo=@getimagesize($tmp);
                $mimeOk=in_array($detectedMime,$allowedMime,true);
                $sizeOk=is_array($imgInfo) && in_array((int)($imgInfo[2] ?? 0), [IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_GIF,IMAGETYPE_WEBP], true);
                if(!$mimeOk || !$sizeOk){
                    $profileError=$translations['unsupported_image_type'] ?? 'Unsupported image type';
                } else {
                    $fileName=apSecRandomFilename('webp');
                    $filePath=$uploadDir.$fileName;
                    $oldPhoto=(string)($users[$email]['photo'] ?? '');
                    $tmpUploadPath=$uploadDir.'tmp_'.bin2hex(random_bytes(8)).'.upload';
                    if(move_uploaded_file($tmp,$tmpUploadPath) && apSecConvertImageToWebp($tmpUploadPath,$filePath,$detectedMime)){
                        @unlink($tmpUploadPath);
                        @chmod($filePath,0644);
                        $relPath='uploads/avatars/'.$fileName;
                        $users[$email]['photo']=$relPath;
                        saveUsers($users);
                        if($oldPhoto !== '' && str_starts_with($oldPhoto,'uploads/avatars/') && $oldPhoto !== $relPath){
                            $oldAbs=APP_ROOT.'/'.$oldPhoto;
                            if(is_file($oldAbs)){ @unlink($oldAbs); }
                        }
                        $_SESSION['user']=$users[$email];
                        $_SESSION['user']['email']=$email;
                        $_SESSION['auth_time']=time();
                        $sessionData=getUserFromActiveSessions(session_id());
                        if($sessionData){
                            $sessionData['user_data']=$_SESSION['user'];
                            addUserToActiveSessions($email,session_id(),$_SESSION['user']);
                        }
                        $profileSuccess=$translations['photo_updated'];
                    }else{
                        if(is_file($tmpUploadPath ?? '')){ @unlink($tmpUploadPath); }
                        if(is_file($filePath)){ @unlink($filePath); }
                        $profileError=$translations['photo_upload_failed'] ?? 'Failed to upload photo';
                    }
                }
            }
            }
        }
    }
    if(isset($_POST['reset_photo'])){
        apSecRequireSameOrigin();
        $email=$_SESSION['user']['email']??'';
        if($email && hasOAuthConnection($email,'google')){
            $connections=getOAuthConnectionsForUser($email);
            $googlePhoto=(string)($connections['google']['provider_photo']??'');
            if($googlePhoto!==''){
                $users=loadUsers();
                $oldPhoto=(string)($users[$email]['photo']??'');
                if(str_starts_with($oldPhoto,'uploads/avatars/')){
                    $oldAbs=APP_ROOT.'/'.$oldPhoto;
                    if(is_file($oldAbs)){ @unlink($oldAbs); }
                }
                $users[$email]['photo']=$googlePhoto;
                saveUsers($users);
                $_SESSION['user']=$users[$email];
                $_SESSION['user']['email']=$email;
                $_SESSION['auth_time']=time();
                $sessionData=getUserFromActiveSessions(session_id());
                if($sessionData){
                    $sessionData['user_data']=$_SESSION['user'];
                    addUserToActiveSessions($email,session_id(),$_SESSION['user']);
                }
                $profileSuccess=$translations['photo_reset_to_default'];
            }
        }
    }
    if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['settings'])){
    $newLanguage=$_POST['language'];
    $newTheme=$_POST['theme'];
    $newColor=ap_sanitize_hex_color((string)($_POST['color'] ?? ''));
    $newIconSortOrder=in_array(($_POST['icon_sort_order']??''),['alpha','popularity'],true)?$_POST['icon_sort_order']:'alpha';
    $newIconStyle=in_array(($_POST['icon_style']??''),['monochrome','multicolor','animated'],true)?$_POST['icon_style']:'animated';
    $newWallpaper=in_array(($_POST['wallpaper']??''),$allowedWallpapers,true)?$_POST['wallpaper']:'default';
    if(isset($_SESSION['user']['email'])){
        $userEmail=$_SESSION['user']['email'];
        $users=loadUsers();
        if(isset($users[$userEmail])){
            $users[$userEmail]['language']=$newLanguage;
            $users[$userEmail]['theme']=$newTheme;
            $users[$userEmail]['color']=$newColor;
            $users[$userEmail]['icon_sort_order']=$newIconSortOrder;
            $users[$userEmail]['icon_style']=$newIconStyle;
            $users[$userEmail]['wallpaper']=$newWallpaper;
            saveUsers($users);
            $_SESSION['user']['language']=$newLanguage;
            $_SESSION['user']['theme']=$newTheme;
            $_SESSION['user']['color']=$newColor;
            $_SESSION['user']['icon_sort_order']=$newIconSortOrder;
            $_SESSION['user']['icon_style']=$newIconStyle;
            $_SESSION['user']['wallpaper']=$newWallpaper;
            $config['language']=$newLanguage;
            $config['theme']=$newTheme;
            $config['color']=$newColor;
            $config['icon_sort_order']=$newIconSortOrder;
            $config['icon_style']=$newIconStyle;
            $config['wallpaper']=$newWallpaper;
            ap_write_pref_cookies($config);
            $language=$newLanguage;
            $translations=$allTranslations[$language]??[];
            $translations=array_map(fn($v)=>$v??'',array_merge($defaultTranslations,$translations));
        }
    }
    
        // Build the URL with parameters
    $redirectUrl = '/main';
    $params = [];
    
    if(isset($_POST['current_app']) && !empty($_POST['current_app'])) {
        $params['app'] = $_POST['current_app'];
    }
    
        // Add theme, language and color parameters
    $params['theme'] = $newTheme;
    $params['lang'] = $newLanguage;
    $params['color'] = $newColor;
    
    if(!empty($params)) {
        $redirectUrl .= '?' . http_build_query($params);
    }
    
    header('Location: ' . $redirectUrl);
    exit;
}
    // SSH terminal AJAX (admin only) is handled in a dedicated file.
    if($sessionIsAdmin && (isset($_POST['ssh_terminal_init']) || isset($_POST['ssh_terminal_exec']) || isset($_POST['ssh_terminal_ws_token']))){
        adminRequireCapability('ssh');
        adminRequireMutationThrottle('ssh', 30, 60);
    }
    if(handleSshTerminalRequest($sessionIsAdmin)){
        if ($sessionIsAdmin && isset($_POST['ssh_terminal_init'])) {
            adminAuditLog('ssh_init', (string)($_POST['host'] ?? ''), 'ok');
        }
        exit;
    }

    // Quick Commands API endpoints (admin only)
    if(isset($_POST['ajax']) && $sessionIsAdmin && isset($_POST['quick_command_action'])){
        $db = getDB();
        $action = $_POST['quick_command_action'];
        $userEmail = $_SESSION['user']['email'] ?? '';
        
        // Ensure table exists
        $db->exec("CREATE TABLE IF NOT EXISTS quick_commands (
            id          BIGSERIAL PRIMARY KEY,
            email       TEXT NOT NULL,
            name        TEXT NOT NULL DEFAULT '',
            command     TEXT NOT NULL DEFAULT '',
            directory   TEXT NOT NULL DEFAULT '',
            created_at  INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
            updated_at  INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
            FOREIGN KEY (email) REFERENCES users(email) ON DELETE CASCADE ON UPDATE CASCADE
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_quick_commands_email ON quick_commands(email)");
        $qcCols = [];
        $qcRes = $db->query("SELECT column_name AS name FROM information_schema.columns WHERE table_schema='public' AND table_name='quick_commands'");
        while($qcRes && ($qcRow = $qcRes->fetch(PDO::FETCH_ASSOC))){
            if(isset($qcRow['name'])) $qcCols[] = (string)$qcRow['name'];
        }
        $qcEnsureColumn = static function(string $column, string $definition) use ($db, &$qcCols): void {
            if(in_array($column, $qcCols, true)) return;
            try { $db->exec("ALTER TABLE quick_commands ADD COLUMN $column $definition"); $qcCols[] = $column; } catch (Throwable $e) {}
        };
        $qcEnsureColumn('email', "TEXT NOT NULL DEFAULT ''");
        $qcEnsureColumn('name', "TEXT NOT NULL DEFAULT ''");
        $qcEnsureColumn('command', "TEXT NOT NULL DEFAULT ''");
        $qcEnsureColumn('directory', "TEXT NOT NULL DEFAULT ''");
        $qcEnsureColumn('created_at', "INTEGER NOT NULL DEFAULT 0");
        $qcEnsureColumn('updated_at', "INTEGER NOT NULL DEFAULT 0");
        
        if($action === 'list'){
            $stmt = $db->prepare("SELECT id, name, command, directory, created_at, updated_at FROM quick_commands WHERE email = :email ORDER BY name ASC");
            $stmt->bindValue(':email', $userEmail, PDO::PARAM_STR);
            $result = $stmt->execute();
            $commands = [];
            while($row = $result->fetch(PDO::FETCH_ASSOC)){
                $commands[] = $row;
            }
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'commands' => $commands]);
            exit;
        }
        
        if($action === 'create'){
            $name = trim($_POST['name'] ?? '');
            $command = trim($_POST['command'] ?? '');
            $directory = trim($_POST['directory'] ?? '');
            
            if($name === '' || $command === ''){
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Name and command are required']);
                exit;
            }
            
            $stmt = $db->prepare("INSERT INTO quick_commands (email, name, command, directory) VALUES (:email, :name, :command, :directory)");
            $stmt->bindValue(':email', $userEmail, PDO::PARAM_STR);
            $stmt->bindValue(':name', $name, PDO::PARAM_STR);
            $stmt->bindValue(':command', $command, PDO::PARAM_STR);
            $stmt->bindValue(':directory', $directory, PDO::PARAM_STR);
            $stmt->execute();
            $id = $db->lastInsertRowID();
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'id' => $id]);
            exit;
        }
        
        if($action === 'update'){
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $command = trim($_POST['command'] ?? '');
            $directory = trim($_POST['directory'] ?? '');
            
            if($id <= 0 || $name === '' || $command === ''){
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Invalid data']);
                exit;
            }
            
            $stmt = $db->prepare("UPDATE quick_commands SET name = :name, command = :command, directory = :directory, updated_at = extract(epoch from now())::bigint WHERE id = :id AND email = :email");
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':email', $userEmail, PDO::PARAM_STR);
            $stmt->bindValue(':name', $name, PDO::PARAM_STR);
            $stmt->bindValue(':command', $command, PDO::PARAM_STR);
            $stmt->bindValue(':directory', $directory, PDO::PARAM_STR);
            $stmt->execute();
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
        
        if($action === 'delete'){
            $id = (int)($_POST['id'] ?? 0);
            
            if($id <= 0){
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Invalid ID']);
                exit;
            }
            
            $stmt = $db->prepare("DELETE FROM quick_commands WHERE id = :id AND email = :email");
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':email', $userEmail, PDO::PARAM_STR);
            $stmt->execute();
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
        exit;
    }

    // Save Telegram bot config (admin only)
    if(isset($_POST['save_tg_bot_config']) && isset($_POST['ajax']) && $sessionIsAdmin){
        $wb = trim((string)($_POST['tg_webapp_base_url'] ?? ''));
        $wb = rtrim($wb, '/');
        if ($wb !== '' && !preg_match('#^https?://#i', $wb)) {
            $wb = 'https://' . $wb;
        }
        $cfg = [
            'bot_token' => trim($_POST['tg_bot_token'] ?? ''),
            'webapp_base_url' => $wb,
        ];
        if(saveTgBotConfig($cfg)){
            header('Content-Type: application/json');
            echo json_encode(['success'=>true]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success'=>false,'error'=>'Failed to write config']);
        }
        exit;
    }

    // Test SMTP (admin only)
    if(isset($_POST['test_smtp']) && isset($_POST['ajax']) && $sessionIsAdmin){
        apSecRequireSameOrigin();
        $testTo = trim((string)($_POST['smtp_test_to'] ?? ''));
        if($testTo === '' || !filter_var($testTo, FILTER_VALIDATE_EMAIL)){
            header('Content-Type: application/json');
            echo json_encode(['success'=>false,'error'=>$translations['smtp_test_invalid'] ?? 'Enter a valid test email address']);
            exit;
        }
        $existingCfg = loadAuthConfig();
        $testCfg = [
            'smtp_host' => trim((string)($_POST['smtp_host'] ?? $existingCfg['smtp_host'] ?? '')),
            'smtp_port' => (int)($_POST['smtp_port'] ?? $existingCfg['smtp_port'] ?? 587),
            'smtp_username' => trim((string)($_POST['smtp_username'] ?? $existingCfg['smtp_username'] ?? '')),
            'smtp_password' => (string)($_POST['smtp_password'] ?? ''),
        ];
        if($testCfg['smtp_password'] === ''){
            $testCfg['smtp_password'] = (string)($existingCfg['smtp_password'] ?? '');
        }
        if(!apSmtpConfigured($testCfg)){
            header('Content-Type: application/json');
            echo json_encode(['success'=>false,'error'=>$translations['smtp_not_configured'] ?? 'SMTP is not configured']);
            exit;
        }
        $ok = sendTestEmail($testTo, $testCfg);
        while(ob_get_level() > 1) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success'=>$ok,
            'error'=>$ok ? null : ($translations['smtp_test_fail'] ?? 'Failed to send test email. Check SMTP settings and PHP error log.'),
            'message'=>$ok ? sprintf($translations['smtp_test_ok'] ?? 'Test email sent to %s', $testTo) : null,
        ]);
        exit;
    }

    // Test Telegram bot (admin only)
    if(isset($_POST['test_tg_bot']) && isset($_POST['ajax']) && $sessionIsAdmin){
        $cfg = ['bot_token' => trim($_POST['tg_bot_token'] ?? '')];
        saveTgBotConfig($cfg);
        if(empty($cfg['bot_token'])){
            header('Content-Type: application/json');
            echo json_encode(['success'=>false,'error'=>'Bot token is empty']);
            exit;
        }
        $chatIds = loadAuthorizedAdminChats();
        if(empty($chatIds)){
            header('Content-Type: application/json');
            echo json_encode(['success'=>false,'error'=>'No authorized chat IDs. Users must sign in to the Telegram bot first.']);
            exit;
        }
        $ok = sendTelegramMessage('Test message from Shell Application. Bot is configured correctly.');
        while(ob_get_level() > 1) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success'=>$ok,'error'=>$ok?null:'Delivery failed. See PHP error log. Token starts: '.substr($cfg['bot_token'],0,10).'...']);
        exit;
    }
    if(isset($_POST['generate_tg_link_token']) && isset($_POST['ajax']) && $sessionIsAdmin){
        $email = trim((string)($_POST['tg_link_email'] ?? ''));
        $users = loadUsers();
        if($email === '' || !isset($users[$email])){
            header('Content-Type: application/json');
            echo json_encode(['success'=>false,'error'=>'User not found']);
            exit;
        }
        $current = (string)($_SESSION['user']['email'] ?? '');
        if((int)($_SESSION['user']['role'] ?? 0) < 2){
            header('Content-Type: application/json');
            echo json_encode(['success'=>false,'error'=>'Permission denied']);
            exit;
        }
        $token = createTelegramLinkToken($email, $current);
        header('Content-Type: application/json');
        echo json_encode(['success'=>true,'token'=>$token,'email'=>$email,'expires_in'=>900]);
        exit;
    }
    // Save user folders config (admin only)
    if(isset($_POST['save_user_folders_config']) && isset($_POST['ajax']) && $sessionIsAdmin){
        $cfg = loadUserFoldersConfig();
        if(saveUserFoldersConfig($cfg)){
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'config' => $cfg]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to sync storage paths']);
        }
        exit;
    }

    // Load admin panel HTML on demand (admin only, AJAX) — lazy loading
    if(isset($_POST['get_admin_panel']) && isset($_POST['ajax']) && $sessionIsAdmin){
        adminPanelRequireGate();
        $_apCaps = adminUserCapabilities();
        $_apCapUsers = in_array('users', $_apCaps, true);
        $_apCapSsh = in_array('ssh', $_apCaps, true);
        $_apCapDocker = in_array('docker', $_apCaps, true);
        $_apCapSecrets = in_array('secrets', $_apCaps, true);
        $_apCapDb = in_array('database', $_apCaps, true);
        $_apCapIntegrations = in_array('integrations', $_apCaps, true);
        // Load $apps here since it's normally loaded after the POST block
        $_apAppsDir = 'apps/';
        $_apApps = [];
        $_apAccessApps = [];
        $_apUr = (int)($_SESSION['user']['role'] ?? 0);
        $_apVisitStats2 = getTotalVisitsPerApp();
        if(is_dir($_apAppsDir)){
            $_apSubdirs = array_filter(glob($_apAppsDir.'*'),'is_dir');
            foreach($_apSubdirs as $_apSubdir){
                $_apNameFile = $_apSubdir.'/name.json';
                $_apFolder   = basename($_apSubdir);
                if(!file_exists($_apNameFile)) continue;
                $_apNameData = json_decode(file_get_contents($_apNameFile),true);
                $_apName = $_apNameData[$language]??($_apNameData['en']??$_apFolder);
                $_apIconFs = $_apSubdir.'/icons/multicolor.svg';
                $_apIcon = file_exists($_apIconFs) ? $_apIconFs : 'default_icon.png';
                $_apTotalV = $_apVisitStats2[$_apFolder] ?? 0;
                $_apMr = getAppMinRole($_apFolder);
                $_apAccessApps[] = ['folder'=>$_apFolder,'name'=>$_apName,'icon'=>$_apIcon,'is_svg'=>(strtolower(pathinfo($_apIcon,PATHINFO_EXTENSION))==='svg'),'visits'=>(int)$_apTotalV,'min_role'=>$_apMr];
                if(!userMeetsAppMinRole($_apUr,$_apFolder)) continue;
                $_apApps[$_apName] = ['folder'=>$_apFolder,'icon'=>$_apIcon,'visits'=>$_apTotalV];
            }
            usort($_apAccessApps, function($a,$b){ if($a['visits']===$b['visits']) return strcoll($a['folder'],$b['folder']); return $b['visits']-$a['visits']; });
            uasort($_apApps, function($a,$b){ if($a['visits']===$b['visits']) return strcoll($a['folder'],$b['folder']); return $b['visits']-$a['visits']; });
        }
        // Use $_apApps instead of $apps inside this handler
        $apps = $_apApps; // make available to ob_start block

        $_apAuthCfg  = loadAuthConfig();
        $_apTgCfg    = loadTgBotConfig();
        $_apDockerCfg= loadDockerConfig();
        $_apOmnirouteCfg = loadOmnirouteOAuthConfig();
        $_apAccessSec = loadAppAccessSecurityConfig();
        $_apHostCountry = appAccessHostCountry();
        $_apCountries = [
            'US'=>'United States','CA'=>'Canada','MX'=>'Mexico','BR'=>'Brazil','AR'=>'Argentina','GB'=>'United Kingdom','IE'=>'Ireland','FR'=>'France','DE'=>'Germany','ES'=>'Spain','PT'=>'Portugal','IT'=>'Italy','NL'=>'Netherlands','BE'=>'Belgium','CH'=>'Switzerland','AT'=>'Austria','PL'=>'Poland','CZ'=>'Czechia','SK'=>'Slovakia','HU'=>'Hungary','RO'=>'Romania','BG'=>'Bulgaria','GR'=>'Greece','SE'=>'Sweden','NO'=>'Norway','FI'=>'Finland','DK'=>'Denmark','EE'=>'Estonia','LV'=>'Latvia','LT'=>'Lithuania','UA'=>'Ukraine','BY'=>'Belarus','RU'=>'Russia','TR'=>'Turkey','IL'=>'Israel','SA'=>'Saudi Arabia','AE'=>'United Arab Emirates','IN'=>'India','CN'=>'China','JP'=>'Japan','KR'=>'South Korea','ID'=>'Indonesia','TH'=>'Thailand','VN'=>'Vietnam','AU'=>'Australia','NZ'=>'New Zealand','ZA'=>'South Africa','EG'=>'Egypt','NG'=>'Nigeria'
        ];
        $_apOnlyofficeToken = getConfig('onlyoffice_jwt_secret', '');
        require_once __DIR__ . '/../apps/cloud/includes/ai_config_store.php';
        $_apAiCfg = cloud_ai_load_config();
        $_apChats    = loadAuthorizedTelegramSessions();
        $_apUList    = loadUsers();
        $_apPList    = loadPendingUsers();
        $_apAllU     = $_apUList;
        foreach($_apPList as $_pe=>$_pd){ if(!isset($_apAllU[$_pe])){ $_pd['_pending']=true; $_apAllU[$_pe]=$_pd; } }
        $_apCurEmail = $_SESSION['user']['email'] ?? '';
    
        ob_start();
?>
<!-- ═══ Admin panel dynamic HTML ═══ -->
<div id="ap-topbar">
    <div class="ap-brand">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>
        <?php echo my_htmlspecialchars($translations['admin_panel_title']??'Admin Panel'); ?>
    </div>
    <div class="ap-user-info">
        <img src="<?php echo my_htmlspecialchars(app_avatar_src($_SESSION['user']['photo'] ?? 'default_avatar.png'), ENT_QUOTES, 'UTF-8'); ?>" alt="avatar" style="width:32px;height:32px;border-radius:50%;border:2px solid var(--border-color);object-fit:cover;">
        <div>
            <div class="ap-user-name"><?php echo my_htmlspecialchars($_SESSION['user']['name']??''); ?></div>
            <div class="ap-user-role"><?php echo my_htmlspecialchars($translations['admin']??'Admin'); ?></div>
        </div>
        <button id="ap-close-btn" type="button">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
</div>
<div id="ap-body">
    <div id="ap-sidebar">
        <span id="ap-session-timer" class="ap-session-timer" title="<?php echo my_htmlspecialchars($translations['ap_session_time_left']??'Session time left'); ?>"></span>
        <button class="ap-nav-btn active" data-panel="ap-users" data-cap="users"<?php echo $_apCapUsers?'':' style="display:none"'; ?>><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M16 3.128a4 4 0 0 1 0 7.744"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/></svg><span class="ap-tooltip"><?php echo my_htmlspecialchars($translations['user_management']??'User Management'); ?></span></button>
        <div class="ap-sidebar-divider"></div>
        <button class="ap-nav-btn" data-panel="ap-settings" data-cap="secrets"<?php echo $_apCapSecrets?'':' style="display:none"'; ?>><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><circle cx="12" cy="12" r="4"/></svg><span class="ap-tooltip"><?php echo my_htmlspecialchars($translations['settings_title']??'Settings'); ?></span></button>
        <div class="ap-sidebar-divider"></div>
        <button class="ap-nav-btn" data-panel="ap-apps-access" data-cap="users"<?php echo $_apCapUsers?'':' style="display:none"'; ?>><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg><span class="ap-tooltip"><?php echo my_htmlspecialchars($translations['ap_app_access_title']??'App access'); ?></span></button>
        <div class="ap-sidebar-divider"></div>
        <button class="ap-nav-btn" data-panel="ap-stats"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M3 3v18h18V3H3zm16 16H5V5h14v14zM7 12h2v5H7v-5zm4-2h2v7h-2v-7zm4 4h2v3h-2v-3z"/></svg><span class="ap-tooltip"><?php echo my_htmlspecialchars($translations['stats_graph']??'Statistics'); ?></span></button>
        <div class="ap-sidebar-divider"></div>
        <button class="ap-nav-btn" data-panel="ap-ssh" data-cap="ssh"<?php echo $_apCapSsh?'':' style="display:none"'; ?>><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M3 5h18v14H3V5zm2 2v10h14V7H5zm2.4 4.2l3 2.2-3 2.2v-1.7l1.2-.9-1.2-.9v-1.9zm5.6 3.1h4v1.6h-4v-1.6z"/></svg><span class="ap-tooltip"><?php echo my_htmlspecialchars($translations['ssh_terminal']??'SSH Terminal'); ?></span></button>
        <button class="ap-nav-btn" data-panel="ap-backup" data-cap="integrations"<?php echo $_apCapIntegrations?'':' style="display:none"'; ?>><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 20H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h3.9a2 2 0 0 1 1.69.9l.81 1.2a2 2 0 0 0 1.67.9H20a2 2 0 0 1 2 2v.5"/><path d="M12 10v4h4"/><path d="m12 14 1.535-1.605a5 5 0 0 1 8 1.5"/><path d="M22 22v-4h-4"/><path d="m22 18-1.535 1.605a5 5 0 0 1-8-1.5"/></svg><span class="ap-tooltip"><?php echo my_htmlspecialchars($translations['backup']??'Backup'); ?></span></button>
        <div class="ap-sidebar-divider"></div>
        <button class="ap-nav-btn" data-panel="ap-docs"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2v8l3-3 3 3V2"/><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H19a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6.5a1 1 0 0 1 0-5H20"/></svg><span class="ap-tooltip"><?php echo my_htmlspecialchars($translations['view_documentation']??'Documentation'); ?></span></button>
        <div class="ap-sidebar-divider"></div>
        <button class="ap-nav-btn" data-panel="ap-db" data-cap="database"<?php echo $_apCapDb?'':' style="display:none"'; ?>><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3C7.58 3 4 4.79 4 7v10c0 2.21 3.59 4 8 4s8-1.79 8-4V7c0-2.21-3.58-4-8-4zm0 2c3.87 0 6 1.47 6 2s-2.13 2-6 2-6-1.47-6-2 2.13-2 6-2zM6 9.7c1.47.82 3.57 1.3 6 1.3s4.53-.48 6-1.3V11c0 .53-2.13 2-6 2s-6-1.47-6-2V9.7zm0 4c1.47.82 3.57 1.3 6 1.3s4.53-.48 6-1.3V15c0 .53-2.13 2-6 2s-6-1.47-6-2v-1.3zm0 4c1.47.82 3.57 1.3 6 1.3s4.53-.48 6-1.3V19c0 .53-2.13 2-6 2s-6-1.47-6-2v-1.3z"/></svg><span class="ap-tooltip"><?php echo my_htmlspecialchars($translations['database']??'Database'); ?></span></button>
        <div class="ap-sidebar-divider"></div>
        <button class="ap-nav-btn" data-panel="ap-omniroute" data-cap="integrations"<?php echo $_apCapIntegrations?'':' style="display:none"'; ?>><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg><span class="ap-tooltip"><?php echo my_htmlspecialchars($translations['omniroute_title']??'OmniRoute'); ?></span></button>
        <div class="ap-sidebar-divider"></div>
        <button class="ap-nav-btn" data-panel="ap-performance"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h4l2-6 4 12 2-6h6"/><path d="M3 20h18"/></svg><span class="ap-tooltip"><?php echo my_htmlspecialchars($translations['performance_monitor_title']??'Performance'); ?></span></button>
        <div class="ap-sidebar-divider"></div>
        <button class="ap-nav-btn" data-panel="ap-docker-manager" data-cap="docker"<?php echo $_apCapDocker?'':' style="display:none"'; ?>><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 50 50"><path d="M 20 9 L 20 14 L 10 14 L 10 19 L 5 19 L 5 24 L 1.125 24 C 0.640625 24 0.242188 24.335938 0.15625 24.8125 C 0.148438 24.847656 0 25.683594 0 26.75 C 0 27.449219 0.0664063 28.210938 0.1875 28.96875 C 1.332031 28.695313 3.429688 28.285156 3.0625 26.9375 C 5.035156 29.222656 9.769531 28.53125 10.96875 27.40625 C 12.308594 29.347656 20.113281 28.605469 20.65625 27.09375 C 22.335938 29.0625 27.542969 29.0625 29.21875 27.09375 C 29.761719 28.605469 37.535156 29.347656 38.875 27.40625 C 39.300781 27.804688 40.1875 28.136719 41.21875 28.3125 C 41.566406 27.652344 41.886719 26.988281 42.1875 26.28125 C 48.539063 26.203125 49.910156 21.636719 49.96875 21.4375 C 50.078125 21.054688 49.929688 20.660156 49.625 20.40625 C 49.519531 20.316406 47.175781 18.414063 43.375 19.0625 C 42.308594 15.589844 39.5625 14.007813 39.4375 13.9375 C 39.078125 13.734375 38.632813 13.765625 38.3125 14.03125 C 38.210938 14.113281 35.847656 16.117188 36.21875 20.21875 C 36.3125 21.25 36.582031 22.160156 37 22.96875 C 36.179688 23.425781 34.769531 24 32.5 24 L 32 24 L 32 19 L 27 19 L 27 9 Z M 41.21875 28.3125 C 41.097656 28.546875 40.941406 28.773438 40.8125 29 L 49.84375 29 C 48.757813 28.726563 46.425781 28.359375 46.8125 26.9375 C 45.535156 28.414063 43.109375 28.632813 41.21875 28.3125 Z M 40.8125 29 L 0.1875 29 C 0.429688 30.46875 0.929688 32.007813 1.6875 33.5 C 7.117188 34.777344 12.816406 32.832031 12.875 32.8125 C 13.398438 32.628906 13.945313 32.917969 14.125 33.4375 C 14.308594 33.957031 14.050781 34.539063 13.53125 34.71875 C 13.339844 34.785156 9.90625 35.9375 5.6875 35.9375 C 4.851563 35.9375 3.972656 35.890625 3.09375 35.78125 C 5.71875 39.261719 10.167969 42 17 42 C 27.804688 42 36.113281 37.410156 40.8125 29 Z M 0.1875 29 C 0.183594 28.984375 0.191406 28.984375 0.1875 28.96875 C 0.121094 28.984375 0.0585938 28.984375 0 29 Z M 22 11 L 25 11 L 25 14 L 22 14 Z M 12 16 L 15 16 L 15 19 L 12 19 Z M 17 16 L 20 16 L 20 19 L 17 19 Z M 22 16 L 25 16 L 25 19 L 22 19 Z M 7 21 L 10 21 L 10 24 L 7 24 Z M 12 21 L 15 21 L 15 24 L 12 24 Z M 17 21 L 20 21 L 20 24 L 17 24 Z M 22 21 L 25 21 L 25 24 L 22 24 Z M 27 21 L 30 21 L 30 24 L 27 24 Z M 16 31 C 16.128906 31 16.261719 31.019531 16.375 31.0625 C 16.253906 31.132813 16.15625 31.253906 16.15625 31.40625 C 16.15625 31.632813 16.335938 31.84375 16.5625 31.84375 C 16.714844 31.84375 16.867188 31.75 16.9375 31.625 C 16.988281 31.742188 17 31.863281 17 32 C 17 32.550781 16.550781 33 16 33 C 15.449219 33 15 32.550781 15 32 C 15 31.449219 15.449219 31 16 31 Z" fill="currentColor"/></svg><span class="ap-tooltip"><?php echo my_htmlspecialchars($translations['docker_manager_title']??'Docker Manager'); ?></span></button>
    </div>
    <div id="ap-content">
        <!-- Users Panel -->
        <div class="ap-panel active" id="ap-users">
            <div class="ap-breadcrumb"><?php echo my_htmlspecialchars($translations['admin_panel_title']??'Admin Panel'); ?> / <span><?php echo my_htmlspecialchars($translations['user_management']??'User Management'); ?></span></div>
            <div class="ap-page-title"><?php echo my_htmlspecialchars($translations['user_management']??'User Management'); ?></div>
            <div class="ap-page-subtitle"><?php echo my_htmlspecialchars($translations['ap_users_subtitle']??'View, edit roles, and delete registered users.'); ?></div>
            <!-- User Analytics Summary -->
            <div class="ap-card ap-user-analytics-card" style="margin-bottom:14px;">
                <div class="ap-stat-grid">
                    <div class="ap-stat-tile">
                        <div class="ap-stat-label"><?php echo my_htmlspecialchars($translations['total_users']??'Total Users'); ?></div>
                        <div id="ap-analytics-total" class="ap-stat-value"><?php echo count($_apAllU); ?></div>
                    </div>
                    <div class="ap-stat-tile">
                        <div class="ap-stat-label"><?php echo my_htmlspecialchars($translations['verified_users']??'Verified'); ?></div>
                        <div id="ap-analytics-verified" class="ap-stat-value" style="color:var(--primary-color);"><?php echo count(array_filter($_apAllU, function($u) { return !empty($u['verified']); })); ?></div>
                    </div>
                    <div class="ap-stat-tile">
                        <div class="ap-stat-label"><?php echo my_htmlspecialchars($translations['admins']??'Admins'); ?></div>
                        <div id="ap-analytics-admins" class="ap-stat-value" style="color:#dc3545;"><?php echo count(array_filter($_apAllU, function($u) { return ($u['role'] ?? 0) == 2; })); ?></div>
                    </div>
                    <div class="ap-stat-tile">
                        <div class="ap-stat-label"><?php echo my_htmlspecialchars($translations['managers']??'Managers'); ?></div>
                        <div id="ap-analytics-managers" class="ap-stat-value" style="color:#ffc107;"><?php echo count(array_filter($_apAllU, function($u) { return ($u['role'] ?? 0) == 1; })); ?></div>
                    </div>
                    <div class="ap-stat-tile">
                        <div class="ap-stat-label"><?php echo my_htmlspecialchars($translations['guests']??'Guests'); ?></div>
                        <div id="ap-analytics-guests" class="ap-stat-value" style="color:#28a745;"><?php echo count(array_filter($_apAllU, function($u) { return ($u['role'] ?? 0) == 0; })); ?></div>
                    </div>
                    <div class="ap-stat-tile">
                        <div class="ap-stat-label"><?php echo my_htmlspecialchars($translations['pending_users']??'Pending'); ?></div>
                        <div id="ap-analytics-pending" class="ap-stat-value" style="color:#ff9800;"><?php echo count(array_filter($_apAllU, function($u) { return !empty($u['_pending']); })); ?></div>
                    </div>
                </div>
            </div>
            <div class="ap-card">
                <div class="ap-card-title"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg><?php echo my_htmlspecialchars($translations['users_list']??'Users List'); ?></div>
                <input type="text" class="ap-search-bar" id="ap-users-search" placeholder="<?php echo my_htmlspecialchars($translations['search_users']??'Search users...'); ?>">
                <div style="margin-bottom:10px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <button type="button" id="ap-refresh-users-btn" class="ap-btn ap-btn-secondary" style="padding:5px 12px;font-size:12px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46A7.93 7.93 0 0020 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74A7.93 7.93 0 004 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/></svg> <?php echo my_htmlspecialchars($translations['refresh']??'Refresh'); ?></button>
                    <div class="ap-status" id="ap-roles-status" style="margin:0;padding:5px 12px;font-size:12px;"></div>
                </div>
                <div class="ap-users-table-scroll">
                    <table class="ap-table" id="ap-users-table" style="min-width:900px;">
                        <thead><tr>
                            <th><?php echo my_htmlspecialchars($translations['name']??'Name'); ?></th>
                            <th><?php echo my_htmlspecialchars($translations['email']??'Email'); ?></th>
                            <th><?php echo my_htmlspecialchars($translations['user_role']??'Role'); ?></th>
                            <th><?php echo my_htmlspecialchars($translations['language']??'Lang'); ?></th>
                            <th><?php echo my_htmlspecialchars($translations['theme']??'Theme'); ?></th>
                            <th><?php echo my_htmlspecialchars($translations['color']??'Color'); ?></th>
                            <th><?php echo my_htmlspecialchars($translations['ap_col_status']??'Status'); ?></th>
                            <th><?php echo my_htmlspecialchars($translations['ap_col_date']??'Created'); ?></th>
                            <th></th>
                        </tr></thead>
                        <tbody>
<?php foreach($_apAllU as $_email=>$_ud):
    $_isRoot=($_email==='root@root'); $_isSelf=($_email===$_apCurEmail);
    $_isVer=!empty($_ud['verified']); $_isPend=!empty($_ud['_pending']); $_role=(int)($_ud['role']??0);
    $_eH=my_htmlspecialchars($_email); $_nH=my_htmlspecialchars($_ud['name']??'');
    $_ph=my_htmlspecialchars(app_avatar_src($_ud['photo']??'default_avatar.png')); $_dis=$_isSelf?' disabled':'';
?>
                        <tr data-email="<?php echo $_eH; ?>" data-name="<?php echo $_nH; ?>">
                            <td><div style="display:flex;align-items:center;gap:8px;white-space:nowrap;"><img src="<?php echo $_ph; ?>" style="width:24px;height:24px;border-radius:50%;border:1px solid var(--border-color);object-fit:cover;flex-shrink:0;" alt=""><?php echo $_nH; ?></div></td>
                            <td><span class="ap-copy-email" data-copy-email="<?php echo $_eH; ?>" tabindex="0" role="button" aria-label="Copy email" title="Click to copy email"><?php echo $_eH; ?></span></td>
                            <td><?php if($_isRoot): ?><span style="color:var(--primary-color);font-size:12px;font-weight:700;"><?php echo my_htmlspecialchars($translations['admin']??'Admin'); ?> &#x1F512;</span><?php elseif($_isPend): ?><span style="color:#aaa;font-size:12px;">Guest</span><?php else: ?><select class="ap-role-select ap-user-field" data-email="<?php echo $_eH; ?>" data-field="role"<?php echo $_dis; ?>><option value="0" <?php echo $_role===0?'selected':''; ?>><?php echo my_htmlspecialchars($translations['guest']??'Guest'); ?></option><option value="1" <?php echo $_role===1?'selected':''; ?>><?php echo my_htmlspecialchars($translations['manager']??'Manager'); ?></option><option value="2" <?php echo $_role===2?'selected':''; ?>><?php echo my_htmlspecialchars($translations['admin']??'Admin'); ?></option></select><?php endif; ?></td>
                            <td><?php if(!$_isRoot&&!$_isPend): ?><select class="ap-role-select ap-user-field" data-email="<?php echo $_eH; ?>" data-field="language"<?php echo $_dis; ?>><?php foreach(['en'=>'EN','ru'=>'RU','uk'=>'UK','be'=>'BE','pl'=>'PL','cs'=>'CS'] as $_lv=>$_ll): ?><option value="<?php echo $_lv; ?>" <?php echo ($_ud['language']??'en')===$_lv?'selected':''; ?>><?php echo $_ll; ?></option><?php endforeach; ?></select><?php else: ?><span style="opacity:.35;font-size:12px;">&#x2014;</span><?php endif; ?></td>
                            <td><?php if(!$_isRoot&&!$_isPend): ?><select class="ap-role-select ap-user-field" data-email="<?php echo $_eH; ?>" data-field="theme"<?php echo $_dis; ?>><?php foreach(['light'=>'theme_light','dark'=>'theme_dark','auto'=>'theme_auto'] as $_tv=>$_tk): ?><option value="<?php echo $_tv; ?>" <?php echo ($_ud['theme']??'light')===$_tv?'selected':''; ?>><?php echo my_htmlspecialchars($translations[$_tk]??$_tk); ?></option><?php endforeach; ?></select><?php else: ?><span style="opacity:.35;font-size:12px;">&#x2014;</span><?php endif; ?></td>
                            <td><?php if(!$_isRoot&&!$_isPend): ?><input type="color" class="ap-user-field ap-color-picker" data-email="<?php echo $_eH; ?>" data-field="color" value="<?php echo my_htmlspecialchars($_ud['color']??'#007bff'); ?>"<?php echo $_dis; ?> style="width:36px;height:28px;padding:2px;border:1px solid var(--border-color);border-radius:4px;background:var(--bg-color);cursor:pointer;"><?php else: ?><span style="opacity:.35;font-size:12px;">&#x2014;</span><?php endif; ?></td>
                            <td><?php if($_isRoot): ?><span class="ap-verified-yes" style="font-size:16px;">&#x2713;</span><?php elseif($_isPend): ?><span style="color:#ff9800;font-weight:bold;font-size:12px;">&#x23F3; Pending</span><?php else: ?><button type="button" class="ap-verified-toggle ap-user-field" data-email="<?php echo $_eH; ?>" data-field="verified" data-value="<?php echo $_isVer?'1':'0'; ?>" style="background:none;border:none;cursor:pointer;padding:2px;font-size:16px;"><span class="<?php echo $_isVer?'ap-verified-yes':'ap-verified-no'; ?>" style="font-size:16px;"><?php echo $_isVer?'&#x2713;':'&#x2717;'; ?></span></button><?php endif; ?></td>
                            <td style="font-size:11px;opacity:.55;white-space:nowrap;"><?php echo my_htmlspecialchars(substr($_ud['created_at']??'&#x2014;',0,10)); ?></td>
                            <td><?php echo renderApUserActionsCell($_email, $_ud['name'] ?? '', $_isRoot, $_isSelf, $_isPend, $translations); ?></td>
                        </tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="ap-users-autosave-hint"><span style="font-size:12px;opacity:.4;font-family:sans-serif;"><?php echo my_htmlspecialchars($translations['ap_autosave']??'Changes are saved automatically on edit'); ?></span></div>
            </div>
        </div>
        <!-- Settings Panel (Email, Telegram, Docker, Backup) -->
        <div class="ap-panel" id="ap-settings">
            <div class="ap-breadcrumb"><?php echo my_htmlspecialchars($translations['admin_panel_title']??'Admin Panel'); ?> / <span><?php echo my_htmlspecialchars($translations['settings_title']??'Settings'); ?></span></div>
            <div class="ap-page-title"><?php echo my_htmlspecialchars($translations['settings_title']??'System Settings'); ?></div>
            <div class="ap-page-subtitle"><?php echo my_htmlspecialchars($translations['ap_settings_subtitle']??'Configure email, Telegram bot, OnlyOffice, Docker, Backup, and AI assistant integrations.'); ?></div>

            <div class="ap-settings-tabs" role="tablist" aria-label="<?php echo my_htmlspecialchars($translations['settings_title']??'Settings'); ?>">
                <button type="button" class="ap-settings-tab active" data-settings-section="email" role="tab" aria-selected="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                    <span><?php echo my_htmlspecialchars($translations['email_settings']??'Email'); ?></span>
                </button>
                <button type="button" class="ap-settings-tab" data-settings-section="telegram" role="tab" aria-selected="false">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12l-6.871 4.326-2.962-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.833.941z"/></svg>
                    <span><?php echo my_htmlspecialchars($translations['bot_settings']??'Telegram Bot'); ?></span>
                </button>
                <button type="button" class="ap-settings-tab" data-settings-section="docker" role="tab" aria-selected="false">
                    <svg width="16" height="16" viewBox="0 0 50 50"><path d="M20 9v5H10v5H5v5H1.125c-.484 0-.883.336-.969.813C.148 24.848 0 25.684 0 26.75c0 .7.066 1.461.188 2.219C1.332 28.695 3.43 28.285 3.063 26.938c1.972 2.285 6.706 1.593 7.905.468 1.34 1.942 9.145 1.2 9.688-.312 1.68 1.969 6.887 1.969 8.563 0 .543 1.512 8.316 2.254 9.656.312.426.399 1.313.731 2.344.907.347-.66.668-1.325.968-2.032C48.54 26.203 49.91 21.637 49.97 21.438c.11-.383-.04-.778-.344-1.032-.105-.09-2.45-1.992-6.25-1.343-1.066-3.473-3.813-5.055-3.938-5.125-.359-.204-.804-.172-1.125.093-.101.082-2.464 2.086-2.093 6.188.093 1.031.363 1.941.781 2.75-.82.457-2.23 1.031-4.5 1.031H32v-5h-5V9H20zm20.813 20H.188c.242 1.469.742 3.008 1.5 4.5C4.313 36.98 8.762 42 17 42c10.805 0 19.113-4.59 23.813-13zM22 11h3v3h-3v-3zm-10 5h3v3h-3v-3zm5 0h3v3h-3v-3zm5 0h3v3h-3v-3zM7 21h3v3H7v-3zm5 0h3v3h-3v-3zm5 0h3v3h-3v-3zm5 0h3v3h-3v-3zm5 0h3v3h-3v-3z" fill="currentColor"/></svg>
                    <span><?php echo my_htmlspecialchars($translations['docker_title']??'Docker'); ?></span>
                </button>
                <button type="button" class="ap-settings-tab" data-settings-section="omniroute" role="tab" aria-selected="false">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                    <span><?php echo my_htmlspecialchars($translations['omniroute_title']??'OmniRoute'); ?></span>
                </button>
                <button type="button" class="ap-settings-tab" data-settings-section="onlyoffice" role="tab" aria-selected="false">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M5 4h10a2 2 0 0 1 2 2v3h-2V6H5v12h6v2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><path d="M14 12v-2l5 3-5 3v-2H9v-2h5z"/></svg>
                    <span><?php echo my_htmlspecialchars($translations['onlyoffice_title']??'OnlyOffice'); ?></span>
                </button>
                <button type="button" class="ap-settings-tab" data-settings-section="backup" role="tab" aria-selected="false">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <span><?php echo my_htmlspecialchars($translations['backup']??'Backup'); ?></span>
                </button>
                <button type="button" class="ap-settings-tab" data-settings-section="ai" role="tab" aria-selected="false">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></svg>
                    <span><?php echo my_htmlspecialchars($translations['ai_assistant_settings']??'AI Assistant'); ?></span>
                </button>
            </div>

            <!-- Email Settings -->
            <div class="ap-card ap-settings-card active" data-settings-card="email">
                <div class="ap-card-title"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg><?php echo my_htmlspecialchars($translations['email_settings']??'Email Settings'); ?></div>
                <div style="margin-bottom:20px;">
                    <h4 style="font-size:13px;font-weight:600;margin:0 0 10px 0;opacity:.8;">Google OAuth 2.0</h4>
                    <div class="ap-field"><label><?php echo my_htmlspecialchars($translations['google_client_id']??'Google Client ID'); ?></label><input type="text" id="ap-gcid" value="<?php echo my_htmlspecialchars($_apAuthCfg['google_client_id']??''); ?>" placeholder="xxxx.apps.googleusercontent.com"></div>
                    <div class="ap-field"><label><?php echo my_htmlspecialchars($translations['google_client_secret']??'Google Client Secret'); ?></label><div class="ap-pwd-wrap"><input type="password" id="ap-gcsecret" value="<?php echo my_htmlspecialchars($_apAuthCfg['google_client_secret']??''); ?>" placeholder="GOCSPX-&#x2026;"><button type="button" class="ap-pwd-eye" data-target="ap-gcsecret"><svg class="ap-eye-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg><svg class="ap-eye-off" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg></button></div></div>
                    <div class="ap-hint"><?php echo my_htmlspecialchars($translations['oauth_callback_hint']??'OAuth callback URL'); ?>: <code><?php echo my_htmlspecialchars(oauthLoginRedirectUri()); ?></code></div>
                </div>
                <div style="margin-bottom:20px;">
                    <h4 style="font-size:13px;font-weight:600;margin:0 0 10px 0;opacity:.8;">GitHub OAuth</h4>
                    <div class="ap-field"><label><?php echo my_htmlspecialchars($translations['github_client_id']??'GitHub Client ID'); ?></label><input type="text" id="ap-ghcid" value="<?php echo my_htmlspecialchars($_apAuthCfg['github_client_id']??''); ?>" placeholder="Iv1.xxxxx" autocomplete="off"><div class="ap-hint"><?php echo my_htmlspecialchars($translations['github_client_id_hint']??'Client ID from OAuth App settings (Iv1.xxxxx), not App ID.'); ?></div></div>
                    <div class="ap-field"><label><?php echo my_htmlspecialchars($translations['github_client_secret']??'GitHub Client Secret'); ?></label><div class="ap-pwd-wrap"><input type="password" id="ap-ghcsecret" value="<?php echo my_htmlspecialchars($_apAuthCfg['github_client_secret']??''); ?>" placeholder="Client secret from OAuth App" autocomplete="off"><button type="button" class="ap-pwd-eye" data-target="ap-ghcsecret"><svg class="ap-eye-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg><svg class="ap-eye-off" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg></button></div></div>
                    <div class="ap-hint"><?php echo my_htmlspecialchars($translations['oauth_callback_hint']??'OAuth callback URL'); ?>: <code><?php echo my_htmlspecialchars(oauthLoginRedirectUri()); ?></code></div>
                </div>
                <div style="margin-bottom:20px;">
                    <h4 style="font-size:13px;font-weight:600;margin:0 0 10px 0;opacity:.8;">SMTP</h4>
                    <div class="ap-field"><label><?php echo my_htmlspecialchars($translations['smtp_host']??'SMTP Host'); ?></label><input type="text" id="ap-smtp-host" value="<?php echo my_htmlspecialchars($_apAuthCfg['smtp_host']??''); ?>" placeholder="smtp.gmail.com"></div>
                    <div class="ap-field"><label><?php echo my_htmlspecialchars($translations['smtp_port']??'SMTP Port'); ?></label><input type="number" id="ap-smtp-port" value="<?php echo my_htmlspecialchars($_apAuthCfg['smtp_port']??587); ?>" placeholder="587"></div>
                    <div class="ap-field"><label><?php echo my_htmlspecialchars($translations['smtp_username']??'SMTP Username'); ?></label><input type="text" id="ap-smtp-user" value="<?php echo my_htmlspecialchars($_apAuthCfg['smtp_username']??''); ?>" placeholder="user@gmail.com"></div>
                    <div class="ap-field"><label><?php echo my_htmlspecialchars($translations['smtp_password']??'SMTP Password'); ?></label><div class="ap-pwd-wrap"><input type="password" id="ap-smtp-pass" value="<?php echo my_htmlspecialchars($_apAuthCfg['smtp_password']??''); ?>" placeholder="xxxx xxxx xxxx xxxx"><button type="button" class="ap-pwd-eye" data-target="ap-smtp-pass"><svg class="ap-eye-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg><svg class="ap-eye-off" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg></button></div></div>
                    <div class="ap-field"><label><?php echo my_htmlspecialchars($translations['smtp_test_to']??'Test recipient'); ?></label><input type="email" id="ap-smtp-test-to" value="<?php echo my_htmlspecialchars($_SESSION['user']['email']??''); ?>" placeholder="user@example.com"></div>
                </div>
                <div style="margin-bottom:20px;">
                    <h4 style="font-size:13px;font-weight:600;margin:0 0 10px 0;opacity:.8;">Cloudflare Turnstile</h4>
                    <div class="ap-field"><label><?php echo my_htmlspecialchars($translations['turnstile_site_key']??'Turnstile Site Key'); ?></label><input type="text" id="ap-turnstile-site" value="<?php echo my_htmlspecialchars($_apAuthCfg['turnstile_site_key']??''); ?>" placeholder="0x4AAAAAAA..." autocomplete="off"></div>
                    <div class="ap-field"><label><?php echo my_htmlspecialchars($translations['turnstile_secret_key']??'Turnstile Secret Key'); ?></label><div class="ap-pwd-wrap"><input type="password" id="ap-turnstile-secret" value="<?php echo my_htmlspecialchars($_apAuthCfg['turnstile_secret_key']??''); ?>" placeholder="0x4AAAAAAA..." autocomplete="off"><button type="button" class="ap-pwd-eye" data-target="ap-turnstile-secret"><svg class="ap-eye-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg><svg class="ap-eye-off" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg></button></div><div class="ap-hint"><?php echo my_htmlspecialchars($translations['turnstile_enabled_hint']??'Get keys at dash.cloudflare.com → Turnstile. Leave empty to disable.'); ?></div></div>
                </div>
                <div class="ap-btn-row"><button type="button" id="ap-save-email" class="ap-btn ap-btn-primary"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg><?php echo my_htmlspecialchars($translations['save']??'Save'); ?></button><button type="button" id="ap-test-email" class="ap-btn ap-btn-success"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg><?php echo my_htmlspecialchars($translations['smtp_test']??'Test email'); ?></button></div>
                <div class="ap-status" id="ap-email-status"></div>
            </div>

            <!-- Telegram Settings -->
            <div class="ap-card ap-settings-card" data-settings-card="telegram">
                <div class="ap-card-title"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12l-6.871 4.326-2.962-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.833.941z"/></svg><?php echo my_htmlspecialchars($translations['bot_settings']??'Telegram Bot'); ?></div>
                <div class="ap-field"><label><?php echo my_htmlspecialchars($translations['tg_bot_token']??'Bot Token'); ?></label><div class="ap-pwd-wrap"><input type="password" id="ap-tg-token" value="<?php echo my_htmlspecialchars($_apTgCfg['bot_token']??''); ?>" placeholder="1234567890:ABCDef&#x2026;"><button type="button" class="ap-pwd-eye" data-target="ap-tg-token"><svg class="ap-eye-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg><svg class="ap-eye-off" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg></button></div><div class="ap-hint"><?php echo my_htmlspecialchars($translations['tg_bot_token_hint']??'Get your token from @BotFather. Do not URL-encode it.'); ?></div></div>
                <div class="ap-field"><label><?php echo my_htmlspecialchars($translations['tg_recipients']??'Authorized Chat IDs'); ?></label><div class="ap-recipients-box"><?php if(!empty($_apChats)): foreach($_apChats as $_s): ?><div><?php echo my_htmlspecialchars(($_s['email']??'') . ' · ' . ($_s['chat_id']??'')); ?></div><?php endforeach; else: ?><span style="opacity:.45;"><?php echo my_htmlspecialchars($translations['tg_no_chats']??'No chat IDs found.'); ?></span><?php endif; ?></div><div class="ap-hint"><?php echo my_htmlspecialchars($translations['ap_tg_hint']??$translations['tg_recipients_hint']??''); ?></div></div>
                <div class="ap-field"><label><?php echo my_htmlspecialchars($translations['tg_link_account']??'Bind account'); ?></label><select id="ap-tg-link-email"><?php foreach($_apUList as $_em=>$_u): if((int)($_u['role']??0)<2) continue; ?><option value="<?php echo my_htmlspecialchars($_em); ?>"<?php echo $_em===$_apCurEmail?' selected':''; ?>><?php echo my_htmlspecialchars($_em); ?></option><?php endforeach; ?></select><div class="ap-hint"><?php echo my_htmlspecialchars($translations['tg_link_hint']??'Generate a one-time code and send it to the Telegram bot.'); ?></div></div>
                <div class="ap-field"><label><?php echo my_htmlspecialchars($translations['tg_link_token']??'One-time code'); ?></label><input type="text" id="ap-tg-link-token" readonly placeholder="--------" autocomplete="off"></div>
                <div class="ap-field"><label><?php echo my_htmlspecialchars($translations['tg_public_base_url']??'Public site URL'); ?></label><input type="text" id="ap-tg-webapp-url" value="<?php echo my_htmlspecialchars($_apTgCfg['webapp_base_url']??''); ?>" placeholder="https://example.com" autocomplete="off"><div class="ap-hint"><?php echo my_htmlspecialchars($translations['tg_public_base_url_hint']??''); ?></div></div>
                <div class="ap-btn-row">
                    <button type="button" class="ap-btn ap-btn-primary" id="ap-tg-save"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg><?php echo my_htmlspecialchars($translations['save']??'Save'); ?></button>
                    <button type="button" class="ap-btn" id="ap-tg-link-generate"><?php echo my_htmlspecialchars($translations['tg_link_generate']??'Generate code'); ?></button>
                    <button type="button" class="ap-btn ap-btn-success" id="ap-tg-test"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg><?php echo my_htmlspecialchars($translations['tg_test']??'Test'); ?></button>
                </div>
                <div class="ap-status" id="ap-tg-status"></div>
            </div>

            <!-- Docker Settings -->
            <div class="ap-card ap-settings-card" data-settings-card="docker">
                <div class="ap-card-title"><svg xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="20" height="20" viewBox="0 0 50 50"><path d="M 20 9 L 20 14 L 10 14 L 10 19 L 5 19 L 5 24 L 1.125 24 C 0.640625 24 0.242188 24.335938 0.15625 24.8125 C 0.148438 24.847656 0 25.683594 0 26.75 C 0 27.449219 0.0664063 28.210938 0.1875 28.96875 C 1.332031 28.695313 3.429688 28.285156 3.0625 26.9375 C 5.035156 29.222656 9.769531 28.53125 10.96875 27.40625 C 12.308594 29.347656 20.113281 28.605469 20.65625 27.09375 C 22.335938 29.0625 27.542969 29.0625 29.21875 27.09375 C 29.761719 28.605469 37.535156 29.347656 38.875 27.40625 C 39.300781 27.804688 40.1875 28.136719 41.21875 28.3125 C 41.566406 27.652344 41.886719 26.988281 42.1875 26.28125 C 48.539063 26.203125 49.910156 21.636719 49.96875 21.4375 C 50.078125 21.054688 49.929688 20.660156 49.625 20.40625 C 49.519531 20.316406 47.175781 18.414063 43.375 19.0625 C 42.308594 15.589844 39.5625 14.007813 39.4375 13.9375 C 39.078125 13.734375 38.632813 13.765625 38.3125 14.03125 C 38.210938 14.113281 35.847656 16.117188 36.21875 20.21875 C 36.3125 21.25 36.582031 22.160156 37 22.96875 C 36.179688 23.425781 34.769531 24 32.5 24 L 32 24 L 32 19 L 27 19 L 27 9 Z M 41.21875 28.3125 C 41.097656 28.546875 40.941406 28.773438 40.8125 29 L 49.84375 29 C 48.757813 28.726563 46.425781 28.359375 46.8125 26.9375 C 45.535156 28.414063 43.109375 28.632813 41.21875 28.3125 Z M 40.8125 29 L 0.1875 29 C 0.429688 30.46875 0.929688 32.007813 1.6875 33.5 C 7.117188 34.777344 12.816406 32.832031 12.875 32.8125 C 13.398438 32.628906 13.945313 32.917969 14.125 33.4375 C 14.308594 33.957031 14.050781 34.539063 13.53125 34.71875 C 13.339844 34.785156 9.90625 35.9375 5.6875 35.9375 C 4.851563 35.9375 3.972656 35.890625 3.09375 35.78125 C 5.71875 39.261719 10.167969 42 17 42 C 27.804688 42 36.113281 37.410156 40.8125 29 Z M 0.1875 29 C 0.183594 28.984375 0.191406 28.984375 0.1875 28.96875 C 0.121094 28.984375 0.0585938 28.984375 0 29 Z M 22 11 L 25 11 L 25 14 L 22 14 Z M 12 16 L 15 16 L 15 19 L 12 19 Z M 17 16 L 20 16 L 20 19 L 17 19 Z M 22 16 L 25 16 L 25 19 L 22 19 Z M 7 21 L 10 21 L 10 24 L 7 24 Z M 12 21 L 15 21 L 15 24 L 12 24 Z M 17 21 L 20 21 L 20 24 L 17 24 Z M 22 21 L 25 21 L 25 24 L 22 24 Z M 27 21 L 30 21 L 30 24 L 27 24 Z M 16 31 C 16.128906 31 16.261719 31.019531 16.375 31.0625 C 16.253906 31.132813 16.15625 31.253906 16.15625 31.40625 C 16.15625 31.632813 16.335938 31.84375 16.5625 31.84375 C 16.714844 31.84375 16.867188 31.75 16.9375 31.625 C 16.988281 31.742188 17 31.863281 17 32 C 17 32.550781 16.550781 33 16 33 C 15.449219 33 15 32.550781 15 32 C 15 31.449219 15.449219 31 16 31 Z" fill="currentColor"/></svg><?php echo my_htmlspecialchars($translations['docker_title']??'Docker'); ?></div>
                <div class="ap-field"><label><?php echo my_htmlspecialchars($translations['docker_token_label']??'API Token'); ?></label><div class="ap-pwd-wrap"><input type="password" id="ap-docker-token" value="<?php echo my_htmlspecialchars($_apDockerCfg['token']??''); ?>" placeholder="ptr_xxxxx"><button type="button" class="ap-pwd-eye" data-target="ap-docker-token"><svg class="ap-eye-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg><svg class="ap-eye-off" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg></button></div></div>
                <div class="ap-field"><label><?php echo my_htmlspecialchars($translations['docker_username_label']??'API Username'); ?></label><input type="text" id="ap-docker-username" value="<?php echo my_htmlspecialchars($_apDockerCfg['username']??''); ?>" placeholder="login"></div>
                <div class="ap-field"><label><?php echo my_htmlspecialchars($translations['docker_password_label']??'API Password'); ?></label><div class="ap-pwd-wrap"><input type="password" id="ap-docker-password" value="<?php echo my_htmlspecialchars($_apDockerCfg['password']??''); ?>" placeholder="password"><button type="button" class="ap-pwd-eye" data-target="ap-docker-password"><svg class="ap-eye-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg><svg class="ap-eye-off" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg></button></div></div>
                <div class="ap-btn-row"><button type="button" id="ap-save-docker" class="ap-btn ap-btn-primary"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg><?php echo my_htmlspecialchars($translations['save']??'Save'); ?></button></div>
                <div class="ap-status" id="ap-docker-status"></div>
            </div>
            <div class="ap-card ap-settings-card" data-settings-card="omniroute">
                <div class="ap-card-title"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg><?php echo my_htmlspecialchars($translations['omniroute_title']??'OmniRoute'); ?></div>
                <div class="ap-field"><label><?php echo my_htmlspecialchars($translations['omniroute_google_client_id']??'Google OAuth Client ID'); ?></label><input type="text" id="ap-omniroute-gcid" value="<?php echo my_htmlspecialchars($_apOmnirouteCfg['google_client_id']??''); ?>" placeholder="xxxx.apps.googleusercontent.com"></div>
                <div class="ap-field"><label><?php echo my_htmlspecialchars($translations['omniroute_google_client_secret']??'Google OAuth Client Secret'); ?></label><div class="ap-pwd-wrap"><input type="password" id="ap-omniroute-gcsecret" value="<?php echo my_htmlspecialchars($_apOmnirouteCfg['google_client_secret']??''); ?>" placeholder="GOCSPX-..."><button type="button" class="ap-pwd-eye" data-target="ap-omniroute-gcsecret"><svg class="ap-eye-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg><svg class="ap-eye-off" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg></button></div></div>
                <div class="ap-btn-row"><button type="button" id="ap-save-omniroute" class="ap-btn ap-btn-primary"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg><?php echo my_htmlspecialchars($translations['save']??'Save'); ?></button></div>
                <div class="ap-status" id="ap-omniroute-status"></div>
            </div>
            <div class="ap-card ap-settings-card" data-settings-card="onlyoffice">
                <div class="ap-card-title"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M5 4h10a2 2 0 0 1 2 2v3h-2V6H5v12h6v2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><path d="M14 12v-2l5 3-5 3v-2H9v-2h5z"/></svg><?php echo my_htmlspecialchars($translations['onlyoffice_title']??'OnlyOffice'); ?></div>
                <div class="ap-field"><label><?php echo my_htmlspecialchars($translations['onlyoffice_token_label']??'JWT Token'); ?></label><div class="ap-pwd-wrap"><input type="password" id="ap-onlyoffice-token" value="<?php echo my_htmlspecialchars($_apOnlyofficeToken??''); ?>" placeholder="your-onlyoffice-jwt-token"><button type="button" class="ap-pwd-eye" data-target="ap-onlyoffice-token"><svg class="ap-eye-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg><svg class="ap-eye-off" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg></button></div><div class="ap-hint"><?php echo my_htmlspecialchars($translations['onlyoffice_token_hint']??'Stored in the config table with key onlyoffice_jwt_secret.'); ?></div></div>
                <div class="ap-btn-row"><button type="button" id="ap-save-onlyoffice" class="ap-btn ap-btn-primary"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg><?php echo my_htmlspecialchars($translations['save']??'Save'); ?></button></div>
                <div class="ap-status" id="ap-onlyoffice-status"></div>
            </div>

            <!-- Backup Settings -->
            <div class="ap-card ap-settings-card" data-settings-card="backup">
                <div class="ap-card-title"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg><?php echo my_htmlspecialchars($translations['backup_settings']??'Backup Credentials'); ?></div>
                <div class="ap-field"><label><?php echo my_htmlspecialchars($translations['backup_host_label']??'Duplicati URL'); ?></label><input type="text" id="ap-backup-host" value="" placeholder="http://127.0.0.1:8200"><div class="ap-hint"><?php echo my_htmlspecialchars($translations['backup_host_hint']??'URL reachable from the AppDashboard server.'); ?></div></div>
                <div class="ap-field"><label><?php echo my_htmlspecialchars($translations['backup_token_label']??'API Token (Forever Token)'); ?></label><div class="ap-pwd-wrap"><input type="password" id="ap-backup-token" value="" placeholder="JWT token; Bearer prefix is optional"><button type="button" class="ap-pwd-eye" data-target="ap-backup-token"><svg class="ap-eye-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg><svg class="ap-eye-off" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg></button></div><div class="ap-hint"><?php echo my_htmlspecialchars($translations['backup_credentials_hint']??'Stored encrypted in the database and used by the Backup manager.'); ?></div></div>
                <div class="ap-btn-row">
                    <button type="button" id="ap-save-backup-creds" class="ap-btn ap-btn-primary"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg><?php echo my_htmlspecialchars($translations['save']??'Save'); ?></button>
                    <button type="button" id="ap-delete-backup-creds" class="ap-btn ap-btn-danger"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM8 9h8v10H8V9zm7.5-5l-1-1h-5l-1 1H5v2h14V4z"/></svg><?php echo my_htmlspecialchars($translations['delete']??'Delete'); ?></button>
                </div>
                <div class="ap-status" id="ap-backup-creds-status"></div>
            </div>
            <div class="ap-card ap-settings-card" data-settings-card="ai" style="overflow-x:auto;">
                <div class="ap-card-title"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></svg><?php echo my_htmlspecialchars($translations['changeApiKey']??'Change API Key'); ?></div>
                <div class="ap-field"><label><?php echo my_htmlspecialchars($translations['search_engine_url']??'Search Engine URL (SearXNG)'); ?></label><input type="text" id="ap-search-engine-url" value="<?php echo my_htmlspecialchars(getConfig('search_engine_url', '')); ?>" placeholder="https://search.example.com" autocomplete="off" style="font-family:monospace;font-size:12px;"><div class="ap-hint"><?php echo my_htmlspecialchars($translations['search_engine_url_hint']??'SearXNG instance URL. Used for AI web search and recipe image search. JSON format must be enabled on the instance.'); ?></div></div>
                <div class="ap-field"><label><?php echo my_htmlspecialchars($translations['apiEndpoint']??'API Endpoint'); ?></label><input type="text" id="ap-ai-endpoint" value="<?php echo my_htmlspecialchars($_apAiCfg['api_endpoint']??''); ?>" placeholder="https://ai.example.com/v1/chat/completions" autocomplete="off" style="font-family:monospace;font-size:12px;"></div>
                <div class="ap-field"><label><?php echo my_htmlspecialchars($translations['currentApiKey']??'Current API Key'); ?></label><div class="ap-pwd-wrap"><input type="password" id="ap-ai-current-key" value="<?php echo my_htmlspecialchars($_apAiCfg['api_key']??''); ?>" readonly autocomplete="off" style="font-family:monospace;font-size:12px;"><button type="button" class="ap-pwd-eye" data-target="ap-ai-current-key"><svg class="ap-eye-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg><svg class="ap-eye-off" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg></button></div></div>
                <div class="ap-field"><label><?php echo my_htmlspecialchars($translations['ai_models_label']??'Models (one per line)'); ?></label><textarea id="ap-ai-models" rows="4" style="width:100%;font-family:monospace;font-size:12px;resize:vertical;"><?php echo my_htmlspecialchars(implode("\n", $_apAiCfg['models'] ?? [])); ?></textarea></div>
                <div class="ap-field"><label><?php echo my_htmlspecialchars($translations['ai_default_model_label']??'Default model'); ?></label><input type="text" id="ap-ai-default-model" value="<?php echo my_htmlspecialchars($_apAiCfg['default_model']??''); ?>" placeholder="nvidia/openai/gpt-oss-120b" autocomplete="off" style="font-family:monospace;font-size:12px;"></div>
                <div class="ap-field"><label><?php echo my_htmlspecialchars($translations['newApiKey']??'New API Key'); ?></label><textarea id="ap-ai-new-key" rows="2" placeholder="sk-..." autocomplete="off" style="width:100%;font-family:monospace;font-size:12px;resize:vertical;"></textarea></div>
                <div class="ap-field"><label><?php echo my_htmlspecialchars($translations['confirmNewApiKey']??'Confirm New API Key'); ?></label><textarea id="ap-ai-confirm-key" rows="2" placeholder="sk-..." autocomplete="off" style="width:100%;font-family:monospace;font-size:12px;resize:vertical;"></textarea></div>
                <div id="ap-ai-key-match" class="ap-hint" style="display:none;"></div>
                <div class="ap-hint" style="color:#ffc107;border:1px solid rgba(255,193,7,.35);background:rgba(255,193,7,.08);padding:10px;border-radius:6px;"><?php echo my_htmlspecialchars($translations['apiKeyChangeWarning']??'Changing the API key will affect all AI assistant functionality. Make sure you have the correct key.'); ?></div>
                <div class="ap-btn-row"><button type="button" id="ap-save-ai-config" class="ap-btn ap-btn-primary"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg><?php echo my_htmlspecialchars($translations['save']??'Save'); ?></button></div>
                <div class="ap-status" id="ap-ai-config-status"></div>
            </div>

        </div>
        <!-- Docker Manager Panel -->
        
<div class="ap-panel" id="ap-docker-manager" style="flex:1; min-height:0; overflow:hidden; margin:0; padding:0;">
            <div style="flex:1; min-height:0; width:100%; position:relative; overflow:hidden;">
                <iframe id="ap-docker-frame" name="devtools-docker" title="Docker Manager" src="/project/docker/index.php?embed=1&amp;lang=<?php echo urlencode($language); ?>&amp;theme=<?php echo urlencode($themeForAppLink); ?>&amp;color=<?php echo urlencode($config['color'] ?? '#007bff'); ?>"
                        title="<?php echo my_htmlspecialchars($translations['docker_manager_title'] ?? 'Docker Manager'); ?>"
                        style="position:absolute; inset:0; width:100%; height:100%; border:0; display:block; border-radius:0;">
                </iframe>
            </div>
        </div>
        <div class="ap-panel" id="ap-performance" style="flex:1; min-height:0; overflow:hidden; margin:0; padding:0;">
            <div style="flex:1; min-height:0; width:100%; position:relative; overflow:hidden;">
                <iframe id="ap-performance-frame" name="devtools-performance" title="Performance Monitor" src="/project/performance/index.php?embed=1&amp;lang=<?php echo urlencode($language); ?>&amp;theme=<?php echo urlencode($themeForAppLink); ?>&amp;color=<?php echo urlencode($config['color'] ?? '#007bff'); ?>"
                        title="<?php echo my_htmlspecialchars($translations['performance_monitor_title'] ?? 'Performance'); ?>"
                        style="position:absolute; inset:0; width:100%; height:100%; border:0; display:block; border-radius:0;">
                </iframe>
            </div>
        </div>
        <div class="ap-panel" id="ap-apps-access">
            <div class="ap-breadcrumb"><?php echo my_htmlspecialchars($translations['admin_panel_title']??'Admin Panel'); ?> / <span><?php echo my_htmlspecialchars($translations['ap_app_access_title']??'App access'); ?></span></div>
            <div class="ap-page-title"><?php echo my_htmlspecialchars($translations['ap_app_access_title']??'App access'); ?></div>
            <div class="ap-page-subtitle"><?php echo my_htmlspecialchars($translations['ap_app_access_subtitle']??'Minimum role required to open each app. Default is Administrator (2).'); ?></div>
            <div class="ap-card">
                <div class="ap-card-title"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm7.93 9h-3.02a15.7 15.7 0 0 0-1.12-5A8.03 8.03 0 0 1 19.93 11zM12 4.04c.83 1.2 1.48 3.07 1.77 4.96h-3.54C10.52 7.11 11.17 5.24 12 4.04zM4.07 13h3.02c.15 1.75.55 3.5 1.12 5a8.03 8.03 0 0 1-4.14-5zm3.02-2H4.07a8.03 8.03 0 0 1 4.14-5 15.7 15.7 0 0 0-1.12 5zM12 19.96c-.83-1.2-1.48-3.07-1.77-4.96h3.54c-.29 1.89-.94 3.76-1.77 4.96zM14.18 13H9.82a13.3 13.3 0 0 1 0-2h4.36a13.3 13.3 0 0 1 0 2zm1.61 5c.57-1.5.97-3.25 1.12-5h3.02a8.03 8.03 0 0 1-4.14 5z"/></svg><?php echo my_htmlspecialchars($translations['ap_geo_access_title']??'Country and VPN restrictions'); ?></div>
                <div class="ap-world-map" id="ap-world-map" data-countries="<?php echo my_htmlspecialchars(json_encode($_apCountries, JSON_UNESCAPED_UNICODE)); ?>" data-selected="<?php echo my_htmlspecialchars(json_encode($_apAccessSec['blocked_countries'], JSON_UNESCAPED_UNICODE)); ?>" data-host-country="<?php echo my_htmlspecialchars($_apHostCountry); ?>"></div>
                <label class="ap-toggle-row check-label"><input type="checkbox" id="ap-block-vpn-traffic" class="checkbox" <?php echo !empty($_apAccessSec['block_vpn'])?'checked':''; ?>> <span class="checkbox-box"><svg width="14" height="11" viewBox="0 0 24 18" fill="none"><path d="M2 9L8.5 15.5L22 2" stroke="#111113" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" /></svg></span><span><?php echo my_htmlspecialchars($translations['ap_block_vpn_traffic']??'Block VPN/proxy/hosting traffic'); ?></span></label>
                <div class="ap-hint"><?php echo my_htmlspecialchars($translations['ap_geo_access_hint']??'Requests from selected countries are blocked when opening apps. VPN detection uses client IP metadata when available.'); ?></div>
            </div>
            <div class="ap-card">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                    <label style="font-size:12px;opacity:.75;white-space:nowrap;"><?php echo my_htmlspecialchars($translations['ap_sort_by']??'Sort:'); ?></label>
                    <select id="ap-app-access-sort" style="background:var(--bg-color);border:1px solid var(--border-color);color:var(--text-color);border-radius:4px;padding:4px 8px;font-size:12px;">
                        <option value="pop"><?php echo my_htmlspecialchars($translations['ap_sort_popularity']??'Popularity'); ?></option>
                        <option value="name"><?php echo my_htmlspecialchars($translations['ap_sort_name']??'Name'); ?></option>
                        <option value="role"><?php echo my_htmlspecialchars($translations['ap_sort_by_rights']??'Required role'); ?></option>
                    </select>
                    <button type="button" class="ap-btn ap-btn-primary" id="ap-save-app-access"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg><?php echo my_htmlspecialchars($translations['save']??'Save'); ?></button>
                </div>
                <div id="ap-app-access-grid" class="ap-app-access-grid"><?php foreach($_apAccessApps as $_aa): $_fh=my_htmlspecialchars($_aa['folder']); $_nh=my_htmlspecialchars($_aa['name']); $_mr=(int)$_aa['min_role']; $_vh=(int)$_aa['visits']; $_isSvg=!empty($_aa['is_svg']); $_ga=mt_rand(110,165);$_ghx=mt_rand(18,40);$_ghy=mt_rand(14,34);$_gl=mt_rand(24,44);$_gf=mt_rand(46,66);$_gs=mt_rand(48,66);$_gm=mt_rand(38,62);$_ge=mt_rand(50,74); ?>
                    <div class="ap-app-access-tile" data-folder="<?php echo $_fh; ?>" data-name="<?php echo $_nh; ?>" data-visits="<?php echo $_vh; ?>" data-role="<?php echo $_mr; ?>">
                        <?php if($_isSvg): ?><span class="ap-app-access-icon-svg" style="--icon-src:url('<?php echo my_htmlspecialchars($_aa['icon']); ?>');--g-angle:<?php echo $_ga; ?>deg;--g-hx:<?php echo $_ghx; ?>%;--g-hy:<?php echo $_ghy; ?>%;--g-light:<?php echo $_gl; ?>%;--g-fade:<?php echo $_gf; ?>%;--g-start:<?php echo $_gs; ?>%;--g-mid:<?php echo $_gm; ?>%;--g-end:<?php echo $_ge; ?>%;" aria-label="<?php echo $_nh; ?>" role="img"></span><?php else: ?><img src="<?php echo my_htmlspecialchars($_aa['icon']); ?>" alt="" class="ap-app-access-icon" width="40" height="40"><?php endif; ?>
                        <div class="ap-app-access-title"><?php echo $_nh; ?></div>
                        <select class="ap-app-access-level" data-folder="<?php echo $_fh; ?>" aria-label="<?php echo $_nh; ?>">
                            <option value="0" <?php echo $_mr===0?'selected':''; ?>><?php echo my_htmlspecialchars($translations['guest']??'Guest'); ?> (0)</option>
                            <option value="1" <?php echo $_mr===1?'selected':''; ?>><?php echo my_htmlspecialchars($translations['manager']??'Manager'); ?> (1)</option>
                            <option value="2" <?php echo $_mr===2?'selected':''; ?>><?php echo my_htmlspecialchars($translations['admin']??'Admin'); ?> (2)</option>
                        </select>
                    </div>
                <?php endforeach; ?></div>
                <p style="font-size:11px;opacity:.5;margin:10px 0 0 0;font-family:sans-serif;"><?php echo my_htmlspecialchars($translations['ap_app_access_hint']??'Users below the required role do not see the app in the menu.'); ?></p>
                <div class="ap-status" id="ap-app-access-status"></div>
            </div>
        </div>
        <!-- Statistics Panel -->
        <div class="ap-panel" id="ap-stats">
            <div class="ap-breadcrumb"><?php echo my_htmlspecialchars($translations['admin_panel_title']??'Admin Panel'); ?> / <span><?php echo my_htmlspecialchars($translations['stats_graph']??'Statistics'); ?></span></div>
            <div class="ap-page-title"><?php echo my_htmlspecialchars($translations['stats_graph']??'Visit Statistics'); ?></div>
            <div class="ap-page-subtitle"><?php echo my_htmlspecialchars($translations['ap_stats_subtitle']??'Visit statistics and disk usage per user.'); ?></div>
            <!-- Live chart preview -->
            <div class="ap-card" style="padding:14px 20px 16px 20px;">
                <div class="ap-card-title" style="margin-bottom:10px;flex-wrap:wrap;gap:8px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0;"><path d="M3 3v18h18V3H3zm16 16H5V5h14v14zM7 12h2v5H7v-5zm4-2h2v7h-2v-7zm4 4h2v3h-2v-3z"/></svg>
                    <?php echo my_htmlspecialchars($translations['stats_graph']??'Chart Preview'); ?>
                    <!-- Range controls -->
                    <div id="ap-stats-range-controls" style="margin-left:auto;display:flex;align-items:center;gap:6px;font-weight:400;text-transform:none;letter-spacing:0;">
                        <button type="button" id="ap-chart-range-7"   class="ap-btn ap-btn-secondary ap-chart-range-btn" data-range="7"   style="padding:3px 9px;font-size:11px;">7d</button>
                        <button type="button" id="ap-chart-range-30"  class="ap-btn ap-btn-secondary ap-chart-range-btn" data-range="30"  style="padding:3px 9px;font-size:11px;">30d</button>
                        <button type="button" id="ap-chart-range-90"  class="ap-btn ap-btn-secondary ap-chart-range-btn" data-range="90"  style="padding:3px 9px;font-size:11px;">90d</button>
                        <select id="ap-chart-year-select" style="background:var(--bg-color);border:1px solid var(--border-color);color:var(--text-color);border-radius:4px;padding:2px 6px;font-size:11px;font-family:sans-serif;max-width:90px;"></select>
                        <span style="opacity:.4;font-size:11px;margin:0 2px;">|</span>
                        <label style="font-size:11px;opacity:.6;white-space:nowrap;"><?php echo my_htmlspecialchars($translations['date_label']??'From'); ?>:</label>
                        <input type="date" id="ap-chart-date-from" style="background:var(--bg-color);border:1px solid var(--border-color);color:var(--text-color);border-radius:4px;padding:2px 6px;font-size:11px;font-family:sans-serif;max-width:130px;">
                        <label style="font-size:11px;opacity:.6;white-space:nowrap;"><?php echo my_htmlspecialchars($translations['ap_sort_modified']??'To'); ?>:</label>
                        <input type="date" id="ap-chart-date-to"   style="background:var(--bg-color);border:1px solid var(--border-color);color:var(--text-color);border-radius:4px;padding:2px 6px;font-size:11px;font-family:sans-serif;max-width:130px;">
                        <button type="button" id="ap-refresh-chart" class="ap-btn ap-btn-secondary" style="padding:3px 8px;font-size:11px;">&#x21BA;</button>
                        <button type="button" id="ap-toggle-analysis" class="ap-btn ap-btn-secondary" style="padding:3px 9px;font-size:11px;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:3px;"><path d="M11 13H7"/><path d="M19 9h-4"/><path d="M3 3v16a2 2 0 0 0 2 2h16"/><rect x="15" y="5" width="4" height="12" rx="1"/><rect x="7" y="8" width="4" height="9" rx="1"/></svg>
                            <?php echo my_htmlspecialchars($translations['stats_show_analysis']??'Show Analysis'); ?>
                        </button>
                    </div>
                </div>
                <div style="position:relative;height:220px;width:100%;"><canvas id="ap-stats-canvas"></canvas></div>
            </div>
            <!-- Analysis Mode Section -->
            <div id="ap-stats-analysis" class="ap-card" style="display:none;padding:20px;margin-top:14px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                    <div class="ap-card-title" style="margin:0;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin-right:6px;"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                        <?php echo my_htmlspecialchars($translations['stats_analysis_mode']??'Analysis Mode'); ?>
                    </div>
                    <span style="font-size:11px;opacity:.6;"><?php echo my_htmlspecialchars($translations['stats_analysis_description']??'Insights based on usage patterns for the selected year'); ?></span>
                </div>
                
                <!-- Summary Cards -->
                <div id="ap-analysis-summary" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:20px;"></div>
                
                <!-- Pie Chart and Popular Apps Grid -->
                <div class="ap-analysis-grid">
                    <!-- Pie Chart -->
                    <div class="ap-analysis-box">
                        <div style="font-weight:600;margin-bottom:10px;font-size:13px;"><?php echo my_htmlspecialchars($translations['stats_load_distribution']??'App Load Distribution'); ?></div>
                        <div style="position:relative;height:280px;"><canvas id="ap-pie-canvas"></canvas></div>
                    </div>
                    <!-- Popular Apps -->
                    <div class="ap-analysis-box">
                        <div style="font-weight:600;margin-bottom:10px;font-size:13px;"><?php echo my_htmlspecialchars($translations['stats_popular_apps']??'Most Popular Apps'); ?></div>
                        <div id="ap-popular-apps" style="max-height:280px;overflow-y:auto;"></div>
                    </div>
                </div>
                
                <!-- Peak Load Days -->
                <div style="background:var(--group-block-bg);border-radius:8px;padding:14px;border:1px solid var(--border-color);">
                    <div style="font-weight:600;margin-bottom:10px;font-size:13px;"><?php echo my_htmlspecialchars($translations['stats_peak_load_days']??'Peak Load Days'); ?></div>
                    <div id="ap-peak-days" style="max-height:200px;overflow-y:auto;"></div>
                </div>
            </div>
            <!-- Per-user storage usage -->
            <div class="ap-card" id="ap-storage-usage-card">
                <div class="ap-card-title" style="flex-wrap:wrap;gap:8px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0;"><path d="M3 3v18h18V3H3zm16 16H5V5h14v14zM7 12h2v5H7v-5zm4-2h2v7h-2v-7zm4 4h2v3h-2v-3z"/></svg>
                    <span id="ap-storage-usage-title"><?php echo my_htmlspecialchars($translations['ap_storage_usage_title']??'Disk Usage by User'); ?></span>
                    <span id="ap-storage-usage-meta" style="margin-left:auto;font-weight:400;text-transform:none;letter-spacing:0;font-size:11px;opacity:.6;"></span>
                    <button type="button" id="ap-storage-usage-refresh" class="ap-btn ap-btn-secondary" style="padding:3px 9px;font-size:11px;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:3px;"><path d="M21 12a9 9 0 1 1-3-6.7"></path><path d="M21 3v6h-6"></path></svg>
                        <span id="ap-storage-usage-refresh-label"><?php echo my_htmlspecialchars($translations['ap_storage_usage_refresh']??'Refresh'); ?></span>
                    </button>
                </div>
                <div style="position:relative;height:280px;width:100%;"><canvas id="ap-storage-usage-canvas"></canvas></div>
                <div id="ap-storage-usage-empty" style="display:none;font-size:12px;opacity:.65;padding:18px 0;text-align:center;"><?php echo my_htmlspecialchars($translations['ap_storage_usage_empty']??'No storage usage data collected yet.'); ?></div>
                <div id="ap-storage-usage-sources" style="margin-top:12px;display:flex;flex-wrap:wrap;gap:6px 14px;font-size:11px;opacity:.7;"></div>
                <div class="ap-status" id="ap-storage-usage-status" style="margin:10px 0 0;"></div>
            </div>
        </div>
        <!-- SSH Terminal Panel -->
        <div class="ap-panel" id="ap-ssh">
            <div class="ap-ssh-layout">
                <div class="ap-ssh-topbar">
                    <div style="min-width:0;">
                        <div class="ap-breadcrumb" style="margin:0 0 6px 0;">
                            <?php echo my_htmlspecialchars($translations['admin_panel_title']??'Admin Panel'); ?> / <span style="opacity:.9;">SSH Terminal</span>
                        </div>
                        <div class="ap-page-title" style="margin:0;font-size:16px;">SSH Terminal</div>
                    </div>
                    <button type="button" id="ap-ssh-settings-btn" class="ap-btn ap-btn-secondary" style="padding:7px 12px;border-radius:8px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 1v2"></path>
                            <path d="M12 21v2"></path>
                            <path d="M4.22 4.22l1.42 1.42"></path>
                            <path d="M18.36 18.36l1.42 1.42"></path>
                            <path d="M1 12h2"></path>
                            <path d="M21 12h2"></path>
                            <path d="M4.22 19.78l1.42-1.42"></path>
                            <path d="M18.36 5.64l1.42-1.42"></path>
                            <circle cx="12" cy="12" r="7"></circle>
                        </svg>
                    </button>
                </div>
                <!-- Quick Commands Buttons Bar -->
                <div id="ap-ssh-quick-commands-bar">
                    <button type="button" id="ap-ssh-quick-commands-manage-btn" style="padding:5px 10px;background:var(--primary-color);color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:12px;font-weight:500;white-space:nowrap;" title="Manage Quick Commands">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:3px;">
                            <polyline points="13 17 9 12 13 7"></polyline>
                            <polyline points="18 17 14 12 18 7"></polyline>
                            <polyline points="4 17 0 12 4 7"></polyline>
                        </svg>
                        Manage Commands
                    </button>
                </div>
                <div class="ap-ssh-terminal-shell"><div id="ap-ssh-terminal"></div></div>
            </div>

            <!-- SSH Settings Modal -->
            <div id="ap-ssh-settings-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:10000;justify-content:center;align-items:center;">
                <div id="ap-ssh-settings-content" style="background:var(--modal-bg);color:var(--text-color);padding:28px;border-radius:12px;width:92%;max-width:640px;max-height:90vh;overflow:auto;box-shadow:0 8px 40px rgba(0,0,0,.3);position:relative;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px;">
                        <div style="font-weight:700;text-transform:uppercase;letter-spacing:.5px;font-size:13px;opacity:.9;display:flex;align-items:center;gap:8px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M19.14 12.94c.04-.3.06-.61.06-.94s-.02-.64-.06-.94l2.03-1.58a.5.5 0 0 0 .12-.64l-1.92-3.32a.5.5 0 0 0-.6-.22l-2.39.96a7.3 7.3 0 0 0-1.63-.94l-.36-2.54A.5.5 0 0 0 14.2 1h-4a.5.5 0 0 0-.49.42l-.36 2.54c-.59.23-1.13.54-1.63.94l-2.39-.96a.5.5 0 0 0-.6.22L2.8 7.48a.5.5 0 0 0 .12.64l2.03 1.58c-.04.3-.06.62-.06.94s.02.64.06.94L2.92 14.52a.5.5 0 0 0-.12.64l1.92 3.32c.13.22.39.3.6.22l2.39-.96c.5.4 1.04.71 1.63.94l.36 2.54c.05.24.25.42.49.42h4c.24 0 .44-.18.49-.42l.36-2.54c.59-.23 1.13-.54 1.63-.94l2.39.96c.21.08.47 0 .6-.22l1.92-3.32a.5.5 0 0 0-.12-.64l-2.03-1.58ZM12 15.5A3.5 3.5 0 1 1 12 8a3.5 3.5 0 0 1 0 7.5Z"></path>
                            </svg>
                            <?php echo my_htmlspecialchars($translations['ssh_profile']??'SSH Profile'); ?>
                        </div>
                        <button type="button" id="ap-ssh-settings-close" class="ap-btn ap-btn-secondary" style="padding:6px 10px;"><?php echo my_htmlspecialchars($translations['close']??'Close'); ?></button>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-bottom:10px;">
                        <div class="ap-field" style="margin:0;"><label><?php echo my_htmlspecialchars($translations['ssh_host']??'Host'); ?></label><input id="ap-ssh-host" type="text" value="127.0.0.1" placeholder="127.0.0.1"></div>
                        <div class="ap-field" style="margin:0;"><label><?php echo my_htmlspecialchars($translations['ssh_user']??'User'); ?></label><input id="ap-ssh-user" type="text" value="<?php echo my_htmlspecialchars(get_current_user() ?: 'root'); ?>" placeholder="root"></div>
                        <div class="ap-field" style="margin:0;"><label><?php echo my_htmlspecialchars($translations['ssh_port']??'Port'); ?></label><input id="ap-ssh-port" type="number" value="22" min="1" max="65535"></div>
                        <div class="ap-field" style="margin:0;"><label><?php echo my_htmlspecialchars($translations['ssh_backend']??'Backend'); ?></label><input type="text" value="WebSocket + PTY" readonly style="opacity:.75;"></div>
                        <?php
                        $apSshKeysDir = function_exists('apSshKeysDir') ? apSshKeysDir() : (appdata_data_dir() . '/.ssh');
                        $apSshDefaultKey = function_exists('apSshDiscoverKeyPath') ? apSshDiscoverKeyPath() : '';
                        ?>
                        <div class="ap-field" style="margin:0;grid-column:1/-1;"><label><?php echo my_htmlspecialchars($translations['ssh_key_path']??'SSH private key'); ?></label><input id="ap-ssh-key-path" type="text" value="<?php echo my_htmlspecialchars($apSshDefaultKey); ?>" placeholder="<?php echo my_htmlspecialchars($apSshKeysDir . '/id_ed25519'); ?>" readonly style="opacity:.85;"></div>
                        <div class="ap-field" style="margin:0;grid-column:1/-1;"><div class="ap-hint" id="ap-ssh-key-hint"><?php echo my_htmlspecialchars($translations['ssh_key_only_hint']??'Password login is disabled. Place id_ed25519 or id_rsa in data/.ssh/'); ?></div></div>
                        <div class="ap-field" style="margin:0;"><label><?php echo my_htmlspecialchars($translations['ssh_key_passphrase_optional']??'Key passphrase (optional)'); ?></label><input id="ap-ssh-key-passphrase" type="password" placeholder="<?php echo my_htmlspecialchars($translations['optional']??'optional'); ?>"></div>
                    </div>

                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:10px;">
                        <button type="button" id="ap-ssh-test-profile" class="ap-btn ap-btn-secondary"><?php echo my_htmlspecialchars($translations['ssh_test_connection']??'Test Connection'); ?></button>
                        <button type="button" id="ap-ssh-save-profile" class="ap-btn ap-btn-primary"><?php echo my_htmlspecialchars($translations['ssh_save_profile']??'Save SSH Profile'); ?></button>
                    </div>
                    <div class="ap-status" id="ap-ssh-modal-status" style="margin-top:12px;"></div>

                    <div class="ap-field" style="margin-top:14px;margin-bottom:0;">
                        <label><?php echo my_htmlspecialchars($translations['ssh_connection_test_output']??'Connection test output'); ?></label>
                        <pre id="ap-ssh-modal-connect-output" style="background:var(--bg-color);color:var(--text-color);border:1px solid var(--border-color);border-radius:6px;padding:12px;min-height:140px;max-height:40vh;overflow:auto;font-family:'Courier New',monospace;font-size:12px;line-height:1.45;white-space:pre-wrap;margin:0;"></pre>
                    </div>
                </div>
            </div>

            <!-- Quick Commands Management Modal -->
            <div id="ap-ssh-quick-commands-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:10001;justify-content:center;align-items:center;">
                <div style="background:var(--modal-bg);color:var(--text-color);padding:28px;border-radius:12px;width:95%;max-width:900px;max-height:90vh;overflow:auto;box-shadow:0 8px 40px rgba(0,0,0,.3);position:relative;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:20px;">
                        <div style="font-weight:700;text-transform:uppercase;letter-spacing:.5px;font-size:13px;opacity:.9;display:flex;align-items:center;gap:8px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polyline points="13 17 9 12 13 7"></polyline>
                                <polyline points="18 17 14 12 18 7"></polyline>
                                <polyline points="4 17 0 12 4 7"></polyline>
                            </svg>
                            Manage Quick Commands
                        </div>
                        <button type="button" id="ap-ssh-quick-commands-close" class="ap-btn ap-btn-secondary" style="padding:6px 10px;">Close</button>
                    </div>

                    <!-- Add/Edit Command Form -->
                    <div style="background:var(--group-block-bg);padding:16px;border-radius:8px;margin-bottom:20px;">
                        <div style="font-weight:600;margin-bottom:12px;font-size:14px;" id="ap-ssh-qc-form-title">Add New Command</div>
                        <input type="hidden" id="ap-ssh-qc-edit-id" value="">
                        <div style="display:grid;grid-template-columns:1fr 2fr 1fr;gap:10px;margin-bottom:12px;">
                            <div class="ap-field" style="margin:0;">
                                <label>Name</label>
                                <input id="ap-ssh-qc-name" type="text" placeholder="e.g., Start TeamViewer">
                            </div>
                            <div class="ap-field" style="margin:0;">
                                <label>Command</label>
                                <input id="ap-ssh-qc-command" type="text" placeholder="e.g., teamviewer">
                            </div>
                            <div class="ap-field" style="margin:0;">
                                <label>Directory (optional)</label>
                                <input id="ap-ssh-qc-directory" type="text" placeholder="e.g., /opt">
                            </div>
                        </div>
                        <div style="display:flex;gap:10px;">
                            <button type="button" id="ap-ssh-qc-save-btn" class="ap-btn ap-btn-primary">Save Command</button>
                            <button type="button" id="ap-ssh-qc-cancel-btn" class="ap-btn ap-btn-secondary" style="display:none;">Cancel</button>
                        </div>
                    </div>

                    <!-- Commands List -->
                    <div style="font-weight:600;margin-bottom:12px;font-size:14px;">Saved Commands</div>
                    <div id="ap-ssh-qc-list" style="display:grid;gap:10px;">
                        <div style="text-align:center;padding:20px;opacity:.6;">Loading...</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="ap-panel" id="ap-backup" style="flex:1; min-height:0; overflow:hidden; margin:0; padding:0;">
            <div style="flex:1; min-height:0; width:100%; position:relative; overflow:hidden;">
                <iframe id="ap-backup-frame" name="devtools-backup" title="Backup Dashboard" src="/backup_dashboard.php?embed=1&amp;lang=<?php echo urlencode($language); ?>&amp;theme=<?php echo urlencode($themeForAppLink); ?>&amp;color=<?php echo urlencode($config['color'] ?? '#007bff'); ?>"
                        title="<?php echo my_htmlspecialchars($translations['backup'] ?? 'Backup'); ?>"
                        style="position:absolute; inset:0; width:100%; height:100%; border:0; display:block; border-radius:0;">
                </iframe>
            </div>
        </div>
        <div class="ap-panel" id="ap-docs" style="flex:1; min-height:0; overflow:hidden; margin:0; padding:0;">
            <div style="flex:1; min-height:0; width:100%; position:relative; overflow:hidden;">
                <iframe name="devtools-docs" title="Documentation Viewer" src="/doc_viewer.php?embed=1&amp;lang=<?php echo urlencode($language); ?>&amp;theme=<?php echo urlencode($themeForAppLink); ?>&amp;color=<?php echo urlencode($config['color'] ?? '#007bff'); ?>"
                        title="<?php echo my_htmlspecialchars($translations['documentation'] ?? 'Documentation'); ?>"
                        style="position:absolute; inset:0; width:100%; height:100%; border:0; display:block; border-radius:0;">
                </iframe>
            </div>
        </div>
        <div class="ap-panel" id="ap-db" style="flex:1; min-height:0; overflow:hidden; margin:0; padding:0;">
            <div style="flex:1; min-height:0; width:100%; position:relative; overflow:hidden;">
                <iframe name="devtools-db" title="Database Admin" src="/db_admin.php?embed=1&amp;lang=<?php echo urlencode($language); ?>&amp;theme=<?php echo urlencode($themeForAppLink); ?>&amp;color=<?php echo urlencode($config['color'] ?? '#007bff'); ?>"
                        title="<?php echo my_htmlspecialchars($translations['database'] ?? 'Database'); ?>"
                        style="position:absolute; inset:0; width:100%; height:100%; border:0; display:block; border-radius:0;">
                </iframe>
            </div>
        </div>
        <div class="ap-panel" id="ap-omniroute" style="flex:1; min-height:0; overflow:hidden; margin:0; padding:0;">
            <div style="flex:1; min-height:0; width:100%; position:relative; overflow:hidden;">
                <iframe id="ap-omniroute-frame" name="devtools-omniroute" data-src="/omniroute-bridge?next=/admin-omniroute/home" title="<?php echo my_htmlspecialchars($translations['omniroute_title'] ?? 'OmniRoute'); ?>" style="position:absolute; inset:0; width:100%; height:100%; border:0; display:block; border-radius:0; background:#0b0d10;" allow="clipboard-read; clipboard-write; microphone"></iframe>
            </div>
        </div>
    </div><!-- #ap-content -->
</div><!-- #ap-body -->
<?php
        $html = ob_get_clean();
        // Get all available years first (lightweight query)
        $_apDb = getDB();
        $_apYearsResult = $_apDb->query("SELECT DISTINCT substr(stat_date, 1, 4) as year FROM visit_stats ORDER BY year DESC");
        $_apAvailableYears = [];
        while($_apRow = $_apYearsResult->fetch(PDO::FETCH_ASSOC)){
            $_apAvailableYears[] = $_apRow['year'];
        }
        // Load stats data - only for requested year to avoid overloading
        $_apStatsYear = $_POST['stats_year'] ?? date('Y');
        // If requested year is not in available years, use the most recent
        if(!in_array($_apStatsYear, $_apAvailableYears) && !empty($_apAvailableYears)){
            $_apStatsYear = $_apAvailableYears[0];
        }
        if($_apStatsYear && $_apStatsYear !== 'all'){
            // Load only the requested year's data
            $_apFromDate = $_apStatsYear . '-01-01';
            $_apToDate = $_apStatsYear . '-12-31';
            $_apVStats = json_encode(loadVisitStatsRange($_apFromDate, $_apToDate));
        } else {
            // Fallback to all data (shouldn't happen with new UI)
            $_apVStats = json_encode(loadVisitStats());
        }
        // Clean any stray output that may have been buffered before this handler
        while(ob_get_level() > 0) ob_end_clean();
        while(ob_get_level() > 1) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success'=>true,'html'=>$html,'stats'=>$_apVStats,'available_years'=>$_apAvailableYears,'storage_usage'=>json_encode(loadStorageUsageSnapshot()),'session_expires_at'=>(int)($_SESSION['admin_panel_gate_until'] ?? 0),'csrf'=>apSecCsrfToken()]);
        exit;
    }

    // Queue a storage usage refresh (admin only, AJAX) — the collector picks it up,
    // so this request never waits for the external databases.
    if(isset($_POST['refresh_storage_usage']) && isset($_POST['ajax']) && $sessionIsAdmin){
        storageUsageRequestRefresh();
        while(ob_get_level() > 1) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success'=>true,'message'=>$translations['ap_storage_usage_queued']??'Refresh queued']);
        exit;
    }

    // Scan user files (admin only, AJAX)
    if(isset($_POST['scan_user_files']) && isset($_POST['ajax']) && $sessionIsAdmin){
        $emailToScan = $_POST['user_email'] ?? '';
        $users = loadUsers();
        $name = isset($users[$emailToScan]) ? ($users[$emailToScan]['name'] ?? '') : '';
        $cfg = loadUserFoldersConfig();
        $files = scanUserFiles($emailToScan, $name, $cfg['apps'] ?? []);
        $totalSize = array_sum(array_column($files, 'size'));
        while(ob_get_level() > 1) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'files' => $files, 'total_size' => $totalSize, 'total_size_human' => formatBytes($totalSize)]);
        exit;
    }

    // Scan user files progressively with count (admin only, AJAX)
    if(isset($_POST['scan_user_files_progressive']) && isset($_POST['ajax']) && $sessionIsAdmin){
        $emailToScan = $_POST['user_email'] ?? '';
        $users = loadUsers();
        $name = isset($users[$emailToScan]) ? ($users[$emailToScan]['name'] ?? '') : '';
        $cfg = loadUserFoldersConfig();
        
        // Count files first for time estimation
        $countData = countUserFiles($emailToScan, $name, $cfg['apps'] ?? []);
        
        // Then perform the scan
        $files = scanUserFiles($emailToScan, $name, $cfg['apps'] ?? []);
        $totalSize = array_sum(array_column($files, 'size'));
        
        while(ob_get_level() > 1) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'files' => $files, 
            'total_files' => count($files),
            'total_size' => $totalSize, 
            'total_size_human' => formatBytes($totalSize),
            'estimated_total_files' => $countData['files'],
            'total_folders' => $countData['folders']
        ]);
        exit;
    }

    // Refresh users list (admin only, AJAX) — includes pending users, full rich HTML
    if(isset($_POST['get_users_table']) && isset($_POST['ajax']) && $sessionIsAdmin){
        $uList=loadUsers(); $pList=loadPendingUsers(); $allU=$uList;
        foreach($pList as $pe=>$pd){ if(!isset($allU[$pe])){ $pd['_pending']=true; $allU[$pe]=$pd; } }
        $curEmail=$_SESSION['user']['email']??''; $rows='';
        foreach($allU as $email=>$ud){
            $isPend=!empty($ud['_pending']); $isRoot=($email==='root@root');
            $isSelf=($email===$curEmail); $isVer=!empty($ud['verified']); $role=(int)($ud['role']??0);
            $eH=my_htmlspecialchars($email); $nH=my_htmlspecialchars($ud['name']??'');
            $ph=my_htmlspecialchars(app_avatar_src($ud['photo']??'default_avatar.png')); $dis=$isSelf?' disabled':'';
            // role cell
            if($isRoot) $rC='<span style="color:var(--primary-color);font-size:12px;font-weight:700;">Admin &#x1F512;</span>';
            elseif($isPend) $rC='<span style="color:#aaa;font-size:12px;">Guest</span>';
            else $rC='<select class="ap-role-select ap-user-field" data-email="'.$eH.'" data-field="role"'.$dis.'><option value="0" '.($role===0?'selected':'').'>Guest</option><option value="1" '.($role===1?'selected':'').'>Manager</option><option value="2" '.($role===2?'selected':'').'>Admin</option></select>';
            // lang cell
            if($isRoot||$isPend) $lC='<span style="opacity:.35;font-size:12px;">&#x2014;</span>';
            else { $l=my_htmlspecialchars($ud['language']??'en'); $lC='<select class="ap-role-select ap-user-field" data-email="'.$eH.'" data-field="language"'.$dis.'><option value="en" '.($l==='en'?'selected':'').'>EN</option><option value="ru" '.($l==='ru'?'selected':'').'>RU</option><option value="uk" '.($l==='uk'?'selected':'').'>UK</option><option value="be" '.($l==='be'?'selected':'').'>BE</option><option value="pl" '.($l==='pl'?'selected':'').'>PL</option><option value="cs" '.($l==='cs'?'selected':'').'>CS</option></select>'; }
            // theme cell
            if($isRoot||$isPend) $tC='<span style="opacity:.35;font-size:12px;">&#x2014;</span>';
            else { $t=my_htmlspecialchars($ud['theme']??'light'); $tC='<select class="ap-role-select ap-user-field" data-email="'.$eH.'" data-field="theme"'.$dis.'><option value="light" '.($t==='light'?'selected':'').'>'.my_htmlspecialchars($translations['theme_light']??'Light').'</option><option value="dark" '.($t==='dark'?'selected':'').'>'.my_htmlspecialchars($translations['theme_dark']??'Dark').'</option><option value="auto" '.($t==='auto'?'selected':'').'>'.my_htmlspecialchars($translations['theme_auto']??'Auto').'</option></select>'; }
            // color cell
            if($isRoot||$isPend) $cC='<span style="opacity:.35;font-size:12px;">&#x2014;</span>';
            else { $c=my_htmlspecialchars($ud['color']??'#007bff'); $cC='<input type="color" class="ap-user-field ap-color-picker" data-email="'.$eH.'" data-field="color" value="'.$c.'"'.$dis.' style="width:36px;height:28px;padding:2px;border:1px solid var(--border-color);border-radius:4px;background:var(--bg-color);cursor:pointer;">'; }
            // status cell
            if($isRoot) $sC='<span class="ap-verified-yes" style="font-size:16px;">&#x2713;</span>';
            elseif($isPend) $sC='<span style="color:#ff9800;font-weight:bold;font-size:12px;">&#x23F3; Pending</span>';
            else { $val=$isVer?'1':'0'; $cls=$isVer?'ap-verified-yes':'ap-verified-no'; $sym=$isVer?'&#x2713;':'&#x2717;'; $selfDis=$isSelf?' disabled title="You cannot change your own status" style="background:none;border:none;cursor:not-allowed;opacity:.45;padding:2px;font-size:16px;"':' style="background:none;border:none;cursor:pointer;padding:2px;font-size:16px;"'; $sC='<button type="button" class="ap-verified-toggle ap-user-field" data-email="'.$eH.'" data-field="verified" data-value="'.$val.'"'.$selfDis.'><span class="'.$cls.'" style="font-size:16px;">'.$sym.'</span></button>'; }
            $aC=renderApUserActionsCell($email, $ud['name']??'', $isRoot, $isSelf, $isPend, $translations);
            $rows.='<tr data-email="'.$eH.'" data-name="'.$nH.'">'
                .'<td><div style="display:flex;align-items:center;gap:8px;white-space:nowrap;"><img src="'.$ph.'" style="width:24px;height:24px;border-radius:50%;border:1px solid var(--border-color);object-fit:cover;flex-shrink:0;" alt="">'.$nH.'</div></td>'
                .'<td><span class="ap-copy-email" data-copy-email="'.$eH.'" tabindex="0" role="button" aria-label="Copy email" title="Click to copy email">'.$eH.'</span></td>'
                .'<td>'.$rC.'</td><td>'.$lC.'</td><td>'.$tC.'</td><td>'.$cC.'</td><td>'.$sC.'</td>'
                .'<td style="font-size:11px;opacity:.55;white-space:nowrap;">'.my_htmlspecialchars(substr($ud['created_at']??'&#x2014;',0,10)).'</td>'
                .'<td>'.$aC.'</td></tr>';
        }
        while(ob_get_level() > 1) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success'=>true,'rows'=>$rows]);
        exit;
    }

    if(isset($_POST['reset_user_password']) && isset($_POST['ajax']) && $sessionIsAdmin){
        apSecRequireSameOrigin();
$emailToReset = $_POST['user_email'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$currentUserEmail = $_SESSION['user']['email'] ?? '';
$response = ['success' => false, 'message' => ''];
if($emailToReset){
    $users = loadUsers();
    if(isset($users[$emailToReset])){
                if(($passwordErrorKey = validatePasswordPolicy($newPassword)) !== null){
                    $response['message'] = $translations[$passwordErrorKey] ?? $passwordErrorKey;
                }else{
                    $users[$emailToReset]['password'] = appPasswordHash($newPassword);
                    $users[$emailToReset]['must_change_password'] = 0;
                    saveUsers($users);
                    if($emailToReset === $currentUserEmail){
                        $_SESSION['user'] = $users[$emailToReset];
                        $_SESSION['user']['email'] = $emailToReset;
                    }
                    $response['success'] = true;
                    $response['message'] = $translations['password_reset_admin_success'] ?? 'Password reset successfully';
                    $response['password'] = $newPassword;
                }
            }else{
                $response['message'] = $translations['user_not_found'] ?? 'User not found';
            }
        }else{
            $response['message'] = $translations['invalid_request'] ?? 'Invalid request';
        }
        while(ob_get_level() > 1) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // Delete user with optional file deletion (admin only, AJAX)
    if(isset($_POST['delete_user_with_files']) && isset($_POST['ajax']) && $sessionIsAdmin){
        $emailToDelete = $_POST['user_email'] ?? '';
        $deleteFiles = isset($_POST['delete_files']) && $_POST['delete_files'] === '1';
        $currentUserEmail = $_SESSION['user']['email'] ?? '';
        $response = ['success' => false, 'message' => ''];
        if($emailToDelete && $emailToDelete !== $currentUserEmail && $emailToDelete !== 'root@root'){
            $deleted = false;
            // Delete from users table directly
            $userRow = getUserByEmail($emailToDelete);
            if($userRow){
                if($deleteFiles){
                    $cfg = loadUserFoldersConfig();
                    deleteUserFolders($emailToDelete, $userRow['name'] ?? '', $cfg['apps'] ?? []);
                }
                deleteUser($emailToDelete);
                removeUserFromActiveSessions($emailToDelete);
                $deleted = true;
            }
            // Delete from pending_users if present
            $pendingRow = getPendingUser($emailToDelete);
            if($pendingRow){
                deletePendingUser($emailToDelete);
                // Remove verification token
                $db = getDB();
                $st = $db->prepare('DELETE FROM verification_tokens WHERE email=:e');
                $st->bindValue(':e', $emailToDelete); $st->execute();
                $deleted = true;
            }
            if($deleted){
                $response['success'] = true;
                $response['message'] = $translations['user_deleted'];
            }
        }
        while(ob_get_level() > 1) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    if(isset($_POST['save_docker_config']) && isset($_POST['ajax']) && $sessionIsAdmin){
        $prevDocker = loadDockerConfig();
        $cfg = [
            'host' => $prevDocker['host'] ?? 'http://localhost:9000',
            'domain' => 'localhost',
            'token' => trim($_POST['docker_token'] ?? ''),
            'username' => trim($_POST['docker_username'] ?? ''),
            'password' => trim($_POST['docker_password'] ?? '')
        ];
        saveDockerConfig($cfg);
        header('Content-Type: application/json');
        echo json_encode(['success'=>true,'message'=>$translations['docker_saved']??'Docker settings saved']);
        exit;
    }
    if(isset($_POST['load_ai_config']) && isset($_POST['ajax']) && $sessionIsAdmin){
        require_once __DIR__ . '/../apps/cloud/includes/ai_config_store.php';
        header('Content-Type: application/json');
        echo json_encode(cloud_ai_load_config(), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if(isset($_POST['save_ai_config']) && isset($_POST['ajax']) && $sessionIsAdmin){
        require_once __DIR__ . '/../apps/cloud/includes/ai_config_store.php';
        $searchEngineUrl = trim((string)($_POST['search_engine_url'] ?? ''));
        if ($searchEngineUrl !== '') {
            $searchEngineUrl = rtrim($searchEngineUrl, '/');
        }
        setConfig('search_engine_url', $searchEngineUrl);
        $payload = [
            'api_endpoint' => trim((string)($_POST['api_endpoint'] ?? '')),
            'default_model' => trim((string)($_POST['default_model'] ?? '')),
            'models' => trim((string)($_POST['models'] ?? '')),
        ];
        $newKey = trim((string)($_POST['api_key'] ?? ''));
        if ($newKey !== '') {
            $payload['api_key'] = $newKey;
        }
        $result = cloud_ai_save_config($payload);
        header('Content-Type: application/json');
        if (isset($result['error'])) {
            echo json_encode(['success'=>false,'error'=>$result['error']], JSON_UNESCAPED_UNICODE);
        } else {
            $savedConfig = cloud_ai_load_config();
            echo json_encode(['success'=>true,'message'=>$translations['ai_config_saved']??'AI configuration saved','config'=>$savedConfig,'has_key'=>trim((string)($savedConfig['api_key'] ?? '')) !== ''], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
    if(isset($_POST['save_onlyoffice_config']) && isset($_POST['ajax']) && $sessionIsAdmin){
        $onlyofficeToken = trim((string)($_POST['onlyoffice_token'] ?? ''));
        setConfig('onlyoffice_jwt_secret', $onlyofficeToken);
        header('Content-Type: application/json');
        echo json_encode(['success'=>true,'message'=>$translations['onlyoffice_saved']??'OnlyOffice token saved']);
        exit;
    }
    if(isset($_POST['save_omniroute_oauth_config']) && isset($_POST['ajax']) && $sessionIsAdmin){
        $omnirouteCfg = [
            'google_client_id' => trim((string)($_POST['omniroute_google_client_id'] ?? '')),
            'google_client_secret' => trim((string)($_POST['omniroute_google_client_secret'] ?? '')),
        ];
        saveOmnirouteOAuthConfig($omnirouteCfg);
        $syncResult = syncOmnirouteOAuthEnv($omnirouteCfg);
        header('Content-Type: application/json');
        echo json_encode($syncResult);
        exit;
    }
    if(isset($_POST['oauth_unlink']) && isset($_SESSION['user']['email'])){
        $provider = oauthNormalizeProvider((string)($_POST['provider'] ?? ''));
        $email = (string)$_SESSION['user']['email'];
        $users = loadUsers();
        $connections = getOAuthConnectionsForUser($email);
        if (!isset($connections[$provider])) {
            $profileError = $translations['oauth_not_linked'] ?? 'Provider is not linked';
        } elseif (isset($users[$email]) && userNeedsPasswordSet($users[$email]) && count($connections) <= 1) {
            $profileError = $translations['oauth_unlink_password_required'] ?? 'Set a password before unlinking your only sign-in method';
        } else {
            unlinkOAuthConnection($email, $provider);
            $profileSuccess = $translations['oauth_unlinked_success'] ?? 'Account unlinked successfully';
        }
    }
    if(isset($_POST['save_email_settings']) && $sessionIsAdmin){
        $isAjaxEmail = isset($_POST['ajax']);
        $existingCfg = loadAuthConfig();
        $authConfig['google_client_id']=$_POST['google_client_id']??'';
        $authConfig['google_client_secret']=$_POST['google_client_secret']??'';
        $authConfig['github_client_id']=$_POST['github_client_id']??'';
        $authConfig['github_client_secret']=$_POST['github_client_secret']??'';
        $authConfig['smtp_host']=trim((string)($_POST['smtp_host']??''));
        $authConfig['smtp_port']=intval($_POST['smtp_port']??587);
        $authConfig['smtp_username']=trim((string)($_POST['smtp_username']??''));
        $newSmtpPassword=(string)($_POST['smtp_password']??'');
        $authConfig['smtp_password']=$newSmtpPassword!==''?$newSmtpPassword:(string)($existingCfg['smtp_password']??'');
        $authConfig['turnstile_site_key']=trim((string)($_POST['turnstile_site_key']??''));
        $authConfig['turnstile_secret_key']=trim((string)($_POST['turnstile_secret_key']??''));
        saveAuthConfig($authConfig);
        $saved = true;
        if($isAjaxEmail){
            header('Content-Type: application/json');
            echo json_encode(['success'=>$saved,'message'=>$saved?$translations['email_settings_saved']:$translations['email_settings_error']]);
            exit;
        }
        if($saved){$emailSettingsSuccess=$translations['email_settings_saved'];}
        else{$emailSettingsError=$translations['email_settings_error'];}
    }
    if(isset($_POST['delete_user']) && $sessionIsAdmin){
        $emailToDelete=$_POST['user_email']??'';
        $currentUserEmail=$_SESSION['user']['email']??'';
        if($emailToDelete&&$emailToDelete!==$currentUserEmail&&$emailToDelete!=='root@root'){
            if(getUserByEmail($emailToDelete)){
                deleteUser($emailToDelete);
                removeUserFromActiveSessions($emailToDelete);
                $userManagementSuccess=$translations['user_deleted'];
            }
            $pendingRow=getPendingUser($emailToDelete);
            if($pendingRow){
                deletePendingUser($emailToDelete);
                $db=getDB();
                $st=$db->prepare('DELETE FROM verification_tokens WHERE email=:e');
                $st->bindValue(':e',$emailToDelete);$st->execute();
                $userManagementSuccess=$translations['user_deleted'];
            }
        }
    }
    if(isset($_POST['save_user_roles'])){
        $currentUserEmail=$_SESSION['user']['email']??'';
        $isAjaxRoles = isset($_POST['ajax']);
        if($currentUserEmail==='root@root'){
            $users=loadUsers();
            $anyUpdated=false;
            // Roles
            if(isset($_POST['user_roles']) && is_array($_POST['user_roles'])){
                foreach($_POST['user_roles'] as $email=>$role){
                    if($email==='root@root') continue;
                    if($email!==$currentUserEmail && isset($users[$email])){
                        $users[$email]['role']=intval($role);
                        $anyUpdated=true;
                    }
                }
            }
            // Languages
            if(isset($_POST['user_languages']) && is_array($_POST['user_languages'])){
                $allowed_langs=['en','ru','uk','be'];
                foreach($_POST['user_languages'] as $email=>$lang){
                    if($email==='root@root') continue;
                    if(isset($users[$email]) && in_array($lang, $allowed_langs)){
                        $users[$email]['language']=$lang;
                        $anyUpdated=true;
                    }
                }
            }
            // Themes
            if(isset($_POST['user_themes']) && is_array($_POST['user_themes'])){
                $allowed_themes=['light','dark','auto'];
                foreach($_POST['user_themes'] as $email=>$theme){
                    if($email==='root@root') continue;
                    if(isset($users[$email]) && in_array($theme, $allowed_themes)){
                        $users[$email]['theme']=$theme;
                        $anyUpdated=true;
                    }
                }
            }
            // Colors
            if(isset($_POST['user_colors']) && is_array($_POST['user_colors'])){
                foreach($_POST['user_colors'] as $email=>$color){
                    if($email==='root@root') continue;
                    if(isset($users[$email]) && preg_match('/^#[0-9a-fA-F]{6}$/', $color)){
                        $users[$email]['color']=$color;
                        $anyUpdated=true;
                    }
                }
            }
            // Verified toggle
            if(isset($_POST['user_verified']) && is_array($_POST['user_verified'])){
                foreach($_POST['user_verified'] as $email=>$v){
                    if($email==='root@root') continue;
                    if(isset($users[$email])){
                        $users[$email]['verified']=(bool)intval($v);
                        $anyUpdated=true;
                    }
                }
            }
            if($anyUpdated){
                saveUsers($users);
                if($isAjaxRoles){
                    header('Content-Type: application/json');
                    echo json_encode(['success'=>true,'message'=>$translations['role_changed']]);
                    exit;
                }
                $userManagementSuccess=$translations['role_changed'];
            } elseif($isAjaxRoles){
                header('Content-Type: application/json');
                echo json_encode(['success'=>true,'message'=>'No changes']);
                exit;
            }
        } elseif($sessionIsAdmin && (isset($_POST['user_verified']) && is_array($_POST['user_verified']) || isset($_POST['user_roles']) && is_array($_POST['user_roles']))){
            // Non-root admins may change the status (verified flag and role)
            // of OTHER users — never their own and never root@root.
            $users=loadUsers();
            $anyUpdated=false;
            if(isset($_POST['user_roles']) && is_array($_POST['user_roles'])){
                foreach($_POST['user_roles'] as $email=>$role){
                    if($email==='root@root') continue;
                    if($email===$currentUserEmail) continue;
                    if(isset($users[$email])){
                        $users[$email]['role']=intval($role);
                        $anyUpdated=true;
                        adminAuditLog('user_role_change', $email, 'role='.intval($role));
                    }
                }
            }
            foreach($_POST['user_verified'] ?? [] as $email=>$v){
                if($email==='root@root') continue;
                if($email===$currentUserEmail) continue;
                if(isset($users[$email])){
                    $users[$email]['verified']=(bool)intval($v);
                    $anyUpdated=true;
                    adminAuditLog('user_status_change', $email, (bool)intval($v)?'verified':'unverified');
                }
            }
            if($anyUpdated){
                saveUsers($users);
                if($isAjaxRoles){
                    header('Content-Type: application/json');
                    echo json_encode(['success'=>true,'message'=>$translations['role_changed']]);
                    exit;
                }
                $userManagementSuccess=$translations['role_changed'];
            } elseif($isAjaxRoles){
                header('Content-Type: application/json');
                echo json_encode(['success'=>true,'message'=>'No changes']);
                exit;
            }
        } elseif($isAjaxRoles){
            header('Content-Type: application/json');
            echo json_encode(['success'=>false,'message'=>'Permission denied']);
            exit;
        }
    }
    if(isset($_POST['terminate_session'])){
        $sessionId=$_POST['session_id']??'';
        $userEmail=$_SESSION['user']['email']??'';
        if($sessionId&&$userEmail){
            removeUserFromActiveSessions($userEmail,$sessionId);
            $sessionManagementSuccess=$translations['session_terminated'];
        }
    }
    if(isset($_POST['terminate_all_sessions'])){
    $userEmail=$_SESSION['user']['email']??'';
    if($userEmail){
                // Delete all sessions EXCEPT the current one
        $db = getDB();
        $currentSessionId = session_id();
        $stmt = $db->prepare("DELETE FROM active_sessions WHERE email=:e AND session_id != :sid");
        $stmt->bindValue(':e', $userEmail);
        $stmt->bindValue(':sid', $currentSessionId);
        $stmt->execute();
        
                // Update the current session
        addUserToActiveSessions($userEmail, $currentSessionId, $_SESSION['user']);
        $sessionManagementSuccess=$translations['all_sessions_terminated'];
    }
}
    if(isset($_POST['save_app_access_levels']) && $sessionIsAdmin && isset($_POST['ajax'])){
        $raw=$_POST['levels_json']??'{}';
        $dec=json_decode($raw,true);
        if(!is_array($dec)){
            while(ob_get_level()>1)ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['success'=>false,'error'=>'invalid_levels']);
            exit;
        }
        $clean=[];
        foreach($dec as $k=>$v){
            $clean[(string)$k]=(int)$v;
        }
        saveAppAccessLevelsFromMap($clean);
        while(ob_get_level()>1)ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success'=>true,'message'=>$translations['app_access_saved']??'Saved']);
        exit;
    }
    if(isset($_POST['save_app_access_security']) && $sessionIsAdmin && isset($_POST['ajax'])){
        $countries=json_decode((string)($_POST['countries_json']??'[]'),true);
        saveAppAccessSecurityConfig(['blocked_countries'=>is_array($countries)?$countries:[],'block_vpn'=>($_POST['block_vpn']??'0')==='1']);
        while(ob_get_level()>1)ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success'=>true,'message'=>$translations['app_access_saved']??'Saved']);
        exit;
    }
    if(isset($_POST['logout'])){
        if(isset($_SESSION['user']['email'])){
            adminPanelClearGate((string)$_SESSION['user']['email']);
            removeUserFromActiveSessions($_SESSION['user']['email'],session_id());
        } else {
            adminPanelClearGate();
        }
        setTrustedDeviceLogoutSuppression();
        clearTrustedDeviceHintCookie();
        clearTrustedDeviceManifestCookie();
        clearTrustedDeviceCookie();
        session_destroy();
        header('Location: /');
        exit;
    }
}

if(isset($_GET['code']) || isset($_GET['error']) || oauthIsOAuthPath(oauthRequestPath())){
    $__oauthPath = oauthRequestPath();
    if($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['error']) && oauthIsOAuthPath($__oauthPath)){
        $authError = ($translations['oauth_auth_failed']??'Authentication failed').': '.trim((string)($_GET['error_description'] ?? $_GET['error'] ?? 'access_denied'));
    }elseif($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['code'])){
        $clientIpOAuth=trim(explode(',',($_SERVER['HTTP_X_FORWARDED_FOR']??$_SERVER['REMOTE_ADDR']??'0.0.0.0'))[0]);
        $oauthLockedSec=getIpLockoutSeconds($clientIpOAuth,'login');
        if($oauthLockedSec>0){
            $authError=sprintf($translations['login_ip_locked']??'Too many failed attempts. Try again in %d seconds.',$oauthLockedSec);
        }else{
            try{
                if($__oauthPath === '/login/oauth/callback'){
                    oauthHandleCallback((string)($_GET['provider'] ?? ''), (string)$_GET['code'], (string)($_GET['state'] ?? ''), 'login');
                }elseif($__oauthPath==='/login'){
                    oauthHandleLegacyGoogleCallback((string)$_GET['code']);
                }
            }catch(Throwable $e){
                error_log('OAuth callback error: '.$e->getMessage());
                recordFailedAttempt($clientIpOAuth,'login');
                $authError = ($translations['oauth_auth_failed']??'Authentication failed').': '.$e->getMessage();
            }
        }
    }
}
