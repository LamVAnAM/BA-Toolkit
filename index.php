<?php
require_once 'config/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="<?= ($lang === 'en') ? 'en' : 'vi' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enterprise BA Toolkit - <?= ($lang === 'en' ? 'EN' : 'VI') ?></title>
    <meta name="author" content="vannamdigital">
    <meta name="contact" content="namxp2@gmail.com">
    <base href="<?= htmlspecialchars(appUrl()) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/mermaid/dist/mermaid.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script>
        window.I18N_DATA = <?= json_encode($translations) ?>;
        window.CURRENT_LANG = '<?= $lang ?>';
        window.GOOGLE_CLIENT_ID = <?= json_encode((string)(getenv('GOOGLE_CLIENT_ID') ?: ($_ENV['GOOGLE_CLIENT_ID'] ?? ''))) ?>;
        window.CSRF_TOKEN = <?= json_encode(getCsrfToken()) ?>;
        window.APP_BASE_PATH = <?= json_encode(appBasePath()) ?>;

        if (window.mermaid) {
            mermaid.initialize({
                startOnLoad: false,
                theme: 'base',
                themeVariables: {
                    primaryColor: '#e3f2fd',
                    primaryTextColor: '#0d47a1',
                    primaryBorderColor: '#0d47a1',
                    lineColor: '#2196f3',
                    secondaryColor: '#f1f8e9',
                    tertiaryColor: '#fff',
                    edgeLabelBackground: '#ffffff'
                },
                flowchart: {
                    useMaxWidth: true,
                    htmlLabels: true,
                    curve: 'linear',
                    padding: 20,
                    nodeSpacing: 70,
                    rankSpacing: 80
                }
            });
        }

        function i18n(key) {
            if (!window.I18N_DATA[key]) return key;
            const data = window.I18N_DATA[key];
            if (window.CURRENT_LANG === 'vi') return data.vi;
            if (window.CURRENT_LANG === 'en') return data.en;
            return data.vi + ' / ' + data.en;
        }
    </script>
</head>
<body class="<?= !isAuthenticated() ? 'auth-mode' : '' ?>">

<?php if (!isAuthenticated()): ?>
    <?php require_once 'modules/login.view.php'; ?>
<?php else: ?>
<div class="app-shell">
<?php require_once 'modules/sidebar.php'; ?>

<main class="main-content">
    <header class="topbar">
        <div class="topbar-heading">
            <button class="sidebar-toggle" type="button" onclick="toggleSidebar()" aria-label="Toggle navigation">
                <i class="fas fa-bars"></i>
            </button>
            <div class="topbar-title-group">
                <span class="eyebrow">Enterprise BA Toolkit</span>
                <h2 id="view-title">Dashboard</h2>
            </div>
        </div>
        <div class="topbar-actions">
            <div class="topbar-status">
                <span class="status-dot"></span>
                <span><?= htmlspecialchars($_SESSION['role'] ?? 'user') ?></span>
            </div>
            <div id="header-actions"></div>
        </div>
    </header>

    <div id="content-area" class="container">
        <div id="loading" style="text-align:center; padding:50px;">Loading...</div>
    </div>
    <footer class="app-footer" id="app-footer">
        <div class="app-footer-inner">
            <span class="app-footer-text" id="app-footer-text">Powered by <strong>vannamdigital</strong></span>
            <a class="app-footer-link" id="app-footer-link" href="mailto:namxp2@gmail.com">namxp2@gmail.com</a>
        </div>
    </footer>
</main>
</div>
<div class="app-backdrop" onclick="closeSidebar()"></div>
<?php endif; ?>

<div id="reportModal" class="modal-overlay">
    <div class="modal">
        <div style="background:var(--primary); color:white; padding:15px 20px; display:flex; justify-content:space-between; align-items:center;">
            <h3>PROJECT REQUIREMENT REPORT</h3>
            <button onclick="closeModal()" style="background:none; border:none; color:white; font-size:1.5rem; cursor:pointer;">x</button>
        </div>
        <div class="modal-body report-content" id="reportContent"></div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal()">Close</button>
            <button class="btn btn-primary" onclick="copyReport()">Copy</button>
            <button class="btn btn-success" onclick="exportPDF()">Export PDF</button>
            <button class="btn btn-outline" onclick="exportDOCX()">DOCX</button>
        </div>
    </div>
</div>

<div id="mainModal" class="modal-overlay">
    <div class="modal" style="max-width: 600px;">
        <div style="background:var(--primary); color:white; padding:15px 20px; display:flex; justify-content:space-between; align-items:center;">
            <h2>Modal Title</h2>
            <button onclick="closeModal()" style="background:none; border:none; color:white; font-size:1.5rem; cursor:pointer;">x</button>
        </div>
        <div class="modal-body"></div>
    </div>
</div>

<div id="toast" class="toast">Message</div>

<script src="assets/js/app.js"></script>
<script>
async function handleLogout() {
    try {
        const response = await fetch('api/auth.php?action=logout', { method: 'POST' });
        const result = await response.json();
        if (result.success) {
            location.href = 'index.php';
        }
    } catch (e) {
        showToast('Logout failed.');
    }
}
</script>

</body>
</html>
