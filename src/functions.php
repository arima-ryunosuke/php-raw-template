<?php

# Don't touch this code. This is auto generated.

namespace ryunosuke\NightDragon;

# constants


# functions
if (!isset($excluded_functions["is_hasharray"]) && (!function_exists("ryunosuke\\NightDragon\\is_hasharray") || (!false && (new \ReflectionFunction("ryunosuke\\NightDragon\\is_hasharray"))->isInternal()))) {
    /**
     * 配列が連想配列か調べる
     *
     * 空の配列は普通の配列とみなす。
     *
     * Example:
     * ```php
     * assertFalse(is_hasharray([]));
     * assertFalse(is_hasharray([1, 2, 3]));
     * assertTrue(is_hasharray(['x' => 'X']));
     * ```
     *
     * @param array $array 調べる配列
     * @return bool 連想配列なら true
     */
    function is_hasharray(array $array)
    {
        $i = 0;
        foreach ($array as $k => $dummy) {
            if ($k !== $i++) {
                return true;
            }
        }
        return false;
    }
}
if (function_exists("ryunosuke\\NightDragon\\is_hasharray") && !defined("ryunosuke\\NightDragon\\is_hasharray")) {
    define("ryunosuke\\NightDragon\\is_hasharray", "ryunosuke\\NightDragon\\is_hasharray");
}

if (!isset($excluded_functions["array_explode"]) && (!function_exists("ryunosuke\\NightDragon\\array_explode") || (!false && (new \ReflectionFunction("ryunosuke\\NightDragon\\array_explode"))->isInternal()))) {
    /**
     * 配列を指定条件で分割する
     *
     * 文字列の explode を更に一階層掘り下げたイメージ。
     * $condition で指定された要素は結果配列に含まれない。
     *
     * $condition にはクロージャが指定できる。クロージャの場合は true 相当を返した場合に分割要素とみなされる。
     * 引数は (値, キー)の順番。
     *
     * $limit に負数を与えると「その絶対値-1までを結合したものと残り」を返す。
     * 端的に言えば「正数を与えると後詰めでその個数で返す」「負数を与えると前詰めでその（絶対値）個数で返す」という動作になる。
     *
     * Example:
     * ```php
     * // null 要素で分割
     * assertSame(array_explode(['a', null, 'b', 'c'], null), [['a'], [2 => 'b', 3 => 'c']]);
     * // クロージャで分割（大文字で分割）
     * assertSame(array_explode(['a', 'B', 'c', 'D', 'e'], function($v){return ctype_upper($v);}), [['a'], [2 => 'c'], [4 => 'e']]);
     * // 負数指定
     * assertSame(array_explode(['a', null, 'b', null, 'c'], null, -2), [[0 => 'a', 1 => null, 2 => 'b'], [4 => 'c']]);
     * ```
     *
     * @param iterable $array 対象配列
     * @param mixed $condition 分割条件
     * @param int $limit 最大分割数
     * @return array 分割された配列
     */
    function array_explode($array, $condition, $limit = \PHP_INT_MAX)
    {
        $array = arrayval($array, false);

        $limit = (int) $limit;
        if ($limit < 0) {
            // キーまで考慮するとかなりややこしくなるので富豪的にやる
            $reverse = array_explode(array_reverse($array, true), $condition, -$limit);
            $reverse = array_map(function ($v) { return array_reverse($v, true); }, $reverse);
            return array_reverse($reverse);
        }
        // explode において 0 は 1 と等しい
        if ($limit === 0) {
            $limit = 1;
        }

        $result = [];
        $chunk = [];
        $n = 0;
        foreach ($array as $k => $v) {
            if ($limit === 1) {
                $chunk = array_slice($array, $n, null, true);
                break;
            }

            $n++;
            if ($condition instanceof \Closure) {
                $match = $condition($v, $k);
            }
            else {
                $match = $condition === $v;
            }

            if ($match) {
                $limit--;
                $result[] = $chunk;
                $chunk = [];
            }
            else {
                $chunk[$k] = $v;
            }
        }
        $result[] = $chunk;
        return $result;
    }
}
if (function_exists("ryunosuke\\NightDragon\\array_explode") && !defined("ryunosuke\\NightDragon\\array_explode")) {
    define("ryunosuke\\NightDragon\\array_explode", "ryunosuke\\NightDragon\\array_explode");
}

