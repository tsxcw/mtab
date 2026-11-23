<?php

namespace app\model;

use think\Model;

class AiModelModel extends Model
{
    protected $pk = 'id';
    protected $name = "ai_model";

    public static function getModel($id = null, $user_id = null)
    {
        try {
            $mode = self::where("status", 1)->find($id);
            if ($mode) {
                //如果模型user_id为空或者等于当前用户id，则返回模型；因为null标识为公共模型；
                if (!$mode['user_id'] || $mode['user_id'] == $user_id) {
                    return $mode;
                }
            }
        } catch (\Exception $e) {
            return false;
        }
        return false;
    }

    /**
     * 清除指定模型的"不支持思考参数"缓存
     * 管理员/用户在修改模型（api_host 或 model）后联动调用，让配置即时生效。
     * 缓存 key 由 api_host + model 决定，与模型归属用户无关，故抽成静态方法供多处复用。
     */
    public static function clearThinkUnsupportedCache($mode): void
    {
        if (!$mode || empty($mode['api_host']) || empty($mode['model'])) {
            return;
        }
        $key = 'ai_think_unsupported:' . md5(trim($mode['api_host']) . '|' . $mode['model']);
        cache($key, null);
    }
}