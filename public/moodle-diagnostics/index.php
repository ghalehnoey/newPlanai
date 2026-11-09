<?php
declare(strict_types=1);
ini_set('display_errors', '0');
const PRIVATE_DIR = __DIR__ . '/../../storage/app/hsu-moodle-diagnostics';
const MOODLE_URL = 'https://elearning.hsu.ac.ir';
const PANEL_ORIGIN = 'https://static.hakimisadr.com';
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; connect-src 'self'; img-src 'self' data:; frame-ancestors 'none'; form-action 'self'");
session_name('hsu_moodle_diagnostics');
session_set_cookie_params(['lifetime'=>0,'path'=>'/moodle-diagnostics/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
session_start();
if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
$action = (string)($_GET['action'] ?? '');
$method = $_SERVER['REQUEST_METHOD'];

function reply(array $data, int $status=200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function input(): array {
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384) reply(['ok'=>false,'message'=>'درخواست بیش از حد بزرگ است.'],413);
    if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        $data=json_decode(file_get_contents('php://input'),true);
        if (!is_array($data)) reply(['ok'=>false,'message'=>'درخواست معتبر نیست.'],400);
        return $data;
    }
    return $_POST;
}
function scrub(mixed $value, string $token=''): mixed {
    if (is_array($value)) {foreach($value as $key=>$item)$value[$key]=scrub($item,$token);return $value;}
    if (!is_string($value)) return $value;
    if ($token!=='') $value=str_replace($token,'[REDACTED]',$value);
    $value=preg_replace('/\b[a-f0-9]{32,}\b/i','[REDACTED]',$value);
    $value=preg_replace('/(wstoken|password|secret|authorization)([\s=:]+)[^\s&<]+/i','$1$2[REDACTED]',$value);
    return mb_substr($value,0,1200);
}
function csrf(array $data): void {
    $origin=$_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin!=='' && $origin!==PANEL_ORIGIN) reply(['ok'=>false,'message'=>'مبدأ درخواست مجاز نیست.'],403);
    $sent=(string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $data['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'],$sent)) reply(['ok'=>false,'message'=>'صفحه را دوباره باز کنید.'],403);
}
function authenticated(): bool {
    if (empty($_SESSION['authenticated'])) return false;
    if (time()-(int)($_SESSION['last_seen']??0)>3600) {unset($_SESSION['authenticated']);return false;}
    $_SESSION['last_seen']=time();return true;
}
function establish_login(): never {
    session_regenerate_id(true);
    $_SESSION=['authenticated'=>true,'last_seen'=>time(),'csrf'=>bin2hex(random_bytes(24))];
    header('Location: index.php',true,303);exit;
}

if ($action==='ticket_login' && $method==='POST') {
    // One-use owner login delivered through the already authorised SSH tunnel.
    $data=input();$ticket=(string)($data['ticket']??'');
    $file=fopen(PRIVATE_DIR.'/auth.json','c+');
    if (!$file || !flock($file,LOCK_EX)) reply(['ok'=>false],503);
    $auth=json_decode(stream_get_contents($file),true)??[];
    $valid=strlen($ticket)>=32 && !empty($auth['ticket_digest']) && (int)($auth['ticket_expires']??0)>=time() && hash_equals($auth['ticket_digest'],hash('sha256',$ticket));
    if ($valid) {
        unset($auth['ticket_digest'],$auth['ticket_expires']);
        ftruncate($file,0);rewind($file);fwrite($file,json_encode($auth));fflush($file);
    }
    flock($file,LOCK_UN);fclose($file);
    if (!$valid) reply(['ok'=>false,'message'=>'لینک ورود موقت نامعتبر یا مصرف‌شده است.'],403);
    establish_login();
}

