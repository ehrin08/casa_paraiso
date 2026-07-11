import './bootstrap';

import { session as turboSession } from '@hotwired/turbo';
import Alpine from 'alpinejs';

turboSession.drive = false;

window.Alpine = Alpine;

const modalStoreName = 'casaModal';

const syncBodyScrollLock = () => {
    if (!document.body) {
        return;
    }

    const modalIsOpen = Boolean(Alpine.store(modalStoreName)?.active);
    const panelIsOpen = document.querySelector('[data-panel-host]')?.classList.contains('is-open') ?? false;

    document.body.classList.toggle('overflow-y-hidden', modalIsOpen || panelIsOpen);
};

Alpine.store(modalStoreName, {
    active: null,
    trigger: null,

    open(name, trigger = null) {
        if (typeof name !== 'string' || name.length === 0) {
            return;
        }

        this.active = name;
        this.trigger = trigger instanceof HTMLElement ? trigger : document.activeElement;

        syncBodyScrollLock();
    },

    close(name = this.active) {
        if (name && name !== this.active) {
            return;
        }

        const trigger = this.trigger;

        this.active = null;
        this.trigger = null;

        syncBodyScrollLock();

        window.requestAnimationFrame(() => {
            if (trigger instanceof HTMLElement && trigger.isConnected) {
                trigger.focus({ preventScroll: true });
            }
        });
    },
});

const modalStore = () => Alpine.store(modalStoreName);

window.casaModal = ({ name, initialShow = false, focusable = false }) => ({
    name,
    initialShow,
    focusable,

    get show() {
        return Alpine.store(modalStoreName).active === this.name;
    },

    init() {
        if (this.initialShow) {
            modalStore().open(this.name);
        }

        this.$watch('show', (show) => {
            if (show && this.focusable) {
                window.setTimeout(() => this.firstFocusable()?.focus(), 100);
            }
        });

        if (this.show && this.focusable) {
            window.setTimeout(() => this.firstFocusable()?.focus(), 100);
        }
    },

    close() {
        modalStore().close(this.name);
    },

    focusables() {
        const selector = 'a, button, input:not([type=\'hidden\']), textarea, select, details, [tabindex]:not([tabindex=\'-1\'])';

        return [...this.$el.querySelectorAll(selector)].filter((element) => !element.hasAttribute('disabled'));
    },

    firstFocusable() {
        return this.focusables()[0];
    },

    handleTab(event) {
        const focusable = this.focusables();

        if (focusable.length === 0) {
            event.preventDefault();
            return;
        }

        const currentIndex = focusable.indexOf(document.activeElement);
        const nextIndex = event.shiftKey
            ? (currentIndex <= 0 ? focusable.length - 1 : currentIndex - 1)
            : (currentIndex === -1 || currentIndex === focusable.length - 1 ? 0 : currentIndex + 1);

        event.preventDefault();
        focusable[nextIndex].focus();
    },
});

window.addEventListener('open-modal', (event) => {
    modalStore().open(event.detail, event.target);
});

window.addEventListener('close-modal', (event) => {
    modalStore().close(event.detail);
});

