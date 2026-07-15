# Roadmap HAPA

Ultimo riesame: 15 luglio 2026.

Questo documento ordina il lavoro per dipendenze tecniche, valore operativo e rischio. Lo stato viene aggiornato a ogni merge significativo.

## Legenda

- `[x]` completato e presente in `main`;
- `[ ]` da implementare;
- **Gate**: condizione richiesta per considerare conclusa la fase.

## Baseline completata

- [x] Bootstrap condiviso HTTP e CLI.
- [x] Configurazione ambiente validata e secret file centralizzati.
- [x] Trusted proxy espliciti.
- [x] Kernel HTTP con 404, 405, 500, correlation ID e header di sicurezza.
- [x] Logging JSON e redazione dei dati sensibili.
- [x] Health check live/ready con PostgreSQL, Redis e verifica schema.
- [x] PostgreSQL con migrazioni, `JSONB`, `TIMESTAMPTZ` e vincoli principali.
- [x] Docker development e production con runtime separato dalle migrazioni.
- [x] CI con audit Composer, PostgreSQL, Redis, PHPStan e smoke test production.
- [x] Contratti iniziali tipizzati per Marketplace, Space e GLS.
- [x] Documentazione architetturale completa.
- [x] Pull request tecniche sostituite chiuse.

## Priorità immediata

La prossima attività deve produrre la prima capacità di dominio verificabile: modello ordine, transizioni deterministiche, repository e persistenza outbox nella stessa transazione.

## Fase 1 — Dominio ordine

- [ ] Definire l’aggregato `Order` e l’entità `OrderLine` con invarianti esplicite.
- [ ] Rinominare gli stati semanticamente ambigui `complete` e `completed` prima della presenza di dati reali.
- [ ] Definire la macchina a stati deterministica.
- [ ] Definire transizioni ammesse, prerequisiti, errori di dominio ed eventi prodotti.
- [ ] Modellare quantità ordinate, disponibili, spedibili e annullabili.
- [ ] Introdurre storico delle transizioni ordine.
- [ ] Introdurre optimistic locking basato sul campo `version`.
- [ ] Aggiungere test unitari esaustivi delle transizioni e delle invarianti.

**Gate:** ogni modifica di stato attraversa un metodo di dominio testato; transizioni arbitrarie risultano impossibili.

## Fase 2 — Persistenza e transazioni

- [ ] Definire le porte `OrderRepository` e `MarketplaceRepository`.
- [ ] Implementare repository PostgreSQL espliciti.
- [ ] Definire il transaction boundary applicativo.
- [ ] Implementare transaction manager o Unit of Work esplicita.
- [ ] Persistire dominio e messaggi outbox nella stessa transazione PostgreSQL.
- [ ] Implementare mapping tra record, aggregato e value object.
- [ ] Aggiungere test integration su rollback, concorrenza, optimistic locking e idempotenza.

**Gate:** un aggiornamento ordine e il relativo evento outbox vengono confermati o annullati insieme.

## Fase 3 — Contratti, DTO e client di integrazione

- [ ] Introdurre `ExternalOrderLine` al posto degli array strutturati.
- [ ] Introdurre `SpaceOrderLine` al posto degli array strutturati.
- [ ] Introdurre `ShipmentPackage` con peso, lunghezza, larghezza e altezza.
- [ ] Definire peso reale, peso volumetrico e peso tariffabile.
- [ ] Introdurre risultati tipizzati per ogni operazione adapter.
- [ ] Introdurre errori tipizzati: temporaneo, definitivo, validazione, autenticazione e rate limit.
- [ ] Definire timeout, retry policy e idempotency key nei contratti applicativi.
- [ ] Selezionare e integrare il client HTTP usato dagli adapter.
- [ ] Definire redazione e persistenza controllata di request e response tecniche.

**Gate:** i casi d’uso ricevono e restituiscono tipi applicativi; payload provider e array generici restano confinati negli adapter.

## Fase 4 — Transactional outbox e automazioni

- [x] Schema outbox con idempotency key, tentativi, lock token, worker identity e stati terminali.
- [ ] Implementare scrittura outbox tramite repository transazionale.
- [ ] Implementare claim atomico con `FOR UPDATE SKIP LOCKED`.
- [ ] Implementare worker concorrenti con lock token e worker identity.
- [ ] Implementare retry con exponential backoff e jitter.
- [ ] Implementare scadenza e recupero dei lock.
- [ ] Implementare dead letter e gestione manuale degli errori definitivi.
- [ ] Implementare handler registry per evento e provider.
- [ ] Implementare scheduler per import ordini, disponibilità, tracking e riconciliazione.
- [ ] Aggiungere metriche su coda, tentativi, latenze, lock ed errori.
- [ ] Aggiungere test di concorrenza, crash recovery e idempotenza del worker.

**Gate:** due worker possono operare contemporaneamente senza doppie delivery e ogni fallimento raggiunge retry o dead letter in modo deterministico.

## Fase 5 — Prima vertical slice Marketplace → HAPA → Space

