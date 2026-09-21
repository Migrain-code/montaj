import '@fortawesome/fontawesome-free/css/all.min.css';
import Collapse from 'bootstrap/js/dist/collapse';
import Offcanvas from 'bootstrap/js/dist/offcanvas';
import Carousel from 'bootstrap/js/dist/carousel';

// Bootstrap bileşenlerini data-* API'siyle etkinleştir (import edilmeleri yeterli)
window.bootstrap = { Collapse, Offcanvas, Carousel };

document.addEventListener('DOMContentLoaded', () => {
    stickyHeader();
    revealOnScroll();
    animateCounters();
    galleryFilter();
    lightbox();
    quoteForm();
    backToTop();
});

/* Sticky header gölgesi */
function stickyHeader() {
    const header = document.getElementById('siteHeader');
    if (!header) return;
    const toggle = () => header.classList.toggle('is-sticky', window.scrollY > 10);
    toggle();
    window.addEventListener('scroll', toggle, { passive: true });
}

/* Görünür olunca yumuşak giriş */
function revealOnScroll() {
    const items = document.querySelectorAll('.reveal');
    if (!items.length || !('IntersectionObserver' in window)) {
        items.forEach((el) => el.classList.add('in'));
        return;
    }
    const io = new IntersectionObserver((entries) => {
        entries.forEach((e) => {
            if (e.isIntersecting) {
                e.target.classList.add('in');
                io.unobserve(e.target);
            }
        });
    }, { threshold: 0.12 });
    items.forEach((el) => io.observe(el));
}

