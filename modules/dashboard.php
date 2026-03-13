<?php require_once '../config/bootstrap.php'; ?>
<div class="page-header">
    <div class="header-intro">
        <h1>Hello, <?= htmlspecialchars($_SESSION['full_name'] ?: $_SESSION['username']) ?></h1>
        <p><?= __('surveyed_units') ?> - <?= __('select_unit_hint') ?></p>
    </div>
    <div class="header-controls">
        <div class="search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="dept-search" placeholder="Search systems or units..." onkeyup="filterDepts()">
        </div>
    </div>
</div>

<div id="dept-card-list" class="card-grid">
    <!-- Loaded via app.js: renderDeptCards() -->
</div>
