<?php

// Determines the base URL.
function getBaseURL(){
  global $cfg;
  $protocol = 'http'.(!empty($_SERVER['HTTPS']) ? 's' : '');
  $url = $protocol.'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
  $dispatches = explode(',',$cfg['gen']['dispatches']);
  foreach($dispatches as $dx){
    if(strpos($url,'/'.$dx.'/') !== false){
      $url = explode('/'.$dx.'/',$url);
      return $url[0].'/';
    }
  }
  return $url;
}

// Builds URL's for links within the system.  Prevents the need to construct every time from scratch.
function url($a){
  global $base_url;
  if(!empty($a)){
    $url = $base_url.'go/';
    if(isset($a['section'])){
      $section = $a['section'];
      unset($a['section']);
      $url .= $section.'/';
      foreach($a as $n => $v){
        $url .= $n.'/'.$v.'/';
      }
    } else {
      foreach($a as $n => $v){
        $url .= $n.'/'.$v.'/';
      }
    }
  } else {
    $url = $base_url;
  }
  return $url;
}

// Generates a complete XML tag.  Attributes must be an associative array.
function genTag($n,$v,$attrib=false,$cdata=false){
  $a = '';
  if(!empty($attrib)){
    foreach($attrib as $an => $av){
      $a .= ' '.$an.'="'.$av.'" ';
    }
  }
  $v = $cdata ? '<![CDATA['.$v.']]>' : $v;
  return '<'.$n.$a.'>'.$v.'</'.$n.'>'."\n";
}

// Filters out any "illegal" characters.
function charFilter($s,$entities=true){ 
  $allowed = array_merge(Array(9,10,12,13),range(32,126));
  $l = strlen($s);
  $o = '';
  for($i=0;$i<$l;$i++){
    if(in_array(ord($s[$i]),$allowed)) $o .= $s[$i];
  }
  $o = ($entities) ? htmlentities($o) : $o;  
  return $o;
}

// Replaces the str_replace() function to only replace content and not affect any HTML tags.
// NOTE:  While more robust than just str_replace, it slows down the system horribly so it's not used right now.
function replaceInHTML($find,$replace,$s,$case_sensitive=false){
  // return $case_sensitive ? str_replace($find,$replace,$s) : str_ireplace($find,$replace,$s); // Override for testing efficiency of this function.
  $dom = new DOMDocument();
  $dom->loadHtml($s);
  $xpath = new DOMXPath($dom);
  foreach($xpath->query('//text()') as $node){
    $text = $node->wholeText;
    $text = $case_sensitive ? str_replace($find,$replace,$text) : str_ireplace($find,$replace,$text);
    $new_node = $dom->createDocumentFragment();
    $new_node->appendXML($text);
    $node->parentNode->replaceChild($new_node,$node);   
  }
  return $dom->saveHTML($xpath->query('//body')->item(0));
}

// Builds a button for an url array.
function button($a,$label='Go',$critical=false,$wide=false){
  return "<a class=\"button".($critical ? ' button_critical' : '').($wide ? ' button_fullwidth' : '')."\" href=\"".url($a)."\">".$label."</a>\n";
}

// This button uses a javascript confirm before proceeding to its link.
function confirmButton($a,$label='Go',$critical=false,$wide=false,$message='Are you sure?'){
  return "<a class=\"button".($critical ? ' button_critical' : '').($wide ? ' button_fullwidth' : '')."\" onclick=\"confirmAction('".$message."','".url($a)."');\">".$label."</a>\n";
}

function homeButton(){
  return button(Array(),'Return to Index');
}

function backButton($label='Cancel'){
  global $base_url;
  $url = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $base_url;
  return "<a class=\"button button_fullwidth\" href=\"".$url."\" title=\"$label\">$label</a>\n";
}

// Forces a page redirect to a specified URL Array.
function redirect($a=false){
  header('Location: '.url($a));
  exit();  
}

// Forces a page redirect to an URL specified as a string instead of URL Array.
function redirectRaw($url){
  header('Location: '.$url);
  exit();
}

function goBack(){
  if(!empty($_SERVER['HTTP_REFERER'])){
    redirectRaw($_SERVER['HTTP_REFERER']);
  } else {
    return false;
  }
}

function title($s){ // Adds to the page title.
  $GLOBALS['HTML']['title'].= ' - '.$s;
}

