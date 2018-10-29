<?php
namespace Youtube\Model;

use Youtube\Model\ItagInfoData;

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

        $properties = ItagInfoData::getItagInfo()[$id];
        foreach ($properties as $key => $value) {
            $this->{$key} = $value;
        }
    }
}
