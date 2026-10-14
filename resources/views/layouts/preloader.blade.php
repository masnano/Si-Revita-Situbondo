<!-- ======================================================================= -->
<!-- FUTURISTIC QUANTUM PRELOADER & ANTI-DOUBLE SUBMIT OVERLAY (SI-REVITA)  -->
<!-- ======================================================================= -->
<div id="futuristic-preloader"
     class="fixed inset-0 z-[999999] hidden items-center justify-center bg-slate-950/85 backdrop-blur-xl transition-all duration-300 select-none pointer-events-auto"
     role="dialog" aria-modal="true" aria-live="assertive">

    <!-- Ambient Neon Background Orbs -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[550px] h-[550px] bg-gradient-to-tr from-emerald-600/20 via-teal-500/20 to-cyan-500/25 rounded-full blur-[100px] animate-pulse"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[350px] h-[350px] bg-cyan-400/15 rounded-full blur-[70px] animate-ping" style="animation-duration: 4s;"></div>
    </div>

    <!-- Futuristic Cyber HUD Card -->
    <div class="relative w-full max-w-md mx-4 p-7 sm:p-9 rounded-3xl bg-slate-900/95 border border-cyan-500/30 shadow-[0_0_80px_rgba(6,182,212,0.25)] text-center ring-1 ring-white/10 overflow-hidden backdrop-blur-2xl">
        
        <!-- Laser Scanner Beam -->
        <div class="laser-scanner-line pointer-events-none"></div>

        <!-- Cyber Corner Accents -->
        <div class="absolute top-3 left-3 w-3 h-3 border-t-2 border-l-2 border-cyan-400/80"></div>
        <div class="absolute top-3 right-3 w-3 h-3 border-t-2 border-r-2 border-cyan-400/80"></div>
        <div class="absolute bottom-3 left-3 w-3 h-3 border-b-2 border-l-2 border-cyan-400/80"></div>
        <div class="absolute bottom-3 right-3 w-3 h-3 border-b-2 border-r-2 border-cyan-400/80"></div>

        <!-- Concentric Holographic Orbital Rings -->
        <div class="relative w-28 h-28 mx-auto mb-6 flex items-center justify-center">
            <!-- Ring 1: Outer cyan dashed spinning clockwise -->
            <div class="absolute inset-0 rounded-full border-2 border-dashed border-cyan-400/60 orbital-spin-cw shadow-[0_0_20px_rgba(6,182,212,0.4)]"></div>
            
            <!-- Ring 2: Middle emerald gradient spinning counter-clockwise -->
            <div class="absolute inset-2.5 rounded-full border-2 border-emerald-400/80 orbital-spin-ccw shadow-[0_0_25px_rgba(16,185,129,0.5)]">
                <!-- Orbital satellites -->
                <div class="absolute -top-1.5 left-1/2 -translate-x-1/2 w-3 h-3 rounded-full bg-cyan-300 shadow-[0_0_10px_#22d3ee]"></div>
                <div class="absolute -bottom-1.5 left-1/2 -translate-x-1/2 w-3 h-3 rounded-full bg-emerald-300 shadow-[0_0_10px_#34d399]"></div>
            </div>

            <!-- Ring 3: Inner glowing teal pulse -->
            <div class="absolute inset-5 rounded-full border border-teal-300/40 bg-teal-500/10 animate-pulse"></div>

            <!-- Central Hologram Icon / Logo Core -->
            <div class="relative z-10 w-12 h-12 rounded-2xl bg-gradient-to-tr from-slate-950 via-slate-900 to-emerald-950 border border-cyan-400/60 flex items-center justify-center shadow-lg shadow-cyan-500/30">
                <i data-lucide="cpu" class="w-6 h-6 text-cyan-300 animate-pulse"></i>
            </div>
        </div>

        <!-- Security Header Badge -->
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-cyan-500/10 border border-cyan-500/30 text-[10px] font-mono uppercase tracking-widest text-cyan-300 mb-3 shadow-xs">
            <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-400"></span>
            </span>
            <span>SI-REVITA QUANTUM ENGINE</span>
        </div>

        <!-- Dynamic Main Title -->
        <h3 id="futuristic-preloader-title" 
            class="text-base sm:text-lg font-black tracking-wider text-transparent bg-clip-text bg-gradient-to-r from-emerald-300 via-teal-200 to-cyan-300 uppercase font-mono leading-tight">
            MEMPROSES DATA SISTEM...
        </h3>

        <!-- Dynamic Subtitle -->
        <p id="futuristic-preloader-subtitle" 
           class="text-xs sm:text-sm text-slate-300 mt-2 font-medium leading-relaxed max-w-sm mx-auto">
            Mohon tunggu sejenak, sistem sedang mengamankan sesi dan memproses data Anda...
        </p>

        <!-- Animated Shimmer Laser Progress Bar -->
        <div class="mt-6 relative w-full h-2 rounded-full bg-slate-800/80 overflow-hidden border border-cyan-500/30 p-0.5 shadow-inner">
            <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 via-teal-400 to-cyan-400 laser-progress-bar shadow-[0_0_15px_#22d3ee]"></div>
        </div>

        <!-- Real-Time Micro Ticker -->
        <div class="mt-4 flex items-center justify-center gap-2 text-[11px] font-mono text-emerald-400/90">
            <i data-lucide="shield-check" class="w-3.5 h-3.5 shrink-0 text-emerald-400"></i>
            <span id="futuristic-preloader-ticker">Mengunci sesi transaksi anti-double submit...</span>
        </div>

        <!-- Emergency / Manual Dismissal Link (Visible after 10s if download/network finished) -->
        <div id="futuristic-preloader-dismiss-wrap" class="hidden mt-4 pt-3 border-t border-slate-800/80">
            <button type="button" 
                    onclick="window.hidePreloader()" 
                    class="text-[11px] font-mono text-slate-400 hover:text-cyan-300 transition-colors underline focus:outline-none">
                Tutup jendela jika proses / unduhan telah selesai &times;
            </button>
        </div>

    </div>
</div>

<style>
    /* Concentric Orbital Spin Animations */
    @keyframes orbitalSpinCw {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    @keyframes orbitalSpinCcw {
        from { transform: rotate(0deg); }
        to { transform: rotate(-360deg); }
    }
    .orbital-spin-cw {
        animation: orbitalSpinCw 4.5s linear infinite;
    }
    .orbital-spin-ccw {
        animation: orbitalSpinCcw 2.5s linear infinite;
    }

    /* Laser Scanner Beam */
    @keyframes laserScanner {
        0% {
            top: -10%;
            opacity: 0;
        }
        25% {
            opacity: 0.8;
        }
        75% {
            opacity: 0.8;
        }
        100% {
            top: 110%;
            opacity: 0;
        }
    }
    .laser-scanner-line {
        position: absolute;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(90deg, transparent 0%, rgba(34, 211, 238, 0.2) 20%, rgba(34, 211, 238, 0.9) 50%, rgba(52, 211, 153, 0.9) 70%, transparent 100%);
        box-shadow: 0 0 15px rgba(34, 211, 238, 0.8), 0 0 30px rgba(52, 211, 153, 0.6);
        animation: laserScanner 2.8s ease-in-out infinite;
        z-index: 20;
    }

    /* Laser Progress Bar */
    @keyframes laserProgressBar {
        0% {
            width: 5%;
            margin-left: 0%;
        }
        50% {
            width: 70%;
            margin-left: 15%;
        }
        100% {
            width: 25%;
            margin-left: 75%;
        }
    }
    .laser-progress-bar {
        animation: laserProgressBar 1.8s ease-in-out infinite alternate;
    }
</style>

<script>
(function() {
    let tickerInterval = null;
    let dismissTimeout = null;

    const tickerPhrases = [
        "Mengunci sesi transaksi anti-double submit...",
        "Verifikasi token keamanan & enkripsi data...",
        "Menyelaraskan kalkulasi pembukuan perbendaharaan...",
        "Validasi integritas Buku Kas Umum (BKU)...",
        "Menghubungkan ke basis data terenkripsi...",
        "Memproses sinkronisasi kepatuhan BPK RI..."
    ];

    /**
     * Menampilkan preloader futuristis dengan judul & subjudul dinamis.
     */
    window.showPreloader = function(title, subtitle) {
        const el = document.getElementById('futuristic-preloader');
        if (!el) return;

        const titleEl = document.getElementById('futuristic-preloader-title');
        const subtitleEl = document.getElementById('futuristic-preloader-subtitle');
        const tickerEl = document.getElementById('futuristic-preloader-ticker');
        const dismissWrap = document.getElementById('futuristic-preloader-dismiss-wrap');

        if (title && titleEl) titleEl.innerText = title;
        if (subtitle && subtitleEl) subtitleEl.innerText = subtitle;

        if (dismissWrap) dismissWrap.classList.add('hidden');

        // Tampilkan modal
        el.classList.remove('hidden');
        el.classList.add('flex');

        // Refresh lucide icons
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }

        // Jalankan rotasi ticker
        let phraseIdx = 0;
        if (tickerInterval) clearInterval(tickerInterval);
        tickerInterval = setInterval(function() {
            phraseIdx = (phraseIdx + 1) % tickerPhrases.length;
            if (tickerEl) {
                tickerEl.innerText = tickerPhrases[phraseIdx];
            }
        }, 1300);

        // Setelah 10 detik, munculkan tombol darurat tutup jika respon berupa file download
        if (dismissTimeout) clearTimeout(dismissTimeout);
        dismissTimeout = setTimeout(function() {
            if (dismissWrap) {
                dismissWrap.classList.remove('hidden');
            }
        }, 10000);
    };

    /**
     * Menyembunyikan preloader dan menghentikan ticker.
     */
    window.hidePreloader = function() {
        const el = document.getElementById('futuristic-preloader');
        if (!el) return;

        el.classList.remove('flex');
        el.classList.add('hidden');

        if (tickerInterval) {
            clearInterval(tickerInterval);
            tickerInterval = null;
        }
        if (dismissTimeout) {
            clearTimeout(dismissTimeout);
            dismissTimeout = null;
        }

        // Buka kembali form submit lock
        document.querySelectorAll('form[data-submitting="true"]').forEach(function(f) {
            delete f.dataset.submitting;
            f.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function(b) {
                b.disabled = false;
                b.classList.remove('opacity-75', 'cursor-not-allowed', 'pointer-events-none');
            });
        });
    };

    /**
     * Mendeteksi pesan informatif & judul futuristis sesuai konteks form/modul.
     */
    window.getFormPreloaderInfo = function(form) {
        const action = (form.getAttribute('action') || window.location.pathname).toLowerCase();
        const method = (form.querySelector('input[name="_method"]')?.value || form.getAttribute('method') || 'GET').toUpperCase();
        
        // 1. Modul RPD
        if (action.includes('/rpd')) {
            if (action.includes('post-bku') || action.includes('post_bku')) {
                return {
                    title: 'MEMBUKUKAN KE BUKU KAS UMUM (BKU)',
                    subtitle: 'Menyimpan transaksi belanja, mencatat Saldo Masuk penerimaan termin SP2D, dan sinkronisasi kas tunai...'
                };
            }
            if (action.includes('unpost')) {
                return {
                    title: 'MEMBATALKAN POSTING BKU',
                    subtitle: 'Menghapus transaksi terkait di BKU dan mengembalikan seluruh item ke status Draf Siap Posting...'
                };
            }
            if (action.includes('sync-disbursement') || action.includes('sync_disbursement')) {
                return {
                    title: 'SINKRONISASI SALDO MASUK SP2D',
                    subtitle: 'Memastikan saldo masuk termin dan penarikan tunai kas tercatat balance di BKU...'
                };
            }
            if (action.includes('ai-enhance') || action.includes('ai_enhance')) {
                return {
                    title: 'OPTIMASI CERDAS GOOGLE GEMINI AI',
                    subtitle: 'AI sedang menyelaraskan fase konstruksi, memvariasikan narasi BKU resmi, dan memverifikasi toko rekanan...'
                };
            }
            // Upload berkas RPD (create / store)
            const fileInput = form.querySelector('input[type="file"][name="rpd_file"]');
            if (fileInput) {
                const useAi = form.querySelector('input[name="use_ai"]');
                const isAiActive = !useAi || useAi.checked;
                return {
                    title: isAiActive ? 'MEMPROSES RPD & PECAH BAHAN AI' : 'MENGUNGGAH BERKAS RPD REVITALISASI',
                    subtitle: isAiActive 
                        ? 'Google Gemini AI sedang menganalisis berkas, memecah biaya mobilisasi (ongkos kirim bahan), dan menyusun operasional tim P2SP anti-temuan BPK...'
                        : 'Mengekstrak baris data tabel RPD, memvalidasi nominal termin, dan menyusun draf rincian BKU...'
                };
            }
        }

        // 2. Modul RAB
        if (action.includes('/rab')) {
            if (form.querySelector('input[type="file"][name="rab_file"]')) {
                return {
                    title: 'MENGUNGGAH ANGGARAN RAB REVITALISASI',
                    subtitle: 'Membaca pos anggaran, volume, dan harga satuan material/upah/alat revitalisasi...'
                };
            }
            if (method === 'DELETE') {
                return {
                    title: 'MENGHAPUS POS ANGGARAN RAB',
                    subtitle: 'Menghapus pos belanja dan mereposisi data anggaran RAB...'
                };
            }
            return {
                title: 'MEMPERBARUI ANGGARAN RAB',
                subtitle: 'Menyimpan perubahan pos belanja revitalisasi satuan pendidikan...'
            };
        }

        // 3. Modul Transaksi & Pembukuan Kas
        if (action.includes('/transactions')) {
            if (method === 'DELETE') {
                return {
                    title: 'MENGHAPUS TRANSAKSI BKU',
                    subtitle: 'Menghapus pencatatan transaksi dan memperbarui rekonsiliasi saldo kas perbendaharaan...'
                };
            }
            if (method === 'PUT' || method === 'PATCH') {
                return {
                    title: 'MEMPERBARUI TRANSAKSI BKU',
                    subtitle: 'Menyimpan penyesuaian transaksi dan merekonsiliasi saldo Buku Kas Umum...'
                };
            }
            return {
                title: 'MENYIMPAN TRANSAKSI PERBENDAHARAAN',
                subtitle: 'Memvalidasi nomor bukti, menghitung potongan pajak PPN/PPh, dan membukukan ke BKU...'
            };
        }

        // 4. Modul Pajak & Audit AI
        if (action.includes('auto-setor-pajak') || action.includes('auto_setor_pajak')) {
            return {
                title: 'SETOR OTOMATIS PAJAK NEGARA (AI)',
                subtitle: 'Menghasilkan kode NTPN valid dan membukukan setoran pajak ke Buku Pembantu Pajak...'
            };
        }
        if (action.includes('ai_audit') || action.includes('ai-audit')) {
            return {
                title: 'AUDIT KEPATUHAN SPJ GOOGLE GEMINI AI',
                subtitle: 'Memeriksa kepatuhan perpajakan, mendeteksi anomali bukti, dan mengevaluasi kesiapan audit BPK RI...'
            };
        }

        // 5. Modul Login / Otentikasi
        if (action.includes('/login')) {
            return {
                title: 'OTENTIKASI PENGGUNA SI-REVITA',
                subtitle: 'Memverifikasi kredensial akun dan hak akses perbendaharaan sekolah...'
            };
        }

        // 6. Modul Satuan Pendidikan, Pengguna & Konfigurasi
        if (action.includes('/schools')) {
            return {
                title: 'MENGELOLA DATA SATUAN PENDIDIKAN',
                subtitle: 'Menyimpan data master sekolah dan konfigurasi perbendaharaan...'
            };
        }
        if (action.includes('/projects')) {
            return {
                title: 'MENGELOLA PROYEK REVITALISASI',
                subtitle: 'Menyimpan paket pekerjaan dan anggaran fisik revitalisasi...'
            };
        }
        if (action.includes('/users')) {
            return {
                title: 'MENGELOLA DATA PENGGUNA',
                subtitle: 'Menyimpan kredensial dan hak akses akun perbendaharaan...'
            };
        }
        if (action.includes('/roles')) {
            return {
                title: 'MENGELOLA PERAN & HAK AKSES',
                subtitle: 'Memperbarui konfigurasi hak akses pengguna...'
            };
        }
        if (action.includes('/laporan') || action.includes('/reports')) {
            return {
                title: 'MEMPROSES LAPORAN PERBENDAHARAAN',
                subtitle: 'Menyusun rekonsiliasi kas, memformat dokumen buku pembantu, dan memvalidasi saldo...'
            };
        }

        // 7. Penghapusan Data (Generic DELETE)
        if (method === 'DELETE') {
            return {
                title: 'MENGHAPUS DATA SISTEM',
                subtitle: 'Memproses penghapusan data secara aman dari sistem...'
            };
        }

        // Default fallback
        const customTitle = form.getAttribute('data-loading-title');
        const customSubtitle = form.getAttribute('data-loading-subtitle');
        return {
            title: customTitle || 'MEMPROSES DATA SISTEM...',
            subtitle: customSubtitle || 'Mohon tunggu, sistem sedang memproses data untuk menjaga integritas transaksi...'
        };
    };

    /**
     * Memanggil preloader untuk form yang sudah dikonfirmasi.
     */
    window.triggerFormPreloader = function(form) {
        if (!form) return;
        form.dataset.submitting = 'true';

        const submitBtns = form.querySelectorAll('button[type="submit"], input[type="submit"]');
        submitBtns.forEach(function(btn) {
            btn.disabled = true;
            btn.classList.add('opacity-75', 'cursor-not-allowed', 'pointer-events-none');
        });

        const info = window.getFormPreloaderInfo(form);
        window.showPreloader(info.title, info.subtitle);
    };

    /**
     * Intersepsi global seluruh form submit untuk mencegah double submit.
     */
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (!form || form.tagName !== 'FORM') return;

        // Abaikan form yang ditandai no-preloader
        if (form.dataset.noPreloader === 'true') return;

        // Cegah pengiriman ganda jika form sudah dalam proses kirim
        if (form.dataset.submitting === 'true') {
            e.preventDefault();
            e.stopImmediatePropagation();
            return false;
        }

        // Cek validasi HTML5 bawaan browser
        if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
            return;
        }

        // Jika target _blank (misal pratinjau cetak di tab baru)
        if (form.target === '_blank') {
            window.showPreloader('Membuka Laporan / Dokumen...', 'Mempersiapkan dokumen di tab baru...');
            setTimeout(function() {
                window.hidePreloader();
            }, 2500);
            return;
        }

        // Jika form memerlukan konfirmasi modal, biarkan modal konfirmasi berjalan lebih dulu
        const isDelete = form.querySelector('input[name="_method"][value="DELETE"]') !== null;
        const hasConfirmAttr = form.hasAttribute('data-confirm');
        const onsubmitAttr = form.getAttribute('onsubmit') || '';
        const hasConfirmOnsubmit = onsubmitAttr.includes('confirm(');

        if ((isDelete || hasConfirmAttr || hasConfirmOnsubmit) && form.dataset.confirmed !== 'true') {
            return;
        }

        // Kunci form dari double submission
        form.dataset.submitting = 'true';

        // Nonaktifkan tombol submit
        const submitBtns = form.querySelectorAll('button[type="submit"], input[type="submit"]');
        submitBtns.forEach(function(btn) {
            btn.disabled = true;
            btn.classList.add('opacity-75', 'cursor-not-allowed', 'pointer-events-none');
        });

        // Tampilkan preloader futuristis
        const info = window.getFormPreloaderInfo(form);
        window.showPreloader(info.title, info.subtitle);
    }, true);

    // Buka kunci form jika pengguna menekan tombol Back di browser (bfcache)
    window.addEventListener('pageshow', function(e) {
        window.hidePreloader();
    });

    // Inisialisasi ikon jika DOM sudah siap
    document.addEventListener('DOMContentLoaded', function() {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    });
})();
</script>
