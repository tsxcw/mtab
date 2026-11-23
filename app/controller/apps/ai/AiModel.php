<?php

namespace app\controller\apps\ai;

use app\BaseController;
use app\model\AiModelModel;

class AiModel extends BaseController
{
    public function ModelList(): \think\response\Json
    {
        $user = $this->getUser(true);
        $list = AiModelModel::where([
            "status" => 1,
            "user_id" => null
        ])->whereOr('user_id', $user->user_id)
            ->field("user_id,id,name,tips,multimodal")
            ->where("status", 1)
            ->order("user_id")
            ->select();
        return $this->success("ok", $list);
    }

    public function ModelListManager(): \think\response\Json
    {
        $user = $this->getAdmin();
        $list = AiModelModel::where('user_id', $user->user_id)->removeWhereField("user_id")
            ->where("user_id", null)
            ->select();
        return $this->success("ok", $list);
    }

    function delMode(): \think\response\Json
    {
        $user = $this->getAdmin();
        $id = $this->request->post("id", false);
        if ($id) {
            AiModelModel::destroy($id);
            return $this->success("ok");
        } else {
            return $this->error("参数错误");
        }
    }

    function ModelSave(): \think\response\Json
    {
        $data = $this->request->post();
        $this->getAdmin();
        if (isset($data['id']) && $data['id'] > 0) {
            AiModelModel::update($data);
            // 管理员修改模型配置后，联动清除该模型的"不支持思考参数"缓存
            AiModelModel::clearThinkUnsupportedCache(AiModelModel::find($data['id']));
        } else {
            AiModelModel::create($data);
        }
        return $this->success("ok");
    }

    /**
     * 用户添加/编辑自己的私有模型（登录即可，无需管理员）
     * 私有模型 user_id 为当前用户，仅本人可见可用
     */
    public function UserModelSave(): \think\response\Json
    {
        $user = $this->getUser(true);
        $data = $this->request->only(["id", "name", "tips", "model", "api_host", "sk", "multimodal"]);
        if (empty($data["name"]) || empty($data["model"]) || empty($data["api_host"]) || empty($data["sk"])) {
            return $this->error("名称、模型标识、网关地址与密钥均为必填");
        }
        $data["multimodal"] = empty($data["multimodal"]) ? 0 : 1;
        if (!empty($data["id"])) {
            //编辑：仅允许修改自己的模型；网关/密钥/标识留空则保持原值（列表不回传敏感信息）
            $find = AiModelModel::find($data["id"]);
            if (!$find || $find["user_id"] != $user->user_id) {
                return $this->error("模型不存在或无权修改");
            }
            foreach (["model", "api_host", "sk", "tips"] as $keepField) {
                if (empty($data[$keepField])) {
                    unset($data[$keepField]);
                }
            }
            $find->save($data);
            // 用户修改私有模型（api_host/model）后联动清除"不支持思考参数"缓存
            AiModelModel::clearThinkUnsupportedCache($find);
            return $this->success("ok", ["id" => (int)$find["id"]]);
        }
        $data["user_id"] = $user->user_id;
        $data["status"] = 1;
        $mode = AiModelModel::create($data);
        return $this->success("ok", ["id" => (int)$mode["id"]]);
    }

    /**
     * 用户删除自己的私有模型（校验归属）
     */
    public function UserModelDel(): \think\response\Json
    {
        $user = $this->getUser(true);
        $id = $this->request->post("id", 0);
        $find = AiModelModel::find($id);
        if (!$find || $find["user_id"] != $user->user_id) {
            return $this->error("模型不存在或无权删除");
        }
        $find->delete();
        return $this->success("ok");
    }

    /**
     * 获取自己的私有模型完整信息（编辑回显用，校验归属）
     */
    public function UserModelDetail(): \think\response\Json
    {
        $user = $this->getUser(true);
        $id = $this->request->post("id", 0);
        $find = AiModelModel::find($id);
        if (!$find || $find["user_id"] != $user->user_id) {
            return $this->error("模型不存在或无权查看");
        }
        return $this->success("ok", [
            "id"         => (int)$find["id"],
            "name"       => $find["name"],
            "tips"       => $find["tips"],
            "model"      => $find["model"],
            "api_host"   => $find["api_host"],
            "sk"         => $find["sk"],
            "multimodal" => (int)$find["multimodal"],
        ]);
    }
}