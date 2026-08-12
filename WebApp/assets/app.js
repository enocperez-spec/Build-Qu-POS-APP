const state = {
    report: window.__QU_REPORT__ || null,
    user: window.__QU_BOOTSTRAP__?.user || null,
    needsSetup: false,
    authStatusError: "",
    pendingTwoFactor: null,
    htmlUrl: null,
    reports: [],
    uploads: [],
    selectedUploadId: "",
    dashboardHealth: null,
    activeTab: "current",
    settingsTab: "users",
    currentPage: "dashboard",
    releaseNotesReturnPage: "dashboard",
};

const FEATURE_RELEASES = [
    {
        version: "v001.01",
        releasedAt: "2026-08-11 10:15:00 -04:00",
        title: "HTML5 Web Application Foundation",
        description: "Created the IONOS-ready PHP and HTML5 web application foundation for uploading terminal CSV files, generating searchable QU POS reports, and displaying the latest dashboard report.",
        type: "Feature",
        status: "Released",
    },
    {
        version: "v001.02",
        releasedAt: "2026-08-11 10:35:00 -04:00",
        title: "CSV Upload History",
        description: "Added database-backed CSV uploads so every uploaded file is retained as historical data instead of replacing the previous dataset.",
        type: "Feature",
        status: "Released",
    },
    {
        version: "v001.03",
        releasedAt: "2026-08-11 10:55:00 -04:00",
        title: "Current And Previous CSV Comparison",
        description: "Added comparison logic so the newest CSV becomes the current dataset and the immediately previous upload becomes the comparison dataset.",
        type: "Feature",
        status: "Released",
    },
    {
        version: "v001.04",
        releasedAt: "2026-08-11 11:15:00 -04:00",
        title: "Protected Login And Two-Factor Authentication",
        description: "Added protected sign-in, first-admin setup, authenticator-app two-factor verification, and QR-code setup support.",
        type: "Security",
        status: "Released",
    },
    {
        version: "v001.05",
        releasedAt: "2026-08-11 11:35:00 -04:00",
        title: "User Roles And User Management",
        description: "Added Admin, Tech, and Read-Only roles with user creation, role assignment, activation, deactivation, deletion, and 2FA reset controls.",
        type: "Feature",
        status: "Released",
    },
    {
        version: "v001.06",
        releasedAt: "2026-08-11 11:55:00 -04:00",
        title: "Dashboard Historical Upload Selector",
        description: "Added a dashboard dropdown for loading past CSV uploads while keeping the dashboard focused on the latest generated report by default.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v001.07",
        releasedAt: "2026-08-11 12:20:00 -04:00",
        title: "Navigation And Logout Polish",
        description: "Highlighted the active navigation page and moved Log Out to the top-right user area with compact red button styling.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v001.08",
        releasedAt: "2026-08-11 13:05:00 -04:00",
        title: "Cloud Import Automation Endpoint",
        description: "Added a protected cloud-import endpoint and GitHub Actions Playwright automation template for scheduled QU Admin terminal export and web-app import.",
        type: "Feature",
        status: "Released",
    },
    {
        version: "v001.09",
        releasedAt: "2026-08-11 14:25:00 -04:00",
        title: "Dashboard Metric Card Icons And Colors",
        description: "Added icon-based dashboard metric cards with distinct colors for POS terminals, stable version, out-of-date stores, Kiosk versions, QuBox versions, and other versions.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v002.00",
        releasedAt: "2026-08-11 14:45:00 -04:00",
        title: "Responsive Stable-Version Card",
        description: "Improved dashboard card responsiveness so long stable-version values resize and stay inside the metric card on narrower screens.",
        type: "Bug Fix",
        status: "Released",
    },
    {
        version: "v002.01",
        releasedAt: "2026-08-11 15:30:05 -04:00",
        title: "GitHub README And Dashboard Screenshot",
        description: "Updated the GitHub README with a full application overview, role descriptions, project layout, validation commands, and a dashboard screenshot.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v002.02",
        releasedAt: "2026-08-11 16:25:49 -04:00",
        title: "Feature Release Tracking",
        description: "Added a Feature Releases section and version-tracking history so each completed request can be recorded with version, date, title, description, change type, and release status.",
        type: "Feature",
        status: "Released",
    },
    {
        version: "v002.03",
        releasedAt: "2026-08-11 16:32:31 -04:00",
        title: "Application Footer And Release Notes",
        description: "Added a consistent footer with clickable application version, a dedicated Release Notes page, newest-first release history, and a Back to Application button.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v002.04",
        releasedAt: "2026-08-11 16:35:41 -04:00",
        title: "Legacy Windows Tool Cleanup",
        description: "Removed legacy PowerShell and HTA launcher files from the GitHub project so the repository reflects the web application codebase.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v002.05",
        releasedAt: "2026-08-11 16:37:28 -04:00",
        title: "Header Version Badge Cleanup",
        description: "Removed the application version badge from the top page header while keeping the clickable footer version link for release notes.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v002.06",
        releasedAt: "2026-08-11 16:38:47 -04:00",
        title: "GitHub Actions CSV Automation",
        description: "Added a scheduled GitHub Actions workflow that exports the QU Admin terminal CSV every 6 hours and imports it into the web app through the protected cloud-import endpoint.",
        type: "Feature",
        status: "Released",
    },
    {
        version: "v002.07",
        releasedAt: "2026-08-11 16:41:10 -04:00",
        title: "Dashboard Last Updated Header",
        description: "Replaced the current date and time under the username with a Dashboard Last Updated timestamp based on the loaded report generation time.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v002.08",
        releasedAt: "2026-08-11 17:21:32 -04:00",
        title: "QU Export Failure Diagnostics",
        description: "Added GitHub Actions failure artifacts for the QU Admin export automation, including screenshot, page HTML, URL, page title, and error summary.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v002.09",
        releasedAt: "2026-08-11 17:36:33 -04:00",
        title: "QU Admin Login Automation Fix",
        description: "Improved the GitHub Actions QU Admin login flow to wait for the login form, fill visible username and password fields, and confirm login before searching for the Actions button.",
        type: "Bug Fix",
        status: "Released",
    },
    {
        version: "v003.00",
        releasedAt: "2026-08-11 17:45:44 -04:00",
        title: "QU Export Target Page Detection",
        description: "Fixed the GitHub Actions QU export automation so it stops retrying login after successfully reaching the terminals page.",
        type: "Bug Fix",
        status: "Released",
    },
    {
        version: "v003.01",
        releasedAt: "2026-08-11 17:48:33 -04:00",
        title: "QU Export Readiness Detection",
        description: "Improved the QU Admin automation to wait for the actual login form or Actions button instead of relying on URL timing during redirects.",
        type: "Bug Fix",
        status: "Released",
    },
    {
        version: "v003.02",
        releasedAt: "2026-08-11 18:06:43 -04:00",
        title: "Upload Page Report Cleanup",
        description: "Removed the dashboard/report view from the Upload CSV page so the section only shows upload controls and CSV Upload History.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v003.03",
        releasedAt: "2026-08-11 18:10:09 -04:00",
        title: "Admin-Only Users Navigation",
        description: "Reinforced Users page access so only Admin users can see or open user management, while Tech and Read-Only roles are redirected away.",
        type: "Security",
        status: "Released",
    },
    {
        version: "v003.04",
        releasedAt: "2026-08-11 18:11:39 -04:00",
        title: "Footer-Only Release Notes Access",
        description: "Removed Release Notes from the left navigation while keeping release notes available through the clickable footer version.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v003.05",
        releasedAt: "2026-08-11 18:12:48 -04:00",
        title: "Modern Logout Button Styling",
        description: "Updated the Log Out button with a brighter red pill style, stronger contrast, subtle glow, and modern hover interaction.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v003.06",
        releasedAt: "2026-08-11 18:38:00 -04:00",
        title: "Admin Settings And Role-Based Navigation Management",
        description: "Added an Admin-only Settings section containing Users, User Roles, API Logs, and API Call Times. Added editable role permissions, QU EI schedule management, and automatic permission registration for navigation sections.",
        type: "Feature",
        status: "Released",
    },
    {
        version: "v003.07",
        releasedAt: "2026-08-11 18:55:00 -04:00",
        title: "Production Asset Cache Busting",
        description: "Added automatic cache-busting query strings to CSS and JavaScript assets so browsers load newly deployed dashboard and Settings updates immediately.",
        type: "Bug Fix",
        status: "Released",
    },
    {
        version: "v003.08",
        releasedAt: "2026-08-11 19:15:00 -04:00",
        title: "QU EI Store Data Synchronization",
        description: "Added SQL storage, API logging, schedule entries, and cloud automation support for exporting Store Information from QU EI and importing the latest store data.",
        type: "Feature",
        status: "Released",
    },
    {
        version: "v003.09",
        releasedAt: "2026-08-11 19:35:00 -04:00",
        title: "Dashboard Data Health Summary",
        description: "Added dashboard health cards for latest terminal sync, latest store sync, store data volume, and QU EI automation status so users can quickly confirm data freshness.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v004.00",
        releasedAt: "2026-08-11 19:55:00 -04:00",
        title: "Dashboard Trend Indicators",
        description: "Added trend labels to dashboard metric cards showing whether out-of-date stores and stable-version usage improved compared with the previous upload.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v004.01",
        releasedAt: "2026-08-11 20:15:00 -04:00",
        title: "Clickable Dashboard Metric Cards",
        description: "Made dashboard metric cards clickable so users can jump directly to filtered out-of-date stores, POS, Kiosk, QuBox, and other version details.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v004.02",
        releasedAt: "2026-08-11 20:35:00 -04:00",
        title: "Polished Header User Profile Card",
        description: "Updated the top-right dashboard user area with a polished profile card showing initials, username, role, dashboard last updated time, and the Log Out action.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v004.03",
        releasedAt: "2026-08-11 20:55:00 -04:00",
        title: "Cleaner Dashboard Trend Wording",
        description: "Improved the stable usage and out-of-date stores trend labels so unchanged, improved, and increased states read more clearly on the dashboard cards.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v004.04",
        releasedAt: "2026-08-11 21:05:00 -04:00",
        title: "Dashboard Trend Label Cleanup",
        description: "Removed the visual trend chips from the Current Stable Version and Out-Of-Date Stores dashboard cards for a cleaner card layout.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v004.05",
        releasedAt: "2026-08-11 21:25:00 -04:00",
        title: "QuKDS And QuORB Version Split",
        description: "Split Other Terminal Versions into dedicated QuKDS and QuORB dashboard cards and report sections while keeping unmatched terminal versions in Other.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v004.06",
        releasedAt: "2026-08-11 21:35:00 -04:00",
        title: "Remove Other Versions Dashboard Category",
        description: "Removed the Other Versions dashboard card and Other Terminal Versions report section now that QuKDS and QuORB are split into their own categories.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v004.07",
        releasedAt: "2026-08-11 21:50:00 -04:00",
        title: "Compact Dashboard Sync Status Bar",
        description: "Replaced the large dashboard sync and job health cards with a slimmer single-line status bar to reduce vertical space.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v004.08",
        releasedAt: "2026-08-11 22:05:00 -04:00",
        title: "Dashboard Metric Card Alignment",
        description: "Improved dashboard metric card alignment so labels, icons, values, and metadata stay grouped inside each card, with long version numbers kept together.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v004.09",
        releasedAt: "2026-08-11 22:25:00 -04:00",
        title: "Stable Version Adoption Dashboard",
        description: "Updated the Kiosk, QuBox, QuKDS, and QuORB dashboard cards to display the current stable version and its usage percentage. The dashboard layout was also adjusted to display all seven metric cards in one row on standard desktop screens.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v005.00",
        releasedAt: "2026-08-11 22:35:00 -04:00",
        title: "Production POS Version Label",
        description: "Changed the dashboard section title from Downloadable QU POS Versions to Downloadable Production Qu POS Version for clearer production reporting.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v005.01",
        releasedAt: "2026-08-11 22:45:00 -04:00",
        title: "Application Version Badge Colors",
        description: "Applied the same stable, current, higher, and out-of-date version color coding used for QU POS to the Kiosk, QuBox, QuKDS, and QuORB version sections.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v005.02",
        releasedAt: "2026-08-11 22:55:00 -04:00",
        title: "Product-Specific Version Card Labels",
        description: "Updated version detail cards so POS uses Terminals, while Kiosk, QuBox, QuKDS, and QuORB sections show product-specific device labels with live device and store counts.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v005.03",
        releasedAt: "2026-08-11 23:05:00 -04:00",
        title: "Store Operational Status Badges",
        description: "Added store status indicators to version drill-down tables so each store can show Live, Not Operational, or No Store Data from the latest QU EI Store Information import.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v005.04",
        releasedAt: "2026-08-11 23:15:00 -04:00",
        title: "Store Status Badge Color Polish",
        description: "Updated store status badge colors so Live displays in green and Not Operational displays in gray for clearer visual separation.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v005.05",
        releasedAt: "2026-08-11 23:30:00 -04:00",
        title: "Store Export Modal Handling",
        description: "Updated the cloud Store Information export automation to dismiss the QU EI What's New modal after login so scheduled store syncs can continue to the export action.",
        type: "Bug Fix",
        status: "Released",
    },
    {
        version: "v005.06",
        releasedAt: "2026-08-11 23:40:00 -04:00",
        title: "Store Export Modal Close Reliability",
        description: "Improved the QU EI Store Information export automation with multiple short-timeout close strategies for the What's New modal, including Escape and X-button fallbacks.",
        type: "Bug Fix",
        status: "Released",
    },
];

