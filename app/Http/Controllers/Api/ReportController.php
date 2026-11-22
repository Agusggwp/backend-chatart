<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\ReportRequest;
use App\Models\Report;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function store(ReportRequest $request): JsonResponse
    {
        $report = Report::create([
            'reporter_id' => $request->user()->id,
            'reported_user_id' => $request->validated('reported_user_id'),
            'conversation_id' => $request->validated('conversation_id'),
            'message_id' => $request->validated('message_id'),
            'reason' => $request->validated('reason'),
            'description' => $request->validated('description'),
            'status' => 'pending',
        ]);

        return ApiResponse::success(
            data: [
                'report' => $report,
            ],
            message: 'Laporan Anda telah berhasil dikirim dan akan ditinjau oleh tim kami.',
            status: 201
        );
    }
}
