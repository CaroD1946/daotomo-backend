<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAnalyticsController extends Controller
{
    /**
     * Báo cáo thống kê tổng quan (Visual Analytics).
     */
    public function overview(Request $request): JsonResponse
    {
        // 1. Thống kê nhanh thẻ KPI
        $totalRevenue = (float) Order::whereIn('order_status', ['paid', 'delivered'])
            ->sum('total_amount');

        $totalOrders = Order::count();
        $paidOrders = Order::whereIn('order_status', ['paid', 'delivered'])->count();
        $pendingOrders = Order::where('order_status', 'pending')->count();
        $cancelledOrders = Order::where('order_status', 'cancelled')->count();

        $totalUsers = User::count();
        $linkedUsers = User::whereNotNull('minecraft_username')
            ->where('minecraft_username', '!=', '')
            ->count();

        // 2. Doanh thu 7 ngày gần nhất (nhóm theo từng ngày)
        $sevenDaysAgo = now()->subDays(6)->startOfDay();
        $raw7Days = Order::whereIn('order_status', ['paid', 'delivered'])
            ->where('created_at', '>=', $sevenDaysAgo)
            ->selectRaw("substr(created_at, 1, 10) as day, sum(total_amount) as revenue, count(id) as count")
            ->groupBy('day')
            ->orderBy('day', 'asc')
            ->get()
            ->keyBy('day');

        $revenue7Days = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = now()->subDays($i)->format('Y-m-d');
            $revenue7Days[] = [
                'date' => $d,
                'label' => now()->subDays($i)->format('d/m'),
                'revenue' => isset($raw7Days[$d]) ? (float) $raw7Days[$d]->revenue : 0.0,
                'orders_count' => isset($raw7Days[$d]) ? (int) $raw7Days[$d]->count : 0,
            ];
        }

        // 3. Cơ cấu trạng thái đơn hàng (Pie / Donut chart)
        $orderStatuses = [
            ['label' => 'Đã Thanh Toán / Đã Giao', 'value' => $paidOrders,      'color' => '#10b981'],
            ['label' => 'Đang Chờ Quét QR',        'value' => $pendingOrders,   'color' => '#f59e0b'],
            ['label' => 'Đã Hủy / Hết Hạn',        'value' => $cancelledOrders, 'color' => '#ef4444'],
        ];

        // 4. Top 5 sản phẩm bán chạy nhất
        $topProductsRaw = OrderItem::select('product_id', DB::raw('sum(quantity) as total_qty'), DB::raw('sum(subtotal) as total_sales'))
            ->groupBy('product_id')
            ->orderBy('total_qty', 'desc')
            ->limit(5)
            ->get();

        $productIds = $topProductsRaw->pluck('product_id')->filter();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $topProducts = $topProductsRaw->map(function ($item) use ($products) {
            $p = $products->get($item->product_id);
            return [
                'id' => $item->product_id,
                'name' => $p ? $p->name : 'Sản phẩm #' . $item->product_id,
                'category' => $p ? $p->category : 'other',
                'total_qty' => (int) $item->total_qty,
                'total_sales' => (float) $item->total_sales,
            ];
        });

        return response()->json([
            'success' => true,
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_orders' => $totalOrders,
                'paid_orders' => $paidOrders,
                'pending_orders' => $pendingOrders,
                'cancelled_orders' => $cancelledOrders,
                'total_users' => $totalUsers,
                'linked_users' => $linkedUsers,
            ],
            'revenue_7days' => $revenue7Days,
            'order_statuses' => $orderStatuses,
            'top_products' => $topProducts,
        ]);
    }
}
