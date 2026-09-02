<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>500 | Ract</title>
</head>
<body>
    <main>
        <h1>Application error</h1>
        <p><?= e($message) ?></p>
        <?php if ($exception !== null): ?>
            <pre><?= e($exception) ?></pre>
        <?php endif; ?>
    </main>
</body>
</html>