// Builds a table out of 2D database query results.
function buildSimpleDataTable($d){
  if((empty($d)) || (empty($d[0]))) return false;
  $o  = "<table class=\"listing\">\n<tr>";
  foreach($d[0] as $n => $v){
    $o .= "<th>".ucwords(str_replace('_',' ',$n))."</th>";
  }
  $o .= "</tr>\n";
  foreach($d as $row){
    $rc = empty($rc) ? 1 : 0;
    $o .= "<tr class=\"row_$rc detail\">";
    foreach($row as $n => $v){
      $o .= "<td>".$v."</td>";
    }
    $o .= "</tr>\n";
  }  
  $o .= "</table>\n";
  return $o;
}

function genSalt(){
  $salt = '';
  $salt_length = mt_rand(256,512);
  for($i=0;$i<=$salt_length;$i++){
    $salt .= chr(mt_rand(0,255));
  }
  $salt .= strrev(sha1(microtime()));
  $salt .= mt_rand(-204981,8893452);
  return md5(base64_encode($salt));
}

function encodeURLData($a){ // Input must be an array.
  global $cfg;
  $a['encode_url_data_salt'] = genSalt(); // Makes it much harder to guess the contents, and therefore the key, once encrypted.
  $s = base64_encode(encrypt(serialize($a),'url'));
  return $s;
}

function decodeURLData($s){ // Input must be the encoded string.
  global $cfg;
  $a = unserialize(decrypt(base64_decode($s),'url'));
  unset($a['encode_url_data_salt']);  // We don't need the salt after decoding.
  return $a;
}

function debug($d){
  file_put_contents('./dat/logs/debug.txt',print_r($d,1));
}

function validateCAPTCHA($code){
  global $cfg;
  $cookie_name = $cfg['cookie']['prefix'].'_captcha';
  return ((isset($_COOKIE[$cookie_name])) && (md5($code) == $_COOKIE[$cookie_name]));
}

function formatDateTime($ts,$fmt='db',$tz_adjust=true){
  global $cfg, $user;
  if(!array_key_exists($fmt,$cfg['date'])) return $ts;
  $ts = (is_int($ts) === false) ? strtotime($ts) : $ts;
  $ts = $tz_adjust ? adjustTimeZone($ts) : $ts;
  return date($cfg['date'][$fmt],$ts);
}

function assembleDateTime($year=2004,$month=1,$day=1,$hours=0,$minutes=0,$seconds=0){
  global $cfg;
  return date($cfg['date']['db'],strtotime($year.'-'.sprintf("%02s",$month).'-'.sprintf("%02s",$day).' '.sprintf("%02s",$hours).':'.sprintf("%02s",$minutes).':'.sprintf("%02s",$seconds)));
}

function getServerTimeZoneOffset(){
  $server_time_zone = new DateTimeZone(date_default_timezone_get());
  $server_datetime = new DateTime('now',$server_time_zone);
  return $server_time_zone->getOffset($server_datetime);
}

function adjustTimeZone($ts){
  global $cfg, $user;
  $ts = $ts - $cfg['date']['server_time_zone_offset'];
  if($user->isLoggedIn()){
    return $ts + $user->time_zone_offset;
  } else {
    return $ts + $cfg['date']['default_time_zone_offset'];
  }
}

function randomString($l=5){
  $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
  $chars_len = strlen($chars);
  $out = '';
  for($i=0;$i<$l;$i++){
    $out .= $chars[mt_rand(0,$chars_len-1)];    
  }
  return $out;
}

function convertUserNameToFileName($s){
  return str_replace(' ','_',strtolower($s));
}

function encodeIP($ip){
  return sprintf('%u',ip2long($ip));
}

function decodeIP($ip){
  return long2ip($ip);
}

