/**
 * Affiche un toast élégant et professionnel
 * @param {string} message - Le message à afficher
 * @param {object} [options] - Options de personnalisation
 * @param {'success'|'error'|'info'|'warning'} [options.type='info'] - Type de toast
 * @param {number} [options.duration=4000] - Durée d'affichage en ms
 * @param {string} [options.title] - Titre optionnel
 * @param {('top-right'|'top-center'|'bottom-right'|'bottom-center')} [options.position='top-right']
 * @param {boolean} [options.closable=true] - Bouton de fermeture
 * @param {() => void} [options.onClose] - Callback à la fermeture
 * @param {string|object} [options.image] - Image ou avatar
 *   - string : URL de l'image
 *   - object : { src, alt?, shape?:'circle'|'rounded'|'square', size?:number }
 * @param {'circle'|'rounded'|'square'} [options.imageShape='circle'] - Forme si image est une string
 * @param {number} [options.imageSize=40] - Taille en px si image est une string
 */
function showToast(message, options = {}) {
    const {
        type = 'info',
        duration = 4000,
        title,
        position = 'top-right',
        closable = true,
        onClose,
        image,
        imageShape = 'circle',
        imageSize = 40,
    } = options;

    // ─── Résolution de l'image ───
    const imgConfig = resolveImage(image, imageShape, imageSize);

    // ─── Icônes SVG par type ───
    const icons = {
        success: `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>`,
        error: `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>`,
        warning: `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
        info: `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>`,
    };

    // ─── Couleurs par type ───
    const themes = {
        success: { bg: '#ecfdf5', border: '#10b981', accent: '#059669', text: '#065f46' },
        error:   { bg: '#fef2f2', border: '#ef4444', accent: '#dc2626', text: '#991b1b' },
        warning: { bg: '#fffbeb', border: '#f59e0b', accent: '#d97706', text: '#92400e' },
        info:    { bg: '#eff6ff', border: '#3b82f6', accent: '#2563eb', text: '#1e40af' },
    };

    const theme = themes[type];

    // ─── Conteneur principal (créé une seule fois) ───
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        Object.assign(container.style, {
            position: 'fixed',
            zIndex: '9999',
            display: 'flex',
            flexDirection: 'column',
            gap: '10px',
            pointerEvents: 'none',
            fontFamily: "'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif",
            ...getPositionStyles(position),
        });
        document.body.appendChild(container);

        const style = document.createElement('style');
        style.textContent = `
      @keyframes toast-in {
        from { opacity: 0; transform: translateY(-12px) scale(0.96); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
      }
      @keyframes toast-out {
        from { opacity: 1; transform: translateY(0) scale(1); }
        to   { opacity: 0; transform: translateY(-12px) scale(0.96); }
      }
      @keyframes toast-progress {
        from { width: 100%; }
        to   { width: 0%; }
      }
    `;
        document.head.appendChild(style);
    }

    // ─── Création du toast ───
    const toast = document.createElement('div');
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    Object.assign(toast.style, {
        display: 'flex',
        alignItems: 'flex-start',
        gap: '12px',
        padding: '14px 16px',
        minWidth: '300px',
        maxWidth: '420px',
        background: theme.bg,
        border: `1px solid ${theme.border}33`,
        borderLeft: `4px solid ${theme.accent}`,
        borderRadius: '10px',
        boxShadow: '0 4px 24px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.04)',
        pointerEvents: 'auto',
        animation: 'toast-in 0.35s cubic-bezier(0.21, 1.02, 0.73, 1) forwards',
        backdropFilter: 'blur(12px)',
        position: 'relative',
        overflow: 'hidden',
    });

    // ═══════════════════════════════════════
    //  🖼  IMAGE / AVATAR  (nouveau bloc)
    // ═══════════════════════════════════════
    if (imgConfig) {
        const imgEl = document.createElement('img');
        imgEl.src = imgConfig.src;
        imgEl.alt = imgConfig.alt || '';
        imgEl.loading = 'lazy';

        const radiusMap = { circle: '50%', rounded: '8px', square: '2px' };
        const size = imgConfig.size;
        const borderSize = Math.max(2, Math.round(size * 0.05));

        Object.assign(imgEl.style, {
            width: `${size}px`,
            height: `${size}px`,
            borderRadius: radiusMap[imgConfig.shape] || '50%',
            objectFit: 'cover',
            flexShrink: '0',
            border: `${borderSize}px solid ${theme.accent}22`,
            boxShadow: `0 2px 8px ${theme.accent}18`,
            transition: 'transform 0.2s ease, box-shadow 0.2s ease',
        });

        // Micro-animation au survol du toast
        imgEl.addEventListener('mouseenter', () => {
            imgEl.style.transform = 'scale(1.08)';
            imgEl.style.boxShadow = `0 4px 14px ${theme.accent}30`;
        });
        imgEl.addEventListener('mouseleave', () => {
            imgEl.style.transform = 'scale(1)';
            imgEl.style.boxShadow = `0 2px 8px ${theme.accent}18`;
        });

        // Fallback si l'image ne charge pas → initiales
        imgEl.addEventListener('error', () => {
            imgEl.replaceWith(createInitialsFallback(title || message, imgConfig, theme));
        });

        toast.appendChild(imgEl);

        // ─── Sinon, icône classique ───
    } else {
        const iconWrap = document.createElement('span');
        iconWrap.innerHTML = icons[type];
        iconWrap.style.cssText = 'flex-shrink:0; margin-top:1px;';
        toast.appendChild(iconWrap);
    }

    // ─── Contenu texte ───
    const body = document.createElement('div');
    body.style.cssText = 'flex:1; min-width:0;';

    if (title) {
        const titleEl = document.createElement('div');
        titleEl.textContent = title;
        Object.assign(titleEl.style, {
            fontWeight: '600',
            fontSize: '14px',
            color: theme.text,
            marginBottom: '2px',
            lineHeight: '1.4',
        });
        body.appendChild(titleEl);
    }

    const msgEl = document.createElement('div');
    msgEl.textContent = message;
    Object.assign(msgEl.style, {
        fontSize: '13px',
        color: theme.text + 'cc',
        lineHeight: '1.5',
        wordBreak: 'break-word',
    });
    body.appendChild(msgEl);
    toast.appendChild(body);

    // ─── Bouton fermer ───
    if (closable) {
        const closeBtn = document.createElement('button');
        closeBtn.setAttribute('aria-label', 'Fermer');
        closeBtn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="${theme.text}66" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`;
        Object.assign(closeBtn.style, {
            background: 'none',
            border: 'none',
            cursor: 'pointer',
            padding: '4px',
            flexShrink: '0',
            borderRadius: '4px',
            display: 'flex',
            alignItems: 'center',
            transition: 'background 0.15s',
        });
        closeBtn.addEventListener('mouseenter', () => (closeBtn.style.background = theme.border + '18'));
        closeBtn.addEventListener('mouseleave', () => (closeBtn.style.background = 'none'));
        closeBtn.addEventListener('click', () => dismiss());
        toast.appendChild(closeBtn);
    }

    // ─── Barre de progression ───
    const progress = document.createElement('div');
    Object.assign(progress.style, {
        position: 'absolute',
        bottom: '0',
        left: '0',
        height: '3px',
        background: theme.accent,
        borderRadius: '0 0 0 10px',
        animation: `toast-progress ${duration}ms linear forwards`,
        opacity: '0.6',
    });
    toast.appendChild(progress);

    container.appendChild(toast);

    // ─── Fermeture automatique ───
    let timeout = setTimeout(dismiss, duration);
    let paused = false;

    toast.addEventListener('mouseenter', () => {
        if (paused) return;
        clearTimeout(timeout);
        progress.style.animationPlayState = 'paused';
    });

    toast.addEventListener('mouseleave', () => {
        if (paused) return;
        progress.style.animationPlayState = 'running';
        timeout = setTimeout(dismiss, 1500);
    });

    function dismiss() {
        if (paused) return;
        paused = true;
        clearTimeout(timeout);
        toast.style.animation = 'toast-out 0.3s cubic-bezier(0.06, 0.71, 0.55, 1) forwards';
        toast.addEventListener('animationend', () => {
            toast.remove();
            if (container.children.length === 0) container.remove();
            onClose?.();
        }, { once: true });
    }

    return { dismiss };
}

// ═══════════════════════════════════════════
//  🛠  Helpers
// ═══════════════════════════════════════════

/** Normalise l'option image en objet config */
function resolveImage(image, defaultShape, defaultSize) {
    if (!image) return null;

    if (typeof image === 'string') {
        return { src: image, shape: defaultShape, size: defaultSize, alt: '' };
    }

    return {
        src: image.src,
        shape: image.shape || defaultShape,
        size: image.size || defaultSize,
        alt: image.alt || '',
    };
}

/** Crée un fallback avec initiales si l'image ne charge pas */
function createInitialsFallback(text, imgConfig, theme) {
    const initials = (text || '?')
        .split(/\s+/)
        .slice(0, 2)
        .map(w => w[0]?.toUpperCase())
        .join('');

    const el = document.createElement('div');
    const size = imgConfig.size;
    const fontSize = Math.round(size * 0.42);

    const radiusMap = { circle: '50%', rounded: '8px', square: '2px' };

    Object.assign(el.style, {
        width: `${size}px`,
        height: `${size}px`,
        borderRadius: radiusMap[imgConfig.shape] || '50%',
        background: `linear-gradient(135deg, ${theme.accent}28, ${theme.accent}14)`,
        color: theme.accent,
        fontWeight: '700',
        fontSize: `${fontSize}px`,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        flexShrink: '0',
        border: `2px solid ${theme.accent}22`,
        fontFamily: "'Inter', 'Segoe UI', system-ui, sans-serif",
        letterSpacing: '0.5px',
    });

    el.textContent = initials;
    return el;
}

/** Retourne les styles CSS de position */
function getPositionStyles(position) {
    const map = {
        'top-right':     { top: '20px', right: '20px', alignItems: 'flex-end' },
        'top-center':    { top: '20px', left: '50%', transform: 'translateX(-50%)', alignItems: 'center' },
        'bottom-right':  { bottom: '20px', right: '20px', alignItems: 'flex-end' },
        'bottom-center': { bottom: '20px', left: '50%', transform: 'translateX(-50%)', alignItems: 'center' },
    };
    return map[position] || map['top-right'];
}
