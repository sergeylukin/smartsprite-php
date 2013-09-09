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
  // containing arrays of SmartSprite syntax references per sprite
  $references = array(),
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
    ),

  // Default configuration variables
  $config = array(
    'absolutePathRoot'  => null,
    'relativePathRoot'  => null,
    'spritesBasePath'   => null
  ),

  // The main meat for our library - CSS that contains references to images 
  // with Smartsprite commenting syntax
  $input_css = '',

  // Generated CSS with references to Image sprites
  $output_css = '',

  // Array of generated sprites that contain:
  //   - Filename (e.g. mysprite.png)
  //   - Image contents (in either PNG, JPEG, etc. format) that is ready to be 
  //     written to a file
  $all_sprites = array();

  // Temporary variable that holds a name of sprite that is being currently 
  // populated with all references to it found in original CSS
  private $_tmp_currently_parsed_sprite = '';

  function __construct($css = '') {

    $this->input_css = $this->output_css = $css;

  }

  // Getter and Setter
  // Used to configure
  function __get($key) {
    if( isset($this->config[$key]) ) return $this->config[$key];
    return null;
  }
  function __set($key, $value) {
    return $this->config[$key] = $value;
  }

  function crunch() {
    if ($this->verbose)
    echo "\nsmartsprite Version: $this->version\nAuthor: Alexander Kaupp 2008\nFor more information visit: http://www.tanila.de/smartsprite/\n\n";

    $this->chkGDVersion();

    if ( empty($this->input_css) ) die("The css has no content! \n\n");
    if( !$this->parseSpriteDefs() ) {
      return false;
    }

    $this->collectImageInfos();
    $this->sortImagesByHeight();
    $this->createSpriteImages();
    
    //$this->replaceBGIMGStrings();
    if ($this->verbose)
    echo "\nsmartsprite creation successful\n\nHave a nice day :)\n\n";

    return true;
  }

  function getCSS() {
    return $this->output_css;
  }

  function getSprites() {
    return $this->all_sprites;
  }


function chkGDVersion(){
  $gdInfo = @gd_info();
  if ($gdInfo) {
    $gdVersion = $gdInfo['GD Version'];
    if ($this->verbose)
    echo "Using: GD_lib $gdVersion \n\n";
  } else die ( "ERROR: no GD Library found.\n" );
}

// finds the sprite definition:
// Example:
// /** sprite: mysprite;
//   sprite-image: url('../img/sprite.gif');
//   sprite-margin: 20;
//   sprite-layout: horizontal */
function parseSpriteDefs(){
  if ($this->verbose)
  echo "\nParsing css\n\n";
  $this->_spriteDefs = array();
  $_matches = '';
  $_starttag = '\/\*\*\s+';
  $_endtag = '\*\/';
  $_spritetag = '\s+.*;?\s+.*\s+';
  $_regExSpriteDef = '/'.$_starttag.'sprite\s*:.*;'.$_spritetag.$_spritetag.$_spritetag.$_endtag.'/i';


  $_cnt = $this->input_css;
  preg_match_all($_regExSpriteDef,$_cnt,$_matches);

  $_i=0;
  $_matches = $_matches[0];
  foreach($_matches as $match){
    $this->_spriteDefs[$_i]= $match;
    if ($this->verbose)
    echo "Sprite definition found:\n\n$match \n";
    $_i++;
  }

  if (!$this->_spriteDefs) {
    return false;
  }

  $this->parseSpriteProperties();
  return true;
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
      $spritename = trim($this->parseString($this->regExSpriteUrl ,$spritedef),'\'\""');
      $this->sprites[$_spritename]['url']   = $this->spritesBasePath . DIRECTORY_SEPARATOR . $spritename;
      $this->sprites[$_spritename]['filename'] = $spritename;
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
    $this->_tmp_currently_parsed_sprite = $_spritename;
    // empty the references array for this sprite, just in case
    $this->references[$_spritename] = array();
    $this->collectSpriteImgRefs($_spritename,$this->input_css);
    $this->sprites[$_spritename]['images'] = $this->references[$_spritename];
  }
}