if (!isset($excluded_functions["array_sprintf"]) && (!function_exists("ryunosuke\\NightDragon\\array_sprintf") || (!false && (new \ReflectionFunction("ryunosuke\\NightDragon\\array_sprintf"))->isInternal()))) {
    /**
     * キーと値で sprintf する
     *
     * 配列の各要素を文字列化して返すイメージ。
     * $glue を与えるとさらに implode して返す（返り値が文字列になる）。
     *
     * $format は書式文字列（$v, $k）。
     * callable を与えると sprintf ではなくコールバック処理になる（$v, $k）。
     * 省略（null）するとキーを format 文字列、値を引数として **vsprintf** する。
     *
     * Example:
     * ```php
     * $array = ['key1' => 'val1', 'key2' => 'val2'];
     * // key, value を利用した sprintf
     * assertSame(array_sprintf($array, '%2$s=%1$s'), ['key1=val1', 'key2=val2']);
     * // 第3引数を与えるとさらに implode される
     * assertSame(array_sprintf($array, '%2$s=%1$s', ' '), 'key1=val1 key2=val2');
     * // クロージャを与えるとコールバック動作になる
     * $closure = function($v, $k){return "$k=" . strtoupper($v);};
     * assertSame(array_sprintf($array, $closure, ' '), 'key1=VAL1 key2=VAL2');
     * // 省略すると vsprintf になる
     * assertSame(array_sprintf([
     *     'str:%s,int:%d' => ['sss', '3.14'],
     *     'single:%s'     => 'str',
     * ], null, '|'), 'str:sss,int:3|single:str');
     * ```
     *
     * @param iterable $array 対象配列
     * @param string|callable $format 書式文字列あるいはクロージャ
     * @param string $glue 結合文字列。未指定時は implode しない
     * @return array|string sprintf された配列
     */
    function array_sprintf($array, $format = null, $glue = null)
    {
        if (is_callable($format)) {
            $callback = func_user_func_array($format);
        }
        elseif ($format === null) {
            $callback = function ($v, $k) { return vsprintf($k, is_array($v) ? $v : [$v]); };
        }
        else {
            $callback = function ($v, $k) use ($format) { return sprintf($format, $v, $k); };
        }

        $result = [];
        foreach ($array as $k => $v) {
            $result[] = $callback($v, $k);
        }

        if ($glue !== null) {
            return implode($glue, $result);
        }

        return $result;
    }
}
if (function_exists("ryunosuke\\NightDragon\\array_sprintf") && !defined("ryunosuke\\NightDragon\\array_sprintf")) {
    define("ryunosuke\\NightDragon\\array_sprintf", "ryunosuke\\NightDragon\\array_sprintf");
}

if (!isset($excluded_functions["file_set_contents"]) && (!function_exists("ryunosuke\\NightDragon\\file_set_contents") || (!false && (new \ReflectionFunction("ryunosuke\\NightDragon\\file_set_contents"))->isInternal()))) {
    /**
     * ディレクトリも掘る file_put_contents
     *
     * 書き込みは一時ファイルと rename を使用してアトミックに行われる。
     *
     * Example:
     * ```php
     * file_set_contents(sys_get_temp_dir() . '/not/filename.ext', 'hoge');
     * assertSame(file_get_contents(sys_get_temp_dir() . '/not/filename.ext'), 'hoge');
     * ```
     *
     * @param string $filename 書き込むファイル名
     * @param string $data 書き込む内容
     * @param int $umask ディレクトリを掘る際の umask
     * @return int 書き込まれたバイト数
     */
    function file_set_contents($filename, $data, $umask = 0002)
    {
        if (func_num_args() === 2) {
            $umask = umask();
        }

        $filename = path_normalize($filename);

        if (!is_dir($dirname = dirname($filename))) {
            if (!@mkdir_p($dirname, $umask)) {
                throw new \RuntimeException("failed to mkdir($dirname)");
            }
        }

        $tempnam = tempnam($dirname, 'tmp');
        if (($result = file_put_contents($tempnam, $data)) !== false) {
            if (rename($tempnam, $filename)) {
                @chmod($filename, 0666 & ~$umask);
                return $result;
            }
            unlink($tempnam);
        }
        return false;
    }
}
if (function_exists("ryunosuke\\NightDragon\\file_set_contents") && !defined("ryunosuke\\NightDragon\\file_set_contents")) {
    define("ryunosuke\\NightDragon\\file_set_contents", "ryunosuke\\NightDragon\\file_set_contents");
}

