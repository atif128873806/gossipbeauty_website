import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

/*
 | Gossips Beauty — public site behaviour.
 |
 | Progressive enhancements coordinate the editorial reveals with GSAP,
 | update the reading position, and give the service cards a subtle pointer tilt.
 |
 | Nothing here is required for the site to work. The `js` class on <html> is
 | added first, and every rule that depends on it lives under `.js`, so with
 | JavaScript off the page renders complete and still. Visitors who ask their
 | system for reduced motion get no movement at all.
 */
const root = document.documentElement;
const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
const coarsePointer = window.matchMedia('(pointer: coarse)');

root.classList.add('js');

if (reduced.matches) {
    root.classList.add('reduced-motion');
}

/* Scroll reveals --------------------------------------------------------- */
const revealables = gsap.utils.toArray('.reveal');

if (reduced.matches) {
    revealables.forEach((element) => element.classList.add('reveal-in'));
} else {
    const lightweight = coarsePointer.matches;

    revealables.forEach((element) => {
        const configuredDelay = Number.parseFloat(getComputedStyle(element).getPropertyValue('--reveal-delay')) || 0;

        gsap.fromTo(element,
            {
                autoAlpha: 0,
                y: lightweight ? 18 : 42,
                rotateX: lightweight ? 0 : 2.5,
                filter: lightweight ? 'blur(0px)' : 'blur(6px)',
                transformPerspective: 1100,
            },
            {
                autoAlpha: 1,
                y: 0,
                rotateX: 0,
                filter: 'blur(0px)',
                duration: lightweight ? 0.62 : 0.9,
                delay: Math.min(configuredDelay / 1000, 0.36),
                ease: 'power3.out',
                clearProps: 'filter,transform,visibility',
                onStart: () => element.classList.add('gsap-entering'),
                onComplete: () => element.classList.remove('gsap-entering'),
                scrollTrigger: {
                    trigger: element,
                    start: 'top 90%',
                    once: true,
                },
            },
        );
    });
}

/* Editorial openings: curtain-cut photos, copy reveal, and quiet scroll depth */
if (!reduced.matches) {
    const openings = gsap.utils.toArray('[data-editorial-opening]');
    const lightweight = coarsePointer.matches;

    openings.forEach((opening) => {
        const frames = gsap.utils.toArray('[data-editorial-frame]', opening);
        const copy = gsap.utils.toArray('[data-editorial-copy]', opening);
        const images = frames.map((frame) => frame.querySelector('img')).filter(Boolean);
        const studioOpening = opening.dataset.editorialMotion === 'studio';

        const timeline = gsap.timeline({
            defaults: { ease: 'power3.out' },
            scrollTrigger: {
                trigger: opening,
                start: 'top 78%',
                once: true,
            },
        });

        timeline.fromTo(frames,
            {
                autoAlpha: 0,
                y: lightweight ? 18 : 34,
                rotation: studioOpening && !lightweight ? 1.2 : 0,
                clipPath: 'inset(0 0 100% 0 round 12px)',
            },
            {
                autoAlpha: 1,
                y: 0,
                rotation: 0,
                clipPath: 'inset(0 0 0% 0 round 12px)',
                duration: lightweight ? 0.85 : 1.2,
                stagger: lightweight ? 0.1 : 0.18,
                clearProps: 'clipPath,transform,visibility',
            },
            0,
        ).fromTo(images,
            { scale: studioOpening ? (lightweight ? 1.08 : 1.2) : (lightweight ? 1.06 : 1.15) },
            { scale: 1, duration: lightweight ? 1.1 : 1.65, stagger: 0.14, ease: 'power2.out' },
            0.02,
        ).fromTo(copy,
            {
                autoAlpha: 0,
                y: lightweight ? 14 : 24,
                x: studioOpening && !lightweight ? -18 : 0,
                filter: lightweight ? 'blur(0px)' : 'blur(5px)',
            },
            {
                autoAlpha: 1,
                y: 0,
                x: 0,
                filter: 'blur(0px)',
                duration: lightweight ? 0.65 : 0.85,
                stagger: 0.1,
                ease: 'power3.out',
                clearProps: 'filter,transform,visibility',
            },
            0.28,
        );

        if (!lightweight) {
            images.forEach((image, index) => {
                gsap.to(image, {
                    yPercent: index === 0 ? (studioOpening ? -7 : -5) : (studioOpening ? 6 : 4),
                    ease: 'none',
                    scrollTrigger: {
                        trigger: opening,
                        start: 'top top',
                        end: 'bottom top',
                        scrub: 0.8,
                    },
                });
            });
        }
    });
}

