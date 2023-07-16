<?php

namespace Ship;

use Illuminate\Support\Facades\Log;
use \Imagick;

class ImageHelper
{
  public static function getImageSize($file, $scale = 100)
  {
    $type = substr($file, strrpos($file,  '.'));

    $width = null;
    $height = null;

    if (!is_int($scale)) {
      if (is_string($scale)) {
        $scale = intval($scale);
      } else {
        $scale = (int) $scale;
      }
    }
    $s = $scale / 100.0;

    if ($type == '.eps') {

      try {
        $file_handle = fopen($file, "r");

        if (!$file_handle) {
          Log::error('ImageHelper getImageSize: File open failed. ' . $file);
        }

        while (!feof($file_handle)) {
          $line = fgets($file_handle);
          if (strpos($line, 'HiResBoundingBox') !== false) {
            $line = str_replace("\r\n", '', $line);
            $ex = explode(' ', $line);
            $width = number_format((intval(trim($ex[3])) * $s) / 72, 3);
            $height = number_format((intval(trim($ex[4])) * $s) / 72, 3);
            break;
          }
        }
        fclose($file_handle);
      } catch (Exception $e) {
        Log::error('ImageHelper getImageSize: Error opening EPS file - ' . $e->getMessage());
      }
    } else if ($type == '.pdf') {

      $output = shell_exec("pdfinfo " . $file);

      // find page sizes
      preg_match('/Page size:\s+([0-9]{0,5}\.?[0-9]{0,3}) x ([0-9]{0,5}\.?[0-9]{0,3})/', $output, $pagesizematches);

      $pagesizematches = [100, 100, 100]; //TODO for height uncomment when output is working

      if ($pagesizematches != []) {
        $width = number_format(($pagesizematches[1] * $s) / 72, 3);
        $height = number_format(($pagesizematches[2] * $s) / 72, 3);
      } else {
        Log::error('ImageHelper getImageSize: pdfinfo failed. ' . $file);
      }
    } else if ($type == '.jpg' || $type == '.jpeg') {

      $size = @getimagesize($file);

      if (is_array($size) && isset($size[1])) {
        $width = number_format(($size[0] * $s) / 72, 3);
        $height = number_format(($size[1] * $s) / 72, 3);
      } else {
        Log::error('ImageHelper getImageSize: getimagesize failed. ' . $file);
      }
    } else {

      $size = @getimagesize($file);

      if (is_array($size) && isset($size[1])) {
        $width = number_format(($size[0] * $s) / 150, 3);
        $height = number_format(($size[1] * $s) / 150, 3);
      } else {
        Log::error('ImageHelper getImageSize: getimagesize failed. ' . $file);
      }
    }

    if ($height != null) {
      return ['file' => $file, 'type' => $type, 'width' => $width, 'height' => $height, 'scale' =>  $scale];
    }

    return false;
  }

  public static function createThumb($image_path, $flop = 0, $thumb_path, $size = 250)
  {

    set_time_limit(0);
    Log::error("BEGIN " . $image_path);
    if (stripos($image_path, "pdf") !== false) {
      shell_exec("pdftoppm -jpeg $image_path " . $image_path);

      shell_exec("mv $image_path" . "-1.jpg " . str_replace("pdf", "jpg", $thumb_path));
    } else {
      $image = new Imagick($image_path);

      // $image->trimImage(20000);
      if ($flop == 1) {
        $image->flopImage();
      }
      try {
        $image->setImageAlphaChannel(Imagick::VIRTUALPIXELMETHOD_WHITE);
      } catch (\Exception $e) {
        Log::error('ImageHelper createThumb: ' . $e->getMessage());
        Log::error('ImageHelper createThumb image_path: ' . $image_path);
      }
      $image->transformImageColorspace(Imagick::COLORSPACE_CMY);
      // $image->thumbnailImage($size, $size, true);

      if (file_exists($thumb_path)) {
        try {
          unlink($thumb_path);
        } catch (\Exception $e) {
          Log::error('ImageHelper createThumb: failed deleting old thumb ' . $thumb_path . ' - ' . $e->getMessage());
          return;
        }
      }
      if (!$image->writeImage($thumb_path)) {
        Log::error('ImageHelper createThumb: Error writing thumbnail ' . $image_path);
      }
    }
  }
}
