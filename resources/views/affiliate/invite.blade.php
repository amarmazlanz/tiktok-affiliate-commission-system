@extends('layouts.auth')

@section('title', 'Invite Affiliate')

@section('content')
    <main class="min-h-screen bg-slate-100">
        <section class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-bold text-emerald-700">Referral Foundation</p>
                    <h2 class="mt-1 text-2xl font-black text-slate-950">Invite Affiliate</h2>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-slate-500">
                        @if ($affiliate->affiliate_type === 'inhouse')
                            Use the appropriate link below to invite new affiliates to your downline.
                        @else
                            Share this link to invite new affiliates as your direct downline.
                        @endif
                    </p>
                </div>
                <button type="button" id="how-it-works-toggle"
                    class="shrink-0 flex items-center gap-1.5 self-start rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-bold text-slate-600 shadow-sm hover:border-slate-300 hover:bg-slate-50">
                    <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>
                    </svg>
                    How It Works
                    <span id="how-it-works-chevron" class="ml-0.5 h-2.5 w-2.5 border-b-2 border-r-2 border-current transition-transform -rotate-45"></span>
                </button>
            </div>

            <div id="how-it-works-panel" class="hidden rounded-xl border border-emerald-100 bg-emerald-50 p-5">
                <p class="text-sm font-black text-emerald-800">How It Works</p>
                <ol class="mt-3 space-y-3 text-sm text-emerald-900">
                    @if ($affiliate->affiliate_type === 'inhouse')
                        <li><span class="font-black">1.</span> Choose the affiliate type you want to invite — Inhouse or Online — and copy the corresponding link.</li>
                        <li><span class="font-black">2.</span> Send the link to the person you want to invite.</li>
                        <li><span class="font-black">3.</span> They complete the registration form and submit an application.</li>
                        <li><span class="font-black">4.</span> Admin reviews and approves the application — the new affiliate will be assigned as your direct downline.</li>
                    @else
                        <li><span class="font-black">1.</span> Copy the link below and send it to the person you want to invite.</li>
                        <li><span class="font-black">2.</span> They complete the registration form and submit an application.</li>
                        <li><span class="font-black">3.</span> Admin reviews and approves the application — the new affiliate will be assigned as your direct downline.</li>
                    @endif
                </ol>
            </div>

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
                    @if ($affiliate->affiliate_type === 'inhouse')
                        <div class="rounded-lg border border-teal-200 bg-teal-50 p-5">
                            <p class="text-sm font-black text-teal-800">Inhouse Affiliate Invite Link</p>
                            <p class="mt-0.5 text-xs text-teal-700">For individuals registering as an Inhouse affiliate.</p>
                            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                                <input type="text" readonly value="{{ $inhouseInviteUrl }}" class="form-field mt-0 min-w-0 flex-1 bg-white text-sm">
                                <button type="button" class="btn-primary shrink-0" data-copy-value="{{ $inhouseInviteUrl }}" data-copy-message="Inhouse invite link copied.">Copy Link</button>
                            </div>
                        </div>

                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-5">
                            <p class="text-sm font-black text-amber-800">Online Affiliate Invite Link</p>
                            <p class="mt-0.5 text-xs text-amber-700">For individuals registering as an Online affiliate.</p>
                            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                                <input type="text" readonly value="{{ $onlineInviteUrl }}" class="form-field mt-0 min-w-0 flex-1 bg-white text-sm">
                                <button type="button" class="btn-primary shrink-0" data-copy-value="{{ $onlineInviteUrl }}" data-copy-message="Online invite link copied.">Copy Link</button>
                            </div>
                        </div>
                    @else
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-5">
                            <p class="text-sm font-black text-amber-800">Online Affiliate Invite Link</p>
                            <p class="mt-0.5 text-xs text-amber-700">Applicants using this link will be registered as Online affiliates.</p>
                            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                                <input type="text" readonly value="{{ $onlineInviteUrl }}" class="form-field mt-0 min-w-0 flex-1 bg-white text-sm">
                                <button type="button" class="btn-primary shrink-0" data-copy-value="{{ $onlineInviteUrl }}" data-copy-message="Invite link copied.">Copy Link</button>
                            </div>
                        </div>
                    @endif
                </div>

                @unless ($referralEnabled)
                    <p class="mt-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
                        This referral link is currently disabled and cannot be used for new registrations.
                    </p>
                @endunless
            </div>
        </section>
    </main>

    <div class="fixed bottom-5 right-5 z-[70] hidden rounded-lg border border-emerald-200 bg-white px-4 py-3 text-sm font-bold text-emerald-800 shadow-xl" data-copy-toast></div>

    <script>
        (() => {
            const toggle = document.getElementById('how-it-works-toggle');
            const panel = document.getElementById('how-it-works-panel');
            const chevron = document.getElementById('how-it-works-chevron');
            let open = false;

            toggle.addEventListener('click', () => {
                open = !open;
                panel.classList.toggle('hidden', !open);
                chevron.classList.toggle('-rotate-45', !open);
                chevron.classList.toggle('rotate-45', open);
                chevron.classList.toggle('-translate-y-0.5', open);
            });
        })();

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
