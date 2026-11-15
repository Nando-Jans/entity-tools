import { Controller } from "@hotwired/stimulus";

export default class extends Controller<HTMLElement> {
    static targets = ["form", "container"];
    declare readonly formTarget: HTMLFormElement;
    declare readonly hasFormTarget: boolean;

    openPopup(event: Event) {
        const button = event.currentTarget as HTMLButtonElement;
        const popup = this.element.querySelector<HTMLDialogElement>(`[data-index-popup="${button.dataset.popup}"]`);
        if (!popup) return;
        popup.querySelectorAll<HTMLInputElement>('[data-filter-index]').forEach(input => {
            input.value = (this.formTarget.elements.namedItem(`filter${input.dataset.filterIndex}Value`) as HTMLInputElement)?.value || '';
        });
        this.fillSearchValue(popup);
        popup.showModal();
    }

    syncSearchField(event: Event) {
        const popup = (event.currentTarget as HTMLElement).closest('dialog');
        if (popup) this.fillSearchValue(popup);
    }

    private fillSearchValue(popup: Element) {
        if (popup.getAttribute('data-index-popup') !== 'search') return;
        const field = popup.querySelector<HTMLSelectElement>('[name="field"]');
        const value = popup.querySelector<HTMLInputElement>('[name="value"]');
        if (!field || !value) return;
        for (const [key, column] of new FormData(this.formTarget).entries()) {
            if (key.endsWith('Field') && column === field.value) {
                value.value = (this.formTarget.elements.namedItem(key.replace(/Field$/, 'Value')) as HTMLInputElement)?.value || '';
                break;
            }
        }
    }

    closePopup(event: Event) {
        (event.currentTarget as HTMLElement).closest('dialog')?.close();
    }

    clearPopupFilters(event: Event) {
        (event.currentTarget as HTMLElement).closest('dialog')?.querySelectorAll<HTMLInputElement>('[data-filter-index]').forEach(input => input.value = '');
    }

    async applyPopup(event: Event) {
        event.preventDefault();
        const popupForm = event.currentTarget as HTMLFormElement;
        const criteria = new FormData(popupForm);
        const params = new URLSearchParams();
        new FormData(this.formTarget).forEach((value, key) => params.set(key, String(value)));
        const popup = popupForm.dataset.popup;
        if (popup === 'sort') {
            params.set('sortField', String(criteria.get('field')));
            params.set('sortDirection', String(criteria.get('direction')));
        } else if (popup === 'search') {
            for (const [key, field] of params.entries()) {
                if (key.endsWith('Field') && field === criteria.get('field')) {
                    params.set(key.replace(/Field$/, 'Value'), String(criteria.get('value') || ''));
                }
            }
        } else if (popup === 'filter') {
            popupForm.querySelectorAll<HTMLInputElement>('[data-filter-index]').forEach(input => {
                params.set(`filter${input.dataset.filterIndex}Value`, input.value);
            });
        }
        this.applyPersistentParams(params, this.formTarget);
        params.set('page', '1');
        const url = new URL(this.formTarget.action, window.location.origin);
        params.forEach((value, key) => url.searchParams.set(key, value));
        popupForm.closest('dialog')?.close();
        await this.loadUrl(url.toString());
    }

    async search(event: Event) {
        event.preventDefault();
        const form = event.target as HTMLFormElement;
        const formData = new FormData(form);
        const params = new URLSearchParams();

        for (const [key, value] of formData.entries()) {
            if (value) {
                params.set(key, value as string);
            }
        }

        this.applyPersistentParams(params, form);

        const url = `${form.action}${form.action.includes('?') ? '&' : '?'}${params.toString()}`;
        await this.loadUrl(url);
    }

    async sort(event: Event) {
        event.preventDefault();
        const link = event.currentTarget as HTMLAnchorElement;
        const url = new URL(link.href);
        if (this.hasFormTarget) {
            const params = new URLSearchParams();
            new FormData(this.formTarget).forEach((value, key) => params.append(key, String(value)));
            this.applyPersistentParams(params, this.formTarget);
            params.forEach((value, key) => url.searchParams.set(key, value));
        }
        url.searchParams.set('sortField', link.dataset.sortField!);
        url.searchParams.set('sortDirection', link.dataset.sortDirection!);
        url.searchParams.set('page', '1');
        await this.loadUrl(url.toString());
    }