/* Sayaç animasyonu (yalnızca sayısal değerler) */
function animateCounters() {
    const nums = document.querySelectorAll('[data-count]');
    if (!nums.length) return;
    const run = (el) => {
        const raw = el.dataset.count.trim();
        const match = raw.match(/^(\d+)(.*)$/);
        if (!match) { el.textContent = raw; return; }
        const target = parseInt(match[1], 10);
        const suffix = match[2] || '';
        const duration = 1200;
        const start = performance.now();
        const tick = (now) => {
            const p = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - p, 3);
            el.textContent = Math.round(target * eased) + suffix;
            if (p < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    };
    if (!('IntersectionObserver' in window)) { nums.forEach(run); return; }
    const io = new IntersectionObserver((entries) => {
        entries.forEach((e) => { if (e.isIntersecting) { run(e.target); io.unobserve(e.target); } });
    }, { threshold: 0.5 });
    nums.forEach((el) => io.observe(el));
}

/* Galeri kategori filtresi */
function galleryFilter() {
    const wrap = document.querySelector('[data-gallery]');
    if (!wrap) return;
    const buttons = wrap.querySelectorAll('.filter-btn');
    const items = wrap.querySelectorAll('.gallery-item');
    const apply = (cat) => {
        buttons.forEach((b) => b.classList.toggle('active', b.dataset.filter === cat));
        items.forEach((it) => it.classList.toggle('is-hidden', cat !== 'all' && it.dataset.category !== cat));
        const url = new URL(window.location);
        if (cat === 'all') url.searchParams.delete('kategori'); else url.searchParams.set('kategori', cat);
        window.history.replaceState({}, '', url);
    };
    buttons.forEach((b) => b.addEventListener('click', () => apply(b.dataset.filter)));
    const active = wrap.querySelector('.filter-btn.active');
    if (active && active.dataset.filter !== 'all') apply(active.dataset.filter);
}

/* Basit lightbox */
function lightbox() {
    const links = Array.from(document.querySelectorAll('.gallery-item[data-full]'));
    if (!links.length) return;
    const box = document.createElement('div');
    box.className = 'lightbox';
    box.innerHTML = `
        <button class="lb-btn lb-close" aria-label="Kapat"><i class="fa-solid fa-xmark"></i></button>
        <button class="lb-btn lb-prev" aria-label="Önceki"><i class="fa-solid fa-chevron-left"></i></button>
        <img alt="">
        <button class="lb-btn lb-next" aria-label="Sonraki"><i class="fa-solid fa-chevron-right"></i></button>
        <div class="lb-caption"></div>`;
    document.body.appendChild(box);
    const img = box.querySelector('img');
    const caption = box.querySelector('.lb-caption');
    let index = 0;
    const visible = () => links.filter((l) => !l.classList.contains('is-hidden'));
    const show = (i) => {
        const list = visible();
        if (!list.length) return;
        index = (i + list.length) % list.length;
        const el = list[index];
        img.src = el.dataset.full;
        img.alt = el.dataset.alt || '';
        caption.textContent = el.dataset.title || '';
        box.classList.add('open');
        document.body.style.overflow = 'hidden';
    };
    const close = () => { box.classList.remove('open'); document.body.style.overflow = ''; };
    links.forEach((l) => l.addEventListener('click', (e) => { e.preventDefault(); show(visible().indexOf(l)); }));
    box.querySelector('.lb-close').addEventListener('click', close);
    box.querySelector('.lb-prev').addEventListener('click', () => show(index - 1));
    box.querySelector('.lb-next').addEventListener('click', () => show(index + 1));
    box.addEventListener('click', (e) => { if (e.target === box) close(); });
    document.addEventListener('keydown', (e) => {
        if (!box.classList.contains('open')) return;
        if (e.key === 'Escape') close();
        if (e.key === 'ArrowLeft') show(index - 1);
        if (e.key === 'ArrowRight') show(index + 1);
    });
}

/* Teklif formu: ile bağlı ilçe listesi + fotoğraf önizleme */
function quoteForm() {
    document.querySelectorAll('form[data-quote-form]').forEach((form) => {
        const provinceSel = form.querySelector('[name="province_id"]');
        const districtSel = form.querySelector('[name="district_id"]');
        const dataEl = form.querySelector('script[data-districts]');
        if (provinceSel && districtSel && dataEl) {
            let map = {};
            try { map = JSON.parse(dataEl.textContent); } catch (e) { map = {}; }
            const preselected = districtSel.dataset.selected || '';
            const fill = () => {
                const list = map[provinceSel.value] || [];
                districtSel.innerHTML = '<option value="">İlçe seçin</option>';
                list.forEach((d) => {
                    const opt = document.createElement('option');
                    opt.value = d.id; opt.textContent = d.name;
                    if (String(d.id) === preselected) opt.selected = true;
                    districtSel.appendChild(opt);
                });
                districtSel.disabled = !provinceSel.value;
            };
            provinceSel.addEventListener('change', fill);
            fill();
        }

        const input = form.querySelector('input[type="file"][name="photos[]"]');
        const previews = form.querySelector('[data-photo-previews]');
        const drop = form.querySelector('.photo-drop');
        if (input && previews) {
            const render = () => {
                previews.innerHTML = '';
                const files = Array.from(input.files || []).slice(0, 5);
                files.forEach((file) => {
                    if (!file.type.startsWith('image/')) return;
                    const img = document.createElement('img');
                    img.src = URL.createObjectURL(file);
                    img.onload = () => URL.revokeObjectURL(img.src);
                    previews.appendChild(img);
                });
                const label = form.querySelector('[data-photo-count]');
                if (label) label.textContent = files.length ? `${files.length} fotoğraf seçildi` : '';

                // Fotoğraf zorunlu: seçildiğinde alan yeşile döner, hata durumu kalkar.
                if (drop) {
                    drop.classList.toggle('is-filled', files.length > 0);
                    if (files.length > 0) drop.classList.remove('is-invalid');
                }
            };
            input.addEventListener('change', render);
            if (drop) {
                ['dragenter', 'dragover'].forEach((ev) => drop.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.add('dragover'); }));
                ['dragleave', 'drop'].forEach((ev) => drop.addEventListener(ev, () => drop.classList.remove('dragover')));
            }
        }
    });
}

/* Yukarı çık */
function backToTop() {
    const btn = document.querySelector('.back-to-top');
    if (!btn) return;
    const toggle = () => btn.classList.toggle('show', window.scrollY > 500);
    toggle();
    window.addEventListener('scroll', toggle, { passive: true });
    btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
}
