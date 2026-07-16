# Politica di sicurezza HAPA

Ultimo riesame: 16 luglio 2026.

## Ambito

HAPA gestisce ordini, dati di spedizione e integrazioni con provider esterni. Sicurezza applicativa, isolamento dei dati, tracciabilità, minimizzazione delle informazioni personali e continuità operativa sono requisiti architetturali.

Riferimenti:

- [`ARCHITECTURE.md`](ARCHITECTURE.md): architettura generale;
- [`SYMFONY_ALIGNMENT.md`](SYMFONY_ALIGNMENT.md): allineamento con le primitive Symfony;
- [`TODO.md`](TODO.md): interventi aperti e gate.

## Principi

- deny-by-default per accessi e autorizzazioni;
- least privilege per utenti, processi, database, cache e secret;
- defense in depth tra reverse proxy, Nginx, applicazione, PostgreSQL e container;
- validazione di ogni input esterno;
- separazione tra dominio, diagnostica e payload provider;
- idempotenza e audit per operazioni irreversibili;
- segreti e dati personali esclusi da log e messaggi di errore;
- PostgreSQL come sorgente autorevole del processo.

## Segnalazione

Le vulnerabilità devono essere comunicate al proprietario del repository attraverso un canale privato. Issue, pull request e discussioni pubbliche devono restare prive di dettagli sfruttabili, credenziali, payload reali e dati personali.

## Segreti

- credenziali, token, certificati e chiavi restano fuori dal repository;
- la produzione usa secret file montati in `/run/secrets` o un secret manager equivalente;
- PostgreSQL e Redis ricevono i segreti tramite file;
- i file locali sotto `secrets/` sono esclusi da Git e usano permessi restrittivi;
- i valori di `.env.example` hanno esclusivo scopo locale;
- i segreti production devono essere ruotabili indipendentemente dal codice;
- log, audit e delivery tecniche applicano redazione prima della persistenza;
- ogni rotazione prevede verifica applicativa e procedura di rollback;
- immagini e artifact restano privi di secret decrittati;
- eventuali vault Symfony possono essere usati come alternativa, mantenendo una sola sorgente autorevole per ambiente.

## Repository e supply chain

- il repository proprietario richiede visibilità privata; lo stato aperto è tracciato nella roadmap;
- `composer.lock` viene versionato;
- la CI esegue `composer audit --locked`;
- le action CI sono referenziate tramite commit SHA;
- Dependabot propone aggiornamenti per Composer, GitHub Actions e immagini Docker;
- gli aggiornamenti delle dipendenze attraversano pull request e pipeline completa;
- immagini e artifact production vengono associati al commit di origine;
- le immagini base production verranno fissate tramite digest prima dell’esercizio reale;
- il container dependency injection verrà compilato e validato in CI;
- dipendenze introdotte per adapter o sicurezza richiedono valutazione di manutenzione, licenza e advisory.

## Dati personali

Prima dell’attivazione degli adapter reali devono essere definite e verificate:

- minimizzazione dei payload salvati;
- campi soggetti a redazione, cifratura o tokenizzazione;
- retention per indirizzi, API log, audit e label;
- procedure di cancellazione o anonimizzazione;
- autorizzazioni operative per visualizzazione, esportazione e ristampa;
- tracciamento delle consultazioni sensibili;
- separazione tra dati di dominio e diagnostica tecnica;
- accesso ai backup limitato e auditato;
- eliminazione sicura di file temporanei ed etichette scadute.

## Autenticazione e password

Il pannello operativo adotterà un contesto di autenticazione principale basato su sessione. Eventuali accessi macchina tramite token appartengono a un contesto distinto quando esiste un requisito effettivo.

Il layer di presentazione corrente espone soltanto route GET, stati vuoti e controlli disabilitati. Login e recupero accesso sono schermate non operative: non ricevono credenziali finché sessione, throttling, CSRF e repository utenti non sono implementati. Nessun dato personale o operativo viene inserito per finalità dimostrative.

Requisiti:

