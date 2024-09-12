<?php

namespace library;

use Illuminate\Support\Facades\Log;

class Pendant
{
    public static $settings = '/media/RDrive/Pendants/PendantEngine/settings.xml';
    public static $backup = '/media/RDrive/settings_backup/';

    private static function loadXmlSettings()
    {
        try {

            $settings_xml = file_get_contents(self::$settings);
        } catch (\Exception $e) {
            Log::error('loadXmlSettings: Error Accessing File ' . $e->getMessage());
            return false;
        }

        $doc = simplexml_load_string($settings_xml);
        $json = json_encode($doc);
        $array = json_decode($json, TRUE);

        return $array;
    }

    public static function getXmlSettings()
    {

        $array = Pendant::loadXmlSettings();

        if (!is_array($array)) {
            return false;
        }

        $stylenames = array();

        foreach ($array['Styles']['style'] as $style) {
            $stylenames[] = $style['StyleName'];
        }

        foreach ($array['Styles']['Style'] as $style) {
            $stylenames[] = $style['StyleName'];
        }

        return $stylenames;
    }

    public static function findXmlSettings($graphic_sku = false)
    {

        $array = Pendant::loadXmlSettings();

        if (!is_array($array)) {
            return false;
        }

        $max = 0;

        foreach ($array['Styles']['style'] as $index => $style) {
            if ($style['StyleName'] == $graphic_sku) {
                return ['found' => '1', 'xml' => $style, 'key' => 'style', 'array' => $array, 'index' => $index];
            }
            if ($style['Sno'] > $max) {
                $max = $style['Sno'];
            }
        }

        foreach ($array['Styles']['Style'] as $index => $style) {
            if ($style['StyleName'] == $graphic_sku) {
                return ['found' => '1', 'xml' => $style, 'key' => 'Style', 'array' => $array, 'index' => $index];
            }
            if ($style['Sno'] > $max) {
                $max = $style['Sno'];
            }
        }

        return ['found' => '0', 'max' => $max, 'array' => $array];
    }

    public static function saveXmlSettings($input)
    {

        $result = Pendant::findXmlSettings(trim($input['StyleName']));

        $array = $result['array'];

        if ($result['found'] == '1') {

            $settings = $result['xml'];
            $key = $result['key'];
            $index = $result['index'];
        } else {

            $settings = array();
            $settings['Sno'] = $result['max'] + 1;
            $settings['StyleName'] = trim($input['StyleName']);
            $key = 'Style';
        }

        $settings['FontP1'] =  $input['FontP1'];
        $settings['FontP2'] =  $input['FontP2'];
        $settings['FontP3'] =  $input['FontP3'];
        $settings['FontP4'] =  $input['FontP4'];
        $settings['FontP5'] =  $input['FontP5'];
        $settings['FontP6'] =  $input['FontP6'];
        $settings['FontB1'] =  $input['FontB1'];
        $settings['FontB2'] =  $input['FontB2'];
        $settings['FontB3'] =  $input['FontB3'];
        $settings['FontB4'] =  $input['FontB4'];
        $settings['FontB5'] =  $input['FontB5'];
        $settings['FontB6'] =  $input['FontB6'];
        $settings['FontSizeP1'] =  $input['FontSizeP1'];
        $settings['FontSizeP2'] =  $input['FontSizeP2'];
        $settings['FontSizeP3'] =  $input['FontSizeP3'];
        $settings['FontSizeP4'] =  $input['FontSizeP4'];
        $settings['FontSizeP5'] =  $input['FontSizeP5'];
        $settings['FontSizeP6'] =  $input['FontSizeP6'];
        $settings['FontSizeB1'] =  $input['FontSizeB1'];
        $settings['FontSizeB2'] =  $input['FontSizeB2'];
        $settings['FontSizeB3'] =  $input['FontSizeB3'];
        $settings['FontSizeB4'] =  $input['FontSizeB4'];
        $settings['FontSizeB5'] =  $input['FontSizeB5'];
        $settings['FontSizeB6'] =  $input['FontSizeB6'];
        $settings['ChangeCase'] =  $input['ChangeCase'];
        $settings['CombineP'] =  $input['CombineP'] ?? '0';
        $settings['TemplateFile'] = ' ';
        $settings['CombineB'] =  $input['CombineB'] ?? '0';
        $settings['SingleOrder'] =  $input['SingleOrder'] ?? '0';

        if (isset($index)) {
            $array['Styles'][$key][$index] = $settings;
        } else {
            $array['Styles'][$key][] = $settings;
        }

        $a2x = new ArrayToXml($array, 'settings');

        $str = str_replace("\n", "\r\n", $a2x->toXml());
        $str = str_replace("&#xE9;", "é", $str);
        $str = str_replace("&#xFEFF;", '', $str);

        copy(self::$settings, self::$backup . 'settings_' . date("YmdHis") . '.xml');

        try {
            return file_put_contents(self::$settings, $str);
        } catch (\Exception $e) {
            return false;
        }
    }
}
