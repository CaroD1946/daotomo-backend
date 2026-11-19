<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders', 'coupon_code')) {
                    $table->string('coupon_code', 50)->nullable()->after('total_amount');
                }
                if (!Schema::hasColumn('orders', 'discount_amount')) {
                    $table->decimal('discount_amount', 12, 2)->default(0)->after('coupon_code');
                }
                if (!Schema::hasColumn('orders', 'original_amount')) {
                    $table->decimal('original_amount', 12, 2)->nullable()->after('discount_amount');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (Schema::hasColumn('orders', 'original_amount')) {
                    $table->dropColumn('original_amount');
                }
                if (Schema::hasColumn('orders', 'discount_amount')) {
                    $table->dropColumn('discount_amount');
                }
                if (Schema::hasColumn('orders', 'coupon_code')) {
                    $table->dropColumn('coupon_code');
                }
            });
        }
    }
};
