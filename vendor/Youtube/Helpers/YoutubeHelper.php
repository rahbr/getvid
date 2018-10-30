<?php
namespace Youtube\Helpers;

/**
 * Description of YoutubeHelper
 *
 * @author NB24165
 */
class YoutubeHelper
{

    public static function getFileSizeHuman($size)
    {
        if (!$size) {
            return '-';
        }

        return round($size / 1024 / 1024, 2) . ' MB';
    }
}