window.customerCalendarBooking = (config) => ({
    availabilityUrl: config.availabilityUrl,
    serviceId: config.initialServiceId || '',
    staffId: config.initialStaffId || '',
    month: config.initialMonth,
    selectedDate: '',
    selectedSlot: config.initialSlot || '',
    slotsByDate: {},
    loading: false,
    error: '',
    weekDays: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
    slotPreviewLimit: config.slotPreviewLimit || 2,

    init() {
        if (this.selectedSlot) {
            this.selectedDate = this.selectedSlot.slice(0, 10);
        }

        if (this.serviceId) {
            this.fetchAvailability();
        }
    },

    get monthLabel() {
        const [year, month] = this.month.split('-').map(Number);
        return new Date(year, month - 1, 1).toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
    },

    get calendarDays() {
        const [year, month] = this.month.split('-').map(Number);
        const firstDay = new Date(year, month - 1, 1);
        const daysInMonth = new Date(year, month, 0).getDate();
        const days = [];

        for (let index = 0; index < firstDay.getDay(); index += 1) {
            days.push({ key: `blank-${index}`, label: '', date: null, available: false, previewSlots: [], moreSlots: 0 });
        }

        for (let day = 1; day <= daysInMonth; day += 1) {
            const date = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const slots = this.slotsByDate[date] || [];

            days.push({
                key: date,
                label: day,
                date,
                available: slots.length > 0,
                previewSlots: slots.slice(0, this.slotPreviewLimit),
                moreSlots: Math.max(0, slots.length - this.slotPreviewLimit),
            });
        }

        return days;
    },

    get selectedDateSlots() {
        return this.selectedDate ? (this.slotsByDate[this.selectedDate] || []) : [];
    },

    get selectedDateLabel() {
        if (!this.selectedDate) {
            return 'Choose a highlighted date.';
        }

        return new Date(`${this.selectedDate}T00:00:00`).toLocaleDateString(undefined, {
            weekday: 'long',
            month: 'long',
            day: 'numeric',
        });
    },

    get selectedSlotLabel() {
        if (!this.selectedSlot) {
            return '';
        }

        return new Date(this.selectedSlot.replace(' ', 'T')).toLocaleString(undefined, {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    },

    serviceChanged() {
        this.selectedDate = '';
        this.selectedSlot = '';
        this.fetchAvailability();
    },

    staffChanged() {
        this.selectedDate = '';
        this.selectedSlot = '';
        this.fetchAvailability();
    },

    previousMonth() {
        const [year, month] = this.month.split('-').map(Number);
        const date = new Date(year, month - 2, 1);
        this.month = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
        this.selectedDate = '';
        this.selectedSlot = '';
        this.fetchAvailability();
    },

    nextMonth() {
        const [year, month] = this.month.split('-').map(Number);
        const date = new Date(year, month, 1);
        this.month = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
        this.selectedDate = '';
        this.selectedSlot = '';
        this.fetchAvailability();
    },

    selectDate(date) {
        this.selectedDate = date;
        this.selectedSlot = '';
    },

    chooseSlot(slot) {
        this.selectedSlot = slot.starts_at;
    },

    moreSlotsLabel(day) {
        return day.moreSlots ? `+${day.moreSlots} more` : '';
    },

    fetchAvailability() {
        this.error = '';
        this.slotsByDate = {};

        if (!this.serviceId) {
            return Promise.resolve();
        }

        this.loading = true;

        return window.axios.get(this.availabilityUrl, {
            params: {
                service_id: this.serviceId,
                preferred_staff_profile_id: this.staffId || null,
                month: this.month,
            },
        }).then((response) => {
            this.slotsByDate = response.data.dates || {};

            if (this.selectedDate && !this.slotsByDate[this.selectedDate]) {
                this.selectedDate = '';
                this.selectedSlot = '';
            }
        }).catch(() => {
            this.error = 'Availability could not be loaded. Try another service or month.';
        }).finally(() => {
            this.loading = false;
        });
    },
});

Alpine.start();

let loadingRevealTimer = null;
let loadingFallbackTimer = null;

const loadingElement = () => document.querySelector('[data-page-loading]');

const revealPageLoading = () => {
    const element = loadingElement();

    if (!element) {
        return;
    }

    element.classList.add('is-visible');
    element.setAttribute('aria-hidden', 'false');

    window.clearTimeout(loadingFallbackTimer);
    loadingFallbackTimer = window.setTimeout(() => {
        hidePageLoading();
    }, 12000);
};

const showPageLoading = (delay = 0) => {
    window.clearTimeout(loadingRevealTimer);

    if (delay > 0) {
        loadingRevealTimer = window.setTimeout(revealPageLoading, delay);
        return;
    }

    revealPageLoading();
};

const hidePageLoading = () => {
    const element = loadingElement();

    window.clearTimeout(loadingRevealTimer);
    window.clearTimeout(loadingFallbackTimer);

    loadingRevealTimer = null;
    loadingFallbackTimer = null;

    if (!element) {
        return;
    }

    element.classList.remove('is-visible');
    element.setAttribute('aria-hidden', 'true');
};

const isModifiedClick = (event) => {
    return event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey;
};

const linkUrl = (link) => {
    try {
        return new URL(link.href, window.location.href);
    } catch {
        return null;
    }
};

const formUrl = (form) => {
    try {
        return new URL(form.action || window.location.href, window.location.href);
    } catch {
        return null;
    }
};

const shouldSkipNavigationUrl = (url) => {
    if (!url || url.origin !== window.location.origin) {
        return true;
    }

    if (url.pathname.includes('/export') || url.pathname.includes('/availability')) {
        return true;
    }

    return url.pathname === window.location.pathname
        && url.search === window.location.search
        && Boolean(url.hash);
};

const isFastLink = (link) => {
    if (!(link instanceof HTMLAnchorElement) || link.hasAttribute('data-turbo')) {
        return false;
    }

    if (link.target && link.target !== '_self') {
        return false;
    }

    if (link.hasAttribute('download') || link.hasAttribute('data-panel-link') || link.hasAttribute('data-no-turbo')) {
        return false;
    }

    if (link.closest('[data-turbo="false"]')) {
        return false;
    }

    const url = linkUrl(link);

    return !shouldSkipNavigationUrl(url) && url.href !== window.location.href;
};

const isFastGetForm = (form) => {
    if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-turbo')) {
        return false;
    }

    if (form.method.toLowerCase() !== 'get' || (form.target && form.target !== '_self')) {
        return false;
    }

    if (form.hasAttribute('data-no-turbo') || form.closest('[data-turbo="false"]')) {
        return false;
    }

    return !shouldSkipNavigationUrl(formUrl(form));
};

const prepareFastNavigation = (root = document) => {
    root.querySelectorAll('a[href]').forEach((link) => {
        if (isFastLink(link)) {
            link.setAttribute('data-turbo', 'true');
        }
    });

    root.querySelectorAll('form').forEach((form) => {
        if (isFastGetForm(form)) {
            form.setAttribute('data-turbo', 'true');
        } else if (!form.hasAttribute('data-turbo') && form.method.toLowerCase() !== 'get') {
            form.setAttribute('data-turbo', 'false');
        }
    });
};

const shouldHandleLink = (link, event) => {
    if (!link || event.defaultPrevented || isModifiedClick(event)) {
        return false;
    }

    if (link.getAttribute('data-turbo') === 'true') {
        return false;
    }

    if (link.target && link.target !== '_self') {
        return false;
    }

    if (link.hasAttribute('download') || link.hasAttribute('data-no-loading')) {
        return false;
    }

    const url = linkUrl(link);

    if (shouldSkipNavigationUrl(url)) {
        return false;
    }

    return url.href !== window.location.href;
};

const closestLink = (target, selector = 'a[href]') => {
    return target instanceof Element ? target.closest(selector) : null;
};

const panelElements = () => {
    const host = document.querySelector('[data-panel-host]');

    return {
        host,
        content: host?.querySelector('[data-panel-content]'),
        status: host?.querySelector('[data-panel-status]'),
        title: host?.querySelector('[data-panel-title]'),
        dialog: host?.querySelector('.casa-panel'),
    };
};

const closeModalWithin = (root) => {
    if (!root || !modalStore().active) {
        return;
    }

    const containsActiveModal = [...root.querySelectorAll('[data-modal-name]')]
        .some((element) => element.getAttribute('data-modal-name') === modalStore().active);

    if (containsActiveModal) {
        modalStore().close();
    }
};

const panelPathPrefixes = [
    '/admin/appointments/',
    '/admin/customers/',
    '/admin/staff/',
    '/admin/services/',
    '/admin/transactions/',
    '/admin/promotions/',
    '/admin/feedback/',
    '/staff/appointments/',
    '/staff/customers/',
    '/staff/transactions/',
    '/staff/feedback/',
    '/customer/appointments/',
    '/customer/feedback/',
];

const isPanelEligibleUrl = (url) => {
    if (!url || shouldSkipNavigationUrl(url)) {
        return false;
    }

    return panelPathPrefixes.some((prefix) => url.pathname.startsWith(prefix));
};

const isPanelLink = (link) => {
    const { host } = panelElements();

    if (!host || !link || link.hasAttribute('data-no-panel')) {
        return false;
    }

    if (link.target && link.target !== '_self') {
        return false;
    }

    return link.hasAttribute('data-panel-link');
};

const setPanelLoading = (url) => {
    const { host, content, status, title, dialog } = panelElements();

    if (!host) {
        return;
    }

    host.classList.add('is-open', 'is-loading');
    host.setAttribute('aria-hidden', 'false');
    syncBodyScrollLock();

    if (title) {
        title.textContent = 'Loading';
    }

    if (status) {
        status.textContent = `Opening ${url.pathname}`;
    }

    if (content) {
        closeModalWithin(content);
        content.innerHTML = '';
    }

    window.setTimeout(() => dialog?.focus(), 20);
};

const closePanel = () => {
    const { host, content } = panelElements();

    if (!host) {
        return;
    }

    host.classList.remove('is-open', 'is-loading');
    host.setAttribute('aria-hidden', 'true');
    closeModalWithin(content);
    syncBodyScrollLock();

    if (content) {
        content.innerHTML = '';
    }
};

const panelPageHtml = (doc) => {
    const header = doc.querySelector('[data-page-header]');
    const main = doc.querySelector('[data-page-content]');

    if (!main) {
        return null;
    }

    return `
        <div class="casa-panel-page">
            ${header ? `<header>${header.innerHTML}</header>` : ''}
            <main>${main.innerHTML}</main>
        </div>
    `;
};

const openPanel = async (url) => {
    let { host, content } = panelElements();

    if (!host || !content || !isPanelEligibleUrl(url)) {
        window.location.href = url.href;
        return;
    }

    setPanelLoading(url);

    try {
        const response = await window.fetch(url.href, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-Casa-Panel': '1',
            },
        });

        if (!response.ok) {
            throw new Error(`Panel request failed with ${response.status}`);
        }

        const text = await response.text();
        const doc = new DOMParser().parseFromString(text, 'text/html');
        const pageHtml = panelPageHtml(doc);

        if (!pageHtml) {
            window.location.href = url.href;
            return;
        }

        ({ host, content } = panelElements());

        if (!host || !content) {
            window.location.href = url.href;
            return;
        }

        content.innerHTML = pageHtml;
        host.classList.remove('is-loading');

        const heading = content.querySelector('h1, h2');
        const { title } = panelElements();

        if (title) {
            title.textContent = heading?.textContent?.trim() || 'Workspace panel';
        }

        window.Alpine?.initTree(content);
        content.querySelector('input, select, textarea, button, a')?.focus({ preventScroll: true });
    } catch {
        window.location.href = url.href;
    }
};

