<?php

function apRedirectToSetPassword(array $user): void {
    $_SESSION['pending_oauth_data'] = [
        'email' => (string)($user['email'] ?? ''),
        'name' => (string)($user['name'] ?? ''),
        'photo' => (string)($user['photo'] ?? 'default_avatar.png'),
        'provider' => 'email',
        'reg_method' => 'Email',
    ];
    unset($_SESSION['pending_google_data']);
    $_SESSION['pending_set_password_only'] = true;
    header('Location: /register?step=set_password&password_required=1');
    exit;
}

if(isset($_SESSION['user']['email'])&&$path!=='/register'&&!isset($_GET['code'])){
    $freshUserForPwd = getUserByEmail((string)$_SESSION['user']['email']);
    if($freshUserForPwd && (userNeedsPasswordSet($freshUserForPwd)||!empty($freshUserForPwd['must_change_password']))){
        apRedirectToSetPassword($freshUserForPwd);
    }elseif($freshUserForPwd){
        appClearPendingPasswordFlow();
    }
}

if(isset($_SESSION['user'])&&!$needsAuth&&$path!=='/login'&&$path!=='/register'){
    $currentSession=getUserFromActiveSessions(session_id());
    if($currentSession){
        $freshUser=updateUserActivity(session_id());
        if(is_array($freshUser)&&!empty($freshUser['email'])){
            $_SESSION['user']=$freshUser;
        }
    }elseif(!ensureAuthenticatedSession()){
        removeUserFromActiveSessions($_SESSION['user']['email'],session_id());
        session_destroy();
        $needsAuth=true;
        $showLogin=true;
        header('Location: /login' . ($themeFromUrl ? '?theme=' . urlencode($themeFromUrl) : '') . ($langFromUrl ? '&lang=' . urlencode($langFromUrl) : '') . ($colorFromUrl ? '&color=' . urlencode($colorFromUrl) : ''));
        exit;
    }
}

if($needsAuth&&$path!=='/login'&&$path!=='/register'&&$path!=='/verify'&&$path!=='/one-time-pin'&&!oauthIsOAuthPath($path)&&!isset($_GET['code'])){
    header('Location: /login' . ($themeFromUrl ? '?theme=' . urlencode($themeFromUrl) : '') . ($langFromUrl ? '&lang=' . urlencode($langFromUrl) : '') . ($colorFromUrl ? '&color=' . urlencode($colorFromUrl) : ''));
    exit;
}

if($needsAuth){
    $showLogin=true;
    $currentApp='';
}else{
    $currentApp=$_GET['app']??'';
    if(isset($_SESSION['user']['email'])&&!empty($currentApp)&&!$needsAuth){
        if(!in_array($currentApp,$PUBLIC_APPS_WITHOUT_AUTH)&&!userMeetsAppMinRole((int)($_SESSION['user']['role']??0),$currentApp)){
            header('Location: /main');
            exit;
        }
        if(appAccessRequestBlocked()){
            http_response_code(403);
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>403 Forbidden</title></head><body style="font-family:sans-serif;text-align:center;padding:60px"><h1>403 Forbidden</h1></body></html>';
            exit;
        }
        $currentSession=getUserFromActiveSessions(session_id());
        if(!$currentSession && !ensureAuthenticatedSession()){
            removeUserFromActiveSessions($_SESSION['user']['email'],session_id());
            session_destroy();
            header('Location: /login' . ($themeFromUrl ? '?theme=' . urlencode($themeFromUrl) : '') . ($langFromUrl ? '&lang=' . urlencode($langFromUrl) : '') . ($colorFromUrl ? '&color=' . urlencode($colorFromUrl) : ''));
            exit;
        }
    }
    $showLogin=false;
}
$newDeviceLoginNotificationDelayMs=null;
if($path==='/main' && !$needsAuth && PHP_SESSION_ACTIVE===session_status()){
    $newDeviceLoginNotificationDelayMs=consumeTrustedDevicePromptDelayMs();
    session_write_close();
}

