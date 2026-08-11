# Release Notes

Current application version: `v004.02`

Version sequence: `v001.01` through `v001.09`, then `v002.00`, followed by
`v002.00` through `v002.09`, then `v003.00` through `v003.09`, then `v004.00`,
`v004.01`, `v004.02`, and so on.

Going forward, every approved and completed feature, improvement, bug fix, or
security change should add a new entry here and update the current application
version.

Newest releases are listed first.

| Version | Release Date And Time | Type | Title | Description | Status |
| --- | --- | --- | --- | --- | --- |
| v004.02 | 2026-08-11 20:35:00 -04:00 | Improvement | Polished Header User Profile Card | Updated the top-right dashboard user area with a polished profile card showing initials, username, role, dashboard last updated time, and the Log Out action. | Released |
| v004.01 | 2026-08-11 20:15:00 -04:00 | Improvement | Clickable Dashboard Metric Cards | Made dashboard metric cards clickable so users can jump directly to filtered out-of-date stores, POS, Kiosk, QuBox, and other version details. | Released |
| v004.00 | 2026-08-11 19:55:00 -04:00 | Improvement | Dashboard Trend Indicators | Added trend labels to dashboard metric cards showing whether out-of-date stores and stable-version usage improved compared with the previous upload. | Released |
| v003.09 | 2026-08-11 19:35:00 -04:00 | Improvement | Dashboard Data Health Summary | Added dashboard health cards for latest terminal sync, latest store sync, store data volume, and QU EI automation status so users can quickly confirm data freshness. | Released |
| v003.08 | 2026-08-11 19:15:00 -04:00 | Feature | QU EI Store Data Synchronization | Added SQL storage, API logging, schedule entries, and cloud automation support for exporting Store Information from QU EI and importing the latest store data. | Released |
| v003.07 | 2026-08-11 18:55:00 -04:00 | Bug Fix | Production Asset Cache Busting | Added automatic cache-busting query strings to CSS and JavaScript assets so browsers load newly deployed dashboard and Settings updates immediately. | Released |
| v003.06 | 2026-08-11 18:38:00 -04:00 | Feature | Admin Settings And Role-Based Navigation Management | Added an Admin-only Settings section containing Users, User Roles, API Logs, and API Call Times. Added editable role permissions, QU EI schedule management, and automatic permission registration for navigation sections. | Released |
| v003.05 | 2026-08-11 18:12:48 -04:00 | Improvement | Modern Logout Button Styling | Updated the Log Out button with a brighter red pill style, stronger contrast, subtle glow, and modern hover interaction. | Released |
| v003.04 | 2026-08-11 18:11:39 -04:00 | Improvement | Footer-Only Release Notes Access | Removed Release Notes from the left navigation while keeping release notes available through the clickable footer version. | Released |
| v003.03 | 2026-08-11 18:10:09 -04:00 | Security | Admin-Only Users Navigation | Reinforced Users page access so only Admin users can see or open user management, while Tech and Read-Only roles are redirected away. | Released |
| v003.02 | 2026-08-11 18:06:43 -04:00 | Improvement | Upload Page Report Cleanup | Removed the dashboard/report view from the Upload CSV page so the section only shows upload controls and CSV Upload History. | Released |
| v003.01 | 2026-08-11 17:48:33 -04:00 | Bug Fix | QU Export Readiness Detection | Improved the QU Admin automation to wait for the actual login form or Actions button instead of relying on URL timing during redirects. | Released |
| v003.00 | 2026-08-11 17:45:44 -04:00 | Bug Fix | QU Export Target Page Detection | Fixed the GitHub Actions QU export automation so it stops retrying login after successfully reaching the terminals page. | Released |
| v002.09 | 2026-08-11 17:36:33 -04:00 | Bug Fix | QU Admin Login Automation Fix | Improved the GitHub Actions QU Admin login flow to wait for the login form, fill visible username and password fields, and confirm login before searching for the Actions button. | Released |
| v002.08 | 2026-08-11 17:21:32 -04:00 | Improvement | QU Export Failure Diagnostics | Added GitHub Actions failure artifacts for the QU Admin export automation, including screenshot, page HTML, URL, page title, and error summary. | Released |
| v002.07 | 2026-08-11 16:41:10 -04:00 | Improvement | Dashboard Last Updated Header | Replaced the current date and time under the username with a Dashboard Last Updated timestamp based on the loaded report generation time. | Released |
| v002.06 | 2026-08-11 16:38:47 -04:00 | Feature | GitHub Actions CSV Automation | Added a scheduled GitHub Actions workflow that exports the QU Admin terminal CSV every 6 hours and imports it into the web app through the protected cloud-import endpoint. | Released |
| v002.05 | 2026-08-11 16:37:28 -04:00 | Improvement | Header Version Badge Cleanup | Removed the application version badge from the top page header while keeping the clickable footer version link for release notes. | Released |
| v002.04 | 2026-08-11 16:35:41 -04:00 | Improvement | Legacy Windows Tool Cleanup | Removed legacy PowerShell and HTA launcher files from the GitHub project so the repository reflects the web application codebase. | Released |
| v002.03 | 2026-08-11 16:32:31 -04:00 | Improvement | Application Footer And Release Notes | Added a consistent footer with clickable application version, a dedicated Release Notes page, newest-first release history, and a Back to Application button. | Released |
| v002.02 | 2026-08-11 16:25:49 -04:00 | Feature | Feature Release Tracking | Added a Feature Releases section and version-tracking history so each completed request can be recorded with version, date, title, description, change type, and release status. | Released |
| v002.01 | 2026-08-11 15:30:05 -04:00 | Improvement | GitHub README And Dashboard Screenshot | Updated the GitHub README with a full application overview, role descriptions, project layout, validation commands, and a dashboard screenshot. | Released |
| v002.00 | 2026-08-11 14:45:00 -04:00 | Bug Fix | Responsive Stable-Version Card | Improved dashboard card responsiveness so long stable-version values resize and stay inside the metric card on narrower screens. | Released |
| v001.09 | 2026-08-11 14:25:00 -04:00 | Improvement | Dashboard Metric Card Icons And Colors | Added icon-based dashboard metric cards with distinct colors for POS terminals, stable version, out-of-date stores, Kiosk versions, QuBox versions, and other versions. | Released |
| v001.08 | 2026-08-11 13:05:00 -04:00 | Feature | Cloud Import Automation Endpoint | Added a protected cloud-import endpoint and GitHub Actions Playwright automation template for scheduled QU Admin terminal export and web-app import. | Released |
| v001.07 | 2026-08-11 12:20:00 -04:00 | Improvement | Navigation And Logout Polish | Highlighted the active navigation page and moved Log Out to the top-right user area with compact red button styling. | Released |
| v001.06 | 2026-08-11 11:55:00 -04:00 | Improvement | Dashboard Historical Upload Selector | Added a dashboard dropdown for loading past CSV uploads while keeping the dashboard focused on the latest generated report by default. | Released |
| v001.05 | 2026-08-11 11:35:00 -04:00 | Feature | User Roles And User Management | Added Admin, Tech, and Read-Only roles with user creation, role assignment, activation, deactivation, deletion, and 2FA reset controls. | Released |
| v001.04 | 2026-08-11 11:15:00 -04:00 | Security | Protected Login And Two-Factor Authentication | Added protected sign-in, first-admin setup, authenticator-app two-factor verification, and QR-code setup support. | Released |
| v001.03 | 2026-08-11 10:55:00 -04:00 | Feature | Current And Previous CSV Comparison | Added comparison logic so the newest CSV becomes the current dataset and the immediately previous upload becomes the comparison dataset. | Released |
| v001.02 | 2026-08-11 10:35:00 -04:00 | Feature | CSV Upload History | Added database-backed CSV uploads so every uploaded file is retained as historical data instead of replacing the previous dataset. | Released |
| v001.01 | 2026-08-11 10:15:00 -04:00 | Feature | HTML5 Web Application Foundation | Created the IONOS-ready PHP and HTML5 web application foundation for uploading terminal CSV files, generating searchable QU POS reports, and displaying the latest dashboard report. | Released |
