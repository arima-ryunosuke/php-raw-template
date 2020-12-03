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
     * that(is_hasharray([]))->isFalse();
     * that(is_hasharray([1, 2, 3]))->isFalse();
     * that(is_hasharray(['x' => 'X']))->isTrue();
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
     * that(array_explode(['a', null, 'b', 'c'], null))->isSame([['a'], [2 => 'b', 3 => 'c']]);
     * // クロージャで分割（大文字で分割）
     * that(array_explode(['a', 'B', 'c', 'D', 'e'], function($v){return ctype_upper($v);}))->isSame([['a'], [2 => 'c'], [4 => 'e']]);
     * // 負数指定
     * that(array_explode(['a', null, 'b', null, 'c'], null, -2))->isSame([[0 => 'a', 1 => null, 2 => 'b'], [4 => 'c']]);
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
     * that(array_sprintf($array, '%2$s=%1$s'))->isSame(['key1=val1', 'key2=val2']);
     * // 第3引数を与えるとさらに implode される
     * that(array_sprintf($array, '%2$s=%1$s', ' '))->isSame('key1=val1 key2=val2');
     * // クロージャを与えるとコールバック動作になる
     * $closure = function($v, $k){return "$k=" . strtoupper($v);};
     * that(array_sprintf($array, $closure, ' '))->isSame('key1=VAL1 key2=VAL2');
     * // 省略すると vsprintf になる
     * that(array_sprintf([
     *     'str:%s,int:%d' => ['sss', '3.14'],
     *     'single:%s'     => 'str',
     * ], null, '|'))->isSame('str:sss,int:3|single:str');
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

if (!isset($excluded_functions["array_map_method"]) && (!function_exists("ryunosuke\\NightDragon\\array_map_method") || (!false && (new \ReflectionFunction("ryunosuke\\NightDragon\\array_map_method"))->isInternal()))) {
    /**
     * メソッドを指定できるようにした array_map
     *
     * 配列内の要素は全て同一（少なくともシグネチャが同じ $method が存在する）オブジェクトでなければならない。
     * スルーする場合は $ignore=true とする。スルーした場合 map ではなく filter される（結果配列に含まれない）。
     * $ignore=null とすると 何もせずそのまま要素を返す。
     *
     * Example:
     * ```php
     * $exa = new \Exception('a');
     * $exb = new \Exception('b');
     * $std = new \stdClass();
     * // getMessage で map される
     * that(array_map_method([$exa, $exb], 'getMessage'))->isSame(['a', 'b']);
     * // getMessage で map されるが、メソッドが存在しない場合は取り除かれる
     * that(array_map_method([$exa, $exb, $std, null], 'getMessage', [], true))->isSame(['a', 'b']);
     * // getMessage で map されるが、メソッドが存在しない場合はそのまま返す
     * that(array_map_method([$exa, $exb, $std, null], 'getMessage', [], null))->isSame(['a', 'b', $std, null]);
     * ```
     *
     * @param iterable $array 対象配列
     * @param string $method メソッド
     * @param array $args メソッドに渡る引数
     * @param bool|null $ignore メソッドが存在しない場合にスルーするか。null を渡すと要素そのものを返す
     * @return array $method が true を返した新しい配列
     */
    function array_map_method($array, $method, $args = [], $ignore = false)
    {
        if ($ignore === true) {
            $array = array_filter(arrayval($array, false), function ($object) use ($method) {
                return is_callable([$object, $method]);
            });
        }
        return array_map(function ($object) use ($method, $args, $ignore) {
            if ($ignore === null && !is_callable([$object, $method])) {
                return $object;
            }
            return ([$object, $method])(...$args);
        }, arrayval($array, false));
    }
}
if (function_exists("ryunosuke\\NightDragon\\array_map_method") && !defined("ryunosuke\\NightDragon\\array_map_method")) {
    define("ryunosuke\\NightDragon\\array_map_method", "ryunosuke\\NightDragon\\array_map_method");
}

