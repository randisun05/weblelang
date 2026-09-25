<?php

namespace App\Payments\Exceptions;

use RuntimeException;

/** Webhook tidak lolos verifikasi (signature/token salah, payload tidak lengkap). */
class InvalidWebhook extends RuntimeException {}