function parseString($_regExe,$_str) {
  $_matches = '';
  preg_match($_regExe,$_str,$_matches);
  return (isset($_matches[1])) ? $_matches[1] : '';
}

function collectSpriteImgRefs($_spriteName,$_str){
  if ($this->verbose)
  echo "\nParsing Image references for: $_spriteName: \n\n";

  $_regEx = '/
    background[-image]*:\s*   # look for `background` property
    url\((.*)\)     # grab the image URI in Group #1
    (\s*.*);\s*  # grab all the rest of styles for that background in Group #2
    \/\*\*\s+       # find SmartSprite syntax starting tag
    sprite-ref:\s*'.$_spriteName.';* # find reference to a sprite name, like:
                                    # `sprite-ref: sprite_name;`
    ([^\/]*)        # grab any additional Smartsprite syntax in Group #3
    \s*\*\/         # find SmartSprite syntax ending tag
    |               # ......OR......
    \/\*\*\s+       # find SmartSprite syntax starting tag
    sprite-ref:\s*'.$_spriteName.';* # find reference to a sprite name, like:
                                    # `sprite-ref: sprite_name;`
    ([^\/]*)        # grab any additional Smartsprite syntax in Group #4
    \s*\*\/\s*      # find SmartSprite syntax ending tag
    background[-image]*:\s*   # look for `background` property
    url\((.*)\)     # grab the image URI in Group #5
    (\s*.*);        # grab all the rest of styles for that background in Group #6
    /ix';

  preg_replace_callback($_regEx, array($this, 'parseSpriteReference'), $_str);
  return true;
}

function parseSpriteReference($matches) {
  $uri = !empty($matches[1]) ? $matches[1] : @$matches[5];
  $background_properties = !empty($matches[2]) ? $matches[2] : @$matches[6];
  $smartsprite_syntax = !empty($matches[3]) ? $matches[3] : @$matches[4];
  $whole_match = $matches[0];
  $_spritename = $this->_tmp_currently_parsed_sprite;
  $_imagename = trim($uri,'\'\""');

  if ($this->verbose) echo "Image definition found: $_imagename \n";
  
  $data = array(
    'file_location' => $_imagename,
    'replace_string' => $whole_match,
    'urlsuffix' => $background_properties,
    'repeat' => $this->getBGImgRepeat( $background_properties ),
    'align' => $this->getBGImgAlign( $background_properties ),
    'position'  => $this->getSpriteImgPosition( $smartsprite_syntax )
  );

  // Update instance array
  if( !is_array($this->references[$_spritename])) $this->references[$_spritename] = array();
  $this->references[$_spritename][$_imagename] = $data;

  if ($this->verbose)
  echo "\n";

  // just return empty string for now..
  // however this may be used with greater profit to replace references in 
  // CSS.. mention for TODO
  return '';
}

