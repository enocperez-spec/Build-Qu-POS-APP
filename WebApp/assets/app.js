const state = {
    report: window.__QU_REPORT__ || null,
    user: window.__QU_BOOTSTRAP__?.user || null,
    needsSetup: false,
    authStatusError: "",
    pendingTwoFactor: null,
    htmlUrl: null,
    reports: [],
    uploads: [],
    dashboardUploads: [],
    selectedUploadId: "",
    dashboardHealth: null,
    activeTab: "current",
    uploadTab: "terminal",
    settingsTab: "users",
    currentPage: "dashboard",
    deviceHealthDashboard: null,
    deviceHealthStore: null,
    deviceHealthDays: 30,
    deviceHealthQuery: "",
    deviceHealthPage: 1,
    deviceHealthPageSize: 10,
    deviceHealthChartObserver: null,
    releaseNotesReturnPage: "dashboard",
    baseReport: window.__QU_REPORT__ || null,
    brandFilter: { mode: "all", brand: "", combination: "", selectedBrands: [], match: "any" },
};

const FEATURE_RELEASES = [
    {
        version: "v001.01",
        releasedAt: "August 11, 2026 10:15 EST",
        title: "HTML5 Web Application Foundation",
        description: "Created the IONOS-ready PHP and HTML5 web application foundation for uploading terminal CSV files, generating searchable QU POS reports, and displaying the latest dashboard report.",
        type: "Feature",
        status: "Released",
    },
    {
        version: "v001.02",
        releasedAt: "August 11, 2026 10:35 EST",
        title: "CSV Upload History",
        description: "Added database-backed CSV uploads so every uploaded file is retained as historical data instead of replacing the previous dataset.",
        type: "Feature",
        status: "Released",
    },
    {
        version: "v001.03",
        releasedAt: "August 11, 2026 10:55 EST",
        title: "Current And Previous CSV Comparison",
        description: "Added comparison logic so the newest CSV becomes the current dataset and the immediately previous upload becomes the comparison dataset.",
        type: "Feature",
        status: "Released",
    },
    {
        version: "v001.04",
        releasedAt: "August 11, 2026 11:15 EST",
        title: "Protected Login And Two-Factor Authentication",
        description: "Added protected sign-in, first-admin setup, authenticator-app two-factor verification, and QR-code setup support.",
        type: "Security",
        status: "Released",
    },
    {
        version: "v001.05",
        releasedAt: "August 11, 2026 11:35 EST",
        title: "User Roles And User Management",
        description: "Added Admin, Tech, and Read-Only roles with user creation, role assignment, activation, deactivation, deletion, and 2FA reset controls.",
        type: "Feature",
        status: "Released",
    },
    {
        version: "v001.06",
        releasedAt: "August 11, 2026 11:55 EST",
        title: "Dashboard Historical Upload Selector",
        description: "Added a dashboard dropdown for loading past CSV uploads while keeping the dashboard focused on the latest generated report by default.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v001.07",
        releasedAt: "August 11, 2026 12:20 EST",
        title: "Navigation And Logout Polish",
        description: "Highlighted the active navigation page and moved Log Out to the top-right user area with compact red button styling.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v001.08",
        releasedAt: "August 11, 2026 13:05 EST",
        title: "Cloud Import Automation Endpoint",
        description: "Added a protected cloud-import endpoint and GitHub Actions Playwright automation template for scheduled QU Admin terminal export and web-app import.",
        type: "Feature",
        status: "Released",
    },
    {
        version: "v001.09",
        releasedAt: "August 11, 2026 14:25 EST",
        title: "Dashboard Metric Card Icons And Colors",
        description: "Added icon-based dashboard metric cards with distinct colors for POS terminals, stable version, out-of-date stores, Kiosk versions, QuBox versions, and other versions.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v002.00",
        releasedAt: "August 11, 2026 14:45 EST",
        title: "Responsive Stable-Version Card",
        description: "Improved dashboard card responsiveness so long stable-version values resize and stay inside the metric card on narrower screens.",
        type: "Bug Fix",
        status: "Released",
    },
    {
        version: "v002.01",
        releasedAt: "August 11, 2026 15:30 EST",
        title: "GitHub README And Dashboard Screenshot",
        description: "Updated the GitHub README with a full application overview, role descriptions, project layout, validation commands, and a dashboard screenshot.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v002.02",
        releasedAt: "August 11, 2026 16:25 EST",
        title: "Feature Release Tracking",
        description: "Added a Feature Releases section and version-tracking history so each completed request can be recorded with version, date, title, description, change type, and release status.",
        type: "Feature",
        status: "Released",
    },
    {
        version: "v002.03",
        releasedAt: "August 11, 2026 16:32 EST",
        title: "Application Footer And Release Notes",
        description: "Added a consistent footer with clickable application version, a dedicated Release Notes page, newest-first release history, and a Back to Application button.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v002.04",
        releasedAt: "August 11, 2026 16:35 EST",
        title: "Legacy Windows Tool Cleanup",
        description: "Removed legacy PowerShell and HTA launcher files from the GitHub project so the repository reflects the web application codebase.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v002.05",
        releasedAt: "August 11, 2026 16:37 EST",
        title: "Header Version Badge Cleanup",
        description: "Removed the application version badge from the top page header while keeping the clickable footer version link for release notes.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v002.06",
        releasedAt: "August 11, 2026 16:38 EST",
        title: "GitHub Actions CSV Automation",
        description: "Added a scheduled GitHub Actions workflow that exports the QU Admin terminal CSV every 6 hours and imports it into the web app through the protected cloud-import endpoint.",
        type: "Feature",
        status: "Released",
    },
    {
        version: "v002.07",
        releasedAt: "August 11, 2026 16:41 EST",
        title: "Dashboard Last Updated Header",
        description: "Replaced the current date and time under the username with a Dashboard Last Updated timestamp based on the loaded report generation time.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v002.08",
        releasedAt: "August 11, 2026 17:21 EST",
        title: "QU Export Failure Diagnostics",
        description: "Added GitHub Actions failure artifacts for the QU Admin export automation, including screenshot, page HTML, URL, page title, and error summary.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v002.09",
        releasedAt: "August 11, 2026 17:36 EST",
        title: "QU Admin Login Automation Fix",
        description: "Improved the GitHub Actions QU Admin login flow to wait for the login form, fill visible username and password fields, and confirm login before searching for the Actions button.",
        type: "Bug Fix",
        status: "Released",
    },
    {
        version: "v003.00",
        releasedAt: "August 11, 2026 17:45 EST",
        title: "QU Export Target Page Detection",
        description: "Fixed the GitHub Actions QU export automation so it stops retrying login after successfully reaching the terminals page.",
        type: "Bug Fix",
        status: "Released",
    },
    {
        version: "v003.01",
        releasedAt: "August 11, 2026 17:48 EST",
        title: "QU Export Readiness Detection",
        description: "Improved the QU Admin automation to wait for the actual login form or Actions button instead of relying on URL timing during redirects.",
        type: "Bug Fix",
        status: "Released",
    },
    {
        version: "v003.02",
        releasedAt: "August 11, 2026 18:06 EST",
        title: "Upload Page Report Cleanup",
        description: "Removed the dashboard/report view from the Upload CSV page so the section only shows upload controls and CSV Upload History.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v003.03",
        releasedAt: "August 11, 2026 18:10 EST",
        title: "Admin-Only Users Navigation",
        description: "Reinforced Users page access so only Admin users can see or open user management, while Tech and Read-Only roles are redirected away.",
        type: "Security",
        status: "Released",
    },
    {
        version: "v003.04",
        releasedAt: "August 11, 2026 18:11 EST",
        title: "Footer-Only Release Notes Access",
        description: "Removed Release Notes from the left navigation while keeping release notes available through the clickable footer version.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v003.05",
        releasedAt: "August 11, 2026 18:12 EST",
        title: "Modern Logout Button Styling",
        description: "Updated the Log Out button with a brighter red pill style, stronger contrast, subtle glow, and modern hover interaction.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v003.06",
        releasedAt: "August 11, 2026 18:38 EST",
        title: "Admin Settings And Role-Based Navigation Management",
        description: "Added an Admin-only Settings section containing Users, User Roles, API Logs, and API Call Times. Added editable role permissions, QU EI schedule management, and automatic permission registration for navigation sections.",
        type: "Feature",
        status: "Released",
    },
    {
        version: "v003.07",
        releasedAt: "August 11, 2026 18:55 EST",
        title: "Production Asset Cache Busting",
        description: "Added automatic cache-busting query strings to CSS and JavaScript assets so browsers load newly deployed dashboard and Settings updates immediately.",
        type: "Bug Fix",
        status: "Released",
    },
    {
        version: "v003.08",
        releasedAt: "August 11, 2026 19:15 EST",
        title: "QU EI Store Data Synchronization",
        description: "Added SQL storage, API logging, schedule entries, and cloud automation support for exporting Store Information from QU EI and importing the latest store data.",
        type: "Feature",
        status: "Released",
    },
    {
        version: "v003.09",
        releasedAt: "August 11, 2026 19:35 EST",
        title: "Dashboard Data Health Summary",
        description: "Added dashboard health cards for latest terminal sync, latest store sync, store data volume, and QU EI automation status so users can quickly confirm data freshness.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v004.00",
        releasedAt: "August 11, 2026 19:55 EST",
        title: "Dashboard Trend Indicators",
        description: "Added trend labels to dashboard metric cards showing whether out-of-date stores and stable-version usage improved compared with the previous upload.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v004.01",
        releasedAt: "August 11, 2026 20:15 EST",
        title: "Clickable Dashboard Metric Cards",
        description: "Made dashboard metric cards clickable so users can jump directly to filtered out-of-date stores, POS, Kiosk, QuBox, and other version details.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v004.02",
        releasedAt: "August 11, 2026 20:35 EST",
        title: "Polished Header User Profile Card",
        description: "Updated the top-right dashboard user area with a polished profile card showing initials, username, role, dashboard last updated time, and the Log Out action.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v004.03",
        releasedAt: "August 11, 2026 20:55 EST",
        title: "Cleaner Dashboard Trend Wording",
        description: "Improved the stable usage and out-of-date stores trend labels so unchanged, improved, and increased states read more clearly on the dashboard cards.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v004.04",
        releasedAt: "August 11, 2026 21:05 EST",
        title: "Dashboard Trend Label Cleanup",
        description: "Removed the visual trend chips from the Current Stable Version and Out-Of-Date Stores dashboard cards for a cleaner card layout.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v004.05",
        releasedAt: "August 11, 2026 21:25 EST",
        title: "QuKDS And QuORB Version Split",
        description: "Split Other Terminal Versions into dedicated QuKDS and QuORB dashboard cards and report sections while keeping unmatched terminal versions in Other.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v004.06",
        releasedAt: "August 11, 2026 21:35 EST",
        title: "Remove Other Versions Dashboard Category",
        description: "Removed the Other Versions dashboard card and Other Terminal Versions report section now that QuKDS and QuORB are split into their own categories.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v004.07",
        releasedAt: "August 11, 2026 21:50 EST",
        title: "Compact Dashboard Sync Status Bar",
        description: "Replaced the large dashboard sync and job health cards with a slimmer single-line status bar to reduce vertical space.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v004.08",
        releasedAt: "August 11, 2026 22:05 EST",
        title: "Dashboard Metric Card Alignment",
        description: "Improved dashboard metric card alignment so labels, icons, values, and metadata stay grouped inside each card, with long version numbers kept together.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v004.09",
        releasedAt: "August 11, 2026 22:25 EST",
        title: "Stable Version Adoption Dashboard",
        description: "Updated the Kiosk, QuBox, QuKDS, and QuORB dashboard cards to display the current stable version and its usage percentage. The dashboard layout was also adjusted to display all seven metric cards in one row on standard desktop screens.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v005.00",
        releasedAt: "August 11, 2026 22:35 EST",
        title: "Production POS Version Label",
        description: "Changed the dashboard section title from Downloadable QU POS Versions to Downloadable Production Qu POS Version for clearer production reporting.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v005.01",
        releasedAt: "August 11, 2026 22:45 EST",
        title: "Application Version Badge Colors",
        description: "Applied the same stable, current, higher, and out-of-date version color coding used for QU POS to the Kiosk, QuBox, QuKDS, and QuORB version sections.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v005.02",
        releasedAt: "August 11, 2026 22:55 EST",
        title: "Product-Specific Version Card Labels",
        description: "Updated version detail cards so POS uses Terminals, while Kiosk, QuBox, QuKDS, and QuORB sections show product-specific device labels with live device and store counts.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v005.03",
        releasedAt: "August 11, 2026 23:05 EST",
        title: "Store Operational Status Badges",
        description: "Added store status indicators to version drill-down tables so each store can show Live, Not Operational, or No Store Data from the latest QU EI Store Information import.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v005.04",
        releasedAt: "August 11, 2026 23:15 EST",
        title: "Store Status Badge Color Polish",
        description: "Updated store status badge colors so Live displays in green and Not Operational displays in gray for clearer visual separation.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v005.05",
        releasedAt: "August 11, 2026 23:30 EST",
        title: "Store Export Modal Handling",
        description: "Updated the cloud Store Information export automation to dismiss the QU EI What's New modal after login so scheduled store syncs can continue to the export action.",
        type: "Bug Fix",
        status: "Released",
    },
    {
        version: "v005.06",
        releasedAt: "August 11, 2026 23:15 EST",
        title: "Store Export Modal Close Reliability",
        description: "Improved the QU EI Store Information export automation with multiple short-timeout close strategies for the What's New modal, including Escape and X-button fallbacks.",
        type: "Bug Fix",
        status: "Released",
    },
    {
        version: "v005.07",
        releasedAt: "August 11, 2026 23:50 EST",
        title: "Store Export Login Detection Fix",
        description: "Fixed QU EI Store Information export automation so post-login page inputs are not mistaken for the login form after the What's New modal closes.",
        type: "Bug Fix",
        status: "Released",
    },
    {
        version: "v005.08",
        releasedAt: "August 12, 2026 00:00 EST",
        title: "Store Export Menu Label Fix",
        description: "Updated the QU EI Store Information export automation to click the current Export Stores Info menu label used on the Stores page.",
        type: "Bug Fix",
        status: "Released",
    },
    {
        version: "v005.09",
        releasedAt: "August 12, 2026 00:10 EST",
        title: "Store Export Selector Hardening",
        description: "Updated the QU EI Store Information export automation to use the Stores page export test ID and pair export clicks with the download listener for clearer failures.",
        type: "Bug Fix",
        status: "Released",
    },
    {
        version: "v006.00",
        releasedAt: "August 11, 2026 23:15 EST",
        title: "Protected Store Status Lookup",
        description: "Added a protected store-status lookup endpoint so support users and automation can verify a store's latest QU EI operational status by store name or store ID.",
        type: "Feature",
        status: "Released",
    },
    {
        version: "v006.01",
        releasedAt: "August 11, 2026 21:54 EST",
        title: "Release Notes Page Cleanup",
        description: "Removed the Back to Application button and version sequence section from the Release Notes page for a cleaner release history view.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v006.02",
        releasedAt: "August 11, 2026 21:57 EST",
        title: "Release Notes Current Version Spacing",
        description: "Added spacing around the Current Version badge on the Release Notes page so the header area has cleaner separation from the panel edge and release list.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v006.03",
        releasedAt: "August 11, 2026 22:01 EST",
        title: "Release Notes Scroll Position",
        description: "Updated the footer version link so opening Release Notes always starts at the top of the page.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v006.04",
        releasedAt: "August 11, 2026 22:12 EST",
        title: "Filtered Report Export Buttons",
        description: "Added CSV and Excel export buttons so users can download the current filtered and sorted report view, including out-of-date stores and mixed-version store lists.",
        type: "Feature",
        status: "Released",
    },
    {
        version: "v006.05",
        releasedAt: "August 11, 2026 22:17 EST",
        title: "Co-Brand Dashboard Filtering",
        description: "Added support for store brand metadata, co-branded store relationships, dashboard brand filters, dynamic co-brand combinations, and filtered exports without double-counting stores.",
        type: "Feature",
        status: "Released",
    },
    {
        version: "v006.06",
        releasedAt: "August 11, 2026 22:28 EST",
        title: "Compact Dashboard Control Row",
        description: "Combined the past upload selector, sync/job status indicators, and dashboard brand filter into one compact dashboard control row to reduce vertical clutter.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v006.07",
        releasedAt: "August 11, 2026 22:45 EST",
        title: "Manual Store Information Upload",
        description: "Added a Store Information CSV upload tab so Tech and Admin users can manually import QU EI store data into SQL when automation is unavailable.",
        type: "Feature",
        status: "Released",
    },
    {
        version: "v006.08",
        releasedAt: "August 11, 2026 22:52 EST",
        title: "Store Import History Tab",
        description: "Updated the Upload CSV page so the Store Information tab displays Store Information CSV import history instead of terminal upload history.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v006.09",
        releasedAt: "August 11, 2026 23:05 EST",
        title: "Store Status Matching Fix",
        description: "Improved Store Information matching so store status can resolve by alternate store ID fields, leading store numbers in names, and normalized store-name fallback.",
        type: "Bug Fix",
        status: "Released",
    },
    {
        version: "v007.00",
        releasedAt: "August 11, 2026 23:20 EST",
        title: "QuBox Down Report Tab",
        description: "Added a QuBox Down report tab that identifies stores with no QuBox in the current terminal export or a QuBox last seen online more than two days ago.",
        type: "Feature",
        status: "Released",
    },
    {
        version: "v007.01",
        releasedAt: "August 12, 2026 00:02 EST",
        title: "Device Health Dashboard Improvements",
        description: "Added top-level and site-level Device Health dashboards based on the approved screenshots and mock-ups. Updated the dashboards to use accurate SQL data, removed unsupported region components, and added validation for device totals, health scores, status counts, version adoption, and brand filtering.",
        type: "Feature",
        status: "Released",
    },
    {
        version: "v007.02",
        releasedAt: "August 12, 2026 00:34 EST",
        title: "Store Health Navigation",
        description: "Added a dedicated Store Health navigation page with direct store search, reporting-period selection, and scorecard routing while preserving the fleet-level Device Health dashboard.",
        type: "Improvement",
        status: "Released",
    },
    {
        version: "v007.03",
        releasedAt: "August 12, 2026 00:43 EST",
        title: "Live Store Device Health Scope",
        description: "Limited the Device Health dashboard to stores whose latest QU EI operational status is Live. Not Operational and unverified-status stores are excluded before all metrics, trends, version adoption, brand filters, and tables are calculated, with scope counts displayed on the dashboard.",
        type: "Improvement",
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
        tech: { dashboard: true, reports: true, upload: true, alerts: true, device_health: true, settings: false },
        read_only: { dashboard: true, reports: true, upload: false, alerts: true, device_health: true, settings: false },
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
                ${canAccess("device_health") ? `<button class="${navClass("deviceHealth")}" id="deviceHealthNavBtn">Device Health</button>` : ""}
                ${canAccess("device_health") ? `<button class="${navClass("storeHealth")}" id="storeHealthNavBtn">Store Health</button>` : ""}
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
        <section class="panel upload-workspace">
            <div class="upload-tabs" role="tablist" aria-label="CSV upload type">
                <button class="upload-tab${state.uploadTab === "terminal" ? " active" : ""}" type="button" data-upload-tab="terminal">Terminal CSV</button>
                <button class="upload-tab${state.uploadTab === "store" ? " active" : ""}" type="button" data-upload-tab="store">Store Information CSV</button>
            </div>
            <div class="upload-tab-panel${state.uploadTab === "terminal" ? " active" : ""}" id="terminalUploadPanel">
                <div class="upload-panel">
                    <div class="drop-card">
                        <label for="currentCsv">Terminal CSV Upload <span class="subtle">(required)</span></label>
                        <p class="subtle">Uploads the QU EI terminal export, saves it to SQL history, and builds the latest searchable report.</p>
                        <input class="file-input" id="currentCsv" type="file" accept=".csv,text/csv">
                        <div id="currentFileName" class="file-name">No file selected</div>
                    </div>
                    <div class="actions">
                        <button class="btn primary" id="generateBtn">Upload Terminal CSV And Build Report</button>
                    </div>
                </div>
                <section class="progress-panel">
                    <div class="steps">
                        <div class="step" data-step="0"><strong>Loading CSV</strong><br><span>Waiting</span></div>
                        <div class="step" data-step="1"><strong>Building report</strong><br><span>Waiting</span></div>
                        <div class="step" data-step="2"><strong>Writing HTML</strong><br><span>Waiting</span></div>
                        <div class="step" data-step="3"><strong>Done</strong><br><span>Waiting</span></div>
                    </div>
                    <div id="statusMessage" class="status-message"></div>
                </section>
            </div>
            <div class="upload-tab-panel${state.uploadTab === "store" ? " active" : ""}" id="storeUploadPanel">
                <div class="upload-panel">
                    <div class="drop-card">
                        <label for="storeCsv">Store Information CSV Upload <span class="subtle">(required)</span></label>
                        <p class="subtle">Uploads the QU EI Store Information export into SQL as a manual backup for the automated store sync.</p>
                        <input class="file-input" id="storeCsv" type="file" accept=".csv,text/csv">
                        <div id="storeFileName" class="file-name">No file selected</div>
                    </div>
                    <div class="actions">
                        <button class="btn primary" id="storeImportBtn">Upload Store Information CSV</button>
                    </div>
                </div>
                <section class="progress-panel">
                    <div class="steps store-steps">
                        <div class="step" data-store-step="0"><strong>Loading CSV</strong><br><span>Waiting</span></div>
                        <div class="step" data-store-step="1"><strong>Saving to SQL</strong><br><span>Waiting</span></div>
                        <div class="step" data-store-step="2"><strong>Updating store data</strong><br><span>Waiting</span></div>
                        <div class="step" data-store-step="3"><strong>Done</strong><br><span>Waiting</span></div>
                    </div>
                    <div id="storeStatusMessage" class="status-message"></div>
                </section>
            </div>
        </section>`;
}

function renderHome() {
    state.currentPage = "dashboard";
    state.report = null;
    state.baseReport = null;
    state.selectedUploadId = "";
    app.innerHTML = shell(header() + `<div id="dashboardControlsMount">${dashboardControlPanel()}</div><div id="reportMount">${latestReportLoading()}</div>`, "dashboard");
    bindShell();
    bindDashboardControls();
    if (state.report) bindReport();
    loadDashboardUploads();
    if (!state.report) loadLatestReport();
}

function dashboardControlPanel() {
    return `
        <section class="dashboard-control-panel">
            <div class="dashboard-control-section dashboard-control-upload">
                <div>
                    <h2>Past Data Uploads</h2>
                    <p class="subtle">Select a saved CSV upload to view that historical report. The selected upload is compared against the upload immediately before it.</p>
                </div>
                <div class="selector-actions">
                    <select class="text-input" id="dashboardUploadSelect">
                        ${dashboardUploadOptions()}
                    </select>
                    <button class="btn" id="loadUploadReportBtn" type="button">Load Upload</button>
                </div>
            </div>
            <div class="dashboard-control-section dashboard-control-health">
                ${dashboardHealthItems(state.dashboardHealth)}
            </div>
            ${brandFilterBar("dashboard")}
        </section>`;
}

function dashboardHealthPanel(health) {
    if (!health) return "";
    return `<section class="dashboard-health">${dashboardHealthItems(health)}</section>`;
}

function dashboardHealthItems(health) {
    if (!health) return `
        ${healthItem("Terminal Sync", "Loading...", "Checking latest upload", "neutral")}
        ${healthItem("Store Sync", "Loading...", "Checking latest store import", "neutral")}
        ${healthItem("Terminal Job", "Loading...", "Checking automation", "neutral")}
        ${healthItem("Store Job", "Loading...", "Checking automation", "neutral")}`;
    const terminal = health.latestTerminalUpload;
    const store = health.latestStoreImport;
    const jobs = health.apiJobs || [];
    const terminalJob = jobs.find(job => job.jobKey === "qu_ei_terminals_csv");
    const storeJob = jobs.find(job => job.jobKey === "qu_ei_stores_csv");
    return `
        ${healthItem("Terminal Sync", terminal ? dateTimeLabel(terminal.uploadedAt) : "Not synced", terminal ? `${terminal.rowCount} rows` : "Upload or run job", terminal ? "sync" : "warning")}
        ${healthItem("Store Sync", store ? dateTimeLabel(store.uploadedAt) : "Not synced", store ? `${store.rowCount} stores` : "Run Store export", store ? "store" : "warning")}
        ${healthItem("Terminal Job", terminalJob?.status || "Not Run Yet", terminalJob ? `Next ${dateTimeLabel(terminalJob.nextRunAt)}` : "No schedule found", statusTone(terminalJob?.status))}
        ${healthItem("Store Job", storeJob?.status || "Not Run Yet", storeJob ? `Next ${dateTimeLabel(storeJob.nextRunAt)}` : "No schedule found", statusTone(storeJob?.status))}`;
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

function setLoadedReport(report) {
    state.baseReport = report;
    state.report = applyBrandFilter(report);
}

function bindDashboardUploadSelector() {
    document.getElementById("loadUploadReportBtn")?.addEventListener("click", () => {
        const id = document.getElementById("dashboardUploadSelect")?.value || "";
        if (!id) {
            state.report = null;
            state.baseReport = null;
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

function bindDashboardControls() {
    bindDashboardUploadSelector();
    bindBrandFilters();
}

function dashboardUploadOptions() {
    const uploads = state.dashboardUploads || [];
    return `<option value="">Latest Generated Report</option>` + uploads.map((upload, index) => {
        const label = `${index === 0 ? "Current CSV" : (index === 1 ? "Previous CSV" : "Historical CSV")} - ${new Date(upload.uploadedAt).toLocaleString()} - ${upload.filename}`;
        return `<option value="${escapeHtml(upload.id)}"${String(upload.id) === String(state.selectedUploadId) ? " selected" : ""}>${escapeHtml(label)}</option>`;
    }).join("");
}

function refreshDashboardControls() {
    const mount = document.getElementById("dashboardControlsMount");
    if (!mount) return;
    mount.innerHTML = dashboardControlPanel();
    bindDashboardControls();
}

function readOnlyPanel() {
    return `
        <section class="panel" style="padding:24px;">
            <h2 style="margin-top:0;">Read-Only Access</h2>
            <p class="subtle">You can open saved reports, search report data, and review alerts. Uploading CSV files and generating new reports requires a Tech or Admin role.</p>
            <button class="btn primary" id="viewReportsBtn">View Reports</button>
        </section>`;
}

function uploadHistoryPanel() {
    const title = state.uploadTab === "store" ? "Store Information CSV History" : "Terminal CSV Upload History";
    return `<section class="panel" style="padding:20px;margin-top:18px;"><h2 id="uploadHistoryTitle">${escapeHtml(title)}</h2><div id="uploadHistory" class="report-list"><div class="empty">Loading upload history...</div></div></section>`;
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
    app.innerHTML = shell(header() + uploadPanel() + uploadHistoryPanel(), "upload");
    bindShell();
    bindUpload();
    loadActiveUploadHistory();
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
    document.getElementById("deviceHealthNavBtn")?.addEventListener("click", renderDeviceHealthPage);
    document.getElementById("storeHealthNavBtn")?.addEventListener("click", renderStoreHealthPage);
    document.getElementById("settingsNavBtn")?.addEventListener("click", () => renderSettings("users"));
    bindFooter();
}

function bindFooter() {
    document.getElementById("footerVersionBtn")?.addEventListener("click", () => renderReleaseNotes(state.currentPage || "dashboard"));
}

function scrollPageToTop() {
    requestAnimationFrame(() => window.scrollTo({ top: 0, left: 0, behavior: "auto" }));
}

async function logout() {
    await fetch("api/auth.php?action=logout", { method: "POST" });
    state.user = null;
    state.report = null;
    state.baseReport = null;
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
    const store = document.getElementById("storeCsv");
    current?.addEventListener("change", () => document.getElementById("currentFileName").textContent = current.files[0]?.name || "No file selected");
    store?.addEventListener("change", () => document.getElementById("storeFileName").textContent = store.files[0]?.name || "No file selected");
    document.getElementById("generateBtn")?.addEventListener("click", generateReport);
    document.getElementById("storeImportBtn")?.addEventListener("click", importStoreCsv);
    document.querySelectorAll(".upload-tab").forEach(button => {
        button.addEventListener("click", () => activateUploadTab(button.dataset.uploadTab || "terminal"));
    });
}

function activateUploadTab(tab) {
    state.uploadTab = tab === "store" ? "store" : "terminal";
    document.querySelectorAll(".upload-tab").forEach(button => button.classList.toggle("active", button.dataset.uploadTab === tab));
    document.getElementById("terminalUploadPanel")?.classList.toggle("active", state.uploadTab === "terminal");
    document.getElementById("storeUploadPanel")?.classList.toggle("active", state.uploadTab === "store");
    const title = document.getElementById("uploadHistoryTitle");
    if (title) title.textContent = state.uploadTab === "store" ? "Store Information CSV History" : "Terminal CSV Upload History";
    loadActiveUploadHistory();
}

function setStep(index, label) {
    document.querySelectorAll(".step").forEach((step, i) => {
        step.classList.toggle("done", i < index);
        step.classList.toggle("active", i === index);
        step.querySelector("span").textContent = i < index ? "Completed" : (i === index ? "In progress" : "Waiting");
    });
    document.getElementById("statusMessage").textContent = label;
}

function setStoreStep(index, label) {
    document.querySelectorAll("[data-store-step]").forEach((step, i) => {
        step.classList.toggle("done", i < index);
        step.classList.toggle("active", i === index);
        step.querySelector("span").textContent = i < index ? "Completed" : (i === index ? "In progress" : "Waiting");
    });
    document.getElementById("storeStatusMessage").textContent = label;
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
        setLoadedReport(payload.report);
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

async function importStoreCsv() {
    const store = document.getElementById("storeCsv");
    if (!store.files[0]) {
        document.getElementById("storeStatusMessage").textContent = "Choose a Store Information CSV file first.";
        return;
    }

    const form = new FormData();
    form.append("storeCsv", store.files[0]);

    const button = document.getElementById("storeImportBtn");
    button.disabled = true;
    try {
        setStoreStep(0, "Loading CSV");
        await pause(180);
        setStoreStep(1, "Saving to SQL");
        const response = await fetch("api/store-import.php", { method: "POST", body: form });
        const payload = await response.json();
        if (!payload.ok) throw new Error(payload.error || "Store Information import failed.");
        setStoreStep(2, "Updating store data");
        state.dashboardHealth = payload.health || state.dashboardHealth;
        await pause(180);
        setStoreStep(4, `Done. Imported ${payload.rowCount} store rows.`);
        await loadStoreImports();
    } catch (error) {
        document.getElementById("storeStatusMessage").textContent = error.message;
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
            refreshDashboardControls();
            mount.innerHTML = `<section class="empty" style="margin-top:18px;">No generated reports yet. Tech or Admin users can upload a CSV from the Upload CSV page.</section>`;
            return;
        }
        setLoadedReport(payload.report);
        state.dashboardHealth = payload.health || null;
        state.htmlUrl = payload.metadata?.url || null;
        refreshHeaderLastUpdated();
        refreshDashboardControls();
        mount.innerHTML = reportView(state.report, { includeBrandFilter: false });
        bindReport();
    } catch (error) {
        mount.innerHTML = `<section class="empty" style="margin-top:18px;">${escapeHtml(error.message)}</section>`;
    }
}

async function loadDashboardUploads() {
    try {
        const response = await fetch("api/uploads.php?action=list");
        const payload = await response.json();
        if (!payload.ok) throw new Error(payload.error || "Could not load upload history.");
        state.dashboardUploads = payload.uploads || [];
        refreshDashboardControls();
        const select = document.getElementById("dashboardUploadSelect");
        if (!select) return;
        const currentValue = select.value;
        select.innerHTML = dashboardUploadOptions();
        select.value = state.selectedUploadId || currentValue;
    } catch (error) {
        const select = document.getElementById("dashboardUploadSelect");
        if (!select) return;
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
        setLoadedReport(payload.report);
        state.selectedUploadId = String(id);
        state.htmlUrl = null;
        refreshHeaderLastUpdated();
        refreshDashboardControls();
        mount.innerHTML = reportView(state.report, { includeBrandFilter: false });
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

async function loadStoreImports() {
    const list = document.getElementById("uploadHistory");
    if (!list) return;
    const response = await fetch("api/uploads.php?action=list-store-imports");
    const payload = await response.json();
    if (!payload.ok) {
        list.innerHTML = `<div class="empty">${escapeHtml(payload.error)}</div>`;
        return;
    }
    const imports = payload.imports || [];
    list.innerHTML = imports.length ? imports.map((item, index) => `
        <div class="report-item">
            <div>
                <strong>${escapeHtml(item.filename)}</strong><br>
                <span class="subtle">${index === 0 ? "Current Store Information CSV" : "Historical Store Information CSV"} • ${escapeHtml(new Date(item.uploadedAt).toLocaleString())} • ${escapeHtml(item.rowCount)} stores</span>
            </div>
        </div>`).join("") : `<div class="empty">No Store Information CSV imports have been saved yet.</div>`;
}

function loadActiveUploadHistory() {
    const list = document.getElementById("uploadHistory");
    if (list) list.innerHTML = `<div class="empty">Loading upload history...</div>`;
    return state.uploadTab === "store" ? loadStoreImports() : loadUploads();
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

async function renderDeviceHealthPage() {
    if (!canAccess("device_health")) {
        renderHome();
        return;
    }
    state.currentPage = "deviceHealth";
    state.deviceHealthStore = null;
    app.innerHTML = shell(header() + `
        <div id="deviceHealthMount">
            <section class="health-loading-state">
                <span class="health-loading-ring" aria-hidden="true"></span>
                <strong>Calculating 30-day device health...</strong>
                <span>Reconciling historical SQL snapshots and distinct devices.</span>
            </section>
        </div>`, "deviceHealth");
    bindShell();
    await loadDeviceHealthDashboard();
}

function renderStoreHealthPage() {
    if (!canAccess("device_health")) {
        renderHome();
        return;
    }
    state.currentPage = "storeHealth";
    state.deviceHealthStore = null;
    app.innerHTML = shell(header() + `
        <section class="device-health-page health-store-landing-page">
            <div class="health-page-heading">
                <div>
                    <h2>Store Health</h2>
                    <p>Search for a store to open its device health scorecard.</p>
                </div>
                <div class="health-updated">
                    <span class="health-live-dot" aria-hidden="true"></span>
                    <span>Historical terminal CSV snapshots</span>
                </div>
            </div>

            <section class="health-store-search-card">
                <div class="health-store-search-intro">
                    <span class="health-store-search-icon" aria-hidden="true">${metricIcon("terminals")}</span>
                    <div>
                        <h3>Open a Store Device Health Scorecard</h3>
                        <p>Search by Store ID, Store Name, or Brand to review every reporting device, its current status, and historical health score.</p>
                    </div>
                </div>
                <form class="health-store-finder health-store-landing-finder" id="healthStoreFinderForm">
                    <label for="healthStoreFinder">Find a Store</label>
                    <input class="text-input" id="healthStoreFinder" type="search" placeholder="Enter a Store ID, Store Name, or Brand" autocomplete="off" autofocus>
                    <label class="health-store-period-field" for="storeHealthDays">
                        <span>Reporting Period</span>
                        <select id="storeHealthDays">
                            ${[7, 30, 60, 90].map(days => `<option value="${days}"${state.deviceHealthDays === days ? " selected" : ""}>Last ${days} Days</option>`).join("")}
                        </select>
                    </label>
                    <button class="btn" type="submit">Search Stores</button>
                </form>
                <div id="healthStoreSearchResults">
                    <div class="health-store-search-empty">
                        <strong>Ready to search</strong>
                        <span>Selecting a result opens the complete Store Device Health Scorecard.</span>
                    </div>
                </div>
            </section>
        </section>`, "storeHealth");
    bindShell();
    bindStoreHealthLanding();
    scrollPageToTop();
}

function bindStoreHealthLanding() {
    document.getElementById("healthStoreFinderForm")?.addEventListener("submit", searchDeviceHealthStores);
    document.getElementById("storeHealthDays")?.addEventListener("change", event => {
        state.deviceHealthDays = Number(event.target.value) || 30;
        const results = document.getElementById("healthStoreSearchResults");
        if (results) {
            results.innerHTML = `<div class="health-store-search-empty"><strong>Reporting period updated</strong><span>Search for a store to build its ${escapeHtml(state.deviceHealthDays)}-day scorecard.</span></div>`;
        }
    });
}

async function loadDeviceHealthDashboard() {
    const mount = document.getElementById("deviceHealthMount");
    if (!mount) return;
    const params = deviceHealthQueryParams("dashboard");
    try {
        const response = await fetch(`api/device-health.php?${params.toString()}`);
        const payload = await parseJsonResponse(response);
        if (!payload.ok) throw new Error(payload.error || "Could not load Device Health.");
        state.deviceHealthDashboard = payload.dashboard;
        const pageCount = Math.max(1, Math.ceil((payload.dashboard.stores?.length || 0) / state.deviceHealthPageSize));
        state.deviceHealthPage = Math.min(state.deviceHealthPage, pageCount);
        mount.innerHTML = deviceHealthDashboardView(payload.dashboard);
        bindDeviceHealthDashboard();
        renderDeviceHealthCharts();
    } catch (error) {
        mount.innerHTML = `<section class="empty health-error-state">${escapeHtml(error.message)}</section>`;
    }
}

function deviceHealthQueryParams(action) {
    const params = new URLSearchParams({
        action,
        days: String(state.deviceHealthDays),
        mode: state.brandFilter.mode || "all",
        brand: state.brandFilter.brand || "",
        combination: state.brandFilter.combination || "",
        selectedBrands: (state.brandFilter.selectedBrands || []).join("|"),
        match: state.brandFilter.match || "any",
        query: state.deviceHealthQuery || "",
    });
    return params;
}

function deviceHealthDashboardView(dashboard) {
    const summary = dashboard.summary || {};
    const period = dashboard.period || {};
    const stores = dashboard.stores || [];
    const pageCount = Math.max(1, Math.ceil(stores.length / state.deviceHealthPageSize));
    const start = (state.deviceHealthPage - 1) * state.deviceHealthPageSize;
    const visibleStores = stores.slice(start, start + state.deviceHealthPageSize);
    return `
        <section class="device-health-page">
            <div class="health-page-heading">
                <div>
                    <h2>Device Health Dashboard</h2>
                    <p>Live-store device reporting health calculated from historical CSV snapshots.</p>
                </div>
                <div class="health-updated">
                    <span class="health-live-dot" aria-hidden="true"></span>
                    <span>Data through <strong>${escapeHtml(formatHealthDate(period.end))}</strong></span>
                    <button class="health-refresh-btn" id="refreshDeviceHealthBtn" type="button">Refresh Data</button>
                </div>
            </div>

            ${healthLiveStoreScope(dashboard.scope || {})}
            ${deviceHealthFilters(dashboard)}

            <div class="health-metric-grid">
                ${healthMetricCard("Fleet Health", healthPercent(summary.fleetHealthScore), `${formatNumber(summary.healthyChecks)} healthy of ${formatNumber(summary.totalChecks)} checks`, "healthy", "stable")}
                ${healthMetricCard("Stores at 100%", formatNumber(summary.storesAt100), `of ${formatNumber(summary.totalStores)} filtered stores`, "info", "terminals")}
                ${healthMetricCard("Stores Below 95%", formatNumber(summary.storesBelow95), "Review lowest-scoring stores", "warning", "outdated")}
                ${healthMetricCard("Devices Down Now", formatNumber(summary.devicesDownNow), healthStatusSummary(summary), "critical", "outdated")}
                ${healthMetricCard("QuBox Down", formatNumber(summary.quboxDown), "Warning, critical, or offline", "critical", "qubox")}
                ${healthMetricCard("Kiosk Down", formatNumber(summary.kioskDown), "Warning, critical, or offline", "critical", "kiosk")}
            </div>

            <div class="health-analytics-grid">
                <section class="health-panel health-trend-panel">
                    <div class="health-panel-heading">
                        <div><h3>Fleet Health Trend</h3><p>${escapeHtml(period.snapshotCount || 0)} snapshots in the selected period</p></div>
                        <span class="health-score-key">Healthy checks / Expected checks</span>
                    </div>
                    <canvas id="fleetHealthTrendCanvas" class="health-chart" role="img" aria-label="Fleet health score trend for the selected period"></canvas>
                </section>
                <section class="health-panel health-worst-panel">
                    <div class="health-panel-heading"><div><h3>Worst Stores</h3><p>Lowest health scores in this filtered view</p></div></div>
                    ${worstStoresTable(dashboard.worstStores || [])}
                </section>
            </div>

            <div class="health-analytics-grid health-lower-grid">
                <section class="health-panel health-store-panel">
                    <div class="health-panel-heading">
                        <div><h3>Store Scorecard</h3><p>Click a store to open its device-level scorecard.</p></div>
                        <button class="health-export-btn" id="exportDeviceHealthBtn" type="button">Export CSV</button>
                    </div>
                    ${healthStoreTable(visibleStores, start)}
                    ${healthPagination(stores.length, pageCount)}
                </section>
                <section class="health-panel health-issues-panel">
                    <div class="health-panel-heading"><div><h3>Issues by Device Type</h3><p>Current status and stable-version adoption</p></div></div>
                    <canvas id="deviceIssuesCanvas" class="health-chart health-issues-chart" role="img" aria-label="Current device health status counts by product type"></canvas>
                    ${stableAdoptionTable(dashboard.issuesByType || [])}
                </section>
            </div>

            ${healthMethodologyPanel(dashboard.methodology, period)}
        </section>`;
}

function deviceHealthFilters(dashboard) {
    const brands = dashboard.availableBrands || [];
    const selectedBrands = state.brandFilter.selectedBrands || [];
    const primaryValue = state.brandFilter.mode === "brand" ? `brand:${state.brandFilter.brand}` : state.brandFilter.mode;
    const combinations = uniqueText((dashboard.stores || [])
        .map(store => (store.brands || []).length > 1 ? brandCombinationLabel(store.brands) : "")
        .filter(Boolean)).sort((a, b) => a.localeCompare(b));
    return `
        <section class="health-filter-bar">
            <label class="health-filter-field">
                <span>Reporting Period</span>
                <select class="text-input" id="healthDaysSelect">
                    ${[7, 30, 60, 90].map(days => `<option value="${days}"${state.deviceHealthDays === days ? " selected" : ""}>Last ${days} Days</option>`).join("")}
                </select>
            </label>
            <label class="health-filter-field">
                <span>Brand / Co-Brand</span>
                <select class="text-input" id="healthBrandSelect">
                    <option value="all"${primaryValue === "all" ? " selected" : ""}>All Brands</option>
                    ${brands.map(brand => `<option value="brand:${escapeHtml(brand)}"${primaryValue === `brand:${brand}` ? " selected" : ""}>${escapeHtml(brand)}</option>`).join("")}
                    <option value="cobranded"${primaryValue === "cobranded" ? " selected" : ""}>All Co-Branded Stores</option>
                    <option value="combination"${primaryValue === "combination" ? " selected" : ""}>Custom Brand Combination</option>
                </select>
            </label>
            ${state.brandFilter.mode === "cobranded" ? `
                <label class="health-filter-field">
                    <span>Co-Brand Combination</span>
                    <select class="text-input" id="healthCoBrandSelect">
                        <option value="">All Combinations</option>
                        ${combinations.map(combination => `<option value="${escapeHtml(combination)}"${state.brandFilter.combination === combination ? " selected" : ""}>${escapeHtml(combination)}</option>`).join("")}
                    </select>
                </label>` : ""}
            ${state.brandFilter.mode === "combination" ? `
                <label class="health-filter-field health-filter-multi">
                    <span>Select Brands</span>
                    <select class="text-input" id="healthMultiBrandSelect" multiple size="4">
                        ${brands.map(brand => `<option value="${escapeHtml(brand)}"${selectedBrands.includes(brand) ? " selected" : ""}>${escapeHtml(brand)}</option>`).join("")}
                    </select>
                </label>
                <label class="health-filter-field">
                    <span>Match Rule</span>
                    <select class="text-input" id="healthBrandMatchSelect">
                        <option value="any"${state.brandFilter.match === "any" ? " selected" : ""}>Contains Any</option>
                        <option value="all"${state.brandFilter.match === "all" ? " selected" : ""}>Contains All</option>
                        <option value="exact"${state.brandFilter.match === "exact" ? " selected" : ""}>Exact Combination</option>
                    </select>
                </label>` : ""}
            <label class="health-filter-field health-search-field">
                <span>Search Store ID, Store Name, or Brand</span>
                <input class="text-input" id="healthStoreSearch" type="search" value="${escapeHtml(state.deviceHealthQuery)}" placeholder="Search stores">
            </label>
        </section>`;
}

function healthMetricCard(label, value, meta, tone, icon) {
    return `
        <article class="health-metric health-tone-${tone}">
            <span class="health-metric-icon" aria-hidden="true">${metricIcon(icon)}</span>
            <div><span class="health-metric-label">${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong><small>${escapeHtml(meta)}</small></div>
        </article>`;
}

function healthLiveStoreScope(scope) {
    const notOperational = Number(scope.notOperationalExcluded || 0);
    const unverified = Number(scope.unverifiedStatusExcluded || 0);
    return `
        <section class="health-live-scope" aria-label="Device Health store scope">
            <span class="health-live-scope-icon" aria-hidden="true">${metricIcon("stable")}</span>
            <div class="health-live-scope-copy">
                <strong>Live Stores Only</strong>
                <span>All Device Health metrics, trends, version adoption, and tables include only stores whose latest QU EI status is Live.</span>
            </div>
            <div class="health-live-scope-counts">
                <span><strong>${formatNumber(scope.liveStoresInPeriod || 0)}</strong> Live stores</span>
                <span><strong>${formatNumber(notOperational)}</strong> Not Operational excluded</span>
                ${unverified > 0 ? `<span><strong>${formatNumber(unverified)}</strong> Without confirmed Live status excluded</span>` : ""}
            </div>
        </section>`;
}

function worstStoresTable(stores) {
    if (!stores.length) return `<div class="health-empty">No stores match the selected filters.</div>`;
    return `
        <div class="health-compact-table">
            ${stores.map((store, index) => `
                <button class="health-compact-row" type="button" data-health-store-id="${escapeHtml(store.storeId)}">
                    <span class="health-rank">${index + 1}</span>
                    <span><strong>${escapeHtml(store.storeId)}</strong><small>${escapeHtml(store.storeName)}</small></span>
                    ${healthScoreBadge(store.healthScore)}
                </button>`).join("")}
        </div>`;
}

function healthStoreTable(stores, offset = 0) {
    if (!stores.length) return `<div class="health-empty">No store health data is available for this filter.</div>`;
    return `
        <div class="health-table-wrap">
            <table class="health-table">
                <thead><tr><th>#</th><th>Store ID</th><th>Store Name</th><th>Brand</th><th>Health Score</th><th>Healthy</th><th>Warning</th><th>Critical</th><th>Offline</th><th>Last Good Snapshot</th></tr></thead>
                <tbody>${stores.map((store, index) => `
                    <tr class="health-store-row" tabindex="0" data-health-store-id="${escapeHtml(store.storeId)}">
                        <td>${offset + index + 1}</td>
                        <td><strong>${escapeHtml(store.storeId)}</strong></td>
                        <td>${escapeHtml(store.storeName)}</td>
                        <td>${escapeHtml((store.brands || []).join(" + ") || "No Data")}</td>
                        <td>${healthScoreBadge(store.healthScore)}</td>
                        <td class="health-number-healthy">${formatNumber(store.healthy)}</td>
                        <td class="health-number-warning">${formatNumber(store.warning)}</td>
                        <td class="health-number-critical">${formatNumber(store.critical)}</td>
                        <td class="health-number-offline">${formatNumber(store.offline)}</td>
                        <td>${escapeHtml(formatHealthDate(store.lastGoodSnapshot))}</td>
                    </tr>`).join("")}</tbody>
            </table>
        </div>`;
}

function healthPagination(total, pageCount) {
    if (pageCount <= 1) return `<div class="health-pagination"><span>Showing ${formatNumber(total)} stores</span></div>`;
    return `
        <div class="health-pagination">
            <span>Showing ${formatNumber(total)} stores</span>
            <div>
                <button type="button" data-health-page="${state.deviceHealthPage - 1}"${state.deviceHealthPage <= 1 ? " disabled" : ""}>Previous</button>
                <span>Page ${state.deviceHealthPage} of ${pageCount}</span>
                <button type="button" data-health-page="${state.deviceHealthPage + 1}"${state.deviceHealthPage >= pageCount ? " disabled" : ""}>Next</button>
            </div>
        </div>`;
}

function stableAdoptionTable(items) {
    if (!items.length) return `<div class="health-empty">No device data is available.</div>`;
    return `
        <div class="health-adoption-list">
            ${items.map(item => `
                <div class="health-adoption-row">
                    <span><strong>${escapeHtml(item.label)}</strong><small>Stable ${escapeHtml(item.stableVersion || "No Data")}</small></span>
                    <span>${item.stableUsagePercent === null ? "No Data" : `${healthPercent(item.stableUsagePercent)} (${formatNumber(item.stableDevices)}/${formatNumber(item.reportingDevices)})`}</span>
                </div>`).join("")}
        </div>`;
}

function healthMethodologyPanel(methodology, period) {
    if (!methodology) return "";
    return `
        <details class="health-methodology">
            <summary>Calculation Methodology and Data Coverage</summary>
            <div class="health-methodology-grid">
                <div><strong>Device Health Score</strong><p>${escapeHtml(methodology.score)}</p></div>
                <div><strong>Distinct Device Identity</strong><p>${escapeHtml(methodology.identity)}</p></div>
                <div><strong>Healthy</strong><p>${escapeHtml(methodology.healthy)}</p></div>
                <div><strong>Warning</strong><p>${escapeHtml(methodology.warning)}</p></div>
                <div><strong>Critical</strong><p>${escapeHtml(methodology.critical)}</p></div>
                <div><strong>Offline</strong><p>${escapeHtml(methodology.offline)}</p></div>
            </div>
            <p class="health-source-note">Coverage: ${escapeHtml(period.snapshotCount || 0)} snapshots from ${escapeHtml(formatHealthDate(period.start))} through ${escapeHtml(formatHealthDate(period.end))}. Missing or unparseable source fields are displayed as No Data or classified as Critical only where the documented rule requires it.</p>
        </details>`;
}

function bindDeviceHealthDashboard() {
    document.getElementById("refreshDeviceHealthBtn")?.addEventListener("click", loadDeviceHealthDashboard);
    document.getElementById("healthDaysSelect")?.addEventListener("change", event => {
        state.deviceHealthDays = Number(event.target.value) || 30;
        state.deviceHealthPage = 1;
        loadDeviceHealthDashboard();
    });
    document.getElementById("healthBrandSelect")?.addEventListener("change", event => {
        const value = event.target.value;
        state.brandFilter = value.startsWith("brand:")
            ? { mode: "brand", brand: value.slice(6), combination: "", selectedBrands: [], match: "any" }
            : { mode: value, brand: "", combination: "", selectedBrands: [], match: "any" };
        state.deviceHealthPage = 1;
        loadDeviceHealthDashboard();
    });
    document.getElementById("healthCoBrandSelect")?.addEventListener("change", event => {
        state.brandFilter = { ...state.brandFilter, combination: event.target.value };
        state.deviceHealthPage = 1;
        loadDeviceHealthDashboard();
    });
    document.getElementById("healthMultiBrandSelect")?.addEventListener("change", event => {
        state.brandFilter = { ...state.brandFilter, selectedBrands: [...event.target.selectedOptions].map(option => option.value) };
        state.deviceHealthPage = 1;
        loadDeviceHealthDashboard();
    });
    document.getElementById("healthBrandMatchSelect")?.addEventListener("change", event => {
        state.brandFilter = { ...state.brandFilter, match: event.target.value };
        state.deviceHealthPage = 1;
        loadDeviceHealthDashboard();
    });
    let searchTimer = null;
    document.getElementById("healthStoreSearch")?.addEventListener("input", event => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            state.deviceHealthQuery = event.target.value.trim();
            state.deviceHealthPage = 1;
            loadDeviceHealthDashboard();
        }, 350);
    });
    document.querySelectorAll("[data-health-store-id]").forEach(element => {
        const open = () => renderDeviceHealthStore(element.dataset.healthStoreId);
        element.addEventListener("click", open);
        element.addEventListener("keydown", event => {
            if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                open();
            }
        });
    });
    document.querySelectorAll("button[data-health-page]").forEach(button => {
        button.addEventListener("click", () => {
            state.deviceHealthPage = Number(button.dataset.healthPage) || 1;
            const mount = document.getElementById("deviceHealthMount");
            mount.innerHTML = deviceHealthDashboardView(state.deviceHealthDashboard);
            bindDeviceHealthDashboard();
            renderDeviceHealthCharts();
            document.querySelector(".health-store-panel")?.scrollIntoView({ behavior: "smooth", block: "start" });
        });
    });
    document.getElementById("exportDeviceHealthBtn")?.addEventListener("click", exportDeviceHealthCsv);
}

async function renderDeviceHealthStore(storeId, returnPage = state.currentPage === "storeHealth" ? "storeHealth" : "deviceHealth") {
    state.currentPage = returnPage;
    app.innerHTML = shell(header() + `
        <div id="deviceHealthMount">
            <section class="health-loading-state"><span class="health-loading-ring" aria-hidden="true"></span><strong>Building store scorecard...</strong></section>
        </div>`, returnPage);
    bindShell();
    const params = new URLSearchParams({ action: "store", days: String(state.deviceHealthDays), storeId: String(storeId || "") });
    try {
        const response = await fetch(`api/device-health.php?${params.toString()}`);
        const payload = await parseJsonResponse(response);
        if (!payload.ok) throw new Error(payload.error || "Could not load the store scorecard.");
        state.deviceHealthStore = payload.scorecard;
        document.getElementById("deviceHealthMount").innerHTML = deviceHealthStoreView(payload.scorecard, returnPage);
        bindDeviceHealthStore(returnPage);
        drawStoreHealthGauge(document.getElementById("storeHealthGauge"), payload.scorecard.store.healthScore);
        scrollPageToTop();
    } catch (error) {
        document.getElementById("deviceHealthMount").innerHTML = `<section class="empty health-error-state">${escapeHtml(error.message)}</section>`;
    }
}

function deviceHealthStoreView(scorecard, returnPage = "deviceHealth") {
    const store = scorecard.store || {};
    const period = scorecard.period || {};
    return `
        <section class="device-health-page health-scorecard-page">
            <div class="health-page-heading">
                <div>
                    <button class="health-back-btn" id="backToHealthPageBtn" type="button">${returnPage === "storeHealth" ? "Back to Store Health Search" : "Back to Device Health Dashboard"}</button>
                    <h2>Store Device Health Scorecard</h2>
                    <p>${escapeHtml(period.days || 30)}-day device reporting health by store.</p>
                </div>
                <div class="health-updated"><span class="health-live-dot"></span><span>Data through <strong>${escapeHtml(formatHealthDate(period.end))}</strong></span><button class="health-refresh-btn" id="refreshStoreHealthBtn" type="button">Refresh Data</button></div>
            </div>

            <form class="health-store-finder" id="healthStoreFinderForm">
                <label for="healthStoreFinder">Search Store ID, Store Name, or Brand</label>
                <input class="text-input" id="healthStoreFinder" type="search" value="${escapeHtml(`${store.storeId} - ${store.storeName}`)}" autocomplete="off">
                <button class="btn" type="submit">Find Store</button>
            </form>
            <div id="healthStoreSearchResults"></div>

            <section class="health-store-hero">
                <div class="health-gauge-wrap"><canvas id="storeHealthGauge" width="190" height="190" role="img" aria-label="${escapeHtml(healthPercent(store.healthScore))} 30-day health score"></canvas></div>
                <div class="health-store-fact"><span>Store ID</span><strong>${escapeHtml(store.storeId)}</strong></div>
                <div class="health-store-fact health-store-name"><span>Store Name</span><strong>${escapeHtml(store.storeName)}</strong></div>
                <div class="health-store-fact"><span>Status</span>${healthOperationalStatus(store.operationalStatus)}</div>
                <div class="health-store-fact"><span>Brand</span><strong>${escapeHtml((store.brands || []).join(" + ") || "No Data")}</strong></div>
            </section>

            <div class="health-store-metrics">
                ${healthMetricCard("Expected Devices", formatNumber(store.expectedDevices), "Distinct devices in period", "info", "terminals")}
                ${healthMetricCard("Healthy Now", formatNumber(store.healthy), "Last seen within 48 hours", "healthy", "stable")}
                ${healthMetricCard("Warning", formatNumber(store.warning), "Last seen 2 to 7 days ago", "warning", "outdated")}
                ${healthMetricCard("Critical", formatNumber(store.critical), "Stale over 7 days or timestamp issue", "critical", "outdated")}
                ${healthMetricCard("Offline", formatNumber(store.offline), "Expected but absent", "offline", "outdated")}
                ${healthMetricCard("Snapshots Checked", formatNumber(store.snapshotCount), `Last good: ${formatHealthDate(store.lastGoodSnapshot)}`, "info", "qukds")}
            </div>

            <section class="health-panel health-device-breakdown">
                <div class="health-panel-heading"><div><h3>Device Breakdown</h3><p>Current health, version position, and 30-day reporting score.</p></div></div>
                ${deviceBreakdownTable(scorecard.devices || [])}
            </section>

            <section class="health-panel health-timeline-panel">
                <div class="health-panel-heading"><div><h3>Last ${escapeHtml(period.days || 30)} Days</h3><p>Daily summary of every collected snapshot. Hover or focus a day for reconciled counts.</p></div></div>
                <div class="health-timeline-legend"><span class="healthy">Healthy</span><span class="warning">Warning</span><span class="critical">Critical</span><span class="offline">Offline</span></div>
                ${healthTimeline(scorecard.timeline || [])}
            </section>

            ${missingDataPanel(scorecard.missingData || [])}
            ${healthMethodologyPanel(scorecard.methodology, period)}
        </section>`;
}

function deviceBreakdownTable(devices) {
    if (!devices.length) return `<div class="health-empty">No applicable devices were found for this store.</div>`;
    return `
        <div class="health-table-wrap">
            <table class="health-table health-device-table">
                <thead><tr><th>Device</th><th>Product</th><th>Current Status</th><th>Current Version</th><th>Stable Status</th><th>Last Seen</th><th>30-Day Score</th><th>Impact</th></tr></thead>
                <tbody>${devices.map(device => `
                    <tr>
                        <td><strong>${escapeHtml(device.label)}</strong>${device.computerName || device.networkAddress ? `<small>${escapeHtml([device.computerName, device.networkAddress].filter(Boolean).join(" • "))}</small>` : ""}</td>
                        <td>${escapeHtml(device.productLabel)}</td>
                        <td>${healthStatusBadge(device.currentStatus)}</td>
                        <td>${escapeHtml(device.currentVersion || "No Data")}</td>
                        <td>${healthStableBadge(device.stableStatus, device.stableVersion)}</td>
                        <td>${escapeHtml(device.lastSeen || "No Data")}${device.dataIssue ? `<small class="health-data-issue">${escapeHtml(device.dataIssue)}</small>` : ""}</td>
                        <td>${healthScoreBar(device.healthScore)}</td>
                        <td><span class="health-impact health-impact-${escapeHtml(String(device.impact || "").toLowerCase().replaceAll(" ", "-"))}">${escapeHtml(device.impact)}</span></td>
                    </tr>`).join("")}</tbody>
            </table>
        </div>`;
}

function healthTimeline(days) {
    if (!days.length) return `<div class="health-empty">No historical snapshots are available.</div>`;
    return `
        <div class="health-timeline">
            ${days.map(day => {
                const title = `${day.label}: ${healthPercent(day.score)} across ${day.snapshotCount} snapshots; ${day.healthyChecks} healthy, ${day.warningChecks} warning, ${day.criticalChecks} critical, ${day.offlineChecks} offline.`;
                return `<button class="health-day health-day-${escapeHtml(day.status.key)}" type="button" title="${escapeHtml(title)}" aria-label="${escapeHtml(title)}"><span>${escapeHtml(day.label)}</span></button>`;
            }).join("")}
        </div>`;
}

function missingDataPanel(fields) {
    if (!fields.length) return "";
    return `
        <section class="health-missing-data">
            <strong>Data Coverage Notice</strong>
            <p>The following source data is missing or unavailable, so related values are shown as No Data: ${escapeHtml(fields.join(", "))}.</p>
        </section>`;
}

function bindDeviceHealthStore(returnPage = "deviceHealth") {
    document.getElementById("backToHealthPageBtn")?.addEventListener("click", returnPage === "storeHealth" ? renderStoreHealthPage : renderDeviceHealthPage);
    document.getElementById("refreshStoreHealthBtn")?.addEventListener("click", () => renderDeviceHealthStore(state.deviceHealthStore?.store?.storeId, returnPage));
    document.getElementById("healthStoreFinderForm")?.addEventListener("submit", searchDeviceHealthStores);
}

async function searchDeviceHealthStores(event) {
    event.preventDefault();
    const query = document.getElementById("healthStoreFinder")?.value.trim() || "";
    const results = document.getElementById("healthStoreSearchResults");
    if (!query) {
        results.innerHTML = "";
        return;
    }
    results.innerHTML = `<div class="health-search-message">Searching stores...</div>`;
    try {
        const params = new URLSearchParams({ action: "search", days: String(state.deviceHealthDays), query });
        const response = await fetch(`api/device-health.php?${params.toString()}`);
        const payload = await parseJsonResponse(response);
        if (!payload.ok) throw new Error(payload.error || "Store search failed.");
        const stores = payload.stores || [];
        if (stores.length === 1) {
            await renderDeviceHealthStore(stores[0].storeId);
            return;
        }
        results.innerHTML = stores.length ? `
            <div class="health-search-results">
                ${stores.map(store => `<button type="button" data-health-search-store="${escapeHtml(store.storeId)}"><strong>${escapeHtml(store.storeId)} - ${escapeHtml(store.storeName)}</strong><span>${escapeHtml((store.brands || []).join(" + ") || "No Brand Data")}</span></button>`).join("")}
            </div>` : `<div class="health-search-message">No matching stores were found.</div>`;
        results.querySelectorAll("[data-health-search-store]").forEach(button => button.addEventListener("click", () => renderDeviceHealthStore(button.dataset.healthSearchStore)));
    } catch (error) {
        results.innerHTML = `<div class="health-search-message health-search-error">${escapeHtml(error.message)}</div>`;
    }
}

function renderDeviceHealthCharts() {
    const dashboard = state.deviceHealthDashboard;
    if (!dashboard) return;
    const draw = () => {
        drawFleetHealthTrend(document.getElementById("fleetHealthTrendCanvas"), dashboard.trend || []);
        drawDeviceIssues(document.getElementById("deviceIssuesCanvas"), dashboard.issuesByType || []);
    };
    draw();
    state.deviceHealthChartObserver?.disconnect();
    if (window.ResizeObserver) {
        let timer = null;
        state.deviceHealthChartObserver = new ResizeObserver(() => {
            clearTimeout(timer);
            timer = setTimeout(draw, 120);
        });
        document.querySelectorAll(".health-trend-panel, .health-issues-panel").forEach(panel => state.deviceHealthChartObserver.observe(panel));
    }
}

function prepareHealthCanvas(canvas, height) {
    if (!canvas) return null;
    const width = Math.max(320, Math.floor(canvas.getBoundingClientRect().width || canvas.parentElement?.clientWidth || 600));
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    canvas.style.height = `${height}px`;
    canvas.width = Math.floor(width * dpr);
    canvas.height = Math.floor(height * dpr);
    const context = canvas.getContext("2d");
    context.setTransform(dpr, 0, 0, dpr, 0, 0);
    context.clearRect(0, 0, width, height);
    return { context, width, height };
}

function drawFleetHealthTrend(canvas, points) {
    const prepared = prepareHealthCanvas(canvas, 245);
    if (!prepared) return;
    const { context: ctx, width, height } = prepared;
    const values = points.map(point => point.score).filter(value => value !== null && value !== undefined && Number.isFinite(Number(value)));
    if (!values.length) {
        drawCanvasEmpty(ctx, width, height, "No trend data available");
        return;
    }
    const margin = { left: 44, right: 18, top: 18, bottom: 36 };
    const plotWidth = width - margin.left - margin.right;
    const plotHeight = height - margin.top - margin.bottom;
    ctx.font = "12px Segoe UI, sans-serif";
    ctx.fillStyle = "#8fa9c5";
    ctx.strokeStyle = "rgba(80, 120, 160, .34)";
    ctx.lineWidth = 1;
    [0, 25, 50, 75, 100].forEach(value => {
        const y = margin.top + plotHeight - (value / 100) * plotHeight;
        ctx.beginPath();
        ctx.moveTo(margin.left, y);
        ctx.lineTo(width - margin.right, y);
        ctx.stroke();
        ctx.fillText(`${value}%`, 4, y + 4);
    });
    ctx.strokeStyle = "#47d66f";
    ctx.lineWidth = 3;
    ctx.beginPath();
    let started = false;
    points.forEach((point, index) => {
        if (point.score === null || point.score === undefined || !Number.isFinite(Number(point.score))) return;
        const x = margin.left + (points.length <= 1 ? plotWidth / 2 : (index / (points.length - 1)) * plotWidth);
        const y = margin.top + plotHeight - (Number(point.score) / 100) * plotHeight;
        if (!started) {
            ctx.moveTo(x, y);
            started = true;
        } else {
            ctx.lineTo(x, y);
        }
    });
    ctx.stroke();
    const labelIndexes = uniqueText(["0", String(Math.floor((points.length - 1) / 2)), String(points.length - 1)]).map(Number);
    ctx.fillStyle = "#a9bfd6";
    labelIndexes.forEach(index => {
        const point = points[index];
        if (!point) return;
        const x = margin.left + (points.length <= 1 ? plotWidth / 2 : (index / (points.length - 1)) * plotWidth);
        ctx.textAlign = index === 0 ? "left" : index === points.length - 1 ? "right" : "center";
        ctx.fillText(String(point.label || ""), x, height - 10);
    });
    ctx.textAlign = "left";
}

function drawDeviceIssues(canvas, items) {
    const rowHeight = 32;
    const prepared = prepareHealthCanvas(canvas, Math.max(190, items.length * rowHeight + 50));
    if (!prepared) return;
    const { context: ctx, width, height } = prepared;
    if (!items.length) {
        drawCanvasEmpty(ctx, width, height, "No device issue data available");
        return;
    }
    const left = Math.min(118, width * .28);
    const right = 40;
    const maxTotal = Math.max(...items.map(item => Number(item.total || 0)), 1);
    const colors = { healthy: "#47d66f", warning: "#ffc20d", critical: "#ff8a34", offline: "#ff5252" };
    ctx.font = "12px Segoe UI, sans-serif";
    items.forEach((item, index) => {
        const y = 14 + index * rowHeight;
        ctx.fillStyle = "#d9ecff";
        ctx.textAlign = "right";
        ctx.fillText(item.label, left - 10, y + 15);
        let x = left;
        ["critical", "warning", "offline", "healthy"].forEach(key => {
            const count = Number(item[key] || 0);
            if (!count) return;
            const segmentWidth = ((width - left - right) * count) / maxTotal;
            ctx.fillStyle = colors[key];
            ctx.fillRect(x, y, segmentWidth, 20);
            if (segmentWidth > 24) {
                ctx.fillStyle = key === "warning" ? "#07101f" : "#ffffff";
                ctx.textAlign = "center";
                ctx.fillText(String(count), x + segmentWidth / 2, y + 14);
            }
            x += segmentWidth;
        });
        ctx.fillStyle = "#a9bfd6";
        ctx.textAlign = "left";
        ctx.fillText(formatNumber(item.total), Math.min(x + 7, width - 32), y + 15);
    });
    const legendY = height - 18;
    let legendX = left;
    Object.entries(colors).forEach(([key, color]) => {
        ctx.fillStyle = color;
        ctx.fillRect(legendX, legendY - 10, 10, 10);
        ctx.fillStyle = "#a9bfd6";
        ctx.textAlign = "left";
        const label = key[0].toUpperCase() + key.slice(1);
        ctx.fillText(label, legendX + 15, legendY);
        legendX += ctx.measureText(label).width + 42;
    });
}

function drawStoreHealthGauge(canvas, score) {
    if (!canvas) return;
    const size = 190;
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    canvas.width = size * dpr;
    canvas.height = size * dpr;
    canvas.style.width = `${size}px`;
    canvas.style.height = `${size}px`;
    const ctx = canvas.getContext("2d");
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, size, size);
    const value = score !== null && score !== undefined && Number.isFinite(Number(score)) ? Math.max(0, Math.min(100, Number(score))) : null;
    ctx.lineWidth = 13;
    ctx.strokeStyle = "#263b55";
    ctx.beginPath();
    ctx.arc(95, 95, 70, -Math.PI / 2, Math.PI * 1.5);
    ctx.stroke();
    if (value !== null) {
        ctx.strokeStyle = healthScoreColor(value);
        ctx.lineCap = "round";
        ctx.beginPath();
        ctx.arc(95, 95, 70, -Math.PI / 2, -Math.PI / 2 + Math.PI * 2 * (value / 100));
        ctx.stroke();
    }
    ctx.fillStyle = "#f4f8ff";
    ctx.font = "800 34px Segoe UI, sans-serif";
    ctx.textAlign = "center";
    ctx.fillText(value === null ? "N/A" : `${value.toFixed(1)}%`, 95, 93);
    ctx.fillStyle = "#9bb0c8";
    ctx.font = "12px Segoe UI, sans-serif";
    ctx.fillText("30-Day Health Score", 95, 118);
}

function drawCanvasEmpty(ctx, width, height, message) {
    ctx.fillStyle = "#8fa9c5";
    ctx.font = "14px Segoe UI, sans-serif";
    ctx.textAlign = "center";
    ctx.fillText(message, width / 2, height / 2);
    ctx.textAlign = "left";
}

function exportDeviceHealthCsv() {
    const dashboard = state.deviceHealthDashboard;
    if (!dashboard?.stores?.length) {
        alert("There are no stores in the current Device Health view to export.");
        return;
    }
    const headers = ["Store ID", "Store Name", "Brands", "Operational Status", "Health Score", "Total Devices", "Healthy", "Warning", "Critical", "Offline", "Last Good Snapshot"];
    const rows = dashboard.stores.map(store => [
        store.storeId,
        store.storeName,
        (store.brands || []).join(" + "),
        store.operationalStatus || "No Data",
        store.healthScore === null ? "No Data" : store.healthScore,
        store.totalDevices,
        store.healthy,
        store.warning,
        store.critical,
        store.offline,
        store.lastGoodSnapshot || "No Data",
    ]);
    const content = [headers, ...rows].map(row => row.map(csvCell).join(",")).join("\r\n");
    downloadFile(`device-health-${state.deviceHealthDays}-days-${new Date().toISOString().slice(0, 10)}.csv`, content, "text/csv;charset=utf-8");
}

function healthScoreBadge(score) {
    if (score === null || score === undefined || !Number.isFinite(Number(score))) return `<span class="health-score-badge no-data">No Data</span>`;
    const value = Number(score);
    return `<span class="health-score-badge ${healthScoreTone(value)}">${value.toFixed(1)}%</span>`;
}

function healthScoreBar(score) {
    if (score === null || score === undefined || !Number.isFinite(Number(score))) return `<span class="health-score-bar-label">No Data</span>`;
    const value = Math.max(0, Math.min(100, Number(score)));
    return `<div class="health-score-cell"><span>${value.toFixed(1)}%</span><span class="health-score-track"><span style="width:${value}%;background:${healthScoreColor(value)}"></span></span></div>`;
}

function healthScoreTone(score) {
    if (score >= 95) return "healthy";
    if (score >= 90) return "warning";
    return "critical";
}

function healthScoreColor(score) {
    if (score >= 95) return "#47d66f";
    if (score >= 90) return "#ffc20d";
    return "#ff5252";
}

function healthStatusBadge(status) {
    const key = status?.key || "offline";
    return `<span class="health-status-badge ${escapeHtml(key)}">${escapeHtml(status?.label || "Offline")}</span>`;
}

function healthStableBadge(status, stableVersion) {
    const key = status?.key || "unavailable";
    const version = stableVersion ? ` • ${stableVersion}` : "";
    return `<span class="health-stable-badge ${escapeHtml(key)}">${escapeHtml((status?.label || "Not Available") + version)}</span>`;
}

function healthOperationalStatus(status) {
    const value = String(status || "").trim();
    if (!value) return `<strong class="health-operation unknown">No Data</strong>`;
    const tone = value.toLowerCase().includes("not operational") ? "offline" : value.toLowerCase().includes("live") ? "live" : "unknown";
    return `<strong class="health-operation ${tone}">${escapeHtml(value)}</strong>`;
}

function healthStatusSummary(summary) {
    return `${formatNumber(summary.warning)} warning • ${formatNumber(summary.critical)} critical • ${formatNumber(summary.offline)} offline`;
}

function healthPercent(value) {
    return value !== null && value !== undefined && Number.isFinite(Number(value)) ? `${Number(value).toFixed(1)}%` : "No Data";
}

function formatNumber(value) {
    return value !== null && value !== undefined && Number.isFinite(Number(value)) ? Number(value).toLocaleString() : "No Data";
}

function formatHealthDate(value) {
    if (!value) return "No Data";
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString([], { month: "short", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit" });
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
    scrollPageToTop();
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
    if (page === "deviceHealth") {
        renderDeviceHealthPage();
        return;
    }
    if (page === "storeHealth") {
        renderStoreHealthPage();
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

function reportView(report, options = {}) {
    const includeBrandFilter = options.includeBrandFilter ?? true;
    const comparisonTab = report.comparison ? `<button class="tab-btn${state.activeTab === "comparison" ? " active" : ""}" data-tab="comparison">Comparison</button>` : "";
    return `
        <section class="report">
            ${includeBrandFilter ? brandFilterBar() : ""}
            ${summaryCards(report)}
            <div class="tabs">
                <button class="tab-btn${state.activeTab === "current" ? " active" : ""}" data-tab="current">Current Versions</button>
                <button class="tab-btn${state.activeTab === "stores" ? " active" : ""}" data-tab="stores">Stores Version Report</button>
                <button class="tab-btn${state.activeTab === "alerts" ? " active" : ""}" data-tab="alerts">Alerts</button>
                <button class="tab-btn${state.activeTab === "qubox-down" ? " active" : ""}" data-tab="qubox-down">QuBox Down</button>
                ${comparisonTab}
            </div>
            <div class="toolbar">
                <div class="report-tools">
                    <input id="reportSearch" class="search" type="search" placeholder="Search brands, stores, versions, terminals, and alerts">
                    <div class="export-actions" aria-label="Export current filtered view">
                        <button class="btn export-btn" id="exportCsvBtn" type="button">Export CSV</button>
                        <button class="btn export-btn" id="exportExcelBtn" type="button">Export Excel</button>
                    </div>
                </div>
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
    if (tab === "qubox-down") return quboxDownTab(report);
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
                ${simpleTable(["Store ID", "Store Name", "Brands", "Status", "Terminals", "Types", "Latest Seen"], (item.storeRows || []).map(store => [
                    store.storeId,
                    store.storeName,
                    storeBrands(store).join(", "),
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
    return simpleTable(["Store ID", "Store Name", "Brands", "Versions Detected", "Most Common Version", "Out-Of-Date Terminals", "Total POS Terminals", "Latest Seen"], report.stores.map(store => [
        store.storeId,
        store.storeName,
        storeBrands(store).join(", "),
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

function quboxDownTab(report) {
    const rows = report.alerts?.quboxDownStores || [];
    return `
        <h2>QuBox Down</h2>
        <p class="subtle">Stores appear here when no QuBox is present in the current terminal export, or when the QuBox last seen online is older than 2 days.</p>
        ${simpleTable(["Store ID", "Store Name", "Brands", "Status", "Issue", "QuBox Version", "Computer Name", "Last Seen", "Age Days"], rows.map(item => [
            item.storeId,
            item.storeName,
            storeBrands(item).join(", "),
            storeStatusBadge(item.storeStatus),
            item.issue,
            item.quboxVersion ? badge(item.quboxVersion, report) : "",
            item.computerName,
            item.lastSeen,
            item.ageDays
        ]))}`;
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
    document.getElementById("exportCsvBtn")?.addEventListener("click", () => exportCurrentView("csv"));
    document.getElementById("exportExcelBtn")?.addEventListener("click", () => exportCurrentView("excel"));
    bindBrandFilters();
    bindSortableHeaders();
}

function brandFilterBar(variant = "report") {
    const base = state.baseReport || state.report;
    const brands = availableBrands(base);
    const combinations = availableBrandCombinations(base);
    const selectedBrands = state.brandFilter.selectedBrands || [];
    const primaryValue = state.brandFilter.mode === "brand" ? `brand:${state.brandFilter.brand}` : state.brandFilter.mode;
    return `
        <section class="brand-filter-panel brand-filter-${escapeHtml(variant)}">
            <div class="brand-filter-heading">
                <span class="label">Dashboard Brand Filter</span>
                <strong>${escapeHtml(activeBrandFilterLabel())}</strong>
            </div>
            <div class="brand-filter-controls">
                <select class="text-input brand-select" id="brandFilterSelect">
                    <option value="all"${primaryValue === "all" ? " selected" : ""}>All Brands</option>
                    ${brands.map(brand => `<option value="brand:${escapeHtml(brand)}"${primaryValue === `brand:${brand}` ? " selected" : ""}>${escapeHtml(brand)}</option>`).join("")}
                    <option value="cobranded"${primaryValue === "cobranded" ? " selected" : ""}>Co-Branded</option>
                    <option value="combination"${primaryValue === "combination" ? " selected" : ""}>Select Combination</option>
                </select>
                ${state.brandFilter.mode === "cobranded" ? `
                    <select class="text-input brand-select" id="coBrandCombinationSelect">
                        <option value="">All Co-Branded Combinations</option>
                        ${combinations.map(combo => `<option value="${escapeHtml(combo)}"${state.brandFilter.combination === combo ? " selected" : ""}>${escapeHtml(combo)}</option>`).join("")}
                    </select>` : ""}
                ${state.brandFilter.mode === "combination" ? `
                    <select class="text-input brand-select multi-brand-select" id="multiBrandSelect" multiple size="${Math.min(Math.max(brands.length, 4), 7)}">
                        ${brands.map(brand => `<option value="${escapeHtml(brand)}"${selectedBrands.includes(brand) ? " selected" : ""}>${escapeHtml(brand)}</option>`).join("")}
                    </select>
                    <select class="text-input brand-select" id="brandMatchSelect">
                        <option value="any"${state.brandFilter.match === "any" ? " selected" : ""}>Contains Any</option>
                        <option value="all"${state.brandFilter.match === "all" ? " selected" : ""}>Contains All</option>
                        <option value="exact"${state.brandFilter.match === "exact" ? " selected" : ""}>Exact Combination</option>
                    </select>` : ""}
            </div>
        </section>`;
}

function bindBrandFilters() {
    document.getElementById("brandFilterSelect")?.addEventListener("change", event => {
        const value = event.target.value;
        if (value.startsWith("brand:")) {
            state.brandFilter = { mode: "brand", brand: value.slice(6), combination: "", selectedBrands: [], match: "any" };
        } else {
            state.brandFilter = { mode: value, brand: "", combination: "", selectedBrands: [], match: "any" };
        }
        renderFilteredReport();
    });
    document.getElementById("coBrandCombinationSelect")?.addEventListener("change", event => {
        state.brandFilter = { ...state.brandFilter, combination: event.target.value };
        renderFilteredReport();
    });
    document.getElementById("multiBrandSelect")?.addEventListener("change", event => {
        const selectedBrands = [...event.target.selectedOptions].map(option => option.value);
        state.brandFilter = { ...state.brandFilter, selectedBrands };
        renderFilteredReport();
    });
    document.getElementById("brandMatchSelect")?.addEventListener("change", event => {
        state.brandFilter = { ...state.brandFilter, match: event.target.value };
        renderFilteredReport();
    });
}

function renderFilteredReport() {
    const mount = document.getElementById("reportMount");
    if (!mount || !state.baseReport) return;
    state.report = applyBrandFilter(state.baseReport);
    const isDashboard = state.currentPage === "dashboard";
    if (isDashboard) refreshDashboardControls();
    mount.innerHTML = reportView(state.report, { includeBrandFilter: !isDashboard });
    refreshHeaderLastUpdated();
    bindReport();
}

function applyBrandFilter(report) {
    if (!report) return report;
    if (state.brandFilter.mode === "all") return { ...report, brandFilter: filterSummary() };
    const filtered = {
        ...report,
        downloadableVersions: filterVersionGroups(report.downloadableVersions || []),
        kioskVersions: filterVersionGroups(report.kioskVersions || []),
        quboxVersions: filterVersionGroups(report.quboxVersions || []),
        qukdsVersions: filterVersionGroups(report.qukdsVersions || []),
        quorbVersions: filterVersionGroups(report.quorbVersions || []),
        otherVersions: filterVersionGroups(report.otherVersions || []),
        stores: (report.stores || []).filter(storeMatchesBrandFilter),
        brandFilter: filterSummary(),
    };
    filtered.summary = filteredSummary(filtered);
    filtered.outOfDateVersionSummary = outOfDateVersionSummary(filtered.downloadableVersions, filtered.summary.currentStableVersion);
    filtered.alerts = {
        mixedVersionStores: filtered.stores.filter(store => Number(store.uniqueVersionCount) > 1),
        staleTerminals: (report.alerts?.staleTerminals || []).filter(storeMatchesBrandFilter),
        quboxDownStores: (report.alerts?.quboxDownStores || []).filter(storeMatchesBrandFilter),
        farBehindStores: filtered.stores.filter(store => Number(store.outOfDateTerminalCount) > 0).sort((a, b) => Number(b.outOfDateTerminalCount) - Number(a.outOfDateTerminalCount)),
    };
    if (report.comparison) {
        filtered.comparison = {
            ...report.comparison,
            changedTerminals: (report.comparison.changedTerminals || []).filter(storeMatchesBrandFilter),
        };
        filtered.comparison.changedTerminalCount = filtered.comparison.changedTerminals.length;
    }
    return filtered;
}

function filterVersionGroups(groups) {
    return groups.map(group => {
        const storeRows = (group.storeRows || []).filter(storeMatchesBrandFilter);
        return {
            ...group,
            storeRows,
            terminalCount: storeRows.reduce((sum, store) => sum + Number(store.terminalCount || 0), 0),
            storeCount: storeRows.length,
            terminalTypes: uniqueText(storeRows.flatMap(store => String(store.terminalTypes || "").split(/,\s*/))).join(", "),
        };
    }).filter(group => group.terminalCount > 0);
}

function filteredSummary(report) {
    const posStable = stableVersionGroup(report.downloadableVersions);
    const kioskStable = stableVersionGroup(report.kioskVersions);
    const quboxStable = stableVersionGroup(report.quboxVersions);
    const qukdsStable = stableVersionGroup(report.qukdsVersions);
    const quorbStable = stableVersionGroup(report.quorbVersions);
    const posTotal = totalDevices(report.downloadableVersions);
    return {
        ...report.summary,
        uniqueQuPosAppVersions: report.downloadableVersions.length,
        posAppTerminals: posTotal,
        mostCurrentVersion: report.downloadableVersions[0]?.version || "N/A",
        currentStableVersion: posStable?.version || "N/A",
        currentStableVersionCount: posStable?.terminalCount || 0,
        currentStableVersionUsagePercent: percent(posStable?.terminalCount || 0, posTotal),
        outOfDateStores: report.stores.filter(store => Number(store.outOfDateTerminalCount) > 0).length,
        outOfDatePosTerminals: report.stores.reduce((sum, store) => sum + Number(store.outOfDateTerminalCount || 0), 0),
        kioskVersions: report.kioskVersions.length,
        quboxVersions: report.quboxVersions.length,
        qukdsVersions: report.qukdsVersions.length,
        quorbVersions: report.quorbVersions.length,
        kioskStableVersion: kioskStable?.version || "No Data",
        kioskStableUsagePercent: stableUsage(kioskStable, report.kioskVersions),
        quboxStableVersion: quboxStable?.version || "No Data",
        quboxStableUsagePercent: stableUsage(quboxStable, report.quboxVersions),
        qukdsStableVersion: qukdsStable?.version || "No Data",
        qukdsStableUsagePercent: stableUsage(qukdsStable, report.qukdsVersions),
        quorbStableVersion: quorbStable?.version || "No Data",
        quorbStableUsagePercent: stableUsage(quorbStable, report.quorbVersions),
    };
}

function stableVersionGroup(groups) {
    return [...(groups || [])].sort((a, b) => Number(b.terminalCount || 0) - Number(a.terminalCount || 0) || compareVersions(b.version, a.version))[0] || null;
}

function stableUsage(stable, groups) {
    const total = totalDevices(groups);
    return total > 0 && stable ? percent(stable.terminalCount, total) : null;
}

function totalDevices(groups) {
    return (groups || []).reduce((sum, group) => sum + Number(group.terminalCount || 0), 0);
}

function percent(part, whole) {
    return whole > 0 ? Math.round((Number(part || 0) / whole) * 1000) / 10 : 0;
}

function outOfDateVersionSummary(groups, stableVersion) {
    if (!stableVersion || stableVersion === "N/A") return [];
    return (groups || [])
        .filter(group => compareVersions(group.version, stableVersion) < 0)
        .map(group => ({ version: group.version, terminalCount: group.terminalCount, storeCount: group.storeCount }))
        .sort((a, b) => compareVersions(b.version, a.version));
}

function storeMatchesBrandFilter(store) {
    const filter = state.brandFilter;
    const brands = storeBrands(store);
    if (filter.mode === "all") return true;
    if (filter.mode === "brand") return brands.includes(filter.brand);
    if (filter.mode === "cobranded") {
        if (brands.length < 2) return false;
        return !filter.combination || brandCombinationLabel(brands) === filter.combination;
    }
    if (filter.mode === "combination") {
        const selected = filter.selectedBrands || [];
        if (!selected.length) return true;
        if (filter.match === "all") return selected.every(brand => brands.includes(brand));
        if (filter.match === "exact") return selected.length === brands.length && selected.every(brand => brands.includes(brand));
        return selected.some(brand => brands.includes(brand));
    }
    return true;
}

function availableBrands(report) {
    const brands = [];
    allReportStores(report).forEach(store => brands.push(...storeBrands(store)));
    return uniqueText(brands).sort((a, b) => a.localeCompare(b));
}

function availableBrandCombinations(report) {
    const combinations = allReportStores(report)
        .map(store => storeBrands(store))
        .filter(brands => brands.length > 1)
        .map(brandCombinationLabel);
    return uniqueText(combinations).sort((a, b) => a.localeCompare(b));
}

function allReportStores(report) {
    const stores = [...(report?.stores || [])];
    ["downloadableVersions", "kioskVersions", "quboxVersions", "qukdsVersions", "quorbVersions", "otherVersions"].forEach(key => {
        (report?.[key] || []).forEach(group => stores.push(...(group.storeRows || [])));
    });
    return stores;
}

function storeBrands(store) {
    const explicit = Array.isArray(store?.storeBrands) ? store.storeBrands : [];
    return uniqueText([...explicit, ...brandsFromText(store?.storeName || "")]).sort((a, b) => a.localeCompare(b));
}

function brandsFromText(text) {
    const value = String(text || "").toLowerCase();
    const patterns = {
        "Auntie Anne's": ["auntie anne", "[aa]", "aa-"],
        Carvel: ["carvel", "[cv]", "cv-", " cb-", "/ cb-"],
        Cinnabon: ["cinnabon", "[cn]", "cn-"],
        Jamba: ["jamba", "[ja]", "ja-"],
        "Moe's": ["moes", "moe's", "[moes]", "moes-"],
        "Schlotzsky's": ["schlotzsky", "sch-", "[sch]"],
        "McAlister's Deli": ["mcalister", "mcalister's", "mcalisters", "[mca]"],
    };
    return Object.entries(patterns)
        .filter(([, needles]) => needles.some(needle => value.includes(needle)))
        .map(([brand]) => brand);
}

function brandCombinationLabel(brands) {
    return uniqueText(brands).sort((a, b) => a.localeCompare(b)).join(" + ");
}

function activeBrandFilterLabel() {
    const filter = state.brandFilter;
    if (filter.mode === "all") return "All Brands";
    if (filter.mode === "brand") return filter.brand || "Brand";
    if (filter.mode === "cobranded") return filter.combination || "Co-Branded";
    if (filter.mode === "combination") {
        const brands = filter.selectedBrands?.length ? filter.selectedBrands.join(" + ") : "No brands selected";
        const mode = filter.match === "all" ? "Contains All" : filter.match === "exact" ? "Exact Combination" : "Contains Any";
        return `${mode}: ${brands}`;
    }
    return "All Brands";
}

function filterSummary() {
    return {
        mode: state.brandFilter.mode,
        label: activeBrandFilterLabel(),
        match: state.brandFilter.match,
        selectedBrands: state.brandFilter.selectedBrands || [],
    };
}

function uniqueText(values) {
    return [...new Set((values || []).map(value => String(value || "").trim()).filter(Boolean))];
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

function exportCurrentView(format) {
    const exportData = currentVisibleTableData();
    if (!exportData.sections.some(section => section.rows.length > 0)) {
        alert("There are no visible rows to export.");
        return;
    }
    const baseName = exportFileName(format);
    if (format === "excel") {
        downloadFile(`${baseName}.xls`, excelHtml(exportData), "application/vnd.ms-excel;charset=utf-8");
        return;
    }
    downloadFile(`${baseName}.csv`, csvText(exportData), "text/csv;charset=utf-8");
}

function currentVisibleTableData() {
    const sections = [];
    document.querySelectorAll("#tabContent .table-wrap").forEach(wrap => {
        const table = wrap.querySelector("table");
        const tableHeaders = [...table?.querySelectorAll("thead th") || []].map(cell => cell.textContent.trim());
        if (!tableHeaders.length) return;
        const section = exportSectionLabel(wrap);
        const rows = [];
        table?.querySelectorAll("tbody tr").forEach(row => {
            if (row.style.display === "none") return;
            const cells = [...row.children].map(cell => cell.textContent.replace(/\s+/g, " ").trim());
            rows.push(cells);
        });
        if (rows.length) sections.push({ section, headers: tableHeaders, rows });
    });
    return { sections };
}

function exportSectionLabel(element) {
    let current = element.previousElementSibling;
    while (current) {
        if (/^H[1-3]$/i.test(current.tagName)) return current.textContent.trim();
        current = current.previousElementSibling;
    }
    const section = element.closest(".report-section");
    return section?.querySelector("h2")?.textContent.trim() || activeTabLabel();
}

function activeTabLabel() {
    return document.querySelector(`.tab-btn[data-tab="${state.activeTab}"]`)?.textContent.trim() || "Report";
}

function exportFileName(format) {
    const generated = String(state.report?.generatedOn || new Date().toISOString()).replace(/[^a-z0-9]+/gi, "-").replace(/^-|-$/g, "");
    const tab = activeTabLabel().replace(/[^a-z0-9]+/gi, "-").replace(/^-|-$/g, "").toLowerCase() || "report";
    return `qu-pos-${tab}-filtered-${generated}-${format}`;
}

function csvText({ sections }) {
    const lines = [[`Brand Filter: ${activeBrandFilterLabel()}`].map(csvCell).join(",")];
    sections.forEach((section, index) => {
        lines.push("");
        lines.push([`Section: ${section.section}`].map(csvCell).join(","));
        lines.push(section.headers.map(csvCell).join(","));
        section.rows.forEach(row => lines.push(row.map(csvCell).join(",")));
    });
    return lines.join("\r\n");
}

function csvCell(value) {
    const text = String(value ?? "");
    return /[",\r\n]/.test(text) ? `"${text.replaceAll('"', '""')}"` : text;
}

function excelHtml({ sections }) {
    const tables = sections.map(section => {
        const headerRow = `<tr>${section.headers.map(cell => `<th>${escapeHtml(cell)}</th>`).join("")}</tr>`;
        const bodyRows = section.rows.map(row => `<tr>${row.map(cell => `<td>${escapeHtml(cell)}</td>`).join("")}</tr>`).join("");
        return `<h2>${escapeHtml(section.section)}</h2><table>${headerRow}${bodyRows}</table>`;
    }).join("<br>");
    return `<!doctype html><html><head><meta charset="utf-8"></head><body><h1>Brand Filter: ${escapeHtml(activeBrandFilterLabel())}</h1>${tables}</body></html>`;
}

function downloadFile(fileName, content, mimeType) {
    const blob = new Blob([content], { type: mimeType });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(link.href);
}

boot();
