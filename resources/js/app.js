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

// x-capitalize: upper-cases the first letter of every word as the user types, so names and
// street lines are entered consistently. Mirrors the server-side CapitalizesWords trait,
// which is what actually guarantees the stored value.
document.addEventListener('alpine:init', () => {
    window.Alpine.directive('capitalize', (el) => {
        el.addEventListener('input', () => {
            const next = el.value.replace(/(?<=^|[\s\-'’.])\p{L}/gu, (char) => char.toUpperCase());

            // Bail before re-dispatching, or the event we fire below loops forever.
            if (next === el.value) {
                return;
            }

            const start = el.selectionStart;
            const end = el.selectionEnd;

            el.value = next;
            el.setSelectionRange(start, end);
            el.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });
});