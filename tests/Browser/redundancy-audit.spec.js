import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

const credentials = {
    admin: {
        email: process.env.E2E_ADMIN_EMAIL,
        password: process.env.E2E_ADMIN_PASSWORD,
        destination: /\/admin\/dashboard(?:\?|$)/,
    },
    customer: {
        email: process.env.E2E_CUSTOMER_EMAIL,
        password: process.env.E2E_CUSTOMER_PASSWORD,
        destination: /\/customer\/appointments(?:\?|$)/,
    },
    staff: {
        email: process.env.E2E_STAFF_EMAIL,
        password: process.env.E2E_STAFF_PASSWORD,
        destination: /\/staff\/dashboard(?:\?|$)/,
    },
};

const activeAppointmentStatuses = ['confirmed', 'completed', 'cancelled', 'no_show'];
const bookingLegendLabels = ['Available', 'Cancelled', 'Completed', 'Confirmed', 'No-show'];
const availabilityLegendLabels = ['Available', 'Confirmed booking', 'Unavailable'];
const customerLegendLabels = [
    'Cancelled or no-show · visit closed',
    'Completed · visit finished',
    'Confirmed · reserved schedule',
];
const representativeTargetSelector = [
    '.casa-button-primary',
    '.casa-button-secondary',
    '.casa-danger-button',
    '.casa-icon-button',
    '.casa-nav-link',
    '.casa-mobile-dock-link',
    '.casa-input',
    '[data-operational-day]',
    '[data-operational-calendar] [role="group"] button',
    '[data-customer-calendar-day]',
].join(', ');

async function login(page, role) {
    const account = credentials[role];

    test.skip(!account.email || !account.password, `Set E2E_${role.toUpperCase()}_EMAIL and E2E_${role.toUpperCase()}_PASSWORD.`);

    await page.goto('/login');
    await page.getByLabel(/email/i).fill(account.email);
    await page.getByLabel(/password/i).fill(account.password);
    await page.getByRole('button', { name: /sign in/i }).click();
    await expect(page).toHaveURL(account.destination);
}

async function expectNoSeriousAccessibilityViolations(page) {
    const result = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa'])
        .analyze();

    const serious = result.violations.filter(({ impact }) => impact === 'serious' || impact === 'critical');
    expect(serious, JSON.stringify(serious, null, 2)).toEqual([]);
}

async function expectUniqueFormRelationships(page) {
    const problems = await page.evaluate(() => {
        const ids = [...document.querySelectorAll('[id]')].map((element) => element.id).filter(Boolean);
        const duplicates = [...new Set(ids.filter((id, index) => ids.indexOf(id) !== index))];
        const unresolvedLabels = [...document.querySelectorAll('label[for]')]
            .map((label) => label.getAttribute('for'))
            .filter((target) => target && document.querySelectorAll(`#${CSS.escape(target)}`).length !== 1);

        return { duplicates, unresolvedLabels };
    });

    expect(problems).toEqual({ duplicates: [], unresolvedLabels: [] });
}

async function expectTwoToneFocus(locator) {
    await expect(locator).toBeVisible();
    await locator.focus();
    await expect(locator).toBeFocused();

    const focusStyle = await locator.evaluate((element) => {
        const style = getComputedStyle(element);

        return {
            boxShadow: style.boxShadow,
            outlineColor: style.outlineColor,
            outlineStyle: style.outlineStyle,
            outlineWidth: Number.parseFloat(style.outlineWidth),
            shadowColors: style.boxShadow.match(/rgba?\([^)]+\)/g) ?? [],
        };
    });

    expect(focusStyle.outlineStyle).not.toBe('none');
    expect(focusStyle.outlineWidth).toBeGreaterThanOrEqual(2);
    expect(focusStyle.outlineColor).not.toBe('rgba(0, 0, 0, 0)');
    expect(focusStyle.boxShadow).not.toBe('none');
    expect(focusStyle.shadowColors.some((color) => color !== focusStyle.outlineColor)).toBe(true);
}

