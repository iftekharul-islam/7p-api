<?php

namespace Ship;

use Illuminate\Support\Facades\Log;

class CSV
{

  public function createFile($array, $dir = null, $headings = null, $filename = null, $delimiter = ',', $mode = 'a')
  {
    if ($dir == null) {
      $file_path = sprintf("%s/%s", public_path(), 'assets/exports/');
    } else {
      $file_path = $dir;
    }

    if ($filename == null) {
      $filename = sprintf("export_%s.csv", date("Y_m_d_His", strtotime('now')));
    }

    $fully_specified_path = sprintf("%s%s", $file_path, $filename);

    if ($headings != NULL) {
      $fh = fopen($fully_specified_path, $mode);
      fputcsv($fh, $headings, $delimiter);
      fclose($fh);
    }

    $array = array_chunk($array, 1000);

    foreach ($array as $chunk) {
      $fh = fopen($fully_specified_path, $mode);
      foreach ($chunk as $item) {
        try {
          fputcsv($fh, $item, $delimiter);
        } catch (\Exception $e) {
          Log::error('CSV: ' . $e->getMessage());
          Log::error($item);
        }
      }
      fclose($fh);
    }

    return $fully_specified_path;
  }

  public function intoArray($filepath, $delimiter)
  {
    $array = array();

    if (($handle = fopen($filepath, "r")) !== FALSE) {

      while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
        $array[] = $data;
      }

      fclose($handle);
      return $array;
    } else {
      return null;
    }
  }
}
