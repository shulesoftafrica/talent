import Alpine from 'alpinejs';

window.Alpine = Alpine;

/**
 * Shared "fetch JSON on open" modal pattern, used by the AI Profile Review,
 * Job Coach, and Career Readiness modals. `url` may be a function so it can
 * depend on data only known at render time (e.g. a specific job's id).
 */
Alpine.data('aiModal', (url) => ({
    open: false,
    loading: false,
    data: null,
    error: null,

    async show() {
        this.open = true;
        if (this.data) return; // don't refetch if already loaded once
        this.loading = true;
        this.error = null;
        try {
            const resolvedUrl = typeof url === 'function' ? url() : url;
            const res = await fetch(resolvedUrl, { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error('request failed');
            this.data = await res.json();
        } catch (e) {
            this.error = 'Could not load this right now. Please try again.';
        } finally {
            this.loading = false;
        }
    },

    close() {
        this.open = false;
    },
}));

/**
 * Career Readiness needs to be opened from multiple places on the same page
 * (the rail's "Grow Your Career" card and the applications empty state), so
 * it lives in a global store instead of a per-component aiModal() instance.
 */
Alpine.store('careerReadiness', {
    open: false,
    loading: false,
    data: null,
    error: null,

    async show() {
        this.open = true;
        if (this.data) return;
        this.loading = true;
        this.error = null;
        try {
            const res = await fetch('/app/ai/career-readiness', { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error('request failed');
            this.data = await res.json();
        } catch (e) {
            this.error = 'Could not load this right now. Please try again.';
        } finally {
            this.loading = false;
        }
    },

    close() {
        this.open = false;
    },

    async enroll(trainingId) {
        const training = this.data?.recommendations?.find((r) => r.id === trainingId);
        if (!training || training.enrolled) return;
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            const res = await fetch(`/app/trainings/${trainingId}/enroll`, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
            });
            if (!res.ok) throw new Error('request failed');
            training.enrolled = true;
        } catch (e) {
            // leave the button as-is; the user can retry
        }
    },
});

Alpine.start();
