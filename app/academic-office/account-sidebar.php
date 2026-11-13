<?php
// Account settings is accessible only to authenticated Academic Office users.
?>
<aside class="sidebar acad-account-sidebar" id="sidebar">
  <div class="acad-account-brand"><img src="../assets/images/BCP_LOGO.png" alt="BCP"/><div><b>BESTLINK COLLEGE</b><small>ACADEMIC OFFICE</small></div></div>
  <div class="acad-account-nav-title">MAIN NAVIGATION</div>
  <a href="dashboard.php" class="acad-account-link">▦ &nbsp; Dashboard</a>
  <div class="acad-account-nav-title">ACADEMIC MODULES</div>
  <a href="preview-review.php" class="acad-account-link">▤ &nbsp; Preview Requests</a>
  <a href="history.php" class="acad-account-link">◷ &nbsp; Approval History</a>
  <a href="account.php" class="acad-account-link active" aria-current="page">⚙ &nbsp; Account Settings</a>
  <div class="acad-account-logout"><form method="post" action="../../logout.php"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(authCsrf(), ENT_QUOTES, 'UTF-8') ?>"><button type="submit">↪ &nbsp; Logout</button></form></div>
</aside>
