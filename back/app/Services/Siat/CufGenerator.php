<?php
namespace App\Services\Siat;
class CufGenerator {
    public function generate(string $nit, string $timestamp, int $branch, int $modality, int $emission, int $invoice, int $pos, string $control): string {
        $number=str_pad($nit,13,'0',STR_PAD_LEFT).$timestamp.str_pad((string)$branch,4,'0',STR_PAD_LEFT).$modality.$emission.'1'.'01'.str_pad((string)$invoice,10,'0',STR_PAD_LEFT).str_pad((string)$pos,4,'0',STR_PAD_LEFT);
        $number.=$this->mod11($number); $hex=''; while(bccomp($number,'0')>0){$hex=strtoupper(dechex((int)bcmod($number,'16'))).$hex;$number=bcdiv($number,'16',0);} return ($hex?:'0').$control;
    }
    private function mod11(string $value): int {$sum=0;$mult=2;for($i=strlen($value)-1;$i>=0;$i--){$sum+=$mult*(int)$value[$i];if(++$mult>9)$mult=2;}return $sum%11;}
}
