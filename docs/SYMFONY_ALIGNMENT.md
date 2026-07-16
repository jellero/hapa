# Allineamento architetturale con Symfony

Ultimo riesame: 16 luglio 2026.

## 1. Scopo

HAPA usa componenti Symfony selezionati come primitive infrastrutturali del proprio framework custom. Questo documento riguarda esclusivamente il repository HAPA.

Worker provider, scheduler, retry, dead letter, cursori e adapter asincroni appartengono a `jellero/hapa-automation` e seguono scelte tecniche autonome, purché rispettino i contratti RabbitMQ condivisi.

## 2. Stato sintetico HAPA

Già allineato:

- front controller e ciclo HTTP esplicito;
- dependency injection tramite costruttore;
- container Symfony compilato;
- configurazione tipizzata;
- dominio separato dal delivery HTTP;
- error handling centralizzato;
- correlation ID e logging strutturato;
- health check;
- PostgreSQL autorevole;
- transactional outbox;
- test unitari, integrazione e smoke production.

Da completare:

- autenticazione e autorizzazione;
- CSRF e sessioni;
- validazione strutturata dei form e dei messaggi RabbitMQ;
- relay e consumer RabbitMQ;
- inbox idempotente;
- rate limiting applicativo;
- test di contratto con `hapa-automation`.

## 3. Componenti Symfony in HAPA

### Adottati

- `symfony/dependency-injection`;
- `symfony/http-foundation`;
- `symfony/routing`;
- `symfony/console`;
- `symfony/dotenv`.

### Da adottare quando richiesti dalla vertical slice

- `symfony/validator` per request, form, comandi e messaggi RabbitMQ;
- `symfony/serializer` per mapping controllato degli envelope;
- `symfony/clock` quando sostituisce l’interfaccia Clock proprietaria senza perdere determinismo;
- componenti Symfony Security necessari a password, sessione, autenticazione e autorizzazione;
- `symfony/security-csrf`;
- `symfony/rate-limiter` per login e azioni applicative;
- `symfony/lock` soltanto per sezioni critiche del processo HAPA.

### Non richiesti come vincolo

- FrameworkBundle;
- Doctrine ORM;
- bundle per organizzare il dominio;
- service locator;
- controller con logica di business;
- Messenger come unica copia durevole degli eventi;
- Scheduler Symfony nel repository HAPA.

## 4. Composition root

Il composition root HAPA deve:

- registrare servizi privati per impostazione predefinita;
- esporre soltanto entry point necessari;
- usare constructor injection;
- dichiarare alias tra porte e implementazioni;
- compilare e validare il container;
- confinare lettura di ambiente e secret;
- non registrare worker o adapter provider;
- non importare classi da `hapa-automation`.

Entità, value object e regole di dominio restano normali oggetti PHP e non ricevono il container.

## 5. Configurazione

La distinzione è:

- ambiente e secret per valori infrastrutturali;
- oggetti immutabili tipizzati per configurazione HAPA;
- costanti per invarianti;
- feature flag espliciti per capacità applicative.

Le credenziali RabbitMQ HAPA possono essere aggiunte quando viene implementato il relay/consumer. Le credenziali Space, marketplace, GLS e BRT non appartengono alla configurazione HAPA.

## 6. Validazione e mapping

Symfony Validator può essere usato per:

- request e form;
- comandi applicativi;
- configurazioni;
- envelope RabbitMQ;
- payload di eventi e risultati ricevuti.

Le invarianti restano nel dominio e nei vincoli PostgreSQL.

Il mapping RabbitMQ segue:

```text
JSON envelope
  -> verifica dimensione e schema
  -> MessageEnvelope tipizzato
  -> payload versione-specifico
  -> comando applicativo HAPA
  -> dominio e repository
```

Campi critici mancanti producono un rifiuto tipizzato. Il consumer registra il `message_id` e applica il caso d’uso nella stessa transazione.

## 7. Sicurezza del pannello

Il pannello richiede:

- sessione autenticata;
- password hashing aggiornabile;
- reset password sicuro;
- cookie `Secure`, `HttpOnly` e `SameSite`;
- rotazione e revoca sessione;
- MFA per ruoli sensibili;
- CSRF su login e mutazioni;
- throttling;
- autorizzazione deny-by-default;
- voter o policy per risorsa e azione;
- audit delle operazioni.

