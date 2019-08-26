<?php

/**
* @name RSS feed class
* @author Troy McQuinn
* @version (see config.ini under "version")
* @abstract These functions render XML for RSS feeds.
*/

class rss{

  /**
  * Creates an XML tag.
  * @param $tag = the name of the tag.
  * @param $data = the contents of the tag.
  * @param $cdata = true if it should be wrapped in the CDATA tag, false if not. // Added 2010-02-17 TMC
  * @return $x = the formatted XML tag.
  */
  static function tag($tag=false,$data=false,$cdata=false){
    if(($tag === false) || ($data === false)) return false;
    if($cdata === false){
      $x = '<'.$tag.'>'.$data.'</'.$tag.'>'."\n";
    } else {
      $x = '<'.$tag.'><![CDATA['.$data.']]></'.$tag.'>'."\n";
    }
    return $x;
  }
  
  /**
  * Creates XML for an array of items.
  * @param array $d = the data to format in XML.
  * @return $x = the formatted XML.
  */
  static function makeXML($d=false){
    if($d === false) return false;
    global $base, $cfg, $dbo;
		if(strpos($cfg['url']['media'],$base) === false){
			$cfg['url']['media'] = $base.$cfg['url']['media'];
		}
    $x = '';
    $x .= self::tag('title',$cfg['gen']['title'],true);
    $x .= self::tag('description',$cfg['gen']['description'],true);
    $x .= self::tag('link',$base);
    foreach($d as $row){
			switch($row['section_type']){
					case 'gallery':
						$item_page = $row['item_ord'];
						$thumbnail_url = (!empty($row['image_type_thumbnail'])) ? $cfg['url']['media'].$row['section_name'].'/t/'.sprintf("%03d",$row['item_ord']).'.'.$row['image_type_thumbnail'] : '';
					break;
					case 'track':
						if(!isset($track_enum)){
							$track_enum = $dbo->enumerateSectionType('track');
						}
						$item_page = calculateTrackPage($track_enum[$row['section_name']],$row['item_ord']);
						$thumbnail_filename = explode('.',$row['file_name'])[0];
						$thumbnail_url = (!empty($row['image_type_thumbnail'])) ? $cfg['url']['media'].$row['section_name'].'/t/'.$thumbnail_filename.'.'.$row['image_type_thumbnail'] : '';
					break;
					case 'video':
						$item_page = $row['item_ord'];
						$thumbnail_filename = explode('.',$row['file_name'])[0];
						$thumbnail_url = (!empty($row['image_type_thumbnail'])) ? $cfg['url']['media'].$row['section_name'].'/t/'.$thumbnail_filename.'.'.$row['image_type_thumbnail'] : '';
					break;
					case 'list':
          case 'status':
						if(!isset($list_enum)){
							$list_enum = $dbo->enumerateSectionType('list');
						}
						$item_page = calculateListPage($list_enum[$row['section_name']],$row['item_ord']);
						$thumbnail_url = false;
					break;
					case 'article':
					default:
						$item_page = $row['item_ord'];
						$thumbnail_url = false;
					break;
			}
      $x .= "<item>\n";
      $x .= (!empty($thumbnail_url)) ? "<media:thumbnail url='$thumbnail_url' />\n" : '';
      $x .= self::tag('title',strip_tags($row['title']),true);
      $x .= self::tag('description',strip_tags($row['description']),true);
      $link = url(Array('section'=>$row['section_name'],'page'=>$item_page));
      $x .= self::tag('link',$link);
      $x .= self::tag('guid',$link.'#'.$item_page);
      $x .= self::tag('pubDate',gmdate($cfg['date']['gmt'],(strtotime($row['datetime']) - ($cfg['date']['server_timezone'] * 3600))));
      $x .= "</item>\n";
    }
    return $x;
  }
  
  static function makeStatusXML($d=false){
    if($d === false) return false;
    global $base, $cfg;
    $x = '';
    $x .= self::tag('title',$cfg['gen']['title'],true);
    $x .= self::tag('description',$cfg['gen']['description'],true);
    $x .= self::tag('link',$base);
    foreach($d as $row){
      $x .= "<item>\n";
      $x .= self::tag('title',strip_tags($row['title']),true);
      $x .= self::tag('description',strip_tags($row['description']),true);
      $x .= self::tag('pubDate',gmdate($cfg['date']['gmt'],(strtotime($row['datetime']) - ($cfg['date']['server_timezone'] * 3600))));
      $x .= "</item>\n";
    }
    return $x;
  }
  