prepareFastNavigation();

document.addEventListener('turbo:before-render', (event) => {
    prepareFastNavigation(event.detail.newBody);
});

document.addEventListener('turbo:visit', () => {
    showPageLoading(150);
});

document.addEventListener('turbo:load', () => {
    prepareFastNavigation();
    hidePageLoading();
});

document.addEventListener('turbo:before-cache', () => {
    closePanel();
    hidePageLoading();
});

document.addEventListener('turbo:fetch-request-error', hidePageLoading);

document.addEventListener('click', (event) => {
    const closeTrigger = event.target instanceof Element ? event.target.closest('[data-panel-close], [data-panel-backdrop]') : null;

    if (closeTrigger) {
        event.preventDefault();
        closePanel();
        return;
    }

    const link = closestLink(event.target);

    if (!link || event.defaultPrevented || isModifiedClick(event) || !isPanelLink(link)) {
        return;
    }

    const url = linkUrl(link);

    if (!url) {
        return;
    }

    event.preventDefault();
    openPanel(url);
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    if (modalStore().active) {
        event.preventDefault();
        event.stopPropagation();
        modalStore().close();
        return;
    }

    const { host } = panelElements();

    if (host?.classList.contains('is-open')) {
        closePanel();
    }
});

document.addEventListener('click', (event) => {
    const link = closestLink(event.target);

    if (shouldHandleLink(link, event)) {
        showPageLoading();
    }
});

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-no-loading')) {
        return;
    }

    if (form.getAttribute('data-turbo') === 'true') {
        return;
    }

    if (form.target && form.target !== '_self') {
        return;
    }

    if (shouldSkipNavigationUrl(formUrl(form))) {
        return;
    }

    showPageLoading();
});

window.addEventListener('pageshow', () => {
    prepareFastNavigation();
    hidePageLoading();
});

/*
 * Turbo is intentionally opt-in so state-changing forms and specialized panel
 * links retain their normal Laravel behavior. Eligible links and GET forms are
 * prepared above on the initial document and before every Turbo body swap.
 */
