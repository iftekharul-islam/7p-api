<?php

namespace Ship;

use Exception;
use Illuminate\Support\Facades\Log;

class Wasatch
{

    protected $units = array();
    protected $QUEUES = null;

    protected $staging = [
        '1' => '/wasatch/staging-1/',
        '2' => '/wasatch/staging-2/',
        '3' => '/wasatch/staging-3/',
        '4' => '/wasatch/staging-4/',
        '5' => '/wasatch/staging-5/',
        '6' => '/wasatch/staging-6/',
        '7' => '/wasatch/staging-7/',
        '8' => '/wasatch/staging-8/',

    ];
    protected $hotfolders = [
        '1' => '/media/RDrive/SOFT-1/',
        '2' => '/media/RDrive/SOFT-2/',
        '3' => '/media/RDrive/SOFT-3/',
        '4' => '/media/RDrive/SOFT-4/',
        '5' => '/media/RDrive/SOFT-5/',
        '6' => '/media/RDrive/SOFT-6/',
        '7' => '/media/RDrive/SOFT-7/',
        '8' => '/media/RDrive/SOFT-8/',
        //        '5' => '/media/RDrive/Epson-5/'
    ];

    protected $conf = [
        'SOFT' => '720v_Yarrington_Bodyflex_DS_Transfer_Production',
        'HARD' => '720x1440_Chromaluxe_Gloss_White_DS_Transfer_Multi_Purpose',
        'EPSO' => null
    ];

    protected $prefix = '//10.10.0.14/public/graphics';
    protected $webPrefix = 'http://order.monogramonline.com/media';
    protected $old_prefix = '//10.10.0.9/Graphics';

    protected $ip = 'http://10.10.0.254/';

    protected $system_setup = '/wasatch/setup.xml';

    public function getUnits()
    {

        $response = $this->simpleRequest($this->ip . 'xmlSystem.dyn');

        foreach ($response['PRINTUNIT'] as $line) {
            $this->units[$line['@attributes']['number']] = $line['@attributes']['name'];
        }

        if (is_array($this->units)) {
            return $this->units;
        }

        return null;
    }

    private function simpleRequest($url)
    {

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $xml = curl_exec($ch);
            curl_close($ch);
        } catch (Exception $e) {
            Log::info('Wasatch Curl Error: ' . $e->getMessage());
            return false;
        }

