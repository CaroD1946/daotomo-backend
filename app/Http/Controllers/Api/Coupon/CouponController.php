<?php

namespace App\Http\Controllers\Api\Coupon;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    /**
     * Kiểm tra và tính toán giảm giá cho mã coupon.
     */
    public function validateCoupon(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|max:50',
            'total_amount' => 'required|numeric|min:0',
        ]);

        $code = strtoupper(trim($request->input('code')));
        $totalAmount = (float) $request->input('total_amount');
        $user = $request->user();
        $userId = $user ? $user->id : null;

        $coupon = Coupon::where('code', $code)->first();
        if (!$coupon) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'message' => 'Mã giảm giá không tồn tại hoặc đã bị xóa.',
            ], 404);
        }

        $check = $coupon->isValidForOrder($totalAmount, $userId);
        if (!$check['valid']) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'message' => $check['message'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'valid' => true,
            'code' => $coupon->code,
            'discount_type' => $coupon->discount_type,
            'discount_value' => $coupon->discount_value,
            'discount_amount' => $check['discount_amount'],
            'final_total' => $check['final_total'],
            'description' => $coupon->description,
            'message' => "Áp dụng mã {$coupon->code} thành công! Bạn được giảm " . number_format($check['discount_amount'], 0, ',', '.') . "đ.",
        ]);
    }
}
