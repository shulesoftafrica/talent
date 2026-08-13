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

/**
 * The Help & Feedback bubble (see resources/views/components/feedback-bubble.blade.php).
 * `config` carries everything that needs a translated string or a route URL
 * — all of that has to come from Blade/`__()`, so the JS side stays purely
 * behavioural and never hardcodes copy.
 */
Alpine.data('feedbackBubble', (config) => ({
    open: false,
    step: 'choose',
    category: null,
    subcategory: null,
    sentiment: null,
    message: '',
    submitting: false,
    error: null,
    history: [],
    historyLoading: false,
    historyLoaded: false,

    openChooser() {
        this.open = true;
        this.step = 'choose';
        this.loadHistory();
    },

    close() {
        this.open = false;
        this.step = 'choose';
        this.category = null;
        this.subcategory = null;
        this.sentiment = null;
        this.message = '';
        this.error = null;
    },

    chooseCategory(category) {
        this.category = category;
        this.subcategory = null;
        this.sentiment = null;
        this.message = '';
        this.error = null;
        this.step = 'form';
    },

    get subcategoryOptions() {
        return config.subcategories[this.category] || [];
    },

    get messageLabel() {
        return config.strings[this.category]?.messageLabel || '';
    },

    get messagePlaceholder() {
        return config.strings[this.category]?.messagePlaceholder || '';
    },

    statusClass(status) {
        return {
            new: 'bg-ttn-subtle text-ttn-text2',
            in_review: 'bg-ttn-amber-bg text-ttn-amber-text',
            responded: 'bg-ttn-primary-light text-ttn-primary-dark',
            resolved: 'bg-ttn-primary-light text-ttn-primary-dark',
        }[status] || 'bg-ttn-subtle text-ttn-text2';
    },

    async loadHistory() {
        if (this.historyLoaded) return;
        this.historyLoading = true;
        try {
            const res = await fetch(config.historyEndpoint, { headers: { Accept: 'application/json' } });
            const data = await res.json();
            this.history = data.items || [];
            this.historyLoaded = true;
        } catch (e) {
            // leave history empty; the chooser step still works without it
        } finally {
            this.historyLoading = false;
        }
    },

    async submit() {
        this.error = null;

        if (['help', 'problem', 'idea'].includes(this.category) && !this.message.trim()) {
            this.error = config.errors.messageRequired;
            return;
        }

        this.submitting = true;
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            const res = await fetch(config.endpoint, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({
                    category: this.category,
                    subcategory: this.subcategory,
                    sentiment: this.category === 'feedback' ? this.sentiment : null,
                    message: this.message || null,
                    context_label: config.contextLabel,
                    context_path: config.contextPath,
                }),
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                this.error = data.message || config.errors.generic;
                return;
            }
            this.step = 'success';
            this.historyLoaded = false;
        } catch (e) {
            this.error = config.errors.generic;
        } finally {
            this.submitting = false;
        }
    },
}));

/**
 * The 👍/😐/👎 quick-rating prompt shown after specific candidate actions
 * (see resources/views/components/quick-rating.blade.php). Deliberately
 * separate from feedbackBubble — it always submits sentiment=category
 * 'feedback' with a fixed subcategory identifying the triggering event, and
 * has its own tiny dismissed/thanked lifecycle instead of the bubble's
 * multi-step form.
 */
Alpine.data('quickRating', (config) => ({
    dismissed: false,
    thanked: false,
    askingWhy: false,
    why: '',
    pendingSentiment: null,

    async rate(sentiment) {
        if (sentiment === 'dislike') {
            this.askingWhy = true;
            this.pendingSentiment = sentiment;
            return;
        }
        await this.send(sentiment, null);
    },

    async sendWhy() {
        await this.send('dislike', this.why || null);
    },

    skipWhy() {
        this.askingWhy = false;
        this.thanked = true;
    },

    async send(sentiment, message) {
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            await fetch(config.endpoint, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({
                    category: 'feedback',
                    subcategory: config.eventKey,
                    sentiment,
                    message,
                    context_label: config.contextLabel,
                    context_path: config.contextPath,
                }),
            });
        } catch (e) {
            // best-effort; the prompt still closes so it never blocks the candidate
        } finally {
            this.askingWhy = false;
            this.thanked = true;
            this.$dispatch('quick-rating-sent');
        }
    },
}));

Alpine.start();
