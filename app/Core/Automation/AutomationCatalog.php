<?php

declare(strict_types=1);

namespace Hapa\Core\Automation;

final class AutomationCatalog
{
    /** @return non-empty-list<AutomationDefinition> */
    public function definitions(): array
    {
        return [
            new AutomationDefinition(
                'Accetta ordini completi',
                'Marketplace → HAPA',
                'Ogni 10 minuti',
                'Lock, log e idempotenza',
                'Da collegare',
                'neutral',
            ),
            new AutomationDefinition(
                'Recupera indirizzi',
                'Marketplace → anagrafica ordine',
                'Ogni 10 minuti',
                'Normalizzazione e revisione manuale',
                'Da collegare',
                'neutral',
            ),
            new AutomationDefinition(
                'Importa ordini di lavoro',
                'SellRapido/canale → HAPA',
                'Ogni 10 minuti',
                'Cursore e deduplica',
                'Da collegare',
                'neutral',
            ),
            new AutomationDefinition(
                'Esporta verso Space',
                'HAPA → CSV → FTP Space',
                'Ogni 10 minuti',
                'Consegna idempotente e riconciliazione',
                'Da collegare',
                'neutral',
            ),
            new AutomationDefinition(
                'Aggiorna disponibilità',
                'Space → righe ordine',
                'Ogni 10 minuti',
                'Quantità ricevute e controllo coerenza',
                'Da collegare',
                'neutral',
            ),
            new AutomationDefinition(
                'Gestisci parziali confermati',
                'Conferma operatore → picking',
                'Ogni 10 minuti',
                'Conferma manuale obbligatoria',
                'Gate manuale',
                'warning',
            ),
            new AutomationDefinition(
                'Recupera errori temporanei',
                'Outbox → retry/dead letter',
                'A ogni esecuzione worker',
                'Backoff, jitter e lock scaduti',
                'Runtime pronto',
                'success',
            ),
        ];
    }
}
