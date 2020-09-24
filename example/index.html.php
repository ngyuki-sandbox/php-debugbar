<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <title></title>
</head>
<body id="body">
    <div>
        <form method="get" action="/">
            <button data-ajax="/ajax">Ajax</button>
            <button formmethod="post">POST</button>
            <button formaction="/pdo">PDO</button>
            <button formaction="/doctrine">Doctrine</button>
            <button formaction="/exception">Exception</button>
            <button data-ajax="/exception">Ajax Exception</button>
        </form>
    </div>
    <?php if (isset($exception) && $exception instanceof Throwable): ?>
        <pre><?= $exception ?></pre>
    <?php endif; ?>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script>
        $(() => {
            $(document).on('click', '[data-ajax]', async (ev) => {
                ev.preventDefault();
                const url = ev.currentTarget.getAttribute('data-ajax');
                try {
                    await $.get(url);
                } catch (err) {
                    console.error(err);
                }
            });
        });
    </script>
</body>
</html>