- password hashing aggiornabile tramite algoritmo `auto` o equivalente;
- rehash trasparente al login quando parametri o algoritmo diventano obsoleti;
- password assenti da log, audit, eccezioni e payload persistiti;
- token di reset casuali, monouso, con scadenza e valore hashato a riposo;
- risposte di login e recupero account prive di indicazioni sull’esistenza dell’utente;
- invalidazione delle sessioni dopo reset password, disabilitazione utente o modifica dei privilegi;
- MFA per ruoli amministrativi e operazioni ad alto impatto prima dell’esercizio reale;
- protezione da credential stuffing tramite throttling e monitoraggio.

## Sicurezza della sessione

- cookie `Secure`, `HttpOnly` e `SameSite` coerente con il flusso applicativo;
- rotazione dell’identificativo dopo autenticazione e variazione dei privilegi;
- scadenza per inattività e durata massima assoluta;
- logout server-side e revoca della sessione;
- reautenticazione per gestione utenti, secret, annullamenti, ristampa massiva e replay di dead letter;
- sessioni attive consultabili e revocabili da amministratori autorizzati;
- invalidazione delle sessioni esistenti dopo eventi di sicurezza rilevanti;
- clock applicativo iniettato per rendere scadenze e test deterministici.

## CSRF e login throttling

- protezione CSRF sul login e su ogni operazione mutativa basata su cookie;
- token legato all’intenzione o al form quando il rischio lo richiede;
- throttling combinato per account normalizzato e indirizzo IP o rete sorgente;
- limiti distinti per login, recupero credenziali e operazioni amministrative;
- risposta uniforme in caso di limite raggiunto;
- audit e alert su pattern ripetuti o distribuiti.

## Autorizzazione

L’autorizzazione segue deny-by-default:

- controllo per area e route;
- policy o voter per risorsa e azione;
- permessi valutati server-side;
- separazione tra lettura, modifica, approvazione, annullamento, retry, replay e amministrazione;
- verifica della versione dell’ordine prima di azioni concorrenti;
- audit delle operazioni con impatto operativo;
- registrazione dei dinieghi rilevanti con dati minimizzati;
- account di servizio con permessi limitati alla singola integrazione.

## Validazione degli input

Ogni confine applicativo applica validazione strutturata:

- request HTTP e form;
- parametri route e query;
- payload Marketplace, Space e GLS;
- messaggi outbox persistiti;
- file o label ricevuti dai provider;
- configurazioni e feature flag.

La validazione del boundary usa DTO e constraint. Le invarianti del dominio restano protette nel modello e tramite vincoli PostgreSQL.

## Client HTTP e protezione SSRF

Ogni provider usa un client dedicato con:

- base URI fissa e allowlist degli host;
- verifica TLS attiva;
- timeout di connessione, inattività e durata massima;
- limite redirect minimo, preferibilmente zero per operazioni mutative;
- limite alla dimensione della risposta;
- content type atteso e decoding controllato;
- correlation ID e user agent identificabile;
- redazione di `Authorization`, cookie, token e parametri sensibili.

URL derivati da payload esterni vengono validati contro schema e host autorizzati. Reti private, loopback, link-local e metadata endpoint vengono bloccati, salvo allowlist infrastrutturale esplicita.

I retry HTTP vengono applicati soltanto a errori temporanei e operazioni idempotenti. Operazioni mutative richiedono idempotency key o riconciliazione sicura.

Per i marketplace, credenziali, quote, cursori e audit sono isolati per account e connettore. Il canale sorgente viene conservato separatamente dal percorso tecnico: un ordine Amazon ricevuto tramite SellRapido non viene riclassificato come ordine SellRapido. L’attivazione concorrente di un adapter diretto e dell’aggregatore sullo stesso account-canale è vietata per prevenire doppie importazioni e doppie notifiche.

## Webhook e callback in ingresso

Quando un provider usa webhook o callback:

- firma e timestamp vengono verificati sul body originale;
- esiste una finestra massima anti-replay;
- l’identificativo evento è idempotente;
- source IP o certificato vengono verificati quando il provider offre tale garanzia;
- payload e dimensione vengono limitati;
- la risposta HTTP viene prodotta rapidamente e il lavoro prosegue tramite outbox;
- secret di firma supportano rotazione controllata;
- errori di verifica producono audit minimizzato e metrica dedicata.

## Rate limiting

La protezione è distribuita su tre livelli:

