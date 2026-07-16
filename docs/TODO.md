# Roadmap HAPA

Ultimo riesame: 16 luglio 2026.

Questo documento ordina il lavoro per dipendenze tecniche, valore operativo e rischio. Lo stato viene aggiornato a ogni merge significativo.

Riferimenti:

- [`ARCHITECTURE.md`](ARCHITECTURE.md);
- [`CATALOG_PRICING.md`](CATALOG_PRICING.md);
- [`CARRIERS.md`](CARRIERS.md);
- [`DEVELOPMENT_WORKFLOW.md`](DEVELOPMENT_WORKFLOW.md);
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
- [x] Contratto Shipping provider-neutral e contratti iniziali tipizzati per Marketplace, Space, GLS e BRT.
- [x] Dipendenze tra moduli dichiarate e verificate contro import illegittimi e cicli.
- [x] Distinzione tipizzata tra canale marketplace e connettore tecnico.
- [x] Portafoglio futuro definito per SellRapido, Amazon, eMAG, Temu e IBS.
- [x] Modulo Catalog con prezzo in unità minori, scorta di sicurezza, quantità vendibile e motore ricarichi deterministico.
- [x] Schema PostgreSQL per articoli, regole prezzo e offerte marketplace versionate.
- [x] Contratti tipizzati per catalogo Space incrementale e pubblicazione offerte marketplace.
- [x] Schema e tipi di dominio iniziali per clienti, identità esterne e indirizzi.
- [x] Anagrafica ordine estesa con numero interno, cliente, origine e snapshot di fatturazione.
- [x] Aggregato ordine, righe immutabili, macchina a stati, eventi e storico versionato delle transizioni.
- [x] Predisposizione vincolata dell’origine `b2c_ecommerce`, senza funzionalità e-commerce attive.
- [x] Design system e interfaccia operativa responsive per tutte le aree previste.
- [x] Viste presentazionali per elenco e dettaglio clienti.
- [x] Schema iniziale transactional outbox.
- [x] Composition root separato, container compilato, configurazioni tipizzate e Clock iniettato.
- [x] Repository PostgreSQL ordine, transaction manager, optimistic locking e outbox atomica.
- [x] Worker outbox one-shot con claim concorrente, retry, dead letter e lock recovery.
- [x] Scheduler persistente con gli otto job ordini/catalogo/spedizioni censiti e disattivati fino agli adapter reali.
- [x] Documentazione architetturale completa.
- [x] Confronto architetturale con le pratiche Symfony attuali.
- [x] Pull request tecniche sostituite chiuse.

## Priorità immediata

La prossima sequenza deve collegare clienti, sicurezza e primo provider alla base transazionale ora disponibile:

1. aggregato e repository cliente, query paginata per clienti e ordini;
2. autenticazione, autorizzazione, CSRF e audit delle azioni;
3. discovery e adapter del primo account-canale;
4. vertical slice Marketplace → HAPA → Space per gli ordini;
5. vertical slice Space → pricing HAPA → primo marketplace per catalogo e offerte;
6. osservabilità e gestione autorizzata delle dead letter.

## Fase 0 — Composition root e primitive condivise

- [x] Introdurre il container basato su `symfony/dependency-injection`.
- [x] Configurare servizi privati per impostazione predefinita.
- [x] Usare constructor injection nei servizi applicativi e infrastrutturali implementati.
- [x] Definire alias espliciti tra interfacce e implementazioni.
- [ ] Definire named alias per client e adapter multipli.
- [x] Definire tag e tagged iterator per gli handler outbox.
- [x] Compilare e validare il container nei test e in CI.
- [ ] Generare la cache del container production associata al commit.
- [x] Introdurre `ApplicationConfig`, `DatabaseConfig`, `RedisConfig`, `ProxyConfig`, `IntegrationConfig` e `AutomationConfig`.
- [x] Confinare lettura di ambiente e secret al configuration loader del composition root.
- [x] Introdurre un’interfaccia Clock e implementazioni system/test.
- [x] Aggiungere test del grafo servizi, alias e configurazioni mancanti.

**Gate:** l’applicazione avvia HTTP, CLI e test attraverso lo stesso container compilabile; servizi di dominio e casi d’uso ricevono dipendenze tramite costruttore.

## Fase 1 — Dominio ordine

