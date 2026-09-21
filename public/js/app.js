/* CITRAC GMAO — App JS */

/**
 * Replace an <i data-lucide="name"> placeholder with an inline SVG
 * that references the project's Lucide sprite sheet.
 */
let URL_APP = window.location.origin + '/'

function lucideReplace(el, size) {
    size = size || 18;
    var name = el.getAttribute('data-lucide');
    var extraClass = el.className || '';
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', 'lucide lucide-' + name + (extraClass ? ' ' + extraClass : ''));
    svg.setAttribute('width', String(size));
    svg.setAttribute('height', String(size));
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '2');
    svg.setAttribute('stroke-linecap', 'round');
    svg.setAttribute('stroke-linejoin', 'round');
    var use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
    use.setAttributeNS('http://www.w3.org/1999/xlink', 'xlink:href', '#lucide-' + name);
    svg.appendChild(use);
    el.replaceWith(svg);
}

/**
 * Walk the entire DOM and render every <i data-lucide> into SVG.
 */
function lucideRenderAll() {
    document.querySelectorAll('i[data-lucide]').forEach(function(el) {
        lucideReplace(el);
    });
}

document.addEventListener('DOMContentLoaded', () => {

    /* --- Initial Lucide icon render --- */
    lucideRenderAll();

    /* --- Sidebar Toggle (collapse) --- */
    const sidebarToggle = document.querySelector('#sidebarToggle');
    const sidebar = document.querySelector('#sidebar');
    const mainWrapper = document.querySelector('#main-wrapper');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.add('collapsed');
            if (mainWrapper) mainWrapper.classList.add('expanded');
        });
    }

    /* --- Banner Toggle (reopen sidebar) --- */
    const bannerToggle = document.querySelector('#bannerToggle');

    if (bannerToggle && sidebar) {
        bannerToggle.addEventListener('click', () => {
            sidebar.classList.remove('collapsed');
            if (mainWrapper) mainWrapper.classList.remove('expanded');
        });
    }

    /* --- Mobile overlay sidebar --- */
    let overlay = document.querySelector('.sidebar-overlay');
    if (!overlay && window.innerWidth <= 1024) {
        overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:99;display:none;';
        document.body.appendChild(overlay);
    }
    if (overlay) {
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.style.display = 'none';
        });
    }

    /* --- ⌘K / Ctrl+K Search Shortcut --- */
    const searchInput = document.getElementById('search-input');
    document.addEventListener('keydown', (e) => {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            if (searchInput) searchInput.focus();
        }
        if (e.key === 'Escape' && searchInput) {
            searchInput.blur();
        }
    });

    /* --- Auto-dismiss flash messages --- */
    document.querySelectorAll('.flash-success, .flash-error, .flash-warning, .flash-info').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity .4s ease';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 400);
        }, 5000);
    });

    /* --- Confirm delete / dangerous actions --- */
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', (e) => {
            const msg = el.dataset.confirm || 'Confirmer cette action ?';
            if (!confirm(msg)) e.preventDefault();
        });
    });

    /* --- Re-run Lucide sprite replacement after dynamic content --- */
    const observer = new MutationObserver(() => {
        document.querySelectorAll('i[data-lucide]').forEach(function(el) {
            lucideReplace(el);
        });
    });
    observer.observe(document.body, { childList: true, subtree: true });
});
