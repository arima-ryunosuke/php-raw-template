<?php

namespace ryunosuke\NightDragon;

class Renderer
{
    const META_COMMENT              = '# meta template data';
    const VARIABLE_COMMENT          = '// using variables:';
    const MODIFIER_FUNCTION_COMMENT = '// using modifier functions:';
    const ACCESS_KEY_COMMENT        = '// using array keys:';

    const DEFAULT_PROTOCOL = 'RewriteWrapper';

    /** @var bool デバッグモード */
    private $debug;

    /** @var bool エラーハンドリングを内部で行うか */
    private $errorHandling;

    /** @var string ストリームプロトコル名 */
    private $wrapperProtocol;

    /** @var Template テンプレートクラス名 */
    private $templateClass;

    /** @var string コンパイル済ディレクトリ */
    private $compileDir;

    /** @var array テンプレート書き換えオプション */
    private $gatherOptions = [];

    /** @var array レンダリングオプション */
    private $renderOptions = [];

    /** @var array ファイルシステムのキャッシュ */
    private $stats = [];

    /** @var array アサインされたグローバル変数（テンプレート変数とは別） */
    private $globalVars = [];

    /** @var array レンダリングに使用されたすべての変数 */
    private $assignedVars = [];

    /** @var array レンダリング中に出くわした修飾子・配列キーを保持する配列 */
    private $consts = ['modifier' => [], 'accessor' => []];

    /**
     * ENT_QUOTES で htmlspecialchars するだけのラッパー関数
     *
     * デフォルトオプションとして html を指定したいがための実装だけど、ショートタグを書き換える関係上可変引数に対応している。
     *
     * @param string|null ...$strings 文字列
     * @return string html 文字列
     */
    public static function html(...$strings): string
    {
        $result = '';
        foreach ($strings as $string) {
            $result .= htmlspecialchars($string, ENT_QUOTES);
        }
        return $result;
    }

    /**
     * 配列アクセス可能なら `$value[$key]`, そうでないなら `$value->$key` アクセスする
     *
     * ArrayAccess なオブジェクトは [$key] を優先する。
     *
     * @param array|object $value キーアクセス可能ななにか
     * @param string ...$keys キー
     * @return mixed キーの値
     */
    public static function access($value, string ...$keys)
    {
        foreach ($keys as $key) {
            $value = is_arrayable($value) ? $value[$key] : $value->$key;
        }
        return $value;
    }

    /**
     * html 的文字列から空白をよしなに除去する
     *
     * DomDocument を利用してるので、おかしな部分 html を食わせると意図しない結果になる可能性がある。
     * （一応 notice が出るようにはしてある）。
     *
     * @param string $html タグコンテンツ
     * @param array $attrs タグの属性配列
     * @return string 空白除去された文字列
     */
    public static function strip($html, $attrs = [])
    {
        $IDENTIFIER = 'nightdragonboundary';
        while (strpos($html, $IDENTIFIER) !== false) {
            $IDENTIFIER .= rand(1000, 9999);
        }

        $wrapperTag = "{$IDENTIFIER}wrappertag";
        $phpTag = "{$IDENTIFIER}phptag";

        $mapper = [
            "<$wrapperTag>"  => '',
            "</$wrapperTag>" => '',
        ];

        // php タグは特殊すぎるので退避する（=> とか & とか <=> とか html との相性が悪すぎる）
        $html = preg_replace_callback('#<\?.*?\?>#ums', function ($m) use (&$mapper, $phpTag) {
            $tag = $phpTag . count($mapper);
            $mapper["<$tag>"] = $m[0];
            $mapper["</$tag>"] = '';
            return "<$tag/>";
        }, $html);
        $html = "<$wrapperTag>$html</$wrapperTag>"; // documentElement がないと <p> が自動付与されてしまう
        $html = '<?xml encoding="UTF-8">' . $html;  // xml 宣言がないとマルチバイト文字が html エンティティになってしまう

        libxml_clear_errors();
        $current = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOXMLDECL);
        if (!($attrs['noerror'] ?? false)) {
            foreach (libxml_get_errors() as $error) {
                if ($error->code !== 801) {
                    trigger_error($error->message);
                }
            }
        }
        libxml_use_internal_errors($current);

