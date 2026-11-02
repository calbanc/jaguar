// Loaded at runtime from an untracked deployment configuration.
export async function loadMapboxToken(): Promise<string> {
  try {
    const response = await fetch(new URL('mapbox-config.json', document.baseURI), { cache: 'no-store' });
    if (!response.ok) return '';
    const config = await response.json();
    const token = typeof config?.mapboxToken === 'string' ? config.mapboxToken.trim() : '';
    // Only public browser tokens belong in frontend configuration.
    return token.startsWith('pk.') ? token : '';
  } catch {
    return '';
  }
}