const APP_VERSION = FEATURE_RELEASES[FEATURE_RELEASES.length - 1].version;

const app = document.getElementById("app");

function escapeHtml(value) {
    return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function versionStatus(version, report, baseline = null) {
    const stable = baseline?.stableVersion || report?.summary?.currentStableVersion;
    const current = baseline?.currentVersion || report?.summary?.mostCurrentVersion;
    if (!version || !stable || stable === "N/A") return "neutral";
    if (version === stable) return "stable";
    if (version === current) return "current";
    if (/^\d+(?:[.-]\d+)+$/.test(version)) {
        return compareVersions(version, stable) < 0 ? "outdated" : "higher";
    }
    return "neutral";
}

function compareVersions(a, b) {
    const left = String(a).split(/[.-]/).map(Number);
    const right = String(b).split(/[.-]/).map(Number);
    for (let i = 0; i < Math.max(left.length, right.length); i++) {
        const diff = (left[i] || 0) - (right[i] || 0);
        if (diff !== 0) return diff;
    }
    return 0;
}

function badge(version, report, baseline = null) {
    return `<span class="badge ${versionStatus(version, report, baseline)}">${escapeHtml(version)}</span>`;
}

function versionBaseline(report, versions, appType = "pos") {
    const stableByType = {
        pos: report?.summary?.currentStableVersion,
        kiosk: report?.summary?.kioskStableVersion,
        qubox: report?.summary?.quboxStableVersion,
        qukds: report?.summary?.qukdsStableVersion,
        quorb: report?.summary?.quorbStableVersion,
    };
    return {
        stableVersion: stableByType[appType] || "N/A",
        currentVersion: mostCurrentVersion(versions),
    };
}

function mostCurrentVersion(versions) {
    return (versions || [])
        .map(item => item.version)
        .filter(version => /^\d+(?:[.-]\d+)+$/.test(version))
        .sort(compareVersions)
        .at(-1) || "N/A";
}

function deviceLabel(appType = "pos") {
    const labels = {
        pos: "Terminals",
        kiosk: "Kiosk",
        qubox: "QuBox",
        qukds: "QuKDS",
        quorb: "QuORB",
    };
    return labels[appType] || "Devices";
}

function storeStatusBadge(status) {
    const label = String(status || "No Store Data").trim() || "No Store Data";
    const tone = label.toLowerCase().includes("not operational")
        ? "not-operational"
        : label.toLowerCase().includes("live")
            ? "live"
            : "unknown";
    return `<span class="store-status-badge ${tone}">${escapeHtml(label)}</span>`;
}

function canManageUsers() {
    return state.user?.role === "admin";
}

function canGenerateReports() {
    return canAccess("upload") && ["admin", "tech"].includes(state.user?.role);
}

function canAccess(section) {
    if (!state.user) return false;
    if (state.user.role === "admin") return true;
    const fallback = {
        tech: { dashboard: true, reports: true, upload: true, alerts: true, settings: false },
        read_only: { dashboard: true, reports: true, upload: false, alerts: true, settings: false },
    };
    return !!(state.user.permissions?.[section] ?? fallback[state.user.role]?.[section]);
}

function canManageSettings() {
    return state.user?.role === "admin" && canAccess("settings");
}

function roleLabel(role) {
    if (role === "admin") return "Admin";
    if (role === "tech") return "Tech";
    return "Read-Only";
}

function dashboardLastUpdatedLabel() {
    return state.report?.generatedOn || "Not loaded yet";
}

function refreshHeaderLastUpdated() {
    const element = document.getElementById("dashboardLastUpdated");
    if (element) element.textContent = dashboardLastUpdatedLabel();
}

function navClass(page) {
    return `nav-item nav-button${state.currentPage === page ? " active" : ""}`;
}

function shell(content, page = state.currentPage || "dashboard") {
    state.currentPage = page;
    const uploadNav = canAccess("upload") ? `<button class="${navClass("upload")}" id="uploadNavBtn">Upload CSV</button>` : "";
    const settingsNav = canManageSettings() ? `<button class="${navClass("settings")}" id="settingsNavBtn">Settings</button>` : "";
    return `
        <div class="app-shell">
            <aside class="sidebar">
                <img class="brand-logo" src="assets/goto-foods-white-logo.png" alt="GoTo Foods">
                ${canAccess("dashboard") ? `<button class="${navClass("dashboard")}" id="dashboardNavBtn">Dashboard</button>` : ""}
                ${canAccess("reports") ? `<button class="${navClass("reports")}" id="reportsNavBtn">View Reports</button>` : ""}
                ${uploadNav}
                ${canAccess("alerts") ? `<button class="${navClass("alerts")}" id="alertsNavBtn">Alerts</button>` : ""}
                ${settingsNav}
            </aside>
            <main class="main">${content}${footer()}</main>
        </div>`;
}

function footer() {
    return `
        <footer class="app-footer">
            <span>Copyright © GoTo Foods | Version </span>
            <button class="footer-version" id="footerVersionBtn" type="button">${escapeHtml(APP_VERSION)}</button>
        </footer>`;
}

function header() {
    return `
        <div class="topbar">
            <div class="title-row">
                <div class="app-icon">&lt;/&gt;</div>
                <div>
                    <h1>QU POS Application Version Tools</h1>
                    <p class="subtle">QU terminal Generate A Searchable Version Report.</p>
                </div>
            </div>
            <div class="user-profile-card">
                <div class="user-avatar" aria-hidden="true">${escapeHtml(userInitials(state.user?.displayName || ""))}</div>
                <div class="user-profile-copy">
                    <div class="user-name-line">
                        <span class="user-name">${escapeHtml(state.user?.displayName || "")}</span>
                        <span class="role-badge">${escapeHtml(roleLabel(state.user?.role))}</span>
                    </div>
                    <div class="dashboard-updated-line">
                        Dashboard Last Updated: <span id="dashboardLastUpdated">${escapeHtml(dashboardLastUpdatedLabel())}</span>
                    </div>
                </div>
                ${state.user ? `<button class="btn logout-btn" id="logoutBtn" type="button">Log Out</button>` : ""}
            </div>
        </div>`;
}

function userInitials(name) {
    const parts = String(name || "")
        .trim()
        .split(/\s+/)
        .filter(Boolean);
    if (!parts.length) return "QU";
    return parts.slice(0, 2).map(part => part[0]).join("").toUpperCase();
}

function uploadPanel() {
    return `
        <section class="panel upload-panel">
            <div class="drop-card">
                <label for="currentCsv">CSV Upload <span class="subtle">(required)</span></label>
                <input class="file-input" id="currentCsv" type="file" accept=".csv,text/csv">
                <div id="currentFileName" class="file-name">No file selected</div>
            </div>
            <div class="actions">
                <button class="btn primary" id="generateBtn">Upload CSV And Build Report</button>
            </div>
        </section>
        <section class="panel progress-panel">
            <div class="steps">
                <div class="step" data-step="0"><strong>Loading CSV</strong><br><span>Waiting</span></div>
                <div class="step" data-step="1"><strong>Building report</strong><br><span>Waiting</span></div>
                <div class="step" data-step="2"><strong>Writing HTML</strong><br><span>Waiting</span></div>
                <div class="step" data-step="3"><strong>Done</strong><br><span>Waiting</span></div>
            </div>
            <div id="statusMessage" class="status-message"></div>
        </section>`;
}

function renderHome() {
    state.currentPage = "dashboard";
    state.report = null;
    state.selectedUploadId = "";
    app.innerHTML = shell(header() + dashboardUploadSelector() + `<div id="dashboardHealthMount"></div><div id="reportMount">${latestReportLoading()}</div>`, "dashboard");
    bindShell();
    bindDashboardUploadSelector();
    if (state.report) bindReport();
    loadDashboardUploads();
    if (!state.report) loadLatestReport();
}

function dashboardUploadSelector() {
    return `
        <section class="panel dashboard-selector">
            <div>
                <h2>Past Data Uploads</h2>
                <p class="subtle">Select a saved CSV upload to view that historical report. The selected upload is compared against the upload immediately before it.</p>
            </div>
            <div class="selector-actions">
                <select class="text-input" id="dashboardUploadSelect">
                    <option value="">Latest Generated Report</option>
                </select>
                <button class="btn" id="loadUploadReportBtn" type="button">Load Upload</button>
            </div>
        </section>`;
}

function dashboardHealthPanel(health) {
    if (!health) return "";
    const terminal = health.latestTerminalUpload;
    const store = health.latestStoreImport;
    const jobs = health.apiJobs || [];
    const terminalJob = jobs.find(job => job.jobKey === "qu_ei_terminals_csv");
    const storeJob = jobs.find(job => job.jobKey === "qu_ei_stores_csv");
    return `
        <section class="dashboard-health">
            ${healthItem("Terminal Sync", terminal ? dateTimeLabel(terminal.uploadedAt) : "Not synced", terminal ? `${terminal.rowCount} rows` : "Upload or run job", terminal ? "sync" : "warning")}
            ${healthItem("Store Sync", store ? dateTimeLabel(store.uploadedAt) : "Not synced", store ? `${store.rowCount} stores` : "Run Store export", store ? "store" : "warning")}
            ${healthItem("Terminal Job", terminalJob?.status || "Not Run Yet", terminalJob ? `Next ${dateTimeLabel(terminalJob.nextRunAt)}` : "No schedule found", statusTone(terminalJob?.status))}
            ${healthItem("Store Job", storeJob?.status || "Not Run Yet", storeJob ? `Next ${dateTimeLabel(storeJob.nextRunAt)}` : "No schedule found", statusTone(storeJob?.status))}
        </section>`;
}

function healthItem(label, value, meta, type) {
    return `
        <article class="health-item health-${escapeHtml(type)}">
            <span class="health-dot" aria-hidden="true"></span>
            <span class="health-copy">
                <span class="health-label">${escapeHtml(label)}</span>
                <strong>${escapeHtml(value)}</strong>
                <span class="health-meta">${escapeHtml(meta || "")}</span>
            </span>
        </article>`;
}

function statusTone(status) {
    const value = String(status || "").toLowerCase();
    if (value.includes("success")) return "automation";
    if (value.includes("fail") || value.includes("error")) return "warning";
    return "neutral";
}

function dateTimeLabel(value) {
    if (!value) return "Not scheduled";
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString();
}

function bindDashboardUploadSelector() {
    document.getElementById("loadUploadReportBtn")?.addEventListener("click", () => {
        const id = document.getElementById("dashboardUploadSelect")?.value || "";
        if (!id) {
            state.report = null;
            state.selectedUploadId = "";
            loadLatestReport();
            return;
        }
        loadReportFromUpload(id);
    });
    document.getElementById("dashboardUploadSelect")?.addEventListener("change", event => {
        if (event.target.value) loadReportFromUpload(event.target.value);
    });
}

function readOnlyPanel() {
    return `
        <section class="panel" style="padding:24px;">
            <h2 style="margin-top:0;">Read-Only Access</h2>
            <p class="subtle">You can open saved reports, search report data, and review alerts. Uploading CSV files and generating new reports requires a Tech or Admin role.</p>
            <button class="btn primary" id="viewReportsBtn">View Reports</button>
        </section>`;
}

function latestReportLoading() {
    return `<section class="empty" style="margin-top:18px;">Loading latest generated report...</section>`;
}

function renderUploadPage() {
    state.currentPage = "upload";
    if (!canGenerateReports()) {
        renderHome();
        return;
    }
    app.innerHTML = shell(header() + uploadPanel() + `<section class="panel" style="padding:20px;margin-top:18px;"><h2>CSV Upload History</h2><div id="uploadHistory" class="report-list"><div class="empty">Loading upload history...</div></div></section>`, "upload");
    bindShell();
    bindUpload();
    loadUploads();
}

async function boot() {
    if (state.report) {
        app.innerHTML = `<main class="report-only">${header()}${reportView(state.report)}${footer()}</main>`;
        bindShell();
        bindReport();
        return;
    }

    try {
        const response = await fetch("api/auth.php?action=status");
        const payload = await response.json();
        if (!payload.ok) throw new Error(payload.error || "Unable to check login setup.");
        state.user = payload.user;
        state.needsSetup = !!payload.needsSetup;
        state.authStatusError = "";
    } catch (error) {
        state.user = null;
        state.authStatusError = error.message;
    }

    if (!state.user) {
        renderAuth();
        return;
    }

    renderHome();
}

async function refreshAuthStatus() {
    const response = await fetch("api/auth.php?action=status");
    const payload = await response.json();
    if (payload.ok && payload.user) {
        state.user = payload.user;
    }
}

function renderAuth() {
    const setup = state.needsSetup;
    app.innerHTML = `
        <main class="auth-page">
            <section class="auth-card panel">
                <img class="brand-logo" src="assets/goto-foods-white-logo.png" alt="GoTo Foods">
                <h1>${setup ? "Create First Admin" : "Sign In"}</h1>
                <p class="subtle">${setup ? "Set up the first administrator account for this protected tool." : "Sign in to generate and view QU POS reports."}</p>
                ${state.authStatusError ? `<div class="auth-error">Setup check failed: ${escapeHtml(state.authStatusError)}</div>` : ""}
                <form id="authForm">
                    <label>Email</label>
                    <input class="text-input" id="authEmail" type="email" autocomplete="email" required>
                    ${setup ? `<label>Display Name</label><input class="text-input" id="authName" type="text" autocomplete="name" required>` : ""}
                    <label>Password</label>
                    <input class="text-input" id="authPassword" type="password" autocomplete="${setup ? "new-password" : "current-password"}" required>
                    <button class="btn primary" type="submit">${setup ? "Create Admin" : "Sign In"}</button>
                </form>
                <div id="authMessage" class="status-message"></div>
            </section>
            ${footer()}
        </main>`;
    document.getElementById("authForm").addEventListener("submit", submitAuth);
    bindFooter();
}

function renderTwoFactor(setup = null) {
    const isSetup = !!setup;
    app.innerHTML = `
        <main class="auth-page">
            <section class="auth-card panel">
                <img class="brand-logo" src="assets/goto-foods-white-logo.png" alt="GoTo Foods">
                <h1>${isSetup ? "Set Up Two-Factor Authentication" : "Two-Factor Authentication"}</h1>
                <p class="subtle">${isSetup ? "Add this account to Microsoft Authenticator, Google Authenticator, Authy, or another authenticator app, then enter the 6-digit code." : "Enter the 6-digit code from your authenticator app."}</p>
                ${isSetup ? `
                    <div class="qr-card">
                        <div id="twoFactorQr" class="qr-code" aria-label="Two-factor setup QR code"></div>
                        <div id="qrFallback" class="subtle"></div>
                    </div>
                    <div class="two-factor-box">
                        <span class="label">Setup Key</span>
                        <code>${escapeHtml(setup.secret)}</code>
                    </div>
                    <p class="subtle">Scan the QR code with your authenticator app, or enter the setup key manually.</p>
                ` : ""}
                <form id="twoFactorForm">
                    <label>6-digit code</label>
                    <input class="text-input code-input" id="twoFactorCode" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required>
                    <button class="btn primary" type="submit">${isSetup ? "Finish Setup" : "Verify Code"}</button>
                    <button class="btn" id="backToLoginBtn" type="button">Back To Sign In</button>
                </form>
                <div id="twoFactorMessage" class="status-message"></div>
            </section>
            ${footer()}
        </main>`;
    document.getElementById("twoFactorForm").addEventListener("submit", submitTwoFactor);
    document.getElementById("backToLoginBtn").addEventListener("click", async () => {
        await fetch("api/auth.php?action=logout", { method: "POST" });
        state.pendingTwoFactor = null;
        renderAuth();
    });
    if (isSetup) renderTwoFactorQr(setup.otpauthUri);
    document.getElementById("twoFactorCode").focus();
    bindFooter();
}

function renderTwoFactorQr(otpauthUri) {
    const container = document.getElementById("twoFactorQr");
    const fallback = document.getElementById("qrFallback");
    if (!container || !fallback) return;
    if (typeof window.QRCode !== "function") {
        fallback.textContent = "QR code library did not load. Use the setup key below.";
        return;
    }
    container.innerHTML = "";
    try {
        new window.QRCode(container, {
            text: otpauthUri,
            width: 220,
            height: 220,
            colorDark: "#07101f",
            colorLight: "#ffffff",
            correctLevel: window.QRCode.CorrectLevel.M,
        });
        fallback.textContent = "Scan this QR code to add the account.";
    } catch (error) {
        fallback.textContent = "QR code could not be generated. Use the setup key below.";
    }
}

async function submitAuth(event) {
    event.preventDefault();
    const message = document.getElementById("authMessage");
    const action = state.needsSetup ? "setup" : "login";
    const body = {
        email: document.getElementById("authEmail").value,
        password: document.getElementById("authPassword").value,
        displayName: document.getElementById("authName")?.value || "",
    };
    try {
        const response = await fetch(`api/auth.php?action=${action}`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(body),
        });
        const payload = await response.json();
        if (!payload.ok) throw new Error(payload.error || "Authentication failed.");
        if (payload.requiresTwoFactorSetup) {
            state.pendingTwoFactor = { setup: payload.setup };
            renderTwoFactor(payload.setup);
            return;
        }
        if (payload.requiresTwoFactor) {
            state.pendingTwoFactor = {};
            renderTwoFactor();
            return;
        }
        state.user = payload.user;
        await refreshAuthStatus();
        state.needsSetup = false;
        renderHome();
    } catch (error) {
        message.textContent = error.message;
    }
}

