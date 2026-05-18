<?php

namespace App\Exceptions;

use RuntimeException;

// Временный сбой шлюза — задание уйдёт на повторную попытку
class ProviderUnavailableException extends RuntimeException {}
