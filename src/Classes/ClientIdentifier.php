<?php

declare(strict_types=1);

/**
 * Audiotracks for Contao Open Source CMS Copyright (c) 2023 Web ex Machina.
 *
 * @category ContaoBundle
 *
 * @see     https://github.com/Web-Ex-Machina/contao-audiotracks/
 */

namespace WEM\AudioTracksBundle\Classes;

use Symfony\Component\HttpFoundation\RequestStack;
use WEM\UtilsBundle\Classes\Encryption;

/**
 * Identifies a visitor (likes, listening sessions) without storing his IP address
 * in clear.
 *
 * Two modes (audio_tracks.identifier), both deterministic (the same IP always
 * gives the same identifier, so it can be used in a WHERE clause):
 *  - "encryption" (default): the IP is encrypted with the "wem.encryption_util"
 *    service (contao-utils), it can be decrypted with the key;
 *  - "hmac": the IP is hashed with a keyed HMAC-SHA256, nobody can get the IP back,
 *    even with the key (it can only be tested for a known IP).
 * The key is the "wem_contao_encryption.encryption_key" parameter (kernel.secret by
 * default): if it changes, the identifiers already stored cannot be matched anymore.
 */
class ClientIdentifier
{
    public const MODE_ENCRYPTION = 'encryption';

    public const MODE_HMAC = 'hmac';

    private const UNKNOWN = 'unknown';

    private string|null $current = null;

    public function __construct(
        private readonly Encryption $encryption,
        private readonly string $mode = self::MODE_ENCRYPTION,
        private readonly string $key = '',
        private readonly RequestStack|null $requestStack = null,
    ) {
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * The identifier of the current visitor.
     */
    public function get(): string
    {
        return $this->current ??= $this->fromIp((string) $this->requestStack?->getCurrentRequest()?->getClientIp());
    }

    /**
     * The identifier matching an IP address.
     */
    public function fromIp(string $ip): string
    {
        $ip = '' !== $ip ? $ip : self::UNKNOWN;

        if (self::MODE_HMAC === $this->mode) {
            return hash_hmac('sha256', $ip, 'wem_audiotracks|'.$this->key);
        }

        return (string) $this->encryption->encrypt_b64($ip);
    }

    /**
     * Check if a stored value is a hash (hmac mode) or a purged placeholder: it
     * cannot be converted.
     */
    public function isHashOrPurged(string $value): bool
    {
        return 1 === preg_match('/^[0-9a-f]{64}$/', $value) || str_starts_with($value, 'purged-');
    }

    /**
     * The identifier, in the current mode, of a value stored by a previous version or
     * mode (raw IP or encrypted IP). Null if the value is already an identifier of
     * the current mode, or cannot be converted.
     */
    public function toCurrent(string $stored): string|null
    {
        if ('' === $stored || $this->isHashOrPurged($stored)) {
            return null;
        }

        if ($this->isRawIp($stored)) {
            return $this->fromIp($stored);
        }

        // An encrypted identifier is already the current one in the encryption mode
        if (self::MODE_ENCRYPTION === $this->mode) {
            return null;
        }

        try {
            $ip = $this->encryption->decrypt_b64($stored);
        } catch (\Throwable) {
            return null;
        }

        return \is_string($ip) && (self::UNKNOWN === $ip || $this->isRawIp($ip)) ? $this->fromIp($ip) : null;
    }

    /**
     * Check if a stored value is a raw IP address, not an identifier yet.
     */
    public function isRawIp(string $value): bool
    {
        return false !== filter_var($value, FILTER_VALIDATE_IP);
    }
}
