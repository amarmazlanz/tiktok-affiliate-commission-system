@php
$agPage = match(true) {
    request()->routeIs('admin.affiliates.*') => 'affiliates',
    request()->routeIs('admin.affiliate-registrations.*') => 'registrations',
    request()->routeIs('admin.tiktok-account-requests.*') => 'tiktok_requests',
    request()->routeIs('admin.orders.*') => 'csv_upload',
    request()->routeIs('admin.commissions.*') => 'commissions',
    request()->routeIs('admin.settings.*', 'admin.commission-rate-settings.*') => 'settings',
    default => 'dashboard',
};
@endphp

<div id="ag-overlay" onclick="agClose()"
    class="fixed inset-0 z-[60] bg-black/40 opacity-0 pointer-events-none transition-opacity duration-200">
</div>

<aside id="ag-drawer" aria-label="Panduan Admin"
    class="fixed inset-y-0 right-0 z-[70] flex w-full max-w-md translate-x-full flex-col bg-white shadow-2xl transition-transform duration-300">

    <div class="flex shrink-0 items-center justify-between border-b border-slate-200 px-5 py-4">
        <div>
            <p class="text-xs font-bold uppercase tracking-widest text-rose-600">Panduan Admin</p>
            <h2 id="ag-title" class="mt-0.5 text-lg font-black text-slate-950"></h2>
        </div>
        <div class="flex items-center gap-2">
            <div class="flex overflow-hidden rounded-lg border border-slate-200">
                <button id="ag-btn-bm" onclick="agLang('bm')"
                    class="px-3 py-1.5 text-xs font-bold">BM</button>
                <button id="ag-btn-en" onclick="agLang('en')"
                    class="border-l border-slate-200 px-3 py-1.5 text-xs font-bold">EN</button>
            </div>
            <button onclick="agClose()"
                class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 6 6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto px-5 py-6">
        <div id="ag-body" class="space-y-5"></div>
    </div>
</aside>

<script>
const AG_PAGE = '{{ $agPage }}';
let AG_LANG = 'bm';

