@php
    $initialUnreadCount = 0;
    if (auth()->check()) {
        $initialUnreadCount = \App\Models\SystemNotification::whereDoesntHave('reads', function ($query) {
            $query->where('user_id', auth()->id());
        })->count();
    }
@endphp

<!-- Notifications Dropdown & Push Manager -->
<div class="relative" 
     :class="{ 'z-[100]': isOpen }"
     style="position: relative;"
     :style="isOpen ? 'z-index: 100;' : ''"
     x-data="notificationManager({{ $initialUnreadCount }})"
     x-init="init()"
     @mouseenter="onMouseEnter()" 
     @mouseleave="onMouseLeave()"
     @click.outside="isOpen = false">

    <!-- Bell Trigger Button -->
    <button type="button" 
            @click="toggleOpen()"
            class="relative p-2 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
            :class="{ 'bg-slate-100 text-slate-900': isOpen }"
            title="Notifikasi Aktivitas Sistem">
        <i data-lucide="bell" class="w-4 h-4 transition-transform duration-300" :class="{ 'rotate-12': unreadCount > 0 }"></i>
        
        <!-- Notification Counter Badge -->
        <span x-cloak
              x-show="unreadCount > 0" 
              x-text="unreadCount > 99 ? '99+' : unreadCount"
              class="absolute -top-1 -right-1 flex h-4 min-w-[1rem] px-1 items-center justify-center rounded-full bg-rose-600 text-[9px] font-extrabold text-white shadow-sm ring-2 ring-white animate-pulse">
            {{ $initialUnreadCount > 0 ? ($initialUnreadCount > 99 ? '99+' : $initialUnreadCount) : '' }}
        </span>
    </button>

    <!-- Dropdown Modal / Popover -->
    <div x-cloak
         x-show="isOpen" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-1 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-1 scale-95"
         class="absolute right-0 top-full mt-2 w-80 sm:w-[360px] max-w-[calc(100vw-2rem)] bg-white rounded-2xl shadow-2xl border border-slate-200/90 py-0 z-[100] overflow-hidden text-xs origin-top-right"
         style="z-index: 100;">
        
        <!-- Dropdown Header -->
        <div class="px-4 py-3 bg-slate-50/90 border-b border-slate-100 flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <div class="p-1.5 rounded-lg bg-emerald-100 text-emerald-700">
                    <i data-lucide="bell-ring" class="w-4 h-4"></i>
                </div>
                <div>
                    <div class="font-bold text-slate-800 text-sm flex items-center gap-1.5">
                        <span>Notifikasi</span>
                        <span x-show="unreadCount > 0" 
                              x-text="unreadCount + ' Baru'"
                              class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700"></span>
                    </div>
                    <div class="text-[10px] text-slate-500">Aktivitas penambahan & transaksi data</div>
                </div>
            </div>

            <!-- Read All Button -->
            <button type="button" 
                    @click.stop="markAllAsRead()"
                    :disabled="unreadCount === 0 || loadingMarkAll"
                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold transition-all"
                    :class="unreadCount > 0 
                        ? 'text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 active:scale-95' 
                        : 'text-slate-400 bg-slate-100 border border-slate-200/60 cursor-not-allowed opacity-60'"
                    title="Tandai semua notifikasi telah dibaca">
                <i data-lucide="check-check" class="w-3.5 h-3.5"></i>
                <span x-text="loadingMarkAll ? 'Memproses...' : 'Read All'">Read All</span>
            </button>
        </div>

        <!-- Notification List -->
        <div class="max-h-[min(380px,calc(100vh-14rem))] overflow-y-auto divide-y divide-slate-100">
            <!-- Loading Indicator -->
            <div x-show="loading && notifications.length === 0" class="py-8 text-center text-slate-400">
                <div class="inline-block animate-spin rounded-full h-5 w-5 border-2 border-emerald-500 border-t-transparent mb-2"></div>
                <div class="text-xs">Memuat notifikasi...</div>
            </div>

            <!-- Empty State -->
            <div x-show="!loading && notifications.length === 0" class="py-10 px-4 text-center">
                <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-2.5">
                    <i data-lucide="bell-off" class="w-6 h-6"></i>
                </div>
                <div class="font-bold text-slate-700 text-xs">Belum Ada Notifikasi</div>
                <div class="text-[11px] text-slate-400 mt-0.5">Penambahan Sekolah, Proyek, RAB, RPD & Posting BKU akan muncul di sini.</div>
            </div>

            <!-- Items -->
            <template x-for="item in notifications" :key="item.id">
                <div @click="goToNotification(item)"
                     class="group p-3.5 flex items-start gap-3 transition-colors cursor-pointer"
                     :class="item.is_read ? 'bg-white hover:bg-slate-50/80' : 'bg-emerald-50/30 hover:bg-emerald-50/70'">
                    
                    <!-- Type Icon Avatar -->
                    <div class="shrink-0 p-2 rounded-xl border mt-0.5"
                         :class="{
                            'bg-emerald-50 text-emerald-600 border-emerald-100': item.type === 'school',
                            'bg-indigo-50 text-indigo-600 border-indigo-100': item.type === 'project',
                            'bg-amber-50 text-amber-600 border-amber-100': item.type === 'rab',
                            'bg-purple-50 text-purple-600 border-purple-100': item.type === 'rpd',
                            'bg-sky-50 text-sky-600 border-sky-100': item.type === 'bku',
                         }">
                        <template x-if="item.type === 'school'">
                            <i data-lucide="school" class="w-4 h-4"></i>
                        </template>
                        <template x-if="item.type === 'project'">
                            <i data-lucide="building-2" class="w-4 h-4"></i>
                        </template>
                        <template x-if="item.type === 'rab'">
                            <i data-lucide="calculator" class="w-4 h-4"></i>
                        </template>
                        <template x-if="item.type === 'rpd'">
                            <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
                        </template>
                        <template x-if="item.type === 'bku'">
                            <i data-lucide="book-open" class="w-4 h-4"></i>
                        </template>
                    </div>

                    <!-- Content Details -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-1 mb-0.5">
                            <span class="font-bold text-slate-800 text-[11px] group-hover:text-emerald-700 transition-colors truncate" x-text="item.title"></span>
                            <span class="text-[10px] text-slate-400 shrink-0 font-medium" x-text="item.time_ago"></span>
                        </div>
                        <p class="text-[11px] text-slate-600 leading-relaxed line-clamp-2" x-text="item.message"></p>
                    </div>

                    <!-- Unread Indicator Dot -->
                    <div class="shrink-0 pt-1.5" x-show="!item.is_read">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 block ring-2 ring-emerald-200"></span>
                    </div>
                </div>
            </template>
        </div>

        <!-- Dropdown Footer -->
        <div class="px-4 py-2.5 bg-slate-50 border-t border-slate-100 flex items-center justify-between text-[11px]">
            <button type="button" 
                    @click.stop="testSoundChime()" 
                    class="text-slate-600 hover:text-emerald-700 font-medium flex items-center gap-1.5 transition-colors"
                    title="Uji coba suara denting notifikasi">
                <i data-lucide="volume-2" class="w-3.5 h-3.5 text-emerald-600"></i>
                <span>Tes Suara Denting</span>
            </button>
            
            <template x-if="permission !== 'granted'">
                <button type="button" 
                        @click.stop="requestBrowserNotification()" 
                        class="text-emerald-600 hover:text-emerald-800 font-bold hover:underline flex items-center gap-1">
                    <i data-lucide="bell" class="w-3 h-3"></i>
                    <span>Aktifkan Desktop</span>
                </button>
            </template>
            <template x-if="permission === 'granted'">
                <span class="text-emerald-700 font-semibold flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    <span>Desktop Aktif</span>
                </span>
            </template>
        </div>
    </div>

    <!-- Floating In-App Push Toast Alert (Teleported directly to dedicated portal root with absolute highest z-index) -->
    <template x-teleport="#toast-portal-root">
        <div x-cloak
             x-show="toast.show" 
             x-transition:enter="transition ease-out duration-300 transform"
             x-transition:enter-start="translate-x-8 opacity-0 scale-95"
             x-transition:enter-end="translate-x-0 opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-200 transform"
             x-transition:leave-start="translate-x-0 opacity-100 scale-100"
             x-transition:leave-end="translate-x-8 opacity-0 scale-95"
             class="fixed top-5 right-4 sm:top-20 sm:right-6 z-[99999999] w-80 sm:w-[360px] max-w-[calc(100vw-2rem)] pointer-events-auto no-print"
             style="position: fixed; z-index: 99999999;">
            
            <div class="relative bg-white/95 backdrop-blur-md rounded-2xl shadow-2xl border border-slate-200/90 p-4 ring-1 ring-slate-900/10 overflow-hidden"
                 :class="{
                    'border-l-4 border-l-emerald-500': toast.color === 'emerald',
                    'border-l-4 border-l-indigo-500': toast.color === 'indigo',
                    'border-l-4 border-l-amber-500': toast.color === 'amber',
                    'border-l-4 border-l-purple-500': toast.color === 'purple',
                    'border-l-4 border-l-sky-500': toast.color === 'sky',
                 }">
                
                <div class="flex items-start gap-3.5">
                    <!-- Icon Indicator -->
                    <div class="p-2.5 rounded-xl shrink-0 mt-0.5 shadow-sm"
                         :class="{
                            'bg-emerald-100 text-emerald-700': toast.color === 'emerald',
                            'bg-indigo-100 text-indigo-700': toast.color === 'indigo',
                            'bg-amber-100 text-amber-700': toast.color === 'amber',
                            'bg-purple-100 text-purple-700': toast.color === 'purple',
                            'bg-sky-100 text-sky-700': toast.color === 'sky',
                         }">
                        <i data-lucide="bell-ring" class="w-5 h-5 animate-bounce"></i>
                    </div>

                    <!-- Content -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-1">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-600 flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span>
                                <span>Push Notifikasi Baru</span>
                            </span>
                            <button type="button" 
                                    @click="toast.show = false" 
                                    class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
                                    title="Tutup">
                                <i data-lucide="x" class="w-4 h-4"></i>
                            </button>
                        </div>

                        <h4 class="font-bold text-slate-900 text-xs sm:text-sm mt-1 leading-snug" x-text="toast.title"></h4>
                        <p class="text-xs text-slate-600 mt-1 leading-relaxed" x-text="toast.message"></p>
                        
                        <div class="mt-3 flex items-center justify-between pt-2 border-t border-slate-100">
                            <a x-show="toast.url" 
                               :href="toast.url" 
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-md shadow-emerald-500/20 active:scale-95 transition-all">
                                <span>Buka Halaman</span>
                                <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                            </a>
                            <button type="button" 
                                    @click="toast.show = false" 
                                    class="text-xs text-slate-400 hover:text-slate-700 font-medium ml-auto">
                                Tutup
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Auto-dismiss subtle progress line -->
                <div class="absolute bottom-0 left-0 right-0 h-1 bg-slate-100 overflow-hidden">
                    <div class="h-full bg-emerald-500 transition-all ease-linear"
                         :style="toast.show ? 'width: 0%; transition-duration: 6500ms;' : 'width: 100%;'"></div>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
