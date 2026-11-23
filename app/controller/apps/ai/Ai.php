<?php

namespace app\controller\apps\ai;

use app\BaseController;
use app\model\AiModel;
use app\model\AiModelModel;
use app\model\DialogueModel;
use app\model\FileModel;
use Exception;
use Orhanerday\OpenAi\OpenAi;
use think\response\Json;

class Ai extends BaseController
{
    // 创建对话
    function createDialogues(): Json
    {
        $user = $this->getUser(true);
        $model = $this->request->post("model", '');
        $add = DialogueModel::create(["user_id" => $user->user_id, "mode_id" => $model]);
        return $this->success("ok", ['id' => $add['id'], "create_time" => $add['create_time'], 'title' => ""]);
    }

    // 删除对话
    function deleteDialogues(): Json
    {
        $user = $this->getUser(true);
        $dialogue_id = $this->request->post("dialogue_id", '');
        $find = DialogueModel::where('user_id', $user['user_id'])->where('id', $dialogue_id)->find();
        if (!$find) {
            return $this->error("对话不存在");
        }
        $messages = AiModel::where('dialogue_id', $dialogue_id)->field('images')->select()->toArray();
        $this->deleteMessageImages($messages, $user['user_id']);
        DialogueModel::destroy($dialogue_id);
        AiModel::where('dialogue_id', $dialogue_id)->delete();
        return $this->success("ok");
    }

    // 获取对话消息
    function messageList(): Json
    {
        $user = $this->getUser(true);
        $dialogue_id = $this->request->post("dialogue_id", '');
        $messages = AiModel::where('user_id', $user['user_id'])->where('dialogue_id', $dialogue_id)->order("create_time", "asc")->select();
        return $this->success("ok", $messages);
    }

    // 对话列表
    function dialogues(): Json
    {
        $user = $this->getUser(true);
        $messages = DialogueModel::where('user_id', $user['user_id']);
        $messages = $messages->order("create_time", "desc")->limit($this->request->post('offset', 0), 50)->select()->toArray();
        return $this->success("ok", ['data' => $messages]);
    }

    // 修改对话标题
    function reDialogueTitle(): Json
    {
        $user = $this->getUser(true);
        $dialogue_id = $this->request->post("dialogue_id", '');
        $title = $this->request->post("title", '');
        DialogueModel::where('user_id', $user['user_id'])->where('id', $dialogue_id)->update(['title' => $title]);
        return $this->success("ok");
    }

    //删除所有对话
    function clearDialog(): Json
    {
        $user = $this->getUser(true);
        $messages = AiModel::where('user_id', $user['user_id'])->field('images')->select()->toArray();
        $this->deleteMessageImages($messages, $user['user_id']);
        DialogueModel::where('user_id', $user['user_id'])->delete();
        AiModel::where('user_id', $user['user_id'])->delete();
        return $this->success("ok");
    }

    /**
     * 清除某个模型的"不支持思考参数"缓存（仅管理员可用）
     * 用于管理员在后台调整模型配置后，想立刻重试思考参数注入的场景
     */
    function clearThinkCache(): Json
    {
        $this->getAdmin(); // 校验管理员身份（manager == 1）
        $model_id = $this->request->post("model_id", '');
        if (!$model_id) {
            return $this->error("缺少 model_id");
        }
        $mode = AiModelModel::getModel($model_id, $this->getUser(true)['user_id']);
        if (!$mode) {
            return $this->error("模型不存在");
        }
        AiModelModel::clearThinkUnsupportedCache($mode);
        return $this->success("ok");
    }