/* Work lookbook: stagger each photo's masked arrival as it enters the viewport. */
if (!reduced.matches) {
    const galleries = gsap.utils.toArray('[data-work-gallery]');
    const lightweight = coarsePointer.matches;

    galleries.forEach((gallery) => {
        gsap.utils.toArray('[data-work-card]', gallery).forEach((card) => {
            const image = card.querySelector('[data-work-image] img');
            const details = card.querySelectorAll('.work-index, .work-category, .work-caption, .work-action');

            const reveal = gsap.timeline({
                defaults: { ease: 'power3.out' },
                scrollTrigger: {
                    trigger: card,
                    start: 'top 88%',
                    once: true,
                },
            });

            reveal.fromTo(card,
                {
                    autoAlpha: 0,
                    y: lightweight ? 14 : 24,
                    clipPath: 'inset(0 0 14% 0 round 14px)',
                },
                {
                    autoAlpha: 1,
                    y: 0,
                    clipPath: 'inset(0 0 0% 0 round 14px)',
                    duration: lightweight ? 0.68 : 0.88,
                    clearProps: 'clipPath,transform,visibility',
                },
            );

            if (image) {
                reveal.fromTo(image,
                    { autoAlpha: 0.45 },
                    {
                        autoAlpha: 1,
                        duration: lightweight ? 0.55 : 0.7,
                    },
                    0.1,
                );
            }

            reveal.fromTo(details,
                { autoAlpha: 0, y: lightweight ? 8 : 12 },
                {
                    autoAlpha: 1,
                    y: 0,
                    duration: lightweight ? 0.42 : 0.56,
                    stagger: 0.045,
                    clearProps: 'transform,visibility',
                },
                0.2,
            );
        });
    });
}

/* Header, reading position and image depth -------------------------------- */
const header = document.querySelector('[data-site-header]');
const progress = document.querySelector('[data-scroll-progress]');
const depthLayers = [...document.querySelectorAll('[data-depth="3"]')];

/* Close the native mobile disclosure after route or in-page navigation. */
document.querySelectorAll('[data-menu]').forEach((menu) => {
    const summary = menu.querySelector('summary');

    menu.addEventListener('click', (event) => {
        if (event.target.closest('a')) {
            menu.open = false;
        }
    });

    menu.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && menu.open) {
            menu.open = false;
            summary?.focus();
        }
    });
});

let scrollFrame = null;
let settleTimer = null;
let depthSettleTimer = null;

const syncScroll = () => {
    scrollFrame = null;

    if (header) {
        header.classList.toggle('is-scrolled', window.scrollY > 8);
    }

    if (progress) {
        const available = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
        root.style.setProperty('--page-progress', String(Math.min(1, window.scrollY / available)));
    }

    if (!reduced.matches && !coarsePointer.matches) {
        depthLayers.forEach((layer) => {
            const distance = Math.max(-22, Math.min(22, window.scrollY * 0.035));
            layer.style.setProperty('--depth-y', `${distance}px`);
        });
    }
};

const requestScrollSync = () => {
    if (!scrollFrame) {
        scrollFrame = requestAnimationFrame(syncScroll);
    }

    if (progress) {
        progress.classList.add('is-moving');
        window.clearTimeout(settleTimer);
        settleTimer = window.setTimeout(() => progress.classList.remove('is-moving'), 180);
    }

    if (!reduced.matches && !coarsePointer.matches && depthLayers.length) {
        depthLayers.forEach((layer) => layer.classList.add('is-moving'));
        window.clearTimeout(depthSettleTimer);
        depthSettleTimer = window.setTimeout(() => {
            depthLayers.forEach((layer) => layer.classList.remove('is-moving'));
        }, 180);
    }
};

syncScroll();
window.addEventListener('scroll', requestScrollSync, { passive: true });
window.addEventListener('resize', requestScrollSync, { passive: true });

if (reduced.addEventListener) {
    reduced.addEventListener('change', requestScrollSync);
}

if (coarsePointer.addEventListener) {
    coarsePointer.addEventListener('change', requestScrollSync);
}

/* Salon choreography: small pointer tilt on service pieces ----------------- */
if (!reduced.matches && !coarsePointer.matches) {
    document.querySelectorAll('[data-tilt]').forEach((surface) => {
        let frame = null;

        surface.addEventListener('pointermove', (event) => {
            if (event.pointerType !== 'mouse' || frame) {
                return;
            }

            frame = requestAnimationFrame(() => {
                frame = null;
                const rect = surface.getBoundingClientRect();
                const x = Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width));
                const y = Math.max(0, Math.min(1, (event.clientY - rect.top) / rect.height));
                surface.style.setProperty('--tilt-x', `${((0.5 - y) * 3).toFixed(2)}deg`);
                surface.style.setProperty('--tilt-y', `${((x - 0.5) * 3).toFixed(2)}deg`);
                surface.classList.add('is-tilting');
            });
        }, { passive: true });

        surface.addEventListener('pointerleave', () => {
            surface.style.setProperty('--tilt-x', '0deg');
            surface.style.setProperty('--tilt-y', '0deg');
            surface.classList.remove('is-tilting');
        }, { passive: true });
    });
}

/* Short route curtain lets public pages feel like connected scenes. -------- */
if (!reduced.matches) {
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');

        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target) {
            return;
        }

        const destination = new URL(link.href, window.location.href);
        const samePageAnchor = destination.origin === window.location.origin
            && destination.pathname === window.location.pathname
            && destination.search === window.location.search
            && destination.hash;

        if (destination.origin !== window.location.origin || samePageAnchor || destination.href === window.location.href) {
            return;
        }

        event.preventDefault();
        document.body.classList.add('is-transitioning');
        window.setTimeout(() => window.location.assign(destination.href), 170);
    });
}

/* Mobile menu: close it after following a link inside it --------------- */
document.querySelectorAll('details[data-menu]').forEach((menu) => {
    menu.addEventListener('click', (event) => {
        if (event.target.closest('a')) {
            menu.removeAttribute('open');
        }
    });
});