function processAvatar($preset,$upload,$offsite,$user_name){
  global $cfg, $error;
  if(!empty($offsite)){ // If offsite avatar was given
    $type = 2;
    $link = $offsite;
  } else if(!empty($upload)){ // If avatar was uploaded
    $type = 1;
    switch($_FILES['avatar_upload']['type']){      
      case 'image/png':
        $img = imagecreatefrompng($upload);
      break;      
      case 'image/jpeg':
        $img = imagecreatefromjpeg($upload);
      break;      
      case 'image/gif':
        $img = imagecreatefromgif($upload);
      break;
      default:
        $error[] = "Avatar image format not supported.";
        return false;
      break;
    }
    $avatar = imagecreatetruecolor($cfg['img']['avatar_size'],$cfg['img']['avatar_size']);
    imagecopyresampled($avatar,$img,0,0,0,0,$cfg['img']['avatar_size'],$cfg['img']['avatar_size'],imagesx($img),imagesy($img));
    imagedestroy($img);
    switch($_FILES['avatar_upload']['type']){
      case 'image/png':
        $link = convertUserNameToFileName($user_name).'.png';
        $filename = $cfg['dir']['usr_av'].'/'.$link;
        imagepng($avatar,$filename,0);
      break;
      case 'image/jpeg':
        $link = convertUserNameToFileName($user_name).'.jpg';
        $filename = $cfg['dir']['usr_av'].'/'.$link;
        imagejpeg($avatar,$filename,$cfg['img']['avatar_quality']);
      break;
      case 'image/gif':
        $link = convertUserNameToFileName($user_name).'.gif';
        $filename = $cfg['dir']['usr_av'].'/'.$link;
        imagegif($avatar,$filename);
      break;
    }
  } else if((in_array($preset,scandir($cfg['dir']['av']))) && ($preset != '.') && ($preset != '..')){ // If valid preset avatar selected
    $type = 0;
    $link = $preset;
  } else { // Default preset avatar
    $type = 0;
    $link = $cfg['img']['default_avatar'];
  }
  return array($type,$link);
}

function processImageUpload($upload,$user_name=false){
  
  global $cfg, $user;
  if(empty($user_name)) $user_name = $user->name;
  if(empty($user_name)) return false; // Reality check.
  $dir = $cfg['dir']['usr_img'].'/'.convertUserNameToFileName($user_name).'/';
  if(!is_dir($dir)) mkdir($dir); // Create directory if it does not exist.
  
  switch($_FILES['image_upload']['type']){      
      case 'image/png':
        $img = imagecreatefrompng($upload);
      break;      
      case 'image/jpeg':
        $img = imagecreatefromjpeg($upload);
      break;      
      case 'image/gif':
        $img = imagecreatefromgif($upload);
      break;
      default:
        $error[] = "Image format not supported.";
        return false;
      break;
  }
  
  // Proportional resize based on maximum size for either dimension from config.
  $new_width = $width = imagesx($img);
  $new_height = $height = imagesy($img);
  $maxw = $cfg['img']['max_post_image_width'];
  $maxh = $cfg['img']['max_post_image_height'];
  $ratio = $width / $height;
  if($width > $maxw){
    $new_width = $maxw;
    $new_height = floor($new_width / $ratio);
  }
  if($new_height > $maxh){
    $new_height = $maxh;
    $new_width = floor($new_height * $ratio);
  }

  // Resample image based on newly calculated dimensions
  $img_new = imagecreatetruecolor($new_width,$new_height);
  imagecopyresampled($img_new,$img,0,0,0,0,$new_width,$new_height,$width,$height);
  imagedestroy($img);
  
  // Build the file name and place in the user's image upload directory.
  // Save the image in the same file type as the original.
  $rn = mt_rand(050000,999999);  // Random number for base file name
  switch($_FILES['image_upload']['type']){
    case 'image/jpeg':
      $fn = $rn.'.jpg';
      imagejpeg($img_new,$dir.$fn,$cfg['img']['upload_quality']);
    break;
    case 'image/gif':
      $fn = $rn.'.gif';
      imagegif($img_new,$dir.$fn);
    break;
    case 'image/png':
    default:
      $fn = $rn.'.png';
      imagepng($img_new,$dir.$fn,(10 - round($cfg['img']['upload_quality'] / 10)));
    break;
  }
  
  // Return the file name for database insertion.
  return $fn;
  
}

function getAvatarURL($type,$link){
  global $cfg, $base_url;
  switch($type){  
    case 0:
      return $base_url.'/'.$cfg['dir']['av'].'/'.$link;
    break;    
    case 1:
      return $base_url.'/'.$cfg['dir']['usr_av'].'/'.$link;
    break;    
    case 2:
      return $link;
    break;    
  }
}

