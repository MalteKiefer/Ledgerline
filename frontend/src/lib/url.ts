// A URL that arrives with user data — a contact's website from an imported
// vCard or a CardDAV replica, a business partner's URL — must never be bound to
// href unhandled: "javascript:..." in that field executes in the app's own
// origin the moment somebody clicks the link. Only schemes that navigate are
// allowed through; anything else yields undefined, which renders the anchor
// inert rather than dangerous.
const NAVIGABLE = /^(?:https?:|mailto:|tel:)/i;

export function safeHref(value: string | null | undefined): string | undefined {
  const raw = (value ?? '').trim();
  if (!raw) return undefined;
  // A bare "example.com" is what people actually type into a website field.
  if (/^[\w.-]+\.[a-z]{2,}(?:[/?#].*)?$/i.test(raw)) return `https://${raw}`;
  return NAVIGABLE.test(raw) ? raw : undefined;
}
