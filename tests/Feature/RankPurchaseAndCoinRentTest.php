<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankPurchaseAndCoinRentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_user_can_purchase_rank_when_no_rank_owned(): void
    {
        $user = User::factory()->create([
            'coins' => 1000,
            'minecraft_username' => 'TestPlayer01',
        ]);

        $knight = Product::where('slug', 'rank-knight')->firstOrFail();

        $res = $this->actingAs($user)->postJson('/api/orders', [
            'items' => [
                ['productId' => $knight->id, 'quantity' => 1],
            ],
            'minecraftUsername' => 'TestPlayer01',
            'paymentMethod' => 'qr',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_user_cannot_repurchase_same_rank_or_lower_rank(): void
    {
        $user = User::factory()->create([
            'minecraft_username' => 'MasterPlayer',
        ]);

        $master = Product::where('slug', 'rank-master')->firstOrFail();
        $knight = Product::where('slug', 'rank-knight')->firstOrFail();
        $legend = Product::where('slug', 'rank-legend')->firstOrFail();
        $god = Product::where('slug', 'rank-god')->firstOrFail();

        // Create an existing completed order where user owns Rank Master (Tier 3)
        $order = Order::create([
            'order_code' => 'DTM-TESTMASTER',
            'user_id' => $user->id,
            'minecraft_username' => 'MasterPlayer',
            'payment_status' => 'paid',
            'order_status' => 'delivered',
            'total_amount' => 129000,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $master->id,
            'product_name' => $master->name,
            'price' => 129000,
            'quantity' => 1,
            'subtotal' => 129000,
        ]);

        // Attempt 1: Try to re-buy Rank Master -> BLOCKED
        $resSame = $this->actingAs($user)->postJson('/api/orders', [
            'items' => [['productId' => $master->id, 'quantity' => 1]],
            'minecraftUsername' => 'MasterPlayer',
        ]);
        $resSame->assertStatus(422)
            ->assertJsonPath('success', false);

        // Attempt 2: Try to buy Rank Knight (Tier 1 < Tier 3) -> BLOCKED
        $resKnight = $this->actingAs($user)->postJson('/api/orders', [
            'items' => [['productId' => $knight->id, 'quantity' => 1]],
            'minecraftUsername' => 'MasterPlayer',
        ]);
        $resKnight->assertStatus(422)
            ->assertJsonPath('success', false);

        // Attempt 3: Try to buy Rank Legend (Tier 2 < Tier 3) -> BLOCKED
        $resLegend = $this->actingAs($user)->postJson('/api/orders', [
            'items' => [['productId' => $legend->id, 'quantity' => 1]],
            'minecraftUsername' => 'MasterPlayer',
        ]);
        $resLegend->assertStatus(422)
            ->assertJsonPath('success', false);

        // Attempt 4: Try to upgrade to Rank God (Tier 5 > Tier 3) -> ALLOWED
        $resGod = $this->actingAs($user)->postJson('/api/orders', [
            'items' => [['productId' => $god->id, 'quantity' => 1]],
            'minecraftUsername' => 'MasterPlayer',
        ]);
        $resGod->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_user_can_rent_rank_with_coins_and_queues_luckperms_command(): void
    {
        $user = User::factory()->create([
            'coins' => 200,
            'minecraft_username' => 'CoinRenter',
        ]);

        // Rent Knight for 3 days = 80 Coins
        $res = $this->actingAs($user)->postJson('/api/ranks/rent-with-coins', [
            'rank' => 'knight',
            'duration' => '3d',
            'minecraftUsername' => 'CoinRenter',
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('remaining_coins', 120); // 200 - 80 = 120

        $this->assertDatabaseHas('game_delivery_queue', [
            'game_username' => 'CoinRenter',
            'command' => 'lp user CoinRenter parent addtemp knight 3d',
            'status' => 'pending',
        ]);
    }

    public function test_rent_rank_with_coins_fails_when_balance_insufficient(): void
    {
        $user = User::factory()->create([
            'coins' => 50,
            'minecraft_username' => 'PoorPlayer',
        ]);

        // Try to rent God 7 days = 600 Coins
        $res = $this->actingAs($user)->postJson('/api/ranks/rent-with-coins', [
            'rank' => 'god',
            'duration' => '7d',
            'minecraftUsername' => 'PoorPlayer',
        ]);

        $res->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_user_can_query_my_rank_status(): void
    {
        $user = User::factory()->create([
            'minecraft_username' => 'GodOwner',
        ]);

        $god = Product::where('slug', 'rank-god')->firstOrFail();
        $order = Order::create([
            'order_code' => 'DTM-GODOWNER',
            'user_id' => $user->id,
            'minecraft_username' => 'GodOwner',
            'payment_status' => 'paid',
            'order_status' => 'delivered',
            'total_amount' => 219000,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $god->id,
            'product_name' => $god->name,
            'price' => 219000,
            'quantity' => 1,
            'subtotal' => 219000,
        ]);

        $res = $this->actingAs($user)->getJson('/api/ranks/my-rank');
        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('current_rank', 'God')
            ->assertJsonPath('current_tier', 5);
    }
}
