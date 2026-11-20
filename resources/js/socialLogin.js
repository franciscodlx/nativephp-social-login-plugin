// Bridge calls for web-view apps (Livewire, Inertia + Vue/React). The PHP
// facade is the recommended API: it also keeps state, PKCE and nonce.
// These only open and close the browser; listen for
// Franciscodlxdev\SocialLogin\Events\AuthSessionCompleted / AuthSessionFailed.

async function bridgeCall(method, params = {}) {
  const response = await fetch('/_native/api/call', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ method, params }),
  });
  return response.json();
}

/** Opens `url` in the system browser and waits for a redirect to `callbackUri`. */
export function openAuthSession({ url, callbackUri, prefersEphemeralSession = false }) {
  return bridgeCall('SocialLogin.OpenAuthSession', { url, callbackUri, prefersEphemeralSession });
}

/** Dismisses the browser of the sign-in in progress. */
export function cancelAuthSession() {
  return bridgeCall('SocialLogin.CancelAuthSession');
}

export const Events = {
  AuthSessionCompleted: 'Franciscodlxdev\\SocialLogin\\Events\\AuthSessionCompleted',
  AuthSessionFailed: 'Franciscodlxdev\\SocialLogin\\Events\\AuthSessionFailed',
};
