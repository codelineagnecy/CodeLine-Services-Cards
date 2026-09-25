(function () {
    'use strict';

    var REVEAL_STAGGER_MS = 340;
    var REVEAL_TRANSITION_MS = 1100; // must match the .clsc-revealed transition duration in CSS

    // `vw` includes the vertical scrollbar's width, but the `%` used to
    // center .clsc's 100vw full-bleed box does not, so on any scrollable
    // page the section drifts left of the true viewport edge by half the
    // scrollbar's width. Publishing the gap as a custom property lets the
    // CSS cancel it out exactly (see .clsc in services-cards.css).
    function updateScrollbarWidthVar() {
        var gap = window.innerWidth - document.documentElement.clientWidth;
        document.documentElement.style.setProperty('--clsc-scrollbar-width', Math.max(0, gap) + 'px');
    }

    function initReveal(root) {
        if (!root || root.dataset.clscRevealReady === '1') {
            return;
        }

        root.dataset.clscRevealReady = '1';

        var cards = root.querySelectorAll('.clsc-card:not(.clsc-card--clone)');
        if (!cards.length) {
            return;
        }

        var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (reduceMotion || !('IntersectionObserver' in window)) {
            return;
        }

        root.querySelectorAll('.clsc-card').forEach(function (card) {
            card.classList.add('clsc-reveal-pending');
        });

        // A card plus its autoplay loop clones (see initOne), looked up at
        // call time so clones added later are included. Styling them in the
        // same callback starts their float animations in the same frame, so
        // a clone always floats in step with its original and the loop seam
        // never shows a vertical jump.
        function withClones(card, index) {
            return [card].concat(Array.prototype.slice.call(
                root.querySelectorAll('.clsc-card--clone[data-clsc-clone-of="' + index + '"]')
            ));
        }

        var revealed = false;

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting || revealed) {
                    return;
                }

                revealed = true;

                cards.forEach(function (card, index) {
                    var revealDelay = index * REVEAL_STAGGER_MS;

                    window.setTimeout(function () {
                        withClones(card, index).forEach(function (el) {
                            el.classList.remove('clsc-reveal-pending');
                            el.classList.add('clsc-revealed');
                        });
                    }, revealDelay);

                    // Randomize each card's float duration/distance so the fleet
                    // moves organically instead of in lockstep: 4.5-6s cycle,
                    // 8-14px of travel. The phase offset stays 0 so the float
                    // animation always starts at its 0% keyframe (translateY(0)),
                    // matching .clsc-revealed's resting transform exactly - a
                    // negative offset would jump the animation straight to its
                    // mid-cycle position, producing a visible snap right as
                    // floating begins.
                    var duration = 4.5 + Math.random() * 1.5;
                    var distance = 8 + Math.random() * 6;
                    var phaseOffset = 0;

                    window.setTimeout(function () {
                        withClones(card, index).forEach(function (el) {
                            el.style.setProperty('--clsc-float-duration', duration.toFixed(2) + 's');
                            el.style.setProperty('--clsc-float-delay', phaseOffset.toFixed(2) + 's');
                            el.style.setProperty('--clsc-float-distance', '-' + distance.toFixed(1) + 'px');
                            el.classList.add('clsc-floating');
                        });
                    }, revealDelay + REVEAL_TRANSITION_MS);
                });

                observer.disconnect();
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -60px 0px' });

        observer.observe(root);
    }

    // Desktop/laptop breakpoint, identical to the homepage Cases slider:
    // arrows (and optional admin autoplay) at 1200px and up, touch swipe
    // below it.
    var DESKTOP_MQ = '(min-width: 1200px)';
    var ARROW_SPEED_MS = 600; // same slide speed as the Cases slider
    var AUTOPLAY_PX_PER_SECOND = 40; // same glide speed as the Cases slider

    function initOne(root) {
        if (!root || root.dataset.clscReady === '1') {
            return;
        }

        var track = root.querySelector('.clsc-track');
        var prev = root.querySelector('.clsc-arrow--prev');
        var next = root.querySelector('.clsc-arrow--next');

        if (!track || !prev || !next) {
            return;
        }

        var updateFrame = 0;
        var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var desktopQuery = window.matchMedia ? window.matchMedia(DESKTOP_MQ) : null;
        var adminAutoplayEnabled = root.getAttribute('data-clsc-autoplay') === '1';

        // Touch gesture state, used only to suppress the click that ends a
        // real swipe. A gesture only becomes a "real drag" (gestureIsDrag)
        // once the finger has moved past GESTURE_DRAG_DISTANCE *and* that
        // movement actually scrolled the track past GESTURE_SCROLL_DELTA, so
        // ordinary jitter during a tap is never mistaken for a swipe.
        var GESTURE_DRAG_DISTANCE = 10;
        var GESTURE_SCROLL_DELTA = 1.5;
        var gestureStartX = 0;
        var gestureStartScrollLeft = 0;
        var gestureActive = false;
        var gestureIsDrag = false;

        function startGesture(x) {
            gestureStartX = x;
            gestureStartScrollLeft = track.scrollLeft;
            gestureActive = true;
            gestureIsDrag = false;
        }

        function updateGesture(x) {
            if (!gestureActive) {
                return;
            }

            var moved = Math.abs(x - gestureStartX);
            var scrolled = Math.abs(track.scrollLeft - gestureStartScrollLeft);

            if (moved > GESTURE_DRAG_DISTANCE && scrolled > GESTURE_SCROLL_DELTA) {
                gestureIsDrag = true;
            }
        }

        // `cancelled` clears gestureIsDrag too (touchcancel: the gesture was
        // aborted, so nothing later should still treat it as a completed
        // drag). A normal touchend leaves gestureIsDrag as-is so the click
        // handler below can consume it.
        function endGesture(cancelled) {
            gestureActive = false;

            if (cancelled) {
                gestureIsDrag = false;
            }
        }

        function isDesktopViewport() {
            return desktopQuery ? desktopQuery.matches : true;
        }

        function maxScrollLeft() {
            return Math.max(0, track.scrollWidth - track.clientWidth);
        }

        function trackGap() {
            var style = window.getComputedStyle(track);
            return parseFloat(style.columnGap || style.gap || '0') || 0;
        }

        function cardWidth() {
            var card = track.querySelector('.clsc-card');
            if (!card) {
                return track.clientWidth;
            }

            return card.offsetWidth + trackGap();
        }

        // Marquee mode: the admin autoplay setting is on, the viewport is
        // desktop and reduced motion is off. The track then loops endlessly
        // (like the Cases slider's Swiper loop) using clones of the real
        // cards appended after them. Clones are hidden from assistive
        // technology, carry no IDs and are out of the tab order.
        var originals = Array.prototype.slice.call(track.querySelectorAll('.clsc-card'));
        var leadClones = [];
        var clones = [];
        var loopPeriod = 0;

        function marqueeMode() {
            return adminAutoplayEnabled && !reduceMotion && isDesktopViewport() && originals.length > 0;
        }

        function makeClone(card, index) {
            var clone = card.cloneNode(true);

            clone.classList.add('clsc-card--clone');
            clone.setAttribute('data-clsc-clone-of', String(index));
            clone.setAttribute('aria-hidden', 'true');
            clone.removeAttribute('id');
            clone.querySelectorAll('[id]').forEach(function (el) {
                el.removeAttribute('id');
            });
            clone.querySelectorAll('a, area, button, input, select, textarea, iframe, summary, audio, video, [contenteditable], [tabindex]').forEach(function (el) {
                el.setAttribute('tabindex', '-1');
            });

            return clone;
        }

        // A clone added after its original started floating would otherwise
        // float out of phase with it; align the CSS animation clocks.
        function syncCloneMotion(clone, card) {
            if (typeof card.getAnimations !== 'function') {
                return;
            }

            var isCss = function (a) { return !!a.animationName; };
            var source = card.getAnimations().filter(isCss);
            var target = clone.getAnimations().filter(isCss);

            target.forEach(function (animation, i) {
                if (source[i] && source[i].animationName === animation.animationName) {
                    animation.currentTime = source[i].currentTime;
                }
            });
        }

        function appendClone(index, before) {
            var clone = makeClone(originals[index], index);

            track.insertBefore(clone, before);
            syncCloneMotion(clone, originals[index]);
            return clone;
        }

        // Loop layout: [one lead clone set] [real cards] [trailing clone
        // sets]. The loop position is kept within the real cards' lap
        // [period, 2 x period), so there are always cards on both sides:
        // arrows can step either way and a focused real card can take the
        // place of the clone that was on screen (see revealFocusedCard).
        // Trailing sets cover positions up to three periods plus a full
        // track width.
        function syncClones() {
            if (!marqueeMode()) {
                if (leadClones.length) {
                    var position = track.scrollLeft;

                    leadClones.concat(clones).forEach(function (clone) {
                        clone.remove();
                    });
                    leadClones = [];
                    clones = [];
                    clearSubpixel();
                    track.scrollLeft = loopPeriod > 0 ? ((position - loopPeriod) % loopPeriod + loopPeriod) % loopPeriod : 0;
                }
                loopPeriod = 0;
                return;
            }

            var step = cardWidth();
            var period = step * originals.length;

            if (period <= 0) {
                return;
            }

            var startPosition = leadClones.length ? null : track.scrollLeft;

            if (startPosition !== null) {
                leadClones = originals.map(function (card, index) {
                    return appendClone(index, originals[0]);
                });
            }

            var needed = (1 + Math.ceil((track.clientWidth + step + trackGap()) / period)) * originals.length;

            while (clones.length < needed) {
                clones.push(appendClone(clones.length % originals.length, null));
            }

            while (clones.length > needed) {
                clones.pop().remove();
            }

            loopPeriod = originals[0].offsetLeft - leadClones[0].offsetLeft;

            // The lead set pushed the real cards right by one period; follow
            // them so nothing visibly moves.
            if (startPosition !== null) {
                track.scrollLeft = startPosition + loopPeriod;
            }
        }

        // Moves a loop position by whole periods (identical content) into
        // the real cards' lap [period, 2 x period).
        function loopNormalize(position) {
            if (loopPeriod > 0) {
                while (position >= 2 * loopPeriod) {
                    position -= loopPeriod;
                }
                while (position < loopPeriod) {
                    position += loopPeriod;
                }
            }

            return position;
        }

        // Keyboard focus always lands on a real card, while the marquee may
        // be showing one of its clones, and Chrome scrolls the focused card
        // into view (centering it) before focusin fires. Starting again from
        // the last settled position (recorded by the scroll listener, whose
        // events arrive a frame later), the track is shifted by whole
        // periods so the real card takes the place of the copy that was on
        // screen; if no copy was fully visible, it moves only as far as
        // needed. Either way the track does not visibly jump.
        var settledPosition = 0;

        function revealFocusedCard(target) {
            var card = target && target.closest ? target.closest('.clsc-card') : null;

            if (loopPeriod <= 0 || !card || card.classList.contains('clsc-card--clone')) {
                return;
            }

            var position = settledPosition;
            var x = card.offsetLeft - track.offsetLeft - position;
            var room = track.clientWidth - card.offsetWidth;
            var best = null;

            [0, 1, -1].forEach(function (shift) {
                var copyX = x + shift * loopPeriod;
                var overflow = copyX < 0 ? copyX : Math.max(0, copyX - room);

                if (best === null || Math.abs(overflow) < Math.abs(best.overflow)) {
                    best = { shift: shift, overflow: overflow };
                }
            });

            track.scrollLeft = position - best.shift * loopPeriod + best.overflow;
        }

        // When every card fits inside the slider's edge insets, the group is
        // centered (see .clsc--centered in services-cards.css). Measured from
        // the cards' natural size, so toggling the class can never flip the
        // result. A looping marquee always fills the width, so it never centers.
        function updateCentering() {
            var centered = false;

            if (!marqueeMode() && originals.length) {
                var natural = originals.length * originals[0].offsetWidth + (originals.length - 1) * trackGap();
                var inset = parseFloat(window.getComputedStyle(root).getPropertyValue('--clsc-edge-inset')) || 0;

                centered = natural + 2 * inset <= root.clientWidth + 0.5;
            }

            root.classList.toggle('clsc--centered', centered);
        }

        function updateStateNow() {
            updateFrame = 0;

            if (loopPeriod > 0) {
                // Endless loop: both arrows always available.
                prev.disabled = next.disabled = false;
                prev.hidden = next.hidden = false;
                prev.setAttribute('aria-disabled', 'false');
                next.setAttribute('aria-disabled', 'false');
                return;
            }

            var maxScroll = maxScrollLeft();
            var canScroll = maxScroll > 2;
            var atStart = track.scrollLeft <= 2;
            var atEnd = track.scrollLeft >= (maxScroll - 2);

            prev.disabled = !canScroll || atStart;
            next.disabled = !canScroll || atEnd;
            prev.hidden = !canScroll;
            next.hidden = !canScroll;
            prev.setAttribute('aria-disabled', prev.disabled ? 'true' : 'false');
            next.setAttribute('aria-disabled', next.disabled ? 'true' : 'false');
        }

        function scheduleUpdateState() {
            if (updateFrame) {
                return;
            }

            updateFrame = window.requestAnimationFrame(updateStateNow);
        }

        // Layout work (clones, centering) runs only on init, resize and
        // breakpoint changes -- not on every scroll/autoplay frame.
        var layoutFrame = 0;

        function refreshLayoutNow() {
            layoutFrame = 0;
            syncClones();
            updateCentering();
            updateStateNow();
            autoplaySync();
        }

        function scheduleRefreshLayout() {
            if (layoutFrame) {
                return;
            }

            layoutFrame = window.requestAnimationFrame(refreshLayoutNow);
        }

        // Arrow navigation animates scrollLeft directly with one rAF loop
        // instead of native smooth scrollBy(): Chrome's smooth-scroll
        // animation is unreliable on an overflow-x: hidden element (the
        // desktop layout), sometimes silently no-op'ing. A new click cancels
        // the running animation, so rapid clicks never stack.
        var arrowRafId = null;
        var arrowTarget = 0;

        function stopArrowAnimation() {
            if (arrowRafId !== null) {
                window.cancelAnimationFrame(arrowRafId);
                arrowRafId = null;
            }
        }

        function easeInOut(t) {
            return t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2;
        }

        function animateScrollTo(target) {
            stopArrowAnimation();
            clearSubpixel();

            var from = track.scrollLeft;
            var distance = target - from;

            arrowTarget = target;

            if (reduceMotion || Math.abs(distance) < 1) {
                track.scrollLeft = target;
                scheduleUpdateState();
                return;
            }

            var startTs = null;

            function step(ts) {
                if (startTs === null) {
                    startTs = ts;
                }

                var progress = Math.min(1, (ts - startTs) / ARROW_SPEED_MS);
                track.scrollLeft = from + distance * easeInOut(progress);

                if (progress < 1) {
                    arrowRafId = window.requestAnimationFrame(step);
                } else {
                    arrowRafId = null;
                    if (loopPeriod > 0) {
                        track.scrollLeft = loopNormalize(track.scrollLeft);
                    }
                    scheduleUpdateState();
                    autoplaySync();
                }
            }

            arrowRafId = window.requestAnimationFrame(step);
        }

        // Moves to the next/previous card boundary. Autoplay can leave the
        // track between two cards, so the current index is rounded in the
        // direction of travel rather than to the nearest card. A click made
        // while an arrow animation is still running continues from that
        // animation's target, so rapid clicks each advance one card.
        // In marquee mode the track is first shifted by whole loop periods
        // (identical content, so nothing visibly moves) into the real cards'
        // lap, which has cloned cards on both sides.
        function scrollByCard(direction) {
            var stepWidth = cardWidth();
            var maxScroll = maxScrollLeft();

            if (stepWidth <= 0 || maxScroll <= 0) {
                return;
            }

            var base = arrowRafId !== null ? arrowTarget : track.scrollLeft;

            if (loopPeriod > 0) {
                var current = track.scrollLeft;
                var shift = loopNormalize(current) - current;

                if (shift !== 0) {
                    stopArrowAnimation();
                    track.scrollLeft = current + shift;
                    base += shift;
                }
            }

            var position = base / stepWidth;
            var index = direction > 0 ? Math.floor(position + 0.01) + 1 : Math.ceil(position - 0.01) - 1;
            var target = Math.max(0, Math.min(maxScroll, index * stepWidth));

            animateScrollTo(target);
            autoplaySync();
        }

        // Optional desktop-only autoplay, gated by the administrator's
        // "Enable automatic movement on desktop" setting. Uses the same
        // stable driver as the Cases slider: one requestAnimationFrame loop
        // guarded by an explicit "already running" check (so it can never
        // stack), a constant px/second marquee glide over the endlessly
        // looping track, and a per-frame delta clamped to 100ms so returning
        // to a background tab never produces a jump. The position is kept in
        // its own float accumulator so sub-pixel steps are never lost to
        // scrollLeft rounding.
        var autoplayRafId = null;
        var autoplayLastTs = null;
        var autoplayPosition = 0;
        var subpixel = 0;

        // scrollLeft snaps to whole device pixels, which at 40px/second
        // makes the glide step unevenly (e.g. 1, 1, 0 px per frame on a 1x
        // screen). The remaining fraction of a device pixel is carried as a
        // translate on the track, so the motion is as even as the Cases
        // slider's transform-based glide.
        function applySubpixel() {
            subpixel = track.scrollLeft - autoplayPosition;
            track.style.translate = subpixel ? subpixel + 'px 0' : '';
        }

        function clearSubpixel() {
            subpixel = 0;
            track.style.translate = '';
        }
        var hovered = false;
        var focusWithin = false;
        var pageHidden = (typeof document.hidden === 'boolean') ? document.hidden : false;

        function autoplayAllowed() {
            return marqueeMode()
                && loopPeriod > 0
                && !hovered
                && !focusWithin
                && !pageHidden
                && arrowRafId === null;
        }

        function autoplayFrame(ts) {
            if (!autoplayAllowed()) {
                autoplayStop();
                return;
            }

            if (autoplayLastTs !== null) {
                var dt = Math.min(ts - autoplayLastTs, 100);

                autoplayPosition = loopNormalize(autoplayPosition + AUTOPLAY_PX_PER_SECOND * dt / 1000);
                track.scrollLeft = autoplayPosition;
                applySubpixel();
            }

            autoplayLastTs = ts;
            autoplayRafId = window.requestAnimationFrame(autoplayFrame);
        }

        function autoplayStart() {
            if (autoplayRafId !== null || !autoplayAllowed()) {
                return;
            }

            // Resume from wherever the track is now (arrows may have moved it),
            // including any sub-pixel offset left by the previous run.
            autoplayPosition = track.scrollLeft - subpixel;
            autoplayLastTs = null;
            autoplayRafId = window.requestAnimationFrame(autoplayFrame);
        }

        function autoplayStop() {
            if (autoplayRafId !== null) {
                window.cancelAnimationFrame(autoplayRafId);
                autoplayRafId = null;
            }
            autoplayLastTs = null;
        }

        function autoplaySync() {
            if (autoplayAllowed()) {
                autoplayStart();
            } else {
                autoplayStop();
            }
        }

        function onTouchStart(event) {
            var touch = event.touches && event.touches[0];
            if (!touch) {
                return;
            }

            startGesture(touch.clientX);
        }

        function onTouchMove(event) {
            var touch = event.touches && event.touches[0];
            if (!touch) {
                return;
            }

            updateGesture(touch.clientX);
        }

        function onTouchEnd() {
            endGesture(false);
        }

        function onTouchCancel() {
            endGesture(true);
        }

        prev.addEventListener('click', function () {
            scrollByCard(-1);
        });

        next.addEventListener('click', function () {
            scrollByCard(1);
        });

        track.addEventListener('scroll', function () {
            settledPosition = track.scrollLeft;
            scheduleUpdateState();
        }, { passive: true });

        // Touch swipe (tablet/mobile) is native overflow scrolling; these
        // passive listeners only observe it and never block page scrolling.
        // No wheel or mouse-drag handlers exist: on desktop the track is
        // overflow hidden and moves only through the arrows.
        track.addEventListener('touchstart', onTouchStart, { passive: true });
        track.addEventListener('touchmove', onTouchMove, { passive: true });
        track.addEventListener('touchend', onTouchEnd, { passive: true });
        track.addEventListener('touchcancel', onTouchCancel, { passive: true });

        // Prevent the browser's native image/link drag-ghost over card images.
        track.addEventListener('dragstart', function (event) {
            event.preventDefault();
        });

        // A loop clone's link still opens its service when clicked, but a
        // mouse press must not focus it: focus stays on real cards only.
        track.addEventListener('mousedown', function (event) {
            if (event.target.closest && event.target.closest('.clsc-card--clone')) {
                event.preventDefault();
            }
        });

        // Arrow keys move the track directly by scrollLeft (see
        // animateScrollTo above for why native smooth scrolling is avoided).
        // maxScroll clamps so repeated presses can't overshoot.
        track.addEventListener('keydown', function (event) {
            if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') {
                return;
            }

            event.preventDefault();

            if (loopPeriod > 0) {
                scrollByCard(event.key === 'ArrowRight' ? 1 : -1);
                return;
            }

            stopArrowAnimation();

            var maxScroll = maxScrollLeft();
            var step = cardWidth();

            if (event.key === 'ArrowRight') {
                track.scrollLeft = Math.min(maxScroll, track.scrollLeft + step);
            } else {
                track.scrollLeft = Math.max(0, track.scrollLeft - step);
            }

            scheduleUpdateState();
        });

        // Only a gesture that was confirmed as a real swipe (see updateGesture
        // above) suppresses the click that follows it. Consuming the flag
        // here stops a stale "was dragging" state from leaking into a later,
        // unrelated click.
        track.addEventListener('click', function (event) {
            if (gestureIsDrag) {
                event.preventDefault();
                event.stopPropagation();
            }
            gestureIsDrag = false;
        }, true);

        // Only keyboard focus pauses autoplay: a mouse click on an arrow also
        // focuses it, and that must not keep the marquee paused after the
        // pointer leaves the slider.
        function isKeyboardFocus(el) {
            try {
                return el.matches(':focus-visible');
            } catch (e) {
                return true;
            }
        }

        if (adminAutoplayEnabled && !reduceMotion) {
            root.addEventListener('mouseenter', function () { hovered = true; autoplayStop(); });
            root.addEventListener('mouseleave', function () { hovered = false; autoplaySync(); });
            root.addEventListener('focusin', function (event) {
                revealFocusedCard(event.target);
                focusWithin = isKeyboardFocus(event.target);
                autoplaySync();
            });
            root.addEventListener('focusout', function (event) {
                if (!event.relatedTarget || !root.contains(event.relatedTarget)) {
                    focusWithin = false;
                    autoplaySync();
                }
            });

            document.addEventListener('visibilitychange', function () {
                pageHidden = !!document.hidden;
                autoplaySync();
            });
        }

        // One listener per slider, attached once (initOne is guarded by
        // data-clsc-ready): leaving desktop stops any arrow animation and
        // autoplay and removes the loop clones; entering desktop may add
        // them and start autoplay again.
        function onBreakpointChange() {
            if (!isDesktopViewport()) {
                stopArrowAnimation();
                autoplayStop();
            }
            scheduleRefreshLayout();
        }

        if (desktopQuery) {
            if (desktopQuery.addEventListener) {
                desktopQuery.addEventListener('change', onBreakpointChange);
            } else if (desktopQuery.addListener) {
                desktopQuery.addListener(onBreakpointChange);
            }
        }

        if ('ResizeObserver' in window) {
            var resizeObserver = new ResizeObserver(scheduleRefreshLayout);
            resizeObserver.observe(track);
            resizeObserver.observe(root);
        } else {
            var resizeTimer = 0;
            window.addEventListener('resize', function () {
                window.clearTimeout(resizeTimer);
                resizeTimer = window.setTimeout(scheduleRefreshLayout, 120);
            }, { passive: true });
        }

        root.dataset.clscReady = '1';
        refreshLayoutNow();
        window.setTimeout(scheduleRefreshLayout, 120);
    }

    function initAll() {
        updateScrollbarWidthVar();
        document.querySelectorAll('.clsc').forEach(function (root) {
            initOne(root);
            initReveal(root);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll, { once: true });
    } else {
        initAll();
    }

    window.addEventListener('pageshow', initAll, { passive: true });

    (function () {
        var resizeTimer = 0;
        window.addEventListener('resize', function () {
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(updateScrollbarWidthVar, 120);
        }, { passive: true });
    }());
}());
