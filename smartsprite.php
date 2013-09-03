<?php
/**
  class smartsprite
 
  @author Alexander Kaupp <tanila@tanila.org>
  @version 0.5.8
  @package tdev_smartsprite
 
 
 Software License Agreement (BSD License)
 Copyright (c) 2009, tanila.de tanila.org
 All rights reserved.

Redistribution and use in source and binary forms, 
with or without modification, are permitted provided that the following
conditions are met:

* Redistributions of source code must retain the above copyright notice,
  this list of conditions and the following disclaimer.
* Redistributions in binary form must reproduce the above copyright 
  notice, this list of conditions and the following disclaimer in the 
  documentation and/or other materials provided with the  distribution.
* Neither the name of tanila.de and tanila.org nor the names of its 
  contributors may be used to endorse or promote products derived from
  this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT 
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A 
PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT 
HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, 
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED
TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR 
PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF 
LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT 
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

 
 
 @todo  dockblocks, finish, verbosity set by args

  color count not working atm...
 
 @filesource
 */

error_reporting(E_ALL);
//class_exists('tcssparser') || require_once('tcssparser.php');

class Smartsprite {
  var 
  $cssParser = 0,
  $version = '0.5.8',
  $verbose = true,
  // css-file to parse
  $_filename = '',
  // the content of the original file
  $_fileCntOrig = '',
  // the content of the parsed file
  $_fileCntParsed = '',
  // Folder to store temporary files (write access 4 the webserver)
  $_tmpFolder = '',
  // regExes 4 parsing
  $regExSpriteName  = '/sprite:\s*(.*);/i',
  $regExSpriteUrl   = '/sprite-image:\s*url\((.*)\);/i',
  $regExSpriteLayout  = '/sprite-layout:\s*(.*);/i',
  $refExSpriteMargin  = '/sprite-margin:\s*(.*);/i',
  $refExSpriteBackground  = '/sprite-background:\s*#*(.*);/i',
  $refExSpriteOpt = '/sprite-optimize:\s*(.*);/i',
  // force 8bit option:
  $refExSpriteForce8Bit = '/sprite-force8bit:\s*(.*);/i',
  $refExSpriteOptimsation = '/sprite-colorcount:\s*(.*);/i',
  // dataURL Option:
  $refExSpriteDataURL = '/sprite-dataurl:\s*(.*);/i',
  // containing an array with string global sprite definitions
  $_spriteDefs = array(),
  $sprites = array(),
  // the file prefix for the generated css file
  $_fileprefix = '-sprite.css',
  $_filestrippedprefix = '-sprite-min.css',
  // path where tmp files will be stored in...
  $_tmpPath = '',
  // the working dir = tmpdir + hostname
  $currentDir = '',
  $_maxColors = 0,
  $image_types = array(
    IMAGETYPE_GIF=>'gif',   //1=GIF
    IMAGETYPE_JPEG=>'jpg',    //2=JPG
    IMAGETYPE_PNG=>'png',   //3=PNG
    IMAGETYPE_SWF=>'swf',   //4=SWF
    IMAGETYPE_PSD=>'psd',   //5=PSD
    IMAGETYPE_BMP=>'bmp',   //6=BMP
    IMAGETYPE_TIFF_II=>'tiff',  //7=TIFF(intelbyteorder)
    IMAGETYPE_TIFF_MM=>'tiff',  //8=TIFF(motorolabyteorder)
    IMAGETYPE_JPC=>'jpc',   //9=JPC
    IMAGETYPE_JP2=>'jp2',   //10=JP2
    IMAGETYPE_JPX=>'jpf',   //11=JPXYes!jpfextensioniscorrectforJPXimagetype
    IMAGETYPE_JB2=>'jb2',   //12=JB2
    IMAGETYPE_SWC=>'swc',   //13=SWC
    IMAGETYPE_IFF=>'aiff',    //14=IFF
    IMAGETYPE_WBMP=>'wbmp',   //15=WBMP
    IMAGETYPE_XBM=>'xbm'    //16=XBM
    );