  static function makeEventsXML($d=false){
    if($d === false) return false;
    global $base, $cfg;
    $x = '';
    $x .= self::tag('title',$cfg['gen']['title'],true);
    $x .= self::tag('description',$cfg['gen']['description'],true);
    $x .= self::tag('link',$base);
    foreach($d as $row){
      $x .= "<item>\n";
      $x .= self::tag('title',strip_tags($row['title']),true);
      $x .= self::tag('description',strip_tags($row['description']),true);
      $x .= self::tag('pubDate',gmdate($cfg['date']['gmt'],(strtotime($row['datetime']) - ($cfg['date']['server_timezone'] * 3600))));
      $x .= "</item>\n";
    }
    return $x;
  }
  
  /**
  * Creates XML formatted for Apple iTunes-based podcast feeds.
  * @param array $d = the data to format into XML.
  * @return $x = the formatted XML.
  */
  static function makePodCastXML($d=false,$section='podcasts'){
    if($d === false) return false;
    global $base, $cfg;
		if(strpos($cfg['url']['media'],$base) === false){
			$cfg['url']['media'] = $base.$cfg['url']['media'];
		}
    $x = '';
    $x .= self::tag('title',$cfg['podcast']['podcast_name'],true);
    $x .= self::tag('link',url(Array('section'=>$section)));
    $x .= self::tag('language','en-us');
    $x .= self::tag('copyright','&#xA9; '.date("Y").' '.$cfg['podcast']['podcast_name']);
    $x .= self::tag('itunes:subtitle',$cfg['podcast']['podcast_subtitle']);
    $x .= self::tag('itunes:author',$cfg['podcast']['podcast_author']);
    $x .= self::tag('itunes:summary',$cfg['podcast']['podcast_description']);
    $x .= self::tag('description',$cfg['podcast']['podcast_description']);
    $x .= "<itunes:owner>\n";
    $x .= self::tag('itunes:name',$cfg['podcast']['podcast_author']);
    $x .= self::tag('itunes:email','');    
    $x .= "</itunes:owner>\n";
		$image_url = $cfg['url']['media'].$section.'/t/'.explode('.',$d[0]['file_name'])[0].'.'.$d[0]['image_type_thumbnail'];
    $x .= "<itunes:image href=\"$image_url\" />\n";
    $categories = explode(',',$cfg['podcast']['podcast_categories']);
    foreach($categories as $category){
      $x .= "<itunes:category text=\"$category\" />\n";
    }
    foreach($d as $item_id => $item){
      $url = $cfg['url']['media'].$section.'/'.$item['file_name'];
			$x .= "<item>\n";
      $x .= self::tag('title','Episode No. '.$item['item_ord']);
      $x .= self::tag('itunes:author',$cfg['podcast']['podcast_author']);
      $x .= self::tag('itunes:subtitle',$item['title']);
      $x .= self::tag('itunes:summary',strip_tags($item['description']));
      $x .= "<enclosure url=\"$url\" length=\"".$item['file_size']."\" type=\"audio/mpeg\" />\n";
      $x .= self::tag('guid',$url);
      $x .= self::tag('pubDate',gmdate($cfg['date']['podcast'],(strtotime($item['datetime']) - ($cfg['date']['server_timezone'] * 3600))));
      $duration = Array();
      $duration['hours'] = floor($item['length'] / 60 / 60);
      $duration['minutes'] = floor(floor($item['length'] / 60) - floor($duration['hours'] * 60));
      $duration['seconds'] = floor(floor($item['length'] - floor($duration['minutes'] * 60) - floor($duration['hours'] * 60 * 60)));
      $duration_formatted = ':'.sprintf("%02d",$duration['seconds']);
      $duration_formatted = ($duration['minutes'] > 0) ? sprintf("%02d",$duration['minutes']).$duration_formatted : $duration_formatted;
      $duration_formatted = ($duration['hours'] > 0) ? $duration['hours'].':'.$duration_formatted : $duration_formatted;
      $x .= self::tag('itunes:duration',$duration_formatted);
      $x .= self::tag('itunes:keywords','');    
      $x .= "</item>\n";
    }
    return $x;
  }



}

?>