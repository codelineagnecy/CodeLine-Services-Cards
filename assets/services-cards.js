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

        var cards = root.querySelectorAll('.clsc-card');
        if (!cards.length) {
            return;
        }

        var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (reduceMotion || !('IntersectionObserver' in window)) {
            return;
        }

        cards.forEach(function (card) {
            card.classList.add('clsc-reveal-pending');
        });

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
                        card.classList.remove('clsc-reveal-pending');
                        card.classList.add('clsc-revealed');
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

                    card.style.setProperty('--clsc-float-duration', duration.toFixed(2) + 's');
                    card.style.setProperty('--clsc-float-delay', phaseOffset.toFixed(2) + 's');
                    card.style.setProperty('--clsc-float-distance', '-' + distance.toFixed(1) + 'px');

                    window.setTimeout(function () {
                        card.classList.add('clsc-floating');
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
    var AUTOPLAY_EDGE_PAUSE_MS = 1500;

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

        function cardWidth() {
            var card = track.querySelector('.clsc-card');
            if (!card) {
                return track.clientWidth;
            }

            var style = window.getComputedStyle(track);
            var gap = parseFloat(style.columnGap || style.gap || '0') || 0;
            return card.offsetWidth + gap;
        }

        function updateStateNow() {
            updateFrame = 0;

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
        function scrollByCard(direction) {
            var stepWidth = cardWidth();
            var maxScroll = maxScrollLeft();

            if (stepWidth <= 0 || maxScroll <= 0) {
                return;
            }

            var base = arrowRafId !== null ? arrowTarget : track.scrollLeft;
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
        // stack), a constant px/second glide, and a per-frame delta clamped
        // to 100ms so returning to a background tab never produces a jump.
        // As this track does not loop, the glide reverses direction at each
        // end after a short pause instead of wrapping around.
        var autoplayRafId = null;
        var autoplayLastTs = null;
        var autoplayPosition = 0;
        var autoplayDirection = 1;
        var autoplayPauseUntil = 0;
        var hovered = false;
        var focusWithin = false;
        var pageHidden = (typeof document.hidden === 'boolean') ? document.hidden : false;

        function autoplayAllowed() {
            return adminAutoplayEnabled
                && !reduceMotion
                && isDesktopViewport()
                && !hovered
                && !focusWithin
                && !pageHidden
                && arrowRafId === null
                && maxScrollLeft() > 2;
        }

        function autoplayFrame(ts) {
            if (!autoplayAllowed()) {
                autoplayStop();
                return;
            }

            if (autoplayLastTs !== null && ts >= autoplayPauseUntil) {
                var dt = Math.min(ts - autoplayLastTs, 100);
                var maxScroll = maxScrollLeft();

                autoplayPosition += autoplayDirection * AUTOPLAY_PX_PER_SECOND * dt / 1000;

                if (autoplayPosition >= maxScroll) {
                    autoplayPosition = maxScroll;
                    autoplayDirection = -1;
                    autoplayPauseUntil = ts + AUTOPLAY_EDGE_PAUSE_MS;
                } else if (autoplayPosition <= 0) {
                    autoplayPosition = 0;
                    autoplayDirection = 1;
                    autoplayPauseUntil = ts + AUTOPLAY_EDGE_PAUSE_MS;
                }

                track.scrollLeft = autoplayPosition;
                scheduleUpdateState();
            }

            autoplayLastTs = ts;
            autoplayRafId = window.requestAnimationFrame(autoplayFrame);
        }

        function autoplayStart() {
            if (autoplayRafId !== null || !autoplayAllowed()) {
                return;
            }

            // Resume from wherever the track is now (arrows may have moved it).
            autoplayPosition = track.scrollLeft;
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

        track.addEventListener('scroll', scheduleUpdateState, { passive: true });

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

        // Arrow keys move the track directly by scrollLeft (see
        // animateScrollTo above for why native smooth scrolling is avoided).
        // maxScroll clamps so repeated presses can't overshoot.
        track.addEventListener('keydown', function (event) {
            if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') {
                return;
            }

            event.preventDefault();
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

        if (adminAutoplayEnabled && !reduceMotion) {
            root.addEventListener('mouseenter', function () { hovered = true; autoplayStop(); });
            root.addEventListener('mouseleave', function () { hovered = false; autoplaySync(); });
            root.addEventListener('focusin', function () { focusWithin = true; autoplayStop(); });
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
        // autoplay; entering desktop may start autoplay again.
        function onBreakpointChange() {
            if (!isDesktopViewport()) {
                stopArrowAnimation();
            }
            autoplaySync();
            scheduleUpdateState();
        }

        if (desktopQuery) {
            if (desktopQuery.addEventListener) {
                desktopQuery.addEventListener('change', onBreakpointChange);
            } else if (desktopQuery.addListener) {
                desktopQuery.addListener(onBreakpointChange);
            }
        }

        if ('ResizeObserver' in window) {
            var resizeObserver = new ResizeObserver(scheduleUpdateState);
            resizeObserver.observe(track);
            resizeObserver.observe(root);
        } else {
            var resizeTimer = 0;
            window.addEventListener('resize', function () {
                window.clearTimeout(resizeTimer);
                resizeTimer = window.setTimeout(scheduleUpdateState, 120);
            }, { passive: true });
        }

        root.dataset.clscReady = '1';
        scheduleUpdateState();
        window.setTimeout(function () {
            scheduleUpdateState();
            autoplaySync();
        }, 120);
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
