<?php

namespace Ship;

use Illuminate\Support\Facades\Log;

class FileHelper
{
  public static function getContents($dir, &$results = array())
  {
    //recursively list all files in directory
    if (!is_dir($dir)) {
      $results[] = $dir;
    } else {
      $files = array_diff(scandir($dir), array('..', '.'));;
      foreach ($files as $key => $value) {
        $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
        if (!is_dir($path)) {
          $results[] = $path;
        } else {
          FileHelper::getContents($path, $results);
        }
      }
    }
    return $results;
  }

  private function recurseCopy($src, $dst, $rename = 0)
  {

    if (is_dir($src)) {

      $dir = opendir($src);

      mkdir($dst);

      while (false !== ($file = readdir($dir))) {

        if (($file != '.') && ($file != '..')) {

          if (is_dir($src . '/' . $file)) {

            $this->recurseCopy($src . '/' . $file, $dst . '/' . $file, $rename);
          } else {

            try {

              if (substr($file, -4) == '.tmp' || substr($file, -3) == '.db') {
                unlink($src . '/' . $file);
                continue;
              }

              if ($rename) {
                $new_file = $this->uniqueFileName($dst, $file, null, 1);
              } else {
                $new_file = $file;
              }

              copy($src . '/' . $file, $dst . '/' . $new_file);
            } catch (\Exception $e) {
              Log::error('recurseCopy: Cannot copy file ' . $dir . ' - ' . $e->getMessage());
            }
          }
        }
      }
      closedir($dir);
    } else {

      if ($rename) {
        if (strrpos($dst, '/')) {
          $file = substr($dst, strrpos($dst, '/') + 1);
          $dir = substr($dst, 0, strlen($dst) - strlen($file));
        } else {
          $file = $dst;
          $dir = '/';
        }

        $new_file = $this->uniqueFileName($dir, $file, null, 1);
      } else {
        $new_file = $dst;
        $dir = '';
      }

      try {
        copy($src, $dir . $new_file);
      } catch (\Exception $e) {
        Log::error('recurseCopy: Cannot copy file ' . $dir . ' - ' . $e->getMessage());
      }
    }
  }

  private function recurseAppend($src, $dst)
  {

    if (is_dir($src)) {
      $dir = opendir($src);
      if (!is_dir($dst)) {
        mkdir($dst);
      }
      while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
          if (is_dir($src . '/' . $file)) {
            $this->recurseAppend($src . '/' . $file, $dst . '/' . $file);
          } else {
            try {
              copy($src . '/' . $file, $dst . '/' . $file);
            } catch (\Exception $e) {
              Log::error('recurseAppend: Cannot copy file ' . $dir . ' - ' . $e->getMessage());
            }
          }
        }
      }
      closedir($dir);
    } else {
      if (file_exists($dst)) {
        $file = substr($dst, strrpos($dst, '/') + 1);
        $dir = substr($dst, 0, strlen($file));

        $file = $this->uniqueFilename($dir, $file);
      } else {
        $file = $dst;
      }
      try {
        copy($src, $file);
      } catch (\Exception $e) {
        Log::error('recurseAppend: Cannot copy file ' . $src . ' - ' . $e->getMessage());
      }
    }
  }

  public static function remove($path)
  {

    if (!file_exists($path)) {
      return true;
    }

    if (!is_dir($path)) {
      try {
        return unlink($path);
      } catch (\Exception $e) {
        Log::error('FileManager remove: cannot unlink ' . $path);
        return false;
      }
    } else {

      if (substr($path, strlen($path) - 1, 1) != '/') {
        $path .= '/';
      }

      $files = glob($path . '*', GLOB_MARK);
      foreach ($files as $file) {
        if (is_dir($file)) {
          self::remove($file);
        } else {
          try {
            unlink($file);
          } catch (\Exception $e) {
            Log::error('FileManager remove: cannot remove file ' . $file);
            return false;
          }
        }
      }

      return @rmdir($path);
    }
  }

  public static function removeEmptySubFolders($path, $original_path = null)
  {
    if ($original_path == null) {
      $original_path = $path;
    }

    $empty = true;

    foreach (glob($path . "*") as $file) {
      if (is_dir($file)) {

        if (substr($file, -1) != '/') {
          $file .= '/';
        }

        if (!FileHelper::removeEmptySubFolders($file, $original_path)) {
          $empty = false;
        }
      } else {
        $empty = false;
      }
    }

    if ($empty && $path != $original_path && file_exists($path)) {
      @rmdir($path);
    }

    return $empty;
  }
}