- [x] Definire l’aggregato `Order` e l’entità `OrderLine` con invarianti esplicite.
- [x] Rinominare gli stati semanticamente ambigui `complete` e `completed` prima della presenza di dati reali.
- [x] Definire la macchina a stati deterministica.
- [x] Definire transizioni ammesse, prerequisiti, errori di dominio ed eventi prodotti.
- [x] Modellare quantità ordinate, disponibili, spedibili e annullabili.
- [x] Introdurre storico versionato delle transizioni ordine.
- [x] Introdurre versione incrementale e verifica esplicita della versione attesa nel dominio.
- [ ] Produrre tramite Clock applicativo ogni istante passato alle decisioni di dominio.
- [x] Aggiungere test unitari esaustivi delle transizioni, invarianti e condizioni temporali.

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
- [x] Implementare il repository PostgreSQL esplicito per `Order` (restano Customer e Marketplace).
- [x] Definire il transaction boundary applicativo per il salvataggio ordine.
- [x] Implementare transaction manager esplicito.
- [x] Persistire ordine e messaggi outbox nella stessa transazione PostgreSQL.
- [x] Implementare mapping tra record, aggregato ordine e value object.
- [x] Implementare controllo versione atomico per optimistic locking ordine.
- [x] Aggiungere integration test su mapping, optimistic locking, outbox e handler idempotente.
- [ ] Aggiungere test multi-connessione su rollback e concorrenza reale.

**Gate:** un aggiornamento ordine e il relativo evento outbox vengono confermati o annullati insieme.

## Fase 3 — Contratti, DTO e client di integrazione

- [x] Introdurre `ExternalOrderLine` al posto degli array strutturati.
- [x] Introdurre `MarketplaceChannel`, `MarketplaceConnector` ed `ExternalOrderReference`.
- [x] Introdurre `Money`, batch/cursore Space e DTO di pubblicazione offerta marketplace.
- [x] Estrarre `CarrierAdapter`, `CarrierCode`, `ShipmentRequest` e `ShipmentResult` nel contratto comune `Shipping`.
- [x] Registrare i moduli provider GLS e BRT dietro il contratto comune.
- [ ] Introdurre `SpaceOrderLine` al posto degli array strutturati.
- [ ] Introdurre `ShipmentPackage` con peso, lunghezza, larghezza e altezza.
- [ ] Definire peso reale, peso volumetrico e peso tariffabile.
- [ ] Introdurre risultati tipizzati per ogni operazione adapter.
- [ ] Introdurre errori tipizzati: temporaneo, definitivo, validazione, autenticazione e rate limit.
- [ ] Integrare `symfony/validator` ai confini HTTP e provider.
- [ ] Valutare `symfony/serializer` per mapping controllato verso DTO.
- [x] Definire versione di schema per messaggi e payload persistiti.
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
- [x] Implementare scrittura outbox tramite repository transazionale.
- [x] Implementare claim atomico con `FOR UPDATE SKIP LOCKED`.
- [x] Implementare worker concorrenti con identity univoca.
- [x] Implementare lock con scadenza e recovery.
- [ ] Implementare heartbeat per handler di lunga durata.
- [ ] Implementare timeout per handler.
- [x] Implementare retry con exponential backoff e jitter.
- [x] Implementare lo stato terminale dead letter.
- [ ] Implementare la gestione manuale autorizzata degli errori definitivi.
- [x] Implementare handler registry tramite servizi taggati.
- [x] Proteggere l’handler audit ordine con chiave outbox stabile.
- [x] Aggiungere versione schema ai messaggi persistiti.
- [ ] Gestire messaggi indecodificabili dopo variazioni di codice.
- [ ] Resettare servizi stateful tra job.
- [ ] Implementare graceful shutdown su `SIGTERM` e `SIGINT`.
- [ ] Definire limiti di memoria, tempo e job per processo.
- [ ] Definire supervisor o orchestratore e strategia di restart.
- [ ] Esporre liveness, readiness e statistiche del worker.
- [ ] Implementare comandi autorizzati per inspect, retry, replay e rimozione dead letter.
- [x] Implementare scheduler persistente e censire gli otto job ordini/catalogo/spedizioni a dieci minuti.
- [x] Proteggere claim scheduler e job globali tramite lock PostgreSQL.
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

## Fase 5A — Catalogo Space, ricarichi e offerte marketplace

