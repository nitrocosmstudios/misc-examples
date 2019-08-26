<?php

/**
*
* This class facilitates the generation of charts!
* NOTE:  GD 2.0.1 or later is required!
*
*/

class nitro_charts{

  // NOTE:  The properties of this class should probably be set in the config.ini later on.

  // Creates a generic pallette of colors for general use.
  // Red, Green, Blue values.
  // IMPORTANT:  Keep all values above 20.
  var $colors = Array(
    0 => Array(240,50,24),
    1 => Array(200,180,24),
    2 => Array(100,240,160),
    3 => Array(50,80,210),
    4 => Array(80,40,190),
    5 => Array(240,70,10),  
  );
  
  // Default background color.
  var $bgcolor = Array(230,230,230);
  
  // Set the truetype font relative path to use in charts (will use getcwd(); as a prefix).
  var $font = 'trebuc.ttf';
  
  // Set the font size
  var $font_size = 7;
  
  // The working size enlargement factor, for oversampling.  This will result in better quality.  Must be 1 or higher!
  var $oversampling = 3;
  
  // The display string length cutoff for bar / line chart labels.
  var $bar_label_cutoff = 7;
  
  // The display string length cutoff for bar / line values.
  var $bar_value_cutoff = 6;
  
  // The display string length cutoff for pie chart labels.
  var $pie_label_cutoff = 18;
  
  // The display string length cutoff for pie chart values.
  var $pie_value_cutoff = 3;
  
  // The output file.  By default (false), it outputs directly instead of to a specified file.
  var $output_file = false;
  
  function setFontSize($x){
    $this->font_size = $x;
  }
  
  function setOverSampling($x){
    $this->oversampling = $x;
  }
  
  function setOutputFile($f){
    $this->output_file = $f;
  }
  
  /**
  * Loads the font.
  */
  function loadFont($path,$font_name){
    $this->font = $font_name;
    putenv('GDFONTPATH='.$path);
  }
  
  /**
  * Accepts a hex code for a background color.
  * @param $x = the color hex code.
  * @example 000000 for black, FFFFFF for white.
  */
  function setBackgroundColor($h){
    $r = hexdec(substr($h,0,2));
    $g = hexdec(substr($h,2,2));
    $b = hexdec(substr($h,4,2));
    $this->bgcolor = Array($r,$g,$b);  
  }
  
  /**
  * Allows easily specifying new colors by hex code.
  */
  function makeColor($img,$h){
    $r = hexdec(substr($h,0,2));
    $g = hexdec(substr($h,2,2));
    $b = hexdec(substr($h,4,2));
    return imagecolorallocate($img,$r,$g,$b);
  }
  
  /**
  * Allows setting a custom preset list of colors by hex code!
  * @param $a = a one-dimensional array of hex codes!  !tuna!
  * NOTE:  Leave OUT the '#' in the hex codes.
  */
  function setColorsByHex($a){
    $this->colors = Array();
    foreach($a as $h){
      $r = hexdec(substr($h,0,2));
      $g = hexdec(substr($h,2,2));
      $b = hexdec(substr($h,4,2));
      $this->colors[] = Array($r,$g,$b);    
    }
  }
    
  /**
  * Generates a list of random colors.
  * @param $img = the GD image, passed by reference.
  * @param $n = how many random colors to generate.
  * @param $s = the brightness difference between light, medium, and dark shades.
  * @return $c = an array of the random colors.
  */  
  function getRandomColors(&$img,$n,$s=20){
    $c = Array('l'=>Array(),'d'=>Array());
    for($i=0;$i<=$n;$i++){
      $r = mt_rand($s,255);
      $g = mt_rand($s,255);
      $b = mt_rand($s,255);
      $rh = ($r+$s > 255) ? 255 : $r + $s;
      $gh = ($g+$s > 255) ? 255 : $g + $s;
      $bh = ($b+$s > 255) ? 255 : $b + $s;
      $c['h'][] = imagecolorallocate($img,$rh,$gh,$bh);
      $c['l'][] = imagecolorallocate($img,$r,$g,$b);
      $c['d'][] = imagecolorallocate($img,$r-$s,$g-$s,$b-$s);
    }
    return $c;
  }
  
