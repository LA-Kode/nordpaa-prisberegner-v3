<?php
class NP_FPDF {
    protected $pages = array();
    protected $wPt = 595.28; protected $hPt = 841.89;
    function AddPage(){ $this->pages[] = ""; }
    function Write($h,$txt){ $this->pages[count($this->pages)-1] .= $txt."\n"; }
    function Output($dest,$name){
        $content = "%PDF-1.3\n%âãÏÓ\n";
        $objects = array();
        $objects[] = "1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj";
        $objects[] = "2 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1 >>endobj";
        $stream = "BT /F1 12 Tf 72 770 Td (" . $this->esc("Nordpaa Tilbud") . ") Tj ET\n";
        $y = 740;
        foreach(explode("\n", $this->pages[0]) as $line){
            if($line==='') continue;
            $stream .= "BT /F1 11 Tf 72 ".$y." Td (".$this->esc($line).") Tj ET\n";
            $y -= 16;
        }
        $len = strlen($stream);
        $objects[] = "4 0 obj<< /Length $len >>stream\n$stream\nendstream\nendobj";
        $objects[] = "3 0 obj<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /MediaBox [0 0 ".$this->wPt." ".$this->hPt."] /Contents 4 0 R >>endobj";
        $objects[] = "5 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>endobj";
        $ofs = array();
        foreach($objects as $o){ $ofs[] = strlen($content); $content .= $o."\n"; }
        $xref = strlen($content);
        $content .= "xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
        foreach($ofs as $o){ $content .= sprintf("%010d 00000 n \n", $o); }
        $content .= "trailer<< /Size ".(count($objects)+1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
        if($dest==='F'){ file_put_contents($name, $content); return $name; }
        header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="'.$name.'"'); echo $content; exit;
    }
    protected function esc($s){ return str_replace(array("\\","(",")","\r","\n"), array("\\\\","\(","\)","",""), $s); }
}
