<?php

namespace App\Console\Commands;

use App\Models\DoctorDeal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RecalculateDealsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deals:recalculate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate doctor deal invoice contributions and achieved amounts';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting recalculation of doctor deal achievements...');
        $log = [];
        $totalFixed = 0;

        DB::beginTransaction();
        try {
            $deals = DoctorDeal::with(['invoices.details', 'drugs'])->get();

            foreach ($deals as $deal) {
                $dealDrugIds = $deal->drugs->pluck('id')->toArray();
                $isGeneralDeal = empty($dealDrugIds);
                $dealDifferenceTotal = 0;

                foreach ($deal->invoices as $invoice) {
                    $correctContribution = 0;

                    foreach ($invoice->details as $detail) {
                        if ($isGeneralDeal || in_array($detail->drug_id, $dealDrugIds)) {
                            $correctContribution += ($detail->quantity * $detail->unit_price);
                        }
                    }

                    $oldContribution = $invoice->pivot->contribution_amount;

                    if (round($correctContribution, 2) != round($oldContribution, 2)) {
                        $deal->invoices()->updateExistingPivot($invoice->id, [
                            'contribution_amount' => $correctContribution,
                        ]);

                        $difference = $correctContribution - $oldContribution;
                        $dealDifferenceTotal += $difference;

                        $log[] = "Deal ID: {$deal->id} | Invoice #{$invoice->serial_number} | Old: {$oldContribution} | New: {$correctContribution} | Diff: {$difference}";
                        $totalFixed++;
                    }
                }

                if ($dealDifferenceTotal != 0) {
                    $deal->increment('achieved_amount', $dealDifferenceTotal);
                }
            }

            DB::commit();

            $logContent = "=== سجل الفواتير التي تم إصلاحها ===\n";
            $logContent .= "إجمالي الفواتير المعدلة: {$totalFixed}\n\n";
            $logContent .= implode("\n", $log);

            Storage::put('fixed_invoices.txt', $logContent);

            $this->info("Completed successfully. Fixed {$totalFixed} invoice contributions.");
            $this->info('Log written to storage/app/fixed_invoices.txt');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Recalculation failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