if (!isset($excluded_functions["mkdir_p"]) && (!function_exists("ryunosuke\\NightDragon\\mkdir_p") || (!false && (new \ReflectionFunction("ryunosuke\\NightDragon\\mkdir_p"))->isInternal()))) {
    /**
     * ディレクトリを再帰的に掘る
     *
     * 既に存在する場合は何もしない（エラーも出さない）。
     *
     * @param string $dirname ディレクトリ名
     * @param int $umask ディレクトリを掘る際の umask
     * @return bool 作成したら true
     */
    function mkdir_p($dirname, $umask = 0002)
    {
        if (func_num_args() === 1) {
            $umask = umask();
        }

        if (file_exists($dirname)) {
            return false;
        }

        return mkdir($dirname, 0777 & (~$umask), true);
    }
}
if (function_exists("ryunosuke\\NightDragon\\mkdir_p") && !defined("ryunosuke\\NightDragon\\mkdir_p")) {
    define("ryunosuke\\NightDragon\\mkdir_p", "ryunosuke\\NightDragon\\mkdir_p");
}

if (!isset($excluded_functions["path_is_absolute"]) && (!function_exists("ryunosuke\\NightDragon\\path_is_absolute") || (!false && (new \ReflectionFunction("ryunosuke\\NightDragon\\path_is_absolute"))->isInternal()))) {
    /**
     * パスが絶対パスか判定する
     *
     * Example:
     * ```php
     * assertTrue(path_is_absolute('/absolute/path'));
     * assertFalse(path_is_absolute('relative/path'));
     * // Windows 環境では下記も true になる
     * if (DIRECTORY_SEPARATOR === '\\') {
     *     assertTrue(path_is_absolute('\\absolute\\path'));
     *     assertTrue(path_is_absolute('C:\\absolute\\path'));
     * }
     * ```
     *
     * @param string $path パス文字列
     * @return bool 絶対パスなら true
     */
    function path_is_absolute($path)
    {
        // スキームが付いている場合は path 部分で判定
        $parts = parse_url($path);
        if (isset($parts['scheme'], $parts['path'])) {
            $path = $parts['path'];
        }
        elseif (isset($parts['scheme'], $parts['host'])) {
            $path = $parts['host'];
        }

        if (substr($path, 0, 1) === '/') {
            return true;
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            if (preg_match('#^([a-z]+:(\\\\|\\/|$)|\\\\)#i', $path) !== 0) {
                return true;
            }
        }

        return false;
    }
}
if (function_exists("ryunosuke\\NightDragon\\path_is_absolute") && !defined("ryunosuke\\NightDragon\\path_is_absolute")) {
    define("ryunosuke\\NightDragon\\path_is_absolute", "ryunosuke\\NightDragon\\path_is_absolute");
}

if (!isset($excluded_functions["path_normalize"]) && (!function_exists("ryunosuke\\NightDragon\\path_normalize") || (!false && (new \ReflectionFunction("ryunosuke\\NightDragon\\path_normalize"))->isInternal()))) {
    /**
     * パスを正規化する
     *
     * 具体的には ./ や ../ を取り除いたり連続したディレクトリ区切りをまとめたりする。
     * realpath ではない。のでシンボリックリンクの解決などはしない。その代わりファイルが存在しなくても使用することができる。
     *
     * Example:
     * ```php
     * $DS = DIRECTORY_SEPARATOR;
     * assertSame(path_normalize('/path/to/something'), "{$DS}path{$DS}to{$DS}something");
     * assertSame(path_normalize('/path/through/../something'), "{$DS}path{$DS}something");
     * assertSame(path_normalize('./path/current/./through/../something'), "path{$DS}current{$DS}something");
     * ```
     *
     * @param string $path パス文字列
     * @return string 正規化されたパス
     */
    function path_normalize($path)
    {
        $ds = '/';
        if (DIRECTORY_SEPARATOR === '\\') {
            $ds .= '\\\\';
        }

        $result = [];
        foreach (preg_split("#[$ds]#u", $path) as $n => $part) {
            if ($n > 0 && $part === '') {
                continue;
            }
            if ($part === '.') {
                continue;
            }
            if ($part === '..') {
                if (empty($result)) {
                    throw new \InvalidArgumentException("'$path' is invalid as path string.");
                }
                array_pop($result);
                continue;
            }
            $result[] = $part;
        }
        return implode(DIRECTORY_SEPARATOR, $result);
    }
}
if (function_exists("ryunosuke\\NightDragon\\path_normalize") && !defined("ryunosuke\\NightDragon\\path_normalize")) {
    define("ryunosuke\\NightDragon\\path_normalize", "ryunosuke\\NightDragon\\path_normalize");
}

