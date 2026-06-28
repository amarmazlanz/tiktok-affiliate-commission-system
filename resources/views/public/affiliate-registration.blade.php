<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Affiliate Registration</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <main class="flex min-h-screen items-center justify-center px-4 py-10">
        <section class="w-full max-w-3xl rounded-xl bg-white shadow-lg ring-1 ring-slate-200">
            <header class="border-b border-slate-200 px-6 py-7 text-center sm:px-10">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-xl bg-emerald-700 text-lg font-black text-white">TT</div>
                <p class="mt-4 text-xs font-bold uppercase tracking-wide text-emerald-700">TikTok Affiliate Commission System</p>
                <h1 class="mt-2 text-3xl font-black text-slate-950">Affiliate Registration</h1>
            </header>

            @if (! $isValid)
                <div class="p-8 sm:p-10">
                    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-4 text-center text-sm font-semibold text-red-700">
                        This referral link is invalid or no longer active.
                    </div>
                </div>
            @else
                <div class="space-y-6 p-6 sm:p-10">
                    <div class="rounded-lg border border-emerald-100 bg-emerald-50 p-5">
                        <p class="text-sm font-bold text-emerald-800">Invited by</p>
                        <p class="mt-1 break-words text-lg font-black text-slate-950">{{ $referral->affiliate->name }}</p>
                        <div class="mt-2 flex flex-wrap gap-2 text-xs font-bold text-slate-600">
                            <span class="rounded-full bg-white px-2.5 py-1">{{ $referral->affiliate->affiliate_code }}</span>
                            <span class="rounded-full bg-white px-2.5 py-1">{{ $referral->affiliate->group_name ?: 'No Group' }}</span>
                        </div>
                        <p class="mt-4 text-sm text-emerald-900">Your application will be reviewed by the administrator before activation.</p>
                    </div>

                    <form method="POST" action="{{ route('public.affiliate-registration.store', $referral->referral_code) }}" class="space-y-6" data-registration-form>
                        @csrf
                        <input type="hidden" name="referral_code" value="{{ $referral->referral_code }}">
                        <div class="absolute -left-[9999px]" aria-hidden="true">
                            <label for="website">Website</label>
                            <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            @php
                                $fields = [
                                    ['full_name', 'Full Legal Name', 'text', 'e.g. Nur Aisyah Binti Ahmad', true],
                                    ['nric', 'NRIC / No. IC', 'text', 'e.g. 900101-01-1234', true],
                                    ['phone', 'Phone Number', 'tel', 'e.g. 012-3456789', true],
                                    ['email', 'Email Address', 'email', 'name@example.com', true],
                                    ['tiktok_username', 'TikTok Username', 'text', 'e.g. @aisyah_shop', true],
                                    ['tiktok_username_confirmation', 'Confirm TikTok Username', 'text', 'Re-enter TikTok username', true],
                                    ['additional_tiktok_username', 'Additional TikTok Username', 'text', 'Optional', false],
                                ];
                            @endphp
                            @foreach ($fields as [$name, $label, $type, $placeholder, $required])
                                <div class="{{ $name === 'full_name' ? 'sm:col-span-2' : '' }}">
                                    <label for="{{ $name }}" class="block text-sm font-bold text-slate-700">{{ $label }}</label>
                                    <input
                                        id="{{ $name }}"
                                        name="{{ $name }}"
                                        type="{{ $type }}"
                                        value="{{ $name === 'nric' ? '' : old($name) }}"
                                        placeholder="{{ $placeholder }}"
                                        @if ($name === 'nric')
                                            inputmode="numeric"
                                            autocomplete="off"
                                            maxlength="14"
                                            data-nric-input
                                        @elseif ($name === 'phone')
                                            inputmode="tel"
                                            autocomplete="tel"
                                            maxlength="12"
                                            data-phone-input
                                        @endif
                                        @required($required)
                                        class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm shadow-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                    >
                                    @error($name)
                                        <p class="mt-2 text-sm font-semibold text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endforeach
                        </div>

                        <div>
                            <label for="notes" class="block text-sm font-bold text-slate-700">Notes</label>
                            <textarea id="notes" name="notes" rows="3" maxlength="1000" placeholder="Optional information for the administrator" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm shadow-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100">{{ old('notes') }}</textarea>
                            @error('notes')<p class="mt-2 text-sm font-semibold text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <p class="text-sm text-slate-600">Your personal information is collected for affiliate registration, duplicate prevention, and administrative review.</p>
                            <label class="mt-4 flex items-start gap-3 text-sm text-slate-700">
                                <input name="consent" type="checkbox" value="1" @checked(old('consent')) required class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-700 focus:ring-emerald-500">
                                <span>I confirm that the information provided is accurate and I consent to its processing for affiliate registration.</span>
                            </label>
                            @error('consent')<p class="mt-2 text-sm font-semibold text-red-600">{{ $message }}</p>@enderror
                        </div>

                        @error('referral_code')
                            <p class="text-sm font-semibold text-red-600">{{ $message }}</p>
                        @enderror
                        @error('website')
                            <p class="text-sm font-semibold text-red-600">Unable to submit this application.</p>
                        @enderror

                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-emerald-700 px-4 py-3 text-sm font-black text-white shadow-sm hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-60" data-submit-button>
                            Submit Application
                        </button>
                    </form>
                </div>
            @endif
        </section>
    </main>

    @if ($isValid)
        <script>
            const formatNric = (value) => {
                const digits = value.replace(/\D/g, '').slice(0, 12);

                if (digits.length <= 6) {
                    return digits;
                }

                if (digits.length <= 8) {
                    return `${digits.slice(0, 6)}-${digits.slice(6)}`;
                }

                return `${digits.slice(0, 6)}-${digits.slice(6, 8)}-${digits.slice(8)}`;
            };

            const localPhoneDigits = (value) => {
                let digits = value.replace(/\D/g, '');

                if (digits.startsWith('60')) {
                    digits = `0${digits.slice(2)}`;
                }

                return digits.slice(0, 11);
            };

            const formatMalaysianPhone = (value) => {
                const digits = localPhoneDigits(value);

                if (digits.length <= 3) {
                    return digits;
                }

                return `${digits.slice(0, 3)}-${digits.slice(3)}`;
            };

            const attachFormatter = (selector, formatter) => {
                const input = document.querySelector(selector);

                if (! input) {
                    return;
                }

                const applyFormat = () => {
                    input.value = formatter(input.value);
                };

                input.addEventListener('input', applyFormat);
                input.addEventListener('blur', applyFormat);
                applyFormat();
            };

            attachFormatter('[data-nric-input]', formatNric);
            attachFormatter('[data-phone-input]', formatMalaysianPhone);

            document.querySelector('[data-registration-form]')?.addEventListener('submit', (event) => {
                const form = event.currentTarget;
                const button = form.querySelector('[data-submit-button]');
                if (form.dataset.submitting === '1') {
                    event.preventDefault();
                    return;
                }
                form.dataset.submitting = '1';
                button.disabled = true;
                button.textContent = 'Submitting application...';
            });
        </script>
    @endif
</body>
</html>
