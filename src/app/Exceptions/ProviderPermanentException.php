<?php

namespace App\Exceptions;

use RuntimeException;

// Постоянный сбой (неверный номер/email) — повторные попытки бессмысленны
class ProviderPermanentException extends RuntimeException {}
