// Suppress benign ResizeObserver loop errors from polluting logs
window.addEventListener('error', (e) => {
    if (e.message === 'ResizeObserver loop completed with undelivered notifications.') {
        e.stopImmediatePropagation();
    }
});

// Livewire navigate syncs <html> element attributes from the server response, which does
// not include the `dark` class applied by Flux via JavaScript. This causes a brief flash
// of light mode before livewire:navigated re-applies the class. The MutationObserver
// below re-applies dark mode as a microtask (before the next browser paint) whenever
// the class is stripped during navigation.
document.addEventListener('livewire:navigate', () => {
    const observer = new MutationObserver(() => {
        const stored = localStorage.getItem('flux.appearance');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const shouldBeDark = stored === 'dark' || (!stored && prefersDark);

        if (shouldBeDark) {
            document.documentElement.classList.add('dark');
        }

        observer.disconnect();
    });

    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
});