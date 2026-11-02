function enhanceTable(table, options = {}) {
    const el = typeof table === 'string' ? document.querySelector(table) : table;
    if (!el) { console.warn('enhanceTable : table introuvable'); return; }

    const pageSize    = options.pageSize    || 10;
    const filename    = options.filename    || 'export.csv';
    const placeholder = options.placeholder || 'Rechercher...';

    const allRows = Array.from(el.tBodies[0] ? el.tBodies[0].rows : el.rows)
        .filter(r => r.cells.length && !r.closest('thead'));
    const headers = Array.from(el.tHead ? el.tHead.rows[0].cells : [])
        .map(th => th.textContent.trim());

    // --- Habillage ---
    const wrapper = document.createElement('div');
    wrapper.className = 'dt-wrapper';
    el.parentNode.insertBefore(wrapper, el);

    const toolbar = document.createElement('div');
    toolbar.className = 'dt-toolbar';
    toolbar.innerHTML = `
        <div class="dt-search">
            <input type="text" placeholder="${placeholder}">
        </div>
        <button class="dt-export" type="button">⬇ Exporter CSV</button>
    `;
    wrapper.appendChild(toolbar);
    wrapper.appendChild(el);

    const footer = document.createElement('div');
    footer.className = 'dt-footer';
    footer.innerHTML = `<div class="dt-count"></div><div class="dt-pagination"></div>`;
    wrapper.appendChild(footer);

    const empty = document.createElement('div');
    empty.className = 'dt-empty';
    empty.textContent = 'Aucun résultat';
    empty.style.display = 'none';
    el.after(empty);

    const inputSearch = toolbar.querySelector('.dt-search input');
    const btnExport   = toolbar.querySelector('.dt-export');
    const countEl     = footer.querySelector('.dt-count');
    const pagerEl     = footer.querySelector('.dt-pagination');

    // --- État ---
    let currentPage = 1;
    let filtered = allRows.slice();
    let sortCol = -1;              // (TRI) index de colonne triée
    let sortDir = 1;              // (TRI) 1 = croissant, -1 = décroissant
    let searchTerm = '';          // (TRI) on mémorise le filtre pour le réappliquer après tri

    function applyFilter() {
        const term = searchTerm.trim().toLowerCase();
        filtered = term
            ? allRows.filter(r => r.textContent.toLowerCase().indexOf(term) !== -1)
            : allRows.slice();
        currentPage = 1;
    }

    // ===== (TRI) Comparaison intelligente d'une colonne =====
    function cellValue(row, col) {
        const cell = row.cells[col];
        return cell ? cell.textContent.trim() : '';
    }

    function compare(a, b) {
        let va = cellValue(a, sortCol);
        let vb = cellValue(b, sortCol);

        // Tentative numérique (gère "1 234,56" et "1234.56")
        const na = parseFloat(va.replace(/\s/g, '').replace(',', '.'));
        const nb = parseFloat(vb.replace(/\s/g, '').replace(',', '.'));
        const bothNum = !isNaN(na) && !isNaN(nb)
            && /^[\d\s.,%€$-]+$/.test(va) && /^[\d\s.,%€$-]+$/.test(vb);

        if (bothNum) return (na - nb) * sortDir;

        // Tentative date (JJ/MM/AAAA ou AAAA-MM-JJ)
        const da = parseDate(va), db = parseDate(vb);
        if (da && db) return (da - db) * sortDir;

        // Texte, insensible à la casse et aux accents
        return va.localeCompare(vb, 'fr', { sensitivity: 'base' }) * sortDir;
    }

    function parseDate(s) {
        let m = s.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);          // JJ/MM/AAAA
        if (m) return new Date(+m[3], +m[2] - 1, +m[1]).getTime();
        m = s.match(/^(\d{4})-(\d{2})-(\d{2})$/);                // AAAA-MM-JJ
        if (m) return new Date(+m[1], +m[2] - 1, +m[3]).getTime();
        return null;
    }

    function sortBy(col) {
        if (sortCol === col) {
            sortDir = -sortDir;          // même colonne -> on inverse le sens
        } else {
            sortCol = col;
            sortDir = 1;                 // nouvelle colonne -> croissant
        }

        allRows.sort(compare);           // on trie la liste maître...
        allRows.forEach(r => el.tBodies[0].appendChild(r)); // ...et on réordonne le DOM
        applyFilter();                   // on réapplique le filtre courant (ordre préservé)
        updateHeaderIndicators();
        render();
    }

    function updateHeaderIndicators() {
        const ths = el.tHead ? el.tHead.rows[0].cells : [];
        Array.from(ths).forEach((th, i) => {
            th.classList.remove('dt-sort-asc', 'dt-sort-desc');
            if (i === sortCol) {
                th.classList.add(sortDir === 1 ? 'dt-sort-asc' : 'dt-sort-desc');
            }
        });
    }
    // ===== fin bloc TRI =====

    function render() {
        const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
        currentPage = Math.min(Math.max(1, currentPage), totalPages);

        const start = (currentPage - 1) * pageSize;
        const end = start + pageSize;

        allRows.forEach(r => r.style.display = 'none');
        filtered.slice(start, end).forEach(r => r.style.display = '');

        empty.style.display = filtered.length === 0 ? 'block' : 'none';

        if (filtered.length) {
            countEl.textContent =
                `${start + 1} – ${Math.min(end, filtered.length)} sur ${filtered.length}` +
                (filtered.length !== allRows.length ? ` (filtré sur ${allRows.length})` : '');
        } else {
            countEl.textContent = `0 sur ${allRows.length}`;
        }

        renderPager(totalPages);
    }

    function renderPager(totalPages) {
        pagerEl.innerHTML = '';
        if (totalPages <= 1) return;

        const addBtn = (label, page, { disabled = false, active = false } = {}) => {
            const b = document.createElement('button');
            b.innerHTML = label;
            b.disabled = disabled;
            if (active) b.classList.add('active');
            b.addEventListener('click', () => { currentPage = page; render(); });
            pagerEl.appendChild(b);
        };

        addBtn('‹', currentPage - 1, { disabled: currentPage === 1 });

        const windowSize = 5;
        let s = Math.max(1, currentPage - Math.floor(windowSize / 2));
        let e = Math.min(totalPages, s + windowSize - 1);
        s = Math.max(1, e - windowSize + 1);
        for (let p = s; p <= e; p++) addBtn(p, p, { active: p === currentPage });

        addBtn('›', currentPage + 1, { disabled: currentPage === totalPages });
    }

    function exportCSV() {
        const escape = v => {
            v = (v == null ? '' : String(v)).replace(/"/g, '""');
            return /[",\n;]/.test(v) ? `"${v}"` : v;
        };
        const lines = [];
        if (headers.length) lines.push(headers.map(escape).join(';'));
        filtered.forEach(r => {
            const cols = Array.from(r.cells).map(td => escape(td.textContent.trim()));
            lines.push(cols.join(';'));
        });
        const blob = new Blob(['\uFEFF' + lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = filename;
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    // ===== (TRI) Récupération de la valeur à trier =====
    // Priorité à l'attribut data-sort, sinon le texte visible de la cellule.
    function cellValue(row, col) {
        const cell = row.cells[col];
        if (!cell) return '';
        const ds = cell.getAttribute('data-sort');
        return ds !== null ? ds.trim() : cell.textContent.trim();
    }

    function compare(a, b) {
        let va = cellValue(a, sortCol);
        let vb = cellValue(b, sortCol);

        // 1) Nombre pur (idéal pour data-sort="1234" ou data-sort="20260115")
        const pa = Number(va);
        const pb = Number(vb);
        if (va !== '' && vb !== '' && !isNaN(pa) && !isNaN(pb)) {
            return (pa - pb) * sortDir;
        }

        // 2) Nombre formaté (ex. "1 234,56", "45 %", "12 €")
        const na = parseFloat(va.replace(/\s/g, '').replace(',', '.'));
        const nb = parseFloat(vb.replace(/\s/g, '').replace(',', '.'));
        const bothNum = !isNaN(na) && !isNaN(nb)
            && /^[\d\s.,%€$-]+$/.test(va) && /^[\d\s.,%€$-]+$/.test(vb);
        if (bothNum) return (na - nb) * sortDir;

        // 3) Date (JJ/MM/AAAA ou AAAA-MM-JJ)
        const da = parseDate(va), db = parseDate(vb);
        if (da && db) return (da - db) * sortDir;

        // 4) Texte, insensible à la casse et aux accents
        return va.localeCompare(vb, 'fr', { sensitivity: 'base' }) * sortDir;
    }
    // --- Événements ---
    inputSearch.addEventListener('input', function () {
        searchTerm = this.value;
        applyFilter();
        render();
    });
    btnExport.addEventListener('click', exportCSV);

    // (TRI) rendre les en-têtes cliquables
    if (el.tHead) {
        Array.from(el.tHead.rows[0].cells).forEach((th, i) => {
            th.classList.add('dt-sortable');
            th.addEventListener('click', () => sortBy(i));
        });
    }

    render();
}
