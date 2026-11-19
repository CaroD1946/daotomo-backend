<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use App\Models\ServerReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServerReviewController extends Controller
{
    /**
     * Lấy danh sách đánh giá của cư dân hiển thị công khai trên website.
     */
    public function index(): JsonResponse
    {
        $reviews = ServerReview::where('is_approved', true)
            ->latest()
            ->take(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $reviews,
        ]);
    }

    /**
     * Lấy đánh giá của chính tài khoản đang đăng nhập.
     */
    public function myReview(Request $request): JsonResponse
    {
        $user = $request->user();
        $review = ServerReview::where('user_id', $user->id)->first();

        return response()->json([
            'success' => true,
            'data' => $review,
        ]);
    }

    /**
     * Gửi mới hoặc cập nhật đánh giá của tài khoản cho server.
     */
    public function storeOrUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|min:5|max:1000',
        ], [
            'rating.required' => 'Vui lòng chọn số sao đánh giá (1 - 5 sao).',
            'rating.min' => 'Số sao tối thiểu là 1.',
            'rating.max' => 'Số sao tối đa là 5.',
            'comment.required' => 'Vui lòng nhập nội dung nhận xét hoặc cảm nghĩ.',
            'comment.min' => 'Nội dung nhận xét tối thiểu 5 ký tự.',
            'comment.max' => 'Nội dung nhận xét tối đa 1000 ký tự.',
        ]);

        $user = $request->user();
        $playerName = $user->minecraft_username ?: ($user->name ?: $user->username);

        $review = ServerReview::updateOrCreate(
            ['user_id' => $user->id],
            [
                'player_name' => $playerName,
                'rating' => (int) $validated['rating'],
                'comment' => trim($validated['comment']),
                'tag' => 'Cư dân Season 2',
                'is_approved' => true,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Cảm ơn bạn! Đánh giá của bạn đã được ghi nhận thành công.',
            'data' => $review,
        ]);
    }

    /**
     * Xóa đánh giá của chính tài khoản.
     */
    public function deleteMyReview(Request $request): JsonResponse
    {
        $user = $request->user();
        ServerReview::where('user_id', $user->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa đánh giá của bạn.',
        ]);
    }
}