async function submitTwoFactor(event) {
    event.preventDefault();
    const message = document.getElementById("twoFactorMessage");
    const action = state.pendingTwoFactor?.setup ? "confirm-2fa-setup" : "verify-2fa";
    try {
        const response = await fetch(`api/auth.php?action=${action}`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ code: document.getElementById("twoFactorCode").value }),
        });
        const payload = await response.json();
        if (!payload.ok) throw new Error(payload.error || "Two-factor verification failed.");
        state.user = payload.user;
        await refreshAuthStatus();
        state.needsSetup = false;
        state.pendingTwoFactor = null;
        renderHome();
    } catch (error) {
        message.textContent = error.message;
    }
}

function bindShell() {
    document.getElementById("logoutBtn")?.addEventListener("click", logout);
    document.getElementById("reportsNavBtn")?.addEventListener("click", loadReports);
    document.getElementById("dashboardNavBtn")?.addEventListener("click", renderHome);
    document.getElementById("uploadNavBtn")?.addEventListener("click", renderUploadPage);
    document.getElementById("alertsNavBtn")?.addEventListener("click", renderAlertsPage);
    document.getElementById("settingsNavBtn")?.addEventListener("click", () => renderSettings("users"));
    bindFooter();
}

function bindFooter() {
    document.getElementById("footerVersionBtn")?.addEventListener("click", () => renderReleaseNotes(state.currentPage || "dashboard"));
}

