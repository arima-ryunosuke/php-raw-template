<?php

namespace ryunosuke\NightDragon;

class Template
{
    /** @var Renderer 親レンダラー */
    private $renderer;

    /** @var string テンプレートファイル名 */
    private $filename;

    /** @var array アサインされた変数 */
    private $vars = [];

    /** @var static 継承を経た場合の元テンプレートオブジェクト */
    private $original;

    /** @var static 親テンプレートオブジェクト */
    private $parent;

    /** @var string[][] 宣言されたブロック配列 */
    private $blocks = [];

    /** @var string begin で始められた現在のブロック名 */
    private $currentBlock;

    public function __construct(Renderer $renderer, string $filename)
    {
        $this->renderer = $renderer;
        $this->filename = $filename;
        $this->original = $this;
    }

    /**
     * 変数を指定してレンダリング
     *
     * @param array $vars 変数配列
     * @return string レンダリング結果
     */
    public function render(array $vars = []): string
    {
        $this->vars = $vars;

        $compiled = $this->renderer->compile($this->filename, $vars);
        $contents = (function () {
            ob_start();
            extract(func_get_arg(1));
            require func_get_arg(0);
            return ob_get_clean();
        })($compiled, $vars);

        if ($this->parent) {
            return $this->parent->render($vars);
        }

        return $contents;
    }

    /**
     * テンプレートの継承を宣言する
     *
     * @param string $filename 親テンプレート名
     * @return static 継承したテンプレートオブジェクト
     */
    public function extend(string $filename)
    {
        assert($this->parent === null, "this template is already extended.");

        $this->parent = new static($this->renderer, $this->resolvePath($filename));
        $this->parent->original = $this->original;
        $this->original = null;
        return $this->parent;
    }

    /**
     * ブロックを開始する
     *
     * @param string $name ブロック名
     */
    public function begin(string $name)
    {
        // 継承しないとブロック機能を使えない…ようにしようとしたけど止めた。継承を使わなくてもブロックに名前をつける意義はある
        // assert($this->parent !== null, "this template is not extended.");
        assert(!array_key_exists($name, $this->blocks), "block '$name' is already defined.");
        assert($this->currentBlock !== $name, "block '$name' is nested.");

        $this->currentBlock = $name;
        ob_start();
    }

    /**
     * ブロックを終了する
     */
    public function end()
    {
        assert($this->currentBlock !== null, "block is not begun.");

        $name = $this->currentBlock;
        $this->currentBlock = null;
        $this->blocks[$name][] = ob_get_clean();

        if ($this->original) {
            $recho = function ($iterator) use (&$recho) {
                foreach ($iterator as $block) {
                    if ($block instanceof \Closure) {
                        $recho($block());
                    }
                    else {
                        echo $block;
                    }
                }
            };
            $recho($this->original->blocks[$name]);
        }
    }

    /**
     * コンテンツ指定の begin ~ end
     *
     * 下記は同じ意味となる。
     *
     * - `<?php $this->block('block', 'hoge') ?>`
     * - `<?php $this->begin('block') ?>hoge<?php $this->end() ?>`
     *
     * コンテンツは省略すると空文字になるので「親がコンテンツを持たない場合」には単にこのメソッドを呼ぶだけで良い。
     *
     * @param string $name ブロック名
     * @param string $contents ブロックのコンテンツ
     */
    public function block(string $name, string $contents = '')
    {
        $this->begin($name);
        echo $contents;
        $this->end();
    }

    /**
     * 親ブロックの内容を出力する
     */
    public function parent()
    {
        assert($this->parent !== null, "this template is not extended.");
        assert($this->currentBlock !== null, "block is not begun.");

        $name = $this->currentBlock;
        $this->blocks[$this->currentBlock][] = ob_get_clean();
        $this->blocks[$this->currentBlock][] = function () use ($name) { return $this->parent->blocks[$name]; };
        ob_start();
    }

    /**
     * 指定ファイルを取り込む
     *
     * ファイルはテンプレートファイルとしてレンダリングして取り込まれる。
     *
     * @param string $filename 読み込むファイル名
     * @param array $vars 変数配列
     */
    public function include(string $filename, array $vars = [])
    {
        $template = new static($this->renderer, $this->resolvePath($filename));
        echo $template->render($vars + $this->vars);
    }

    /**
     * 指定ファイルをただのファイルとして読み込む
     *
     * ファイルはただのテキストファイルとして取り込まれる。
     *
     * @param string $filename 読み込むファイル名
     */
    public function content(string $filename)
    {
        $filename = $this->renderer->resolvePath($this->resolvePath($filename));
        echo file_get_contents($filename);
    }

    /**
     * 指定されたパスを解決する
     *
     * 絶対パスはそのまま、相対パスは自身のディレクトリが基底となる。
     * compileDir やストリームラッパーなどの状態によらず、 __DIR__ などの定数は使用可能（適宜読み替えられる）。
     *
     * @param string $filename
     * @return string パス名
     */
    private function resolvePath(string $filename): string
    {
        if (path_is_absolute($filename)) {
            return $filename;
        }
        return dirname($this->filename) . "/$filename";
    }
}
