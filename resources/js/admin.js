/* ─── Admin Panel JS ──────────────────────────────────────────────────── */
import Alpine from 'alpinejs';
window.Alpine = Alpine;

Alpine.data('toaster', () => ({
    toasts: [],
    add(toast) {
        toast.id = Date.now();
        this.toasts.push(toast);
        setTimeout(() => {
            this.remove(toast.id);
        }, 5000);
    },
    remove(id) {
        this.toasts = this.toasts.filter(t => t.id !== id);
    },
}));

Alpine.data('catalogImageWorkbench', () => ({
    endpoint: '',
    csrfToken: '',
    cursor: 0,
    query: '',
    categoryId: '',
    batchSize: 3,
    running: false,
    autoRun: false,
    message: '',
    totals: { processed: 0, updated: 0, failed: 0 },
    logs: [],

    init() {
        const dataset = this.$el?.dataset || {};
        this.endpoint = dataset.endpoint || '';
        this.csrfToken = dataset.csrfToken || '';
        this.cursor = Number.parseInt(dataset.initialCursor || '0', 10) || 0;
        this.query = dataset.query || '';
        this.categoryId = dataset.categoryId || '';
        this.batchSize = Math.min(Math.max(Number.parseInt(dataset.batchSize || '3', 10) || 3, 1), 3);
    },

    async toggleAuto() {
        this.autoRun = !this.autoRun;
        if (this.autoRun && !this.running) {
            await this.runBatch(true);
        }
    },

    async runBatch(fromAuto) {
        if (this.running) return;
        this.running = true;
        this.message = `${this.batchSize} ürünlük parti çalışıyor...`;

        try {
            const response = await fetch(this.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    limit: this.batchSize,
                    cursor: this.cursor,
                    q: this.query,
                    category_id: this.categoryId,
                }),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.ok) {
                throw new Error(data.message || `Sunucu ${response.status} döndü.`);
            }

            this.cursor = data.cursor || this.cursor;
            this.totals.processed += data.processed || 0;
            this.totals.updated += data.updated || 0;
            this.totals.failed += data.failed || 0;
            this.logs = [
                ...(data.results || []).map((item) => ({ ...item, key: `${Date.now()}-${item.id}` })),
                ...this.logs,
            ].slice(0, 80);

            if ((data.processed || 0) === 0) {
                this.message = 'İşlenecek eksik görsel kalmadı.';
                this.autoRun = false;
            } else {
                this.message = `${data.processed} ürün işlendi, ${data.updated} görsel eklendi. Kalan: ${data.remaining}.`;
            }

            if (this.autoRun && (data.remaining || 0) > 0) {
                window.setTimeout(() => {
                    this.running = false;
                    this.runBatch(true);
                }, 900);
                return;
            }

            if (!fromAuto && (data.updated || 0) > 0) {
                window.setTimeout(() => window.location.reload(), 900);
            }
        } catch (error) {
            this.message = error.message || 'Batch çalıştırılamadı.';
            this.autoRun = false;
        } finally {
            if (!this.autoRun) {
                this.running = false;
            }
        }
    },
}));

