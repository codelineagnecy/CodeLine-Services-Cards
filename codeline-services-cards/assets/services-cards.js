(function () {
    'use strict';

    function initOne(root) {
        if (!root || root.dataset.clscReady === '1') {
            return;
        }

        var track = root.querySelector('.clsc-track');
        var controls = root.querySelector('.clsc-controls');
        var prev = root.querySelector('.clsc-arrow--prev');
        var next = root.querySelector('.clsc-arrow--next');
        var startX = 0;
        var draggedDistance = 0;

        if (!track || !prev || !next) {
            return;
        }

        function onTouchStart(event) {
            var touch = event.touches && event.touches[0];
            if (!touch) {
                return;
            }

            startX = touch.clientX;
            draggedDistance = 0;
        }

        function onTouchMove(event) {
            var touch = event.touches && event.touches[0];
            if (!touch) {
                return;
            }

            var deltaX = touch.clientX - startX;
            draggedDistance = Math.max(draggedDistance, Math.abs(deltaX));
        }

        function onTouchEnd() {}

        function cardWidth() {
            var card = track.querySelector('.clsc-card');
            if (!card) {
                return track.clientWidth;
            }

            var style = window.getComputedStyle(track);
            var gap = parseFloat(style.columnGap || style.gap || '0') || 0;
            return card.getBoundingClientRect().width + gap;
        }

        function updateState() {
            var maxScroll = Math.max(0, track.scrollWidth - track.clientWidth);
            var canScroll = maxScroll > 2;
            var atStart = track.scrollLeft <= 2;
            var atEnd = track.scrollLeft >= (maxScroll - 2);

            prev.disabled = !canScroll || atStart;
            next.disabled = !canScroll || atEnd;
            prev.hidden = !canScroll;
            next.hidden = !canScroll;
        }

        prev.addEventListener('click', function () {
            track.scrollBy({ left: -cardWidth(), behavior: 'smooth' });
        });

        next.addEventListener('click', function () {
            track.scrollBy({ left: cardWidth(), behavior: 'smooth' });
        });

        track.addEventListener('scroll', updateState, { passive: true });
        track.addEventListener('touchstart', onTouchStart, { passive: true });
        track.addEventListener('touchmove', onTouchMove, { passive: true });
        track.addEventListener('touchend', onTouchEnd, { passive: true });
        track.addEventListener('touchcancel', onTouchEnd, { passive: true });

        track.addEventListener('click', function (event) {
            if (draggedDistance > 10) {
                event.preventDefault();
                event.stopPropagation();
            }

            draggedDistance = 0;
        }, true);

        window.addEventListener('resize', function () {
            updateState();
        });
        window.addEventListener('load', function () {
            updateState();
        });

        if ('ResizeObserver' in window) {
            var resizeObserver = new ResizeObserver(function () {
                updateState();
            });
            resizeObserver.observe(track);
            resizeObserver.observe(root);
        }

        updateState();
        setTimeout(updateState, 120);
        root.dataset.clscReady = '1';
    }

    function initAll() {
        document.querySelectorAll('.clsc').forEach(initOne);
    }

    document.addEventListener('DOMContentLoaded', function () {
        initAll();

        var observer = new MutationObserver(function () {
            initAll();
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
        });
    });
})();
