import { defineConfig } from '@playwright/test';

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://localhost:8001';

export default defineConfig({
    testDir: './tests/Browser',
    outputDir: './storage/testing/playwright-results',
    fullyParallel: false,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 2 : 0,
    reporter: process.env.CI ? [['html', { outputFolder: 'storage/testing/playwright-report', open: 'never' }], ['line']] : 'list',
    use: {
        baseURL,
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
        video: 'retain-on-failure',
    },
    projects: [
        { name: 'mobile-320', use: { viewport: { width: 320, height: 720 } } },
        { name: 'mobile-390', use: { viewport: { width: 390, height: 844 } } },
        { name: 'tablet-768', use: { viewport: { width: 768, height: 1024 }, reducedMotion: 'reduce' } },
        { name: 'desktop-1024', use: { viewport: { width: 1024, height: 768 } } },
        { name: 'desktop-1440', use: { viewport: { width: 1440, height: 900 } } },
    ],
});
