# Documentazione HAPA

La documentazione tecnica è organizzata nei seguenti riferimenti principali:

- [`ARCHITECTURE.md`](ARCHITECTURE.md): architettura applicativa, infrastrutturale e operativa; stato implementativo; decisioni e confini.
- [`AUTOMATIONS.md`](AUTOMATIONS.md): flusso ordini, catalogo e spedizioni, otto job schedulati, runtime outbox, retry e gate di attivazione.
- [`CATALOG_PRICING.md`](CATALOG_PRICING.md): ownership Space/HAPA/marketplace, disponibilità vendibile, ricarichi, schema, contratti e attivazione API.
- [`CARRIERS.md`](CARRIERS.md): ownership del contratto Shipping, moduli GLS/BRT, discovery, idempotenza e failure mode.
- [`CUSTOMERS_AND_ORDERS.md`](CUSTOMERS_AND_ORDERS.md): anagrafiche canoniche, identità esterne, indirizzi, ordini e predisposizione B2C.
- [`DEVELOPMENT_WORKFLOW.md`](DEVELOPMENT_WORKFLOW.md): percorso canonico dal dato alla UI, responsabilità dei livelli e dipendenze tra moduli.
- [`INTERFACE.md`](INTERFACE.md): architettura e stato dell’interfaccia, design system, accessibilità, sicurezza e integrazione futura.
- [`MARKETPLACES.md`](MARKETPLACES.md): portafoglio SellRapido, Amazon, eMAG, Temu e IBS; identità, gate e strategia di attivazione.
- [`SYMFONY_ALIGNMENT.md`](SYMFONY_ALIGNMENT.md): confronto con Symfony e decisioni su dependency injection, client HTTP, sicurezza, worker, scheduler e componenti selezionati.
- [`SECURITY.md`](SECURITY.md): requisiti di sicurezza, dati personali, supply chain, produzione e risposta agli incidenti.
- [`TODO.md`](TODO.md): roadmap ordinata, gate di completamento, debito tecnico controllato e traguardo end-to-end.
- [`PR_CHECKLIST.md`](PR_CHECKLIST.md): verifica operativa prima di pubblicare o fondere una modifica.
- [`adr/0001-provider-neutral-carriers.md`](adr/0001-provider-neutral-carriers.md): decisione sul contratto corrieri condiviso.
- [`adr/0002-space-catalog-pricing.md`](adr/0002-space-catalog-pricing.md): ownership Space/HAPA/marketplace per prezzi, stock e offerte pubblicabili.

## Regola di aggiornamento

Ogni pull request che modifica una decisione architetturale, l’interfaccia, un’integrazione marketplace, un requisito di sicurezza o una fase della roadmap deve aggiornare il documento corrispondente nello stesso changeset.

L’allineamento Symfony rappresenta un riferimento comparativo: HAPA mantiene il framework custom proprietario e adotta soltanto primitive che migliorano affidabilità, sicurezza e manutenibilità.

La documentazione distingue sempre:

- comportamento implementato e verificato;
- struttura parziale;
- componente pianificato.
