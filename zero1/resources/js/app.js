/*
 * Zero 1 Barbearia — JS do site público e compartilhado.
 * Sem frameworks e compatível com CSP estrita (sem eval/inline handlers).
 */
document.documentElement.classList.remove('no-js');

// Cabeçalho: muda de transparente para sólido ao rolar.
const header = document.querySelector('[data-site-header]');
if (header) {
    const onScroll = () => header.toggleAttribute('data-scrolled', window.scrollY > 24);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
}

// Menus (mobile do site e lateral do painel): [data-toggle="id"] abre/fecha #id.
document.querySelectorAll('[data-toggle]').forEach((btn) => {
    const target = document.getElementById(btn.dataset.toggle);
    if (!target) return;
    const set = (open) => {
        btn.setAttribute('aria-expanded', String(open));
        target.toggleAttribute('data-open', open);
        document.body.classList.toggle('overflow-hidden', open && btn.hasAttribute('data-lock-scroll'));
    };
    btn.addEventListener('click', () => set(btn.getAttribute('aria-expanded') !== 'true'));
    target.querySelectorAll('a, [data-close]').forEach((el) => el.addEventListener('click', () => set(false)));
    document.addEventListener('keydown', (e) => e.key === 'Escape' && btn.getAttribute('aria-expanded') === 'true' && (set(false), btn.focus()));
});

// Motion da landing: só carrega se o <head> marcou html.anim (JS ativo e sem "reduzir movimento").
if (document.documentElement.classList.contains('anim')) {
    import('./landing-motion.js').catch(() => document.documentElement.classList.remove('anim'));
}

// CTA fixo no mobile: some quando o rodapé/CTA final está visível.
const sticky = document.querySelector('[data-sticky-cta]');
const stickyHide = document.querySelectorAll('[data-hide-sticky]');
if (sticky && stickyHide.length && 'IntersectionObserver' in window) {
    const visible = new Set();
    const io = new IntersectionObserver((entries) => {
        entries.forEach((e) => (e.isIntersecting ? visible.add(e.target) : visible.delete(e.target)));
        sticky.toggleAttribute('data-hidden', visible.size > 0);
    });
    stickyHide.forEach((el) => io.observe(el));
}

// Confirmação para ações destrutivas: <form data-confirm="Mensagem">
document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (e) => {
        if (!window.confirm(form.dataset.confirm)) e.preventDefault();
    });
});

// Evita envio duplo e mostra estado de carregamento.
document.querySelectorAll('form[data-loading]').forEach((form) => {
    form.addEventListener('submit', () => {
        const btn = form.querySelector('[type="submit"]');
        if (btn) {
            btn.setAttribute('aria-busy', 'true');
            btn.disabled = true;
            btn.dataset.label = btn.textContent;
            btn.textContent = btn.dataset.loadingText || 'Enviando…';
        }
    });
});
window.addEventListener('pageshow', () => {
    document.querySelectorAll('[aria-busy="true"]').forEach((btn) => {
        btn.disabled = false;
        btn.removeAttribute('aria-busy');
        if (btn.dataset.label) btn.textContent = btn.dataset.label;
    });
});

// Mostrar/ocultar campos condicionais: [data-show-when="name=value"]
const syncConditional = () => {
    document.querySelectorAll('[data-show-when]').forEach((el) => {
        const [name, value] = el.dataset.showWhen.split('=');
        const inputs = document.querySelectorAll(`[name="${name}"]`);
        let current = null;
        inputs.forEach((i) => {
            if ((i.type === 'radio' || i.type === 'checkbox') ? i.checked : true) current = i.type === 'checkbox' ? (i.checked ? i.value : '') : i.value;
        });
        const show = value.split('|').includes(current ?? '');
        el.hidden = !show;
    });
};
document.addEventListener('change', syncConditional);
syncConditional();

// Consentimento de cookies de marketing (só existe se GA4/Pixel estiverem configurados).
const consentBox = document.querySelector('[data-consent]');
if (consentBox) {
    const KEY = 'zero1-consent-v1';
    const load = () => {
        const { ga4, pixel } = consentBox.dataset;
        if (ga4) {
            const s = document.createElement('script');
            s.async = true;
            s.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(ga4)}`;
            document.head.appendChild(s);
            window.dataLayer = window.dataLayer || [];
            window.gtag = function gtag() { window.dataLayer.push(arguments); };
            window.gtag('js', new Date());
            window.gtag('config', ga4, { anonymize_ip: true });
        }
        if (pixel) {
            /* eslint-disable */
            !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
            /* eslint-enable */
            window.fbq('init', pixel);
            window.fbq('track', 'PageView');
        }
    };
    let choice = null;
    try { choice = localStorage.getItem(KEY); } catch (e) { /* armazenamento indisponível */ }
    if (choice === 'granted') load();
    if (!choice) consentBox.hidden = false;
    consentBox.querySelectorAll('[data-consent-choice]').forEach((b) => b.addEventListener('click', () => {
        const value = b.dataset.consentChoice;
        try { localStorage.setItem(KEY, value); } catch (e) { /* ignore */ }
        consentBox.hidden = true;
        if (value === 'granted') load();
    }));
    document.querySelectorAll('[data-consent-reopen]').forEach((b) => b.addEventListener('click', () => { consentBox.hidden = false; }));
}

// Pré-visualização de imagem antes do upload.
document.querySelectorAll('input[type="file"][data-preview]').forEach((input) => {
    const img = document.getElementById(input.dataset.preview);
    input.addEventListener('change', () => {
        const file = input.files?.[0];
        if (img && file) {
            img.src = URL.createObjectURL(file);
            img.hidden = false;
        }
    });
});

// Contador de caracteres: <textarea data-count="170">
document.querySelectorAll('[data-count]').forEach((el) => {
    const out = document.createElement('p');
    out.className = 'form-hint text-right';
    el.after(out);
    const upd = () => { out.textContent = `${el.value.length}/${el.dataset.count}`; };
    el.addEventListener('input', upd);
    upd();
});

// Luz que acompanha o cursor nos cards .spotlight (só com mouse; nada muda no toque).
if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
    document.addEventListener('pointermove', (e) => {
        const card = e.target.closest?.('.spotlight');
        if (!card) return;
        const r = card.getBoundingClientRect();
        card.style.setProperty('--mx', `${e.clientX - r.left}px`);
        card.style.setProperty('--my', `${e.clientY - r.top}px`);
    }, { passive: true });
}

// Botão "Copiar": [data-copy="elId"] [data-ok="texto"]
document.querySelectorAll('[data-copy]').forEach((btn) => {
    btn.addEventListener('click', async () => {
        const el = document.getElementById(btn.dataset.copy);
        if (!el) return;
        try {
            await navigator.clipboard.writeText(el.value || el.textContent);
            const old = btn.textContent;
            btn.textContent = btn.dataset.ok || 'Copiado';
            setTimeout(() => { btn.textContent = old; }, 1600);
        } catch (e) {
            el.select?.(); document.execCommand?.('copy');
        }
    });
});
