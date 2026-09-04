<?php

namespace App\Models;

use App\Enums\OrgLevel;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'org_node_id',
        'name',
        'email',
        'phone',
        'password',
        'role',
        'is_active',
        'max_social_links',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'max_social_links' => 'integer',
        'role' => Role::class,
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(OrgNode::class, 'org_node_id');
    }

    // ---------------- บทบาท ----------------

    public function isSystemAdmin(): bool
    {
        return $this->role === Role::SystemAdmin;
    }

    public function hasAbility(string $ability): bool
    {
        return $this->is_active && $this->role->can($ability);
    }

    public function level(): ?OrgLevel
    {
        return $this->node?->level_id;
    }

    // ---------------- ขอบเขตข้อมูล ----------------

    /** id โหนดที่มองเห็นได้ = โหนดตัวเอง + ลูกหลานทุกชั้น (cache ต่อ request) */
    public function visibleNodeIds(): array
    {
        return once(function () {
            if ($this->isSystemAdmin()) {
                return OrgNode::pluck('id')->all();
            }

            return $this->node?->subtreeIds() ?? [];
        });
    }

    /** id โหนดลูกหลาน "ไม่รวมตัวเอง" — ใช้ตอนสร้างหน่วยงาน/สมาชิกใต้สังกัด */
    public function manageableNodeIds(): array
    {
        return $this->visibleNodeIds();
    }

    public function canAccessNode(?int $nodeId): bool
    {
        return $nodeId !== null && in_array($nodeId, $this->visibleNodeIds(), true);
    }

    /**
     * จัดการผู้ใช้คนอื่นได้ไหม
     * เงื่อนไข: ต้องมีสิทธิ์ manage-members + เป้าหมายอยู่ในสายงานตัวเอง + ห้ามจัดการตัวเอง/คนระดับสูงกว่า
     */
    public function canManageUser(self $target): bool
    {
        if (! $this->hasAbility('manage-members') || $this->id === $target->id) {
            return false;
        }

        if ($this->isSystemAdmin()) {
            return true;
        }

        if (! $this->canAccessNode($target->org_node_id)) {
            return false;
        }

        // ห้ามแตะคนที่อยู่ระดับเดียวกันหรือสูงกว่าในโหนดเดียวกัน
        return $target->org_node_id !== $this->org_node_id;
    }

    /** ผู้ใช้ทั้งหมดในสายงานที่ตัวเองดูแล */
    public function scopeInScopeOf(Builder $q, self $user): Builder
    {
        return $q->whereIn('org_node_id', $user->visibleNodeIds());
    }

    public function getRoleLabelAttribute(): string
    {
        return $this->role->label();
    }
}
