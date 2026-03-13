<?php
require_once '../config/bootstrap.php';
requireAdmin();
?>
<div class="module-container">
    <div class="section-header">
        <h1><i class="fas fa-user-shield"></i> User Administration</h1>
        <div style="display:flex; gap:8px;">
            <button class="btn btn-outline" onclick="loadAdminUsers('all')">All</button>
            <button class="btn btn-outline" onclick="loadAdminUsers('pending')">Pending</button>
            <button class="btn btn-outline" onclick="loadAdminUsers('approved')">Approved</button>
        </div>
    </div>
    <div class="section">
        <div style="overflow:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Approval</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="admin-user-table-body">
                    <tr><td colspan="8" style="text-align:center; color:#666;">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
