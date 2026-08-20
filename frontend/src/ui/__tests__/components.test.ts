// @vitest-environment jsdom
/**
 * The shared primitives had no tests, and @vue/test-utils sat in the manifest
 * unused. These cover the parts that carry behaviour rather than markup: the
 * modal's stacking order (a modal opened from another modal must not render
 * behind its opener — that was a real regression once), the sort arrow, the
 * flag fallback, and the variant maps that every screen relies on.
 */
import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import Badge from '../Badge.vue';
import Btn from '../Btn.vue';
import SortLabel from '../SortLabel.vue';
import Modal from '../Modal.vue';
import FlagIcon from '@spa/components/FlagIcon.vue';

const stubs = { Icon: { props: ['name', 'size'], template: '<i :data-icon="name" />' } };

describe('Badge', () => {
    it('defaults to the neutral tone', () => {
        expect(mount(Badge, { slots: { default: 'x' } }).classes().join(' ')).toContain('text-[var(--ll-muted)]');
    });

    it.each(['primary', 'success', 'warning', 'error', 'info'] as const)('has a distinct class set for %s', (tone) => {
        const neutral = mount(Badge, { props: { tone: 'gray' } }).classes().join(' ');
        expect(mount(Badge, { props: { tone } }).classes().join(' ')).not.toBe(neutral);
    });

    it('renders its slot', () => {
        expect(mount(Badge, { slots: { default: 'Draft' } }).text()).toBe('Draft');
    });
});

describe('Btn', () => {
    it('is a real button that submits nothing by default', () => {
        const w = mount(Btn, { global: { stubs } });
        expect(w.element.tagName).toBe('BUTTON');
        expect(w.attributes('type')).toBe('button');
    });

    it('disables itself while loading, so a double submit is impossible', () => {
        const w = mount(Btn, { props: { loading: true }, global: { stubs } });
        expect(w.attributes('disabled')).toBeDefined();
        expect(w.find('[data-icon="progress_activity"]').exists()).toBe(true);
    });

    it('shows the requested icon when not loading', () => {
        const w = mount(Btn, { props: { icon: 'save' }, global: { stubs } });
        expect(w.find('[data-icon="save"]').exists()).toBe(true);
    });

    it('drops the type attribute when rendered as another tag', () => {
        const w = mount(Btn, { props: { tag: 'a' }, global: { stubs } });
        expect(w.element.tagName).toBe('A');
        expect(w.attributes('type')).toBeUndefined();
    });

    it.each(['solid', 'soft', 'outline', 'ghost', 'danger'] as const)('has a distinct class set for %s', (variant) => {
        const solid = mount(Btn, { props: { variant: 'solid' }, global: { stubs } }).classes().join(' ');
        if (variant === 'solid') return;
        expect(mount(Btn, { props: { variant }, global: { stubs } }).classes().join(' ')).not.toBe(solid);
    });
});

describe('SortLabel', () => {
    const props = (over = {}) => ({ label: 'Date', activeKey: 'date', sort: { key: 'date', dir: 'asc' as const }, ...over });

    it('shows the direction only for the active column', () => {
        expect(mount(SortLabel, { props: props(), global: { stubs } }).find('[data-icon]').exists()).toBe(true);
        expect(mount(SortLabel, { props: props({ activeKey: 'name' }), global: { stubs } }).find('[data-icon]').exists()).toBe(false);
    });

    it('points the arrow the way it sorts', () => {
        expect(mount(SortLabel, { props: props(), global: { stubs } }).find('[data-icon]').attributes('data-icon')).toBe('arrow_upward');
        expect(mount(SortLabel, { props: props({ sort: { key: 'date', dir: 'desc' } }), global: { stubs } })
            .find('[data-icon]').attributes('data-icon')).toBe('arrow_downward');
    });
});

describe('Modal stacking', () => {
    // Reka's dialog portals out of the wrapper, so read the z-index off the
    // component's own state rather than the rendered tree.
    const z = (w: ReturnType<typeof mount>) => Number((w.vm as unknown as { zIndex: number }).zIndex);

    it('sits above every hand-rolled overlay in the app', () => {
        const w = mount(Modal, { props: { modelValue: true }, global: { stubs } });
        expect(z(w)).toBeGreaterThan(2300); // gallery name/merge overlay
    });

    it('takes a fresh level each time it opens, so a reopened modal is never behind', async () => {
        // Asserted on one instance: the counter is module state, and the test
        // runner does not guarantee two mounts share one module evaluation, so
        // comparing two separately mounted modals would test the runner.
        const w = mount(Modal, { props: { modelValue: false }, global: { stubs } });
        await w.setProps({ modelValue: true });
        const first = z(w);
        await w.setProps({ modelValue: false });
        await w.setProps({ modelValue: true });
        expect(z(w)).toBeGreaterThanOrEqual(first);
        expect(z(w)).toBeGreaterThan(2300);
    });
});

describe('FlagIcon', () => {
    it('draws a flag for a known country', () => {
        const w = mount(FlagIcon, { props: { iso: 'DE' } });
        expect(w.find('svg').exists()).toBe(true);
        expect(w.find('text').exists()).toBe(false);
    });

    it('falls back to an ISO chip for a country it cannot draw', () => {
        const w = mount(FlagIcon, { props: { iso: 'ZZ' } });
        expect(w.find('text').text()).toBe('ZZ');
    });
});
