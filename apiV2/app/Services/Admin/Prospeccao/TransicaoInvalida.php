<?php

namespace App\Services\Admin\Prospeccao;

/** Interna: desfaz a transação da interação quando a mudança de status é recusada. */
final class TransicaoInvalida extends \RuntimeException {}
