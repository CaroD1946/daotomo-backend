<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\CouponUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminCouponController extends Controller
{
    /**
     * Danh sách tất cả các mã coupon.
     */
    public function index(Request $request): JsonResponse
    {
        $coupons = Coupon::withCount('usages')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $coupons,
        ]);
    }

    /**
     * Tạo mã coupon mới.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:coupons,code',
            'description' => 'nullable|string|max:255',
            'discount_type' => ['required', Rule::in(['percent', 'fixed'])],
            'discount_value' => 'required|numeric|min:0.01',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'max_uses' => 'nullable|integer|min:0',
            'expires_at' => 'nullable|date',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['min_order_amount'] = (float) ($validated['min_order_amount'] ?? 0);
        $validated['max_discount'] = (float) ($validated['max_discount'] ?? 0);
        $validated['max_uses'] = (int) ($validated['max_uses'] ?? 0);

        $coupon = Coupon::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Tạo mã giảm giá thành công!',
            'data' => $coupon,
        ], 201);
    }

    /**
     * Cập nhật thông tin mã coupon.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);

        $validated = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('coupons', 'code')->ignore($coupon->id)],
            'description' => 'nullable|string|max:255',
            'discount_type' => ['sometimes', 'required', Rule::in(['percent', 'fixed'])],
            'discount_value' => 'sometimes|required|numeric|min:0.01',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'max_uses' => 'nullable|integer|min:0',
            'expires_at' => 'nullable|date',
            'is_active' => 'nullable|boolean',
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper(trim($validated['code']));
        }

        $coupon->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật mã giảm giá thành công!',
            'data' => $coupon,
        ]);
    }

    /**
     * Xóa mã coupon.
     */
    public function destroy(int $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa mã giảm giá thành công!',
        ]);
    }

    /**
     * Xem danh sách tài khoản đã sử dụng mã coupon này.
     */
    public function usages(int $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);

        $usages = CouponUsage::with(['user:id,username,name,email,minecraft_username', 'order:id,order_code,total_amount,order_status'])
            ->where('coupon_id', $coupon->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'coupon' => $coupon,
            'total_usages' => $usages->count(),
            'data' => $usages,
        ]);
    }
}