if(isset($_GET['app'])&&!empty($_GET['app'])&&!$showLogin&&!$needsAuth&&$path==='/main'){
    incrementVisitStat($_GET['app']);
}
// Full visit stats for the chart; totals for sidebar sorting
$visitStats=loadVisitStats();
$visitStatsTotals=array_map(function($d){return array_sum((array)$d);}, $visitStats);

$appsDir='apps/';
$appsFsDir=(defined('APP_ROOT') ? APP_ROOT . '/' : '') . $appsDir;
$apps=[];
$groupedApps=[];
// Default shell menu button geometry for app pages.
$defaultMenuButton=['left'=>20,'top'=>13,'width'=>40,'height'=>40];
	$shellParseMenuButton=function(array $nameData,string $key,array $defaults):array{
    $button=$defaults;
    if(isset($nameData[$key])&&is_array($nameData[$key])){
        $aliases=['left'=>'x','top'=>'y'];
        foreach($defaults as $k=>$defaultValue){
            $rawValue=$nameData[$key][$k]??($nameData[$key][$aliases[$k]??'']??null);
            if(is_numeric($rawValue)){
                $button[$k]=max(0,(int)$rawValue);
            }
        }
    }
	    return $button;
	};
	$shellParseMenuButtonStyle=function(array $nameData,string $key):array{
	    $style=[];
	    if(isset($nameData[$key])&&is_array($nameData[$key])){
	        foreach($nameData[$key] as $prop=>$value){
	            $prop=(string)$prop;
	            if(preg_match('/^[a-zA-Z-]+$/',$prop)&&is_scalar($value)){
	                $style[$prop]=(string)$value;
	            }
	        }
	    }
	    return $style;
	};
$_shellUserRole=(int)($_SESSION['user']['role']??0);
$iconStyle=in_array(($config['icon_style']??''),['monochrome','multicolor','animated'],true)?$config['icon_style']:'animated';
if(is_dir($appsFsDir)){
    $subdirs=array_filter(glob($appsFsDir.'*'),'is_dir');
    foreach($subdirs as $subdir){
        $nameFile=$subdir.'/name.json';
        $folder=basename($subdir);
        if(isset($_SESSION['user']['email'])&&!userMeetsAppMinRole($_shellUserRole,$folder)){continue;}
        if(file_exists($nameFile)){
            $nameData=json_decode(file_get_contents($nameFile),true);
            if(!is_array($nameData)){$nameData=[];}
            $name=$nameData[$language]??($nameData['en']??basename($subdir));
            $menuButton=$shellParseMenuButton($nameData,'menu_button',$defaultMenuButton);
            $menuButtonMobile=$shellParseMenuButton($nameData,'menu_button_mobile',$menuButton);
            // Icons always come from the app's icons/ folder; fall back to
            // the multicolor icon when the selected style is missing.
            $iconFs=$subdir.'/icons/'.$iconStyle.'.svg';
            if(!is_file($iconFs)){
                $iconFs=$subdir.'/icons/multicolor.svg';
                $iconStyleForApp='multicolor';
            }else{
                $iconStyleForApp=$iconStyle;
            }
            if(is_file($iconFs)){
                $icon=$appsDir.$folder.'/icons/'.basename($iconFs);
                $iconMasked=($iconStyleForApp==='monochrome');
            }else{
                $icon=defaultAppIconSrc();
                $iconMasked=false;
            }
            $totalVisits=$visitStatsTotals[$folder]??0;
            $appRailApps=['gitea','immich','kanboard','dawarich','sentry','x','X','lopaka'];
            $href='/main?app='.urlencode($folder).'&lang='.urlencode($language).'&theme='.urlencode($themeForAppLink).'&color='.urlencode($config['color']);
            $descFile=$subdir.'/description.json';
            $description='';
            if(is_file($descFile)){
                $descData=json_decode((string)file_get_contents($descFile),true);
                if(is_array($descData)){
                    $description=(string)($descData[$language]??($descData['en']??''));
                }
            }
	            $apps[$name]=['name'=>$name,'folder'=>$folder,'icon'=>$icon,'is_svg'=>(strtolower(pathinfo($icon,PATHINFO_EXTENSION))==='svg'),'icon_masked'=>$iconMasked,'icon_style'=>$iconStyle,'visits'=>$totalVisits,'menu_button'=>$menuButton,'menu_button_mobile'=>$menuButtonMobile,'menu_button_scroll'=>(bool)($nameData['menu_button_scroll']??false),'menu_button_style'=>$shellParseMenuButtonStyle($nameData,'menu_button_style'),'menu_button_mobile_style'=>$shellParseMenuButtonStyle($nameData,'menu_button_mobile_style'),'href'=>$href,'target'=>'_self','app_rail'=>in_array($folder,$appRailApps,true),'description'=>$description];
        }
    }
    $iconSortOrder=$config['icon_sort_order']??'alpha';
    if($iconSortOrder==='popularity'){
        uasort($apps,function($a,$b){
            if($a['visits']===$b['visits']){return strcoll($a['name'],$b['name']);}
            return $b['visits']-$a['visits'];
        });
    }else{
        uasort($apps,function($a,$b){return strcoll($a['name'],$b['name']);});
    }
    foreach($apps as $name=>$appData){
        $firstLetter=mb_strtoupper(mb_substr($name,0,1,'UTF-8'),'UTF-8');
        $groupedApps[$firstLetter][$name]=$appData;
    }
    ksort($groupedApps);
}

