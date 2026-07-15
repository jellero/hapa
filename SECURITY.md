# Politica di sicurezza

## Ambito

HAPA gestisce ordini, dati di spedizione e integrazioni con provider esterni. Sicurezza applicativa, isolamento dei dati, tracciabilità e minimizzazione delle informazioni personali sono requisiti di progetto.

## Segnalazione

Le vulnerabilità devono essere comunicate al proprietario del repository attraverso un canale privato. Issue, pull request e discussioni pubbliche non devono contenere dettagli sfruttabili, credenziali, payload reali o dati personali.

## Segreti

- credenziali, token, certificati e chiavi restano fuori dal repository;
- la produzione usa secret file montati in `/run/secrets` o un secret manager equivalente;
- PostgreSQL e Redis ricevono i segreti tramite file, senza inserirli nel comando del processo;
- i file locali sotto `secrets/` sono esclusi da Git e devono avere permessi restrittivi;
- i valori di `.env.example` sono esclusivamente locali;
- i segreti production devono essere ruotabili senza modifica del codice;
- log, audit e delivery tecniche applicano redazione prima della persistenza.

## Repository e supply chain

- il repository proprietario deve mantenere visibilità privata;
- `composer.lock` viene versionato;
- la CI esegue `composer audit --locked`;
- le action CI sono referenziate tramite commit SHA;
- Dependabot propone aggiornamenti per Composer, GitHub Actions e immagini Docker;
- gli aggiornamenti delle dipendenze passano attraverso pull request e pipeline completa;
- immagini e artifact production vengono associati al commit di origine.

## Dati personali

Prima dell’attivazione degli adapter reali devono essere definite:

- minimizzazione dei payload salvati;
- campi soggetti a redazione o cifratura;
- retention per indirizzi, API log, audit e label;
- procedure di cancellazione o anonimizzazione;
- autorizzazioni operative per visualizzazione e ristampa.

## Produzione

La configurazione production impone:

- `APP_DEBUG=false`;
- `APP_URL` HTTPS;
- terminazione TLS su reverse proxy o load balancer esterno;
- HSTS sul punto di terminazione TLS;
- ascolto HTTP applicativo limitato a loopback per impostazione predefinita;
- secret file PostgreSQL e Redis espliciti e sufficientemente robusti;
- filesystem applicativo read-only;
- processi non privilegiati;
- reti interne per database e cache;
- endpoint separati per liveness e readiness;
- readiness limitata alle reti private e priva dei dettagli dei componenti in produzione;
- messaggi delle eccezioni esclusi dai log production.

## Risposta agli incidenti

Ogni incidente deve essere collegato tramite correlation ID a log applicativi, delivery esterne e audit. Il runbook operativo dovrà includere contenimento, rotazione segreti, riconciliazione ordini, ripristino e verifica post-incidente.
