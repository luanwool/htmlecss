/*
 * Motion da landing (carregado sob demanda, só quando html.anim está presente —
 * ou seja, com JS ativo e SEM prefers-reduced-motion). Sem este módulo, a página já é
 * completa e legível: as regras .anim só escondem elementos enquanto ele está ativo.
 *
 * Diretrizes: uma abertura orquestrada no hero; revelações curtas e escalonadas no resto;
 * apenas transform/opacity (sem layout shift); parallax só em telas >= 768px.
 */
import { animate, inView, scroll, stagger } from 'motion';

window.__z1Motion = true;

const easeOut = [0.16, 1, 0.3, 1];
const softBack = [0.34, 1.4, 0.64, 1]; // leve overshoot, só em cards
const wide = window.matchMedia('(min-width: 768px)').matches;

/* ---------- Hero ---------- */
const hero = document.querySelector('[data-hero]');
if (hero) {
    const title = hero.querySelector('[data-hero-title]');
    if (title) {
        // Divide o título em palavras mascaradas; o leitor de tela recebe o texto inteiro.
        const text = title.textContent.trim().replace(/\s+/g, ' ');
        title.setAttribute('aria-label', text);
        title.textContent = '';
        const wrap = document.createElement('span');
        wrap.setAttribute('aria-hidden', 'true');
        text.split(' ').forEach((word, i, all) => {
            const mask = document.createElement('span');
            mask.className = 'hero-word';
            const inner = document.createElement('span');
            inner.textContent = word;
            mask.appendChild(inner);
            wrap.appendChild(mask);
            if (i < all.length - 1) wrap.appendChild(document.createTextNode(' '));
        });
        title.appendChild(wrap);
        title.style.opacity = '1';
        animate(title.querySelectorAll('.hero-word > span'), { y: ['110%', '0%'] }, { duration: 0.9, delay: stagger(0.07, { startDelay: 0.25 }), ease: easeOut });
    }

    const rule = hero.querySelector('[data-hero-rule]');
    if (rule) animate(rule, { scaleX: [0, 1] }, { duration: 0.7, ease: easeOut });
    animate(hero.querySelectorAll('[data-hero-fade]'), { opacity: [0, 1], y: [14, 0] }, { duration: 0.7, delay: stagger(0.12, { startDelay: 0.55 }), ease: easeOut });
    animate(hero.querySelectorAll('[data-hero-facts] > div'), { opacity: [0, 1], y: [18, 0] }, { duration: 0.6, delay: stagger(0.08, { startDelay: 1.0 }), ease: easeOut });

    const numeral = hero.querySelector('[data-hero-numeral]');
    if (numeral) {
        animate(numeral, { opacity: [0, 1], scale: [1.06, 1] }, { duration: 1.8, ease: easeOut });
        if (wide) {
            scroll(animate(numeral, { y: [0, 160] }, { ease: 'linear' }), { target: hero, offset: ['start start', 'end start'] });
        }
    }
    const media = hero.querySelector('[data-hero-media]');
    if (media) {
        animate(media, { scale: [1.08, 1], opacity: [0, 0.45] }, { duration: 1.6, ease: easeOut });
    }
}

/* ---------- Barra de progresso de leitura ---------- */
const progress = document.querySelector('[data-scroll-progress]');
if (progress) {
    progress.hidden = false;
    scroll(animate(progress, { scaleX: [0, 1] }, { ease: 'linear' }));
}

/* ---------- Revelações por seção ---------- */
const revealIn = (els, { card = false } = {}) => animate(els, { opacity: [0, 1], y: [card ? 28 : 20, 0], scale: card ? [0.97, 1] : [1, 1] }, {
    duration: card ? 0.55 : 0.5,
    delay: stagger(0.07),
    ease: card ? softBack : easeOut,
});

// Grupos: os filhos .reveal entram juntos, escalonados, quando o grupo aparece.
document.querySelectorAll('[data-stagger]').forEach((group) => {
    const items = [...group.querySelectorAll(':scope > .reveal, :scope > li > .reveal, :scope > li.reveal')];
    if (!items.length) return;
    inView(group, () => { revealIn(items, { card: group.dataset.stagger === 'cards' }); }, { amount: 0.12, margin: '0px 0px -6% 0px' });
});

// Elementos .reveal soltos (títulos, textos de seção).
document.querySelectorAll('.reveal').forEach((el) => {
    if (el.closest('[data-stagger]') || el.closest('[data-hero]')) return;
    inView(el, () => { animate(el, { opacity: [0, 1], y: [18, 0] }, { duration: 0.6, ease: easeOut }); }, { amount: 0.2 });
});

// Galeria: leve zoom-out da foto ao entrar.
document.querySelectorAll('[data-gallery-img]').forEach((img) => {
    inView(img, () => { animate(img, { scale: [1.08, 1] }, { duration: 1.1, ease: easeOut }); }, { amount: 0.25 });
});
