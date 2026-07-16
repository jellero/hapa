# Documentazione HAPA

La documentazione tecnica è organizzata nei seguenti riferimenti:

- [`ARCHITECTURE.md`](ARCHITECTURE.md): confini tra HAPA e `hapa-automation`, proprietà dei dati, RabbitMQ e deploy separati.
- [`AUTOMATIONS.md`](AUTOMATIONS.md): responsabilità asincrone trasferite al repository dedicato.
- [`CATALOG_PRICING.md`](CATALOG_PRICING.md): anagrafica prodotti, prezzo e stock sincronizzati da Space, ricarichi gestiti dalla UI.
- [`CARRIERS.md`](CARRIERS.md): contratto Shipping, GLS/BRT, idempotenza e failure mode.
- [`CUSTOMERS_AND_ORDERS.md`](CUSTOMERS_AND_ORDERS.md): clienti, identità esterne, indirizzi, ordini e predisposizione B2C.
- [`DEVELOPMENT_WORKFLOW.md`](DEVELOPMENT_WORKFLOW.md): ownership, livelli applicativi e percorso di delivery.
- [`INTERFACE.md`](INTERFACE.md): mappa e stato dell’interfaccia HAPA.
- [`MARKETPLACES.md`](MARKETPLACES.md): canali, connettori, account e writer attivi.
- [`SYMFONY_ALIGNMENT.md`](SYMFONY_ALIGNMENT.md): componenti Symfony adottati nel framework custom.
- [`SECURITY.md`](SECURITY.md): sicurezza applicativa, dati, provider e produzione.
- [`TODO.md`](TODO.md): roadmap HAPA dopo l’estrazione delle automazioni.
- [`PR_CHECKLIST.md`](PR_CHECKLIST.md): verifica prima del merge.
- [`adr/0001-provider-neutral-carriers.md`](adr/0001-provider-neutral-carriers.md): contratto corrieri condiviso.
- [`adr/0002-space-catalog-pricing.md`](adr/0002-space-catalog-pricing.md): ownership Space/HAPA/marketplace per prodotti, prezzi e stock.
- [`adr/0003-external-automation-runtime.md`](adr/0003-external-automation-runtime.md): separazione del runtime asincrono e sincronizzazione RabbitMQ.

## Regola di aggiornamento

Ogni modifica a proprietà dei dati, messaggi, interfaccia, sicurezza, provider o roadmap aggiorna la documentazione corrispondente nello stesso changeset.

La documentazione distingue sempre tra comportamento implementato, struttura parziale e capacità pianificata.
