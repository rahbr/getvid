<?php
namespace Youtube\Models;

use Youtube\Models\ItagInfoData;

/**
 * Description of ITagInfoModel
 */
class ItagInfoModel
{

    /** @var int $id */
    public $id;

    /** @var string $format */
    public $format;

    /** @var boolean $withVideo */
    public $withVideo;

    /** @var boolean $withAudio */
    public $withAudio;

    public function __construct($id = null)
    {
        $this->id = $id;
        if (!isset(ItagInfoData::getItagInfo()[$id])) {
            // log error ITag not found!
            return;
        }
        $properties = ItagInfoData::getItagInfo()[$id];
        foreach ($properties as $key => $value) {
            $this->{$key} = $value;
        }
    }
}
