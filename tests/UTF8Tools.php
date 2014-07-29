<?php
/*=========================================================================
 MIDAS Server
 Copyright (c) Kitware SAS. 26 rue Louis Guérin. 69100 Villeurbanne, FRANCE
 All rights reserved.
 More information http://www.kitware.com

 Licensed under the Apache License, Version 2.0 (the "License");
 you may not use this file except in compliance with the License.
 You may obtain a copy of the License at

         http://www.apache.org/licenses/LICENSE-2.0.txt

 Unless required by applicable law or agreed to in writing, software
 distributed under the License is distributed on an "AS IS" BASIS,
 WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 See the License for the specific language governing permissions and
 limitations under the License.
=========================================================================*/

/** tools for detecting non UTF-8 files and transforming non UTF-8 files to UTF-8. */
class UTF8Tools
  {
  protected $excludedDirs = array('_build', '_test', '.git', 'bin', 'build', 'data', 'library', 'log', 'tmp');
  protected $excludedExts = array('gif', 'ico', 'ini', 'jar', 'jpeg', 'jpg', 'keystore', 'png', 'psd', 'swc', 'swf');
  protected $excludedFiles = array('.DS_Store', '.htaccess', '.gitignore', '.travis.yml');

  /**
   * return true if the string is UTF8 encoded.
   */
  protected function isUTF8($str)
    {
    $len = strlen($str);
    for($i = 0; $i < $len; $i++)
      {
      $c = ord($str[$i]);
      if($c > 128)
        {
        if(($c > 247))
          {
          return false;
          }
        else if($c > 239)
          {
          $bytes = 4;
          }
        else if($c > 223)
          {
          $bytes = 3;
          }
        else if($c > 191)
          {
          $bytes = 2;
          }
        else
          {
          return false;
          }
        if(($i + $bytes) > $len)
          {
          return false;
          }
        while($bytes > 1)
          {
          $i++;
          $b = ord($str[$i]);
          if($b < 128 || $b > 191)
            {
            return false;
            }
          $bytes--;
          }
        }
      }
    return true;
    }

  /**
   * gets a list of all files rooted at the src, excluding
   * certain subdirs, extensions, and filenames.
   */
  function getMatchingFilesRecursive($src, $dir = '')
    {
    $files = array();
    if(!is_dir($src))
      {
      $files[] = $src;
      }
    else
      {
      $root = opendir($src);
      if($root)
        {
        while($file = readdir($root))
          {
          // We ignore the current and parent directory links
          if($file == '.' || $file == '..')
            {
            continue;
            }
          if(is_dir($src.'/'.$file))
            {
            if(array_search($file, $this->excludedDirs) !== false)
              {
              continue;
              }
            $files = array_merge($files, $this->getMatchingFilesRecursive($src.'/'.$file, $dir.'/'.$file));
            }
          else
            {
            if(array_search($file, $this->excludedFiles) !== false)
              {
              continue;
              }
            $pathParts = pathinfo($file);
            if(array_key_exists('extension', $pathParts))
              {
              if(array_search($pathParts['extension'], $this->excludedExts) === false)
                {
                $files[] = $src.'/'.$file;
                }
              }
            }
          }
        }
      }
    return $files;
    }

  /**
   * create a listing of files, should be called from the MIDAS BASE DIR, checks
   * them for non-UTF-8 encoded files, and if createUtf8Version is true,
   * will create another file in the same dir alongside any non-UTF-8 file
   * that is UTF-8 encoded and has the same name as the non-UTF-8 file, with
   * an extension of .utf8.
   */
  public function listNonUtf8Files($srcDir, $createUtf8Version = false)
    {
    $allFiles =  $this->getMatchingFilesRecursive($srcDir);
    foreach($allFiles as $file)
      {
      $filecontents = file_get_contents($file);
      if(!$this->isUTF8($filecontents))
        {
        echo "ERROR: non-UTF-8 characters found in ".$file."\n";
        if($createUtf8Version)
          {
          $utf8Version = mb_convert_encoding($filecontents, 'UTF-8');
          $outfilepath = $file.'.utf8';
          file_put_contents($outfilepath, $utf8Version);
          }
        }
      }
    }
  }

// do not create UTF-8 versions by default
$create = false;
if(sizeof($argv) > 3 || sizeof($argv) < 2)
  {
  if($argv[3] !== 'create')
    {
    echo "Usage:\n\nphp UTF8Tools.php --src <MIDAS SOURCE DIR> [create]\n\noptional argument create says to create UTF-8 versions on non UTF-8 encoded files\n";
    exit();
    }
  else
    {
    $create = true;
    }
  }
$srcDir = $argv[2];
$utf8 = new UTF8Tools();
$utf8->listNonUtf8Files($srcDir, $create);