/**
 * Sintesis Suara Denting Lonceng Khas Notifikasi menggunakan Web Audio API.
 * Menghasilkan suara denting 3 harmoni (C6 - G6 - C7) jernih, renyah, dan elegan.
 */
function playSignatureNotificationChime() {
    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;

        const ctx = new AudioCtx();
        if (ctx.state === 'suspended') {
            ctx.resume();
        }

        const now = ctx.currentTime;

        // 1. Nada Dasar Denting: 1046.5 Hz (C6) dengan decay cepat pukulan lonceng
        const osc1 = ctx.createOscillator();
        const gain1 = ctx.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(1046.5, now);
        osc1.frequency.exponentialRampToValueAtTime(1020, now + 0.12);

        gain1.gain.setValueAtTime(0.35, now);
        gain1.gain.exponentialRampToValueAtTime(0.0001, now + 0.6);

        osc1.connect(gain1);
        gain1.connect(ctx.destination);
        osc1.start(now);
        osc1.stop(now + 0.6);

        // 2. Harmoni Kedua Lonceng: 1567.98 Hz (G6) masuk di t + 0.08 detik (chime effect)
        const osc2 = ctx.createOscillator();
        const gain2 = ctx.createGain();
        osc2.type = 'triangle';
        osc2.frequency.setValueAtTime(1567.98, now + 0.08);

        gain2.gain.setValueAtTime(0.0001, now);
        gain2.gain.setValueAtTime(0.28, now + 0.08);
        gain2.gain.exponentialRampToValueAtTime(0.0001, now + 0.95);

        osc2.connect(gain2);
        gain2.connect(ctx.destination);
        osc2.start(now + 0.08);
        osc2.stop(now + 0.95);

        // 3. Kristal Kemilau Harmoni Atas: 2093 Hz (C7) masuk di t + 0.12 detik (lingering sustain)
        const osc3 = ctx.createOscillator();
        const gain3 = ctx.createGain();
        osc3.type = 'sine';
        osc3.frequency.setValueAtTime(2093, now + 0.12);

        gain3.gain.setValueAtTime(0.0001, now);
        gain3.gain.setValueAtTime(0.18, now + 0.12);
        gain3.gain.exponentialRampToValueAtTime(0.0001, now + 1.25);

        osc3.connect(gain3);
        gain3.connect(ctx.destination);
        osc3.start(now + 0.12);
        osc3.stop(now + 1.25);
    } catch (e) {
        console.warn('Playback audio notification failed:', e);
    }
}