function getIMLink($im,$im_type_id){
  global $cfg, $base_url;
  switch($im_type_id){
    case 1: // AIM
      $alt = 'AOL Instant Messenger';
      return "<a href=\"aim://".$im."\" title=\"$alt\" ><img class=\"small_icon\" src=\"".$base_url.$cfg['dir']['icon']."aim.png\" alt=\"$alt\" /></a>";
    break;
    case 2: // GTalk / Hangouts
      $alt = 'Google Talk';
      return "<a href=\"xmpp:".$im."\" title=\"$alt\" ><img class=\"small_icon\" src=\"".$base_url.$cfg['dir']['icon']."gtalk.png\" alt=\"$alt\" /></a>";
    break;
    case 3: // Skype
      $alt = 'Skype';
      return "<a href=\"callme://".$im."\" title=\"$alt\" ><img class=\"small_icon\" src=\"".$base_url.$cfg['dir']['icon']."skype.png\" alt=\"$alt\" /></a>";
    break;
    case 4: // Jabber
    default:
      $alt = 'XMPP / Jabber';
      return "<a href=\"xmpp:".$im."\" title=\"$alt\" ><img class=\"small_icon\" src=\"".$base_url.$cfg['dir']['icon']."jabber.png\" alt=\"$alt\" /></a>";
    break;
    
  }
}

function showPaginationControls($page=1,$page_size=10,$item_count=false,$url_array=Array()){
  if($item_count === false) return false;
  if($page < 1) $page = 1;
  $o = "<div class=\"pagination\">\n";
  $page_count = ceil($item_count / $page_size);
  if($page_count < 1) return false;
  $first_url = url(array_merge($url_array,Array('page'=>1)));
  $previous_url = url(array_merge($url_array,Array('page'=>$page - 1)));
  $next_url = url(array_merge($url_array,Array('page'=>$page + 1)));
  $last_url = url(array_merge($url_array,Array('page'=>$page_count)));
  $o .= ($page > 1) ? "<a class=\"nav_first\" title=\"First\" href=\"$first_url\"></a>\n" : '';
  $o .= ($page > 1) ? "<a class=\"nav_previous\" title=\"Previous\" href=\"$previous_url\"></a>\n" : '';
  $o .= ($page_count > 1) ? "<div class=\"pagination_label\" >Page ".$page." of ".$page_count."</div>\n" : '';
  $o .= ($page < $page_count) ? "<a class=\"nav_next\" title=\"Next\" href=\"$next_url\"></a>\n" : '';
  $o .= ($page < $page_count) ? "<a class=\"nav_last\" title=\"Last\" href=\"$last_url\"></a>\n" : '';
  $o .= "</div>\n";
  return $o;
}

function showQuotingThread($topic_id){
  if(empty($topic_id)) return false;
  global $cfg, $data;
  $d = $data->getFullThread($topic_id);
  $d = array_reverse($d); // Display posts in reverse order.
  $html = '';
  if(!empty($d)){
    $html .= "<div id=\"quoting_thread\">\n";
    foreach($d as $row){
      $html .= "<div class=\"controls\"><a class=\"button\" onclick=\"javascript:quotePost('".$row['post_id']."');\" title=\"Quote this post\">quote</a>";
      $html .= "Post by <i id=\"quoting_user_".$row['post_id']."\">".$row['user_name']."</i> on <i id=\"quoting_time_".$row['post_id']."\">".date($cfg['date']['long'],strtotime($row['post_time']))."</i></div>\n";
      $html .= "<div class=\"quoting_post\" id=\"quoting_post_".$row['post_id']."\">\n";
      $html .= $row['content'];
      $html .= "</div>\n";      
    }    
    $html .= "</div>\n";
  }  
  return $html;
}

// General purpose outgoing e-mail function.
function sendEmail($to=false,$subject=false,$message=false){
  global $cfg;
  $headers  = 'From: '.$cfg['email']['system']."\r\n";
  $headers .= 'Reply-To: '.$cfg['email']['system']."\r\n";
  $headers .= 'X-Mailer: PHP/'.phpversion()."\r\n";
  $headers .= 'X-Application: '.$cfg['gen']['title'].' - '.$cfg['gen']['system_version'];
  $message = wordwrap($message,70,"\r\n");
  return mail($to,$subject,$message,$headers);
}
  
