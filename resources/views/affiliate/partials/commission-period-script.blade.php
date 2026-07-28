<script>
    window.initCommissionDatePicker = () => {
        const form = document.querySelector('[data-period-filter-form]');
        const periodType = form?.querySelector('[name="period_type"]');
        const customControls = form?.querySelector('[data-custom-date-controls]');
        const monthYearControls = form?.querySelector('[data-month-year-controls]');
        const el = document.getElementById('date-range-picker');

        const syncVisibility = () => {
            const type = periodType?.value || 'month';
            customControls?.classList.toggle('hidden', type !== 'custom');
            monthYearControls?.classList.toggle('hidden', type !== 'month');
        };

        syncVisibility();

        if (!el || !window.flatpickr) return;
        if (el._flatpickr) el._flatpickr.destroy();

        const fromInput = document.getElementById('date-from-value');
        const toInput = document.getElementById('date-to-value');
        const fromVal = fromInput?.value;
        const toVal = toInput?.value;

        const parseDate = (str) => {
            if (!str) return null;
            const [y, m, d] = str.split('-');
            return new Date(+y, +m - 1, +d);
        };
        const defaultDate = [parseDate(fromVal), parseDate(toVal)].filter(Boolean);

        const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        const fmtDisplay = (d) => `${String(d.getDate()).padStart(2,'0')} ${months[d.getMonth()]} ${d.getFullYear()}`;
        const fmtYmd = (d) => [d.getFullYear(), String(d.getMonth()+1).padStart(2,'0'), String(d.getDate()).padStart(2,'0')].join('-');

        flatpickr(el, {
            mode: 'range',
            dateFormat: 'Y-m-d',
            defaultDate: defaultDate.length ? defaultDate : null,
            locale: { rangeSeparator: ' -> ' },
            onReady: (selectedDates) => {
                if (selectedDates.length === 2) {
                    el.value = `${fmtDisplay(selectedDates[0])} -> ${fmtDisplay(selectedDates[1])}`;
                } else if (selectedDates.length === 1) {
                    el.value = fmtDisplay(selectedDates[0]);
                }
            },
            onChange: (selectedDates) => {
                if (selectedDates.length === 2) {
                    periodType.value = 'custom';
                    fromInput.value = fmtYmd(selectedDates[0]);
                    toInput.value = fmtYmd(selectedDates[1]);
                    el.value = `${fmtDisplay(selectedDates[0])} -> ${fmtDisplay(selectedDates[1])}`;
                    el.closest('form')?.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
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
            document.querySelectorAll('[data-period-select], [data-period-type-select]').forEach((field) => {
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
                browserUrl.searchParams.set('period_type', data.periodType || 'month');
                browserUrl.searchParams.set('month', data.month);
                browserUrl.searchParams.set('year', data.year);

                if (data.dateFrom && data.dateTo) {
                    browserUrl.searchParams.set('date_from', data.dateFrom);
                    browserUrl.searchParams.set('date_to', data.dateTo);
                } else {
                    browserUrl.searchParams.delete('date_from');
                    browserUrl.searchParams.delete('date_to');
                }

                if (data.sourceAffiliate === null) browserUrl.searchParams.delete('source_affiliate');
                if (updateHistory) window.history.replaceState({}, '', browserUrl);
            } catch (error) {
                if (error.name !== 'AbortError') errorPanel?.classList.remove('hidden');
            } finally {
                if (activeRequest === requestController) setLoading(false);
            }
        };

        const submitPeriodForm = (form) => {
            if (! form) return;
            const formData = new FormData(form);
            const url = new URL(form.action || window.location.href, window.location.origin);
            url.search = '';
            formData.forEach((value, key) => {
                if (String(value) !== '' && key !== '_date_display') url.searchParams.set(key, value);
            });
            url.searchParams.delete('commission_page');
            loadPeriod(url);
        };

        document.addEventListener('change', (event) => {
            const typeSelect = event.target.closest('[data-period-type-select]');

            if (typeSelect) {
                const form = typeSelect.form;
                const dateFrom = form?.querySelector('[name="date_from"]');
                const dateTo = form?.querySelector('[name="date_to"]');
                const customControls = form?.querySelector('[data-custom-date-controls]');
                const monthYearControls = form?.querySelector('[data-month-year-controls]');

                customControls?.classList.toggle('hidden', typeSelect.value !== 'custom');
                monthYearControls?.classList.toggle('hidden', typeSelect.value !== 'month');

                if (typeSelect.value !== 'custom') {
                    if (dateFrom) dateFrom.value = '';
                    if (dateTo) dateTo.value = '';
                    submitPeriodForm(form);
                }
                return;
            }

            const periodSelect = event.target.closest('[data-period-select]');

            if (periodSelect) {
                const form = periodSelect.form;
                const month = form?.querySelector('[name="month"]');
                const year = form?.querySelector('[name="year"]');
                const periodType = form?.querySelector('[name="period_type"]');
                const dateFrom = form?.querySelector('[name="date_from"]');
                const dateTo = form?.querySelector('[name="date_to"]');
                if (! form || ! month || ! year) return;
                if (periodType) periodType.value = 'month';
                if (dateFrom) dateFrom.value = '';
                if (dateTo) dateTo.value = '';
                if (year.value === 'all') month.value = 'all';
                submitPeriodForm(form);
                return;
            }

            const autoSubmitSelect = event.target.closest('[data-auto-submit-select]');
            if (autoSubmitSelect?.form && autoSubmitSelect.form.dataset.submitting !== '1') {
                autoSubmitSelect.form.dataset.submitting = '1';
                autoSubmitSelect.form.submit();
            }
        });

        document.addEventListener('submit', (event) => {
            const form = event.target.closest('[data-period-filter-form]');
            if (! form) return;
            event.preventDefault();
            submitPeriodForm(form);
        });

        window.addEventListener('popstate', () => loadPeriod(window.location.href, false));
    })();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.initCommissionDatePicker);
    } else {
        window.initCommissionDatePicker();
    }
</script>