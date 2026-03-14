<?php

namespace App\Exports;

use App\Models\Recipe;
use App\Models\LaborCost;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class RecipeExport
{
    const NAVY     = 'FF041930';
    const GOLD     = 'FFC9913A';
    const GOLD_LT  = 'FFF5E6CF';
    const WHITE    = 'FFFFFFFF';
    const GREY1    = 'FFF2F2F2';
    const GREY2    = 'FFE8E8E8';
    const GREY_BRD = 'FFCCCCCC';
    const GREEN_BG = 'FFD6F0D6';
    const GREEN_TX = 'FF1A5E1A';
    const RED_BG   = 'FFFDECEA';
    const RED_TX   = 'FFC0392B';
    const DARK     = 'FF1A1A2E';
    const MID_GREY = 'FF888888';

    public function __construct(
        protected Recipe $recipe,
        protected float  $multiplier = 1.0
    ) {}

    public function download()
    {
        $spreadsheet = $this->build();
        $filename    = 'recipe-' . $this->recipe->id . '-'
                     . str($this->recipe->recipe_name)->slug() . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function build(): Spreadsheet
    {
        $recipe = $this->recipe->loadMissing(['ingredients.ingredient']);
        $mult   = max($this->multiplier, 0.01);

        $unitSell     = $recipe->sell_mode === 'piece'
            ? (float) $recipe->selling_price_per_piece
            : (float) $recipe->selling_price_per_kg;
        $vatPct       = (float) ($recipe->vat_percent ?? $recipe->vat ?? 0);
        $batchIngCost = (float) $recipe->ingredients_cost_per_batch;

        $user     = Auth::user();
        $rootId   = $user->created_by ?: $user->id;
        $laborRec = LaborCost::where('user_id', $rootId)->first();
        $rate     = $recipe->labor_cost_mode === 'external'
            ? (float) ($laborRec->external_cost_per_min ?? 0)
            : (float) ($laborRec->shop_cost_per_min     ?? 0);
        $batchLabCost = round($recipe->labour_time_min * $rate, 2);

        if ($recipe->sell_mode === 'piece') {
            $pcs         = max(1, (int) ($recipe->total_pieces ?? 1));
            $unitIngCost = $batchIngCost / $pcs;
            $unitLabCost = $batchLabCost / $pcs;
        } else {
            $wg          = $recipe->recipe_weight ?: $recipe->ingredients->sum('quantity_g');
            $kg          = max($wg / 1000, 0.001);
            $unitIngCost = $batchIngCost / $kg;
            $unitLabCost = $batchLabCost / $kg;
        }

        $unitTotal  = (float) $recipe->total_expense;
        $adjLabUnit = $unitLabCost / $mult;
        $adjTotal   = ($unitTotal - $unitLabCost) + $adjLabUnit;
        $grossPrice = $unitSell;
        $netPrice   = $vatPct > 0 ? $grossPrice / (1 + $vatPct / 100) : $grossPrice;

        $ingPct    = $netPrice > 0 ? round($unitIngCost * 100 / $netPrice, 2) : 0;
        $labPct    = $netPrice > 0 ? round($adjLabUnit  * 100 / $netPrice, 2) : 0;
        $totalPct  = $netPrice > 0 ? round($adjTotal    * 100 / $netPrice, 2) : 0;
        $margin    = $netPrice - $adjTotal;
        $marginPct = $netPrice > 0 ? round($margin * 100 / $netPrice, 2) : 0;

        $ingRows      = $recipe->ingredients->map(fn ($ri) => [
            'name'  => $ri->ingredient->ingredient_name,
            'qty_g' => round((float) $ri->quantity_g * $mult, 2),
            'cost'  => round(($ri->quantity_g / 1000) * (float) ($ri->ingredient->price_per_kg ?? 0) * $mult, 2),
        ]);
        $totalQty     = round($ingRows->sum('qty_g'), 2);
        $totalIngCost = round($ingRows->sum('cost'),  2);
        $adjBatchLab  = round($batchLabCost / $mult, 2);
        $grandTotal   = round($totalIngCost + $adjBatchLab, 2);
        $reportDate   = now()->format('d F Y');

        $sp  = new Spreadsheet();
        $ws1 = $sp->getActiveSheet();
        $ws1->setTitle('Summary');
        $ws1->setShowGridLines(false);

        foreach (['A'=>3,'B'=>24,'C'=>18,'D'=>18,'E'=>18,'F'=>3] as $col=>$w) {
            $ws1->getColumnDimension($col)->setWidth($w);
        }

        // ── Summary sheet ────────────────────────────────────────────
        $r = 1;
        $ws1->getRowDimension($r)->setRowHeight(6); $r++;
        $this->colorBand($ws1, $r, 1, 6, self::NAVY, 8); $r++;

        $ws1->mergeCells("B{$r}:F{$r}");
        $ws1->setCellValue("B{$r}", 'RECIPE COST SHEET  ·  '.strtoupper($recipe->recipe_name));
        $this->s($ws1,"B{$r}:F{$r}",self::NAVY,self::WHITE,18,true,false,'center');
        $ws1->getRowDimension($r)->setRowHeight(46); $r++;

        $ws1->mergeCells("B{$r}:F{$r}");
        $ws1->setCellValue("B{$r}", "Confidential  ·  Prepared {$reportDate}  ·  BakeryPro");
        $this->s($ws1,"B{$r}:F{$r}",self::NAVY,self::GOLD,9,false,true,'center');
        $ws1->getRowDimension($r)->setRowHeight(18); $r++;

        $this->colorBand($ws1,$r,1,6,self::GOLD,4); $r++;
        $ws1->getRowDimension($r)->setRowHeight(12); $r++;

        $r = $this->secHdr($ws1,$r,'RECIPE INFORMATION',2,5);
        foreach ([
            ['Recipe Name', $recipe->recipe_name,                                       true],
            ['Sale Mode',   $recipe->sell_mode==='piece' ? 'Per Piece' : 'Per Kg',      false],
            ['Multiplier',  '× '.number_format($mult,2),                                true],
            ['VAT Rate',    number_format($vatPct,2).'%',                                false],
            ['Report Date', $reportDate,                                                 true],
        ] as [$lbl,$val,$shade]) {
            $bg = $shade ? self::GREY1 : self::WHITE;
            $this->fillRow($ws1,$r,2,5,$bg);
            $ws1->setCellValue("B{$r}",$lbl);
            $this->s($ws1,"B{$r}",$bg,self::DARK,10,true,false,'left',1);
            $ws1->mergeCells("C{$r}:E{$r}");
            $ws1->setCellValue("C{$r}",$val);
            $this->s($ws1,"C{$r}",$bg,self::DARK,10,false,false,'left',1);
            $ws1->getRowDimension($r)->setRowHeight(20); $r++;
        }
        $ws1->getRowDimension($r)->setRowHeight(12); $r++;

        $r = $this->secHdr($ws1,$r,'KEY PERFORMANCE INDICATORS',2,5);

        foreach ([[2,'KPI'],[3,'Value (€)'],[4,'% of Price'],[5,'']] as [$col,$h]) {
            $cell = Coordinate::stringFromColumnIndex($col).$r;
            $ws1->setCellValue($cell,$h);
            $this->s($ws1,$cell,self::NAVY,self::WHITE,9,true,false,'center');
        }
        $ws1->getRowDimension($r)->setRowHeight(22); $r++;

        foreach ([
            ['Selling Price',   $grossPrice,  1.0,              false,false],
            ['Ingredient Cost', $unitIngCost, $ingPct/100,      true, false],
            ['Labour Cost',     $adjLabUnit,  $labPct/100,      false,false],
            ['Total Cost',      $adjTotal,    $totalPct/100,    true, false],
            ['Net Margin',      $margin,      $marginPct/100,   false,true ],
        ] as [$lbl,$val,$pct,$shade,$isMgn]) {
            $bg = $isMgn ? ($margin>=0 ? self::GREEN_BG : self::RED_BG)
                         : ($shade ? self::GREY1 : self::WHITE);
            $tc = $isMgn ? ($margin>=0 ? self::GREEN_TX : self::RED_TX) : self::DARK;
            $this->fillRow($ws1,$r,2,5,$bg);
            $ws1->setCellValue("B{$r}",$lbl);
            $this->s($ws1,"B{$r}",$bg,$tc,10,$isMgn,false,'left',1);
            $ws1->setCellValue("C{$r}",$val);
            $this->s($ws1,"C{$r}",$bg,$tc,10,$isMgn,false,'right');
            $ws1->getStyle("C{$r}")->getNumberFormat()->setFormatCode('€ #,##0.00');
            $ws1->setCellValue("D{$r}",$pct);
            $this->s($ws1,"D{$r}",$bg,$tc,10,$isMgn,false,'right');
            $ws1->getStyle("D{$r}")->getNumberFormat()->setFormatCode('0.00%');
            $ws1->getStyle("E{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($bg);
            $ws1->getStyle("E{$r}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB(self::GREY_BRD);
            $ws1->getRowDimension($r)->setRowHeight(22); $r++;
        }

        $ws1->getRowDimension($r)->setRowHeight(12); $r++;
        $ws1->mergeCells("B{$r}:E{$r}");
        $ws1->setCellValue("B{$r}","  See 'Ingredients' sheet for the full ingredient breakdown");
        $this->s($ws1,"B{$r}:E{$r}",self::GOLD_LT,self::DARK,9,false,true,'left',1);
        $ws1->getRowDimension($r)->setRowHeight(20); $r++;
        $ws1->getRowDimension($r)->setRowHeight(14); $r++;
        $this->colorBand($ws1,$r,1,6,self::GREY1,4); $r++;
        $ws1->mergeCells("B{$r}:F{$r}");
        $ws1->setCellValue("B{$r}","This document is confidential. © ".now()->year." BakeryPro · All Rights Reserved");
        $this->s($ws1,"B{$r}:F{$r}",self::NAVY,self::MID_GREY,8,false,true,'center');
        $ws1->getRowDimension($r)->setRowHeight(20);
        $ws1->freezePane('B7');
        $this->page($ws1);

        // ── Ingredients sheet ────────────────────────────────────────
        $ws2 = $sp->createSheet();
        $ws2->setTitle('Ingredients');
        $ws2->setShowGridLines(false);
        foreach (['A'=>3,'B'=>7,'C'=>30,'D'=>18,'E'=>18,'F'=>3] as $col=>$w) {
            $ws2->getColumnDimension($col)->setWidth($w);
        }

        $r2=1; $ws2->getRowDimension($r2)->setRowHeight(6); $r2++;
        $this->colorBand($ws2,$r2,1,6,self::NAVY,8); $r2++;

        $ws2->mergeCells("A{$r2}:F{$r2}");
        $ws2->setCellValue("A{$r2}",'INGREDIENT BREAKDOWN  ·  '.strtoupper($recipe->recipe_name));
        $this->s($ws2,"A{$r2}:F{$r2}",self::NAVY,self::WHITE,16,true,false,'center');
        $ws2->getRowDimension($r2)->setRowHeight(42); $r2++;

        $ws2->mergeCells("A{$r2}:F{$r2}");
        $ws2->setCellValue("A{$r2}","Multiplier: × {$mult}  ·  {$reportDate}");
        $this->s($ws2,"A{$r2}:F{$r2}",self::NAVY,self::GOLD,9,false,true,'center');
        $ws2->getRowDimension($r2)->setRowHeight(18); $r2++;

        $this->colorBand($ws2,$r2,1,6,self::GOLD,4); $r2++;
        $ws2->getRowDimension($r2)->setRowHeight(12); $r2++;
        $r2 = $this->secHdr($ws2,$r2,'INGREDIENTS LIST',2,5);

        foreach ([[2,'#'],[3,'Ingredient Name'],[4,'Quantity (g)'],[5,'Total Cost (€)']] as [$col,$h]) {
            $cell = Coordinate::stringFromColumnIndex($col).$r2;
            $ws2->setCellValue($cell,$h);
            $this->s($ws2,$cell,self::GOLD_LT,self::NAVY,9,true,false,'center');
            $ws2->getStyle($cell)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setARGB(self::GOLD);
        }
        $ws2->getRowDimension($r2)->setRowHeight(22); $r2++;

        foreach ($ingRows as $idx=>$ing) {
            $bg = $idx%2===0 ? self::GREY1 : self::WHITE;
            $this->fillRow($ws2,$r2,2,5,$bg);
            $ws2->setCellValue("B{$r2}",$idx+1);
            $this->s($ws2,"B{$r2}",$bg,self::MID_GREY,9,false,false,'center');
            $ws2->setCellValue("C{$r2}",$ing['name']);
            $this->s($ws2,"C{$r2}",$bg,self::DARK,10,false,false,'left',1);
            $ws2->setCellValue("D{$r2}",$ing['qty_g']);
            $this->s($ws2,"D{$r2}",$bg,self::DARK,10,false,false,'right');
            $ws2->getStyle("D{$r2}")->getNumberFormat()->setFormatCode('#,##0.00');
            $ws2->setCellValue("E{$r2}",$ing['cost']);
            $this->s($ws2,"E{$r2}",$bg,self::DARK,10,false,false,'right');
            $ws2->getStyle("E{$r2}")->getNumberFormat()->setFormatCode('€ #,##0.00');
            $ws2->getRowDimension($r2)->setRowHeight(21); $r2++;
        }

        foreach ([
            ['Total Ingredients',$totalQty,  $totalIngCost,self::GOLD_LT,false],
            ['Labour Cost',       null,       $adjBatchLab, self::GREY2,  false],
            ['GRAND TOTAL',       null,       $grandTotal,  self::GREEN_BG,true],
        ] as [$lbl,$qty,$cost,$bg,$bold]) {
            $ws2->mergeCells("B{$r2}:C{$r2}");
            $ws2->setCellValue("B{$r2}",$lbl);
            $this->s($ws2,"B{$r2}:C{$r2}",$bg,self::DARK,10,$bold,false,'left',1);
            $ws2->getStyle("B{$r2}:C{$r2}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setARGB(self::GOLD);
            if ($qty!==null) {
                $ws2->setCellValue("D{$r2}",$qty);
                $this->s($ws2,"D{$r2}",$bg,self::DARK,10,$bold,false,'right');
                $ws2->getStyle("D{$r2}")->getNumberFormat()->setFormatCode('#,##0.00');
            } else {
                $ws2->getStyle("D{$r2}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($bg);
            }
            $ws2->getStyle("D{$r2}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setARGB(self::GOLD);
            $ws2->setCellValue("E{$r2}",$cost);
            $this->s($ws2,"E{$r2}",$bg,self::DARK,10,$bold,false,'right');
            $ws2->getStyle("E{$r2}")->getNumberFormat()->setFormatCode('€ #,##0.00');
            $ws2->getStyle("E{$r2}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setARGB(self::GOLD);
            $ws2->getRowDimension($r2)->setRowHeight(22); $r2++;
        }

        $ws2->getRowDimension($r2)->setRowHeight(14); $r2++;
        $this->colorBand($ws2,$r2,1,6,self::GREY1,4); $r2++;
        $ws2->mergeCells("A{$r2}:F{$r2}");
        $ws2->setCellValue("A{$r2}","© ".now()->year." BakeryPro · Confidential · All Rights Reserved");
        $this->s($ws2,"A{$r2}:F{$r2}",self::NAVY,self::MID_GREY,8,false,true,'center');
        $ws2->getRowDimension($r2)->setRowHeight(20);
        $ws2->freezePane('B9');
        $this->page($ws2);

        $sp->setActiveSheetIndex(0);
        return $sp;
    }

    // Helpers
    private function s($ws,string $range,string $bg,string $color,int $sz=10,
        bool $bold=false,bool $italic=false,string $h='left',int $indent=0): void
    {
        $hMap=['left'=>Alignment::HORIZONTAL_LEFT,'center'=>Alignment::HORIZONTAL_CENTER,'right'=>Alignment::HORIZONTAL_RIGHT];
        $ws->getStyle($range)->applyFromArray([
            'font'      =>['name'=>'Calibri','bold'=>$bold,'italic'=>$italic,'size'=>$sz,'color'=>['argb'=>$color]],
            'fill'      =>['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$bg]],
            'alignment' =>['horizontal'=>$hMap[$h]??$hMap['left'],'vertical'=>Alignment::VERTICAL_CENTER,'indent'=>$indent],
            'borders'   =>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['argb'=>self::GREY_BRD]]],
        ]);
    }

    private function fillRow($ws,int $row,int $c1,int $c2,string $color): void
    {
        for ($c=$c1;$c<=$c2;$c++) {
            $cell=Coordinate::stringFromColumnIndex($c).$row;
            $ws->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($color);
            $ws->getStyle($cell)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB(self::GREY_BRD);
        }
    }

    private function colorBand($ws,int $r,int $c1,int $c2,string $color,int $h): void
    {
        for ($c=$c1;$c<=$c2;$c++) {
            $ws->getCell(Coordinate::stringFromColumnIndex($c).$r)->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($color);
        }
        $ws->getRowDimension($r)->setRowHeight($h);
    }

    private function secHdr($ws,int $row,string $label,int $c1,int $c2): int
    {
        $ws->mergeCells(Coordinate::stringFromColumnIndex($c1).$row.':'.Coordinate::stringFromColumnIndex($c2).$row);
        $cell=Coordinate::stringFromColumnIndex($c1).$row;
        $ws->setCellValue($cell,"  {$label}");
        $this->s($ws,$cell,self::GOLD,self::WHITE,9,true,false,'left',1);
        $ws->getRowDimension($row)->setRowHeight(20);
        return $row+1;
    }

    private function page($ws): void
    {
        $ws->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);
        $ws->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
        $ws->getPageSetup()->setFitToPage(true);
        $ws->getPageSetup()->setFitToWidth(1);
        $ws->getPageSetup()->setFitToHeight(0);
        $ws->getPageMargins()->setLeft(0.4)->setRight(0.4)->setTop(0.6)->setBottom(0.6);
    }
}