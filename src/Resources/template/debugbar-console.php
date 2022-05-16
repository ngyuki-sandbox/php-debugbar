<?php
declare(strict_types = 1);
/* @var App\Component\DebugBar\Utils\ConsoleRenderer $this */
?>
<html lang="ja">
<head>
    <title>php debugbar console</title>
    <style>
        body {
            overflow-y: hidden;
        }
        .phpdebugbar {
            top: 0 !important;
            bottom: 0 !important;
            left: 0 !important;
            right: 0 !important;
        }
        .phpdebugbar-body {
            height: auto !important;
        }
        .phpdebugbar-close-btn {
            display: none !important;
        }
    </style>
</head>
<body>
<?php echo $this->middleware->renderHead() ?>
<script>
    // この画面での DebugBar の高さの保存や復元を行わない
    // デフォだと DebugBar の高さが LocalStorage に保存・復元されている
    // この画面は固定で全画面で表示するため、そのままだと全画面のサイズが保存されてしまい
    // 他の通常の DebugBar が表示される画面で不自然に大きく表示されてしまう
    PhpDebugBar.DebugBar.prototype.setHeight = () => {};
    PhpDebugBar.DebugBar.prototype.restoreState = () => {};

    // 自ページ以外の Ajax の通知も受け取る
    history.pushState({}, '', '#php-debugbar-console');
</script>
<?php echo $this->middleware->renderBody() ?>
</body>
</html>
