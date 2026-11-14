<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\DoctorDeal;
use App\Models\Zone;
use App\Models\ZoneExpense;

class DoctorCommissionService
{
    /**
     * Calculate the earned commission for a doctor deal based on unified business rules.
     * If target_amount > 0: fixed-target deal => commission_amount.
     * If target_amount <= 0: open-ended percentage deal => achieved_amount * commission_percentage / 100.
     */
    public function getDealCommission(DoctorDeal $deal): float
    {
        if ((float) $deal->target_amount > 0) {
            return (float) $deal->commission_amount;
        }

        return round((float) $deal->achieved_amount * ((float) $deal->commission_percentage / 100), 2);
    }

    /**
     * Calculate remaining unpaid commission on a deal.
     */
    public function getDealRemaining(DoctorDeal $deal): float
    {
        $commission = $this->getDealCommission($deal);

        return max(0, round($commission - (float) $deal->paid_amount, 2));
    }

    /**
     * Calculate consolidated financial balance for a doctor across non-archived deals.
     *
     * @return array{total_earned: float, total_paid: float, net_balance: float, status: string}
     */
    public function getDoctorBalance(Doctor $doctor): array
    {
        $doctor->loadMissing(['deals' => function ($q) {
            $q->where('is_archived', false);
        }]);

        $totalEarned = 0;
        $totalPaid = 0;

        foreach ($doctor->deals as $deal) {
            $totalEarned += $this->getDealCommission($deal);
            $totalPaid += (float) $deal->paid_amount;
        }

        $totalEarned = round($totalEarned, 2);
        $totalPaid = round($totalPaid, 2);
        $net = round($totalEarned - $totalPaid, 2);

        $status = 'خالص';
        if ($net > 0) {
            $status = 'له (دائن)';
        } elseif ($net < 0) {
            $status = 'عليه (مدين)';
        }

        return [
            'total_earned' => $totalEarned,
            'total_paid' => $totalPaid,
            'net_balance' => $net,
            'status' => $status,
        ];
    }

    /**
     * Record a commission payment as a ZoneExpense in the doctor's assigned zone.
     */
    public function recordDealExpense(DoctorDeal $deal, float $amount): ?ZoneExpense
    {
        if ($amount <= 0) {
            return null;
        }

        $doctor = $deal->doctor ?: Doctor::find($deal->doctor_id);
        if (! $doctor || ! $doctor->center_id) {
            return null;
        }

        $line = 1;
        $firstDrug = $deal->drugs()->first();
        if ($firstDrug) {
            $line = $firstDrug->line;
        }

        $zone = Zone::where('line', $line)->whereHas('centers', function ($q) use ($doctor) {
            $q->where('centers.id', $doctor->center_id);
        })->first();

        if (! $zone) {
            $zone = Zone::whereHas('centers', function ($q) use ($doctor) {
                $q->where('centers.id', $doctor->center_id);
            })->first();
        }

        if ($zone) {
            return ZoneExpense::create([
                'zone_id' => $zone->id,
                'amount' => $amount,
                'description' => 'دفعة من اتفاق مالي (عمولة) للطبيب: '.$doctor->name,
                'expense_date' => now()->format('Y-m-d'),
            ]);
        }

        return null;
    }

    /**
     * Settle a doctor deal, update its payment status, and post the corresponding zone expense.
     */
    public function settleDeal(DoctorDeal $deal): float
    {
        $targetCommission = $this->getDealCommission($deal);
        $diff = max(0, round($targetCommission - (float) $deal->paid_amount, 2));

        $deal->update([
            'is_paid' => true,
            'status' => 1,
            'paid_amount' => $targetCommission,
        ]);

        if ($diff > 0) {
            $this->recordDealExpense($deal, $diff);
        }

        return $diff;
    }
}
