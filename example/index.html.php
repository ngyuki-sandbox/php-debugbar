<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <title></title>
</head>
<body id="body">
    <div>
        <form method="get" action="/">
            <button data-jquery="/ajax">jQuery</button>
            <button data-fetch="/ajax">fetch</button>
            <button formmethod="post">POST</button>
            <button formaction="/pdo">PDO</button>
            <button formaction="/doctrine">Doctrine</button>
            <button formaction="/exception">Exception</button>
            <button data-ajax="/exception">Ajax Exception</button>
            <button formaction="/download">Download</button>
        </form>
    </div>
    <?php if (isset($exception) && $exception instanceof Throwable): ?>
        <pre><?= $exception ?></pre>
    <?php endif; ?>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script>
        $(() => {
            $(document).on('click', '[data-jquery]', async (ev) => {
                ev.preventDefault();
                const url = ev.currentTarget.getAttribute('data-jquery');
                try {
                    await $.get(url);
                } catch (err) {
                    console.error(err);
                }
            });
            $(document).on('click', '[data-fetch]', async (ev) => {
                ev.preventDefault();
                const url = ev.currentTarget.getAttribute('data-fetch');
                try {
                    await fetch(url, { headers: { 'X-Requested-With': 'xmlhttprequest' }});
                } catch (err) {
                    console.error(err);
                }
            });
        });
    </script>
</body>
</html>
