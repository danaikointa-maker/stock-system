<?php

namespace App\Models;

use App\Enums\OrgLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * โหนดในสายงาน: เจ้าของระบบ -> คลังใหญ่ -> คลังย่อย -> ตัวแทนขาย -> ร้านค้า -> ผู้ขาย
 *
 * ใช้ adjacency list (parent_id) + materialized path (path = '/1/2/3/')
 * path ถูกเซ็ตอัตโนมัติโดย trigger `trg_org_nodes_bi` ตอน INSERT
 */
class OrgNode extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'parent_id', 'level_id', 'code', 'name', 'phone', 'address',
        'lat', 'lng', 'credit_limit', 'status',
        'email', 'line_id', 'opening_hours', 'photos', 'notes', 'shop_type',
        'show_on_map', 'map_cover_photo', 'map_description',
    ];

    protected $casts = [
        'level_id'     => OrgLevel::class,
        'credit_limit' => 'decimal:2',
        'depth'        => 'integer',
        'photos'       => 'array',
    ];

    /**
     * คำนวณ path/depth และตรวจความถูกต้องของลำดับชั้นในชั้น Application
     * (บน MySQL ยังมี trigger `trg_org_nodes_bi` เป็นด่านสุดท้ายกันข้อมูลเสียจาก raw SQL)
     */
    protected static function booted(): void
    {
        static::creating(function (self $node) {
            $level = $node->level_id instanceof OrgLevel
                ? $node->level_id
                : OrgLevel::from((int) $node->level_id);

            if (! $node->parent_id) {
                if ($level !== OrgLevel::SystemOwner) {
                    throw new \DomainException('เฉพาะเจ้าของระบบเท่านั้นที่ไม่มีหน่วยงานต้นสังกัด');
                }

                $node->path = '/';
                $node->depth = 0;

                return;
            }

            $parent = static::findOrFail($node->parent_id);

            if ($parent->level_id->value + 1 !== $level->value) {
                throw new \DomainException(
                    "ระดับชั้นไม่ถูกต้อง: {$parent->level_id->label()} ต้องมีลูกเป็น "
                    . ($parent->level_id->child()?->label() ?? 'ไม่มี')
                    . " แต่ได้รับ {$level->label()}"
                );
            }

            $node->path = $parent->path . $parent->id . '/';
            $node->depth = $parent->depth + 1;
        });
    }

    // ---------------- Relations ----------------

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function stockBalances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    // ---------------- Hierarchy helpers ----------------

    /** path ของ "ลูกๆ" ของโหนดนี้ เช่น '/1/2/' */
    public function childPathPrefix(): string
    {
        return $this->path . $this->id . '/';
    }

    /** id ของบรรพบุรุษทั้งหมด จาก path */
    public function ancestorIds(): array
    {
        return array_map('intval', array_filter(explode('/', $this->path)));
    }

    /** ลูกหลานทุกชั้น (ไม่รวมตัวเอง) */
    public function scopeDescendantsOf(Builder $q, self $node): Builder
    {
        return $q->where('path', 'like', $node->childPathPrefix() . '%');
    }

    /** ตัวเอง + ลูกหลานทุกชั้น — ใช้ทำ data scope ของ user */
    public function scopeSubtreeOf(Builder $q, self $node): Builder
    {
        return $q->where(function (Builder $w) use ($node) {
            $w->where('id', $node->id)
              ->orWhere('path', 'like', $node->childPathPrefix() . '%');
        });
    }

    public function scopeLevel(Builder $q, OrgLevel $level): Builder
    {
        return $q->where('level_id', $level->value);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }

    /** id ของตัวเอง + ลูกหลานทั้งหมด (ใช้ใน whereIn) */
    public function subtreeIds(): array
    {
        // กันกรณีถูก select มาโดยไม่มีคอลัมน์ path ซึ่งจะทำให้คำนวณลูกหลานผิด
        if (! array_key_exists('path', $this->attributes)) {
            return static::whereKey($this->id)->firstOrFail()->subtreeIds();
        }

        return static::subtreeOf($this)->pluck('id')->all();
    }

    public function isAncestorOf(self $other): bool
    {
        return str_starts_with($other->path, $this->childPathPrefix());
    }

    public function isDirectParentOf(self $other): bool
    {
        return $other->parent_id === $this->id;
    }
}
