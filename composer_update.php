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

function syscall($command, $stderr = FALSE){
  $result = '';
  $suffix = $stderr ? "2>&1" : "";
  if ($proc = popen("($command){$suffix}","r")){
    while (!feof($proc)) $result .= fgets($proc, 1000);
    pclose($proc);
    return $result;
  }
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

// Parse arguments
$arguments = getopt('h', array(
  'php::',
  'help',
));

$arguments = is_array($arguments) ? $arguments : array();
$arguments += array(
  'php' => 'php',
  'h' => null,
  'help' => null,
);

if ($arguments['help'] !== null || $arguments['h'] !== null) {
  echo "
  Usage is:
    php composer_update.php [options]
  Available options:
    --php specify path to php. Example: --php=/usr/bin/php
    -h, --help to view this help message
  ";
  exit;
}

// Get current dir
$cwd = getcwd();

chdir(__DIR__);

// Shortcut to php binary
$php_bin = $arguments['php'];

// Check php now
$info = syscall("{$php_bin} -v");
if (!preg_match('/^PHP [0-9]+\.[0-9]+\.[0-9]+/i', $info)) {
  die('Couldn\'t find php in '. $php_bin);
}

// Check composer
$composer_bin = "{$cwd}/composer.phar";
$info = syscall("{$php_bin} {$composer_bin} --version");
if (!preg_match('/composer version/i', $info)) {
  //die('Couldn\'t find composer in '. $composer_bin); //@fixme: Doesn't work (
}

// Self-update composer
syscall("{$php_bin} {$composer_bin} self-update");

// Require DrupalHelpers
require_once '../drupal_helpers/DrupalHelpers.php';

// Find drupal root directory
$drupal_root = DrupalHelpersNS\DrupalHelpers::findDrupalRoot();
if (!$drupal_root) {
  die('Can\'t find Drupal index.php');
}

$list = list_dir($drupal_root.'/sites');

// Now search for composer.json files
foreach ($list->raw as $info) {
  // Only check if it is not directory
  if (!isset($info['children']) && $info['basename'] == 'composer.json') {
    $composer_dir = $info['dirname'];
    chdir($composer_dir);
    // Run composer update for this composer dir
    syscall("{$php_bin} {$composer_bin} update");
  }
}

// Back to old current directory
chdir($cwd);