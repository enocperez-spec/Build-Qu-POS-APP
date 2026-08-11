<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QU POS Application Version Tools</title>
    <?php
        $assetVersion = static fn(string $path): string => is_file(__DIR__ . '/' . $path) ? (string)filemtime(__DIR__ . '/' . $path) : (string)time();
    ?>
    <link rel="stylesheet" href="assets/app.css?v=<?= htmlspecialchars($assetVersion('assets/app.css'), ENT_QUOTES) ?>">
</head>
<body>
    <script>
        window.__QU_BOOTSTRAP__ = {
            user: <?php
                require_once __DIR__ . '/api/AuthService.php';
                echo json_encode(Auth::currentUser());
    ?>
        };
    </script>
    <div id="app"></div>
    <script src="assets/qrcode.min.js?v=<?= htmlspecialchars($assetVersion('assets/qrcode.min.js'), ENT_QUOTES) ?>"></script>
    <script src="assets/app.js?v=<?= htmlspecialchars($assetVersion('assets/app.js'), ENT_QUOTES) ?>"></script>
</body>
</html>