        $traverse = function (\DOMNode $node) use (&$traverse) {
            foreach ($node->childNodes ?? [] as $child) {
                $traverse($child);
                if ($child instanceof \DOMText && !in_array($node->nodeName, ['pre', 'textarea', 'script'])) {
                    $child->textContent = preg_replace('#\s+#u', ' ', trim($child->textContent));
                }
            }
        };
        $traverse($dom->documentElement);

        return strtr($dom->saveHTML($dom->documentElement), $mapper);
    }

    public function __construct(array $options)
    {
        // オプションを弄ったら README も書き換えること
        $debug = $options['debug'] ?? false;
        $options += [
            // デバッグ系
            'debug'              => $debug,
            'errorHandling'      => $debug,
            'gatherVariable'     => $debug,
            'gatherModifier'     => $debug,
            'gatherAccessor'     => $debug,
            'constFilename'      => null,
            'typeMapping'        => [],
            'specialVariable'    => [],
            // インジェクション系
            'wrapperProtocol'    => Renderer::DEFAULT_PROTOCOL,
            'templateClass'      => '\\' . Template::class,
            // ディレクトリ系
            'compileDir'         => null,
            // コンパイルオプション系
            'customTagHandler'   => [
                'strip' => '\\' . Renderer::class . '::strip',
            ],
            'compatibleShortTag' => false,
            'defaultNamespace'   => '\\',
            'defaultClass'       => '',
            'defaultFilter'      => '\\' . Renderer::class . '::html',
            'defaultGetter'      => '\\' . Renderer::class . '::access',
            'defaultCloser'      => "\n",
            'nofilter'           => '', // for compatible. In the future the default will be "@"
            'varModifier'        => '|', // for compatible. In the future the default will be ['|', '&']
            'varReceiver'        => '$_',
            'varAccessor'        => '.',
            'varExpander'        => '', // for compatible. In the future the default will be "`"
        ];

        $this->debug = (bool) $options['debug'];
        $this->errorHandling = (bool) $options['errorHandling'];
        $this->wrapperProtocol = (string) $options['wrapperProtocol'];
        $this->templateClass = '\\' . ltrim($options['templateClass'], '\\');
        $this->compileDir = (string) $options['compileDir'];
        $this->gatherOptions = [
            'gatherVariable'  => (bool) $options['gatherVariable'],
            'gatherModifier'  => (bool) $options['gatherModifier'],
            'gatherAccessor'  => (bool) $options['gatherAccessor'],
            'constFilename'   => (string) $options['constFilename'],
            'typeMapping'     => (array) $options['typeMapping'],
            'specialVariable' => (array) $options['specialVariable'],
        ];
        $this->renderOptions = [
            'customTagHandler'   => (array) $options['customTagHandler'],
            'compatibleShortTag' => (bool) $options['compatibleShortTag'],
            'defaultNamespace'   => (array) $options['defaultNamespace'],
            'defaultClass'       => (array) $options['defaultClass'],
            'defaultFilter'      => (string) $options['defaultFilter'],
            'defaultGetter'      => (string) $options['defaultGetter'],
            'defaultCloser'      => (string) $options['defaultCloser'],
            'varModifier'        => ((array) $options['varModifier']) + [1 => ''],
            'varReceiver'        => (string) $options['varReceiver'],
            'varAccessor'        => (string) $options['varAccessor'],
            'varExpander'        => (string) $options['varExpander'],
            'nofilter'           => (string) $options['nofilter'],
        ];

        if (!strlen($this->compileDir)) {
            $this->compileDir = sys_get_temp_dir() . '/' . $this->wrapperProtocol;
        }
        mkdir_p($this->compileDir);
        // Windows では {C:\compile}\C;\path\to\file, Linux では {/compile}/path/to/file となり、DS の有無が異なる
        // （Windows だと DS も含めないとフルパスにならない、Linux だと DS を含めるとフルパスにならない）
        $this->compileDir = realpath($this->compileDir) . (DIRECTORY_SEPARATOR === '\\' ? '\\' : '');

        RewriteWrapper::register($this->wrapperProtocol);
    }

    public function __destruct()
    {
        if ($this->debug && strlen($this->gatherOptions['constFilename'])) {
            $this->outputConstFile($this->gatherOptions['constFilename'], $this->consts);
        }
    }

    public function resolvePath($filename)
    {
        // compile ディレクトリ内で __DIR__ を使うとその絶対パスになっているので元のパスに読み替える
        $filename = strtr(str_lchop($filename, $this->compileDir), [';' => ':']);

        return $filename;
    }

    /**
     * テンプレートファイルのコンパイル
     *
     * ファイル名を与えるとコンパイルして読み込むべきファイル名を返す。
     *
     * @param string $filename テンプレートファイル名
     * @param array $vars 変数配列
     * @return string 読み込むべきファイル名
     */
    public function compile(string $filename, array $vars): string
    {
        if (isset($this->stats[$filename])) {
            return $this->stats[$filename];
        }

        $filename = $this->resolvePath($filename);
        if (!is_readable($filename)) {
            throw new \InvalidArgumentException("$filename is not readable.");
        }

        // デバッグ中はメタ情報埋め込みのためテンプレートファイル自体に手を出す
        if ($this->debug && is_writable($filename)) {
            $ropt = $this->renderOptions;
            $content = file_get_contents($filename);
            $source = new Source($content, $ropt['compatibleShortTag'] ? Source::SHORT_TAG_REPLACE : Source::SHORT_TAG_NOTHING);

            $E = function ($v) { return var_export($v, true); };
            $meta = [];
            if ($this->gatherOptions['gatherVariable']) {
                $variables = $this->gatherVariable($source, $ropt['varReceiver'], $vars);
                $meta[] = self::VARIABLE_COMMENT;
                $meta[] = array_sprintf($variables, '/** @var %s %s */', "\n");
            }
            if ($this->gatherOptions['gatherModifier']) {
                $modifiers = $this->gatherModifier($source, $ropt['varModifier'], $ropt['defaultNamespace'], $ropt['defaultClass']);
                if ($this->gatherOptions['constFilename']) {
                    $this->consts['modifier'] += $modifiers;
                }
                else {
                    $meta[] = self::MODIFIER_FUNCTION_COMMENT;
                    $meta[] = array_sprintf($modifiers, function ($v, $k) use ($E) {
                        return "if (false) {function $k(...\$args){define({$E($k)}, $v(...[]));return $v(...\$args);}}";
                    }, "\n");
                }
            }
            if ($this->gatherOptions['gatherAccessor']) {
                $accessors = $this->gatherAccessor($source, $ropt['varAccessor']);
                if ($this->gatherOptions['constFilename']) {
                    $this->consts['accessor'] += $accessors;
                }
                else {
                    $meta[] = self::ACCESS_KEY_COMMENT;
                    $meta[] = array_sprintf($accessors, function ($v, $k) use ($E) {
                        return "true or define({$E($v)}, {$E($v)});";
                    }, "\n");
                }
            }

            if ($meta) {
                $formatter = "@formatter";
                $newcontent = (string) $source->replace([
                    T_OPEN_TAG,
                    function (Token $token) { return $token->id === T_COMMENT && trim($token->token) === self::META_COMMENT; },
                    Source::MATCH_MANY,
                    T_CLOSE_TAG,
                ], "<?php\n" . self::META_COMMENT . "\n// $formatter:off\n" . implode("\n", $meta) . "\n// $formatter:on\n?>\n");

                // phpstorm が「変更された」と感知して ctrl+z が効かなくなるので書き換えられた場合のみ保存する
                if ($content !== $newcontent) {
                    file_put_contents($filename, $newcontent);
                }
            }
        }

        $fileid = $this->compileDir . strtr($filename, [':' => ';']);
        if ($this->debug || !file_exists($fileid)) {
            $path = "$this->wrapperProtocol://dummy/$filename?" . http_build_query($this->renderOptions);
            file_set_contents($fileid, file_get_contents($path));
        }

        if ($this->debug) {
            $this->assignedVars += $vars;
        }

        return $this->stats[$filename] = $fileid;
    }

    private function detectType($var): string
    {
        $map = function ($type) use (&$map) {
            if (is_array($type)) {
                return array_map($map, $type);
            }
            $result = $this->gatherOptions['typeMapping'][$type] ?? $type;
            if (is_array($result)) {
                return implode('|', $result);
            }
            return $result;
        };

        // 配列は array じゃなくて Type[] にできる可能性がある
        if (is_array($var) && count($var) > 0) {
            $type = $this->detectType(array_shift($var));
            // 型がバラバラでも抽象型の可能性があるので反変を取る
            foreach ($var as $v) {
                $next = $this->detectType($v);
                if ($next === $type || is_subclass_of($next, $type)) {
                    continue;
                }
                elseif (is_subclass_of($type, $next)) {
                    $type = $next;
                    continue;
                }
                else {
                    $type = null;
                    break;
                }
            }
            if ($type) {
                return array_sprintf(explode('|', $type), '%s[]', '|');
            }
        }

        // 無名クラスはファイル名なども含まれて一意じゃないので親クラス・実装インターフェースで名前引き
        if (is_object($var)) {
            $ref = new \ReflectionClass($var);
            if ($ref->isAnonymous()) {
                if ($pc = $ref->getParentClass()) {
                    $is = array_diff($ref->getInterfaceNames(), $pc->getInterfaceNames());
                    return array_sprintf(array_merge([$pc->name], $map($is)), '\\%s', '|');
                }
                if ($is = $ref->getInterfaceNames()) {
                    return array_sprintf($map($is), '\\%s', '|');
                }
                // 本当に匿名ならどうしようもないので object
                return $map('object');
            }
            return $map('\\' . get_class($var));
        }

        return $map(gettype($var));
    }

    private function gatherVariable(Source $source, string $receiver, array $vars): array
    {
        $result = [];
        $result['$this'] = $this->templateClass;
        $result[$receiver] = "mixed";
        foreach ($source->match([T_VARIABLE]) as $tokens) {
            $code = (string) $tokens->shift();
            $vname = substr($code, 1);
            if (array_key_exists($vname, $vars)) {
                $result[$code] = $this->detectType($vars[$vname]);
            }
        }
        foreach ($this->gatherOptions['specialVariable'] as $name => $type) {
            if (array_key_exists($name, $result)) {
                $result[$name] = is_array($type) ? implode('|', $type) : $type;
            }
        }
        return array_filter($result, 'strlen');
    }

    private function gatherModifier(Source $source, array $modifiers, array $namespaces, array $classes): array
    {
        $namespaces[] = $source->namespace();

        $result = [];
        foreach ($source->match([
            $modifiers,
            Source::MATCH_ANY,
            Source::MATCH_MANY,
            [T_CLOSE_TAG, $modifiers[0], $modifiers[1], '(', '}'],
        ]) as $tokens) {
            $tokens->shrink();
            $tokens->strip();
            $stmt = (string) $tokens;
            foreach ($classes as $class) {
                if (method_exists($class, $stmt)) {
                    $funcname = trim("$class::$stmt", '\\');
                    $result[trim($stmt, '\\')] = "\\$funcname";
                    continue 2;
                }
            }
            foreach ($namespaces as $namespace) {
                $funcname = trim("$namespace\\$stmt", '\\');
                if (function_exists($funcname)) {
                    $result[trim($stmt, '\\')] = "\\$funcname";
                    continue 2;
                }
            }
        }
        return $result;
    }

    private function gatherAccessor(Source $source, string $accessor): array
    {
        $result = [];
        foreach ($source->match([$accessor, T_STRING]) as $tokens) {
            $code = (string) $tokens->pop();
            $result[$code] = $code;
        }
        return $result;
    }

    private function outputConstFile(string $filename, array $consts)
    {
        if (!$consts['modifier'] && !$consts['accessor']) {
            return false;
        }

        // 既に保存されているならマージする
        if (file_exists($filename)) {
            try {
                $current = include($filename);
            }
            catch (\Throwable $t) {
                $current = [];
            }
            $consts['modifier'] += $current['modifier'] ?? [];
            $consts['accessor'] += $current['accessor'] ?? [];
        }

        ksort($consts['modifier']);
        ksort($consts['accessor']);

        $E = function ($v) { return var_export($v, true); };
        $V = function ($v) { return $v; };
        $ms = array_sprintf($consts['modifier'], function ($v, $k) use ($E) {
            return "function $k(...\$args){define({$E($k)}, $v(...[]));return $v(...\$args);}";
        }, "\n");
        $as = array_sprintf($consts['accessor'], function ($v, $k) use ($E) {
            return "define({$E($v)}, {$E($v)});";
        }, "\n");
        file_put_contents($filename, "<?php
if (null) {
    {$V(self::MODIFIER_FUNCTION_COMMENT)}
    {$V(indent_php($ms, 4))}
    {$V(self::ACCESS_KEY_COMMENT)}
    {$V(indent_php($as, 4))}
}
return {$V(var_export2($consts, 1))};
");
    }

    /**
     * レンダラーオブジェクト自身に変数をアサインする
     *
     * いわゆる共通・グローバル変数となり、テンプレート変数側と競合した場合はテンプレート側が優先される。
     *
     * - 配列を与えるとすべて追加する
     * - $name, $value を与えるとその名前・値で1つだけアサインする
     *
     * @param string|array $name 変数名 or 変数配列
     * @param mixed $value アサインする値
     * @return $this
     */
    public function assign($name, $value = null)
    {
        if (is_array($name)) {
            foreach ($name as $k => $v) {
                $this->assign($k, $v);
            }
        }
        else {
            $this->globalVars[$name] = $value;
        }
        return $this;
    }

    /**
     * アサインされている変数を返す
     *
     * debug 時は一度でもレンダリングが走ったテンプレートの変数もまとめて返す。
     * 基本的に debug 時の使用を想定していて本運用環境での使用は推奨しない。
     *
     * @return array アサインされた全変数
     */
    public function getAssignedVars(): array
    {
        return $this->assignedVars;
    }

    /**
     * ファイル名を指定してレンダリング
     *
     * @param string $filename テンプレートファイル名
     * @param array $vars 変数配列
     * @return string レンダリングされたコンテント文字列
     */
    public function render(string $filename, array $vars = [])
    {
        /** @var Template $template */

        $templateClass = $this->templateClass;
        $template = new $templateClass($this, $filename);

        if ($this->errorHandling) {
            $already = set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) use (&$already) {
                if (error_reporting() === 0) {
                    return $already !== null ? $already(...func_get_args()) : false;
                }
                throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
            });
        }

        $ob_level = ob_get_level();

        try {
            return $template->render($vars + $this->globalVars);
        }
        catch (\Throwable $t) {
            if ($this->errorHandling) {
                $this->rewriteException($t);
            }
            throw $t;
        }
        finally {
            while (ob_get_level() > $ob_level) {
                ob_end_clean();
            }
            if ($this->errorHandling) {
                restore_error_handler();
            }
        }
    }

    private function rewriteException(\Throwable $ex)
    {
        // @memo 例外の属性をリフレクションで書き換えるのはどうかと思うけど、投げ方を工夫するより後から書き換えたほうが楽だと思う

        $refile = function ($file) {
            $innerfile = $this->resolvePath($file);
            return $innerfile === $file ? null : $innerfile;
        };

        $remessage = function ($file, $line) {
            $LENGTH = 3;
            $partial = array_slice(file($file), max(0, $line - $LENGTH - 1), $LENGTH * 2, true);
            foreach ($partial as $n => $row) {
                $partial[$n] = ($n === $line - 1 ? '*' : ' ') . $row;
            }
            return "\nnear:\n" . rtrim(implode('', $partial)) . "\n";
        };

        $rewritten = [];

        // 投げ元がテンプレート（パースエラーとか変数の undefined とか）なら自身のファイル名とメッセージを書き換える
        if ($innerfile = $refile($ex->getFile())) {
            $innerline = RewriteWrapper::getLineMapping($innerfile, $ex->getLine());
            $rewritten['file'] = $innerfile;
            $rewritten['line'] = $innerline;
            $rewritten['message'] = $ex->getMessage() . $remessage($innerfile, $innerline);
        }

        // スタックトレースは共通で書き換えてしまう
        $rewritten['trace'] = array_map(function ($trace) use ($refile) {
            if (isset($trace['file'], $trace['line']) && $innerfile = $refile($trace['file'])) {
                $trace['file'] = $innerfile ?? $trace['file'];
                $trace['line'] = RewriteWrapper::getLineMapping($innerfile, $trace['line']);
            }
            return $trace;
        }, $ex->getTrace());

        // リフレクションで書き戻し（コアな private に依存してるので念の為 hasProperty でチェックする）
        // ちなみに、どちらも Throwable だが trace はトップレベルの private なのでリフレクションを分岐しないと取得できない
        $refex = new \ReflectionClass($ex instanceof \Exception ? \Exception::class : \Error::class);
        foreach ($rewritten as $name => $value) {
            if ($refex->hasProperty($name)) {
                $property = $refex->getProperty($name);
                $property->setAccessible(true);
                $property->setValue($ex, $value);
            }
        }

        // と、いうようなことを全例外に対して行う
        if ($previous = $ex->getPrevious()) {
            $this->rewriteException($previous);
        }
    }
}
