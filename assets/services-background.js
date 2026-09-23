(function () {
    'use strict';

    var READY_KEY = 'clscCodeBackgroundReady';
    var BACKGROUND = '#090909';
    var SECTION_ANCESTOR_SELECTOR = '.elementor-section, .e-con, [data-element_type="section"], [data-element_type="container"]';
    var DESKTOP_MAX_DPR = 2;
    var MOBILE_MAX_DPR = 1.5;
    var DESKTOP_FPS = 60;
    var MOBILE_FPS = 48;
    var REDUCED_FPS = 24;
    var TAU = Math.PI * 2;
    var PARTICLE_BRIGHTNESS = 1.20;

    function clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
    }

    function random(min, max) {
        return min + (Math.random() * (max - min));
    }

    function createDustSprite() {
        var sprite = document.createElement('canvas');
        var size = 48;
        var ctx = sprite.getContext('2d');

        sprite.width = size;
        sprite.height = size;

        if (!ctx) {
            return sprite;
        }

        var center = size / 2;
        var gradient = ctx.createRadialGradient(
            center,
            center,
            0,
            center,
            center,
            center
        );

        gradient.addColorStop(0, 'rgba(255,255,255,1)');
        gradient.addColorStop(0.10, 'rgba(255,255,255,0.96)');
        gradient.addColorStop(0.30, 'rgba(247,249,251,0.48)');
        gradient.addColorStop(0.58, 'rgba(238,241,244,0.13)');
        gradient.addColorStop(1, 'rgba(235,238,241,0)');

        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, size, size);

        return sprite;
    }

    function createParticle(width, height, fromEdge) {
        var depth = Math.pow(Math.random(), 0.72);
        var angle = random(0, TAU);
        var speed = random(10, 24) + (depth * random(28, 58));
        var sizeRoll = Math.random();
        var radius;

        // Most dust stays tiny. A few closer particles are a little larger/softer.
        if (sizeRoll > 0.965) {
            radius = random(2.1, 3.2);
        } else if (sizeRoll > 0.76) {
            radius = random(1.05, 1.9);
        } else {
            radius = random(0.42, 1.08);
        }

        radius *= 0.72 + (depth * 0.62);

        var x = random(0, width);
        var y = random(0, height);

        if (fromEdge) {
            var edge = Math.floor(Math.random() * 4);
            var margin = 28;

            if (edge === 0) {
                x = -margin;
            } else if (edge === 1) {
                x = width + margin;
            } else if (edge === 2) {
                y = -margin;
            } else {
                y = height + margin;
            }
        }

        return {
            x: x,
            y: y,
            vx: Math.cos(angle) * speed,
            vy: Math.sin(angle) * speed,
            angle: angle,
            speed: speed,
            depth: depth,
            radius: radius,
            alpha: random(0.13, 0.34) + (depth * random(0.12, 0.28)),
            phaseX: random(0, TAU),
            phaseY: random(0, TAU),
            wobbleX: random(0.38, 1.20),
            wobbleY: random(0.34, 1.05),
            turn: random(-0.11, 0.11),
            pulse: random(0.5, 1.3),
            life: random(0, 8),
            maxLife: random(7.5, 16)
        };
    }

    function mountBackground(section) {
        if (!section || section.dataset[READY_KEY] === '1') {
            return;
        }

        section.classList.add('clsc-code-background');

        var canvas = document.createElement('canvas');
        var context = canvas.getContext('2d', {
            alpha: false,
            desynchronized: true
        });

        if (!context) {
            return;
        }

        section.dataset[READY_KEY] = '1';

        var sprite = createDustSprite();
        var isMobile = window.matchMedia &&
            window.matchMedia('(max-width: 767px)').matches;
        var reduceMotion = window.matchMedia &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        canvas.className = 'clsc-code-background-canvas';
        canvas.setAttribute('aria-hidden', 'true');
        section.insertBefore(canvas, section.firstChild);

        var width = 1;
        var height = 1;
        var dpr = 1;
        var particles = [];
        var animationId = 0;
        var isVisible = true;
        var lastFrame = 0;
        var lastStep = 0;

        var targetFps = reduceMotion ? REDUCED_FPS : (isMobile ? MOBILE_FPS : DESKTOP_FPS);
        var frameInterval = 1000 / targetFps;
        var motionFactor = reduceMotion ? 0 : 1;

        function particleCount() {
            var area = width * height;
            var base = Math.round(area / (isMobile ? 10500 : 9000));
            var min = isMobile ? 34 : 48;
            var max = isMobile ? 74 : 132;

            return clamp(base, min, max);
        }

        function buildParticles() {
            var count = particleCount();
            particles = new Array(count);

            for (var i = 0; i < count; i += 1) {
                particles[i] = createParticle(width, height, false);
            }
        }

        function resize() {
            var rect = section.getBoundingClientRect();
            var nextWidth = Math.max(1, Math.round(rect.width));
            var nextHeight = Math.max(1, Math.round(rect.height));

            if (nextWidth === width && nextHeight === height) {
                return;
            }

            width = nextWidth;
            height = nextHeight;
            dpr = Math.min(
                window.devicePixelRatio || 1,
                isMobile ? MOBILE_MAX_DPR : DESKTOP_MAX_DPR
            );

            canvas.width = Math.max(1, Math.round(width * dpr));
            canvas.height = Math.max(1, Math.round(height * dpr));
            canvas.style.width = width + 'px';
            canvas.style.height = height + 'px';
            context.setTransform(dpr, 0, 0, dpr, 0, 0);
            context.imageSmoothingEnabled = true;

            buildParticles();
            draw(performance.now(), 0);
        }

        function recycle(particle) {
            var fresh = createParticle(width, height, true);
            var keys = Object.keys(fresh);

            for (var i = 0; i < keys.length; i += 1) {
                particle[keys[i]] = fresh[keys[i]];
            }
        }

        function updateParticle(particle, elapsed, nowSeconds) {
            particle.life += elapsed;
            particle.angle += particle.turn * elapsed;

            // A small local flow field keeps the dust organic: every speck has its
            // own direction, while the overall motion still feels coherent.
            var flowX = Math.sin(
                (particle.y * 0.012) + (nowSeconds * 0.72) + particle.phaseX
            ) * (5 + (particle.depth * 7));
            var flowY = Math.cos(
                (particle.x * 0.010) - (nowSeconds * 0.58) + particle.phaseY
            ) * (4 + (particle.depth * 6));

            var directionalX = Math.cos(particle.angle) * particle.speed;
            var directionalY = Math.sin(particle.angle) * particle.speed;
            var microX = Math.sin(
                (nowSeconds * particle.wobbleX * 2.0) + particle.phaseX
            ) * (2.2 + (particle.depth * 4.8));
            var microY = Math.cos(
                (nowSeconds * particle.wobbleY * 1.8) + particle.phaseY
            ) * (2.0 + (particle.depth * 4.2));

            particle.x += (
                directionalX + flowX + microX
            ) * elapsed * motionFactor;
            particle.y += (
                directionalY + flowY + microY
            ) * elapsed * motionFactor;

            var margin = 42;
            var outOfBounds =
                particle.x < -margin ||
                particle.x > width + margin ||
                particle.y < -margin ||
                particle.y > height + margin;

            if (outOfBounds || particle.life > particle.maxLife) {
                recycle(particle);
            }
        }

        function drawParticle(particle, nowSeconds) {
            var fadeIn = clamp(particle.life / 0.9, 0, 1);
            var fadeOut = clamp((particle.maxLife - particle.life) / 1.4, 0, 1);
            var twinkle = 0.82 + (
                Math.sin((nowSeconds * particle.pulse * 2.2) + particle.phaseX) * 0.18
            );
            var alpha = clamp(
                particle.alpha * fadeIn * fadeOut * twinkle * PARTICLE_BRIGHTNESS,
                0,
                0.864
            );

            if (alpha <= 0.002) {
                return;
            }

            var diameter = Math.max(2.2, particle.radius * 9.0);

            context.globalAlpha = alpha;
            context.drawImage(
                sprite,
                particle.x - (diameter / 2),
                particle.y - (diameter / 2),
                diameter,
                diameter
            );
        }

        function draw(timestamp, deltaMs) {
            var elapsed = Math.min(deltaMs, 64) / 1000;
            var nowSeconds = timestamp / 1000;

            // Opaque fill guarantees a perfectly uniform black background.
            context.globalAlpha = 1;
            context.fillStyle = BACKGROUND;
            context.fillRect(0, 0, width, height);

            for (var i = 0; i < particles.length; i += 1) {
                if (elapsed > 0) {
                    updateParticle(particles[i], elapsed, nowSeconds);
                }
                drawParticle(particles[i], nowSeconds);
            }

            context.globalAlpha = 1;
        }

        function tick(timestamp) {
            animationId = window.requestAnimationFrame(tick);

            if (!isVisible || document.hidden) {
                lastStep = timestamp;
                return;
            }

            if (!lastStep) {
                lastStep = timestamp;
            }

            if ((timestamp - lastFrame) < frameInterval) {
                return;
            }

            var deltaMs = timestamp - lastStep;
            lastStep = timestamp;
            lastFrame = timestamp;
            draw(timestamp, deltaMs);
        }

        function start() {
            if (!animationId) {
                lastStep = performance.now();
                animationId = window.requestAnimationFrame(tick);
            }
        }

        function stop() {
            if (animationId) {
                window.cancelAnimationFrame(animationId);
                animationId = 0;
            }
        }

        if ('IntersectionObserver' in window) {
            var visibilityObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.target === section) {
                        isVisible = entry.isIntersecting;
                        if (isVisible && !reduceMotion) {
                            start();
                        } else if (!isVisible) {
                            stop();
                        }
                    }
                });
            }, { rootMargin: '160px 0px' });

            visibilityObserver.observe(section);
        }

        if ('ResizeObserver' in window) {
            var resizeObserver = new ResizeObserver(resize);
            resizeObserver.observe(section);
        } else {
            var resizeTimer = 0;
            window.addEventListener('resize', function () {
                window.clearTimeout(resizeTimer);
                resizeTimer = window.setTimeout(resize, 120);
            }, { passive: true });
        }

        document.addEventListener('visibilitychange', function () {
            if (document.hidden || reduceMotion) {
                stop();
            } else if (isVisible) {
                start();
            }
        });

        resize();
        if (!reduceMotion) {
            start();
        }
    }

    // Resolve the real Services section for a plugin-rendered `.clsc` wrapper:
    // climb to the nearest enclosing Elementor section/container so the
    // background covers the whole visual section (including Elementor's own
    // padding), not just the card markup's own box. If the shortcode isn't
    // placed inside an Elementor section (e.g. a plain page/block), the
    // `.clsc` wrapper itself is a safe, self-contained fallback target.
    function resolveServiceSection(clscRoot) {
        if (!clscRoot || typeof clscRoot.closest !== 'function') {
            return clscRoot;
        }

        var ancestor = clscRoot.closest(SECTION_ANCESTOR_SELECTOR);
        return ancestor || clscRoot;
    }

    var HEADING_TEXT_TAGS = { P: 1, H1: 1, H2: 1, H3: 1, H4: 1, H5: 1, H6: 1, SPAN: 1, DIV: 1 };

    // Relocate the detected heading to be a direct child of the resolved
    // section (the same element `mountBackground` paints black and sizes to
    // the full section width). Its original position is usually inside an
    // Elementor widget/column wrapper whose own rendered width depends on
    // page-specific container settings outside the plugin's control (e.g. a
    // narrower "Content Width" at one breakpoint) — moving the heading out
    // of that wrapper, rather than trying to anchor/size it relative to
    // whatever that wrapper turns out to be, is what makes centering and
    // single-line sizing reliable regardless of the host page's own layout.
    function relocateHeading(section, heading) {
        if (!section || !heading || section.contains(heading) && heading.parentElement === section) {
            return;
        }

        section.insertBefore(heading, section.firstChild);
    }

    // Most direct, most reliable case: Elementor's Text Editor widget often
    // holds both the "Onze Services" copy and the `[codeline_services_cards]`
    // shortcode in one widget, so the heading renders as a plain sibling
    // element (usually a <p>, not a `.elementor-heading-title`) immediately
    // before `.clsc` in the DOM. Checked before the ancestor/section
    // heuristics below, which only recognize real Heading widgets.
    function markPrecedingSibling(clscRoot, section) {
        var sibling = clscRoot.previousElementSibling;

        while (sibling) {
            if (HEADING_TEXT_TAGS[sibling.tagName] && (sibling.textContent || '').trim() !== '') {
                sibling.classList.add('clsc-services-heading');
                relocateHeading(section, sibling);
                return true;
            }

            // Skip past empty/whitespace-only elements to find real content.
            if ((sibling.textContent || '').trim() !== '') {
                return false;
            }

            sibling = sibling.previousElementSibling;
        }

        return false;
    }

    function markServicesHeading(clscRoot) {
        if (!clscRoot) {
            return;
        }

        var section = resolveServiceSection(clscRoot);

        if (markPrecedingSibling(clscRoot, section)) {
            return;
        }

        function markIn(scope) {
            if (!scope || clscRoot.contains(scope)) {
                return false;
            }

            var heading = scope.matches && scope.matches('.elementor-heading-title')
                ? scope
                : scope.querySelector && scope.querySelector('.elementor-heading-title');

            if (heading && !clscRoot.contains(heading)) {
                heading.classList.add('clsc-services-heading');
                relocateHeading(section, heading);

                var textWidget = null;
                if (scope.querySelector) {
                    textWidget = scope.querySelector('.elementor-widget-text-editor, .elementor-widget-theme-post-content, .elementor-widget-text-editor p, .elementor-widget-theme-post-content p');
                }
                if (textWidget && !clscRoot.contains(textWidget)) {
                    textWidget.classList.add('clsc-services-copy');
                }
                return true;
            }

            return false;
        }

        if (markIn(section)) {
            return;
        }

        var current = clscRoot.parentElement;
        var depth = 0;

        while (current && depth < 8) {
            if (markIn(current)) {
                return;
            }

            var sibling = current.previousElementSibling;
            while (sibling) {
                if (markIn(sibling)) {
                    return;
                }
                sibling = sibling.previousElementSibling;
            }

            current = current.parentElement;
            depth += 1;
        }
    }

    // Auto-detection never scans the whole document for arbitrary sections:
    // it only ever starts from elements the plugin itself rendered
    // (`.clsc`) or that were explicitly opted in (the legacy manual class),
    // so it can never attach to the Hero or any unrelated section.
    function collectTargets() {
        var targets = [];
        var seen = [];

        function addTarget(el) {
            if (!el || seen.indexOf(el) !== -1) {
                return;
            }
            seen.push(el);
            targets.push(el);
        }

        // Backwards compatibility: a manually-added Elementor class still works.
        document.querySelectorAll('.codeline-services-background').forEach(addTarget);

        // Primary path: auto-detect from the plugin's own Services markup.
        document.querySelectorAll('.clsc').forEach(function (root) {
            markServicesHeading(root);
            addTarget(resolveServiceSection(root));
        });

        return targets;
    }

    function init() {
        var targets = collectTargets();

        if (!targets.length) {
            // Fail safely: no plugin-rendered Services wrapper found on this page.
            return;
        }

        targets.forEach(mountBackground);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }

    // Elementor can render/populate sections slightly after DOMContentLoaded
    // in some setups; a single late safety pass keeps init idempotent
    // (mountBackground no-ops on already-mounted targets) without resorting
    // to a MutationObserver or polling loop.
    window.addEventListener('load', init, { once: true });
}());