if (!isset($excluded_functions["delegate"]) && (!function_exists("ryunosuke\\NightDragon\\delegate") || (!false && (new \ReflectionFunction("ryunosuke\\NightDragon\\delegate"))->isInternal()))) {
    /**
     * 指定 callable を指定クロージャで実行するクロージャを返す
     *
     * ほぼ内部向けで外から呼ぶことはあまり想定していない。
     *
     * @param \Closure $invoker クロージャを実行するためのクロージャ（実処理）
     * @param callable $callable 最終的に実行したいクロージャ
     * @param int $arity 引数の数
     * @return \Closure $callable を実行するクロージャ
     */
    function delegate($invoker, $callable, $arity = null)
    {
        // 「delegate 経由で作成されたクロージャ」であることをマーキングするための use 変数
        $__rfunc_delegate_marker = true;
        assert($__rfunc_delegate_marker === true); // phpstorm の警告解除

        if ($arity === null) {
            $arity = parameter_length($callable, true, true);
        }

        if (is_infinite($arity)) {
            return eval('return function (...$_) use ($__rfunc_delegate_marker, $invoker, $callable) {
                return $invoker($callable, func_get_args());
            };');
        }

        $arity = abs($arity);
        switch ($arity) {
            case 0:
                return function () use ($__rfunc_delegate_marker, $invoker, $callable) {
                    return $invoker($callable, func_get_args());
                };
            case 1:
                return function ($_1) use ($__rfunc_delegate_marker, $invoker, $callable) {
                    return $invoker($callable, func_get_args());
                };
            case 2:
                return function ($_1, $_2) use ($__rfunc_delegate_marker, $invoker, $callable) {
                    return $invoker($callable, func_get_args());
                };
            case 3:
                return function ($_1, $_2, $_3) use ($__rfunc_delegate_marker, $invoker, $callable) {
                    return $invoker($callable, func_get_args());
                };
            case 4:
                return function ($_1, $_2, $_3, $_4) use ($__rfunc_delegate_marker, $invoker, $callable) {
                    return $invoker($callable, func_get_args());
                };
            case 5:
                return function ($_1, $_2, $_3, $_4, $_5) use ($__rfunc_delegate_marker, $invoker, $callable) {
                    return $invoker($callable, func_get_args());
                };
            default:
                $argstring = array_map(function ($v) { return '$_' . $v; }, range(1, $arity));
                return eval('return function (' . implode(', ', $argstring) . ') use ($__rfunc_delegate_marker, $invoker, $callable) {
                    return $invoker($callable, func_get_args());
                };');
        }
    }
}
if (function_exists("ryunosuke\\NightDragon\\delegate") && !defined("ryunosuke\\NightDragon\\delegate")) {
    define("ryunosuke\\NightDragon\\delegate", "ryunosuke\\NightDragon\\delegate");
}

if (!isset($excluded_functions["reflect_callable"]) && (!function_exists("ryunosuke\\NightDragon\\reflect_callable") || (!false && (new \ReflectionFunction("ryunosuke\\NightDragon\\reflect_callable"))->isInternal()))) {
    /**
     * callable から ReflectionFunctionAbstract を生成する
     *
     * Example:
     * ```php
     * assertInstanceof(\ReflectionFunction::class, reflect_callable('sprintf'));
     * assertInstanceof(\ReflectionMethod::class, reflect_callable('\Closure::bind'));
     * ```
     *
     * @param callable $callable 対象 callable
     * @return \ReflectionFunction|\ReflectionMethod リフレクションインスタンス
     */
    function reflect_callable($callable)
    {
        // callable チェック兼 $call_name 取得
        if (!is_callable($callable, true, $call_name)) {
            throw new \InvalidArgumentException("'$call_name' is not callable");
        }

        if ($callable instanceof \Closure || strpos($call_name, '::') === false) {
            return new \ReflectionFunction($callable);
        }
        else {
            list($class, $method) = explode('::', $call_name, 2);
            // for タイプ 5: 相対指定による静的クラスメソッドのコール (PHP 5.3.0 以降)
            if (strpos($method, 'parent::') === 0) {
                list(, $method) = explode('::', $method);
                return (new \ReflectionClass($class))->getParentClass()->getMethod($method);
            }
            return new \ReflectionMethod($class, $method);
        }
    }
}
if (function_exists("ryunosuke\\NightDragon\\reflect_callable") && !defined("ryunosuke\\NightDragon\\reflect_callable")) {
    define("ryunosuke\\NightDragon\\reflect_callable", "ryunosuke\\NightDragon\\reflect_callable");
}