function imageSuggestionState() {
    return {
        imageModal: {
            open: false,
            loading: false,
            applying: false,
            productId: null,
            productName: '',
            candidates: [],
            currentImageUrl: null,
            error: null,
            success: null,
        },

        resetImageModal(productId, productName) {
            this.imageModal.open = true;
            this.imageModal.loading = true;
            this.imageModal.applying = false;
            this.imageModal.productId = productId;
            this.imageModal.productName = productName;
            this.imageModal.candidates = [];
            this.imageModal.currentImageUrl = null;
            this.imageModal.error = null;
            this.imageModal.success = null;
        },

        async openImageSuggest(productId, productName) {
            this.resetImageModal(productId, productName);

            try {
                const response = await fetch(`/admin/products/${productId}/image-suggestions`, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(data.message || `Sunucu ${response.status} döndü.`);
                }

                this.imageModal.candidates = Array.isArray(data.candidates) ? data.candidates : [];
                this.imageModal.currentImageUrl = data.product?.current_image_url || null;
                if (this.imageModal.candidates.length === 0) {
                    this.imageModal.error = 'Uygun ve indirilebilir görsel adayı bulunamadı. Ürün adını veya barkodu güncellemeyi deneyin.';
                }
            } catch (error) {
                this.imageModal.error = error.message || 'Adaylar yüklenemedi.';
            } finally {
                this.imageModal.loading = false;
            }
        },

        closeImageSuggest() {
            this.imageModal.open = false;
        },

        markImageCandidateBroken(candidate) {
            if (candidate && typeof candidate === 'object') {
                candidate.failed = true;
            }
        },

        async applyImageCandidate(candidate) {
            if (!this.imageModal.productId || this.imageModal.applying) return;

            const payload = typeof candidate === 'string'
                ? { url: candidate }
                : {
                    url: candidate?.url || '',
                    thumb: candidate?.thumb || null,
                };

            if (!payload.url) {
                this.imageModal.error = 'Görsel URL bilgisi eksik.';
                return;
            }

            this.imageModal.applying = true;
            this.imageModal.error = null;
            this.imageModal.success = null;

            try {
                const response = await fetch(`/admin/products/${this.imageModal.productId}/image-suggestions/apply`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload),
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.ok) {
                    throw new Error(data.message || `Sunucu ${response.status} döndü.`);
                }

                this.imageModal.success = 'Görsel uygulandı. Sayfa yenileniyor...';
                this.imageModal.currentImageUrl = data.image_url;
                window.setTimeout(() => window.location.reload(), 700);
            } catch (error) {
                this.markImageCandidateBroken(candidate);
                this.imageModal.error = error.message || 'Görsel uygulanamadı.';
            } finally {
                this.imageModal.applying = false;
            }
        },
    };
}

Alpine.data('bulkSelect', () => ({
    selectedIds: [],
    csrfToken: '',
    ...imageSuggestionState(),

    init() {
        this.csrfToken = this.$el?.dataset?.csrfToken || '';
    },

    get allSelected() {
        const boxes = document.querySelectorAll('.product-checkbox');
        return boxes.length > 0 && this.selectedIds.length === boxes.length;
    },

    toggle(id) {
        const index = this.selectedIds.indexOf(id);
        if (index === -1) this.selectedIds.push(id);
        else this.selectedIds.splice(index, 1);
    },

    toggleAll(event) {
        if (event.target.checked) {
            this.selectedIds = Array.from(document.querySelectorAll('.product-checkbox')).map((el) => Number.parseInt(el.value, 10));
        } else {
            this.selectedIds = [];
        }
    },

    clearAll() {
        this.selectedIds = [];
    },

    appendIds(event) {
        const form = event.target;
        form.querySelectorAll('input[data-selected-id]').forEach((input) => input.remove());
        this.selectedIds.forEach((id) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = id;
            input.dataset.selectedId = '1';
            form.appendChild(input);
        });
    },
}));

Alpine.data('productFormImageSuggest', () => ({
    csrfToken: '',
    ...imageSuggestionState(),

    init() {
        this.csrfToken = this.$el?.dataset?.csrfToken || '';
    },
}));

Alpine.data('imageUpload', () => ({
    preview: null,
    dragging: false,

    onFileChange(event) {
        const file = event.target.files?.[0];
        if (file) this.preview = URL.createObjectURL(file);
    },

    onDrop(event) {
        this.dragging = false;
        const file = event.dataTransfer.files?.[0];
        if (!file || !file.type.startsWith('image/')) return;

        const input = document.getElementById('image_file');
        if (!(input instanceof HTMLInputElement)) return;

        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
        this.preview = URL.createObjectURL(file);
    },

    clearPreview() {
        this.preview = null;
        const input = document.getElementById('image_file');
        if (input instanceof HTMLInputElement) {
            input.value = '';
        }
    },
}));

Alpine.start();

/* ── Dashboard Charts ─────────────────────────────────────────────────── */

function parseDashboardData() {
    const payload = document.getElementById('dashboard-chart-data');
    if (!(payload instanceof HTMLTemplateElement)) return null;
    try {
        return JSON.parse(payload.innerHTML.trim());
    } catch {
        return null;
    }
}