        if ($xml != 404) {
            try {
                $doc = simplexml_load_string($xml);
                $json = json_encode($doc);
                return json_decode($json, TRUE);
            } catch (Exception $e) {
                Log::info('Wasatch XML Decode Error: ' . $e->getMessage());
                return false;
            }
        } else {
            Log::info('Wasatch 404 Error');
            return false;
        }
    }

    public function clearJob($batch)
    {

        $units = $this->getQueues();

        if (!is_array($this->QUEUES)) {
            return $this->QUEUES;
        }

        $indexes = array();

        foreach ($this->QUEUES as $unit_name => $unit) {
            $num = substr($unit_name, -1);
            foreach ($unit as $queue_name => $queue) {
                if (is_array($queue)) {
                    foreach ($queue as $index => $item) {
                        if (strpos($item, $batch) !== false) {
                            if ($queue_name == 'STAGED_XML') {
                                unlink(storage_path() . $this->staging[$num] . $item);
                            } else if ($queue_name == 'HOT_FOLDER') {
                                unlink($this->hotfolders[$num] . $item);
                            } else if ($queue_name == 'RIP_QUEUE') {
                                $this->delete($num, 'job', $index, $batch);
                            } else if ($queue_name == 'PRINT_QUEUE') {
                                $this->delete($num, 'index', $index, $batch);
                            }
                        }
                    }
                }
            }
        }

        return '1';
    }

    public function getQueues()
    {

        $this->QUEUES = array();

        foreach ($this->hotfolders as $i => $hotfolder) {

            $xml = '<?xml version="1.0" encoding="utf-8"?><WASATCH ACTION=SYSTEM>' .
                '<SETANNOTATIONBARCODE PRINTUNIT=' . $i . '>IDAutomationHC39XS,18.0</SETANNOTATIONBARCODE>' .
                '</WASATCH>';

            $setup = $this->simpleRequest($this->ip . 'xmlSubmission.dyn?' . $xml);

            $unit = 'PRINTER_' . $i;

            if (!file_exists($this->hotfolders[$i])) {
                Log::error('Wasatch: Hotfolder does not exist - ' . $this->hotfolders[$i]);
                return 'Error: Hotfolder does not exist';
            }

            $this->removeBadFiles($this->staging[$i]);

            $this->QUEUES[$unit]['STAGED_XML'] = array_diff(scandir(storage_path() . $this->staging[$i]), array('..', '.'));

            FileHelper::removeEmptySubFolders($this->hotfolders[$i]);

            $this->removeBadFiles($this->hotfolders[$i]);

            $this->QUEUES[$unit]['HOT_FOLDER'] = array_diff(scandir($this->hotfolders[$i]), array('..', '.'));

            $input = $this->simpleRequest($this->ip . 'xmlQueueStatus.dyn?PRINTUNIT=' . $i);

            if ($input === false) {
                //                Log::error($this->ip . ' Wasatch getQueues: Cannot retrieve Queue Info');
                ////            return 'Error: Cannot retrieve Wasatch queues';
                return "NOTE: Wasatch queues has been disabled, this will NOT affect any printing behavior.";
            }

            $this->QUEUES[$unit]['RIP_QUEUE'] = array();
            $this->QUEUES[$unit]['PRINT_QUEUE'] = array();

            $RIP_TOTAL = array();

            if (isset($input['RIPQUEUE']['ITEM'])) {
                foreach ($input['RIPQUEUE']['ITEM'] as $item) {
                    if (isset($item['@attributes']['job'])) {
                        $this->QUEUES[$unit]['RIP_QUEUE'][$item['@attributes']['job']] = $item['@attributes']['job'];
                        try {
                            $RIP_TOTAL[] = substr($item['@attributes']['job'], 0, strpos(str_replace('-', '_', $item['@attributes']['job']), '_', 4));
                        } catch (Exception $e) {
                            $RIP_TOTAL[] = substr($item['@attributes']['job'], 0, strpos(str_replace('-', '_', $item['@attributes']['job']), '_'));
                        }
                    }
                }
            } else {
                $this->QUEUES[$unit]['RIP_QUEUE'] = [];
            }

            $PRINT_TOTAL = array();

            if (isset($input['PRINTQUEUE']['ITEM'])) {
                foreach ($input['PRINTQUEUE']['ITEM'] as $item) {
                    if (isset($item['@attributes']['notes'])) {
                        $this->QUEUES[$unit]['PRINT_QUEUE'][$item['@attributes']['index']] = $item['@attributes']['notes'];
                        try {
                            $PRINT_TOTAL[] = substr($item['@attributes']['notes'], 0, strpos(str_replace('-', '_', $item['@attributes']['notes']), '_', 4));
                        } catch (Exception $e) {
                            $PRINT_TOTAL[] = substr($item['@attributes']['notes'], 0, strpos(str_replace('-', '_', $item['@attributes']['notes']), '_'));
                        }
                    }
                }
            } else {
                $this->QUEUES[$unit]['PRINT_QUEUE'] = [];
            }

            $this->QUEUES[$unit]['TOTAL'] = count($this->QUEUES[$unit]['STAGED_XML']) +
                count($this->QUEUES[$unit]['HOT_FOLDER']) +
                count(array_unique($RIP_TOTAL)) +
                count(array_unique($PRINT_TOTAL));
        }

        return $this->QUEUES;
    }

    private function removeBadFiles($directory)
    {

        if (substr($directory, -1) != '/') {
            $directory .= '/';
        }

        if (file_exists(storage_path() . $directory . 'Thumbs.db')) {
            unlink(storage_path() . $directory . 'Thumbs.db');
        }

        return;
    }

    private function delete($unit, $type, $id, $note)
    {

        $xml = null;

        $xml = '<?xml version="1.0" encoding="utf-8"?><WASATCH ACTION="JOB">';

        $xml .= '<DELETE printunit=' . $unit . ' ' . $type . '="' . $id . '" />';

        $xml .= '</WASATCH>';

        $newfile = fopen($this->hotfolders[$unit] . $id . '.xml', "w");
        fwrite($newfile, $xml);
        fclose($newfile);

        $newfile = fopen('/media/RDrive/temp/' . $id . '.xml', "w");
        fwrite($newfile, $xml);
        fclose($newfile);

        if (auth()->user()) {
            Log::info(auth()->user()->username . '-' . $id . ' Deleted - ' . $note);
        } else {
            Log::info($id . 'Deleted - ' . $note);
        }

        return;
    }

    public function notInQueue($batch)
    {

        if ($this->QUEUES == null) {
            $units = $this->getQueues();
        }

        if (!is_array($this->QUEUES)) {
            return $this->QUEUES;
        }

        $length = strlen($batch);
        $underscore_batch = str_replace('-', '_', $batch);

        foreach ($this->QUEUES as $unit_name => $unit) {
            foreach ($unit as $queue_name => $queue) {
                if (is_array($queue)) {
                    foreach ($queue as $item) {
                        if (substr($item, 0, $length) == $batch || substr($item, 0, $length) == $underscore_batch) {
                            // Log::error('Wasatch: Batch already in Queue - ' . $batch);
                            return 'Batch in ' . str_replace('_', ' ', $unit_name . ' ' . $queue_name);
                        }
                    }
                }
            }
        }

        return '1';
    }

    public function printJob($files, $barcode, $hotfolder, $imgconf, $width = null, $quantity = 1)
    {
        //        dd($files, $barcode, $hotfolder, $imgconf, $width);
        //        ##################
        //        // Load route info by Batch ($barcode);
        //        $batchRoutes = Batch::getBatchWitRoute($barcode);
        //        $nesting = $batchRoutes->route->nesting;
        //        $groupA=[];
        //        $groupB=[];
        //        if ($nesting == 1) {
        //            $i = 0;
        //            $ii = 1;
        //            foreach ($files as $name => $info) {
        //                if ($info['type'] == ".pdf") {
        //                    $groupPdf[$name] = $info;
        //                    continue;
        //                }
        //
        //                if ($i % 2 == 0) {
        //                    $info['itmem_info']['group'] = (($ii + 1) / 2) . "-A";
        //                    $groupA[$name] = $info;
        //                } else {
        //                    $info['itmem_info']['group'] = ($ii / 2) . "-B";
        //                    $groupB[$name] = $info;
        //                }
        //                $i++;
        //                $ii++;
        //            }
        //
        //            $groupA = array_reverse($groupA);
        //            $groupB = array_reverse($groupB);
        //            $files = array_merge($groupB, $groupA, $groupPdf);
        //        }
        //        dd($files);
        //        ##################
        $xml = null;

        $xml = '<?xml version="1.0" encoding="utf-8"?>';
        $xml .= '<WASATCH ACTION="JOB">';
        $xml .= '<LAYOUT NOTES="' . $barcode . ' Layout">';
        $xml .= '<Copies>' . $quantity . '</Copies>';

        $y = 0;


        foreach ($files as $name => $info) {

            //            dd($files, $barcode, $hotfolder, $imgconf, $width, ((((int) $info['frameSize']) - ((int) $info['height'])) / 2));


            if (!isset($info['type'])) {
                $info['type'] = ".pdf";
            }
            if (!empty($info['frameSize'] && ($info['type']) != ".pdf") && (((((int) $info['frameSize']) - ((int) $info['height'])) / 2) > 0) && (((int) $info['frameSize']) > ((int) $info['height']))) {
                //                $y += 1.25;
                $y += ((((int) $info['frameSize']) - ((int) $info['height'])) / 2);
            }


            $xml .= '<PAGE XPOSITION="0.0" YPOSITION="' . number_format($y, 1) . '">';




            if ($info['source'] == 'R') {
                $xml .= '<FileName>' . $this->webPrefix . $name . '</FileName>';
            } else if ($info['source'] == 'P') {
                $xml .= '<FileName>' . $this->old_prefix . $name . '</FileName>';
            }
            if ($this->conf[$imgconf] != null) {
                //        $xml .= '<IMGCONF>' . $this->conf[$imgconf] . '</IMGCONF>';
            }
            $xml .= '<Copies>0</Copies>';
            //            $xml .= '<ANNOTATE><BARCODE>' . $barcode . '</BARCODE></ANNOTATE>';
            if (!isset($info['scale'])) {
                $xml .= '<Scale>' . 100 . '</Scale>';
            } else {
                $xml .= '<Scale>' . $info['scale'] . '</Scale>';
            }
            // if (isset($info['mirror']) && $info['mirror'] == 1) {
            //   $xml .= '<Mirror>1</Mirror>';
            // } else {
            //   $xml .= '<Mirror>0</Mirror>';
            // }

            if (isset($info['mirror'])) {
                if (empty($info['mirror'])) {
                    $xml .= '<Mirror>0</Mirror>';
                } else {
                    $xml .= '<Mirror>1</Mirror>';
                }
            }

            $xml .= '<DELETEAFTERRIP /><DELETEAFTERPRINT />';
            $xml .= '</PAGE>';

            if (!isset($info['height'])) {
                $info['height'] = $info['frameSize'];
            }

            if ((!empty($info['frameSize']) && (((int) $info['frameSize']) > ((int) $info['height'])))) {
                $y += $info['frameSize'];
            } else {
                //    $y += $info['height'] + 1.75;
                /*
                 * This fixes the height issue
                 */
                if (isset($info['height'])) {
                    $y += $info['height'] ?? 0 + 1.75;
                } else {
                    $y += $info['frameSize'] + 1.75;
                }
            }
        }
        $xml .= '<DELETEAFTERPRINT/>';
        $xml .= '</LAYOUT></WASATCH>';

        //        $this->jdbg("xml", $xml);
        //dd($files, $xml);

        // $tmpfile = fopen('/media/RDrive/temp/' .  $barcode  . '.xml', "w");
        // fwrite($tmpfile, $xml);
        // fclose($tmpfile);
        //dd($this->staging[$hotfolder] . $barcode . '.xml', $hotfolder, $this->staging);
        $newfile = fopen(storage_path() . $this->staging[$hotfolder] . $barcode . '.xml', "w");
        fwrite($newfile, $xml);
        fclose($newfile);

        if (auth()->user()) {
            //   Log::info("printJob = ".auth()->user()->username . '-' . $barcode . '-' . $hotfolder);
        } else {
            //   Log::info('printJob = AutoPrint-' . $barcode . '-' . $hotfolder);
        }

        return;
    }

    public function stagedXml()
    {

        $units = $this->getQueues();

        foreach ($this->staging as $unit => $stage_dir) {
            if (
                !isset($units['PRINTER_' . $unit]) ||
                (count($units['PRINTER_' . $unit]['RIP_QUEUE']) == 0 &&
                    count($units['PRINTER_' . $unit]['PRINT_QUEUE']) < 100 &&
                    count($units['PRINTER_' . $unit]['HOT_FOLDER']) == 0)
            ) {

                $files = glob(storage_path() . $stage_dir . '*.*');
                if (count($files) > 0) {
                    array_multisort(
                        array_map('filemtime', $files),
                        SORT_NUMERIC,
                        SORT_ASC,
                        $files
                    );
                    if (copy($files[0], $this->hotfolders[$unit] . basename($files[0]))) {
                        try {
                            unlink($files[0]);
                        } catch (Exception $e) {
                            Log::error('Wasatch: Error Removing Staged File ' . $e->getMessage());
                        }
                    }
                }
            }
        }

        return;
    }


    public function jdbg($label, $obj)
    {
        $logStr = "5p -- {$label}: ";
        switch (gettype($obj)) {
            case 'boolean':
                if ($obj) {
                    $logStr .= "(bool) -> TRUE";
                } else {
                    $logStr .= "(bool) -> FALSE";
                }
                break;
            case 'integer':
            case 'double':
            case 'string':
                $logStr .= "(" . gettype($obj) . ") -> {$obj}";
                break;
            case 'array':
                $logStr .= "(array) -> " . print_r($obj, true);
                break;
            case 'object':
                try {
                    if (method_exists($obj, 'debug')) {
                        $logStr .= "(" . get_class($obj) . ") -> " . print_r($obj->debug(), true);
                    } else {
                        $logStr .= "Don't know how to log object of class " . get_class($obj);
                    }
                } catch (Exception $e) {
                    $logStr .= "Don't know how to log object of class " . get_class($obj);
                }
                break;
            case 'NULL':
                $logStr .= "NULL";
                break;
            default:
                $logStr .= "Don't know how to log type " . gettype($obj);
        }

        Log::info($logStr);
    }
}
