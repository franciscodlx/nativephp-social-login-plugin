# franciscodlxdev/nativephp-social-login

<p align="center"><img src="https://raw.githubusercontent.com/franciscodlx/nativephp-social-login/main/.github/assets/demo.gif" width="260" alt="Demo: iniciar sesión en el navegador del sistema y volver a la app"></p>

Login social para [NativePHP Mobile](https://nativephp.com) (iOS, Android) con OAuth 2.0 + PKCE en el navegador del sistema: `ASWebAuthenticationSession` en iOS y Custom Tabs en Android. Sin WebView y sin `client_secret` en la app. Incluye LinkedIn, X, Mercado Libre, Twitch, GitHub, Discord, Slack, Strava, Telegram y TikTok.

Es la versión para NativePHP de la familia de [React Native](https://github.com/franciscodlx/rn-social-login), [Flutter](https://github.com/franciscodlx/flutter_social_login), [Capacitor](https://github.com/franciscodlx/capacitor-social-login) y [NativeScript](https://github.com/franciscodlx/nativescript-social-login): mismo flujo, mismos códigos de error.

## Compatibilidad

| Paquete | NativePHP Mobile | PHP   | Laravel      | iOS    | Android        |
| ------- | ---------------- | ----- | ------------ | ------ | -------------- |
| 0.1.x   | 4.8 (probado), 3 | ≥ 8.3 | 11, 12, 13   | ≥ 15.0 | ≥ 7.0 (API 24) |

## Instalación

```sh
composer require franciscodlxdev/nativephp-social-login
php artisan native:plugin:register franciscodlxdev/nativephp-social-login
```

En `.env`, los esquemas de tus redirects (separados por coma):

```dotenv
SOCIAL_LOGIN_REDIRECT_SCHEMES=miapp
```

- **iOS**: nada más. `ASWebAuthenticationSession` recibe `miapp://…` sin registrar el esquema.
- **Android**: un hook `post_compile` agrega esos esquemas al `intent-filter` de la actividad de redirect del plugin, más las `<queries>` para encontrar navegadores en Android 11+. Vuelve a compilar (`php artisan native:run`) cuando cambies la lista. Si falta un esquema, `signIn` falla con `INVALID_CONFIG` en lugar de dejar el navegador abierto sin regreso.

Usa un esquema propio de tu app, distinto de `NATIVEPHP_DEEPLINK_SCHEME`: ese lo atiende NativePHP para navegar entre pantallas.

## Cómo funciona

El navegador abre y cierra fuera de PHP, así que el inicio de sesión tiene dos pasos:

1. `SocialLogin::signIn($provider, $options)` genera `state`, PKCE y `nonce`, guarda la solicitud pendiente en la caché del dispositivo y abre el navegador. Regresa enseguida.
2. Lo nativo responde con un evento. `AuthSessionCompleted` trae la URL del redirect, que pasas a `SocialLogin::complete($url)`. `AuthSessionFailed` trae el código de error, que pasas a `SocialLogin::fail($code, $message)`.

`complete()` solo acepta un redirect con el `state` de un inicio de sesión que la app abrió, y lo consume: un evento falsificado o repetido no puede inyectar un código.

## Uso

En una pantalla nativa (`NativeComponent`, NativePHP 4):

```php
use Franciscodlxdev\SocialLogin\AuthException;
use Franciscodlxdev\SocialLogin\Events\AuthSessionCompleted;
use Franciscodlxdev\SocialLogin\Events\AuthSessionFailed;
use Franciscodlxdev\SocialLogin\Facades\SocialLogin;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;

class Login extends NativeComponent
{
    public string $error = '';

    public function signInWithLinkedIn(): void
    {
        SocialLogin::signIn(SocialLogin::linkedin(), [
            'clientId' => 'TU_CLIENT_ID',
            'redirectUri' => 'https://api.miapp.com/auth/linkedin/callback', // lo ve LinkedIn
            'callbackUri' => 'miapp://callback', // donde vuelve la app
        ]);
    }

    #[On(AuthSessionCompleted::class)]
    public function completed(string $url): void
    {
        try {
            $result = SocialLogin::complete($url);
            // El código no le sirve a la app: tu backend lo cambia por el perfil.
            $profile = Http::post('https://api.miapp.com/auth/linkedin/exchange', $result->forBackend())
                ->json('profile');
        } catch (AuthException $e) {
            $this->error = "{$e->authCode}: {$e->getMessage()}";
        }
    }

    #[On(AuthSessionFailed::class)]
    public function failed(string $code, string $message = ''): void
    {
        $error = SocialLogin::fail($code, $message); // CANCELLED si el usuario cerró el navegador
        $this->error = "{$error->authCode}: {$error->getMessage()}";
    }
}
```

Con Livewire (vistas web) es igual, con `#[OnNative(AuthSessionCompleted::class)]`.

### Proveedores

| Proveedor             | Configuración                       | Canje del código                                   |
| --------------------- | ----------------------------------- | -------------------------------------------------- |
| LinkedIn              | `SocialLogin::linkedin()`           | Backend (secret)                                   |
| X                     | `SocialLogin::x()`                  | En el dispositivo: `'exchangeCode' => true` (PKCE) |
| Mercado Libre         | `SocialLogin::mercadolibre('MX')`   | Backend (secret + PKCE)                            |
| Twitch                | `SocialLogin::twitch()`             | Backend (secret, verifica `nonce`)                 |
| GitHub, Discord       | `SocialLogin::preset('github')`     | Backend (secret + PKCE)                            |
| Slack                 | `SocialLogin::preset('slack')`      | Backend (secret, verifica `nonce`)                 |
| Strava                | `SocialLogin::preset('strava')`     | Backend (secret)                                   |
| Telegram              | `SocialLogin::telegram()`           | Backend (secret + PKCE)                            |
| TikTok                | `SocialLogin::tiktok()`             | Backend (client key + secret)                      |

Cada uno acepta overrides: `SocialLogin::linkedin(['extraAuthParams' => ['prompt' => 'login']])`, o los endpoints de un servidor de pruebas. Las constantes de cada clase en `Franciscodlxdev\SocialLogin\Providers` (`LinkedIn::TOKEN_ENDPOINT`, `Twitch::JWKS_URI`, …) sirven para tu backend, y los `mapProfile` normalizan el perfil.

Para otro proveedor, crea un `ProviderConfig`:

```php
use Franciscodlxdev\SocialLogin\ProviderConfig;

$provider = new ProviderConfig(
    id: 'mi-idp',
    authorizationEndpoint: 'https://idp.example.com/authorize',
    defaultScopes: ['openid', 'profile', 'email'],
    tokenEndpoint: 'https://idp.example.com/token', // solo si admite clientes públicos
    userInfoEndpoint: 'https://idp.example.com/userinfo',
    issuer: 'https://idp.example.com',
    useNonce: true,
);
```

### Opciones de `signIn`

| Opción                    | Para qué                                                                                       |
| ------------------------- | ---------------------------------------------------------------------------------------------- |
| `clientId`, `redirectUri` | Obligatorias                                                                                   |
| `callbackUri`             | URL de la app cuando `redirectUri` es una página de tu backend que reenvía (proveedores que solo aceptan https) |
| `scopes`, `extraParams`   | Sustituyen los scopes por defecto / agregan parámetros                                         |
| `prefersEphemeralSession` | No comparte cookies con el navegador; en iOS además evita el aviso de confirmación             |
| `exchangeCode`            | Canje en el dispositivo como cliente público: `complete()` trae tokens y `profile`             |

`SignInResult` trae `code`, `codeVerifier`, `nonce`, `redirectUri`, `params` y, con `exchangeCode`, `accessToken`, `refreshToken`, `idToken`, `idTokenClaims` y `profile`. `forBackend()` arma el cuerpo para tu backend.

Desde JavaScript (Inertia, vistas web) solo abres y cierras el navegador con `resources/js/socialLogin.js`. El estado, PKCE y `nonce` viven en PHP, así que el flujo completo se hace con la facade.

## Errores normalizados

`AuthException` con `$e->authCode` en `CANCELLED | NETWORK | INVALID_CONFIG | PROVIDER_ERROR | NOT_SUPPORTED`, y `providerError`, `providerErrorDescription` y `status` cuando el proveedor respondió.

## Seguridad

- No hay `client_secret` en la app. PKCE (S256), `state` y `nonce` salen de `random_bytes()` en el dispositivo.
- Las solicitudes pendientes se guardan como JSON, nunca serializadas, y expiran en 10 minutos (`SOCIAL_LOGIN_PENDING_TTL`).
- En Android solo se acepta el redirect del inicio de sesión en curso (mismo esquema, host y ruta).
- El `id_token` se decodifica **sin verificar la firma**; verifícala en tu backend.
- La librería no guarda tokens. Usa `Native\Mobile\Facades\SecureStorage`.

## Probar sin credenciales

`examples/demo` corre todos los proveedores contra `tools/mock-idp` y los backends de `tools/`. Ver [CONTRIBUTING.md](../../CONTRIBUTING.md).

## Licencia

MIT
