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
        Schema::create('server_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('player_name');
            $table->unsignedTinyInteger('rating')->default(5);
            $table->text('comment');
            $table->string('tag')->default('Cư dân từ Season 1');
            $table->boolean('is_approved')->default(true);
            $table->timestamps();
        });

        // Seed default initial community reviews
        \Illuminate\Support\Facades\DB::table('server_reviews')->insert([
            [
                'user_id' => null,
                'player_name' => 'MinhKhoi_VN',
                'rating' => 5,
                'comment' => 'Đảo Tò Mò là server Minecraft Việt sinh tồn chill nhất mình từng chơi. Admin hỗ trợ nhiệt tình, cộng đồng thân thiện và hệ thống kinh tế rất vui nhộn!',
                'tag' => 'Cư dân từ Season 1',
                'is_approved' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => null,
                'player_name' => 'SkyCraft99',
                'rating' => 5,
                'comment' => 'Hệ thống bảo vệ Land rất an toàn, không lo bị grief đồ. Cửa hàng web đặt mua đồ rất tiện, nạp QR là có code nhận đồ ngay trong game!',
                'tag' => 'Cư dân từ Season 1',
                'is_approved' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => null,
                'player_name' => 'ThaoNguyen_MC',
                'rating' => 5,
                'comment' => 'Voice Discord mỗi tối vui cực kỳ, tuần nào cũng có event build và săn boss rôm rả. 10/10 điểm cho server!',
                'tag' => 'Cư dân từ Season 1',
                'is_approved' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_reviews');
    }
};