  function setRandomColors($n,$s=20){
    $c = Array();
    for($i=0;$i<=$n;$i++){
      $r = mt_rand($s,255);
      $g = mt_rand($s,255);
      $b = mt_rand($s,255);
      $c[] = Array($r,$g,$b);
    }
    $this->colors = $c;
  }
  /**
  * Same as above but uses the list of pre-defined colors instead of a random, variable-length list.
  */  
  function getPresetColors(&$img,$s=20){
    $c = Array('l'=>Array(),'d'=>Array());
    foreach($this->colors as $color){
      $r = ($color[0] < $s) ? $color[0] + $s : $color[0];
      $g = ($color[1] < $s) ? $color[1] + $s : $color[1];
      $b = ($color[2] < $s) ? $color[2] + $s : $color[2];
      $rh = ($r+$s > 255) ? 255 : $r + $s;
      $gh = ($g+$s > 255) ? 255 : $g + $s;
      $bh = ($b+$s > 255) ? 255 : $b + $s;
      $c['h'][] = imagecolorallocate($img,$rh,$gh,$bh);
      $c['l'][] = imagecolorallocate($img,$r,$g,$b);
      $c['d'][] = imagecolorallocate($img,$r-$s,$g-$s,$b-$s);
    }
    return $c;  
  }
  
  /**
  * Sets up the preset background color.
  */
  function getBackgroundColor(&$img){
    $r = $this->bgcolor[0];
    $g = $this->bgcolor[1];
    $b = $this->bgcolor[2];
    return imagecolorallocate($img,$r,$g,$b);
  }
    