async function logout() {
    await fetch("api/auth.php?action=logout", { method: "POST" });
    state.user = null;
    state.report = null;
    renderAuth();
}

function emptyState() {
    if (!canGenerateReports()) {
        return `<section class="empty" style="margin-top:18px;">Use View Reports to open previously generated QU POS reports.</section>`;
    }
    return `<section class="empty" style="margin-top:18px;">Upload a CSV to save it to history and build the latest report.</section>`;
}

function bindUpload() {
    const current = document.getElementById("currentCsv");
    current?.addEventListener("change", () => document.getElementById("currentFileName").textContent = current.files[0]?.name || "No file selected");
    document.getElementById("generateBtn")?.addEventListener("click", generateReport);
}

function setStep(index, label) {
    document.querySelectorAll(".step").forEach((step, i) => {
        step.classList.toggle("done", i < index);
        step.classList.toggle("active", i === index);
        step.querySelector("span").textContent = i < index ? "Completed" : (i === index ? "In progress" : "Waiting");
    });
    document.getElementById("statusMessage").textContent = label;
}

async function generateReport() {
    const current = document.getElementById("currentCsv");
    if (!current.files[0]) {
        document.getElementById("statusMessage").textContent = "Choose a CSV file first.";
        return;
    }

    const form = new FormData();
    form.append("currentCsv", current.files[0]);

    const button = document.getElementById("generateBtn");
    button.disabled = true;
    try {
        setStep(0, "Loading CSV");
        await pause(180);
        setStep(1, "Building report");
        const response = await fetch("api/generate.php", { method: "POST", body: form });
        const payload = await response.json();
        if (!payload.ok) throw new Error(payload.error || "Report generation failed.");
        setStep(2, "Writing HTML");
        await pause(180);
        state.report = payload.report;
        state.htmlUrl = payload.htmlUrl;
        setStep(4, "Done");
        refreshHeaderLastUpdated();
        await loadUploads();
    } catch (error) {
        document.getElementById("statusMessage").textContent = error.message;
    } finally {
        button.disabled = false;
    }
}

async function loadLatestReport() {
    const mount = document.getElementById("reportMount");
    try {
        const response = await fetch("api/reports.php?action=latest");
        const payload = await response.json();
        if (!payload.ok) throw new Error(payload.error || "Could not load latest report.");
        if (!payload.report) {
            state.dashboardHealth = payload.health || null;
            document.getElementById("dashboardHealthMount").innerHTML = dashboardHealthPanel(state.dashboardHealth);
            mount.innerHTML = `<section class="empty" style="margin-top:18px;">No generated reports yet. Tech or Admin users can upload a CSV from the Upload CSV page.</section>`;
            return;
        }
        state.report = payload.report;
        state.dashboardHealth = payload.health || null;
        state.htmlUrl = payload.metadata?.url || null;
        refreshHeaderLastUpdated();
        document.getElementById("dashboardHealthMount").innerHTML = dashboardHealthPanel(state.dashboardHealth);
        mount.innerHTML = reportView(state.report);
        bindReport();
    } catch (error) {
        mount.innerHTML = `<section class="empty" style="margin-top:18px;">${escapeHtml(error.message)}</section>`;
    }
}

async function loadDashboardUploads() {
    const select = document.getElementById("dashboardUploadSelect");
    if (!select) return;
    try {
        const response = await fetch("api/uploads.php?action=list");
        const payload = await response.json();
        if (!payload.ok) throw new Error(payload.error || "Could not load upload history.");
        const currentValue = select.value;
        select.innerHTML = `<option value="">Latest Generated Report</option>` + payload.uploads.map((upload, index) => {
            const label = `${index === 0 ? "Current CSV" : (index === 1 ? "Previous CSV" : "Historical CSV")} - ${new Date(upload.uploadedAt).toLocaleString()} - ${upload.filename}`;
            return `<option value="${escapeHtml(upload.id)}">${escapeHtml(label)}</option>`;
        }).join("");
        select.value = state.selectedUploadId || currentValue;
    } catch (error) {
        select.innerHTML = `<option value="">${escapeHtml(error.message)}</option>`;
    }
}

