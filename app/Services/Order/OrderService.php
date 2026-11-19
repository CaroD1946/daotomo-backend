<?php

namespace App\Services\Order;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\GameDeliveryQueue;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Order\OrderDeliveryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class OrderService
{
    /**
     * Coin rental pricing for temporary ranks (Days => Coins)
     */
    public const COIN_RENT_PRICING = [
        'knight'   => ['1d' => 30,  '3d' => 80,  '5d' => 120, '7d' => 150],
        'legend'   => ['1d' => 50,  '3d' => 135, '5d' => 200, '7d' => 250],
        'master'   => ['1d' => 70,  '3d' => 190, '5d' => 280, '7d' => 350],
        'overlord' => ['1d' => 90,  '3d' => 245, '5d' => 360, '7d' => 450],
        'god'      => ['1d' => 120, '3d' => 320, '5d' => 480, '7d' => 600],
    ];

    /**
     * Map rank identifier or slug to integer tier level (1 to 5).
     */
    public static function getRankTier(string $identifier): int
    {
        $id = strtolower(trim(str_replace(['rank-', 'rank_'], '', $identifier)));
        if ($id === 'god' || preg_match('/\bgod\b/i', $id)) return 5;
        if ($id === 'overlord' || preg_match('/\boverlord\b/i', $id)) return 4;
        if ($id === 'master' || preg_match('/\bmaster\b/i', $id)) return 3;
        if ($id === 'legend' || preg_match('/\blegend\b/i', $id)) return 2;
        if ($id === 'knight' || preg_match('/\bknight\b/i', $id)) return 1;
        return 0;
    }

    /**
     * Map tier level to human readable Rank title.
     */
    public static function getRankTitleByTier(int $tier): string
    {
        return match ($tier) {
            1 => 'Knight',
            2 => 'Legend',
            3 => 'Master',
            4 => 'Overlord',
            5 => 'God',
            default => 'Chưa có',
        };
    }

    public static function getRankSlugByTier(int $tier): string
    {
        return match ($tier) {
            1 => 'rank-knight',
            2 => 'rank-legend',
            3 => 'rank-master',
            4 => 'rank-overlord',
            5 => 'rank-god',
            default => '',
        };
    }

    /**
     * Detect user's current highest owned rank tier from paid/completed orders.
     */
    public static function getUserHighestRankTier(?User $user, ?string $mcUsername = null): array
    {
        if (!$user && empty($mcUsername)) {
            return ['tier' => 0, 'rank_name' => 'Chưa có'];
        }

        $highestTier = 0;

        // 1. Check orders by user_id
        if ($user) {
            $paidOrders = Order::with('items.product')
                ->where('user_id', $user->id)
                ->whereIn('payment_status', ['paid', 'completed'])
                ->get();

            foreach ($paidOrders as $ord) {
                foreach ($ord->items as $it) {
                    $tier = self::getRankTier($it->product?->game_reward ?: $it->product?->slug ?: $it->product_name ?: '');
                    if ($tier > $highestTier) {
                        $highestTier = $tier;
                    }
                }
            }
        }

        // 2. Also check orders by minecraft_username if provided
        $mcName = $mcUsername ?: $user?->minecraft_username;
        if (!empty($mcName)) {
            $mcOrders = Order::with('items.product')
                ->where('minecraft_username', $mcName)
                ->whereIn('payment_status', ['paid', 'completed'])
                ->get();

            foreach ($mcOrders as $ord) {
                foreach ($ord->items as $it) {
                    $tier = self::getRankTier($it->product?->game_reward ?: $it->product?->slug ?: $it->product_name ?: '');
                    if ($tier > $highestTier) {
                        $highestTier = $tier;
                    }
                }
            }
        }

        return [
            'tier' => $highestTier,
            'rank_name' => self::getRankTitleByTier($highestTier),
        ];
    }

