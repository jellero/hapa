# TODO HAPA

Roadmap operativa ordinata per dipendenze tecniche e valore funzionale.

## Stato attuale

La foundation tecnica comprende bootstrap HTTP/CLI condiviso, configurazione e secret centralizzati, PostgreSQL, Redis, logging strutturato, health check, migrazioni, Docker development/production, CI con test integration e smoke test production, schema iniziale e contratti tipizzati per Marketplace, Space e GLS.

## Fase 1 — Dominio ordine

- [ ] Definire il modello `Order` e `OrderLine` con invarianti esplicite.
- [ ] Definire la macchina a stati deterministica dell’ordine.
- [ ] Rinominare gli stati semanticamente ambigui, in particolare `complete` e `completed`.
- [ ] Definire transizioni ammesse, prerequisiti, errori di dominio ed effetti prodotti.
- [ ] Introdurre storico delle transizioni ordine.
- [ ] Introdurre optimistic locking basato sul campo `version`.
- [ ] Aggiungere test unitari completi della macchina a stati.

## Fase 2 — Persistenza e transazioni

- [ ] Implementare repository `OrderRepository` e `MarketplaceRepository`.
- [ ] Definire il transaction boundary applicativo.
- [ ] Implementare Unit of Work o transaction manager esplicito.
- [ ] Persistire dominio e messaggi outbox nella stessa transazione PostgreSQL.
- [ ] Aggiungere test integration su rollback, concorrenza e idempotenza.

## Fase 3 — Contratti e DTO

- [ ] Introdurre `ExternalOrderLine` al posto degli array strutturati.
- [ ] Introdurre `SpaceOrderLine` al posto degli array strutturati.
- [ ] Introdurre `ShipmentPackage` con peso, lunghezza, larghezza e altezza.
- [ ] Definire peso reale, peso volumetrico e peso tariffabile.
- [ ] Introdurre risultati ed errori tipizzati per ogni adapter.
- [ ] Distinguere errori temporanei, definitivi, di validazione e di autenticazione.
- [ ] Definire timeout, rate limit e idempotency key nei contratti applicativi.

## Fase 4 — Transactional outbox e automazioni

- [ ] Implementare claim atomico con `FOR UPDATE SKIP LOCKED`.
- [ ] Implementare worker concorrenti con lock token e worker identity.
- [ ] Implementare retry con backoff e jitter.
- [ ] Implementare scadenza e recupero dei lock.
- [ ] Implementare dead letter e gestione manuale degli errori definitivi.
- [ ] Implementare handler registry per evento e provider.
- [ ] Implementare scheduler per import ordini, disponibilità, tracking e riconciliazione.
- [ ] Aggiungere metriche su coda, tentativi, latenze ed errori.
- [ ] Aggiungere test di concorrenza e idempotenza del worker.

## Fase 5 — Prima vertical slice Marketplace → Space

- [ ] Implementare import incrementale degli ordini marketplace.
- [ ] Gestire paginazione, cursori e finestre temporali.
- [ ] Rendere l’import idempotente su marketplace e ID ordine esterno.
- [ ] Implementare accettazione dell’ordine.
- [ ] Implementare recupero dell’indirizzo di spedizione.
- [ ] Persistire ordine, righe e indirizzo.
- [ ] Inviare l’ordine a Space tramite API.
- [ ] Persistire identificativo Space e stato della consegna esterna.
- [ ] Implementare riconciliazione tra stato interno, marketplace e Space.
- [ ] Coprire il flusso con test end-to-end e adapter fake.

## Fase 6 — Disponibilità, magazzino e picking

- [ ] Implementare aggiornamento disponibilità da Space.
- [ ] Modellare `pick_sessions` e `pick_tasks`.
- [ ] Modellare scansioni barcode e anomalie di scansione.
- [ ] Gestire operatore, postazione, timestamp e audit delle attività.
- [ ] Implementare ordini completi e disponibilità parziale.
- [ ] Implementare decisioni su quantità da spedire e quantità da annullare.
- [ ] Introdurre approvazione esplicita per i parziali.
- [ ] Implementare riconciliazione tra disponibilità, picking e quantità finali.

## Fase 7 — GLS, colli e tracking

- [ ] Modellare uno o più colli per spedizione.
- [ ] Calcolare peso volumetrico e peso tariffabile per collo.
- [ ] Completare `ShipmentRequest` con contatti, servizio, note e opzioni GLS.
- [ ] Implementare creazione spedizione GLS.
- [ ] Implementare generazione e memorizzazione dell’etichetta.
- [ ] Implementare ristampa e recupero label.
- [ ] Implementare annullamento spedizione.
- [ ] Implementare recupero e riconciliazione dello stato spedizione.
- [ ] Inviare tracking e fulfilment al marketplace.
- [ ] Gestire tracking e quantità per ordini parziali.

## Fase 8 — Autenticazione e pannello operativo

- [ ] Implementare utenti, ruoli e permessi.
- [ ] Implementare autenticazione sicura e gestione sessioni.
- [ ] Applicare CSRF alle operazioni mutative del pannello.
- [ ] Implementare dashboard ordini e filtri operativi.
- [ ] Implementare dettaglio ordine, righe, transizioni e tentativi esterni.
- [ ] Implementare azioni manuali controllate: retry, revisione, approvazione parziale e annullamento.
- [ ] Implementare visibilità su outbox, dead letter e riconciliazioni.
- [ ] Registrare ogni azione operativa nell’audit log.

## Fase 9 — Operatività production

- [ ] Rendere il repository privato.
- [ ] Definire backup automatici PostgreSQL e procedura di restore verificata.
- [ ] Definire retention per payload tecnici, audit, indirizzi e label.
- [ ] Introdurre metriche, dashboard e alerting.
- [ ] Introdurre tracing distribuito e propagazione del correlation ID verso i provider.
- [ ] Definire rotazione dei secret e procedura di emergenza.
- [ ] Definire runbook di deploy, rollback, incident response e riconciliazione.
- [ ] Fissare immagini base production tramite digest.
- [ ] Eseguire scansione periodica di dipendenze, immagini e secret.
- [ ] Eseguire test di carico sui flussi di import, worker e picking.

## Pulizia e ridondanze residue

- [ ] Sostituire accessi statici all’ambiente con configurazioni tipizzate iniettate nei servizi.
- [ ] Introdurre `ApplicationConfig`, `DatabaseConfig`, `RedisConfig` e `ProxyConfig`.
- [ ] Rimuovere `DB_CONNECTION` finché PostgreSQL resta l’unico database supportato.
- [ ] Limitare l’ambiente del container migration alle sole variabili PostgreSQL.
- [ ] Sostituire la costante della migrazione minima con un manifest di schema versionato.
- [ ] Dichiarare una politica univoca per le migrazioni: rollback completo oppure forward-only.
- [ ] Valutare l’unificazione dei job CI `quality` e `static-analysis` quando i tempi di esecuzione lo consentono.
- [ ] Chiudere le pull request sostituite e rimuovere i branch tecnici obsoleti.

## Criterio di completamento end-to-end

Il primo traguardo funzionale è un ordine reale che attraversa integralmente questo percorso:

1. importazione dal marketplace;
2. accettazione;
3. acquisizione indirizzo;
4. persistenza idempotente;
5. invio a Space;
6. aggiornamento disponibilità;
7. picking completo o parziale;
8. generazione spedizione ed etichetta GLS;
9. invio tracking al marketplace;
10. visibilità completa nel pannello operativo, nei log, nell’audit e nelle riconciliazioni.
