# Politica di sicurezza HAPA

Ultimo riesame: 15 luglio 2026.

## Ambito

HAPA gestisce ordini, dati di spedizione e integrazioni con provider esterni. Sicurezza applicativa, isolamento dei dati, tracciabilità, minimizzazione delle informazioni personali e continuità operativa sono requisiti architetturali.

Il riferimento tecnico generale è [`ARCHITECTURE.md`](ARCHITECTURE.md). Gli interventi aperti sono tracciati in [`TODO.md`](TODO.md).

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
- ogni rotazione deve prevedere verifica applicativa e procedura di rollback.

## Repository e supply chain

- il repository proprietario richiede visibilità privata; lo stato aperto è tracciato nella roadmap;
- `composer.lock` viene versionato;
- la CI esegue `composer audit --locked`;
- le action CI sono referenziate tramite commit SHA;
- Dependabot propone aggiornamenti per Composer, GitHub Actions e immagini Docker;
- gli aggiornamenti delle dipendenze attraversano pull request e pipeline completa;
- immagini e artifact production vengono associati al commit di origine;
- le immagini base production verranno fissate tramite digest prima dell’esercizio reale.

## Dati personali

Prima dell’attivazione degli adapter reali devono essere definite e verificate:

- minimizzazione dei payload salvati;
- campi soggetti a redazione, cifratura o tokenizzazione;
- retention per indirizzi, API log, audit e label;
- procedure di cancellazione o anonimizzazione;
- autorizzazioni operative per visualizzazione, esportazione e ristampa;
- tracciamento delle consultazioni sensibili;
- separazione tra dati di dominio e diagnostica tecnica.

## Sicurezza applicativa

Le funzionalità operative adotteranno:

- autenticazione con gestione sicura della sessione;
- autorizzazione esplicita per ruolo e permesso;
- CSRF sulle operazioni mutative;
- validazione degli input ai confini HTTP e provider;
- rate limiting sugli endpoint sensibili;
- optimistic locking sulle modifiche concorrenti;
- audit delle azioni manuali;
- errori esterni classificati e privi di segreti nei messaggi persistiti.

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
- immagini applicative associate al commit distribuito.

## Logging, audit e diagnostica

- ogni richiesta riceve un correlation ID;
- il correlation ID accompagna log, delivery esterne, audit e chiamate provider;
- i log applicativi sono strutturati;
- chiavi e valori sensibili vengono redatti;
- payload completi vengono persistiti soltanto quando necessari e secondo retention;
- gli errori production espongono messaggi generici verso il client;
- metriche e alert devono escludere dati personali e segreti.

## Backup e ripristino

Prima dell’esercizio production devono essere disponibili:

- backup automatici PostgreSQL;
- cifratura e controllo accessi dei backup;
- retention documentata;
- restore periodico verificato;
- definizione di RPO e RTO;
- procedura di ricostruzione delle delivery e riconciliazione degli ordini.

## Risposta agli incidenti

Ogni incidente deve essere collegato tramite correlation ID a log applicativi, delivery esterne e audit. Il runbook operativo deve includere:

1. rilevazione e classificazione;
2. contenimento;
3. conservazione delle evidenze;
4. rotazione dei segreti coinvolti;
5. verifica di integrità di ordini, spedizioni e tracking;
6. riconciliazione con Marketplace, Space e GLS;
7. ripristino;
8. verifica post-incidente e azioni correttive.