    /**
     * 安全删除消息关联的图片文件
     */
    private function deleteMessageImages(array $messages, int $userId): void
    {
        $allowedMimeTypes = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/x-icon', 'image/svg+xml'];
        $imagesDir = realpath(joinPath(public_path(), 'images'));

        foreach ($messages as $msg) {
            if (empty($msg['images'])) {
                continue;
            }
            $imageUrls = json_decode($msg['images'], true);
            if (!is_array($imageUrls) || empty($imageUrls)) {
                continue;
            }
            foreach ($imageUrls as $url) {
                $parsedUrl = parse_url($url);
                $urlPath = $parsedUrl['path'] ?? '';
                if (empty($urlPath)) {
                    continue;
                }
                $relativePath = ltrim($urlPath, '/');
                if (empty($relativePath)) {
                    continue;
                }
                $fileRecord = FileModel::where('user_id', $userId)
                    ->where('path', $relativePath)
                    ->find();
                if (!$fileRecord) {
                    continue;
                }
                $fullPath = joinPath(public_path(), $fileRecord['path']);
                $realPath = realpath($fullPath);
                if (!$realPath || !$imagesDir || strpos($realPath . DIRECTORY_SEPARATOR, $imagesDir . DIRECTORY_SEPARATOR) !== 0) {
                    continue;
                }
                $mimeType = FileModel::detectMimeType($realPath);
                if (!in_array($mimeType, $allowedMimeTypes)) {
                    continue;
                }
                FileModel::delFile($fileRecord['path']);
            }
        }
    }

    /**
     * 从 API 地址中提取 setCustomURL() 所需的基础地址
     */
    private function extractOrigin(string $apiHost): string
    {
        $url = rtrim(trim($apiHost), '/');
        return preg_replace('#/v\d+(/.*)?$#i', '', $url);
    }

    /**
     * 构建当前访问的完整基础URL（scheme + host + port）
     */
    private function buildBaseUrl(): string
    {
        $proto = $this->request->header('X-Forwarded-Proto', '');
        if ($proto) {
            $scheme = strtolower(explode(',', $proto)[0]);
        } else {
            $scheme = $this->request->isSsl() ? 'https' : 'http';
        }

        $forwardedHost = $this->request->header('X-Forwarded-Host', '');
        if ($forwardedHost) {
            $host = trim(explode(',', $forwardedHost)[0]);
        } else {
            $host = $this->request->host();
        }

        $forwardedPort = $this->request->header('X-Forwarded-Port', '');
        if ($forwardedPort) {
            $port = (int) trim(explode(',', $forwardedPort)[0]);
        } else {
            $port = (int) $this->request->port();
        }

        if (($scheme === 'http' && $port == 80) || ($scheme === 'https' && $port == 443) || $port == 0) {
            return $scheme . '://' . $host;
        }
        return $scheme . '://' . $host . ':' . $port;
    }

