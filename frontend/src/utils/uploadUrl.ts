/**
 * Backend base URL for direct browser requests (e.g. img src).
 * API calls use the dev server proxy; upload URLs must point at the PHP server.
 */
export const BACKEND_BASE =
  (typeof import.meta !== "undefined" && (import.meta as any).env?.VITE_BACKEND_BASE) ||
  (typeof import.meta !== "undefined" && (import.meta as any).env?.VITE_API_TARGET) ||
  (typeof window !== "undefined" ? window.location.origin : "http://localhost:8000");

function normalizeUploadPath(raw: string): string {
  const unified = raw.replace(/\\/g, "/").trim();
  const uploadsIndex = unified.toLowerCase().indexOf("/uploads/");

  if (uploadsIndex >= 0) {
    return unified.slice(uploadsIndex);
  }

  if (unified.toLowerCase().startsWith("uploads/")) {
    return `/${unified}`;
  }

  return unified;
}

/**
 * Returns a URL suitable for img src or window.open.
 * If url is relative (e.g. /uploads/...), prefixes with BACKEND_BASE.
 */
export function resolveUploadUrl(url: string | null | undefined): string {
  if (!url || typeof url !== "string") return "";
  const trimmed = normalizeUploadPath(url);
  if (!trimmed) return "";

  const base = BACKEND_BASE.replace(/\/$/, "");

  if (trimmed.startsWith("http://") || trimmed.startsWith("https://")) {
    try {
      const parsed = new URL(trimmed);
      if (parsed.pathname.toLowerCase().startsWith("/uploads/")) {
        return `${base}${parsed.pathname}`;
      }
      return trimmed;
    } catch {
      return trimmed;
    }
  }

  return trimmed.startsWith("/") ? `${base}${trimmed}` : `${base}/${trimmed}`;
}