if (!isset($excluded_functions["array_each"]) && (!function_exists("ryunosuke\\NightDragon\\array_each") || (!false && (new \ReflectionFunction("ryunosuke\\NightDragon\\array_each"))->isInternal()))) {
    /**
     * array_reduce の参照版（のようなもの）
     *
     * 配列をループで回し、その途中経過、値、キー、連番をコールバック引数で渡して最終的な結果を返り値として返す。
     * array_reduce と少し似てるが、下記の点が異なる。
     *
     * - いわゆる $carry は返り値で表すのではなく、参照引数で表す
     * - 値だけでなくキー、連番も渡ってくる
     * - 巨大配列の場合でも速度劣化が少ない（array_reduce に巨大配列を渡すと実用にならないレベルで遅くなる）
     *
     * $callback の引数は `($value, $key, $n)` （$n はキーとは関係がない 0 ～ 要素数-1 の通し連番）。
     *
     * 返り値ではなく参照引数なので return する必要はない（ワンライナーが書きやすくなる）。
     * 返り値が空くのでループ制御に用いる。
     * 今のところ $callback が false を返すとそこで break するのみ。
     *
     * 第3引数を省略した場合、**クロージャの第1引数のデフォルト値が使われる**。
     * これは特筆すべき動作で、不格好な第3引数を完全に省略することができる（サンプルコードを参照）。
     * ただし「php の文法違反（今のところエラーにはならないし、全てにデフォルト値をつければ一応回避可能）」「リフレクションを使う（ほんの少し遅くなる）」などの弊害が有るので推奨はしない。
     * （ただ、「意図していることをコードで表す」といった観点ではこの記法の方が正しいとも思う）。
     *
     * Example:
     * ```php
     * // 全要素を文字列的に足し合わせる
     * that(array_each([1, 2, 3, 4, 5], function(&$carry, $v){$carry .= $v;}, ''))->isSame('12345');
     * // 値をキーにして要素を2乗値にする
     * that(array_each([1, 2, 3, 4, 5], function(&$carry, $v){$carry[$v] = $v * $v;}, []))->isSame([
     *     1 => 1,
     *     2 => 4,
     *     3 => 9,
     *     4 => 16,
     *     5 => 25,
     * ]);
     * // 上記と同じ。ただし、3 で break する
     * that(array_each([1, 2, 3, 4, 5], function(&$carry, $v, $k){
     *     if ($k === 3) return false;
     *     $carry[$v] = $v * $v;
     * }, []))->isSame([
     *     1 => 1,
     *     2 => 4,
     *     3 => 9,
     * ]);
     *
     * // 下記は完全に同じ（第3引数の代わりにデフォルト引数を使っている）
     * that(array_each([1, 2, 3], function(&$carry = [], $v) {
     *         $carry[$v] = $v * $v;
     *     }))->isSame(array_each([1, 2, 3], function(&$carry, $v) {
     *         $carry[$v] = $v * $v;
     *     }, [])
     *     // 個人的に↑のようなぶら下がり引数があまり好きではない（クロージャを最後の引数にしたい）
     * );
     * ```
     *
     * @param iterable $array 対象配列
     * @param callable $callback 評価クロージャ。(&$carry, $key, $value) を受ける
     * @param mixed $default ループの最初や空の場合に適用される値
     * @return mixed each した結果
     */
    function array_each($array, $callback, $default = null)
    {
        if (func_num_args() === 2) {
            /** @var \ReflectionFunction $ref */
            $ref = reflect_callable($callback);
            $params = $ref->getParameters();
            if ($params[0]->isDefaultValueAvailable()) {
                $default = $params[0]->getDefaultValue();
            }
        }

        $n = 0;
        foreach ($array as $k => $v) {
            $return = $callback($default, $v, $k, $n++);
            if ($return === false) {
                break;
            }
        }
        return $default;
    }
}
if (function_exists("ryunosuke\\NightDragon\\array_each") && !defined("ryunosuke\\NightDragon\\array_each")) {
    define("ryunosuke\\NightDragon\\array_each", "ryunosuke\\NightDragon\\array_each");
}

if (!isset($excluded_functions["array_all"]) && (!function_exists("ryunosuke\\NightDragon\\array_all") || (!false && (new \ReflectionFunction("ryunosuke\\NightDragon\\array_all"))->isInternal()))) {
    /**
     * 全要素が true になるなら true を返す（1つでも false なら false を返す）
     *
     * $callback が要求するならキーも渡ってくる。
     *
     * Example:
     * ```php
     * that(array_all([true, true]))->isTrue();
     * that(array_all([true, false]))->isFalse();
     * that(array_all([false, false]))->isFalse();
     * ```
     *
     * @param iterable $array 対象配列
     * @param callable $callback 評価クロージャ。 null なら値そのもので評価
     * @param bool|mixed $default 空配列の場合のデフォルト値
     * @return bool 全要素が true なら true
     */
    function array_all($array, $callback = null, $default = true)
    {
        if (is_empty($array)) {
            return $default;
        }

        $callback = func_user_func_array($callback);

        foreach ($array as $k => $v) {
            if (!$callback($v, $k)) {
                return false;
            }
        }
        return true;
    }
}
if (function_exists("ryunosuke\\NightDragon\\array_all") && !defined("ryunosuke\\NightDragon\\array_all")) {
    define("ryunosuke\\NightDragon\\array_all", "ryunosuke\\NightDragon\\array_all");
}

