<?php
/** BCP-branded Office Open XML workbook, no third-party runtime dependency. */
declare(strict_types=1);

function bcpXlsxEscape($value): string {
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', ' ', (string)($value ?? '')) ?? '';
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}
function bcpXlsxCol(int $num): string {
    $s=''; while ($num>0) {$num--; $s=chr(65+$num%26).$s;$num=intdiv($num,26);}return $s;
}
function bcpXlsxCell(int $col,int $row,$value,int $style=0,bool $numeric=false): string {
    $ref=bcpXlsxCol($col).$row;
    if ($numeric) return '<c r="'.$ref.'" s="'.$style.'"><v>'.(int)$value.'</v></c>';
    return '<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.bcpXlsxEscape($value).'</t></is></c>';
}
function bcpXlsxRow(int $number,array $cells,int $style=0,array $numericCols=[],?float $height=null): string {
    $xml='<row r="'.$number.'"'.($height!==null?' ht="'.$height.'" customHeight="1"':'').'>';
    foreach ($cells as $idx=>$value) $xml.=bcpXlsxCell($idx+1,$number,$value,$style,in_array($idx,$numericCols,true));
    return $xml.'</row>';
}
function bcpXlsxSheet(array $spec): string {
    $cols=$spec['widths'];$max=count($cols);$last=bcpXlsxCol($max);
    $xml='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
    $xml.='<sheetViews><sheetView workbookViewId="0" showGridLines="0">';
    if (!empty($spec['freeze'])) {$top=$spec['freeze'];$xml.='<pane ySplit="'.($top-1).'" topLeftCell="A'.$top.'" activePane="bottomLeft" state="frozen"/>';} $xml.='</sheetView></sheetViews><sheetFormatPr defaultRowHeight="17"/><cols>';
    foreach ($cols as $index=>$width) $xml.='<col min="'.($index+1).'" max="'.($index+1).'" width="'.$width.'" customWidth="1"/>';
    $xml.='</cols><sheetData>';
    foreach ($spec['rows'] as $item) $xml.=$item;
    $xml.='</sheetData>';
    if (!empty($spec['merges'])) {
        $xml.='<mergeCells count="'.count($spec['merges']).'">';
        foreach ($spec['merges'] as $range) $xml.='<mergeCell ref="'.$range.'"/>';
        $xml.='</mergeCells>';
    }
    if (!empty($spec['filter'])) $xml.='<autoFilter ref="'.$spec['filter'].'"/>';
    $xml.='<printOptions horizontalCentered="1"/><pageMargins left="0.3" right="0.3" top="0.5" bottom="0.5" header="0.2" footer="0.25"/><pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/><headerFooter><oddFooter>&amp;LBCP | Academic Office&amp;CConfidential institutional report&amp;RPage &amp;P of &amp;N</oddFooter></headerFooter>';
    if (!empty($spec['logo'])) $xml.='<drawing r:id="rId1"/>';
    return $xml.'</worksheet>';
}
function bcpXlsxStyles(): string {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
      .'<fonts count="6"><font><sz val="10"/><name val="Aptos"/><color rgb="FF273444"/></font><font><b/><sz val="16"/><name val="Aptos Display"/><color rgb="FF15345B"/></font><font><b/><sz val="12"/><name val="Aptos"/><color rgb="FF2458A6"/></font><font><b/><sz val="10"/><name val="Aptos"/><color rgb="FFFFFFFF"/></font><font><b/><sz val="10"/><name val="Aptos"/><color rgb="FF15345B"/></font><font><sz val="9"/><name val="Aptos"/><color rgb="FF667085"/></font></fonts>'
      .'<fills count="6"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF15345B"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFEAF2FC"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF3F5F8"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFD4A83F"/><bgColor indexed="64"/></patternFill></fill></fills>'
      .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="hair"><color rgb="FFDDE5EF"/></left><right style="hair"><color rgb="FFDDE5EF"/></right><top style="hair"><color rgb="FFDDE5EF"/></top><bottom style="hair"><color rgb="FFDDE5EF"/></bottom><diagonal/></border></borders>'
      .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="9">'
      .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' //0 plain
      .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' //1 title
      .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' //2 subtitle
      .'<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment wrapText="1" vertical="center"/></xf>' //3 heading
      .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment wrapText="1" vertical="center"/></xf>' //4 data
      .'<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyAlignment="1"><alignment wrapText="1" vertical="center"/></xf>' //5 alternate
      .'<xf numFmtId="0" fontId="4" fillId="3" borderId="1" xfId="0" applyAlignment="1"><alignment wrapText="1" vertical="center"/></xf>' //6 summary
      .'<xf numFmtId="0" fontId="5" fillId="4" borderId="0" xfId="0" applyAlignment="1"><alignment wrapText="1" vertical="center"/></xf>' //7 note
      .'<xf numFmtId="0" fontId="4" fillId="5" borderId="0" xfId="0"/>' //8 gold
      .'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
}
/** Supply an immutable report record, its persisted metrics, and breakdown. */
function bcpWriteReportXlsx(array $report,array $metrics,array $breakdown,array $scheduleRows,string $logoPath): string {
    if (!class_exists('ZipArchive')) throw new RuntimeException('XLSX export requires the PHP zip extension. Enable php_zip in XAMPP.');
    $temp=tempnam(sys_get_temp_dir(),'bcp_xlsx_'); if ($temp===false) throw new RuntimeException('Unable to create workbook.');
    $zip=new ZipArchive(); if ($zip->open($temp,ZipArchive::OVERWRITE)!==true){unlink($temp);throw new RuntimeException('Unable to create workbook archive.');}
    try {
        $reportId=(int)$report['report_id'];$scope=(string)($report['scope_label']?:'All Departments');
        $summaryRows=[];$merge=[];
        $summaryRows[]=bcpXlsxRow(1,[''],0,[],31);
        $summaryRows[]=bcpXlsxRow(2,['BESTLINK COLLEGE OF THE PHILIPPINES'],1,[],30);$merge[]='A2:H2';
        $summaryRows[]=bcpXlsxRow(3,['ACADEMIC OFFICE  |  OFFICIAL SCHEDULE REPORT'],2,[],25);$merge[]='A3:H3';
        $summaryRows[]=bcpXlsxRow(4,[''],8,[],5);$merge[]='A4:H4';
        $summaryRows[]=bcpXlsxRow(5,['Report Reference','BCP-AO-'.str_pad((string)$reportId,6,'0',STR_PAD_LEFT),'Department / Program',$scope],6,[],23);$merge[]='D5:H5';
        $summaryRows[]=bcpXlsxRow(6,['Reporting Period',$report['period_start'].' to '.$report['period_end'],'Generated By',$report['username']??'Academic Office'],5,[],23);$merge[]='D6:H6';
        $summaryRows[]=bcpXlsxRow(7,['Saved At',$report['created_at'],'Captured Meetings',count($scheduleRows)],5,[],23);$merge[]='D7:H7';
        $summaryRows[]=bcpXlsxRow(9,['APPROVAL SUMMARY'],3,[],25);$merge[]='A9:H9';
        $summaryRows[]=bcpXlsxRow(10,['Requests','Approved','Pending','Rejected','Programs','Saved Meetings'],3,[],24);
        $summaryRows[]=bcpXlsxRow(11,[(int)($metrics['requests']??0),(int)($metrics['approved']??0),(int)($metrics['pending']??0),(int)($metrics['rejected']??0),(int)($metrics['programs']??count($breakdown)),count($scheduleRows)],6,[0,1,2,3,4,5],25);
        $summaryRows[]=bcpXlsxRow(13,['DEPARTMENT BREAKDOWN'],3,[],25);$merge[]='A13:H13';
        $summaryRows[]=bcpXlsxRow(14,['Program','Requests','Approved','Rejected','Pending'],3,[],24);
        $i=15;foreach($breakdown as $record){$summaryRows[]=bcpXlsxRow($i,[$record['program_code']??'',(int)($record['requests']??0),(int)($record['approved']??0),(int)($record['rejected']??0),(int)($record['pending']??0)],$i%2?4:5,[1,2,3,4],22);$i++;}
        if (!$breakdown) {$summaryRows[]=bcpXlsxRow($i++,['No departmental records within the selected period.'],7);}
        $i+=2;$summaryRows[]=bcpXlsxRow($i,['Historical values reflect the saved report. Detailed rows are captured at report creation.'],7,[],24);$merge[]='A'.$i.':H'.$i;
        $summary=bcpXlsxSheet(['rows'=>$summaryRows,'widths'=>[23,28,25,24,24,22,19,18],'merges'=>$merge,'logo'=>true]);

        $headers=['Program','Academic Year','Semester','Batch ID','Section','Subject Code','Subject Title','Teacher','Delivery Mode','Day','Start Time','End Time','Room'];
        $detail=[];$detailMerges=['A1:M1','A2:M2','A3:M3','A5:M5'];
        $detail[]=bcpXlsxRow(1,['BESTLINK COLLEGE OF THE PHILIPPINES'],1,[],35);
        $detail[]=bcpXlsxRow(2,['ACADEMIC OFFICE  |  DETAILED CLASS SCHEDULES'],2,[],25);
        $detail[]=bcpXlsxRow(3,['REPORT #'.$reportId.'  |  '. $scope .'  |  '.$report['period_start'].' TO '.$report['period_end']],7,[],25);
        $detail[]=bcpXlsxRow(5,['CAPTURED SCHEDULE RECORDS'],3,[],24);
        $detail[]=bcpXlsxRow(6,$headers,3,[],33);
        $j=7;foreach($scheduleRows as $row){$detail[]=bcpXlsxRow($j,[$row['program_code']??'',$row['academic_year']??'',$row['semester']??'',$row['batch_id']??'',$row['section_code']??'',$row['subject_code']??'',$row['subject_title']??'',$row['teacher_name']??'',$row['delivery_mode']??'',$row['day_of_week']??'',$row['start_time']??'',$row['end_time']??'',$row['room_name']??'ONLINE'],$j%2?4:5,[],31);$j++;}
        if($j===7)$detail[]=bcpXlsxRow(7,['No captured approved meetings in this report.'],7);
        $detailSheet=bcpXlsxSheet(['rows'=>$detail,'widths'=>[15,19,12,13,20,19,41,32,18,18,16,16,22],'merges'=>$detailMerges,'filter'=>'A6:M'.max(6,$j-1),'freeze'=>7,'logo'=>false]);
        $contentTypes='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="png" ContentType="image/png"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/></Types>';
        $zip->addFromString('[Content_Types].xml',$contentTypes);
        $zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView/></bookViews><sheets><sheet name="Report Summary" sheetId="1" r:id="rId1"/><sheet name="Detailed Schedules" sheetId="2" r:id="rId2"/></sheets><definedNames><definedName name="_xlnm.Print_Titles" localSheetId="0">\'Report Summary\'!$1:$4</definedName><definedName name="_xlnm.Print_Titles" localSheetId="1">\'Detailed Schedules\'!$1:$6</definedName></definedNames><calcPr calcId="191029"/></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml',bcpXlsxStyles());
        $zip->addFromString('xl/worksheets/sheet1.xml',$summary);
        $zip->addFromString('xl/worksheets/sheet2.xml',$detailSheet);
        if (!is_file($logoPath)) throw new RuntimeException('Official BCP logo is missing.');
        $zip->addFile($logoPath,'xl/media/bcp-logo.png');
        $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/></Relationships>');
        $zip->addFromString('xl/drawings/_rels/drawing1.xml.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/bcp-logo.png"/></Relationships>');
        // Logo anchored to G1:H3. The headline stays centered across the report width.
        $zip->addFromString('xl/drawings/drawing1.xml','<?xml version="1.0" encoding="UTF-8"?><xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><xdr:twoCellAnchor editAs="oneCell"><xdr:from><xdr:col>0</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>0</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from><xdr:to><xdr:col>1</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>3</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:to><xdr:pic><xdr:nvPicPr><xdr:cNvPr id="2" name="BCP Official Logo"/><xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr></xdr:nvPicPr><xdr:blipFill><a:blip r:embed="rId1" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill><xdr:spPr><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic><xdr:clientData/></xdr:twoCellAnchor></xdr:wsDr>');
    } finally { $zip->close(); }
    return $temp;
}