function sendRegistrationEmails($d){
  global $cfg, $base_url;
  $user_message  = file_get_contents($cfg['dir']['template'].'registration_email.tpl');
  $admin_message = file_get_contents($cfg['dir']['template'].'registration_admin.tpl');
  $replacements = Array(
    'site_title'                => $cfg['gen']['title'],
    'user_name'                 => $d['user_name'],
    'approval_notice'           => (($cfg['user']['auto_approve'] == 1) ? 'Your account has been approved.' : 'The administrator will approve your account soon.'),
    'admin_activation_notice'   => (($cfg['user']['auto_approve'] == 1) ? 'The account was auto-approved.' : 'You will need to approve this account before they can log in.'),
    'base_href'                 => $base_url,
    'ip'                        => $_SERVER['REMOTE_ADDR'],
    'timestamp'                 => formatDateTime(date($cfg['date']['db']),'long'),
  );
  foreach($replacements as $n => $v){
    $user_message = str_replace('{'.$n.'}',$v,$user_message);
    $admin_message = str_replace('{'.$n.'}',$v,$admin_message);
  }
  $subject = 'Registration for '.$d['user_name'].' at '.$cfg['gen']['title'];
  return (sendEmail($d['email_address'],$subject,$user_message) && sendEmail($cfg['email']['admin'],$subject,$admin_message));
}

function sendPMEmail($to_address,$to_username,$from_username){
  global $cfg, $base_url, $user, $data;
  $message = file_get_contents($cfg['dir']['template'].'pm_email.tpl');
  $replacements = Array(
    'site_title'                => $cfg['gen']['title'],
    'from_user_name'            => $user->name,
    'to_user_name'              => $to_username,
    'base_href'                 => $base_url,
    'timestamp'                 => formatDateTime(date($cfg['date']['db']),'long'),
  );
  foreach($replacements as $n => $v){
    $message = str_replace('{'.$n.'}',$v,$message);
  }
  $subject = 'New private message from '.$from_username;
  return sendEmail($to_address,$subject,$message);
}

function sendSecurityNoticeEmail($user_name=false){
  global $cfg, $base_url;
  $message = file_get_contents($cfg['dir']['template'].'security_notice.tpl');
  $attempts = $cfg['user']['max_failed_logins_allowed'];
  $replacements = Array(
    'ip'          => $_SERVER['REMOTE_ADDR'],
    'attempts'    => $attempts,
    'base_href'   => $base_url,
    'user_name'   => $user_name,
    'user_agent'  => $_SERVER['HTTP_USER_AGENT'],
  );
  foreach($replacements as $n => $v){
    $message = str_replace('{'.$n.'}',$v,$message);
  }
  $subject = 'Security Notice:  '.$attempts.' unsuccessful login attempts from '.$user_name;
  return sendEmail($cfg['email']['admin'],$subject,$message);  
}

function sendPasswordResetMail($new_password,$user_name,$email_address){
  global $cfg, $base_url;
  $message = file_get_contents($cfg['dir']['template'].'reset_password.tpl');
  $replacements = Array(
    'user_name'         => $user_name,
    'new_password'      => $new_password,
    'base_href'         => $base_url,  
  );
  foreach($replacements as $n => $v){
    $message = str_replace('{'.$n.'}',$v,$message);
  }
  $subject = 'Password reset notification for '.$user_name;
  return sendEmail($email_address,$subject,$message);
}

function numberToWords($n){
  
  if($n < 1) return 'zero';
  if($n > 1000000000000) return $n; // Just return the number if it's out of a reasonable range for this application.
  
  $n = intval($n); // We only deal with integers in this function.
  
  $o = '';
  
  $tens = Array('twenty','thirty','fourty','fifty','sixty','seventy','eighty','ninety');
  $ones = Array('one','two','three','four','five','six','seven','eight','nine','ten','eleven','twelve','thirteen','fourteen','fifteen','sixteen','seventeen','eighteen','nineteen');
  
  $units = Array(
    'trillion'  => 1000000000000,
    'billion'   => 1000000000,
    'million'   => 1000000,
    'thousand'  => 1000,
    'hundred'   => 100,
  );
  
  foreach($units as $unit => $amt){
    $x = floor($n / $amt);
    $n = $n - ($x * $amt);
    $o .= ($x > 0) ? numberToWords($x).' '.$unit.' ' : ' ';
  }
  
  if($n >=20){ // Process tens.
    $x = floor($n / 10);
    $n = $n - ($x * 10);
    $o .= ($x > 0) ? $tens[$x-2].' ' : ' ';
  }
  
  if($n > 0){ // Process ones.
    $o .= $ones[$n-1];
  }
  
  return trim($o);
  
}

