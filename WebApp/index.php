<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QU POS Application Version Tools</title>
    <link rel="stylesheet" href="assets/app.css">
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
    <script src="assets/qrcode.min.js"></script>
    <script src="assets/app.js"></script>
</body>
</html>
