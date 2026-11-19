<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed standard ranks
        Product::create([
            'name' => 'Rank Knight',
            'slug' => 'rank-knight',
            'description' => 'Rank khởi đầu',
            'price' => 49000,
            'category' => 'rank',
            'in_stock' => true,
            'is_active' => true,
            'game_reward' => 'rank_knight',
        ]);

        Product::create([
            'name' => 'Rank Legend',
            'slug' => 'rank-legend',
            'description' => 'Rank Legend',
            'price' => 89000,
            'category' => 'rank',
            'in_stock' => true,
            'is_active' => true,
            'game_reward' => 'rank_legend',
        ]);

        Product::create([
            'name' => 'Rank Master',
            'slug' => 'rank-master',
            'description' => 'Rank Master',
            'price' => 129000,
            'category' => 'rank',
            'in_stock' => true,
            'is_active' => true,
            'game_reward' => 'rank_master',
        ]);
    }

    public function test_coupon_validation_api()
    {
        $coupon = Coupon::create([
            'code' => 'TEST10',
            'description' => 'Giảm 10%',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'min_order_amount' => 50000,
            'max_discount' => 20000,
            'max_uses' => 100,
            'used_count' => 0,
            'is_active' => true,
        ]);

        // Below min amount
        $res = $this->postJson('/api/coupons/validate', [
            'code' => 'TEST10',
            'total_amount' => 30000,
        ]);
        $res->assertStatus(422)
            ->assertJson(['valid' => false]);

        // Valid order
        $res2 = $this->postJson('/api/coupons/validate', [
            'code' => 'TEST10',
            'total_amount' => 100000,
        ]);
        $res2->assertStatus(200)
            ->assertJson([
                'valid' => true,
                'discount_amount' => 10000,
                'final_total' => 90000,
            ]);
    }

    public function test_order_creation_with_coupon()
    {
        $user = User::factory()->create([
            'minecraft_username' => 'SteveLinked',
        ]);

        $coupon = Coupon::create([
            'code' => 'SALE20K',
            'description' => 'Giảm 20k',
            'discount_type' => 'fixed',
            'discount_value' => 20000,
            'min_order_amount' => 40000,
            'max_uses' => 10,
            'used_count' => 0,
            'is_active' => true,
        ]);

        $product = Product::where('slug', 'rank-knight')->first();

        $res = $this->actingAs($user)->postJson('/api/orders', [
            'items' => [
                ['productId' => $product->id, 'quantity' => 1],
            ],
            'couponCode' => 'SALE20K',
            'paymentMethod' => 'qr',
        ]);

        $res->assertStatus(201);
        $orderData = $res->json('order');
        $this->assertEquals(29000, $orderData['total_amount']); // 49000 - 20000 = 29000
        $this->assertEquals('SALE20K', $orderData['coupon_code']);
        $this->assertEquals(20000, $orderData['discount_amount']);
        $this->assertEquals(49000, $orderData['original_amount']);

        // Check coupon usage recorded and used_count incremented
        $this->assertEquals(1, $coupon->fresh()->used_count);
    }

    public function test_rank_upgrade_deducts_owned_rank_price()
    {
        $user = User::factory()->create([
            'minecraft_username' => 'RankUpgrader',
        ]);

        // User already has Knight rank (paid & delivered order)
        $knightProduct = Product::where('slug', 'rank-knight')->first();
        $prevOrder = Order::create([
            'order_code' => 'DTM-PREV01',
            'user_id' => $user->id,
            'minecraft_username' => 'RankUpgrader',
            'total_amount' => 49000,
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'status' => 'paid',
            'payment_method' => 'qr',
        ]);
        $prevOrder->items()->create([
            'product_id' => $knightProduct->id,
            'product_name' => $knightProduct->name,
            'price' => 49000,
            'quantity' => 1,
            'subtotal' => 49000,
        ]);

        // Now user upgrades to Master (Tier 3, 129k)
        $masterProduct = Product::where('slug', 'rank-master')->first();
        $res = $this->actingAs($user)->postJson('/api/orders', [
            'items' => [
                ['productId' => $masterProduct->id, 'quantity' => 1],
            ],
            'paymentMethod' => 'qr',
        ]);

        $res->assertStatus(201);
        $order = $res->json('order');
        // Price should be 129000 - 49000 = 80000
        $this->assertEquals(80000, $order['total_amount']);
        $this->assertStringContainsString('Nâng cấp từ Rank Knight', $order['items'][0]['product_name']);
    }

    public function test_order_creation_requires_minecraft_link_for_registered_user()
    {
        $user = User::factory()->create([
            'minecraft_username' => null,
            'role' => 'user',
        ]);

        $product = Product::where('slug', 'rank-knight')->first();

        $res = $this->actingAs($user)->postJson('/api/orders', [
            'items' => [
                ['productId' => $product->id, 'quantity' => 1],
            ],
            'paymentMethod' => 'qr',
        ]);

        $res->assertStatus(400)
            ->assertJson([
                'success' => false,
                'require_link' => true,
            ]);
    }

    public function test_lucky_wheel_status_and_spin()
    {
        $user = User::factory()->create([
            'coins' => 50,
            'lucky_spins' => 2,
        ]);

        // Check status with actingAs
        $resStatus = $this->actingAs($user)->getJson('/api/lucky-wheel/status');
        $resStatus->assertStatus(200)
            ->assertJson([
                'success' => true,
                'lucky_spins' => 2,
                'coins' => 50,
            ]);

        // Check status with Bearer token header (real API client flow)
        $bearerToken = $user->createToken('test-wheel')->plainTextToken;
        $resBearer = $this->withHeader('Authorization', 'Bearer ' . $bearerToken)->getJson('/api/lucky-wheel/status');
        $resBearer->assertStatus(200)
            ->assertJson([
                'success' => true,
                'lucky_spins' => 2,
                'coins' => 50,
            ]);

        // Perform spin using free spin
        $resSpin = $this->actingAs($user)->postJson('/api/lucky-wheel/spin');
        $resSpin->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // lucky_spins should have been decremented from 2 to 1 (or +1 if landed on spin slot)
        $this->assertNotNull($resSpin->json('reward'));
        $this->assertNotNull($resSpin->json('slot_index'));
    }

    public function test_announcement_banner_flow()
    {
        $res = $this->getJson('/api/announcement');
        $res->assertStatus(200)
            ->assertJsonStructure(['success', 'enabled', 'text', 'link', 'badge']);

        $admin = User::factory()->create(['role' => 'admin']);
        $updateRes = $this->actingAs($admin)->putJson('/api/admin/announcement', [
            'enabled' => true,
            'text' => 'Thông báo Open Beta đặc biệt!',
            'link' => '/html/Store.html',
            'badge' => 'Hot Event',
        ]);
        $updateRes->assertStatus(200);

        $getAfter = $this->getJson('/api/announcement');
        $getAfter->assertJson([
            'text' => 'Thông báo Open Beta đặc biệt!',
            'badge' => 'Hot Event',
        ]);
    }

    public function test_admin_analytics_overview()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $res = $this->actingAs($admin)->getJson('/api/admin/analytics/overview');
        $res->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'summary' => ['total_revenue', 'total_orders', 'paid_orders', 'pending_orders', 'cancelled_orders', 'total_users', 'linked_users'],
                'revenue_7days',
                'order_statuses',
                'top_products',
            ]);
    }
}
