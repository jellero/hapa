# Roadmap HAPA

Ultimo riesame: 16 luglio 2026.

Questo documento ordina il lavoro per dipendenze tecniche, valore operativo e rischio. Lo stato viene aggiornato a ogni merge significativo.

Riferimenti:

- [`ARCHITECTURE.md`](ARCHITECTURE.md);
- [`INTERFACE.md`](INTERFACE.md);
- [`MARKETPLACES.md`](MARKETPLACES.md);
- [`SYMFONY_ALIGNMENT.md`](SYMFONY_ALIGNMENT.md);
- [`SECURITY.md`](SECURITY.md).

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
- [x] Distinzione tipizzata tra canale marketplace e connettore tecnico.
- [x] Portafoglio futuro definito per SellRapido, Amazon, eMAG, Temu e IBS.
- [x] Schema e tipi di dominio iniziali per clienti, identità esterne e indirizzi.
- [x] Anagrafica ordine estesa con numero interno, cliente, origine e snapshot di fatturazione.
- [x] Predisposizione vincolata dell’origine `b2c_ecommerce`, senza funzionalità e-commerce attive.
- [x] Design system e interfaccia operativa responsive per tutte le aree previste.
- [x] Viste presentazionali per elenco e dettaglio clienti.
- [x] Schema iniziale transactional outbox.
- [x] Documentazione architetturale completa.
- [x] Confronto architetturale con le pratiche Symfony attuali.
- [x] Pull request tecniche sostituite chiuse.

## Priorità immediata

La prossima sequenza deve consolidare il composition root e produrre la prima capacità di dominio verificabile:

1. container dependency injection compilato;
2. configurazioni tipizzate e Clock iniettato;
3. aggregato ordine e transizioni deterministiche;
4. repository PostgreSQL;
5. persistenza dell’evento outbox nella stessa transazione.

## Fase 0 — Composition root e primitive condivise

- [ ] Introdurre il container basato su `symfony/dependency-injection`.
- [ ] Configurare servizi privati per impostazione predefinita.
- [ ] Usare constructor injection in ogni servizio applicativo e infrastrutturale.
- [ ] Definire alias espliciti tra interfacce e implementazioni.
- [ ] Definire named alias per client e adapter multipli.
- [ ] Definire tag e tagged iterator per handler outbox, adapter e policy.
- [ ] Compilare e validare il container in CI.
- [ ] Generare la cache del container production associata al commit.
- [ ] Introdurre `ApplicationConfig`, `DatabaseConfig`, `RedisConfig`, `ProxyConfig` e `IntegrationConfig`.
- [ ] Confinare lettura di ambiente e secret al composition root.
- [ ] Introdurre un’interfaccia Clock e implementazioni system/test.
- [ ] Aggiungere test del grafo servizi, alias, tag e configurazioni mancanti.

**Gate:** l’applicazione avvia HTTP, CLI e test attraverso lo stesso container compilabile; servizi di dominio e casi d’uso ricevono dipendenze tramite costruttore.

## Fase 1 — Dominio ordine

- [ ] Definire l’aggregato `Order` e l’entità `OrderLine` con invarianti esplicite.
- [x] Rinominare gli stati semanticamente ambigui `complete` e `completed` prima della presenza di dati reali.
- [ ] Definire la macchina a stati deterministica.
- [ ] Definire transizioni ammesse, prerequisiti, errori di dominio ed eventi prodotti.
- [ ] Modellare quantità ordinate, disponibili, spedibili e annullabili.
- [ ] Introdurre storico delle transizioni ordine.
- [ ] Introdurre optimistic locking basato sul campo `version`.
- [ ] Usare Clock per ogni decisione temporale del dominio.
- [ ] Aggiungere test unitari esaustivi delle transizioni, invarianti e condizioni temporali.

**Gate:** ogni modifica di stato attraversa un metodo di dominio testato; transizioni arbitrarie risultano impossibili.

## Fase 1A — Anagrafiche clienti e ordini

- [x] Definire codice cliente, stato, tipo, contatti e dati fiscali opzionali.
- [x] Separare identità canonica e identità esterne per sorgente, account e ID cliente.
- [x] Evitare deduplicazione automatica basata sulla sola email.
- [x] Modellare indirizzi attivi e predefiniti di spedizione e fatturazione.
- [x] Collegare opzionalmente l’ordine al cliente senza perdere lo storico alla cancellazione.
- [x] Introdurre numero ordine interno univoco, origine e data ordine.
- [x] Separare rubrica cliente e snapshot storici di spedizione e fatturazione.
- [x] Predisporre l’origine ordine `b2c_ecommerce` con vincoli PostgreSQL.
- [ ] Definire aggregato `Customer` e policy di modifica, archiviazione, merge e anonimizzazione.
- [ ] Definire `CustomerRepository` e query repository per clienti e ordini.
- [ ] Implementare casi d’uso di creazione, modifica, consultazione e associazione identità.
- [ ] Implementare ricerca paginata e criteri espliciti di riconciliazione.
- [ ] Collegare UI e API soltanto dopo autenticazione, autorizzazione, CSRF e audit.
- [ ] Definire retention e procedure per diritti dell’interessato e obblighi di conservazione ordine.

