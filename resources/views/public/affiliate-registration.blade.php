<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Affiliate Registration</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <main class="flex min-h-screen items-center justify-center px-4 py-12">
        <section class="w-full max-w-xl rounded-xl bg-white p-8 text-center shadow-lg ring-1 ring-slate-200 sm:p-10">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-xl bg-emerald-700 text-lg font-black text-white">TT</div>
            <p class="mt-5 text-xs font-bold uppercase tracking-wide text-emerald-700">TikTok Affiliate Commission System</p>
            <h1 class="mt-2 text-3xl font-black text-slate-950">Affiliate Registration</h1>

            @if ($isValid)
                <p class="mt-5 text-lg font-semibold text-slate-700">You were invited by {{ $referrerName }}.</p>
                <p class="mt-3 text-slate-500">Online registration will be available soon.</p>
            @else
                <div class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-4 text-sm font-semibold text-red-700">
                    This referral link is invalid or no longer active.
                </div>
            @endif
        </section>
    </main>
</body>
</html>
