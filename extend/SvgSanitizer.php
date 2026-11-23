<?php

/**
 * SVG 净化器
 *
 * 由于站点无法通过响应头限制 SVG 的访问方式（不能修改 nginx），
 * 只能在文件落盘前把可执行内容全部剥离，让文件即便被直接打开
 * （同源顶层文档）也只会作为静态图形渲染。
 */
class SvgSanitizer
{
    /**
     * 允许保留的 SVG 元素白名单（小写比较）
     * 不在列表中的元素会被整体删除，包括 script / foreignObject / iframe /
     * object / embed / animate / set / handler 等可执行或可注入的元素
     */
    const ALLOWED_ELEMENTS = [
        'svg', 'g', 'defs', 'desc', 'title', 'metadata', 'symbol', 'use', 'switch', 'a',
        'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'text', 'tspan', 'textpath',
        'image',
        'marker', 'pattern', 'mask', 'clippath', 'lineargradient', 'radialgradient', 'stop',
        'style',
        'filter', 'feblend', 'fecolormatrix', 'fecomponenttransfer', 'fecomposite',
        'feconvolvematrix', 'fediffuselighting', 'fedisplacementmap', 'fedistantlight',
        'fedropshadow', 'feflood', 'fefunca', 'fefuncb', 'fefuncg', 'fefuncr',
        'fegaussianblur', 'feimage', 'femerge', 'femergenode', 'femorphology', 'feoffset',
        'fepointlight', 'fespecularlighting', 'fespotlight', 'fetile', 'feturbulence',
    ];

    /**
     * 允许保留的带前缀属性（其余带前缀属性如 ev:event 一律删除）
     */
    const ALLOWED_ATTR_PREFIXES = ['xlink', 'xml', 'xmlns'];

    /**
     * 净化指定 SVG 文件（原地覆盖）
     * @param string $path 文件绝对路径
     * @return bool 净化成功并已写回返回 true；任何解析或校验失败返回 false
     */
    public static function sanitizeFile(string $path): bool
    {
        if (!is_file($path)) {
            return false;
        }
        // 原始文件内容，用于解析
        $content = file_get_contents($path);
        if ($content === false) {
            return false;
        }
        // 净化后的 SVG 文本，失败为 null
        $clean = self::sanitizeString($content);
        if ($clean === null) {
            return false;
        }
        // 写回原文件，使用排它锁避免并发读写交叉
        return file_put_contents($path, $clean, LOCK_EX) !== false;
    }

    /**
     * 净化一段 SVG 文本
     * @param string $content 原始 SVG 文本
     * @return string|null 净化后的 SVG 文本；不合法或存在无法处理的风险时返回 null
     */
    public static function sanitizeString(string $content): ?string
    {
        // 去掉 UTF-8 BOM，否则 BOM 会被当成 XML 声明之前的多余内容导致解析失败
        if (strncmp($content, "\xEF\xBB\xBF", 3) === 0) {
            $content = substr($content, 3);
        }
        // 拒绝实体定义：XXE 外部实体与实体膨胀（billion laughs）都靠 <!ENTITY 实现
        if (preg_match('/<!ENTITY/i', $content)) {
            return null;
        }
        // 允许 iconfont 等导出工具生成的标准 svg DOCTYPE（指向 W3C 的 DTD）；
        // 但 DOCTYPE 不能带内部子集 [...]，也不能是 svg 以外的根类型
        if (preg_match('/<!DOCTYPE/i', $content) && !preg_match('/<!DOCTYPE\s+svg\b[^>\[]*>/i', $content)) {
            return null;
        }

        // 上一次的 libxml 错误开关，用于解析后恢复现场
        $prevUseErrors = libxml_use_internal_errors(true);
        // SVG 文档对象
        $doc = new DOMDocument();
        // LIBXML_NONET 禁止解析阶段发起任何网络请求
        $loaded = $doc->loadXML($content, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prevUseErrors);
        if (!$loaded) {
            return null;
        }

        // 根节点，必须是 svg
        $root = $doc->documentElement;
        if (!$root || strtolower($root->localName) !== 'svg') {
            return null;
        }
        // 根节点必须属于 SVG 命名空间（无命名空间时由下方补全）
        if ($root->namespaceURI !== null && $root->namespaceURI !== '' && $root->namespaceURI !== 'http://www.w3.org/2000/svg') {
            return null;
        }
        // 补齐默认命名空间，避免浏览器把根节点当成未知 XML 而拒绝渲染
        if ($root->namespaceURI !== 'http://www.w3.org/2000/svg' && !$root->hasAttribute('xmlns')) {
            $root->setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        }

        // 递归清理元素、属性与注释
        self::cleanNode($root);
        // 单独处理 <style> 内的 CSS 文本，剔除 @import、远程 url() 等
        self::cleanStyleNodes($doc);

        // 只序列化根节点，得到不带 XML 声明的纯 SVG 文本
        $out = $doc->saveXML($root);
        return $out === false ? null : $out;
    }