async function expectStatusLegend(page, expectedLabels, itemSelector = '.casa-filter-chip:visible') {
    const legend = page.locator('[data-status-legend]').first();

    await expect(legend).toBeVisible();
    await expect.poll(async () => (await legend.locator(itemSelector).allInnerTexts())
        .map((label) => label.trim())
        .sort()).toEqual([...expectedLabels].sort());

    expect(await legend.innerText()).not.toMatch(/\b(?:pending|requests?|requested)\b/i);
}

async function expectActiveStatusOptions(page, calendarSelector = '[data-operational-calendar]') {
    const statusFilter = page.locator(`${calendarSelector} select[x-model="statusFilter"]`);

    await expect(statusFilter).toBeVisible();
    const options = await statusFilter.locator('option').evaluateAll((elements) => elements.map((option) => ({
        label: option.textContent?.trim() ?? '',
        value: option.value,
    })));

    expect(options.map(({ value }) => value).filter(Boolean).sort()).toEqual([...activeAppointmentStatuses].sort());
    expect(options.map(({ label }) => label).join(' ')).not.toMatch(/\b(?:pending|requests?|requested)\b/i);
}

async function expectRepresentativeTargetsAtLeast44Pixels(page) {
    const result = await page.locator(representativeTargetSelector).evaluateAll((elements) => {
        const visible = elements.filter((element) => {
            const style = getComputedStyle(element);
            const box = element.getBoundingClientRect();

            return style.display !== 'none'
                && style.visibility !== 'hidden'
                && Number.parseFloat(style.opacity) !== 0
                && box.width > 0
                && box.height > 0;
        });

        return {
            inspected: visible.length,
            undersized: visible
                .filter((element) => {
                    const box = element.getBoundingClientRect();

                    return Math.round(box.width) < 44 || Math.round(box.height) < 44;
                })
                .map((element) => {
                    const box = element.getBoundingClientRect();

                    return {
                        height: Number(box.height.toFixed(2)),
                        name: element.getAttribute('aria-label') || element.textContent?.trim().slice(0, 80) || element.id,
                        tag: element.tagName.toLowerCase(),
                        width: Number(box.width.toFixed(2)),
                    };
                }),
        };
    });

    expect(result.inspected).toBeGreaterThan(0);
    expect(result.undersized, JSON.stringify(result.undersized, null, 2)).toEqual([]);
}

async function expectMeaningfulTextAtLeast14Pixels(page) {
    const result = await page.evaluate(() => {
        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
        const inspected = [];
        const undersized = [];
        let node = walker.nextNode();

        while (node) {
            const text = node.textContent?.replace(/\s+/g, ' ').trim() ?? '';
            const element = node.parentElement;

            if (text && element
                && !['NOSCRIPT', 'OPTION', 'SCRIPT', 'STYLE', 'SVG'].includes(element.tagName)
                && !element.closest('[aria-hidden="true"], [hidden], .sr-only')) {
                const style = getComputedStyle(element);
                const box = element.getBoundingClientRect();
                const fontSize = Number.parseFloat(style.fontSize);
                const isVisible = style.display !== 'none'
                    && style.visibility !== 'hidden'
                    && Number.parseFloat(style.opacity) !== 0
                    && box.width > 0
                    && box.height > 0;

                if (isVisible) {
                    inspected.push(element);

                    if (fontSize < 14) {
                        undersized.push({
                            fontSize,
                            tag: element.tagName.toLowerCase(),
                            text: text.slice(0, 100),
                        });
                    }
                }
            }

            node = walker.nextNode();
        }

        return { inspected: inspected.length, undersized };
    });

    expect(result.inspected).toBeGreaterThan(0);
    expect(result.undersized, JSON.stringify(result.undersized, null, 2)).toEqual([]);
}

async function expectNoDocumentOverflow(page) {
    const overflow = await page.evaluate(() => ({
        clientWidth: document.documentElement.clientWidth,
        scrollWidth: Math.max(document.documentElement.scrollWidth, document.body.scrollWidth),
    }));

    expect(overflow.scrollWidth).toBeLessThanOrEqual(overflow.clientWidth + 1);
}

