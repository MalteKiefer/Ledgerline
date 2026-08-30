<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Mail;

use RuntimeException;

abstract class CompanyMailTransportFailure extends RuntimeException {}