function getCssSelectorsOfSpriteRef($_spritename) {
  $_matches = '';

  $_str = $this->input_css;

  $_regExSelector = "/
    (.*\s*)                       # grab class name
    {[^}]*                        # match anything excpet curly brace
    \/\*\*\s+                     # match Smartsprite syntax opening tag
    sprite-ref:\s* $_spritename;  # match our spritename
    [^\/]*                        # allow anything further except for closing tag
    \s*\*\/                       # match closing tag
    /ix";
  
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

function getSpriteImgPosition($str){
  $_result = array(
    'horizontal'  => 'auto',
    'vertical'    => 'auto'
  );

  $_regex = "/
    \s*sprite-pos:\s* # match `sprite-pos` property
    ([^;]*)\s*;?      # grab everything untill semicolon or the end of string
    /x";
  preg_match($_regex, $str, $matches);

  if( !empty($matches) ) {
    // Extract the values and do our best to detect horizontal and vertical
    // values
    $pos = explode(' ', trim($matches[1]));
    // First value is always Horizontal
    $_result['horizontal'] = $pos[0];
    // If there are more than 1 values than Vertical is also specified - simple
    if( count($pos) > 1 ) {
      $_result['vertical'] = $pos[1];
    }
  }

  return $_result;
}

function collectImageInfos(){
  foreach($this->sprites as $spritekey => $spriteval ) {
    $imgdefs = &$spriteval['images'];
    foreach( $imgdefs as $spriteimagekey => $spriteimagevalue ){
      if ($this->verbose)
      echo "Fetching Image-File Properties for file : $spriteimagekey\t";
      if( substr($spriteimagekey, 0, 3) === '../' ) {
        $path_prefix = $this->relativePathRoot;
      } else {
        $path_prefix = $this->absolutePathRoot;
      }
      $fullfilename = realpath($path_prefix . DIRECTORY_SEPARATOR . $spriteimagekey);
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
    $sprite_bgurl = 'background-image: url(\''.$sprite['filename'].'\')';
    $_imagelocations = $sprite['images'];
     if ($_imagelocations) {
    foreach( $_imagelocations as $_imglocation => $_imglocationvalue) {

      // Load calculated sprite background position
      $left = -$_imglocationvalue['spritepos_left'];
      $top  = -$_imglocationvalue['spritepos_top'];

      // Apply user specified position for sprite background position
      $horizontal_pos = $_imglocationvalue['position']['horizontal'];
      if( $horizontal_pos !== 'auto' ) {
        if( preg_match('/^[+-]/', $horizontal_pos) ) $left = $left + $horizontal_pos;
        else $left = $horizontal_pos;
      }
      $vertical_pos = $_imglocationvalue['position']['vertical'];
      if( $vertical_pos !== 'auto' ) {
        if( preg_match('/^[+-]/', $vertical_pos) ) $top = $top + $vertical_pos;
        else $top = $vertical_pos;
      }

      $suffix = $_imglocationvalue['urlsuffix'].';';
      $strLeft = $left . ( is_numeric($left) && $left !== 0 ? 'px' : '');
      $strTop = $top . ( is_numeric($top) && $top !== 0 ? 'px' : '');
      $_bgpostr = 'background-position: '.$strLeft.' '.$strTop.';';
 
       if ($_imglocationvalue['repeat'] && $_imglocationvalue['repeat'] != 'no-repeat') {
         $_bgpostr .= 'background-repeat: '.$_imglocationvalue['repeat'].';';
      }
      $_repl = $_bgpostr;
      $this->output_css = str_replace($_imglocationvalue['replace_string'], $_repl, $this->output_css);
    } // image loop
    } // if images
  } // Sprite-Loop
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
  
    if ($this->verbose)
    echo "creating css-sprite-file: $filename \n";
    $backgroundHEX = $this->sprites[$spritekey]['background'];

    if ($_imagelocations) {
      $w = $this->sprites[$spritekey]['width'];
      $h = $this->sprites[$spritekey]['height'];

  $fileEXT = substr($this->sprites[$spritekey]['filename'], -3);
  
  // $this->_trueColor &&  
  if ($this->sprites[$spritekey]['force8bit'] == false ) {
    if ($this->verbose)
    echo "using truecolor mode\n";
    if ( $fileEXT == 'png')  $image = imagecreatetruecolor($w, $h);
    if ( $fileEXT == 'gif')  $image = imagecreatetruecolor($w, $h);
    if ( $fileEXT == 'jpg')  $image =  imagecreatetruecolor($w, $h);
  } else {
    if ($this->verbose)
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

      $imgtype = $this->sprites[$spritekey]['imagetype'];
      $filename = $this->sprites[$spritekey]['filename'];
      $filepath = $this->spritesBasePath . DIRECTORY_SEPARATOR . $filename;
      $sprite = $this->returnImage($image, $imgtype);

      // Add sprite to the array of sprites
      array_push($this->all_sprites, array(
        'filename'  => $filename,
        'image'     => $sprite['image']
      ));
      // Replace BG reference in Output CSS
      // $this->safeImageToFile($image, $this->sprites[$spritekey]['imagetype'], $this->sprites[$spritekey]['filename'], $spritekey, $OPTIMIZE, $DATAURL ); 
      $this->updateCSS($sprite['image'], $spritekey, $filepath, $sprite['mime'], $DATAURL);

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
  if( substr($filelocation, 0, 3) === '../' ) {
    $path_prefix = $this->relativePathRoot;
  } else {
    $path_prefix = $this->absolutePathRoot;
  }
  $filelocation = realpath($path_prefix . DIRECTORY_SEPARATOR . $filelocation);

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
  if ($this->verbose)
  echo 'testing imagefile: '.$filelocation.' colors: '.$_colorCount."\n";

  if (  $this->_maxColors < $_colorCount ) $this->_maxColors = $_colorCount;
  if ($_colorCount  == 0) $this->_trueColor = true;
  if (! $_result) die("ERROR: Can not open file: $filelocation\n");
  return $_result;
}

function returnImage($imgres, $imgtype) {
  $_result = 0;
  ob_start();
  switch ($imgtype) {
    case 1 :
      @imagegif($imgres);
      $_mime = 'image/gif';
      break;
    case 2 :
      @imagejpeg($imgres);
      $_mime = 'image/jpeg';
      break;
    case 3 :
      @imagepng($imgres);
      $_mime = 'image/png';
      break;
    default:
      @imagegif($imgres);
      $_mime = 'image/gif';
  }
  $_result = ob_get_contents();
  ob_end_clean();
  ImageDestroy($imgres);

  if (!$_result) die("ERROR: Can not parse sprite image\n");
  return array('image'  => $_result,
               'mime'   => $_mime);

  // if ($optimize == true ) {
  //   
  //   // todo: file existance chk
  //   require_once(dirname(dirname(__FILE__)).'/tanilaimgmin/class.tanilaimgmin.php');
  //     
  //   $tanilaimgmin = new tanilaimgmin($filename);
  //   $_optIMG = $tanilaimgmin->getResultFilename();

  //     switch ($this->getFileExtToImgType($_optIMG)) {
  //       case 1 :
  //       $_mime = 'image/gif';
  //         break;
  //       case 2 :
  //         $_mime = 'image/jpeg';
  //         break;
  //       case 3 :
  //          $_mime = 'image/png';
  //         break;
  //       default:
  //         $_mime = 'image/gif';
  //     }

  // } else { // optimize
  //   $_optIMG = $filename; 
  // }

  // call tanilaimgmin
  }

  function updateCSS($image, $spritekey, $filepath, $mime, $dataurl) {
    
    $_spriteDef = $this->_spriteDefs[0];
    array_splice($this->_spriteDefs,0,1);
    
    $_replacement = ($dataurl) 
      ? $this->getImageDataURL($image, $filepath, $mime, $spritekey) 
      : $this->getImageJointBG($filepath, $spritekey);//'';
    
    $this->output_css = str_replace($_spriteDef, $_replacement, $this->output_css);
    $this->replaceBGIMGStrings();
  }
// creates a joint css-rule for all matching css-rules with 
// dataURL_background-sprite and a hack of IE:
function getImageDataURL($image, $filepath, $_mime, $spritekey) {
  // todo spriterefname = spriteimg name
  // only set file extension in sprite def
  //$_fileExt = end(explode(".", basename($file) ));
  //$_fileNoExt = str_replace('.'.$_fileExt,'',basename( $file ) );
  $_data = base64_encode($image);
  $_selectors = $this->sprites[$spritekey]['cssselectors'];
  $_IEHack = "\n".'*background-image: url("'.$filepath.'");*background-repeat: no-repeat;'."\n";
  return $_selectors.'{background-image: url("data:'.$_mime.';base64,'.$_data .'"); background-repeat: no-repeat; '.$_IEHack.'}' ;
}
// creates a joint css-rule for all matching css-rules with the same
// background-sprite
function getImageJointBG($file, $spritekey) {
  //$_fileExt = end(explode(".", basename($file) ));
  //$_fileNoExt = str_replace('.'.$_fileExt,'', basename($file) );
  $_selectors = $this->sprites[$spritekey]['cssselectors'];
  return $_selectors.' {background-image: url("'.$file.'"); background-repeat: no-repeat;}' ;
}

} // class tsmartsprite

