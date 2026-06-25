<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Application Submitted</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <main class="flex min-h-screen items-center justify-center px-4 py-12">
        <section class="w-full max-w-xl rounded-xl bg-white p-8 shadow-lg ring-1 ring-slate-200 sm:p-10">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-2xl font-black text-emerald-700">&#10003;</div>
            <h1 class="mt-5 text-center text-2xl font-black text-slate-950">Application submitted successfully.</h1>
            <p class="mt-2 text-center text-slate-500">The administrator will review your application.</p>

            <dl class="mt-7 grid gap-4 rounded-lg bg-slate-50 p-5 text-sm sm:grid-cols-2">
                <div><dt class="font-bold text-slate-500">Application Reference</dt><dd class="mt-1 font-mono font-black text-slate-950">{{ $application->application_reference }}</dd></div>
                <div><dt class="font-bold text-slate-500">Current Status</dt><dd class="mt-1"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-black {{ $application->status === 'duplicate_review' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">{{ str($application->status)->headline() }}</span></dd></div>
                <div><dt class="font-bold text-slate-500">Invited By</dt><dd class="mt-1 font-semibold text-slate-950">{{ $application->referrer?->name ?? '-' }}</dd></div>
                <div><dt class="font-bold text-slate-500">Submission Date</dt><dd class="mt-1 font-semibold text-slate-950">{{ $application->submitted_at?->format('d/m/Y H:i') }}</dd></div>
            </dl>

            @if ($application->status === 'duplicate_review')
                <p class="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
                    An application using similar information may already exist. Your submission has been sent for administrator review.
                </p>
            @endif
            <p class="mt-5 text-center text-xs text-slate-500">Keep your application reference for future communication.</p>
        </section>
    </main>
</body>
</html>
