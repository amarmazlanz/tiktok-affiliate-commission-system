@extends('layouts.auth')

@section('title', 'Help')

@section('content')
    @php
        $sections = [
            [
                'title' => 'Cara Login',
                'content' => [
                    'Buka halaman login sistem dan masukkan Login ID affiliate anda bersama password.',
                    'Login ID biasanya ialah Affiliate Code yang diberikan oleh admin.',
                    'Jika anda menggunakan temporary password, sistem mungkin meminta anda menukar password sebelum masuk ke dashboard.',
                ],
            ],
            [
                'title' => 'Cara Tukar Password',
                'content' => [
                    'Pergi ke Account Settings dan pilih bahagian Change Password.',
                    'Masukkan password semasa, password baru dan pengesahan password baru.',
                    'Gunakan password yang kuat dan jangan berkongsi password dengan orang lain.',
                ],
            ],
            [
                'title' => 'Dashboard',
                'content' => [
                    'Dashboard memaparkan ringkasan profil, jualan, komisyen dan status team anda.',
                    'Gunakan filter bulan dan tahun untuk melihat prestasi bagi tempoh tertentu.',
                    'Shortcut pada dashboard membantu anda pergi ke My Commission, My Team dan TikTok Accounts dengan cepat.',
                ],
            ],
            [
                'title' => 'My Commission',
                'content' => [
                    'Halaman My Commission memaparkan ringkasan komisyen anda mengikut tempoh yang dipilih.',
                    'Pecahan komisyen termasuk Personal Commission, Manager Bonus, Level 1, Level 2 dan Level 3 jika layak.',
                    'Commission Breakdown hanya menunjukkan entry komisyen yang anda terima sebagai receiver.',
                ],
            ],
            [
                'title' => 'My Team',
                'content' => [
                    'Halaman My Team menunjukkan downline dan struktur team di bawah anda sahaja.',
                    'Anda boleh melihat level team, jumlah TikTok account, jualan dan komisyen yang berkaitan dengan team anda.',
                    'Anda tidak boleh melihat team lain yang tidak berada di bawah struktur anda.',
                ],
            ],
            [
                'title' => 'Invite Affiliate',
                'content' => [
                    'Halaman Invite Affiliate menyediakan referral link anda jika fungsi referral telah diaktifkan.',
                    'Kongsi link tersebut kepada calon affiliate yang ingin mendaftar di bawah anda.',
                    'Pendaftaran melalui link masih tertakluk kepada semakan dan kelulusan admin.',
                ],
            ],
            [
                'title' => 'TikTok Accounts',
                'content' => [
                    'Halaman TikTok Accounts menunjukkan username TikTok yang telah dikaitkan dengan profil affiliate anda.',
                    'Anda boleh menghantar request untuk menambah username TikTok baru.',
                    'Anda juga boleh set akaun sendiri kepada Active atau Inactive. Perubahan status hanya memberi kesan kepada matching order masa depan, bukan report komisyen lama.',
                ],
            ],
            [
                'title' => 'Account Settings',
                'content' => [
                    'Account Settings membolehkan anda menyemak maklumat login dan menukar password.',
                    'Pastikan maklumat asas anda sentiasa betul supaya admin mudah membuat semakan jika perlu.',
                    'Jangan simpan password di tempat yang boleh diakses oleh orang lain.',
                ],
            ],
            [
                'title' => 'Soalan Lazim',
                'content' => [
                    'Kenapa jualan tidak muncul? Pastikan order sudah diimport oleh admin dan status order layak dikira.',
                    'Kenapa TikTok username baru belum aktif? Request username perlu disemak dan diluluskan oleh admin terlebih dahulu.',
                    'Kenapa komisyen berubah selepas report baru dijalankan? Admin boleh menjalankan semula report bulanan berdasarkan data order terkini.',
                ],
            ],
            [
                'title' => 'Hubungi Admin',
                'content' => [
                    'Hubungi admin jika anda terlupa password, TikTok username tidak betul atau komisyen tidak kelihatan seperti dijangka.',
                    'Sediakan Affiliate Code, nama penuh dan maklumat ringkas isu supaya semakan boleh dibuat dengan cepat.',
                    'Jangan hantar password lama atau maklumat sensitif melalui mesej terbuka.',
                ],
            ],
        ];
    @endphp

    <main class="min-h-screen bg-slate-100">
        <section class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6">
            <div>
                <p class="text-sm font-bold text-emerald-700">Affiliate Guide</p>
                <h2 class="mt-1 text-2xl font-black text-slate-950">Help</h2>
                <p class="mt-2 text-sm text-slate-500">Panduan ringkas untuk menggunakan TikTok Affiliate Commission System.</p>
            </div>

            <div class="app-card divide-y divide-slate-200 overflow-hidden">
                @foreach ($sections as $section)
                    <details class="group bg-white p-5 open:bg-slate-50" @if ($loop->first) open @endif>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4">
                            <span class="text-base font-black text-slate-950">{{ $section['title'] }}</span>
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-lg font-black text-slate-500 transition group-open:rotate-45 group-open:bg-emerald-100 group-open:text-emerald-700">
                                +
                            </span>
                        </summary>
                        <div class="mt-4 space-y-3 text-sm leading-6 text-slate-600">
                            @foreach ($section['content'] as $paragraph)
                                <p>{{ $paragraph }}</p>
                            @endforeach
                        </div>
                    </details>
                @endforeach
            </div>
        </section>
    </main>
@endsection
