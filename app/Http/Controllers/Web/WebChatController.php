<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Status;
use App\Models\User;
use App\Services\CallService;
use App\Services\ChatService;
use App\Services\StatusService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WebChatController extends Controller
{
    public function __construct(
        protected ChatService $chatService,
        protected StatusService $statusService,
        protected CallService $callService,
        protected UserService $userService,
    ) {}

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();

        $token = $user->createToken('web_session')->plainTextToken;
        session(['sanctum_token' => $token]);

        $conversations = $this->chatService->listConversations($user);
        $otherUsers = User::where('id', '!=', $user->id)->orderBy('name')->get();
        $statuses = Status::query()->active()->with('user')->latest()->get();
        $blockedUsers = $this->chatService->listBlockedUsers($user);
        $callHistory = $this->callService->listCalls($user);

        return view('chat.index', [
            'currentUser' => $user,
            'token' => session('sanctum_token'),
            'conversations' => $conversations,
            'otherUsers' => $otherUsers,
            'statuses' => $statuses,
            'blockedUsers' => $blockedUsers,
            'callHistory' => $callHistory,
        ]);
    }
}
