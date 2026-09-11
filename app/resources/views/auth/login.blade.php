<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SentinelAI Security Auditor</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        dark: {
                            900: '#07080b',
                            800: '#0e1017',
                            700: '#151824',
                            600: '#1d2130',
                            500: '#2c3247',
                        },
                        purple: {
                            DEFAULT: '#9333ea',
                            accent: '#a855f7',
                            glow: '#c084fc',
                            dark: '#581c87',
                        }
                    },
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    }
                }
            }
        }
    </script>
    <style>
        body { background-color: #07080b; color: #f8fafc; }
        .glow-purple { box-shadow: 0 0 35px -5px rgba(168, 85, 247, 0.3); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 bg-dark-900">

    <div class="w-full max-w-md">
        <!-- Logo & Header -->
        <div class="text-center mb-8">
            <div class="inline-flex w-14 h-14 rounded-2xl bg-gradient-to-tr from-purple-dark via-purple to-purple-accent items-center justify-center text-white shadow-2xl glow-purple mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            </div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">Sentinel<span class="text-purple-accent">AI</span></h1>
            <p class="text-sm text-slate-400 mt-1">AI-Powered Linux Server Security & Risk Auditor</p>
        </div>

        <!-- Login Card -->
        <div class="bg-dark-800 border border-dark-600/70 rounded-2xl p-8 shadow-2xl backdrop-blur-xl">
            <form action="{{ route('login.submit') }}" method="POST" class="space-y-5">
                @csrf

                @if ($errors->any())
                    <div class="p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div>
                    <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">Admin Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email', 'admin@sentinel.local') }}" required autofocus
                           class="w-full px-4 py-2.5 bg-dark-900 border border-dark-600 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-purple-accent focus:ring-1 focus:ring-purple-accent transition-all text-sm">
                </div>

                <div>
                    <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">Password</label>
                    <input type="password" id="password" name="password" value="SentinelAdmin2026!" required
                           class="w-full px-4 py-2.5 bg-dark-900 border border-dark-600 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-purple-accent focus:ring-1 focus:ring-purple-accent transition-all text-sm">
                </div>

                <div class="flex items-center justify-between text-xs text-slate-400">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="remember" checked class="rounded bg-dark-900 border-dark-600 text-purple focus:ring-0">
                        <span>Keep me logged in</span>
                    </label>
                </div>

                <button type="submit"
                        class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-purple to-purple-accent hover:from-purple-accent hover:to-purple text-white font-semibold text-sm shadow-lg shadow-purple/25 hover:shadow-purple/40 transition-all">
                    Sign in to Console
                </button>
            </form>

            <div class="mt-6 pt-6 border-t border-dark-600/50 text-center">
                <p class="text-xs text-slate-400">
                    Default credentials: <br>
                    <code class="font-mono text-purple-glow text-[11px] bg-dark-900 px-2 py-0.5 rounded mt-1 inline-block">admin@sentinel.local / SentinelAdmin2026!</code>
                </p>
            </div>
        </div>

        <div class="text-center mt-6 text-xs text-slate-500 font-mono">
            Read-Only Audit System &bull; Portainer Stack Ready
        </div>
    </div>

</body>
</html>
