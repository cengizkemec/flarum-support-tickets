/**
 * Turkish characters that have no ASCII decomposition (or differ from it),
 * so slugs stay valid and readable.
 */
const TURKISH_CHAR_MAP: Record<string, string> = {
  ç: 'c',
  Ç: 'c',
  ğ: 'g',
  Ğ: 'g',
  ı: 'i',
  İ: 'i',
  ö: 'o',
  Ö: 'o',
  ş: 's',
  Ş: 's',
  ü: 'u',
  Ü: 'u',
};

/**
 * Same maximum the PHP side uses (see src/Support/TicketSlug.php) so a slug
 * generated here matches one generated on the server.
 */
export const SLUG_MAX_LENGTH = 80;

/**
 * Build a URL slug from a human readable value, handling Turkish characters.
 */
export function slugify(value: string, fallback = ''): string {
  let slug = (value || '')
    .replace(/[çÇğĞıİöÖşŞüÜ]/g, (char) => TURKISH_CHAR_MAP[char] || char)
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

  if (slug.length > SLUG_MAX_LENGTH) {
    slug = slug.slice(0, SLUG_MAX_LENGTH).replace(/-+$/g, '');
  }

  return slug || fallback;
}

/**
 * Turn a label into a safe machine name for a custom form field
 * ("Your Minecraft server" -> "your_minecraft_server").
 */
export function fieldName(value: string): string {
  return slugify(value)
    .replace(/-/g, '_')
    .slice(0, 60);
}
