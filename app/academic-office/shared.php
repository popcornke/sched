<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/shared/auth.php';
authRequire(false,['ACADEMIC_OFFICE']);
require_once __DIR__.'/shared-functions.php';
function acadLayout(string $title):void {
 $name=acadH($_SESSION['auth_username']??'Academic Office');$csrf=acadH(authCsrf());$page=basename($_SERVER['SCRIPT_NAME']??'');
 $links=['dashboard.php'=>['Dashboard','▦'],'preview-review.php'=>['Preview Requests','▤'],'history.php'=>['Approval History','◷']];
 echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.acadH($title).' | BCP Academic Office</title><link rel="stylesheet" href="assets/office.css?v=9"><link rel="stylesheet" href="assets/admin-consistency.css?v=9"></head><body class="acad-shell"><div class="layout"><aside class="acad-sidebar" id="acadSidebar"><div class="brand"><img src="../assets/images/BCP_LOGO.png" alt="BCP logo"><span class="acad-notice" aria-label="Academic Office">AO</span></div><div class="acad-side-heading"><b>Main Navigation</b><small>General</small></div><nav><a class="'.($page==='dashboard.php'?'active':'').'" href="dashboard.php"><span class="acad-nav-symbol">◉</span> Dashboard</a></nav><div class="acad-side-heading acad-mod-heading"><b>Academic Modules</b><small>Reviews &amp; Approvals</small></div><nav>';
 foreach($links as $href=>$item) {if($href==='dashboard.php')continue;echo '<a class="'.($page===$href?'active':'').'" href="'.$href.'"><span class="acad-nav-symbol">'.$item[1].'</span> '.acadH($item[0]).'</a>';}
 echo '</nav><div class="sidefoot"><form method="post" action="../../logout.php"><input type="hidden" name="csrf_token" value="'.$csrf.'"><button type="submit">↪ &nbsp; Logout</button></form></div></aside><div class="main"><header class="acad-topbar"><button type="button" class="menu acad-hamburger" aria-label="Toggle sidebar" aria-expanded="true" onclick="document.body.classList.toggle(\'acad-sidebar-hidden\');this.setAttribute(\'aria-expanded\',!document.body.classList.contains(\'acad-sidebar-hidden\'))">☰</button><span class="acad-topbar-spacer"></span><span class="role">♙ &nbsp; ACADEMIC OFFICE</span><span class="acad-avatar" title="'.$name.'">'.acadH(strtoupper(substr((string)($_SESSION['auth_username']??'A'),0,1))).'</span></header><main class="acad-content">';
}
function acadEnd():void{echo '</main></div></div></body></html>';}
