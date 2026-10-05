@extends('layouts.app')

@section('title', 'Rencana Anggaran Biaya (RAB)')

@section('content')
<div class="space-y-6" x-data="{ openAddModal: false, editModal: false, activeItem: null }">

    <!-- Header & Controls -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-emerald-600 mb-1">
                <i data-lucide="clipboard-list" class="w-4 h-4"></i>
                <span>Pagu & Struktur Pembiayaan</span>
            </div>
            <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">
                Rencana Anggaran Biaya (RAB)
            </h1>
            <p class="text-xs text-slate-500">Rincian komponen belanja bahan material, upah tukang, sewa peralatan, dan operasional.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <!-- Project Selector -->
            <form action="{{ route('rab.index') }}" method="GET">
                <select name="project_id" onchange="this.form.submit()" class="text-xs font-semibold px-3 py-2 rounded-xl border border-slate-300 bg-slate-50">
                    @foreach($projects as $p)
                        <option value="{{ $p->id }}" {{ $selectedProjectId == $p->id ? 'selected' : '' }}>
                            {{ $p->school->name }} - {{ $p->title }}
                        </option>
                    @endforeach
                </select>
            </form>

            @permission('rab.create')
            <button @click="openAddModal = true" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-md shadow-emerald-600/20 transition-all">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>Tambah Item RAB</span>
            </button>
            @endpermission
        </div>
    </div>

    <!-- Summary Box -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[11px] font-semibold text-slate-500 uppercase">Pagu Kontrak Proyek</span>
            <div class="text-base font-bold text-slate-900 mt-1">@rupiah($selectedProject->contract_amount ?? 0)</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[11px] font-semibold text-emerald-600 uppercase">Total Alokasi Rincian RAB</span>
            <div class="text-base font-bold text-emerald-600 mt-1">@rupiah($totalRAB)</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[11px] font-semibold text-blue-600 uppercase">Selisih Pagu vs RAB</span>
            <div class="text-base font-bold text-blue-600 mt-1">@rupiah(($selectedProject->contract_amount ?? 0) - $totalRAB)</div>
        </div>
    </div>

    <!-- RAB Items Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px]">
                        <th class="py-3 px-3">Kode</th>
                        <th class="py-3 px-3">Kategori</th>
                        <th class="py-3 px-4">Uraian Belanja / Item</th>
                        <th class="py-3 px-3 text-center">Volume</th>
                        <th class="py-3 px-3 text-right">Harga Satuan (Rp)</th>
                        <th class="py-3 px-3 text-right">Total Pagu (Rp)</th>
                        <th class="py-3 px-3 text-right">Realisasi (Rp)</th>
                        <th class="py-3 px-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($items as $item)
                    <tr class="hover:bg-slate-50/70">
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
                                <button type="button" @click="editModal = true; activeItem = {{ json_encode($item) }}" class="p-1 text-blue-600 hover:bg-blue-50 rounded">
                                    <i data-lucide="edit" class="w-3.5 h-3.5"></i>
                                </button>
                                @endpermission
                                @permission('rab.delete')
                                <form action="{{ route('rab.destroy', $item->id) }}" method="POST" class="inline" onsubmit="return confirm('Hapus item RAB ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1 text-rose-600 hover:bg-rose-50 rounded">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                    </button>
                                </form>
                                @endpermission
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-8 text-center text-slate-400 text-xs">
                            Belum ada item pos belanja RAB untuk paket kegiatan ini.
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
            <h3 class="font-bold text-sm text-slate-900 mb-4 border-b pb-2">Tambah Pos Belanja RAB</h3>
            
            <form action="{{ route('rab.store') }}" method="POST" class="space-y-4 text-xs">
                @csrf
                <input type="hidden" name="project_id" value="{{ $selectedProjectId }}">

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Kode Rekening</label>
                        <input type="text" name="code" placeholder="5.2.2.01..." class="w-full px-3 py-2 border rounded-xl font-mono">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Kategori Belanja *</label>
                        <select name="category" required class="w-full px-3 py-2 border rounded-xl bg-slate-50">
                            <option value="Material/Bahan">Material/Bahan</option>
                            <option value="Upah Tenaga Kerja">Upah Tenaga Kerja</option>
                            <option value="Peralatan/Sewa">Peralatan/Sewa</option>
                            <option value="Honor & Operasional">Honor & Operasional</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Uraian Pekerjaan / Nama Barang *</label>
                    <input type="text" name="name" required placeholder="Contoh: Semen Gresik 40 kg" class="w-full px-3 py-2 border rounded-xl">
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Volume *</label>
                        <input type="number" step="any" name="volume" required value="1" class="w-full px-3 py-2 border rounded-xl font-mono">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Satuan *</label>
                        <input type="text" name="unit" required placeholder="sak, m3, OH, dll" class="w-full px-3 py-2 border rounded-xl">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Harga Satuan (Rp) *</label>
                        <input type="number" step="any" name="unit_price" required placeholder="0" class="w-full px-3 py-2 border rounded-xl font-mono">
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Catatan / Spesifikasi Teknis</label>
                    <input type="text" name="notes" placeholder="Keterangan..." class="w-full px-3 py-2 border rounded-xl">
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" @click="openAddModal = false" class="px-4 py-2 rounded-xl text-slate-600 hover:bg-slate-100">Batal</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold">Simpan Item</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Item RAB -->
    <div x-show="editModal" x-transition class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="display: none;">
        <div @click.away="editModal = false" class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
            <h3 class="font-bold text-sm text-slate-900 mb-4 border-b pb-2">Edit Pos Belanja RAB</h3>
            
            <form :action="'{{ url('/rab') }}/' + (activeItem ? activeItem.id : '')" method="POST" class="space-y-4 text-xs">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Kode Rekening</label>
                        <input type="text" name="code" :value="activeItem ? activeItem.code : ''" class="w-full px-3 py-2 border rounded-xl font-mono">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Kategori Belanja *</label>
                        <select name="category" required class="w-full px-3 py-2 border rounded-xl bg-slate-50" x-model="activeItem ? activeItem.category : ''">
                            <option value="Material/Bahan">Material/Bahan</option>
                            <option value="Upah Tenaga Kerja">Upah Tenaga Kerja</option>
                            <option value="Peralatan/Sewa">Peralatan/Sewa</option>
                            <option value="Honor & Operasional">Honor & Operasional</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Uraian Pekerjaan / Nama Barang *</label>
                    <input type="text" name="name" required :value="activeItem ? activeItem.name : ''" class="w-full px-3 py-2 border rounded-xl">
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Volume *</label>
                        <input type="number" step="any" name="volume" required :value="activeItem ? activeItem.volume : ''" class="w-full px-3 py-2 border rounded-xl font-mono">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Satuan *</label>
                        <input type="text" name="unit" required :value="activeItem ? activeItem.unit : ''" class="w-full px-3 py-2 border rounded-xl">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Harga Satuan (Rp) *</label>
                        <input type="number" step="any" name="unit_price" required :value="activeItem ? activeItem.unit_price : ''" class="w-full px-3 py-2 border rounded-xl font-mono">
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Catatan</label>
                    <input type="text" name="notes" :value="activeItem ? activeItem.notes : ''" class="w-full px-3 py-2 border rounded-xl">
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" @click="editModal = false" class="px-4 py-2 rounded-xl text-slate-600 hover:bg-slate-100">Batal</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

