<?php

namespace app\model;

use think\Model;

/**
 *
 */
class AiModel extends Model
{
    protected $name = "ai";
    protected $pk = "id";

    /**
     * @param int $user_id 用户id
     * @param string $role 角色
     * @param string $message 消息
     * @param int $dialogue_id 对话id
     * @param string $reasoning_content 深度思考内容
     * @param array $images 图片URL数组
     * @return AiModel|Model
     * @noinspection PhpMissingParamTypeInspection
     */
    static function addMessage($user_id, $role, $message, $dialogue_id, $reasoning_content = "", $images = [])
    {
        if (!$role) {
            $role = 'user';
        }
        $data = ['user_id' => $user_id, 'message' => $message, 'role' => $role, 'dialogue_id' => $dialogue_id, 'reasoning_content' => $reasoning_content];
        if (!empty($images)) {
            $data['images'] = json_encode($images);
        }
        return self::create($data);
    }

    /**
     * 获取历史消息，支持多模态格式
     * 当消息包含图片时，content 为数组格式（OpenAI 多模态格式）
     */
    static function history($user_id, $dialogue_id): array
    {
        $list = self::where('user_id', $user_id)->where('dialogue_id', $dialogue_id)->field("role,message as content,images")->select()->toArray();

        // 第一步：过滤掉空内容的 assistant 消息
        $filtered = [];
        foreach ($list as $item) {
            if ($item['role'] === 'assistant' && empty(trim($item['content'] ?? ''))) {
                continue; // 跳过空 assistant 消息
            }
            $filtered[] = $item;
        }

        // 第二步：构建消息列表，确保 user/assistant 严格交替
        $content = [];
        $lastRole = null;
        foreach ($filtered as $item) {
            $role = $item['role'];

            // 如果当前角色与上一条相同，跳过（避免连续 user 或连续 assistant）
            if ($role === $lastRole) {
                continue;
            }

            // 如果是 user 且上一条也是 user，需要补一个空 assistant 占位
            if ($role === 'user' && $lastRole === 'user') {
                $content[] = ["role" => "assistant", "content" => ""];
            }

            // 构建消息内容
            $msg = ["role" => $role];

            // 如果消息包含图片，构建多模态 content 数组
            if (!empty($item['images'])) {
                $imageUrls = json_decode($item['images'], true);
                if (is_array($imageUrls) && !empty($imageUrls)) {
                    $contentParts = [];
                    foreach ($imageUrls as $url) {
                        $contentParts[] = [
                            "type" => "image_url",
                            "image_url" => ["url" => $url]
                        ];
                    }
                    $contentParts[] = [
                        "type" => "text",
                        "text" => $item['content']
                    ];
                    $msg['content'] = $contentParts;
                } else {
                    $msg['content'] = $item['content'];
                }
            } else {
                $msg['content'] = $item['content'];
            }

            $content[] = $msg;
            $lastRole = $role;
        }

        // 确保第一条是 user，最后一条是 assistant
        if (!empty($content)) {
            if ($content[0]['role'] !== 'user') {
                array_shift($content);
            }
            if (!empty($content) && end($content)['role'] !== 'assistant') {
                $content[] = ["role" => "assistant", "content" => ""];
            }
        }

        return $content;
    }

}