test('public experience loads local fonts and passes the critical accessibility gate', async ({ page }) => {
    await page.goto('/');
    await page.evaluate(() => document.fonts.ready);

    expect(await page.evaluate(() => document.fonts.check('400 14px Manrope'))).toBe(true);
    expect(await page.evaluate(() => document.fonts.check('600 24px "Cormorant Garamond"'))).toBe(true);
    await expectNoSeriousAccessibilityViolations(page);
});

test('keyboard focus uses a visible two-tone treatment', async ({ page }) => {
    await page.goto('/login');
    const email = page.getByLabel(/^email$/i);

    await email.focus();
    await email.press('Tab');
    await page.keyboard.press('Shift+Tab');
    await expectTwoToneFocus(email);
});

for (const role of ['admin', 'staff']) {
    test(`${role} mobile drawer closes with Escape and restores focus`, async ({ page }) => {
        const viewport = page.viewportSize();

        test.skip(!viewport || viewport.width >= 1024, 'The workspace drawer is replaced by the persistent desktop sidebar.');
        await login(page, role);

        const opener = page.getByRole('button', { name: /open account navigation/i });
        const drawer = page.locator('#mobile-workspace-navigation');
        const firstDrawerControl = drawer.locator('a, button, input, select, textarea, [tabindex]').first();

        await expectTwoToneFocus(opener);
        await opener.press('Enter');
        await expect(drawer).toBeVisible();
        await expect(opener).toHaveAttribute('aria-expanded', 'true');
        await expect(firstDrawerControl).toBeFocused();

        await page.keyboard.press('Escape');
        await expect(drawer).toBeHidden();
        await expect(opener).toHaveAttribute('aria-expanded', 'false');
        await expect(opener).toBeFocused();
    });
}

test('admin workspace has unique controls and only exposes Clear filters when active', async ({ page }) => {
    await login(page, 'admin');
    await expectNoSeriousAccessibilityViolations(page);

    await page.goto('/admin/customers');
    await expectUniqueFormRelationships(page);
    await expect(page.getByRole('link', { name: /clear filters/i })).toHaveCount(0);

    await page.goto('/admin/customers?q=__no_matching_customer__');
    await expect(page.getByRole('link', { name: /clear filters/i })).toHaveCount(1);
});

test('customer mobile navigation has one canonical destination set', async ({ page }) => {
    await login(page, 'customer');

    const viewport = page.viewportSize();

    if (viewport && viewport.width < 1024) {
        const dock = page.locator('[data-customer-mobile-dock], .casa-mobile-dock').first();
        await expect(dock).toBeVisible();
        await expect(page.getByRole('button', { name: /open (?:menu|navigation)/i })).toHaveCount(0);
        await expect(dock.getByRole('link')).toHaveCount(3);

        const undersized = await dock.getByRole('link').evaluateAll((links) => links
            .filter((link) => {
                const box = link.getBoundingClientRect();
                const fontSize = Number.parseFloat(getComputedStyle(link).fontSize);

                return box.height < 44 || box.width < 44 || fontSize < 14;
            })
            .map((link) => link.textContent?.trim()));
        expect(undersized).toEqual([]);
    }

    await expectUniqueFormRelationships(page);
    await expectNoSeriousAccessibilityViolations(page);
});

test('staff workspace uses the staff route and exposes only its scoped customer workspace', async ({ page }) => {
    await login(page, 'staff');
    await page.goto('/staff/customers');
    await expect(page).toHaveURL(/\/staff\/customers(?:\?|$)/);
    await expectUniqueFormRelationships(page);
    await expectNoSeriousAccessibilityViolations(page);
});

for (const workspace of [
    { role: 'admin', path: '/admin/appointments' },
    { role: 'staff', path: '/staff/customers' },
    { role: 'customer', path: '/customer/appointments' },
]) {
    test(`${workspace.role} workspace keeps representative text, targets, and overflow within design limits`, async ({ page }) => {
        await login(page, workspace.role);
        await page.goto(workspace.path);
        await expectRepresentativeTargetsAtLeast44Pixels(page);
        await expectMeaningfulTextAtLeast14Pixels(page);
        await expectNoDocumentOverflow(page);
    });
}