function createHiDpiCanvas(canvas) {
    const context = canvas.getContext('2d');
    if (!context) return null;
    const parent = canvas.parentElement;
    const width  = parent?.clientWidth ?? canvas.clientWidth;
    const height = parent?.clientHeight ?? 320;
    const ratio  = window.devicePixelRatio || 1;
    canvas.width  = Math.max(Math.floor(width * ratio), 1);
    canvas.height = Math.max(Math.floor(height * ratio), 1);
    canvas.style.width  = `${width}px`;
    canvas.style.height = `${height}px`;
    context.setTransform(ratio, 0, 0, ratio, 0, 0);
    context.clearRect(0, 0, width, height);
    return { context, width, height };
}

function drawAxes(context, labels, width, height, bounds, colors) {
    const steps = 4;
    context.save();
    context.strokeStyle = colors.grid;
    context.fillStyle   = colors.textMuted;
    context.lineWidth   = 1;
    context.font        = '12px system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';

    for (let step = 0; step <= steps; step++) {
        const value = bounds.max * (step / steps);
        const y     = bounds.top + bounds.chartHeight - (bounds.chartHeight * step) / steps;
        context.beginPath();
        context.moveTo(bounds.left, y);
        context.lineTo(width - bounds.right, y);
        context.stroke();
        context.textAlign    = 'right';
        context.textBaseline = 'middle';
        context.fillText(formatAxisValue(value), bounds.left - 10, y);
    }

    labels.forEach((label, index) => {
        const x = bounds.left + (bounds.chartWidth * index) / Math.max(labels.length - 1, 1);
        context.textAlign    = index === 0 ? 'left' : index === labels.length - 1 ? 'right' : 'center';
        context.textBaseline = 'top';
        context.fillText(label, x, height - bounds.bottom + 10);
    });

    context.restore();
}

function drawLineChart(canvas, labels, values) {
    if (!labels.length || !values.length) return;
    const surface = createHiDpiCanvas(canvas);
    if (!surface) return;
    const { context, width, height } = surface;
    const bounds = {
        top: 18, right: 18, bottom: 38, left: 40,
        chartWidth: width - 58, chartHeight: height - 56,
        max: Math.max(...values, 1),
    };
    const colors = {
        line: '#f97316', fill: 'rgba(249,115,22,0.12)',
        grid: 'rgba(148,163,184,0.15)', textMuted: '#94a3b8',
    };

    drawAxes(context, labels, width, height, bounds, colors);

    context.save();
    context.strokeStyle = colors.line;
    context.fillStyle   = colors.fill;
    context.lineWidth   = 2;
    context.lineJoin    = 'round';
    context.lineCap     = 'round';

    values.forEach((value, index) => {
        const x = bounds.left + (bounds.chartWidth * index) / Math.max(values.length - 1, 1);
        const y = bounds.top + bounds.chartHeight - (value / bounds.max) * bounds.chartHeight;
        if (index === 0) { context.beginPath(); context.moveTo(x, y); } else { context.lineTo(x, y); }
    });

    context.stroke();
    context.lineTo(bounds.left + bounds.chartWidth, bounds.top + bounds.chartHeight);
    context.lineTo(bounds.left, bounds.top + bounds.chartHeight);
    context.closePath();
    context.fill();

    values.forEach((value, index) => {
        const x = bounds.left + (bounds.chartWidth * index) / Math.max(values.length - 1, 1);
        const y = bounds.top + bounds.chartHeight - (value / bounds.max) * bounds.chartHeight;
        context.beginPath();
        context.fillStyle = '#f97316';
        context.arc(x, y, 3.5, 0, Math.PI * 2);
        context.fill();
    });

    context.restore();
}

function drawBarChart(canvas, labels, values) {
    if (!labels.length || !values.length) return;
    const surface = createHiDpiCanvas(canvas);
    if (!surface) return;
    const { context, width, height } = surface;
    const bounds = {
        top: 18, right: 18, bottom: 38, left: 40,
        chartWidth: width - 58, chartHeight: height - 56,
        max: Math.max(...values, 1),
    };
    const colors = {
        bar: '#fb923c', grid: 'rgba(148,163,184,0.15)', textMuted: '#94a3b8',
    };

    drawAxes(context, labels, width, height, bounds, colors);

    const slotWidth = bounds.chartWidth / values.length;
    const barWidth  = Math.min(Math.max(slotWidth * 0.52, 18), 42);

    context.save();
    context.fillStyle = colors.bar;
    values.forEach((value, index) => {
        const barHeight = (value / bounds.max) * bounds.chartHeight;
        const x = bounds.left + slotWidth * index + (slotWidth - barWidth) / 2;
        const y = bounds.top + bounds.chartHeight - barHeight;
        context.beginPath();
        context.roundRect(x, y, barWidth, Math.max(barHeight, 4), 6);
        context.fill();
    });
    context.restore();
}

