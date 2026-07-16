# Politica di sicurezza HAPA

Ultimo riesame: 16 luglio 2026.

## Ambito

HAPA gestisce anagrafiche clienti, ordini, prodotti, regole commerciali, dati di magazzino e stato applicativo delle integrazioni.

Il runtime asincrono, gli adapter provider, i cursori, i retry e le dead letter provider appartengono al repository autonomo `jellero/hapa-automation`. I due servizi hanno database, credenziali, immagini e cicli di deploy separati e comunicano soltanto tramite RabbitMQ. HAPA contiene esclusivamente il relay di delivery della propria transactional outbox.

## Principi

- deny-by-default per accessi e autorizzazioni;
- least privilege per utenti, processi, database, broker e secret;
- nessun database condiviso tra HAPA e `hapa-automation`;
- validazione di ogni input HTTP o RabbitMQ;
- idempotenza per messaggi ed effetti esterni;
- minimizzazione di dati personali e payload;
- segreti esclusi da codice, log, audit ed eventi;
- audit delle decisioni applicative in HAPA;
- audit tecnico dei provider nel servizio esterno;
- PostgreSQL HAPA come sorgente autorevole del dominio HAPA.

## Segnalazione

Le vulnerabilità devono essere comunicate al proprietario tramite un canale privato. Issue, pull request e discussioni pubbliche non devono contenere credenziali, payload reali, dati personali o dettagli immediatamente sfruttabili.

## Segreti e identità di servizio

HAPA conserva soltanto i propri secret:

- credenziali PostgreSQL HAPA;
- credenziali Redis HAPA;
- credenziali RabbitMQ del publisher HAPA, limitate alle exchange e routing key necessarie;
- secret di sessione e autenticazione quando implementati.

Le credenziali Space, marketplace, GLS e BRT appartengono a `hapa-automation` e non devono essere montate nel container HAPA.

Requisiti:

- secret file o secret manager;
- permessi restrittivi;
- rotazione indipendente dei due servizi;
- account RabbitMQ separati per publisher HAPA e consumer/worker automation;
- permessi del publisher limitati all’exchange `hapa.events` e alle routing key HAPA ammesse;
- virtual host o ACL distinti per ambiente;
- TLS fuori dall’ambiente locale;
- nessun secret in `.env.example`, command line o artifact CI;
- `RABBITMQ_ENABLED=false` fino al completamento del test end-to-end.

## Repository e supply chain

- repository proprietari con visibilità adeguata;
- `composer.lock` versionato;
- `composer audit --locked` in CI;
- GitHub Actions referenziate tramite commit SHA;
- aggiornamenti dipendenze tramite pull request e pipeline;
- immagini associate al commit distribuito;
- immagini base fissate tramite digest prima dell’esercizio reale;
- scansione dipendenze, immagini e secret;
- SBOM e firma artifact quando supportate dall’infrastruttura.

## Dati personali

HAPA tratta email, telefono, indirizzi, identificativi fiscali, dati ordine e spedizione come dati personali o operativi sensibili.

Devono essere definite:

- minimizzazione dei view model e dei messaggi RabbitMQ;
- cifratura o tokenizzazione quando richiesta;
- retention distinta per anagrafiche, ordini, audit e label;
- cancellazione o anonimizzazione controllata;
- autorizzazione per consultazione, export e ristampa;
- tracciamento degli accessi sensibili;
- gestione coerente di backup e diritti dell’interessato.

Un messaggio verso `hapa-automation` contiene soltanto i dati necessari all’operazione. Payload provider grezzi non vengono copiati nel database HAPA.

## Autenticazione e sessione

Il pannello HAPA userà un contesto di autenticazione basato su sessione.

Requisiti:

- password hashing aggiornabile;
- rehash trasparente;
- token reset monouso, con scadenza e hash a riposo;
- risposta uniforme per account esistente o inesistente;
- cookie `Secure`, `HttpOnly` e `SameSite`;
- rotazione sessione dopo login o variazione privilegi;
- timeout per inattività e durata massima;
- revoca server-side;
- MFA per ruoli sensibili;
- throttling per account e rete sorgente;
- audit di login, dinieghi e modifiche privilegiate.

Le schermate correnti restano presentazionali e non devono ricevere credenziali finché questi gate non sono implementati.

## Autorizzazione e CSRF

L’autorizzazione segue deny-by-default:

- permessi per route, risorsa e azione;
- separazione tra lettura, modifica, approvazione e amministrazione;
- CSRF su login e ogni mutazione basata su cookie;
- optimistic locking per modifiche concorrenti;
- reautenticazione per operazioni ad alto impatto;
- audit di ricarichi, ordini, spedizioni, utenti e configurazioni.

