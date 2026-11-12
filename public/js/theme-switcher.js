/* ============================================================
   CITRAC GMAO — Sélecteur de thème de couleurs
   ------------------------------------------------------------
   - Mémorise le choix de l'utilisateur dans localStorage.
   - Applique le thème sur <html data-theme="..."> ; les couleurs
     sont ensuite gérées par public/css/themes.css.
   - Thèmes disponibles : base (eucalyptus), cacao, vert, orange.
   Rien d'autre n'est modifié : retirer ce script et themes.css
   restaure le comportement d'origine.
   ============================================================ */
(function () {
  'use strict';

  var STORAGE_KEY = 'citrac-theme';
  var THEMES = ['base', 'cacao', 'vert', 'orange', 'bleu', 'gris', 'blanc'];
  var LABELS = {
    base: 'Eucalyptus (par défaut)',
    cacao: 'Cacao doux',
    vert: 'Vert doux',
    orange: 'Orange doux',
    bleu: 'Bleu léger',
    gris: 'Gris léger',
    blanc: 'Blanc'
  };

  function readTheme() {
    var t;
    try { t = window.localStorage.getItem(STORAGE_KEY); } catch (e) { t = null; }
    return THEMES.indexOf(t) !== -1 ? t : 'base';
  }

  // Enregistre le theme en base pour l'utilisateur connecte (si disponible).
  // Les variables window.CitracThemeEndpoint / window.CitracThemeCsrf sont
  // posees par un script inline dans le <head> des pages applicatives.
  function persistTheme(theme) {
    var url = window.CitracThemeEndpoint;
    var csrf = window.CitracThemeCsrf;
    if (!url || !csrf || !window.fetch) { return; }
    var body = 'theme=' + encodeURIComponent(theme) + '&_token=' + encodeURIComponent(csrf);
    try {
      window.fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: body,
        credentials: 'same-origin'
      }).catch(function () {});
    } catch (e) {}
  }

  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    try { window.localStorage.setItem(STORAGE_KEY, theme); } catch (e) {}
    // Met à jour l'état visuel des options (si le menu existe déjà)
    var options = document.querySelectorAll('.theme-option');
    for (var i = 0; i < options.length; i++) {
      var val = options[i].getAttribute('data-theme-value');
      options[i].classList.toggle('active', val === theme);
    }
  }

  // Applique + enregistre en base (declenche uniquement sur un choix utilisateur).
  function chooseTheme(theme) {
    applyTheme(theme);
    persistTheme(theme);
  }

  // Expose le thème actif très tôt (le data-theme initial est posé
  // par un script inline dans le <head> pour éviter tout clignotement).
  window.CitracTheme = { apply: applyTheme, current: readTheme };

  function buildSwitcher(container) {
    if (!container || container.querySelector('.theme-switcher')) {
      return;
    }

    var current = readTheme();

    var wrap = document.createElement('div');
    wrap.className = 'theme-switcher';
    wrap.id = 'themeSwitcher';

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'theme-switcher__btn';
    btn.setAttribute('aria-label', 'Choisir un thème de couleurs');
    btn.setAttribute('title', 'Thème de couleurs');
    btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-palette"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.555C21.965 6.012 17.461 2 12 2z"/></svg>';

    var menu = document.createElement('div');
    menu.className = 'theme-switcher__menu';
    menu.setAttribute('role', 'menu');

    var title = document.createElement('div');
    title.className = 'theme-switcher__title';
    title.textContent = 'Thème de couleurs';
    menu.appendChild(title);

    THEMES.forEach(function (theme) {
      var opt = document.createElement('button');
      opt.type = 'button';
      opt.className = 'theme-option' + (theme === current ? ' active' : '');
      opt.setAttribute('data-theme-value', theme);
      opt.setAttribute('role', 'menuitem');

      var sw = document.createElement('span');
      sw.className = 'swatch swatch--' + theme;
      opt.appendChild(sw);

      var label = document.createElement('span');
      label.textContent = LABELS[theme];
      opt.appendChild(label);

      opt.addEventListener('click', function () {
        chooseTheme(theme);
        wrap.classList.remove('open');
      });

      menu.appendChild(opt);
    });

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      wrap.classList.toggle('open');
    });

    document.addEventListener('click', function (e) {
      if (!wrap.contains(e.target)) {
        wrap.classList.remove('open');
      }
    });

    wrap.appendChild(btn);
    wrap.appendChild(menu);
    container.appendChild(wrap);
  }

  document.addEventListener('DOMContentLoaded', function () {
    applyTheme(readTheme());
    // Barre supérieure des pages applicatives
    var actions = document.querySelector('.banner-actions');
    if (actions) {
      buildSwitcher(actions);
    }
    // Page de connexion
    var loginCard = document.querySelector('.login-wrapper');
    if (loginCard && !document.querySelector('.banner-actions')) {
      buildSwitcher(loginCard);
    }
  });
})();