  /**
  * Generates a pie chart.  Mmm, pie.
  * @param $d = an array of data in key => value pairs.  The values must be numeric.
  * @param $x = the x position of the chart center.
  * @param $y = the y position of the chart center.
  * @param $cw = the width of the chart.
  * @param $ch = the height of the chart.
  * @param $w = the width of the overall image.
  * @param $h = the height of the overall image.
  * @param $kw = the width of the key.
  * @param $t = (optional) the 'thickness' of the pie chart.
  * @param $s = (optional) the spacing, in degrees, between pie slices.  Mmm, pie...
  */  
  function createPieChart($d,$x,$y,$cw,$ch,$w,$h,$kw=300,$t=8,$s=6){
    // Oversampling adjustments
    $nw = $w;
    $nh = $h;
    $cw = $cw * $this->oversampling;
    $ch = $ch * $this->oversampling;
    $w = $w * $this->oversampling;
    $h = $h * $this->oversampling;
    $x = $x * $this->oversampling;
    $y = $y * $this->oversampling;
    $t = $t * $this->oversampling;
    $kw = $kw * $this->oversampling;
    // Start image
    $img = imagecreatetruecolor($w,$h);
    // Get colors
    // $colors = $this->getRandomColors($img,25,30);
    $colors = $this->getPresetColors($img,30);
    $bgcolor = $this->getBackgroundColor($img);
    // Make some more colors.
    $black = $this->makeColor($img,'000000');
    $grey = $this->makeColor($img,'eeeeee');
    $darkgrey = $this->makeColor($img,'888888');
    $white = $this->makeColor($img,'ffffff');
    // Apply the background color.
    imagefilledrectangle($img,0,0,$w,$h,$bgcolor);
    // Calculate the percentage / corresponding degrees-out-of-360 for each value given in the data.
    $sum = 0;
    $deg = Array();
    $pct = Array();
    foreach($d as $n => $v){ $sum += $v; }
    foreach($d as $n => $v){
      $degrees = round(360 * ($v / $sum)) - $s;
      // Permit not the degrees to be less than 1.      
      $deg[] = ($degrees < 1) ? 1 : $degrees;
      $pct[$n] = round(($v / $sum) * 100);
    }
    
    $tc = count($colors['d']); // Total number of available pallete colors.  Resets to first if we run out.
    $hlt = 2 * $this->oversampling; // Highlight ridge thickness
    
    // Create the underlying '3D' 'stack' of slices. (Repetitively make and overlay them, moving each iteration up 1px)
    for($i=$y+$t;$i>$y-1;$i--){
      $dgt = 0; // Starting angle.
      $cc = 0; // Counter for colors      
      $shade = ($i <= $y+$hlt) ? 'h' : 'd';
      foreach($deg as $dg){
        if($cc == $tc) $cc = 0;
        imagefilledarc($img,$x,$i,$cw,$ch,$dgt,$dgt+$dg,$colors[$shade][$cc],IMG_ARC_PIE);
        $dgt += $dg + $s;
        $dgt = ($dgt > 360) ? 360 : $dgt;
        $cc++;
      }    
    }    
    
    // Create the top-layer pie slices.
    $dgt = 0; // Starting angle.
    $cc = 0; // Counter for colors
    foreach($deg as $dg){
      if($cc == $tc) $cc = 0;
      imagefilledarc($img,$x,$y,$cw,$ch,$dgt,$dgt+$dg,$colors['l'][$cc],IMG_ARC_PIE);
      $dgt += $dg + $s;
      $dgt = ($dgt > 360) ? 360 : $dgt;
      $cc++;
    }

    // Create the key to the chart.
    $key_margin = 8 * $this->oversampling;
    $key_padding = 10 * $this->oversampling;
    $key_start_x_pos = $w - $kw - $key_margin + $key_padding;
    $key_start_y_pos = $key_margin + $key_padding;
    $key_bg_x_pos = $w - $kw - $key_margin;
    $key_bg_y_pos = $key_margin;
    $kbgso = 2 * $this->oversampling; // Key background shadow offset
    $cc = 0; // Color Counter.
    $xpos = $key_start_x_pos;
    $ypos = $key_start_y_pos;
    $ygap_factor = 1.2;
    $yspacing = round((($this->font_size + ($this->font_size / $ygap_factor))) * $this->oversampling);
    $square_size = 6 * $this->oversampling;
    $text_indent = 11 * $this->oversampling;
    $font_size = $this->font_size * $this->oversampling;
    $bw = 1 * $this->oversampling; // Border widths
    // Draw the chart background
    $this->roundedRectangle($img,$key_bg_x_pos+$kbgso,$key_bg_y_pos+$kbgso,$w-$key_margin+$kbgso,$h-$key_margin+$kbgso,8*$this->oversampling,$darkgrey); // Key bg Shadow
    $this->roundedRectangle($img,$key_bg_x_pos,$key_bg_y_pos,$w-$key_margin,$h-$key_margin,8*$this->oversampling,$grey); // Main key background
    // Draw the chart background.
    foreach($d as $n => $v){      
      if($cc == $tc) $cc = 0;      
      imagefilledrectangle($img,$xpos-$bw,$ypos-$bw,$xpos+$square_size+$bw,$ypos+$square_size+$bw,$black);
      imagefilledrectangle($img,$xpos,$ypos,$xpos+$square_size,$ypos+$square_size,$colors['l'][$cc]);
      imagettftext($img,$font_size,0,$xpos+$text_indent,$ypos+$square_size,$black,$this->font,substr($n,0,$this->pie_label_cutoff).' '.substr($v,0,$this->pie_value_cutoff).' ('.$pct[$n].'%)');
      $ypos += $yspacing;
      $cc++;    
    }
    
    // Plot the percentage value labels on the chart.
    // NOTE:  This was really tough!
    $ld = 18 * $this->oversampling; // Label distance, from edge of chart.
    $lda_x = - 10 * $this->oversampling; // Label position adjustment.
    $lda_y = + 8 * $this->oversampling; // Label position adjustment.
    $dgc = 0;
    $dgi = 0;
    foreach($d as $n => $v){
      $dg = $deg[$dgi];
      $dgc += $dg + $s;
      $dg_centered = $dgc - ($dg / 2) - $s;
      $t = $this->getEllipticalPolar($x,$y,$cw,$ch,$dg_centered,$ld);
      $tx = $t['x'] + $lda_x;
      $ty = $t['y'] + $lda_y;
      // Prevent x coordinate from being pushed off-image.
      $tx = ($tx < 1*$this->oversampling) ? $this->oversampling : $tx;
      $dgi++;      
      imagettftext($img,$font_size,0,$tx,$ty,$black,$this->font,$pct[$n].'%');    
    }

    // Down-sample the image to the intended size.
    $newimg = imagecreatetruecolor($nw,$nh);
    imagecopyresampled($newimg,$img,0,0,0,0,$nw,$nh,$w,$h);
    imagedestroy($img);
  
    // Return the final image.
    if($this->output_file === false){
      header('Content-type: image/png');
      imagepng($newimg);
    } else {
      imagepng($newimg,$this->output_file);
    }
    imagedestroy($newimg);
  }
  