**Gate:** cliente e ordine sono gestibili tramite casi d’uso transazionali, autorizzati e auditati; nessuna fusione o cancellazione modifica dati storici senza policy esplicita.

## Fase 2 — Persistenza e transazioni

- [ ] Definire le porte `CustomerRepository`, `OrderRepository` e `MarketplaceRepository`.
- [ ] Implementare repository PostgreSQL espliciti.
- [ ] Definire il transaction boundary applicativo.
- [ ] Implementare transaction manager o Unit of Work esplicita.
- [ ] Persistire dominio e messaggi outbox nella stessa transazione PostgreSQL.
- [ ] Implementare mapping tra record, aggregato e value object.
- [ ] Implementare controllo versione atomico per optimistic locking.
- [ ] Aggiungere test integration su rollback, concorrenza, optimistic locking e idempotenza.

**Gate:** un aggiornamento ordine e il relativo evento outbox vengono confermati o annullati insieme.

## Fase 3 — Contratti, DTO e client di integrazione

- [x] Introdurre `ExternalOrderLine` al posto degli array strutturati.
- [x] Introdurre `MarketplaceChannel`, `MarketplaceConnector` ed `ExternalOrderReference`.
- [ ] Introdurre `SpaceOrderLine` al posto degli array strutturati.
- [ ] Introdurre `ShipmentPackage` con peso, lunghezza, larghezza e altezza.
- [ ] Definire peso reale, peso volumetrico e peso tariffabile.
- [ ] Introdurre risultati tipizzati per ogni operazione adapter.
- [ ] Introdurre errori tipizzati: temporaneo, definitivo, validazione, autenticazione e rate limit.
- [ ] Integrare `symfony/validator` ai confini HTTP e provider.
- [ ] Valutare `symfony/serializer` per mapping controllato verso DTO.
- [ ] Definire versione di schema per messaggi e payload persistiti.
- [ ] Integrare `symfony/http-client` con scoped client per provider.
- [ ] Configurare base URI, TLS, timeout di connessione, inattività e durata massima.
- [ ] Limitare redirect e dimensione delle risposte.
- [ ] Implementare allowlist host e protezione SSRF.
- [ ] Definire retry HTTP esclusivamente per errori temporanei e operazioni idempotenti.
- [ ] Definire un budget totale che coordini retry HTTP e retry outbox.
- [ ] Definire timeout, rate limit e idempotency key nei contratti applicativi.
- [ ] Definire redazione e persistenza controllata di request e response tecniche.
- [ ] Aggiungere MockHttpClient o equivalente per test deterministici.

**Gate:** i casi d’uso ricevono e restituiscono tipi applicativi; payload provider e array generici restano confinati negli adapter; ogni client applica una policy di rete verificata.

## Fase 4 — Transactional outbox, worker e scheduler

- [x] Schema outbox con idempotency key, tentativi, lock token, worker identity e stati terminali.
- [ ] Implementare scrittura outbox tramite repository transazionale.
- [ ] Implementare claim atomico con `FOR UPDATE SKIP LOCKED`.
- [ ] Implementare worker concorrenti con identity univoca.
- [ ] Implementare lock con scadenza e recovery.
- [ ] Implementare heartbeat per handler di lunga durata.
- [ ] Implementare timeout per handler.
- [ ] Implementare retry con exponential backoff e jitter.
- [ ] Implementare dead letter e gestione manuale degli errori definitivi.
- [ ] Implementare handler registry tramite servizi taggati.
- [ ] Rendere ogni handler idempotente o protetto da chiave stabile.
- [ ] Aggiungere versione schema ai messaggi persistiti.
- [ ] Gestire messaggi indecodificabili dopo variazioni di codice.
- [ ] Resettare servizi stateful tra job.
- [ ] Implementare graceful shutdown su `SIGTERM` e `SIGINT`.
- [ ] Definire limiti di memoria, tempo e job per processo.
- [ ] Definire supervisor o orchestratore e strategia di restart.
- [ ] Esporre liveness, readiness e statistiche del worker.
- [ ] Implementare comandi autorizzati per inspect, retry, replay e rimozione dead letter.
- [ ] Implementare scheduler per import ordini, disponibilità, tracking e riconciliazione.
- [ ] Proteggere scheduler leader e job globali tramite lock distribuito.
- [ ] Definire timezone, jitter, overlap policy e misfire policy per ogni job.
- [ ] Persistire cursori e watermark dei job nel database.
- [ ] Implementare quote provider tramite rate limiter distribuito.
- [ ] Aggiungere metriche su coda, tentativi, latenza, età e fallimenti.
- [ ] Aggiungere test di concorrenza, crash recovery, graceful shutdown e idempotenza.
- [ ] Aggiungere test di compatibilità dei messaggi tra release consecutive.