if (!isset($excluded_functions["parameter_length"]) && (!function_exists("ryunosuke\\NightDragon\\parameter_length") || (!false && (new \ReflectionFunction("ryunosuke\\NightDragon\\parameter_length"))->isInternal()))) {
    /**
     * callable の引数の数を返す
     *
     * クロージャはキャッシュされない。毎回リフレクションを生成し、引数の数を調べてそれを返す。
     * （クロージャには一意性がないので key-value なキャッシュが適用できない）。
     * ので、ループ内で使ったりすると目に見えてパフォーマンスが低下するので注意。
     *
     * Example:
     * ```php
     * // trim の引数は2つ
     * assertSame(parameter_length('trim'), 2);
     * // trim の必須引数は1つ
     * assertSame(parameter_length('trim', true), 1);
     * ```
     *
     * @param callable $callable 対象 callable
     * @param bool $require_only true を渡すと必須パラメータの数を返す
     * @param bool $thought_variadic 可変引数を考慮するか。 true を渡すと可変引数の場合に無限長を返す
     * @return int 引数の数
     */
    function parameter_length($callable, $require_only = false, $thought_variadic = false)
    {
        // クロージャの $call_name には一意性がないのでキャッシュできない（spl_object_hash でもいいが、かなり重複するので完全ではない）
        if ($callable instanceof \Closure) {
            /** @var \ReflectionFunctionAbstract $ref */
            $ref = reflect_callable($callable);
            if ($thought_variadic && $ref->isVariadic()) {
                return INF;
            }
            elseif ($require_only) {
                return $ref->getNumberOfRequiredParameters();
            }
            else {
                return $ref->getNumberOfParameters();
            }
        }

        // $call_name 取得
        is_callable($callable, false, $call_name);

        $cache = cache($call_name, function () use ($callable) {
            /** @var \ReflectionFunctionAbstract $ref */
            $ref = reflect_callable($callable);
            return [
                '00' => $ref->getNumberOfParameters(),
                '01' => $ref->isVariadic() ? INF : $ref->getNumberOfParameters(),
                '10' => $ref->getNumberOfRequiredParameters(),
                '11' => $ref->isVariadic() ? INF : $ref->getNumberOfRequiredParameters(),
            ];
        }, __FUNCTION__);
        return $cache[(int) $require_only . (int) $thought_variadic];
    }
}
if (function_exists("ryunosuke\\NightDragon\\parameter_length") && !defined("ryunosuke\\NightDragon\\parameter_length")) {
    define("ryunosuke\\NightDragon\\parameter_length", "ryunosuke\\NightDragon\\parameter_length");
}

if (!isset($excluded_functions["func_user_func_array"]) && (!function_exists("ryunosuke\\NightDragon\\func_user_func_array") || (!false && (new \ReflectionFunction("ryunosuke\\NightDragon\\func_user_func_array"))->isInternal()))) {
    /**
     * パラメータ定義数に応じて呼び出し引数を可変にしてコールする
     *
     * デフォルト引数はカウントされない。必須パラメータの数で呼び出す。
     * もちろん可変引数は未対応。
     *
     * $callback に null を与えると例外的に「第1引数を返すクロージャ」を返す。
     *
     * php の標準関数は定義数より多い引数を投げるとエラーを出すのでそれを抑制したい場合に使う。
     *
     * Example:
     * ```php
     * // strlen に2つの引数を渡してもエラーにならない
     * $strlen = func_user_func_array('strlen');
     * assertSame($strlen('abc', null), 3);
     * ```
     *
     * @param callable $callback 呼び出すクロージャ
     * @return \Closure 引数ぴったりで呼び出すクロージャ
     */
    function func_user_func_array($callback)
    {
        // null は第1引数を返す特殊仕様
        if ($callback === null) {
            return function ($v) { return $v; };
        }
        // クロージャはユーザ定義しかありえないので調べる必要がない
        if ($callback instanceof \Closure) {
            // が、組み込みをバイパスする delegate はクロージャなのでそれだけは除外
            $uses = reflect_callable($callback)->getStaticVariables();
            if (!isset($uses['__rfunc_delegate_marker'])) {
                return $callback;
            }
        }

        // 上記以外は「引数ぴったりで削ぎ落としてコールするクロージャ」を返す
        $plength = parameter_length($callback, true, true);
        return delegate(function ($callback, $args) use ($plength) {
            if (is_infinite($plength)) {
                return $callback(...$args);
            }
            return $callback(...array_slice($args, 0, $plength));
        }, $callback, $plength);
    }
}
if (function_exists("ryunosuke\\NightDragon\\func_user_func_array") && !defined("ryunosuke\\NightDragon\\func_user_func_array")) {
    define("ryunosuke\\NightDragon\\func_user_func_array", "ryunosuke\\NightDragon\\func_user_func_array");
}

