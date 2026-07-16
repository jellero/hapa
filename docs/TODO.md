# Roadmap HAPA

Ultimo riesame: 16 luglio 2026.

Questa roadmap contiene soltanto lavoro di proprietà HAPA. Scheduler, worker, retry provider, cursori e adapter asincroni sono mantenuti in `jellero/hapa-automation`.

## Baseline completata

- [x] Bootstrap HTTP e CLI condiviso.
- [x] Container Symfony compilato e configurazioni tipizzate.
- [x] PostgreSQL, Redis, health check, Docker e CI.
- [x] Dominio ordine, transizioni, repository e optimistic locking.
- [x] Transaction manager e transactional outbox.
- [x] Modello clienti e ordini B2C-ready.
- [x] Modello catalogo prodotti.
- [x] Prezzo base e stock Space rappresentati nel prodotto.
- [x] Motore deterministico delle regole di ricarico.
- [x] Schema prodotti, regole e offerte marketplace.
- [x] UI presentazionale per clienti, ordini, catalogo, picking e spedizioni.
- [x] Runtime automazioni rimosso dal container, CLI, route e UI HAPA.
- [x] Confine RabbitMQ e database separati documentato.
- [x] Foundation autonoma `hapa-automation` disponibile sulla propria branch `main`.
- [x] Contratto ordine `order.changed` allineato tra producer HAPA e consumer `hapa-automation`.
- [x] Test producer e consumer del payload ordine canonico.
- [x] Envelope RabbitMQ canonico e routing key versionate nel relay HAPA.
- [x] `message_id` UUIDv5 stabile derivato dalla chiave di idempotenza.
- [x] Relay outbox HAPA con publisher confirm, retry e stato dead.

## Stato dell’integrazione con hapa-automation

La foundation tecnica esterna, il contratto ordine e il relay producer HAPA sono implementati. Il relay resta disabilitato per default e l’integrazione non è ancora stata verificata end-to-end con un broker reale.

Risolto:

- `OrderEventOutboxMapper` pubblica esclusivamente l’event type canonico `order.changed`;
- il payload usa `version`, `change_type` e `status` risultante quando l’evento determina lo stato;
- `order.address_changed` e gli altri eventi non determinanti non inventano uno stato;
- `hapa-automation` accetta il formato canonico e, durante la transizione, gli alias legacy;
- la proiezione ordine esterna gestisce messaggi fuori ordine senza regressione della versione;
- entrambi i repository hanno test sul contratto ordine;
- il relay trasforma la riga outbox nell’envelope condiviso;
- `message_id`, `correlation_id`, `occurred_at` e `schema_version` sono pubblicati stabilmente;
- il publisher usa messaggi persistenti e publisher confirm;
- lock recovery, retry di delivery e stato dead riutilizzano la transactional outbox HAPA;
- la connessione RabbitMQ è disponibile tramite una rete Docker esterna condivisa, senza accesso cross-database.

Da completare prima di qualsiasi attivazione provider:

- HAPA deve implementare consumer e inbox idempotente per gli eventi di esito;
- catalogo e ricarichi devono avere producer HAPA e test di contratto speculari;
- publish, consume, deduplica e dead letter devono essere verificati con RabbitMQ reale tra i due repository;
- devono essere disponibili metriche e alert sul backlog outbox e sui messaggi dead.

Nessun job provider deve essere abilitato finché questi punti non sono risolti. Il relay HAPA deve rimanere disabilitato finché il test end-to-end non è completato.

## Priorità immediata HAPA

1. consumer RabbitMQ idempotente e inbox HAPA;
2. test end-to-end HAPA → RabbitMQ → `hapa-automation`;
3. contratti e test producer di catalogo e ricarichi;
4. aggregato e repository `Customer`;
5. repository e read model dell’anagrafica prodotti;
6. autenticazione, autorizzazione, sessioni e CSRF;
7. CRUD autorizzato e auditato delle regole di ricarico;
8. vertical slice prodotto Space → HAPA;
9. vertical slice ricarico HAPA → pubblicazione marketplace;
10. vertical slice ordine marketplace → HAPA → Space;
11. picking, colli e richieste spedizione.

## Fase 1 — Anagrafiche

- [ ] Definire aggregato `Customer` e policy di modifica, archiviazione, merge e anonimizzazione.
- [ ] Implementare `CustomerRepository`.
- [ ] Implementare query paginate clienti e ordini.
- [ ] Definire retention e diritti dell’interessato.

**Gate:** clienti e ordini sono gestibili tramite casi d’uso transazionali, autorizzati e auditati.

## Fase 2 — Prodotti e ricarichi