$currentDomain=(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?"https":"http")."://".$_SERVER['HTTP_HOST'];
$selectedApp=isset($_GET['app'])?$_GET['app']:null;

$pageTitle="Shell Application";
$selectedAppMenuButton=$defaultMenuButton;
$selectedAppMenuButtonMobile=$defaultMenuButton;
$selectedAppMenuButtonScroll=false;
$selectedAppMenuButtonStyle=[];
$selectedAppMenuButtonMobileStyle=[];
if($selectedApp){
    foreach($apps as $name=>$appData){
        if($appData['folder']===$selectedApp){
            $pageTitle=$translations['app']." | ".$name;
            if(isset($appData['menu_button'])&&is_array($appData['menu_button'])){
                $selectedAppMenuButton=array_merge($defaultMenuButton,$appData['menu_button']);
            }
            if(isset($appData['menu_button_mobile'])&&is_array($appData['menu_button_mobile'])){
                $selectedAppMenuButtonMobile=array_merge($selectedAppMenuButton,$appData['menu_button_mobile']);
            }else{
                $selectedAppMenuButtonMobile=$selectedAppMenuButton;
            }
            $selectedAppMenuButtonScroll=!empty($appData['menu_button_scroll']);
            $selectedAppMenuButtonStyle=is_array($appData['menu_button_style']??null)?$appData['menu_button_style']:[];
            $selectedAppMenuButtonMobileStyle=is_array($appData['menu_button_mobile_style']??null)?$appData['menu_button_mobile_style']:[];
            break;
        }
    }
    $selectedNameFile=$appsFsDir.basename((string)$selectedApp).'/name.json';
    if(is_file($selectedNameFile)){
        $selectedNameData=json_decode((string)file_get_contents($selectedNameFile),true);
        if(is_array($selectedNameData)){
            $selectedAppMenuButton=$shellParseMenuButton($selectedNameData,'menu_button',$defaultMenuButton);
            $selectedAppMenuButtonMobile=$shellParseMenuButton($selectedNameData,'menu_button_mobile',$selectedAppMenuButton);
            $selectedAppMenuButtonScroll=!empty($selectedNameData['menu_button_scroll']);
            $selectedAppMenuButtonStyle=$shellParseMenuButtonStyle($selectedNameData,'menu_button_style');
            $selectedAppMenuButtonMobileStyle=$shellParseMenuButtonStyle($selectedNameData,'menu_button_mobile_style');
        }
    }
}
$userDataForApps=isset($_SESSION['user'])?['name'=>$_SESSION['user']['name'],'email'=>$_SESSION['user']['email'],'photo'=>$_SESSION['user']['photo']??'default_avatar.png','role'=>$_SESSION['user']['role']??0]:null;
$usersList=loadUsers();
$isRootUser=isset($_SESSION['user']['email'])&&$_SESSION['user']['email']==='root@root';
$isAdmin = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] == 2;
$activeSessions=[];
$trustedDevices=[];
$currentIp=getClientIp();
$isCurrentDeviceTrusted=false;
if(isset($_SESSION['user']['email'])){
    $activeSessions=getUserActiveSessions($_SESSION['user']['email']);
    $trustedDevices=getUserTrustedDevices($_SESSION['user']['email']);
    $isCurrentDeviceTrusted=isTrustedDevice($_SESSION['user']['email'], getDeviceFingerprint());
}
$currentUserEmail = $_SESSION['user']['email'] ?? null;
$isAdmin = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] == 2;

