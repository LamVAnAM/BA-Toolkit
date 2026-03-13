<?php
require_once __DIR__ . '/../config/bootstrap.php';
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <div class="brand-logo">
                <i class="fas fa-layer-group"></i>
            </div>
            <div class="brand-text">
                <span class="brand-name">BA Toolkit</span>
                <span class="brand-tagline">Enterprise Workspace</span>
            </div>
        </div>
        
        <div class="sidebar-lang">
            <label style="font-size: 0.75rem; color: var(--text-disabled); font-weight: 700; display: block; margin-bottom: 6px;">Language:</label>
            <div class="lang-selector">
                <i class="fas fa-globe"></i>
                <select id="lang-switch" onchange="location.href='?lang='+this.value">
                    <option value="vi" <?= $lang === 'vi' ? 'selected' : '' ?>>Vietnamese</option>
                    <option value="en" <?= $lang === 'en' ? 'selected' : '' ?>>English</option>
                    <option value="bilingual" <?= $lang === 'bilingual' ? 'selected' : '' ?>>Bilingual (VI/EN)</option>
                </select>
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>
    </div>

    <div class="sidebar-scroll">
        <nav class="sidebar-nav">
            <div class="nav-section">
                <span class="nav-label"><?= __('main') ?></span>
                <a href="#" class="nav-link active" onclick="loadView('dashboard')">
                    <i class="fas fa-grid-2"></i> <span><?= __('dashboard') ?></span>
                </a>
            </div>

            <div class="nav-section">
                <span class="nav-label"><?= __('organization') ?></span>
                <a href="#" class="nav-link" onclick="loadView('organization')">
                    <i class="fas fa-building-user"></i> <span><?= __('departments') ?></span>
                </a>
            </div>

            <div class="nav-section">
                <span class="nav-label"><?= __('requirement_survey') ?></span>
                <a href="#" class="nav-link" onclick="loadView('survey')">
                    <i class="fas fa-clipboard-check"></i> <span><?= __('survey_form') ?></span>
                </a>
            </div>

            <div class="nav-section">
                <span class="nav-label"><?= __('analysis_design') ?></span>
                <a href="#" class="nav-link" onclick="loadView('process_mapping')">
                    <i class="fas fa-draw-polygon"></i> <span><?= __('process_mapping') ?></span>
                </a>
                <a href="#" class="nav-link" onclick="loadView('data_architecture')">
                    <i class="fas fa-database"></i> <span><?= __('data_architecture') ?></span>
                </a>
                <a href="#" class="nav-link" onclick="loadView('integration')">
                    <i class="fas fa-network-wired"></i> <span><?= __('integration') ?></span>
                </a>
            </div>

            <div class="nav-section">
                <span class="nav-label"><?= __('tracking_reports') ?></span>
                <a href="#" class="nav-link" onclick="loadView('backlog')">
                    <i class="fas fa-list-check"></i> <span><?= __('backlog') ?></span>
                </a>
                <a href="#" class="nav-link" onclick="loadView('reports')">
                    <i class="fas fa-chart-line"></i> <span><?= __('reports') ?></span>
                </a>
                <a href="#" class="nav-link" onclick="loadView('user_guide')">
                    <i class="fas fa-book-open"></i> <span><?= __('user_guide') ?></span>
                </a>
                <a href="#" class="nav-link" onclick="loadView('ai_toolkit')">
                    <i class="fas fa-microchip"></i> <span>AI Toolkit</span>
                </a>
                <a href="#" class="nav-link" onclick="loadView('profile')">
                    <i class="fas fa-user-circle"></i> <span>User Profile</span>
                </a>
            </div>

            <?php if (($_SESSION['role'] ?? 'user') === 'admin'): ?>
            <div class="nav-section">
                <span class="nav-label">System Admin</span>
                <a href="#" class="nav-link" onclick="loadView('settings')">
                    <i class="fas fa-sliders-h"></i> <span><?= __('configuration') ?></span>
                </a>
                <a href="#" class="nav-link" onclick="loadView('admin_users')">
                    <i class="fas fa-users-gear"></i> <span>User Control</span>
                </a>
            </div>
            <?php endif; ?>
        </nav>
    </div>

    <div class="sidebar-footer">
        <div class="user-strip">
            <div class="user-avatar">
                <span><?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?></span>
            </div>
            <div class="user-meta">
                <span class="user-display-name"><?= htmlspecialchars($_SESSION['full_name'] ?: $_SESSION['username']) ?></span>
                <span class="user-badge"><?= htmlspecialchars($_SESSION['role']) ?></span>
            </div>
            <button class="logout-trigger" onclick="handleLogout()" title="Sign Out">
                <i class="fas fa-arrow-right-from-bracket"></i>
            </button>
        </div>
    </div>
</aside>