  function __construct($_filename='') {

    if ($this->verbose)
    echo "\nsmartsprite Version: $this->version\nAuthor: Alexander Kaupp 2008\nFor more information visit: http://www.tanila.de/smartsprite/\n\n";

    $this->chkGDVersion();
    $this->setFilename($_filename);

    $this->collectImageInfos();
    $this->sortImagesByHeight();
    $this->createSpriteImages();
    
    $this->writeParsedFile();
    //$this->replaceBGIMGStrings();
    if ($this->verbose)
    echo "\nsmartsprite creation successful\n\nHave a nice day :)\n\n";


//print_r( $this->sprites );

  } // tsmartsprite

function setFilename($_filename,$tmppath=''){

  if (empty($_filename) ) die ("Error: No css-file specified!\n\n");
  if (!file_exists($_filename)) die("Error: File: $_filename not found!\n\n");
  $this->_filename = $_filename;
//  $this->_outfile = rtrim($_filename,'.css').$this->_fileprefix;
  $this->_outfile = basename($_filename,".css").$this->_fileprefix;
  $this->_outfilestripped = rtrim($this->_filename,'.css').$this->_filestrippedprefix;
  $this->readFileContent();
} // setFilename

function chkGDVersion(){
  $gdInfo = @gd_info();
  if ($gdInfo) {
    $gdVersion = $gdInfo['GD Version'];
    if ($this->verbose)
    echo "Using: GD_lib $gdVersion \n\n";
  } else die ( "ERROR: no GD Library found.\n" );
}

function getFilename(){
  return $this->_filename;
}

function readFileContent(){
  $_fn = $this->getFilename();
  if (is_readable($_fn)) {
    if($this->verbose) echo "Reading File: $_fn ... \n";
  } else die("ERROR: Can not read File $_fn please check file permissions!");

  // setting original file contetnt
  $this->_fileCntOrig = file_get_contents  ( $_fn );
  // copy original file parsed file content
  $this->_fileCntParsed = $this->_fileCntOrig;

  if ( empty($this->_fileCntOrig) ) die("The css-file: .$_fn has no content! \n\n");
  $this->parseSpriteDefs();
}

// finds the sprite definition:
// Example:
// /** sprite: mysprite;
//   sprite-image: url('../img/sprite.gif');
//   sprite-margin: 20;
//   sprite-layout: horizontal */
function parseSpriteDefs(){
  if ($this->verbose)
  echo "\nParsing file: $this->_filename \n\n";
  $this->_spriteDefs = array();
  $_matches = '';
  $_starttag = '\/\*\*\s+';
  $_endtag = '\*\/';
  $_spritetag = '\s+.*;?\s+.*\s+';
  $_regExSpriteDef = '/'.$_starttag.'sprite\s*:.*;'.$_spritetag.$_spritetag.$_spritetag.$_endtag.'/i';


  $_cnt = $this->_fileCntOrig;
  preg_match_all($_regExSpriteDef,$_cnt,$_matches);

  $_i=0;
  $_matches = $_matches[0];
  foreach($_matches as $match){
    $this->_spriteDefs[$_i]= $match;
    if ($this->verbose)
    echo "Sprite definition found:\n\n$match \n";
    $_i++;
  }

  if (!$this->_spriteDefs) die("ERROR: No sprite Definition found in $this->_filename or syntax error! \n Nothing to do for me!\n\n");
  $this->parseSpriteProperties();
}
function bool2String($x) {
  return (is_bool($x) ? ($x ? "true":"false"):$x);
}

function parseSpriteProperties(){
  if ($this->verbose)
  echo "\nParsing Sprite Image References:\n";

  foreach($this->_spriteDefs as $spritedef) {
    $_spritename = $this->parseString($this->regExSpriteName ,$spritedef);

    if (!empty($_spritename))
      $url= trim($this->parseString($this->regExSpriteUrl ,$spritedef),'\'\""');
      $this->sprites[$_spritename]['url']   = $url;
      $this->sprites[$_spritename]['filename'] = $this->sprites[$_spritename]['url'];
      $this->sprites[$_spritename]['imagetype'] = $this->getFileExtToImgType($this->sprites[$_spritename]['filename']);
      $this->sprites[$_spritename]['layout']  = $this->parseString($this->regExSpriteLayout ,$spritedef);
      $this->sprites[$_spritename]['margin'] = $this->parseString($this->refExSpriteMargin ,$spritedef);
      $_selectors = $this->sprites[$_spritename]['cssselectors'] = $_selectors = $this->getCssSelectorsOfSpriteRef($_spritename);
      $this->sprites[$_spritename]['background'] = $this->parseString($this->refExSpriteBackground ,$spritedef);
    

      // set to transparent or white if empty
      if (empty( $this->sprites[$_spritename]['background'] )) $this->sprites[$_spritename]['background'] = 'ffffff7f';
      
      // colorcount not used atm
       $this->sprites[$_spritename]['colorcount'] = $this->parseString($this->refExSpriteOptimsation ,$spritedef);
       if (empty( $this->sprites[$_spritename]['colorcount'] )) $this->sprites[$_spritename]['colorcount'] = '0'; // 0 means no color optimisation/reduction (todo)


  // force to 8Bit output?
  $_s8Bit = $this->parseString($this->refExSpriteForce8Bit ,$spritedef);
  $this->sprites[$_spritename]['force8bit'] = ($_s8Bit == 'false') ? false : true;
  // dataURL
  $_dataURL = $this->parseString($this->refExSpriteDataURL ,$spritedef);
  $_DATAURL= $this->sprites[$_spritename]['dataurl'] = ($_dataURL == 'false') ? false : true;
  
  // optimize Image:
  $_imgOPT = $this->parseString($this->refExSpriteOpt ,$spritedef);
  $_IMGOPT = $this->sprites[$_spritename]['optimize'] = ($_imgOPT == 'true' ) ? true: false; 
  
    $this->sprites[$_spritename]['width'] = 0;
    $this->sprites[$_spritename]['height'] = 0;
    $M = intval( $this->sprites[$_spritename]['margin'] );
    $this->sprites[$_spritename]['margin'] = $M;
    $URL = $this->sprites[$_spritename]['url'];
    $FN = $this->sprites[$_spritename]['filename'];
    $LO = $this->sprites[$_spritename]['layout'];
    $_BG = $this->sprites[$_spritename]['background'];
    //refExSpriteDataURL
    $_colorMode = $this->bool2String($this->sprites[$_spritename]['force8bit']);
    $_colorCount = $this->sprites[$_spritename]['colorcount'];
    
    $_sdataURL = $this->bool2String($_DATAURL);
    $_sIMGOPT = $this->bool2String($_IMGOPT);
    
    if ($this->verbose) {
      echo "\nSprite Informations found for: $_spritename:";
      echo "\nUrl:\t\t$URL\n";
      echo "Filename:\t$FN\n";
      echo "Layout:\t\t$LO\n";
      echo "Margin:\t\t$M\n";
      echo "Selectors:\t$_selectors\n";
      echo "Background:\t$_BG\n";
      echo "8-BitMode:\t$_colorMode\n";
      // echo "ColorCount:\t$_colorCount\n";
      echo "OptimizeImage:\t$_sIMGOPT\n";
      echo "DataURL:\t$_sdataURL\n";
      echo "\n\n";
    }
    $this->sprites[$_spritename]['images'] = $this->collectSpriteImgRefs($_spritename,$this->_fileCntOrig);
  }
}

function parseString($_regExe,$_str) {
  $_matches = '';
  preg_match($_regExe,$_str,$_matches);
  return (isset($_matches[1])) ? $_matches[1] : '';
}

function collectSpriteImgRefs($_ssRefName,$_str){
  if ($this->verbose)
  echo "\nParsing Image references for: $_ssRefName: \n\n";
  $_result = array();

  $_matches = '';
  $_starttag = '\/\*\*\s+';
  $_endtag = '\s*\*\/';

  $_regEx = '/background[-image]*:\s*url\((.*)\)(\s*.*);\s*'.$_starttag.'sprite-ref:\s*'.$_ssRefName.';* '.$_endtag.'/i';
  preg_match_all($_regEx,$_str,$_matches);
  $_replace_strs = $_matches[0];
  $_file_locs = $_matches[1];
  $_suffixes =$_matches[2];


  //$_selectors = $this->getCssSelectorsOfSpriteRef($_ssRefName);
  //die( $_selectors."\n" );

  $i=0;
  foreach ($_file_locs as $_file_loc => $value) {
    $value = trim($value,'\'\""');
    $_imagename = $value;
    $_result[$_imagename]['file_location'] = $value;

    if ($this->verbose) echo "Image definition found: $value \n";
    
    $_result[$_imagename]['replace_string'] = $_replace_strs[$i];
    $_result[$_imagename]['urlsuffix'] = $_suffixes[$i];
    $_result[$_imagename]['repeat'] = $this->getBGImgRepeat( $_suffixes[$i] );
    $_result[$_imagename]['align'] = $this->getBGImgAlign( $_suffixes[$i] );

    $i++;
  }
  if ($this->verbose)
  echo "\n";
  return $_result;
}

function getCssSelectorsOfSpriteRef($_ssRefName) {
  $_matches = '';

  $_str = $this->_fileCntOrig;

  $_starttag = '\/\*\*\s+';
  $_endtag = '\s*\*\/';
  $_regCSSselector = '([0-9a-z]((.|,|:)[0-9a-z]){0,10})';
//  $_regExSelector = '/(.*\s*){\s*.*\s*'.$_starttag.'sprite-ref:\s*'.$_ssRefName.'; '.$_endtag.'/i';
  $_regExSelector = '/(.*\s*){[^}]*?'.$_starttag.'sprite-ref:\s*'.$_ssRefName.'; '.$_endtag.'/i';
  
//  \\s*{[^}]*?}
  
  preg_match_all($_regExSelector,$_str,$_matches);
  $_selector = $_matches[1];

  return implode(', ',$_selector);
}


function getBGImgRepeat($str){
  $_result = 'no-repeat';
  $str = str_replace(';','',$str);
  $str = strtolower($str);
  $arr= explode(' ',$str);
  if (in_array('repeat-x',$arr)) $_result = 'repeat-x';
  if (in_array('repeat-y',$arr)) $_result = 'repeat-y';
  if (in_array('repeat',$arr)) $_result = 'repeat'; 
  return $_result;
}
function getBGImgAlign($str){
  $_result = '';
  $str = str_replace(';','',$str);
  $str = strtolower($str);
  $arr= explode(' ',$str);
  if (in_array('left',$arr)) $_result = 'left';
  if (in_array('right',$arr)) $_result = 'right';

  // new
  if (in_array('center',$arr)) $_result = 'center';
  //
  return $_result;
}

function collectImageInfos(){
  foreach($this->sprites as $spritekey => $spriteval ) {
    $imgdefs = &$spriteval['images'];
    foreach( $imgdefs as $spriteimagekey => $spriteimagevalue ){
      if ($this->verbose)
      echo "Fetching Image-File Properties for file : $spriteimagekey\t";
      $fullfilename = dirname($this->_filename).'/'.$spriteimagekey;
//      die($fullfilename);
      if (file_exists($fullfilename) ) {
        list($width, $height, $type) = getimagesize($fullfilename );
        $this->sprites[$spritekey]['images'][$spriteimagekey]['width'] = $width;
        $this->sprites[$spritekey]['images'][$spriteimagekey]['height'] = $height;
        $this->sprites[$spritekey]['images'][$spriteimagekey]['type'] = $type;
        if ($this->verbose)
        echo "width:$width;\theight:\t$height;\ttype:$type;\n";
      } else  die( "\nERROR: File not Found: $fullfilename\n");
    } // img loop
  } // sprite loop
}

function replaceBGIMGStrings() {

  if ($this->verbose)
  echo "Replacing Image References...\n";
  
  foreach($this->sprites as $sprite ) {
    $sprite_bgurl = 'background: url(\''.$sprite['filename'].'\')';
    $_imagelocations = $sprite['images'];
     if ($_imagelocations) {
    foreach( $_imagelocations as $_imglocation => $_imglocationvalue) {
      $top  = $_imglocationvalue['spritepos_top'];
      $left = $_imglocationvalue['spritepos_left'];
      $suffix = $_imglocationvalue['urlsuffix'].';';
      $strLeft = ($left > 0 ) ? '-'.$left.'px' : '0';
      $strTop = ($top > 0 ) ? '-'.$top.'px' : '0';
      switch ($_imglocationvalue['align']) {
        
        case 'left':  
            $_imglocationvalue['spritepos_left']=0;
            $_bgpostr = 'background-position: '.$strLeft.' -'.$strTop.';';
            break;
        case 'right':
            $_bgpostr='background-position: right '.$strTop.';';
            break;
        case 'center': 
            $_bgpostr='background-position: center '.$strTop.';';
            break;
        default:
            $_bgpostr = 'background-position: '.$strLeft.' '.$strTop.';';
            break;
      }
 
       if ($_imglocationvalue['repeat'] && $_imglocationvalue['repeat'] != 'no-repeat') {
         $_bgpostr .= 'background-repeat: '.$_imglocationvalue['repeat'].';';
      }
      $_repl = $_bgpostr;
      $this->_fileCntParsed = str_replace($_imglocationvalue['replace_string'], $_repl, $this->_fileCntParsed);
    } // image loop
    } // if images
  } // Sprite-Loop
}

function writeParsedFile() {
    if ($this->verbose)
    echo "Writing smartsprite css file...\n";
    $filename =$this->_outfile;
    $filename = dirname($this->_filename).'/'.$filename;
    $handle = @fopen($filename, "w");
    if (!$handle) die("ERROR: Can not write to: $filename");
    fwrite ($handle, $this->_fileCntParsed);
    fclose($handle);
}

function sortImagesByHeight() {
  if ($this->verbose)
  echo "\nSorting Images... \n";
  foreach ($this->sprites as $spritekey => $spritevalue) {
    $_sum_width  = 0;
    $_sum_height = 0;
    $_max_width = 0;
    $_max_height = 0;
    $_widthtarr = array();
    $_heighttarr = array();
    $_layout = $spritevalue['layout'];
    $_imagelocations = $spritevalue['images'];
    $_margin = $this->sprites[$spritekey]['margin'];
    if ($_imagelocations) {
      foreach ($_imagelocations as $image => $imagevalue) {
        $_widthtarr[$image] = $imagevalue['width'];
        $_heighttarr[$image] = $imagevalue['height'];
        $_sum_width  += $imagevalue['width']+$_margin ;
        $_sum_height += $imagevalue['height']+$_margin ;
      } // sprite images loop
        $_sum_width  += $_margin;
        $_sum_height +=$_margin;
        $_max_width = max($_widthtarr);
        $_max_height = max($_heighttarr);
        $_min_width = min($_widthtarr);
        $_min_height = min($_heighttarr);
        switch ($_layout) {
          case 'horizontal':
            array_multisort($_heighttarr, SORT_NUMERIC,SORT_DESC, $this->sprites[$spritekey]['images']);
            $this->sprites[$spritekey]['height'] = $_max_height;
            $this->sprites[$spritekey]['width']  = $_sum_width;
            break;
          case 'vertical':
            array_multisort($_widthtarr, SORT_NUMERIC,SORT_DESC, $this->sprites[$spritekey]['images']);
            $this->sprites[$spritekey]['height'] = $_sum_height;
            $this->sprites[$spritekey]['width']  = $_max_width;
            break;
        }
    if ($this->verbose)
    echo "Calculating Sprite positions...\n";
    $_sum_width  = $_margin;
    $_sum_height = $_margin;
    switch ($_layout) {
      case 'horizontal':
        $_sum_height = 0;
        break;
      case 'vertical':
        $_sum_width = 0;
        break;
    }
    foreach ($this->sprites[$spritekey]['images'] as $imagekey => $imagevalue) {

      switch ($this->sprites[$spritekey]['images'][$imagekey]['align']) {
        case 'left': $this->sprites[$spritekey]['images'][$imagekey]['spritepos_left']=0;
          break;
        case 'right': $this->sprites[$spritekey]['images'][$imagekey]['spritepos_left']=$_max_width-$this->sprites[$spritekey]['images'][$imagekey]['width'];//$w-3; //-$imgleft = $imagevalue['width'];
          break;
        case 'center': $this->sprites[$spritekey]['images'][$imagekey]['spritepos_left']=( $_max_width-$this->sprites[$spritekey]['images'][$imagekey]['width'] ) / 2;//$w-3; //-$imgleft = $imagevalue['width'];
          break;
      }

      switch ($_layout) {
        case 'horizontal':
          $this->sprites[$spritekey]['images'][$imagekey]['spritepos_top'] = $_sum_height;
          $this->sprites[$spritekey]['images'][$imagekey]['spritepos_left'] = $_sum_width;
          $_sum_width += $this->sprites[$spritekey]['images'][$imagekey]['width']+$_margin;
          break;
        case 'vertical':
          $this->sprites[$spritekey]['images'][$imagekey]['spritepos_top'] = $_sum_height;
          $this->sprites[$spritekey]['images'][$imagekey]['spritepos_left'] = $_sum_width;
          //$_sum_height += $_imagelocations[$imagekey]['height']+$_margin;
          $_sum_height += $this->sprites[$spritekey]['images'][$imagekey]['height']+$_margin;
          break;
        }
      } // sprite images loop
    } // if no images
  } // Sprite loop
  //$this->replaceBGIMGStrings();
}

function createSpriteImages() {

  if ($this->verbose)
  echo "Creating smartsprite file...\n";

  foreach ($this->sprites as $spritekey => $spritevalue) {
    $_imagelocations = &$this->sprites[$spritekey]['images'];
    $filename = $this->sprites[$spritekey]['filename'];
    
  $OPTIMIZE = $this->sprites[$spritekey]['optimize'];
  $DATAURL = $this->sprites[$spritekey]['dataurl'];
  
    echo "creating css-sprite-file: $filename \n";
    $backgroundHEX = $this->sprites[$spritekey]['background'];

    if ($_imagelocations) {
      $w = $this->sprites[$spritekey]['width'];
      $h = $this->sprites[$spritekey]['height'];

  $fileEXT = substr($this->sprites[$spritekey]['filename'], -3);
  
  // $this->_trueColor &&  
  if ($this->sprites[$spritekey]['force8bit'] == false ) {
    echo "using truecolor mode\n";
    if ( $fileEXT == 'png')  $image = imagecreatetruecolor($w, $h);
    if ( $fileEXT == 'gif')  $image = imagecreatetruecolor($w, $h);
    if ( $fileEXT == 'jpg')  $image =  imagecreatetruecolor($w, $h);
  } else {
    echo "using 8bit mode\n";
    if ( $fileEXT == 'png')  $image = imagecreate($w, $h);
    if ( $fileEXT == 'gif')  $image = imagecreate($w, $h);
    if ( $fileEXT == 'jpg')  $image =  imagecreate($w, $h);
  }

  // spriteBG color to RGB:
  $_colArr = sscanf($backgroundHEX, '%2x%2x%2x%2x');
  $BG_R = $_colArr[0];
  $BG_G = $_colArr[1];
  $BG_B = $_colArr[2];
  $BG_A = $_colArr[3];

    if ($fileEXT !='jpg') {
      if (!empty( $BG_A) ) {
        imagealphablending($image,false);
        imagesavealpha($image,true);
          $transparent = imagecolorallocatealpha($image,$BG_R, $BG_G, $BG_B, $BG_A);
        imagecolortransparent($image,$transparent);
      } else {
        $transparent = imagecolorallocate($image,$BG_R, $BG_G, $BG_B);
      }
    
      imagefilledrectangle($image, 0, 0, $w, $h, $transparent);
    } else {
      //$transparent = imagecolorallocate($image,255, 255, 255);  // white
      $transparent = imagecolorallocate($image,$BG_R, $BG_G, $BG_B);
      imagefilledrectangle($image, 0, 0, $w, $h, $transparent);
    }

      foreach ($_imagelocations as $_image => $imagevalue) {
        $subimg = $this->loadImageFromFile($imagevalue);

        switch ($imagevalue['align']) {
          case 'left': $imagevalue['spritepos_left']=0;
            break;
          case 'right': $imagevalue['spritepos_left']=$w-$imagevalue['width'];//$w-3; //-$imgleft = $imagevalue['width'];
            break;
          case 'center': $imagevalue['spritepos_left']=( $w-$imagevalue['width'] ) / 2;//$w-3; //-$imgleft = $imagevalue['width'];
            break;
        }

        // stretching to full width:
        switch ($imagevalue['repeat']) {
          case 'repeat-x':
            // changed 10/06/2010 by tanila
            // image resample was a stretch
            // added repeat to support iregular repeat-x
            $nWidth = 0;
            while($nWidth <= $w) {
              imagecopyresampled($image, $subimg,
              $imagevalue['spritepos_left'] + $nWidth,
              $imagevalue['spritepos_top'],
              0,
              0,
              $imagevalue['width'],
              $imagevalue['height'],
              $imagevalue['width'],
              $imagevalue['height']);
              $nWidth += $imagevalue['width'];
            } 
            break;
          case 'repeat-y':
            imagecopyresampled($image,$subimg,$imagevalue['spritepos_left'],$imagevalue['spritepos_top'],0,0,$imagevalue['width'],$h,$imagevalue['width'],$imagevalue['height']);     
            break;
          default:
              imagecopy($image,$subimg,$imagevalue['spritepos_left'],$imagevalue['spritepos_top'],0,0,$imagevalue['width'],$imagevalue['height']);
        }
      } // image loop
      $this->safeImageToFile($image, $this->sprites[$spritekey]['imagetype'], $this->sprites[$spritekey]['filename'], $spritekey, $OPTIMIZE, $DATAURL ); 
    } // if images exists
  } // Sprite loop
}

function getFileExtToImgType($_fileName) {
  $_fileName = strtolower($_fileName);
  $_fileExt = end(explode('.', $_fileName));
  $i=1;
  foreach ( $this->image_types as $imagetype) {
    if ($imagetype == $_fileExt) return $i;
    $i++;
  }
}

function loadImageFromFile($imageInfo) {
  $_result = 0;
  $filelocation = $imageInfo['file_location'];
  $filelocation = dirname($this->_filename).'/'.$filelocation;

  switch ($imageInfo['type']) {
    case 1 : $_result = @imagecreatefromgif($filelocation);
      break;
    case 2 : $_result = @imagecreatefromjpeg($filelocation);
      break;
    case 3 : $_result = @imagecreatefrompng($filelocation);
      break;
    case 4 : $_result = @imagecreatefromswf($filelocation);
      break;
    case 6 : $_result = @imagecreatefromwbmp($filelocation);
      break;
    case 15 : $_result = @imagecreatefromxbm($filelocation);
      break;
  }

  $_colorCount = ImageColorsTotal($_result);
  echo 'testing imagefile: '.$filelocation.' colors: '.$_colorCount."\n";

  if (  $this->_maxColors < $_colorCount ) $this->_maxColors = $_colorCount;
  if ($_colorCount  == 0) $this->_trueColor = true;
  if (! $_result) die("ERROR: Can not open file: $filelocation\n");
  return $_result;
}

function safeImageToFile($imgres, $imgtype, $filename, $spritekey, $optimize, $dataurl) {
  $filename = dirname($this->_filename).'/'.$filename;
  //if ($this->verbose)

  echo "Writing smartsprite file: $filename  colors: $this->_maxColors  ...\n";

  $_result = 0;
  switch ($imgtype) {
    case 1 :
      $_result = @imagegif($imgres, $filename);
      $_mime = 'image/gif';
      break;
    case 2 :
      $_result = @imagejpeg($imgres, $filename);
      $_mime = 'image/jpeg';
      break;
    case 3 :
       $_result = @imagepng($imgres, $filename);
       $_mime = 'image/png';
      break;
    default:
      $_result = @imagegif($imgres, $filename);
      $_mime = 'image/gif';
  }
  ImageDestroy($imgres);

  if (!$_result) die("ERROR: Can not write smartsprite file to: $filename\n");

  // call tanilaimgmin

if ($optimize == true ) {
  
  // todo: file existance chk
  require_once(dirname(dirname(__FILE__)).'/tanilaimgmin/class.tanilaimgmin.php');
    
  $tanilaimgmin = &new tanilaimgmin($filename);
  $_optIMG = $tanilaimgmin->getResultFilename();

    switch ($this->getFileExtToImgType($_optIMG)) {
      case 1 :
      $_mime = 'image/gif';
        break;
      case 2 :
        $_mime = 'image/jpeg';
        break;
      case 3 :
         $_mime = 'image/png';
        break;
      default:
        $_mime = 'image/gif';
    }

} else { // optimize
  $_optIMG = $filename; 
}
    
    $_spriteDef = $this->_spriteDefs[0];
    array_splice($this->_spriteDefs,0,1);
    
    $_replacement = ($dataurl) 
      ? $this->getImageDataURL($_optIMG, $_mime, $spritekey) 
      : $this->getImageJointBG($_optIMG, $spritekey);//'';
    
    $this->_fileCntParsed = str_replace($_spriteDef, $_replacement, $this->_fileCntParsed);
    $this->replaceBGIMGStrings();
}
// creates a joint css-rule for all matching css-rules with 
// dataURL_background-sprite and a hack of IE:
function getImageDataURL($file, $_mime, $spritekey) {
  // todo spriterefname = spriteimg name
  // only set file extension in sprite def
  //$_fileExt = end(explode(".", basename($file) ));
  //$_fileNoExt = str_replace('.'.$_fileExt,'',basename( $file ) );
  $_data = $this->getBase64EncodedImgString($file);
  $_selectors = $this->sprites[$spritekey]['cssselectors'];
  $_IEHack = "\n".'*background: url("'.basename( $file ).'") no-repeat;'."\n";
  return $_selectors.'{background: url("data:'.$_mime.';base64,'.$_data .'") no-repeat; '.$_IEHack.'}' ;
}
// creates a joint css-rule for all matching css-rules with the same
// background-sprite
function getImageJointBG($file, $spritekey) {
  //$_fileExt = end(explode(".", basename($file) ));
  //$_fileNoExt = str_replace('.'.$_fileExt,'', basename($file) );
  $_selectors = $this->sprites[$spritekey]['cssselectors'];
  return $_selectors.' {background: url("'.$file.'") no-repeat;}' ;
}

function getBase64EncodedImgString($file) {
  $contents = file_get_contents($file);
  return base64_encode($contents);
}

} // class tsmartsprite

