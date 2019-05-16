<?php
namespace mngProject;

class u
{

  static function echo($text, $echo = false){
      if ($echo){
        echo "{$text}\n";
      }
  }

  static function getJson($file){

    // Check if file exists
    if (!self::fileExists($file)){
      throw new \Exception("File `{$file}` does not exist!");
    }
    // get file content
    $content = @file_get_contents($file);
    if (!$content){
      throw new \Exception("Cannot read file {$file}");
    }

    // parse json
    $json = json_decode($content, true);
    if (!$json || !is_array($json)){
      throw new \Exception("Cannot parse the content of {$file}");
    }

    return $json;
  }

  static function fileExists($file){
    if (preg_match('/http/', $file)) {
      // https://stackoverflow.com/a/10444151/586449
      $file_headers = @get_headers($file);
      if($file_headers[0] == 'HTTP/1.0 404 Not Found'){
            return false;
      } else if ($file_headers[0] == 'HTTP/1.0 302 Found' && $file_headers[7] == 'HTTP/1.0 404 Not Found'){
          return false;
      } else {
          return true;
      }
    } else {
      return file_exists($file);
    }
  }
}
