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

## Priorità immediata HAPA

1. aggregato e repository `Customer`;
2. repository e read model dell’anagrafica prodotti;
3. autenticazione, autorizzazione, sessioni e CSRF;
4. CRUD autorizzato e auditato delle regole di ricarico;
5. consumer RabbitMQ idempotente per eventi provenienti da `hapa-automation`;
6. relay della transactional outbox verso RabbitMQ;
7. vertical slice prodotto Space → HAPA;
8. vertical slice ricarico HAPA → pubblicazione marketplace;
9. vertical slice ordine marketplace → HAPA → Space;
10. picking, colli e richieste spedizione.

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

- [ ] Definire envelope e routing key versionate.
- [ ] Implementare relay outbox con publisher confirm.
- [ ] Implementare consumer RabbitMQ con inbox idempotente HAPA.
- [ ] Gestire aggiornamenti fuori ordine tramite versione entità.
- [ ] Introdurre test di contratto con `hapa-automation`.
- [ ] Esporre metriche su outbox, consumer lag e messaggi rifiutati.
- [ ] Definire deploy compatibile tra due versioni consecutive.

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
