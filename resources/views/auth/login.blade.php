<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Si Revita Situbondo</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; } </style>
</head>
<body class="min-h-full flex items-center justify-center p-4 sm:p-6 lg:p-8 bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950 text-slate-100">

    <div class="w-full max-w-4xl grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
        
        <!-- Left Hero Column -->
        <div class="lg:col-span-6 space-y-6 text-center lg:text-left">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-semibold">
                <i data-lucide="map-pin" class="w-3.5 h-3.5"></i>
                Pemerintah Kabupaten Situbondo
            </div>
            
            <div class="space-y-2">
                <div class="flex items-center justify-center lg:justify-start gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-400 to-teal-600 flex items-center justify-center shadow-lg shadow-emerald-500/30">
                        <i data-lucide="landmark" class="w-7 h-7 text-white"></i>
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight">
                        Si Revita
                    </h1>
                </div>
                <p class="text-sm font-semibold text-emerald-400">
                    Sistem Revitalisasi dan Pelaporan Keuangan Sekota Situbondo
                </p>
            </div>

            <p class="text-sm text-slate-300 leading-relaxed max-w-lg">
                Platform terpadu akuntansi & pelaporan dana revitalisasi sarana fisik sekolah. Menghasilkan output baku perbendaharaan:
                <strong class="text-white">Buku Kas Umum (BKU)</strong>, 
                <strong class="text-white">Buku Pembantu Kas (BPK)</strong>, 
                <strong class="text-white">Buku Bank (BB)</strong>, dan 
                <strong class="text-white">Buku Pajak (BP)</strong>.
            </p>

            <!-- Features Highlights -->
            <div class="grid grid-cols-2 gap-3 pt-2 text-left">
                <div class="p-3 rounded-xl bg-slate-800/60 border border-slate-700/60">
                    <div class="flex items-center gap-2 text-emerald-400 font-bold text-xs mb-1">
                        <i data-lucide="shield-check" class="w-4 h-4"></i> Role & Permission
                    </div>
                    <p class="text-[11px] text-slate-400">Akses granular Root, Admin Revitalisasi, dan Pengawas</p>
                </div>
                <div class="p-3 rounded-xl bg-slate-800/60 border border-slate-700/60">
                    <div class="flex items-center gap-2 text-sky-400 font-bold text-xs mb-1">
                        <i data-lucide="printer" class="w-4 h-4"></i> Siap Cetak & Ekspor
                    </div>
                    <p class="text-[11px] text-slate-400">Kop resmi Situbondo, format Excel/CSV & Kuitansi</p>
                </div>
            </div>
        </div>

        <!-- Right Login Card -->
        <div class="lg:col-span-6 bg-slate-900/90 backdrop-blur-xl border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl shadow-black/60">
            <div class="mb-6">
                <h2 class="text-xl font-bold text-white">Masuk ke Sistem</h2>
                <p class="text-xs text-slate-400 mt-1">Gunakan akun Anda atau pilih akun demo di bawah untuk mencoba langsung.</p>
            </div>

            @if($errors->any())
            <div class="mb-5 p-3.5 bg-rose-500/10 border border-rose-500/30 rounded-xl text-rose-300 text-xs flex items-center gap-2.5">
                <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
                <div>{{ $errors->first() }}</div>
            </div>
            @endif

            <form action="{{ route('login.post') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Username atau Email</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                            <i data-lucide="user" class="w-4 h-4"></i>
                        </div>
                        <input type="text" name="login" required autofocus value="{{ old('login') }}"
                               placeholder="root / admin / user"
                               class="w-full pl-10 pr-4 py-2.5 bg-slate-950/70 border border-slate-700 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                        </div>
                        <input type="password" name="password" required
                               placeholder="••••••••"
                               class="w-full pl-10 pr-4 py-2.5 bg-slate-950/70 border border-slate-700 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                    </div>
                </div>

                <div class="flex items-center justify-between text-xs pt-1">
                    <label class="flex items-center gap-2 text-slate-400 cursor-pointer">
                        <input type="checkbox" name="remember" class="rounded border-slate-700 bg-slate-950 text-emerald-500 focus:ring-0">
                        <span>Ingat saya di perangkat ini</span>
                    </label>
                </div>

                <button type="submit" 
                        class="w-full py-2.5 px-4 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-bold rounded-xl text-sm shadow-lg shadow-emerald-500/25 transition-all flex items-center justify-center gap-2">
                    <i data-lucide="log-in" class="w-4 h-4"></i>
                    <span>Masuk Aplikasi</span>
                </button>
            </form>

            <!-- Quick Demo 1-Click Login Box -->
            <div class="mt-6 pt-6 border-t border-slate-800">
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2.5 text-center">
                    Coba Langsung (1-Click Demo Login)
                </p>
                <div class="grid grid-cols-3 gap-2">
                    <a href="{{ route('quick-login', 'root') }}" 
                       class="p-2 rounded-xl bg-purple-950/40 border border-purple-500/30 hover:bg-purple-900/40 text-center transition-all group">
                        <div class="font-bold text-xs text-purple-300 group-hover:text-purple-200">👑 Root</div>
                        <div class="text-[10px] text-slate-400">Akses Penuh</div>
                    </a>

                    <a href="{{ route('quick-login', 'admin') }}" 
                       class="p-2 rounded-xl bg-emerald-950/40 border border-emerald-500/30 hover:bg-emerald-900/40 text-center transition-all group">
                        <div class="font-bold text-xs text-emerald-300 group-hover:text-emerald-200">⚡ Admin</div>
                        <div class="text-[10px] text-slate-400">Pengelola</div>
                    </a>

                    <a href="{{ route('quick-login', 'user') }}" 
                       class="p-2 rounded-xl bg-sky-950/40 border border-sky-500/30 hover:bg-sky-900/40 text-center transition-all group">
                        <div class="font-bold text-xs text-sky-300 group-hover:text-sky-200">👁️ User</div>
                        <div class="text-[10px] text-slate-400">Viewer</div>
                    </a>
                </div>
            </div>

        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.lucide) {
                window.lucide.createIcons();
            }
        });
    </script>
</body>
</html>

