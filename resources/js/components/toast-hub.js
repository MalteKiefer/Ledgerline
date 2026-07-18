// Toast hub rendered once in the layout; listens for `ll-toast` events.
export default (labels = {}) => ({
    items: [],
    init() {
        window.addEventListener('ll-toast', (e) => this.push(e.detail));
    },
    push({ message, url }) {
        const id = Date.now() + Math.random();
        this.items.push({ id, message, url, linkLabel: labels.link || '' });
        setTimeout(() => this.dismiss(id), 6000);
    },
    dismiss(id) {
        this.items = this.items.filter((i) => i.id !== id);
    },
});