const AG_GUIDES = {
    dashboard: {
        bm: {
            title: 'Admin Dashboard',
            steps: [
                { n: 1, h: 'Statistik Utama', b: 'Panel atas papar <strong>Total Sales</strong> dan <strong>Total Commission</strong> berdasarkan commission run terkini. Label seperti "(April 2026)" menunjukkan tempoh yang dirujuk.' },
                { n: 2, h: 'Recent Commission Runs', b: 'Senarai 5 commission run terbaru beserta status masing-masing. Klik <strong>Commission Runs</strong> di menu kiri untuk lihat butiran penuh dan View Report.' },
                { n: 3, h: 'Top Affiliates', b: 'Jadual 10 affiliates teratas berdasarkan jualan. Gunakan penapis <strong>Month</strong> dan <strong>Year</strong> di bahagian kanan untuk tukar tempoh.' },
                { n: 4, h: 'Quick Actions', b: 'Shortcut terus ke halaman <strong>Manage Affiliates</strong>, <strong>Upload CSV</strong>, dan <strong>Run Commission</strong> untuk aksi yang kerap digunakan.' },
            ],
            tips: [
                'Angka "Total Sales" menggunakan tarikh settle TikTok, bukan tarikh order dicipta.',
                'Jika tiada data muncul, pastikan CSV sudah diupload dan commission run sudah dijalankan terlebih dahulu.',
            ]
        },
        en: {
            title: 'Admin Dashboard',
            steps: [
                { n: 1, h: 'Key Statistics', b: 'The top panel shows <strong>Total Sales</strong> and <strong>Total Commission</strong> based on the latest commission run. The label (e.g. "April 2026") indicates the reference period.' },
                { n: 2, h: 'Recent Commission Runs', b: 'Lists the 5 most recent commission runs and their status. Click <strong>Commission Runs</strong> in the left menu for full details and View Report.' },
                { n: 3, h: 'Top Affiliates', b: 'Top 10 affiliates by sales. Use the <strong>Month</strong> and <strong>Year</strong> filters on the right to change the period.' },
                { n: 4, h: 'Quick Actions', b: 'Shortcuts to <strong>Manage Affiliates</strong>, <strong>Upload CSV</strong>, and <strong>Run Commission</strong> for frequently used actions.' },
            ],
            tips: [
                '"Total Sales" uses TikTok\'s settlement date, not the order creation date.',
                'If no data appears, ensure CSV has been uploaded and a commission run has been executed first.',
            ]
        }
    },
    affiliates: {
        bm: {
            title: 'Pengurusan Affiliates',
            steps: [
                { n: 1, h: 'Senarai Affiliates', b: 'Semua affiliates dalam sistem dipaparkan di sini. Guna kotak <strong>carian</strong> untuk cari mengikut nama, kod affiliate, atau kumpulan.' },
                { n: 2, h: 'Lihat Profil', b: 'Klik <strong>View</strong> pada mana-mana affiliates untuk lihat profil penuh — termasuk hierarki upline, TikTok accounts, dan sejarah komisyen.' },
                { n: 3, h: 'Tambah Affiliates', b: 'Klik butang <strong>Add Affiliate</strong> di atas untuk daftarkan affiliates baru secara manual tanpa melalui link referral.' },
                { n: 4, h: 'Reset Password', b: 'Dalam profil affiliates, klik <strong>Reset Password</strong> untuk janakan kata laluan sementara bagi affiliates yang terlupa kata laluan mereka.' },
                { n: 5, h: 'Urus TikTok Account', b: 'Tambah atau padam TikTok account dari halaman profil affiliates. Order dari akaun yang didaftarkan sahaja akan dikira dalam komisyen.' },
            ],
            tips: [
                'Affiliates jenis "Inhouse" mempunyai akaun login sistem. "External" adalah rekod sahaja tanpa akses login.',
                'Gunakan halaman <strong>Hierarchy</strong> di menu untuk lihat carta organisasi keseluruhan.',
                'Boleh edit upline affiliates melalui butang Edit jika hierarki perlu diperbetulkan.',
            ]
        },
        en: {
            title: 'Affiliate Management',
            steps: [
                { n: 1, h: 'Affiliate List', b: 'All affiliates in the system are listed here. Use the <strong>search box</strong> to find by name, affiliate code, or group.' },
                { n: 2, h: 'View Profile', b: 'Click <strong>View</strong> on any affiliate to see the full profile — including upline hierarchy, TikTok accounts, and commission history.' },
                { n: 3, h: 'Add Affiliate', b: 'Click <strong>Add Affiliate</strong> at the top to manually register a new affiliate without a referral link.' },
                { n: 4, h: 'Reset Password', b: 'Within an affiliate\'s profile, click <strong>Reset Password</strong> to generate a temporary password for affiliates who forgot theirs.' },
                { n: 5, h: 'Manage TikTok Accounts', b: 'Add or remove TikTok accounts from an affiliate\'s profile page. Only orders from registered accounts count toward commissions.' },
            ],
            tips: [
                '"Inhouse" affiliates have a system login. "External" are records only with no login access.',
                'Use the <strong>Hierarchy</strong> page in the menu to view the full organisation chart.',
                'You can edit an affiliate\'s upline via the Edit button if the hierarchy needs correcting.',
            ]
        }
    },
    registrations: {
        bm: {
            title: 'Permohonan Affiliates',
            steps: [
                { n: 1, h: 'Senarai Permohonan', b: 'Permohonan yang dihantar melalui <strong>link referral</strong> affiliates akan muncul di sini dengan status "Pending".' },
                { n: 2, h: 'Semak Butiran', b: 'Klik <strong>View</strong> untuk lihat maklumat penuh pemohon — nama, NRIC, nombor telefon, akaun TikTok, dan siapa yang mengajak mereka.' },
                { n: 3, h: 'Luluskan Permohonan', b: 'Klik <strong>Approve</strong> untuk luluskan. Sistem akan auto-cipta akaun User dan Affiliates. Kata laluan sementara akan dijana secara automatik.' },
                { n: 4, h: 'Tolak Permohonan', b: 'Klik <strong>Reject</strong> dan masukkan sebab penolakan jika pemohon tidak layak atau maklumat tidak sah.' },
            ],
            tips: [
                'Sistem auto-kesan duplikat berdasarkan NRIC, nombor telefon, dan username TikTok.',
                'Affiliates yang berkongsi link referral akan dicadangkan sebagai upline secara automatik.',
                'Admin boleh tukar upline yang dicadangkan sebelum meluluskan permohonan.',
            ]
        },
        en: {
            title: 'Pending Registrations',
            steps: [
                { n: 1, h: 'Applications List', b: 'Applications submitted via affiliates\' <strong>referral links</strong> appear here with "Pending" status.' },
                { n: 2, h: 'Review Details', b: 'Click <strong>View</strong> to see the full applicant info — name, NRIC, phone, TikTok account, and who invited them.' },
                { n: 3, h: 'Approve Application', b: 'Click <strong>Approve</strong> to accept. The system automatically creates a User and Affiliate account with a temporary password.' },
                { n: 4, h: 'Reject Application', b: 'Click <strong>Reject</strong> and enter a reason if the applicant is ineligible or information is invalid.' },
            ],
            tips: [
                'The system auto-detects duplicates based on NRIC, phone number, and TikTok username.',
                'The referring affiliate is automatically suggested as the upline.',
                'Admin can change the suggested upline before approving.',
            ]
        }
    },
    tiktok_requests: {
        bm: {
            title: 'Permintaan TikTok Account',
            steps: [
                { n: 1, h: 'Senarai Permintaan', b: 'Affiliates boleh meminta tambahan TikTok account baru melalui dashboard mereka. Semua permintaan akan muncul di sini.' },
                { n: 2, h: 'Semak Username', b: 'Pastikan <strong>username TikTok</strong> yang diminta adalah betul dan merupakan milik affiliates berkenaan sebelum diluluskan.' },
                { n: 3, h: 'Luluskan', b: 'Klik <strong>Approve</strong> untuk tambah akaun TikTok ke profil affiliates. Order dari akaun ini akan dikira dalam komisyen mereka.' },
                { n: 4, h: 'Tolak', b: 'Klik <strong>Reject</strong> jika username tidak sah, tidak wujud, atau sudah didaftarkan oleh affiliates lain.' },
            ],
            tips: [
                'Satu affiliates boleh mempunyai lebih daripada satu TikTok account.',
                'Selepas diluluskan, data dari akaun baru perlu diupload semula melalui CSV Upload.',
            ]
        },
        en: {
            title: 'TikTok Account Requests',
            steps: [
                { n: 1, h: 'Request List', b: 'Affiliates can request new TikTok accounts via their dashboard. All pending requests appear here.' },
                { n: 2, h: 'Verify Username', b: 'Ensure the requested <strong>TikTok username</strong> is correct and belongs to the affiliate before approving.' },
                { n: 3, h: 'Approve', b: 'Click <strong>Approve</strong> to add the TikTok account to the affiliate\'s profile. Orders from this account will count toward their commissions.' },
                { n: 4, h: 'Reject', b: 'Click <strong>Reject</strong> if the username is invalid, doesn\'t exist, or is already registered by another affiliate.' },
            ],
            tips: [
                'An affiliate can have more than one TikTok account.',
                'After approval, data from the new account needs to be re-uploaded via CSV Upload.',
            ]
        }
    },
    csv_upload: {
        bm: {
            title: 'CSV Upload (Data Order)',
            steps: [
                { n: 1, h: 'Muat Turun dari TikTok', b: 'Pergi ke <strong>TikTok Seller Centre</strong> → bahagian laporan → export data order dalam format CSV atau Excel.' },
                { n: 2, h: 'Pilih Fail', b: 'Klik kawasan upload atau <strong>drag & drop</strong> fail CSV/Excel ke dalam kotak yang disediakan.' },
                { n: 3, h: 'Semak Format', b: 'Pastikan fail mengandungi lajur standard TikTok: Order ID, TikTok Username, jumlah jualan, status order, dan tarikh. Jangan ubah format fail asal.' },
                { n: 4, h: 'Upload & Tunggu', b: 'Klik butang <strong>Upload</strong> dan tunggu proses selesai. Sistem akan padankan order kepada affiliates berdasarkan TikTok username mereka.' },
                { n: 5, h: 'Semak Keputusan', b: 'Sistem tunjukkan berapa order berjaya diimport. Order yang gagal biasanya disebabkan TikTok username tidak wujud dalam sistem.' },
            ],
            tips: [
                'Hanya order berstatus <strong>"Settled"</strong> akan dikira dalam komisyen.',
                'Boleh upload fail yang sama berkali-kali — sistem akan update rekod, bukan duplikat.',
                'Selepas upload selesai, pergi ke <strong>Commission Runs</strong> untuk kira komisyen.',
            ]
        },
        en: {
            title: 'CSV Upload (Order Data)',
            steps: [
                { n: 1, h: 'Download from TikTok', b: 'Go to <strong>TikTok Seller Centre</strong> → reports section → export order data in CSV or Excel format.' },
                { n: 2, h: 'Select File', b: 'Click the upload area or <strong>drag & drop</strong> the CSV/Excel file into the provided box.' },
                { n: 3, h: 'Check Format', b: 'Ensure the file has standard TikTok columns: Order ID, TikTok Username, sales amount, order status, and date. Do not alter the original file format.' },
                { n: 4, h: 'Upload & Wait', b: 'Click <strong>Upload</strong> and wait for the process to finish. The system matches orders to affiliates based on TikTok username.' },
                { n: 5, h: 'Review Results', b: 'The system shows how many orders were successfully imported. Failures are usually due to an unregistered TikTok username.' },
            ],
            tips: [
                'Only orders with status <strong>"Settled"</strong> are counted in commissions.',
                'You can upload the same file multiple times — the system updates records, not duplicates.',
                'After uploading, go to <strong>Commission Runs</strong> to calculate commissions.',
            ]
        }
    },
    commissions: {
        bm: {
            title: 'Commission Runs',
            steps: [
                { n: 1, h: 'Pilih Tempoh', b: 'Pilih <strong>Month</strong> dan <strong>Year</strong> untuk commission run yang mahu dijalankan. Pastikan CSV untuk bulan tersebut sudah diupload.' },
                { n: 2, h: 'Pilih Status', b: '<strong>Provisional</strong> = draf, boleh dikira semula bila-bila masa tanpa pengesahan. <strong>Final</strong> = dikunci, perlu centang kotak pengesahan untuk kira semula.' },
                { n: 3, h: 'Jalankan', b: 'Klik <strong>"Run Commission Calculation"</strong>. Proses mengambil masa beberapa saat bergantung kepada jumlah data order.' },
                { n: 4, h: 'Lihat Laporan', b: 'Klik <strong>View Report</strong> untuk lihat butiran komisyen setiap affiliates — Personal, Manager Bonus, Overriding, dan jumlah bayaran.' },
                { n: 5, h: 'Padam & Run Semula', b: 'Gunakan butang <strong>Delete</strong> jika perlu upload semula CSV dan kira semula. Semua commission entries akan dipadam dan boleh dijalankan semula.' },
            ],
            tips: [
                'Untuk kira semula run berstatus "Final", centang kotak pengesahan terlebih dahulu.',
                'Memadam commission run juga akan memadam semua commission entries untuk bulan itu.',
                'Satu bulan hanya boleh ada satu commission run pada satu masa.',
            ]
        },
        en: {
            title: 'Commission Runs',
            steps: [
                { n: 1, h: 'Select Period', b: 'Choose the <strong>Month</strong> and <strong>Year</strong> for the commission run. Ensure the CSV for that month has already been uploaded.' },
                { n: 2, h: 'Choose Status', b: '<strong>Provisional</strong> = draft, can be recalculated anytime. <strong>Final</strong> = locked, requires checking the confirmation box to recalculate.' },
                { n: 3, h: 'Run', b: 'Click <strong>"Run Commission Calculation"</strong>. Processing takes a few seconds depending on the volume of order data.' },
                { n: 4, h: 'View Report', b: 'Click <strong>View Report</strong> to see detailed commission breakdowns per affiliate — Personal, Manager Bonus, Overriding, and total payout.' },
                { n: 5, h: 'Delete & Re-run', b: 'Use the <strong>Delete</strong> button if you need to re-upload CSV and recalculate. All commission entries will be removed and can be re-run.' },
            ],
            tips: [
                'To recalculate a "Final" run, check the confirmation checkbox first.',
                'Deleting a commission run also deletes all commission entries for that month.',
                'Only one commission run can exist per month at a time.',
            ]
        }
    },
    settings: {
        bm: {
            title: 'Admin Settings',
            steps: [
                { n: 1, h: 'Tukar Password', b: 'Masukkan <strong>password semasa</strong> dan <strong>password baru</strong> untuk tukar kata laluan akaun admin anda.' },
                { n: 2, h: 'Tambah Admin Baru', b: 'Masukkan nama, email, dan password untuk cipta akaun <strong>admin baru</strong>. Admin baru akan mempunyai akses penuh ke semua halaman admin.' },
            ],
            tips: [
                'Pastikan password baru sekurang-kurangnya 8 aksara.',
                'Hanya kongsi kelayakan admin kepada kakitangan yang dipercayai.',
            ]
        },
        en: {
            title: 'Admin Settings',
            steps: [
                { n: 1, h: 'Change Password', b: 'Enter your <strong>current password</strong> and a <strong>new password</strong> to update your admin account credentials.' },
                { n: 2, h: 'Add New Admin', b: 'Enter a name, email, and password to create a <strong>new admin account</strong>. New admins will have full access to all admin pages.' },
            ],
            tips: [
                'Ensure the new password is at least 8 characters.',
                'Only share admin credentials with trusted staff.',
            ]
        }
    }
};

