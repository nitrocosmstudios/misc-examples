<?php 

function redirect($url_array){
	global $base;
	if(empty($url_array)){
		header('Location: '.$base);
	} else {
		header('Location: '.url($url_array));
	}
	exit();
}

function parseURL($dispatch='go'){
	return input::parseURL($dispatch);
}

function url($add=false,$append=false){
	return input::buildURL($add,$append);
}

function getMaxKey($d){
	return array_search(max($d),$d);
}

function array_append($x,$y){
	foreach($y as $n => $v){
		$x[] = $v;
	}
	return $x;
}

function trace($x,$echo=true){
	$x = '<pre>'.print_r($x,1)."</pre>\n";
	if($echo): echo $x; exit(); else: return $x; endif;
}

function loadTemplate($t=false,$p=Array(),$cms=false){
	$dir = ($cms) ? 'cms' : 'pub';
	$s = file_get_contents('../dat/views/tpl/'.$dir.'/'.$t.'.html');
	if(!empty($p)){
		foreach($p as $n => $v){
			$s = str_replace('{'.$n.'}',$v,$s);
		}
	}
	$s = preg_replace("/\{(.*?)\}/",'',$s); // Remove any remaining replacement {tags}.
	return "\n".$s."\n";
}

// General purpose outgoing e-mail function.
function sendEmail($from=false,$subject=false,$message=false){
  global $cfg;
  $headers  = 'From: '.$from."\r\n";
  $headers .= 'Reply-To: '.$from."\r\n";
  $headers .= 'X-Mailer: PHP/'.phpversion()."\r\n";
  $headers .= 'X-Application: '.$cfg['gen']['title'].' - '.$cfg['gen']['version'];
  $message = wordwrap($message,70,"\r\n");
  return @mail($cfg['gen']['mailto'],$subject,$message,$headers); // Don't throw errors if mail() can't send the message.
}

function getStatusFile(){
	return unserialize(file_get_contents('../dat/status.dat'));
}

function updateStatusFile($d){
	file_put_contents('../dat/status.dat',serialize($d));
}
	
	
function buildDate($y=false,$m=false,$d=false,$fmt='db'){
	global $cfg;
	return date($cfg['date'][$fmt],strtotime(sprintf("%04d",intval($y)).'-'.sprintf("%02d",intval($m)).'-'.sprintf("%02d",intval($d))));
}

function buildCalendarNavCode($y=false,$m=false,$d=false){
	$x  = sprintf("%04d",$y).sprintf("%02d",intval($m));
	$x .= !empty($d) ? sprintf("%02d",intval($d)) : '';
	return $x;
}

function parseCalendarNavCode($x){
	if(strlen($x) == 6){
		return Array(intval(substr($x,0,4)),intval(substr($x,4,2)),1);
	} else if(strlen($x) == 8){
		return Array(intval(substr($x,0,4)),intval(substr($x,4,2)),intval(substr($x,6,2)));
	} else {
		return Array(date("Y"),date("m"),date("d"));
	}
}

function getExecutionTime(){
	global $start_time;
	return microtime(true) - $start_time;
}

function getYears(){
	global $cfg;
	$epoch = date('Y',strtotime($cfg['date']['epoch']));
	$apocalypse = date('Y') + 10;
	$d = Array();
	for($i=$epoch;$i<=$apocalypse;$i++){
		$d[$i] = $i;
	}
	return $d;
}

function getMonths(){
	$d = Array();
	for($i=1;$i<=12;$i++){
		$t = mktime(0,0,0,$i,1,1994);
		$d[sprintf("%02d",$i)] = date('F',$t);
	}
	return $d;
}

function indexGalleryTitles($d){
	if(empty($d)) return false;
	$t = Array();
	foreach($d as $row){
		$t[$row['item_ord']] = $row['title'];
	}
	return $t;	
}

// Function to determine the item_ord for first, previous, next, and last items in a gallery item list.
function calculateGalleryNavigation($d,$id){
	if(empty($d)) return false;
	$ret = array_fill_keys(Array('first','previous','next','last'),'');
	$last = count($d) - 1;
	$pos = 0;
	$ret['first'] = $d[0]['item_ord']; // Initial value.
	$ret['last'] = $d[$last]['item_ord'];
	foreach($d as $i => $row){
		if($id == $row['item_ord']) $pos = $i;
	}
	$ret['previous'] = ($pos === 0) ? $d[0]['item_ord'] : $d[$pos-1]['item_ord'];
	$ret['next'] = ($pos === $last) ? $d[$last]['item_ord'] : $d[$pos+1]['item_ord'];	
	return $ret;
}

function calculatePageStart($page,$pg_size){
	return ($pg_size * ($page - 1));
}

// Calculates the page in which a particular track item can be found.
function calculateTrackPage($enum=false,$item_ord=false){
	global $cfg;
	$page_size = $cfg['limit']['track_page'];
	if(!$enum || !$item_ord || ($page_size < 1)) return 1;
	foreach($enum as $i => $ord){
		if($ord == $item_ord) return (floor($i / $page_size)+1);
	}
	return 1;		
}

// Grabs the section title for the corresponding section name using the menu system data (which is always available across the whole site)
function getSectionTitleFromSectionName($section_name){
	global $menu_system;
	foreach($menu_system as $row){
		if($row['section_name'] == $section_name) return $row['title'];
	}
	return ucwords(str_replace('_',' ',$section_name)); // If not found, just return a formatted version of the section name.
}


?>