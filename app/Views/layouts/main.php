<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($framework ?? 'Ract') ?></title>
    <style>
        :root { color-scheme: dark; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        body { margin: 0; background: #090d18; color: #dce6ff; }
        main { width: min(760px, calc(100% - 3rem)); margin: 10vh auto; }
        .card { padding: 2.5rem; border: 1px solid #293451; border-radius: 1rem; background: #11182a; box-shadow: 0 1rem 4rem #0006; }
        h1 { margin-top: 0; color: #fff; font-size: clamp(2.25rem, 8vw, 4.5rem); }
        p { color: #aebddd; line-height: 1.7; }
        code { color: #8ee8c6; background: #080c15; border-radius: .35rem; padding: .2rem .45rem; }
        a { color: #8ee8c6; }
        .badge { display: inline-block; padding: .35rem .7rem; border-radius: 2rem; background: #183b36; color: #8ee8c6; }
    </style>
</head>
<body>
<main>
    <?= $content ?>
</main>
</body>
</html>
