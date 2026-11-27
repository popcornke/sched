<?php
declare(strict_types=1);
// Shared CSV response utilities; authorization is handled by the calling page.
function acadCsvStart(string $filename) {
 header('Content-Type: text/csv; charset=UTF-8');
 header('Content-Disposition: attachment; filename="'.$filename.'"');
 header('Cache-Control: no-store');
 $out=fopen('php://output','w');
 fwrite($out,"\xEF\xBB\xBF");
 return $out;
}
function acadCsvRow($out,array $row):void {
 $row=array_map(static function($value){$v=(string)$value;return preg_match('/^[\s\x00-\x1f]*[=+@-]|^[\t\r\n]/',$v)?"'".$v:$v;},$row);
 fputcsv($out,$row,',','"','');
}