async function loadReportFromUpload(id) {
    const mount = document.getElementById("reportMount");
    mount.innerHTML = `<section class="empty" style="margin-top:18px;">Loading selected upload report...</section>`;
    try {
        const response = await fetch(`api/reports.php?action=from-upload&id=${encodeURIComponent(id)}`);
        const payload = await response.json();
        if (!payload.ok) throw new Error(payload.error || "Could not load selected upload report.");
        state.report = payload.report;
        state.selectedUploadId = String(id);
        state.htmlUrl = null;
        refreshHeaderLastUpdated();
        mount.innerHTML = reportView(state.report);
        bindReport();
    } catch (error) {
        mount.innerHTML = `<section class="empty" style="margin-top:18px;">${escapeHtml(error.message)}</section>`;
    }
}

async function loadUploads() {
    const list = document.getElementById("uploadHistory");
    if (!list) return;
    const response = await fetch("api/uploads.php?action=list");
    const payload = await response.json();
    if (!payload.ok) {
        list.innerHTML = `<div class="empty">${escapeHtml(payload.error)}</div>`;
        return;
    }
    state.uploads = payload.uploads;
    list.innerHTML = state.uploads.length ? state.uploads.map((upload, index) => `
        <div class="report-item">
            <div>
                <strong>${escapeHtml(upload.filename)}</strong><br>
                <span class="subtle">${index === 0 ? "Current CSV" : (index === 1 ? "Previous CSV" : "Historical CSV")} • ${escapeHtml(new Date(upload.uploadedAt).toLocaleString())} • ${escapeHtml(upload.rowCount)} rows</span>
            </div>
            ${canManageUsers() ? `<button class="btn danger" data-delete-upload="${upload.id}">Delete</button>` : ""}
        </div>`).join("") : `<div class="empty">No CSV uploads have been saved yet.</div>`;
    list.querySelectorAll("button[data-delete-upload]").forEach(button => {
        button.addEventListener("click", () => deleteUpload(button.dataset.deleteUpload));
    });
}

async function deleteUpload(id) {
    if (!confirm("Delete this historical CSV upload? This cannot be undone.")) return;
    const response = await fetch("api/uploads.php?action=delete", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id }),
    });
    const payload = await response.json();
    if (!payload.ok) alert(payload.error || "Could not delete CSV upload.");
    await loadUploads();
}

function pause(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function loadReports() {
    state.currentPage = "reports";
    app.innerHTML = shell(header() + `<div id="reportMount"></div>`, "reports");
    bindShell();
    const mount = document.getElementById("reportMount");
    mount.innerHTML = `<section class="panel" style="padding:20px;margin-top:18px;"><h2>Saved Reports</h2><div class="empty">Loading reports...</div></section>`;
    const response = await fetch("api/reports.php");
    const payload = await response.json();
    if (!payload.ok) {
        mount.innerHTML = `<section class="empty" style="margin-top:18px;">${escapeHtml(payload.error)}</section>`;
        return;
    }
    state.reports = payload.reports;
    mount.innerHTML = `
        <section class="panel" style="padding:20px;margin-top:18px;">
            <h2>Saved Reports</h2>
            <div class="report-list">
                ${state.reports.length ? state.reports.map(report => `
                    <div class="report-item">
                        <div><strong>${escapeHtml(report.name)}</strong><br><span class="subtle">${escapeHtml(report.date)} • ${escapeHtml(report.sourceCsv || "")} • Stable ${escapeHtml(report.currentStableVersion || "N/A")}</span></div>
                        <a class="btn" href="${escapeHtml(report.url)}" target="_blank">Open</a>
                    </div>`).join("") : `<div class="empty">No reports have been generated yet.</div>`}
            </div>
        </section>`;
}

async function renderAlertsPage() {
    state.currentPage = "alerts";
    state.activeTab = "alerts";
    app.innerHTML = shell(header() + `<div id="reportMount"><section class="empty" style="margin-top:18px;">Loading alerts...</section></div>`, "alerts");
    bindShell();
    await loadLatestReport();
    state.activeTab = "alerts";
    document.querySelectorAll(".tab-btn").forEach(button => button.classList.toggle("active", button.dataset.tab === "alerts"));
    const tabContentElement = document.getElementById("tabContent");
    if (tabContentElement && state.report) {
        tabContentElement.innerHTML = tabContent(state.report, "alerts");
    }
}

function renderReleaseNotes(returnPage = state.currentPage || "dashboard") {
    if (returnPage !== "releaseNotes") {
        state.releaseNotesReturnPage = returnPage;
    }
    state.currentPage = "releaseNotes";
    app.innerHTML = shell(header() + `
        <section class="panel release-panel">
            <div class="release-heading">
                <div>
                    <h2>Release Notes</h2>
                    <p class="subtle">Newest releases are shown first. Every completed feature, improvement, bug fix, or security change receives the next application version number.</p>
                </div>
                <div class="release-current">
                    <span class="label">Current Version</span>
                    <strong>${escapeHtml(APP_VERSION)}</strong>
                </div>
            </div>
            <button class="btn back-btn" id="backToApplicationBtn" type="button">Back to Application</button>
            <div class="release-rules">
                Version sequence: v001.01 through v001.09, then v002.00 through v002.09, then v003.00 through v003.09, then v004.00 through v004.09, then v005.00 through v005.09, then v006.00, v006.01, and so on.
            </div>
            <div class="release-list">
                ${FEATURE_RELEASES.slice().reverse().map(release => `
                    <article class="release-card">
                        <div class="release-meta">
                            <span class="release-version">${escapeHtml(release.version)}</span>
                            <span class="release-type ${escapeHtml(release.type.toLowerCase().replaceAll(" ", "-"))}">${escapeHtml(release.type)}</span>
                            <span class="release-status">${escapeHtml(release.status)}</span>
                        </div>
                        <h3>${escapeHtml(release.title)}</h3>
                        <p>${escapeHtml(release.description)}</p>
                        <div class="subtle">${escapeHtml(release.releasedAt)}</div>
                    </article>
                `).join("")}
            </div>
        </section>`, "releaseNotes");
    bindShell();
    document.getElementById("backToApplicationBtn")?.addEventListener("click", () => navigateToPage(state.releaseNotesReturnPage || "dashboard"));
}

function navigateToPage(page) {
    if (page === "reports") {
        loadReports();
        return;
    }
    if (page === "upload") {
        renderUploadPage();
        return;
    }
    if (page === "alerts") {
        renderAlertsPage();
        return;
    }
    if (page === "settings" || page === "users") {
        renderSettings(state.settingsTab || "users");
        return;
    }
    renderHome();
}

async function renderUsers() {
    renderSettings("users");
}

function settingsTabs(activeTab) {
    const tabs = [
        ["users", "Users"],
        ["roles", "User Roles"],
        ["apiLogs", "API Logs"],
        ["apiTimes", "API Call Times"],
    ];
    return `
        <section class="panel settings-panel">
            <div class="settings-heading">
                <div>
                    <h2>Settings</h2>
                    <p class="subtle">Admin tools for users, permissions, QU EI schedules, and retrieval logs.</p>
                </div>
            </div>
            <div class="tabs settings-tabs">
                ${tabs.map(([key, label]) => `<button class="tab-btn ${activeTab === key ? "active" : ""}" data-settings-tab="${key}">${label}</button>`).join("")}
            </div>
            <div id="settingsContent"></div>
        </section>`;
}

async function renderSettings(tab = "users") {
    if (!canManageUsers()) {
        renderHome();
        return;
    }
    state.currentPage = "settings";
    state.settingsTab = tab;
    app.innerHTML = shell(header() + settingsTabs(tab), "settings");
    bindShell();
    document.querySelectorAll("button[data-settings-tab]").forEach(button => {
        button.addEventListener("click", () => renderSettings(button.dataset.settingsTab));
    });
    const content = document.getElementById("settingsContent");
    if (tab === "roles") {
        content.innerHTML = `<div class="empty">Loading role permissions...</div>`;
        await loadRolePermissions();
        return;
    }
    if (tab === "apiLogs") {
        content.innerHTML = apiLogsPanel();
        bindApiLogs();
        await loadApiLogs();
        return;
    }
    if (tab === "apiTimes") {
        content.innerHTML = apiTimesPanel();
        bindApiTimes();
        await loadApiSchedules();
        return;
    }
    content.innerHTML = userManagementPanel();
    document.getElementById("createUserForm").addEventListener("submit", createUser);
    await loadUsers();
}

function userManagementPanel() {
    return `
        <div class="settings-tab-panel">
            <h2>User Management</h2>
            <form id="createUserForm" class="user-form">
                <input class="text-input" id="newUserName" placeholder="Display name" required>
                <input class="text-input" id="newUserEmail" type="email" placeholder="Email" required>
                <input class="text-input" id="newUserPassword" type="password" placeholder="Temporary password" required>
                <select class="text-input" id="newUserRole">
                    <option value="tech">Tech</option>
                    <option value="read_only">Read-Only</option>
                    <option value="admin">Admin</option>
                </select>
                <button class="btn primary" type="submit">Add User</button>
            </form>
            <div id="usersMessage" class="status-message"></div>
            <div id="usersList" class="report-list"></div>
        </div>`;
}

async function loadRolePermissions() {
    const content = document.getElementById("settingsContent");
    const response = await fetch("api/settings.php?action=permissions");
    const payload = await parseJsonResponse(response);
    if (!payload.ok) {
        content.innerHTML = `<div class="empty">${escapeHtml(payload.error)}</div>`;
        return;
    }
    content.innerHTML = `
        <div class="settings-tab-panel">
            <h2>User Roles</h2>
            <p class="subtle">Choose which navigation sections each role can see and open. Admin keeps Settings access by design.</p>
            <div id="rolesMessage" class="status-message"></div>
            <div class="permission-grid">
                ${payload.roles.map(role => `
                    <form class="permission-card" data-role="${escapeHtml(role.key)}">
                        <h3>${escapeHtml(role.label)}</h3>
                        ${payload.sections.map(section => {
                            const checked = payload.permissions?.[role.key]?.[section.key] ? "checked" : "";
                            const disabled = role.key === "admin" && section.key === "settings" ? "disabled" : "";
                            return `
                                <label class="permission-row">
                                    <input type="checkbox" name="${escapeHtml(section.key)}" ${checked} ${disabled}>
                                    <span>${escapeHtml(section.label)}</span>
                                </label>`;
                        }).join("")}
                        <button class="btn primary" type="submit">Save ${escapeHtml(role.label)}</button>
                    </form>
                `).join("")}
            </div>
        </div>`;
    document.querySelectorAll(".permission-card").forEach(form => {
        form.addEventListener("submit", saveRolePermissions);
    });
}

async function saveRolePermissions(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const role = form.dataset.role;
    const permissions = {};
    form.querySelectorAll("input[type='checkbox']").forEach(input => {
        permissions[input.name] = input.checked || input.disabled;
    });
    const message = document.getElementById("rolesMessage");
    message.textContent = "Saving permissions...";
    const response = await fetch("api/settings.php?action=save-permissions", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ role, permissions }),
    });
    const payload = await parseJsonResponse(response);
    if (!payload.ok) {
        message.textContent = payload.error || "Could not save permissions.";
        message.className = "status-message error-message";
        return;
    }
    await refreshAuthStatus();
    message.textContent = "Permissions saved.";
    message.className = "status-message success-message";
}

