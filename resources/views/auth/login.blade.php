@extends('layouts.web')

@section('title', 'Login - ARTDEVATA Web')

@section('content')
<div class="fixed top-0 left-0 w-full h-[220px] bg-wa-green -z-10"></div>

<div class="w-full max-w-4xl bg-white rounded-lg shadow-2xl overflow-hidden flex flex-col md:flex-row my-auto">
    <!-- Left Section: Information & Quick Switcher -->
    <div class="w-full md:w-1/2 p-8 border-b md:border-b-0 md:border-r border-wa-border flex flex-col justify-between">
        <div>
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-full bg-wa-green text-white flex items-center justify-center text-xl shadow">
                    <i class="fa-brands fa-whatsapp"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-800 tracking-wide">ARTDEVATA WEB</h1>
                    <p class="text-xs text-wa-muted">Sistem Chat Real-Time Berbasis Laravel Reverb</p>
                </div>
            </div>

            <h2 class="text-lg font-semibold text-gray-800 mb-2">Masuk ke Obrolan Anda</h2>
            <p class="text-sm text-wa-muted mb-6">
                Pilih pengguna di bawah untuk login instan (1-Klik) atau gunakan formulir di samping.
            </p>

            <!-- Quick Login Cards -->
            <div class="space-y-3 mb-6">
                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                    <i class="fa-solid fa-bolt text-yellow-500 mr-1"></i> Quick Login (1-Klik):
                </div>
                @foreach($users as $u)
                <a href="{{ route('web.quick-login', $u->id) }}" 
                   class="flex items-center gap-3 p-3 rounded-lg border border-wa-border hover:border-wa-green hover:bg-emerald-50/50 transition duration-150 group">
                    <div class="w-11 h-11 rounded-full bg-wa-green/20 text-wa-green font-bold flex items-center justify-center text-base shrink-0 group-hover:bg-wa-green group-hover:text-white transition">
                        {{ strtoupper(substr($u->name, 0, 2)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-sm text-gray-800 group-hover:text-wa-green transition truncate">{{ $u->name }}</div>
                        <div class="text-xs text-wa-muted">@<span>{{ $u->username }}</span> &bull; {{ $u->email }}</div>
                    </div>
                    <div class="text-xs font-medium text-wa-green px-2.5 py-1 bg-wa-green/10 rounded-full group-hover:bg-wa-green group-hover:text-white transition">
                        Masuk &rarr;
                    </div>
                </a>
                @endforeach
            </div>
        </div>

        <div class="pt-4 border-t border-wa-border flex items-center justify-between text-xs text-wa-muted">
            <span><i class="fa-solid fa-lock text-wa-green mr-1"></i> Terenkripsi End-to-End</span>
            <span>Laravel 12 &bull; Reverb</span>
        </div>
    </div>

    <!-- Right Section: Credentials Form -->
    <div class="w-full md:w-1/2 p-8 bg-gray-50/60 flex flex-col justify-center">
        <h3 class="text-base font-bold text-gray-800 mb-1">Login dengan Akun</h3>
        <p class="text-xs text-wa-muted mb-5">Masukkan email atau username Anda beserta kata sandi.</p>

        @if($errors->any())
        <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded-lg flex items-center gap-2">
            <i class="fa-solid fa-circle-exclamation text-base"></i>
            <div>{{ $errors->first() }}</div>
        </div>
        @endif

        <form action="{{ route('web.login.submit') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="block text-xs font-semibold text-gray-700 mb-1">Email / Username</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <i class="fa-solid fa-user text-sm"></i>
                    </span>
                    <input type="text" name="email" id="email" value="{{ old('email', 'agus@artdevata.id') }}" required
                           class="w-full pl-9 pr-3 py-2 text-sm border border-wa-border rounded-lg focus:outline-none focus:border-wa-green focus:ring-1 focus:ring-wa-green bg-white"
                           placeholder="nama@artdevata.id atau username">
                </div>
            </div>

            <div>
                <label for="password" class="block text-xs font-semibold text-gray-700 mb-1">Kata Sandi</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <i class="fa-solid fa-lock text-sm"></i>
                    </span>
                    <input type="password" name="password" id="password" value="Password123" required
                           class="w-full pl-9 pr-3 py-2 text-sm border border-wa-border rounded-lg focus:outline-none focus:border-wa-green focus:ring-1 focus:ring-wa-green bg-white"
                           placeholder="Kata sandi akun">
                </div>
                <div class="text-[11px] text-wa-muted mt-1">Kata sandi akun default: <code class="bg-gray-200 px-1 py-0.5 rounded text-gray-700">Password123</code></div>
            </div>

            <div class="flex items-center justify-between text-xs pt-1">
                <label class="flex items-center gap-2 text-gray-600 cursor-pointer">
                    <input type="checkbox" name="remember" value="1" checked class="rounded border-gray-300 text-wa-green focus:ring-wa-green">
                    <span>Ingat saya di browser ini</span>
                </label>
            </div>

            <button type="submit" 
                    class="w-full py-2.5 px-4 bg-wa-green hover:bg-emerald-600 text-white font-semibold rounded-lg shadow transition duration-150 flex items-center justify-center gap-2 text-sm">
                <span>Masuk ke Chat</span>
                <i class="fa-solid fa-arrow-right text-xs"></i>
            </button>
        </form>
    </div>
</div>
@endsection