$loginError='';
if ($action==='login' && $method==='POST') {
    $data=input();csrf($data);
    $auth=json_decode(file_get_contents(PRIVATE_DIR.'/auth.json'),true);
    $ratePath=PRIVATE_DIR.'/login-rate.json';
    $lock=fopen(PRIVATE_DIR.'/login-rate.lock','c');flock($lock,LOCK_EX);
    $rates=is_file($ratePath)?json_decode(file_get_contents($ratePath),true):[];
    $ip=hash('sha256',$_SERVER['REMOTE_ADDR']??'unknown');
    $recent=array_values(array_filter($rates[$ip]??[],fn($t)=>$t>time()-900));
    $valid=count($recent)<10 && ($data['username']??'')==='admin' && password_verify((string)($data['password']??''),$auth['password_hash']);
    if ($valid)unset($rates[$ip]);else{$recent[]=time();$rates[$ip]=$recent;}
    file_put_contents($ratePath,json_encode($rates),LOCK_EX);chmod($ratePath,0600);
    flock($lock,LOCK_UN);fclose($lock);unset($data['password']);
    if ($valid)establish_login();
    $loginError=count($recent)>=10?'تعداد تلاش‌ها زیاد است؛ ۱۵ دقیقه دیگر تلاش کنید.':'اطلاعات ورود صحیح نیست.';
}