if (!isset($excluded_functions["concat"]) && (!function_exists("ryunosuke\\NightDragon\\concat") || (!false && (new \ReflectionFunction("ryunosuke\\NightDragon\\concat"))->isInternal()))) {
    /**
     * strcat の空文字回避版
     *
     * 基本は strcat と同じ。ただし、**引数の内1つでも空文字を含むなら空文字を返す**。
     *
     * 「プレフィックスやサフィックスを付けたいんだけど、空文字の場合はそのままで居て欲しい」という状況はまれによくあるはず。
     * コードで言えば `strlen($string) ? 'prefix-' . $string : '';` のようなもの。
     * 可変引数なので 端的に言えば mysql の CONCAT みたいな動作になる（あっちは NULL だが）。
     *
     * ```php
     * assertSame(concat('prefix-', 'middle', '-suffix'), 'prefix-middle-suffix');
     * assertSame(concat('prefix-', '', '-suffix'), '');
     * ```
     *
     * @param mixed $variadic 結合する文字列（可変引数）
     * @return string 結合した文字列
     */
    function concat(...$variadic)
    {
        $result = '';
        foreach ($variadic as $s) {
            if (strlen($s) === 0) {
                return '';
            }
            $result .= $s;
        }
        return $result;
    }
}
if (function_exists("ryunosuke\\NightDragon\\concat") && !defined("ryunosuke\\NightDragon\\concat")) {
    define("ryunosuke\\NightDragon\\concat", "ryunosuke\\NightDragon\\concat");
}

if (!isset($excluded_functions["str_chop"]) && (!function_exists("ryunosuke\\NightDragon\\str_chop") || (!false && (new \ReflectionFunction("ryunosuke\\NightDragon\\str_chop"))->isInternal()))) {
    /**
     * 先頭・末尾の指定文字列を削ぎ落とす
     *
     * Example:
     * ```php
     * // 文字列からパス文字列と拡張子を削ぎ落とす
     * $PATH = '/path/to/something';
     * assertSame(str_chop("$PATH/hoge.php", "$PATH/", '.php'), 'hoge');
     * ```
     *
     * @param string $string 対象文字列
     * @param string $prefix 削ぎ落とす先頭文字列
     * @param string $suffix 削ぎ落とす末尾文字列
     * @param bool $case_insensitivity 大文字小文字を無視するか
     * @return string 削ぎ落とした文字列
     */
    function str_chop($string, $prefix = null, $suffix = null, $case_insensitivity = false)
    {
        $pattern = [];
        if (strlen($prefix)) {
            $pattern[] = '(\A' . preg_quote($prefix, '#') . ')';
        }
        if (strlen($suffix)) {
            $pattern[] = '(' . preg_quote($suffix, '#') . '\z)';
        }
        $flag = 'u' . ($case_insensitivity ? 'i' : '');
        return preg_replace('#' . implode('|', $pattern) . '#' . $flag, '', $string);
    }
}
if (function_exists("ryunosuke\\NightDragon\\str_chop") && !defined("ryunosuke\\NightDragon\\str_chop")) {
    define("ryunosuke\\NightDragon\\str_chop", "ryunosuke\\NightDragon\\str_chop");
}

if (!isset($excluded_functions["str_lchop"]) && (!function_exists("ryunosuke\\NightDragon\\str_lchop") || (!false && (new \ReflectionFunction("ryunosuke\\NightDragon\\str_lchop"))->isInternal()))) {
    /**
     * 先頭の指定文字列を削ぎ落とす
     *
     * Example:
     * ```php
     * // 文字列からパス文字列を削ぎ落とす
     * $PATH = '/path/to/something';
     * assertSame(str_lchop("$PATH/hoge.php", "$PATH/"), 'hoge.php');
     * ```
     *
     * @param string $string 対象文字列
     * @param string $prefix 削ぎ落とす先頭文字列
     * @param bool $case_insensitivity 大文字小文字を無視するか
     * @return string 削ぎ落とした文字列
     */
    function str_lchop($string, $prefix, $case_insensitivity = false)
    {
        return str_chop($string, $prefix, null, $case_insensitivity);
    }
}
if (function_exists("ryunosuke\\NightDragon\\str_lchop") && !defined("ryunosuke\\NightDragon\\str_lchop")) {
    define("ryunosuke\\NightDragon\\str_lchop", "ryunosuke\\NightDragon\\str_lchop");
}

if (!isset($excluded_functions["cachedir"]) && (!function_exists("ryunosuke\\NightDragon\\cachedir") || (!false && (new \ReflectionFunction("ryunosuke\\NightDragon\\cachedir"))->isInternal()))) {
    /**
     * 本ライブラリで使用するキャッシュディレクトリを設定する
     *
     * @param string|null $dirname キャッシュディレクトリ。省略時は返すのみ
     * @return string 設定前のキャッシュディレクトリ
     */
    function cachedir($dirname = null)
    {
        static $cachedir;
        if ($dirname === null) {
            return $cachedir = $cachedir ?? sys_get_temp_dir();
        }

        if (!file_exists($dirname)) {
            @mkdir($dirname, 0777 & (~umask()), true);
        }
        $result = $cachedir;
        $cachedir = realpath($dirname);
        return $result;
    }
}
if (function_exists("ryunosuke\\NightDragon\\cachedir") && !defined("ryunosuke\\NightDragon\\cachedir")) {
    define("ryunosuke\\NightDragon\\cachedir", "ryunosuke\\NightDragon\\cachedir");
}

