<?php


namespace app\model;


use DOMDocument;
use Exception;
use think\Model;

class NoteModel extends Model
{
    protected $name = 'note';
    protected $pk = 'id';

    function getTextAttr($value): string
    {
        // 数据库字段为空时 $value 可能为 null，PHP 8.1+ 下统一按空字符串处理
        return modifyImageUrls((string)$value, request()->root(true));
    }

    function setTextAttr($htmlContent)
    {
        return removeImagesUrls($htmlContent, request()->root(true));
    }

}