  /**
  * Calculates the x,y coordinates for a given set of polar coordinates in an ellipse.
  * @param $x = the x coordinate of the ellipse center.
  * @param $y = the y coordinate of the ellipse center.
  * @param $w = the width of the ellipse. NOTE:  This will serve as the measurement used for the radius.
  * @param $h = the height of the ellipse.  The resulting coordinates will be adjusted to the aspect ratio of the ellipse.
  * @param $a = the angle from the ellipse center to calculate the final coordinate pair.
  * @param $d = the distance from the edge of the ellipse at which to plot the final coordinates.
  * @return Array = the pair of coordintes, x and y, relative to the ellipse center, adjusted for the ellipse aspect ratio.
  */
  function getEllipticalPolar($x,$y,$w,$h,$a,$d=0){
    $rads = $a * (M_PI / 180); // Convert angle degress to radians.
    $l = ($w / 2) + $d; // Length of radius.
    $kx = $l * cos($rads);
    $ky = $l * sin($rads); // We're calculating for a circle radius right now.
    // Now, adjust height coordinate for the aspect ratio.
    $ratio = $h / $w;
    $ky = $ky * $ratio;
    // Return the coordinates, rounded to integers.
    return Array(
      'x' => $x + round($kx),
      'y' => $y + round($ky),
    );  
  }
  
  function roundedRectangle($im,$x,$y,$cx,$cy,$rad,$col){
    // Draw the middle cross shape of the rectangle
    imagefilledrectangle($im,$x,$y+$rad,$cx,$cy-$rad,$col);
    imagefilledrectangle($im,$x+$rad,$y,$cx-$rad,$cy,$col);
    $dia = $rad*2;
    // Now fill in the rounded corners
    imagefilledellipse($im, $x+$rad, $y+$rad, $rad*2, $dia, $col);
    imagefilledellipse($im, $x+$rad, $cy-$rad, $rad*2, $dia, $col);
    imagefilledellipse($im, $cx-$rad, $cy-$rad, $rad*2, $dia, $col);
    imagefilledellipse($im, $cx-$rad, $y+$rad, $rad*2, $dia, $col);
  }
  