function setupDashboardCharts() {
    const chartData      = parseDashboardData();
    const earningsCanvas = document.getElementById('earningsChart');
    const ordersCanvas   = document.getElementById('ordersChart');
    if (!chartData || !(earningsCanvas instanceof HTMLCanvasElement) || !(ordersCanvas instanceof HTMLCanvasElement)) return;

    const labels   = Array.isArray(chartData.labels) ? chartData.labels : [];
    const earnings = Array.isArray(chartData.earnings) ? chartData.earnings.map(Number) : [];
    const orders   = Array.isArray(chartData.units ?? chartData.orders)
        ? (chartData.units ?? chartData.orders).map(Number)
        : [];

    const render = () => {
        drawLineChart(earningsCanvas, labels, earnings);
        drawBarChart(ordersCanvas, labels, orders);
    };

    function handleResize() {
        const parent = earningsCanvas.parentElement;
        if (!parent) return;
        const width  = parent.clientWidth;
        const height = parent.clientHeight;
        if (width > 0 && height > 0) render();
    }   

    render();
    window.addEventListener('resize', debounce(render, 120));
}

function formatAxisValue(value) {
    if (value >= 1_000_000) return `${(value / 1_000_000).toFixed(1)}M`;
    if (value >= 1_000)     return `${(value / 1_000).toFixed(1)}K`;
    return Math.round(value).toString();
}

function debounce(callback, wait) {
    let timeoutId;
    return (...args) => {
        window.clearTimeout(timeoutId);
        timeoutId = window.setTimeout(() => callback(...args), wait);
    };
}

/* ── Dock: dropdown toggle ────────────────────────────────────────────── */
function setupDock() {
    const nav = document.querySelector('[data-dock]');
    if (!nav || nav.dataset.dockReady === '1') return;
    nav.dataset.dockReady = '1';

    const panels  = nav.querySelectorAll('.dock-dropdown');
    const buttons = nav.querySelectorAll('[data-dock-btn]');

    function closeAll() {
        panels.forEach(p => { p.hidden = true; });
        buttons.forEach(b => b.setAttribute('aria-expanded', 'false'));
    }

    function closeWhenOutside(event) {
        if (!nav.contains(event.target)) {
            closeAll();
        }
    }

    buttons.forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();
            const id     = btn.dataset.dockBtn;
            const panel  = nav.querySelector(`.dock-dropdown[data-dock-panel="${id}"]`);
            const isOpen = !panel.hidden;
            closeAll();
            if (!isOpen) {
                panel.hidden = false;
                btn.setAttribute('aria-expanded', 'true');
            }
        });
    });

    // Dropdown link/buton tıklaması: navigate'ten önce kapat ki
    // (a) görsel olarak panel açık kalmasın, (b) navigate cancel olursa stale state kalmasın.
    panels.forEach(p => {
        p.addEventListener('click', e => {
            if (e.target.closest('a, button')) closeAll();
            else e.stopPropagation();
        });
    });

    nav.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', closeAll);
    });

    // Sayfanın herhangi yerine tıkla → kapat. Capture fazı kullanıyoruz;
    // bazı sayfalardaki Alpine/özel handler'lar propagation'ı kesince dock açık kalıyordu.
    document.addEventListener('pointerdown', closeWhenOutside, true);
    document.addEventListener('focusin', closeWhenOutside, true);
    window.addEventListener('click', closeWhenOutside, true);

    // Escape → kapat
    window.addEventListener('keydown', e => { if (e.key === 'Escape') closeAll(); });

    window.addEventListener('resize', closeAll);
    window.addEventListener('scroll', closeAll, true);
    window.addEventListener('pagehide', closeAll);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') closeAll();
    });

    // Sayfa bfcache'ten geri gelirse açık kalmış panel'leri temizle
    window.addEventListener('pageshow', closeAll);
    closeAll();
}

