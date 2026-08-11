# QU POS Web App

IONOS-friendly HTML5 + PHP version of the QU POS report tool.

## What It Does

- Upload current terminal CSV.
- Optionally upload previous CSV for comparison.
- Generates Current Versions, Stores Version Report, Alerts, and Comparison sections.
- Saves generated HTML reports under `data/reports/YYYY-MM-DD`.
- Lists saved reports from MariaDB when configured, otherwise from saved report files.

## Database

The app supports MariaDB report history. Copy `config.example.php` to `config.php`
or create the server-side local config used by your deployment process, then fill in
the database host, name, user, and password outside of GitHub.

## Local Credential Setup

Run this once before SFTP deployment:

```powershell
powershell.exe -ExecutionPolicy Bypass -File "E:\PowerShell\QU_App\AppData\Deployment\Save-IonosSftpCredential.ps1"
```

## Package

```powershell
powershell.exe -ExecutionPolicy Bypass -File "E:\PowerShell\QU_App\AppData\Deployment\New-WebAppPackage.ps1"
```

## Deploy

```powershell
powershell.exe -ExecutionPolicy Bypass -File "E:\PowerShell\QU_App\AppData\Deployment\Deploy-IonosWebApp.ps1" -RemotePath "/"
```

If IONOS uses a web root such as `/htdocs`, deploy with:

```powershell
powershell.exe -ExecutionPolicy Bypass -File "E:\PowerShell\QU_App\AppData\Deployment\Deploy-IonosWebApp.ps1" -RemotePath "/htdocs"
```