function apiLogsPanel() {
    return `
        <div class="settings-tab-panel">
            <div class="settings-actions-heading">
                <div>
                    <h2>API Logs</h2>
                    <p class="subtle">Newest QU EI terminal and store retrieval activity appears first.</p>
                </div>
                <div class="row-actions">
                    <button class="btn primary" data-retrieve-job="qu_ei_terminals_csv" type="button">Retrieve Terminals</button>
                    <button class="btn primary" data-retrieve-job="qu_ei_stores_csv" type="button">Retrieve Stores</button>
                </div>
            </div>
            <div id="apiLogsMessage" class="status-message"></div>
            <div id="apiLogsList"></div>
        </div>`;
}

function bindApiLogs() {
    document.querySelectorAll("button[data-retrieve-job]").forEach(button => {
        button.addEventListener("click", () => retrieveDataNow(button.dataset.retrieveJob, button));
    });
}

async function loadApiLogs() {
    const list = document.getElementById("apiLogsList");
    const response = await fetch("api/settings.php?action=api-logs");
    const payload = await parseJsonResponse(response);
    if (!payload.ok) {
        list.innerHTML = `<div class="empty">${escapeHtml(payload.error)}</div>`;
        return;
    }
    list.innerHTML = simpleTable([
        "Date And Time", "Source", "Trigger", "User", "Status", "Attempts", "Received", "Added", "Updated", "Skipped", "Duration", "Error"
    ], payload.logs.map(log => [
        new Date(log.startedAt).toLocaleString(),
        escapeHtml(log.source),
        escapeHtml(log.triggerType),
        escapeHtml(log.initiatedBy || "System"),
        `<span class="status-pill ${escapeHtml(String(log.status).toLowerCase().replaceAll(" ", "-"))}">${escapeHtml(log.status)}</span>`,
        log.attempts,
        log.recordsReceived,
        log.recordsAdded,
        log.recordsUpdated,
        log.recordsSkipped,
        `${log.durationMs} ms`,
        escapeHtml(log.errorMessage || "")
    ]));
}

async function retrieveDataNow(jobKey, button) {
    const message = document.getElementById("apiLogsMessage");
    button.disabled = true;
    message.textContent = "Starting manual retrieval...";
    try {
        const response = await fetch("api/settings.php?action=retrieve-data", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ jobKey }),
        });
        const payload = await parseJsonResponse(response);
        if (!payload.ok) throw new Error(payload.error || "Could not retrieve data.");
        message.textContent = payload.message || "Manual retrieval started.";
        message.className = "status-message success-message";
        await loadApiLogs();
    } catch (error) {
        message.textContent = error.message;
        message.className = "status-message error-message";
    } finally {
        button.disabled = false;
    }
}

function apiTimesPanel() {
    return `
        <div class="settings-tab-panel">
            <div class="settings-actions-heading">
                <div>
                    <h2>API Call Times</h2>
                    <p class="subtle">Manage QU EI schedules in America/New_York time.</p>
                </div>
                <form id="addScheduleForm" class="inline-form">
                    <select class="text-input" id="newScheduleJob">
                        <option value="qu_ei_terminals_csv">QU EI Terminals CSV</option>
                        <option value="qu_ei_stores_csv">QU EI Store Information CSV</option>
                    </select>
                    <input class="text-input" id="newScheduleTime" type="time" required>
                    <button class="btn primary" type="submit">Add Time</button>
                </form>
            </div>
            <div id="apiTimesMessage" class="status-message"></div>
            <div id="apiTimesList"></div>
        </div>`;
}

function bindApiTimes() {
    document.getElementById("addScheduleForm")?.addEventListener("submit", addScheduleTime);
}

async function loadApiSchedules() {
    const list = document.getElementById("apiTimesList");
    const response = await fetch("api/settings.php?action=schedules");
    const payload = await parseJsonResponse(response);
    if (!payload.ok) {
        list.innerHTML = `<div class="empty">${escapeHtml(payload.error)}</div>`;
        return;
    }
    list.innerHTML = simpleTable([
        "API Job Name", "Scheduled Time", "Timezone", "Last Run", "Next Scheduled Run", "Current Status", "Available Actions"
    ], payload.schedules.map(schedule => [
        escapeHtml(schedule.jobName),
        `<input class="text-input schedule-time-input" type="time" value="${escapeHtml(schedule.scheduledTime)}" data-schedule-id="${escapeHtml(schedule.id)}">`,
        escapeHtml(schedule.timezone),
        schedule.lastRunAt ? new Date(schedule.lastRunAt).toLocaleString() : "Not run yet",
        schedule.nextRunAt ? new Date(schedule.nextRunAt).toLocaleString() : "",
        escapeHtml(schedule.lastStatus),
        `<button class="btn" data-save-schedule="${escapeHtml(schedule.id)}" type="button">Edit</button>`
    ]));
    document.querySelectorAll("button[data-save-schedule]").forEach(button => {
        button.addEventListener("click", () => updateScheduleTime(button.dataset.saveSchedule));
    });
}

async function addScheduleTime(event) {
    event.preventDefault();
    await saveSchedule(
        "api/settings.php?action=add-schedule",
        document.getElementById("newScheduleTime").value,
        null,
        document.getElementById("newScheduleJob").value
    );
    event.target.reset();
}

async function updateScheduleTime(id) {
    const value = document.querySelector(`input[data-schedule-id="${CSS.escape(String(id))}"]`)?.value || "";
    await saveSchedule("api/settings.php?action=update-schedule", value, id);
}

async function saveSchedule(url, scheduledTime, id = null, jobKey = null) {
    const message = document.getElementById("apiTimesMessage");
    message.textContent = "Saving schedule...";
    const response = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id, scheduledTime, jobKey }),
    });
    const payload = await parseJsonResponse(response);
    if (!payload.ok) {
        message.textContent = payload.error || "Could not save schedule.";
        message.className = "status-message error-message";
        return;
    }
    message.textContent = "Schedule saved.";
    message.className = "status-message success-message";
    await loadApiSchedules();
}