  function createBarChart($d,$w,$h,$c='000000'){
    // Oversampling adjustments
    $nw = $w;
    $nh = $h;
    $w = $w * $this->oversampling;
    $h = $h * $this->oversampling;
    // Start image
    $img = imagecreatetruecolor($w,$h);
    // Get colors
    // $colors = $this->getRandomColors($img,25,30);
    $colors = $this->getPresetColors($img,30);
    $bgcolor = $this->getBackgroundColor($img);
    // Make some more colors.
    $black = $this->makeColor($img,'000000');
    $grey = $this->makeColor($img,'cccccc');
    $darkgrey = $this->makeColor($img,'888888');
    $white = $this->makeColor($img,'ffffff');
    // Make the specified main bar color.
    $main_color = $this->makeColor($img,$c);
    // Apply the background color.
    imagefilledrectangle($img,0,0,$w,$h,$bgcolor);
    $bar_spacing = 2 * $this->oversampling;
    $top_margin = 6 * $this->oversampling;
    $right_margin = 12 * $this->oversampling;
    $left_margin = 44 * $this->oversampling;
    $bottom_margin = 44 * $this->oversampling;
    // Calculate the widths, heights, and positioning of each bar and plot it.
    $max = 1; // 1 so we don't divide by zero if all values are zero.
    $bar_total = 0;
    $bars = Array();
    foreach($d as $v){ $max = ($v > $max) ? $v : $max; $bar_total++;}
    foreach($d as $n => $v){
      $bars[$n]['h'] = round(($h - $top_margin - $bottom_margin) * ($v / $max));
      $bars[$n]['w'] = round(($w - $left_margin - $right_margin) / $bar_total) - $bar_spacing;    
    }
    // Create background gridlines
    for($i=1;$i<=4;$i++){
      $f = round($i * ($max / 4));
      $sy = ($h - $bottom_margin) - round(($h - $top_margin - $bottom_margin) * ($f / $max));
      imageline($img,$left_margin,$sy,$w-$right_margin,$sy,$grey);
    }
    $xpos = $left_margin;
    $ypos = $h - $bottom_margin;
    $font_size = $this->font_size * $this->oversampling;
    foreach($bars as $n => $bar){
      imagefilledrectangle($img,$xpos+$bar['w'],$ypos,$xpos,$ypos-$bar['h'],$main_color);
      imagettftext($img,$font_size,90,round($xpos+($bar['w'])),$h-(4*$this->oversampling),$black,$this->font,substr($n,0,$this->bar_label_cutoff));
      imageline($img,round($xpos+($bar['w']/2)),$ypos+(2*$this->oversampling),round($xpos+($bar['w']/2)),$ypos+(6*$this->oversampling),$darkgrey);
      $xpos = $xpos + $bar['w'] + $bar_spacing;      
    }
    imageline($img,$left_margin-$bar_spacing,$h-$bottom_margin,$w-$right_margin,$h-$bottom_margin,$black);
    // Create vertical scale
    imageline($img,$left_margin-$bar_spacing,$top_margin,$left_margin-$bar_spacing,$h-$bottom_margin,$black);
    // Calculate quarter-marks along the vertical scale.
    for($i=1;$i<=4;$i++){
      $f = round($i * ($max / 4));
      $sy = ($h - $bottom_margin) - round(($h - $top_margin - $bottom_margin) * ($f / $max));
      imageline($img,$left_margin-$bar_spacing,$sy,$left_margin-(8*$this->oversampling),$sy,$black);
      imagettftext($img,$font_size,0,(4*$this->oversampling),$sy+(4*$this->oversampling),$black,$this->font,substr($f,0,$this->bar_value_cutoff));
    }
  
    // Down-sample the image to the intended size.
    $newimg = imagecreatetruecolor($nw,$nh);
    imagecopyresampled($newimg,$img,0,0,0,0,$nw,$nh,$w,$h);
    imagedestroy($img);
  
    // Return the final image.
    if($this->output_file === false){
      header('Content-type: image/png');
      imagepng($newimg);
    } else {
      imagepng($newimg,$this->output_file);
    }
    imagedestroy($newimg);
  }
  