    /**
     * 递归清理节点树：删除不在白名单内的元素、注释与处理指令，并清理属性
     * @param DOMNode $node 当前节点
     * @return void
     */
    private static function cleanNode(DOMNode $node): void
    {
        // 待删除的子节点集合，先收集再删除，避免边遍历边删除导致节点漏检
        $remove = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                // 不在白名单内的元素整体删除，其子节点一并丢弃
                if (!in_array(strtolower($child->localName), self::ALLOWED_ELEMENTS, true)) {
                    $remove[] = $child;
                    continue;
                }
                self::cleanAttributes($child);
                self::cleanNode($child);
            } elseif ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction) {
                // 注释与处理指令没有渲染价值，统一删除，避免被当作条件编译等技巧利用
                $remove[] = $child;
            }
        }
        foreach ($remove as $child) {
            // 节点仍挂在当前父节点下时才删除，防止重复删除告警
            if ($child->parentNode === $node) {
                $node->removeChild($child);
            }
        }
    }

    /**
     * 清理元素属性：删除事件属性、危险协议、外部引用与非法前缀属性
     * @param DOMElement $el 待清理的元素
     * @return void
     */
    private static function cleanAttributes(DOMElement $el): void
    {
        // 当前元素的标签名（小写），用于区分 <a> 与其它元素的链接规则
        $tag = strtolower($el->localName);
        // 待删除的属性节点集合（用节点本身，带命名空间的属性也能可靠删除）
        $remove = [];
        // 待改写的属性名 => 新值，循环结束后统一写回，避免边遍历边修改
        $replace = [];
        foreach ($el->attributes as $attr) {
            // 属性名（小写，含前缀）
            $name = strtolower($attr->nodeName);
            // 属性原始值
            $value = (string)$attr->nodeValue;
            // 去掉空白与控制字符后的值，用于协议判断，防止 java\tscript: 这类绕写
            $probe = strtolower(preg_replace('/[\x00-\x20\x7f]+/', '', $value));

            // 只保留 xlink/xml/xmlns 前缀，其它带前缀属性（如 ev:event）一律删除
            if (strpos($name, ':') !== false) {
                // 属性命名空间前缀
                $prefix = substr($name, 0, strpos($name, ':'));
                if (!in_array($prefix, self::ALLOWED_ATTR_PREFIXES, true)) {
                    $remove[] = $attr;
                    continue;
                }
            }

            // xml:base 会改写文档内相对地址的解析基准，直接删除
            if ($name === 'xml:base') {
                $remove[] = $attr;
                continue;
            }

            // 所有 on* 属性都是事件处理器（onload/onclick/onbegin...），直接删除
            if (strncmp($name, 'on', 2) === 0) {
                $remove[] = $attr;
                continue;
            }

            // 危险协议与旧 IE 表达式入口
            if (strpos($probe, 'javascript:') !== false
                || strpos($probe, 'vbscript:') !== false
                || strpos($probe, 'data:text/html') !== false
                || strpos($probe, 'expression(') !== false) {
                $remove[] = $attr;
                continue;
            }

            // href / xlink:href 限制为文档内锚点或内嵌图片数据
            if ($name === 'href' || $name === 'xlink:href') {
                if (!self::isSafeUrl($value, $tag)) {
                    $remove[] = $attr;
                }
                continue;
            }

            // style 属性中的 url() 只允许文档内引用
            if ($name === 'style') {
                $replace[$attr->nodeName] = self::cleanCss($value);
            }
        }
        foreach ($replace as $name => $value) {
            $el->setAttribute($name, $value);
        }
        foreach ($remove as $attr) {
            // 属性仍属于当前元素时才删除，避免重复删除告警
            if ($attr->ownerElement === $el) {
                $el->removeAttributeNode($attr);
            }
        }
    }

    /**
     * 清理所有 <style> 元素内的 CSS 文本
     * @param DOMDocument $doc SVG 文档
     * @return void
     */
    private static function cleanStyleNodes(DOMDocument $doc): void
    {
        // 收集所有 style 元素（NodeList 是实时的，先转数组再处理）
        $nodes = [];
        foreach ($doc->getElementsByTagName('style') as $style) {
            $nodes[] = $style;
        }
        foreach ($nodes as $style) {
            // style 元素内的 CSS 源码
            $css = $style->textContent;
            $style->textContent = self::cleanCss($css);
        }
    }

    /**
     * 清理 CSS 文本：去掉 @import、expression()、javascript: 与非文档内 url()
     * @param string $css 原始 CSS
     * @return string 清理后的 CSS
     */
    private static function cleanCss(string $css): string
    {
        // 去掉 @import，防止引入远程样式表
        $css = preg_replace('/@import[^;]*;?/i', '', $css);
        // 去掉旧 IE 的 expression() 与 behavior 绑定
        $css = preg_replace('/expression\s*\([^)]*\)/i', '', $css);
        $css = preg_replace('/behavior\s*:[^;]*;?/i', '', $css);
        $css = preg_replace('/-moz-binding\s*:[^;]*;?/i', '', $css);
        // 去掉直接写在 CSS 里的 javascript: 协议
        $css = preg_replace('/javascript\s*:/i', '', $css);
        // url() 只保留文档内引用 url(#id)，其余地址替换为 none
        return preg_replace_callback('/url\(\s*([\'"]?)(.*?)\1\s*\)/is', function ($m) {
            // 去掉空白与控制字符后的目标地址，防止用换行拆开协议名
            $target = preg_replace('/[\x00-\x20\x7f]+/', '', $m[2]);
            return strncmp($target, '#', 1) === 0 ? $m[0] : 'none';
        }, $css);
    }

    /**
     * 判断 href 值是否安全
     * @param string $value 属性原始值
     * @param string $tag 所属元素标签名（小写）
     * @return bool 安全返回 true
     */
    private static function isSafeUrl(string $value, string $tag): bool
    {
        // 去掉空白与控制字符后的地址，防止用换行/制表符拆开协议名
        $url = strtolower(preg_replace('/[\x00-\x20\x7f]+/', '', trim($value)));
        if ($url === '') {
            return false;
        }
        // 文档内引用，如 url(#gradient)、use href="#icon"
        if (strncmp($url, '#', 1) === 0) {
            return true;
        }
        // <a> 允许普通外链跳转（点击导航，不会执行脚本）
        if ($tag === 'a') {
            return strncmp($url, 'http://', 7) === 0
                || strncmp($url, 'https://', 8) === 0
                || strncmp($url, 'mailto:', 7) === 0;
        }
        // 其余元素（image / use / feImage）只允许内嵌图片数据
        return preg_match('#^data:image/(png|jpeg|jpg|gif|webp);base64,#', $url) === 1;
    }
}
