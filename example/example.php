<?php

define('DS', DIRECTORY_SEPARATOR);
define('__BASE_PATH', realpath(dirname(__FILE__)));
define('__CSS_PATH', __BASE_PATH . DS . 'css');
define('__SPRITES_PATH', __BASE_PATH . DS . 'images' . DS . 'sprites');

define('__LIB_PATH', dirname(__BASE_PATH));
require_once(__LIB_PATH . DS . 'smartsprite.php');

$css = file_get_contents(__CSS_PATH . DS . 'style.css');

$obj = new Smartsprite($css);
$obj->absolutePathRoot = __BASE_PATH;
$obj->relativePathRoot = __CSS_PATH;
// Path prefix for sprites paths in generated CSS
$obj->spritesBasePath = '../images/sprites';

if( $obj->crunch() ) {
  $new_css = $obj->getCSS();
  $sprites = $obj->getSprites();
  echo "Here are the sprites you would want to create somewhere:"; echo "\n\n";
  print_r($sprites); echo "\n\n";

  if( count($sprites) > 0 ) {
    foreach( $sprites as $sprite ) {
      $spriteFileName = $sprite['filename'];
      $spriteImage = $sprite['image'];
      $spriteFilePath = __SPRITES_PATH . DS . $spriteFileName;
      if( !@$spriteFileHandle = fopen($spriteFilePath, 'w') ) {
        continue;
      }
      fwrite($spriteFileHandle, $spriteImage); fclose($spriteFileHandle);
    }
  }


  echo "And here is the generated CSS:"; echo "\n\n";
  echo $new_css;

  // Save css file
  $cssFilePath = __CSS_PATH . DS . 'style-sprite.css';
  if( !@$cssFileHandle = fopen($cssFilePath, 'w') ) {
    continue;
  }
  fwrite($cssFileHandle, $new_css); fclose($cssFileHandle);

} else {
  echo "Hmm, it didn't really work out";
}

