<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'fa' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ __('app.welcome.title') }} - {{ config('app.name', 'TMAS') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=outfit:500,600,700,800|source-sans-3:400,500,600" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            :root {
                --tmas-ink: #0B1220;
                --tmas-panel: #121A2B;
                --tmas-mist: #E8EEF7;
                --tmas-accent: #14B8A6;
                --tmas-accent-soft: #2DD4BF;
                --tmas-signal: #38BDF8;
                --tmas-ink-muted: rgba(11, 18, 32, 0.68);
                --tmas-font-display: 'Outfit', ui-sans-serif, sans-serif;
                --tmas-font-body: 'Source Sans 3', ui-sans-serif, sans-serif;
            }

            .tmas-welcome {
                font-family: var(--tmas-font-body);
                color: var(--tmas-ink);
                background: var(--tmas-mist);
            }

            .tmas-welcome .font-display {
                font-family: var(--tmas-font-display);
            }

            .tmas-hero {
                position: relative;
                min-height: 100svh;
                display: flex;
                flex-direction: column;
                overflow: hidden;
                isolation: isolate;
            }

            .tmas-hero__visual {
                position: absolute;
                inset: 0;
                z-index: 0;
            }

            .tmas-hero__visual img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                object-position: center;
            }

            .tmas-hero__veil {
                position: absolute;
                inset: 0;
                background:
                    linear-gradient(160deg, rgba(232, 238, 247, 0.92) 0%, rgba(232, 238, 247, 0.78) 42%, rgba(18, 26, 43, 0.28) 100%),
                    radial-gradient(ellipse 70% 55% at 75% 40%, rgba(20, 184, 166, 0.18), transparent 60%);
            }

            .tmas-hero__mesh {
                position: absolute;
                inset: -10%;
                opacity: 0.55;
                background:
                    radial-gradient(circle at 20% 30%, rgba(56, 189, 248, 0.22), transparent 28%),
                    radial-gradient(circle at 70% 55%, rgba(20, 184, 166, 0.2), transparent 32%),
                    radial-gradient(circle at 45% 80%, rgba(45, 212, 191, 0.12), transparent 35%);
                animation: tmas-mesh-drift 18s ease-in-out infinite alternate;
                pointer-events: none;
            }

            .tmas-hero__signal {
                position: absolute;
                inset: 0;
                pointer-events: none;
                opacity: 0.35;
                background-image:
                    linear-gradient(115deg, transparent 40%, rgba(56, 189, 248, 0.35) 50%, transparent 60%),
                    linear-gradient(65deg, transparent 45%, rgba(20, 184, 166, 0.28) 55%, transparent 65%);
                background-size: 220% 220%;
                animation: tmas-signal-pulse 10s ease-in-out infinite;
            }

            .tmas-hero__content {
                position: relative;
                z-index: 1;
                display: flex;
                flex-direction: column;
                flex: 1;
                width: 100%;
                max-width: 72rem;
                margin-inline: auto;
                padding: 1.25rem 1.5rem 3rem;
            }

            @media (min-width: 768px) {
                .tmas-hero__content {
                    padding: 1.75rem 2.5rem 4rem;
                }
            }

            .tmas-nav {
                display: flex;
                justify-content: flex-end;
                margin-bottom: auto;
            }

            .tmas-nav a {
                font-size: 0.875rem;
                font-weight: 500;
                color: var(--tmas-ink-muted);
                text-decoration: none;
                padding: 0.4rem 0.75rem;
                border-radius: 0.5rem;
                transition: color 0.2s ease, background-color 0.2s ease;
            }

            .tmas-nav a:hover,
            .tmas-nav a:focus-visible {
                color: var(--tmas-ink);
                background: rgba(255, 255, 255, 0.45);
                outline: none;
            }

            .tmas-hero__body {
                display: flex;
                flex-direction: column;
                justify-content: center;
                gap: 1.75rem;
                padding-block: 3rem 2rem;
                max-width: 40rem;
            }

            .tmas-brand {
                display: inline-flex;
                align-items: center;
                gap: 0.85rem;
                text-decoration: none;
                color: var(--tmas-ink);
            }

            .tmas-brand__mark {
                width: 3.25rem;
                height: 3.25rem;
                flex-shrink: 0;
            }

            .tmas-brand__wordmark {
                font-family: var(--tmas-font-display);
                font-size: clamp(3.5rem, 12vw, 7.5rem);
                font-weight: 800;
                letter-spacing: -0.06em;
                line-height: 0.9;
            }

            .tmas-headline {
                font-family: var(--tmas-font-display);
                font-size: clamp(1.35rem, 3.2vw, 1.85rem);
                font-weight: 600;
                letter-spacing: -0.03em;
                line-height: 1.25;
                color: var(--tmas-ink);
                max-width: 22ch;
            }

            .tmas-supporting {
                font-size: 1.0625rem;
                line-height: 1.6;
                color: var(--tmas-ink-muted);
                max-width: 36ch;
            }

            .tmas-cta {
                display: flex;
                flex-wrap: wrap;
                gap: 0.75rem;
                padding-top: 0.25rem;
            }

            .tmas-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 2.75rem;
                padding: 0.65rem 1.35rem;
                border-radius: 0.65rem;
                font-family: var(--tmas-font-display);
                font-size: 0.95rem;
                font-weight: 600;
                letter-spacing: -0.01em;
                text-decoration: none;
                transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease, border-color 0.2s ease;
            }

            .tmas-btn:focus-visible {
                outline: 2px solid var(--tmas-accent);
                outline-offset: 3px;
            }

            .tmas-btn--primary {
                background: var(--tmas-ink);
                color: #fff;
                box-shadow: 0 10px 24px -12px rgba(11, 18, 32, 0.55);
            }

            .tmas-btn--primary:hover {
                background: var(--tmas-panel);
                transform: translateY(-2px);
                box-shadow: 0 16px 28px -14px rgba(11, 18, 32, 0.6);
            }

            .tmas-btn--ghost {
                background: rgba(255, 255, 255, 0.55);
                color: var(--tmas-ink);
                border: 1px solid rgba(11, 18, 32, 0.12);
                backdrop-filter: blur(8px);
            }

            .tmas-btn--ghost:hover {
                border-color: rgba(20, 184, 166, 0.55);
                background: rgba(255, 255, 255, 0.8);
            }

            .tmas-capabilities {
                position: relative;
                z-index: 1;
                background: var(--tmas-panel);
                color: var(--tmas-mist);
                padding: 3rem 1.5rem 3.5rem;
            }

            .tmas-capabilities__inner {
                max-width: 72rem;
                margin-inline: auto;
                display: grid;
                gap: 1.75rem;
            }

            @media (min-width: 768px) {
                .tmas-capabilities__inner {
                    grid-template-columns: repeat(3, 1fr);
                    gap: 2.5rem;
                    padding-inline: 1rem;
                }
            }

            .tmas-capabilities p {
                font-size: 1rem;
                line-height: 1.55;
                color: rgba(232, 238, 247, 0.82);
                max-width: 28ch;
                border-inline-start: 2px solid var(--tmas-accent);
                padding-inline-start: 1rem;
            }

            .tmas-reveal {
                opacity: 0;
                transform: translateY(1rem);
                animation: tmas-rise 0.85s cubic-bezier(0.22, 1, 0.36, 1) forwards;
            }

            .tmas-reveal--1 { animation-delay: 0.05s; }
            .tmas-reveal--2 { animation-delay: 0.18s; }
            .tmas-reveal--3 { animation-delay: 0.3s; }
            .tmas-reveal--4 { animation-delay: 0.42s; }

            @keyframes tmas-rise {
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            @keyframes tmas-mesh-drift {
                from { transform: translate3d(-1.5%, -1%, 0) scale(1); }
                to { transform: translate3d(2%, 1.5%, 0) scale(1.04); }
            }

            @keyframes tmas-signal-pulse {
                0%, 100% { background-position: 0% 40%, 100% 60%; opacity: 0.25; }
                50% { background-position: 80% 50%, 20% 40%; opacity: 0.45; }
            }

            @media (prefers-reduced-motion: reduce) {
                .tmas-reveal,
                .tmas-hero__mesh,
                .tmas-hero__signal,
                .tmas-btn--primary {
                    animation: none !important;
                    transition: none !important;
                    opacity: 1;
                    transform: none;
                }
            }
        </style>
    </head>
    <body class="tmas-welcome antialiased">
        <section class="tmas-hero" aria-label="{{ __('app.welcome.brand_full') }}">
            <div class="tmas-hero__visual" aria-hidden="true">
                <img
                    src="{{ asset('images/welcome/tmas-hero-mesh.jpg') }}"
                    alt=""
                    width="1920"
                    height="1080"
                    fetchpriority="high"
                >
                <div class="tmas-hero__veil"></div>
                <div class="tmas-hero__mesh"></div>
                <div class="tmas-hero__signal"></div>
            </div>

            <div class="tmas-hero__content">
                @if (Route::has('login'))
                    <header class="tmas-nav">
                        <nav class="flex items-center gap-1" aria-label="{{ __('app.welcome.title') }}">
                            @auth
                                <a href="{{ route('dashboard') }}">{{ __('app.welcome.dashboard') }}</a>
                            @else
                                <a href="{{ route('login') }}">{{ __('app.welcome.login') }}</a>
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}">{{ __('app.welcome.register') }}</a>
                                @endif
                            @endauth
                        </nav>
                    </header>
                @endif

                <div class="tmas-hero__body">
                    <div class="tmas-brand tmas-reveal tmas-reveal--1" aria-label="{{ __('app.welcome.brand') }}">
                        <svg class="tmas-brand__mark" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <rect x="1.5" y="1.5" width="45" height="45" rx="12" fill="var(--tmas-ink)"/>
                            <circle cx="15" cy="24" r="3.25" fill="var(--tmas-accent)"/>
                            <circle cx="24" cy="15.5" r="3.25" fill="var(--tmas-accent-soft)"/>
                            <circle cx="33" cy="28.5" r="3.25" fill="var(--tmas-signal)"/>
                            <path d="M17.5 22.5L22 17.5M26.5 17.5L31 26.5M17.8 25.5L30.2 28" stroke="rgba(232,238,247,0.55)" stroke-width="1.75" stroke-linecap="round"/>
                        </svg>
                        <span class="tmas-brand__wordmark">{{ __('app.welcome.brand') }}</span>
                    </div>

                    <h1 class="tmas-headline tmas-reveal tmas-reveal--2">
                        {{ __('app.welcome.headline') }}
                    </h1>

                    <p class="tmas-supporting tmas-reveal tmas-reveal--3">
                        {{ __('app.welcome.supporting') }}
                    </p>

                    @if (Route::has('login'))
                        <div class="tmas-cta tmas-reveal tmas-reveal--4">
                            @auth
                                <a href="{{ route('dashboard') }}" class="tmas-btn tmas-btn--primary">
                                    {{ __('app.welcome.dashboard') }}
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="tmas-btn tmas-btn--primary">
                                    {{ __('app.welcome.login') }}
                                </a>
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="tmas-btn tmas-btn--ghost">
                                        {{ __('app.welcome.register') }}
                                    </a>
                                @endif
                            @endauth
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <section class="tmas-capabilities" aria-label="{{ __('app.welcome.brand_full') }}">
            <div class="tmas-capabilities__inner">
                <p>{{ __('app.welcome.capability_routing') }}</p>
                <p>{{ __('app.welcome.capability_coverage') }}</p>
                <p>{{ __('app.welcome.capability_attendance') }}</p>
            </div>
        </section>
    </body>
</html>
