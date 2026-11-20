@extends('layouts.web')

@section('title', 'ARTDEVATA Web - Chat')

@section('content')
<!-- Background green bar behind chat -->
<div class="fixed top-0 left-0 w-full h-[128px] bg-wa-green -z-10"></div>

<!-- Main Web Container -->
<div id="app" class="w-full max-w-[1680px] h-[96vh] bg-white rounded-lg shadow-2xl flex overflow-hidden border border-wa-border relative">

    <!-- LEFT SIDEBAR: Conversations & Tools (35-40%) -->
    <div class="w-full md:w-[420px] lg:w-[450px] h-full flex flex-col border-r border-wa-border bg-white shrink-0">
        <!-- Sidebar Top Header -->
        <div class="h-16 px-4 bg-wa-hover flex items-center justify-between border-b border-wa-border shrink-0">
            <!-- User Profile Avatar -->
            <div class="flex items-center gap-3">
                <div class="relative">
                    <div class="w-10 h-10 rounded-full bg-wa-green text-white font-bold flex items-center justify-center text-sm shadow">
                        {{ strtoupper(substr($currentUser->name, 0, 2)) }}
                    </div>
                    <span class="absolute bottom-0 right-0 w-3 h-3 bg-emerald-500 border-2 border-white rounded-full"></span>
                </div>
                <div class="leading-tight">
                    <div class="font-bold text-sm text-gray-800">{{ $currentUser->name }}</div>
                    <div class="text-[11px] text-wa-muted">@<span>{{ $currentUser->username }}</span></div>
                </div>
            </div>

            <!-- Header Action Icons -->
            <div class="flex items-center gap-4 text-wa-muted text-lg">
                <!-- Status/Stories Button -->
                <button onclick="openStatusModal()" title="Status / Cerita" class="hover:text-wa-green transition relative">
                    <i class="fa-solid fa-circle-notch"></i>
                    @if(count($statuses) > 0)
                    <span class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-wa-green rounded-full border-2 border-white"></span>
                    @endif
                </button>

                <!-- New Chat / Contacts Button -->
                <button onclick="openNewChatModal()" title="Mulai Chat Baru" class="hover:text-wa-green transition">
                    <i class="fa-solid fa-message"></i>
                </button>

                <!-- New Group Button -->
                <button onclick="openNewGroupModal()" title="Buat Grup Baru" class="hover:text-wa-green transition">
                    <i class="fa-solid fa-users"></i>
                </button>

                <!-- More Menu Dropdown -->
                <div class="relative">
                    <button onclick="toggleDropdown('sidebarMenuDropdown')" title="Menu Lainnya" class="hover:text-wa-green transition">
                        <i class="fa-solid fa-ellipsis-vertical"></i>
                    </button>
                    <div id="sidebarMenuDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-xl border border-wa-border py-1 text-sm text-gray-700 z-50">
                        <button onclick="openBlockedModal()" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center gap-2">
                            <i class="fa-solid fa-ban text-red-500 w-4"></i> Kontak Terblokir
                        </button>
                        <button onclick="openStarredModal()" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center gap-2">
                            <i class="fa-solid fa-star text-amber-500 w-4"></i> Pesan Berbintang
                        </button>
                        <button onclick="openCallsModal()" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center gap-2">
                            <i class="fa-solid fa-phone text-emerald-600 w-4"></i> Riwayat Panggilan
                        </button>
                        <div class="border-t border-gray-100 my-1"></div>
                        <form action="{{ route('web.logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2 hover:bg-red-50 text-red-600 flex items-center gap-2">
                                <i class="fa-solid fa-right-from-bracket w-4"></i> Keluar (Logout)
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Switcher Bar -->
        <div class="px-4 py-1.5 bg-emerald-50 border-b border-emerald-100 flex items-center justify-between text-xs text-emerald-800">
            <div class="truncate">
                <i class="fa-solid fa-user-check text-wa-green mr-1"></i>
                <span>Aktif: <strong>{{ $currentUser->name }}</strong></span>
            </div>
            <div class="flex items-center gap-1 shrink-0">
                <span class="text-gray-400">|</span>
                <span class="text-[11px] text-gray-500">Ganti ke:</span>
                @foreach($otherUsers as $ou)
                <a href="{{ route('web.quick-login', $ou->id) }}" class="font-bold underline hover:text-wa-green text-[11px] bg-white px-1.5 py-0.5 rounded border border-emerald-200">
                    {{ explode(' ', $ou->name)[0] }}
                </a>
                @endforeach
            </div>
        </div>

        <!-- Search Bar -->
        <div class="p-2 border-b border-wa-border bg-white">
            <div class="relative flex items-center bg-wa-input rounded-lg px-3 py-1.5">
                <i class="fa-solid fa-magnifying-glass text-wa-muted text-xs mr-3"></i>
                <input type="text" id="chatSearchInput" oninput="filterConversations(this.value)" placeholder="Cari percakapan atau pesan..." 
                       class="w-full bg-transparent text-xs text-gray-800 focus:outline-none">
            </div>
        </div>

        <!-- Filter Tabs: Semua, Belum Dibaca, Sematkan, Arsipkan -->
        <div class="flex items-center gap-1 px-3 py-1.5 border-b border-wa-border text-xs overflow-x-auto bg-gray-50/50">
            <button onclick="setFilter('all')" id="filter-all" class="filter-pill px-3 py-1 rounded-full font-semibold bg-wa-green text-white">Semua</button>
            <button onclick="setFilter('unread')" id="filter-unread" class="filter-pill px-3 py-1 rounded-full font-semibold text-gray-600 hover:bg-gray-200">Belum Dibaca</button>
            <button onclick="setFilter('pinned')" id="filter-pinned" class="filter-pill px-3 py-1 rounded-full font-semibold text-gray-600 hover:bg-gray-200"><i class="fa-solid fa-thumbtack mr-1"></i>Sematkan</button>
            <button onclick="setFilter('archived')" id="filter-archived" class="filter-pill px-3 py-1 rounded-full font-semibold text-gray-600 hover:bg-gray-200"><i class="fa-solid fa-box-archive mr-1"></i>Arsip</button>
        </div>

        <!-- Conversations List -->
        <div id="conversationsList" class="flex-1 overflow-y-auto divide-y divide-gray-100">
            <!-- Dynamic Conversation Items Rendered Here via JS -->
            <div class="p-8 text-center text-wa-muted text-xs">
                <i class="fa-solid fa-spinner fa-spin text-lg mb-2"></i>
                <div>Memuat daftar percakapan...</div>
            </div>
        </div>
    </div>

    <!-- RIGHT MAIN PANEL: Active Chat or Welcome Blank State (60-65%) -->
    <div id="chatPane" class="flex-1 h-full flex flex-col bg-wa-hover relative">
        
        <!-- BLANK STATE: No Chat Selected -->
        <div id="blankState" class="flex-1 flex flex-col items-center justify-center p-8 text-center bg-gray-50 border-b-8 border-wa-green select-none">
            <div class="w-36 h-36 rounded-full bg-emerald-100/70 flex items-center justify-center text-wa-green text-6xl mb-6 shadow-inner">
                <i class="fa-brands fa-whatsapp"></i>
            </div>
            <h2 class="text-2xl font-semibold text-gray-800 mb-2">ARTDEVATA Web</h2>
            <p class="text-sm text-wa-muted max-w-md leading-relaxed mb-8">
                Kirim dan terima pesan real-time, panggilan suara, berbagi foto, catatan suara, dan dokumen langsung dari peramban Anda.
            </p>
            <div class="flex items-center gap-2 text-xs text-wa-muted bg-white px-4 py-2 rounded-full border border-wa-border shadow-sm">
                <i class="fa-solid fa-lock text-wa-green"></i>
                <span>Terenkripsi end-to-end secara privat</span>
            </div>
        </div>

        <!-- ACTIVE CHAT VIEW (Initially Hidden) -->
        <div id="activeChatView" class="hidden flex-1 flex flex-col h-full">
            <!-- Chat Header -->
            <div class="h-16 px-4 bg-wa-hover flex items-center justify-between border-b border-wa-border shrink-0 z-10 shadow-sm">
                <div class="flex items-center gap-3 cursor-pointer" onclick="viewActiveContactInfo()">
                    <div id="chatHeaderAvatar" class="w-10 h-10 rounded-full bg-wa-green text-white font-bold flex items-center justify-center text-sm shadow">
                        ?
                    </div>
                    <div>
                        <div id="chatHeaderTitle" class="font-bold text-sm text-gray-800">Nama Kontak</div>
                        <div id="chatHeaderSubtitle" class="text-xs text-wa-muted">online</div>
                    </div>
                </div>

                <!-- Chat Header Icons -->
                <div class="flex items-center gap-4 text-wa-muted text-lg">
                    <!-- Audio Call Button -->
                    <button onclick="startVoiceCall()" title="Panggilan Suara" class="hover:text-wa-green transition">
                        <i class="fa-solid fa-phone"></i>
                    </button>
                    <!-- In-Chat Search Button -->
                    <button onclick="toggleInChatSearch()" title="Cari Pesan di Chat Ini" class="hover:text-wa-green transition">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </button>
                    <!-- Chat Dropdown Menu -->
                    <div class="relative">
                        <button onclick="toggleDropdown('chatMenuDropdown')" title="Menu Obrolan" class="hover:text-wa-green transition">
                            <i class="fa-solid fa-ellipsis-vertical"></i>
                        </button>
                        <div id="chatMenuDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-xl border border-wa-border py-1 text-sm text-gray-700 z-50">
                            <button onclick="togglePinActiveChat()" id="btnPinChat" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center gap-2">
                                <i class="fa-solid fa-thumbtack text-wa-green w-4"></i> Sematkan Chat
                            </button>
                            <button onclick="toggleArchiveActiveChat()" id="btnArchiveChat" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center gap-2">
                                <i class="fa-solid fa-box-archive text-wa-green w-4"></i> Arsipkan Chat
                            </button>
                            <button onclick="toggleBlockActiveUser()" id="btnBlockUser" class="w-full text-left px-4 py-2 hover:bg-red-50 text-red-600 flex items-center gap-2">
                                <i class="fa-solid fa-ban w-4"></i> Blokir Kontak
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- In-Chat Search Bar (Initially Hidden) -->
            <div id="inChatSearchBar" class="hidden px-4 py-2 bg-white border-b border-wa-border flex items-center gap-3">
                <i class="fa-solid fa-magnifying-glass text-wa-muted text-xs"></i>
                <input type="text" id="inChatSearchInput" oninput="searchMessagesInChat(this.value)" placeholder="Cari pesan dalam percakapan..." class="flex-1 text-xs focus:outline-none">
                <button onclick="toggleInChatSearch()" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <!-- Messages Stream Area -->
            <div id="messagesContainer" class="flex-1 overflow-y-auto p-4 chat-wallpaper space-y-3">
                <!-- Messages Rendered Here Dynamically -->
            </div>

            <!-- Replying Message Snippet Preview -->
            <div id="replySnippetBar" class="hidden bg-gray-100 border-t border-wa-border px-4 py-2 flex items-center justify-between">
                <div class="border-l-4 border-wa-green pl-3 text-xs">
                    <div id="replySenderName" class="font-bold text-wa-green">Balas ke</div>
                    <div id="replyBodyText" class="text-gray-600 truncate max-w-md">Isi pesan</div>
                </div>
                <button onclick="cancelReply()" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <!-- Chat Input Footer -->
            <div class="min-h-16 px-4 py-2.5 bg-wa-hover flex items-end gap-3 border-t border-wa-border shrink-0 z-10">
                <!-- Attachment Button & Dropdown -->
                <div class="relative mb-2">
                    <button onclick="toggleDropdown('attachDropdown')" title="Lampirkan File" class="text-wa-muted hover:text-gray-700 text-xl transition">
                        <i class="fa-solid fa-paperclip"></i>
                    </button>
                    <div id="attachDropdown" class="hidden absolute bottom-12 left-0 w-44 bg-white rounded-lg shadow-xl border border-wa-border py-1 text-sm text-gray-700 z-50">
                        <label class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center gap-2.5 cursor-pointer">
                            <i class="fa-solid fa-image text-purple-500 w-4"></i> Foto / Gambar
                            <input type="file" id="imageInput" accept="image/*" onchange="handleImagePicked(this)" class="hidden">
                        </label>
                        <label class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center gap-2.5 cursor-pointer">
                            <i class="fa-solid fa-file text-blue-500 w-4"></i> Dokumen
                            <input type="file" id="docInput" onchange="handleDocPicked(this)" class="hidden">
                        </label>
                    </div>
                </div>

                <!-- Textarea Message Input -->
                <div class="flex-1 bg-white rounded-lg px-4 py-2 shadow-sm border border-wa-border flex items-center">
                    <textarea id="messageInput" rows="1" placeholder="Ketik pesan..." 
                              onkeydown="handleInputKeyDown(event)"
                              oninput="handleTypingEvent()"
                              class="w-full resize-none max-h-28 text-sm text-gray-800 focus:outline-none bg-transparent"></textarea>
                </div>

                <!-- Send Button -->
                <button onclick="sendMessage()" id="btnSend" title="Kirim Pesan" class="w-10 h-10 rounded-full bg-wa-green hover:bg-emerald-600 text-white flex items-center justify-center text-sm shadow transition mb-1 shrink-0">
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODALS ================= -->