**Gate:** worker multipli operano contemporaneamente senza doppie delivery; ogni fallimento raggiunge retry o dead letter; scheduler e cursori recuperano correttamente dopo downtime.

## Fase 5 — Prima vertical slice Marketplace → HAPA → Space

- [ ] Completare la discovery descritta in [`MARKETPLACES.md`](MARKETPLACES.md) per SellRapido, Amazon, eMAG, Temu e IBS.
- [ ] Scegliere il primo account-canale e il relativo connettore di riferimento.
- [ ] Garantire un solo connettore di import attivo per account-canale.
- [ ] Implementare una suite di conformità condivisa per tutti gli adapter marketplace.
- [ ] Implementare import incrementale degli ordini.
- [ ] Gestire paginazione, cursori e finestre temporali.
- [ ] Rendere l’import idempotente su marketplace e ID ordine esterno.
- [ ] Implementare accettazione dell’ordine.
- [ ] Implementare recupero e normalizzazione dell’indirizzo di spedizione.
- [ ] Persistire ordine, righe e indirizzo.
- [ ] Inviare l’ordine a Space tramite API.
- [ ] Persistire identificativo Space e ogni tentativo di delivery.
- [ ] Implementare riconciliazione tra stato interno, marketplace e Space.
- [ ] Implementare verifica firma, anti-replay e idempotenza per eventuali webhook provider.
- [ ] Coprire il flusso con adapter fake, MockHttpClient, test integration e test end-to-end.

**Gate:** un ordine reale o sandbox attraversa importazione, accettazione, indirizzo, persistenza e invio a Space con retry, sicurezza di rete e tracciabilità completa.

## Fase 6 — Disponibilità, magazzino e picking

- [ ] Implementare aggiornamento disponibilità da Space.
- [ ] Modellare `pick_sessions` e `pick_tasks`.
- [ ] Modellare scansioni barcode e anomalie di scansione.
- [ ] Gestire operatore, postazione, timestamp e audit delle attività.
- [ ] Implementare disponibilità completa e parziale.
- [ ] Implementare decisioni su quantità da spedire e quantità da annullare.
- [ ] Introdurre approvazione esplicita per i parziali.
- [ ] Implementare riconciliazione tra disponibilità, picking e quantità finali.
- [ ] Applicare autorizzazione per operatore, postazione e azione.

**Gate:** il sistema ricostruisce chi ha preparato ogni riga, quali barcode sono stati letti e come sono state determinate le quantità finali.

## Fase 7 — GLS, colli e tracking

- [ ] Modellare uno o più colli per spedizione.
- [ ] Calcolare peso volumetrico e peso tariffabile per collo.
- [ ] Completare `ShipmentRequest` con contatti, servizio, note e opzioni GLS.
- [ ] Implementare creazione spedizione GLS.
- [ ] Implementare generazione, memorizzazione e accesso controllato all’etichetta.
- [ ] Implementare verifica di content type, dimensione e filename delle label.
- [ ] Implementare ristampa e recupero label.
- [ ] Implementare annullamento spedizione.
- [ ] Implementare recupero e riconciliazione dello stato spedizione.
- [ ] Inviare tracking e fulfilment al marketplace.
- [ ] Gestire tracking e quantità per ordini parziali.

**Gate:** ordine, colli, label e tracking risultano collegati, idempotenti, autorizzati e riconciliabili con GLS e marketplace.

## Fase 8 — Autenticazione e pannello operativo