function formatTimeDiff($s){
  
  if($s == 0) return 'just now';
  
  $o  = '';
  
  $units = Array(
    'sec'  => 1,
    'min'  => 60,
    'hr'   => 60 * 60,
    'd'    => 60 * 60 * 24,
    'w'    => 60 * 60 * 24 * 7,
    'mo'   => 60 * 60 * 24 * 30,
    'y'    => 60 * 60 * 24 * 365  
  );
  $units = array_reverse($units);

  foreach($units as $unit => $divisor){
    $x = floor($s / $divisor);
    $s = $s - ($x * $divisor);
    $o .= ($x > 0) ? $x.' '.$unit.' ' : ' ';
    // $o .= ($x > 1) ? 's ' : ' ';    // Plurals
  }
    
  return $o;
  
}

function howLongAgo($datetime){
  
  $diff = time() - strtotime($datetime);
  
  $units = Array(
    'second'  => 1,
    'minute'  => 60,
    'hour'    => 60 * 60,
    'day'     => 60 * 60 * 24,
    'week'    => 60 * 60 * 24 * 7,
    'month'   => 60 * 60 * 24 * 30,
    'year'    => 60 * 60 * 24 * 365  
  );
  $units = array_reverse($units);
  
  foreach($units as $unit => $divisor){
    if($diff > $divisor){
      $p = (round($diff / $divisor) > 1) ? 's' : '';
      return round($diff / $divisor).' '.$unit.$p.' ago';
    }
  }
  
  return 'just now';
  
}

function highLight($find,$content){ // $find can be either an array of terms or a single term in a string.
  if((empty($find)) || (empty($content))) return $content;
  if(is_array($find)){
    foreach($find as $term){
      $content = str_ireplace($term,'<span class="highlight">'.$term.'</span>',$content);
    }    
  } else {
    $content = str_ireplace($find,'<span class="highlight">'.$find.'</span>',$content);
  }
  return $content;
}

function floodControl($user_id){
  global $cfg, $data;
  $last_post_time = $data->getMostRecentPostTimeForUser($user_id);
  if(empty($last_post_time)) return false;
  return ((time() - strtotime($last_post_time)) < $cfg['limit']['flood_minimum']);
}

function showUserRank($user_level){
  switch($user_level){
    case 0:
      return 'Unregistered';
    break;
    case 1:
      return 'Standard';
    break;
    case 2:
      return 'Verified';
    break;
    case 3:
      return 'Moderator';
    break;
    case 4:
      return 'Administrator';
    break;    
  }
}

// This displays formating controls and emoticons for content posting forms.
function showPostingControls(){
  global $cfg;
  $o  = "<div id=\"posting_controls\">\n";
  $o .= "Emoticons: <div id=\"emoticons\">\n";
  foreach($cfg['emotes'] as $emote){
    $description = $emote['description'].': '.$emote['symbol'].' or '.$emote['symbol_alt'];
    $o .= "<img class=\"emoticon\" src=\"".$cfg['dir']['emo'].'/'.$emote['filename']."\" alt=\"$description\" title=\"$description\" onClick=\"insertContent('".str_replace("'","\'",$emote['symbol'])."');\" />\n"; 
  }
  $o .= "</div>\n";
  $o .= "Formatting: <br />";
  $formatting_buttons = Array(
    'bold'          => 'b',
    'italic'        => 'i',
    'strikethrough' => 's',
    'underline'     => 'u',
    'code'          => 'code',
    'quote'         => 'quote',
    'image'         => 'img',
    'url'           => 'url',
    'spoiler'       => 'spoiler',
  );
  foreach($formatting_buttons as $label => $symbol){
    $o .= "<a class=\"button\" onclick=\"addFormatting('".$symbol."');\" title=\"".$label."\">".$label."</a>\n";
  }
  $o .= "</div>\n";
  return $o;
}

// Removes all tags from inside <pre> tags.
function cleanPreformatted($s){
  $results = Array();
  $pattern = "'<pre>(.*?)</pre>'si";
  preg_match_all($pattern,$s,$matches);
  if(empty($matches)) return $s;
  foreach($matches[0] as $i => $match){
    $s = str_replace($matches[0][$i],'<pre>'.trim(strip_tags($matches[1][$i])).'</pre>',$s);
  }
  return $s;
}

// This matches forum code tags.
// The results are a 2D associative array of (with tags) => (without tags).
function matchForumTag($tag,$s){
  $results = Array();
  $pattern = "'\[".$tag."\](.*?)\[/".$tag."\]'si"; // Not going to lie.  I have no idea what I'm doing here.  It works, though.
  preg_match_all($pattern,$s,$matches);
  if(empty($matches)) return false;
  // Convert the two sets of matches (first with tags, second without tags) to a 2D array of with => without.
  foreach($matches[0] as $i => $match){
    $results[] = Array($match,$matches[1][$i]);
  }
  return $results;
}