for (const workspace of [
    { role: 'admin', path: '/admin/appointments' },
    { role: 'staff', path: '/staff/appointments' },
]) {
    test(`${workspace.role} booking legend is complete and omits retired request language`, async ({ page }) => {
        await login(page, workspace.role);
        await page.goto(workspace.path);
        await expectActiveStatusOptions(page);
        await expectStatusLegend(page, bookingLegendLabels);

        if (workspace.role === 'admin') {
            const availabilityButton = page.getByRole('button', { name: 'Availability', exact: true });

            await availabilityButton.click();
            await expect(availabilityButton).toHaveAttribute('aria-pressed', 'true');
            await expectStatusLegend(page, availabilityLegendLabels);
        }
    });
}

test('customer calendar legend is complete and omits retired request language', async ({ page }) => {
    await login(page, 'customer');
    await expectActiveStatusOptions(page, '[data-customer-appointment-calendar]');
    await expectStatusLegend(page, customerLegendLabels, 'p:visible');
});

test('reduced-motion preference removes smooth scrolling and long transitions or animations', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/');

    const motion = await page.evaluate(() => {
        const durationToMilliseconds = (duration) => {
            const value = Number.parseFloat(duration);

            return duration.trim().endsWith('ms') ? value : value * 1000;
        };
        const maximumDuration = (durations) => Math.max(...durations.split(',').map(durationToMilliseconds));
        const transitionTarget = document.querySelector('.casa-button-primary');
        const animationTarget = document.querySelector('.casa-page-loading__dot');
        const transitionStyle = transitionTarget ? getComputedStyle(transitionTarget) : null;
        const animationStyle = animationTarget ? getComputedStyle(animationTarget) : null;

        return {
            animationDuration: animationStyle ? maximumDuration(animationStyle.animationDuration) : null,
            animationIterationCount: animationStyle?.animationIterationCount ?? null,
            scrollBehavior: getComputedStyle(document.documentElement).scrollBehavior,
            transitionDuration: transitionStyle ? maximumDuration(transitionStyle.transitionDuration) : null,
        };
    });

    expect(motion.scrollBehavior).toBe('auto');
    expect(motion.transitionDuration).not.toBeNull();
    expect(motion.transitionDuration).toBeLessThanOrEqual(0.011);
    expect(motion.animationDuration).not.toBeNull();
    expect(motion.animationDuration).toBeLessThanOrEqual(0.011);
    expect(motion.animationIterationCount).toBe('1');
});

test('customer workspace reflows without clipping at 200 percent text zoom', async ({ page }) => {
    await login(page, 'customer');

    const baselineFontSize = await page.evaluate(() => Number.parseFloat(getComputedStyle(document.documentElement).fontSize));
    await page.evaluate(() => {
        document.documentElement.style.setProperty('font-size', '200%', 'important');
    });
    await expect.poll(() => page.evaluate(() => Number.parseFloat(getComputedStyle(document.documentElement).fontSize)))
        .toBeGreaterThanOrEqual(baselineFontSize * 1.99);

    await expectNoDocumentOverflow(page);

    const clippedRegions = await page.evaluate(() => {
        const viewportWidth = document.documentElement.clientWidth;

        return [...document.querySelectorAll('[data-page-header], [data-page-content], [data-customer-mobile-dock]')]
            .filter((element) => {
                const style = getComputedStyle(element);
                const box = element.getBoundingClientRect();

                return style.display !== 'none' && style.visibility !== 'hidden' && box.width > 0 && box.height > 0;
            })
            .filter((element) => {
                const box = element.getBoundingClientRect();

                return box.left < -1 || box.right > viewportWidth + 1;
            })
            .map((element) => ({
                left: Number(element.getBoundingClientRect().left.toFixed(2)),
                selector: element.hasAttribute('data-page-header')
                    ? '[data-page-header]'
                    : element.hasAttribute('data-page-content')
                        ? '[data-page-content]'
                        : '[data-customer-mobile-dock]',
                right: Number(element.getBoundingClientRect().right.toFixed(2)),
                viewportWidth,
            }));
    });

    expect(clippedRegions, JSON.stringify(clippedRegions, null, 2)).toEqual([]);
});
