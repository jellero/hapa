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

- [x] implementare producer normalizzato `space.catalog.item.observed` in Automation;
- [x] implementare consumer RabbitMQ e caso d’uso HAPA;
- [x] separare prodotto da offerta Space per tutte le nuove osservazioni;
- [x] creare prodotti nuovi inattivi con revisione manuale e deduplica;
- [ ] backfill e riconciliazione dei campi Space legacy;
- [ ] mostrare costo, disponibilità, versione ed età del dato;
- [ ] pilot read-only su Space.

**Gate:** una variazione Space aggiorna HAPA una sola volta, non regredisce con eventi fuori ordine e un prodotto nuovo non viene pubblicato prima dell’approvazione.

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

L'integrazione avviene tramite una nuova API PHP sviluppata nel sistema Space. L'API è l'unico componente autorizzato a leggere e scrivere il database Space esistente; HAPA Automation non accede direttamente a PostgreSQL Space.

La tabella operativa corrente per le righe d'ordine è `public.ordini_articoli`. L'API Space inserisce gli ordini in tale modello e restituisce a HAPA Automation identificativi, quantità e stati letti dal database corrente.

- [ ] definire e versionare il contratto HTTP dell'API PHP Space per creazione ordine, lettura dettaglio e consultazione avanzamento;
- [ ] implementare autenticazione servizio-servizio, autorizzazione, TLS, rate limit, audit e rotazione credenziali dell'API Space;
- [ ] definire una chiave di idempotenza per impedire inserimenti duplicati in `ordini_articoli` quando HAPA Automation ritenta la creazione;
- [ ] definire la risposta di creazione con almeno identificativo ordine Space, identificativi delle righe e correlazione con ordine e righe HAPA;
- [ ] documentare quali tabelle e procedure Space devono essere usate per creare correttamente testata e righe, senza bypassare trigger o invarianti esistenti;
- [ ] congelare payload reali redatti dell'API Space per creazione ordine, lettura dettaglio e avanzamento stato;
- [ ] censire valori e semantica effettiva dei campi `stato_os`, `stato_bo`, `stato_sh`, `stato_de`, `stato_arc_sh`, `stato_arc_de` e `closing_order`;
- [ ] chiarire quali campi rappresentano preso in carico, in lavorazione, pronto, non disponibile, spedito, archiviato o altre condizioni operative;
- [ ] distinguere stato della testata, stato delle singole righe, quantità ordinata, disponibile, evasa, spedita e residua;
- [ ] conservare nella risposta API sia i valori grezzi Space sia lo stato normalizzato e la versione del mapping applicato;
- [ ] definire un mapping versionato dai valori del database Space agli stati canonici HAPA, senza dedurlo dai soli nomi delle colonne;
- [ ] definire la matrice delle transizioni ammesse e il trattamento di risposte duplicate, tardive, regressive o incoerenti;
- [ ] completare aggregato e repository `SupplierPurchaseOrder` senza riutilizzare gli stati dell'ordine di vendita;
- [ ] rappresentare in modo esplicito le fasi richiesto, preso in carico, in lavorazione, parzialmente disponibile, pronto, completato, rifiutato e non disponibile dopo la validazione delle regole Space;
- [ ] comando `space.purchase_order.submit.requested` con versione e idempotency key applicativa;
- [ ] adapter HAPA Automation idempotente che chiama l'API Space per inserire l'ordine e recuperarne lo stato;
- [ ] polling dell'API Space con checkpoint persistente, frequenza controllata e riconciliazione periodica;
- [ ] evento `space.purchase_order.status_changed` idempotente con versione sorgente, istante osservato, valori grezzi e dettaglio righe;
- [ ] gestione separata di indisponibilità totale, indisponibilità parziale e sostituzioni, sempre secondo una policy commerciale HAPA esplicita;
- [ ] riconciliazione tramite API dopo timeout ambiguo prima di qualsiasi nuovo inserimento dell'ordine;
- [ ] audit completo di richiesta, risposta redatta, stato precedente, stato nuovo, causazione e decisioni manuali;
- [ ] UI acquisti con timeline degli stati Space, quantità per riga, eccezioni e azioni consentite;
- [ ] test contrattuali tra HAPA Automation e API Space, test di idempotenza inserimento, test delle transizioni e pilot controllato.

**Gate:** HAPA Automation non accede direttamente al database Space; ogni creazione è idempotente; ordine e righe Space sono correlati stabilmente con HAPA; ogni combinazione di stato usata è documentata e verificata; una risposta regressiva non modifica l'acquisto; una indisponibilità non chiude o modifica automaticamente la vendita senza una policy esplicita.

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

- [ ] casi d'uso cliente e versioni append-only;
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