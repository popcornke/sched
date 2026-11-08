<?php

declare(strict_types=1);

require_once __DIR__ . '/shared.php';
saRequire();

$db = authDb();
$hasEmail = saColumnExists($db, 'auth_users', 'email');
$hasVerified = saColumnExists($db, 'auth_users', 'email_verified_at');
$hasPasswordChanged = saColumnExists($db, 'auth_users', 'password_changed_at');

$search = trim((string) ($_GET['q'] ?? ''));
$roleFilter = strtoupper(trim((string) ($_GET['role'] ?? '')));
$statusFilter = (string) ($_GET['status'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;
$roles = ['SUPER_ADMIN', 'ADMIN', 'SCHEDULER', 'ACADEMIC_OFFICE', 'STAFF', 'TEACHER'];

$where = ['1=1'];
$params = [];
if ($search !== '') {
    $where[] = $hasEmail
        ? '(username LIKE :search OR email LIKE :search)'
        : 'username LIKE :search';
    $params['search'] = '%' . $search . '%';
}
if (in_array($roleFilter, $roles, true)) {
    $where[] = 'role = :role';
    $params['role'] = $roleFilter;
}
if ($statusFilter === 'active') {
    $where[] = 'is_active = 1';
} elseif ($statusFilter === 'inactive') {
    $where[] = 'is_active = 0';
}
$whereSql = implode(' AND ', $where);

$totalStmt = $db->prepare('SELECT COUNT(*) FROM auth_users WHERE ' . $whereSql);
$totalStmt->execute($params);
$total = (int) $totalStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$fields = ['user_id', 'username', 'role', 'is_active', 'failed_attempts', 'locked_until', 'created_at'];
if ($hasEmail) $fields[] = 'email';
if ($hasVerified) $fields[] = 'email_verified_at';
if ($hasPasswordChanged) $fields[] = 'password_changed_at';

$sql = 'SELECT ' . implode(', ', $fields) . ' FROM auth_users WHERE ' . $whereSql . ' ORDER BY user_id DESC LIMIT :limit OFFSET :offset';
$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$editId = max(0, (int) ($_GET['edit'] ?? 0));
$editAccount = null;
if ($editId > 0) {
    $editStmt = $db->prepare('SELECT ' . implode(', ', $fields) . ' FROM auth_users WHERE user_id = :id LIMIT 1');
    $editStmt->execute(['id' => $editId]);
    $editAccount = $editStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function saAccountsQuery(array $extra = []): string
{
    $query = array_filter([
        'q' => $_GET['q'] ?? '',
        'role' => $_GET['role'] ?? '',
        'status' => $_GET['status'] ?? '',
    ], static fn($v) => $v !== '');
    return http_build_query(array_merge($query, $extra));
}

saPageStart('Account Management', 'accounts');
?>
<header class="sa-page-head">
    <div>
        <span class="sa-eyebrow">ACCESS CONTROL</span>
        <h1>Account management.</h1>
        <p>CRUD controls for system accounts. Teacher accounts may also depend on their linked teacher record.</p>
    </div>
    <div class="sa-actions"><span class="sa-btn sa-btn--light"><i class="fa-solid fa-users"></i><?= number_format($total) ?> matching accounts</span></div>
</header>

<section class="sa-grid sa-grid--2">
    <article class="sa-card sa-card-pad">
        <div class="sa-section-title"><div><h2>Create account</h2><p>Provision a new login account.</p></div></div>
        <form method="post" action="account-action.php" class="sa-form-grid">
            <input type="hidden" name="csrf_token" value="<?= saEsc(authCsrf()) ?>">
            <input type="hidden" name="action" value="create">
            <div class="sa-field"><label for="new_username">Username</label><input id="new_username" name="username" maxlength="80" required></div>
            <?php if ($hasEmail): ?><div class="sa-field"><label for="new_email">Email</label><input id="new_email" name="email" type="email" maxlength="254"><small>Admin/Scheduler login OTP requires a verified email.</small></div><?php endif; ?>
            <div class="sa-field"><label for="new_role">Role</label><select id="new_role" name="role" required><?php foreach ($roles as $role): ?><option value="<?= saEsc($role) ?>"><?= saEsc(str_replace('_', ' ', $role)) ?></option><?php endforeach; ?></select></div>
            <div class="sa-field"><label for="new_password">Temporary password</label><input id="new_password" name="password" type="password" minlength="12" maxlength="128" required><small>Minimum 12 characters.</small></div>
            <div class="sa-field sa-field--full"><button class="sa-btn sa-btn--primary" type="submit"><i class="fa-solid fa-user-plus"></i>Create account</button></div>
        </form>
    </article>

    <article class="sa-card sa-card-pad" id="edit-card">
        <div class="sa-section-title"><div><h2><?= $editAccount ? 'Edit account' : 'Edit account' ?></h2><p><?= $editAccount ? 'Update the selected account.' : 'Choose Edit from the account table below.' ?></p></div></div>
        <?php if (!$editAccount): ?>
            <div class="sa-empty">No account selected.</div>
        <?php else: ?>
            <form method="post" action="account-action.php" class="sa-form-grid">
                <input type="hidden" name="csrf_token" value="<?= saEsc(authCsrf()) ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" value="<?= (int) $editAccount['user_id'] ?>">
                <div class="sa-field"><label>Username</label><input name="username" maxlength="80" value="<?= saEsc((string) $editAccount['username']) ?>" required></div>
                <?php if ($hasEmail): ?><div class="sa-field"><label>Email</label><input name="email" type="email" maxlength="254" value="<?= saEsc((string) ($editAccount['email'] ?? '')) ?>"></div><?php endif; ?>
                <div class="sa-field"><label>Role</label><select name="role" <?= (int) $editAccount['user_id'] === (int) ($_SESSION['auth_user_id'] ?? 0) ? 'disabled' : '' ?>><?php foreach ($roles as $role): ?><option value="<?= saEsc($role) ?>" <?= $role === $editAccount['role'] ? 'selected' : '' ?>><?= saEsc(str_replace('_', ' ', $role)) ?></option><?php endforeach; ?></select><?php if ((int) $editAccount['user_id'] === (int) ($_SESSION['auth_user_id'] ?? 0)): ?><input type="hidden" name="role" value="SUPER_ADMIN"><small>Your current role cannot be changed here.</small><?php endif; ?></div>
                <div class="sa-field"><label>New password (optional)</label><input name="password" type="password" minlength="12" maxlength="128" placeholder="Leave blank to keep current password"></div>
                <div class="sa-field sa-field--full"><button class="sa-btn sa-btn--primary" type="submit"><i class="fa-solid fa-floppy-disk"></i>Save changes</button></div>
            </form>
        <?php endif; ?>
    </article>
</section>

<section class="sa-section sa-card">
    <div class="sa-card-pad">
        <form method="get" class="sa-filter">
            <div class="sa-field"><label>Search</label><input name="q" value="<?= saEsc($search) ?>" placeholder="Username<?= $hasEmail ? ' or email' : '' ?>"></div>
            <div class="sa-field"><label>Role</label><select name="role"><option value="">All roles</option><?php foreach ($roles as $role): ?><option value="<?= saEsc($role) ?>" <?= $roleFilter === $role ? 'selected' : '' ?>><?= saEsc(str_replace('_', ' ', $role)) ?></option><?php endforeach; ?></select></div>
            <div class="sa-field"><label>Status</label><select name="status"><option value="">All statuses</option><option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
            <button class="sa-btn sa-btn--primary" type="submit"><i class="fa-solid fa-filter"></i>Apply</button>
            <a class="sa-btn sa-btn--light" href="accounts.php">Reset</a>
        </form>
    </div>
    <div class="sa-table-wrap">
        <table class="sa-table">
            <thead><tr><th>ID</th><th>Username</th><?php if ($hasEmail): ?><th>Email</th><?php endif; ?><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if ($accounts === []): ?><tr><td colspan="<?= $hasEmail ? 7 : 6 ?>" class="sa-empty">No accounts found.</td></tr><?php else: foreach ($accounts as $account): ?>
                <?php $isSelf = (int) $account['user_id'] === (int) ($_SESSION['auth_user_id'] ?? 0); ?>
                <tr>
                    <td class="muted">#<?= (int) $account['user_id'] ?></td>
                    <td><strong><?= saEsc((string) $account['username']) ?></strong><?php if ($isSelf): ?> <span class="sa-badge sa-badge--neutral">YOU</span><?php endif; ?></td>
                    <?php if ($hasEmail): ?><td class="muted"><?= saEsc((string) ($account['email'] ?: '—')) ?></td><?php endif; ?>
                    <td><span class="sa-badge sa-badge--role"><?= saEsc((string) $account['role']) ?></span></td>
                    <td><span class="sa-badge <?= (int) $account['is_active'] === 1 ? 'sa-badge--active' : 'sa-badge--inactive' ?>"><?= (int) $account['is_active'] === 1 ? 'ACTIVE' : 'INACTIVE' ?></span></td>
                    <td class="muted"><?= saEsc(date('M d, Y', strtotime((string) $account['created_at']))) ?></td>
                    <td><div class="sa-inline-actions">
                        <a class="sa-btn sa-btn--light" href="accounts.php?<?= saEsc(saAccountsQuery(['edit' => (int) $account['user_id']])) ?>#edit-card"><i class="fa-solid fa-pen"></i>Edit</a>
                        <?php if (!$isSelf): ?>
                            <form method="post" action="account-action.php" onsubmit="return confirm('<?= (int) $account['is_active'] === 1 ? 'Deactivate' : 'Activate' ?> this account?')"><input type="hidden" name="csrf_token" value="<?= saEsc(authCsrf()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="user_id" value="<?= (int) $account['user_id'] ?>"><button class="sa-btn <?= (int) $account['is_active'] === 1 ? 'sa-btn--warn' : 'sa-btn--success' ?>" type="submit"><i class="fa-solid <?= (int) $account['is_active'] === 1 ? 'fa-user-slash' : 'fa-user-check' ?>"></i><?= (int) $account['is_active'] === 1 ? 'Deactivate' : 'Activate' ?></button></form>
                            <form method="post" action="account-action.php" onsubmit="return confirm('Delete this unused account permanently? If it has system history, deletion will be blocked and you should deactivate it instead.')"><input type="hidden" name="csrf_token" value="<?= saEsc(authCsrf()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?= (int) $account['user_id'] ?>"><button class="sa-btn sa-btn--danger" type="submit"><i class="fa-solid fa-trash"></i>Delete</button></form>
                        <?php endif; ?>
                    </div></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?><div class="sa-pagination"><?php for ($i = 1; $i <= $totalPages; $i++): ?><?php if ($i === $page): ?><span class="current"><?= $i ?></span><?php else: ?><a href="accounts.php?<?= saEsc(saAccountsQuery(['page' => $i])) ?>"><?= $i ?></a><?php endif; ?><?php endfor; ?></div><?php endif; ?>
</section>

<div class="sa-note sa-section"><strong>Delete behavior:</strong> accounts already referenced by approvals, logs, notifications, or other records are intentionally protected by database foreign keys. Use <strong>Deactivate</strong> for those accounts so historical records remain intact.</div>
<?php saPageEnd(); ?>
