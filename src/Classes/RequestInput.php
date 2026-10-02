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

use Contao\Input;
use Contao\InputEncodingMode;
use Symfony\Component\HttpFoundation\Request;

/**
 * A parameter of the URL (query string, or the "auto_item" of the route), read
 * from the request it is given.
 *
 * The value is cleaned like Contao does for Input::get(): the insert tags
 * ({{...}}) and the special characters are encoded, so it can safely be displayed
 * or given to the templates. It does not read the global request, so it can be
 * used (and tested) with any Request.
 */
final class RequestInput
{
    /**
     * @return array<mixed>|string|null Null if the parameter is not in the request
     */
    public static function get(Request $request, string $key): array|string|null
    {
        $value = Input::findGet($key, $request);

        if (null === $value) {
            return null;
        }

        // Contao answers with a 404 ("Unused arguments") if a route parameter (the
        // auto_item) of the page was not read with Input::get(): it is the only way to
        // mark it as used. The query string is not tracked.
        if ($request->attributes->has($key)) {
            Input::get($key);
        }

        return Input::encodeInputRecursive($value, InputEncodingMode::encodeAll);
    }
}
