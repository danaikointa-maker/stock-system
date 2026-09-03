<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = ['phone', 'name', 'line_user_id', 'points_balance', 'tier', 'referred_by_node_id', 'status'];
    protected $casts = ['points_balance' => 'integer'];

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class);
    }

    /** การแลกของรางวัลระบบเดิม */
    public function rewardRedemptions(): HasMany
    {
        return $this->hasMany(RewardRedemption::class);
    }

    /** การแลกแต้มระบบใหม่ (v3) — ใช้ในหน้าลูกค้า */
    public function redemptions(): HasMany
    {
        return $this->hasMany(PointRedemption::class);
    }

    /** ประวัติการสแกน QR ทั้งหมด */
    public function scanLogs(): HasMany
    {
        return $this->hasMany(QrScanLog::class);
    }

    /** กระเป๋าแต้มแยกตามร้านผู้ออก (ระบบ wallet v3) */
    public function wallets(): HasMany
    {
        return $this->hasMany(CustomerPointWallet::class);
    }

    public function referredByNode(): BelongsTo
    {
        return $this->belongsTo(OrgNode::class, 'referred_by_node_id');
    }

    public function isBlocked(): bool
    {
        return $this->status === 'blocked';
    }

    /** กระทบยอดจาก ledger (ใช้ใน job รายวัน) */
    public function recalculatedBalance(): int
    {
        return (int) $this->pointTransactions()->sum('points');
    }
}
