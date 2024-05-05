<?php

namespace ryunosuke\NightDragon;

class HtmlObject implements \ArrayAccess
{
    private $mapper;

    private $tagname;
    private $attributes;
    private $contents;

    public function __construct($string)
    {
        $boundary = 'x' . unique_string($string, 32, '0123456789abcdefghijklmnopqrstuvwxyz');
        $string = '<?xml encoding="UTF-8">' . php_strip($string, ['replacer' => $boundary], $this->mapper);

        libxml_clear_errors();
        $current = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($string, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOXMLDECL);
        foreach (libxml_get_errors() as $error) {
            if (!in_array($error->code, [801], true)) {
                trigger_error($error->code . ': ' . $error->message);
            }
        }
        libxml_use_internal_errors($current);

        $this->tagname = $dom->documentElement->tagName;

        $this->attributes = [];
        foreach ($dom->documentElement->attributes as $attribute) {
            $this->attributes[$attribute->name] = $attribute->childNodes->length === 0 ? true : $attribute->value;
        }

        $this->contents = '';
        foreach ($dom->documentElement->childNodes as $child) {
            $this->contents .= $dom->saveHTML($child);
        }
    }

    /**
     * 完全な html タグを返す
     *
     * @return string タグ
     */
    public function __toString()
    {
        $attribute = concat(' ', $this->attribute());
        return "<{$this->tagname()}$attribute>{$this->contents()}</{$this->tagname()}>";
    }

    public function offsetExists($offset)
    {
        return isset($this->attributes[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->attributes[$offset];
    }

    public function offsetSet($offset, $value)
    {
        $this->attributes[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->attributes[$offset]);
    }

    /**
     * タグ名を設定・取得する
     *
     * 引数を与えると設定される。
     *
     * @param string|null $tagname
     * @return string
     */
    public function tagname($tagname = null)
    {
        if ($tagname !== null) {
            $this->tagname = $tagname;
        }
        return $this->tagname;
    }

    /**
     * 属性配列を返す
     *
     * @return array 属性配列
     */
    public function attributes()
    {
        $attrstr = [];
        foreach ($this->attributes as $name => $value) {
            if (is_bool($value)) {
                $attrstr[strtr(htmlspecialchars($name, ENT_QUOTES), $this->mapper)] = $value;
            }
            else {
                $attrstr[strtr(htmlspecialchars($name, ENT_QUOTES), $this->mapper)] = strtr(htmlspecialchars($value, ENT_QUOTES), $this->mapper);
            }
        }
        return $attrstr;
    }

    /**
     * 属性文字列を返す
     *
     * @return array 属性文字列
     */
    public function attribute()
    {
        $attributes = array_filter($this->attributes(), fn($v) => $v !== false);
        return array_sprintf($attributes, fn($v, $k) => $v === true ? $k : "$k=\"$v\"", ' ');
    }

    /**
     * コンテンツ文字列を返す
     *
     * @return string コンテンツ文字列
     */
    public function contents()
    {
        return strtr($this->contents, $this->mapper);
    }
}