- [ ] Definire aggregato o modello applicativo `Product`.
- [ ] Implementare repository prodotto e query catalogo.
- [ ] Applicare eventi Space con deduplica e versione sorgente.
- [ ] Collegare prezzo e stock Space alla UI.
- [ ] Implementare CRUD delle regole di ricarico.
- [ ] Implementare anteprima e versionamento del prezzo finale.
- [ ] Auditare ogni modifica commerciale.
- [ ] Definire una eventuale policy separata di quantità pubblicabile.

**Gate:** il prodotto mostra prezzo e stock Space reali; un operatore autorizzato modifica un ricarico e ottiene un prezzo finale riproducibile.

## Fase 3 — Sicurezza UI

- [ ] Autenticazione e recupero credenziali.
- [ ] Session cookie sicuri e rotazione sessione.
- [ ] MFA per ruoli sensibili.
- [ ] Autorizzazione deny-by-default.
- [ ] CSRF su ogni azione mutativa.
- [ ] Audit degli accessi e delle modifiche.
- [ ] Rate limit su login e operazioni sensibili.

**Gate:** nessun dato o comando operativo è disponibile senza identità e permesso validi.

## Fase 4 — Messaggistica HAPA

- [x] Congelare il contratto canonico degli eventi ordine.
- [x] Allineare `OrderEventOutboxMapper` a `order.changed`, `version`, `change_type` e `status` canonico.
- [x] Gestire nel consumer esterno gli eventi ordine fuori ordine e gli alias legacy.
- [x] Introdurre test producer/consumer del contratto ordine nei due repository.
- [x] Documentare il deploy consumer-first e la rimozione successiva degli alias legacy.
- [x] Definire envelope e routing key versionate nel relay HAPA.
- [x] Definire la generazione stabile di `message_id` dal record outbox.
- [x] Esporre `causation_id` come campo nullable dell’envelope condiviso.
- [x] Implementare relay outbox con publisher confirm.
- [x] Implementare lock recovery, retry esponenziale e stato dead per la consegna al broker.
- [x] Aggiungere configurazione e secret RabbitMQ opt-in.
- [x] Aggiungere la rete Docker condivisa esclusivamente per RabbitMQ.
- [ ] Congelare i contratti canonici di catalogo e ricarichi.
- [ ] Implementare i producer HAPA per `catalog.product.changed` e `pricing.rule.changed`.
- [ ] Implementare consumer RabbitMQ con inbox idempotente HAPA.
- [ ] Esporre metriche su outbox, consumer lag e messaggi rifiutati.
- [ ] Eseguire un test end-to-end con RabbitMQ reale tra i due servizi.

**Gate:** una modifica HAPA raggiunge `hapa-automation` senza perdita e un esito duplicato non produce doppie modifiche.

## Fase 5 — Catalogo end-to-end

- [ ] Consumare evento prodotto/prezzo/stock generato dal servizio asincrono.
- [ ] Aggiornare l’anagrafica prodotto nello stesso transaction boundary dell’inbox.
- [ ] Ricalcolare e versionare il prezzo finale.
- [ ] Produrre richiesta di pubblicazione offerta.
- [ ] Applicare esito e versione remota ricevuti.
- [ ] Gestire divergenze e dati Space scaduti.

**Gate:** una variazione Space è visibile in HAPA e una modifica ricarico produce un’offerta pubblicata e riconciliata.

## Fase 6 — Ordini end-to-end

- [ ] Consumare ordine importato da `hapa-automation`.
- [ ] Riconciliare cliente e identità esterne.
- [ ] Persistire ordine e produrre eventi.
- [ ] Applicare esiti di accettazione e invio Space.
- [ ] Gestire revisione manuale per dati non validi.

**Gate:** un ordine reale attraversa marketplace, HAPA e Space senza duplicati.

## Fase 7 — Magazzino e spedizioni

- [ ] Dominio picking e scansioni barcode.
- [ ] Decisioni manuali sui parziali.
- [ ] Modello colli e pesi.
- [ ] Richiesta spedizione tramite evento.
- [ ] Applicazione etichetta e tracking ricevuti.
- [ ] Restituzione fulfilment governata dal servizio asincrono.

**Gate:** un ordine viene prelevato, spedito e riconciliato con il canale.

## Fuori perimetro HAPA

Sono tracciati esclusivamente in `hapa-automation`:

- scheduler persistente;
- cursori Space e marketplace;
- rate limit provider;
- worker concorrenti;
- retry e dead letter provider;
- adapter HTTP/FTP;
- dashboard tecnica delle code;
- supervisione e graceful shutdown dei worker.