- [x] Definire ownership di prezzo base, disponibilità fisica, scorta di sicurezza e offerta pubblicata.
- [x] Modellare importi monetari senza `float` e disponibilità vendibile mai negativa.
- [x] Implementare regole percentuali, importo fisso e prezzo fisso con precedenza deterministica.
- [x] Aggiungere limiti minimo/massimo, priorità e controllo valuta.
- [x] Creare schema `catalog_items`, `pricing_rules` e `marketplace_offers` con vincoli e versioni.
- [x] Definire `SpaceCatalogAdapter` con cursore e batch incrementale.
- [x] Definire `MarketplaceOfferAdapter` con prezzo, quantità, versione e idempotency key.
- [x] Censire `sync_space_catalog` e `publish_marketplace_offers` come job disabilitati.
- [x] Aggiungere la pagina `/ui/catalog` senza simulare dati o adapter attivi.
- [ ] Definire repository e casi d’uso transazionali per articoli, regole e offerte.
- [ ] Produrre outbox atomica quando prezzo o quantità desiderati cambiano.
- [ ] Implementare il client Space dopo discovery di endpoint, cursori, versioni, quote e semantica stock.
- [ ] Implementare la pubblicazione sul primo account-canale dopo la discovery marketplace.
- [ ] Definire una policy approvata per dati Space scaduti e arresto della pubblicazione.
- [ ] Implementare riconciliazione, metriche, audit e gestione autorizzata delle regole.
- [ ] Collegare read model e comandi UI dopo autenticazione, autorizzazione e CSRF.
- [ ] Coprire crash a metà batch, versioni fuori ordine, timeout ambiguo, rounding e duplicati.

**Gate:** un articolo sandbox attraversa Space → HAPA → marketplace con cursore transazionale, ricarico verificabile, scorta di sicurezza, idempotenza, riconciliazione e visibilità operativa completa.

## Fase 6 — Disponibilità ordine, magazzino e picking

- [ ] Implementare aggiornamento della disponibilità sulle righe ordine da Space, distinto dallo stock catalogo.
- [ ] Modellare `pick_sessions` e `pick_tasks`.
- [ ] Modellare scansioni barcode e anomalie di scansione.
- [ ] Gestire operatore, postazione, timestamp e audit delle attività.
- [ ] Implementare disponibilità completa e parziale.
- [ ] Implementare decisioni su quantità da spedire e quantità da annullare.
- [ ] Introdurre approvazione esplicita per i parziali.
- [ ] Implementare riconciliazione tra disponibilità, picking e quantità finali.
- [ ] Applicare autorizzazione per operatore, postazione e azione.

**Gate:** il sistema ricostruisce chi ha preparato ogni riga, quali barcode sono stati letti e come sono state determinate le quantità finali.

## Fase 7 — Corrieri, colli e tracking

- [x] Introdurre il confine provider-neutral `Shipping` e i codici persistiti `GLS`/`BRT`.
- [x] Rendere provider-neutral lo stato ordine `ready_for_carrier`.
- [ ] Modellare uno o più colli per spedizione.
- [ ] Calcolare peso volumetrico e peso tariffabile per collo.
- [ ] Completare `ShipmentRequest` con contatti e dati logistici comuni.
- [ ] Completare la discovery verificata GLS descritta in [`CARRIERS.md`](CARRIERS.md).
- [ ] Completare la discovery verificata BRT descritta in [`CARRIERS.md`](CARRIERS.md).
- [ ] Definire DTO provider-specifici per servizi, note e opzioni senza contaminare il contratto comune.
- [ ] Implementare creazione spedizione GLS.
- [ ] Implementare creazione spedizione BRT.
- [ ] Implementare una suite di conformità condivisa per tutti gli adapter corriere.
- [ ] Implementare generazione, memorizzazione e accesso controllato all’etichetta.
- [ ] Implementare verifica di content type, dimensione e filename delle label.
- [ ] Implementare ristampa e recupero label.
- [ ] Implementare annullamento spedizione.
- [ ] Implementare recupero e riconciliazione dello stato spedizione.
- [ ] Inviare tracking e fulfilment al marketplace.
- [ ] Gestire tracking e quantità per ordini parziali.

**Gate:** ordine, colli, label e tracking risultano collegati, idempotenti, autorizzati e riconciliabili con il corriere selezionato e il marketplace; almeno GLS e BRT superano la stessa suite di conformità prima dell’attivazione.

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
- [ ] Estendere il catalogo operativo con prodotti, varianti, contenuti e disponibilità specifica dello storefront.
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
8. generazione colli, spedizione ed etichetta tramite GLS o BRT;
9. invio tracking e fulfilment al marketplace;
10. visibilità completa nel pannello, nei log, nell’audit, nelle delivery e nelle riconciliazioni.

Il flusso è considerato concluso quando supera anche i gate trasversali di sicurezza, retry, autorizzazione, osservabilità e recovery.
