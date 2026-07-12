@extends('layouts.auth')

@section('title', 'Invite Affiliate')

@section('content')
    <main class="min-h-screen bg-slate-100">
        <section class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6">
            <div>
                <p class="text-sm font-bold text-emerald-700">Referral Foundation</p>
                <h2 class="mt-1 text-2xl font-black text-slate-950">Invite Affiliate</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">
                    @if ($affiliate->affiliate_type === 'inhouse')
                        Sebagai affiliate Inhouse, kamu boleh menjemput dua jenis affiliate — Inhouse atau Online. Guna link yang sesuai mengikut jenis affiliate yang ingin dijemput.
                    @else
                        Guna link ini untuk menjemput affiliate Online baru sebagai downline kamu.
                    @endif
                </p>
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
                            <p class="text-sm font-black text-teal-800">Link Jemput Affiliate Inhouse</p>
                            <p class="mt-0.5 text-xs text-teal-700">Untuk individu yang akan didaftarkan sebagai affiliate Inhouse.</p>
                            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                                <input type="text" readonly value="{{ $inhouseInviteUrl }}" class="form-field mt-0 min-w-0 flex-1 bg-white text-sm">
                                <button type="button" class="btn-primary shrink-0" data-copy-value="{{ $inhouseInviteUrl }}" data-copy-message="Link Inhouse disalin.">Salin Link</button>
                            </div>
                        </div>

                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-5">
                            <p class="text-sm font-black text-amber-800">Link Jemput Affiliate Online</p>
                            <p class="mt-0.5 text-xs text-amber-700">Untuk individu yang akan didaftarkan sebagai affiliate Online.</p>
                            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                                <input type="text" readonly value="{{ $onlineInviteUrl }}" class="form-field mt-0 min-w-0 flex-1 bg-white text-sm">
                                <button type="button" class="btn-primary shrink-0" data-copy-value="{{ $onlineInviteUrl }}" data-copy-message="Link Online disalin.">Salin Link</button>
                            </div>
                        </div>
                    @else
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-5">
                            <p class="text-sm font-black text-amber-800">Link Jemput Affiliate Online</p>
                            <p class="mt-0.5 text-xs text-amber-700">Individu yang mendaftar melalui link ini akan diproses sebagai affiliate Online.</p>
                            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                                <input type="text" readonly value="{{ $onlineInviteUrl }}" class="form-field mt-0 min-w-0 flex-1 bg-white text-sm">
                                <button type="button" class="btn-primary shrink-0" data-copy-value="{{ $onlineInviteUrl }}" data-copy-message="Link disalin.">Salin Link</button>
                            </div>
                        </div>
                    @endif

                    <div>
                        <label for="referral-code" class="stat-label">Referral Code</label>
                        <div class="mt-2 flex gap-2">
                            <input id="referral-code" type="text" readonly value="{{ $referral->referral_code }}" class="form-field mt-0 min-w-0 flex-1 bg-slate-50 font-mono font-black">
                            <button type="button" class="btn-secondary shrink-0" data-copy-value="{{ $referral->referral_code }}" data-copy-message="Referral code copied.">Copy Code</button>
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
                <p class="stat-label">Cara Jemput Affiliate</p>
                <ol class="mt-4 space-y-4 text-sm text-slate-600">
                    @if ($affiliate->affiliate_type === 'inhouse')
                        <li><span class="font-black text-emerald-700">1.</span> Pilih jenis affiliate yang ingin dijemput — Inhouse atau Online.</li>
                        <li><span class="font-black text-emerald-700">2.</span> Salin link berkenaan dan hantar kepada individu tersebut.</li>
                    @else
                        <li><span class="font-black text-emerald-700">1.</span> Salin link di atas dan hantar kepada individu yang ingin dijemput.</li>
                    @endif
                    <li><span class="font-black text-emerald-700">{{ $affiliate->affiliate_type === 'inhouse' ? '3' : '2' }}.</span> Individu tersebut isi borang pendaftaran dan hantar permohonan.</li>
                    <li><span class="font-black text-emerald-700">{{ $affiliate->affiliate_type === 'inhouse' ? '4' : '3' }}.</span> Admin akan menyemak dan meluluskan — pendaftar akan diletakkan sebagai downline langsung kamu.</li>
                </ol>
            </aside>
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
