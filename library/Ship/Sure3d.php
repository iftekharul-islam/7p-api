<?php

namespace Ship;

use Imagick;
use Illuminate\Support\Facades\Log;
use App\Models\Batch;
use App\Models\Item;
use Ship\ImageHelper;

class Sure3d
{
  protected $download_dir = '/media/RDrive/Sure3d/';
  protected $old_download_dir = '/media/RDrive/Sure3d/';
  const thumb_dir = '/public_html/assets/images/Sure3d/thumbs/';
  const thumb_web = '/assets/images/Sure3d/thumbs/';
  protected $main = '/media/RDrive/MAIN/';

  protected $files = array();

  public function setLink($item, $link = null)
  {

    $item = Item::find($item);

    $options = json_decode($item->item_option, true);

    if ($link != null) {

      $options['Custom_EPS_download_link'] = $link;

      $item->item_option = json_encode($options);
    } else if ($link == null && isset($options['Custom_EPS_download_link'])) {

      $link = $options['Custom_EPS_download_link'];
    } else if ($link == null) {

      return false;
    }

    $item->sure3d = $link;
    $item->save();
    $url = $this->getImage($item);
    return $url;
  }

  public function getImage($item, $find = 0)
  {

    if (!file_exists($this->download_dir)) {
      mkdir($this->download_dir);
    }

    if (!($item instanceof Item)) {
      $item = Item::find($item);
    }

    if (!$item) {
      Log::error('Sure3d getImage: Item not found');
      return null;
    }

    if ($item->sure3d == null) {
      $options = json_decode($item->item_option, true);
      if (isset($options['Custom_EPS_download_link'])) {
        $item->sure3d = str_replace("https", "http", $options['Custom_EPS_download_link']);
        $item->save();
      } else {
        return null;
      }
    }

    if (strpos($item->sure3d, 'postimg.cc')) {
      return null;
    }

    $url = $item->sure3d;

    $ext = substr($url, strrpos($url, '.'));


    $filename = $this->download_dir . $item->order->short_order . '-' . $item->id . $ext;

    if (file_exists($filename) && $find == 0) {
      try {
        unlink($filename);
      } catch (\Exception $e) {
        Log::error('Sure3d getImage: Order# ' . $item->order->short_order . ' failed remove old file = ' . $filename . ' - ' . $e->getMessage());
      }
    } else if (file_exists($filename) && $find > 0) {
      Log::info('Sure3d getImage: file Exist 1 ' . $filename . '  url =' . $url);
      return $url;
    } else if (
      !file_exists($filename) && $find == 1 &&
      file_exists($this->old_download_dir . $item->order->short_order . '-' . $item->id . $ext)
    ) {
      Log::info('Sure3d getImage: file Exist 2 ' . $filename . '  url =' . $url);
      return $url;
    }

    $count = 0;
    set_time_limit(0);
    ini_set('memory_limit', '512M');

    do {

      try {
        touch($filename);
        $fp = fopen($filename, 'w+');
        if ($fp === false) {
          throw new \Exception('Sure3d getImage: Could not open: ' . $filename);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt(
          $ch,
          CURLOPT_USERAGENT,
          'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13'
        );
        curl_exec($ch);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        if (curl_errno($ch)) {
          throw new \Exception(curl_error($ch));
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        fclose($fp);

        if ($statusCode == 200) {
          $saved = true;
        } else {
          throw new \Exception("Sure3d getImage: Status Code: " . $statusCode);
        }
      } catch (\Exception $e) {
        $saved = false;
        $count++;
        Log::error('Sure3d getImage: Count = ' . $count . '  Order# ' . $item->order->short_order . ' Sure3d getImage: ' . $e->getMessage());
        sleep(2);
      }
    } while (!$saved && $count < 10);

    if (!$saved) {
      Log::error('getImage Order# ' . $item->order->short_order . ' Sure3d getImage: Download Failed - ' . $url);
      return null;
    }

    if (strtolower($ext) == '.dxf') {
      $epsUrl = str_replace('.dxf', '.eps', str_replace('/DXF/', '/EPS/', $url));
      $tmp = $this->getImage($item);
    } else {
      try {
        $this->createThumb($item, $ext);
      } catch (\Exception $e) {
        Log::error('Order# ' . $item->order->short_order . ' Sure3d createThumb: Exception creating thumbnail ' . $filename . ' - ' . $e->getMessage());
      }
    }





    return $url;
  }

  public function createThumb($item, $ext = '.eps')
  {



    # Log::info('Create thumb ' . $ext);
    if (!file_exists(base_path() . Sure3d::thumb_dir)) {
      mkdir(base_path() . Sure3d::thumb_dir);
    }

    if (!($item instanceof Item)) {
      $item = Item::with('order')->find($item);
    }

    $file = $item->order->short_order . '-' . $item->id . $ext;
    $image_path = $this->download_dir . $file;
    Log::info("createThumb: " . $file . ' - ' . $image_path);

    if (!file_exists($image_path)) {
      Log::error('Sure3d getImage: Image does not exist - ' . $image_path);
      return;
    }

    // $image = file_get_contents($image_path);

    try {
      $section = strtolower($item->parameter_option->route->stations->first()->section_info->section_name);
    } catch (\Exception $e) {
      $section = '';
    }

    if ($section == 'sublimation' || $section == 'applique') {
      $flop = 1;
    } else {
      $flop = 0;
    }

    $thumb_dir = base_path() . Sure3d::thumb_dir . substr($file, 0, strpos($file, '.')) . '.jpg';

    try {
      ImageHelper::createThumb($image_path, $flop, $thumb_dir);
    } catch (\Exception $e) {
      Log::error('Sure3d createThumb: ' . $e->getMessage());
    }

    return;
  }

  public static function

  getThumb($item)
  {
    if ($item->sure3d == null) {
      return false;
    }
    if (!isset($item->order->short_order)) {
      return false;
    }

    $jpg = $item->order->short_order . '-' . $item->id . '.jpg';

    $thumb_path = base_path() . Sure3d::thumb_dir . $jpg;

    if (!file_exists($thumb_path)) {
      return false;
    }

    try {
      $size = getimagesize($thumb_path);
    } catch (\Exception $e) {
      Log::error('Sure3d: Thumbnail file not found ' . $thumb_path);
      return false;
    }

    return [Sure3d::thumb_web . $jpg, $size[0], $size[1]];
  }

  public function exportBatch($batch)
  {

    if (!($batch instanceof Batch)) {
      $batch = Batch::with('items')->where('batch_number', $batch)->first();
    }

    set_time_limit(0);

    $graphics = array();

    $graphic_count = 0;

    foreach ($batch->items as $item) {
      info("This is Item Sure3d");
      info($item->sure3d);
      if ($item->sure3d != NULL) {
        $graphic_count++;
        $url = $this->getImage($item, 1);
        info("This is Image created by getImage function");
        info($url);
        for ($x = 0; $x < $item->item_quantity; $x++) {
          $graphics[] = $item->order->short_order . '-' . $item->id . substr($item->sure3d, strrpos($item->sure3d, '.'));
        }
      }
    }

    if (count($graphics) > 0 && $graphic_count < count($batch->items)) {
      Batch::note($batch->batch_number, $batch->station_id, '9', 'Sure3d Error: Mixed Sure3d Batch - not all ids set');
      Log::error('Sure3d moveBatch Error: Mixed Sure3d Batch - not all ids set - Batch: ' . $batch->batch_number);
      return false;
    }

    if (count($graphics) > 0) {

      $list = glob($this->main . $batch->batch_number . "*");

      if (count($list) > 0) {

        Batch::note($batch->batch_number, $batch->station_id, '9', 'Sure3d Error: Batch already in MAIN');
        Log::error('Sure3d moveBatch Error: Batch already in MAIN - Batch: ' . $batch->batch_number);
        return false;
      }

      info($batch->route);

      $dir = $this->main . $batch->batch_number . $batch->route->csv_extension;
      info($dir);

      try {
        if (strtolower($batch->route->graphic_dir) == '' && count($graphics) > 1) {
          ini_set('memory_limit', '256M');
          $this->layout($graphics, $batch->batch_number, $dir);
        } else {
          mkdir($dir);
          touch($dir . '/lock');
          foreach ($graphics as $key => $sure3d) {
            if (file_exists($this->download_dir . $sure3d) && strtolower($batch->route->graphic_dir) == '') {
              copy($this->download_dir . $sure3d, $dir . '/' . $batch->batch_number . '-' . $key . substr($sure3d, strrpos($sure3d, '.')));
            } else if (file_exists($this->download_dir . $sure3d)) {
              copy($this->download_dir . $sure3d, $dir . '/' . $sure3d);
            } else if (file_exists($this->old_download_dir . $sure3d) && strtolower($batch->route->graphic_dir) == '') {
              copy($this->old_download_dir . $sure3d, $dir . '/' . $batch->batch_number . '-' . $key . substr($sure3d, strrpos($sure3d, '.')));
            } else if (file_exists($this->old_download_dir . $sure3d)) {
              copy($this->old_download_dir . $sure3d, $dir . '/' . $sure3d);
            } else {
              Log::error('Sure3d: Graphic not found ' . $sure3d);
              Batch::note($batch->batch_number, $batch->station_id, '9', 'Sure3d: Graphic not found ' . $sure3d);
            }
          }
          unlink($dir . '/lock');
        }
      } catch (\Throwable $e) {
        Log::error('Sure3d Export : MAIN Dir ' . $dir . ' - ' . $e->getMessage());
      }
    }

    Batch::note($batch->batch_number, $batch->station_id, '9', 'Sure3d Graphic Created');
    return true;
  }

  public function layout($graphics, $batch_number, $dir)
  {
    Log::info('Layout');
    $pages = array();
    $pages[] = array();
    $page_height = 0;
    $big = '';

    foreach ($graphics as $graphic) {
      Log::info($graphic);

      if (file_exists($this->download_dir . $graphic)) {
        $filename = $this->download_dir . $graphic;
      } else if (file_exists($this->old_download_dir . $graphic)) {
        $filename = $this->old_download_dir . $graphic;
      }

      $size = ImageHelper::getImageSize($filename); //getImageGeometry?

      // if ($size['height'] > $size['width'] && $size['height'] < 1650) {
      //   $this->rotateEps($filename);
      //   $size = $this->getEpsSize($filename);
      // }

      if ($size['width'] > 23 && !strpos(strtolower($dir), 'big')) {
        $big = '-BIG';
      }

      if ($page_height + $size['height'] + .75 > 200) {
        $pages[] = array();
        $page_height = 0;
      }

      $page_height += $size['height'] + .75;

      $pages[count($pages) - 1][] = $graphic;
    }

    mkdir($dir);
    touch($dir . '/lock');

    foreach ($pages as $page => $graphics) {

      $image = new Imagick();

      foreach ($graphics as $graphic) {
        try {
          echo "$graphic\n";
          $image->readImage($filename);
          $image->newImage(50, 50, 'white');
        } catch (\Exception $e) {
          Log::error('Sure3d: Error laying out file ' . $graphic . ' - ' . $e->getMessage());
        }
      }
      echo "1\n";
      $image->resetIterator();
      echo "2\n";
      $combined = $image->appendImages(true);
      echo "3\n";

      $page = $page + 1;

      if (count($pages) == 1) {
        $fp = $dir . '/' . $batch_number . $big . '.eps';
      } else {
        $fp = $dir . '/' . $batch_number . $big . '-page' . $page . 'of' . count($pages) . '.eps';
      }

      $combined->writeImage($fp);
    }

    unlink($dir . '/lock');
    return;
  }

  private function rotateEps($fileName)
  {

    $image = new Imagick();
    $image->readImage($fileName);
    $image->rotateImage('white', 90);
    $image->writeImage($fileName);
    return true;
  }

  public function download()
  {

    $items = Item::with('order')
      ->where('item_status', 1)
      ->whereNotNull('sure3d')
      ->where('is_deleted', '0')
      ->get();

    foreach ($items as $item) {
      $file = $this->download_dir . $item->order->short_order . '-' .
        $item->id . substr($item->sure3d, strrpos($item->sure3d, '.'));

      $old_file = $this->old_download_dir . $item->order->short_order . '-' .
        $item->id . substr($item->sure3d, strrpos($item->sure3d, '.'));

      //      if (!file_exists($file) && !file_exists($old_file)) {
      if (!file_exists($file)) {
        try {
          $item->sure3d = $this->getImage($item);
        } catch (\Exception $e) {
          Log::error('Sure3d download: Exception getting image ' . $item->sure3d . ' - ' . $e->getMessage());
        }
      }
    }
  }

  public function downloadSure3dByItemId($itemId)
  {
    //dd($itemId);
    $items = Item::with('order')
      ->where('id', $itemId)
      ->where('item_status', 1)
      ->whereNotNull('sure3d')
      ->where('is_deleted', '0')
      ->get();

    if ($items->count()) {
      foreach ($items as $item) {
        try {
          $item->sure3d = $this->getImage($item);
        } catch (\Exception $e) {
          Log::error('Sure3d download: Exception getting image ' . $item->sure3d . ' - ' . $e->getMessage());
        }
      }
      dd("Image download in  Sure3D folder");
    } else {
      dd($items, "Its not a Sure3D item");
    }
  }
}