if (!isset($excluded_functions["cache"]) && (!function_exists("ryunosuke\\NightDragon\\cache") || (!false && (new \ReflectionFunction("ryunosuke\\NightDragon\\cache"))->isInternal()))) {
    /**
     * シンプルにキャッシュする
     *
     * この関数は get/set/delete を兼ねる。
     * キャッシュがある場合はそれを返し、ない場合は $provider を呼び出してその結果をキャッシュしつつそれを返す。
     *
     * $provider に null を与えるとキャッシュの削除となる。
     *
     * Example:
     * ```php
     * $provider = function(){return rand();};
     * // 乱数を返す処理だが、キャッシュされるので同じ値になる
     * $rand1 = cache('rand', $provider);
     * $rand2 = cache('rand', $provider);
     * assertSame($rand1, $rand2);
     * // $provider に null を与えると削除される
     * cache('rand', null);
     * $rand3 = cache('rand', $provider);
     * assertNotSame($rand1, $rand3);
     * ```
     *
     * @param string $key キャッシュのキー
     * @param callable $provider キャッシュがない場合にコールされる callable
     * @param string $namespace 名前空間
     * @return mixed キャッシュ
     */
    function cache($key, $provider, $namespace = null)
    {
        static $cacheobject;
        $cacheobject = $cacheobject ?? new class(cachedir())
            {
                const CACHE_EXT = '.php-cache';

                /** @var string キャッシュディレクトリ */
                private $cachedir;

                /** @var array 内部キャッシュ */
                private $cache;

                /** @var array 変更感知配列 */
                private $changed;

                public function __construct($cachedir)
                {
                    $this->cachedir = $cachedir;
                    $this->cache = [];
                    $this->changed = [];
                }

                public function __destruct()
                {
                    // 変更されているもののみ保存
                    foreach ($this->changed as $namespace => $dummy) {
                        $filepath = $this->cachedir . '/' . rawurlencode($namespace) . self::CACHE_EXT;
                        $content = "<?php\nreturn " . var_export($this->cache[$namespace], true) . ";\n";

                        $temppath = tempnam(sys_get_temp_dir(), 'cache');
                        if (file_put_contents($temppath, $content) !== false) {
                            @chmod($temppath, 0644);
                            if (!@rename($temppath, $filepath)) {
                                @unlink($temppath);
                            }
                        }
                    }
                }

                public function has($namespace, $key)
                {
                    // ファイルから読み込む必要があるので get しておく
                    $this->get($namespace, $key);
                    return array_key_exists($key, $this->cache[$namespace]);
                }

                public function get($namespace, $key)
                {
                    // 名前空間自体がないなら作る or 読む
                    if (!isset($this->cache[$namespace])) {
                        $nsarray = [];
                        $cachpath = $this->cachedir . '/' . rawurldecode($namespace) . self::CACHE_EXT;
                        if (file_exists($cachpath)) {
                            $nsarray = require $cachpath;
                        }
                        $this->cache[$namespace] = $nsarray;
                    }

                    return $this->cache[$namespace][$key] ?? null;
                }

                public function set($namespace, $key, $value)
                {
                    // 新しい値が来たら変更フラグを立てる
                    if (!isset($this->cache[$namespace]) || !array_key_exists($key, $this->cache[$namespace]) || $this->cache[$namespace][$key] !== $value) {
                        $this->changed[$namespace] = true;
                    }

                    $this->cache[$namespace][$key] = $value;
                }

                public function delete($namespace, $key)
                {
                    $this->changed[$namespace] = true;
                    unset($this->cache[$namespace][$key]);
                }

                public function clear()
                {
                    // インメモリ情報をクリアして・・・
                    $this->cache = [];
                    $this->changed = [];

                    // ファイルも消す
                    foreach (glob($this->cachedir . '/*' . self::CACHE_EXT) as $file) {
                        unlink($file);
                    }
                }
            };

        // flush (for test)
        if ($key === null) {
            $cacheobject = null;
            return;
        }

        $namespace = $namespace ?? __FILE__;

        $exist = $cacheobject->has($namespace, $key);
        if ($provider === null) {
            $cacheobject->delete($namespace, $key);
            return $exist;
        }
        if (!$exist) {
            $cacheobject->set($namespace, $key, $provider());
        }
        return $cacheobject->get($namespace, $key);
    }
}
if (function_exists("ryunosuke\\NightDragon\\cache") && !defined("ryunosuke\\NightDragon\\cache")) {
    define("ryunosuke\\NightDragon\\cache", "ryunosuke\\NightDragon\\cache");
}