function processImageTags($s){
  $tags = matchForumTag('img',$s);
  if(empty($tags)) return $s;
  foreach($tags as $tag){
    $new = "<img class=\"user_image_link\" src=\"".$tag[1]."\" alt=\"User Image\" />\n";
    $s = str_replace($tag[0],$new,$s);
  }
  return $s;
}

function processURLTags($s){
  $tags = matchForumTag('url',$s);
  if(empty($tags)) return $s;
  foreach($tags as $tag){
    $url_short = explode('/',$tag[1]);
    $url_short = $url_short[2];
    $url = $tag[1];
    $new = "<a href=\"$url\" title=\"User Link to $url\" target=\"_blank\">user link on $url_short</a>";
    $s = str_replace($tag[0],$new,$s);
  }
  return $s;
}

function processQuoteTags($s){
  $tags = matchForumTag('quote',$s);
  if(empty($tags)) return $s;
  foreach($tags as $tag){
    $new = "<div class=\"quote\">".$tag[1]."</div>\n";
    $s = str_replace($tag[0],$new,$s);
  }
  return $s;
}

function processSpoilerTags($s){
  $tags = matchForumTag('spoiler',$s);
  if(empty($tags)) return $s;
  foreach($tags as $tag){
    $new = "spoiler (click to show): <div onclick=\"showSpoiler(this);\" class=\"spoiler\">".$tag[1]."</div>\n";
    $s = str_replace($tag[0],$new,$s);
  }
  return $s;
}

function processPlainTags($t,$s){
  $tags = matchForumTag($t,$s);
  if(empty($tags)) return $s;
  foreach($tags as $tag){
    if($t == 'code') $t = 'pre';
    $new = '<'.$t.'>'.$tag[1].'</'.$t.'>';
    $s = str_replace($tag[0],$new,$s);
  }
  return $s;  
}

// This function applies text services (emoticons, formatting, auto-censor) and other features to content.
// All posts, signatures, and private messages go through this function.
function renderContent($s,$user_name=false){
  
  global $cfg, $user, $data;
    
  // Auto-censor.
  if(!$user->isLoggedIn() || ($user->hide_profanity)){
    foreach($cfg['badwords'] as $badword){
      $replacement = $badword{0}.str_repeat('*',strlen($badword)-1); // Fill with asterisks.  Euphemisms are kind of silly.
      $s = str_ireplace($badword,$replacement,$s);
    }    
  }
  
  // Process special tags.
  $tags = Array('b','i','s','u','code');
  foreach($tags as $tag){
    $s = processPlainTags($tag,$s);
  }
  
  // Process quote tags.
  if(strpos($s,'[quote]') !== false){
    $s = processQuoteTags($s);
  }
  
  // Process image tags.
  if(strpos($s,'[img]') !== false){
    $s = processImageTags($s);
  }  
  
  // Process URL tags.
  if(strpos($s,'[url]') !== false){
    $s = processURLTags($s);
  }
  
  // Process spoiler tags.
  if(strpos($s,'[spoiler]') !== false){
    $s = processSpoilerTags($s);
  } 
  
  // Process emoticons.
  $emotes = array_reverse($cfg['emotes']); // Reverse array because order of detection matters.
  foreach($emotes as $emote){
    $path = $cfg['dir']['emo'].'/';
    $tag = "<img class=\"emoticon\" src=\"".$path.$emote['filename']."\" title=\"".$emote['description']."\" alt=\"".$emote['description']."\" />";
    $find = Array($emote['symbol'],$emote['symbol_alt']);
    $s = str_replace($find,$tag,$s);
  }
  
  // Implement "/me" if a user id is specified.
  if($user_name !== false){
    $s = str_replace('/me','<i>'.$user_name.'</i>',$s);
  }
  
  // If highlight terms are specified in a query string, highlight them.
  $do_not_highlight = Array('b','i','s','u','code','quote','img','url','spoiler'); // Certain matches that will possibly break the HTML if highlighted.
  if(!empty($_GET['hl'])){
    $highlight = $_GET['hl'];
    if(!in_array($highlight,$do_not_highlight)){
      if(strpos($highlight,' ') !== false){
        $highlight = explode(' ',$highlight);
      }
      $s = highLight($highlight,$s);
    }
  }

  // Wrap paragraphs in <p> tags.
  $s = explode("\n",$s);
  foreach($s as $n => $paragraph){
    $s[$n] = "<p>".$paragraph."</p>\n";
  }
  $s = implode('',$s);
  
  // Remove any tags from inside preformatted tags.
  if(strpos($s,'<pre>') !== false){
    $s = cleanPreformatted($s);
  }
  
  // Return processed content.
  return $s;
  
}

