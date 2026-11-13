<?php

declare(strict_types=1);

require_once __DIR__ . '/shared.php';
saRequire();

$db = authDb();
if (!saTableExists($db, 'system_audit_logs')) {
    saPageStart('Audit Logs', 'audit');
    echo '<div class="sa-alert sa-alert--error">Audit table is not installed. Run <span class="sa-code">database/migrations/027_super_admin.sql</span>.</div>';
    saPageEnd();
    exit;
}

$search = trim((string) ($_GET['q'] ?? ''));
$module = trim((string) ($_GET['module'] ?? ''));
$action = trim((string) ($_GET['action'] ?? ''));
$dateFrom = trim((string) ($_GET['from'] ?? ''));
$dateTo = trim((string) ($_GET['to'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = ['1=1'];
$params = [];
if ($search !== '') {
    $where[] = '(actor_username_snapshot LIKE :q OR description LIKE :q OR target_id LIKE :q)';
    $params['q'] = '%' . $search . '%';
}
if ($module !== '') {
    $where[] = 'module = :module';
    $params['module'] = $module;
}
if ($action !== '') {
    $where[] = 'action_type = :action';
    $params['action'] = $action;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $where[] = 'created_at >= :date_from';
    $params['date_from'] = $dateFrom . ' 00:00:00';
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $where[] = 'created_at <= :date_to';
    $params['date_to'] = $dateTo . ' 23:59:59';
}
$whereSql = implode(' AND ', $where);

$countStmt = $db->prepare('SELECT COUNT(*) FROM system_audit_logs WHERE ' . $whereSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$stmt = $db->prepare(
    'SELECT audit_id, actor_user_id, actor_username_snapshot, actor_role_snapshot,
            action_type, module, target_type, target_id, description, ip_address, created_at
     FROM system_audit_logs
     WHERE ' . $whereSql . '
     ORDER BY audit_id DESC
     LIMIT :limit OFFSET :offset'
);
foreach ($params as $key => $value) $stmt->bindValue(':' . $key, $value);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$modules = $db->query('SELECT DISTINCT module FROM system_audit_logs ORDER BY module')->fetchAll(PDO::FETCH_COLUMN);
$actions = $db->query('SELECT DISTINCT action_type FROM system_audit_logs ORDER BY action_type')->fetchAll(PDO::FETCH_COLUMN);

function saAuditQuery(array $extra = []): string
{
    $query = array_filter([
        'q' => $_GET['q'] ?? '',
        'module' => $_GET['module'] ?? '',
        'action' => $_GET['action'] ?? '',
        'from' => $_GET['from'] ?? '',
        'to' => $_GET['to'] ?? '',
    ], static fn($v) => $v !== '');
    return http_build_query(array_merge($query, $extra));
}

saPageStart('Audit Logs', 'audit');
?>
<header class="sa-page-head">
    <div><span class="sa-eyebrow">TRACEABILITY</span><h1>System audit logs.</h1><p>Review account sign-ins, failed logins, OTP attempts, logouts, and system actions by actor, role, module, or date.</p></div>
    <div class="sa-actions"><a class="sa-btn sa-btn--light" href="reports.php?type=audit_logs"><i class="fa-solid fa-file-export"></i>Export audit report</a></div>
</header>

<section class="sa-card">
    <div class="sa-card-pad">
        <form method="get" class="sa-filter">
            <div class="sa-field"><label>Search</label><input name="q" value="<?= saEsc($search) ?>" placeholder="Actor, description, target"></div>
            <div class="sa-field"><label>Module</label><select name="module"><option value="">All modules</option><?php foreach ($modules as $item): ?><option value="<?= saEsc((string) $item) ?>" <?= $module === $item ? 'selected' : '' ?>><?= saEsc((string) $item) ?></option><?php endforeach; ?></select></div>
            <div class="sa-field"><label>Action</label><select name="action"><option value="">All actions</option><?php foreach ($actions as $item): ?><option value="<?= saEsc((string) $item) ?>" <?= $action === $item ? 'selected' : '' ?>><?= saEsc((string) $item) ?></option><?php endforeach; ?></select></div>
            <div class="sa-field"><label>From</label><input type="date" name="from" value="<?= saEsc($dateFrom) ?>"></div>
            <div class="sa-field"><label>To</label><input type="date" name="to" value="<?= saEsc($dateTo) ?>"></div>
            <button class="sa-btn sa-btn--primary" type="submit"><i class="fa-solid fa-filter"></i>Apply</button>
            <a class="sa-btn sa-btn--light" href="audit-logs.php">Reset</a>
        </form>
    </div>
    <div class="sa-table-wrap">
        <table class="sa-table">
            <thead><tr><th>ID</th><th>Date / time</th><th>Actor</th><th>Role</th><th>Action</th><th>Module</th><th>Target</th><th>Description</th><th>IP</th></tr></thead>
            <tbody>
            <?php if ($rows === []): ?><tr><td colspan="9" class="sa-empty">No audit events match the filters.</td></tr><?php else: foreach ($rows as $row): ?>
                <tr>
                    <td class="muted">#<?= (int) $row['audit_id'] ?></td>
                    <td class="muted"><?= saEsc(date('M d, Y H:i:s', strtotime((string) $row['created_at']))) ?></td>
                    <td><strong><?= saEsc((string) ($row['actor_username_snapshot'] ?: 'System')) ?></strong></td>
                    <td><span class="sa-badge sa-badge--role"><?= saEsc((string) ($row['actor_role_snapshot'] ?: 'SYSTEM')) ?></span></td>
                    <td><span class="sa-badge sa-badge--neutral"><?= saEsc((string) $row['action_type']) ?></span></td>
                    <td><?= saEsc((string) $row['module']) ?></td>
                    <td class="muted"><?= saEsc(trim((string) (($row['target_type'] ?? '') . ' ' . ($row['target_id'] ?? '')))) ?: '—' ?></td>
                    <td><?= saEsc((string) ($row['description'] ?: '—')) ?></td>
                    <td class="muted"><?= saEsc((string) ($row['ip_address'] ?: '—')) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <div class="sa-card-pad" style="padding-top:10px;padding-bottom:0;color:#71809d;font-size:.7rem"><?= number_format($total) ?> matching audit events</div>
    <?php if ($totalPages > 1): ?><div class="sa-pagination"><?php for ($i = 1; $i <= $totalPages; $i++): ?><?php if ($i === $page): ?><span class="current"><?= $i ?></span><?php else: ?><a href="audit-logs.php?<?= saEsc(saAuditQuery(['page' => $i])) ?>"><?= $i ?></a><?php endif; ?><?php endfor; ?></div><?php endif; ?>
</section>
<?php saPageEnd(); ?>
