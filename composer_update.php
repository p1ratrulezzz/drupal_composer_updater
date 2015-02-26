<?php
/**
 * Created by PhpStorm.
 * User: p1ratrulezzz
 * Date: 26.02.15
 * Time: 18:38
 */

if (php_sapi_name() != 'cli') {
  die('Please use this only in CLI mode!');
}

function list_dir($directory, &$raw = NULL) {
  $fd = opendir($directory);
  if (!$fd) {
    return FALSE;
  }

  $list = array(
    'raw' => array(),
    'list' => array(),
  );

  if ($raw) {
    $list['raw'] = &$raw;
  }

  while ($file = readdir($fd)) {
    if ($file != '.' && $file != '..') {
      $fullpath = rtrim($directory, '/\\') . '/' . $file;
      $record = pathinfo($fullpath) + array(
          'uri' => $fullpath,
          'filename' => $file,
          'parent' => $directory,
        );

      if (is_dir($fullpath)) {
        $record['children'] = list_dir($fullpath, $list['raw'])->list;
      }

      $list['list'][$fullpath] =
      $list['raw'][$fullpath] = $record;
    }
  }


  return (object) $list;
}

$cwd = __DIR__;
$start_dir = $cwd;
$composer_bin = "{$start_dir}/composer.phar";


$list = list_dir($start_dir);


