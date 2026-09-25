<?php

namespace App\Payments\Exceptions;

use RuntimeException;

/** Gateway tidak dikonfigurasi atau menolak permintaan. Pesan aman ditampilkan ke admin. */
class GatewayException extends RuntimeException {}
