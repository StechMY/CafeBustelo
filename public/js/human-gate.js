(function () {
    'use strict';

    const startedAt = Date.now();

    const behavior = {
        mouseMoves: 0,
        touchEvents: 0,
        keyEvents: 0,
        scrollEvents: 0,
        clicks: 0,
        firstInteractionMs: null,
        dwellMs: 0
    };

    const visibility = {
        wasHidden: false,
        hiddenCount: 0
    };

    function markInteraction() {
        if (behavior.firstInteractionMs === null) {
            behavior.firstInteractionMs = Date.now() - startedAt;
        }
    }

    document.addEventListener('mousemove', function () {
        behavior.mouseMoves++;
        markInteraction();
    }, { passive: true });

    document.addEventListener('touchstart', function () {
        behavior.touchEvents++;
        markInteraction();
    }, { passive: true });

    document.addEventListener('keydown', function () {
        behavior.keyEvents++;
        markInteraction();
    }, { passive: true });

    document.addEventListener('scroll', function () {
        behavior.scrollEvents++;
        markInteraction();
    }, { passive: true });

    document.addEventListener('click', function () {
        behavior.clicks++;
        markInteraction();
    }, { passive: true });

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            visibility.wasHidden = true;
            visibility.hiddenCount++;
        }
    });

    async function sha256(text) {
        try {
            const encoder = new TextEncoder();
            const data = encoder.encode(text);
            const hashBuffer = await crypto.subtle.digest('SHA-256', data);
            const hashArray = Array.from(new Uint8Array(hashBuffer));

            return hashArray
                .map(function (b) {
                    return b.toString(16).padStart(2, '0');
                })
                .join('');
        } catch (e) {
            return null;
        }
    }

    function getVisitorId() {
        try {
            let id = localStorage.getItem('company_contact_visitor_id');

            if (!id) {
                if (window.crypto && crypto.randomUUID) {
                    id = crypto.randomUUID();
                } else {
                    id = String(Date.now()) + '_' + Math.random().toString(16).slice(2);
                }

                localStorage.setItem('company_contact_visitor_id', id);
            }

            return id;
        } catch (e) {
            return String(Date.now()) + '_' + Math.random().toString(16).slice(2);
        }
    }

    function getCanvasPayload() {
        try {
            const canvas = document.createElement('canvas');

            canvas.width = 320;
            canvas.height = 90;

            const ctx = canvas.getContext('2d');

            ctx.textBaseline = 'alphabetic';
            ctx.fillStyle = '#f60';
            ctx.fillRect(125, 1, 62, 20);

            ctx.fillStyle = '#069';
            ctx.font = '16px Arial';
            ctx.fillText('Company Contact Check 123', 2, 20);

            ctx.fillStyle = 'rgba(102, 204, 0, 0.7)';
            ctx.font = '18px Times New Roman';
            ctx.fillText('Fingerprint ✓ Human', 4, 55);

            return canvas.toDataURL();
        } catch (e) {
            return null;
        }
    }

    function getWebGLInfo() {
        try {
            const canvas = document.createElement('canvas');
            const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');

            if (!gl) {
                return {
                    supported: false,
                    vendor: null,
                    renderer: null
                };
            }

            const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');

            return {
                supported: true,
                vendor: debugInfo ? gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL) : gl.getParameter(gl.VENDOR),
                renderer: debugInfo ? gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL) : gl.getParameter(gl.RENDERER),
                version: gl.getParameter(gl.VERSION),
                shadingLanguageVersion: gl.getParameter(gl.SHADING_LANGUAGE_VERSION),
                maxTextureSize: gl.getParameter(gl.MAX_TEXTURE_SIZE),
                maxViewportDims: gl.getParameter(gl.MAX_VIEWPORT_DIMS)
            };
        } catch (e) {
            return {
                supported: false,
                error: true
            };
        }
    }

    async function getAudioHash() {
        return new Promise(function (resolve) {
            try {
                const AudioContext = window.OfflineAudioContext || window.webkitOfflineAudioContext;

                if (!AudioContext) {
                    resolve(null);
                    return;
                }

                const context = new AudioContext(1, 44100, 44100);
                const oscillator = context.createOscillator();
                const compressor = context.createDynamicsCompressor();

                oscillator.type = 'triangle';
                oscillator.frequency.value = 10000;

                compressor.threshold.value = -50;
                compressor.knee.value = 40;
                compressor.ratio.value = 12;
                compressor.attack.value = 0;
                compressor.release.value = 0.25;

                oscillator.connect(compressor);
                compressor.connect(context.destination);
                oscillator.start(0);

                context.startRendering().then(async function (buffer) {
                    let sum = 0;
                    const data = buffer.getChannelData(0);

                    for (let i = 4500; i < 5000; i++) {
                        sum += Math.abs(data[i]);
                    }

                    resolve(await sha256(String(sum)));
                }).catch(function () {
                    resolve(null);
                });
            } catch (e) {
                resolve(null);
            }
        });
    }

    function getStorageInfo() {
        const out = {
            cookieEnabled: navigator.cookieEnabled,
            localStorage: false,
            sessionStorage: false,
            indexedDB: !!window.indexedDB
        };

        try {
            localStorage.setItem('__company_contact_test', '1');
            localStorage.removeItem('__company_contact_test');
            out.localStorage = true;
        } catch (e) {}

        try {
            sessionStorage.setItem('__company_contact_test', '1');
            sessionStorage.removeItem('__company_contact_test');
            out.sessionStorage = true;
        } catch (e) {}

        return out;
    }

    function getPluginsInfo() {
        try {
            const names = [];

            for (let i = 0; i < navigator.plugins.length; i++) {
                names.push(navigator.plugins[i].name);
            }

            return {
                count: names.length,
                names: names.slice(0, 20)
            };
        } catch (e) {
            return {
                count: 0,
                names: []
            };
        }
    }

    function getTouchPointerInfo() {
        return {
            maxTouchPoints: navigator.maxTouchPoints || 0,
            touchEvent: 'ontouchstart' in window,
            pointerEvent: 'PointerEvent' in window,
            coarsePointer: window.matchMedia ? window.matchMedia('(pointer: coarse)').matches : null,
            finePointer: window.matchMedia ? window.matchMedia('(pointer: fine)').matches : null,
            hover: window.matchMedia ? window.matchMedia('(hover: hover)').matches : null
        };
    }

    function getAutomationInfo() {
        const ua = navigator.userAgent || '';
        const isChrome = /Chrome|Chromium|Edg/i.test(ua);

        const out = {
            webdriver: navigator.webdriver === true,
            chromeRuntimeMissingOnChrome: false,
            playwright: false,
            selenium: false,
            phantom: false,
            suspiciousGlobals: []
        };

        if (isChrome && typeof window.chrome === 'undefined') {
            out.chromeRuntimeMissingOnChrome = true;
        }

        const checks = [
            '__playwright',
            '__pw_manual',
            '__nightmare',
            '_phantom',
            'callPhantom',
            '_selenium',
            'selenium',
            'webdriver',
            'domAutomation',
            'domAutomationController'
        ];

        for (const key of checks) {
            try {
                if (window[key] !== undefined) {
                    out.suspiciousGlobals.push(key);
                }
            } catch (e) {}
        }

        out.playwright = out.suspiciousGlobals.some(function (key) {
            return key.indexOf('playwright') >= 0 || key.indexOf('__pw') >= 0;
        });

        out.selenium = out.suspiciousGlobals.some(function (key) {
            return key.indexOf('selenium') >= 0 || key.indexOf('webdriver') >= 0;
        });

        out.phantom = out.suspiciousGlobals.some(function (key) {
            return key.indexOf('phantom') >= 0;
        });

        return out;
    }

    function getMediaCodecInfo() {
        const video = document.createElement('video');
        const audio = document.createElement('audio');

        return {
            video: {
                mp4: video.canPlayType('video/mp4; codecs="avc1.42E01E"'),
                webm: video.canPlayType('video/webm; codecs="vp8, vorbis"'),
                ogg: video.canPlayType('video/ogg; codecs="theora"')
            },
            audio: {
                mp3: audio.canPlayType('audio/mpeg'),
                wav: audio.canPlayType('audio/wav; codecs="1"'),
                ogg: audio.canPlayType('audio/ogg; codecs="vorbis"')
            }
        };
    }

    async function collectSignals() {
        const canvasPayload = getCanvasPayload();
        const canvasHash = canvasPayload ? await sha256(canvasPayload) : null;

        const signals = {
            timestamp: Date.now(),

            screen: {
                width: screen.width,
                height: screen.height,
                availWidth: screen.availWidth,
                availHeight: screen.availHeight,
                colorDepth: screen.colorDepth,
                pixelDepth: screen.pixelDepth,
                pixelRatio: window.devicePixelRatio || 1
            },

            navigator: {
                userAgent: navigator.userAgent,
                language: navigator.language,
                languages: navigator.languages || [],
                platform: navigator.platform,
                vendor: navigator.vendor,
                hardwareConcurrency: navigator.hardwareConcurrency || null,
                deviceMemory: navigator.deviceMemory || null,
                doNotTrack: navigator.doNotTrack || null
            },

            timezone: {
                name: Intl.DateTimeFormat().resolvedOptions().timeZone || null,
                offset: new Date().getTimezoneOffset()
            },

            canvas: {
                hash: canvasHash
            },

            webgl: getWebGLInfo(),

            audio: {
                hash: await getAudioHash()
            },

            plugins: getPluginsInfo(),

            storage: getStorageInfo(),

            automation: getAutomationInfo(),

            mediaCodec: getMediaCodecInfo(),

            touchPointer: getTouchPointerInfo(),

            visibility: visibility,

            behavior: behavior
        };

        signals.behavior.dwellMs = Date.now() - startedAt;

        const fpBase = JSON.stringify({
            screen: signals.screen,
            navigator: {
                language: signals.navigator.language,
                languages: signals.navigator.languages,
                platform: signals.navigator.platform,
                vendor: signals.navigator.vendor,
                hardwareConcurrency: signals.navigator.hardwareConcurrency,
                deviceMemory: signals.navigator.deviceMemory
            },
            timezone: signals.timezone,
            canvas: signals.canvas,
            webgl: signals.webgl,
            audio: signals.audio,
            plugins: signals.plugins,
            touchPointer: signals.touchPointer
        });

        return {
            fingerprint_hash: await sha256(fpBase),
            signals: signals
        };
    }

    async function verify() {
        const btn = document.getElementById('verifyBtn');
        const status = document.getElementById('status');

        btn.disabled = true;
        status.textContent = 'Verifying browser...';

        try {
            const elapsed = Date.now() - startedAt;
            const minWait = 1200;

            if (elapsed < minWait) {
                await new Promise(function (resolve) {
                    setTimeout(resolve, minWait - elapsed);
                });
            }

            const payload = await collectSignals();

            const response = await fetch(window.HUMAN_GATE.verifyUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': window.HUMAN_GATE.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    visitor_id: getVisitorId(),
                    fingerprint_hash: payload.fingerprint_hash,
                    signals: payload.signals
                })
            });

            if (!response.ok) {
                window.location.href = '/404';
                return;
            }

            const data = await response.json();

            if (!data.ok || !data.redirect) {
                window.location.href = '/404';
                return;
            }

            window.location.href = data.redirect;
        } catch (e) {
            window.location.href = '/404';
        }
    }

    document.getElementById('verifyBtn').addEventListener('click', verify);
})();