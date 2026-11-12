<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Logo;

use RuntimeException;

/**
 * The configured logo could not be fetched or is not a picture the plugin
 * serves. {@see self::$reason} is one of the reason constants; it is stored
 * with the logo and shown to the operator.
 */
final class LogoRejected extends RuntimeException
{
    /** The address is not https, carries credentials or cannot be parsed. */
    public const ADDRESS = 'address';
    /** The host resolves to a loopback, private, link-local or other internal address. */
    public const INTERNAL = 'internal';
    /** No answer, a timeout, a TLS error or an HTTP status other than 200. */
    public const UNREACHABLE = 'unreachable';
    /** Too many redirects, or a redirect to an address that is not https. */
    public const REDIRECT = 'redirect';
    /** More than {@see LogoImage::MAX_BYTES}. */
    public const TOO_LARGE = 'too-large';
    /** Not a PNG, JPEG or WebP by its bytes (SVG included). */
    public const TYPE = 'type';
    /** Width or height outside {@see LogoImage::MIN_SIDE} … {@see LogoImage::MAX_SIDE}. */
    public const DIMENSIONS = 'dimensions';
    /** The bytes announce a picture that cannot be read. */
    public const BROKEN = 'broken';

    public const REASONS = [
        self::ADDRESS, self::INTERNAL, self::UNREACHABLE, self::REDIRECT,
        self::TOO_LARGE, self::TYPE, self::DIMENSIONS, self::BROKEN,
    ];

    public function __construct(public readonly string $reason)
    {
        parent::__construct('Logo rejected: ' . $reason);
    }
}