  function createLineChart($d,$w,$h,$c='000000',$line_thickness=4){
    // Oversampling adjustments
    $nw = $w;
    $nh = $h;
    $w = $w * $this->oversampling;
    $h = $h * $this->oversampling;
    // Start image
    $img = imagecreatetruecolor($w,$h);
    // Get colors
    // $colors = $this->getRandomColors($img,25,30);
    $colors = $this->getPresetColors($img,30);
    $bgcolor = $this->getBackgroundColor($img);
    // Make some more colors.
    $black = $this->makeColor($img,'000000');
    $grey = $this->makeColor($img,'cccccc');
    $darkgrey = $this->makeColor($img,'888888');
    $white = $this->makeColor($img,'ffffff');
    // Make the specified main bar color.
    $main_color = $this->makeColor($img,$c);
    // Apply the background color.
    imagefilledrectangle($img,0,0,$w,$h,$bgcolor);
    $top_margin = 6 * $this->oversampling;
    $right_margin = 12 * $this->oversampling;
    $left_margin = 44 * $this->oversampling;
    $bottom_margin = 44 * $this->oversampling;
    // Calculate the widths, heights, and positioning of each bar and plot it.
    $max = 1; // 1 so we don't divide by zero if all values are zero.
    $point_total = 0;
    $points = Array();
    foreach($d as $v){ $max = ($v > $max) ? $v : $max; $point_total++;}
    foreach($d as $n => $v){
      $points[$n] = round(($h - $top_margin - $bottom_margin) * ($v / $max));
    }
    // Determine interval for X axis labels
    $x_label_interval = floor($point_total / 10);
    // Calculate point gap based on how many points exist.
    $point_gap = floor(($w - $left_margin - $right_margin) / $point_total);
    // Create background gridlines
    for($i=1;$i<=4;$i++){
      $f = round($i * ($max / 4));
      $sy = ($h - $bottom_margin) - round(($h - $top_margin - $bottom_margin) * ($f / $max));
      imageline($img,$left_margin,$sy,$w-$right_margin,$sy,$grey);
    }
    $xpos = $left_margin;
    $ypos = $h - $bottom_margin;
    $font_size = $this->font_size * $this->oversampling;
    // Repeat the line drawing procedure, for thickness.
    $prev_xpos = $xpos; // Starting X position.
    $prev_ypos = $ypos; // Starting Y position.
    $counter = 0;
    foreach($points as $n => $point){
      for($tos=0;$tos<=$line_thickness*$this->oversampling;$tos++){
        imageline($img,$prev_xpos+$tos,$prev_ypos+$tos,$xpos+$tos,($ypos-$point)+$tos,$main_color);
      }
      if(((($counter % $x_label_interval) == 0) && (($counter + $x_label_interval) < $point_total)) || ($counter == $point_total - 1)){
        imagettftext($img,$font_size,90,round($xpos),$h-(4*$this->oversampling),$black,$this->font,substr($n,0,$this->bar_label_cutoff));
        imageline($img,$xpos,$ypos+(2*$this->oversampling),$xpos,$ypos+(6*$this->oversampling),$darkgrey);
      }
      $prev_xpos = $xpos;
      $prev_ypos = $ypos-$point;
      $xpos = $xpos + $point_gap;
      $counter++;
    }
    imageline($img,$left_margin,$h-$bottom_margin,$w-$right_margin,$h-$bottom_margin,$black);
    // Create vertical scale
    imageline($img,$left_margin,$top_margin,$left_margin,$h-$bottom_margin,$black);
    // Calculate quarter-marks along the vertical scale.
    for($i=1;$i<=4;$i++){
      $f = round($i * ($max / 4));
      $sy = ($h - $bottom_margin) - round(($h - $top_margin - $bottom_margin) * ($f / $max));
      imageline($img,$left_margin,$sy,$left_margin-(8*$this->oversampling),$sy,$black);
      imagettftext($img,$font_size,0,(4*$this->oversampling),$sy+(4*$this->oversampling),$black,$this->font,substr($f,0,$this->bar_value_cutoff));
    }
  
    // Down-sample the image to the intended size.
    $newimg = imagecreatetruecolor($nw,$nh);
    imagecopyresampled($newimg,$img,0,0,0,0,$nw,$nh,$w,$h);
    imagedestroy($img);
  
    // Return the final image.
    if($this->output_file === false){
      header('Content-type: image/png');
      imagepng($newimg);
    } else {
      imagepng($newimg,$this->output_file);
    }
    imagedestroy($newimg);
  }

}

/************************************************
*  USAGE GUIDE
************************************************/

// error_reporting(E_ALL);
// Proving Grounds - Examples and usage guide (mainly just remnants of testing phase!)

/**

$chart = new nitro_charts();

// Set colors.
$colors = Array(
  '003366',
  'FFAA00',
  'AA44BB',
  '22CCFF',
  '00AA44',
  '5522CC',
);
$chart->setColorsByHex($colors);
// $chart->setBackgroundColor('ffffff');

// Set font path and font file name.
$chart->loadFont('../../fonts/','trebuc.ttf');

$d = Array('men'=>17,'mice'=>34,'donuts'=>173,'tires'=>82,'fire'=>38);
$chart->createPieChart($d,95,65,160,80,345,145,150);

*/

/**********************************************************/

/*

// Make the object.
$chart = new nitro_charts();
// Set the font.
$chart->loadFont('../../fonts/','trebuc.ttf');
// Make some random dummy data for testing.
$d = Array();
for($i=1;$i<=12;$i++){
  // $d[date("M",mktime(0,0,0,$i,1,2009))] = mt_rand(0,150);
  $d[date("M",mktime(0,0,0,$i,1,2009))] = $i * 400;
}
$chart->setBackgroundColor('ffffff');
$chart->setOverSampling(1);
// Whammo.
$chart->createBarChart($d,345,145,'4422FF');

*/

?>