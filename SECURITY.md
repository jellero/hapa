# Politica di sicurezza

## Ambito

HAPA gestisce ordini, dati di spedizione e integrazioni con provider esterni. Sicurezza applicativa, isolamento dei dati, tracciabilità e minimizzazione delle informazioni personali sono requisiti di progetto.

## Segnalazione

Le vulnerabilità devono essere comunicate al proprietario del repository attraverso un canale privato. Issue, pull request e discussioni pubbliche non devono contenere dettagli sfruttabili, credenziali, payload reali o dati personali.

## Segreti

- credenziali, token, certificati e chiavi restano fuori dal repository;
- la produzione usa secret file montati in `/run/secrets` o un secret manager equivalente;
- PostgreSQL e Redis ricevono i segreti tramite file, senza inserirli nel comando del processo;
- i file locali sotto `secrets/` sono esclusi da Git e hanno permessi restrittivi;
- i segreti production sono ruotabili senza modifica del codice;
- log, audit e delivery tecniche applicano redazione prima della persistenza.

## Repository e supply chain

- il repository proprietario mantiene visibilità privata;
- `composer.lock` viene versionato e verificato con `composer audit --locked`;
- le action CI sono referenziate tramite commit SHA;
- le immagini applicative usano tag associati al commit;
- le immagini base production vengono fornite tramite riferimento completo con digest SHA-256;
- Dependabot propone aggiornamenti per Composer, GitHub Actions e Docker;
- gli aggiornamenti passano attraverso pull request e pipeline completa.

## Dati personali

Prima dell’attivazione degli adapter reali vengono definite minimizzazione dei payload, cifratura, redazione, retention, cancellazione o anonimizzazione e autorizzazioni operative per visualizzazione e ristampa.

## Produzione

La configurazione production impone:

- `APP_DEBUG=false` e `APP_URL` HTTPS;
- terminazione TLS e HSTS sul reverse proxy o load balancer esterno;
- trusted proxy e trusted host espliciti;
- ascolto HTTP applicativo limitato a loopback;
- secret file PostgreSQL e Redis;
- filesystem applicativo read-only e processi non privilegiati;
- rete dati interna per PostgreSQL e Redis;
- rete applicativa separata per PHP, Nginx e traffico verso provider esterni;
- endpoint distinti per liveness Nginx, liveness applicativa e readiness;
- readiness privata e vincolata alla versione dello schema;
- messaggi grezzi delle eccezioni esclusi dai log production e di bootstrap.

## Verifica

La CI avvia PostgreSQL e Redis reali, applica le migrazioni, esegue i test e costruisce il Compose production. Lo smoke test verifica runtime, immagine migrazioni, secret mount e health endpoint.

## Risposta agli incidenti

Ogni incidente viene collegato tramite correlation ID a log applicativi, delivery esterne e audit. Il runbook operativo in `docs/OPERATIONS.md` copre contenimento, rotazione segreti, riconciliazione ordini, ripristino e verifica post-incidente.
