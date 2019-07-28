<html lang="ja">
<head>
    <title>{{ $title }}</title>
</head>
<body>
this is {{ $value }}
{{ $ex->getCode() }}
{{ $array[3] }}
@foreach ($array as $key => $value)
    @if ($key == 2)
        {{ $value }}
    @endif
@endforeach
</body>
</html>
