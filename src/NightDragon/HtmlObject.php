<?php

namespace ryunosuke\NightDragon;

class HtmlObject implements \ArrayAccess, \Stringable
{
    private array $mapper = [];

    private string $tagname;
    private array  $attributes;
    private string $contents;

    public function __construct(string $string)
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

    public function __toString(): string
    {
        $attribute = concat(' ', $this->attribute());
        return "<{$this->tagname()}$attribute>{$this->contents()}</{$this->tagname()}>";
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->attributes[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->attributes[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }

    /**
     * タグ名を設定・取得する
     *
     * 引数を与えると設定される。
     */
    public function tagname(?string $tagname = null): string
    {
        if ($tagname !== null) {
            $this->tagname = $tagname;
        }
        return $this->tagname;
    }

    public function attributes(): array
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

    public function attribute(): string
    {
        $attributes = array_filter($this->attributes(), fn($v) => $v !== false);
        return array_sprintf($attributes, fn($v, $k) => $v === true ? $k : "$k=\"$v\"", ' ');
    }

    public function contents(): string
    {
        return strtr($this->contents, $this->mapper);
    }
}
