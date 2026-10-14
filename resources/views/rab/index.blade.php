@extends('layouts.app')

@section('title', 'Rencana Anggaran Biaya (RAB)')

@section('content')
<div class="space-y-6" x-data="{ 
    openAddModal: false, 
    openUploadModal: false, 
    editModal: false, 
    activeItem: null, 
    fileName: '', 
    fileSize: '', 
    isSubmitting: false,
    selectedMenu: '{{ $selectedMenu }}',
    uploadMenu: '{{ $selectedMenu !== 'all' ? $selectedMenu : ($menuStats->keys()->first() ?? 'Rehabilitasi Ruang Kelas') }}',
    isCustomUploadMenu: false,
    customUploadMenu: '',
    addMenu: '{{ $selectedMenu !== 'all' ? $selectedMenu : ($menuStats->keys()->first() ?? 'Rehabilitasi Ruang Kelas') }}',
    isCustomAddMenu: false,
    customAddMenu: '',
    editMenu: '',
    isCustomEditMenu: false,
    customEditMenu: '',
    editItem: {
        id: null,
        code: '',
        category: 'Material/Bahan',
        name: '',
        volume: '',
        unit: '',
        unit_price: '',
        notes: ''
    },
    handleFileSelect(e) {
        const file = e.target.files[0];
        if (file) {
            this.fileName = file.name;
            this.fileSize = (file.size / 1024).toFixed(1) + ' KB';
        }
    },
    openEdit(item) {
        this.activeItem = item;
        this.editItem = {
            id: item.id,
            code: item.code || '',
            category: item.category || 'Material/Bahan',
            name: item.name || '',
            volume: item.volume || '',
            unit: item.unit || '',
            unit_price: item.unit_price || '',
            notes: item.notes || ''
        };
        this.editMenu = item.menu_pekerjaan || 'Rehabilitasi Ruang Kelas';
        this.isCustomEditMenu = false;
        this.customEditMenu = '';
        this.editModal = true;
    }
}">

    <!-- Header & Controls -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-emerald-600 mb-1">
                <i data-lucide="clipboard-list" class="w-4 h-4"></i>
                <span>Pagu &amp; Struktur Pembiayaan Per Menu Pekerjaan</span>
            </div>
            <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">
                Rencana Anggaran Biaya (RAB)
            </h1>
            <p class="text-xs text-slate-500">Pengelompokan rincian belanja per menu pekerjaan (Rehab Kelas, Rehab Perpus, Pembangunan Toilet, dsb).</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <!-- Project Selector -->
            <form action="{{ route('rab.index') }}" method="GET">
                <input type="hidden" name="menu_pekerjaan" value="{{ $selectedMenu }}">
                <select name="project_id" onchange="this.form.submit()" class="text-xs font-semibold px-3 py-2 rounded-xl border border-slate-300 bg-slate-50 text-slate-800 focus:ring-2 focus:ring-emerald-500">
                    @foreach($projects as $p)
                        <option value="{{ $p->id }}" {{ $selectedProjectId == $p->id ? 'selected' : '' }}>
                            {{ $p->school->name }} - {{ $p->title }}
                        </option>
                    @endforeach
                </select>
            </form>

            @permission('rab.create')
            <!-- Unduh Template Excel -->
            <a href="{{ route('rab.template') }}" class="px-3.5 py-2 rounded-xl bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-bold text-xs flex items-center gap-1.5 shadow-sm transition-all" title="Unduh format template Excel untuk pengisian RAB">
                <i data-lucide="download" class="w-3.5 h-3.5 text-slate-500"></i>
                <span class="hidden sm:inline">Template Excel</span>
            </a>

            <!-- Unggah RAB Excel -->
            <button type="button" @click="openUploadModal = true" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs flex items-center gap-2 shadow-md shadow-blue-600/25 transition-all hover:-translate-y-0.5">
                <i data-lucide="upload-cloud" class="w-4 h-4 text-blue-100"></i>
                <span>Unggah RAB Excel</span>
            </button>

            <!-- Tambah Item Manual -->
            <button type="button" @click="openAddModal = true" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center gap-2 shadow-md shadow-emerald-600/25 transition-all hover:-translate-y-0.5">
                <i data-lucide="plus-circle" class="w-4 h-4 text-emerald-100"></i>
                <span>Tambah Item</span>
            </button>
            @endpermission
        </div>
    </div>

    <!-- Summary Box Keseluruhan Proyek -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[11px] font-semibold text-slate-500 uppercase">Pagu Kontrak Proyek (Semua Menu)</span>
            <div class="text-base font-bold text-slate-900 mt-1">@rupiah($selectedProject->contract_amount ?? 0)</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[11px] font-semibold text-emerald-600 uppercase">Total Alokasi Rincian RAB</span>
            <div class="text-base font-bold text-emerald-600 mt-1">@rupiah($totalRAB)</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[11px] font-semibold {{ (($selectedProject->contract_amount ?? 0) - $totalRAB) < 0 ? 'text-rose-600' : 'text-blue-600' }} uppercase">
                Selisih Pagu vs RAB
            </span>
            <div class="text-base font-bold {{ (($selectedProject->contract_amount ?? 0) - $totalRAB) < 0 ? 'text-rose-600' : 'text-blue-600' }} mt-1">
                @rupiah(($selectedProject->contract_amount ?? 0) - $totalRAB)
            </div>
        </div>
    </div>

    <!-- Navigation Tabs Menu Pekerjaan -->
    <div class="bg-white p-3 rounded-2xl border border-slate-200/80 shadow-sm space-y-3">
        <div class="flex items-center justify-between px-2">
            <div class="flex items-center gap-2">
                <i data-lucide="layers" class="w-4 h-4 text-emerald-600"></i>
                <span class="text-xs font-bold text-slate-800 uppercase tracking-wider">Pengelompokan Menu Pekerjaan</span>
                <span class="text-[10px] text-slate-400 font-semibold">({{ $menuStats->count() }} Menu Aktif)</span>
            </div>
            
            <button type="button" @click="openUploadModal = true; isCustomUploadMenu = true" class="text-[11px] font-bold text-blue-600 hover:text-blue-700 flex items-center gap-1 hover:underline">
                <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                <span>Impor Menu Baru</span>
            </button>
        </div>

        <!-- Tab Pills List -->
        <div class="flex flex-wrap items-center gap-2 pt-1 border-t border-slate-100">
            <!-- Tab: Semua Menu -->
            <a href="{{ route('rab.index', ['project_id' => $selectedProjectId, 'menu_pekerjaan' => 'all']) }}" 
               class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 shadow-sm
                      {{ $selectedMenu === 'all' 
                         ? 'bg-slate-900 text-white shadow-slate-900/20' 
                         : 'bg-slate-50 text-slate-600 hover:bg-slate-100 border border-slate-200/80' }}">
                <i data-lucide="layout-grid" class="w-3.5 h-3.5"></i>
                <span>Semua Menu Pekerjaan</span>
                <span class="px-1.5 py-0.5 rounded-md text-[10px] font-bold 
                      {{ $selectedMenu === 'all' ? 'bg-slate-800 text-white' : 'bg-slate-200 text-slate-700' }}">
                    {{ $menuStats->sum('count') }}
                </span>
            </a>

            <!-- Tabs: Tiap Menu Pekerjaan -->
            @foreach($menuStats as $menuName => $stat)
            @php
                $isActive = ($selectedMenu === $menuName);
                $isRehabKelas = str_contains(strtolower($menuName), 'kelas');
                $isPerpus = str_contains(strtolower($menuName), 'perpus');
                $isToilet = str_contains(strtolower($menuName), 'toilet') || str_contains(strtolower($menuName), 'jamban');
            @endphp
            <a href="{{ route('rab.index', ['project_id' => $selectedProjectId, 'menu_pekerjaan' => $menuName]) }}"
               class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 shadow-sm
                      @if($isActive)
                          bg-gradient-to-r from-emerald-600 to-teal-600 text-white shadow-emerald-600/20
                      @else
                          bg-white text-slate-700 hover:bg-slate-50 border border-slate-200/90
                      @endif">
                @if($isRehabKelas)
                    <i data-lucide="school" class="w-3.5 h-3.5"></i>
                @elseif($isPerpus)
                    <i data-lucide="book-open" class="w-3.5 h-3.5"></i>
                @elseif($isToilet)
                    <i data-lucide="droplet" class="w-3.5 h-3.5"></i>
                @else
                    <i data-lucide="building" class="w-3.5 h-3.5"></i>
                @endif
                
                <span>{{ $menuName }}</span>

                <span class="px-1.5 py-0.5 rounded-md text-[10px] font-bold 
                      {{ $isActive ? 'bg-emerald-700/60 text-white' : 'bg-slate-100 text-slate-600' }}">
                    {{ $stat['count'] }}
                </span>

                <span class="text-[11px] font-mono {{ $isActive ? 'text-emerald-100' : 'text-emerald-600' }} ml-1">
                    @rupiah($stat['pagu'])
                </span>
            </a>
            @endforeach
        </div>
    </div>

    <!-- Active Single Menu Detail Banner (Jika sedang filter 1 menu) -->
    @if($selectedMenu !== 'all' && isset($menuStats[$selectedMenu]))
    @php
        $curStat = $menuStats[$selectedMenu];
        $pct = $curStat['pagu'] > 0 ? round(($curStat['realisasi'] / $curStat['pagu']) * 100, 1) : 0;
    @endphp
    <div class="bg-gradient-to-br from-emerald-50 to-teal-50/50 border border-emerald-200 rounded-2xl p-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <span class="px-2 py-0.5 rounded-md bg-emerald-600 text-white text-[10px] font-bold uppercase tracking-wider">
                    Menu Terpilih
                </span>
                <h2 class="text-base font-extrabold text-slate-900">{{ $selectedMenu }}</h2>
            </div>
            <p class="text-xs text-slate-600">
                Menampilkan <strong>{{ $curStat['count'] }} rincian item</strong> belanja khusus untuk menu ini.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-4 text-xs font-semibold">
            <div class="bg-white px-3.5 py-2 rounded-xl border border-emerald-200/80 shadow-sm">
                <span class="text-[10px] uppercase text-slate-400 block font-bold">Pagu Menu Ini</span>
                <span class="text-sm font-bold text-slate-900 font-mono">@rupiah($curStat['pagu'])</span>
            </div>
            <div class="bg-white px-3.5 py-2 rounded-xl border border-emerald-200/80 shadow-sm">
                <span class="text-[10px] uppercase text-emerald-600 block font-bold">Realisasi</span>
                <span class="text-sm font-bold text-emerald-600 font-mono">@rupiah($curStat['realisasi'])</span>
            </div>
            <div class="bg-white px-3.5 py-2 rounded-xl border border-emerald-200/80 shadow-sm">
                <span class="text-[10px] uppercase text-blue-600 block font-bold">Sisa Pagu</span>
                <span class="text-sm font-bold text-blue-600 font-mono">@rupiah($curStat['sisa'])</span>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" @click="uploadMenu = '{{ $selectedMenu }}'; openUploadModal = true" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold flex items-center gap-1.5 shadow-sm shadow-blue-600/20 transition-all hover:-translate-y-0.5">
                    <i data-lucide="upload-cloud" class="w-3.5 h-3.5 text-blue-100"></i>
                    <span>Unggah ke Menu Ini</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    <!-- RAB Items Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px]">
                        @if($selectedMenu === 'all')
                        <th class="py-3 px-3">Menu Pekerjaan</th>
                        @endif
                        <th class="py-3 px-3">Kode</th>
                        <th class="py-3 px-3">Kategori Pos</th>
                        <th class="py-3 px-4">Uraian Belanja / Item Pekerjaan</th>
                        <th class="py-3 px-3 text-center">Volume</th>
                        <th class="py-3 px-3 text-right">Harga Satuan (Rp)</th>
                        <th class="py-3 px-3 text-right">Total Pagu (Rp)</th>
                        <th class="py-3 px-3 text-right">Realisasi (Rp)</th>
                        <th class="py-3 px-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @php
                        $groupedItems = ($selectedMenu === 'all') ? $items->groupBy('menu_pekerjaan') : [$selectedMenu => $items];
                    @endphp

                    @forelse($groupedItems as $groupName => $groupRows)
                        <!-- Header Pemisah Group Menu Pekerjaan (saat melihat semua menu) -->
                        @if($selectedMenu === 'all')
                        <tr class="bg-gradient-to-r from-slate-100/90 to-slate-50 border-y-2 border-slate-200/90 font-bold text-slate-800">
                            <td colspan="9" class="py-2.5 px-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2 text-xs">
                                        <i data-lucide="folder-check" class="w-4 h-4 text-emerald-600"></i>
                                        <span class="text-slate-900 font-extrabold uppercase tracking-wide">{{ $groupName }}</span>
                                        <span class="px-2 py-0.5 rounded-full bg-slate-200 text-slate-700 text-[10px] font-bold">
                                            {{ $groupRows->count() }} Item
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-4 text-xs font-mono">
                                        <span class="text-slate-500 font-normal">Subtotal Pagu:</span>
                                        <span class="text-emerald-700 font-bold text-xs">@rupiah($groupRows->sum('total_price'))</span>
                                        
                                        <a href="{{ route('rab.index', ['project_id' => $selectedProjectId, 'menu_pekerjaan' => $groupName]) }}" 
                                           class="text-[10px] font-semibold text-emerald-600 hover:text-emerald-700 hover:underline flex items-center gap-1 font-sans">
                                            <span>Fokus Menu Ini</span>
                                            <i data-lucide="chevron-right" class="w-3 h-3"></i>
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endif

                        @foreach($groupRows as $item)
                        <tr class="hover:bg-slate-50/70">
                            @if($selectedMenu === 'all')
                            <td class="py-3 px-3">
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold 
                                    @if(str_contains(strtolower($item->menu_pekerjaan), 'kelas')) bg-blue-50 text-blue-700 border border-blue-200/60
                                    @elseif(str_contains(strtolower($item->menu_pekerjaan), 'perpus')) bg-purple-50 text-purple-700 border border-purple-200/60
                                    @elseif(str_contains(strtolower($item->menu_pekerjaan), 'toilet')) bg-teal-50 text-teal-700 border border-teal-200/60
                                    @else bg-slate-100 text-slate-700 border border-slate-200 @endif">
                                    {{ $item->menu_pekerjaan }}
                                </span>
                            </td>
                            @endif

                            <td class="py-3 px-3 font-mono text-slate-600">{{ $item->code ?? '-' }}</td>
                            <td class="py-3 px-3">
                                <span class="px-2 py-0.5 rounded-md font-semibold text-[10px] 
                                    {{ $item->category === 'Material/Bahan' ? 'bg-amber-100 text-amber-800' : '' }}
                                    {{ $item->category === 'Upah Tenaga Kerja' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                    {{ $item->category === 'Peralatan/Sewa' ? 'bg-sky-100 text-sky-800' : '' }}
                                    {{ $item->category === 'Honor & Operasional' ? 'bg-purple-100 text-purple-800' : '' }}
                                ">
                                    {{ $item->category }}
                                </span>
                            </td>
                            <td class="py-3 px-4 font-semibold text-slate-800">
                                {{ $item->name }}
                                @if($item->notes)
                                    <div class="text-[10px] text-slate-400 font-normal">{{ $item->notes }}</div>
                                @endif
                            </td>
                            <td class="py-3 px-3 text-center font-mono">{{ $item->volume }} {{ $item->unit }}</td>
                            <td class="py-3 px-3 text-right font-mono">@rupiah($item->unit_price)</td>
                            <td class="py-3 px-3 text-right font-mono font-bold text-slate-900">@rupiah($item->total_price)</td>
                            <td class="py-3 px-3 text-right font-mono font-bold text-emerald-700">@rupiah($item->realized_amount)</td>
                            <td class="py-3 px-3 text-center">
                                <div class="inline-flex items-center gap-1">
                                    @permission('rab.update')
                                    <button type="button" @click="openEdit({{ json_encode($item) }})" class="p-1 text-blue-600 hover:bg-blue-50 rounded" title="Edit Item">
                                        <i data-lucide="edit" class="w-3.5 h-3.5"></i>
                                    </button>
                                    @endpermission
                                    @permission('rab.delete')
                                    <form action="{{ route('rab.destroy', $item->id) }}" method="POST" class="inline"
                                          data-confirm="Apakah Anda yakin ingin menghapus item anggaran RAB <strong>{{ $item->name }}</strong> (Rp {{ number_format($item->total_price, 0, ',', '.') }})?<br><br>Item ini akan dihapus dari rincian RAB proyek."
                                          data-confirm-title="Hapus Item RAB"
                                          data-confirm-button="Ya, Hapus Item"
                                          onsubmit="return confirm('Hapus item RAB ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1 text-rose-600 hover:bg-rose-50 rounded" title="Hapus Item">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </form>
                                    @endpermission
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    @empty
                    <tr>
                        <td colspan="{{ $selectedMenu === 'all' ? 9 : 8 }}" class="py-12 text-center text-slate-400 text-xs">
                            <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-2">
                                <i data-lucide="inbox" class="w-6 h-6"></i>
                            </div>
                            <p class="font-medium text-slate-600">Belum ada item pos belanja RAB untuk kategori / menu pekerjaan ini.</p>
                            @permission('rab.create')
                            <div class="mt-4 flex items-center justify-center gap-2.5">
                                <button type="button" @click="openUploadModal = true" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs flex items-center gap-2 shadow-sm shadow-blue-600/20 transition-all hover:-translate-y-0.5">
                                    <i data-lucide="upload-cloud" class="w-4 h-4 text-blue-100"></i>
                                    <span>Unggah File RAB</span>
                                </button>
                                <button type="button" @click="openAddModal = true" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center gap-2 shadow-sm shadow-emerald-600/20 transition-all hover:-translate-y-0.5">
                                    <i data-lucide="plus-circle" class="w-4 h-4 text-emerald-100"></i>
                                    <span>Tambah Manual</span>
                                </button>
                            </div>
                            @endpermission
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Tambah Item RAB -->
    <div x-show="openAddModal" x-transition class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="display: none;">
        <div @click.away="openAddModal = false" class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
            <h3 class="font-bold text-sm text-slate-900 mb-4 border-b pb-2 flex items-center gap-2">
                <i data-lucide="plus-circle" class="w-4 h-4 text-emerald-600"></i>
                <span>Tambah Pos Belanja RAB</span>
            </h3>
            
            <form action="{{ route('rab.store') }}" method="POST" class="space-y-4 text-xs">
                @csrf
                <input type="hidden" name="project_id" value="{{ $selectedProjectId }}">

                <!-- Menu Pekerjaan Target -->
                <div class="space-y-1.5 p-3 rounded-xl bg-slate-50 border border-slate-200">
                    <label class="block font-bold text-slate-800">Menu Pekerjaan *</label>
                    
                    <template x-if="!isCustomAddMenu">
                        <div class="flex items-center gap-2">
                            <select name="menu_pekerjaan" x-model="addMenu" required class="w-full px-3 py-2 border rounded-xl bg-white font-semibold text-slate-800">
                                @foreach($menuStats->keys() as $existingMenu)
                                    <option value="{{ $existingMenu }}">{{ $existingMenu }}</option>
                                @endforeach
                                @foreach($standardMenus as $std)
                                    @if(!$menuStats->has($std))
                                        <option value="{{ $std }}">{{ $std }}</option>
                                    @endif
                                @endforeach
                            </select>
                            <button type="button" @click="isCustomAddMenu = true; customAddMenu = ''" class="px-2.5 py-2 rounded-xl bg-white border border-slate-300 text-slate-600 hover:bg-slate-100 font-bold shrink-0" title="Ketik nama menu baru">
                                + Baru
                            </button>
                        </div>
                    </template>

                    <template x-if="isCustomAddMenu">
                        <div class="flex items-center gap-2">
                            <input type="text" name="menu_pekerjaan" x-model="customAddMenu" required placeholder="Contoh: Pembangunan Ruang UKS" class="w-full px-3 py-2 border rounded-xl bg-white font-semibold text-slate-800">
                            <button type="button" @click="isCustomAddMenu = false" class="px-2.5 py-2 rounded-xl bg-slate-200 text-slate-700 hover:bg-slate-300 font-bold shrink-0">
                                Batal
                            </button>
                        </div>
                    </template>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Kode Rekening / AHSP</label>
                        <input type="text" name="code" placeholder="2.1.1.1 atau 5.2.2..." class="w-full px-3 py-2 border rounded-xl font-mono">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Kategori Belanja *</label>
                        <select name="category" required class="w-full px-3 py-2 border rounded-xl bg-white">
                            <option value="Material/Bahan">Material/Bahan</option>
                            <option value="Upah Tenaga Kerja">Upah Tenaga Kerja</option>
                            <option value="Peralatan/Sewa">Peralatan/Sewa</option>
                            <option value="Honor & Operasional">Honor & Operasional</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Uraian Pekerjaan / Nama Barang *</label>
                    <input type="text" name="name" required placeholder="Contoh: Pemasangan Atap Pelana..." class="w-full px-3 py-2 border rounded-xl">
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Volume *</label>
                        <input type="number" step="any" name="volume" required value="1" class="w-full px-3 py-2 border rounded-xl font-mono">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Satuan *</label>
                        <input type="text" name="unit" required placeholder="m², m³, Paket, OH..." class="w-full px-3 py-2 border rounded-xl">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Harga Satuan (Rp) *</label>
                        <input type="number" step="any" name="unit_price" required placeholder="0" class="w-full px-3 py-2 border rounded-xl font-mono">
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Catatan / Spesifikasi Teknis</label>
                    <input type="text" name="notes" placeholder="Keterangan / Divisi..." class="w-full px-3 py-2 border rounded-xl">
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" @click="openAddModal = false" class="px-4 py-2 rounded-xl text-slate-600 hover:bg-slate-100 font-semibold">Batal</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow-md shadow-emerald-600/20">Simpan Item</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Item RAB -->
    <div x-show="editModal" x-transition class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="display: none;">
        <div @click.away="editModal = false" class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
            <h3 class="font-bold text-sm text-slate-900 mb-4 border-b pb-2 flex items-center gap-2">
                <i data-lucide="edit" class="w-4 h-4 text-blue-600"></i>
                <span>Edit Pos Belanja RAB</span>
            </h3>
            
            <form :action="'{{ url('/rab') }}/' + (editItem.id || (activeItem ? activeItem.id : ''))" method="POST" class="space-y-4 text-xs">
                @csrf
                @method('PUT')

                <!-- Menu Pekerjaan Field -->
                <div class="space-y-1.5 p-3 rounded-xl bg-slate-50 border border-slate-200">
                    <label class="block font-bold text-slate-800">Menu Pekerjaan *</label>
                    <template x-if="!isCustomEditMenu">
                        <div class="flex items-center gap-2">
                            <select name="menu_pekerjaan" x-model="editMenu" required class="w-full px-3 py-2 border rounded-xl bg-white font-semibold text-slate-800">
                                @foreach($menuStats->keys() as $existingMenu)
                                    <option value="{{ $existingMenu }}">{{ $existingMenu }}</option>
                                @endforeach
                                @foreach($standardMenus as $std)
                                    @if(!$menuStats->has($std))
                                        <option value="{{ $std }}">{{ $std }}</option>
                                    @endif
                                @endforeach
                            </select>
                            <button type="button" @click="isCustomEditMenu = true; customEditMenu = ''" class="px-2.5 py-2 rounded-xl bg-white border border-slate-300 text-slate-600 hover:bg-slate-100 font-bold shrink-0">
                                + Baru
                            </button>
                        </div>
                    </template>
                    <template x-if="isCustomEditMenu">
                        <div class="flex items-center gap-2">
                            <input type="text" name="menu_pekerjaan" x-model="customEditMenu" required placeholder="Nama menu pekerjaan baru" class="w-full px-3 py-2 border rounded-xl bg-white font-semibold text-slate-800">
                            <button type="button" @click="isCustomEditMenu = false" class="px-2.5 py-2 rounded-xl bg-slate-200 text-slate-700 hover:bg-slate-300 font-bold shrink-0">
                                Batal
                            </button>
                        </div>
                    </template>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Kode Rekening / AHSP</label>
                        <input type="text" name="code" x-model="editItem.code" class="w-full px-3 py-2 border rounded-xl font-mono">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Kategori Belanja *</label>
                        <select name="category" required class="w-full px-3 py-2 border rounded-xl bg-white" x-model="editItem.category">
                            <option value="Material/Bahan">Material/Bahan</option>
                            <option value="Upah Tenaga Kerja">Upah Tenaga Kerja</option>
                            <option value="Peralatan/Sewa">Peralatan/Sewa</option>
                            <option value="Honor & Operasional">Honor & Operasional</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Uraian Pekerjaan / Nama Barang *</label>
                    <input type="text" name="name" required x-model="editItem.name" class="w-full px-3 py-2 border rounded-xl">
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Volume *</label>
                        <input type="number" step="any" name="volume" required x-model="editItem.volume" class="w-full px-3 py-2 border rounded-xl font-mono">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Satuan *</label>
                        <input type="text" name="unit" required x-model="editItem.unit" class="w-full px-3 py-2 border rounded-xl">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Harga Satuan (Rp) *</label>
                        <input type="number" step="any" name="unit_price" required x-model="editItem.unit_price" class="w-full px-3 py-2 border rounded-xl font-mono">
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Catatan</label>
                    <input type="text" name="notes" x-model="editItem.notes" class="w-full px-3 py-2 border rounded-xl">
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" @click="editModal = false" class="px-4 py-2 rounded-xl text-slate-600 hover:bg-slate-100 font-semibold">Batal</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold shadow-md shadow-blue-600/20">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Unggah & Pemetaan Otomatis RAB Excel -->
    <div x-show="openUploadModal" x-transition.opacity class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="display: none;">
        <div @click.away="if(!isSubmitting) openUploadModal = false" class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 border border-blue-200/80 flex items-center justify-center font-bold shrink-0">
                        <i data-lucide="file-spreadsheet" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-sm text-slate-900">Unggah File RAB (Excel / CSV)</h3>
                        <p class="text-[11px] text-slate-500">Impor &amp; petakan RAB sesuai Menu Pekerjaan sasaran.</p>
                    </div>
                </div>
                <button type="button" @click="openUploadModal = false" :disabled="isSubmitting" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
            
            <form action="{{ route('rab.import') }}" method="POST" enctype="multipart/form-data" @submit="isSubmitting = true" class="space-y-4 text-xs">
                @csrf
                <input type="hidden" name="project_id" value="{{ $selectedProjectId }}">

                <!-- Menu Pekerjaan Sasaran -->
                <div class="p-3.5 rounded-xl bg-emerald-50/60 border border-emerald-200 space-y-2">
                    <label class="block font-bold text-emerald-950 text-xs">
                        Pilih Menu Pekerjaan Sasaran Untuk File Ini *
                    </label>
                    <p class="text-[11px] text-slate-600">
                        Pilih ke menu mana rincian belanja dalam file ini akan dipetakan (misal: Rehab Ruang Kelas atau Rehab Perpus):
                    </p>

                    <template x-if="!isCustomUploadMenu">
                        <div class="flex items-center gap-2">
                            <select name="menu_pekerjaan" x-model="uploadMenu" required class="w-full px-3 py-2 border rounded-xl bg-white font-bold text-slate-800 text-xs focus:ring-2 focus:ring-emerald-500">
                                @foreach($menuStats->keys() as $existingMenu)
                                    <option value="{{ $existingMenu }}">{{ $existingMenu }}</option>
                                @endforeach
                                @foreach($standardMenus as $std)
                                    @if(!$menuStats->has($std))
                                        <option value="{{ $std }}">{{ $std }}</option>
                                    @endif
                                @endforeach
                            </select>
                            <button type="button" @click="isCustomUploadMenu = true; customUploadMenu = ''" class="px-3 py-2 rounded-xl bg-white border border-emerald-300 text-emerald-700 hover:bg-emerald-50 font-bold shrink-0">
                                + Menu Baru
                            </button>
                        </div>
                    </template>

                    <template x-if="isCustomUploadMenu">
                        <div class="flex items-center gap-2">
                            <input type="text" name="menu_pekerjaan" x-model="customUploadMenu" required placeholder="Contoh: Pembangunan Ruang UKS / Pagar" class="w-full px-3 py-2 border rounded-xl bg-white font-bold text-slate-800 text-xs">
                            <button type="button" @click="isCustomUploadMenu = false" class="px-3 py-2 rounded-xl bg-slate-200 text-slate-700 hover:bg-slate-300 font-bold shrink-0">
                                Pilih Daftar
                            </button>
                        </div>
                    </template>
                </div>

                <!-- Drag & Drop File Zone -->
                <div class="relative border-2 border-dashed border-slate-300 hover:border-emerald-500 rounded-2xl p-6 text-center transition-all bg-slate-50/50 group"
                     :class="{ 'border-emerald-500 bg-emerald-50/20': fileName }">
                    <input type="file" name="rab_file" id="rab_file" accept=".xlsx,.xls,.csv" required
                           @change="handleFileSelect($event)"
                           class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">

                    <div class="space-y-2 pointer-events-none">
                        <div class="w-12 h-12 mx-auto rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                            <i data-lucide="upload-cloud" class="w-6 h-6"></i>
                        </div>
                        <template x-if="!fileName">
                            <div>
                                <span class="font-bold text-slate-700 text-xs">Klik untuk pilih berkas RAB</span>
                                <span class="text-slate-400"> atau seret ke sini</span>
                                <p class="text-[11px] text-slate-400 mt-1">Mendukung format Excel (.xlsx, .xls) dan CSV (.csv) hingga 20 MB</p>
                            </div>
                        </template>
                        <template x-if="fileName">
                            <div>
                                <span class="font-bold text-emerald-700 text-xs" x-text="fileName"></span>
                                <p class="text-[11px] text-slate-500 mt-0.5" x-text="'Ukuran berkas: ' + fileSize"></p>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Opsi Mode Impor -->
                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-2">
                    <label class="block font-bold text-slate-800 text-xs">Pilihan Mode Impor</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <label class="flex items-start gap-2.5 p-2.5 rounded-lg border border-emerald-300 bg-white cursor-pointer shadow-sm">
                            <input type="radio" name="mode" value="replace" checked class="mt-0.5 text-emerald-600 focus:ring-emerald-500 border-slate-300">
                            <div>
                                <div class="font-bold text-slate-800 text-[11px]">Reset Menu Ini Saja</div>
                                <p class="text-[10px] text-slate-500 leading-tight">Menggantikan item pada menu yang dipilih saja. <strong>Item pada menu lain tetap aman!</strong></p>
                            </div>
                        </label>
                        <label class="flex items-start gap-2.5 p-2.5 rounded-lg border border-slate-200 bg-white cursor-pointer hover:border-slate-300 shadow-sm">
                            <input type="radio" name="mode" value="append" class="mt-0.5 text-emerald-600 focus:ring-emerald-500 border-slate-300">
                            <div>
                                <div class="font-bold text-slate-800 text-[11px]">Tambahkan (Append)</div>
                                <p class="text-[10px] text-slate-500 leading-tight">Menambahkan baris baru ke menu ini tanpa menghapus data sebelumnya.</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Opsi Filter Kuantitas 0 -->
                <div class="flex items-center gap-2 px-1">
                    <input type="checkbox" name="skip_zero_volume" id="skip_zero_volume" value="1" checked class="rounded text-emerald-600 focus:ring-emerald-500 border-slate-300">
                    <label for="skip_zero_volume" class="font-medium text-slate-700 text-xs cursor-pointer select-none">
                        Hanya impor item bervolume &gt; 0 (Abaikan baris master AHSP kosong)
                    </label>
                </div>

                <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                    <a href="{{ route('rab.template') }}" class="text-[11px] font-semibold text-emerald-600 hover:text-emerald-700 flex items-center gap-1">
                        <i data-lucide="download" class="w-3 h-3"></i>
                        <span>Unduh Format Excel RAB</span>
                    </a>

                    <div class="flex items-center gap-2">
                        <button type="button" @click="openUploadModal = false" :disabled="isSubmitting" class="px-4 py-2 rounded-xl text-slate-600 hover:bg-slate-100 font-semibold">
                            Batal
                        </button>
                        <button type="submit" :disabled="isSubmitting" class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold flex items-center gap-2 shadow-md shadow-blue-600/25 transition-all hover:-translate-y-0.5">
                            <template x-if="isSubmitting">
                                <span class="inline-flex items-center gap-1.5">
                                    <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                    <span>Memproses...</span>
                                </span>
                            </template>
                            <template x-if="!isSubmitting">
                                <span class="inline-flex items-center gap-1.5">
                                    <i data-lucide="check" class="w-4 h-4"></i>
                                    <span>Impor ke Menu Ini</span>
                                </span>
                            </template>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
