<script>
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
</script>
