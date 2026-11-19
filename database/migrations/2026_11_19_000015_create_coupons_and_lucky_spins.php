<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Thêm cột lucky_spins vào bảng users
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'lucky_spins')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedInteger('lucky_spins')->default(0)->after('coins');
            });
        }

        // 2. Tạo bảng coupons
        if (!Schema::hasTable('coupons')) {
            Schema::create('coupons', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('description', 255)->nullable();
                $table->string('discount_type', 20)->default('percent'); // 'percent' hoặc 'fixed'
                $table->decimal('discount_value', 12, 2)->default(0);
                $table->decimal('min_order_amount', 12, 2)->default(0);
                $table->decimal('max_discount', 12, 2)->default(0); // Áp dụng cho loại percent
                $table->unsignedInteger('max_uses')->default(0); // 0 = không giới hạn
                $table->unsignedInteger('used_count')->default(0);
                $table->dateTime('expires_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 3. Tạo bảng coupon_usages (lưu lịch sử người dùng áp dụng mã)
        if (!Schema::hasTable('coupon_usages')) {
            Schema::create('coupon_usages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('coupon_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->decimal('discount_amount', 12, 2)->default(0);
                $table->timestamps();
            });
        }

        // 4. Khởi tạo cấu hình announcement bar mặc định trong app_settings
        if (Schema::hasTable('app_settings')) {
            $now = now();
            $defaults = [
                'announcement_enabled' => '1',
                'announcement_text' => '🎉 Đăng ký tài khoản ngay hôm nay để nhận quà tri ân đặc biệt khi Server chính thức Open Beta!',
                'announcement_link' => '/html/Account.html',
                'announcement_badge' => 'Sự Kiện Open'
            ];

            foreach ($defaults as $k => $v) {
                $exists = DB::table('app_settings')->where('key', $k)->exists();
                if (!$exists) {
                    DB::table('app_settings')->insert([
                        'key' => $k,
                        'value' => $v,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'lucky_spins')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('lucky_spins');
            });
        }
        Schema::dropIfExists('coupon_usages');
        Schema::dropIfExists('coupons');
    }
};
