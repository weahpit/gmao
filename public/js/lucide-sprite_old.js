/* Lucide SVG Sprite Helper - lightweight replacement for the full UMD bundle */
/* Converts <i data-lucide="icon-name"></i> to <svg><use xlink:href="#lucide-icon-name"></svg> */
(function() {
    function replaceIcons() {
        document.querySelectorAll('i[data-lucide]').forEach(function(el) {
            var name = el.getAttribute('data-lucide');
            var className = el.className;
            var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('class', 'lucide lucide-' + name + (className ? ' ' + className : ''));
            svg.setAttribute('width', '24');
            svg.setAttribute('height', '24');
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
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', replaceIcons);
    } else {
        replaceIcons();
    }
})();