if (!authenticated()) {
    if ($action!=='' && $action!=='login') reply(['ok'=>false,'stage'=>'login','message'=>'ابتدا وارد پنل شوید.'],401);
    header('Content-Type: text/html; charset=utf-8');
    $csrf=htmlspecialchars($_SESSION['csrf'],ENT_QUOTES,'UTF-8');
    $error=htmlspecialchars($loginError,ENT_QUOTES,'UTF-8');
    echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ورود به پنل آزمایش مودل</title><style>body{background:#f3f5fa;color:#14223a;font:15px/1.9 Tahoma;margin:0;display:grid;min-height:100vh;place-items:center}.card{background:white;border:1px solid #e1e7f0;border-radius:17px;padding:30px;width:min(380px,80vw);box-shadow:0 15px 40px #14223a12}h1{font-size:22px;margin-top:0}p{font-size:12px;color:#66748b}label{display:block;margin:14px 0 5px}input{box-sizing:border-box;width:100%;padding:12px;border:1px solid #b8c3d6;border-radius:8px;font-size:16px;direction:ltr}button{margin-top:23px;width:100%;padding:12px;border:0;border-radius:8px;background:#6150cb;color:white;font:14px Tahoma}.error{color:#b54438}</style><form class="card" method="post" action="index.php?action=login"><h1>پنل آزمایش مودل</h1><p>اجرای آزمون مستقیم از سرور ۱۱ و بررسی پاسخ واقعی وب‌سرویس</p><input type="hidden" name="csrf" value="'.$csrf.'"><label for="username">نام کاربری پنل</label><input id="username" name="username" autocomplete="username" required><label for="password">رمز ورود پنل</label><input id="password" name="password" type="password" autocomplete="current-password" required><p class="error">'.$error.'</p><button>ورود به پنل</button><p>این رمز مربوط به پنل آزمایش است؛ رمز مودل یا پویا را وارد نکنید.</p></form></html>';
    exit;
}

if ($action==='logout' && $method==='POST') {csrf(input());$_SESSION=[];session_destroy();reply(['ok'=>true]);}
if ($action==='') {
    header('Content-Type: text/html; charset=utf-8');
    echo str_replace('__CSRF__',$_SESSION['csrf'],file_get_contents(PRIVATE_DIR.'/panel.html'));exit;
}

$plan=json_decode(file_get_contents(PRIVATE_DIR.'/plan.json'),true);
$statePath=PRIVATE_DIR.'/state.json';
$stateLock=fopen(PRIVATE_DIR.'/state.lock','c');
if (!flock($stateLock,LOCK_EX))reply(['ok'=>false,'message'=>'وضعیت موقتاً در دسترس نیست.'],503);
$database=json_decode(file_get_contents($statePath),true);
$current=$database['current'];
$state=&$database['runs'][$current];
function save_database(string $path,array $database):void {
    $temp=$path.'.tmp';file_put_contents($temp,json_encode($database,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);chmod($temp,0600);rename($temp,$path);
}
if ($action==='status' && $method==='GET') {
    $history=[];foreach(array_reverse($database['runs'],true) as $id=>$run)$history[]=['id'=>$id,'courseid'=>$run['courseid'],'enrolled'=>$run['enrolled'],'fullname'=>$run['fullname'],'created_at'=>$run['created_at']];
    reply(['plan'=>$plan,'state'=>$state,'history'=>$history,'token_stored'=>false]);
}
if ($method!=='POST')reply(['ok'=>false],405);
$data=input();csrf($data);
session_write_close();
if ($action==='new_run') {
    $id=gmdate('Ymd_His').'_'.bin2hex(random_bytes(3));
    $time=new DateTimeImmutable('now',new DateTimeZone('Asia/Tehran'));
    $database['runs'][$id]=['id'=>$id,'fullname'=>'آزمایش اتصال مودل — '.$time->format('Y/m/d H:i'),'shortname'=>'HSU_API_DIAG_'.$id,'created_at'=>gmdate('c'),'courseid'=>null,'enrolled'=>false,'create_uncertain'=>false,'events'=>[]];
    $database['current']=$id;save_database($statePath,$database);
    reply(['ok'=>true,'stage'=>'new_run','message'=>'آزمون تازه آماده شد. هنوز هیچ درسی ساخته نشده است.']);
}
if ($action==='select_run') {
    $id=is_string($data['id']??null)?$data['id']:'';
    if (!array_key_exists($id,$database['runs']))reply(['ok'=>false,'message'=>'آزمون پیدا نشد.'],404);
    $database['current']=$id;save_database($statePath,$database);
    reply(['ok'=>true,'stage'=>'history','message'=>'نتیجه آزمون انتخاب‌شده نمایش داده شد.']);
}

function moodle_call(string $token,string $function,array $parameters):array {
    if (!in_array($function,['core_course_create_courses','enrol_manual_enrol_users'],true))throw new RuntimeException('Function outside scope');
    $fields=['wstoken'=>$token,'wsfunction'=>$function,'moodlewsrestformat'=>'json']+$parameters;
    $ch=curl_init(MOODLE_URL.'/webservice/rest/server.php');
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($fields),CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>25,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded','User-Agent: HSU-Moodle-Diagnostics/2.0']]);
    $start=microtime(true);$body=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);$errno=curl_errno($ch);curl_close($ch);
    $elapsed=(int)round((microtime(true)-$start)*1000);
    if ($body===false || $errno)return ['ok'=>false,'stage'=>'network','http'=>$http,'message'=>scrub($error,$token),'elapsed_ms'=>$elapsed,'uncertain'=>true];
    if ($http!==200)return ['ok'=>false,'stage'=>'http','http'=>$http,'message'=>'پاسخ HTTP موفق نیست. قبل از تکرار ایجاد درس، وضعیت مودل را بررسی کنید.','elapsed_ms'=>$elapsed,'uncertain'=>true];
    $result=json_decode($body,true);if(json_last_error()!==JSON_ERROR_NONE||strlen($body)>1048576)return ['ok'=>false,'stage'=>'format','http'=>$http,'message'=>'پاسخ JSON معتبر نیست؛ ممکن است صفحه ورود، خطای PHP یا واسط شبکه باشد.','elapsed_ms'=>$elapsed,'uncertain'=>true];
    if (is_array($result)&&(isset($result['exception'])||isset($result['errorcode']))) {
        $message=scrub((string)($result['message']??''),$token);if(!empty($result['debuginfo']))$message.=' | '.scrub((string)$result['debuginfo'],$token);
        return ['ok'=>false,'stage'=>'moodle','http'=>$http,'errorcode'=>(string)($result['errorcode']??'unknown'),'message'=>$message,'elapsed_ms'=>$elapsed,'uncertain'=>false];
    }
    return ['ok'=>true,'stage'=>'moodle','http'=>$http,'elapsed_ms'=>$elapsed,'_data'=>$result];
}

$token=is_string($data['token']??null)?$data['token']:'';
try {
    if ($action==='connectivity') {
        $result=moodle_call('','core_course_create_courses',[]);
        if(($result['errorcode']??'')==='invalidtoken')$result=array_merge($result,['ok'=>true,'stage'=>'connectivity','message'=>'HTTPS و مسیر REST از سرور ۱۱ پاسخ صحیح می‌دهند. این آزمون بدون توکن است و اعتبار توکن واقعی را بررسی نمی‌کند.']);
    } elseif(!preg_match('/^[A-Za-z0-9_-]{16,128}$/D',$token)) {
        $result=['ok'=>false,'stage'=>'input','message'=>'توکن سرویس را وارد کنید؛ توکن در پنل ذخیره نمی‌شود.'];
    } elseif($action==='probe') {
        $result=moodle_call($token,'core_course_create_courses',[]);
        if(($result['errorcode']??'')==='invalidparameter')$result=array_merge($result,['ok'=>true,'stage'=>'authentication','message'=>'توکن و مجوز تابع ایجاد درس پذیرفته شدند. این درخواست، هیچ درسی ایجاد نکرد.']);
    } elseif($action==='create') {
        if($state['courseid'])$result=['ok'=>true,'stage'=>'existing','courseid'=>$state['courseid'],'message'=>'این درس قبلاً ساخته شده است؛ درس تکراری ساخته نشد.'];
        elseif($state['create_uncertain'])$result=['ok'=>false,'stage'=>'uncertain','message'=>'نتیجه تلاش قبلی نامشخص است. پیش از تکرار، نام کوتاه درس را در مدیریت مودل جستجو کنید.'];
        else {
            $state['create_uncertain']=true;save_database($statePath,$database);
            $result=moodle_call($token,'core_course_create_courses',['courses[0][fullname]'=>$state['fullname'],'courses[0][shortname]'=>$state['shortname'],'courses[0][categoryid]'=>$plan['categoryid'],'courses[0][visible]'=>0,'courses[0][summary]'=>'درس آزمایشی برای بررسی اتصال وب‌سرویس از سرور ۱۱؛ شامل یک مدرس و پنج حساب آزمایشی بدون امکان ورود.']);
            if($result['ok']) {
                $row=$result['_data'][0]??null;
                if(is_array($row)&&is_int($row['id']??null)&&($row['shortname']??'')===$state['shortname']){$state['courseid']=$row['id'];$state['create_uncertain']=false;$result['courseid']=$row['id'];$result['message']='درس آزمایشی ساخته شد و شناسه معتبر دریافت شد.';}
                else $result=array_merge($result,['ok'=>false,'stage'=>'format','message'=>'پاسخ ایجاد درس قالب مورد انتظار را ندارد؛ پیش از تکرار مودل بررسی شود.']);
            }elseif(empty($result['uncertain']))$state['create_uncertain']=false;
        }
    } elseif($action==='enrol') {
        if(!$state['courseid'])$result=['ok'=>false,'stage'=>'order','message'=>'ابتدا ایجاد درس باید با شناسه معتبر تأیید شود.'];
        elseif($state['enrolled'])$result=['ok'=>true,'stage'=>'existing','courseid'=>$state['courseid'],'message'=>'ثبت‌نام شش نفر قبلاً پذیرفته شده است؛ درخواست دوباره ارسال نشد.'];
        else {
            $assignments=[[$plan['teacher']['id'],$plan['teacher']['roleid']]];
            foreach($plan['students'] as $uid)$assignments[]=[$uid,$plan['student_roleid']];
            $parameters=[];foreach($assignments as $i=>[$uid,$role]){$parameters["enrolments[$i][userid]"]=$uid;$parameters["enrolments[$i][roleid]"]=$role;$parameters["enrolments[$i][courseid]"]=$state['courseid'];}
            $result=moodle_call($token,'enrol_manual_enrol_users',$parameters);
            if($result['ok']) {
                if($result['_data']===null){$state['enrolled']=true;$result['courseid']=$state['courseid'];$result['message']='ثبت‌نام یک مدرس و پنج دانشجوی آزمایشی پذیرفته شد؛ فهرست شرکت‌کنندگان مودل را برای تأیید نهایی باز کنید.';}
                else $result=array_merge($result,['ok'=>false,'stage'=>'format','message'=>'قالب پاسخ ثبت‌نام مورد انتظار نیست؛ فهرست مودل بررسی شود.']);
            }
        }
    }else $result=['ok'=>false,'stage'=>'input','message'=>'عملیات مجاز نیست.'];
    unset($result['_data']);$result=scrub($result,$token);
    $event=['time'=>gmdate('c'),'action'=>$action];foreach(['ok','stage','http','errorcode','message','elapsed_ms'] as $key)if(array_key_exists($key,$result))$event[$key]=$result[$key];
    $state['events'][]=$event;$state['events']=array_slice($state['events'],-30);
    save_database($statePath,$database);unset($token,$data);reply($result);
}catch(Throwable $error) {
    unset($token,$data);reply(['ok'=>false,'stage'=>'internal','message'=>'خطای داخلی؛ اطلاعات محرمانه نمایش داده نشد. قبل از تکرار، وضعیت مودل بررسی شود.'],500);
}