if (!isset($excluded_functions["get_object_properties"]) && (!function_exists("ryunosuke\\NightDragon\\get_object_properties") || (!false && (new \ReflectionFunction("ryunosuke\\NightDragon\\get_object_properties"))->isInternal()))) {
    /**
     * オブジェクトのプロパティを可視・不可視を問わず取得する
     *
     * get_object_vars + no public プロパティを返すイメージ。
     *
     * Example:
     * ```php
     * $object = new \Exception('something', 42);
     * $object->oreore = 'oreore';
     *
     * // get_object_vars はそのスコープから見えないプロパティを取得できない
     * // var_dump(get_object_vars($object));
     *
     * // array キャストは全て得られるが null 文字を含むので扱いにくい
     * // var_dump((array) $object);
     *
     * // この関数を使えば不可視プロパティも取得できる
     * that(get_object_properties($object))->arraySubset([
     *     'message' => 'something',
     *     'code'    => 42,
     *     'oreore'  => 'oreore',
     * ]);
     * ```
     *
     * @param object $object オブジェクト
     * @return array 全プロパティの配列
     */
    function get_object_properties($object)
    {
        if (function_exists('get_mangled_object_vars')) {
            get_mangled_object_vars($object); // @codeCoverageIgnore
        }

        static $refs = [];
        $class = get_class($object);
        if (!isset($refs[$class])) {
            // var_export や var_dump で得られるものは「親が優先」となっているが、不具合的動作だと思うので「子を優先」とする
            $refs[$class] = [];
            $ref = new \ReflectionClass($class);
            do {
                $refs[$ref->name] = array_each($ref->getProperties(), function (&$carry, \ReflectionProperty $rp) {
                    if (!$rp->isStatic()) {
                        $rp->setAccessible(true);
                        $carry[$rp->getName()] = $rp;
                    }
                }, []);
                $refs[$class] += $refs[$ref->name];
            } while ($ref = $ref->getParentClass());
        }

        // 配列キャストだと private で ヌル文字が出たり static が含まれたりするのでリフレクションで取得して勝手プロパティで埋める
        $vars = array_map_method($refs[$class], 'getValue', [$object]);
        $vars += get_object_vars($object);

        return $vars;
    }
}
if (function_exists("ryunosuke\\NightDragon\\get_object_properties") && !defined("ryunosuke\\NightDragon\\get_object_properties")) {
    define("ryunosuke\\NightDragon\\get_object_properties", "ryunosuke\\NightDragon\\get_object_properties");
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
     * that(file_get_contents(sys_get_temp_dir() . '/not/filename.ext'))->isSame('hoge');
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

if (!isset($excluded_functions["file_rewrite_contents"]) && (!function_exists("ryunosuke\\NightDragon\\file_rewrite_contents") || (!false && (new \ReflectionFunction("ryunosuke\\NightDragon\\file_rewrite_contents"))->isInternal()))) {
    /**
     * ファイルを読み込んで内容をコールバックに渡して書き込む
     *
     * Example:
     * ```php
     * // 適当にファイルを用意
     * $testpath = sys_get_temp_dir() . '/rewrite.txt';
     * file_put_contents($testpath, 'hoge');
     * // 前後に 'pre-', '-fix' を付与する
     * file_rewrite_contents($testpath, function($contents, $fp){ return "pre-$contents-fix"; });
     * that($testpath)->fileEquals('pre-hoge-fix');
     * ```
     *
     * @param string $filename 読み書きするファイル名
     * @param callable $callback 書き込む内容。引数で $contents, $fp が渡ってくる
     * @param int $operation ロック定数（LOCL_SH, LOCK_EX, LOCK_NB）
     * @return int 書き込まれたバイト数
     */
    function file_rewrite_contents($filename, $callback, $operation = 0)
    {
        /** @var resource $fp */
        try {
            // 開いて
            $fp = fopen($filename, 'c+b') ?: throws(new \UnexpectedValueException('failed to fopen.'));
            if ($operation) {
                flock($fp, $operation) ?: throws(new \UnexpectedValueException('failed to flock.'));
            }

            // 読み込んで
            rewind($fp) ?: throws(new \UnexpectedValueException('failed to rewind.'));
            $contents = false !== ($t = stream_get_contents($fp)) ? $t : throws(new \UnexpectedValueException('failed to stream_get_contents.'));

            // 変更して
            rewind($fp) ?: throws(new \UnexpectedValueException('failed to rewind.'));
            ftruncate($fp, 0) ?: throws(new \UnexpectedValueException('failed to ftruncate.'));
            $contents = $callback($contents, $fp);

            // 書き込んで
            $return = ($r = fwrite($fp, $contents)) !== false ? $r : throws(new \UnexpectedValueException('failed to fwrite.'));
            fflush($fp) ?: throws(new \UnexpectedValueException('failed to fflush.'));

            // 閉じて
            if ($operation) {
                flock($fp, LOCK_UN) ?: throws(new \UnexpectedValueException('failed to flock.'));
            }
            fclose($fp) ?: throws(new \UnexpectedValueException('failed to fclose.'));

            // 返す
            return $return;
        }
        catch (\Exception $ex) {
            if (isset($fp)) {
                if ($operation) {
                    flock($fp, LOCK_UN);
                }
                fclose($fp);
            }
            throw $ex;
        }
    }
}
if (function_exists("ryunosuke\\NightDragon\\file_rewrite_contents") && !defined("ryunosuke\\NightDragon\\file_rewrite_contents")) {
    define("ryunosuke\\NightDragon\\file_rewrite_contents", "ryunosuke\\NightDragon\\file_rewrite_contents");
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
     * that(path_is_absolute('/absolute/path'))->isTrue();
     * that(path_is_absolute('relative/path'))->isFalse();
     * // Windows 環境では下記も true になる
     * if (DIRECTORY_SEPARATOR === '\\') {
     *     that(path_is_absolute('\\absolute\\path'))->isTrue();
     *     that(path_is_absolute('C:\\absolute\\path'))->isTrue();
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
            if (preg_match('#^([a-z]+:(\\\\|/|$)|\\\\)#i', $path) !== 0) {
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
     * that(path_normalize('/path/to/something'))->isSame("{$DS}path{$DS}to{$DS}something");
     * that(path_normalize('/path/through/../something'))->isSame("{$DS}path{$DS}something");
     * that(path_normalize('./path/current/./through/../something'))->isSame("path{$DS}current{$DS}something");
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
     * @return callable $callable を実行するクロージャ
     */
    function delegate($invoker, $callable, $arity = null)
    {
        $arity = $arity ?? parameter_length($callable, true, true);

        if (reflect_callable($callable)->isInternal()) {
            static $cache = [];
            $cache[$arity] = $cache[$arity] ?? evaluate('return new class()
            {
                private $invoker, $callable;

                public function spawn($invoker, $callable)
                {
                    $that = clone($this);
                    $that->invoker = $invoker;
                    $that->callable = $callable;
                    return $that;
                }

                public function __invoke(' . implode(',', is_infinite($arity)
                        ? ['...$_']
                        : array_map(function ($v) { return '$_' . $v; }, array_keys(array_fill(1, $arity, null)))
                    ) . ')
                {
                    return ($this->invoker)($this->callable, func_get_args());
                }
            };');
            return $cache[$arity]->spawn($invoker, $callable);
        }

        switch (true) {
            case $arity === 0:
                return function () use ($invoker, $callable) { return $invoker($callable, func_get_args()); };
            case $arity === 1:
                return function ($_1) use ($invoker, $callable) { return $invoker($callable, func_get_args()); };
            case $arity === 2:
                return function ($_1, $_2) use ($invoker, $callable) { return $invoker($callable, func_get_args()); };
            case $arity === 3:
                return function ($_1, $_2, $_3) use ($invoker, $callable) { return $invoker($callable, func_get_args()); };
            case $arity === 4:
                return function ($_1, $_2, $_3, $_4) use ($invoker, $callable) { return $invoker($callable, func_get_args()); };
            case $arity === 5:
                return function ($_1, $_2, $_3, $_4, $_5) use ($invoker, $callable) { return $invoker($callable, func_get_args()); };
            case is_infinite($arity):
                return function (...$_) use ($invoker, $callable) { return $invoker($callable, func_get_args()); };
            default:
                $args = implode(',', array_map(function ($v) { return '$_' . $v; }, array_keys(array_fill(1, $arity, null))));
                $stmt = 'return function (' . $args . ') use ($invoker, $callable) { return $invoker($callable, func_get_args()); };';
                return eval($stmt);
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
     * that(reflect_callable('sprintf'))->isInstanceOf(\ReflectionFunction::class);
     * that(reflect_callable('\Closure::bind'))->isInstanceOf(\ReflectionMethod::class);
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
            [$class, $method] = explode('::', $call_name, 2);
            // for タイプ 5: 相対指定による静的クラスメソッドのコール (PHP 5.3.0 以降)
            if (strpos($method, 'parent::') === 0) {
                [, $method] = explode('::', $method);
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
     * that(parameter_length('trim'))->isSame(2);
     * // trim の必須引数は1つ
     * that(parameter_length('trim', true))->isSame(1);
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
     *
     * $callback に null を与えると例外的に「第1引数を返すクロージャ」を返す。
     *
     * php の標準関数は定義数より多い引数を投げるとエラーを出すのでそれを抑制したい場合に使う。
     *
     * Example:
     * ```php
     * // strlen に2つの引数を渡してもエラーにならない
     * $strlen = func_user_func_array('strlen');
     * that($strlen('abc', null))->isSame(3);
     * ```
     *
     * @param callable $callback 呼び出すクロージャ
     * @return callable 引数ぴったりで呼び出すクロージャ
     */
    function func_user_func_array($callback)
    {
        // null は第1引数を返す特殊仕様
        if ($callback === null) {
            return function ($v) { return $v; };
        }
        // クロージャはユーザ定義しかありえないので調べる必要がない
        if ($callback instanceof \Closure) {
            // と思ったが、\Closure::fromCallable で作成されたクロージャは内部属性が伝播されるようなので除外
            if (reflect_callable($callback)->isUserDefined()) {
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
     * that(concat('prefix-', 'middle', '-suffix'))->isSame('prefix-middle-suffix');
     * that(concat('prefix-', '', '-suffix'))->isSame('');
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

if (!isset($excluded_functions["strpos_quoted"]) && (!function_exists("ryunosuke\\NightDragon\\strpos_quoted") || (!false && (new \ReflectionFunction("ryunosuke\\NightDragon\\strpos_quoted"))->isInternal()))) {
    /**
     * クオートを考慮して strpos する
     *
     * Example:
     * ```php
     * // クオート中は除外される
     * that(strpos_quoted('hello "this" is world', 'is'))->isSame(13);
     * // 開始位置やクオート文字は指定できる（5文字目以降の \* に囲まれていない hoge の位置を返す）
     * that(strpos_quoted('1:hoge, 2:*hoge*, 3:hoge', 'hoge', 5, '*'))->isSame(20);
     * ```
     *
     * @param string $haystack 対象文字列
     * @param string|iterable $needle 位置を取得したい文字列
     * @param int $offset 開始位置
     * @param string|array $enclosure 囲い文字。この文字中にいる $from, $to 文字は走査外になる
     * @param string $escape エスケープ文字。この文字が前にある $from, $to 文字は走査外になる
     * @return false|int $needle の位置
     */
    function strpos_quoted($haystack, $needle, $offset = 0, $enclosure = "'\"", $escape = '\\')
    {
        if (is_string($enclosure) || is_null($enclosure)) {
            if (strlen($enclosure)) {
                $chars = str_split($enclosure);
                $enclosure = array_combine($chars, $chars);
            }
            else {
                $enclosure = [];
            }
        }
        $needles = arrayval($needle);

        $strlen = strlen($haystack);

        if ($offset < 0) {
            $offset += $strlen;
        }

        $enclosing = [];
        for ($i = $offset; $i < $strlen; $i++) {
            if ($i !== 0 && $haystack[$i - 1] === $escape) {
                continue;
            }
            foreach ($enclosure as $start => $end) {
                if (substr_compare($haystack, $end, $i, strlen($end)) === 0) {
                    if ($enclosing && $enclosing[count($enclosing) - 1] === $end) {
                        array_pop($enclosing);
                        $i += strlen($end) - 1;
                        continue 2;
                    }
                }
                if (substr_compare($haystack, $start, $i, strlen($start)) === 0) {
                    $enclosing[] = $end;
                    $i += strlen($start) - 1;
                    continue 2;
                }
            }

            if (empty($enclosing)) {
                foreach ($needles as $needle) {
                    if (substr_compare($haystack, $needle, $i, strlen($needle)) === 0) {
                        return $i;
                    }
                }
            }
        }
        return false;
    }
}
if (function_exists("ryunosuke\\NightDragon\\strpos_quoted") && !defined("ryunosuke\\NightDragon\\strpos_quoted")) {
    define("ryunosuke\\NightDragon\\strpos_quoted", "ryunosuke\\NightDragon\\strpos_quoted");
}

if (!isset($excluded_functions["str_chop"]) && (!function_exists("ryunosuke\\NightDragon\\str_chop") || (!false && (new \ReflectionFunction("ryunosuke\\NightDragon\\str_chop"))->isInternal()))) {
    /**
     * 先頭・末尾の指定文字列を削ぎ落とす
     *
     * Example:
     * ```php
     * // 文字列からパス文字列と拡張子を削ぎ落とす
     * $PATH = '/path/to/something';
     * that(str_chop("$PATH/hoge.php", "$PATH/", '.php'))->isSame('hoge');
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
     * that(str_lchop("$PATH/hoge.php", "$PATH/"))->isSame('hoge.php');
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

if (!isset($excluded_functions["evaluate"]) && (!function_exists("ryunosuke\\NightDragon\\evaluate") || (!false && (new \ReflectionFunction("ryunosuke\\NightDragon\\evaluate"))->isInternal()))) {
    /**
     * eval のプロキシ関数
     *
     * 一度ファイルに吐いてから require した方が opcache が効くので抜群に速い。
     * また、素の eval は ParseError が起こったときの表示がわかりにくすぎるので少し見やすくしてある。
     *
     * 関数化してる以上 eval におけるコンテキストの引き継ぎはできない。
     * ただし、引数で変数配列を渡せるようにしてあるので get_defined_vars を併用すれば基本的には同じ（$this はどうしようもない）。
     *
     * 短いステートメントだと opcode が少ないのでファイルを経由せず直接 eval したほうが速いことに留意。
     * 一応引数で指定できるようにはしてある。
     *
     * Example:
     * ```php
     * $a = 1;
     * $b = 2;
     * $phpcode = ';
     * $c = $a + $b;
     * return $c * 3;
     * ';
     * that(evaluate($phpcode, get_defined_vars()))->isSame(9);
     * ```
     *
     * @param string $phpcode 実行する php コード
     * @param array $contextvars コンテキスト変数配列
     * @param int $cachesize キャッシュするサイズ
     * @return mixed eval の return 値
     */
    function evaluate($phpcode, $contextvars = [], $cachesize = 256)
    {
        $cachefile = null;
        if ($cachesize && strlen($phpcode) >= $cachesize) {
            $cachefile = cachedir() . '/' . rawurlencode(__FUNCTION__) . '-' . sha1($phpcode) . '.php';
            if (!file_exists($cachefile)) {
                file_put_contents($cachefile, "<?php $phpcode", LOCK_EX);
            }
        }

        try {
            if ($cachefile) {
                return (static function () {
                    extract(func_get_arg(1));
                    return require func_get_arg(0);
                })($cachefile, $contextvars);
            }
            else {
                return (static function () {
                    extract(func_get_arg(1));
                    return eval(func_get_arg(0));
                })($phpcode, $contextvars);
            }
        }
        catch (\ParseError $ex) {
            $errline = $ex->getLine();
            $errline_1 = $errline - 1;
            $codes = preg_split('#\\R#u', $phpcode);
            $codes[$errline_1] = '>>> ' . $codes[$errline_1];

            $N = 5; // 前後の行数
            $message = $ex->getMessage();
            $message .= "\n" . implode("\n", array_slice($codes, max(0, $errline_1 - $N), $N * 2 + 1));
            if ($cachefile) {
                $message .= "\n in " . realpath($cachefile) . " on line " . $errline . "\n";
            }
            throw new \ParseError($message, $ex->getCode(), $ex);
        }
    }
}
if (function_exists("ryunosuke\\NightDragon\\evaluate") && !defined("ryunosuke\\NightDragon\\evaluate")) {
    define("ryunosuke\\NightDragon\\evaluate", "ryunosuke\\NightDragon\\evaluate");
}

if (!isset($excluded_functions["indent_php"]) && (!function_exists("ryunosuke\\NightDragon\\indent_php") || (!false && (new \ReflectionFunction("ryunosuke\\NightDragon\\indent_php"))->isInternal()))) {
    /**
     * php のコードのインデントを調整する
     *
     * インデントの基準はコードの最初の行になる。
     * その基準インデントを削除した後、指定したインデントレベルでインデントするようなイメージ。
     *
     * Example:
     * ```php
     * $phpcode = '
     *     echo 123;
     *
     *     if (true) {
     *         echo 456;
     *     }
     * ';
     * // 数値指定は空白換算
     * that(indent_php($phpcode, 8))->isSame('
     *         echo 123;
     *
     *         if (true) {
     *             echo 456;
     *         }
     * ');
     * // 文字列を指定すればそれが使用される
     * that(indent_php($phpcode, "  "))->isSame('
     *   echo 123;
     *
     *   if (true) {
     *       echo 456;
     *   }
     * ');
     * // オプション指定
     * that(indent_php($phpcode, [
     *     'indent'    => 4,    // インデント指定（上記の数値・文字列指定はこれの糖衣構文）
     *     'trimempty' => true, // 空行を trim するか
     *     'heredoc'   => true, // php7.3 の Flexible Heredoc もインデントするか
     * ]))->isSame('
     *     echo 123;
     *
     *     if (true) {
     *         echo 456;
     *     }
     * ');
     * ```
     *
     * @param string $phpcode インデントする php コード
     * @param array|int|string $options オプション
     * @return string インデントされた php コード
     */
    function indent_php($phpcode, $options = [])
    {
        if (!is_array($options)) {
            $options = ['indent' => $options];
        }
        $options += [
            'indent'    => 0,
            'trimempty' => true,
            'heredoc'   => version_compare(PHP_VERSION, '7.3.0') < 0,
        ];
        if (is_int($options['indent'])) {
            $options['indent'] = str_repeat(' ', $options['indent']);
        }

        $tmp = token_get_all("<?php $phpcode");
        array_shift($tmp);

        // トークンの正規化
        $tokens = [];
        for ($i = 0; $i < count($tmp); $i++) {
            if (is_string($tmp[$i])) {
                $tmp[$i] = [-1, $tmp[$i], null];
            }

            // 行コメントの分割（T_COMMENT には改行が含まれている）
            if ($tmp[$i][0] === T_COMMENT && preg_match('@^(#|//).*?(\\R)@um', $tmp[$i][1], $matches)) {
                $tmp[$i][1] = trim($tmp[$i][1]);
                if (($tmp[$i + 1][0] ?? null) === T_WHITESPACE) {
                    $tmp[$i + 1][1] = $matches[2] . $tmp[$i + 1][1];
                }
                else {
                    array_splice($tmp, $i + 1, 0, [[T_WHITESPACE, $matches[2], null]]);
                }
            }

            if ($options['heredoc']) {
                // 行コメントと同じ（T_START_HEREDOC には改行が含まれている）
                if ($tmp[$i][0] === T_START_HEREDOC && preg_match('@^(<<<).*?(\\R)@um', $tmp[$i][1], $matches)) {
                    $tmp[$i][1] = trim($tmp[$i][1]);
                    if (($tmp[$i + 1][0] ?? null) === T_ENCAPSED_AND_WHITESPACE) {
                        $tmp[$i + 1][1] = $matches[2] . $tmp[$i + 1][1];
                    }
                    else {
                        array_splice($tmp, $i + 1, 0, [[T_ENCAPSED_AND_WHITESPACE, $matches[2], null]]);
                    }
                }
                // php 7.3 において T_END_HEREDOC は必ず単一行になる
                if ($tmp[$i][0] === T_ENCAPSED_AND_WHITESPACE) {
                    if (($tmp[$i + 1][0] ?? null) === T_END_HEREDOC && preg_match('@^(\\s+)(.*)@um', $tmp[$i + 1][1], $matches)) {
                        $tmp[$i][1] = $tmp[$i][1] . $matches[1];
                        $tmp[$i + 1][1] = $matches[2];
                    }
                }
            }

            $tokens[] = $tmp[$i] + [3 => token_name($tmp[$i][0])];
        }

        // 最初のトークンでインデントレベルを導出
        $indent = '';
        $first = $tokens[0];
        if ($first[0] === T_WHITESPACE) {
            preg_match_all('@^[ \t]*$@um', $first[1], $matches);
            $max = '';
            foreach ($matches[0] as $match) {
                if ($max < strlen($match)) {
                    $max = $match;
                }
            }
            $indent = $max;
        }

        // 改行を置換してインデント
        $hereing = false;
        foreach ($tokens as $i => $token) {
            if ($options['heredoc']) {
                if ($token[0] === T_START_HEREDOC) {
                    $hereing = true;
                }
                if ($token[0] === T_END_HEREDOC) {
                    $hereing = false;
                }
            }
            if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true) || ($hereing && $token[0] === T_ENCAPSED_AND_WHITESPACE)) {
                $token[1] = preg_replace("@(\\R)$indent@um", '$1' . $options['indent'], $token[1]);
            }
            if ($options['trimempty']) {
                if ($token[0] === T_WHITESPACE) {
                    $token[1] = preg_replace("@(\\R)[ \\t]+(\\R)@um", '$1$2', $token[1]);
                }
            }

            $tokens[$i] = $token;
        }
        return implode('', array_column($tokens, 1));
    }
}
if (function_exists("ryunosuke\\NightDragon\\indent_php") && !defined("ryunosuke\\NightDragon\\indent_php")) {
    define("ryunosuke\\NightDragon\\indent_php", "ryunosuke\\NightDragon\\indent_php");
}

if (!isset($excluded_functions["throws"]) && (!function_exists("ryunosuke\\NightDragon\\throws") || (!false && (new \ReflectionFunction("ryunosuke\\NightDragon\\throws"))->isInternal()))) {
    /**
     * throw の関数版
     *
     * hoge() or throw などしたいことがまれによくあるはず。
     *
     * Example:
     * ```php
     * try {
     *     throws(new \Exception('throws'));
     * }
     * catch (\Exception $ex) {
     *     that($ex->getMessage())->isSame('throws');
     * }
     * ```
     *
     * @param \Exception $ex 投げる例外
     * @return mixed （`return hoge or throws` のようなコードで警告が出るので抑止用）
     */
    function throws($ex)
    {
        throw $ex;
    }
}
if (function_exists("ryunosuke\\NightDragon\\throws") && !defined("ryunosuke\\NightDragon\\throws")) {
    define("ryunosuke\\NightDragon\\throws", "ryunosuke\\NightDragon\\throws");
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
        if ($cachedir === null) {
            $cachedir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . strtr(__NAMESPACE__, ['\\' => '%']);
            cachedir($cachedir); // for mkdir
        }

        if ($dirname === null) {
            return $cachedir;
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
     * that($rand1)->isSame($rand2);
     * // $provider に null を与えると削除される
     * cache('rand', null);
     * $rand3 = cache('rand', $provider);
     * that($rand1)->isNotSame($rand3);
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
        $cacheobject = $cacheobject ?? new class(cachedir()) {
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
            if ($provider === null) {
                $cacheobject->clear();
            }
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
     * that(arrayval(123))->isSame([123]);
     * that(arrayval('str'))->isSame(['str']);
     * that(arrayval([123]))->isSame([123]); // 配列は配列のまま
     *
     * // $recursive = false にしない限り再帰的に適用される
     * $stdclass = stdclass(['key' => 'val']);
     * that(arrayval([$stdclass], true))->isSame([['key' => 'val']]); // true なので中身も配列化される
     * that(arrayval([$stdclass], false))->isSame([$stdclass]);       // false なので中身は変わらない
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

if (!isset($excluded_functions["is_empty"]) && (!function_exists("ryunosuke\\NightDragon\\is_empty") || (!false && (new \ReflectionFunction("ryunosuke\\NightDragon\\is_empty"))->isInternal()))) {
    /**
     * 値が空か検査する
     *
     * `empty` とほぼ同じ。ただし
     *
     * - string: "0"
     * - countable でない object
     * - countable である object で count() > 0
     *
     * は false 判定する。
     * ただし、 $empty_stcClass に true を指定すると「フィールドのない stdClass」も true を返すようになる。
     * これは stdClass の立ち位置はかなり特殊で「フィールドアクセスできる組み込み配列」のような扱いをされることが多いため。
     * （例えば `json_decode('{}')` は stdClass を返すが、このような状況は空判定したいことが多いだろう）。
     *
     * なお、関数の仕様上、未定義変数を true 判定することはできない。
     * 未定義変数をチェックしたい状況は大抵の場合コードが悪いが `$array['key1']['key2']` を調べたいことはある。
     * そういう時には使えない（?? する必要がある）。
     *
     * 「 `if ($var) {}` で十分なんだけど "0" が…」という状況はまれによくあるはず。
     *
     * Example:
     * ```php
     * // この辺は empty と全く同じ
     * that(is_empty(null))->isTrue();
     * that(is_empty(false))->isTrue();
     * that(is_empty(0))->isTrue();
     * that(is_empty(''))->isTrue();
     * // この辺だけが異なる
     * that(is_empty('0'))->isFalse();
     * // 第2引数に true を渡すと空の stdClass も empty 判定される
     * $stdclass = new \stdClass();
     * that(is_empty($stdclass, true))->isTrue();
     * // フィールドがあれば empty ではない
     * $stdclass->hoge = 123;
     * that(is_empty($stdclass, true))->isFalse();
     * ```
     *
     * @param mixed $var 判定する値
     * @param bool $empty_stdClass 空の stdClass を空とみなすか
     * @return bool 空なら true
     */
    function is_empty($var, $empty_stdClass = false)
    {
        // object は is_countable 次第
        if (is_object($var)) {
            // が、 stdClass だけは特別扱い（stdClass は継承もできるので、クラス名で判定する（継承していたらそれはもう stdClass ではないと思う））
            if ($empty_stdClass && get_class($var) === 'stdClass') {
                return !(array) $var;
            }
            if (is_countable($var)) {
                return !count($var);
            }
            return false;
        }

        // "0" は false
        if ($var === '0') {
            return false;
        }

        // 上記以外は empty に任せる
        return empty($var);
    }
}
if (function_exists("ryunosuke\\NightDragon\\is_empty") && !defined("ryunosuke\\NightDragon\\is_empty")) {
    define("ryunosuke\\NightDragon\\is_empty", "ryunosuke\\NightDragon\\is_empty");
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
     * that(is_primitive(null))->isTrue();
     * that(is_primitive(false))->isTrue();
     * that(is_primitive(123))->isTrue();
     * that(is_primitive(STDIN))->isTrue();
     * that(is_primitive(new \stdClass))->isFalse();
     * that(is_primitive(['array']))->isFalse();
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
     * that(is_arrayable([]))->isTrue();
     * that(is_arrayable(new \ArrayObject()))->isTrue();
     * that(is_arrayable(new \stdClass()))->isFalse();
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

if (!isset($excluded_functions["is_countable"]) && (!function_exists("ryunosuke\\NightDragon\\is_countable") || (!true && (new \ReflectionFunction("ryunosuke\\NightDragon\\is_countable"))->isInternal()))) {
    /**
     * 変数が count でカウントできるか調べる
     *
     * 要するに {@link http://php.net/manual/function.is-countable.php is_countable} の polyfill。
     *
     * Example:
     * ```php
     * that(is_countable([1, 2, 3]))->isTrue();
     * that(is_countable(new \ArrayObject()))->isTrue();
     * that(is_countable((function () { yield 1; })()))->isFalse();
     * that(is_countable(1))->isFalse();
     * that(is_countable(new \stdClass()))->isFalse();
     * ```
     *
     * @polyfill
     *
     * @param mixed $var 調べる値
     * @return bool count でカウントできるなら true
     */
    function is_countable($var)
    {
        return is_array($var) || $var instanceof \Countable;
    }
}
if (function_exists("ryunosuke\\NightDragon\\is_countable") && !defined("ryunosuke\\NightDragon\\is_countable")) {
    define("ryunosuke\\NightDragon\\is_countable", "ryunosuke\\NightDragon\\is_countable");
}

if (!isset($excluded_functions["var_export2"]) && (!function_exists("ryunosuke\\NightDragon\\var_export2") || (!false && (new \ReflectionFunction("ryunosuke\\NightDragon\\var_export2"))->isInternal()))) {
    /**
     * 組み込みの var_export をいい感じにしたもの
     *
     * 下記の点が異なる。
     *
     * - 配列は 5.4 以降のショートシンタックス（[]）で出力
     * - インデントは 4 固定
     * - ただの配列は1行（[1, 2, 3]）でケツカンマなし、連想配列は桁合わせインデントでケツカンマあり
     * - 文字列はダブルクオート
     * - null は null（小文字）
     * - 再帰構造を渡しても警告がでない（さらに NULL ではなく `'*RECURSION*'` という文字列になる）
     * - 配列の再帰構造の出力が異なる（Example参照）
     *
     * Example:
     * ```php
     * // 単純なエクスポート
     * that(var_export2(['array' => [1, 2, 3], 'hash' => ['a' => 'A', 'b' => 'B', 'c' => 'C']], true))->isSame('[
     *     "array" => [1, 2, 3],
     *     "hash"  => [
     *         "a" => "A",
     *         "b" => "B",
     *         "c" => "C",
     *     ],
     * ]');
     * // 再帰構造を含むエクスポート（標準の var_export は形式が異なる。 var_export すれば分かる）
     * $rarray = [];
     * $rarray['a']['b']['c'] = &$rarray;
     * $robject = new \stdClass();
     * $robject->a = new \stdClass();
     * $robject->a->b = new \stdClass();
     * $robject->a->b->c = $robject;
     * that(var_export2(compact('rarray', 'robject'), true))->isSame('[
     *     "rarray"  => [
     *         "a" => [
     *             "b" => [
     *                 "c" => "*RECURSION*",
     *             ],
     *         ],
     *     ],
     *     "robject" => stdClass::__set_state([
     *         "a" => stdClass::__set_state([
     *             "b" => stdClass::__set_state([
     *                 "c" => "*RECURSION*",
     *             ]),
     *         ]),
     *     ]),
     * ]');
     * ```
     *
     * @param mixed $value 出力する値
     * @param bool $return 返すなら true 出すなら false
     * @return string|null $return=true の場合は出力せず結果を返す
     */
    function var_export2($value, $return = false)
    {
        // インデントの空白数
        $INDENT = 4;

        // 再帰用クロージャ
        $export = function ($value, $nest = 0, $parents = []) use (&$export, $INDENT) {
            // 再帰を検出したら *RECURSION* とする（処理に関しては is_recursive のコメント参照）
            foreach ($parents as $parent) {
                if ($parent === $value) {
                    return $export('*RECURSION*');
                }
            }
            // 配列は連想判定したり再帰したり色々
            if (is_array($value)) {
                $spacer1 = str_repeat(' ', ($nest + 1) * $INDENT);
                $spacer2 = str_repeat(' ', $nest * $INDENT);

                $hashed = is_hasharray($value);

                // スカラー値のみで構成されているならシンプルな再帰
                if (!$hashed && array_all($value, is_primitive)) {
                    return '[' . implode(', ', array_map($export, $value)) . ']';
                }

                // 連想配列はキーを含めて桁あわせ
                if ($hashed) {
                    $keys = array_map($export, array_combine($keys = array_keys($value), $keys));
                    $maxlen = max(array_map('strlen', $keys));
                }
                $kvl = '';
                $parents[] = $value;
                foreach ($value as $k => $v) {
                    /** @noinspection PhpUndefinedVariableInspection */
                    $keystr = $hashed ? $keys[$k] . str_repeat(' ', $maxlen - strlen($keys[$k])) . ' => ' : '';
                    $kvl .= $spacer1 . $keystr . $export($v, $nest + 1, $parents) . ",\n";
                }
                return "[\n{$kvl}{$spacer2}]";
            }
            // オブジェクトは単にプロパティを __set_state する文字列を出力する
            elseif (is_object($value)) {
                $parents[] = $value;
                return get_class($value) . '::__set_state(' . $export(get_object_properties($value), $nest, $parents) . ')';
            }
            // 文字列はダブルクオート
            elseif (is_string($value)) {
                return '"' . addcslashes($value, "\$\"\0\\") . '"';
            }
            // null は小文字で居て欲しい
            elseif (is_null($value)) {
                return 'null';
            }
            // それ以外は標準に従う
            else {
                return var_export($value, true);
            }
        };

        // 結果を返したり出力したり
        $result = $export($value);
        if ($return) {
            return $result;
        }
        echo $result, "\n";
    }
}
if (function_exists("ryunosuke\\NightDragon\\var_export2") && !defined("ryunosuke\\NightDragon\\var_export2")) {
    define("ryunosuke\\NightDragon\\var_export2", "ryunosuke\\NightDragon\\var_export2");
}
