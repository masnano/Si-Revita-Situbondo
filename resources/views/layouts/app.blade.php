<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard')@if(!str_contains(View::yieldContent('title'), 'Si Revita') && !str_contains(View::yieldContent('title'), 'BKU') && !str_contains(View::yieldContent('title'), 'BPK') && !str_contains(View::yieldContent('title'), 'BB') && !str_contains(View::yieldContent('title'), 'BP') && !str_contains(View::yieldContent('title'), 'Realisasi') && !str_contains(View::yieldContent('title'), 'Kuitansi')) - Si Revita Situbondo @endif</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Icons (Lucide via CDN for instant crisp rendering) -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Chart.js CDN for robust charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Tailwind & Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; }

        /* Multi-Page & Landscape Print Styles */
        @media print {
            @page {
                size: A4 landscape;
                margin: 8mm 8mm 10mm 8mm;
            }
            @page portrait-page {
                size: A4 portrait;
                margin: 12mm 10mm 15mm 10mm;
            }
            .print-portrait {
                page: portrait-page;
            }
            html, body {
                height: auto !important;
                min-height: 100% !important;
                overflow: visible !important;
                position: static !important;
                background: #ffffff !important;
                color: #000000 !important;
                font-size: 8.5pt !important;
                margin: 0 !important;
                padding: 0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .h-full, .min-h-screen, .overflow-hidden, .overflow-y-auto, .overflow-x-auto, div.flex-1, main {
                height: auto !important;
                min-height: auto !important;
                max-height: none !important;
                overflow: visible !important;
                display: block !important;
                position: static !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }
            .no-print, aside, header, nav, button, .btn, form.no-print {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
            .print-card {
                border: none !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                background: transparent !important;
            }
            table.report-table {
                width: 100% !important;
                max-width: 100% !important;
                table-layout: auto !important;
                border-collapse: collapse !important;
                font-size: 8pt !important;
                margin-top: 6px !important;
                margin-bottom: 6px !important;
            }
            table.report-table thead {
                display: table-header-group !important;
            }
            table.report-table tfoot {
                display: table-footer-group !important;
            }
            table.report-table tbody tr {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
            table.report-table th, table.report-table td {
                border: 1px solid #000000 !important;
                padding: 3px 5px !important;
                line-height: 1.25 !important;
                color: #000000 !important;
                word-break: normal !important;
                overflow-wrap: break-word !important;
            }
            table.report-table th {
                background-color: #f1f5f9 !important;
                font-weight: 700 !important;
                text-transform: uppercase !important;
                text-align: center !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .avoid-break, .signature-block, .closing-box {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
            .page-break {
                page-break-after: always !important;
                break-after: page !important;
            }
        }
    </style>
    @stack('styles')
</head>
<body class="h-full flex overflow-hidden text-slate-800" x-data="{ sidebarOpen: false }">

    <!-- Mobile Sidebar Backdrop -->
    <div x-show="sidebarOpen" 
         x-transition:enter="transition-opacity ease-linear duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="sidebarOpen = false" 
         class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 lg:hidden no-print"></div>

    <!-- Sidebar Navigation -->
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" 
           class="fixed inset-y-0 left-0 z-50 w-72 bg-slate-900 text-slate-200 flex flex-col transition-transform duration-300 ease-in-out lg:static lg:translate-x-0 no-print border-r border-slate-800 shrink-0">
        
        <!-- App Branding -->
        <div class="p-4 flex items-center gap-3 border-b border-slate-800/80 bg-slate-950/40">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 group">
                <img src="{{ asset('images/logo.png') }}" 
                     alt="Logo Si Revita Situbondo" 
                     class="w-11 h-11 object-contain rounded-xl drop-shadow-md shrink-0 ring-1 ring-emerald-500/40 group-hover:scale-105 transition-transform bg-slate-900 p-0.5">
                <div class="overflow-hidden">
                    <h1 class="text-base font-bold text-white tracking-tight flex items-center gap-1.5 truncate">
                        Si Revita
                        <span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 font-semibold border border-emerald-500/30">Situbondo</span>
                    </h1>
                    <p class="text-[11px] text-slate-400 truncate">Pelaporan Revitalisasi Sekolah</p>
                </div>
            </a>
        </div>

        <!-- Role Indicator Pill -->
        <div class="px-5 py-3 bg-slate-800/40 border-b border-slate-800 text-xs flex items-center justify-between">
            <span class="text-slate-400 font-medium">Hak Akses Anda:</span>
            @if(auth()->user()->isRoot())
                <span class="px-2.5 py-1 rounded-md bg-purple-500/20 text-purple-300 font-bold border border-purple-500/30 flex items-center gap-1">
                    <i data-lucide="shield-alert" class="w-3.5 h-3.5"></i> ROOT
                </span>
            @elseif(auth()->user()->isAdmin())
                <span class="px-2.5 py-1 rounded-md bg-emerald-500/20 text-emerald-300 font-bold border border-emerald-500/30 flex items-center gap-1">
                    <i data-lucide="shield-check" class="w-3.5 h-3.5"></i> ADMIN
                </span>
            @else
                <span class="px-2.5 py-1 rounded-md bg-sky-500/20 text-sky-300 font-bold border border-sky-500/30 flex items-center gap-1">
                    <i data-lucide="eye" class="w-3.5 h-3.5"></i> USER
                </span>
            @endif
        </div>

        <!-- Navigation Links -->
        <nav class="flex-1 overflow-y-auto px-3.5 py-4 space-y-6 text-sm">
            <!-- Main Section -->
            <div>
                <p class="px-3 text-[11px] font-bold uppercase tracking-wider text-slate-400/90 mb-2">Utama</p>
                <div class="space-y-1">
                    <a href="{{ route('dashboard') }}" 
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all {{ request()->routeIs('dashboard') ? 'bg-emerald-600 text-white font-semibold shadow-md shadow-emerald-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                        <span>Dashboard Eksekutif</span>
                    </a>
                </div>
            </div>

            <!-- Core Reports (BKU, BPK, BB, BP) -->
            <div>
                <p class="px-3 text-[11px] font-bold uppercase tracking-wider text-slate-400/90 mb-2 flex items-center justify-between">
                    <span>Output Laporan Keuangan</span>
                    <span class="text-[10px] bg-slate-800 text-emerald-400 px-1.5 py-0.5 rounded font-mono">4 BUKU</span>
                </p>
                <div class="space-y-1">
                    <a href="{{ route('reports.bku') }}" 
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-all {{ request()->routeIs('reports.bku') ? 'bg-emerald-600 text-white font-semibold shadow-md shadow-emerald-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        <i data-lucide="book-open-check" class="w-4 h-4 text-emerald-400"></i>
                        <div class="flex-1">
                            <div class="leading-tight">Buku Kas Umum</div>
                            <span class="text-[11px] text-slate-400 font-mono">BKU</span>
                        </div>
                    </a>

                    <a href="{{ route('reports.bpk') }}" 
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-all {{ request()->routeIs('reports.bpk') ? 'bg-emerald-600 text-white font-semibold shadow-md shadow-emerald-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        <i data-lucide="wallet" class="w-4 h-4 text-amber-400"></i>
                        <div class="flex-1">
                            <div class="leading-tight">Buku Pembantu Kas</div>
                            <span class="text-[11px] text-slate-400 font-mono">BPK (Kas Tunai)</span>
                        </div>
                    </a>

                    <a href="{{ route('reports.bb') }}" 
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-all {{ request()->routeIs('reports.bb') ? 'bg-emerald-600 text-white font-semibold shadow-md shadow-emerald-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        <i data-lucide="building-2" class="w-4 h-4 text-sky-400"></i>
                        <div class="flex-1">
                            <div class="leading-tight">Buku Pembantu Bank</div>
                            <span class="text-[11px] text-slate-400 font-mono">BB (Buku Bank)</span>
                        </div>
                    </a>

                    <a href="{{ route('reports.bp') }}" 
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-all {{ request()->routeIs('reports.bp') ? 'bg-emerald-600 text-white font-semibold shadow-md shadow-emerald-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        <i data-lucide="receipt-tax" class="w-4 h-4 text-rose-400"></i>
                        <div class="flex-1">
                            <div class="leading-tight">Buku Pembantu Pajak</div>
                            <span class="text-[11px] text-slate-400 font-mono">BP (PPN & PPh)</span>
                        </div>
                    </a>

                    <a href="{{ route('reports.realisasi') }}" 
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-all {{ request()->routeIs('reports.realisasi') ? 'bg-emerald-600 text-white font-semibold shadow-md shadow-emerald-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        <i data-lucide="bar-chart-3" class="w-4 h-4 text-indigo-400"></i>
                        <span>Realisasi Anggaran & Fisik</span>
                    </a>

                    <a href="{{ route('reports.kuitansi') }}" 
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-all {{ request()->routeIs('reports.kuitansi*') ? 'bg-emerald-600 text-white font-semibold shadow-md shadow-emerald-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        <i data-lucide="file-check" class="w-4 h-4 text-teal-400"></i>
                        <span>Kuitansi Pengeluaran</span>
                    </a>

                    <a href="{{ route('rpd.index') }}" 
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-all {{ request()->routeIs('rpd*') ? 'bg-emerald-600 text-white font-semibold shadow-md shadow-emerald-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        <i data-lucide="layers" class="w-4 h-4 text-amber-400"></i>
                        <div class="flex-1">
                            <div class="leading-tight">Pecah Bahan RPD</div>
                            <span class="text-[11px] text-amber-300 font-sans">Generator Isian BKU</span>
                        </div>
                        <span class="px-1.5 py-0.5 text-[9px] font-bold rounded bg-amber-500/20 text-amber-300 border border-amber-500/30">BARU</span>
                    </a>
                </div>
            </div>

            <!-- Operations / Data Input -->
            @role('root,admin')
            <div>
                <p class="px-3 text-[11px] font-bold uppercase tracking-wider text-slate-400/90 mb-2">Pencatatan Keuangan</p>
                <div class="space-y-1">
                    <a href="{{ route('transactions.index') }}" 
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-all {{ request()->routeIs('transactions*') ? 'bg-emerald-600 text-white font-semibold shadow-md shadow-emerald-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        <i data-lucide="arrow-left-right" class="w-4 h-4"></i>
                        <span>Transaksi Kas & Bank</span>
                    </a>

                    <a href="{{ route('rab.index') }}" 
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-all {{ request()->routeIs('rab*') ? 'bg-emerald-600 text-white font-semibold shadow-md shadow-emerald-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        <i data-lucide="clipboard-list" class="w-4 h-4"></i>
                        <span>RAB & Pos Belanja</span>
                    </a>
                </div>
            </div>

            <!-- Master Data -->
            <div>
                <p class="px-3 text-[11px] font-bold uppercase tracking-wider text-slate-400/90 mb-2">Data Master</p>
                <div class="space-y-1">
                    <a href="{{ route('schools.index') }}" 
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-all {{ request()->routeIs('schools*') ? 'bg-emerald-600 text-white font-semibold shadow-md shadow-emerald-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        <i data-lucide="school" class="w-4 h-4"></i>
                        <span>Sekolah Situbondo</span>
                    </a>

                    <a href="{{ route('projects.index') }}" 
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-all {{ request()->routeIs('projects*') ? 'bg-emerald-600 text-white font-semibold shadow-md shadow-emerald-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        <i data-lucide="hard-hat" class="w-4 h-4"></i>
                        <span>Paket Revitalisasi</span>
                    </a>
                </div>
            </div>
            @endrole

            <!-- Root Superadmin Control Section -->
            @role('root')
            <div class="pt-2 border-t border-slate-800">
                <p class="px-3 text-[11px] font-bold uppercase tracking-wider text-purple-400 mb-2 flex items-center gap-1.5">
                    <i data-lucide="crown" class="w-3.5 h-3.5"></i>
                    <span>Administrasi Root</span>
                </p>
                <div class="space-y-1">
                    <a href="{{ route('users.index') }}" 
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-all {{ request()->routeIs('users*') ? 'bg-purple-600 text-white font-semibold shadow-md shadow-purple-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        <i data-lucide="users" class="w-4 h-4"></i>
                        <span>Kelola Pengguna</span>
                    </a>

                    <a href="{{ route('roles.index') }}" 
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-all {{ request()->routeIs('roles*') ? 'bg-purple-600 text-white font-semibold shadow-md shadow-purple-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        <i data-lucide="sliders" class="w-4 h-4"></i>
                        <span>Role & Permission Matrix</span>
                    </a>
                </div>
            </div>
            @endrole

            <!-- Bantuan & Dokumen Panduan -->
            <div class="pt-2 border-t border-slate-800">
                <p class="px-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2 flex items-center justify-between">
                    <span>Bantuan & Dokumen</span>
                </p>
                <div class="space-y-1">
                    <a href="{{ route('panduan.index') }}" 
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-all {{ request()->routeIs('panduan*') ? 'bg-emerald-600 text-white font-semibold shadow-md shadow-emerald-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        <i data-lucide="book-open" class="w-4 h-4 text-emerald-400"></i>
                        <div class="flex-1 flex items-center justify-between">
                            <span>Buku Panduan</span>
                            <span class="text-[10px] bg-emerald-500/20 text-emerald-300 px-1.5 py-0.5 rounded font-bold border border-emerald-500/30">PDF</span>
                        </div>
                    </a>
                </div>
            </div>
        </nav>

        <!-- User Profile Card in Sidebar Bottom -->
        <div class="p-3.5 border-t border-slate-800 bg-slate-950/60">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-slate-700 border border-slate-600 flex items-center justify-center font-bold text-white text-xs shrink-0">
                    {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-white truncate">{{ auth()->user()->name }}</p>
                    <p class="text-[11px] text-slate-400 truncate">{{ auth()->user()->email }}</p>
                </div>
                <form action="{{ route('logout') }}" method="POST" class="shrink-0">
                    @csrf
                    <button type="submit" title="Keluar dari Sistem" class="p-1.5 text-slate-400 hover:text-rose-400 hover:bg-slate-800 rounded-md transition-colors">
                        <i data-lucide="log-out" class="w-4 h-4"></i>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <!-- Main Content Container -->
    <div class="flex-1 flex flex-col h-full overflow-hidden min-w-0">
        
        <!-- Top App Bar -->
        <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 sm:px-6 z-20 shrink-0 no-print">
            <div class="flex items-center gap-3">
                <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-2 text-slate-600 hover:text-slate-900 rounded-lg hover:bg-slate-100">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>
                <div class="lg:hidden flex items-center gap-2">
                    <img src="{{ asset('images/logo.png') }}" alt="Logo Si Revita" class="w-8 h-8 object-contain rounded-lg">
                    <span class="text-sm font-extrabold text-slate-900 tracking-tight">Si Revita</span>
                </div>
                <div class="hidden sm:flex items-center gap-3">
                    <img src="{{ asset('images/logo.png') }}" alt="Logo Si Revita" class="w-9 h-9 object-contain hidden md:block">
                    <div>
                        <h2 class="text-sm font-bold text-slate-800">
                            Pemerintah Kabupaten Situbondo
                        </h2>
                        <p class="text-xs text-slate-500">Dinas Pendidikan dan Kebudayaan — Bidang Sarana & Prasarana</p>
                    </div>
                </div>
            </div>

            <!-- Header Quick Actions & Role Switcher -->
            <div class="flex items-center gap-3">
                <!-- Quick Demo Switcher -->
                <div class="relative" x-data="{ openSwitcher: false }" @click.outside="openSwitcher = false">
                    <button type="button" 
                            @click="openSwitcher = !openSwitcher" 
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-medium text-slate-700 bg-slate-50 hover:bg-slate-100 transition-colors">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="hidden md:inline">Ubah Role:</span>
                        <span class="font-bold text-slate-900">{{ strtoupper(auth()->user()->role->name ?? 'GUEST') }}</span>
                        <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-slate-400"></i>
                    </button>

                    <div x-cloak
                         x-show="openSwitcher" 
                         x-transition 
                         class="absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-xl border border-slate-200 py-1.5 z-50 text-xs">
                        <div class="px-3 py-1.5 border-b border-slate-100 text-[11px] font-semibold text-slate-400 uppercase">
                            Coba Akses Role Lain
                        </div>
                        <a href="{{ route('quick-login', 'root') }}" class="flex items-center gap-2.5 px-3 py-2 text-slate-700 hover:bg-purple-50 hover:text-purple-700">
                            <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                            <div>
                                <div class="font-bold">Root (Superadmin)</div>
                                <div class="text-[10px] text-slate-500">Semua fitur & kelola permission</div>
                            </div>
                        </a>
                        <a href="{{ route('quick-login', 'admin') }}" class="flex items-center gap-2.5 px-3 py-2 text-slate-700 hover:bg-emerald-50 hover:text-emerald-700">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            <div>
                                <div class="font-bold">Admin Revitalisasi</div>
                                <div class="text-[10px] text-slate-500">Input transaksi & kelola RAB</div>
                            </div>
                        </a>
                        <a href="{{ route('quick-login', 'user') }}" class="flex items-center gap-2.5 px-3 py-2 text-slate-700 hover:bg-sky-50 hover:text-sky-700">
                            <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                            <div>
                                <div class="font-bold">User (Viewer)</div>
                                <div class="text-[10px] text-slate-500">Lihat & cetak output laporan</div>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Print Page Button if relevant -->
                <button onclick="window.print()" class="hidden md:flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition-colors">
                    <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                    <span>Cetak Layar</span>
                </button>

                <!-- Logout Form -->
                <form action="{{ route('logout') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="p-2 text-slate-500 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Keluar">
                        <i data-lucide="log-out" class="w-4 h-4"></i>
                    </button>
                </form>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-1 overflow-y-auto bg-slate-50/70 p-4 sm:p-6 lg:p-8">
            <!-- Flash Message Alerts -->
            @if(session('success'))
            <div class="mb-5 flex items-center gap-3 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl shadow-sm text-sm no-print">
                <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600 shrink-0"></i>
                <div class="flex-1 font-medium">{{ session('success') }}</div>
            </div>
            @endif

            @if(session('error'))
            <div class="mb-5 flex items-center gap-3 p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl shadow-sm text-sm no-print">
                <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-600 shrink-0"></i>
                <div class="flex-1 font-medium">{{ session('error') }}</div>
            </div>
            @endif

            @if(session('info'))
            <div class="mb-5 flex items-center gap-3 p-4 bg-sky-50 border border-sky-200 text-sky-800 rounded-xl shadow-sm text-sm no-print">
                <i data-lucide="info" class="w-5 h-5 text-sky-600 shrink-0"></i>
                <div class="flex-1 font-medium">{{ session('info') }}</div>
            </div>
            @endif

            @yield('content')
        </main>
    </div>

    <!-- Global Universal Confirmation Modal for Deletion / Critical Actions -->
    <div x-data="globalConfirmHandler()"
         x-cloak
         x-show="isOpen"
         @open-global-confirm.window="openModal($event.detail)"
         @keydown.escape.window="closeModal()"
         class="fixed inset-0 z-50 overflow-y-auto no-print"
         style="display: none;">
        <!-- Backdrop -->
        <div x-show="isOpen"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"
             @click="closeModal()"></div>

        <!-- Dialog Container -->
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div x-show="isOpen"
                 x-transition:enter="ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-200"
                 @click.stop>
                
                <div class="bg-white p-6 sm:p-7">
                    <div class="flex items-start gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl sm:h-12 sm:w-12 shadow-sm"
                             :class="isDanger ? 'bg-rose-100 text-rose-600 border border-rose-200' : 'bg-amber-100 text-amber-700 border border-amber-200'">
                            <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                        </div>
                        <div class="flex-1 mt-0.5">
                            <h3 class="text-base sm:text-lg font-extrabold text-slate-900" x-text="title"></h3>
                            <div class="mt-2 text-xs sm:text-sm text-slate-600 leading-relaxed font-normal" x-html="message"></div>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50/90 px-6 py-4 flex flex-col-reverse sm:flex-row sm:justify-end gap-2.5 border-t border-slate-200">
                    <button type="button"
                            x-ref="cancelBtn"
                            @click="closeModal()"
                            class="w-full sm:w-auto inline-flex justify-center items-center px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold text-slate-700 bg-white border border-slate-300 hover:bg-slate-100 transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-slate-400">
                        Batal
                    </button>
                    <button type="button"
                            @click="confirmAction()"
                            :class="isDanger ? 'bg-rose-600 hover:bg-rose-700 text-white shadow-rose-600/30' : 'bg-amber-600 hover:bg-amber-700 text-white shadow-amber-600/30'"
                            class="w-full sm:w-auto inline-flex justify-center items-center gap-1.5 px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold shadow-md transition-all focus:outline-none focus:ring-2 focus:ring-rose-500">
                        <i data-lucide="trash-2" class="w-4 h-4" x-show="isDanger"></i>
                        <span x-text="confirmText"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Initialize Lucide Icons & Print Filename Helper & Global Confirm -->
    <script>
        function globalConfirmHandler() {
            return {
                isOpen: false,
                title: 'Konfirmasi Penghapusan Data',
                message: 'Apakah Anda yakin ingin menghapus data ini? Tindakan ini tidak dapat dibatalkan.',
                confirmText: 'Ya, Hapus Data',
                isDanger: true,
                targetForm: null,
                callback: null,

                openModal(detail) {
                    this.title = detail.title || 'Konfirmasi Penghapusan Data';
                    this.message = detail.message || 'Apakah Anda yakin ingin menghapus data ini? Tindakan ini tidak dapat dibatalkan.';
                    this.confirmText = detail.confirmText || 'Ya, Hapus Data';
                    this.isDanger = detail.isDanger !== undefined ? detail.isDanger : true;
                    this.targetForm = detail.form || null;
                    this.callback = detail.callback || null;
                    this.isOpen = true;

                    this.$nextTick(() => {
                        if (window.lucide) {
                            window.lucide.createIcons();
                        }
                        if (this.$refs.cancelBtn) {
                            this.$refs.cancelBtn.focus();
                        }
                    });
                },

                closeModal() {
                    this.isOpen = false;
                    this.targetForm = null;
                    this.callback = null;
                },

                confirmAction() {
                    const form = this.targetForm;
                    const cb = this.callback;
                    this.isOpen = false;

                    if (cb && typeof cb === 'function') {
                        cb();
                    } else if (form) {
                        form.dataset.confirmed = 'true';
                        form.submit();
                    }
                }
            };
        }

        // Intersep seluruh form submit yang menghapus data atau memerlukan konfirmasi
        document.addEventListener('submit', function (e) {
            const form = e.target;
            if (!form || form.dataset.confirmed === 'true') {
                return;
            }

            const isDelete = form.querySelector('input[name="_method"][value="DELETE"]') !== null;
            const hasConfirmAttr = form.hasAttribute('data-confirm');
            const onsubmitAttr = form.getAttribute('onsubmit') || '';
            const hasConfirmOnsubmit = onsubmitAttr.includes('confirm(');

            if (isDelete || hasConfirmAttr || hasConfirmOnsubmit) {
                e.preventDefault();
                e.stopImmediatePropagation();

                let message = form.getAttribute('data-confirm');
                if (!message && hasConfirmOnsubmit) {
                    const match = onsubmitAttr.match(/confirm\(\s*['"](.*?)['"]\s*\)/);
                    if (match && match[1]) {
                        message = match[1];
                    }
                }
                if (!message) {
                    message = isDelete 
                        ? 'Apakah Anda yakin ingin menghapus data ini? Seluruh data yang berkaitan akan dihapus secara permanen.'
                        : 'Apakah Anda yakin ingin memproses tindakan ini?';
                }

                const title = form.getAttribute('data-confirm-title') || (isDelete ? 'Konfirmasi Hapus Data' : 'Konfirmasi Tindakan');
                const confirmText = form.getAttribute('data-confirm-button') || (isDelete ? 'Ya, Hapus Data' : 'Ya, Lanjutkan');
                const isDanger = form.getAttribute('data-confirm-danger') !== 'false';

                window.dispatchEvent(new CustomEvent('open-global-confirm', {
                    detail: {
                        title: title,
                        message: message,
                        confirmText: confirmText,
                        isDanger: isDanger,
                        form: form
                    }
                }));
            }
        }, true);

        document.addEventListener('DOMContentLoaded', () => {
            if (window.lucide) {
                window.lucide.createIcons();
            }
        });

        // Ensure browser print / Save as PDF uses document title starting with Month name
        window.addEventListener('beforeprint', () => {
            const pageTitle = document.querySelector('title');
            if (pageTitle && pageTitle.innerText) {
                document.title = pageTitle.innerText;
            }
        });
    </script>
    @stack('scripts')
</body>
</html>

