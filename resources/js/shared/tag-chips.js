// Pure tag-chip transforms. Tags are edited as removable badge chips but stored as a
// comma-joined string (`tagsValue`) so every module's existing load (`tags.join(', ')`)
// and save (`tagsValue.split(',')`) is unchanged. These helpers back both the shared
// zkModule mixin and the standalone files component.

/** The trimmed, non-empty tags parsed from a comma-joined string. */
export function parseTags(value) {
    return (value || '').split(',').map((s) => s.trim()).filter(Boolean);
}

/** Join a tag array back into the canonical comma-joined string. */
export function joinTags(list) {
    return list.join(', ');
}

/** Append the draft's tag(s) (comma-splittable) to the value, skipping duplicates. */
export function addTags(value, draft) {
    const list = parseTags(value);
    for (const p of parseTags(draft)) if (! list.includes(p)) list.push(p);
    return joinTags(list);
}

/** Remove one tag from the value. */
export function removeTagFrom(value, tag) {
    return joinTags(parseTags(value).filter((t) => t !== tag));
}

/** Drop the last chip (backspace on an empty draft). */
export function popTag(value) {
    const list = parseTags(value);
    list.pop();
    return joinTags(list);
}
