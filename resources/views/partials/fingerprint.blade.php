<script>
/**
 * Collect device fingerprint components and return a base64url-encoded JSON string.
 * Components: ua, screen (WxH), lang, tz, platform.
 *
 * Usage:
 *   const fp = collectFingerprint();
 *   // Pass fp as the 'fp' query param on magic-link verify, or
 *   // include fp_components in the /request POST body.
 *
 * @returns {string} base64url-encoded JSON of { ua, screen, lang, tz, platform }
 */
function collectFingerprint() {
    var components = {
        ua:       navigator.userAgent || '',
        screen:   screen.width + 'x' + screen.height,
        lang:     navigator.language || '',
        tz:       Intl.DateTimeFormat().resolvedOptions().timeZone || '',
        platform: navigator.platform || ''
    };

    var json    = JSON.stringify(components);
    var encoded = btoa(json).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');

    return encoded;
}
</script>