La gestione tecnica di retry, dead letter e credenziali provider non viene esposta nella UI HAPA. Un eventuale collegamento operativo con `hapa-automation` deve essere in sola lettura o protetto da un contratto amministrativo separato.

## Validazione degli input

HAPA valida:

- request, route, query e form;
- messaggi RabbitMQ ricevuti;
- envelope, schema version e routing key;
- payload applicativi di prodotti, ordini ed esiti provider;
- configurazioni e feature flag;
- label o documenti prima della loro esposizione.

Le invarianti finali restano nel dominio e nei vincoli PostgreSQL.

## Sicurezza RabbitMQ

Ogni messaggio deve includere:

- `message_id` stabile;
- `event_type` ammesso;
- `schema_version` positivo;
- `occurred_at` UTC;
- `correlation_id`;
- `causation_id` quando disponibile;
- payload JSON object minimizzato.

Il relay HAPA:

- genera `message_id` come UUIDv5 deterministico dalla chiave di idempotenza;
- pubblica con routing key uguale all’event type;
- usa delivery mode persistente e publisher confirm;
- marca la riga completata soltanto dopo conferma del broker;
- ritenta esclusivamente la consegna AMQP;
- non esegue chiamate provider;
- apre le connessioni soltanto durante il comando `outbox:relay`;
- rimane disabilitato per default.

Controlli richiesti:

- allowlist delle routing key;
- limite dimensione messaggi;
- deduplica nel database locale del consumer;
- applicazione del messaggio e aggiornamento inbox nella stessa transazione;
- compatibilità tra almeno due versioni consecutive;
- rifiuto in dead letter dei messaggi non decodificabili;
- nessun ordinamento globale presunto;
- versione entità per eventi fuori ordine;
- metriche su backlog, lag, rifiuti e duplicati.

La transactional outbox HAPA conserva l’intenzione applicativa. Il relay RabbitMQ può ritentare esclusivamente la consegna al broker; non esegue logica provider.

## Sicurezza delle integrazioni provider

Le seguenti responsabilità appartengono a `hapa-automation`:

- allowlist host e protezione SSRF;
- TLS e autenticazione provider;
- timeout e limiti risposta;
- retry HTTP e rate limit;
- webhook, firma e anti-replay;
- cursori e watermark;
- dead letter e riconciliazione;
- redazione dei payload tecnici.

HAPA definisce i dati e gli esiti applicativi ammessi, ma non contiene client HTTP concreti per Space, marketplace, GLS o BRT.

## File, label e documenti

- filename esterni non diventano path locali;
- storage tramite identificativi interni;
- verifica di content type e dimensione;
- accesso autorizzato e auditato;
- download con header sicuri;
- retention e cancellazione definite;
- file temporanei con permessi restrittivi;
- eventuale scansione antimalware.

`hapa-automation` acquisisce la label dal provider; HAPA conserva soltanto il riferimento o il contenuto secondo il contratto e la retention approvati.

## Produzione HAPA

La configurazione production impone:

- `APP_DEBUG=false`;
- `APP_URL` HTTPS;
- trusted proxy espliciti;
- secret PostgreSQL, Redis e RabbitMQ robusti;
- filesystem applicativo read-only;
- processi non privilegiati;
- capability Linux ridotte;
- reti interne per database e cache;
- rete esterna condivisa esclusivamente per AMQP;
- liveness e readiness separate;
- backup e restore verificati;
- log strutturati con redazione;
- nessuna credenziale provider nei container HAPA.

Il Compose HAPA non avvia RabbitMQ, scheduler o worker provider. Il broker appartiene allo stack `hapa-automation`; soltanto il servizio one-shot `outbox-relay` entra nella rete esterna `hapa-messaging`. PostgreSQL e Redis non sono collegati a tale rete.

## Logging, audit e osservabilità

Log tecnici:

- JSON strutturato;
- correlation ID;
- nessun segreto o dato personale non necessario;
- messaggi tecnici limitati in produzione.

Audit HAPA:

- attore;
- azione;
- entità;
- stato precedente e successivo;
- correlation ID;
- timestamp.

L’osservabilità end-to-end deve correlare HAPA, RabbitMQ e `hapa-automation` senza centralizzare payload sensibili. Prima dell’attivazione servono metriche almeno su record pending/retry/dead, età del record più vecchio e latenza di conferma AMQP.

## Incident response

Prima dell’esercizio servono runbook per:

- compromissione account utente;
- leak o rotazione secret;
- messaggi RabbitMQ anomali;
- indisponibilità del broker;
- backlog outbox o consumer;
- provider compromesso o indisponibile;
- duplicati e divergenze di stato;
- restore PostgreSQL;
- disabilitazione rapida del relay, di un account-canale o di un adapter.

La risposta distingue incidente applicativo HAPA da incidente tecnico `hapa-automation`, mantenendo escalation e ownership esplicite.
