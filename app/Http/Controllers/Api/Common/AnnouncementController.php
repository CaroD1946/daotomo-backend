<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    /**
     * Lấy thông tin banner thông báo sự kiện trên đỉnh trang (Public).
     */
    public function getAnnouncement(): JsonResponse
    {
        $enabled = (bool) AppSetting::getValue('announcement_enabled', '1');
        $text = AppSetting::getValue(
            'announcement_text',
            '🎉 Đăng ký tài khoản ngay hôm nay để nhận quà tri ân đặc biệt khi Server chính thức Open Beta!'
        );
        $link = AppSetting::getValue('announcement_link', '/html/Account.html');
        $badge = AppSetting::getValue('announcement_badge', 'Sự Kiện Open');

        return response()->json([
            'success' => true,
            'enabled' => $enabled,
            'text' => $text,
            'link' => $link,
            'badge' => $badge,
        ]);
    }

    /**
     * Cập nhật banner thông báo (Admin Only).
     */
    public function updateAnnouncement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'text' => 'required|string|max:255',
            'link' => 'nullable|string|max:255',
            'badge' => 'nullable|string|max:50',
        ]);

        AppSetting::setValue('announcement_enabled', $validated['enabled'] ? '1' : '0');
        AppSetting::setValue('announcement_text', trim($validated['text']));
        AppSetting::setValue('announcement_link', trim($validated['link'] ?? '/html/Account.html'));
        AppSetting::setValue('announcement_badge', trim($validated['badge'] ?? 'Sự Kiện'));

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật thanh thông báo sự kiện thành công!',
            'data' => [
                'enabled' => (bool) $validated['enabled'],
                'text' => $validated['text'],
                'link' => $validated['link'] ?? '/html/Account.html',
                'badge' => $validated['badge'] ?? 'Sự Kiện',
            ],
        ]);
    }
}