    /**
     * Rent Rank using Coins directly from Web "Kho Xu".
     */
    public function rentRankWithCoins(User $user, string $rank, string $duration, ?string $minecraftUsername = null): array
    {
        $rank = strtolower(trim(str_replace(['rank-', 'rank_'], '', $rank)));
        $duration = strtolower(trim($duration));
        if (is_numeric($duration)) {
            $duration .= 'd';
        }

        if (!isset(self::COIN_RENT_PRICING[$rank])) {
            return [
                'status' => 400,
                'data' => ['success' => false, 'message' => 'Rank không hợp lệ. Hỗ trợ: Knight, Legend, Master, Overlord, God.'],
            ];
        }

        if (!isset(self::COIN_RENT_PRICING[$rank][$duration])) {
            return [
                'status' => 400,
                'data' => ['success' => false, 'message' => 'Thời hạn thuê không hợp lệ. Hỗ trợ: 1d, 3d, 5d, 7d.'],
            ];
        }

        $costCoins = self::COIN_RENT_PRICING[$rank][$duration];
        $targetTier = self::getRankTier($rank);
        $player = !empty($minecraftUsername) ? trim($minecraftUsername) : ($user->minecraft_username ?? '');

        if (empty($player)) {
            return [
                'status' => 400,
                'data' => ['success' => false, 'message' => 'Vui lòng liên kết tài khoản Minecraft hoặc nhập tên nhân vật game để nhận Rank.'],
            ];
        }

        // Rank restriction: check if user already has permanent equal or higher rank
        $userHighest = self::getUserHighestRankTier($user, $player);
        if ($userHighest['tier'] >= $targetTier) {
            $ownedTitle = self::getRankTitleByTier($userHighest['tier']);
            return [
                'status' => 422,
                'data' => [
                    'success' => false,
                    'message' => "Bạn đã sở hữu Rank {$ownedTitle} (Cấp {$userHighest['tier']}). Không cần thuê thêm Rank này hoặc các Rank cấp nhỏ hơn!",
                ],
            ];
        }

        if ($user->coins < $costCoins) {
            return [
                'status' => 422,
                'data' => [
                    'success' => false,
                    'message' => "Số dư không đủ. Cần {$costCoins} Coins, bạn hiện có {$user->coins} Coins. Vui lòng nạp thêm Coins!",
                ],
            ];
        }

        // Deduct coins atomically
        $user->decrement('coins', $costCoins);

        $rankTitle = self::getRankTitleByTier($targetTier);
        $randomHex = strtoupper(bin2hex(random_bytes(3)));
        $orderCode = "COIN-{$randomHex}";

        // Record completed order in DB
        $order = Order::create([
            'order_code' => $orderCode,
            'user_id' => $user->id,
            'minecraft_username' => $player,
            'note' => "Thuê Rank {$rankTitle} ({$duration}) bằng {$costCoins} Coins",
            'payment_method' => 'coins',
            'total_amount' => $costCoins,
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'status' => 'paid',
            'transfer_content' => "COIN {$randomHex}",
            'status_history' => [
                [
                    'status' => 'paid',
                    'timestamp' => now()->toIso8601String(),
                    'note' => "Thanh toán bằng {$costCoins} Coins từ Kho Xu",
                ],
                [
                    'status' => 'delivered',
                    'timestamp' => now()->toIso8601String(),
                    'note' => "Đã kích hoạt Rank {$rankTitle} thời hạn {$duration} in-game qua LuckPerms",
                ],
            ],
        ]);

        // Queue in-game temporary delivery
        GameDeliveryQueue::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'game_username' => $player,
            'game_uuid' => $user->minecraft_uuid,
            'command' => "lp user {$player} parent addtemp {$rank} {$duration}",
            'type' => 'rank_rent_coin',
            'status' => 'pending',
        ]);

        GameDeliveryQueue::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'game_username' => $player,
            'game_uuid' => $user->minecraft_uuid,
            'command' => "broadcast &e&lĐẢO TÒ MÒ &8» &fNgười chơi &a{$player} &fvừa thuê thành công &b&lRank {$rankTitle} ({$duration}) &fbằng Coins!",
            'type' => 'rank_rent_coin',
            'status' => 'pending',
        ]);

        return [
            'status' => 200,
            'data' => [
                'success' => true,
                'message' => "Thuê Rank {$rankTitle} ({$duration}) thành công với {$costCoins} Coins! Hệ thống đang kích hoạt in-game.",
                'remaining_coins' => $user->fresh()->coins,
                'order' => $order,
            ],
        ];
    }
    /**
     * Process order creation with DB product verification, transaction safety, idempotency,
     * Minecraft account link enforcement, Rank Upgrade price deduction, and Coupon application.
     *
     * @param User $user
     * @param array $items
     * @param string $minecraftUsername
     * @param string $note
     * @param string $paymentMethod
     * @param string|null $idempotencyKey
     * @param string|null $couponCode
     * @return array
     */
    public function createOrder(
        User $user,
        array $items,
        string $minecraftUsername = '',
        string $note = '',
        string $paymentMethod = 'qr',
        ?string $idempotencyKey = null,
        ?string $couponCode = null
    ): array {
        // Idempotency check: if already processed, return existing order
        if (!empty($idempotencyKey)) {
            $existing = Order::with('items')
                ->where('user_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return [
                    'status' => 200,
                    'data' => [
                        'success' => true,
                        'message' => 'Đơn hàng đã được tạo trước đó (Idempotent).',
                        'order' => $existing,
                        'data' => $existing,
                    ],
                ];
            }
        }

        if (count($items) === 0) {
            return [
                'status' => 400,
                'data' => [
                    'success' => false,
                    'message' => 'Đơn hàng phải có ít nhất một sản phẩm.',
                ],
            ];
        }

        // Validate items and compute price strictly from DB
        $totalAmount = 0;
        $orderItemsData = [];

        $isGuestOrAdmin = ($user->role === 'admin' || str_starts_with($user->email, 'guest'));
        $finalMcUsername = ($isGuestOrAdmin && !empty($minecraftUsername))
            ? $minecraftUsername
            : (!empty($user->minecraft_username) ? $user->minecraft_username : (!empty($minecraftUsername) ? $minecraftUsername : ''));

        foreach ($items as $item) {
            $productId = $item['productId'] ?? $item['product'] ?? null;
            $quantity = $item['quantity'] ?? null;

            if (!$productId || !is_numeric($productId)) {
                return [
                    'status' => 400,
                    'data' => [
                        'success' => false,
                        'message' => 'Mã sản phẩm không hợp lệ.',
                    ],
                ];
            }

            if (!is_int($quantity) && !ctype_digit(strval($quantity))) {
                return [
                    'status' => 400,
                    'data' => [
                        'success' => false,
                        'message' => 'Số lượng sản phẩm phải là số nguyên dương.',
                    ],
                ];
            }

            $quantity = (int) $quantity;
            if ($quantity <= 0) {
                return [
                    'status' => 400,
                    'data' => [
                        'success' => false,
                        'message' => 'Số lượng sản phẩm phải lớn hơn 0.',
                    ],
                ];
            }

            $product = Product::find($productId);

            if (!$product) {
                return [
                    'status' => 404,
                    'data' => [
                        'success' => false,
                        'message' => "Sản phẩm với ID {$productId} không tồn tại.",
                    ],
                ];
            }

            if (!$product->is_active) {
                return [
                    'status' => 400,
                    'data' => [
                        'success' => false,
                        'message' => "Sản phẩm \"{$product->name}\" hiện không khả dụng.",
                    ],
                ];
            }

            if (!$product->in_stock || ($product->stock_quantity === 0)) {
                return [
                    'status' => 400,
                    'data' => [
                        'success' => false,
                        'message' => "Sản phẩm \"{$product->name}\" đã hết hàng.",
                    ],
                ];
            }

            // Enforce Minecraft Account Link for normal registered users
            if (!$isGuestOrAdmin && empty($user->minecraft_username)) {
                return [
                    'status' => 400,
                    'data' => [
                        'success' => false,
                        'message' => 'Bạn cần liên kết tài khoản Minecraft trước khi mua hàng. Hệ thống sẽ tự động giao vật phẩm đến tài khoản game đã liên kết!',
                        'require_link' => true,
                    ],
                ];
            }

            // Rank Tier Restriction & Rank Upgrade Price Deduction
            $targetRankTier = self::getRankTier($product->game_reward ?: $product->slug);
            $upgradeDiscount = 0;
            $itemName = $product->name;

            if ($targetRankTier > 0) {
                $userHighest = self::getUserHighestRankTier($user, $finalMcUsername);
                if ($userHighest['tier'] >= $targetRankTier) {
                    $ownedTitle = self::getRankTitleByTier($userHighest['tier']);
                    return [
                        'status' => 422,
                        'data' => [
                            'success' => false,
                            'message' => "Bạn đã sở hữu Rank {$ownedTitle} (Cấp {$userHighest['tier']}). Không thể mua thêm Rank này hoặc các Rank cấp nhỏ hơn!",
                            'current_rank' => $ownedTitle,
                            'current_tier' => $userHighest['tier'],
                            'target_tier' => $targetRankTier,
                        ],
                    ];
                } elseif ($userHighest['tier'] > 0) {
                    // Calculate Rank Upgrade offset
                    $fallbackRankPrices = [1 => 49000, 2 => 89000, 3 => 129000, 4 => 169000, 5 => 219000];
                    $ownedRankSlug = self::getRankSlugByTier($userHighest['tier']);
                    $ownedProduct = Product::where('slug', $ownedRankSlug)->orWhere('game_reward', 'rank_' . str_replace('rank-', '', $ownedRankSlug))->first();
                    $ownedPrice = ($ownedProduct && (float)$ownedProduct->price > 0) ? (float) $ownedProduct->price : ($fallbackRankPrices[$userHighest['tier']] ?? 0);
                    $upgradeDiscount = min((float) $product->price, $ownedPrice);
                    $itemName .= " (Nâng cấp từ Rank " . self::getRankTitleByTier($userHighest['tier']) . ")";
                    $note = trim($note . " [Nâng cấp từ Rank {$userHighest['rank_name']} (-" . number_format($upgradeDiscount, 0, ',', '.') . "đ)]");
                }
            }

            $price = max(0, (float) $product->price - $upgradeDiscount);
            $subtotal = $price * $quantity;
            $totalAmount += $subtotal;

            $orderItemsData[] = [
                'product_id' => $product->id,
                'product_name' => $itemName,
                'price' => $price,
                'quantity' => $quantity,
                'subtotal' => $subtotal,
            ];
        }

        // Validate Coupon if provided
        $appliedCoupon = null;
        $discountAmount = 0;
        $originalAmount = $totalAmount;

        if (!empty($couponCode)) {
            $cCode = strtoupper(trim($couponCode));
            $coupon = Coupon::where('code', $cCode)->first();
            if (!$coupon) {
                return [
                    'status' => 422,
                    'data' => [
                        'success' => false,
                        'message' => "Mã giảm giá '{$cCode}' không tồn tại.",
                    ],
                ];
            }

            $cCheck = $coupon->isValidForOrder($totalAmount, $user->id);
            if (!$cCheck['valid']) {
                return [
                    'status' => 422,
                    'data' => [
                        'success' => false,
                        'message' => $cCheck['message'],
                    ],
                ];
            }

            $discountAmount = (float) $cCheck['discount_amount'];
            $totalAmount = (float) $cCheck['final_total'];
            $appliedCoupon = $coupon;
        }

        // Generate unique code e.g. DTM-XXXXXX
        $randomHex = strtoupper(bin2hex(random_bytes(3)));
        $orderCode = "DTM-{$randomHex}";
        $transferContent = "DTM {$randomHex}";

        if ($paymentMethod === 'coins') {
            if ($user->coins < $totalAmount) {
                return [
                    'status' => 422,
                    'data' => [
                        'success' => false,
                        'message' => 'Số dư Xu không đủ. Cần ' . number_format($totalAmount) . ' Xu, bạn hiện có ' . number_format($user->coins) . ' Xu.',
                    ],
                ];
            }
        }

        try {
            $createdOrder = Order::create([
                'order_code' => $orderCode,
                'user_id' => $user->id,
                'minecraft_username' => $finalMcUsername,
                'note' => $note,
                'payment_method' => $paymentMethod,
                'total_amount' => $totalAmount,
                'coupon_code' => $appliedCoupon ? $appliedCoupon->code : null,
                'discount_amount' => $discountAmount,
                'original_amount' => $originalAmount,
                'order_status' => $paymentMethod === 'coins' ? 'processing' : 'pending',
                'payment_status' => $paymentMethod === 'coins' ? 'paid' : 'pending',
                'status' => $paymentMethod === 'coins' ? 'paid' : 'pending',
                'transfer_content' => $transferContent,
                'idempotency_key' => $idempotencyKey,
                'status_history' => [
                    [
                        'status' => 'pending',
                        'timestamp' => now()->toIso8601String(),
                        'note' => 'Đơn hàng được khởi tạo',
                    ],
                ],
            ]);

            foreach ($orderItemsData as $data) {
                $data['order_id'] = $createdOrder->id;
                OrderItem::create($data);
            }

            if ($appliedCoupon) {
                CouponUsage::create([
                    'coupon_id' => $appliedCoupon->id,
                    'user_id' => $user->id,
                    'order_id' => $createdOrder->id,
                    'discount_amount' => $discountAmount,
                ]);
                $appliedCoupon->increment('used_count');
            }

            if ($paymentMethod === 'coins') {
                $user->decrement('coins', $totalAmount);
                app(OrderDeliveryService::class)->queueDelivery($createdOrder);
            }

            $order = $createdOrder->fresh(['items']);
        } catch (\Throwable $e) {
            if (isset($createdOrder) && $createdOrder->id) {
                try {
                    OrderItem::where('order_id', $createdOrder->id)->delete();
                    $createdOrder->delete();
                } catch (\Throwable $ignored) {
                    // Suppress secondary rollback errors
                }
            }
            throw $e;
        }

        return [
            'status' => 201,
            'data' => [
                'success' => true,
                'message' => 'Đặt hàng thành công! Vui lòng thanh toán theo thông tin bên dưới.',
                'order' => $order,
                'data' => $order,
            ],
        ];
    }

    /**
     * Cancel pending order.
     */
    public function cancelOrder(Order $order, User $user, string $reason = ''): array
    {
        if ($order->user_id !== $user->id && $user->role !== 'admin') {
            return [
                'status' => 403,
                'data' => ['success' => false, 'message' => 'Bạn không có quyền thao tác trên đơn hàng này.'],
            ];
        }

        if ($order->order_status !== 'pending') {
            return [
                'status' => 400,
                'data' => [
                    'success' => false,
                    'message' => 'Chỉ có thể hủy đơn hàng khi đang ở trạng thái Chờ thanh toán.',
                ],
            ];
        }

        $history = $order->status_history ?? [];
        $history[] = [
            'status' => 'cancelled',
            'timestamp' => now()->toIso8601String(),
            'note' => !empty($reason) ? $reason : 'Người dùng chủ động hủy',
        ];

        $order->update([
            'order_status' => 'cancelled',
            'status' => 'cancelled',
            'status_history' => $history,
        ]);

        $fresh = $order->fresh(['items']);

        return [
            'status' => 200,
            'data' => [
                'success' => true,
                'message' => 'Đã hủy đơn hàng thành công.',
                'order' => $fresh,
                'data' => $fresh,
            ],
        ];
    }

    /**
     * Submit payment proof.
     */
    public function submitPaymentProof(Order $order, User $user, string $proof, string $note = ''): array
    {
        if ($order->user_id !== $user->id && $user->role !== 'admin') {
            return [
                'status' => 403,
                'data' => ['success' => false, 'message' => 'Bạn không có quyền gửi minh chứng cho đơn hàng này.'],
            ];
        }

        if (empty($proof)) {
            return [
                'status' => 400,
                'data' => ['success' => false, 'message' => 'Vui lòng cung cấp link hoặc ảnh minh chứng.'],
            ];
        }

        $history = $order->status_history ?? [];
        $history[] = [
            'status' => 'proof_submitted',
            'timestamp' => now()->toIso8601String(),
            'note' => !empty($note) ? $note : 'Đã gửi minh chứng thanh toán',
        ];

        $order->update([
            'payment_proof' => $proof,
            'payment_status' => 'pending',
            'status_history' => $history,
        ]);

        $fresh = $order->fresh(['items']);

        return [
            'status' => 200,
            'data' => [
                'success' => true,
                'message' => 'Đã gửi minh chứng thanh toán! Admin sẽ duyệt trong 24h.',
                'order' => $fresh,
                'data' => $fresh,
            ],
        ];
    }

    /**
     * Update order status with state machine invariant enforcement.
     */
    public function updateOrderStatus(Order $order, array $data): array
    {
        $newOrderStatus = $data['orderStatus'] ?? null;
        $newPaymentStatus = $data['paymentStatus'] ?? null;
        $legacyStatus = $data['status'] ?? null;

        // Handle backward compatibility for legacy status field
        if ($legacyStatus && !$newOrderStatus && !$newPaymentStatus) {
            if ($legacyStatus === 'paid') {
                $newPaymentStatus = 'paid';
                $newOrderStatus = 'processing';
            } elseif ($legacyStatus === 'cancelled') {
                $newOrderStatus = 'cancelled';
            } elseif ($legacyStatus === 'delivered' || $legacyStatus === 'completed') {
                $newOrderStatus = 'delivered';
                $newPaymentStatus = 'paid';
            } else {
                $newOrderStatus = $legacyStatus;
            }
        }

        // State Machine Invariance Rules
        // Rule 1: CANNOT transition cancelled order to paid or processing or completed
        if ($order->order_status === 'cancelled') {
            if (($newPaymentStatus && $newPaymentStatus === 'paid') || ($newOrderStatus && in_array($newOrderStatus, ['processing', 'completed', 'delivered']))) {
                return [
                    'status' => 400,
                    'data' => [
                        'success' => false,
                        'message' => 'Không thể chuyển đơn hàng đã hủy sang trạng thái đã thanh toán hoặc xử lý.',
                    ],
                ];
            }
        }

        // Rule 2: CANNOT transition delivered/completed order back to pending
        if (in_array($order->order_status, ['delivered', 'completed'])) {
            if (($newOrderStatus && $newOrderStatus === 'pending') || ($newPaymentStatus && $newPaymentStatus === 'pending')) {
                return [
                    'status' => 400,
                    'data' => [
                        'success' => false,
                        'message' => 'Không thể chuyển đơn hàng đã giao thành công về trạng thái chờ xử lý.',
                    ],
                ];
            }
        }

        $updates = [];
        if ($newOrderStatus) {
            $updates['order_status'] = $newOrderStatus;
            $updates['status'] = $newOrderStatus;
        }

        if ($newPaymentStatus) {
            $updates['payment_status'] = $newPaymentStatus;
            if ($newPaymentStatus === 'paid' && (!isset($updates['status']) || $updates['status'] === 'pending')) {
                $updates['status'] = 'paid';
            }
        }

        if (isset($data['adminNote'])) {
            $updates['admin_note'] = $data['adminNote'];
        }

        $history = $order->status_history ?? [];
        $history[] = [
            'status' => $newOrderStatus ?? $newPaymentStatus ?? 'updated',
            'timestamp' => now()->toIso8601String(),
            'note' => $data['reason'] ?? 'Admin cập nhật trạng thái',
        ];
        $updates['status_history'] = $history;

        $order->update($updates);

        if (($updates['payment_status'] ?? null) === 'paid' || ($updates['status'] ?? null) === 'paid') {
            app(\App\Services\Order\OrderDeliveryService::class)->queueDeliveries($order);
        }

        $fresh = $order->fresh(['items']);

        return [
            'status' => 200,
            'data' => [
                'success' => true,
                'message' => 'Cập nhật trạng thái đơn hàng thành công.',
                'order' => $fresh,
                'data' => $fresh,
            ],
        ];
    }

    /**
     * Build query for admin order searching and filtering.
     */
    public function getAdminOrdersQuery(array $filters = []): Builder
    {
        $query = Order::with(['items', 'user']);

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $status = $filters['status'];
            $query->where(function ($q) use ($status) {
                $q->where('order_status', $status)
                  ->orWhere('payment_status', $status)
                  ->orWhere('status', $status);
            });
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('order_code', 'like', $search)
                  ->orWhere('minecraft_username', 'like', $search)
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('username', 'like', $search)
                         ->orWhere('email', 'like', $search);
                  });
            });
        }

        return $query->orderBy('id', 'desc');
    }
}