function setupConfirmSubmitForms() {
    document.querySelectorAll('form[data-confirm-submit]').forEach((form) => {
        if (form.dataset.confirmReady === '1') return;
        form.dataset.confirmReady = '1';

        form.addEventListener('submit', (event) => {
            const message = form.dataset.confirmSubmit || 'Devam edilsin mi?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
}

function setupAutoSubmitControls() {
    document.querySelectorAll('[data-auto-submit]').forEach((control) => {
        if (control.dataset.autoSubmitReady === '1') return;
        control.dataset.autoSubmitReady = '1';
        control.addEventListener('change', () => {
            if (control.form) control.form.submit();
        });
    });
}

function setupLegacyAdminNav() {
    const sheet = document.getElementById('admin-nav-sheet');
    const toggle = document.querySelector('[data-admin-nav-toggle]');
    const closers = document.querySelectorAll('[data-admin-nav-close]');
    if (!sheet || !toggle || toggle.dataset.legacyNavReady === '1') return;

    toggle.dataset.legacyNavReady = '1';
    const setOpen = (open) => {
        document.body.classList.toggle('nav-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        sheet.setAttribute('aria-hidden', open ? 'false' : 'true');
    };

    toggle.addEventListener('click', () => setOpen(!document.body.classList.contains('nav-open')));
    closers.forEach((el) => el.addEventListener('click', () => setOpen(false)));
    window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setOpen(false);
    });
}

function setupSupportAdminStreams() {
    document.querySelectorAll('[data-support-admin]').forEach((root) => {
        if (root.dataset.supportReady === '1') return;
        root.dataset.supportReady = '1';

        const streamUrl = root.dataset.streamUrl;
        const messages = root.querySelector('[data-support-messages]');
        if (!streamUrl || !messages || typeof EventSource === 'undefined') return;

        let lastId = Number.parseInt(root.dataset.lastId || '0', 10) || 0;
        const connect = () => {
            const url = new URL(streamUrl, window.location.origin);
            url.searchParams.set('after_id', String(lastId));
            const source = new EventSource(url.toString(), { withCredentials: true });

            source.addEventListener('message', (event) => {
                try {
                    const message = JSON.parse(event.data);
                    if (!message?.id || message.id <= lastId) return;
                    lastId = message.id;
                    root.dataset.lastId = String(lastId);
                    messages.appendChild(renderSupportAdminMessage(message));
                    messages.scrollTop = messages.scrollHeight;
                } catch {
                    // Ignore malformed SSE frames.
                }
            });

            source.addEventListener('error', () => {
                source.close();
                window.setTimeout(connect, 1600);
            });
        };

        connect();
        messages.scrollTop = messages.scrollHeight;
    });
}

function renderSupportAdminMessage(message) {
    const wrap = document.createElement('div');
    wrap.dataset.messageId = String(message.id);
    wrap.className = `flex ${message.sender_type === 'admin' ? 'justify-end' : 'justify-start'}`;

    const bubble = document.createElement('div');
    const tone = message.sender_type === 'admin'
        ? 'border-orange-200 bg-orange-50 text-orange-950'
        : message.sender_type === 'ai'
            ? 'border-violet-200 bg-violet-50 text-violet-950'
            : 'border-slate-200 bg-white text-slate-800';
    bubble.className = `max-w-[78%] rounded-md border px-3 py-2 text-sm shadow-sm ${tone}`;

    const name = document.createElement('div');
    name.className = 'mb-1 text-[10px] font-black uppercase tracking-wide text-slate-400';
    name.textContent = message.sender_name || message.sender_type || 'Mesaj';

    const body = document.createElement('div');
    body.className = 'whitespace-pre-line leading-relaxed';
    body.textContent = message.body || '';

    const time = document.createElement('div');
    time.className = 'mt-1 text-right text-[10px] font-semibold text-slate-400';
    time.textContent = message.created_at
        ? new Intl.DateTimeFormat('tr-TR', { hour: '2-digit', minute: '2-digit' }).format(new Date(message.created_at))
        : '';

    bubble.append(name, body, time);
    wrap.appendChild(bubble);
    return wrap;
}

/* ── Init ─────────────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    setupDashboardCharts();
    setupDock();
    setupConfirmSubmitForms();
    setupAutoSubmitControls();
    setupLegacyAdminNav();
    setupSupportAdminStreams();
});
