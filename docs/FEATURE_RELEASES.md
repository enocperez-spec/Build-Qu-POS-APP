# Release Notes

Current application version: `v006.00`

Version sequence: `v001.01` through `v001.09`, then `v002.00`, followed by
`v002.00` through `v002.09`, then `v003.00` through `v003.09`, then `v004.00`
through `v004.09`, then `v005.00` through `v005.09`, then `v006.00` through
`v006.09`, then `v007.00`, and so on.

Going forward, every approved and completed feature, improvement, bug fix, or
security change should add a new entry here and update the current application
version.

Newest releases are listed first.

| Version | Release Date And Time | Type | Title | Description | Status |
| --- | --- | --- | --- | --- | --- |
| v006.00 | August 11, 2026 23:15 EST | Feature | Protected Store Status Lookup | Added a protected store-status lookup endpoint so support users and automation can verify a store's latest QU EI operational status by store name or store ID. | Released |
| v005.09 | 2026-08-12 00:10:00 -04:00 | Bug Fix | Store Export Selector Hardening | Updated the QU EI Store Information export automation to use the Stores page export test ID and pair export clicks with the download listener for clearer failures. | Released |
| v005.08 | 2026-08-12 00:00:00 -04:00 | Bug Fix | Store Export Menu Label Fix | Updated the QU EI Store Information export automation to click the current Export Stores Info menu label used on the Stores page. | Released |
| v005.07 | 2026-08-11 23:50:00 -04:00 | Bug Fix | Store Export Login Detection Fix | Fixed QU EI Store Information export automation so post-login page inputs are not mistaken for the login form after the What's New modal closes. | Released |
| v005.06 | August 11, 2026 23:15 EST | Bug Fix | Store Export Modal Close Reliability | Improved the QU EI Store Information export automation with multiple short-timeout close strategies for the What's New modal, including Escape and X-button fallbacks. | Released |
| v005.05 | 2026-08-11 23:30:00 -04:00 | Bug Fix | Store Export Modal Handling | Updated the cloud Store Information export automation to dismiss the QU EI What's New modal after login so scheduled store syncs can continue to the export action. | Released |
| v005.04 | 2026-08-11 23:15:00 -04:00 | Improvement | Store Status Badge Color Polish | Updated store status badge colors so Live displays in green and Not Operational displays in gray for clearer visual separation. | Released |
| v005.03 | 2026-08-11 23:05:00 -04:00 | Improvement | Store Operational Status Badges | Added store status indicators to version drill-down tables so each store can show Live, Not Operational, or No Store Data from the latest QU EI Store Information import. | Released |
| v005.02 | 2026-08-11 22:55:00 -04:00 | Improvement | Product-Specific Version Card Labels | Updated version detail cards so POS uses Terminals, while Kiosk, QuBox, QuKDS, and QuORB sections show product-specific device labels with live device and store counts. | Released |
| v005.01 | 2026-08-11 22:45:00 -04:00 | Improvement | Application Version Badge Colors | Applied the same stable, current, higher, and out-of-date version color coding used for QU POS to the Kiosk, QuBox, QuKDS, and QuORB version sections. | Released |
| v005.00 | 2026-08-11 22:35:00 -04:00 | Improvement | Production POS Version Label | Changed the dashboard section title from Downloadable QU POS Versions to Downloadable Production Qu POS Version for clearer production reporting. | Released |
| v004.09 | 2026-08-11 22:25:00 -04:00 | Improvement | Stable Version Adoption Dashboard | Updated the Kiosk, QuBox, QuKDS, and QuORB dashboard cards to display the current stable version and its usage percentage. The dashboard layout was also adjusted to display all seven metric cards in one row on standard desktop screens. | Released |
| v004.08 | 2026-08-11 22:05:00 -04:00 | Improvement | Dashboard Metric Card Alignment | Improved dashboard metric card alignment so labels, icons, values, and metadata stay grouped inside each card, with long version numbers kept together. | Released |
| v004.07 | 2026-08-11 21:50:00 -04:00 | Improvement | Compact Dashboard Sync Status Bar | Replaced the large dashboard sync and job health cards with a slimmer single-line status bar to reduce vertical space. | Released |
| v004.06 | 2026-08-11 21:35:00 -04:00 | Improvement | Remove Other Versions Dashboard Category | Removed the Other Versions dashboard card and Other Terminal Versions report section now that QuKDS and QuORB are split into their own categories. | Released |
| v004.05 | 2026-08-11 21:25:00 -04:00 | Improvement | QuKDS And QuORB Version Split | Split Other Terminal Versions into dedicated QuKDS and QuORB dashboard cards and report sections while keeping unmatched terminal versions in Other. | Released |
| v004.04 | 2026-08-11 21:05:00 -04:00 | Improvement | Dashboard Trend Label Cleanup | Removed the visual trend chips from the Current Stable Version and Out-Of-Date Stores dashboard cards for a cleaner card layout. | Released |
| v004.03 | 2026-08-11 20:55:00 -04:00 | Improvement | Cleaner Dashboard Trend Wording | Improved the stable usage and out-of-date stores trend labels so unchanged, improved, and increased states read more clearly on the dashboard cards. | Released |
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