// Global unlock AudioContext pada interaksi user pertama kali
document.addEventListener('pointerdown', function unlockAudio() {
    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (AudioCtx) {
            const tempCtx = new AudioCtx();
            tempCtx.resume().then(() => tempCtx.close());
        }
    } catch (e) {}
}, { once: true });

function notificationManager(initialCount) {
    return {
        isOpen: false,
        unreadCount: initialCount || 0,
        notifications: [],
        loading: false,
        loadingMarkAll: false,
        hoverTimeout: null,
        leaveTimeout: null,
        pollingTimer: null,
        lastKnownId: 0,
        permission: typeof Notification !== 'undefined' ? Notification.permission : 'default',
        toast: {
            show: false,
            title: '',
            message: '',
            url: '',
            color: 'emerald',
            timeout: null
        },

        init() {
            this.fetchNotifications();
            this.startPolling();
            this.checkSessionPush();

            // Dengarkan event kustom jika dipicu dari aksi frontend lain
            window.addEventListener('trigger-push-notification', (e) => {
                this.showPushToast(e.detail);
                this.unreadCount++;
                this.fetchNotifications();
            });
        },

        testSoundChime() {
            playSignatureNotificationChime();
        },

        toggleOpen() {
            this.isOpen = !this.isOpen;
            if (this.isOpen) {
                this.fetchNotifications();
            }
        },

        onMouseEnter() {
            clearTimeout(this.leaveTimeout);
            this.hoverTimeout = setTimeout(() => {
                this.isOpen = true;
                this.fetchNotifications();
            }, 120);
        },

        onMouseLeave() {
            clearTimeout(this.hoverTimeout);
            this.leaveTimeout = setTimeout(() => {
                this.isOpen = false;
            }, 300);
        },

        async fetchNotifications() {
            this.loading = true;
            try {
                const res = await fetch('{{ route("notifications.index") }}', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                if (res.ok) {
                    const data = await res.json();
                    this.notifications = data.notifications || [];
                    this.unreadCount = data.unread_count || 0;
                    if (this.notifications.length > 0) {
                        this.lastKnownId = this.notifications[0].id;
                    }
                    this.$nextTick(() => {
                        if (window.lucide) {
                            window.lucide.createIcons();
                        }
                    });
                }
            } catch (err) {
                console.error('Error fetching notifications:', err);
            } finally {
                this.loading = false;
            }
        },

        async markAllAsRead() {
            if (this.unreadCount === 0 || this.loadingMarkAll) return;
            this.loadingMarkAll = true;

            // Optimistic update
            const prevCount = this.unreadCount;
            this.unreadCount = 0;
            this.notifications.forEach(n => n.is_read = true);

            try {
                const res = await fetch('{{ route("notifications.read_all") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!res.ok) {
                    // Rollback jika gagal
                    this.unreadCount = prevCount;
                }
            } catch (err) {
                console.error('Error mark all as read:', err);
                this.unreadCount = prevCount;
            } finally {
                this.loadingMarkAll = false;
                this.$nextTick(() => {
                    if (window.lucide) window.lucide.createIcons();
                });
            }
        },

        goToNotification(item) {
            item.is_read = true;
            if (this.unreadCount > 0) {
                this.unreadCount = Math.max(0, this.unreadCount - 1);
            }
            window.location.href = `/notifications/${item.id}/visit`;
        },

        startPolling() {
            // Polling interval setiap 12 detik untuk update otomatis secara real-time
            this.pollingTimer = setInterval(async () => {
                try {
                    const res = await fetch('{{ route("notifications.unread_count") }}', {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (res.ok) {
                        const data = await res.json();
                        this.unreadCount = data.unread_count || 0;

                        // Jika ada notifikasi baru masuk yang belum diketahui ID-nya
                        if (data.latest && data.latest.id > this.lastKnownId && this.lastKnownId > 0) {
                            this.lastKnownId = data.latest.id;
                            this.showPushToast(data.latest);
                            if (this.isOpen) {
                                this.fetchNotifications();
                            }
                        } else if (data.latest && this.lastKnownId === 0) {
                            this.lastKnownId = data.latest.id;
                        }
                    }
                } catch (e) {
                    // Fail silently during network hiccups
                }
            }, 12000);
        },

        checkSessionPush() {
            @if(session()->has('push_notification'))
                const pushData = @json(session('push_notification'));
                if (pushData) {
                    setTimeout(() => {
                        this.showPushToast(pushData);
                    }, 400);
                }
            @endif
        },

        showPushToast(data) {
            this.toast.title = data.title || 'Pemberitahuan Sistem';
            this.toast.message = data.message || '';
            this.toast.url = data.url || '';
            this.toast.color = data.color || 'emerald';
            this.toast.show = true;

            // Mainkan suara denting khas notifikasi
            playSignatureNotificationChime();

            clearTimeout(this.toast.timeout);
            this.toast.timeout = setTimeout(() => {
                this.toast.show = false;
            }, 6500);

            this.$nextTick(() => {
                if (window.lucide) window.lucide.createIcons();
            });

            // Browser Web Push Notification (Desktop)
            this.triggerBrowserPush(data.title, data.message, data.url);
        },

        requestBrowserNotification() {
            if (typeof Notification !== 'undefined') {
                Notification.requestPermission().then(perm => {
                    this.permission = perm;
                    if (perm === 'granted') {
                        playSignatureNotificationChime();
                        new Notification('Si Revita Situbondo', {
                            body: 'Push notifikasi browser desktop berhasil diaktifkan.',
                            icon: '{{ asset("favicon-32x32.png") }}'
                        });
                    }
                });
            }
        },

        triggerBrowserPush(title, message, url) {
            if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
                const notif = new Notification(title, {
                    body: message,
                    icon: '{{ asset("favicon-32x32.png") }}',
                    badge: '{{ asset("favicon-16x16.png") }}'
                });

                if (url) {
                    notif.onclick = function() {
                        window.focus();
                        window.location.href = url;
                    };
                }
            }
        }
    };
}
</script>
