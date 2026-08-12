<?php
declare(strict_types=1);

require_once __DIR__ . '/api/AuthService.php';

$assetVersion = static fn(string $path): string => is_file(__DIR__ . '/' . $path)
    ? (string)filemtime(__DIR__ . '/' . $path)
    : (string)time();
$bootstrapJson = json_encode(
    ['user' => Auth::currentUser()],
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?: '{"user":null}';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>QU POS Application Version Tools</title>
    <link rel="stylesheet" href="assets/app.css?v=<?= htmlspecialchars($assetVersion('assets/app.css'), ENT_QUOTES) ?>">
</head>
<body>
    <div id="app" data-qu-bootstrap="<?= htmlspecialchars($bootstrapJson, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></div>
    <script src="assets/qrcode.min.js?v=<?= htmlspecialchars($assetVersion('assets/qrcode.min.js'), ENT_QUOTES) ?>"></script>
    <script src="assets/app.js?v=<?= htmlspecialchars($assetVersion('assets/app.js'), ENT_QUOTES) ?>"></script>
</body>
</html>
