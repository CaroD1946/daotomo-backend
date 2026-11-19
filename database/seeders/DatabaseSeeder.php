<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Product;
use App\Models\SupportTicket;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Admin User (firstOrCreate ensures existing passwords/coins are never overwritten on redeploy)
        User::firstOrCreate(
            ['email' => 'admin@daotomo.site'],
            [
                'username' => 'admin',
                'name' => 'Admin Đảo Tò Mò',
                'password' => Hash::make(env('SEED_ADMIN_PASSWORD', 'Admin@123456')),
                'role' => 'admin',
                'minecraft_username' => 'AdminDaoTomo',
                'skin_url' => '/img/steve.png',
                'coins' => 99999,
            ]
        );

        // 2. Seed Test Player
        User::firstOrCreate(
            ['email' => 'player@daotomo.site'],
            [
                'username' => 'DragonPlayer',
                'name' => 'Dragon Player',
                'password' => Hash::make(env('SEED_PLAYER_PASSWORD', 'Player@123456')),
                'role' => 'user',
                'minecraft_username' => 'TestDummyBot',
                'minecraft_uuid' => '00000000-0000-0000-0000-000000000001',
                'game_linked_at' => now(),
                'skin_url' => '/img/steve.png',
                'coins' => 500,
            ]
        );

        // 3. Seed Demo User
        User::firstOrCreate(
            ['email' => 'user@daotomo.site'],
            [
                'username' => 'demouser',
                'name' => 'Demo User',
                'password' => Hash::make(env('SEED_DEMO_PASSWORD', 'User@123456')),
                'role' => 'user',
                'minecraft_username' => 'DemoUser',
                'skin_url' => '/img/steve.png',
                'coins' => 100,
            ]
        );

        // 4. Seed the Original 12 Products from seed.js
        $img = function ($text) {
            return 'https://placehold.co/800x800/png?text=' . urlencode($text);
        };
        $gallery = function ($text) use ($img) {
            return [$img($text . ' - 1'), $img($text . ' - 2')];
        };

        $products = [
            // ── 5 GÓI RANK VIP (Theo báo cáo & đề xuất bảng giá mới) ──
            [
                'name' => 'Rank Knight',
                'slug' => 'rank-knight',
                'short_description' => 'Rank khởi đầu dành cho tân thủ Đảo Tò Mò với bộ đặc quyền tiện ích.',
                'description' => 'Đặc quyền: Quyền bay /fly tại khu xây dựng, màu chat Đồng, 3 Set đồ khởi đầu tiện ích.',
                'price' => 49000,
                'original_price' => 69000,
                'image' => '/img/ranks/rank-knight.jpg',
                'images' => ['/img/ranks/rank-knight.jpg'],
                'category' => 'vip',
                'in_stock' => true,
                'stock_quantity' => 999,
                'is_active' => true,
                'featured' => true,
                'sort_order' => 1,
                'game_reward' => 'rank_knight',
            ],
            [
                'name' => 'Rank Legend',
                'slug' => 'rank-legend',
                'short_description' => 'Rank Huyền Thoại với hào quang lửa rực rỡ và 10 Chìa khóa Epic.',
                'description' => 'Đặc quyền: Hào quang Aura hạt lửa, 3 Home dịch chuyển, nhận 10 Chìa khóa Epic mở hòm báu.',
                'price' => 89000,
                'original_price' => 119000,
                'image' => '/img/ranks/rank-legend.jpg',
                'images' => ['/img/ranks/rank-legend.jpg'],
                'category' => 'vip',
                'in_stock' => true,
                'stock_quantity' => 999,
                'is_active' => true,
                'featured' => true,
                'sort_order' => 2,
                'game_reward' => 'rank_legend',
            ],
            [
                'name' => 'Rank Master',
                'slug' => 'rank-master',
                'short_description' => 'Bậc thầy sinh tồn với quyền bay toàn đảo và màu chat Bạc.',
                'description' => 'Đặc quyền: Quyền /fly toàn đảo, màu chat Bạc, nhận 5 Chìa khóa Rare Crate hàng tuần.',
                'price' => 129000,
                'original_price' => 169000,
                'image' => '/img/ranks/rank-master.jpg',
                'images' => ['/img/ranks/rank-master.jpg'],
                'category' => 'vip',
                'in_stock' => true,
                'stock_quantity' => 999,
                'is_active' => true,
                'featured' => true,
                'sort_order' => 3,
                'game_reward' => 'rank_master',
            ],
            [
                'name' => 'Rank Overlord',
                'slug' => 'rank-overlord',
                'short_description' => 'Bá chủ Đảo Tò Mò với đặc quyền đổi tên gradient và giữ EXP.',
                'description' => 'Đặc quyền: Đổi tên hiển thị /nick màu gradient, 5 Home dịch chuyển, quyền giữ EXP khi tử vong.',
                'price' => 169000,
                'original_price' => 219000,
                'image' => '/img/ranks/rank-overlord.jpg',
                'images' => ['/img/ranks/rank-overlord.jpg'],
                'category' => 'vip',
                'in_stock' => true,
                'stock_quantity' => 999,
                'is_active' => true,
                'featured' => true,
                'sort_order' => 4,
                'game_reward' => 'rank_overlord',
            ],
            [
                'name' => 'Rank God',
                'slug' => 'rank-god',
                'short_description' => 'Rank Tối Thượng Thần Thánh với toàn bộ đặc quyền cao cấp nhất server.',
                'description' => 'Đặc quyền tối cao: Full quyền bay, hào quang sấm sét, không mất đồ khi tử vong (Keep Inventory).',
                'price' => 219000,
                'original_price' => 299000,
                'image' => '/img/ranks/rank-god.jpg',
                'images' => ['/img/ranks/rank-god.jpg'],
                'category' => 'vip',
                'in_stock' => true,
                'stock_quantity' => 999,
                'is_active' => true,
                'featured' => true,
                'sort_order' => 5,
                'game_reward' => 'rank_god',
            ],

            // ── 6 GÓI NẠP TIỀN VND -> COINS (Chuẩn bảng giá đề xuất Đảo Tò Mò) ──
            [
                'name' => 'Gói 500 Coins',
                'slug' => 'goi-500-coins',
                'short_description' => 'Nạp 500 Coins vào tài khoản game và Web Kho Xu.',
                'description' => 'Gói nạp cơ bản 10.000đ nhận ngay 500 Coins (tỷ giá 1.000đ = 50 Coins). Dùng mua vật phẩm, tiện ích hoặc thuê Rank.',
                'price' => 10000,
                'original_price' => 10000,
                'image' => '/img/coins.png',
                'images' => ['/img/coins.png'],
                'category' => 'coin',
                'in_stock' => true,
                'stock_quantity' => 9999,
                'is_active' => true,
                'featured' => false,
                'sort_order' => 20,
                'game_reward' => 'coin_500',
            ],
            [
                'name' => 'Gói 1.050 Coins (+50 Thưởng)',
                'slug' => 'goi-1050-coins',
                'short_description' => 'Nạp 1.050 Coins (Tặng thêm 50 Coins thưởng).',
                'description' => 'Gói nạp 20.000đ nhận 1.000 Coins gốc + thưởng thêm 50 Coins. Tổng cộng 1.050 Coins chuyển ngay vào tài khoản.',
                'price' => 20000,
                'original_price' => 22000,
                'image' => '/img/coins.png',
                'images' => ['/img/coins.png'],
                'category' => 'coin',
                'in_stock' => true,
                'stock_quantity' => 9999,
                'is_active' => true,
                'featured' => false,
                'sort_order' => 21,
                'game_reward' => 'coin_1050',
            ],
            [
                'name' => 'Gói 2.700 Coins (+200 Thưởng)',
                'slug' => 'goi-2700-coins',
                'short_description' => 'Nạp 2.700 Coins (Tặng thêm 200 Coins thưởng).',
                'description' => 'Gói nạp 50.000đ nhận 2.500 Coins gốc + thưởng thêm 200 Coins. Tổng cộng 2.700 Coins chuyển ngay vào tài khoản.',
                'price' => 50000,
                'original_price' => 55000,
                'image' => '/img/coins.png',
                'images' => ['/img/coins.png'],
                'category' => 'coin',
                'in_stock' => true,
                'stock_quantity' => 9999,
                'is_active' => true,
                'featured' => true,
                'sort_order' => 22,
                'game_reward' => 'coin_2700',
            ],
            [
                'name' => 'Gói 5.600 Coins (+600 Thưởng)',
                'slug' => 'goi-5600-coins',
                'short_description' => 'Nạp 5.600 Coins (Tặng thêm 600 Coins thưởng hấp dẫn).',
                'description' => 'Gói nạp 100.000đ nhận 5.000 Coins gốc + thưởng thêm 600 Coins (tiết kiệm +12%). Hỗ trợ kích hoạt tức thì qua VietQR.',
                'price' => 100000,
                'original_price' => 112000,
                'image' => '/img/coins.png',
                'images' => ['/img/coins.png'],
                'category' => 'coin',
                'in_stock' => true,
                'stock_quantity' => 9999,
                'is_active' => true,
                'featured' => true,
                'sort_order' => 23,
                'game_reward' => 'coin_5600',
            ],
            [
                'name' => 'Gói 11.500 Coins (+1.500 Thưởng)',
                'slug' => 'goi-11500-coins',
                'short_description' => 'Nạp 11.500 Coins (Tặng thêm 1.500 Coins cực khủng).',
                'description' => 'Gói nạp 200.000đ nhận 10.000 Coins gốc + thưởng thêm 1.500 Coins (tiết kiệm +15%). Thích hợp mua sắm trang bị và thuê Rank dài hạn.',
                'price' => 200000,
                'original_price' => 230000,
                'image' => '/img/coins.png',
                'images' => ['/img/coins.png'],
                'category' => 'coin',
                'in_stock' => true,
                'stock_quantity' => 9999,
                'is_active' => true,
                'featured' => false,
                'sort_order' => 24,
                'game_reward' => 'coin_11500',
            ],
            [
                'name' => 'Gói 30.000 Coins (+5.000 Thưởng)',
                'slug' => 'goi-30000-coins',
                'short_description' => 'Gói Đại Gia 30.000 Coins (Tặng thêm 5.000 Coins tối đa).',
                'description' => 'Gói nạp 500.000đ nhận 25.000 Coins gốc + siêu ưu đãi 5.000 Coins thưởng (tiết kiệm +20%). Trở thành phú hộ hàng đầu máy chủ Đảo Tò Mò.',
                'price' => 500000,
                'original_price' => 600000,
                'image' => '/img/coins.png',
                'images' => ['/img/coins.png'],
                'category' => 'coin',
                'in_stock' => true,
                'stock_quantity' => 9999,
                'is_active' => true,
                'featured' => true,
                'sort_order' => 25,
                'game_reward' => 'coin_30000',
            ],

            // ── VẬT PHẨM MINECRAFT CHÍNH HÃNG ──
            [
                'name' => 'Hòm Epic',
                'slug' => 'hom-epic',
                'short_description' => 'Hòm Epic chứa cơ hội nhận vật phẩm hiếm trong server.',
                'description' => 'Hòm Epic là gói rương thần bí chứa trang bị Enchant cao cấp, cánh Elytra phát sáng hoặc thú cưng hiếm.',
                'price' => 29000,
                'original_price' => 35000,
                'image' => $img('Epic Crate'),
                'images' => $gallery('Epic Crate'),
                'category' => 'crate',
                'in_stock' => true,
                'stock_quantity' => 150,
                'is_active' => true,
                'featured' => false,
                'sort_order' => 30,
                'game_reward' => 'epic_crate_x1',
            ],
            [
                'name' => 'Gói Skin Minecraft',
                'slug' => 'goi-skin-minecraft',
                'short_description' => 'Bộ skin Minecraft độc quyền phong cách Đảo Tò Mò.',
                'description' => 'Gói Skin Minecraft gồm bộ skin tuyển chọn giúp nhân vật nổi bật hơn trong các sự kiện máy chủ.',
                'price' => 49000,
                'original_price' => 69000,
                'image' => $img('Minecraft Skin Pack'),
                'images' => $gallery('Minecraft Skin Pack'),
                'category' => 'cosmetic',
                'in_stock' => true,
                'stock_quantity' => 100,
                'is_active' => true,
                'featured' => false,
                'sort_order' => 31,
                'game_reward' => 'skin_pack',
            ],
            [
                'name' => 'Gói Tài Nguyên Minecraft',
                'slug' => 'goi-tai-nguyen-minecraft',
                'short_description' => 'Bộ texture pack và sound pack độc quyền Đảo Tò Mò.',
                'description' => 'Tài nguyên âm thanh và giao diện tùy biến cho trải nghiệm chơi Minecraft sắc nét và sống động hơn.',
                'price' => 59000,
                'original_price' => 89000,
                'image' => $img('Minecraft Resources'),
                'images' => $gallery('Minecraft Resources'),
                'category' => 'cosmetic',
                'in_stock' => true,
                'stock_quantity' => 100,
                'is_active' => true,
                'featured' => false,
                'sort_order' => 32,
                'game_reward' => 'minecraft_resource_pack',
            ],
        ];

        $targetSlugs = array_column($products, 'slug');

        // Clean up or deactivate old products not in the new catalog
        Product::whereNotIn('slug', $targetSlugs)
            ->whereDoesntHave('orderItems')
            ->delete();

        Product::whereNotIn('slug', $targetSlugs)
            ->update(['is_active' => false]);

        foreach ($products as $p) {
            Product::updateOrCreate(
                ['slug' => $p['slug']],
                $p
            );
        }

        // 5. Seed Default Event Coupons: OPEN2027 & NEWYEAR2027
        \App\Models\Coupon::updateOrCreate(
            ['code' => 'OPEN2027'],
            [
                'description' => 'Ưu đãi Khai Mở Server: Giảm 30% toàn bộ sản phẩm mua bằng VNĐ (Giới hạn 10 người)',
                'discount_type' => 'percent',
                'discount_value' => 30.00,
                'min_order_amount' => 0.00,
                'max_discount' => 0.00,
                'max_uses' => 10,
                'used_count' => 0,
                'expires_at' => null,
                'is_active' => true,
            ]
        );

        \App\Models\Coupon::updateOrCreate(
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
