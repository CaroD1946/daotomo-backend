<?php

namespace App\Http\Controllers\Api\Game;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LuckyWheelController extends Controller
{
    /**
     * Danh sách 16 ô phần thưởng trên Vòng Quay May Mắn.
     */
    public const SLOTS = [
        ['id' => 0,  'name' => '10 Coins',           'type' => 'coin',   'amount' => 10,  'icon' => '🪙', 'color' => '#f59e0b'],
        ['id' => 1,  'name' => 'Chìa Hòm Thường',    'type' => 'item',   'amount' => 1,   'icon' => '📦', 'color' => '#3b82f6'],
        ['id' => 2,  'name' => '25 Coins',           'type' => 'coin',   'amount' => 25,  'icon' => '🪙', 'color' => '#10b981'],
        ['id' => 3,  'name' => '+1 Lượt Quay Thêm',  'type' => 'spin',   'amount' => 1,   'icon' => '🎯', 'color' => '#8b5cf6'],
        ['id' => 4,  'name' => '50 Coins',           'type' => 'coin',   'amount' => 50,  'icon' => '🪙', 'color' => '#ec4899'],
        ['id' => 5,  'name' => 'Chìa Hòm Hiếm',      'type' => 'item',   'amount' => 1,   'icon' => '🗝️', 'color' => '#6366f1'],
        ['id' => 6,  'name' => '8 Thỏi Kim Cương',   'type' => 'item',   'amount' => 8,   'icon' => '💎', 'color' => '#06b6d4'],
        ['id' => 7,  'name' => '100 Coins (Lớn)',    'type' => 'coin',   'amount' => 100, 'icon' => '💰', 'color' => '#eab308'],
        ['id' => 8,  'name' => '32 Thịt Bò Bít Tết', 'type' => 'item',   'amount' => 32,  'icon' => '🥩', 'color' => '#f97316'],
        ['id' => 9,  'name' => '15 Coins',           'type' => 'coin',   'amount' => 15,  'icon' => '🪙', 'color' => '#14b8a6'],
        ['id' => 10, 'name' => 'Voucher 20k Store',   'type' => 'coupon', 'amount' => 1,   'icon' => '🎟️', 'color' => '#a855f7'],
        ['id' => 11, 'name' => '1 Táo Vàng Enchant', 'type' => 'item',   'amount' => 1,   'icon' => '🍏', 'color' => '#eab308'],
        ['id' => 12, 'name' => '30 Coins',           'type' => 'coin',   'amount' => 30,  'icon' => '🪙', 'color' => '#22c55e'],
        ['id' => 13, 'name' => 'Chìa Hòm Huyền Bí',  'type' => 'item',   'amount' => 1,   'icon' => '🔮', 'color' => '#d946ef'],
        ['id' => 14, 'name' => 'Danh Hiệu [May Mắn]', 'type' => 'title',  'amount' => 1,   'icon' => '✨', 'color' => '#38bdf8'],
        ['id' => 15, 'name' => 'JACKPOT 200 COINS',  'type' => 'coin',   'amount' => 200, 'icon' => '👑', 'color' => '#fbbf24'],
    ];

    /**
     * Lấy trạng thái vòng quay và số lượt quay của user.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $luckySpins = $user ? (int) ($user->lucky_spins ?? 0) : 0;
        $coins = $user ? (int) ($user->coins ?? 0) : 0;

        return response()->json([
            'success' => true,
            'lucky_spins' => $luckySpins,
            'coins' => $coins,
            'slots' => self::SLOTS,
            'spin_cost_coins' => 15, // Cho phép quay bằng 15 coins khi hết lượt miễn phí
        ]);
    }

    /**
     * Thực hiện quay thưởng.
     */
    public function spin(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Vui lòng đăng nhập để tham gia Vòng Quay May Mắn!',
            ], 401);
        }

        $useCoins = $request->boolean('use_coins', false);
        $userSpins = (int) ($user->lucky_spins ?? 0);
        $userCoins = (int) ($user->coins ?? 0);
        $costCoins = 15;

        if ($userSpins > 0 && !$useCoins) {
            // Trừ 1 lượt quay miễn phí
            $user->decrement('lucky_spins');
        } elseif ($useCoins || $userSpins <= 0) {
            if ($userCoins < $costCoins) {
                return response()->json([
                    'success' => false,
                    'message' => "Bạn đã hết lượt quay miễn phí và không đủ {$costCoins} Coins để quay thêm. Hãy điểm danh mỗi ngày để nhận thêm 2 lượt quay!",
                ], 422);
            }
            $user->decrement('coins', $costCoins);
        }

        // Chọn ngẫu nhiên 1 ô trong 16 ô
        $slotIndex = random_int(0, count(self::SLOTS) - 1);
        $reward = self::SLOTS[$slotIndex];

        // Xử lý cộng thưởng cho người dùng
        if ($reward['type'] === 'coin') {
            $user->increment('coins', $reward['amount']);
        } elseif ($reward['type'] === 'spin') {
            $user->increment('lucky_spins', $reward['amount']);
        }

        $user->refresh();

        return response()->json([
            'success' => true,
            'slot_index' => $slotIndex,
            'reward' => $reward,
            'remaining_spins' => (int) $user->lucky_spins,
            'remaining_coins' => (int) $user->coins,
            'message' => "Chúc mừng! Bạn đã trúng thưởng {$reward['name']}!",
        ]);
    }
}