La UI HAPA non espone la gestione tecnica delle dead letter provider. Un’eventuale vista di stato usa un contratto in sola lettura, redatto e autorizzato.

## 8. Transactional outbox

La tabella outbox PostgreSQL HAPA è la sorgente durevole delle intenzioni applicative.

Un evento di dominio e il record outbox vengono confermati nella stessa transazione. Il futuro relay:

1. reclama un batch;
2. costruisce l’envelope RabbitMQ;
3. pubblica con publisher confirm;
4. registra la consegna al broker;
5. ritenta soltanto errori temporanei del broker.

Il relay non esegue adapter provider, non applica rate limit provider e non contiene riconciliazione esterna.

Messenger può essere valutato come transport o lifecycle del relay/consumer, ma non sostituisce outbox e inbox PostgreSQL.

## 9. Consumer RabbitMQ

Il consumer HAPA deve:

- usare un account RabbitMQ con ACL minime;
- validare routing key, envelope e schema;
- deduplicare per `message_id`;
- gestire due versioni consecutive;
- applicare aggiornamenti fuori ordine tramite versione entità;
- distinguere errore temporaneo, definitivo e messaggio incompatibile;
- evitare log di payload personali;
- esporre metriche di lag, duplicati e rifiuti;
- chiudersi correttamente su `SIGTERM` se eseguito come processo separato.

Il processo consumer può appartenere all’immagine HAPA ma deve essere un entry point separato dal runtime HTTP. Non diventa un worker provider: applica esclusivamente casi d’uso HAPA a messaggi già normalizzati.

## 10. Client HTTP

HAPA usa client HTTP soltanto per capacità realmente di propria competenza. Gli adapter Space, marketplace, GLS e BRT sono eseguiti da `hapa-automation`.

Qualunque client HAPA futuro deve comunque applicare:

- base URI e allowlist;
- TLS;
- timeout;
- limite redirect e dimensione risposta;
- correlation ID;
- redazione dei dati sensibili;
- retry soltanto per operazioni idempotenti.

## 11. Cache e Redis

Redis resta supporto temporaneo per HAPA. Ogni uso deve definire:

- namespace;
- TTL;
- invalidazione;
- comportamento in caso di indisponibilità;
- protezione da stampede;
- divieto di conservare l’unica copia di dati autorevoli.

Redis non coordina scheduler o adapter del repository esterno.

## 12. Testing

La strategia HAPA comprende:

- unit test di dominio;
- integration test PostgreSQL e Redis;
- test del container;
- test HTTP e sicurezza header;
- test outbox transazionale;
- futuri test inbox e consumer;
- contract test producer/consumer con `hapa-automation`;
- smoke test production.

Per ogni contratto RabbitMQ servono fixture versionate e test su:

- messaggio valido;
- duplicato;
- versione precedente supportata;
- versione incompatibile;
- campi mancanti;
- messaggio fuori ordine;
- rollback del caso d’uso;
- dati personali minimizzati.

## 13. Decisioni correnti

| Area | Decisione HAPA |
|---|---|
| framework | custom PHP con componenti Symfony selezionati |
| DI | container compilato e constructor injection |
| persistenza | PostgreSQL esplicito, senza ORM obbligatorio |
| dominio | indipendente da HTTP, RabbitMQ e provider |
| asincronia | transactional outbox + RabbitMQ + inbox |
| provider | esecuzione in `hapa-automation` |
| sicurezza | componenti Symfony adottati progressivamente |
| scheduler | assente da HAPA |
| retry provider | assente da HAPA |
| UI tecnica automazioni | assente da HAPA |

## 14. Sequenza consigliata

1. congelare i contratti messaggi;
2. aggiungere test condivisi;
3. implementare inbox e consumer HAPA;
4. implementare relay outbox;
5. completare autenticazione e autorizzazione;
6. collegare prodotto e ricarichi;
7. verificare una vertical slice con fake adapter;
8. abilitare un provider sandbox nel servizio esterno;
9. introdurre osservabilità end-to-end;
10. abilitare gradualmente il traffico reale.