async function loadUsers() {
    const list = document.getElementById("usersList");
    const response = await fetch("api/users.php?action=list");
    const payload = await response.json();
    if (!payload.ok) {
        list.innerHTML = `<div class="empty">${escapeHtml(payload.error)}</div>`;
        return;
    }
    list.innerHTML = payload.users.map(user => `
        <div class="report-item">
            <div>
                <strong>${escapeHtml(user.displayName)}</strong><br>
                <span class="subtle">${escapeHtml(user.email)} • ${escapeHtml(user.roleLabel || roleLabel(user.role))} • ${user.isActive ? "Active" : "Disabled"} • 2FA ${user.twoFactorEnabled ? "On" : "Not Set Up"}</span>
            </div>
            <div class="row-actions">
                <select class="text-input role-select" data-role-user-id="${user.id}">
                    <option value="tech" ${user.role === "tech" ? "selected" : ""}>Tech</option>
                    <option value="read_only" ${user.role === "read_only" ? "selected" : ""}>Read-Only</option>
                    <option value="admin" ${user.role === "admin" ? "selected" : ""}>Admin</option>
                </select>
                <button class="btn" data-user-id="${user.id}" data-active="${user.isActive ? "0" : "1"}">${user.isActive ? "Disable" : "Enable"}</button>
                <button class="btn warning" data-reset-2fa="${user.id}" ${user.twoFactorEnabled ? "" : "disabled"}>Reset 2FA</button>
                <button class="btn danger" data-delete-user="${user.id}">Delete</button>
            </div>
        </div>`).join("");
    list.querySelectorAll("select[data-role-user-id]").forEach(select => {
        select.addEventListener("change", () => setUserRole(select.dataset.roleUserId, select.value));
    });
    list.querySelectorAll("button[data-user-id]").forEach(button => {
        button.addEventListener("click", () => setUserActive(button.dataset.userId, button.dataset.active === "1"));
    });
    list.querySelectorAll("button[data-reset-2fa]").forEach(button => {
        button.addEventListener("click", () => resetUserTwoFactor(button.dataset.reset2fa));
    });
    list.querySelectorAll("button[data-delete-user]").forEach(button => {
        button.addEventListener("click", () => deleteUser(button.dataset.deleteUser));
    });
}

async function createUser(event) {
    event.preventDefault();
    const message = document.getElementById("usersMessage");
    const button = event.submitter || event.target.querySelector("button[type='submit']");
    message.textContent = "Adding user...";
    message.className = "status-message";
    if (button) button.disabled = true;
    try {
        const response = await fetch("api/users.php?action=create", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                displayName: document.getElementById("newUserName").value,
                email: document.getElementById("newUserEmail").value,
                password: document.getElementById("newUserPassword").value,
                role: document.getElementById("newUserRole").value,
            }),
        });
        const payload = await parseJsonResponse(response);
        if (!payload.ok) throw new Error(payload.error || "Could not create user.");
        message.textContent = "User created.";
        message.className = "status-message success-message";
        event.target.reset();
        await loadUsers();
    } catch (error) {
        message.textContent = error.message;
        message.className = "status-message error-message";
    } finally {
        if (button) button.disabled = false;
    }
}

async function parseJsonResponse(response) {
    const text = await response.text();
    let payload = {};
    try {
        payload = text ? JSON.parse(text) : {};
    } catch (error) {
        throw new Error(text || `Request failed with status ${response.status}.`);
    }
    if (!response.ok && payload.ok !== false) {
        payload.ok = false;
        payload.error = payload.error || `Request failed with status ${response.status}.`;
    }
    return payload;
}

async function setUserActive(id, isActive) {
    const response = await fetch("api/users.php?action=set-active", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id, isActive }),
    });
    const payload = await response.json();
    if (!payload.ok) alert(payload.error || "Could not update user status.");
    await loadUsers();
}

async function setUserRole(id, role) {
    const response = await fetch("api/users.php?action=set-role", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id, role }),
    });
    const payload = await response.json();
    if (!payload.ok) alert(payload.error || "Could not update role.");
    await loadUsers();
}

async function resetUserTwoFactor(id) {
    if (!confirm("Reset 2FA for this user? They will need to set it up again at next sign in.")) return;
    const response = await fetch("api/users.php?action=reset-2fa", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id }),
    });
    const payload = await response.json();
    if (!payload.ok) alert(payload.error || "Could not reset 2FA.");
    await loadUsers();
}

async function deleteUser(id) {
    if (!confirm("Delete this user? This cannot be undone.")) return;
    const response = await fetch("api/users.php?action=delete", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id }),
    });
    const payload = await response.json();
    if (!payload.ok) alert(payload.error || "Could not delete user.");
    await loadUsers();
}

function reportView(report) {
    const comparisonTab = report.comparison ? `<button class="tab-btn" data-tab="comparison">Comparison</button>` : "";
    return `
        <section class="report">
            ${summaryCards(report)}
            <div class="tabs">
                <button class="tab-btn active" data-tab="current">Current Versions</button>
                <button class="tab-btn" data-tab="stores">Stores Version Report</button>
                <button class="tab-btn" data-tab="alerts">Alerts</button>
                ${comparisonTab}
            </div>
            <div class="toolbar">
                <input id="reportSearch" class="search" type="search" placeholder="Search brands, stores, versions, terminals, and alerts">
                <span class="subtle">Generated ${escapeHtml(report.generatedOn)} from ${escapeHtml(report.sourceCsv)}</span>
            </div>
            <div id="tabContent">${tabContent(report, state.activeTab)}</div>
        </section>`;
}

function summaryCards(report) {
    const s = report.summary;
    return `
        <div class="summary-grid">
            ${metricCard("POS App Terminals", s.posAppTerminals, "Total terminals", "terminals", "", "pos")}
            ${metricCard("Current Stable Version", s.currentStableVersion, `${s.currentStableVersionCount} terminals | ${formatPercent(s.currentStableVersionUsagePercent)} stable usage`, "stable", "", "stable")}
            ${metricCard("Out-Of-Date Stores", s.outOfDateStores, "Stores below stable", "outdated", "", "outdated-stores")}
            ${appVersionMetricCard("Kiosk", s.kioskStableVersion, s.kioskStableUsagePercent, "kiosk")}
            ${appVersionMetricCard("QuBox", s.quboxStableVersion, s.quboxStableUsagePercent, "qubox")}
            ${appVersionMetricCard("QuKDS", s.qukdsStableVersion, s.qukdsStableUsagePercent, "qukds")}
            ${appVersionMetricCard("QuORB", s.quorbStableVersion, s.quorbStableUsagePercent, "quorb")}
        </div>`;
}

function card(label, value, meta = "") {
    return `<div class="card"><span class="label">${escapeHtml(label)}</span><span class="value">${escapeHtml(value)}</span>${meta ? `<span class="subtle">${escapeHtml(meta)}</span>` : ""}</div>`;
}

function metricCard(label, value, meta, type, trend = "", shortcut = "") {
    return `
        <button class="card metric-card metric-${escapeHtml(type)} dashboard-shortcut" type="button" data-shortcut="${escapeHtml(shortcut)}" aria-label="Open ${escapeHtml(label)} details">
            <div class="metric-copy">
                <span class="label">${escapeHtml(label)}</span>
                <span class="metric-value-row">
                    <span class="metric-icon" aria-hidden="true">${metricIcon(type)}</span>
                    <span class="value">${escapeHtml(value)}</span>
                </span>
                ${meta ? `<span class="metric-meta">${escapeHtml(meta)}</span>` : ""}
                ${trend}
            </div>
        </button>`;
}

function appVersionMetricCard(label, stableVersion, usagePercent, type) {
    const hasData = stableVersion && stableVersion !== "No Data" && usagePercent !== null && usagePercent !== undefined;
    const value = hasData ? stableVersion : "No Data";
    const meta = hasData ? `Usage: ${formatPercent(usagePercent)}` : "No reporting data";
    return metricCard(label, value, meta, type, "", type);
}

function formatPercent(value) {
    const number = Number(value);
    return Number.isFinite(number) ? `${number.toFixed(1)}%` : "0.0%";
}

function metricIcon(type) {
    const icons = {
        terminals: `<svg viewBox="0 0 48 48"><rect x="8" y="8" width="32" height="24" rx="2"></rect><path d="M14 38h20M19 32v6M29 32v6"></path></svg>`,
        stable: `<svg viewBox="0 0 48 48"><path d="M24 5 39 11v12c0 10-6.5 16.5-15 20C15.5 39.5 9 33 9 23V11l15-6Z"></path><path d="m17 24 5 5 10-12"></path></svg>`,
        outdated: `<svg viewBox="0 0 48 48"><path d="M24 6 44 40H4L24 6Z"></path><path d="M24 17v11M24 35h.01"></path></svg>`,
        kiosk: `<svg viewBox="0 0 48 48"><rect x="15" y="5" width="18" height="34" rx="2"></rect><path d="M19 34h10M21 43h6"></path></svg>`,
        qubox: `<svg viewBox="0 0 48 48"><path d="m24 5 16 9v20l-16 9-16-9V14l16-9Z"></path><path d="M8 14l16 9 16-9M24 23v20"></path></svg>`,
        qukds: `<svg viewBox="0 0 48 48"><rect x="7" y="8" width="34" height="24" rx="3"></rect><path d="M15 40h18M20 32v8M28 32v8M16 18h16M16 24h10"></path></svg>`,
        quorb: `<svg viewBox="0 0 48 48"><rect x="8" y="10" width="32" height="28" rx="4"></rect><path d="M16 20h16M16 28h10M33 28l4 4 7-9"></path></svg>`,
    };
    return icons[type] || icons.terminals;
}

