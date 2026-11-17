/*
 * Dönüşüm ölçümü (Google Analytics 4).
 *
 * Tek bir tıklama dinleyicisi (document) tüm sayfalarda çalışır; bağlantı başına işaretleme
 * gerekmez. Bağlantının türü adresinden anlaşılır:
 *   tel:                                  → phone_click
 *   wa.me / api.whatsapp.com / whatsapp:  → whatsapp_click
 *   mailto:                               → email_click
 *
 * Parametreler: link_location (en yakın [data-track-area], yoksa header/footer/nav/main),
 * page_type (<body data-page-type>), page_path.
 *
 * gtag yalnız Site Ayarları'nda Analytics kimliği girildiyse tanımlıdır (layouts/app). Tanımlı
 * değilse her şey sessizce atlanır. gtag.js ertelenerek yüklendiği için olaylar önce
 * dataLayer kuyruğuna yazılır; betik inince kuyruktakiler gönderilir.
 *
 * Kişisel veri (telefon numarası, e-posta, form alanları) GÖNDERİLMEZ.
 */

const FALLBACK_AREAS = [
    ['header', 'header'],
    ['footer', 'footer'],
    ['nav', 'nav'],
    ['main', 'content'],
];

const WHATSAPP_HOSTS = ['wa.me', 'api.whatsapp.com', 'web.whatsapp.com', 'whatsapp.com', 'www.whatsapp.com'];

export function track(name, params = {}) {
    if (typeof window.gtag !== 'function') return;
    try {
        window.gtag('event', name, {
            page_type: pageType(),
            page_path: window.location.pathname,
            ...params,
        });
    } catch (e) {
        // Ölçüm hiçbir zaman sayfayı bozmamalı.
    }
}

function pageType() {
    return (document.body && document.body.dataset.pageType) || 'other';
}

export function linkLocation(el) {
    const area = el.closest('[data-track-area]');
    if (area) return area.dataset.trackArea;
    for (const [selector, name] of FALLBACK_AREAS) {
        if (el.closest(selector)) return name;
    }
    return 'other';
}

export function linkEvent(link) {
    const href = (link.getAttribute('href') || '').trim().toLowerCase();
    if (href.startsWith('tel:')) return 'phone_click';
    if (href.startsWith('mailto:')) return 'email_click';
    if (href.startsWith('whatsapp:')) return 'whatsapp_click';
    if (/^https?:/.test(href) || href.startsWith('//')) {
        let host = '';
        try { host = new URL(href, window.location.href).hostname; } catch (e) { return null; }
        if (WHATSAPP_HOSTS.includes(host)) return 'whatsapp_click';
    }
    return null;
}

function onClick(event) {
    const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
    if (!link) return;
    const name = linkEvent(link);
    if (!name) return;
    track(name, { link_location: linkLocation(link) });
}

/* Teklif formuna ilk dokunuş (sayfa başına bir kez). Adı GA4'ün otomatik "form_start"
 * olayıyla (Gelişmiş ölçüm) çakışmasın diye "quote_form_start". */
function formStart() {
    let sent = false;
    document.querySelectorAll('form[data-quote-form]').forEach((form) => {
        form.addEventListener('focusin', () => {
            if (sent) return;
            sent = true;
            const source = form.querySelector('input[name="source"]');
            track('quote_form_start', {
                form: source && source.value === 'contact' ? 'contact' : 'quote',
            });
        });
    });
}

export function initTracking() {
    // Yakalama evresinde: başka bir dinleyici stopPropagation yapsa da tıklama ölçülür.
    document.addEventListener('click', onClick, true);
    formStart();
}