function buildTopicBullet($d=false){
  $fn = 'thread_bullet';
  $flags = Array('hidden','sticky','locked');
  foreach($flags as $flag){
    $fn = ($d[$flag] == 1) ? $flag.'_'.$fn : $fn;
  }
  return $fn;  
}

function formatStatsTable($a){ // Cleans up data for a stats table.  Removes "Items" from table headers and rounds decimal numbers.
  if(empty($a)) return false;
  $d = Array();
  foreach($a as $i => $row){
    foreach($row as $n => $v){
      $d[$i][str_replace(Array('items','item'),'',$n)] = (is_numeric($v) ? round($v) : $v);
    }
  }
  return $d;  
}

function displayRegistrationAgreement(){
  global $cfg;
  $text = file_get_contents($cfg['dir']['template'].'registration_agreement.tpl');
  $text = str_replace(Array('{title}','{min_age}'),Array($cfg['gen']['title'],$cfg['user']['minimum_age']),$text);
  return renderContent($text);
}

function getMoniker($post_count=0){
  global $cfg;
  $monikers = explode(',',$cfg['user']['monikers']);
  $p = floor($post_count / ($cfg['user']['moniker_cap'] / (count($monikers) - 1)));
  return ucwords($monikers[$p]);
}

function limitSpikes($d,$ceiling=4){ // Limits extreme values in a 1D array to no more than a ceiling multipler of the average value.
  $avg = ceil(array_sum($d) / count($d));
  foreach($d as $i => $v){
    $d[$i] = ($v > ($avg * $ceiling)) ? ($avg * $ceiling) : $v;
  }
  return $d;
}

function whoIsOnline(){
  global $cfg, $data;
  $online_users = $data->getOnlineUsers();
  $online_user_count = empty($online_users) ? 0 : count($online_users); // This is very weird.  If it's empty, the count isn't zero.
  $visitor_count = $data->getVisitorCount();
  $visitor_count = $visitor_count - $online_user_count;
  $visitor_count = ($visitor_count < 0) ? 0 : $visitor_count;
  $o  = "<div id=\"who_is_online\">\n";
  $o .= "<div class=\"title\">Currently Online</div>\n";
  $o .= "<table>\n";
  if(!empty($online_users)){
    foreach($online_users as $u){
      $profile_link = url(Array('section'=>'profile','user'=>$u['user_id']));
      $avatar  = "<a href=\"".$profile_link."\" title=\"".$u['user_name']."\">";
      $avatar .= "<img class=\"avatar\" src=\"".getAvatarURL($u['avatar_type_id'],$u['avatar_link'])."\" alt=\"".$u['user_name']."\" title=\"".$u['user_name']."\" /></a>";
      $time_online = formatTimeDiff(time() - strtotime($u['login_time']));
      $o .= "<tr><td>$avatar<td><a href=\"$profile_link\" title=\"".$u['user_name']."\">".$u['user_name']."</a><br />$time_online</td></tr>\n";
    }
  }
  $o .= "<tr><td><b>Users:</b></td><td>$online_user_count</td></tr>\n";
  $o .= "<tr><td><b>Guests:</b></td><td>$visitor_count</td></tr>\n";  
  $o .= "</table>\n";
  if($cfg['user']['show_recent_online'] == 1){
    $recent_users = $data->getMostRecentUserList($cfg['user']['recent_online_count']);
    if(!empty($recent_users)){
      $o .= "<br />\n";
      $o .= "<div class=\"title\">Most Recently Online</div>\n";
      $o .= "<table>\n";
      foreach($recent_users as $u){
        $o .= "<tr><td><a href=\"".url(Array('section'=>'profile','user'=>$u['user_id']))."\" title=\"".$u['user_name']."\" >".$u['user_name']."</a></td><td>".howLongAgo($u['time_last_online'])."</td></tr>\n";
      }    
      $o .= "</table>\n";    
    }
  }
  $o .= "</div>\n";
  return $o;
}

  
?>