- [x] Implementare layout applicativo, navigazione responsive e componenti condivisi.
- [x] Implementare le schermate di login e recupero accesso in stato non operativo.
- [x] Implementare le viste di dashboard, clienti, dettaglio cliente, ordini, dettaglio ordine, picking, spedizioni, automazioni, integrazioni, audit, utenti, profilo e impostazioni.
- [x] Implementare stati vuoti espliciti senza dati dimostrativi.
- [x] Applicare escaping centralizzato, CSP, no-store e header di isolamento alle risposte UI.
- [ ] Introdurre view model immutabili collegati alle query applicative.
- [ ] Integrare i componenti Symfony Security necessari al pannello.
- [ ] Implementare utenti, ruoli e permessi.
- [ ] Implementare password hashing aggiornabile e rehash trasparente.
- [ ] Implementare reset password con token monouso, scadenza e hash a riposo.
- [ ] Implementare gestione sessioni con cookie `Secure`, `HttpOnly` e `SameSite`.
- [ ] Ruotare l’identificativo sessione dopo login e variazione privilegi.
- [ ] Implementare timeout di inattività e durata massima assoluta.
- [ ] Implementare revoca sessioni e invalidazione dopo reset password o cambio ruolo.
- [ ] Implementare MFA per ruoli amministrativi e azioni ad alto impatto.
- [ ] Applicare CSRF al login e a ogni operazione mutativa.
- [ ] Implementare login throttling per account e IP.
- [ ] Implementare autorizzazione deny-by-default per route e azione.
- [ ] Implementare policy o voter per ordine, spedizione, retry, replay e amministrazione.
- [ ] Implementare reautenticazione per operazioni sensibili.
- [ ] Implementare dashboard ordini e filtri operativi.
- [ ] Implementare dettaglio ordine, righe, transizioni e tentativi esterni.
- [ ] Implementare azioni manuali controllate: retry, revisione, approvazione parziale e annullamento.
- [ ] Implementare visibilità su outbox, dead letter e riconciliazioni.
- [ ] Registrare ogni azione operativa nell’audit log.
- [ ] Aggiungere test matrice ruoli × azioni, CSRF, session rotation, throttling e revoca.

**Gate:** ogni azione mutativa richiede permesso esplicito, sessione valida e protezione CSRF; azioni ad alto impatto richiedono controllo rafforzato e producono audit correlato.

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
- [ ] Configurare rate limiting volumetrico sul reverse proxy.
- [ ] Validare container DI e configurazioni come gate di deploy.
- [ ] Coordinare deploy e graceful shutdown dei worker.
- [ ] Aggiungere smoke test di tutte le route pubbliche con URL espliciti.
- [ ] Eseguire test di carico sui flussi di import, worker e picking.

**Gate:** backup e restore sono provati, alert e runbook sono operativi, segreti e immagini seguono una policy verificabile e i worker attraversano deploy controllati.

## Fase 10 — Futuro e-commerce B2C

Questa fase resta un TODO di prodotto. La presenza dell’origine ordine nel database non rende operativo alcun flusso pubblico.

- [ ] Definire storefront, account cliente, verifica email, consensi e recupero accesso.
- [ ] Modellare catalogo, prodotti, varianti, contenuti e disponibilità pubblicabile.
- [ ] Modellare listini, promozioni, imposte, valute e arrotondamenti.
- [ ] Implementare carrello persistente e checkout idempotente.
- [ ] Calcolare spedizione, sconti, imposte e totale autorevole lato server.
- [ ] Integrare provider di pagamento con SCA, webhook firmati e riconciliazione.
- [ ] Implementare autorizzazioni, catture, rimborsi, storni e gestione degli errori.
- [ ] Trasformare il checkout confermato in ordine HAPA senza duplicati.
- [ ] Implementare conferme, notifiche, area personale, resi e cancellazioni.
- [ ] Implementare privacy web, cookie, antifrode, rate limiting e protezioni anti-abuso.
- [ ] Coprire acquisto, pagamento, fulfilment, rimborso e recovery con test end-to-end.

**Gate:** un acquisto B2C attraversa checkout, pagamento e creazione ordine in modo idempotente, sicuro, riconciliabile e conforme alle policy sui dati personali.

## Debito tecnico controllato

Questi interventi accompagnano le prime fasi e vengono chiusi prima dell’attivazione degli adapter reali:

- [x] Rimuovere `DB_CONNECTION` finché PostgreSQL resta l’unico database supportato.
- [x] Limitare l’ambiente del container migration alle sole variabili PostgreSQL.
- [x] Sostituire la costante della migrazione minima con un manifest di schema versionato.
- [ ] Dichiarare una politica univoca per le migrazioni: rollback completo oppure forward-only.
- [ ] Definire namespace, TTL, invalidazione e failure policy delle cache Redis.
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

Il flusso è considerato concluso quando supera anche i gate trasversali di sicurezza, retry, autorizzazione, osservabilità e recovery.