if ($isAdmin) {
    $sharedFiles = loadSharedFiles(null, true);
} else {
    $sharedFiles = $currentUserEmail ? loadSharedFiles($currentUserEmail) : [];
}

$userOAuthConnections = [];
if(isset($_SESSION['user']['email'])){
    $userOAuthConnections = getOAuthConnectionsForUser((string)$_SESSION['user']['email']);
}

$registerStep = 'initial';
if(!isset($authError) && !empty($_GET['oauth_error'])){
    $authError = (string)$_GET['oauth_error'];
}
if(!isset($authError) && isset($_GET['error']) && $_GET['error'] === 'verification_failed'){
    $authError = $translations['verification_link_expired_desc'] ?? 'This verification link is invalid, expired, or has already been used.';
    $showReVerifyForm = true;
}
if(!isset($authError) && isset($_GET['error']) && $_GET['error'] === 'csrf'){
    $authError = $translations['csrf_forbidden'] ?? 'Security check failed. Please try again.';
}
if(!isset($authError) && isset($_GET['error']) && $_GET['error'] === 'admin_ip_forbidden'){
    $authError = $translations['admin_ip_forbidden'] ?? 'Administrator access is not allowed from this IP.';
}
$pendingOAuthData = $_SESSION['pending_oauth_data'] ?? $_SESSION['pending_google_data'] ?? null;
$pendingGoogleData = $pendingOAuthData;

// Fallback: if someone opens the set-password URL directly (or in a new tab),
// pending OAuth data may be missing even though we know the user from the
// current session. In that case, populate pending data from the authenticated
// user so the page can render the password setup form.
if (
    ($path === '/register')
    && (($_GET['step'] ?? '') === 'set_password')
    && !isset($_GET['new_oauth_user'])
    && !isset($_GET['new_google_user'])
    && isset($_GET['password_required'])
    && !$pendingOAuthData
) {
    $email = (string)($_SESSION['user']['email'] ?? '');
    // If opened directly without a session, use default admin for deep-link usability.
    if ($email === '') {
        $email = 'root@root';
    }

    $user = getUserByEmail($email);
    $pendingOAuthData = [
        'email' => (string)(($user['email'] ?? null) ?: $email),
        'name' => (string)(($user['name'] ?? null) ?: explode('@', $email)[0]),
        'photo' => (string)(($user['photo'] ?? null) ?: 'default_avatar.png'),
        'provider' => (string)(($user['provider'] ?? null) ?: 'email'),
        'reg_method' => 'Email',
    ];
    $_SESSION['pending_oauth_data'] = $pendingOAuthData;
    $_SESSION['pending_set_password_only'] = true;
    $pendingGoogleData = $pendingOAuthData;
    $registerStep = 'set_password';
}
if($pendingOAuthData){
    $registerStep = 'set_password';
}

if($path==='/login'||$path==='/register'){
    $showLogin=true;
    $needsAuth=true;
    if($path==='/login'){
        $pageTitle='Login';
    }else{
        $pageTitle='Register';
        if($registerStep === 'set_password'){
            $pageTitle='Set Password';
        }
    }
}

// If we are rendering the deep-linked set-password page, do not redirect to
// /main in case the session restore logic marks the user as already
// authenticated. We rely on the pending_set_password_only flag.
if (
    $path === '/register'
    && $registerStep === 'set_password'
    && !empty($_SESSION['pending_set_password_only'])
) {
    $showLogin = true;
}

$showTopBarTitle = !$selectedApp && $path === '/main' && !$showLogin;
$isHomeDashboard = $path === '/main' && !$selectedApp && !$showLogin;
if (!headers_sent()) {
    header('Permissions-Policy: microphone=*');
}
?>