<!-- 1. NEW CHAT MODAL -->
<div id="newChatModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-md overflow-hidden">
        <div class="px-5 py-4 bg-wa-green text-white font-bold flex items-center justify-between">
            <span>Mulai Chat Baru</span>
            <button onclick="closeModal('newChatModal')"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <div class="p-3 border-b border-wa-border bg-gray-50">
            <input type="text" oninput="filterNewChatUsers(this.value)" placeholder="Cari nama atau username..." class="w-full text-xs p-2 rounded border border-gray-300 focus:outline-none">
        </div>
        <div id="newChatUsersList" class="max-h-80 overflow-y-auto divide-y divide-gray-100">
            @foreach($otherUsers as $u)
            <div onclick="startChatWithUser({{ $u->id }})" class="p-3 hover:bg-gray-50 flex items-center gap-3 cursor-pointer transition">
                <div class="w-10 h-10 rounded-full bg-wa-green/20 text-wa-green font-bold flex items-center justify-center text-sm">
                    {{ strtoupper(substr($u->name, 0, 2)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-semibold text-sm text-gray-800">{{ $u->name }}</div>
                    <div class="text-xs text-wa-muted">@<span>{{ $u->username }}</span></div>
                </div>
                <button class="px-3 py-1 bg-wa-green text-white text-xs font-semibold rounded-full hover:bg-emerald-600">Chat</button>
            </div>
            @endforeach
        </div>
    </div>
</div>

<!-- 2. NEW GROUP MODAL -->
<div id="newGroupModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-md overflow-hidden">
        <div class="px-5 py-4 bg-wa-green text-white font-bold flex items-center justify-between">
            <span>Buat Grup Baru</span>
            <button onclick="closeModal('newGroupModal')"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <div class="p-4 space-y-4">
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Nama Grup</label>
                <input type="text" id="groupNameInput" placeholder="Contoh: Tim Dev ARTDEVATA" class="w-full text-sm p-2 rounded border border-gray-300 focus:outline-none focus:border-wa-green">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Pilih Anggota Grup:</label>
                <div class="max-h-56 overflow-y-auto border border-gray-200 rounded p-2 space-y-1">
                    @foreach($otherUsers as $u)
                    <label class="flex items-center gap-3 p-2 hover:bg-gray-50 rounded cursor-pointer">
                        <input type="checkbox" name="groupMembers" value="{{ $u->id }}" class="rounded text-wa-green focus:ring-wa-green">
                        <div class="text-xs font-medium text-gray-800">{{ $u->name }} <span class="text-gray-400">(@<span>{{ $u->username }}</span>)</span></div>
                    </label>
                    @endforeach
                </div>
            </div>
            <button onclick="createGroup()" class="w-full py-2 bg-wa-green hover:bg-emerald-600 text-white font-semibold rounded shadow text-sm">
                Buat Grup
            </button>
        </div>
    </div>
</div>

<!-- 3. STATUS / STORIES MODAL -->
<div id="statusModal" class="hidden fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4">
    <div class="bg-gray-900 rounded-xl shadow-2xl w-full max-w-lg h-[600px] flex flex-col overflow-hidden text-white relative">
        <div class="px-5 py-3.5 bg-gray-800 flex items-center justify-between border-b border-gray-700">
            <span class="font-bold text-sm"><i class="fa-solid fa-circle-notch mr-2 text-wa-green"></i>Pembaruan Status 24 Jam</span>
            <div class="flex items-center gap-3">
                <button onclick="openCreateStatusModal()" class="text-xs bg-wa-green px-2.5 py-1 rounded text-white font-semibold hover:bg-emerald-600">+ Buat Status</button>
                <button onclick="closeModal('statusModal')" class="text-gray-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
        </div>

        <div id="statusContentArea" class="flex-1 flex flex-col items-center justify-center p-6 text-center relative overflow-hidden">
            <!-- Dynamic Status View Container -->
            <div id="statusViewerEmpty" class="text-gray-400 text-xs">
                <i class="fa-solid fa-image text-4xl mb-3 text-gray-600"></i>
                <p>Klik salah satu status di daftar bawah untuk melihat cerita.</p>
            </div>
        </div>

        <!-- Statuses list footer -->
        <div class="h-28 bg-gray-800/90 border-t border-gray-700 p-2 overflow-x-auto flex items-center gap-3">
            @forelse($statuses as $st)
            <div onclick="viewSingleStatus({{ json_encode($st) }})" class="shrink-0 w-20 h-20 rounded-lg border-2 border-wa-green p-1 bg-gray-700 cursor-pointer flex flex-col items-center justify-center text-center hover:scale-105 transition">
                <div class="w-8 h-8 rounded-full bg-wa-green text-white font-bold text-xs flex items-center justify-center mb-1">
                    {{ strtoupper(substr($st->user->name ?? 'U', 0, 1)) }}
                </div>
                <div class="text-[10px] truncate w-full">{{ $st->user->name ?? 'Pengguna' }}</div>
            </div>
            @empty
            <div class="text-xs text-gray-400 text-center w-full">Belum ada pembaruan status 24 jam.</div>
            @endforelse
        </div>
    </div>
</div>

<!-- 3B. CREATE STATUS MODAL -->
<div id="createStatusModal" class="hidden fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4">
    <div class="bg-gray-900 text-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col">
        <div class="px-5 py-3.5 bg-gray-800 flex items-center justify-between border-b border-gray-700">
            <span class="font-bold text-sm"><i class="fa-solid fa-plus-circle text-wa-green mr-2"></i>Buat Pembaruan Status</span>
            <button onclick="closeModal('createStatusModal')" class="text-gray-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <div class="p-4 space-y-4">
            <!-- Type Selector Tabs -->
            <div class="flex border-b border-gray-700">
                <button type="button" onclick="setStatusTab('text')" id="tabBtnText" class="flex-1 py-2 text-xs font-semibold text-wa-green border-b-2 border-wa-green">Status Teks</button>
                <button type="button" onclick="setStatusTab('image')" id="tabBtnImage" class="flex-1 py-2 text-xs font-semibold text-gray-400 border-b-2 border-transparent">Status Foto</button>
            </div>

            <!-- Text Status Panel -->
            <div id="panelStatusText" class="space-y-3">
                <div>
                    <label class="block text-xs text-gray-300 mb-1">Teks Cerita</label>
                    <textarea id="statusTextInput" rows="4" maxlength="1000" placeholder="Ketik status Anda di sini..."
                              class="w-full bg-gray-800 border border-gray-700 rounded-lg p-3 text-sm text-white focus:outline-none focus:border-wa-green"></textarea>
                </div>
                <div>
                    <label class="block text-xs text-gray-300 mb-2">Pilih Warna Latar</label>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="setStatusBg('#00a884')" class="w-7 h-7 rounded-full bg-[#00a884] border-2 border-white focus:scale-110"></button>
                        <button type="button" onclick="setStatusBg('#128c7e')" class="w-7 h-7 rounded-full bg-[#128c7e] border border-gray-600 focus:scale-110"></button>
                        <button type="button" onclick="setStatusBg('#075e54')" class="w-7 h-7 rounded-full bg-[#075e54] border border-gray-600 focus:scale-110"></button>
                        <button type="button" onclick="setStatusBg('#1f2937')" class="w-7 h-7 rounded-full bg-[#1f2937] border border-gray-600 focus:scale-110"></button>
                        <button type="button" onclick="setStatusBg('#4f46e5')" class="w-7 h-7 rounded-full bg-[#4f46e5] border border-gray-600 focus:scale-110"></button>
                        <button type="button" onclick="setStatusBg('#9333ea')" class="w-7 h-7 rounded-full bg-[#9333ea] border border-gray-600 focus:scale-110"></button>
                        <button type="button" onclick="setStatusBg('#e11d48')" class="w-7 h-7 rounded-full bg-[#e11d48] border border-gray-600 focus:scale-110"></button>
                        <button type="button" onclick="setStatusBg('#d97706')" class="w-7 h-7 rounded-full bg-[#d97706] border border-gray-600 focus:scale-110"></button>
                    </div>
                </div>
            </div>

            <!-- Image Status Panel -->
            <div id="panelStatusImage" class="hidden space-y-3">
                <div>
                    <label class="block text-xs text-gray-300 mb-1">Pilih Gambar / Foto</label>
                    <input type="file" id="statusImageFileInput" accept="image/jpeg,image/png,image/webp,image/gif"
                           class="w-full text-xs text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-wa-green file:text-white hover:file:bg-emerald-600 cursor-pointer">
                </div>
                <div id="statusImagePreviewContainer" class="hidden relative rounded-lg overflow-hidden max-h-48 border border-gray-700 flex justify-center bg-black/50 p-1">
                    <img id="statusImagePreviewImg" src="" alt="Pratinjau Status" class="max-h-44 object-contain rounded">
                </div>
                <div>
                    <label class="block text-xs text-gray-300 mb-1">Keterangan / Caption (Opsional)</label>
                    <input type="text" id="statusImageCaption" placeholder="Tambahkan keterangan..."
                           class="w-full bg-gray-800 border border-gray-700 rounded-lg p-2.5 text-xs text-white focus:outline-none focus:border-wa-green">
                </div>
            </div>
        </div>
        <div class="px-5 py-3 bg-gray-800 flex justify-end gap-2 border-t border-gray-700">
            <button onclick="closeModal('createStatusModal')" class="px-3 py-1.5 text-xs text-gray-400 hover:text-white">Batal</button>
            <button onclick="submitNewStatus()" id="btnSubmitStatus" class="px-4 py-1.5 text-xs bg-wa-green text-white font-semibold rounded hover:bg-emerald-600 transition flex items-center gap-1.5">
                <i class="fa-solid fa-paper-plane"></i>
                <span>Bagikan Status</span>
            </button>
        </div>
    </div>
</div>

<!-- 4. FORWARD MESSAGE MODAL -->
<div id="forwardModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-md overflow-hidden">
        <div class="px-5 py-4 bg-wa-green text-white font-bold flex items-center justify-between">
            <span>Teruskan Pesan Ke...</span>
            <button onclick="closeModal('forwardModal')"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <div class="p-3 max-h-72 overflow-y-auto divide-y divide-gray-100" id="forwardConversationsList">
            <!-- Populated via JS -->
        </div>
        <div class="p-3 bg-gray-50 border-t border-gray-200 flex justify-end gap-2">
            <button onclick="closeModal('forwardModal')" class="px-3 py-1.5 text-xs text-gray-600 hover:bg-gray-200 rounded">Batal</button>
            <button onclick="submitForward()" class="px-4 py-1.5 text-xs bg-wa-green text-white font-semibold rounded hover:bg-emerald-600">Teruskan</button>
        </div>
    </div>
</div>

<!-- 5. AUDIO CALL OVERLAY MODAL -->
<div id="audioCallModal" class="hidden fixed inset-0 bg-black/75 z-50 flex items-center justify-center p-4">
    <div class="bg-gray-900 rounded-2xl shadow-2xl w-80 p-6 flex flex-col items-center text-center text-white relative">
        <div id="callAvatar" class="w-24 h-24 rounded-full bg-wa-green text-white font-bold text-3xl flex items-center justify-center mb-4 shadow-xl border-4 border-gray-800">
            ?
        </div>
        <h3 id="callName" class="text-lg font-bold mb-1">Agus Artdevata</h3>
        <p id="callStatusText" class="text-xs text-emerald-400 font-semibold mb-6 animate-pulse">Memanggil...</p>
        <div id="callDuration" class="text-sm font-mono text-gray-400 mb-8">00:00</div>

        <div class="flex items-center justify-center gap-6">
            <button onclick="toggleCallMute()" id="btnCallMute" class="w-12 h-12 rounded-full bg-gray-800 hover:bg-gray-700 text-white flex items-center justify-center text-lg">
                <i class="fa-solid fa-microphone"></i>
            </button>
            <button onclick="endVoiceCall()" class="w-14 h-14 rounded-full bg-red-600 hover:bg-red-700 text-white flex items-center justify-center text-xl shadow-lg">
                <i class="fa-solid fa-phone-slash"></i>
            </button>
        </div>
    </div>
</div>

<!-- 6. BLOCKED CONTACTS MODAL -->
<div id="blockedModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-md overflow-hidden">
        <div class="px-5 py-4 bg-wa-green text-white font-bold flex items-center justify-between">
            <span>Kontak Terblokir</span>
            <button onclick="closeModal('blockedModal')"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <div id="blockedUsersList" class="p-3 max-h-80 overflow-y-auto divide-y divide-gray-100">
            @forelse($blockedUsers as $bu)
            <div class="p-2.5 flex items-center justify-between">
                <div>
                    <div class="font-semibold text-xs text-gray-800">{{ $bu->name }}</div>
                    <div class="text-[11px] text-gray-400">@<span>{{ $bu->username }}</span></div>
                </div>
                <button onclick="unblockUser({{ $bu->id }}, '{{ $bu->name }}')" class="px-2.5 py-1 text-xs border border-wa-green text-wa-green rounded hover:bg-emerald-50 font-semibold">
                    Buka Blokir
                </button>
            </div>
            @empty
            <div class="p-6 text-center text-xs text-gray-400">Tidak ada kontak yang diblokir.</div>
            @endforelse
        </div>
    </div>
</div>

<!-- 7. STARRED MESSAGES MODAL -->
<div id="starredModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-md overflow-hidden">
        <div class="px-5 py-4 bg-amber-500 text-white font-bold flex items-center justify-between">
            <span><i class="fa-solid fa-star mr-2"></i>Pesan Berbintang</span>
            <button onclick="closeModal('starredModal')"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <div id="starredMessagesList" class="p-3 max-h-80 overflow-y-auto divide-y divide-gray-100 text-xs">
            <div class="p-6 text-center text-gray-400">Memuat pesan berbintang...</div>
        </div>
    </div>
</div>

<!-- 8. CALLS HISTORY MODAL -->
<div id="callsModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-md overflow-hidden">
        <div class="px-5 py-4 bg-emerald-600 text-white font-bold flex items-center justify-between">
            <span><i class="fa-solid fa-phone mr-2"></i>Riwayat Panggilan</span>
            <button onclick="closeModal('callsModal')"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <div class="p-2 max-h-80 overflow-y-auto divide-y divide-gray-100 text-xs">
            @forelse($callHistory as $call)
            @php
                $isOut = (int) $call->caller_id === (int) $currentUser->id;
                $other = $isOut ? $call->receiver : $call->caller;
            @endphp
            <div class="p-2.5 flex items-center justify-between hover:bg-gray-50">
                <div class="flex items-center gap-3">
                    <i class="fa-solid {{ $isOut ? 'fa-arrow-up-right text-emerald-500' : 'fa-arrow-down-left text-blue-500' }}"></i>
                    <div>
                        <div class="font-semibold text-gray-800">{{ $other->name ?? 'Pengguna' }}</div>
                        <div class="text-[11px] text-gray-400">{{ $call->created_at?->format('d M Y H:i') ?? '-' }} ({{ $call->duration ?? 0 }}s)</div>
                    </div>
                </div>
                <span class="px-2 py-0.5 rounded text-[10px] {{ $call->status == 'ended' ? 'bg-gray-100 text-gray-600' : 'bg-red-50 text-red-500' }}">
                    {{ ucfirst($call->status) }}
                </span>
            </div>
            @empty
            <div class="p-6 text-center text-gray-400">Belum ada riwayat panggilan.</div>
            @endforelse
        </div>
    </div>
</div>

<!-- 9. IMAGE LIGHTBOX MODAL -->
<div id="lightboxModal" class="hidden fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-4 cursor-pointer" onclick="closeModal('lightboxModal')">
    <img id="lightboxImg" src="" alt="Pratinjau Foto" class="max-w-[90vw] max-h-[90vh] object-contain rounded shadow-2xl">
</div>

@endsection

@push('scripts')
<script>
    // Global State
    const App = {
        currentUser: {!! json_encode($currentUser) !!},
        token: "{{ $token }}",
        activeConversationId: null,
        activeConversation: null,
        conversations: [],
        messages: [],
        currentFilter: 'all',
        replyingMessage: null,
        forwardingMessageId: null,
        pusher: null,
        currentConvChannel: null,
        currentConvChannelId: null,
        activeCall: null,
        callTimer: null,
        callDurationSeconds: 0,
    };

    // Initialize Web App
    document.addEventListener('DOMContentLoaded', () => {
        initReverb();
        loadConversations();
        
        // Auto-sync polling every 3 seconds
        setInterval(() => {
            if (App.activeConversationId) {
                syncActiveChatMessages();
            }
            loadConversations(true);
        }, 3000);
    });

    // Setup Reverb WebSocket Connection via Pusher JS
    function initReverb() {
        try {
            const reverbKey = "{{ config('broadcasting.connections.reverb.key') ?? env('REVERB_APP_KEY', 'lsln0nymfrhmfhidz2jl') }}";
            const reverbHost = "{{ config('broadcasting.connections.reverb.options.host') ?? env('REVERB_HOST', '127.0.0.1') }}";
            const reverbPort = {{ config('broadcasting.connections.reverb.options.port') ?? env('REVERB_PORT', 8080) }};

            App.pusher = new Pusher(reverbKey, {
                wsHost: reverbHost,
                wsPort: reverbPort,
                wssPort: reverbPort,
                forceTLS: false,
                enabledTransports: ['ws'],
                channelAuthorization: {
                    endpoint: '/api/broadcasting/auth',
                    headers: {
                        'Authorization': 'Bearer ' + (App.token || window.SANCTUM_TOKEN),
                        'Accept': 'application/json'
                    }
                },
                authEndpoint: '/api/broadcasting/auth',
                auth: {
                    headers: {
                        'Authorization': 'Bearer ' + (App.token || window.SANCTUM_TOKEN),
                        'Accept': 'application/json'
                    }
                }
            });

            // 1. User private channel (incoming calls, cross-chat notifications, etc.)
            const userChannel = App.pusher.subscribe('private-user.' + App.currentUser.id);

            userChannel.bind('call.incoming', (data) => {
                showIncomingCallModal(data.call);
            });

            userChannel.bind('message.sent', (data) => {
                handleIncomingMessage(data.message);
            });

            userChannel.bind('message.deleted', (data) => {
                handleMessageDeleted(data);
            });

            userChannel.bind('message.reaction', (data) => {
                handleMessageReaction(data);
            });

            // 2. Public statuses channel (real-time new status updates)
            const statusChannel = App.pusher.subscribe('statuses');
            statusChannel.bind('status.created', (data) => {
                handleIncomingStatus(data.status);
            });

            console.log('[Reverb] Real-time WebSocket connected as', App.currentUser.name);
        } catch (e) {
            console.warn('[Reverb] WebSocket connection failed, polling fallback active:', e);
        }
    }

    // Real-time Event Handlers
    function handleIncomingMessage(msg) {
        if (!msg) return;

        // If message belongs to active conversation
        if (Number(msg.conversation_id) === Number(App.activeConversationId)) {
            const exists = App.messages.some(m => Number(m.id) === Number(msg.id));
            if (!exists) {
                App.messages.push(msg);
                renderMessages();

                // If sent by someone else, mark as read
                if (Number(msg.sender_id) !== Number(App.currentUser.id)) {
                    fetch(`/api/conversations/${App.activeConversationId}/read`, {
                        method: 'POST',
                        headers: { 'Authorization': 'Bearer ' + (App.token || window.SANCTUM_TOKEN), 'Accept': 'application/json' }
                    });
                }
            }
        }

        // Always update sidebar conversation list
        loadConversations(true);
    }

    let typingTimer = null;
    function handleUserTyping(data) {
        if (!data || Number(data.user_id) === Number(App.currentUser.id)) return;
        if (Number(data.conversation_id) !== Number(App.activeConversationId)) return;

        const subtitle = document.getElementById('chatHeaderSubtitle');
        if (!subtitle) return;

        if (data.is_typing) {
            subtitle.innerText = 'sedang mengetik...';
            subtitle.className = 'text-xs text-wa-green font-semibold animate-pulse';

            clearTimeout(typingTimer);
            typingTimer = setTimeout(() => {
                const conv = App.activeConversation;
                subtitle.innerText = conv && conv.other_user && conv.other_user.is_online ? 'online' : '';
                subtitle.className = 'text-xs text-wa-muted';
            }, 3000);
        }
    }

    function handleMessagesRead(data) {
        if (!data || Number(data.conversation_id) !== Number(App.activeConversationId)) return;
        App.messages.forEach(m => {
            if (Number(m.sender_id) === Number(App.currentUser.id)) {
                m.is_read = true;
                m.read_at = data.read_at;
            }
        });
        renderMessages();
    }

    function handleMessageReaction(data) {
        if (!data) return;
        const msg = App.messages.find(m => Number(m.id) === Number(data.message_id));
        if (msg) {
            msg.reactions = data.reactions;
            renderMessages();
        }
    }

    function handleMessageDeleted(data) {
        if (!data) return;
        const msg = App.messages.find(m => Number(m.id) === Number(data.message_id));
        if (msg) {
            if (data.is_deleted_for_everyone) {
                msg.is_deleted_for_everyone = true;
                msg.body = 'Pesan ini telah dihapus';
                msg.attachment_url = null;
            } else if (Number(data.deleted_by_user_id) === Number(App.currentUser.id)) {
                App.messages = App.messages.filter(m => Number(m.id) !== Number(data.message_id));
            }
            renderMessages();
        }
    }

    function handleIncomingStatus(status) {
        if (!status) return;
        const btn = document.querySelector('button[title="Status / Cerita"]');
        if (btn && !btn.querySelector('.status-dot')) {
            const dot = document.createElement('span');
            dot.className = 'status-dot absolute -top-1 -right-1 w-2.5 h-2.5 bg-wa-green rounded-full border-2 border-white animate-ping';
            btn.appendChild(dot);
        }
    }

    // Load Conversations List
    async function loadConversations(isSilent = false) {
        try {
            const res = await fetch('/api/conversations', {
                headers: { 'Authorization': 'Bearer ' + (App.token || window.SANCTUM_TOKEN), 'Accept': 'application/json' }
            });
            const json = await res.json();
            if (json.data && json.data.conversations) {
                App.conversations = json.data.conversations;
                renderConversations();

                // Auto-select first conversation if none selected yet
                if (!App.activeConversationId && App.conversations.length > 0) {
                    selectConversation(App.conversations[0].id);
                }
            }
        } catch (e) {
            console.error('Failed to load conversations:', e);
        }
    }

    // Render Conversation List
    function renderConversations() {
        const container = document.getElementById('conversationsList');
        let filtered = App.conversations;

        if (App.currentFilter === 'unread') {
            filtered = filtered.filter(c => c.unread_count > 0);
        } else if (App.currentFilter === 'pinned') {
            filtered = filtered.filter(c => c.is_pinned);
        } else if (App.currentFilter === 'archived') {
            filtered = filtered.filter(c => c.is_archived);
        } else {
            filtered = filtered.filter(c => !c.is_archived);
        }

        if (filtered.length === 0) {
            container.innerHTML = `
                <div class="p-8 text-center text-wa-muted text-xs">
                    <i class="fa-solid fa-comments text-3xl mb-2 text-gray-300"></i>
                    <div>Tidak ada obrolan di tab ini.</div>
                </div>`;
            return;
        }

        let html = '';
        filtered.forEach(c => {
            const isActive = c.id === App.activeConversationId;
            const rawName = c.display_name || (c.other_user ? c.other_user.name : (c.name || 'Grup'));
            const displayName = escapeHtml(rawName);
            const initial = escapeHtml(rawName.substring(0, 2).toUpperCase());
            const rawMsg = c.last_message ? (c.last_message.body || (c.last_message.type === 'image' ? '📷 Foto' : '📎 Berkas')) : 'Belum ada pesan';
            const lastMsg = escapeHtml(rawMsg);
            const time = c.last_message ? formatTime(c.last_message.created_at) : '';
            const unread = c.unread_count > 0 ? `<span class="bg-wa-green text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full shrink-0">${c.unread_count}</span>` : '';
            const pinIcon = c.is_pinned ? `<i class="fa-solid fa-thumbtack text-wa-muted text-xs mr-1"></i>` : '';

            html += `
            <div onclick="selectConversation(${c.id})" 
                 class="px-3.5 py-3 flex items-center gap-3 cursor-pointer transition ${isActive ? 'bg-wa-hover' : 'hover:bg-gray-50'}">
                <div class="relative shrink-0">
                    <div class="w-12 h-12 rounded-full bg-wa-green/20 text-wa-green font-bold flex items-center justify-center text-sm">
                        ${initial}
                    </div>
                    ${!c.is_group && c.other_user && c.other_user.is_online ? '<span class="absolute bottom-0 right-0 w-3 h-3 bg-emerald-500 border-2 border-white rounded-full"></span>' : ''}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between mb-0.5">
                        <span class="font-bold text-sm text-gray-800 truncate">${displayName}</span>
                        <span class="text-[11px] text-wa-muted shrink-0">${time}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="text-xs text-wa-muted truncate">${lastMsg}</div>
                        <div class="flex items-center gap-1">
                            ${pinIcon}
                            ${unread}
                        </div>
                    </div>
                </div>
            </div>`;
        });

        container.innerHTML = html;
    }

    // Select and Open Conversation
    async function selectConversation(id) {
        App.activeConversationId = Number(id);
        App.activeConversation = App.conversations.find(c => Number(c.id) === Number(id));
        
        document.getElementById('blankState').classList.add('hidden');
        document.getElementById('activeChatView').classList.remove('hidden');

        // Update Header
        const conv = App.activeConversation;
        const name = conv ? (conv.display_name || (conv.other_user ? conv.other_user.name : (conv.name || 'Percakapan'))) : 'Obrolan';
        document.getElementById('chatHeaderTitle').innerText = name;
        document.getElementById('chatHeaderAvatar').innerText = name.substring(0, 2).toUpperCase();
        document.getElementById('chatHeaderSubtitle').innerText = conv && conv.other_user && conv.other_user.is_online ? 'online' : '';

        // Update Header Menu Text
        if (conv) {
            document.getElementById('btnPinChat').innerHTML = `<i class="fa-solid fa-thumbtack text-wa-green w-4"></i> ${conv.is_pinned ? 'Lepas Semat Chat' : 'Sematkan Chat'}`;
            document.getElementById('btnArchiveChat').innerHTML = `<i class="fa-solid fa-box-archive text-wa-green w-4"></i> ${conv.is_archived ? 'Buka Arsip Chat' : 'Arsipkan Chat'}`;
            if (conv.other_user) {
                document.getElementById('btnBlockUser').classList.remove('hidden');
                document.getElementById('btnBlockUser').innerHTML = `<i class="fa-solid fa-ban w-4"></i> ${conv.other_user.is_blocked ? 'Buka Blokir Kontak' : 'Blokir Kontak'}`;
            } else {
                document.getElementById('btnBlockUser').classList.add('hidden');
            }
        }

        renderConversations();
        await syncActiveChatMessages();
        
        // Switch conversation WebSocket channel
        if (App.currentConvChannel && App.currentConvChannelId !== Number(id)) {
            App.pusher.unsubscribe('private-conversation.' + App.currentConvChannelId);
            App.currentConvChannel = null;
        }

        if (App.pusher && App.currentConvChannelId !== Number(id)) {
            App.currentConvChannelId = Number(id);
            App.currentConvChannel = App.pusher.subscribe('private-conversation.' + id);

            App.currentConvChannel.bind('message.sent', (data) => {
                handleIncomingMessage(data.message);
            });

            App.currentConvChannel.bind('user.typing', (data) => {
                handleUserTyping(data);
            });

            App.currentConvChannel.bind('messages.read', (data) => {
                handleMessagesRead(data);
            });

            App.currentConvChannel.bind('message.reaction', (data) => {
                handleMessageReaction(data);
            });

            App.currentConvChannel.bind('message.deleted', (data) => {
                handleMessageDeleted(data);
            });
        }

        // Mark as read
        fetch(`/api/conversations/${id}/read`, {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + (App.token || window.SANCTUM_TOKEN), 'Accept': 'application/json' }
        });
    }

    // Sync Messages of Active Chat
    async function syncActiveChatMessages() {
        if (!App.activeConversationId) return;
        try {
            const res = await fetch(`/api/conversations/${App.activeConversationId}/messages`, {
                headers: { 'Authorization': 'Bearer ' + (App.token || window.SANCTUM_TOKEN), 'Accept': 'application/json' }
            });
            const json = await res.json();
            if (json.data && json.data.messages) {
                // Reverse so oldest at top, newest at bottom
                App.messages = [...json.data.messages].reverse();
                renderMessages();
            }
        } catch (e) {
            console.error('Failed to sync messages:', e);
        }
    }

    // Render Messages Stream
    function renderMessages() {
        const container = document.getElementById('messagesContainer');
        if (App.messages.length === 0) {
            container.innerHTML = `
                <div class="h-full flex items-center justify-center text-center text-wa-muted text-xs">
                    <div class="bg-white/80 backdrop-blur-sm px-4 py-2 rounded-lg shadow-sm border border-wa-border">
                        Belum ada pesan. Mulai obrolan sekarang!
                    </div>
                </div>`;
            return;
        }

        let html = '';
        App.messages.forEach(m => {
            const isMine = m.sender_id === App.currentUser.id;
            const isDeleted = m.is_deleted_for_everyone;
            const time = formatTime(m.created_at);
            const ticks = isMine ? (m.is_read ? '<i class="fa-solid fa-check-double text-sky-500 text-[10px]"></i>' : '<i class="fa-solid fa-check text-gray-400 text-[10px]"></i>') : '';

            // Forward badge
            const forwardBadge = m.is_forwarded ? `
                <div class="text-[11px] italic text-gray-500 mb-1 flex items-center gap-1">
                    <i class="fa-solid fa-reply scale-x-[-1] text-xs"></i> Diteruskan
                </div>` : '';

            // Reply snippet
            let replySnippet = '';
            if (m.reply_to) {
                replySnippet = `
                <div class="bg-black/5 border-l-4 border-wa-green p-1.5 rounded text-xs mb-1">
                    <div class="font-bold text-wa-green">${escapeHtml(m.reply_to.sender_name || '')}</div>
                    <div class="text-gray-600 truncate">${escapeHtml(m.reply_to.body || '')}</div>
                </div>`;
            }

            // Media Preview
            let mediaContent = '';
            if (m.attachment_url) {
                const mediaUrl = normalizeMediaUrl(m.attachment_url);
                if (m.type === 'image') {
                    mediaContent = `
                    <div class="mb-1 rounded overflow-hidden cursor-pointer" onclick="openLightbox('${mediaUrl}')">
                        <img src="${mediaUrl}" class="max-h-64 object-cover rounded hover:opacity-95 transition" onerror="this.onerror=null; if(!this.src.includes('/storage/')) this.src='/storage/' + '${m.attachment_url}'.replace(/^https?:\\/\\/[^\\/]+\\/storage\\//, '');">
                    </div>`;
                } else if (m.type === 'voice') {
                    mediaContent = `
                    <div class="mb-1 p-2 bg-black/5 rounded flex items-center gap-2">
                        <audio controls class="h-8 w-56">
                            <source src="${mediaUrl}" type="audio/mpeg">
                        </audio>
                    </div>`;
                } else {
                    mediaContent = `
                    <a href="${mediaUrl}" target="_blank" download class="mb-1 p-2 bg-black/5 rounded flex items-center gap-3 hover:bg-black/10 transition">
                        <i class="fa-solid fa-file-lines text-2xl text-wa-green"></i>
                        <div class="truncate text-xs">
                            <div class="font-bold truncate">${escapeHtml(m.file_name || 'Dokumen')}</div>
                            <div class="text-[10px] text-gray-500">Klik untuk mengunduh</div>
                        </div>
                    </a>`;
                }
            }

            // Reactions Pills
            let reactionsHtml = '';
            if (m.reactions && m.reactions.length > 0) {
                reactionsHtml = '<div class="flex items-center gap-1 mt-1 -mb-1">';
                m.reactions.forEach(r => {
                    reactionsHtml += `
                    <span onclick="toggleReaction(${m.id}, '${r.emoji}')" class="text-xs bg-white/90 border border-wa-border px-1.5 py-0.5 rounded-full shadow-sm cursor-pointer hover:scale-110 transition">
                        ${r.emoji} <span class="text-[10px] text-gray-600 font-bold">${r.count}</span>
                    </span>`;
                });
                reactionsHtml += '</div>';
            }

            // Message Bubble
            html += `
            <div class="flex ${isMine ? 'justify-end' : 'justify-start'} group">
                <div class="relative max-w-[75%] md:max-w-[65%] rounded-lg px-3 py-1.5 text-sm shadow-sm ${isMine ? 'bg-wa-sent text-gray-900 rounded-tr-none' : 'bg-white text-gray-900 rounded-tl-none'}">
                    ${forwardBadge}
                    ${replySnippet}
                    ${mediaContent}

                    <div class="${isDeleted ? 'italic text-gray-500' : 'text-gray-800 break-words'}">
                        ${isDeleted ? '<i class="fa-solid fa-ban text-xs mr-1"></i> Pesan ini telah dihapus' : escapeHtml(m.body || '')}
                    </div>

                    <div class="flex items-center justify-end gap-1 mt-1 text-[10px] text-wa-muted select-none">
                        <span>${time}</span>
                        ${ticks}
                    </div>

                    ${reactionsHtml}

                    <!-- Hover Action Bar -->
                    <div class="hidden group-hover:flex absolute -top-8 ${isMine ? 'right-0' : 'left-0'} bg-white border border-wa-border rounded-full shadow-lg px-2 py-1 items-center gap-2 text-xs text-gray-600 z-10">
                        <button onclick="toggleReaction(${m.id}, '👍')" title="Suka">👍</button>
                        <button onclick="toggleReaction(${m.id}, '❤️')" title="Hati">❤️</button>
                        <button onclick="toggleReaction(${m.id}, '😂')" title="Tertawa">😂</button>
                        <button onclick="toggleReaction(${m.id}, '😮')" title="Kaget">😮</button>
                        <button onclick="toggleReaction(${m.id}, '😢')" title="Sedih">😢</button>
                        <button onclick="toggleReaction(${m.id}, '🙏')" title="Doa">🙏</button>
                        <span class="text-gray-300">|</span>
                        <button onclick="prepareReply(${m.id})" title="Balas"><i class="fa-solid fa-reply"></i></button>
                        <button onclick="openForwardModal(${m.id})" title="Teruskan"><i class="fa-solid fa-share"></i></button>
                        <button onclick="toggleStarMessage(${m.id})" title="Bintang"><i class="fa-solid fa-star text-amber-500"></i></button>
                        <button onclick="deleteMessagePrompt(${m.id}, ${isMine})" title="Hapus"><i class="fa-solid fa-trash text-red-500"></i></button>
                    </div>
                </div>
            </div>`;
        });

        container.innerHTML = html;
        container.scrollTop = container.scrollHeight;
    }

    // Send Message
    async function sendMessage() {
        const input = document.getElementById('messageInput');
        const text = input.value.trim();
        if (!text) return;

        if (!App.activeConversationId) {
            alert('Silakan pilih salah satu obrolan di sebelah kiri terlebih dahulu.');
            return;
        }

        const originalText = text;
        input.value = '';
        input.style.height = 'auto';

        const payload = {
            body: text,
            reply_to_id: App.replyingMessage ? App.replyingMessage.id : null
        };
        cancelReply();

        try {
            const token = App.token || window.SANCTUM_TOKEN;
            const res = await fetch(`/api/conversations/${App.activeConversationId}/messages`, {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': window.CSRF_TOKEN || ''
                },
                body: JSON.stringify(payload)
            });
            const json = await res.json();
            if (res.ok && json.data && json.data.message) {
                App.messages.push(json.data.message);
                renderMessages();
                loadConversations(true);
            } else {
                input.value = originalText;
                alert('Gagal mengirim pesan: ' + (json.message || 'Terjadi kesalahan'));
            }
        } catch (e) {
            input.value = originalText;
            console.error('Failed to send message:', e);
            alert('Gagal mengirim pesan: ' + e);
        }
    }

    // Handle Input Keys (Enter to send, Shift+Enter for new line)
    function handleInputKeyDown(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    }

    // Handle Image Upload
    async function handleImagePicked(input) {
        toggleDropdown('attachDropdown');
        if (!input.files || input.files.length === 0) return;
        if (!App.activeConversationId) {
            alert('Silakan pilih obrolan di sebelah kiri terlebih dahulu.');
            input.value = '';
            return;
        }

        const file = input.files[0];
        const formData = new FormData();
        formData.append('attachment', file);
        formData.append('file_name', file.name);

        try {
            const token = App.token || window.SANCTUM_TOKEN;
            const res = await fetch(`/api/conversations/${App.activeConversationId}/messages`, {
                method: 'POST',
                headers: { 
                    'Authorization': 'Bearer ' + token, 
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': window.CSRF_TOKEN || ''
                },
                body: formData
            });
            const json = await res.json();
            if (res.ok && json.data && json.data.message) {
                App.messages.push(json.data.message);
                renderMessages();
                loadConversations(true);
            } else {
                alert('Gagal mengirim gambar: ' + (json.message || 'Terjadi kesalahan'));
            }
        } catch (e) {
            alert('Gagal mengirim gambar: ' + e);
        }
        input.value = '';
    }

    // Handle Document Upload
    async function handleDocPicked(input) {
        toggleDropdown('attachDropdown');
        if (!input.files || input.files.length === 0) return;
        if (!App.activeConversationId) {
            alert('Silakan pilih obrolan di sebelah kiri terlebih dahulu.');
            input.value = '';
            return;
        }

        const file = input.files[0];
        const formData = new FormData();
        formData.append('attachment', file);
        formData.append('file_name', file.name);

        try {
            const token = App.token || window.SANCTUM_TOKEN;
            const res = await fetch(`/api/conversations/${App.activeConversationId}/messages`, {
                method: 'POST',
                headers: { 
                    'Authorization': 'Bearer ' + token, 
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': window.CSRF_TOKEN || ''
                },
                body: formData
            });
            const json = await res.json();
            if (res.ok && json.data && json.data.message) {
                App.messages.push(json.data.message);
                renderMessages();
                loadConversations(true);
            } else {
                alert('Gagal mengirim dokumen: ' + (json.message || 'Terjadi kesalahan'));
            }
        } catch (e) {
            alert('Gagal mengirim dokumen: ' + e);
        }
        input.value = '';
    }

    // Toggle Reaction
    async function toggleReaction(messageId, emoji) {
        try {
            await fetch(`/api/messages/${messageId}/reactions`, {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + App.token,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ emoji })
            });
            syncActiveChatMessages();
        } catch (e) {
            console.error(e);
        }
    }

    // Quoted Reply
    function prepareReply(messageId) {
        const msg = App.messages.find(m => m.id === messageId);
        if (!msg) return;
        App.replyingMessage = msg;
        document.getElementById('replySenderName').innerText = msg.sender ? msg.sender.name : 'Pengguna';
        document.getElementById('replyBodyText').innerText = msg.body || 'Lampiran Berkas';
        document.getElementById('replySnippetBar').classList.remove('hidden');
        document.getElementById('messageInput').focus();
    }

    function cancelReply() {
        App.replyingMessage = null;
        document.getElementById('replySnippetBar').classList.add('hidden');
    }

    // Star Message
    async function toggleStarMessage(messageId) {
        try {
            await fetch(`/api/messages/${messageId}/star`, {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + App.token, 'Accept': 'application/json' }
            });
            alert('Status bintang pesan berhasil diperbarui.');
        } catch (e) {
            console.error(e);
        }
    }

    // Delete Message
    async function deleteMessagePrompt(messageId, isMine) {
        const forEveryone = isMine && confirm('Hapus untuk semua orang di chat ini? (Batal = Hanya untuk saya)');
        try {
            await fetch(`/api/conversations/${App.activeConversationId}/messages/${messageId}`, {
                method: 'DELETE',
                headers: {
                    'Authorization': 'Bearer ' + App.token,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ delete_type: forEveryone ? 'for_everyone' : 'for_me' })
            });
            syncActiveChatMessages();
        } catch (e) {
            console.error(e);
        }
    }

    // Forward Message
    function openForwardModal(messageId) {
        App.forwardingMessageId = messageId;
        const container = document.getElementById('forwardConversationsList');
        let html = '';
        App.conversations.forEach(c => {
            const name = c.display_name || (c.other_user ? c.other_user.name : (c.name || 'Grup'));
            html += `
            <label class="flex items-center gap-3 p-2 hover:bg-gray-50 rounded cursor-pointer">
                <input type="checkbox" name="forwardTarget" value="${c.id}" class="rounded text-wa-green focus:ring-wa-green">
                <div class="text-xs font-semibold text-gray-800">${name}</div>
            </label>`;
        });
        container.innerHTML = html;
        openModal('forwardModal');
    }

    async function submitForward() {
        const checked = Array.from(document.querySelectorAll('input[name="forwardTarget"]:checked')).map(cb => parseInt(cb.value));
        if (checked.length === 0) {
            alert('Pilih setidaknya satu percakapan tujuan.');
            return;
        }

        try {
            await fetch('/api/messages/forward', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + App.token,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    message_id: App.forwardingMessageId,
                    conversation_ids: checked
                })
            });
            closeModal('forwardModal');
            alert('Pesan berhasil diteruskan!');
            loadConversations(true);
            if (checked.includes(App.activeConversationId)) {
                syncActiveChatMessages();
            }
        } catch (e) {
            alert('Gagal meneruskan pesan: ' + e);
        }
    }

    // Start 1-on-1 Chat With User
    async function startChatWithUser(userId) {
        closeModal('newChatModal');
        try {
            const res = await fetch('/api/conversations', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + App.token,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ user_id: userId })
            });
            const json = await res.json();
            if (json.data && json.data.conversation) {
                await loadConversations();
                selectConversation(json.data.conversation.id);
            }
        } catch (e) {
            alert('Gagal memulai obrolan: ' + e);
        }
    }

    // Create Group Chat
    async function createGroup() {
        const name = document.getElementById('groupNameInput').value.trim();
        const members = Array.from(document.querySelectorAll('input[name="groupMembers"]:checked')).map(cb => parseInt(cb.value));
        if (!name) {
            alert('Silakan masukkan nama grup.');
            return;
        }

        try {
            const res = await fetch('/api/groups', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + App.token,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ name, members })
            });
            const json = await res.json();
            if (json.data && json.data.conversation) {
                closeModal('newGroupModal');
                await loadConversations();
                selectConversation(json.data.conversation.id);
            }
        } catch (e) {
            alert('Gagal membuat grup: ' + e);
        }
    }

    // Pin / Unpin Active Chat
    async function togglePinActiveChat() {
        toggleDropdown('chatMenuDropdown');
        if (!App.activeConversationId) return;
        try {
            await fetch(`/api/conversations/${App.activeConversationId}/pin`, {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + App.token, 'Accept': 'application/json' }
            });
            await loadConversations();
            selectConversation(App.activeConversationId);
        } catch (e) {
            console.error(e);
        }
    }

    // Archive / Unarchive Active Chat
    async function toggleArchiveActiveChat() {
        toggleDropdown('chatMenuDropdown');
        if (!App.activeConversationId) return;
        try {
            await fetch(`/api/conversations/${App.activeConversationId}/archive`, {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + App.token, 'Accept': 'application/json' }
            });
            await loadConversations();
            selectConversation(App.activeConversationId);
        } catch (e) {
            console.error(e);
        }
    }

    // Block / Unblock Active User
    async function toggleBlockActiveUser() {
        toggleDropdown('chatMenuDropdown');
        const conv = App.activeConversation;
        if (!conv || !conv.other_user) return;
        const target = conv.other_user;
        const endpoint = target.is_blocked ? `/api/users/${target.id}/unblock` : `/api/users/${target.id}/block`;
        if (!confirm(`${target.is_blocked ? 'Buka blokir' : 'Blokir'} ${target.name}?`)) return;

        try {
            await fetch(endpoint, {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + App.token, 'Accept': 'application/json' }
            });
            alert(`Kontak ${target.name} berhasil ${target.is_blocked ? 'dibuka blokirnya' : 'diblokir'}.`);
            await loadConversations();
            selectConversation(App.activeConversationId);
        } catch (e) {
            alert('Terjadi kesalahan: ' + e);
        }
    }

    // Voice Calling Simulation
    function startVoiceCall() {
        const conv = App.activeConversation;
        if (!conv || !conv.other_user) {
            alert('Panggilan suara hanya tersedia untuk obrolan privat.');
            return;
        }

        const other = conv.other_user;
        document.getElementById('callName').innerText = other.name;
        document.getElementById('callAvatar').innerText = other.name.substring(0, 2).toUpperCase();
        document.getElementById('callStatusText').innerText = 'Menghubungkan...';
        openModal('audioCallModal');

        // Start Call Timer
        App.callDurationSeconds = 0;
        clearInterval(App.callTimer);
        App.callTimer = setInterval(() => {
            App.callDurationSeconds++;
            const m = String(Math.floor(App.callDurationSeconds / 60)).padStart(2, '0');
            const s = String(App.callDurationSeconds % 60).padStart(2, '0');
            document.getElementById('callDuration').innerText = `${m}:${s}`;
        }, 1000);

        // Notify API
        fetch('/api/calls', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + App.token,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                receiver_id: other.id,
                type: 'audio',
                conversation_id: App.activeConversationId
            })
        });
    }

    function endVoiceCall() {
        clearInterval(App.callTimer);
        closeModal('audioCallModal');
    }

    function toggleCallMute() {
        const btn = document.getElementById('btnCallMute');
        btn.classList.toggle('bg-red-600');
    }

    // UI Utilities & Filters
    function setFilter(filter) {
        App.currentFilter = filter;
        document.querySelectorAll('.filter-pill').forEach(el => {
            el.className = 'filter-pill px-3 py-1 rounded-full font-semibold text-gray-600 hover:bg-gray-200';
        });
        document.getElementById('filter-' + filter).className = 'filter-pill px-3 py-1 rounded-full font-semibold bg-wa-green text-white';
        renderConversations();
    }

    function filterConversations(keyword) {
        keyword = keyword.toLowerCase().trim();
        const items = document.querySelectorAll('#conversationsList > div');
        items.forEach(el => {
            const text = el.innerText.toLowerCase();
            el.style.display = text.includes(keyword) ? 'flex' : 'none';
        });
    }

    function toggleDropdown(id) {
        const el = document.getElementById(id);
        el.classList.toggle('hidden');
    }

    function openModal(id) {
        document.getElementById(id).classList.remove('hidden');
    }

    function closeModal(id) {
        document.getElementById(id).classList.add('hidden');
    }

    function openNewChatModal() { openModal('newChatModal'); }
    function openNewGroupModal() { openModal('newGroupModal'); }
    function openStatusModal() { openModal('statusModal'); }
    function openBlockedModal() { toggleDropdown('sidebarMenuDropdown'); openModal('blockedModal'); }
    function openStarredModal() { toggleDropdown('sidebarMenuDropdown'); openModal('starredModal'); loadStarredMessages(); }
    function openCallsModal() { toggleDropdown('sidebarMenuDropdown'); openModal('callsModal'); }

    function openLightbox(url) {
        document.getElementById('lightboxImg').src = url;
        openModal('lightboxModal');
    }

    function formatTime(isoStr) {
        if (!isoStr) return '';
        const d = new Date(isoStr);
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;')
            .replace(/\n/g, '<br>');
    }

    function normalizeMediaUrl(url) {
        if (!url) return '';
        if (/^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/storage\/(.+)$/i.test(url)) {
            return window.location.origin + '/storage/' + url.replace(/^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\/storage\//i, '');
        }
        return url;
    }

    async function loadStarredMessages() {
        try {
            const res = await fetch('/api/messages/starred', {
                headers: { 'Authorization': 'Bearer ' + (App.token || window.SANCTUM_TOKEN), 'Accept': 'application/json' }
            });
            const json = await res.json();
            const container = document.getElementById('starredMessagesList');
            if (json.data && json.data.messages && json.data.messages.length > 0) {
                let html = '';
                json.data.messages.forEach(m => {
                    html += `
                    <div class="p-2.5 hover:bg-gray-50">
                        <div class="font-bold text-wa-green mb-0.5">${escapeHtml(m.sender ? m.sender.name : 'Pengguna')}</div>
                        <div class="text-gray-800">${escapeHtml(m.body || '')}</div>
                        <div class="text-[10px] text-gray-400 mt-1">${formatTime(m.created_at)}</div>
                    </div>`;
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="p-6 text-center text-gray-400">Belum ada pesan yang dibintangi.</div>';
            }
        } catch (e) {
            console.error(e);
        }
    }

    function handleTypingEvent() {
        if (!App.activeConversationId) return;
        fetch(`/api/conversations/${App.activeConversationId}/typing`, {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + App.token,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ is_typing: true })
        });
    }

    let currentStatusType = 'text';
    let currentStatusBg = '#00a884';

    function openCreateStatusModal() {
        closeModal('statusModal');
        openModal('createStatusModal');

        const fileInput = document.getElementById('statusImageFileInput');
        if (fileInput && !fileInput.dataset.listenerAttached) {
            fileInput.dataset.listenerAttached = 'true';
            fileInput.addEventListener('change', function() {
                const file = this.files[0];
                const previewCont = document.getElementById('statusImagePreviewContainer');
                const previewImg = document.getElementById('statusImagePreviewImg');
                if (file) {
                    previewImg.src = URL.createObjectURL(file);
                    previewCont.classList.remove('hidden');
                } else {
                    previewCont.classList.add('hidden');
                }
            });
        }
    }

    function setStatusTab(type) {
        currentStatusType = type;
        const btnText = document.getElementById('tabBtnText');
        const btnImage = document.getElementById('tabBtnImage');
        const pText = document.getElementById('panelStatusText');
        const pImage = document.getElementById('panelStatusImage');

        if (type === 'text') {
            btnText.className = 'flex-1 py-2 text-xs font-semibold text-wa-green border-b-2 border-wa-green';
            btnImage.className = 'flex-1 py-2 text-xs font-semibold text-gray-400 border-b-2 border-transparent';
            pText.classList.remove('hidden');
            pImage.classList.add('hidden');
        } else {
            btnImage.className = 'flex-1 py-2 text-xs font-semibold text-wa-green border-b-2 border-wa-green';
            btnText.className = 'flex-1 py-2 text-xs font-semibold text-gray-400 border-b-2 border-transparent';
            pImage.classList.remove('hidden');
            pText.classList.add('hidden');
        }
    }

    function setStatusBg(color) {
        currentStatusBg = color;
    }

    async function submitNewStatus() {
        const btn = document.getElementById('btnSubmitStatus');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mengirim...';

        try {
            const formData = new FormData();
            formData.append('type', currentStatusType);

            if (currentStatusType === 'text') {
                const text = document.getElementById('statusTextInput').value.trim();
                if (!text) {
                    alert('Harap masukkan isi status teks.');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Bagikan Status';
                    return;
                }
                formData.append('content', text);
                formData.append('background_color', currentStatusBg);
            } else {
                const fileInput = document.getElementById('statusImageFileInput');
                if (!fileInput.files || !fileInput.files[0]) {
                    alert('Harap pilih foto untuk diunggah.');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Bagikan Status';
                    return;
                }
                formData.append('media', fileInput.files[0]);
                const caption = document.getElementById('statusImageCaption').value.trim();
                if (caption) {
                    formData.append('content', caption);
                }
            }

            const token = App.token || window.SANCTUM_TOKEN;
            const res = await fetch('/api/statuses', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': window.CSRF_TOKEN || ''
                },
                body: formData
            });

            const data = await res.json();
            if (res.ok && data.success) {
                closeModal('createStatusModal');
                document.getElementById('statusTextInput').value = '';
                document.getElementById('statusImageFileInput').value = '';
                document.getElementById('statusImageCaption').value = '';
                const previewCont = document.getElementById('statusImagePreviewContainer');
                if (previewCont) previewCont.classList.add('hidden');
                alert('Status baru berhasil dibagikan!');
                window.location.reload();
            } else {
                let errorMsg = data.message || 'Gagal membagikan status.';
                if (data.errors) {
                    const first = Object.values(data.errors).flat()[0];
                    if (first) errorMsg = first;
                }
                alert(errorMsg);
            }
        } catch (e) {
            console.error(e);
            alert('Terjadi kesalahan saat membagikan status.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Bagikan Status';
        }
    }

    function viewSingleStatus(status) {
        const container = document.getElementById('statusContentArea');
        const caption = escapeHtml(status.content || '');
        const author = escapeHtml(status.user ? status.user.name : '');
        if (status.type === 'image' && status.media_url) {
            const mediaUrl = normalizeMediaUrl(status.media_url);
            container.innerHTML = `
                <div class="w-full h-full flex flex-col items-center justify-center">
                    <img src="${mediaUrl}" class="max-h-96 object-contain rounded mb-3 shadow" onerror="this.onerror=null; if(!this.src.includes('/storage/')) this.src='/storage/' + '${status.media_url}'.replace(/^https?:\\/\\/[^\\/]+\\/storage\\//, '');">
                    <p class="text-sm font-semibold">${caption}</p>
                    <div class="text-[11px] text-gray-400 mt-1">${author}</div>
                </div>`;
        } else {
            const bg = status.background_color || '#00a884';
            container.innerHTML = `
                <div class="w-full h-full flex flex-col items-center justify-center p-8 rounded-lg shadow-inner" style="background-color: ${bg}">
                    <p class="text-2xl font-bold max-w-sm">${caption}</p>
                    <div class="text-xs text-white/80 mt-4">${author}</div>
                </div>`;
        }
    }
</script>
@endpush