    /**
     * DeepSeek 无法访问部署在 localhost/内网的图片地址时，改用 data URL 传图。
     * 仅处理当前站点 public/images 目录中的图片，外部地址保持原样。
     */
    private function inlineLocalImageUrl(string $url): string
    {
        if (stripos($url, 'data:image/') === 0) {
            return $url;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!$path) {
            return $url;
        }

        $filePath = realpath(joinPath(public_path(), ltrim($path, '/')));
        $imagesDir = realpath(joinPath(public_path(), 'images'));
        if (!$filePath || !$imagesDir || strpos($filePath . DIRECTORY_SEPARATOR, $imagesDir . DIRECTORY_SEPARATOR) !== 0) {
            return $url;
        }

        $mimeType = FileModel::detectMimeType($filePath);
        if (!in_array($mimeType, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
            return $url;
        }

        $contents = file_get_contents($filePath);
        if ($contents === false) {
            return $url;
        }
        return 'data:' . $mimeType . ';base64,' . base64_encode($contents);
    }

    /** 将历史消息中的本地图片转换为 DeepSeek 可直接读取的内容。 */
    private function inlineLocalImages(array $messages): array
    {
        foreach ($messages as &$message) {
            if (!isset($message['content']) || !is_array($message['content'])) {
                continue;
            }
            foreach ($message['content'] as &$part) {
                if (($part['type'] ?? '') !== 'image_url' || empty($part['image_url']['url'])) {
                    continue;
                }
                $part['image_url']['url'] = $this->inlineLocalImageUrl($part['image_url']['url']);
            }
            unset($part);
        }
        unset($message);
        return $messages;
    }

    /**
     * 检测厂商类型
     * @return string 'deepseek' | 'openai' | 'aliyun' | 'unknown'
     */
    private function detectProvider(string $apiHost): string
    {
        $host = strtolower($apiHost);
        // DeepSeek 官方
        if (strpos($host, 'deepseek.com') !== false) {
            return 'deepseek';
        }
        // OpenAI 官方 / Azure OpenAI
        if (strpos($host, 'openai.com') !== false || strpos($host, 'openai.azure.com') !== false) {
            return 'openai';
        }
        // 阿里云百炼 / DashScope
        if (strpos($host, 'aliyuncs.com') !== false || strpos($host, 'dashscope') !== false) {
            return 'aliyun';
        }
        return 'unknown';
    }

    /**
     * 根据厂商和思考档位，构建请求体中需要附加的思考参数
     *
     * @param string $provider  厂商类型
     * @param string $effort    归一化后的档位：none / low / high / max
     * @return array            返回 ['top_level' => [], 'extra_body' => []]
     */
    private function buildThinkParams(string $provider, string $effort): array
    {
        $topLevel = [];
        $extraBody = [];

        switch ($provider) {
            case 'deepseek':
                // DeepSeek: thinking.type 和 reasoning_effort 均可放在顶层
                // 根据官方文档，thinking 放 extra_body 是 SDK 推荐方式，但直接放顶层也兼容
                if ($effort === 'none') {
                    $topLevel['thinking'] = ['type' => 'disabled'];
                } else {
                    $topLevel['thinking'] = ['type' => 'enabled'];
                    $topLevel['reasoning_effort'] = $effort;
                }
                break;

            case 'openai':
                // OpenAI: 只支持 reasoning_effort，无 thinking 字段
                // OpenAI 没有 max 档，映射到 high
                if ($effort === 'none') {
                    $topLevel['reasoning_effort'] = 'none';
                } else {
                    $openaiEffortMap = [
                        'low'  => 'low',
                        'high' => 'medium',
                        'max'  => 'high',
                    ];
                    $topLevel['reasoning_effort'] = $openaiEffortMap[$effort] ?? 'medium';
                }
                break;

            case 'aliyun':
                // 阿里云百炼：enable_thinking + thinking_budget
                // 这些是非 OpenAI 标准参数，需通过 extra_body 传递
                if ($effort === 'none') {
                    $extraBody['enable_thinking'] = false;
                } else {
                    $extraBody['enable_thinking'] = true;
                    $budgetMap = [
                        'low'  => 4096,
                        'high' => 16384,
                        'max'  => 262144,
                    ];
                    $extraBody['thinking_budget'] = $budgetMap[$effort] ?? 16384;
                }
                break;

            default:
                // 其他厂商：不传任何思考参数
                break;
        }

        return ['top_level' => $topLevel, 'extra_body' => $extraBody];
    }

    /**
     * 判断异常是否为"不支持的参数"类错误
     */
    private function isUnsupportedParamError(string $msg): bool
    {
        $needles = [
            'unknown parameter',
            'unsupported parameter',
            'unrecognized',
            'invalid parameter',
            'extra fields not permitted',
            'extra_forbidden',
            'reasoning_effort',
            'thinking',
        ];
        $lower = strtolower($msg);
        foreach ($needles as $n) {
            if (strpos($lower, $n) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * 从参数数组中去掉思考相关字段
     */
    private function stripThinkParams(array $params): array
    {
        unset($params['reasoning_effort'], $params['thinking'], $params['extra_body']);
        return $params;
    }

    /**
     * 对话 - 流式输出 AI 回复
     * 当客户端断开连接时会自动停止请求，避免浪费 token
     */
    function Index()
    {
        set_time_limit(0);
        ignore_user_abort(false);
        ini_set('zlib.output_compression', 'Off');
        header('X-Accel-Buffering: no');
        $input = $this->request->post("input", '');
        $dialogue_id = $this->request->post("dialogue_id", '');
        $model_id = $this->request->post("model", '');
        $images = $this->request->post("images", []);

        // === 思考强度解析（归一化为 none / low / high / max 四档） ===
        $thinkLevel = $this->request->post("think_level", 0);
        $effort = 'high'; // 默认档位
        if ($thinkLevel === 'max') {
            $effort = 'max';
        } elseif ($thinkLevel === 'high') {
            $effort = 'high';
        } elseif ($thinkLevel === 'none') {
            $effort = 'none';
        } elseif (is_numeric($thinkLevel)) {
            $thinkLevel = (int)$thinkLevel;
            if ($thinkLevel > 79) {
                $effort = 'max';
            } elseif ($thinkLevel > 49) {
                $effort = 'high';
            } elseif ($thinkLevel > 24) {
                $effort = 'low';
            } else {
                $effort = 'none';
            }
        }

        $user = $this->getUser(true);
        ob_end_clean();
        header('Content-Type: text/event-stream');
        flush();

        $sseClient = $this->request->get('sse', '') === '1';
        $emit = function ($jsonStr) use ($sseClient) {
            if ($sseClient) {
                echo "data: " . $jsonStr . "\n\n";
            } else {
                echo $jsonStr . "\n";
            }
            flush();
        };

        $mode = AiModelModel::getModel($model_id, $user['user_id']);
        if (!$mode) {
            return $this->error("模型不存在");
        }

        // === 厂商检测 ===
        $apiHost = trim($mode->api_host);
        $provider = $this->detectProvider($apiHost);

        // 网关地址兼容
        $apiKey = $mode->sk;
        $model = $mode->model;
        $systemContent = $mode->system_content;
        $openAi = new OpenAi($apiKey);
        if (preg_match('#/v1(/|$)#i', $apiHost)) {
            $openAi->setBaseURL($this->extractOrigin($apiHost));
        } else {
            $fullUrl = rtrim($apiHost, '/');
            if (!preg_match('#/chat/completions$#i', $fullUrl)) {
                $fullUrl .= '/chat/completions';
            }
            $openAi->setBaseURL($fullUrl . '#');
        }
        $chatClient = function ($params) use ($openAi, &$streamHandler) {
            return $openAi->chat($params, $streamHandler);
        };

        $dialogue = DialogueModel::where('user_id', $user->user_id)->where('id', $dialogue_id)->find();
        if (!$dialogue) {
            return $this->error('对话不存在');
        }
        $historyMessages = AiModel::history($user->user_id, $dialogue_id);
        if (!is_array($images)) {
            $images = [];
        }
        $baseUrl = $this->buildBaseUrl();
        $images = array_map(function ($url) use ($baseUrl) {
            if (preg_match('#^https?://#i', $url)) {
                return $url;
            }
            return rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
        }, $images);
        AiModel::addMessage($user->user_id, 'user', $input, $dialogue_id, "", $images);
        if (!$dialogue->title || $dialogue->model_id !== $model_id) {
            if (!$dialogue->title) {
                $dialogue->title = mb_substr($input, 0, 30);
            }
            if ($dialogue->model_id !== $model_id) {
                $dialogue->mode_id = $model_id;
            }
            $dialogue->save();
        }

        if (!empty($images)) {
            $contentParts = [];
            foreach ($images as $url) {
                $contentParts[] = [
                    "type" => "image_url",
                    "image_url" => ["url" => $url]
                ];
            }
            $contentParts[] = ["type" => "text", "text" => $input];
            $historyMessages[] = ["role" => "user", "content" => $contentParts];
        } else {
            $historyMessages[] = ["role" => "user", "content" => $input];
        }

        // 官方 DeepSeek 目前仅由 Flash/V4 系列模型处理图片；纯文本模型会返回接口错误。
        if ($provider === 'deepseek' && !empty($images)) {
            if (!preg_match('/deepseek-(flash|v4)/i', $model)) {
                $emit(json_encode([
                    'code' => 0,
                    'msg' => '当前 DeepSeek 模型不支持图片，请将模型标识改为 deepseek-flash 或 deepseek-v4-pro'
                ], JSON_UNESCAPED_UNICODE));
                exit();
            }
            // DeepSeek 官方服务无法读取本机或内网地址，改为内联 data URL。
            $historyMessages = $this->inlineLocalImages($historyMessages);
        }

        if ($systemContent) {
            array_unshift($historyMessages, ["role" => "system", "content" => $systemContent]);
        }

        $str = "";
        $reasoning_content = "";
        $clientDisconnected = false;
        $lastOutputTime = time();
        $buffer = '';

        // 流式回调
        $streamHandler = function ($ch, $data) use (&$str, &$reasoning_content, &$clientDisconnected, &$lastOutputTime, &$buffer, $emit) {
            if (connection_aborted()) {
                $clientDisconnected = true;
                return 0;
            }

            if (time() - $lastOutputTime > 30) {
                echo ": heartbeat\n\n";
                flush();
                $lastOutputTime = time();
            }

            $buffer .= $data;
            $lastNewlinePos = strrpos($buffer, "\n");
            if ($lastNewlinePos === false) {
                return strlen($data);
            }
            $completePart = substr($buffer, 0, $lastNewlinePos);
            $buffer = substr($buffer, $lastNewlinePos + 1);

            $lines = explode("\n", $completePart);
            foreach ($lines as $line) {
                $trimmedLine = trim($line);
                if (!$trimmedLine) continue;

                // 同时兼容标准 SSE data: 帧和供应商返回的裸 JSON 错误。
                $jsonStr = strpos($trimmedLine, 'data:') === 0
                    ? trim(substr($trimmedLine, 5))
                    : $trimmedLine;
                if ($jsonStr === '[DONE]') continue;

                $json = json_decode($jsonStr, true);
                if (!is_array($json)) {
                    continue;
                }

                if (isset($json['error'])) {
                    $errorMsg = $json['error']['message'] ?? json_encode($json['error'], JSON_UNESCAPED_UNICODE);
                    $emit(json_encode(['code' => 0, 'msg' => $errorMsg], JSON_UNESCAPED_UNICODE));
                    continue;
                }
                if (($json['code'] ?? null) === 0 && isset($json['msg'])) {
                    $emit(json_encode(['code' => 0, 'msg' => $json['msg']], JSON_UNESCAPED_UNICODE));
                    continue;
                }

                $emit($jsonStr);
                $delta = $json['choices'][0]['delta'] ?? null;
                if (is_array($delta)) {
                    if (isset($delta['content']) && is_string($delta['content'])) {
                        $str .= $delta['content'];
                    }
                    if (isset($delta['reasoning_content']) && is_string($delta['reasoning_content'])) {
                        $reasoning_content .= $delta['reasoning_content'];
                    }
                }
                flush();
                $lastOutputTime = time();

                if (connection_aborted()) {
                    $clientDisconnected = true;
                    return 0;
                }
            }
            return strlen($data);
        };

        // === 构建思考参数 ===
        // 先查缓存：判断该 api_host + model 是否已被标记为"不支持思考参数"。
        // 缓存在 catch 兜底 1 中写入，24 小时内不再对该模型注入思考参数。
        $thinkCacheKey = 'ai_think_unsupported:' . md5($apiHost . '|' . $model);
        $thinkUnsupported = cache($thinkCacheKey);
        if ($thinkUnsupported) {
            // 已知不支持：跳过注入，直接构造空参数
            $thinkParams = ['top_level' => [], 'extra_body' => []];
        } else {
            $thinkParams = $this->buildThinkParams($provider, $effort);
        }

        try {
            $chatParams = [
                "model"    => $model,
                "messages" => $historyMessages,
                "stream"   => true,
            ];
            // 合并顶层参数
            $chatParams = array_merge($chatParams, $thinkParams['top_level']);
            // extra_body 中的参数由 Orhanerday 库序列化后合并到请求体
            if (!empty($thinkParams['extra_body'])) {
                $chatParams['extra_body'] = $thinkParams['extra_body'];
            }
            $chatClient($chatParams);
        } catch (Exception $e) {
            if ($clientDisconnected) {
                exit();
            }

            $errorMsg = $e->getMessage();

            // === 兜底 1：思考参数不兼容 → 去掉重试 ===
            if ($this->isUnsupportedParamError($errorMsg) && (!empty($thinkParams['top_level']) || !empty($thinkParams['extra_body']))) {
                $str = "";
                $reasoning_content = "";
                $buffer = '';
                $lastOutputTime = time();
                try {
                    $cleanParams = [
                        "model"    => $model,
                        "messages" => $historyMessages,
                        "stream"   => true,
                    ];
                    $chatClient($cleanParams);
                    // 去掉思考参数后重试成功才算"不支持思考参数"；此时才写缓存，避免重试其它原因失败时误伤 24 小时
                    cache($thinkCacheKey, 1, 86400);
                    goto save_message;
                } catch (Exception $e3) {
                    $errorMsg = $e3->getMessage();
                }
            }

            // === 兜底 2：多模态失败 → 纯文本重试 ===
            if (!empty($images)) {
                $retryMessages = $historyMessages;
                array_pop($retryMessages);
                $retryMessages[] = ["role" => "user", "content" => $input];
                try {
                    $str = "";
                    $reasoning_content = "";
                    $buffer = '';
                    $lastOutputTime = time();
                    $retryParams = [
                        "model"    => $model,
                        "messages" => $retryMessages,
                        "stream"   => true,
                    ];
                    $retryParams = array_merge($retryParams, $thinkParams['top_level']);
                    if (!empty($thinkParams['extra_body'])) {
                        $retryParams['extra_body'] = $thinkParams['extra_body'];
                    }
                    $chatClient($retryParams);
                } catch (Exception $e2) {
                    // 最后一次兜底：纯文本 + 去掉思考参数
                    try {
                        $str = "";
                        $reasoning_content = "";
                        $buffer = '';
                        $lastOutputTime = time();
                        $chatClient([
                            "model"    => $model,
                            "messages" => $retryMessages,
                            "stream"   => true,
                        ]);
                    } catch (Exception $e3) {
                        if (!$clientDisconnected) {
                            $emit(json_encode(['code' => 0, "msg" => $e3->getMessage()]));
                        }
                    }
                }
            } else {
                $emit(json_encode(['code' => 0, "msg" => $errorMsg]));
            }

            save_message:
            // 保持原来的写库逻辑
        }

        // 流结束后 flush 残留
        if ($buffer !== '') {
            $trimmedLine = trim($buffer);
            if ($trimmedLine && strpos($trimmedLine, 'data:') === 0) {
                $jsonStr = trim(substr($trimmedLine, 5));
                if ($jsonStr !== '' && $jsonStr !== '[DONE]') {
                    $js = json_decode($jsonStr);
                    if ($js && is_object($js) && isset($js->choices[0])) {
                        if (isset($js->choices[0]->delta->content)) {
                            $str .= $js->choices[0]->delta->content;
                        }
                        if (isset($js->choices[0]->delta->reasoning_content)) {
                            $reasoning_content .= $js->choices[0]->delta->reasoning_content;
                        }
                    }
                }
            }
            $buffer = '';
        }

        if (!$clientDisconnected && (mb_strlen($str) > 0 || mb_strlen($reasoning_content) > 0)) {
            AiModel::addMessage($user->user_id, 'assistant', $str, $dialogue_id, $reasoning_content);
        }
        flush();
        exit();
    }
}
