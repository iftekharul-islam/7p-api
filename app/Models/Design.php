<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use App\Models\DesignLog;
use library\Pendant;

class Design extends Model
{
    public static $template_dir = '/media/RDrive/Pendants/PendantEngine/Templates';
    public static $template_fonts = '/media/RDrive/Pendants/font_list.txt';

    public function child_skus()
    {
        return $this->hasMany('App\Option', 'id', 'design_id');
    }

    public function sort()
    {
        return $this->belongsTo('App\DesignSort', 'id', 'design_id');
    }

    public static function check($stylename)
    {

        $chk = Design::where('StyleName', $stylename)->first();

        if (!$chk) {

            $design = new Design();
            $design->StyleName = $stylename;
            $design->user_id = auth()->user()->id;
            $design->save();

            DesignLog::add($design->id, $design->StyleName . ' Created');

            return $design;
        } else {
            return $chk;
        }
    }

    public static function updateGraphicInfo($force = 0, $option_id = null)
    {

        if ($option_id == null) {
            $options = Option::with('design')->get();
        } else {
            $options = Option::with('design')->where('id', $option_id)->get();
        }

        if ($force == 1 || (time() - filectime(self::$template_dir . '/.') < 60)) {

            $templates = Design::getTemplates();

            if ($templates != false) {

                Log::info('updateGraphicInfo: Updating Template file');

                foreach ($options as $option) {

                    if ($option->design) {
                        $design = $option->design;
                    } else {
                        $design = Design::check($option->graphic_sku);
                    }

                    if (in_array($option->graphic_sku . '.ai', $templates) || in_array($option->graphic_sku . '.AI', $templates)) {

                        if ($design->template == '0') {
                            DesignLog::add($design->id, 'Template file found');
                        }

                        $design->template = '1';
                        $design->save();
                    } else {

                        if ($design->template == '1') {
                            DesignLog::add($design->id, 'Template file missing');
                        }

                        $design->template = '0';
                        $design->save();
                    }
                }
            } else {
                Log::info('updateGraphicInfo: Failed to update Template file');
                return false;
            }
        }

        if ($force == 1 || (time() - filectime(Pendant::$settings) < 60)) {

            $stylenames = Pendant::getXmlSettings();

            if ($stylenames != false) {

                Log::info('updateGraphicInfo: Updating XML Settings');

                foreach ($options as $option) {

                    if ($option->design) {
                        $design = $option->design;
                    } else {
                        $design = Design::check($option->graphic_sku);
                    }

                    if (in_array($option->graphic_sku, $stylenames)) {

                        if ($design->xml == '0') {
                            DesignLog::add($design->id, 'Pendant XML settings found');
                        }

                        $design->xml = '1';
                        $design->save();
                    } else {

                        if ($design->xml == '1') {
                            DesignLog::add($design->id, 'Pendant XML settings missing');
                        }

                        $design->xml = '0';
                        $design->save();
                    }
                }
            } else {
                Log::info('updateGraphicInfo: Failed to Update XML Settings');
                return false;
            }
        }

        return true;
    }

    public static function findTemplate($style)
    {

        $templates = Design::getTemplates();

        if (in_array($style . '.ai', $templates)) {
            return self::$template_dir . '/' . $style . '.ai';
        } else if (in_array($style . '.AI', $templates)) {
            return self::$template_dir . '/' . $style . '.AI';
        }
    }

    public static function getTemplates()
    {

        try {
            $templates = array_diff(scandir(self::$template_dir), array('..', '.'));
        } catch (\Exception $e) {
            Log::error('getTemplates: Error Accessing File ' . $e->getMessage());
            return false;
        }

        return $templates;
    }

    public static function getFonts()
    {

        try {
            $list = file(self::$template_fonts);
        } catch (\Exception $e) {
            Log::error('Design: Font File not loaded ' . $e->getMessage());
            return false;
        }

        $result = array();

        if ($list) {

            foreach ($list as $line) {
                $font = str_replace("\r\n", '', $line);
                $result[$font] = $font;
            }

            return $result;
        } else {
            return false;
        }
    }

    public static function saveTemplate($id, $StyleName, $file)
    {

        if ($file == null) {
            return false;
        }

        $filename = $StyleName . '.' . $file->getClientOriginalExtension();

        if (move_uploaded_file($file, self::$template_dir . '/' . $filename)) {
            DesignLog::add($id, $filename . ' Uploaded');
            return true;
        }

        return false;
    }
}
