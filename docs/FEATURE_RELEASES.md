# Feature Releases

Current application version: `v002.02`

Version sequence: `v001.01` through `v001.09`, then `v002.00`, followed by
`v002.01`, `v002.02`, and so on.

Going forward, every approved and completed feature, improvement, bug fix, or
security change should add a new entry here and update the current application
version.

| Version | Release Date And Time | Title | Description | Type | Status |
| --- | --- | --- | --- | --- | --- |
| v001.01 | 2026-08-11 10:15:00 -04:00 | HTML5 Web Application Foundation | Created the IONOS-ready PHP and HTML5 web application foundation for uploading terminal CSV files, generating searchable QU POS reports, and displaying the latest dashboard report. | Feature | Released |
| v001.02 | 2026-08-11 10:35:00 -04:00 | CSV Upload History | Added database-backed CSV uploads so every uploaded file is retained as historical data instead of replacing the previous dataset. | Feature | Released |
| v001.03 | 2026-08-11 10:55:00 -04:00 | Current And Previous CSV Comparison | Added comparison logic so the newest CSV becomes the current dataset and the immediately previous upload becomes the comparison dataset. | Feature | Released |
| v001.04 | 2026-08-11 11:15:00 -04:00 | Protected Login And Two-Factor Authentication | Added protected sign-in, first-admin setup, authenticator-app two-factor verification, and QR-code setup support. | Security | Released |
| v001.05 | 2026-08-11 11:35:00 -04:00 | User Roles And User Management | Added Admin, Tech, and Read-Only roles with user creation, role assignment, activation, deactivation, deletion, and 2FA reset controls. | Feature | Released |
| v001.06 | 2026-08-11 11:55:00 -04:00 | Dashboard Historical Upload Selector | Added a dashboard dropdown for loading past CSV uploads while keeping the dashboard focused on the latest generated report by default. | Improvement | Released |
| v001.07 | 2026-08-11 12:20:00 -04:00 | Navigation And Logout Polish | Highlighted the active navigation page and moved Log Out to the top-right user area with compact red button styling. | Improvement | Released |
| v001.08 | 2026-08-11 13:05:00 -04:00 | Cloud Import Automation Endpoint | Added a protected cloud-import endpoint and GitHub Actions Playwright automation template for scheduled QU Admin terminal export and web-app import. | Feature | Released |
| v001.09 | 2026-08-11 14:25:00 -04:00 | Dashboard Metric Card Icons And Colors | Added icon-based dashboard metric cards with distinct colors for POS terminals, stable version, out-of-date stores, Kiosk versions, QuBox versions, and other versions. | Improvement | Released |
| v002.00 | 2026-08-11 14:45:00 -04:00 | Responsive Stable-Version Card | Improved dashboard card responsiveness so long stable-version values resize and stay inside the metric card on narrower screens. | Bug Fix | Released |
| v002.01 | 2026-08-11 15:30:05 -04:00 | GitHub README And Dashboard Screenshot | Updated the GitHub README with a full application overview, role descriptions, project layout, validation commands, and a dashboard screenshot. | Improvement | Released |
| v002.02 | 2026-08-11 16:25:49 -04:00 | Feature Release Tracking | Added a Feature Releases section and version-tracking history so each completed request can be recorded with version, date, title, description, change type, and release status. | Feature | Released |