if (!isset($excluded_functions["arrayval"]) && (!function_exists("ryunosuke\\NightDragon\\arrayval") || (!false && (new \ReflectionFunction("ryunosuke\\NightDragon\\arrayval"))->isInternal()))) {
    /**
     * array キャストの関数版
     *
     * intval とか strval とかの array 版。
     * ただキャストするだけだが、関数なのでコールバックとして使える。
     *
     * $recursive を true にすると再帰的に適用する（デフォルト）。
     * 入れ子オブジェクトを配列化するときなどに使える。
     *
     * Example:
     * ```php
     * // キャストなので基本的には配列化される
     * assertSame(arrayval(123), [123]);
     * assertSame(arrayval('str'), ['str']);
     * assertSame(arrayval([123]), [123]); // 配列は配列のまま
     *
     * // $recursive = false にしない限り再帰的に適用される
     * $stdclass = stdclass(['key' => 'val']);
     * assertSame(arrayval([$stdclass], true), [['key' => 'val']]); // true なので中身も配列化される
     * assertSame(arrayval([$stdclass], false), [$stdclass]);       // false なので中身は変わらない
     * ```
     *
     * @param mixed $var array 化する値
     * @param bool $recursive 再帰的に行うなら true
     * @return array array 化した配列
     */
    function arrayval($var, $recursive = true)
    {
        // return json_decode(json_encode($var), true);

        // 無駄なループを回したくないので非再帰で配列の場合はそのまま返す
        if (!$recursive && is_array($var)) {
            return $var;
        }

        if (is_primitive($var)) {
            return (array) $var;
        }

        $result = [];
        foreach ($var as $k => $v) {
            if ($recursive && !is_primitive($v)) {
                $v = arrayval($v, $recursive);
            }
            $result[$k] = $v;
        }
        return $result;
    }
}
if (function_exists("ryunosuke\\NightDragon\\arrayval") && !defined("ryunosuke\\NightDragon\\arrayval")) {
    define("ryunosuke\\NightDragon\\arrayval", "ryunosuke\\NightDragon\\arrayval");
}

if (!isset($excluded_functions["is_primitive"]) && (!function_exists("ryunosuke\\NightDragon\\is_primitive") || (!false && (new \ReflectionFunction("ryunosuke\\NightDragon\\is_primitive"))->isInternal()))) {
    /**
     * 値が複合型でないか検査する
     *
     * 「複合型」とはオブジェクトと配列のこと。
     * つまり
     *
     * - is_scalar($var) || is_null($var) || is_resource($var)
     *
     * と同義（!is_array($var) && !is_object($var) とも言える）。
     *
     * Example:
     * ```php
     * assertTrue(is_primitive(null));
     * assertTrue(is_primitive(false));
     * assertTrue(is_primitive(123));
     * assertTrue(is_primitive(STDIN));
     * assertFalse(is_primitive(new \stdClass));
     * assertFalse(is_primitive(['array']));
     * ```
     *
     * @param mixed $var 調べる値
     * @return bool 複合型なら false
     */
    function is_primitive($var)
    {
        return is_scalar($var) || is_null($var) || is_resource($var);
    }
}
if (function_exists("ryunosuke\\NightDragon\\is_primitive") && !defined("ryunosuke\\NightDragon\\is_primitive")) {
    define("ryunosuke\\NightDragon\\is_primitive", "ryunosuke\\NightDragon\\is_primitive");
}

if (!isset($excluded_functions["is_arrayable"]) && (!function_exists("ryunosuke\\NightDragon\\is_arrayable") || (!false && (new \ReflectionFunction("ryunosuke\\NightDragon\\is_arrayable"))->isInternal()))) {
    /**
     * 変数が配列アクセス可能か調べる
     *
     * Example:
     * ```php
     * assertTrue(is_arrayable([]));
     * assertTrue(is_arrayable(new \ArrayObject()));
     * assertFalse(is_arrayable(new \stdClass()));
     * ```
     *
     * @param array $var 調べる値
     * @return bool 配列アクセス可能なら true
     */
    function is_arrayable($var)
    {
        return is_array($var) || $var instanceof \ArrayAccess;
    }
}
if (function_exists("ryunosuke\\NightDragon\\is_arrayable") && !defined("ryunosuke\\NightDragon\\is_arrayable")) {
    define("ryunosuke\\NightDragon\\is_arrayable", "ryunosuke\\NightDragon\\is_arrayable");
}
