<?php

namespace App\Service\Exception;

/**
 * A user mistake, not a system failure: the input never had a chance to become
 * a link. Extends the shortener exception so existing entry points keep working
 * with a single catch, while a form can still single this one out for a 422.
 */
class InvalidUrlException extends LinkShortenerException {}
