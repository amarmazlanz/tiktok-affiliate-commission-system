<script>
    window.initCommissionDatePicker = () => {
        const el = document.getElementById('date-range-picker');
        if (!el || !window.flatpickr) return;
        if (el._flatpickr) el._flatpickr.destroy();

        const fromVal = document.getElementById('date-from-value')?.value;
        const toVal   = document.getElementById('date-to-value')?.value;

        const parseDate = (str) => {
            if (!str) return null;
            const [y, m, d] = str.split('-');
            return new Date(+y, +m - 1, +d);
        };
        const defaultDate = [parseDate(fromVal), parseDate(toVal)].filter(Boolean);

        const months = ['Jan','Feb','Mac','Apr','Mei','Jun','Jul','Ogo','Sep','Okt','Nov','Dis'];
        const fmtDisplay = (d) => `${String(d.getDate()).padStart(2,'0')} ${months[d.getMonth()]} ${d.getFullYear()}`;
        const fmtYmd = (d) => [d.getFullYear(), String(d.getMonth()+1).padStart(2,'0'), String(d.getDate()).padStart(2,'0')].join('-');

        flatpickr(el, {
            mode: 'range',
            dateFormat: 'Y-m-d',
            defaultDate: defaultDate.length ? defaultDate : null,
            locale: { rangeSeparator: ' → ' },
            onReady: (selectedDates) => {
                if (selectedDates.length === 2) {
                    el.value = `${fmtDisplay(selectedDates[0])} → ${fmtDisplay(selectedDates[1])}`;
                } else if (selectedDates.length === 1) {
                    el.value = fmtDisplay(selectedDates[0]);
                }
            },
            onChange: (selectedDates) => {
                if (selectedDates.length === 2) {
                    document.getElementById('date-from-value').value = fmtYmd(selectedDates[0]);
                    document.getElementById('date-to-value').value   = fmtYmd(selectedDates[1]);
                    el.value = `${fmtDisplay(selectedDates[0])} → ${fmtDisplay(selectedDates[1])}`;
                    el.closest('form')?.submit();
                }
            },
        });
    };

    (() => {
        let activeRequest = null;

        const setLoading = (loading) => {
            const loadingPanel = document.querySelector('[data-period-loading]');
            const summary = document.querySelector('[data-commission-summary-container]');
            const breakdown = document.querySelector('[data-commission-breakdown-container]');

            loadingPanel?.classList.toggle('hidden', ! loading);
            loadingPanel?.classList.toggle('flex', loading);
            summary?.classList.toggle('opacity-60', loading);
            breakdown?.classList.toggle('opacity-60', loading);
            document.querySelectorAll('[data-period-select]').forEach((field) => {
                field.disabled = loading;
            });
        };

        const loadPeriod = async (url, updateHistory = true) => {
            activeRequest?.abort();
            const requestController = new AbortController();
            activeRequest = requestController;
            const requestUrl = new URL(url, window.location.origin);
            requestUrl.searchParams.set('ajax', '1');
            const errorPanel = document.querySelector('[data-period-error]');

            setLoading(true);
            errorPanel?.classList.add('hidden');

            try {
                const response = await fetch(requestUrl, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: requestController.signal,
                });

                if (! response.ok) throw new Error('Unable to load commission data.');

                const data = await response.json();
                document.querySelector('[data-commission-summary-container]').innerHTML = data.html;
                window.initCommissionDatePicker();

                if (data.breakdownHtml && document.querySelector('[data-commission-breakdown-container]')) {
                    document.querySelector('[data-commission-breakdown-container]').innerHTML = data.breakdownHtml;
                }

                const browserUrl = new URL(requestUrl);
                browserUrl.searchParams.delete('ajax');
                browserUrl.searchParams.delete('commission_page');
                browserUrl.searchParams.set('month', data.month);
                browserUrl.searchParams.set('year', data.year);
                if (data.sourceAffiliate === null) browserUrl.searchParams.delete('source_affiliate');
                if (updateHistory) window.history.replaceState({}, '', browserUrl);
            } catch (error) {
                if (error.name !== 'AbortError') errorPanel?.classList.remove('hidden');
            } finally {
                if (activeRequest === requestController) setLoading(false);
            }
        };

        document.addEventListener('change', (event) => {
            const periodSelect = event.target.closest('[data-period-select]');

            if (periodSelect) {
                const form = periodSelect.form;
                const month = form?.querySelector('[name="month"]');
                const year = form?.querySelector('[name="year"]');
                if (! form || ! month || ! year) return;
                if (year.value === 'all') month.value = 'all';

                const url = new URL(window.location.href);
                url.searchParams.set('month', month.value);
                url.searchParams.set('year', year.value);
                url.searchParams.delete('commission_page');
                loadPeriod(url);
                return;
            }

            const autoSubmitSelect = event.target.closest('[data-auto-submit-select]');
            if (autoSubmitSelect?.form && autoSubmitSelect.form.dataset.submitting !== '1') {
                autoSubmitSelect.form.dataset.submitting = '1';
                autoSubmitSelect.form.submit();
            }
        });

        window.addEventListener('popstate', () => loadPeriod(window.location.href, false));
    })();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.initCommissionDatePicker);
    } else {
        window.initCommissionDatePicker();
    }
</script>
