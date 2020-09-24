# phpdebugbar 使ってみたメモ

## PSR-7/PSR-15 対応

- https://github.com/middlewares/debugbar
    - Ajax で OpenHandlerUrl が使えない
- https://github.com/php-middleware/phpdebugbar
    - リダイレクトも Ajax も対応していない

自作するのが良さそう。

## アセット

↑のパッケージでは `JavascriptRenderer::getBaseUrl()` と RequestURI を比較している。`vendor/maximebf/debugbar/` の中にあるアセットならそれで十分だけど、サードパーティのアセットはそれではロードできない。

`dumpCssAssets()` と `dumpJsAssets()` を使えばすべてのサードパーティも含めたすべてのアセットがサポートできる・・と思いきや、CSS で fontawesome のフォントが参照されており、それはアセットとしては定義されていないため `dumpCssAssets()` には含まれない。

フォントが Data スキームで埋め込まれていれば大丈夫なので、AsseticCollection でフォントを CSS に埋め込んでしまえば・・と思ったけど `url('../fonts/fontawesome-webfont.eot?v=4.7.0')` みたいなクエリを含む形式で参照されると埋め込めないっぽい。

以下の両方を独自に実装して対応するしかなさそう。

- `JavascriptRenderer::getBaseUrl()` と RequestURI を比較
- `getAssets()` でアセットのURLとパスを取得して URL -> パス のマッピングを作る

あるいはフォントさえどうにかできれば `dumpCssAssets()` と `dumpJsAssets()` でもどうにかなるのでフォントだけ特別扱いすれば大丈夫かもしれない。

## DatabaseCollector

Explain や Backtrace も表示できるデータベースコレクタを作ってみた。

debugbar に付属の PDOCollector だとコレクターが PDO インスタンスを持つ形なので使いにくい。
逆に PDO インスタンスがコレクターのインスタンスを持つようにした。

Doctrine を使っているプロジェクトがいくつかあるので、Doctrine との統合も実装。
ただ Doctrine の SQLLogger だと rowCount や error は取れない。

## SettingDataCollector

実行時にちょっとアプリの設定変えたいとき、いちいち local.php みたいなのをいじらなくても debugbar から json などで設定を指定できるようにするもの。

## ExceptionsCollector

ExceptionsCollector は次のようなパイプラインで使うのが良さそう。

- debugbar を有効にするミドルウェア
- catch-all ですべての例外を拾うエラーハンドラのミドルウェア
    - 開発時なら whoops とか
    - 本番ならよくあるエラーページ
- catch-all で ExceptionsCollector に例外を追加して re-throw するミドルウェア
- etc...

１つのミドルウェアで全部やると複雑になるので、3つのミドルウェアでそれぞれ役割を分割する。
