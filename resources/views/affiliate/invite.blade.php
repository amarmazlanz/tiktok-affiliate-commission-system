@extends('layouts.auth')

@section('title', 'Invite Affiliate')

@section('content')
    <main class="min-h-screen bg-slate-100">
        <section class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6">
            <div>
                <p class="text-sm font-bold text-emerald-700">Referral Foundation</p>
                <h2 class="mt-1 text-2xl font-black text-slate-950">Invite Affiliate</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">
                    Share this link with a new applicant. Once the registration and approval modules are available,
                    approved applicants using this link will be assigned as your direct downline.
                </p>
            </div>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_280px]">
                <div class="app-card p-6 sm:p-7">
                    <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-sm font-bold text-slate-500">Referral owner</p>
                            <h3 class="mt-1 break-words text-xl font-black text-slate-950">{{ $affiliate->name }}</h3>
                            <p class="mt-1 font-mono text-sm font-semibold text-slate-500">{{ $affiliate->affiliate_code }}</p>
                        </div>
                        <span class="badge {{ $referralEnabled ? 'badge-green' : 'badge-gray' }}">
                            {{ $referralEnabled ? 'Active' : 'Disabled' }}
                        </span>
                    </div>

                    <div class="mt-6 space-y-5">
                        <div>
                            <label for="referral-code" class="stat-label">Referral Code</label>
                            <div class="mt-2 flex gap-2">
                                <input id="referral-code" type="text" readonly value="{{ $referral->referral_code }}" class="form-field mt-0 min-w-0 flex-1 bg-slate-50 font-mono font-black">
                                <button type="button" class="btn-secondary shrink-0" data-copy-value="{{ $referral->referral_code }}" data-copy-message="Referral code copied.">Copy Code</button>
                            </div>
                        </div>
                        <div>
                            <label for="referral-link" class="stat-label">Referral Link</label>
                            <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                                <input id="referral-link" type="text" readonly value="{{ $affiliate->referralUrl() }}" class="form-field mt-0 min-w-0 flex-1 bg-slate-50 text-sm">
                                <button type="button" class="btn-primary shrink-0" data-copy-value="{{ $affiliate->referralUrl() }}" data-copy-message="Referral link copied.">Copy Link</button>
                            </div>
                        </div>
                    </div>

                    @unless ($referralEnabled)
                        <p class="mt-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
                            This referral link is currently disabled and cannot be used for future registration.
                        </p>
                    @endunless
                </div>

                <aside class="app-card p-6">
                    <p class="stat-label">How It Will Work</p>
                    <ol class="mt-4 space-y-4 text-sm text-slate-600">
                        <li><span class="font-black text-emerald-700">1.</span> Share your unique link.</li>
                        <li><span class="font-black text-emerald-700">2.</span> Applicant opens the registration page.</li>
                        <li><span class="font-black text-emerald-700">3.</span> Future approval assigns the applicant to your direct team.</li>
                    </ol>
                    <p class="mt-5 rounded-lg bg-slate-50 p-3 text-xs leading-5 text-slate-500">
                        Online registration and approval are not active yet.
                    </p>
                </aside>
            </div>
        </section>
    </main>

    <div class="fixed bottom-5 right-5 z-[70] hidden rounded-lg border border-emerald-200 bg-white px-4 py-3 text-sm font-bold text-emerald-800 shadow-xl" data-copy-toast></div>

    <script>
        document.querySelectorAll('[data-copy-value]').forEach((button) => {
            button.addEventListener('click', async () => {
                const value = button.dataset.copyValue;
                const toast = document.querySelector('[data-copy-toast]');

                try {
                    await navigator.clipboard.writeText(value);
                } catch (error) {
                    const fallback = document.createElement('textarea');
                    fallback.value = value;
                    fallback.style.position = 'fixed';
                    fallback.style.opacity = '0';
                    document.body.appendChild(fallback);
                    fallback.select();
                    document.execCommand('copy');
                    fallback.remove();
                }

                toast.textContent = button.dataset.copyMessage;
                toast.classList.remove('hidden');
                window.clearTimeout(window.referralToastTimer);
                window.referralToastTimer = window.setTimeout(() => toast.classList.add('hidden'), 2500);
            });
        });
    </script>
@endsection
