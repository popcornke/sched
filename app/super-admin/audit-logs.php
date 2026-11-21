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
    // Native PDO prepared statements require a unique placeholder per occurrence.
    $where[] = '(actor_username_snapshot LIKE :q_actor OR description LIKE :q_description OR target_id LIKE :q_target)';
    $searchLike = '%' . $search . '%';
    $params['q_actor'] = $searchLike;
    $params['q_description'] = $searchLike;
    $params['q_target'] = $searchLike;
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
<link rel="stylesheet" href="<?= saEsc(authBasePath()) ?>/app/assets/css/sa-audit-refresh.css?v=20261121b">
<header class="sa-page-head sa-audit-heading">
  <div><span class="sa-eyebrow">TRACEABILITY · SUPER ADMIN</span><h1>System audit logs.</h1>
    <p>Monitor account activity, scheduling operations and security events across the system.</p></div>
  <div class="sa-actions"><a class="sa-btn sa-btn--light sa-audit-export" href="reports.php?type=audit_logs"><i class="fa-solid fa-arrow-up-from-bracket"></i> Export audit report</a></div>
</header>
<section class="sa-card sa-audit-card" aria-label="Audit log events">
  <div class="sa-audit-toolbar">
    <div class="sa-audit-toolbar-title"><div><strong>Activity history</strong><span>Search and filter events automatically</span></div><span class="sa-audit-count" id="saAuditTotal"><?= number_format($total) ?> events</span></div>
    <form method="get" action="audit-logs.php" class="sa-audit-filters" id="saAuditFilters" role="search">
      <div class="sa-field sa-audit-search"><label for="saAuditSearch">Search events</label><div class="sa-audit-input-icon"><i class="fa-solid fa-magnifying-glass"></i><input id="saAuditSearch" name="q" value="<?= saEsc($search) ?>" autocomplete="off" placeholder="Actor, description or target..."></div></div>
      <div class="sa-field"><label for="saAuditModule">Module</label><select id="saAuditModule" name="module"><option value="">All modules</option><?php foreach ($modules as $item): ?><option value="<?= saEsc((string)$item) ?>" <?= $module === $item ? 'selected' : '' ?>><?= saEsc(ucwords(strtolower(str_replace('_',' ',(string)$item)))) ?></option><?php endforeach; ?></select></div>
      <div class="sa-field"><label for="saAuditAction">Action</label><select id="saAuditAction" name="action"><option value="">All actions</option><?php foreach ($actions as $item): ?><option value="<?= saEsc((string)$item) ?>" <?= $action === $item ? 'selected' : '' ?>><?= saEsc(ucwords(strtolower(str_replace('_',' ',(string)$item)))) ?></option><?php endforeach; ?></select></div>
      <div class="sa-field sa-audit-date"><label for="saAuditFrom">From</label><input id="saAuditFrom" type="date" name="from" value="<?= saEsc($dateFrom) ?>"></div>
      <div class="sa-field sa-audit-date"><label for="saAuditTo">To</label><input id="saAuditTo" type="date" name="to" value="<?= saEsc($dateTo) ?>"></div>
    </form>
    <p class="sa-audit-hint" id="saAuditHint" role="status" aria-live="polite">Filters update automatically. Clear the search and select All to see every event.</p>
  </div>
  <div id="saAuditResults">
    <div class="sa-table-wrap sa-audit-table-wrap">
    <table class="sa-table sa-audit-table"><thead><tr><th>When</th><th>Actor / role</th><th>Activity</th><th>Module</th><th>Target</th><th class="sa-audit-detail-heading">Details</th></tr></thead><tbody>
    <?php if ($rows === []): ?><tr><td colspan="6" class="sa-empty"><div class="sa-audit-empty"><i class="fa-solid fa-magnifying-glass"></i><strong>No matching events</strong><span>Try different filters or clear your search.</span></div></td></tr><?php else: foreach ($rows as $row):
      $roleText = (string)($row['actor_role_snapshot'] ?: 'SYSTEM');
      $actionText = (string)$row['action_type'];
      $isNegative = (bool)preg_match('/FAIL|DENIED|REJECT|DELETE|LOCK|ERROR/', $actionText);
      $isPositive = (bool)preg_match('/SUCCESS|APPROV|CREATED|SAVED|GENERATED|SUBMITTED|EXPORTED|DOWNLOADED/', $actionText);
      $details = [
         'Event ID' => '#'.(int)$row['audit_id'],
         'Date / time' => (string)$row['created_at'],
         'Actor' => (string)($row['actor_username_snapshot'] ?: 'System'),
         'Role' => $roleText,
         'Action' => $actionText,
         'Module' => (string)$row['module'],
         'Target' => trim((string)(($row['target_type'] ?? '').' '.($row['target_id'] ?? ''))) ?: '—',
         'IP address' => (string)($row['ip_address'] ?: '—'),
         'Description' => (string)($row['description'] ?: '—'),
      ];
      $json = json_encode($details, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE);
      ?>
      <tr>
        <td class="sa-audit-when"><strong><?= saEsc(date('M d, Y', strtotime((string)$row['created_at']))) ?></strong><span><?= saEsc(date('h:i A', strtotime((string)$row['created_at']))) ?> · #<?= (int)$row['audit_id'] ?></span></td>
        <td><div class="sa-audit-actor"><span class="sa-audit-initial"><?= saEsc(strtoupper(substr((string)($row['actor_username_snapshot'] ?: 'S'),0,1))) ?></span><div><strong><?= saEsc((string)($row['actor_username_snapshot'] ?: 'System')) ?></strong><small><?= saEsc(ucwords(strtolower(str_replace('_',' ',$roleText)))) ?></small></div></div></td>
        <td><div class="sa-audit-activity"><span class="sa-audit-event <?= $isNegative ? 'is-negative' : ($isPositive ? 'is-positive' : '') ?>"><?= saEsc(ucwords(strtolower(str_replace('_',' ',$actionText)))) ?></span><span class="sa-audit-desc"><?= saEsc((string)($row['description'] ?: '—')) ?></span></div></td>
        <td><span class="sa-audit-module"><?= saEsc(ucwords(strtolower(str_replace('_',' ',(string)$row['module'])))) ?></span></td>
        <td class="sa-audit-target"><?= saEsc(trim((string)(($row['target_type'] ?? '').' '.($row['target_id'] ?? ''))) ?: '—') ?></td>
        <td><button type="button" class="sa-audit-view" data-audit='<?= saEsc((string)$json) ?>' aria-label="View details for audit event #<?= (int)$row['audit_id'] ?>"><i class="fa-regular fa-eye"></i> View</button></td>
      </tr>
    <?php endforeach; endif; ?></tbody></table></div>
    <div class="sa-audit-footer"><span><?= number_format($total) ?> matching events · Page <?= $page ?> of <?= $totalPages ?></span>
      <?php if($totalPages>1): ?><nav class="sa-audit-pages" aria-label="Audit log pages">
        <?php if($page>1): ?><a href="audit-logs.php?<?= saEsc(saAuditQuery(['page'=>$page-1])) ?>">Previous</a><?php endif; ?>
        <?php for($i=max(1,$page-2);$i<=min($totalPages,$page+2);$i++): ?><a class="<?= $i===$page?'current':'' ?>" <?= $i===$page?'aria-current="page"':'' ?> href="audit-logs.php?<?= saEsc(saAuditQuery(['page'=>$i])) ?>"><?= $i ?></a><?php endfor; ?>
        <?php if($page<$totalPages): ?><a href="audit-logs.php?<?= saEsc(saAuditQuery(['page'=>$page+1])) ?>">Next</a><?php endif; ?>
      </nav><?php endif; ?>
    </div>
  </div>
</section>
<div class="sa-audit-overlay" id="saAuditOverlay" hidden><div class="sa-audit-dialog" role="dialog" aria-modal="true" aria-labelledby="saAuditModalTitle" tabindex="-1">
  <div class="sa-audit-dialog-head"><div><span class="sa-eyebrow">AUDIT EVENT</span><h2 id="saAuditModalTitle">Activity details</h2></div><button type="button" id="saAuditCloseX" class="sa-audit-icon-btn" aria-label="Close details"><i class="fa-solid fa-xmark"></i></button></div>
  <div class="sa-audit-dialog-body" id="saAuditDetails"></div><div class="sa-audit-dialog-foot"><button type="button" class="sa-btn sa-btn--primary" id="saAuditClose">Close</button></div>
</div></div>
<script src="<?= saEsc(authBasePath()) ?>/app/assets/js/sa-audit-refresh.js?v=20261121b" defer></script>
<?php saPageEnd(); ?>