    async paginate(event: Event) {
        event.preventDefault();
        const button = event.currentTarget as HTMLButtonElement;
        const page = button.dataset.page;
        if (!page) return;

        // Try to find the form to get the base URL
        let baseUrl = window.location.pathname;
        if (this.hasFormTarget) {
            baseUrl = this.formTarget.getAttribute('action') || window.location.pathname;
        }

        const fetchUrl = new URL(baseUrl, window.location.origin);

        // Copy current search params from the form if available
        if (this.hasFormTarget) {
            const formData = new FormData(this.formTarget);
            for (const [key, value] of formData.entries()) {
                if (value) {
                    fetchUrl.searchParams.set(key, value as string);
                } else if (key.includes('Field')) {
                    fetchUrl.searchParams.set(key, value as string);
                }
            }
            this.applyPersistentParams(fetchUrl.searchParams, this.formTarget);
        }

        // Preserve page from URL if not overridden
        let currentPage = 1;
        const urlParams = new URLSearchParams(window.location.search);
        currentPage = parseInt((this.hasFormTarget ? this.formTarget.dataset.currentPage : null) || urlParams.get('page') || '1');

        let nextPage = 1;

        if (page === 'back') {
            nextPage = currentPage - 1;
        } else if (page === 'next') {
            nextPage = currentPage + 1;
        } else {
            nextPage = parseInt(page);
        }

        const min = parseInt(button.dataset.min || '1');
        const max = parseInt(button.dataset.max || '1');

        if (nextPage < min) nextPage = min;
        if (button.dataset.max && nextPage > max) nextPage = max;

        fetchUrl.searchParams.set('page', nextPage.toString());

        await this.loadUrl(fetchUrl.toString());
    }

    async loadUrl(url: string) {
        // If we are not in a dialog, we might want to just redirect
        const params = new URLSearchParams(url.split('?')[1]);
        if (params.get('dialog') !== '1') {
            window.location.href = url;
            return;
        }

        try {
            const response = await fetch(url);
            const html = await response.text();

            // We want to replace the content of the index table.
            // Since this controller is likely on the container itself, we can replace its innerHTML.
            // But we need to be careful about Stimulus re-connecting.

            // If we are inside a modal, we might want to update the modal body.
            // The modal controller is likely a parent.

            const modalBody = this.element.closest('[data-core--modal-target="body"]');
            if (modalBody) {
                modalBody.innerHTML = html;
            } else {
                this.element.innerHTML = html;
            }
        } catch (error) {
            console.error('Failed to load index table content:', error);
        }
    }

    selectAll(event: Event) {
        const checkbox = event.currentTarget as HTMLInputElement;
        const checkboxes = this.element.querySelectorAll<HTMLInputElement>('input[data-index-table-checkbox]');
        checkboxes.forEach(cb => {
            cb.checked = checkbox.checked;
        });
    }

    syncSelectAll() {
        const selectAll = this.element.querySelector<HTMLInputElement>('thead input[type="checkbox"]');
        if (!selectAll) return;

        const checkboxes = Array.from(this.element.querySelectorAll<HTMLInputElement>('input[data-index-table-checkbox]'));
        const checkedCount = checkboxes.filter(checkbox => checkbox.checked).length;

        selectAll.checked = checkboxes.length > 0 && checkedCount === checkboxes.length;
        selectAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
    }

    stopRowNavigation(event: Event) {
        event.stopPropagation();
    }

    confirmBulkAction(event: Event) {
        const checkboxes = Array.from(this.element.querySelectorAll<HTMLInputElement>('input[data-index-table-checkbox]:checked'));
        if (checkboxes.length === 0) {
            event.preventDefault();
            window.alert('Selecteer eerst minimaal een item.');
            return;
        }

        const button = event.currentTarget as HTMLButtonElement;
        const message = button.dataset.confirmMessage;
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    }

    private applyPersistentParams(params: URLSearchParams, form: HTMLFormElement) {
        const actionUrl = new URL(form.action, window.location.origin);
        const datasetParams: Record<string, string | undefined> = {
            dialog: form.dataset.dialog,
            form_id: form.dataset.formId,
            itemCount: form.dataset.itemCount,
            columns: form.dataset.columns,
        };

        for (const key of ['dialog', 'form_id', 'itemCount', 'columns']) {
            const value = actionUrl.searchParams.get(key) || datasetParams[key] || params.get(key);

            if (value) {
                params.set(key, value);
            }
        }
    }
}