- [ ] Scegliere il primo marketplace di riferimento.
- [ ] Implementare import incrementale degli ordini.
- [ ] Gestire paginazione, cursori e finestre temporali.
- [ ] Rendere l’import idempotente su marketplace e ID ordine esterno.
- [ ] Implementare accettazione dell’ordine.
- [ ] Implementare recupero e normalizzazione dell’indirizzo di spedizione.
- [ ] Persistire ordine, righe e indirizzo.
- [ ] Inviare l’ordine a Space tramite API.
- [ ] Persistire identificativo Space e ogni tentativo di delivery.
- [ ] Implementare riconciliazione tra stato interno, marketplace e Space.
- [ ] Coprire il flusso con adapter fake, test integration e test end-to-end.

**Gate:** un ordine reale o sandbox attraversa importazione, accettazione, indirizzo, persistenza e invio a Space con retry e tracciabilità completa.

## Fase 6 — Disponibilità, magazzino e picking

- [ ] Implementare aggiornamento disponibilità da Space.
- [ ] Modellare `pick_sessions` e `pick_tasks`.
- [ ] Modellare scansioni barcode e anomalie di scansione.
- [ ] Gestire operatore, postazione, timestamp e audit delle attività.
- [ ] Implementare disponibilità completa e parziale.
- [ ] Implementare decisioni su quantità da spedire e quantità da annullare.
- [ ] Introdurre approvazione esplicita per i parziali.
- [ ] Implementare riconciliazione tra disponibilità, picking e quantità finali.

**Gate:** il sistema ricostruisce chi ha preparato ogni riga, quali barcode sono stati letti e come sono state determinate le quantità finali.

## Fase 7 — GLS, colli e tracking

- [ ] Modellare uno o più colli per spedizione.
- [ ] Calcolare peso volumetrico e peso tariffabile per collo.
- [ ] Completare `ShipmentRequest` con contatti, servizio, note e opzioni GLS.
- [ ] Implementare creazione spedizione GLS.
- [ ] Implementare generazione, memorizzazione e accesso controllato all’etichetta.
- [ ] Implementare ristampa e recupero label.
- [ ] Implementare annullamento spedizione.
- [ ] Implementare recupero e riconciliazione dello stato spedizione.
- [ ] Inviare tracking e fulfilment al marketplace.
- [ ] Gestire tracking e quantità per ordini parziali.

**Gate:** ordine, colli, label e tracking risultano collegati, idempotenti e riconciliabili con GLS e marketplace.

## Fase 8 — Autenticazione e pannello operativo

- [ ] Implementare utenti, ruoli e permessi.
- [ ] Implementare autenticazione sicura e gestione sessioni.
- [ ] Applicare CSRF alle operazioni mutative.
- [ ] Implementare dashboard ordini e filtri operativi.
- [ ] Implementare dettaglio ordine, righe, transizioni e tentativi esterni.
- [ ] Implementare azioni manuali controllate: retry, revisione, approvazione parziale e annullamento.
- [ ] Implementare visibilità su outbox, dead letter e riconciliazioni.
- [ ] Registrare ogni azione operativa nell’audit log.

**Gate:** ogni azione mutativa richiede permesso esplicito e produce audit correlato all’ordine.

## Fase 9 — Operatività production

- [ ] Rendere privato il repository proprietario.
- [ ] Definire backup automatici PostgreSQL e procedura di restore verificata.
- [ ] Definire retention per payload tecnici, audit, indirizzi e label.
- [ ] Introdurre metriche, dashboard e alerting.
- [ ] Introdurre tracing distribuito e propagazione del correlation ID verso i provider.
- [ ] Definire rotazione dei secret e procedura di emergenza.
- [ ] Definire runbook di deploy, rollback, incident response e riconciliazione.
- [ ] Fissare immagini base production tramite digest.
- [ ] Eseguire scansione periodica di dipendenze, immagini e secret.
- [ ] Eseguire test di carico sui flussi di import, worker e picking.

**Gate:** backup e restore sono provati, alert e runbook sono operativi, segreti e immagini seguono una policy verificabile.

## Debito tecnico controllato

Questi interventi accompagnano le prime fasi e vengono chiusi prima dell’attivazione degli adapter reali:

- [ ] Sostituire gli accessi statici all’ambiente con configurazioni tipizzate iniettate.
- [ ] Introdurre `ApplicationConfig`, `DatabaseConfig`, `RedisConfig`, `ProxyConfig` e `IntegrationConfig`.
- [ ] Rimuovere `DB_CONNECTION` finché PostgreSQL resta l’unico database supportato.
- [ ] Limitare l’ambiente del container migration alle sole variabili PostgreSQL.
- [ ] Sostituire la costante della migrazione minima con un manifest di schema versionato.
- [ ] Dichiarare una politica univoca per le migrazioni: rollback completo oppure forward-only.
- [ ] Valutare l’unificazione dei job CI `quality` e `static-analysis` in base ai tempi effettivi.
- [ ] Rimuovere i branch tecnici obsoleti dopo la chiusura delle pull request sostituite.

## Criterio di completamento end-to-end

Il primo traguardo di prodotto è un ordine reale che attraversa integralmente:

1. importazione dal marketplace;
2. accettazione;
3. acquisizione e normalizzazione indirizzo;
4. persistenza idempotente;
5. invio a Space;
6. aggiornamento disponibilità;
7. picking completo o parziale;
8. generazione colli, spedizione ed etichetta GLS;
9. invio tracking e fulfilment al marketplace;
10. visibilità completa nel pannello, nei log, nell’audit, nelle delivery e nelle riconciliazioni.