function tabContent(report, tab) {
    if (tab === "stores") return storesTab(report);
    if (tab === "alerts") return alertsTab(report);
    if (tab === "comparison") return comparisonTab(report);
    return currentTab(report);
}

function currentTab(report) {
    return `
        ${versionSection("Downloadable Production Qu POS Version", report.downloadableVersions, report, "pos-versions", "pos")}
        ${outdatedSection(report)}
        ${versionSection("Downloadable Kiosk Versions", report.kioskVersions, report, "kiosk-versions", "kiosk")}
        ${versionSection("QuBox Versions", report.quboxVersions, report, "qubox-versions", "qubox")}
        ${versionSection("QuKDS Versions", report.qukdsVersions, report, "qukds-versions", "qukds")}
        ${versionSection("QuORB Versions", report.quorbVersions, report, "quorb-versions", "quorb")}`;
}

function versionSection(title, versions, report, sectionId = "", appType = "pos") {
    if (!versions?.length) return "";
    const baseline = versionBaseline(report, versions, appType);
    return `
        <section class="report-section" ${sectionId ? `id="${escapeHtml(sectionId)}"` : ""}>
            <h2>${escapeHtml(title)}</h2>
            ${simpleTable(["Version", "Release Train", "Terminals", "Stores", "Types", "Download"], versions.map(item => [
                badge(item.version, report, baseline),
                item.releaseTrain || "",
                item.terminalCount,
                item.storeCount,
                item.terminalTypes,
                item.url ? `<a href="${escapeHtml(item.url)}" target="_blank">Download</a>` : ""
            ]))}
            ${versions.map(item => versionDetail(item, report, baseline, appType)).join("")}
        </section>`;
}

function versionDetail(item, report, baseline = null, appType = "pos") {
    const productName = deviceLabel(appType);
    return `
        <details class="version-detail">
            <summary>${badge(item.version, report, baseline)}<span>${escapeHtml(item.terminalCount)} ${escapeHtml(productName)} across ${escapeHtml(item.storeCount)} stores</span></summary>
            <div class="detail-body">
                ${item.url ? `<p><a href="${escapeHtml(item.url)}" target="_blank">${escapeHtml(item.url)}</a></p>` : ""}
                ${simpleTable(["Store ID", "Store Name", "Status", "Terminals", "Types", "Latest Seen"], (item.storeRows || []).map(store => [
                    store.storeId,
                    store.storeName,
                    storeStatusBadge(store.storeStatus),
                    store.terminalCount,
                    store.terminalTypes,
                    store.latestSeen
                ]))}
            </div>
        </details>`;
}

function outdatedSection(report) {
    if (!report.outOfDateVersionSummary?.length) return `<h2>Out-Of-Date Stores</h2><div class="empty">All POS app terminals are on or above the current stable version.</div>`;
    return `
        <h2>Out-Of-Date Stores</h2>
        <p class="subtle">Current stable version is ${escapeHtml(report.summary.currentStableVersion)}. Any POS app version below that is counted as out-of-date.</p>
        ${simpleTable(["Version", "Out-Of-Date Terminals", "Stores"], report.outOfDateVersionSummary.map(item => [
            badge(item.version, report),
            item.terminalCount,
            item.storeCount
        ]))}`;
}

function storesTab(report) {
    return simpleTable(["Store ID", "Store Name", "Versions Detected", "Most Common Version", "Out-Of-Date Terminals", "Total POS Terminals", "Latest Seen"], report.stores.map(store => [
        store.storeId,
        store.storeName,
        (store.versionsDetectedList || []).map(version => badge(version, report)).join(""),
        badge(store.mostCommonVersion, report),
        `${escapeHtml(store.outOfDateTerminalCount)}${Number(store.outOfDateTerminalCount) > 0 ? '<span class="sr-only"> out-of-date-store</span>' : ""}`,
        store.totalPosTerminals,
        store.latestSeen
    ]));
}

function alertsTab(report) {
    const alerts = report.alerts || {};
    return `
        <h2>Mixed-Version Stores</h2>
        ${storesTab({ ...report, stores: alerts.mixedVersionStores || [] })}
        <h2>Stale Terminals</h2>
        ${simpleTable(["Store ID", "Store Name", "Terminal ID", "Computer Name", "Type", "Version", "Last Seen", "Age Days"], (alerts.staleTerminals || []).map(item => [
            item.storeId, item.storeName, item.terminalId, item.computerName, item.terminalType, badge(item.currentVersion, report), item.lastSeen, item.ageDays
        ]))}
        <h2>Far Behind Stores</h2>
        ${storesTab({ ...report, stores: alerts.farBehindStores || [] })}`;
}

function comparisonTab(report) {
    const comparison = report.comparison;
    if (!comparison) return `<div class="empty">Upload a previous CSV to enable comparison.</div>`;
    return `
        <div class="summary-grid">
            ${card("Changed Terminals", comparison.changedTerminalCount)}
            ${card("New Terminals", comparison.newTerminalCount)}
            ${card("Removed Terminals", comparison.removedTerminalCount)}
        </div>
        <h2>Changed Terminals</h2>
        ${simpleTable(["Store ID", "Store Name", "Terminal ID", "Computer Name", "Type", "Previous Version", "Current Version", "Change"], comparison.changedTerminals.map(item => [
            item.storeId, item.storeName, item.terminalId, item.computerName, item.terminalType, item.previousVersion, badge(item.currentVersion, report), item.changeType
        ]))}`;
}

function simpleTable(headers, rows) {
    if (!rows?.length) return `<div class="empty">No rows to show.</div>`;
    return `
        <div class="table-wrap">
            <table>
                <thead><tr>${headers.map(header => `<th>${escapeHtml(header)}</th>`).join("")}</tr></thead>
                <tbody>
                    ${rows.map(row => `<tr>${row.map(cell => `<td>${cell ?? ""}</td>`).join("")}</tr>`).join("")}
                </tbody>
            </table>
        </div>`;
}

function bindReport() {
    document.querySelectorAll(".tab-btn").forEach(button => {
        button.addEventListener("click", () => activateReportTab(button.dataset.tab));
    });
    document.getElementById("reportSearch")?.addEventListener("input", event => filterRows(event.target.value));
    document.querySelectorAll(".dashboard-shortcut").forEach(button => {
        button.addEventListener("click", () => followDashboardShortcut(button.dataset.shortcut));
    });
    bindSortableHeaders();
}

function activateReportTab(tab, search = null, sectionId = "") {
    state.activeTab = tab;
    document.querySelectorAll(".tab-btn").forEach(item => item.classList.toggle("active", item.dataset.tab === tab));
    document.getElementById("tabContent").innerHTML = tabContent(state.report, state.activeTab);
    const searchInput = document.getElementById("reportSearch");
    if (search !== null && searchInput) searchInput.value = search;
    filterRows(searchInput?.value || "");
    bindSortableHeaders();
    const target = sectionId ? document.getElementById(sectionId) : document.getElementById("tabContent");
    target?.scrollIntoView({ behavior: "smooth", block: "start" });
    if (searchInput && search !== null) searchInput.focus({ preventScroll: true });
}

function followDashboardShortcut(shortcut) {
    const stableVersion = state.report?.summary?.currentStableVersion || "";
    const actions = {
        pos: () => activateReportTab("current", "", "pos-versions"),
        stable: () => activateReportTab("current", stableVersion && stableVersion !== "N/A" ? stableVersion : "", "pos-versions"),
        "outdated-stores": () => activateReportTab("stores", "out-of-date-store"),
        kiosk: () => activateReportTab("current", "", "kiosk-versions"),
        qubox: () => activateReportTab("current", "", "qubox-versions"),
        qukds: () => activateReportTab("current", "", "qukds-versions"),
        quorb: () => activateReportTab("current", "", "quorb-versions"),
    };
    (actions[shortcut] || actions.pos)();
}

function bindSortableHeaders() {
    document.querySelectorAll("#tabContent th").forEach((th, index) => th.addEventListener("click", () => sortTable(th.closest("table"), index)));
}

function filterRows(query) {
    const needle = query.trim().toLowerCase();
    document.querySelectorAll("#tabContent tbody tr").forEach(row => {
        row.style.display = !needle || row.textContent.toLowerCase().includes(needle) ? "" : "none";
    });
}

function sortTable(table, columnIndex) {
    const tbody = table?.querySelector("tbody");
    if (!tbody) return;
    const rows = [...tbody.querySelectorAll("tr")];
    const direction = table.dataset.sortDirection === "asc" ? "desc" : "asc";
    table.dataset.sortDirection = direction;
    rows.sort((a, b) => {
        const left = a.children[columnIndex]?.textContent.trim() || "";
        const right = b.children[columnIndex]?.textContent.trim() || "";
        const leftNumber = Number(left.replaceAll(",", ""));
        const rightNumber = Number(right.replaceAll(",", ""));
        const result = Number.isFinite(leftNumber) && Number.isFinite(rightNumber)
            ? leftNumber - rightNumber
            : left.localeCompare(right, undefined, { numeric: true, sensitivity: "base" });
        return direction === "asc" ? result : -result;
    });
    tbody.append(...rows);
}

boot();