function agRender() {
    const g = AG_GUIDES[AG_PAGE]?.[AG_LANG];
    if (!g) return;

    document.getElementById('ag-title').textContent = g.title;

    let html = '';
    g.steps.forEach(function(s) {
        html += '<div class="flex gap-3">'
            + '<div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-rose-100 text-xs font-black text-rose-700">' + s.n + '</div>'
            + '<div>'
            + '<p class="text-sm font-bold text-slate-900">' + s.h + '</p>'
            + '<p class="mt-0.5 text-sm leading-relaxed text-slate-600">' + s.b + '</p>'
            + '</div></div>';
    });

    if (g.tips && g.tips.length) {
        html += '<div class="rounded-xl border border-amber-200 bg-amber-50 p-4">'
            + '<p class="mb-2 text-xs font-black uppercase tracking-widest text-amber-700">Tips</p>'
            + '<ul class="space-y-1.5">';
        g.tips.forEach(function(t) {
            html += '<li class="flex gap-2 text-sm text-amber-900"><span class="mt-0.5 shrink-0 text-amber-500">•</span><span>' + t + '</span></li>';
        });
        html += '</ul></div>';
    }

    document.getElementById('ag-body').innerHTML = html;

    var active = 'px-3 py-1.5 text-xs font-bold bg-rose-600 text-white';
    var inactive = 'px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-50';
    document.getElementById('ag-btn-bm').className = AG_LANG === 'bm' ? active : inactive;
    document.getElementById('ag-btn-en').className = AG_LANG === 'en'
        ? 'border-l border-slate-200 ' + active
        : 'border-l border-slate-200 ' + inactive;
}

function agOpen() {
    agRender();
    var overlay = document.getElementById('ag-overlay');
    var drawer = document.getElementById('ag-drawer');
    overlay.classList.remove('opacity-0', 'pointer-events-none');
    drawer.classList.remove('translate-x-full');
    document.body.style.overflow = 'hidden';
}

function agClose() {
    var overlay = document.getElementById('ag-overlay');
    var drawer = document.getElementById('ag-drawer');
    overlay.classList.add('opacity-0', 'pointer-events-none');
    drawer.classList.add('translate-x-full');
    document.body.style.overflow = '';
}

function agLang(lang) {
    AG_LANG = lang;
    agRender();
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') agClose();
});
</script>
