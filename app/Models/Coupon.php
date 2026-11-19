<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'description',
        'discount_type',
        'discount_value',
        'min_order_amount',
        'max_discount',
        'max_uses',
        'used_count',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'discount_value' => 'float',
        'min_order_amount' => 'float',
        'max_discount' => 'float',
        'max_uses' => 'integer',
        'used_count' => 'integer',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class, 'coupon_id');
    }

    /**
     * Kiểm tra coupon có hợp lệ cho đơn hàng và user không.
     */
    public function isValidForOrder(float $orderTotal, ?int $userId = null): array
    {
        if (!$this->is_active) {
            return ['valid' => false, 'message' => 'Mã giảm giá này hiện đang tạm dừng sử dụng.'];
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return ['valid' => false, 'message' => 'Mã giảm giá đã hết hạn sử dụng.'];
        }

        if ($this->max_uses > 0 && $this->used_count >= $this->max_uses) {
            return ['valid' => false, 'message' => 'Mã giảm giá đã hết lượt sử dụng.'];
        }

        if ($this->min_order_amount > 0 && $orderTotal < $this->min_order_amount) {
            $formattedMin = number_format($this->min_order_amount, 0, ',', '.') . 'đ';
            return ['valid' => false, 'message' => "Mã chỉ áp dụng cho đơn hàng từ {$formattedMin} trở lên."];
        }

        if ($userId) {
            $alreadyUsed = $this->usages()->where('user_id', $userId)->exists();
            if ($alreadyUsed) {
                return ['valid' => false, 'message' => 'Bạn đã sử dụng mã giảm giá này rồi.'];
            }
        }

        // Tính toán số tiền được giảm
        $discountAmount = 0.0;
        if ($this->discount_type === 'percent') {
            $discountAmount = ($orderTotal * $this->discount_value) / 100.0;
            if ($this->max_discount > 0 && $discountAmount > $this->max_discount) {
                $discountAmount = $this->max_discount;
            }
        } else {
            // fixed
            $discountAmount = $this->discount_value;
        }

        // Không giảm quá tổng giá trị đơn hàng
        $discountAmount = min($orderTotal, max(0, $discountAmount));

        return [
            'valid' => true,
            'discount_amount' => $discountAmount,
            'final_total' => max(0, $orderTotal - $discountAmount),
            'coupon' => $this,
        ];
    }
}