1. reverse proxy o frontiera per traffico volumetrico e DoS;
2. applicazione per login, endpoint sensibili e azioni costose;
3. integrazione per quote Marketplace, Space e GLS.

Symfony RateLimiter o un componente equivalente può gestire i limiti applicativi. Lo stato condiviso risiede in Redis o altro backend distribuito; ogni limiter dichiara comportamento in caso di indisponibilità del backend.

## Transactional outbox e worker

La consegna asincrona segue semantica almeno una volta. Ogni handler deve essere idempotente oppure usare una chiave idempotente stabile derivata dall’evento di business.

Requisiti di sicurezza e affidabilità:

- schema versione nei messaggi persistiti;
- validazione prima dell’esecuzione;
- claim atomico e lock con scadenza;
- identity univoca del worker;
- timeout per handler;
- reset dei servizi stateful tra job;
- graceful shutdown su segnali di terminazione;
- retry limitato con backoff e jitter;
- dead letter persistente;
- replay autorizzato, idempotente e auditato;
- accesso ai payload falliti limitato ai ruoli necessari;
- gestione esplicita dei messaggi indecodificabili dopo un deploy.

## Scheduler e lock

Ogni job ricorrente definisce frequenza, timezone, jitter, lock distribuito, policy di sovrapposizione, misfire policy e cursore persistente.

Symfony Lock o un meccanismo equivalente protegge il ruolo di scheduler leader e i job globali. Modifiche concorrenti a un ordine usano optimistic locking e transazioni PostgreSQL.

## File, etichette e documenti

- filename esterni esclusi dai path locali;
- storage tramite identificativi interni;
- content type e dimensione verificati;
- accesso alle label autorizzato e auditato;
- download con header sicuri e disposizione coerente;
- scansione antimalware quando vengono introdotti upload o documenti non generati da provider fidati;
- retention e cancellazione definite;
- file temporanei creati con permessi restrittivi.

## Produzione

La configurazione production impone:

- `APP_DEBUG=false`;
- `APP_URL` HTTPS;
- terminazione TLS su reverse proxy o load balancer esterno;
- HSTS sul punto di terminazione TLS;
- ascolto HTTP applicativo limitato a loopback per impostazione predefinita;
- trusted proxy espliciti;
- secret file PostgreSQL e Redis sufficientemente robusti;
- filesystem applicativo read-only;
- processi non privilegiati;
- capability Linux ridotte;
- reti interne per database e cache;
- endpoint separati per liveness e readiness;
- readiness limitata alle reti private e priva dei dettagli dei componenti in produzione;
- messaggi delle eccezioni esclusi dai log production;
- immagini applicative associate al commit distribuito;
- container DI compilato e immutabile;
- worker supervisionati e arrestati in modo graceful durante il deploy.

## Logging, audit e diagnostica

- ogni richiesta riceve un correlation ID;
- il correlation ID accompagna log, delivery esterne, audit e chiamate provider;
- i log applicativi sono strutturati;
- chiavi e valori sensibili vengono redatti;
- payload completi vengono persistiti soltanto quando necessari e secondo retention;
- gli errori production espongono messaggi generici verso il client;
- metriche e alert escludono dati personali e segreti;
- audit e log tecnici hanno retention e autorizzazioni distinte;
- alert coprono login anomali, dead letter, retry ripetuti, webhook invalidi e variazioni dei privilegi.

## Backup e ripristino

Prima dell’esercizio production devono essere disponibili:

- backup automatici PostgreSQL;
- cifratura e controllo accessi dei backup;
- retention documentata;
- restore periodico verificato;
- definizione di RPO e RTO;
- procedura di ricostruzione delle delivery e riconciliazione degli ordini;
- verifica che secret, file temporanei e cache restino fuori dai backup quando superflui.

## Risposta agli incidenti

Ogni incidente deve essere collegato tramite correlation ID a log applicativi, delivery esterne e audit. Il runbook operativo deve includere:

1. rilevazione e classificazione;
2. contenimento;
3. conservazione delle evidenze;
4. rotazione dei segreti coinvolti;
5. revoca di sessioni e token;
6. verifica di integrità di ordini, spedizioni e tracking;
7. riconciliazione con Marketplace, Space e GLS;
8. ripristino;
9. verifica post-incidente e azioni correttive.
