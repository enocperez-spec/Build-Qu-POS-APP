<?php
declare(strict_types=1);

require_once __DIR__ . '/../WebApp/api/ReportService.php';

function securityExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$root = dirname(__DIR__);
$webRoot = $root . DIRECTORY_SEPARATOR . 'WebApp';
$htaccess = file_get_contents($webRoot . DIRECTORY_SEPARATOR . '.htaccess');
$authSource = file_get_contents($webRoot . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'AuthService.php');
$securitySource = file_get_contents($webRoot . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'SecurityService.php');
$appSource = file_get_contents($webRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'app.js');
$indexSource = file_get_contents($webRoot . DIRECTORY_SEPARATOR . 'index.php');

securityExpect(str_contains($htaccess, 'RewriteCond %{HTTPS} !=on'), 'HTTP requests must redirect to HTTPS.');
foreach (['Strict-Transport-Security', 'Content-Security-Policy', 'X-Content-Type-Options', 'X-Frame-Options', 'Referrer-Policy', 'Permissions-Policy'] as $header) {
    securityExpect(str_contains($htaccess, $header), "Missing required browser header: $header");
}
securityExpect(str_contains($htaccess, "script-src 'self'"), 'CSP must prohibit inline and third-party scripts.');
securityExpect(!str_contains($htaccess, "script-src 'self' 'unsafe-inline'"), 'CSP must not allow inline scripts.');
securityExpect(str_contains($authSource, "'secure' => true"), 'Session cookies must always use the Secure flag.');
securityExpect(str_contains($securitySource, 'FOR UPDATE'), 'Security counters must lock rows while incrementing.');
securityExpect(str_contains($securitySource, 'INSERT IGNORE INTO login_rate_limits'), 'Rate-limit rows must be initialized atomically.');
securityExpect(str_contains($appSource, 'tableCellHtml(cell)'), 'Table cells must pass through the safe renderer.');
securityExpect(!str_contains($appSource, '<td>${cell ?? ""}</td>'), 'Raw table-cell HTML sink is still present.');
securityExpect(str_contains($appSource, 'spreadsheetSafeText(value)'), 'Spreadsheet exports must neutralize formulas.');
securityExpect(!str_contains($indexSource, 'window.__QU_BOOTSTRAP__'), 'Bootstrap data must not use an inline executable script.');

foreach (['config-check.php', 'db-check.php', 'debug-log.php', 'mysqli-check.php', 'php-version.php', 'setup-check.php'] as $file) {
    securityExpect(!is_file($webRoot . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . $file), "Retired diagnostic endpoint remains: $file");
}

$payload = ['storeName' => '</script><script>alert("stored-xss")</script>', 'version' => '=2+2'];
$method = new ReflectionMethod(ReportService::class, 'renderReportHtml');
$html = $method->invoke(null, $payload);
securityExpect(str_contains($html, 'data-qu-report='), 'Generated reports must embed inert JSON data.');
securityExpect(!str_contains($html, 'window.__QU_REPORT__'), 'Generated reports must not create inline executable JSON.');
securityExpect(!str_contains($html, '</script><script>alert'), 'Generated report JSON can terminate a script element.');

echo "Web security regression tests passed.\n";
