<?php

namespace App\Services;

/**
 * Levée quand un compte ne peut pas être supprimé (ou anonymisé) parce que
 * des réservations, remboursements ou gains encore en cours en dépendent.
 */
class SuppressionCompteBloqueeException extends \RuntimeException
{
}
