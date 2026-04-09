// Suppress benign ResizeObserver loop errors from polluting logs
window.addEventListener('error', (e) => {
    if (e.message === 'ResizeObserver loop completed with undelivered notifications.') {
        e.stopImmediatePropagation();
    }
});