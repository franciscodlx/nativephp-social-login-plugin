<?php

namespace Franciscodlxdev\SocialLogin\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * post_compile hook: adds the app's redirect schemes to the intent-filter of
 * the plugin's redirect activity in the generated AndroidManifest.xml. The
 * schemes are app-specific, so the static plugin manifest cannot list them.
 */
class AndroidRedirectSchemes extends Command
{
    protected $signature = 'social-login:android-redirect-schemes
                            {--platform=} {--build-path=} {--plugin-path=} {--app-id=} {--config=} {--plugins=}';

    protected $description = 'Add the social login redirect schemes to the Android manifest';

    private const ACTIVITY = 'dev.franciscodlx.nativephp.sociallogin.AuthRedirectActivity';

    private const MARKER = '<!-- franciscodlxdev/nativephp-social-login: redirect schemes -->';

    public function handle(): int
    {
        if ($this->option('platform') !== 'android') {
            return self::SUCCESS;
        }

        $schemes = array_values(array_filter(
            (array) config('social-login.redirect_schemes', []),
            static fn ($scheme) => is_string($scheme) && preg_match('/^[a-z][a-z0-9+.-]*$/', $scheme) === 1,
        ));
        if ($schemes === []) {
            $this->warn('SOCIAL_LOGIN_REDIRECT_SCHEMES is empty: Android sign-ins will fail with INVALID_CONFIG.');

            return self::SUCCESS;
        }

        $manifest = $this->findManifest((string) $this->option('build-path'));
        if ($manifest === null) {
            $this->error('AndroidManifest.xml with the social login redirect activity not found.');

            return self::FAILURE;
        }

        File::put($manifest, self::ensureQueries(self::inject(File::get($manifest), $schemes)));
        $this->info('Social login redirect schemes: '.implode(', ', $schemes));

        return self::SUCCESS;
    }

    /** Replaces the redirect activity entry with one carrying an intent-filter for `$schemes`. */
    public static function inject(string $xml, array $schemes): string
    {
        $data = implode("\n", array_map(
            static fn (string $scheme) => "                <data android:scheme=\"{$scheme}\" />",
            $schemes,
        ));
        $activity = '        '.self::MARKER."\n"
            ."        <activity\n"
            .'            android:name="'.self::ACTIVITY."\"\n"
            ."            android:exported=\"true\"\n"
            ."            android:theme=\"@android:style/Theme.Translucent.NoTitleBar\">\n"
            ."            <intent-filter>\n"
            ."                <action android:name=\"android.intent.action.VIEW\" />\n"
            ."                <category android:name=\"android.intent.category.DEFAULT\" />\n"
            ."                <category android:name=\"android.intent.category.BROWSABLE\" />\n"
            .$data."\n"
            ."            </intent-filter>\n"
            .'        </activity>';

        $quoted = preg_quote(self::ACTIVITY, '/');
        // Our earlier injection (marker + activity), or the plain entry the plugin manifest produced.
        $patterns = [
            '/[ \t]*'.preg_quote(self::MARKER, '/').'\s*<activity\b[^>]*'.$quoted.'.*?<\/activity>/s',
            '/[ \t]*<activity\b[^>]*android:name="'.$quoted.'"[^>]*\/>/s',
            '/[ \t]*<activity\b[^>]*android:name="'.$quoted.'"[^>]*>.*?<\/activity>/s',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $xml) === 1) {
                return preg_replace($pattern, $activity, $xml, 1);
            }
        }

        return preg_replace('/<\/application>/', $activity."\n    </application>", $xml, 1);
    }

    /** Android 11+ only lets the plugin see browsers it declares in <queries>. */
    public static function ensureQueries(string $xml): string
    {
        if (str_contains($xml, 'android.support.customtabs.action.CustomTabsService')) {
            return $xml;
        }
        $queries = "    <queries>\n"
            ."        <intent>\n"
            ."            <action android:name=\"android.support.customtabs.action.CustomTabsService\" />\n"
            ."        </intent>\n"
            ."        <intent>\n"
            ."            <action android:name=\"android.intent.action.VIEW\" />\n"
            ."            <category android:name=\"android.intent.category.BROWSABLE\" />\n"
            ."            <data android:scheme=\"https\" />\n"
            ."        </intent>\n"
            ."    </queries>\n";

        return preg_replace('/(\s*<application\b)/', "\n".$queries.'$1', $xml, 1);
    }

    private function findManifest(string $buildPath): ?string
    {
        $candidates = [
            $buildPath.'/app/src/main/AndroidManifest.xml',
            base_path('nativephp/android/app/src/main/AndroidManifest.xml'),
        ];
        foreach ($candidates as $path) {
            if ($path !== '/app/src/main/AndroidManifest.xml' && File::exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
