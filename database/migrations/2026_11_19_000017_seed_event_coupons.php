<?php

use App\Models\Coupon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('coupons')) {
            // 1. Mã open2027: Giảm 30% all sản phẩm mua bằng tiền, giới hạn 10 người dùng
            Coupon::updateOrCreate(
                ['code' => 'OPEN2027'],
                [
                    'description' => 'Ưu đãi Khai Mở Server: Giảm 30% toàn bộ sản phẩm mua bằng VNĐ (Giới hạn 10 người)',
                    'discount_type' => 'percent',
                    'discount_value' => 30.00,
                    'min_order_amount' => 0.00,
                    'max_discount' => 0.00, // Không giới hạn mức giảm tối đa
                    'max_uses' => 10,
                    'used_count' => 0,
                    'expires_at' => null,
                    'is_active' => true,
                ]
            );

            // 2. Mã Newyear2027: Giảm 10.000 VNĐ, giới hạn 50 người dùng
            Coupon::updateOrCreate(
                ['code' => 'NEWYEAR2027'],
                [
                    'description' => 'Mừng Năm Mới 2027: Giảm ngay 10.000đ trực tiếp vào đơn hàng (Giới hạn 50 người)',
                    'discount_type' => 'fixed',
                    'discount_value' => 10000.00,
                    'min_order_amount' => 0.00,
                    'max_discount' => 0.00,
                    'max_uses' => 50,
                    'used_count' => 0,
                    'expires_at' => null,
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('coupons')) {
            Coupon::whereIn('code', ['OPEN2027', 'NEWYEAR2027'])->delete();
        }
    }
};
