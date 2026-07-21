<?php

namespace App\Domain\Exceptions;

use DomainException;

class IdempotencyConflictException extends DomainException {}
