# Roadmap HAPA

Ultimo riesame: 17 luglio 2026.

La roadmap segue vertical slice di business. HAPA decide e conserva lo stato commerciale; Automation esegue le integrazioni. Nessun job provider viene abilitato globalmente prima del pilot della singola capacità.

## Baseline

- [x] runtime HTTP/CLI, PostgreSQL, Redis, Docker e CI;
- [x] dominio ordine e outbox transazionale;
- [x] modelli iniziali cliente, prodotto, ricarico e spedizione;
- [x] runtime Automation fisicamente separato;
- [x] RabbitMQ e contratto ordine v1 verificati HAPA → Automation;
- [x] diagrammi architetturali recuperati e riallineati;
- [x] decisione HAPA system of record e Space fornitore;
- [x] schema additivo per account marketplace, offerte fornitore, acquisti, storico cliente, colli e label.

## P0 — Sicurezza e messaggistica bidirezionale

- [ ] unire e distribuire il consumer HAPA con inbox idempotente;
- [ ] autenticazione, sessioni, autorizzazione deny-by-default e CSRF;
- [ ] audit delle azioni commerciali e degli accessi sensibili;
- [ ] metriche su inbox, outbox, dead letter e consumer lag;
- [ ] contratti v2 producer/consumer nei due repository;
- [ ] storage sicuro per etichette e documenti.

**Gate:** nessun dato reale o comando provider è attivabile senza identità, permesso, deduplica e osservabilità.

## P1 — Catalogo Space → HAPA

- [ ] implementare `space.catalog.item.observed` in Automation;
- [ ] implementare consumer e caso d’uso HAPA;
- [ ] separare definitivamente prodotto da offerta Space;
- [ ] backfill e riconciliazione dei campi Space legacy;
- [ ] mostrare costo, disponibilità, versione ed età del dato;
- [ ] pilot read-only su Space.

**Gate:** una variazione Space aggiorna HAPA una sola volta e non regredisce con eventi fuori ordine.

## P2 — Offerte HAPA → IBS

- [ ] CRUD ricarichi autorizzato e auditato;
- [ ] calcolo deterministico con costo, ricarico, fee, IVA e arrotondamento approvati;
- [ ] account IBS esplicito;
- [ ] comando `marketplace.offer.publish.requested` con prezzo e quantità finali;
- [ ] adapter IBS e riconciliazione;
- [ ] pilot su un sottoinsieme SKU;
- [ ] cutover del writer precedente.

**Gate:** HAPA riproduce il prezzo pubblicato e IBS viene aggiornato da un solo writer.

## P3 — Ordini IBS → HAPA

- [ ] congelare un payload IBS reale redatto;
- [ ] import incrementale e watermark Automation;
- [ ] `marketplace.order.observed` idempotente;
- [ ] account + external order ID come identità;
- [ ] cliente, identità, snapshot indirizzi e storico;
- [ ] snapshot economici di ordine e righe;
- [ ] revisione manuale per anomalie;
- [ ] riconciliazione con ordini IBS esistenti.

**Gate:** lo stesso ordine non crea duplicati e i dati storici restano ricostruibili.

## P4 — Acquisto HAPA → Space

- [ ] aggregato e repository `SupplierPurchaseOrder`;
- [ ] rimuovere le transizioni Space dal ciclo vendita;
- [ ] comando `space.purchase_order.submit.requested`;
- [ ] adapter Space idempotente;
- [ ] esiti accettato, rifiutato, parziale e pronto;
- [ ] riconciliazione dopo timeout ambiguo;
- [ ] UI acquisti e gestione eccezioni.

**Gate:** vendita e acquisto hanno numeri, versioni e stati indipendenti.

## P5 — Picking, GLS ed etichetta

- [ ] picking e quantità finali;
- [ ] colli, pesi e dimensioni;
- [ ] comando `shipping.shipment.create.requested`;
- [ ] adapter GLS e ambiente di collaudo;
- [ ] storage label con checksum e retention;
- [ ] stampa e ristampa autorizzate dalla UI;
- [ ] tracking e riconciliazione.

**Gate:** una ristampa non crea una nuova spedizione e un timeout GLS non genera duplicati.

## P6 — Fulfilment IBS e chiusura

- [ ] comando di fulfilment IBS;
- [ ] verifica tracking e quantità;
- [ ] esiti e riconciliazione marketplace;
- [ ] read model di stato complessivo;
- [ ] chiusura ordine solo quando vendita, acquisto e spedizione sono coerenti.

## P7 — Storico cliente e operatività

- [ ] casi d’uso cliente e versioni append-only;
- [ ] merge, rettifica, anonimizzazione e retention;
- [ ] ricerca e timeline completa;
- [ ] export autorizzato e auditato;
- [ ] backup/restore e prove di continuità.

## P8 — Fiscalità

- [ ] validazione con commercialista e consulente;
- [ ] scelta canale diretto/intermediario;
- [ ] modello documento immutabile;
- [ ] fattura elettronica, ricevute, scarti e conservazione;
- [ ] corrispettivi secondo il processo approvato;
- [ ] riconciliazione e segregazione ruoli.

Nessun codice fiscale operativo viene introdotto prima dei gate in [`FISCAL.md`](FISCAL.md).

## Espansioni

Solo dopo la stabilizzazione IBS/Space/GLS:

1. Temu;
2. Amazon;
3. BRT;
4. eventuale storefront diretto.

Ogni espansione riusa i contratti normalizzati e mantiene un solo writer per account e capacità.
