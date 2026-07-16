# Documentazione HAPA

La documentazione tecnica è organizzata nei seguenti riferimenti principali:

- [`ARCHITECTURE.md`](ARCHITECTURE.md): architettura applicativa, infrastrutturale e operativa; stato implementativo; decisioni e confini.
- [`CUSTOMERS_AND_ORDERS.md`](CUSTOMERS_AND_ORDERS.md): anagrafiche canoniche, identità esterne, indirizzi, ordini e predisposizione B2C.
- [`INTERFACE.md`](INTERFACE.md): architettura e stato dell’interfaccia, design system, accessibilità, sicurezza e integrazione futura.
- [`MARKETPLACES.md`](MARKETPLACES.md): portafoglio SellRapido, Amazon, eMAG, Temu e IBS; identità, gate e strategia di attivazione.
- [`SYMFONY_ALIGNMENT.md`](SYMFONY_ALIGNMENT.md): confronto con Symfony e decisioni su dependency injection, client HTTP, sicurezza, worker, scheduler e componenti selezionati.
- [`SECURITY.md`](SECURITY.md): requisiti di sicurezza, dati personali, supply chain, produzione e risposta agli incidenti.
- [`TODO.md`](TODO.md): roadmap ordinata, gate di completamento, debito tecnico controllato e traguardo end-to-end.

## Regola di aggiornamento

Ogni pull request che modifica una decisione architetturale, l’interfaccia, un’integrazione marketplace, un requisito di sicurezza o una fase della roadmap deve aggiornare il documento corrispondente nello stesso changeset.

L’allineamento Symfony rappresenta un riferimento comparativo: HAPA mantiene il framework custom proprietario e adotta soltanto primitive che migliorano affidabilità, sicurezza e manutenibilità.

La documentazione distingue sempre:

- comportamento implementato e verificato;
- struttura parziale;
- componente pianificato.
