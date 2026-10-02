<?php

declare(strict_types=1);

/**
 * Audiotracks for Contao Open Source CMS
 * Copyright (c) 2023 Web ex Machina
 *
 * @category ContaoBundle
 * @package  Web-Ex-Machina/contao-audiotracks
 * @author   Web ex Machina <contact@webexmachina.fr>
 * @link     https://github.com/Web-Ex-Machina/contao-audiotracks/
 */

namespace WEM\AudioTracksBundle\Classes;

use Contao\Environment;
use WEM\UtilsBundle\Classes\Encryption;

/**
 * Identifies a visitor (likes, listening sessions) without storing his IP address in clear.
 *
 * The IP is encrypted with the "wem.encryption_util" service (contao-utils). This encryption is
 * deterministic, the same IP always gives the same identifier, so it can be used in a WHERE clause.
 * The key is the "wem_contao_encryption.encryption_key" parameter (kernel.secret by default):
 * if it changes, the identifiers already stored cannot be matched anymore.
 */
class ClientIdentifier
{
    private ?string $current = null;

    public function __construct(private readonly Encryption $encryption)
    {
    }

    /**
     * The identifier of the current visitor.
     */
    public function get(): string
    {
        return $this->current ??= $this->fromIp((string) Environment::get('ip'));
    }

    /**
     * The identifier matching an IP address.
     */
    public function fromIp(string $ip): string
    {
        return (string) $this->encryption->encrypt_b64('' !== $ip ? $ip : 'unknown');
    }

    /**
     * Check if a stored value is a raw IP address, not an identifier yet.
     */
    public function isRawIp(string $value): bool
    {
        return false !== filter_var($value, FILTER_VALIDATE_IP);
    }
}
